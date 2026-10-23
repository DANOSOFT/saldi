<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/stripe/faktura_pay.php --- 2026.10.19
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20261019 CL/ASR Created: POST {save_card} -> creates the PaymentIntent for the invoice
//                 (one per Saldi invoice, idempotent) and returns the client secret for
//                 Stripe's Payment Element. Refuses when the invoice is paid or a payment is
//                 already in progress (Requirements par. 5.4). With {pay_now:1} for an enrolled
//                 debtor the saved card is charged off-session straight away.

$FAKTURA_JSON = true;
include(__DIR__ . '/faktura_common.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') faktura_fail(405, '', 'method', 'method');
$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) $in = $_POST;
$saveCard = !empty($in['save_card']) && $inv['recurring'] && !$inv['enrolled'];
$payNow = !empty($in['pay_now']) && $inv['enrolled'];

if (!stripe_invoice_payable($inv['state'])) {
	$msg = ($inv['state'] === 'pending') ? stripe_txt('pay_in_progress') : stripe_txt('already_paid');
	faktura_fail(409, '', $msg, 'not_payable');
}
if ($inv['pending']) faktura_fail(409, '', stripe_txt('pay_in_progress'), 'in_progress');

$res = $payNow
	? stripe_payment_create($inv, 'manual', false, (string)$inv['debtor']['stripe_pm_id'])
	: stripe_payment_create($inv, 'page', $saveCard);

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
if (!$res['ok']) {
	http_response_code(502);
	stripe_log('faktura_pay: ' . $inv['id'] . ' ' . ($res['error'] ?? ''));
	print json_encode(array('ok' => false, 'error' => (string)($res['error_code'] ?? $res['error'] ?? 'stripe'), 'message' => 'Betalingen kunne ikke startes. Prøv igen om lidt.'));
	exit;
}
if ($payNow) {
	// off-session confirm: the result is already known (or the webhook will finish it)
	$state = ($res['status'] === 'succeeded') ? 'pending' : $res['status'];
	if ($res['status'] === 'succeeded') stripe_payment_sync($res['pi']);
	print json_encode(array('ok' => true, 'pi' => $res['pi'], 'status' => $res['status']));
	exit;
}
print json_encode(array('ok' => true, 'client_secret' => $res['client_secret'], 'pi' => $res['pi']));
