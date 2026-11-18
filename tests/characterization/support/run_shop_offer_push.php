<?php
// tests/characterization/support/run_shop_offer_push.php
//
// Child-process runner for ShopOfferPushTest: runs the webshop offer reconciler (includes/stdFunc/shopOfferPush.php), the item
// card's and the sale's price calls, or api/rest_api.php itself against a test tenant whose shops are the fake endpoint the test
// started.
//
// argv: 1 = tenant database
//       2 = scenario, then its arguments:
//           tick <unix|now> [budget] [maxItems]    shopOfferReconcileTick()
//           sync_price <vare_id>                   sync_shop_price(), as an item card save outside a stocked group
//           sync_vare <vare_id> <lager> [mode]     sync_shop_vare() called directly, 'all' as a card save, 'stock' as a sale
//           sale <vare_id> <lager>                 a committed sale: shopSyncMark() and shopSyncDrainQueue() (the document path)
//           state <vare_id>                        the item's state (shopOfferStateRead())
//           hold <seconds> <lock file>             holds a lock file of the state directory (tick.lock, lock-N) exclusively;
//                                                  .held-<lock file> exists while it does
//           locktable <seconds> <table> <marker>   holds an exclusive lock on a table of the tenant; <marker> exists while it does
//           rest <query string> [post body]        api/rest_api.php with that query (and that POST body), from 127.0.0.1
//
// Prints one JSON object on the last line of stdout, with the elapsed seconds.
//
// History:
// 20261117 CL/LH Created for the webshop offer push.
//                Codex verification: sale mode, scheduling, the due time, an item's state and the drain, with timing.
//                Single-writer rebuild: the reconciler's tick, a committed sale, holding a lock, and api/rest_api.php.
//                Re-send rebuild: holding a table lock, and a POST body for api/rest_api.php.
// 20261118 CL/LH CodeRabbit review of #1157: the table lock's marker appears only once the lock is held.
if ($argc < 3) {
	fwrite(STDERR, "usage: php run_shop_offer_push.php <tenant_db> <scenario> <args...>\n");
	exit(2);
}
$tenantDb = $argv[1];
$scenario = $argv[2];
$started = microtime(true);
if ($scenario === 'rest') {
	parse_str((string)($argv[3] ?? ''), $_GET);
	parse_str((string)($argv[4] ?? ''), $_POST);
	$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
	$_SERVER['REQUEST_URI'] = '/saldi/api/rest_api.php';
	$_SERVER['SERVER_PORT'] = 80;
	$_SERVER['HTTP_HOST'] = '127.0.0.1';
	chdir(dirname(__DIR__, 3) . '/api');
	ob_start();
	register_shutdown_function(function () use ($started) { // rest_api.php ends with exit(json_encode($value))
		$body = (string)ob_get_clean();
		$decoded = json_decode($body, true);
		fwrite(STDOUT, "\n" . json_encode(array('response' => $decoded === null ? $body : $decoded, 'elapsed' => round(microtime(true) - $started, 3))) . "\n");
	});
	include 'rest_api.php';
	exit(0);
}
require __DIR__ . '/bootstrap_ordrefunc.php';
error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
ob_end_clean();
if ($scenario === 'tick') {
	$options = array('now' => ($argv[3] ?? 'now') === 'now' ? time() : (int)$argv[3]);
	if (isset($argv[4])) $options['budget'] = (float)$argv[4];
	if (isset($argv[5])) $options['maxItems'] = (int)$argv[5];
	$out = shopOfferReconcileTick($options);
} elseif ($scenario === 'sync_price') {
	$out = array('result' => sync_shop_price((int)$argv[3]));
} elseif ($scenario === 'sync_vare') {
	$out = array('result' => sync_shop_vare((int)$argv[3], 0, (int)($argv[4] ?? 1), $argv[5] ?? 'all'));
} elseif ($scenario === 'sale') {
	shopSyncMark((int)$argv[3], 0, (int)($argv[4] ?? 1), 'stock');
	$out = array('delivered' => shopSyncDrainQueue());
} elseif ($scenario === 'state') {
	$out = array('state' => shopOfferStateRead((int)$argv[3]));
} elseif ($scenario === 'hold') {
	$dir = shopOfferPushDir(true);
	$fh = fopen($dir . basename((string)$argv[4]), 'c');
	flock($fh, LOCK_EX);
	touch($dir . '.held-' . basename((string)$argv[4])); // tells the test the lock is held
	usleep((int)((float)$argv[3] * 1000000));
	unlink($dir . '.held-' . basename((string)$argv[4]));
	flock($fh, LOCK_UN);
	fclose($fh);
	$out = array('held' => (float)$argv[3]);
} elseif ($scenario === 'locktable') {
	$table = preg_replace('/[^a-z_]/', '', (string)$argv[4]);
	if (@pg_query($connection, 'BEGIN') === false || @pg_query($connection, "LOCK TABLE $table IN ACCESS EXCLUSIVE MODE") === false) {
		$error = pg_last_error($connection);
		@pg_query($connection, 'ROLLBACK');
		fwrite(STDERR, "could not lock $table: $error\n"); // no marker: the test sees the lock was never held
		exit(1);
	}
	touch((string)$argv[5]); // the lock is held
	usleep((int)((float)$argv[3] * 1000000));
	unlink((string)$argv[5]);
	pg_query($connection, 'COMMIT');
	$out = array('held' => (float)$argv[3], 'table' => $table);
} else {
	fwrite(STDERR, "unknown scenario $scenario\n");
	exit(2);
}
$out['elapsed'] = round(microtime(true) - $started, 3);
fwrite(STDOUT, json_encode($out) . "\n");
