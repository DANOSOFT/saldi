<?php
// --- includes/docsIncludes/invoiceExtractionApi.php -----
// Helper functions for invoice extraction API integration

// 20260910 CDX/PHR Enable local UBL XML invoice upload and extraction.
// 20260922 CL/LAH Leverandørforslag fra AI-scan: return the seller's CVR/VAT number, IBAN and
//                  reg.nr./kontonr. plus the buyer's CVR (vendorCvr, vendorIban, vendorBankReg,
//                  vendorBankKonto, customerCvr) from both the extract-invoice API and UBL XML,
//                  so extractInvoiceHandler.php can match the vendor against kreditorer.
// 20260922 CL/LAH The extraction service moved from ai.saldi.dk to https://wuweiworkai.com
//                  (same container, same key). The URL is now read from settings
//                  (var_grp 'app_api', var_name 'extract_url', global db) with that host as the
//                  default, so the next move is a settings row instead of a code change.
// 20260922 CL/LAH UBL line names that are only punctuation (".") no longer end up in description.
function invoiceExtractionApiResolveApiKey() {
	if (!function_exists('db_select') || !function_exists('db_fetch_array')) {
		error_log("Invoice extraction API key lookup is unavailable");
		return null;
	}

	$qtxt = "SELECT var_value FROM settings WHERE var_name = 'apikey' AND var_grp = 'app_api'";
	$query = db_select($qtxt, __FILE__ . " linje " . __LINE__, true);
	if (!$query || !($row = db_fetch_array($query))) return null;

	$apiKey = trim($row['var_value'] ?? '');
	return $apiKey !== '' ? $apiKey : null;
}

/**
 * Endpoint of the extract-invoice service. Overridable per install through the global
 * settings row var_grp='app_api', var_name='extract_url' (next to the 'apikey' row).
 *
 * @return string
 */
function invoiceExtractionApiResolveUrl() {
	$default = 'https://wuweiworkai.com/extract-invoice';
	if (!function_exists('db_select') || !function_exists('db_fetch_array')) return $default;

	$qtxt = "SELECT var_value FROM settings WHERE var_name = 'extract_url' AND var_grp = 'app_api'";
	$query = db_select($qtxt, __FILE__ . " linje " . __LINE__, true);
	if (!$query || !($row = db_fetch_array($query))) return $default;

	$url = trim($row['var_value'] ?? '');
	return preg_match('#^https?://#i', $url) ? $url : $default;
}

function invoiceExtractionApiCurlTransport($apiUrl, $headers, $body, $options) {
	if (!function_exists('curl_init')) {
		return array('response' => false, 'http_code' => 0, 'error' => 'cURL is not available', 'errno' => 0);
	}

	$ch = curl_init($apiUrl);
	if (!$ch) {
		return array('response' => false, 'http_code' => 0, 'error' => 'Failed to initialize cURL', 'errno' => 0);
	}

	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => $body,
		CURLOPT_HTTPHEADER => $headers,
		CURLOPT_CONNECTTIMEOUT => $options['connect_timeout'],
		CURLOPT_TIMEOUT => $options['timeout']
	));

	$response = curl_exec($ch);
	$result = array(
		'response' => $response,
		'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
		'error' => curl_error($ch),
		'errno' => curl_errno($ch)
	);
	curl_close($ch);

	return $result;
}

function invoiceExtractionApiDependencies() {
	global $invoiceExtractionApiDependencies;
	return is_array($invoiceExtractionApiDependencies ?? null) ? $invoiceExtractionApiDependencies : array();
}

/**
 * Extract standard invoice fields from an OIOUBL or Peppol UBL XML document.
 *
 * @param string $filePath Full path to the XML document.
 * @return array{
 *   amount: string|null,
 *   date: string|null,
 *   vendor: string|null,
 *   invoiceNumber: string|null,
 *   description: string|null,
 *   currency: string|null,
 *   vendorCvr: string|null,        Seller's CompanyID (PartyTaxScheme, else PartyLegalEntity), as written.
 *   vendorIban: string|null,       PayeeFinancialAccount/ID when it is an IBAN.
 *   vendorBankReg: string|null,    FinancialInstitutionBranch/ID for a Danish reg.nr./kontonr. account.
 *   vendorBankKonto: string|null,  PayeeFinancialAccount/ID when it is not an IBAN.
 *   customerCvr: string|null       Buyer's CompanyID, so the caller can tell the two apart.
 * }|null SALDI invoice fields, or null when the XML is not a supported UBL invoice.
 */
function extractUblInvoiceData($filePath) {
	$xmlContent = file_get_contents($filePath);
	if ($xmlContent === false || trim($xmlContent) === '') {
		error_log("UBL invoice XML is empty or unreadable: $filePath");
		return null;
	}
	if (stripos($xmlContent, '<!DOCTYPE') !== false || stripos($xmlContent, '<!ENTITY') !== false) {
		error_log("UBL invoice XML contains a prohibited document type or entity declaration: $filePath");
		return null;
	}

	$previousUseInternalErrors = libxml_use_internal_errors(true);
	$document = new DOMDocument();
	$loaded = $document->loadXML($xmlContent, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
	libxml_clear_errors();
	libxml_use_internal_errors($previousUseInternalErrors);
	if (!$loaded || !$document->documentElement) {
		error_log("Failed to parse UBL invoice XML: $filePath");
		return null;
	}

	$documentType = $document->documentElement->localName;
	if (!in_array($documentType, array('Invoice', 'CreditNote'), true)) {
		error_log("Unsupported UBL XML document type: $documentType (file: $filePath)");
		return null;
	}

	$xpath = new DOMXPath($document);
	$getValue = function ($expression) use ($xpath) {
		$nodes = $xpath->query($expression);
		if (!$nodes || $nodes->length === 0) return null;
		$value = trim($nodes->item(0)->textContent);
		return $value !== '' ? $value : null;
	};

	$invoiceNumber = $getValue('/*[local-name()="Invoice" or local-name()="CreditNote"]/*[local-name()="ID"][1]');
	$date = $getValue('/*[local-name()="Invoice" or local-name()="CreditNote"]/*[local-name()="IssueDate"][1]');
	$vendor = $getValue('/*/*[local-name()="AccountingSupplierParty"]//*[local-name()="PartyName"]/*[local-name()="Name"][1]');
	if ($vendor === null) {
		$vendor = $getValue('/*/*[local-name()="AccountingSupplierParty"]//*[local-name()="PartyLegalEntity"]/*[local-name()="RegistrationName"][1]');
	}

	$amountNodes = $xpath->query('/*/*[local-name()="LegalMonetaryTotal"]/*[local-name()="PayableAmount"][1]');
	$amount = null;
	$currency = null;
	if ($amountNodes && $amountNodes->length > 0) {
		$amountNode = $amountNodes->item(0);
		$amount = trim($amountNode->textContent);
		if ($amount === '') $amount = null;
		$currency = trim($amountNode->getAttribute('currencyID'));
		if ($currency === '') $currency = null;
	}
	if ($currency === null) {
		$currency = $getValue('/*/*[local-name()="DocumentCurrencyCode"][1]');
	}

	$descriptionValues = array();
	$descriptionNodes = $xpath->query('/*/*[local-name()="InvoiceLine" or local-name()="CreditNoteLine"]/*[local-name()="Item"]/*[local-name()="Name" or local-name()="Description"]');
	if ($descriptionNodes) {
		foreach ($descriptionNodes as $descriptionNode) {
			$value = trim($descriptionNode->textContent);
			// Placeholder lines ("." / "-") carry no text worth showing; some ERPs emit one per
			// empty invoice line (seen in an OIOUBL invoice from Gregershus ApS, 2026-09-22).
			if ($value === '' || !preg_match('/[\p{L}\p{N}]/u', $value)) continue;
			if (!in_array($value, $descriptionValues, true)) $descriptionValues[] = $value;
		}
	}
	if (empty($descriptionValues)) {
		$note = $getValue('/*/*[local-name()="Note"][1]');
		if ($note !== null) $descriptionValues[] = $note;
	}
	$description = !empty($descriptionValues) ? implode('; ', $descriptionValues) : null;

	// Party identity: CVR/VAT numbers sit in PartyTaxScheme/CompanyID (with the DK prefix)
	// or PartyLegalEntity/CompanyID; the seller's account in PaymentMeans.
	$partyCompanyId = function ($party) use ($getValue) {
		$value = $getValue('/*/*[local-name()="' . $party . '"]//*[local-name()="PartyTaxScheme"]/*[local-name()="CompanyID"][1]');
		if ($value === null) {
			$value = $getValue('/*/*[local-name()="' . $party . '"]//*[local-name()="PartyLegalEntity"]/*[local-name()="CompanyID"][1]');
		}
		return $value;
	};
	$vendorCvr = $partyCompanyId('AccountingSupplierParty');
	$customerCvr = $partyCompanyId('AccountingCustomerParty');
	$vendorIban = null;
	$vendorBankReg = null;
	$vendorBankKonto = null;
	$accountNodes = $xpath->query('/*/*[local-name()="PaymentMeans"]/*[local-name()="PayeeFinancialAccount"]/*[local-name()="ID"][1]');
	if ($accountNodes && $accountNodes->length > 0) {
		$accountNode = $accountNodes->item(0);
		$accountId = preg_replace('/\s+/', '', trim($accountNode->textContent));
		$scheme = strtoupper(trim($accountNode->getAttribute('schemeID')));
		if ($accountId !== '') {
			if ($scheme === 'IBAN' || preg_match('/^[A-Za-z]{2}\d{2}[A-Za-z0-9]{11,30}$/', $accountId)) {
				$vendorIban = strtoupper($accountId);
			} else {
				$vendorBankKonto = $accountId;
				$vendorBankReg = $getValue('/*/*[local-name()="PaymentMeans"]/*[local-name()="PayeeFinancialAccount"]/*[local-name()="FinancialInstitutionBranch"]/*[local-name()="ID"][1]');
			}
		}
	}

	if ($amount === null && $date === null && $vendor === null && $invoiceNumber === null && $description === null && $currency === null) {
		return null;
	}

	return array(
		'amount' => $amount,
		'date' => $date,
		'vendor' => $vendor,
		'invoiceNumber' => $invoiceNumber,
		'description' => $description,
		'currency' => $currency,
		'vendorCvr' => $vendorCvr,
		'vendorIban' => $vendorIban,
		'vendorBankReg' => $vendorBankReg,
		'vendorBankKonto' => $vendorBankKonto,
		'customerCvr' => $customerCvr
	);
}

/**
 * Extract invoice data locally from UBL XML or through the external API for PDF/images.
 *
 * @param string $filePath Full path to an XML, PDF, or image file (jpg, jpeg, png).
 * @param string $invoiceId Unique ID for the invoice (e.g., "invoice-001")
 * @return array{
 *   amount: string|null,
 *   date: string|null,
 *   vendor: string|null,           Seller's name as printed on the invoice.
 *   invoiceNumber: string|null,
 *   description: string|null,
 *   currency: string|null,
 *   vendorCvr: string|null,        Seller's CVR/VAT number as printed (not normalized).
 *   vendorIban: string|null,       Seller's IBAN as printed.
 *   vendorBankReg: string|null,    Seller's Danish reg.nr., if printed.
 *   vendorBankKonto: string|null,  Seller's Danish kontonr., if printed.
 *   customerCvr: string|null       Buyer's CVR/VAT number as printed, or null.
 * }|null SALDI invoice fields on success, null on failure.
 */
function extractInvoiceData($filePath, $invoiceId = null) {
	$fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
	if ($fileExt === 'xml') return extractUblInvoiceData($filePath);

	$dependencies = invoiceExtractionApiDependencies();
	$keyResolver = isset($dependencies['key_resolver']) && is_callable($dependencies['key_resolver'])
		? $dependencies['key_resolver']
		: 'invoiceExtractionApiResolveApiKey';
	$transport = isset($dependencies['transport']) && is_callable($dependencies['transport'])
		? $dependencies['transport']
		: 'invoiceExtractionApiCurlTransport';
	$urlResolver = isset($dependencies['url_resolver']) && is_callable($dependencies['url_resolver'])
		? $dependencies['url_resolver']
		: 'invoiceExtractionApiResolveUrl';

	if (!file_exists($filePath)) {
		error_log("File not found: $filePath");
		return null;
	}

	$allowedTypes = array('pdf', 'jpg', 'jpeg', 'png');
	if (!in_array($fileExt, $allowedTypes)) {
		error_log("Unsupported file type for invoice extraction: $fileExt (file: $filePath)");
		return null;
	}

	$fileContent = file_get_contents($filePath);
	if ($fileContent === false) {
		error_log("Failed to read file: $filePath");
		return null;
	}
	if (strlen($fileContent) === 0) {
		error_log("File is empty: $filePath");
		return null;
	}

	// PDFs are sent unchanged so the extraction service can inspect every page.
	if ($fileExt !== 'pdf' && @getimagesize($filePath) === false) {
		error_log("File is not a valid image: $filePath");
		return null;
	}

	$apiKey = call_user_func($keyResolver);
	if (empty($apiKey)) {
		error_log("Invoice extraction API key is not configured");
		return null;
	}

	if (empty($invoiceId)) $invoiceId = 'invoice-' . time() . '-' . rand(1000, 9999);

	$requestData = array(
		'id' => $invoiceId,
		'image' => base64_encode($fileContent),
		'skip_classification' => true
	);
	$requestBody = json_encode($requestData);
	if ($requestBody === false) {
		error_log("Failed to encode invoice extraction API request");
		return null;
	}

	$headers = array(
		'Content-Type: application/json',
		'Accept: application/json',
		'Authorization: Bearer ' . $apiKey
	);
	$options = array('connect_timeout' => 10, 'timeout' => 120);
	$transportResult = call_user_func($transport, call_user_func($urlResolver), $headers, $requestBody, $options);

	if (!is_array($transportResult)) {
		error_log("Invoice extraction API transport returned an invalid result");
		return null;
	}

	$response = $transportResult['response'] ?? false;
	$httpCode = (int) ($transportResult['http_code'] ?? 0);
	$curlError = $transportResult['error'] ?? '';
	$curlErrorNo = (int) ($transportResult['errno'] ?? 0);
	if ($curlError !== '') {
		if ($curlErrorNo === 28) error_log("Invoice extraction API request timed out: $curlError");
		else error_log("cURL error calling invoice extraction API: $curlError");
		return null;
	}

	if ($httpCode < 200 || $httpCode >= 300) {
		error_log("Invoice extraction API returned HTTP $httpCode. Response: " . substr((string) $response, 0, 500));
		return null;
	}

	$responseData = json_decode($response, true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		error_log("Failed to parse JSON response from invoice extraction API: " . json_last_error_msg());
		return null;
	}

	if (isset($responseData['status']) && !in_array($responseData['status'], array('success', 'partial_success'), true)) {
		error_log("Invoice extraction API returned non-success status: " . $responseData['status']);
		return null;
	}

	$amount = null;
	$date = null;
	$vendor = null;
	$invoiceNumber = null;
	$description = null;
	$currency = null;
	$vendorCvr = null;
	$vendorIban = null;
	$vendorBankReg = null;
	$vendorBankKonto = null;
	$customerCvr = null;
	if (isset($responseData['extracted_data'])) {
		$extractedData = $responseData['extracted_data'];
		if (isset($extractedData['total_amount'])) $amount = $extractedData['total_amount'];
		if (isset($extractedData['invoice_number'])) $invoiceNumber = $extractedData['invoice_number'];
		if (isset($extractedData['invoice_description'])) $description = $extractedData['invoice_description'];

		if (isset($extractedData['invoice_date'])) {
			$rawDate = $extractedData['invoice_date'];
			if (preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{2})$/', $rawDate, $matches)) {
				$date = '20' . $matches[3] . '-' . $matches[2] . '-' . $matches[1];
			} elseif (preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{4})$/', $rawDate, $matches)) {
				$date = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
			} else {
				$timestamp = strtotime($rawDate);
				$date = $timestamp !== false && $timestamp > 0 ? date('Y-m-d', $timestamp) : $rawDate;
			}
		}

		if (isset($extractedData['vendor'])) $vendor = $extractedData['vendor'];
		if (isset($extractedData['currency'])) $currency = $extractedData['currency'];

		// Party identity fields (added to the extract-invoice service 2026-09-22); older
		// service versions simply don't return them and every value stays null.
		$identityField = function ($key) use ($extractedData) {
			if (!isset($extractedData[$key]) || is_array($extractedData[$key])) return null;
			$value = trim((string) $extractedData[$key]);
			return ($value === '' || in_array(strtolower($value), array('null', 'none', 'n/a', 'unknown'), true)) ? null : $value;
		};
		$vendorCvr = $identityField('vendor_vat_number');
		$vendorIban = $identityField('vendor_iban');
		$vendorBankReg = $identityField('vendor_bank_reg');
		$vendorBankKonto = $identityField('vendor_bank_account');
		$customerCvr = $identityField('customer_vat_number');
	}

	if ($amount !== null || $date !== null || $vendor !== null || $invoiceNumber !== null || $description !== null || $currency !== null) {
		return array(
			'amount' => $amount,
			'date' => $date,
			'vendor' => $vendor,
			'invoiceNumber' => $invoiceNumber,
			'description' => $description,
			'currency' => $currency,
			'vendorCvr' => $vendorCvr,
			'vendorIban' => $vendorIban,
			'vendorBankReg' => $vendorBankReg,
			'vendorBankKonto' => $vendorBankKonto,
			'customerCvr' => $customerCvr
		);
	}

	return null;
}
?>
