<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/stripe/faktura_state.php --- 2026.10.22
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
// 20261019 CL/ASR Created: GET -> current payment state of the invoice as JSON. The page polls
//                 this after Stripe redirects back. If a PaymentIntent is still 'pending' here
//                 the state is synced from Stripe (idempotent, same handler as the webhook).
// 20261022 CDX/LH Record the signed invoice payment-state endpoint in the master port history.
// 20261022 CL/LH S5 Return the Danish decline sentence instead of Stripe's raw code.

$FAKTURA_JSON = true;
include(__DIR__ . '/faktura_common.php');

if ($inv['pending'] && !empty($inv['pending']['payment_intent_id'])) {
	stripe_payment_sync((string)$inv['pending']['payment_intent_id']);
	$inv = stripe_invoice_load($faktura_o);
}
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
$out = array('ok' => true, 'state' => $inv['state']);
if ($inv['paid']) {
	$out['paid_at'] = substr((string)$inv['paid']['succeeded_at'], 0, 10);
	$out['card'] = stripe_card_label($inv['paid']['pm_brand'], $inv['paid']['pm_last4']);
	$out['saved'] = stripe_bool($inv['paid']['save_card']);
}
if ($inv['failed']) $out['error'] = stripe_decline_text($inv['failed']['last_error']); // customer-facing: never the raw code
print json_encode($out);
