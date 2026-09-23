<?php
// --- includes/docsIncludes/extractInvoiceHandler.php ---
// AJAX handler for invoice extraction from pool files
// ----------------------------------------------------------------------
// 20260909 CDX/MJ SST-775 Moved normalizeDateFormat() to poolDateNormalizer.php so the date
//             handling is testable - this file connects to a database and exits when included.
//             Behaviour is unchanged here; the fixes live in that file.
// 20260922 CL/LAH Leverandørforslag fra AI-scan: 'extract' now also returns the seller's
//             CVR/IBAN/bank details and a server-side kreditor match (vendorMatch); 'save'
//             re-runs the match from the posted identity fields (vendorScan=1) and stores
//             the pool_files.vendor_* columns. Match logic lives in poolVendorMatcher.php.
// 20260922 CL/LAH A fatal is reported as a JSON error instead of an empty body; the vendor
//             match is wrapped so it can never fail the scan; JSON_INVALID_UTF8_SUBSTITUTE
//             on the responses because legacy adresser rows can hold non-UTF-8 bytes.
// 20260923 CL/LAH Output stays buffered until shutdown, so a warning, a late fatal or
//             db_query.php's alert() can no longer corrupt the JSON (Astra review).

// Set JSON response header FIRST
header('Content-Type: application/json');

// Start output buffering to capture any unwanted output
ob_start();

// Every response of this handler must be one JSON document. A fatal error, a warning printed
// with display_errors on, or db_query.php's alert() on a failed query would otherwise reach
// the browser as an empty or non-JSON body ("Unexpected end of JSON input", seen on ssl3
// 2026-09-22). All output stays buffered until shutdown; if the buffer is not valid JSON by
// then, it is replaced by a JSON error naming the cause, which is also written to the log.
register_shutdown_function(function () {
	$error = error_get_last();
	$fatal = $error !== null && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR), true);
	// Before the guarded buffer is opened (session/db checks) responses are echoed directly;
	// only a fatal needs handling there.
	if (empty($GLOBALS['extractInvoiceOutputGuard']) && !$fatal) return;
	$out = '';
	while (ob_get_level() > 0) $out = ob_get_clean() . $out;
	$trimmed = trim($out);
	if (!$fatal && $trimmed !== '') {
		json_decode($trimmed);
		if (json_last_error() === JSON_ERROR_NONE) {
			echo $trimmed;
			return;
		}
		// Valid JSON with stray output in front of it (a warning, a debug echo): send the JSON.
		$jsonStart = strrpos($trimmed, '{"success"');
		if ($jsonStart !== false) {
			$tail = substr($trimmed, $jsonStart);
			json_decode($tail);
			if (json_last_error() === JSON_ERROR_NONE) {
				error_log("extractInvoiceHandler: stray output before JSON: " . substr(strip_tags(substr($trimmed, 0, $jsonStart)), 0, 500));
				echo $tail;
				return;
			}
		}
	}
	if ($fatal) {
		$reason = basename($error['file']) . ':' . $error['line'] . ' ' . $error['message'];
	} elseif ($trimmed === '') {
		$reason = 'tomt svar';
	} elseif (preg_match('#alert\((["\'])(.*?)\1\)#s', $trimmed, $alertMatch)) {
		// db_query.php's alert() after a failed query: its text is the user-facing message.
		$reason = $alertMatch[2];
		error_log("extractInvoiceHandler: output before alert: " . substr(strip_tags($trimmed), 0, 500));
	} else {
		$reason = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($trimmed), ENT_QUOTES, 'UTF-8')));
	}
	error_log("extractInvoiceHandler: non-JSON response replaced: " . substr($reason, 0, 500));
	echo json_encode(array('success' => false, 'error' => 'Serverfejl: ' . mb_substr($reason, 0, 300, 'UTF-8')), JSON_INVALID_UTF8_SUBSTITUTE);
});

// Start session so the tenant db can be resolved from it below - a POSTed
// db name must never be trusted directly (it would let a tampered request
// read/write/delete another tenant's pool documents; see SST-776).
@session_start();
$s_id = session_id();

// Include database connection
include_once(__DIR__ . "/../connect.php");
include_once(__DIR__ . "/poolAmountNormalizer.php");
include_once(__DIR__ . "/poolDateNormalizer.php");
include_once(__DIR__ . "/poolVendorMatcher.php");

// Resolve the tenant db from the session's online-table entry, same pattern
// as includes/_docPoolData.php and includes/online.php - never from $_POST['db'].
$qtxt = "select db from online where session_id = '" . db_escape_string($s_id) . "' order by logtime desc limit 1";
$onlineRow = db_fetch_array(db_select($qtxt, __FILE__ . " line " . __LINE__));
$db = trim($onlineRow['db'] ?? '');

if (empty($db)) {
	ob_end_clean();
	echo json_encode(['success' => false, 'error' => 'Session udløbet - log ind igen']);
	exit;
}

// Validate db name (only allow alphanumeric and underscore)
if (!preg_match('/^[a-zA-Z0-9_]+$/', $db)) {
	ob_end_clean();
	echo json_encode(['success' => false, 'error' => 'Ugyldig database navn']);
	exit;
}

// Connect to the session's own database
global $sqhost, $squser, $sqpass;
$connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . " line " . __LINE__);

if (!$connection) {
	ob_end_clean();
	echo json_encode(['success' => false, 'error' => 'Kunne ikke forbinde til database: ' . $db]);
	exit;
}

// Include the extraction API
include_once("invoiceExtractionApi.php");

// Discard any buffered output from includes, then keep buffering until shutdown (see the
// shutdown handler at the top) so nothing can reach the browser outside the JSON response.
ob_end_clean();
ob_start();
$GLOBALS['extractInvoiceOutputGuard'] = true;

/**
 * Match the vendor identity of a scanned invoice against the tenant's kreditorer.
 * Two database reads (adresser art='K' and the tenant's own CVR from art='S'), no API calls.
 *
 * @param array{name?:string|null,cvr?:string|null,iban?:string|null,bank_reg?:string|null,bank_konto?:string|null,customerCvr?:string|null} $identity
 * @return array See poolVendorMatch().
 */
function extractInvoiceMatchVendor(array $identity) {
	try {
		return extractInvoiceMatchVendorUnsafe($identity);
	} catch (Throwable $e) {
		// The match is a bonus on top of the scan; never let it take the scan down.
		error_log("extractInvoiceHandler vendor match failed: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
		return null;
	}
}

function extractInvoiceMatchVendorUnsafe(array $identity) {
	$ownCvr = poolVendorLoadOwnCvr();
	// The buyer's CVR on the invoice is a second way to know which number is not the seller's.
	$customerCvr = normalizePoolVendorCvr($identity['customerCvr'] ?? null);
	if ($customerCvr !== null && $ownCvr === null) $ownCvr = $customerCvr;
	$vendorCvr = normalizePoolVendorCvr($identity['cvr'] ?? null);
	if ($vendorCvr !== null && $customerCvr !== null && $vendorCvr === $customerCvr) $identity['cvr'] = null;
	return poolVendorMatch($identity, poolVendorLoadIndex(), array('ownCvr' => $ownCvr, 'nameScan' => 'full'));
}


// Get action and poolFile from POST
$action = isset($_POST['action']) ? $_POST['action'] : '';
$poolFile = isset($_POST['poolFile']) ? $_POST['poolFile'] : '';

if (empty($poolFile)) {
	echo json_encode(['success' => false, 'error' => 'Ingen fil angivet']);
	exit;
}

// poolFile must be a bare filename - reject any path component so a
// tampered value can't escape the tenant's own pulje directory (SST-776).
if ($poolFile !== basename($poolFile) || $poolFile === '.' || $poolFile === '..') {
	echo json_encode(['success' => false, 'error' => 'Ugyldigt filnavn']);
	exit;
}

// Get docFolder from POST, but only accept the same fixed values
// documents.php itself ever assigns to $docFolder - a POSTed path is not
// trusted for directory traversal (SST-776).
$requestedDocFolder = isset($_POST['docFolder']) ? $_POST['docFolder'] : '../bilag';
$allowedDocFolders = ['../owncloud', '../bilag', '../documents'];
$docFolder = in_array($requestedDocFolder, $allowedDocFolders, true) ? $requestedDocFolder : '../bilag';

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
		
		$vendorMatch = extractInvoiceMatchVendor(array(
			'name' => $result['vendor'] ?? null,
			'cvr' => $result['vendorCvr'] ?? null,
			'iban' => $result['vendorIban'] ?? null,
			'bank_reg' => $result['vendorBankReg'] ?? null,
			'bank_konto' => $result['vendorBankKonto'] ?? null,
			'customerCvr' => $result['customerCvr'] ?? null,
		));

		echo json_encode([
			'success' => true,
			'data' => [
				'amount' => $result['amount'] ?? null,
				'date' => $normalizedDate,
				'vendor' => $result['vendor'] ?? null,
				'invoiceNumber' => $result['invoiceNumber'] ?? null,
				'description' => $result['description'] ?? null,
				'currency' => $result['currency'] ?? null,
				'vendorCvr' => $result['vendorCvr'] ?? null,
				'vendorIban' => $result['vendorIban'] ?? null,
				'vendorBankReg' => $result['vendorBankReg'] ?? null,
				'vendorBankKonto' => $result['vendorBankKonto'] ?? null,
				'customerCvr' => $result['customerCvr'] ?? null,
				'vendorMatch' => $vendorMatch
			]
		], JSON_INVALID_UTF8_SUBSTITUTE);
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
	$newCurrency = isset($_POST['newCurrency']) ? $_POST['newCurrency'] : '';
	// Vendor identity is only (re)matched when the caller is one of the scanning paths
	// (vendorScan=1); a plain metadata save leaves the vendor_* columns untouched.
	$vendorScan = isset($_POST['vendorScan']) && $_POST['vendorScan'] === '1';
	$newVendorCvr = isset($_POST['newVendorCvr']) ? $_POST['newVendorCvr'] : '';
	$newVendorIban = isset($_POST['newVendorIban']) ? $_POST['newVendorIban'] : '';
	$newVendorBankReg = isset($_POST['newVendorBankReg']) ? $_POST['newVendorBankReg'] : '';
	$newVendorBankKonto = isset($_POST['newVendorBankKonto']) ? $_POST['newVendorBankKonto'] : '';
	$newCustomerCvr = isset($_POST['newCustomerCvr']) ? $_POST['newCustomerCvr'] : '';
	
	$baseName = pathinfo($poolFile, PATHINFO_FILENAME);
	
	// Read existing data from database
	$existingSubject = '';
	$existingAccount = '';
	$existingAmount = '';
	$existingDate = '';
	$existingInvoiceNumber = '';
	$existingDescription = '';
	$existingCurrency = '';
	
	$qtxt = "SELECT * FROM pool_files WHERE filename = '". db_escape_string($poolFile) ."'";
	$existingRow = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	
	if ($existingRow) {
		$existingSubject = $existingRow['subject'] ?? '';
		$existingAccount = $existingRow['account'] ?? '';
		$existingAmount = $existingRow['amount'] ?? '';
		$existingDate = $existingRow['file_date'] ?? '';
		$existingInvoiceNumber = $existingRow['invoice_number'] ?? '';
		$existingDescription = $existingRow['description'] ?? '';
		$existingCurrency = $existingRow['currency'] ?? '';

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
	// Normalize aliases like "kr"/"kr." to "DKK" - fetchbilagsmatch.php's currency hard
	// gate is a plain string match, so an unrecognized currency string (as returned
	// verbatim by the AI extraction API) would silently exclude this file from every
	// match regardless of how well amount/date/text otherwise line up.
	$finalCurrency = normalizePoolCurrency(!empty($newCurrency) ? $newCurrency : $existingCurrency) ?? '';

	// Format date using the normalization function (handles Danish months, etc.)
	$dateToUse = !empty($newDate) ? $newDate : $existingDate;
	$finalDate = normalizeDateFormat($dateToUse);

	// Normalize the amount to a real number now, so Bilagsmatch scoring can join on
	// norm_amount directly instead of re-parsing this free-form string at query time.
	$finalNormAmount = normalizePoolAmount($finalAmount);
	$normAmountSql = ($finalNormAmount === null) ? 'NULL' : db_escape_string((string) $finalNormAmount);

	// Match the scanned vendor against kreditorer on the server, so the frontend never
	// carries the match result back and forth. The name as read stays in subject as before.
	$vendorMatch = null;
	if ($vendorScan && !poolVendorColumnsExist()) {
		// Migration not applied on this tenant yet (see poolVendorColumnsExist): save the
		// ordinary fields as before rather than failing the request; the file is matched
		// on the next scan or when the pool opens after the columns exist.
		error_log("extractInvoiceHandler: pool_files.vendor_* columns missing on $db - vendor match skipped for $poolFile");
		$vendorScan = false;
	}
	if ($vendorScan) {
		$vendorMatch = extractInvoiceMatchVendor(array(
			'name' => $newSubject,
			'cvr' => $newVendorCvr,
			'iban' => $newVendorIban,
			'bank_reg' => $newVendorBankReg,
			'bank_konto' => $newVendorBankKonto,
			'customerCvr' => $newCustomerCvr,
		));
	}

	// Update or Insert into Database
	if ($existingRow) {
		$qtxt = "UPDATE pool_files SET
			subject = '". db_escape_string($finalSubject) ."',
			account = '". db_escape_string($finalAccount) ."',
			amount = '". db_escape_string($finalAmount) ."',
			norm_amount = $normAmountSql,
			invoice_number = '". db_escape_string($finalInvoiceNumber) ."',
			description = '". db_escape_string($finalDescription) ."',
			currency = '". db_escape_string($finalCurrency) ."',
			file_date = '". db_escape_string($finalDate) ."',
			updated = CURRENT_TIMESTAMP";
		if ($vendorMatch !== null) $qtxt .= ",\n\t\t\t" . poolVendorUpdateSql($vendorMatch);
		$qtxt .= "
			WHERE filename = '". db_escape_string($poolFile) ."'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	} else {
		$vendorColumns = $vendorMatch !== null ? poolVendorColumnValues($vendorMatch) : array();
		$qtxt = "INSERT INTO pool_files (filename, subject, account, amount, norm_amount, file_date, invoice_number, description, currency"
			. ($vendorColumns ? ', ' . implode(', ', array_keys($vendorColumns)) : '') . ") VALUES (
			'". db_escape_string($poolFile) ."',
			'". db_escape_string($finalSubject) ."',
			'". db_escape_string($finalAccount) ."',
			'". db_escape_string($finalAmount) ."',
			$normAmountSql,
			'". db_escape_string($finalDate) ."',
			'". db_escape_string($finalInvoiceNumber) ."',
			'". db_escape_string($finalDescription) ."',
			'". db_escape_string($finalCurrency) ."'"
			. ($vendorColumns ? ', ' . implode(', ', array_values($vendorColumns)) : '') . "
		)";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	
	echo json_encode(['success' => true, 'vendor' => $vendorMatch], JSON_INVALID_UTF8_SUBSTITUTE);
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
