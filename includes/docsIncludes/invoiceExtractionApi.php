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
	
/* 	// include connect to get master DB
	include "connect.php";

	$qtxt = "SELECT var_value FROM settings WHERE var_name = 'apikey' AND var_grp = 'app_api'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$apiKey = $r['var_value'];
	}

	// include online.php to get user DB
	include "online.php"; */
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
	
	// Track temporary file for cleanup
	$tempImagePath = null;
	$fileToProcess = $filePath;
	
	// If PDF, convert to image first
	if ($fileExt === 'pdf') {
		$tempImagePath = sys_get_temp_dir() . '/invoice_' . uniqid() . '.png';
		
		// Try using Imagick PHP extension first
		if (class_exists('Imagick')) {
			try {
				$imagick = new Imagick();
				$imagick->setResolution(150, 150); // Set resolution before reading
				$imagick->readImage($filePath . '[0]'); // Read first page only
				$imagick->setImageFormat('png');
				$imagick->setImageCompressionQuality(90);
				$imagick->writeImage($tempImagePath);
				$imagick->clear();
				$imagick->destroy();
				$fileToProcess = $tempImagePath;
			} catch (Exception $e) {
				error_log("Imagick PDF conversion failed: " . $e->getMessage());
				// Fall back to command line
				$tempImagePath = null;
			}
		}
		
		// Fall back to ImageMagick command line if Imagick extension not available or failed
		if ($tempImagePath === null || !file_exists($tempImagePath)) {
			$tempImagePath = sys_get_temp_dir() . '/invoice_' . uniqid() . '.png';
			$escapedInput = escapeshellarg($filePath . '[0]');
			$escapedOutput = escapeshellarg($tempImagePath);
			$command = "convert -density 150 $escapedInput -quality 90 $escapedOutput 2>&1";
			exec($command, $output, $returnCode);
			
			if ($returnCode !== 0 || !file_exists($tempImagePath)) {
				error_log("ImageMagick PDF conversion failed. Return code: $returnCode, Output: " . implode("\n", $output));
				if ($tempImagePath && file_exists($tempImagePath)) {
					@unlink($tempImagePath);
				}
				return null;
			}
			$fileToProcess = $tempImagePath;
		}
	}
	
	// Read file content
	$fileContent = file_get_contents($fileToProcess);
	if ($fileContent === false) {
		error_log("Failed to read file: $fileToProcess");
		if ($tempImagePath && file_exists($tempImagePath)) {
			@unlink($tempImagePath);
		}
		return null;
	}
	
	// Verify file is not empty
	if (strlen($fileContent) === 0) {
		error_log("File is empty: $fileToProcess");
		if ($tempImagePath && file_exists($tempImagePath)) {
			@unlink($tempImagePath);
		}
		return null;
	}
	
	// For images (including converted PDFs), verify it's a valid image file using getimagesize
	if ($fileExt !== 'pdf' || $tempImagePath !== null) {
		$imageInfo = @getimagesize($fileToProcess);
		if ($imageInfo === false) {
			error_log("File is not a valid image: $fileToProcess");
			if ($tempImagePath && file_exists($tempImagePath)) {
				@unlink($tempImagePath);
			}
			return null;
		}
	}
	
	// Base64 encode the image content
	$base64Image = base64_encode($fileContent);
	
	// Clean up temporary file now that we have the content
	if ($tempImagePath && file_exists($tempImagePath)) {
		@unlink($tempImagePath);
	}
	
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
	$vendor = null;
	if (isset($responseData['extracted_data'])) {
		$extractedData = $responseData['extracted_data'];
		
		// Get total_amount
		if (isset($extractedData['total_amount'])) {
			$amount = $extractedData['total_amount'];
		}

		// Get invoice_number
		if (isset($extractedData['invoice_number'])) {
			$invoiceNumber = $extractedData['invoice_number'];
		}	

		// Get invoice_description
		if (isset($extractedData['invoice_description'])) {
			$description = $extractedData['invoice_description'];
		}	
		
		// Get invoice_date
		if (isset($extractedData['invoice_date'])) {
			$rawDate = $extractedData['invoice_date'];
			$date = null;
			
			// Try to convert date to YYYY-MM-DD format
			// Handle DD-MM-YY format first
			if (preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{2})$/', $rawDate, $matches)) {
				$day = $matches[1];
				$month = $matches[2];
				$year2digit = $matches[3];
				$year4digit = '20' . $year2digit;
				$date = $year4digit . '-' . $month . '-' . $day;
			}
			// Handle DD-MM-YYYY format
			elseif (preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{4})$/', $rawDate, $matches)) {
				$day = $matches[1];
				$month = $matches[2];
				$year = $matches[3];
				$date = $year . '-' . $month . '-' . $day;
			}
			// Use strtotime for other formats (handles "January 26, 2026", "2026-01-26", etc.)
			else {
				$timestamp = strtotime($rawDate);
				if ($timestamp !== false && $timestamp > 0) {
					$date = date('Y-m-d', $timestamp);
				} else {
					$date = $rawDate; // Keep original if parsing fails
				}
			}
		}
		
		// Get vendor
		if (isset($extractedData['vendor'])) {
			$vendor = $extractedData['vendor'];
		}
	}
	
	// Return extracted data
	if ($amount !== null || $date !== null) {
		return array(
			'amount' => $amount,
			'date' => $date,
			'vendor' => $vendor,
			'invoiceNumber' => $invoiceNumber,
			'description' => $description
		);
	}
	
	// No data extracted
	return null;
}

