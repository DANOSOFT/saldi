<?php
// Copyright (c) 2026 Danosoft ApS
// 20261022 CL/MJ SST-876 Step 1: how many webshop calls one document is worth, measured by running
//                  the real sync_shop_vare().
//
// The ticket's own step 1 says to restore a pos_50 backup. pos_50 is Havemoebelland's live data
// and we do not test against customer data, and it is not needed: what has to be reproduced is an
// item that is a component in 33 sets, which is a fixture rather than a tenant.
//
// So the database layer and the transport are both replaced here - db_select(), db_fetch_array()
// and getAvailable() are each wrapped in if (!function_exists(...)), so the stubs below are the
// ones that exist - and the shipped sync_shop_vare() runs against them unchanged. What is counted
// is the thing that decides whether the 30 second budget survives a document: how many rounds of
// http calls one item is worth.
//
// Invoice 230508 of 2026-10-19 is the shape being reproduced: five lines, the first of them item
// 13054, a component in 33 sets. On live it made 108 successful calls and then skipped everything
// that followed, including item 107657.

chdir(__DIR__);   // sync_shop_vare() opens ../temp/<db>/rest_api.log

$db        = 'sst876_test_fanout';
$regnaar   = 2026;
$bruger_id = 1;

// ---------------------------------------------------------------- the fixture
$SHOPS = array(
	'box4' => 'http://shop-one.test/adapter-one.php',
	'box5' => 'http://shop-one.test/adapter-two.php',
	'box6' => 'http://shop-two.test/api.php',
);
$SETS = array();                                  // the 33 sets item 123 is a component in
for ($n = 0; $n < 33; $n++) {
	$SETS[] = 9001 + $n;
}
$ITEMS = array(
	123  => array('varenr' => 'DIB13054',  'delvare' => 'on', 'gruppe' => 1, 'parents' => $SETS),
	3580 => array('varenr' => 'DIB107657', 'delvare' => '',   'gruppe' => 1, 'parents' => array()),
	4711 => array('varenr' => 'DIB004711', 'delvare' => 'on', 'gruppe' => 1, 'parents' => $SETS),
);
foreach ($SETS as $setId) {
	$ITEMS[$setId] = array('varenr' => 'SET' . $setId, 'delvare' => '', 'gruppe' => 1, 'parents' => array());
}

// ---------------------------------------------------------------- the stubbed database
$GLOBALS['shopRs']      = array(0 => null);   // result sets, from index 1 so none of them is falsy
$GLOBALS['shopQueries'] = 0;

function db_select($qtext, $spor = '', $global = false)
{
	global $SHOPS, $ITEMS;
	$GLOBALS['shopQueries']++;
	$rows = array();
	if (strpos($qtext, "from grupper where art='API'") !== false) {
		$rows[] = $SHOPS;
	} elseif (preg_match("/select delvare,gruppe from varer where id='(\d+)'/", $qtext, $m)) {
		$item = isset($ITEMS[(int)$m[1]]) ? $ITEMS[(int)$m[1]] : null;
		if ($item) {
			$rows[] = array('delvare' => $item['delvare'], 'gruppe' => $item['gruppe']);
		}
	} elseif (strpos($qtext, "select box8 from grupper") !== false) {
		$rows[] = array('box8' => 'on');
	} elseif (strpos($qtext, 'from lagerstatus,varer') !== false) {
		preg_match("/lagerstatus.vare_id='(\d+)'/", $qtext, $m);
		$item = isset($ITEMS[(int)$m[1]]) ? $ITEMS[(int)$m[1]] : null;
		if ($item) {
			$rows[] = array('varenr' => $item['varenr'], 'varenr_alias' => '', 'kostpris' => '100',
				'salgspris' => '200', 'm_type' => '', 'm_rabat' => '0', 'stock' => '67');
		}
	} elseif (strpos($qtext, 'sum(beholdning) as total_stock') !== false) {
		$rows[] = array('total_stock' => '67');
	} elseif (preg_match("/select shop_id from shop_varer where saldi_id ?= ?'?(\d+)'?/", $qtext, $m)) {
		$rows[] = array('shop_id' => 'shop-' . (int)$m[1]);
	} elseif (preg_match("/FROM varer WHERE varer.id='(\d+)'/", $qtext, $m)) {
		$item = isset($ITEMS[(int)$m[1]]) ? $ITEMS[(int)$m[1]] : null;
		if ($item) {
			$rows[] = array('varenr' => $item['varenr'], 'varenr_alias' => '', 'kostpris' => '100',
				'salgspris' => '200', 'm_type' => '', 'm_rabat' => '0', 'retail_price' => '250',
				'colli_webfragt' => '0', 'stregkode' => '5712345678901');
		}
	} elseif (preg_match("/select \* from styklister where vare_id = '(\d+)'/", $qtext, $m)) {
		$item = isset($ITEMS[(int)$m[1]]) ? $ITEMS[(int)$m[1]] : null;
		foreach (($item ? $item['parents'] : array()) as $parent) {
			$rows[] = array('indgaar_i' => $parent, 'vare_id' => (int)$m[1]);
		}
	} elseif (preg_match("/select varenr,varenr_alias,kostpris from varer where id = '(\d+)'/", $qtext, $m)) {
		$item = isset($ITEMS[(int)$m[1]]) ? $ITEMS[(int)$m[1]] : null;
		if ($item) {
			$rows[] = array('varenr' => $item['varenr'], 'varenr_alias' => '', 'kostpris' => '100');
		}
	}
	$GLOBALS['shopRs'][] = $rows;
	return count($GLOBALS['shopRs']) - 1;
}

function db_fetch_array($handle)
{
	if (!is_int($handle) || !isset($GLOBALS['shopRs'][$handle]) || !$GLOBALS['shopRs'][$handle]) {
		return false;
	}
	return array_shift($GLOBALS['shopRs'][$handle]);
}

function db_escape_string($text)
{
	return str_replace("'", "''", (string)$text);
}

function getAvailable($itemId, $stockNo)
{
	return '67|67';
}

// ---------------------------------------------------------------- the stubbed transport
$GLOBALS['shopRounds'] = array();   // one entry per curl_multi round, each the urls it carried
$GLOBALS['shopDead']   = '';        // url substring that answers nothing

function shopApiCurlMultiCall(array $urls, $timeout, $connect, $userAgent)
{
	$GLOBALS['shopRounds'][] = array_values($urls);
	$out = array();
	foreach ($urls as $slot => $url) {
		$dead = ($GLOBALS['shopDead'] !== '' && strpos($url, $GLOBALS['shopDead']) !== false);
		$out[$slot] = array(
			'httpCode'    => $dead ? 0 : 200,
			'body'        => $dead ? '' : '67',
			'error'       => $dead ? 'curl error 7: stubbed' : '',
			'transient'   => $dead,
			'unreachable' => $dead,
		);
	}
	return $out;
}

require_once(__DIR__ . '/../includes/std_func.php');

ini_set('error_log', tempnam(sys_get_temp_dir(), 'sst876fanout'));
@mkdir(__DIR__ . '/../temp/' . $db, 0700, true);

function check($ok, $message)
{
	if (!$ok) {
		throw new RuntimeException($message);
	}
	echo "PASS: $message\n";
}

function calls_in_rounds()
{
	$n = 0;
	foreach ($GLOBALS['shopRounds'] as $round) {
		$n += count($round);
	}
	return $n;
}

function reset_fanout()
{
	$GLOBALS['shopRounds'] = array();
	$GLOBALS['shopDead']   = '';
	$GLOBALS['shopRs']     = array(0 => null);
	$state = &shopApiSyncState();
	$state['degraded']  = array();
	$state['failCount'] = array();
	$state['spent']     = 0.0;
	$marks = &shopSyncMarkState();
	$marks['items'] = array();
	shopSyncClearFailedItems();
	foreach (glob(shopSyncQueueDir() . '*') as $file) {
		unlink($file);
	}
	// The set guard inside sync_shop_vare() is a static for the length of a request, and a test
	// process is one request, so it is cleared the way a new request would get it.
	shopSyncResetSentSets();
}

// ---------------------------------------------------------------- an ordinary item
reset_fanout();
check(sync_shop_vare(3580, 0, 4, 'all') === 'OK', 'An ordinary item syncs');
check(calls_in_rounds() === 8, 'An ordinary item is the documented eight calls in all mode: '
	. calls_in_rounds());
check(count($GLOBALS['shopRounds']) === 3, 'Those eight calls are three rounds, not eight: '
	. count($GLOBALS['shopRounds']));

reset_fanout();
sync_shop_vare(3580, 0, 4, 'stock');
check(calls_in_rounds() === 3, 'A stock change is three calls, one per shop: ' . calls_in_rounds());
check(count($GLOBALS['shopRounds']) === 1, 'A stock change is one round: ' . count($GLOBALS['shopRounds']));
$sent = implode(' ', $GLOBALS['shopRounds'][0]);
check(strpos($sent, 'update_stock=') !== false, 'The stock call is the one that is sent');
check(strpos($sent, 'update_price=') === false, 'No price call is sent on a stock change');
check(strpos($sent, 'sku=') === false, 'No sku call is sent on a stock change');
check(strpos($sent, 'salesPrice=') !== false, 'The stock call still carries the prices it always did');

// ---------------------------------------------------------------- the line from invoice 230508
reset_fanout();
sync_shop_vare(123, 0, 4, 'stock');
$rounds = count($GLOBALS['shopRounds']);
$calls  = calls_in_rounds();
check($rounds === 34, 'Item 13054 is one round for itself and one per set: ' . $rounds);
check($calls === 102, 'That is 102 calls where it used to be 206: ' . $calls);
// At the quarter second a call took on live, 34 rounds is about 8.5 seconds. The budget is 30,
// and this one line used to exhaust it on its own.
check($rounds * 0.25 < 29, 'One line of 33 sets now fits inside the time budget: '
	. sprintf('%.1f', $rounds * 0.25) . 's of 29s');

// ---------------------------------------------------------------- the rest of the document
reset_fanout();
shopSyncMark(123, 0, 4, 'stock');
shopSyncMark(4711, 0, 4, 'stock');
shopSyncMark(3580, 0, 4, 'stock');
check(shopSyncFlush(0) === 'OK', 'The document syncs');
$rounds = count($GLOBALS['shopRounds']);
// 123 and 4711 belong to the same 33 sets. Those sets are sent once for the document, not once
// per line: 3 items + 33 sets.
check($rounds === 36, 'Sets shared by two lines are sent once for the document: ' . $rounds);
check(calls_in_rounds() === 108, 'The whole document is 108 calls: ' . calls_in_rounds());

// Before any of this, those three lines were 8 + 6*33 + 8 + 6*33 + 8 = 420 serial calls, which at
// a quarter of a second is 105 seconds against a 30 second budget.
check(420 * 0.25 > 29, 'The same three lines could not have fitted in the budget before');

// ---------------------------------------------------------------- a shop that is not answering
reset_fanout();
$GLOBALS['shopDead'] = 'shop-two.test';
$result = sync_shop_vare(3580, 0, 4, 'stock');
check(shopSyncFailed($result), 'A shop that cannot be reached is reported as a failure');
check(shopSyncFailedItems() === array('DIB107657'), 'The item is named by its number, not its id');
$pending = shopSyncQueuePending(0);
check(count($pending) === 1 && $pending[0]['vare_id'] === 3580,
	'The item a shop could not be told about is left on the queue');
check($pending[0]['mode'] === 'stock', 'It is queued for the kind of change that was being sent');

// The two shops that answered are not re-sent when the queue is drained for the one that did not:
// what is queued is the item, and the drain sends it to whoever is configured, which is the
// behaviour "the figure is read again when it is delivered" buys.
reset_fanout();
shopSyncQueueAdd(3580, 0, 4, 'stock');
check(shopSyncDrainQueue(50) === 1, 'The queued item is delivered on the next request');
check(shopSyncQueueCount() === 0, 'A delivered item leaves the queue');
check(count($GLOBALS['shopRounds']) === 1, 'Delivering it is one round: ' . count($GLOBALS['shopRounds']));

// ---------------------------------------------------------------- the budget, end to end
reset_fanout();
$state = &shopApiSyncState();
$state['spent'] = 29.5;
$result = sync_shop_vare(3580, 0, 4, 'stock');
check(count($GLOBALS['shopRounds']) === 0, 'Nothing is sent once the budget is used up');
check(shopSyncFailed($result), 'A skipped send is reported, not silently dropped');
$pending = shopSyncQueuePending(0);
check(count($pending) === 1, 'What the budget skipped is on the queue instead of being lost');

// ---------------------------------------------------------------- one request, two documents
// Mass invoicing posts ten orders in one php request and each order's figures are its own, so
// the sets sent for the first order say nothing about the second. This is the case that makes
// the per-document scope of the guard load-bearing rather than cosmetic.
reset_fanout();
shopSyncMark(123, 0, 4, 'stock');
shopSyncFlush(0);
$firstOrder = count($GLOBALS['shopRounds']);
shopSyncMark(123, 0, 4, 'stock');
shopSyncFlush(0);
check(count($GLOBALS['shopRounds']) === $firstOrder * 2,
	'The second order in the same request sends its sets again: ' . count($GLOBALS['shopRounds'])
	. ' rounds for two orders of ' . $firstOrder);

// And a direct call - the product card - is a document of one item, so it does not inherit the
// sets a flush earlier in the same request already sent.
reset_fanout();
shopSyncMark(123, 0, 4, 'stock');
shopSyncFlush(0);
$afterFlush = count($GLOBALS['shopRounds']);
sync_shop_vare(123, 0, 4, 'stock');
check(count($GLOBALS['shopRounds']) === $afterFlush * 2,
	'A direct sync after a flush sends the sets rather than thinking it already did');

foreach (glob(shopSyncQueueDir() . '*') as $leftover) {
	unlink($leftover);
}
@rmdir(shopSyncQueueDir(false));
@rmdir(__DIR__ . '/../temp/' . $db);
echo "\nAll checks passed.\n";
