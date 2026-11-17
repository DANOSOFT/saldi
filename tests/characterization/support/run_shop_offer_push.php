<?php
// tests/characterization/support/run_shop_offer_push.php
//
// Child-process runner for ShopOfferPushTest: runs the webshop offer push (includes/stdFunc/shopOfferPush.php) or the item
// card's price calls against a test tenant, with the shops configured to the fake endpoint the test started.
//
// argv: 1 = tenant database
//       2 = scenario: tick | sync_price | sync_vare
//       3 = tick: unix time to evaluate at ('now' for the clock); sync_price / sync_vare: varer.id
//       4 = tick: 'force' or '-'; sync_vare: warehouse number
//       5 = tick: most items (default 10)
//       6 = tick: most seconds (default 3)
//       7 = tick: comma-separated varer.id values to send first (optional)
//
// Prints one JSON object on the last line of stdout.
//
// History:
// 20261117 CL/LH Created for the webshop offer push.
if ($argc < 4) {
	fwrite(STDERR, "usage: php run_shop_offer_push.php <tenant_db> <tick|sync_price|sync_vare> <args...>\n");
	exit(2);
}
$tenantDb = $argv[1];
$scenario = $argv[2];
require __DIR__ . '/bootstrap_ordrefunc.php';
error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
ob_end_clean();
if ($scenario === 'tick') {
	$options = array(
		'now' => $argv[3] === 'now' ? time() : (int)$argv[3],
		'force' => ($argv[4] ?? '-') === 'force',
		'maxItems' => (int)($argv[5] ?? 10),
		'maxSeconds' => (float)($argv[6] ?? 3),
		'prefer' => isset($argv[7]) ? array_map('intval', explode(',', $argv[7])) : array(),
	);
	$out = shopOfferPushTick($options);
} elseif ($scenario === 'sync_price') {
	$out = array('result' => sync_shop_price((int)$argv[3]));
} elseif ($scenario === 'sync_vare') {
	$out = array('result' => sync_shop_vare((int)$argv[3], 0, (int)($argv[4] ?? 1), 'all'));
} else {
	fwrite(STDERR, "unknown scenario $scenario\n");
	exit(2);
}
fwrite(STDOUT, json_encode($out) . "\n");
