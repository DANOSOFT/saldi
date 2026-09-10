<?php
// --- includes/docsIncludes/invoiceExtractionApi.php --- patch 5.0.0 --- 2026-08-31 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// Helper functions for invoice extraction API integration
// 20260831 CX/PHR Added local OIOUBL/Peppol XML invoice extraction

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
 *   currency: string|null
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
			if ($value !== '' && !in_array($value, $descriptionValues, true)) $descriptionValues[] = $value;
		}
	}
	if (empty($descriptionValues)) {
		$note = $getValue('/*/*[local-name()="Note"][1]');
		if ($note !== null) $descriptionValues[] = $note;
	}
	$description = !empty($descriptionValues) ? implode('; ', $descriptionValues) : null;

	if ($amount === null && $date === null && $vendor === null && $invoiceNumber === null && $description === null && $currency === null) {
		return null;
	}

	return array(
		'amount' => $amount,
		'date' => $date,
		'vendor' => $vendor,
		'invoiceNumber' => $invoiceNumber,
		'description' => $description,
		'currency' => $currency
	);
}

/**
 * Extract invoice data locally from UBL XML or through the external API for PDF/images.
 *
 * @param string $filePath Full path to an XML, PDF, or image file (jpg, jpeg, png).
 * @param string $invoiceId Unique ID for the invoice (e.g., "invoice-001")
 * @return array|null Returns SALDI invoice fields on success, null on failure
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
	$transportResult = call_user_func($transport, 'https://ai.saldi.dk/extract-invoice', $headers, $requestBody, $options);

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
	}

	if ($amount !== null || $date !== null || $vendor !== null || $invoiceNumber !== null || $description !== null || $currency !== null) {
		return array(
			'amount' => $amount,
			'date' => $date,
			'vendor' => $vendor,
			'invoiceNumber' => $invoiceNumber,
			'description' => $description,
			'currency' => $currency
		);
	}

	return null;
}
?>
