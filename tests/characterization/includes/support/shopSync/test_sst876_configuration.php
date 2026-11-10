<?php
// Copyright (c) 2026 Danosoft ApS
// 20261027 CDX/MJ SST-876 Verify no-webshop requests avoid queue I/O and transactions defer backlog delivery.
// 20261027 CDX/MJ SST-876 Assert document stock is deferred until commit rather than published inside the transaction.
// 20261028 CDX/MJ SST-876 Relocate this isolated fixture under the CI-discovered regression suite.
// 20261110 CL/SZ Sync of master into hml/shopify: shopSyncApiConfigured() reads box4, box5 and box6 (SD-750), so the stub answers that query.
// 20261110 CL/SZ SD-817 The stub answers the shopify_legacy outbound settings.
//                A tenant whose slots are all closed queues nothing, and one open slot still queues.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$GLOBALS['configuration'] = false;
$GLOBALS['configurationReads'] = 0;
$GLOBALS['queueAccesses'] = 0;
$GLOBALS['delivered'] = array();
$GLOBALS['legacySettings'] = array();   // slot => array(var_name => var_value), as settings holds them
$fixture = sys_get_temp_dir() . '/sst876config-' . bin2hex(random_bytes(8)) . '/';
mkdir($fixture, 0700, true);

function shopSyncQueueDir($create = true) {
	$GLOBALS['queueAccesses']++;
	return $GLOBALS['fixture'];
}

function db_select($sql, $location = '') {
	if (strpos($sql, "select box4, box5, box6 from grupper where art='API'") !== false) {
		$GLOBALS['configurationReads']++;
		return $GLOBALS['configuration'];
	}
	if (preg_match("/from settings where var_grp = 'shopify_legacy:([456])'/", $sql, $m)) {
		$rows = array();
		foreach ($GLOBALS['legacySettings'][(int)$m[1]] ?? array() as $name => $value) {
			$rows[] = array('var_name' => $name, 'var_value' => $value);
		}
		return new ArrayIterator($rows);
	}
	if (strpos($sql, 'select indgaar_i from styklister') !== false) {
		return false;
	}
	throw new RuntimeException('Unexpected fixture query: ' . $sql);
}

function db_fetch_array($row) {
	if ($row instanceof ArrayIterator) {
		if (!$row->valid()) {
			return false;
		}
		$current = $row->current();
		$row->next();
		return $current;
	}
	return $row;
}

function sync_shop_vare($id, $variant, $warehouse, $mode = 'all') {
	$GLOBALS['delivered'][] = (int)$id;
	return 'OK';
}

require_once dirname(__DIR__, 5) . '/includes/std_func.php';
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

	// SD-817: a tenant that has cut over still has its URLs, but outbound is closed on every slot.
	$db = 'sd817_all_closed_fixture';
	$GLOBALS['configuration'] = array('box4' => 'http://shop.test/adapter', 'box5' => 'http://shop5.test/adapter');
	$GLOBALS['legacySettings'] = array(4 => array('outbound_enabled' => '0'), 5 => array('outbound_enabled' => 'off'));
	shopSyncQueueAdd(88, 0, 4, 'stock');
	$closedMarker = $fixture . shopSyncQueueKey(88, 0, 4);
	$closedOriginal = file_get_contents($closedMarker);
	$GLOBALS['queueAccesses'] = 0;
	$GLOBALS['delivered'] = array();
	for ($id = 1; $id <= 50; $id++) {
		shopSyncMark($id, 0, 4);
	}
	check(shopSyncMarkedCount() === 0, 'With outbound closed on every slot, document lines are not marked or queued');
	check(shopSyncFlush() === 'no api' && shopSyncDrainQueue() === 0, 'With outbound closed on every slot, flush and drain return immediately');
	check($GLOBALS['queueAccesses'] === 0 && $GLOBALS['delivered'] === array(), 'With outbound closed on every slot, the queue is not touched and nothing is sent');
	check(file_get_contents($closedMarker) === $closedOriginal, 'A marker left from before the cutover is not sent while outbound is closed');
	unlink($closedMarker);

	$db = 'sd817_one_open_fixture';
	$GLOBALS['configuration'] = array('box4' => 'http://shop.test/adapter', 'box5' => 'http://shop5.test/adapter');
	$GLOBALS['legacySettings'] = array(4 => array('outbound_enabled' => '0'));   // slot 5 has no row, so it is open
	shopSyncMark(124, 0, 4);
	check(shopSyncMarkedCount() === 1, 'One slot still open for outbound keeps queueing');
	check(shopSyncFlush() === 'OK' && $GLOBALS['delivered'] === array(124), 'One slot still open for outbound sends as before');
	check($GLOBALS['configurationReads'] === 4, 'Each tenant reads its configuration once per request');
} finally {
	foreach (glob($fixture . '*') as $file) {
		unlink($file);
	}
	rmdir($fixture);
}
