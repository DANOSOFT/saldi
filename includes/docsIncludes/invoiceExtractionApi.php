<?php
// --- includes/docsIncludes/invoiceExtractionApi.php -----
// Helper functions for invoice extraction API integration
// ----------------------------------------------------------------------
// 
// CONFIGURATION:
// To enable invoice extraction, add the API URL to the settings table:
// 
// INSERT INTO settings (var_name, var_grp, var_value) 
// VALUES ('invoiceExtractionApiUrl', 'api', 'https://your-api-endpoint.com/extract');
// 
// If the API URL is not configured, the function will return null and uploads
// will proceed without extraction (graceful degradation).
// ----------------------------------------------------------------------

/**
 * Extract invoice data from PDF or image file using external API
 * 
 * @param string $filePath Full path to the PDF or image file (jpg, jpeg, png)
 * @param string $invoiceId Unique ID for the invoice (e.g., "invoice-001")
 * @return array|null Returns array with 'amount' and 'date' on success, null on failure
 */
function extractInvoiceData($filePath, $invoiceId = null) {
	// Get API URL from settings or use default
	global $db;
	$apiUrl = "http://72.62.59.20:5000/extract-invoice";
	$apiKey = "change-me-in-production";
	
	// Try to get API URL from settings
	/* $qtxt = "SELECT var_value FROM settings WHERE var_name = 'invoiceExtractionApiUrl' AND var_grp = 'api'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$apiUrl = $r['var_value'];
	} */
	
	// If not configured, return null (API call disabled)
	if (empty($apiUrl)) {
		error_log("Invoice extraction API URL not configured in settings");
		return null;
	}
	
	// Generate invoice ID if not provided
	if (empty($invoiceId)) {
		$invoiceId = 'invoice-' . time() . '-' . rand(1000, 9999);
	}
	
	// Check if file exists
	if (!file_exists($filePath)) {
		error_log("File not found: $filePath");
		return null;
	}
	
	// Detect file type
	$fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
	$allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'];
	
	if (!in_array($fileExt, $allowedTypes)) {
		error_log("Unsupported file type for invoice extraction: $fileExt (file: $filePath)");
		return null;
	}
	
	// Read file content
	$fileContent = file_get_contents($filePath);
	if ($fileContent === false) {
		error_log("Failed to read file: $filePath");
		return null;
	}
	
	// Verify file is not empty
	if (strlen($fileContent) === 0) {
		error_log("File is empty: $filePath");
		return null;
	}
	
	// For images, verify it's a valid image file using getimagesize
	if (in_array($fileExt, ['jpg', 'jpeg', 'png'])) {
		$imageInfo = @getimagesize($filePath);
		if ($imageInfo === false) {
			error_log("File is not a valid image: $filePath");
			return null;
		}
	}
	
	// Base64 encode the raw file content
	$base64Image = base64_encode($fileContent);
	
	// Prepare API request data
	$requestData = array(
		'id' => $invoiceId,
		'image' => $base64Image,
		'skip_classification' => true
	);
	
	// Make API call using cURL
	$ch = curl_init($apiUrl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json',
		'Accept: application/json',
		'Authorization: Bearer ' . $apiKey
	));
	curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 second connection timeout
	
	// Execute request
	$response = curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curlError = curl_error($ch);
	curl_close($ch);
	print_r($response);
	exit;

	// Check for cURL errors
	if ($curlError) {
		error_log("cURL error calling invoice extraction API: $curlError");
		return null;
	}
	
	// Check HTTP status code
	if ($httpCode < 200 || $httpCode >= 300) {
		error_log("Invoice extraction API returned HTTP $httpCode. Response: " . substr($response, 0, 500));
		return null;
	}
	
	// Parse JSON response
	$responseData = json_decode($response, true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		error_log("Failed to parse JSON response from invoice extraction API: " . json_last_error_msg());
		return null;
	}
	
	// Check if response indicates success
	if (isset($responseData['status']) && $responseData['status'] !== 'success') {
		error_log("Invoice extraction API returned non-success status: " . ($responseData['status'] ?? 'unknown'));
		return null;
	}
	
	// Extract amount and date from response
	$amount = null;
	$date = null;
	
	if (isset($responseData['extracted_data'])) {
		$extractedData = $responseData['extracted_data'];
		
		// Get total_amount
		if (isset($extractedData['total_amount'])) {
			$amount = $extractedData['total_amount'];
		}
		
		// Get invoice_date
		if (isset($extractedData['invoice_date'])) {
			$date = $extractedData['invoice_date'];
			// Convert date format if needed (API returns YYYY-MM-DD, convert to DD-MM-YYYY for .info file)
			if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
				$date = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
			}
		}
	}
	
	// Return extracted data
	if ($amount !== null || $date !== null) {
		return array(
			'amount' => $amount,
			'date' => $date
		);
	}
	
	// No data extracted
	error_log("Invoice extraction API returned no extractable data");
	return null;
}

