<?php
// 20261019 CL/LH MB-74: Check the order-line replay fingerprint, its per-render token and tenant/user/order boundaries.
// Run: php tests/integration/orderLineReplay.php
require_once __DIR__ . '/../../debitor/orderIncludes/orderLineReplay.php';
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

$checks = 0;
/** @return void */
function checkReplay($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
	$GLOBALS['checks']++;
}

$session = [];
$post = ['save' => 'Gem', 'id' => '42', 'order_form_token' => orderFormToken(), 'posn0' => '1', 'vare0' => '1000', 'dkan0' => '2'];
$key = orderLineReplayKey($post, 'tenant_a', 'user_a');
checkReplay($key !== null, 'A POST with a new line has a fingerprint');
checkReplay(!orderLineIsReplay($session, 42, $key), 'The first save adds the line');
checkReplay(!orderLineIsReplay($session, 42, $key), 'A save that was not remembered (failed) is validated again');
orderLineRememberSave($session, 42, $key);
checkReplay(orderLineIsReplay($session, 42, $key), 'Reload/resend/double-click of the saved POST is a replay');
checkReplay(!orderLineIsReplay($session, 43, $key), 'Replays are scoped to the order');

$newRender = $post;
$newRender['order_form_token'] = orderFormToken();
checkReplay(!orderLineIsReplay($session, 42, orderLineReplayKey($newRender, 'tenant_a', 'user_a')),
	'The same item entered again from a new render is added');
$changed = $post;
$changed['dkan0'] = '3';
checkReplay(!orderLineIsReplay($session, 42, orderLineReplayKey($changed, 'tenant_a', 'user_a')), 'A changed form is not a replay');
foreach ([['tenant_b', 'user_a'], ['tenant_a', 'user_b']] as [$tenant, $user]) {
	checkReplay(!orderLineIsReplay($session, 42, orderLineReplayKey($post, $tenant, $user)), 'Replays are scoped to tenant/user');
}

$descriptionOnly = $post;
$descriptionOnly['vare0'] = '';
$descriptionOnly['beskrivelse0'] = 'Tekstlinje';
checkReplay(orderLineReplayKey($descriptionOnly, 'tenant_a', 'user_a') !== null, 'A text-only new line has a fingerprint');
$noLine = $post;
$noLine['vare0'] = ' ';
checkReplay(orderLineReplayKey($noLine, 'tenant_a', 'user_a') === null, 'A POST without a new line has no fingerprint');
foreach ([null, [], 'invalid', strtoupper($post['order_form_token'])] as $token) {
	$invalid = $post;
	$invalid['order_form_token'] = $token;
	checkReplay(orderLineReplayKey($invalid, 'tenant_a', 'user_a') === null, 'A missing or invalid form token disables the guard without warnings');
}
foreach ([null, '0', 'abc'] as $id) {
	$noOrder = $post;
	$noOrder['id'] = $id;
	checkReplay(orderLineReplayKey($noOrder, 'tenant_a', 'user_a') === null, 'A POST without an order id has no fingerprint');
}
checkReplay(!orderLineIsReplay($session, 42, null), 'No fingerprint is never a replay');
orderLineRememberSave($session, 44, null);
checkReplay(!isset($session['order_line_saves'][44]), 'No fingerprint is never remembered');

for ($i = 1; $i <= 200; $i++) {
	orderLineRememberSave($session, 1000 + $i, hash('sha256', (string)$i));
}
checkReplay(count($session['order_line_saves']) === 128, 'Remembered saves are bounded');
orderLineRememberSave($session, 1073, $key);
checkReplay(array_key_last($session['order_line_saves']) === 1073, 'A new save for an order moves it to the newest end');
echo "$checks order line replay checks passed with warnings enabled.\n";
