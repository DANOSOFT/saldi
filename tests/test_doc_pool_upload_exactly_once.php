<?php
/**
 * Focused regression checks for the drag/drop upload callback in docPool.php.
 * Run: php tests/test_doc_pool_upload_exactly_once.php
 */

$passed = 0;
$failed = 0;

function check($condition, $message) {
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "PASS  $message\n";
	} else {
		$failed++;
		echo "FAIL  $message\n";
	}
}

$source = file_get_contents(__DIR__ . '/../includes/docsIncludes/docPool.php');
$start = strpos($source, 'function uploadFiles(files)');
$end = strpos($source, "document.addEventListener('DOMContentLoaded'", $start);
$uploadBlock = $start !== false && $end !== false ? substr($source, $start, $end - $start) : '';

check($uploadBlock !== '', 'isolates the drag/drop upload callback');
check(strpos($uploadBlock, "const extracted = data.extracted;") !== false, 'reuses extracted data returned by upload');
check(strpos($uploadBlock, "extractFormData.append('action', 'extract')") === false, 'does not issue a second extract action from upload');
check(strpos($uploadBlock, "svData.append('action', 'save')") !== false, 'sends the save action');
check(strpos($uploadBlock, "svData.append('docFolder', '\$docFolder')") !== false, 'forwards docFolder during save');
check(strpos($uploadBlock, "svData.append('newCurrency', extracted.currency)") !== false, 'forwards extracted currency during save');
check(strpos($uploadBlock, "svData.append('poolFile', data.filename)") !== false, 'saves using the final upload filename');
check(strpos($uploadBlock, 'if (!saveResponse.ok)') !== false, 'rejects an unsuccessful metadata save response');
check(strpos($uploadBlock, 'if (!saveResult.success)') !== false, 'rejects a metadata save error payload');
check(strpos($uploadBlock, 'uploadedCount++;') > strpos($uploadBlock, 'if (!saveResult.success)'), 'counts upload success only after metadata is saved');
check(strpos($uploadBlock, 'automatic extraction returned no result; skipping save') !== false, 'logs and skips save when upload returns no extraction result');

echo "\nResults: $passed passed, $failed failed\n";
exit($failed ? 1 : 0);
