<?php
// Copyright (c) 2026 Danosoft ApS
// 20261022 CL/MJ SST-876 Verify that a document sends each item once, and that what could not be
//                  delivered is delivered later rather than lost.
//
// sync_shop_vare() itself needs a tenant database, so it is replaced here: it is wrapped in
// if (!function_exists(...)) like everything else in std_func.php, so the stub below is the one
// that exists and the real mark, flush and drain layer runs against it. What is tested is which
// items that layer decides to send, with which mode, in which order, and what it does with the
// ones that fail - none of which needs a database.
// 20261026 CDX/MJ Model the queue structure lookup without a tenant database.
// 20261027 CDX/MJ SST-876 Model the cached API configuration check in the flush fixture.
// 20261028 CDX/MJ SST-876 Relocate this isolated fixture under the CI-discovered regression suite.

$GLOBALS['calls']   = array();   // every (vare_id, variant_id, lager, mode) the layer sent
$GLOBALS['answers'] = array();   // vare_id => answer sync_shop_vare() should give
$GLOBALS['answer']  = 'OK';      // answer for every item not named above

$GLOBALS['onDeliver'] = null;   // run inside a delivery, to stand in for a second request

function sync_shop_vare($vare_id, $variant_id, $lager, $mode = 'all')
{
	$GLOBALS['calls'][] = array((int)$vare_id, (int)$variant_id, (int)$lager, (string)$mode);
	if ($GLOBALS['onDeliver']) {
		$hook = $GLOBALS['onDeliver'];
		$GLOBALS['onDeliver'] = null;   // once, or it recurses
		$hook();
	}
	return isset($GLOBALS['answers'][(int)$vare_id]) ? $GLOBALS['answers'][(int)$vare_id] : $GLOBALS['answer'];
}

/** A drain as a second request would see it: the in-process guard is what the test is avoiding. */
function shopSyncDrainQueueNested()
{
	$delivered = 0;
	foreach (shopSyncQueuePending(50) as $entry) {
		$claim = shopSyncQueueClaim($entry);
		if (!$claim) {
			continue;
		}
		shopSyncQueueDone($claim);
		$delivered++;
	}
	return $delivered;
}

// This isolated fixture has ordinary products only, never assembled items.
function db_select($sql, $location = '') {
	if (strpos($sql, "select box4 from grupper where art='API'") !== false) {
		return array('box4' => 'http://shop.test/adapter');
	}
	if (strpos($sql, 'select indgaar_i from styklister') !== false) {
		return false;
	}
	throw new RuntimeException('Unexpected fixture query: ' . $sql);
}

function db_fetch_array($row) {
	return $row;
}

$db = 'sst876_test_flush';
require_once(dirname(__DIR__, 5) . '/includes/std_func.php');

// Every failure below is deliberate and shopSyncDrainQueue() reports a final one through
// error_log(). That belongs in a log, not in the output of a passing test run.
ini_set('error_log', tempnam(sys_get_temp_dir(), 'sst876flush'));

function check($ok, $message)
{
	if (!$ok) {
		throw new RuntimeException($message);
	}
	echo "PASS: $message\n";
}

$dir = shopSyncQueueDir();
foreach (glob($dir . '*') as $stale) {
	unlink($stale);
}

function reset_flush()
{
	$GLOBALS['calls']   = array();
	$GLOBALS['answers'] = array();
	$GLOBALS['answer']  = 'OK';
	shopSyncClearFailedItems();
	foreach (glob(shopSyncQueueDir() . '*') as $file) {
		unlink($file);
	}
	$marks = &shopSyncMarkState();
	$marks['items'] = array();
}

// ---------------------------------------------------------------- one send per item per document
reset_flush();
shopSyncMark(123, 0, 4, 'stock');
shopSyncMark(123, 0, 4, 'stock');
shopSyncMark(123, 0, 4, 'stock');
check(shopSyncMarkedCount() === 1, 'An item on three lines is marked once');
check(shopSyncFlush(0) === 'OK', 'A document with nothing wrong answers OK');
check(count($GLOBALS['calls']) === 1, 'An item on three lines is sent once');
check($GLOBALS['calls'][0] === array(123, 0, 4, 'stock'), 'The item is sent for the warehouse and mode it was marked with');

// The same item in two warehouses is two figures, so it stays two sends.
reset_flush();
shopSyncMark(123, 0, 4, 'stock');
shopSyncMark(123, 0, 1, 'stock');
shopSyncFlush(0);
check(count($GLOBALS['calls']) === 2, 'An item changed in two warehouses is sent for each of them');

// A variant and its parent are different figures too.
reset_flush();
shopSyncMark(123, 0, 4, 'stock');
shopSyncMark(123, 55, 4, 'stock');
shopSyncFlush(0);
check(count($GLOBALS['calls']) === 2, 'A variant is not collapsed into its parent product');

// A mark is durable the moment it is made. If the request died here - a php timeout halfway
// through a mass invoicing run - the stock movements are committed and this is the only thing
// left to say the shops were never told.
reset_flush();
shopSyncMark(123, 0, 4, 'stock');
check(shopSyncQueueCount() === 1, 'A marked line is on the queue before anything is sent');
$queued = shopSyncQueuePending(0)[0];
check($queued['vare_id'] === 123 && $queued['lager'] === 4 && $queued['mode'] === 'stock',
	'The queued line carries the item, the warehouse and the mode');
shopSyncFlush(0);
check(shopSyncQueueCount() === 0, 'A line the shops have taken leaves the queue');

// ---------------------------------------------------------------- the mode
reset_flush();
shopSyncMark(123, 0, 4, 'stock');
shopSyncMark(123, 0, 4, 'all');
shopSyncFlush(0);
check($GLOBALS['calls'][0][3] === 'all', 'Asking for the prices too widens an item already marked for stock');

reset_flush();
shopSyncMark(123, 0, 4, 'all');
shopSyncMark(123, 0, 4, 'stock');
shopSyncFlush(0);
check($GLOBALS['calls'][0][3] === 'all', 'A later stock-only line does not narrow an item marked for prices');

reset_flush();
shopSyncMark(123, 0, 4);
shopSyncFlush(0);
check($GLOBALS['calls'][0][3] === 'stock', 'Marking without a mode sends only the stock calls');

// ---------------------------------------------------------------- what the flush answers
reset_flush();
$GLOBALS['answers'][123] = 'sync errors: 2';
shopSyncMark(123, 0, 4, 'stock');
shopSyncMark(3580, 0, 4, 'stock');
$result = shopSyncFlush(0);
check(shopSyncFailed($result), 'A document where a shop refused an item answers with a failure');
check(count($GLOBALS['calls']) === 2, 'One item failing does not stop the rest of the document being sent');

reset_flush();
$GLOBALS['answer'] = 'no api';
shopSyncMark(123, 0, 4, 'stock');
shopSyncMark(3580, 0, 4, 'stock');
check(shopSyncFlush(0) === 'no api', 'An install with no webshop configured answers no api');
check(count($GLOBALS['calls']) === 1, 'With no webshop configured the flush gives up instead of trying every item in turn');

reset_flush();
check(shopSyncFlush(0) === 'OK' && count($GLOBALS['calls']) === 0, 'A document that changed no stock sends nothing');

// ---------------------------------------------------------------- the queue, end to end
reset_flush();
shopSyncQueueAdd(3580, 0, 4, 'stock');
shopSyncQueueWrite($dir . shopSyncQueueKey(3580, 0, 4), 'stock', 0, time() - 7200);
shopSyncQueueAdd(6019, 0, 4, 'stock');
$delivered = shopSyncDrainQueue(50);
check($delivered === 2, 'Both queued updates are delivered');
check($GLOBALS['calls'][0][0] === 3580, 'The update that has waited longest is delivered first');
check(shopSyncQueueCount() === 0, 'A delivered update is taken off the queue');

// A figure is never carried on the queue, so what reaches the shop is whatever the database says
// at the moment of delivery. The call the layer makes is the ordinary one, by id.
reset_flush();
shopSyncQueueAdd(3580, 0, 4, 'stock');
shopSyncDrainQueue(50);
check(count($GLOBALS['calls']) === 1 && $GLOBALS['calls'][0] === array(3580, 0, 4, 'stock'),
	'A queued update is delivered by reading the item again, not from a stored figure');

// A failed delivery keeps the marker and counts the attempt.
reset_flush();
$GLOBALS['answer'] = 'sync errors: 1';
shopSyncQueueAdd(3580, 0, 4, 'stock');
check(shopSyncDrainQueue(50) === 0, 'A failed delivery is not counted as delivered');
$entry = shopSyncQueueRead($dir . shopSyncQueueKey(3580, 0, 4));
check($entry !== null, 'A failed delivery keeps the update on the queue');
check($entry['attempts'] === 1, 'A failed delivery counts the attempt');

// It keeps the marker however often it fails - dropping it would be losing the update again.
for ($n = 0; $n < 6; $n++) {
	shopSyncDrainQueue(50);
}
$entry = shopSyncQueueRead($dir . shopSyncQueueKey(3580, 0, 4));
check($entry !== null, 'An update that cannot be delivered is kept rather than dropped');
check($entry['attempts'] >= 5, 'The attempts keep being counted past the limit');

// An item that is no longer stock managed has nothing left to deliver.
reset_flush();
$GLOBALS['answer'] = 'no stock';
shopSyncQueueAdd(3580, 0, 4, 'stock');
shopSyncDrainQueue(50);
check(shopSyncQueueCount() === 0, 'An item that is no longer stock managed is taken off the queue');

// Nothing configured: keep the backlog, do not quietly discard it.
reset_flush();
$GLOBALS['answer'] = 'no api';
shopSyncQueueAdd(3580, 0, 4, 'stock');
shopSyncDrainQueue(50);
check(shopSyncQueueCount() === 1, 'With no webshop configured the backlog is kept');

// The document comes first and the backlog after it, so the figures somebody is waiting for are
// not held up by yesterday's.
reset_flush();
shopSyncQueueWrite($dir . shopSyncQueueKey(6019, 0, 4), 'stock', 0, time() - 7200);
shopSyncMark(123, 0, 4, 'stock');
shopSyncFlush(50);
check(count($GLOBALS['calls']) === 2, 'The flush sends the document and then the backlog');
check($GLOBALS['calls'][0][0] === 123, 'The document is delivered before the backlog, not after it');

// ---------------------------------------------------------------- two requests at once
// Delivering a queued item takes seconds of http calls. If another request changes the same
// item's stock while that is happening, it queues the item again - and the delivery that is
// already running must not take that newer marker away with it when it finishes. Reported by a
// second pair of eyes on this branch, and it is the ticket's own "an older worker result must
// not clear a newer pending change".
reset_flush();
shopSyncQueueAdd(3580, 0, 4, 'stock');
$GLOBALS['onDeliver'] = function () {
	// stands in for another request posting a sale for the same item mid-delivery
	shopSyncQueueAdd(3580, 0, 4, 'stock');
};
check(shopSyncDrainQueue(50) === 1, 'The queued item is delivered');
$GLOBALS['onDeliver'] = null;
check(shopSyncQueueCount() === 1,
	'A change made while the item was being delivered is still queued afterwards');

// And the item being delivered is not delivered twice over by a second request that starts
// while the first is still working on it.
reset_flush();
shopSyncQueueAdd(3580, 0, 4, 'stock');
$GLOBALS['onDeliver'] = function () {
	$GLOBALS['nested'] = shopSyncDrainQueueNested();
};
shopSyncDrainQueue(50);
$GLOBALS['onDeliver'] = null;
check(isset($GLOBALS['nested']) && $GLOBALS['nested'] === 0,
	'A second request does not deliver an item the first one is already delivering');

// ---------------------------------------------------------------- what gets reported
reset_flush();
check(shopSyncFailedItems() === array(), 'Nothing is reported when nothing failed');
shopSyncNoteFailedItem('DIB038BW');
shopSyncNoteFailedItem('DIB038BW');
shopSyncNoteFailedItem('DIBS015YW');
check(shopSyncFailedItems() === array('DIB038BW', 'DIBS015YW'),
	'An item that failed at several shops is named once, in the order it failed');
shopSyncNoteFailedItem('   ');
check(count(shopSyncFailedItems()) === 2, 'An empty item number is not reported');
shopSyncClearFailedItems();
check(shopSyncFailedItems() === array(), 'The collected item numbers can be cleared between pages');

// ---------------------------------------------------------------- what deserves another attempt
check(shopSyncWorthRetrying(array('skipped' => true, 'httpCode' => 0)) === true,
	'A call the budget never let happen is worth another attempt');
check(shopSyncWorthRetrying(array('skipped' => false, 'httpCode' => 0)) === true,
	'A call that got no answer at all is worth another attempt');
check(shopSyncWorthRetrying(array('skipped' => false, 'httpCode' => 503)) === true,
	'A shop that is busy is worth another attempt');
check(shopSyncWorthRetrying(array('skipped' => false, 'httpCode' => 200)) === false,
	'A shop that answered and refused the item is not retried for ever');
check(shopSyncWorthRetrying(array('skipped' => false, 'httpCode' => 404)) === false,
	'An item the shop does not know is not retried for ever');

foreach (glob($dir . '*') as $leftover) {
	unlink($leftover);
}
@rmdir($dir);
echo "\nAll checks passed.\n";
