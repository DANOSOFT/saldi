<?php
// --- includes/docsIncludes/invoiceExtractionApi.php -----
// Helper functions for invoice extraction API integration

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
 * Extract invoice data from a PDF or image file using the external API.
 *
 * @param string $filePath Full path to the PDF or image file (jpg, jpeg, png)
 * @param string $invoiceId Unique ID for the invoice (e.g., "invoice-001")
 * @return array|null Returns SALDI invoice fields on success, null on failure
 */
function extractInvoiceData($filePath, $invoiceId = null) {
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

	$fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
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
