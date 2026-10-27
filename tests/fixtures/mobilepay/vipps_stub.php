<?php
// Router for `php -S`: a stand-in for api.vipps.no used by MobilepayWebhookSyncTest and MobilepayPaymentFlowTest.
// The scenario file (env MP_STUB_SCENARIO) holds
// {"token"|"list"|"delete"|"register"|"create"|"capture": {"code": int, "body": string}};
// every request is appended to the log file (env MP_STUB_LOG) as "METHOD PATH BODY".
// 20261027 CL/LH Also answers the ePayment create and capture calls of debitor/payments/mobilepay (PR #502).

$scenario = json_decode((string)@file_get_contents(getenv('MP_STUB_SCENARIO')), true) ?: array();
$method   = $_SERVER['REQUEST_METHOD'];
$path     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
file_put_contents(getenv('MP_STUB_LOG'), $method . ' ' . $path . ' ' . file_get_contents('php://input') . "\n", FILE_APPEND);

if ($path === '/accesstoken/get') {
	$key = 'token';
} elseif ($path === '/webhooks/v1/webhooks') {
	$key = ($method === 'POST') ? 'register' : 'list';
} elseif ($method === 'DELETE' && strpos($path, '/webhooks/v1/webhooks/') === 0) {
	$key = 'delete';
} elseif ($method === 'POST' && $path === '/epayment/v1/payments') {
	$key = 'create';
} elseif ($method === 'POST' && preg_match('#^/epayment/v1/payments/[^/]+/capture$#', $path)) {
	$key = 'capture';
} else {
	http_response_code(404);
	return true;
}
$reply = $scenario[$key] ?? array('code' => 500, 'body' => '');
http_response_code((int)$reply['code']);
header('Content-Type: application/json');
echo $reply['body'];
return true;
