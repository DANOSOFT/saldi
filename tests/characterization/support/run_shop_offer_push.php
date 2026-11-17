<?php
// tests/characterization/support/run_shop_offer_push.php
//
// Child-process runner for ShopOfferPushTest: runs the webshop offer push (includes/stdFunc/shopOfferPush.php) or the item
// card's and the sale's price calls against a test tenant, with the shops configured to the fake endpoint the test started.
//
// argv: 1 = tenant database
//       2 = scenario, then its arguments:
//           tick <unix|now> <force|-> [maxItems] [maxSeconds] [prefer ids, comma-separated]
//           sync_price <vare_id>               sync_shop_price(), as an item card save outside a stocked group
//           sync_vare <vare_id> <lager> [mode] sync_shop_vare(), 'all' as a card save, 'stock' as a sale
//           schedule <unix>                    shopOfferPushSchedule()
//           due                                shopOfferPushDueAt()
//           state <vare_id>                    shopOfferItemLoad()
//           drain                              shopSyncDrainQueue(), the call after a sale, an invoice or a receipt commits
//
// Prints one JSON object on the last line of stdout, with the elapsed seconds.
//
// History:
// 20261117 CL/LH Created for the webshop offer push.
//                Codex verification: sale mode, scheduling, the due time, an item's state and the drain, with timing.
if ($argc < 3) {
	fwrite(STDERR, "usage: php run_shop_offer_push.php <tenant_db> <scenario> <args...>\n");
	exit(2);
}
$tenantDb = $argv[1];
$scenario = $argv[2];
require __DIR__ . '/bootstrap_ordrefunc.php';
error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
ob_end_clean();
$started = microtime(true);
if ($scenario === 'tick') {
	$options = array(
		'now' => ($argv[3] ?? 'now') === 'now' ? time() : (int)$argv[3],
		'force' => ($argv[4] ?? '-') === 'force',
		'maxItems' => (int)($argv[5] ?? 10),
		'maxSeconds' => (float)($argv[6] ?? 3),
		'prefer' => isset($argv[7]) ? array_map('intval', explode(',', $argv[7])) : array(),
	);
	$out = shopOfferPushTick($options);
} elseif ($scenario === 'sync_price') {
	$out = array('result' => sync_shop_price((int)$argv[3]));
} elseif ($scenario === 'sync_vare') {
	$out = array('result' => sync_shop_vare((int)$argv[3], 0, (int)($argv[4] ?? 1), $argv[5] ?? 'all'));
} elseif ($scenario === 'schedule') {
	shopOfferPushSchedule((int)$argv[3]);
	$out = array('due' => shopOfferPushDueAt());
} elseif ($scenario === 'due') {
	$out = array('due' => shopOfferPushDueAt());
} elseif ($scenario === 'state') {
	$out = array('state' => function_exists('shopOfferItemLoad') ? shopOfferItemLoad((int)$argv[3]) : null);
} elseif ($scenario === 'drain') {
	$out = array('delivered' => shopSyncDrainQueue());
} else {
	fwrite(STDERR, "unknown scenario $scenario\n");
	exit(2);
}
$out['elapsed'] = round(microtime(true) - $started, 3);
fwrite(STDOUT, json_encode($out) . "\n");
