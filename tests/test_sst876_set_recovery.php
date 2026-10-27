<?php
// Copyright (c) 2026 Danosoft ApS
// 20261026 CDX/MJ SST-876 Reproduce and guard recovery of non-stock-managed sets after a shop outage.

$http = getenv('SALDI_SHOP_SET_RECOVERY_HTTP') === '1';
if ($http) {
	putenv('SALDI_SHOP_REAL_TRANSPORT=1');
	$server = proc_open(array('python3', __DIR__ . '/support/shop_sync_mock.py'),
		array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => STDERR), $pipes);
	if (!is_resource($server)) {
		throw new RuntimeException('Could not start the local recovery mock.');
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
		throw new RuntimeException('The local recovery mock did not start.');
	}
}
putenv('SALDI_SHOP_FIXTURE_ONLY=1');
require __DIR__ . '/test_sst876_item_fanout.php';
set_exception_handler(function ($error) {
	fwrite(STDERR, $error->getMessage() . "\n");
	exit(1);
});

if ($http) {
	$base = 'http://127.0.0.1:' . $port;
	$SHOPS = array('box4' => $base . '/adapter-one.php', 'box5' => $base . '/adapter-two.php',
		'box6' => $base . '/adapter-three.php');
}
$logPath = __DIR__ . '/../temp/' . $db . '/rest_api.log';

/** @return array<int,string> Successful HTTP requests, or URLs handed to the healthy stub. */
function set_recovery_urls($http, $logPath, $offset) {
	if ($http) {
		$text = substr(file_get_contents($logPath), $offset);
		preg_match_all('/shop sync ok [^\n]+ http 200 (http[^\n]+)/', $text, $matches);
		return $matches[1];
	}
	$urls = array();
	foreach ($GLOBALS['shopRounds'] as $round) {
		$urls = array_merge($urls, $round);
	}
	return $urls;
}

reset_fanout();
$document = array(123, 2518, 2519, 526, 5787, 3580);
$setCounts = array(33, 25, 25, 25, 20, 2);
$sets = array();
foreach ($document as $i => $id) {
	$parents = array();
	for ($n = 0; $n < $setCounts[$i]; $n++) {
		$parent = 40000 + $i * 100 + $n;
		$parents[] = $parent;
		$sets[] = $parent;
		$ITEMS[$parent] = array('varenr' => 'SET' . $parent, 'delvare' => '', 'gruppe' => 12, 'parents' => array());
	}
	$ITEMS[$id] = array('varenr' => 'ITEM' . $id, 'delvare' => 'on', 'gruppe' => 1, 'parents' => $parents);
	shopSyncMark($id, 0, 4);
}
$GLOBALS['shopDead'] = 'adapter-two.php';
$healthyShop2 = $SHOPS['box5'];
if ($http) {
	$SHOPS['box5'] = 'http://127.0.0.1:1/adapter-two.php';
}
check(shopSyncFailed(shopSyncFlush(0)), 'A sale reports the unreachable second shop');
check(count($sets) === 130 && shopSyncQueueCount() === 136,
	'All six lines and 130 non-stock-managed sets remain queued during the outage');

// The queue contains only ids; recovery must calculate new set availability rather than use the sale's figures.
foreach ($sets as $id) {
	$GLOBALS['setAvailability'][$id] = '83|11';
}
$SHOPS['box5'] = $healthyShop2;
$recovered = array();
for ($request = 0; $request < 10 && shopSyncQueueCount() > 0; $request++) {
	reset_fanout(false);
	// More than five seconds of preparation across 130 sets: recovery must interleave preparation and sends.
	$GLOBALS['setAvailabilityDelay'] = 0.04;
	clearstatcache(true, $logPath);
	$offset = is_file($logPath) ? filesize($logPath) : 0;
	shopSyncDrainQueue(50);
	foreach (set_recovery_urls($http, $logPath, $offset) as $url) {
		if (strpos($url, 'adapter-two.php') !== false) {
			parse_str(parse_url($url, PHP_URL_QUERY), $query);
			$recovered[$query['itemNo']] = $query;
		}
	}
}
check(count($recovered) === 136, 'The recovered shop receives all 136 items before the queue becomes empty (received ' . count($recovered) . ')');
foreach ($sets as $id) {
	$query = $recovered['SET' . $id] ?? array();
	if (($query['stock'] ?? '') !== '11' || ($query['totalStock'] ?? '') !== '83') {
		throw new RuntimeException('Set ' . $id . ' did not receive freshly calculated stock');
	}
	if (($query['sku'] ?? '') !== 'SET' . $id || ($query['stockno'] ?? '') !== '4') {
		throw new RuntimeException('Set ' . $id . ' lost its original part-path identifiers');
	}
}
check(shopSyncQueueCount() === 0, 'The queue empties only after every set has been sent successfully');

// A known set that cannot currently produce a payload is not evidence of delivery.
reset_fanout();
$id = $sets[0];
$saved = $ITEMS[$id];
unset($ITEMS[$id]);
shopSyncQueueAdd($id, 0, 4);
check(shopSyncDrainQueue(50) === 0, 'A set with no payload is not counted as delivered');
$pending = shopSyncQueuePending();
check(count($pending) === 1 && $pending[0]['attempts'] === 0,
	'A known set that sent nothing remains queued without consuming a transport attempt');
$ITEMS[$id] = $saved;

// Full product-card retries also retain the separate cost-price call used on the original part path.
reset_fanout();
shopSyncQueueAdd($id, 0, 4, 'all');
clearstatcache(true, $logPath);
$offset = is_file($logPath) ? filesize($logPath) : 0;
shopSyncDrainQueue(50);
$stockCalls = $costCalls = 0;
foreach (set_recovery_urls($http, $logPath, $offset) as $url) {
	parse_str(parse_url($url, PHP_URL_QUERY), $query);
	if (isset($query['update_stock'])) {
		$stockCalls++;
	} elseif (isset($query['costPrice'])) {
		$costCalls++;
	}
}

check($stockCalls === 3 && $costCalls === 3, 'Full-mode recovery preserves stock and cost-price sends to all three shops');
check(shopSyncQueueCount() === 0, 'A fully delivered set is removed from the queue');

// A queued component must not narrow an existing full-mode marker for one of its sets.
reset_fanout();
shopSyncQueueAdd($document[0], 0, 4, 'stock');
shopSyncQueueAdd($id, 0, 4, 'all');
clearstatcache(true, $logPath);
$offset = is_file($logPath) ? filesize($logPath) : 0;
shopSyncDrainQueue(50);
$costCalls = 0;
foreach (set_recovery_urls($http, $logPath, $offset) as $url) {
	parse_str(parse_url($url, PHP_URL_QUERY), $query);
	if (($query['sku'] ?? '') === 'SET' . $id && !isset($query['update_stock'])) {
		$costCalls++;
	}
}
check($costCalls === 3, 'A component stock retry cannot narrow an existing full-mode set retry');
for ($request = 0; $request < 10 && shopSyncQueueCount() > 0; $request++) {
	reset_fanout(false);
	shopSyncDrainQueue(50);
}
check(shopSyncQueueCount() === 0, 'Mixed component and set retries resolve after all required payloads are sent');

foreach (glob(shopSyncQueueDir() . '*') as $file) {
	unlink($file);
}
echo "All queued-set recovery checks passed.\n";
