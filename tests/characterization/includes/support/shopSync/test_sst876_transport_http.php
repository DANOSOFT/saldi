<?php
// Copyright (c) 2026 Danosoft ApS
// 20261027 CDX/MJ SST-876 Verify actual parallel and shell-cURL transfer completion, including partial HTTP 200 responses.
// 20261028 CDX/MJ SST-876 Relocate this isolated fixture under the CI-discovered regression suite.
set_exception_handler(function ($error) {
	fwrite(STDERR, (string)$error . "\n");
	exit(1);
});
require_once dirname(__DIR__, 5) . '/includes/stdFunc/shopApiRequest.php';
if (!function_exists('curl_multi_init') || !function_exists('shell_exec')) {
	echo "SKIP: the transport regression requires ext-curl and shell_exec.\n";
	exit(0);
}
$server = proc_open(array('python3', dirname(__DIR__, 4) . '/support/shop_sync_fault_mock.py'),
	array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => STDERR), $pipes);
if (!is_resource($server)) {
	throw new RuntimeException('Could not start the loopback transfer mock.');
}
register_shutdown_function(function () use ($server, $pipes) {
	proc_terminate($server);
	foreach ($pipes as $pipe) {
		fclose($pipe);
	}
	proc_close($server);
});
$port = trim((string)fgets($pipes[1]));
if (!ctype_digit($port)) {
	throw new RuntimeException('The transfer mock did not start.');
}
$base = 'http://127.0.0.1:' . $port;
$urls = array();
foreach (array('ok', 'partial', 'timeout', 'empty', 'refuse', 'unavailable') as $path) {
	$urls[$path] = $base . '/' . $path;
}

/**
 * Assert a transport outcome without accepting incomplete HTTP 200 responses.
 *
 * @param array $call Transport result.
 * @param string $path Mock scenario.
 * @param string $transport Transport label.
 * @return void
 */
function checkTransfer($call, $path, $transport) {
	$outcome = shopApiOutcome($call);
	$accepted = in_array($path, array('ok', 'empty'), true);
	if (($outcome['state'] === 'ok') !== $accepted) {
		throw new RuntimeException($transport . ' classified ' . $path . ' incorrectly: ' . json_encode($call));
	}
	if (in_array($path, array('partial', 'timeout'), true) &&
		($call['httpCode'] !== 200 || $call['error'] === '' || !$call['transient'] || $call['unreachable'])) {
		throw new RuntimeException($transport . ' lost the partial-transfer error: ' . json_encode($call));
	}
	echo 'PASS: ', $transport, ' ', $path, "\n";
}
foreach (shopApiCurlMultiCall($urls, 1, 1, 'SST-876 regression') as $path => $call) {
	checkTransfer($call, $path, 'parallel cURL');
}
foreach ($urls as $path => $url) {
	checkTransfer(shopApiShellCall($url, array('timeout' => 1, 'connectTimeout' => 1, 'userAgent' => 'SST-876 regression')),
		$path, 'shell cURL');
}

// Exercise the real collector and queue as well as the low-level response classification.
putenv('SALDI_SHOP_FIXTURE_ONLY=1');
putenv('SALDI_SHOP_REAL_TRANSPORT=1');
require __DIR__ . '/test_sst876_item_fanout.php';
$SHOPS = array('box4' => $base . '/ok', 'box5' => $base . '/partial', 'box6' => $base . '/ok');
$single = shopApiRequest($base . '/partial', array(), null, array('retries' => 0, 'timeout' => 1));
check(!$single['ok'] && $single['httpCode'] === 200 && shopSyncWorthRetrying($single),
	'A single-handle HTTP 200 transfer failure also stays retryable');
$refused = shopApiRequest($base . '/refuse', array(), null, array('retries' => 0, 'timeout' => 1));
check(!$refused['ok'] && !shopSyncWorthRetrying($refused), 'An answered refusal is still reported without automatic retry');
try {
	reset_fanout();
	shopSyncMark(3580, 0, 4);
	check(shopSyncFailed(shopSyncFlush(0)), 'A partial HTTP 200 makes the document report failure');
	check(shopSyncQueueCount() === 1, 'The item stays queued after a partial HTTP 200');
	$SHOPS['box5'] = $base . '/ok';
	reset_fanout(false);
	check(shopSyncDrainQueue() === 1 && shopSyncQueueCount() === 0, 'A later complete response delivers and removes the pending item');
} finally {
	reset_fanout();
}
