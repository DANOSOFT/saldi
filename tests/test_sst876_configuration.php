<?php
// Copyright (c) 2026 Danosoft ApS
// 20261027 CDX/MJ SST-876 Verify no-webshop requests avoid queue I/O and transactions defer backlog delivery.
// 20261027 CDX/MJ SST-876 Assert document stock is deferred until commit rather than published inside the transaction.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$GLOBALS['configuration'] = false;
$GLOBALS['configurationReads'] = 0;
$GLOBALS['queueAccesses'] = 0;
$GLOBALS['delivered'] = array();
$fixture = sys_get_temp_dir() . '/sst876config-' . bin2hex(random_bytes(8)) . '/';
mkdir($fixture, 0700, true);

function shopSyncQueueDir($create = true) {
	$GLOBALS['queueAccesses']++;
	return $GLOBALS['fixture'];
}

function db_select($sql, $location = '') {
	if (strpos($sql, "select box4 from grupper where art='API'") !== false) {
		$GLOBALS['configurationReads']++;
		return $GLOBALS['configuration'];
	}
	if (strpos($sql, 'select indgaar_i from styklister') !== false) {
		return false;
	}
	throw new RuntimeException('Unexpected fixture query: ' . $sql);
}

function db_fetch_array($row) {
	return $row;
}

function sync_shop_vare($id, $variant, $warehouse, $mode = 'all') {
	$GLOBALS['delivered'][] = (int)$id;
	return 'OK';
}

require_once __DIR__ . '/../includes/std_func.php';
function check($ok, $message) {
	if (!$ok) {
		throw new RuntimeException($message);
	}
	echo "PASS: $message\n";
}

try {
	$db = 'sst876_no_api_fixture';
	// An earlier configured request may have left work. No API must not scan or remove it.
	shopSyncQueueAdd(77, 0, 4, 'stock');
	$marker = $fixture . shopSyncQueueKey(77, 0, 4);
	$original = file_get_contents($marker);
	$GLOBALS['queueAccesses'] = 0;
	for ($id = 1; $id <= 50; $id++) {
		shopSyncMark($id, 0, 4);
	}
	check(shopSyncMarkedCount() === 0, 'Without a webshop, document lines are not marked or queued');
	check(shopSyncFlush() === 'no api' && shopSyncDrainQueue() === 0, 'Without a webshop, flush and drain return immediately');
	check($GLOBALS['configurationReads'] === 1, 'The API configuration is checked once per tenant/request');
	check($GLOBALS['queueAccesses'] === 0, 'No-webshop requests perform no queue directory reads or writes');
	check(file_get_contents($marker) === $original, 'Existing retry work is untouched while configuration is disabled');

	$db = 'sst876_configured_fixture';
	$GLOBALS['configuration'] = array('box4' => 'http://shop.test/adapter');
	shopSyncMark(123, 0, 4);
	check(shopSyncMarkedCount() === 1, 'A configured webshop still queues document items');
	$GLOBALS['db_transaktion_depth'] = 1;
	$GLOBALS['queueAccesses'] = 0;
	check(shopSyncDrainQueue() === 0 && $GLOBALS['queueAccesses'] === 0, 'A retry drain never scans the queue inside a transaction');
	check(shopSyncFlush() === 'OK', 'The transactional flush safely defers work until commit');
	check($GLOBALS['delivered'] === array() && shopSyncMarkedCount() === 1, 'An open transaction sends neither uncommitted document figures nor old backlog');
	check(is_file($marker), 'The old marker remains queued until commit');
	$GLOBALS['db_transaktion_depth'] = 0;
	check(shopSyncDrainQueue() === 2 && $GLOBALS['delivered'] === array(123, 77), 'After commit the backlog sends successfully');
	check(!is_file($marker), 'Delivered backlog is removed only after its post-commit send');
	check($GLOBALS['configurationReads'] === 2, 'A different tenant has an independent cached configuration');
} finally {
	foreach (glob($fixture . '*') as $file) {
		unlink($file);
	}
	rmdir($fixture);
}
