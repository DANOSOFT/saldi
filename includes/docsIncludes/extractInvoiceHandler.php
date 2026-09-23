<?php
// --- includes/docsIncludes/extractInvoiceHandler.php ---
// AJAX handler for invoice extraction from pool files
// ----------------------------------------------------------------------
// 20260910 CL/SZ SST-777: validate the suggested account against kontoplan before saving,
//                 and skip an automatic re-extraction save that would overwrite a field
//                 the user already corrected by hand (manually_edited).
// 20260910 SZ SST-777 (CodeRabbit): manually_edited came back from Postgres as "f", which
//                 !empty() treated as true - every row read as manually edited. Check
//                 explicit truthy values instead. Also closed the race where a manual save
//                 landing between the read and an automatic save's write got overwritten:
//                 the automatic UPDATE's WHERE clause now itself requires
//                 manually_edited = false, and skipped:true is reported if it loses that race.
// 20260909 CDX/MJ SST-775 Moved normalizeDateFormat() to poolDateNormalizer.php so the date
//             handling is testable - this file connects to a database and exits when included.
//             Behaviour is unchanged here; the fixes live in that file.
// 20260922 CL/LAH Leverandørforslag fra AI-scan: 'extract' now also returns the seller's
//             CVR/IBAN/bank details and a server-side kreditor match (vendorMatch); 'save'
//             re-runs the match from the posted identity fields (vendorScan=1) and stores
//             the pool_files.vendor_* columns. Match logic lives in poolVendorMatcher.php.

// 20260914 CDX/LH Share atomic metadata saves and reject stale confirmations.

// Set JSON response header FIRST
header('Content-Type: application/json');

// Start output buffering to capture any unwanted output
ob_start();

// Start session so the tenant db can be resolved from it below - a POSTed
// db name must never be trusted directly (it would let a tampered request
// read/write/delete another tenant's pool documents; see SST-776).
@session_start();
$s_id = session_id();

// Include database connection
include_once(__DIR__ . "/../connect.php");
include_once(__DIR__ . "/poolAmountNormalizer.php");
include_once(__DIR__ . "/poolDateNormalizer.php");
require_once __DIR__ . "/poolMetadata.php";
require_once __DIR__ . "/../std_func.php";
include_once(__DIR__ . "/poolVendorMatcher.php");

// Resolve the tenant db from the session's online-table entry, same pattern
// as includes/_docPoolData.php and includes/online.php - never from $_POST['db'].
$qtxt = "select db, regnskabsaar, language_id from online where session_id = '" . db_escape_string($s_id) . "' order by logtime desc limit 1";
$onlineRow = db_fetch_array(db_select($qtxt, __FILE__ . " line " . __LINE__));
$db = trim($onlineRow['db'] ?? '');
$regnaar = (int)($onlineRow['regnskabsaar'] ?? 0);
$sprog_id = (int)($onlineRow['language_id'] ?? 1);

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
include_once(__DIR__ . "/invoiceExtractionApi.php");

// Discard any buffered output from includes
ob_end_clean();

/**
 * Match the vendor identity of a scanned invoice against the tenant's kreditorer.
 * Two database reads (adresser art='K' and the tenant's own CVR from art='S'), no API calls.
 *
 * @param array{name?:string|null,cvr?:string|null,iban?:string|null,bank_reg?:string|null,bank_konto?:string|null,customerCvr?:string|null} $identity
 * @return array See poolVendorMatch().
 */
function extractInvoiceMatchVendor(array $identity) {
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

if (!is_string($poolFile) || $poolFile === '') {
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
	$metadata = db_fetch_array(db_select("SELECT * FROM pool_files WHERE filename = '" . db_escape_string($poolFile) . "'", __FILE__ . ' line ' . __LINE__));
	$metadataVersion = $metadata ? poolMetadataVersion($metadata) : null;
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
			'version' => $metadataVersion,
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
		]);
	} else {
		echo json_encode(['success' => false, 'error' => 'Kunne ikke udtrække data fra fakturaen']);
	}
	exit;
}

// Action: save - Save extracted data to the database
if ($action === 'save') {
	$input = [];
	foreach (['newSubject' => 'subject', 'newAccount' => 'account', 'newAmount' => 'amount',
		'newDate' => 'file_date', 'newInvoiceNumber' => 'invoice_number',
		'newDescription' => 'description', 'newCurrency' => 'currency'] as $requestKey => $field) {
		if (array_key_exists($requestKey, $_POST)) {
			$input[$field] = $_POST[$requestKey];
		}
	}
	try {
		$version = $_POST['poolVersion'] ?? null;
		if ($version !== null && !is_string($version)) {
			throw new InvalidArgumentException('Invalid version', 422);
		}
		$result = poolMetadataSave($poolFile, $input, ($_POST['manual'] ?? '') === '1', $version, $regnaar);

		// Vendor identity is only (re)matched when the caller is one of the scanning paths
		// (vendorScan=1); a plain metadata save/correction leaves the vendor_* columns
		// untouched. Best-effort, like the rest of this handler - a failed or skipped match
		// never fails the save itself, it just reports vendor: null.
		$vendorMatch = null;
		if (($_POST['vendorScan'] ?? '') === '1') {
			if (!poolVendorColumnsExist()) {
				// Migration not applied on this tenant yet: the ordinary fields are already
				// saved above; the file is matched on the next scan or when the pool opens
				// after the columns exist.
				error_log("extractInvoiceHandler: pool_files.vendor_* columns missing on $db - vendor match skipped for $poolFile");
			} else {
				$vendorMatch = extractInvoiceMatchVendor(array(
					'name' => $_POST['newSubject'] ?? '',
					'cvr' => $_POST['newVendorCvr'] ?? '',
					'iban' => $_POST['newVendorIban'] ?? '',
					'bank_reg' => $_POST['newVendorBankReg'] ?? '',
					'bank_konto' => $_POST['newVendorBankKonto'] ?? '',
					'customerCvr' => $_POST['newCustomerCvr'] ?? '',
				));
				if ($vendorMatch !== null) {
					db_modify(
						"UPDATE pool_files SET " . poolVendorUpdateSql($vendorMatch) . " WHERE filename = '" . db_escape_string($poolFile) . "'",
						__FILE__ . " linje " . __LINE__
					);
				}
			}
		}

		$result['vendor'] = $vendorMatch;
		echo json_encode($result);
	} catch (RuntimeException | InvalidArgumentException $error) {
		$status = $error->getCode() === 409 ? 409 : 422;
		http_response_code($status);
		$message = $status === 409
			? findtekst('5253|Dokumentet er ændret. Genindlæs det før du gemmer.', $sprog_id)
			: findtekst('5254|Kontrollér konto, beløb og dato. Ingen ændringer er gemt.', $sprog_id);
		echo json_encode(['success' => false, 'error' => $message]);
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
