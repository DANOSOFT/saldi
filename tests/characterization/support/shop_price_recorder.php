<?php
// tests/characterization/support/shop_price_recorder.php
//
// A fake webshop endpoint for ShopOfferPushTest: the router of a loopback-only `php -S` server. It appends every request as one
// JSON line (shop number, status, query) to the file named by SALDI_SHOP_RECORDER_LOG, and keeps the shop's own price per item
// in <log>.remote, the way a real shop holds it: an accepted update_price or update_stock with a salesPrice sets the price when
// it is applied, which may be later than the request arrived and earlier than the answer leaves. The shop number is the N of a
// path /shopN.php (or ?shop=N). Control files next to the log change the answer:
//   <log>.fail or <log>.failN     HTTP 503 (all shops, or shop N), nothing applied
//   <log>.rejectN                 HTTP 200 with {"success":false,"error":"temporary lock"}, a refusal, nothing applied
//   <log>.slow or <log>.slowN     wait the number of seconds in the file before answering
//   <log>.rules                   JSON list of {shop?, item?, sales?, call?, apply?, answer?, refuse?, status?}: the first rule
//                                 whose given fields match delays applying by `apply` and the answer after it by `answer`
//                                 seconds, refuses the call (`refuse`) or answers with an HTTP `status` (nothing applied)
// It never forwards anything to a real shop.
//
// History:
// 20261117 CL/LH Created for the webshop offer push (includes/stdFunc/shopOfferPush.php).
//                Codex verification: per-shop failures, refusals and delays, and a shop number from the path.
//                Single-writer rebuild: the shop's applied price per item (<log>.remote), with apply and answer delays, refusals and statuses per rule.
$log = (string)getenv('SALDI_SHOP_RECORDER_LOG');
if ($log === '') {
	http_response_code(500);
	echo 'no log';
	return true;
}
$shop = preg_match('#/shop([0-9]+)\.php#', (string)$_SERVER['REQUEST_URI'], $m) ? $m[1] : (string)($_GET['shop'] ?? '');
$call = isset($_GET['update_price']) ? 'update_price' : (isset($_GET['update_stock']) ? 'update_stock' : 'other');
$item = (string)($_GET['itemNo'] ?? '');
$slow = is_file($log . '.slow' . $shop) ? (float)file_get_contents($log . '.slow' . $shop) : (is_file($log . '.slow') ? (float)file_get_contents($log . '.slow') : 0);
$status = (is_file($log . '.fail') || is_file($log . '.fail' . $shop)) ? 503 : 200;
$reject = $status === 200 && is_file($log . '.reject' . $shop);
$apply = $answer = 0.0;
foreach ((array)json_decode((string)@file_get_contents($log . '.rules'), true) as $rule) {
	if ((isset($rule['shop']) && (string)$rule['shop'] !== $shop) || (isset($rule['item']) && (string)$rule['item'] !== $item)
		|| (isset($rule['sales']) && (string)$rule['sales'] !== (string)($_GET['salesPrice'] ?? '')) || (isset($rule['call']) && $rule['call'] !== $call)) {
		continue;
	}
	$apply = (float)($rule['apply'] ?? 0);
	$answer = (float)($rule['answer'] ?? 0);
	if (!empty($rule['refuse'])) $reject = true;
	if (isset($rule['status'])) $status = (int)$rule['status'];
	break;
}
// logged when the request arrives, so a test that clears the log after a slow call is not handed its line later
file_put_contents($log, json_encode(array('time' => microtime(true), 'shop' => $shop, 'status' => $reject ? 'refused' : $status, 'slow' => $slow, 'query' => $_GET)) . "\n", FILE_APPEND | LOCK_EX);
$reject = $reject && $status === 200;
if ($status === 200 && !$reject && $item !== '' && array_key_exists('salesPrice', $_GET) && ($call === 'update_price' || $call === 'update_stock')) {
	if ($apply > 0) {
		usleep((int)($apply * 1000000));
	}
	$fh = fopen($log . '.remote', 'c+');
	flock($fh, LOCK_EX);
	$remote = json_decode((string)stream_get_contents($fh), true) ?: array();
	$remote[$shop . '|' . $item] = array('sales' => (string)$_GET['salesPrice'], 'retail' => (string)($_GET['retailPrice'] ?? ''), 'call' => $call, 'at' => microtime(true));
	ftruncate($fh, 0);
	rewind($fh);
	fwrite($fh, json_encode($remote));
	fflush($fh);
	flock($fh, LOCK_UN);
	fclose($fh);
}
if ($slow + $answer > 0) {
	usleep((int)(($slow + $answer) * 1000000));
}
http_response_code($status);
echo $status >= 400 ? 'down' : ($reject ? '{"success":false,"error":"temporary lock"}' : 'OK');
return true;
