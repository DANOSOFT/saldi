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

// Get docFolder from POST (same as what docPool.php uses)
$docFolder = isset($_POST['docFolder']) ? $_POST['docFolder'] : '../bilag';

// Build full path to the pool file using the same path structure as docPool.php
// docFolder is relative to the includes/ directory (e.g., "../bilag")
// Since this handler is in includes/docsIncludes/, we need to go up one level first
$puljePath = "../" . $docFolder . "/$db/pulje";

// Normalize the path to handle potential double-slashes
$puljePath = preg_replace('#/+#', '/', $puljePath);

// Debug: log the constructed paths
error_log("extractInvoiceHandler: docFolder=$docFolder, db=$db, puljePath=$puljePath, poolFile=$poolFile");

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
		echo json_encode([
			'success' => true,
			'data' => [
				'amount' => $result['amount'] ?? null,
				'date' => $result['date'] ?? null,
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

// Action: save - Save extracted data to the .info file
if ($action === 'save') {
	$newAmount = isset($_POST['newAmount']) ? $_POST['newAmount'] : '';
	$newDate = isset($_POST['newDate']) ? $_POST['newDate'] : '';
	$newSubject = isset($_POST['newSubject']) ? $_POST['newSubject'] : '';
	$newAccount = isset($_POST['newAccount']) ? $_POST['newAccount'] : '';
	$newInvoiceNumber = isset($_POST['newInvoiceNumber']) ? $_POST['newInvoiceNumber'] : '';
	$newDescription = isset($_POST['newDescription']) ? $_POST['newDescription'] : '';
	
	$baseName = pathinfo($poolFile, PATHINFO_FILENAME);
	$infoFile = "$puljePath/$baseName.info";
	
	// Debug log
	error_log("extractInvoiceHandler SAVE: poolFile=$poolFile, baseName=$baseName, infoFile=$infoFile");
	error_log("extractInvoiceHandler SAVE: newAmount=$newAmount, newDate=$newDate, newSubject=$newSubject");
	error_log("extractInvoiceHandler SAVE: newInvoiceNumber=$newInvoiceNumber, newDescription=$newDescription");
	
	// Read existing .info file if it exists
	$existingSubject = '';
	$existingAccount = '';
	$existingAmount = '';
	$existingDate = '';
	$existingInvoiceNumber = '';
	$existingDescription = '';
	
	if (file_exists($infoFile)) {
		$infoLines = file($infoFile, FILE_IGNORE_NEW_LINES);
		// Check if file() returned false (read failure)
		if ($infoLines !== false && is_array($infoLines)) {
			$existingSubject = isset($infoLines[0]) ? trim($infoLines[0]) : '';
			$existingAccount = isset($infoLines[1]) ? trim($infoLines[1]) : '';
			$existingAmount = isset($infoLines[2]) ? trim($infoLines[2]) : '';
			$existingDate = isset($infoLines[3]) ? trim($infoLines[3]) : '';
			$existingInvoiceNumber = isset($infoLines[4]) ? trim($infoLines[4]) : '';
			$existingDescription = isset($infoLines[5]) ? trim($infoLines[5]) : '';
			error_log("extractInvoiceHandler SAVE: Read existing - subject=$existingSubject, account=$existingAccount, amount=$existingAmount, date=$existingDate, invoiceNumber=$existingInvoiceNumber, description=$existingDescription");
		} else {
			error_log("extractInvoiceHandler SAVE: file() failed or returned non-array for $infoFile");
		}
	} else {
		error_log("extractInvoiceHandler SAVE: Info file does not exist: $infoFile");
	}
	
	// Use new values if provided, otherwise keep existing
	$finalSubject = !empty($newSubject) ? $newSubject : (!empty($existingSubject) ? $existingSubject : $baseName);
	$finalAccount = !empty($newAccount) ? $newAccount : $existingAccount;
	$finalAmount = !empty($newAmount) ? $newAmount : $existingAmount;
	$finalInvoiceNumber = !empty($newInvoiceNumber) ? $newInvoiceNumber : $existingInvoiceNumber;
	$finalDescription = !empty($newDescription) ? $newDescription : $existingDescription;
	
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
	
	error_log("extractInvoiceHandler SAVE: Final values - subject=$finalSubject, account=$finalAccount, amount=$finalAmount, date=$finalDate, invoiceNumber=$finalInvoiceNumber, description=$finalDescription");
	
	// Write to .info file
	$infoLinesToWrite = [
		$finalSubject,
		$finalAccount,
		$finalAmount,
		$finalDate,
		$finalInvoiceNumber,
		$finalDescription
	];
	
	if (file_put_contents($infoFile, implode(PHP_EOL, $infoLinesToWrite) . PHP_EOL) !== false) {
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
					invoice_number = '". db_escape_string($finalInvoiceNumber) ."',
					description = '". db_escape_string($finalDescription) ."',
					file_date = '". db_escape_string($finalDate) ."',
					updated = CURRENT_TIMESTAMP
					WHERE filename = '". db_escape_string($filename) ."'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			} else {
				$qtxt = "INSERT INTO pool_files (filename, subject, account, amount, file_date, invoice_number, description) VALUES (
					'". db_escape_string($filename) ."',
					'". db_escape_string($finalSubject) ."',
					'". db_escape_string($finalAccount) ."',
					'". db_escape_string($finalAmount) ."',
					'". db_escape_string($finalDate) ."',
					'". db_escape_string($finalInvoiceNumber) ."',
					'". db_escape_string($finalDescription) ."'
				)";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		}
		
		echo json_encode(['success' => true]);
	} else {
		error_log("extractInvoiceHandler SAVE: file_put_contents failed for $infoFile");
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
