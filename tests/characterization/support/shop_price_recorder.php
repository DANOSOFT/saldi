<?php
// tests/characterization/support/shop_price_recorder.php
//
// A fake webshop endpoint for ShopOfferPushTest: the router of a loopback-only `php -S` server. It appends every request as one
// JSON line (shop number, status, query) to the file named by SALDI_SHOP_RECORDER_LOG and answers "OK". The shop number is the
// N of a path /shopN.php (or ?shop=N). Control files next to the log change the answer:
//   <log>.fail or <log>.failN     HTTP 503 (all shops, or shop N)
//   <log>.rejectN                 HTTP 200 with {"success":false,"error":"temporary lock"}, a refusal
//   <log>.slow or <log>.slowN     wait the number of seconds in the file before answering
// It never forwards anything to a real shop.
//
// History:
// 20261117 CL/LH Created for the webshop offer push (includes/stdFunc/shopOfferPush.php).
//                Codex verification: per-shop failures, refusals and delays, and a shop number from the path.
$log = (string)getenv('SALDI_SHOP_RECORDER_LOG');
if ($log === '') {
	http_response_code(500);
	echo 'no log';
	return true;
}
$shop = preg_match('#/shop([0-9]+)\.php#', (string)$_SERVER['REQUEST_URI'], $m) ? $m[1] : (string)($_GET['shop'] ?? '');
$slow = is_file($log . '.slow' . $shop) ? (float)file_get_contents($log . '.slow' . $shop) : (is_file($log . '.slow') ? (float)file_get_contents($log . '.slow') : 0);
$status = (is_file($log . '.fail') || is_file($log . '.fail' . $shop)) ? 503 : 200;
$reject = $status === 200 && is_file($log . '.reject' . $shop);
// logged when the request arrives, so a test that clears the log after a slow call is not handed its line later
file_put_contents($log, json_encode(array('time' => microtime(true), 'shop' => $shop, 'status' => $reject ? 'refused' : $status, 'slow' => $slow, 'query' => $_GET)) . "\n", FILE_APPEND | LOCK_EX);
if ($slow > 0) {
	usleep((int)($slow * 1000000));
}
http_response_code($status);
echo $status === 503 ? 'down' : ($reject ? '{"success":false,"error":"temporary lock"}' : 'OK');
return true;
