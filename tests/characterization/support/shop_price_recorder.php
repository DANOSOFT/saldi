<?php
// tests/characterization/support/shop_price_recorder.php
//
// A fake webshop endpoint for ShopOfferPushTest: the router of a loopback-only `php -S` server. It appends every request's
// query parameters as one JSON line to the file named by SALDI_SHOP_RECORDER_LOG and answers "OK", or answers 503 while a
// file of that name plus ".fail" exists. It never forwards anything to a real shop.
//
// History:
// 20261117 CL/LH Created for the webshop offer push (includes/stdFunc/shopOfferPush.php).
$log = (string)getenv('SALDI_SHOP_RECORDER_LOG');
if ($log === '') {
	http_response_code(500);
	echo 'no log';
	return true;
}
$failing = is_file($log . '.fail');
file_put_contents($log, json_encode(array('time' => microtime(true), 'status' => $failing ? 503 : 200, 'query' => $_GET)) . "\n", FILE_APPEND | LOCK_EX);
if ($failing) {
	http_response_code(503);
	echo 'down';
	return true;
}
echo 'OK';
return true;
