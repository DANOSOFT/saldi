<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/stripe/faktura_pay.php --- 2026.11.26
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261019 CL/ASR Created: POST {save_card} -> creates the PaymentIntent for the invoice (one per Saldi invoice, idempotent) and returns the client secret for Stripe's Payment Element. Refuses when the invoice is paid or a payment is already in progress (Requirements par. 5.4). With {pay_now:1} for an enrolled debtor the saved card is charged off-session straight away.
// 20261022 CDX/LH Gate save-card consent and saved-card collection with the enrolment switch.
// 20261022 CDX/LH Use shared eligibility; reconcile attempts inside collection before refusing or creating.
// 20261022 CDX/LH Resume the existing authentication intent; accept saved-card replacement and return effective usage.
// 20261022 CDX/LH Validate payment Origin and refuse saved-card payments before the due date.
// 20261022 CL/LH S5 Danish messages from stripe_txt(); a saved-card decline returns 'declined' with a Danish reason.
// 20261116 CDX/ASR Return actionable errors for an unusable saved card/customer and amounts below the card minimum.
// 20261126 ASR Review PAY-R2-1: an invoice paid (by card or by bank, from its open items) or credited by its credit notes is refused first, also a resume of a waiting 3-D Secure intent (409 already_paid / credited).

$FAKTURA_JSON = true;
include(__DIR__ . '/faktura_common.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') faktura_fail(405, '', 'method', 'method');
if (!stripe_invoice_origin_allowed($_SERVER['HTTP_ORIGIN'] ?? '')) faktura_fail(403, '', stripe_txt('bad_request'), 'origin');
$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) $in = $_POST;
$settled = stripe_invoice_settled_reason($inv);
if ($settled === 'credited') faktura_fail(409, '', stripe_txt('pay_credited'), 'credited');
if ($settled !== '') faktura_fail(409, '', stripe_txt('already_paid'), 'already_paid');
if (!stripe_invoice_collectable($inv)) faktura_fail(409, '', stripe_txt('lead_manual'), 'not_collectable');
if ($inv['state'] === 'below_minimum') faktura_fail(409, '', stripe_txt('pay_amount_too_small'), 'amount_too_small');
$saveCard = !empty($in['save_card']) && stripe_invoice_save_allowed($inv);
if (!empty($in['pay_now']) && !stripe_feature_on('enrolment')) {
	faktura_fail(409, '', stripe_txt('not_active_txt'), 'enrolment_disabled');
}
$payNow = !empty($in['pay_now']) && $inv['enrolled'];
if (!empty($in['pay_now']) && !stripe_invoice_pay_now_allowed($inv)) {
	faktura_fail(409, '', stripe_txt('pay_not_due'), 'not_due');
}

$res = !empty($in['resume']) ? stripe_payment_resume($inv['id']) : ($payNow
	? stripe_payment_create($inv, 'manual', false, (string)$inv['debtor']['stripe_pm_id'])
	: stripe_payment_create($inv, 'page', $saveCard));

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
if (!$res['ok']) {
	$error = stripe_payment_failure_response($res);
	if ($error['status'] === 502 || $error['code'] === 'card_unavailable') {
		stripe_log('faktura_pay: ' . $inv['id'] . ' ' . ($res['error'] ?? ''));
	}
	faktura_fail($error['status'], '', $error['message'], $error['code']);
}
if ($payNow || !empty($in['resume'])) {
	// off-session confirm: the result is already known (or the webhook will finish it)
	$state = ($res['status'] === 'succeeded') ? 'pending' : $res['status'];
	if ($res['status'] === 'succeeded') stripe_payment_sync($res['pi']);
	print json_encode(array('ok' => true, 'pi' => $res['pi'], 'status' => $res['status'], 'client_secret' => $res['client_secret'] ?? ''));
	exit;
}
print json_encode(array('ok' => true, 'client_secret' => $res['client_secret'], 'pi' => $res['pi'], 'setup_future_usage' => $res['setup_future_usage'] ?? null));
