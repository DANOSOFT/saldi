<?php
/**
 * Focused tests for includes/docsIncludes/invoiceExtractionApi.php.
 * Run: php tests/test_invoice_extraction_api.php
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

function db_select($query, $source, $global = false) {
	global $dbSelectCalls, $dbSelectResult;
	$dbSelectCalls[] = array($query, $source, $global);
	return $dbSelectResult;
}

function db_fetch_array($query) {
	return $query;
}

include __DIR__ . '/../includes/docsIncludes/invoiceExtractionApi.php';

$tempDir = sys_get_temp_dir() . '/saldi_invoice_extraction_' . uniqid();
mkdir($tempDir);
$pdfPath = $tempDir . '/invoice.pdf';
$pdfBytes = "%PDF-1.7\npage-one\fpage-two\n%%EOF";
file_put_contents($pdfPath, $pdfBytes);

$dbSelectCalls = array();
$dbSelectResult = array('var_value' => 'global-key');
check(invoiceExtractionApiResolveApiKey() === 'global-key', 'resolves the global API key');
check(count($dbSelectCalls) === 1 && $dbSelectCalls[0][2] === true, 'uses db_select global connection');
check(strpos($dbSelectCalls[0][0], "var_name = 'apikey'") !== false && strpos($dbSelectCalls[0][0], "var_grp = 'app_api'") !== false, 'queries the app_api key setting');

$captured = array();
$invoiceExtractionApiDependencies = array(
	'key_resolver' => function () { return 'test-key'; },
	'transport' => function ($url, $headers, $body, $options) use (&$captured) {
		$captured = array($url, $headers, json_decode($body, true), $options);
		return array('response' => json_encode(array(
			'status' => 'partial_success',
			'extracted_data' => array('total_amount' => '123.45', 'invoice_date' => '26-01-2026', 'vendor' => 'Acme', 'invoice_number' => 'A-1', 'invoice_description' => 'Widgets', 'currency' => 'DKK')
		)), 'http_code' => 200, 'error' => '', 'errno' => 0);
	}
);
$result = extractInvoiceData($pdfPath, 'invoice-test');
check($captured[0] === 'https://ai.saldi.dk/extract-invoice', 'uses the extraction route');
check(in_array('Authorization: Bearer test-key', $captured[1], true), 'sends bearer authentication');
check($captured[2]['id'] === 'invoice-test' && $captured[2]['skip_classification'] === true, 'sends required payload fields');
check(base64_decode($captured[2]['image']) === $pdfBytes, 'passes original multi-page PDF bytes unchanged');
check($captured[3] === array('connect_timeout' => 10, 'timeout' => 120), 'uses required timeouts');
check($result === array('amount' => '123.45', 'date' => '2026-01-26', 'vendor' => 'Acme', 'invoiceNumber' => 'A-1', 'description' => 'Widgets', 'currency' => 'DKK'), 'accepts partial success and preserves SALDI fields');

$transportCalls = 0;
$invoiceExtractionApiDependencies = array(
	'key_resolver' => function () { return null; },
	'transport' => function () use (&$transportCalls) { $transportCalls++; return array(); }
);
check(extractInvoiceData($pdfPath, 'missing-key') === null && $transportCalls === 0, 'does not call transport without an API key');

foreach (array(
	'HTTP failure' => array('response' => 'gateway unavailable', 'http_code' => 503, 'error' => '', 'errno' => 0),
	'transport failure' => array('response' => false, 'http_code' => 0, 'error' => 'Could not resolve host', 'errno' => 6),
	'timeout' => array('response' => false, 'http_code' => 0, 'error' => 'Operation timed out', 'errno' => 28)
) as $label => $transportResult) {
	$invoiceExtractionApiDependencies = array(
		'key_resolver' => function () { return 'test-key'; },
		'transport' => function () use ($transportResult) { return $transportResult; }
	);
	check(extractInvoiceData($pdfPath, 'failure-test') === null, "returns null for $label");
}

unset($invoiceExtractionApiDependencies);
unlink($pdfPath);
rmdir($tempDir);

echo "\nResults: $passed passed, $failed failed\n";
exit($failed ? 1 : 0);
