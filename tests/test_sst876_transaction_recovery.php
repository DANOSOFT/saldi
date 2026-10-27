<?php
// Copyright (c) 2026 Danosoft ApS
// 20261027 CDX/MJ SST-876 Verify commit/rollback stock visibility and recovery for direct transactional sends.
putenv('SALDI_SHOP_FIXTURE_ONLY=1');
require __DIR__ . '/test_sst876_item_fanout.php';
set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) {
		return false;
	}
	throw new ErrorException($message, 0, $severity, $file, $line);
});

/**
 * Verify every emitted stock update uses the committed fixture figure.
 *
 * @param string $expected Committed stock.
 * @return int Number of stock requests.
 */
function checkCommittedStock($expected) {
	$count = 0;
	foreach ($GLOBALS['shopRounds'] as $round) {
		foreach ($round as $url) {
			parse_str(parse_url($url, PHP_URL_QUERY), $params);
			if (isset($params['update_stock'])) {
				check((string)$params['stock'] === (string)$expected && (string)$params['totalStock'] === (string)$expected,
				'Committed stock reaches the shop for ' . ($params['itemNo'] ?? $params['update_stock']));
				$count++;
			}
		}
	}
	return $count;
}

try {
	foreach (array('commit' => '67', 'rollback' => '68', 'nested commit' => '67') as $outcome => $committed) {
		reset_fanout();
		$GLOBALS['fixtureStock'] = '67';
		$GLOBALS['db_transaktion_depth'] = $outcome === 'nested commit' ? 2 : 1;
		shopSyncMark(3580, 0, 4);
		check(shopSyncFlush(0) === 'OK', 'An open transaction defers its document for ' . $outcome);
		check($GLOBALS['shopRounds'] === array(), 'No uncommitted stock is published before ' . $outcome);
		check(shopSyncQueueCount() === 1 && shopSyncMarkedCount() === 1, 'The deferred document remains recoverable');
		if ($outcome === 'nested commit') {
			$GLOBALS['db_transaktion_depth'] = 1;
			check(shopSyncDrainQueue() === 0 && $GLOBALS['shopRounds'] === array(), 'An inner commit never publishes the outer transaction');
		}
		if ($outcome === 'rollback') {
			// A later PHP request has no in-memory marks, but the durable marker must survive.
			$marks = &shopSyncMarkState();
			$marks['items'] = array();
		}
		$GLOBALS['fixtureStock'] = $committed;
		$GLOBALS['db_transaktion_depth'] = 0;
		shopSyncDrainQueue();
		check(checkCommittedStock($committed) === 3, 'Exactly one stock update per shop follows ' . $outcome);
		check(shopSyncQueueCount() === 0 && shopSyncMarkedCount() === 0, 'Successful post-transaction delivery empties the queue');
	}

	// A low-level caller bypassing shopSyncFlush() must still leave correction work after success.
	reset_fanout();
	$GLOBALS['fixtureStock'] = '67';
	$GLOBALS['db_transaktion_depth'] = 1;
	shopSyncMark(123, 0, 4, 'all');
	$state = &shopSyncMarkState();
	$entries = array_values($state['items']);
	$state['items'] = array();
	$result = shopSyncRunDocument($entries);
	check($result['failed'] === 0 && count($GLOBALS['shopRounds']) > 0, 'A direct low-level send succeeds inside the synthetic transaction');
	check(shopSyncQueueCount() === 34, 'Successful direct sends retain the component and all 33 parent-set correction markers');
	foreach (shopSyncQueuePending() as $entry) {
		check($entry['attempts'] === 0 && $entry['mode'] === 'all' && $entry['lager'] === 4,
			'Correction work retains full mode and warehouse without consuming an attempt');
	}
	// Simulate the caller rolling back, then a later request draining committed figures.
	$GLOBALS['fixtureStock'] = '68';
	foreach ($SETS as $set) {
		$GLOBALS['setAvailability'][$set] = '68|68';
	}
	$GLOBALS['shopRounds'] = array();
	$GLOBALS['db_transaktion_depth'] = 0;
	check(shopSyncDrainQueue() === 34, 'A later request delivers every component and set correction');
	check(checkCommittedStock('68') === 102, 'All 34 corrected figures reach all three shops');
	check(shopSyncQueueCount() === 0, 'Recovery removes markers only after the committed figures are sent');
} finally {
	$GLOBALS['db_transaktion_depth'] = 0;
	reset_fanout();
	unset($GLOBALS['fixtureStock']);
}
