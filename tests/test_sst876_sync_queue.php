<?php
// Copyright (c) 2026 Danosoft ApS
// 20261022 CL/MJ SST-876 Verify the parallel sender and the queue of undelivered stock updates.
//
// A plain script rather than a phpunit case, because both halves need the transport replaced
// before includes/stdFunc/shopApiRequest.php is read: every function in that file is wrapped
// in if (!function_exists(...)), so a stub defined first wins and the real sender runs against
// it unchanged. Nothing here touches the network or a database.

// ---------------------------------------------------------------- the stubbed transport
$GLOBALS['sent']    = array();   // every batch of urls the sender handed to the transport
$GLOBALS['answer']  = array();   // url substring => array(httpCode, body, errno)
$GLOBALS['latency'] = 0.0;       // seconds every batch takes, however many urls are in it

function shopApiCurlMultiCall(array $urls, $timeout, $connect, $userAgent)
{
	$GLOBALS['sent'][] = array('urls' => $urls, 'timeout' => (int)$timeout, 'connect' => (int)$connect);
	if ($GLOBALS['latency'] > 0) {
		usleep((int)($GLOBALS['latency'] * 1000000));
	}
	$out = array();
	foreach ($urls as $slot => $url) {
		$answer = array(200, '5', 0);
		foreach ($GLOBALS['answer'] as $needle => $given) {
			if (strpos($url, $needle) !== false) {
				$answer = $given;
			}
		}
		list($code, $body, $errno) = $answer;
		$out[$slot] = array(
			'httpCode'    => (int)$code,
			'body'        => (string)$body,
			'error'       => $errno ? 'curl error ' . $errno . ': stubbed' : '',
			'transient'   => ($errno !== 0 || $code === 0 || $code >= 500),
			'unreachable' => ($code === 0),
		);
	}
	return $out;
}

require_once(__DIR__ . '/../includes/stdFunc/shopApiRequest.php');
require_once(__DIR__ . '/../includes/stdFunc/shopSyncQueue.php');

set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) {
		return false;
	}
	throw new ErrorException($message, 0, $severity, $file, $line);
});

// Every failure below is deliberate, and shopApiLogFailure() writes each one to the php error
// log. That belongs in a log, not in the output of a passing test run.
ini_set('error_log', tempnam(sys_get_temp_dir(), 'sst876test'));

function check($ok, $message)
{
	if (!$ok) {
		throw new RuntimeException($message);
	}
	echo "PASS: $message\n";
}

/** Clears the per-request state between cases, through the reference the senders share. */
function resetSyncState()
{
	$state = &shopApiSyncState();
	$state['degraded']  = array();
	$state['failCount'] = array();
	$state['spent']     = 0.0;
	$GLOBALS['sent']    = array();
	$GLOBALS['answer']  = array();
	$GLOBALS['latency'] = 0.0;
}

$shops = array(
	0 => 'http://one.shop.test/adapter-one.php',
	1 => 'http://one.shop.test/adapter-two.php',
	2 => 'http://other.shop.test/api.php',
);

// ---------------------------------------------------------------- the parallel sender
resetSyncState();
$res = shopApiRequestParallel($shops, array('update_stock' => 1, 'stock' => 5), null, array('context' => 'test'));
check(count($res) === 3 && $res[0]['ok'] && $res[1]['ok'] && $res[2]['ok'], 'All three webshops are answered in one call');
check(count($GLOBALS['sent']) === 1 && count($GLOBALS['sent'][0]['urls']) === 3, 'The three shops go to the transport together, not one at a time');

// The whole point: three shops cost the slowest of them, not the sum of all three. Before this,
// an item was 8 calls paid for serially, which is what used the 30 second budget up.
resetSyncState();
$GLOBALS['latency'] = 0.3;
$before = microtime(true);
shopApiRequestParallel($shops, array('update_stock' => 1), null, array('context' => 'test'));
$elapsed = microtime(true) - $before;
$state = &shopApiSyncState();
check($state['spent'] > 0.25 && $state['spent'] < 0.6, 'The budget is charged the wall clock of one round, not three calls');
check($elapsed < 0.9, 'Three shops are not waited for one after the other');

// A refusal is about the item, so it must not take the shop out for everything that follows.
resetSyncState();
$GLOBALS['answer']['adapter-two'] = array(200, '{"success":false,"message":"no such product"}', 0);
$res = shopApiRequestParallel($shops, array('update_stock' => 1), null, array('context' => 'test'));
check($res[0]['ok'] && !$res[1]['ok'] && $res[2]['ok'], 'One shop refusing an item does not spoil the other two');
check(!$res[1]['skipped'] && $res[1]['error'] !== '', 'A refusal is reported as a failure with a reason');
$state = &shopApiSyncState();
check(count($state['degraded']) === 0, 'A refused item does not count as a dead endpoint');

// An unreachable adapter is dead; the one beside it on the same host is not.
resetSyncState();
$GLOBALS['answer']['adapter-one'] = array(0, '', 7);
$res = shopApiRequestParallel($shops, array('update_stock' => 1), null, array('context' => 'test', 'retries' => 0));
check(!$res[0]['ok'] && $res[1]['ok'] && $res[2]['ok'], 'A dead adapter does not stop the adapter next to it on the same host');
$state = &shopApiSyncState();
check(count($state['degraded']) === 1, 'Exactly the dead adapter is degraded');
check(isset($state['degraded']['http://one.shop.test/adapter-one.php']), 'The degradation key carries the path');

// What one sender learned, the other has to know.
$res = shopApiRequest($shops[0], array('update_stock' => 1), null, array('context' => 'test'));
check($res['skipped'] && strpos($res['error'], 'degraded') !== false,
	'The single sender skips an endpoint the parallel sender found dead');
$res = shopApiRequestParallel($shops, array('update_stock' => 1), null, array('context' => 'test'));
check($res[0]['skipped'] && !$res[1]['skipped'], 'The parallel sender skips the endpoint it degraded itself');

// A used-up budget reports every shop, rather than returning as if nothing was asked.
resetSyncState();
$state = &shopApiSyncState();
$state['spent'] = 29.5;
$res = shopApiRequestParallel($shops, array('update_stock' => 1), null, array('context' => 'test'));
check($res[0]['skipped'] && $res[1]['skipped'] && $res[2]['skipped'], 'An exhausted budget skips every shop');
check(strpos($res[0]['error'], 'time budget of 30s used up') !== false, 'The skip says the budget is what stopped it');
check(count($GLOBALS['sent']) === 0, 'No call is sent once the budget is gone');

// The remaining budget caps the timeouts, so a call cannot start with milliseconds left and
// then block for the full ten seconds.
resetSyncState();
$state = &shopApiSyncState();
$state['spent'] = 27.0;
shopApiRequestParallel($shops, array('update_stock' => 1), null, array('context' => 'test'));
check($GLOBALS['sent'][0]['timeout'] === 3, 'The call timeout is capped by what is left of the budget');
check($GLOBALS['sent'][0]['connect'] <= 3, 'The connect timeout is capped by what is left of the budget');

// A 5xx is worth one retry; an item refused inside a 200 is not.
resetSyncState();
$GLOBALS['answer']['other.shop.test'] = array(503, 'busy', 0);
$res = shopApiRequestParallel($shops, array('update_stock' => 1), null, array('context' => 'test', 'retries' => 1));
check(count($GLOBALS['sent']) === 2 && count($GLOBALS['sent'][1]['urls']) === 1,
	'A transient failure is retried, and only the shop that failed is retried');
check($res[2]['attempts'] === 2, 'The retry is counted on the result');

resetSyncState();
$GLOBALS['answer']['other.shop.test'] = array(200, '{"error":"unknown sku"}', 0);
shopApiRequestParallel($shops, array('update_stock' => 1), null, array('context' => 'test', 'retries' => 1));
check(count($GLOBALS['sent']) === 1, 'A refused item is not retried');

// A 4xx is the shop saying this item or these parameters are wrong, not that the shop is
// unwell, so repeating it is pointless and it must not degrade the endpoint either.
resetSyncState();
$GLOBALS['answer']['other.shop.test'] = array(404, 'not found', 0);
$res = shopApiRequestParallel($shops, array('update_stock' => 1), null, array('context' => 'test', 'retries' => 1));
check(count($GLOBALS['sent']) === 1, 'An item the shop does not have is not retried');
check(!$res[2]['ok'] && !$res[2]['skipped'], 'A 404 is reported as a failure of this item');
$state = &shopApiSyncState();
check(count($state['degraded']) === 0, 'A 404 does not take the shop out for the rest of the request');

// The fallback for an install without the multi handle is not exercised here - the extension is
// present wherever these tests run - so it is pinned in the source instead.
$src = file_get_contents(__DIR__ . '/../includes/stdFunc/shopApiRequest.php');
$at  = strpos($src, 'function shopApiRequestParallel(');
check($at !== false && strpos(substr($src, $at, 1600), "!function_exists('curl_multi_init')") !== false,
	'An install without curl_multi falls back to the single sender');

// ---------------------------------------------------------------- the queue
$db = 'sst876_test_queue';
$GLOBALS['db'] = $db;
$dir = shopSyncQueueDir();
check($dir !== '' && is_dir($dir), 'The queue directory is created under the account temp directory');
foreach (glob($dir . '*') as $stale) {
	unlink($stale);
}

check(shopSyncQueueAdd(123, 0, 4, 'stock'), 'An undelivered update can be queued');
check(count(glob($dir . 'v*')) === 1, 'Queueing one item makes one marker');
shopSyncQueueAdd(123, 0, 4, 'stock');
shopSyncQueueAdd(123, 0, 4, 'stock');
check(count(glob($dir . 'v*')) === 1, 'The same item queued three times is one marker');

$entry = shopSyncQueueRead($dir . shopSyncQueueKey(123, 0, 4));
check($entry['vare_id'] === 123 && $entry['variant_id'] === 0 && $entry['lager'] === 4,
	'The item, variant and warehouse come back off the marker');
check($entry['mode'] === 'stock' && $entry['attempts'] === 0, 'The marker carries the mode and the attempt count');

// No figure is stored, so a queued value can never overwrite a newer one.
$raw = file_get_contents($dir . shopSyncQueueKey(123, 0, 4));
check(preg_match('/^(stock|all) \d+ \d+$/D', trim($raw)) === 1, 'The marker holds no stock figure, only the mode and the timing');

shopSyncQueueAdd(123, 0, 4, 'all');
check(shopSyncQueueRead($dir . shopSyncQueueKey(123, 0, 4))['mode'] === 'all',
	'Asking for prices as well as stock widens the marker');
shopSyncQueueAdd(123, 0, 4, 'stock');
check(shopSyncQueueRead($dir . shopSyncQueueKey(123, 0, 4))['mode'] === 'all',
	'A later stock-only change does not narrow a marker that wanted prices too');

// Oldest first, so yesterday's item is not starved by the ones this request just queued. The
// older item is deliberately the one whose file name sorts last, because scandir() returns
// names in alphabetical order and a fixture where the two agree would pass with no sorting at
// all.
shopSyncQueueAdd(9900, 0, 4, 'all');
shopSyncQueueWrite($dir . shopSyncQueueKey(9900, 0, 4), 'all', 0, time() - 3600);
shopSyncQueueAdd(1100, 0, 4, 'stock');
$pending = shopSyncQueuePending(0);
check(count($pending) >= 2, 'Both queued items are pending');
check($pending[0]['vare_id'] === 9900,
	'The oldest queued item is delivered first, whatever its file name sorts as');
check(count(shopSyncQueuePending(1)) === 1, 'The pending list honours its limit');

$oldest = $pending[0];
$final = shopSyncQueueRetryLater($oldest, 3);
check($final === false, 'One failed attempt is not final');
check(shopSyncQueueRead($pending[0]['path'])['attempts'] === 1, 'The failed attempt is counted');
shopSyncQueueRetryLater(shopSyncQueueRead($pending[0]['path']), 3);
$final = shopSyncQueueRetryLater(shopSyncQueueRead($pending[0]['path']), 3);
check($final === true, 'Reaching the attempt limit reports a final failure');
check(is_file($pending[0]['path']), 'A marker past the attempt limit is kept, so the update is not dropped');

// The sender queues a failed item and the drainer counts the attempt, in that order, so if
// queueing an item that is already queued reset the count, the attempt limit would never be
// reached and an item no shop will ever take would be retried for ever.
shopSyncQueueAdd($oldest['vare_id'], 0, 4, 'stock');
check(shopSyncQueueRead($oldest['path'])['attempts'] === 3,
	'Queueing an item that is already queued keeps the attempts it has cost');

$before = shopSyncQueueCount();
shopSyncQueueDone($oldest);
check(!is_file($oldest['path']), 'A delivered update removes its marker');
check(shopSyncQueueCount() === $before - 1, 'The queue count follows the deliveries');

// A marker name is built from integers, so nothing from a request can reach the path.
shopSyncQueueAdd('../../escape', 0, 4, 'stock');
check(is_file($dir . 'v0-x0-l4'), 'A non-numeric item id is cast to an integer');
check(count(glob($dir . '*escape*')) === 0, 'An item id cannot travel out of the queue directory');

$GLOBALS['db'] = 'not a valid name';
check(shopSyncQueueDir(false) === '', 'An unusable database name produces no path');
$GLOBALS['db'] = $db;

foreach (glob($dir . '*') as $leftover) {
	unlink($leftover);
}
@rmdir($dir);
echo "\nAll checks passed.\n";
