<?php
// --- tests/test_pool_upload.php --- 2026-10-04 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261004 LOE Cover pool upload deduplication: identical bytes are refused once, different
//                 bytes with the same name still get _N, and concurrent uploads register one row.
//
// Runs against the real database layer (db_select()/db_modify() from includes/connect.php), the
// same way the application does - no database abstraction of its own.
//
//   SALDI_TEST_TENANT_DB=<tenant database> php tests/test_pool_upload.php
//
// It uses a throwaway pool directory and only ever touches pool_files rows whose file name starts
// with pooltest_. The suite skips itself (exit 0) when the tenant database is not reachable, like
// the other suites here, so a checkout without a test account still runs green. Like the pool page
// itself, it may fill in missing content hashes for that one tenant.

chdir(__DIR__);

$childMode = isset($argv[1]) && $argv[1] === '--ingest';

$_SERVER['REQUEST_URI'] = '/saldi/includes/documents.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';

require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/docsIncludes/poolUpload.php';

/** @var string $sqhost set by the gitignored includes/connect.php */
/** @var string $squser set by the gitignored includes/connect.php */
/** @var string $sqpass set by the gitignored includes/connect.php */
/** @var string $sqdb set by the gitignored includes/connect.php */
$tenantDb = $childMode ? $argv[2] : (getenv('SALDI_TEST_TENANT_DB') ?: 'tenant_db');
$db = $tenantDb;
$sprog_id = 1;
$connection = @db_connect($sqhost, $squser, $sqpass, $db);
if (!$connection) {
	fwrite(STDERR, "SKIP: tenant database '$tenantDb' is not reachable on this machine.\n");
	exit(0);
}
if (!db_fetch_array(db_select("SELECT id FROM pool_files LIMIT 1", __FILE__ . ' line ' . __LINE__))
	&& !db_fetch_array(db_select("SELECT table_name FROM information_schema.tables WHERE table_name = 'pool_files'", __FILE__ . ' line ' . __LINE__))) {
	fwrite(STDERR, "SKIP: tenant database '$tenantDb' has no pool_files table.\n");
	exit(0);
}

/** @return array All pool rows for the given file names. */
function poolTestRows(array $names) {
	$rows = array();
	if (!$names) {
		return $rows;
	}
	$quoted = array();
	foreach ($names as $name) {
		$quoted[] = "'" . db_escape_string($name) . "'";
	}
	$result = db_select("SELECT * FROM pool_files WHERE filename IN (" . implode(', ', $quoted) . ") ORDER BY id", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($result)) {
		$rows[$row['filename']] = $row;
	}
	return $rows;
}

/** @return array Every pool file name in the scratch pool directory. */
function poolTestFiles($poolDir) {
	$files = array();
	foreach ((array)scandir($poolDir) as $entry) {
		if ($entry !== '.' && $entry !== '..' && is_file($poolDir . '/' . $entry) && strpos($entry, '.lock') === false) {
			$files[] = $entry;
		}
	}
	sort($files);
	return $files;
}

/** @return string Path of a newly written fixture file with unique bytes. */
function poolTestWrite($prefix, $bytes = null) {
	$path = sys_get_temp_dir() . '/' . $prefix . '_' . bin2hex(random_bytes(4));
	if ($bytes === null) {
		$bytes = "%PDF-1.4\n% " . $prefix . "\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
	}
	file_put_contents($path, $bytes);
	return $path;
}

/** @return string A PNG fixture, so a conversion path can be exercised. */
function poolTestPng($path) {
	$image = imagecreatetruecolor(8, 8);
	imagepng($image, $path);
	imagedestroy($image);
	return $path;
}

$poolDir = sys_get_temp_dir() . '/saldi_pool_test_' . bin2hex(random_bytes(6));
mkdir($poolDir, 0700, true);
// One fixture per scenario: reusing a path would mean uploading different bytes under a name the
// pool has already seen, which is a different test.
$pdfA = poolTestWrite('pooltest_a');
$pdfB = poolTestWrite('pooltest_b', "%PDF-1.4\n% other document\n%%EOF\n");
$pdfC = poolTestWrite('pooltest_c');
$pdfD = poolTestWrite('pooltest_d');
$pdfE = poolTestWrite('pooltest_e');
$png = poolTestPng(sys_get_temp_dir() . '/pooltest_photo_' . bin2hex(random_bytes(4)) . '.png');
$xml = poolTestWrite('pooltest_invoice', '<Invoice><cbc:ID>XML-001</cbc:ID></Invoice>');

// ---- child mode: one ingest, used by the concurrency case -------------------------------
if ($childMode) {
	$result = poolUploadIngest($argv[3], basename($argv[4]), $argv[5], false);
	echo json_encode($result);
	exit(0);
}

// ---- assertions ------------------------------------------------------------------------
$checks = 0;
$failures = array();

/** @return void Record one assertion. */
function poolTestCheck($condition, $message) {
	global $checks, $failures;
	$checks++;
	if (!$condition) {
		$failures[] = $message;
		echo "FAIL: $message\n";
	} else {
		echo "ok:   $message\n";
	}
}

$created = array();

// 1. The customer's case: the same bytes twice, different name, no AI scan.
$first = poolUploadIngest($pdfA, 'pooltest_faktura.pdf', $poolDir, false);
$created[] = ifset($first, 'filename', '');
$second = poolUploadIngest($pdfA, 'pooltest_renamed.pdf', $poolDir, false);
poolTestCheck(!empty($first['success']) && !empty($first['filename']), 'first upload is stored');
poolTestCheck(!empty($second['duplicate']) && $second['existing'] === $first['filename'],
	're-uploading the same bytes is refused and names the file already in the pool');
poolTestCheck(strpos($second['message'], 'ligger allerede i puljen') !== false,
	'the refusal message names the pool document');
poolTestCheck(count(poolTestFiles($poolDir)) === 1 && count(poolTestRows($created)) === 1,
	'one file and one row, no silent second copy');

// 2. Same name, different bytes: must still be stored as the next free name.
$different = poolUploadIngest($pdfB, 'pooltest_faktura.pdf', $poolDir, false);
$created[] = ifset($different, 'filename', '');
poolTestCheck(!empty($different['success']) && $different['filename'] === 'pooltest_faktura_1.pdf',
	'a different document with the same name gets the next free name');
poolTestCheck(count(poolTestRows($created)) === 2, 'two rows for two different documents');

// 3. Hashes: the original bytes and the stored file are both recorded.
$rows = poolTestRows($created);
$originalHash = hash_file('sha256', $pdfA);
poolTestCheck(isset($rows[$first['filename']]['source_sha256']) && $rows[$first['filename']]['source_sha256'] === $originalHash,
	'the original upload hash is stored next to the stored file hash');
poolTestCheck($rows[$first['filename']]['content_sha256'] === $originalHash,
	'a plain PDF stores the same hash for content and source');

// 4. AI scan on: extraction may fail on a machine without an API key, the upload must still land.
$ai = poolUploadIngest($pdfC, 'pooltest_ai.pdf', $poolDir, true);
$created[] = ifset($ai, 'filename', '');
poolTestCheck(!empty($ai['success']), 'an upload survives a failing invoice extraction');
poolTestCheck(!empty(poolUploadIngest($pdfC, 'pooltest_ai_again.pdf', $poolDir, false)['duplicate']),
	'the AI path stores an identity the next upload can be matched against');

// 5. A row without hashes is matched by the file in the pool, and a hash whose file is gone does not
//    block anything.
$legacy = 'pooltest_legacy.pdf';
file_put_contents($poolDir . '/' . $legacy, file_get_contents($pdfA));
db_modify("INSERT INTO pool_files (filename, subject) VALUES ('" . db_escape_string($legacy) . "', 'legacy')", __FILE__ . ' line ' . __LINE__);
$created[] = $legacy;
poolTestCheck(!empty(poolUploadIngest($poolDir . '/' . $legacy, 'pooltest_legacy_copy.pdf', $poolDir, false)['duplicate']),
	'a row without a stored hash is still recognised by its file content');
$pdfDHash = hash_file('sha256', $pdfD);
$stale = 'pooltest_stale.pdf';
db_modify("INSERT INTO pool_files (filename, content_sha256) VALUES ('" . db_escape_string($stale) . "', '" . db_escape_string($pdfDHash) . "')", __FILE__ . ' line ' . __LINE__);
$created[] = $stale;
$afterStale = poolUploadIngest($pdfD, 'pooltest_after_stale.pdf', $poolDir, false);
$created[] = ifset($afterStale, 'filename', '');
poolTestCheck(!empty($afterStale['success']),
	'a stored hash whose row has no file on disk does not block the upload');

// 6. A converted image keeps the identity of what was uploaded.
$image = poolUploadIngest($png, 'pooltest_photo.png', $poolDir, false);
$created[] = ifset($image, 'filename', '');
$imageRows = poolTestRows(array(ifset($image, 'filename', '')));
poolTestCheck(!empty($image['success']) && substr((string)ifset($image, 'filename', ''), -4) === '.pdf',
	'an uploaded image is converted to a PDF in the pool');
poolTestCheck(ifset($imageRows, $image['filename'], array('source_sha256' => ''))['source_sha256'] === hash_file('sha256', $png),
	'the image identity is the uploaded bytes, not the converted PDF');
poolTestCheck(ifset($imageRows, $image['filename'], array('content_sha256' => ''))['content_sha256'] === hash_file('sha256', $poolDir . '/' . $image['filename']),
	'the stored hash is the file that ended up in the pool');
poolTestCheck(!empty(poolUploadIngest($png, 'pooltest_photo_again.png', $poolDir, false)['duplicate']),
	'the same image is refused even though the converted PDF differs');

// 7. XML keeps its extension, so local extraction still recognises it.
$xmlResult = poolUploadIngest($xml, 'pooltest_invoice.xml', $poolDir, false);
$created[] = ifset($xmlResult, 'filename', '');
poolTestCheck(!empty($xmlResult['success']) && substr((string)ifset($xmlResult, 'filename', ''), -4) === '.xml',
	'an uploaded XML keeps its extension in the pool');

// 8. Every ingress must resolve the same pool folder. The REST attachment endpoint used to hardcode
//    bilag, which on an owncloud or documents installation put its uploads in a folder the pool page
//    never looks at - the same bilag could then be stored twice without being recognised.
require_once __DIR__ . '/../includes/docsIncludes/poolPaths.php';
require_once __DIR__ . '/../restapi/models/attachment/AttachmentModel.php';
$baseDirMethod = new ReflectionMethod('AttachmentModel', 'getBaseUploadDir');
$baseDirMethod->setAccessible(true);
$restPoolDir = rtrim(str_replace('\\', '/', $baseDirMethod->invoke(null)), '/') . '/' . $db . '/pulje';
poolTestCheck($restPoolDir === str_replace('\\', '/', poolPuljePath($db)),
	'the REST attachment path and the web upload path resolve the same pulje folder');
// That equality cannot fail on an installation whose folder is bilag, which is exactly the case a
// checkout tends to be, so also read the ingress sources for a hardcoded folder. MB-42's helper
// exists because hardcoding bilag silently found nothing on the other two layouts.
foreach (array('/../restapi/models/attachment/AttachmentModel.php',
	'/../includes/docsIncludes/extractInvoiceHandler.php',
	'/../includes/docsIncludes/poolUpload.php') as $ingressFile) {
	$source = file_get_contents(__DIR__ . $ingressFile);
	poolTestCheck(strpos($source, "'/bilag/") === false && strpos($source, '"/bilag/') === false
		&& strpos($source, "'bilag/") === false,
		basename($ingressFile) . ' does not hardcode the bilag document folder');
}

// 9. Concurrency: four processes upload the same bytes at the same time.
$children = array();
$pipes = array();
for ($i = 0; $i < 4; $i++) {
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --ingest '
		. escapeshellarg($tenantDb) . ' ' . escapeshellarg($pdfE) . ' '
		. escapeshellarg('pooltest_concurrent.pdf') . ' ' . escapeshellarg($poolDir);
	$children[$i] = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes[$i]);
}
$results = array();
foreach ($children as $i => $process) {
	$out = stream_get_contents($pipes[$i][1]);
	$err = stream_get_contents($pipes[$i][2]);
	fclose($pipes[$i][1]);
	fclose($pipes[$i][2]);
	$code = proc_close($process);
	$decoded = json_decode(trim($out), true);
	if (!$decoded) {
		$failures[] = "concurrent upload $i produced no result (exit $code): " . trim($err . ' ' . $out);
	}
	$results[] = is_array($decoded) ? $decoded : array();
}
$stored = 0;
$refused = 0;
foreach ($results as $result) {
	if (!empty($result['success'])) {
		$stored++;
		$created[] = $result['filename'];
	}
	if (!empty($result['duplicate'])) {
		$refused++;
	}
}
poolTestCheck($stored === 1 && $refused === 3, "four simultaneous uploads store one document and refuse three (stored=$stored refused=$refused)");
$concurrentFiles = array();
foreach (poolTestFiles($poolDir) as $file) {
	if (strpos($file, 'pooltest_concurrent') === 0) {
		$concurrentFiles[] = $file;
	}
}
poolTestCheck(count($concurrentFiles) === 1, 'simultaneous uploads leave exactly one file in the pool');

// ---- cleanup ---------------------------------------------------------------------------
foreach (array_unique($created) as $name) {
	if (strpos($name, 'pooltest') === 0) {
		db_modify("DELETE FROM pool_files WHERE filename = '" . db_escape_string($name) . "'", __FILE__ . ' line ' . __LINE__);
	}
}
foreach ((array)scandir($poolDir) as $leftover) {
	if ($leftover !== '.' && $leftover !== '..' && is_file($poolDir . '/' . $leftover)) {
		unlink($poolDir . '/' . $leftover);
	}
}
rmdir($poolDir);
foreach (array($pdfA, $pdfB, $pdfC, $pdfD, $pdfE, $png, $xml) as $temporary) {
	if (is_file($temporary)) {
		unlink($temporary);
	}
}

echo "\n" . ($failures ? count($failures) . " of $checks checks FAILED\n" : "all $checks checks passed (" . $tenantDb . ")\n");
exit($failures ? 1 : 0);
