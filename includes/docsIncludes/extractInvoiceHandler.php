<?php
// --- includes/docsIncludes/extractInvoiceHandler.php ---
// AJAX handler for invoice extraction from pool files
// ----------------------------------------------------------------------

// Set JSON response header FIRST
header('Content-Type: application/json');

// Start output buffering to capture any unwanted output
ob_start();

// Include database connection
include_once("../connect.php");

// Get database name from POST
$db = isset($_POST['db']) ? $_POST['db'] : '';
if (empty($db)) {
	ob_end_clean();
	echo json_encode(['success' => false, 'error' => 'Database ikke angivet']);
	exit;
}

// Validate db name (only allow alphanumeric and underscore)
if (!preg_match('/^[a-zA-Z0-9_]+$/', $db)) {
	ob_end_clean();
	echo json_encode(['success' => false, 'error' => 'Ugyldig database navn']);
	exit;
}

// Connect to the specific database
if ($db) {
	global $sqhost, $squser, $sqpass;
	// Close previous connection if exists (optional but good practice)
	// pg_close($connection); // db_connect usually handles new connection, but we just overwrite variable
	$connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . " line " . __LINE__);
	
	if (!$connection) {
		ob_end_clean();
		echo json_encode(['success' => false, 'error' => 'Kunne ikke forbinde til database: ' . $db]);
		exit;
	}
}

// Include the extraction API
include_once("invoiceExtractionApi.php");

// Discard any buffered output from includes
ob_end_clean();

/**
 * Normalize date format from various formats to Y-m-d
 * Handles Danish month names like "januar", "februar", etc.
 * Also handles formats like "17.oktober.2025", "17-10-2025", "2025-10-17", etc.
 */
function normalizeDateFormat($dateStr) {
	if (empty($dateStr)) {
		return '';
	}
	
	// Danish month names to numbers
	$danishMonths = [
		'januar' => '01', 'jan' => '01',
		'februar' => '02', 'feb' => '02',
		'marts' => '03', 'mar' => '03',
		'april' => '04', 'apr' => '04',
		'maj' => '05',
		'juni' => '06', 'jun' => '06',
		'juli' => '07', 'jul' => '07',
		'august' => '08', 'aug' => '08',
		'september' => '09', 'sep' => '09', 'sept' => '09',
		'oktober' => '10', 'okt' => '10', 'oct' => '10',
		'november' => '11', 'nov' => '11',
		'december' => '12', 'dec' => '12'
	];
	
	// Clean up the date string
	$dateStr = trim($dateStr);
	$originalDate = $dateStr;
	
	// Convert to lowercase for matching
	$lowerDate = strtolower($dateStr);
	
	// Replace Danish month names with numbers
	foreach ($danishMonths as $monthName => $monthNum) {
		if (stripos($lowerDate, $monthName) !== false) {
			// Found a Danish month name, try to parse
			// Pattern: day.monthname.year or day monthname year
			if (preg_match('/(\d{1,2})[.\s\-]+' . preg_quote($monthName, '/') . '[.\s\-]+(\d{4}|\d{2})/i', $dateStr, $matches)) {
				$day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
				$year = $matches[2];
				// Handle 2-digit year
				if (strlen($year) == 2) {
					$year = ($year > 50 ? '19' : '20') . $year;
				}
				return $year . '-' . $monthNum . '-' . $day;
			}
		}
	}
	
	// Try standard formats
	// Format: dd.mm.yyyy or dd-mm-yyyy or dd/mm/yyyy
	if (preg_match('/^(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})$/', $dateStr, $matches)) {
		$day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
		$month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
		$year = $matches[3];
		return $year . '-' . $month . '-' . $day;
	}
	
	// Format: yyyy-mm-dd (already correct)
	if (preg_match('/^(\d{4})[.\-\/](\d{1,2})[.\-\/](\d{1,2})$/', $dateStr, $matches)) {
		$year = $matches[1];
		$month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
		$day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
		return $year . '-' . $month . '-' . $day;
	}
	
	// Try PHP's strtotime as fallback
	$timestamp = strtotime($dateStr);
	if ($timestamp !== false && $timestamp > 0) {
		return date('Y-m-d', $timestamp);
	}
	
	// Return original if nothing worked
	return $originalDate;
}

// Get action and poolFile from POST
$action = isset($_POST['action']) ? $_POST['action'] : '';
$poolFile = isset($_POST['poolFile']) ? $_POST['poolFile'] : '';

if (empty($poolFile)) {
	echo json_encode(['success' => false, 'error' => 'Ingen fil angivet']);
	exit;
}

// Get docFolder from POST (same as what docPool.php uses)
$docFolder = isset($_POST['docFolder']) ? $_POST['docFolder'] : '../bilag';

// Build full path to the pool file using the same path structure as docPool.php
// docFolder is relative to the includes/ directory (e.g., "../bilag")
// Since this handler is in includes/docsIncludes/, we need to go up one level first
$puljePath = "../" . $docFolder . "/$db/pulje";

// Normalize the path to handle potential double-slashes
$puljePath = preg_replace('#/+#', '/', $puljePath);

$filePath = "$puljePath/$poolFile";

// If file not found, try alternate path constructions
if (!file_exists($filePath)) {
	error_log("extractInvoiceHandler: File not found at primary path: $filePath. Trying alternates...");
	
	$altPaths = [
		$docFolder . "/$db/pulje/$poolFile",           // Without the extra ../
		"../../bilag/$db/pulje/$poolFile",              // Hardcoded fallback for standard location
	];
	
	foreach ($altPaths as $alt) {
		$alt = preg_replace('#/+#', '/', $alt); // Normalize
		if (file_exists($alt)) {
			$filePath = $alt;
			$puljePath = dirname($alt);
			error_log("extractInvoiceHandler: Found file at alternate path: $alt");
			break;
		}
	}
}

// Action: extract - Call the invoice extraction API
if ($action === 'extract') {
	// Check if file exists
	if (!file_exists($filePath)) {
		echo json_encode(['success' => false, 'error' => 'Fil ikke fundet: ' . $poolFile]);
		exit;
	}
	
	// Generate invoice ID from filename
	$baseName = pathinfo($poolFile, PATHINFO_FILENAME);
	$invoiceId = 'pool-' . $baseName . '-' . time();
	
	// Call the extraction API
	$result = extractInvoiceData($filePath, $invoiceId);
	
	if ($result !== null) {
		// Normalize date format (handles Danish months like "17.oktober.2025")
		$normalizedDate = isset($result['date']) ? normalizeDateFormat($result['date']) : null;
		
		echo json_encode([
			'success' => true,
			'data' => [
				'amount' => $result['amount'] ?? null,
				'date' => $normalizedDate,
				'vendor' => $result['vendor'] ?? null,
				'invoiceNumber' => $result['invoiceNumber'] ?? null,
				'description' => $result['description'] ?? null
			]
		]);
	} else {
		echo json_encode(['success' => false, 'error' => 'Kunne ikke udtrække data fra fakturaen']);
	}
	exit;
}

// Action: save - Save extracted data to the database
if ($action === 'save') {
	$newAmount = isset($_POST['newAmount']) ? $_POST['newAmount'] : '';
	$newDate = isset($_POST['newDate']) ? $_POST['newDate'] : '';
	$newSubject = isset($_POST['newSubject']) ? $_POST['newSubject'] : '';
	$newAccount = isset($_POST['newAccount']) ? $_POST['newAccount'] : '';
	$newInvoiceNumber = isset($_POST['newInvoiceNumber']) ? $_POST['newInvoiceNumber'] : '';
	$newDescription = isset($_POST['newDescription']) ? $_POST['newDescription'] : '';
	
	$baseName = pathinfo($poolFile, PATHINFO_FILENAME);
	
	// Read existing data from database
	$existingSubject = '';
	$existingAccount = '';
	$existingAmount = '';
	$existingDate = '';
	$existingInvoiceNumber = '';
	$existingDescription = '';
	
	$qtxt = "SELECT * FROM pool_files WHERE filename = '". db_escape_string($poolFile) ."'";
	$existingRow = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	
	if ($existingRow) {
		$existingSubject = $existingRow['subject'] ?? '';
		$existingAccount = $existingRow['account'] ?? '';
		$existingAmount = $existingRow['amount'] ?? '';
		$existingDate = $existingRow['file_date'] ?? '';
		$existingInvoiceNumber = $existingRow['invoice_number'] ?? '';
		$existingDescription = $existingRow['description'] ?? '';
		
		// If date in DB is in Y-m-d H:i:s format, we might want to standardize, but let's keep it as is
		// logic below handles newDate overrides
	} else {
		// Fallback to .info file ONLY if not in DB (migration path)
		$infoFile = "$puljePath/$baseName.info";
		if (file_exists($infoFile)) {
			$infoLines = file($infoFile, FILE_IGNORE_NEW_LINES);
			if ($infoLines !== false && is_array($infoLines)) {
				$existingSubject = isset($infoLines[0]) ? trim($infoLines[0]) : '';
				$existingAccount = isset($infoLines[1]) ? trim($infoLines[1]) : '';
				$existingAmount = isset($infoLines[2]) ? trim($infoLines[2]) : '';
				$existingDate = isset($infoLines[3]) ? trim($infoLines[3]) : '';
				$existingInvoiceNumber = isset($infoLines[4]) ? trim($infoLines[4]) : '';
				$existingDescription = isset($infoLines[5]) ? trim($infoLines[5]) : '';
			}
		}
	}
	
	// Use new values if provided, otherwise keep existing
	$finalSubject = !empty($newSubject) ? $newSubject : (!empty($existingSubject) ? $existingSubject : $baseName);
	$finalAccount = !empty($newAccount) ? $newAccount : $existingAccount;
	$finalAmount = !empty($newAmount) ? $newAmount : $existingAmount;
	$finalInvoiceNumber = !empty($newInvoiceNumber) ? $newInvoiceNumber : $existingInvoiceNumber;
	$finalDescription = !empty($newDescription) ? $newDescription : $existingDescription;
	
	// Format date using the normalization function (handles Danish months, etc.)
	$dateToUse = !empty($newDate) ? $newDate : $existingDate;
	$finalDate = normalizeDateFormat($dateToUse);
	
	// Update or Insert into Database
	if ($existingRow) {
		$qtxt = "UPDATE pool_files SET 
			subject = '". db_escape_string($finalSubject) ."',
			account = '". db_escape_string($finalAccount) ."',
			amount = '". db_escape_string($finalAmount) ."',
			invoice_number = '". db_escape_string($finalInvoiceNumber) ."',
			description = '". db_escape_string($finalDescription) ."',
			file_date = '". db_escape_string($finalDate) ."',
			updated = CURRENT_TIMESTAMP
			WHERE filename = '". db_escape_string($poolFile) ."'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	} else {
		$qtxt = "INSERT INTO pool_files (filename, subject, account, amount, file_date, invoice_number, description) VALUES (
			'". db_escape_string($poolFile) ."',
			'". db_escape_string($finalSubject) ."',
			'". db_escape_string($finalAccount) ."',
			'". db_escape_string($finalAmount) ."',
			'". db_escape_string($finalDate) ."',
			'". db_escape_string($finalInvoiceNumber) ."',
			'". db_escape_string($finalDescription) ."'
		)";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	
	echo json_encode(['success' => true]);
	exit;
}

// Action: delete - Delete a pool file and its .info file
if ($action === 'delete') {
	$baseName = pathinfo($poolFile, PATHINFO_FILENAME);
	$ext = pathinfo($poolFile, PATHINFO_EXTENSION);
	
	$pdfPath = "$puljePath/$poolFile";
	$infoPath = "$puljePath/$baseName.info";
	
	$deletedPdf = false;
	$deletedInfo = false;
	
	// Delete the PDF file
	if (file_exists($pdfPath)) {
		if (unlink($pdfPath)) {
			$deletedPdf = true;
		} else {
			echo json_encode(['success' => false, 'error' => 'Kunne ikke slette fil: ' . $poolFile]);
			exit;
		}
	} else {
		// File doesn't exist, consider it deleted
		$deletedPdf = true;
	}
	
	// Delete the .info file if it exists
	if (file_exists($infoPath)) {
		if (unlink($infoPath)) {
			$deletedInfo = true;
		}
		// Don't fail if .info can't be deleted, it's not critical
	}
	
	// Remove from database if pool_files table exists
	$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'pool_files'";
	if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$qtxt = "DELETE FROM pool_files WHERE filename = '". db_escape_string($poolFile) ."'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	
	echo json_encode(['success' => true, 'deletedPdf' => $deletedPdf, 'deletedInfo' => $deletedInfo]);
	exit;
}

// Unknown action
echo json_encode(['success' => false, 'error' => 'Ukendt handling: ' . $action]);
