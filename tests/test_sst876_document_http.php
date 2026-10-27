<?php
// Copyright (c) 2026 Danosoft ApS
// 20261023 CDX/MJ SST-876 Exercise the real cURL transport against a concurrent delayed HTTP mock.
// 20261027 CDX/MJ SST-876 Exercise deferred delivery after commit without duplicate sends or pending correction markers.
// Starts a loopback-only Python mock, or uses SALDI_SHOP_TEST_URL=http://127.0.0.1:<port>/shop.

$endpoint = getenv('SALDI_SHOP_TEST_URL');
if (!$endpoint) {
	$server = proc_open(array('python3', __DIR__ . '/support/shop_sync_mock.py'),
		array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => STDERR), $pipes);
	if (!is_resource($server)) {
		throw new RuntimeException('Could not start the local mock shop.');
	}
	register_shutdown_function(function () use ($server, $pipes) {
		proc_terminate($server);
		foreach ($pipes as $pipe) {
			fclose($pipe);
		}
		proc_close($server);
	});
	$port = trim((string)fgets($pipes[1]));
	$endpoint = 'http://127.0.0.1:' . $port . '/shop';
}
if (!preg_match('#^http://127\.0\.0\.1:[0-9]+/#', $endpoint)) {
	throw new RuntimeException('The mock shop must be on loopback.');
}
putenv('SALDI_SHOP_REAL_TRANSPORT=1');
putenv('SALDI_SHOP_FIXTURE_ONLY=1');
require __DIR__ . '/test_sst876_item_fanout.php';

$SHOPS = array('box4' => $endpoint . '?shop=1', 'box5' => $endpoint . '?shop=2', 'box6' => $endpoint . '?shop=3');
$document = array(123, 2518, 2519, 526, 5787, 3580);
reset_fanout();
foreach ($document as $i => $id) {
	$parents = array();
	for ($n = 0; $n < ($i === 0 ? 33 : ($i === 5 ? 2 : 25)); $n++) {
		$parent = 20000 + $i * 100 + $n;
		$parents[] = $parent;
		$ITEMS[$parent] = array('varenr' => 'SET' . $parent, 'delvare' => '', 'gruppe' => 1, 'parents' => array());
	}
	$ITEMS[$id] = array('varenr' => 'ITEM' . $id, 'delvare' => 'on', 'gruppe' => 1, 'parents' => $parents);
	shopSyncMark($id, 0, 4);
}
$logPath = __DIR__ . '/../temp/' . $db . '/rest_api.log';
$offset = is_file($logPath) ? filesize($logPath) : 0;
$start = microtime(true);
$GLOBALS['db_transaktion_depth'] = 1;
check(shopSyncFlush(0) === 'OK' && shopSyncMarkedCount() === count($document),
	'The six-line document waits for its transaction to commit');
$GLOBALS['db_transaktion_depth'] = 0;
check(shopSyncDrainQueue() > 0, 'The committed six-line document succeeds through actual parallel HTTP');
$elapsed = microtime(true) - $start;
$log = substr(file_get_contents($logPath), $offset);
foreach ($document as $id) {
	check(substr_count($log, 'shop sync ok sync_shop_vare update_stock vare_id ' . $id . ' ') === 3,
		'Document item ' . $id . ' receives three actual HTTP successes');
}
check(strpos($log, 'SHOP SYNC FAILED') === false, 'Actual delayed HTTP produces no failed or skipped updates');
check(shopSyncFailedItems() === array(), 'Actual delayed HTTP produces no warning');
check(shopSyncQueueCount() === 0, 'All document lines and parent sets leave the queue');
check($elapsed < 15, 'Actual delayed HTTP completes within the budget: ' . round($elapsed, 2) . ' seconds');
echo "Actual HTTP requests: ", substr_count($log, 'shop sync ok'), "\n";
