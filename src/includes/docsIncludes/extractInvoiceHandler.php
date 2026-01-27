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

// Include the extraction API
include_once("invoiceExtractionApi.php");

// Discard any buffered output from includes
ob_end_clean();

// Get action and poolFile from POST
$action = isset($_POST['action']) ? $_POST['action'] : '';
$poolFile = isset($_POST['poolFile']) ? $_POST['poolFile'] : '';

if (empty($poolFile)) {
	echo json_encode(['success' => false, 'error' => 'Ingen fil angivet']);
	exit;
}

// Build full path to the pool file
$puljePath = "../../bilag/$db/pulje";
$filePath = "$puljePath/$poolFile";

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
		echo json_encode([
			'success' => true,
			'data' => [
				'amount' => $result['amount'] ?? null,
				'date' => $result['date'] ?? null,
				'vendor' => $result['vendor'] ?? null
			]
		]);
	} else {
		echo json_encode(['success' => false, 'error' => 'Kunne ikke udtrække data fra fakturaen']);
	}
	exit;
}

// Action: save - Save extracted data to the .info file
if ($action === 'save') {
	$newAmount = isset($_POST['newAmount']) ? $_POST['newAmount'] : '';
	$newDate = isset($_POST['newDate']) ? $_POST['newDate'] : '';
	$newSubject = isset($_POST['newSubject']) ? $_POST['newSubject'] : '';
	$newAccount = isset($_POST['newAccount']) ? $_POST['newAccount'] : '';
	
	$baseName = pathinfo($poolFile, PATHINFO_FILENAME);
	$infoFile = "$puljePath/$baseName.info";
	
	// Read existing .info file if it exists
	$existingSubject = '';
	$existingAccount = '';
	$existingAmount = '';
	$existingDate = '';
	
	if (file_exists($infoFile)) {
		$infoLines = file($infoFile, FILE_IGNORE_NEW_LINES);
		$existingSubject = isset($infoLines[0]) ? trim($infoLines[0]) : '';
		$existingAccount = isset($infoLines[1]) ? trim($infoLines[1]) : '';
		$existingAmount = isset($infoLines[2]) ? trim($infoLines[2]) : '';
		$existingDate = isset($infoLines[3]) ? trim($infoLines[3]) : '';
	}
	
	// Use new values if provided, otherwise keep existing
	$finalSubject = !empty($newSubject) ? $newSubject : (!empty($existingSubject) ? $existingSubject : $baseName);
	$finalAccount = !empty($newAccount) ? $newAccount : $existingAccount;
	$finalAmount = !empty($newAmount) ? $newAmount : $existingAmount;
	
	// Format date to yyyy-mm-dd if provided
	$dateToUse = !empty($newDate) ? $newDate : $existingDate;
	$finalDate = '';
	if (!empty($dateToUse)) {
		$timestamp = strtotime($dateToUse);
		if ($timestamp !== false && $timestamp > 0) {
			$finalDate = date('Y-m-d', $timestamp);
		} else {
			$finalDate = $dateToUse; // Keep original if parsing fails
		}
	}
	
	// Write to .info file
	$infoLines = [
		$finalSubject,
		$finalAccount,
		$finalAmount,
		$finalDate
	];
	
	if (file_put_contents($infoFile, implode(PHP_EOL, $infoLines) . PHP_EOL) !== false) {
		// Also update the database if pool_files table exists
		$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = '$db' AND table_name = 'pool_files'";
		if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			$filename = $poolFile;
			$qtxt = "SELECT id FROM pool_files WHERE filename = '". db_escape_string($filename) ."'";
			$existing = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			
			if ($existing) {
				$qtxt = "UPDATE pool_files SET 
					subject = '". db_escape_string($finalSubject) ."',
					account = '". db_escape_string($finalAccount) ."',
					amount = '". db_escape_string($finalAmount) ."',
					file_date = '". db_escape_string($finalDate) ."',
					updated = CURRENT_TIMESTAMP
					WHERE filename = '". db_escape_string($filename) ."'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			} else {
				$qtxt = "INSERT INTO pool_files (filename, subject, account, amount, file_date) VALUES (
					'". db_escape_string($filename) ."',
					'". db_escape_string($finalSubject) ."',
					'". db_escape_string($finalAccount) ."',
					'". db_escape_string($finalAmount) ."',
					'". db_escape_string($finalDate) ."'
				)";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		}
		
		echo json_encode(['success' => true]);
	} else {
		echo json_encode(['success' => false, 'error' => 'Kunne ikke gemme til .info fil']);
	}
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
	$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = '$db' AND table_name = 'pool_files'";
	if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$qtxt = "DELETE FROM pool_files WHERE filename = '". db_escape_string($poolFile) ."'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	
	echo json_encode(['success' => true, 'deletedPdf' => $deletedPdf, 'deletedInfo' => $deletedInfo]);
	exit;
}

// Unknown action
echo json_encode(['success' => false, 'error' => 'Ukendt handling: ' . $action]);
