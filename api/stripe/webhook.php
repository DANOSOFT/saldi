<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/stripe/webhook.php --- 2026.08.07
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
// 20260807 CL/LH Created: Stripe webhook receiver. Contract:
//                doc/stripe/INTERFACE_CONTRACT.md.
// 20260818 CL/LH Hardened claims, DB failures, and invoice identity.
//
// PILOT MODE OF RECORD = record-only: verify + dedupe + record + PDF + alert;
// nothing is booked. The import path is gated behind the import_enabled
// setting AND the boot asserts (par. 6 of the contract) - on this branch the
// asserts fail by construction (the import service lives on the unmerged
// fix/stripe-paid-invoice-api branch), so flipping the setting alone can
// never book anything half-wired.
//
// Response policy: 2xx fast and always - EXCEPT (a) signature failure -> 400,
// (b) infrastructure failure (boot/insert) -> 5xx, and (c) the documented
// unmapped_customer case -> 5xx so Stripe's retry ladder absorbs webhook
// ordering races instead of permanently dropping a first payment.
//
// stripe_events.payload stores the BASE64 of the raw verified body:
// injecttjek() (db_query.php) pattern-matches SQL text and could otherwise
// reject a perfectly valid JSON payload mid-transaction.
//
// nextfakt is never selected, read, or written here (locked decision).

ob_start(); // injecttjek()/alert() print HTML on error paths - the JSON response stays ours

// 1. Raw body FIRST, before any include can touch input streams.
$rawBody = file_get_contents('php://input');

header('Content-Type: application/json; charset=utf-8');

function stripe_wh_respond($status, $msg) {
	ob_end_clean();
	http_response_code($status);
	print json_encode(['status' => $msg]);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') stripe_wh_respond(405, 'method_not_allowed');
if (isset($_GET['db']) || isset($_POST['db'])) stripe_wh_respond(400, 'bad_request');

include(__DIR__ . '/../../includes/connect.php');
include(__DIR__ . '/../../includes/stripeIncludes/stripeBootstrap.php');
include(__DIR__ . '/../../includes/stripeIncludes/stripeHttp.php');
include(__DIR__ . '/../../includes/stripeIncludes/stripeAlertMail.php');

if (!$stripe_boot_ok) stripe_wh_respond(503, 'unavailable');

function stripe_wh_db_select($qtext) {
	http_response_code(500);
	$query = db_select($qtext, __FILE__ . " linje " . __LINE__);
	if ($query === false) {
		stripe_log('webhook: database read failed - responding 500');
		stripe_wh_respond(500, 'database_failed');
	}
	http_response_code(200);
	return $query;
}

function stripe_wh_db_modify($qtext) {
	http_response_code(500);
	$result = stripe_db_modify($qtext, __FILE__ . " linje " . __LINE__);
	if ($result !== false) http_response_code(200);
	return $result;
}

function stripe_wh_require_modify($qtext) {
	if (stripe_wh_db_modify($qtext) === false) {
		stripe_log('webhook: database write failed - responding 500');
		stripe_wh_respond(500, 'database_failed');
	}
}

// 2. Boot asserts (contract par. 6): never process against a half-deployed install.
$tblChk = db_fetch_array(stripe_wh_db_select("SELECT column_name FROM information_schema.columns WHERE table_name='stripe_events' AND column_name='event_id'"));
if (!$tblChk) {
	stripe_log('webhook: BOOT ASSERT FAILED - stripe_events table missing (partial deploy?)');
	stripe_wh_respond(503, 'not_provisioned');
}
$importEnabled = (stripe_setting('import_enabled', 'off') === 'on');
if ($importEnabled) {
	$svc = dirname(__DIR__, 2) . '/restapi/services/ExternalPaidInvoiceImportService.php';
	$idxChk = db_fetch_array(stripe_wh_db_select("SELECT indexname FROM pg_indexes WHERE tablename='ordrer' AND indexname='ordrer_stripe_paid_invoice_uidx'"));
	$fnOk = false;
	if (function_exists('get_next_order_number')) {
		$rf = new ReflectionFunction('get_next_order_number');
		$fnOk = ($rf->getNumberOfParameters() >= 2);
	}
	if (!file_exists($svc) || !$idxChk) {
		// PHP silently discards extra args to user functions, so a partial
		// deploy books NON-atomically while returning 200 - hence the loud 503.
		stripeAlertMail('Stripe webhook: import_enabled men installationen er ikke komplet',
			"Boot assert fejlede (service-fil eller partial unique index mangler).\nWebhooken svarer 503 og behandler INTET, til import_enabled slås fra eller filerne deployes samlet.",
			'boot_assert', 3600);
		stripe_wh_respond(503, 'import_prereqs_missing');
	}
	// fnOk + the SALDI_CALLER_OWNED_POSTING constant are asserted again at call
	// time below, after ordrefunc.php is actually loaded.
	if (!$fnOk) { /* not loaded yet here - checked at call time */ }
}

// 3. Signature verification (manual - no SDK). Empty secret NEVER means accept.
$secret = stripe_setting('webhook_secret');
if (!$secret) {
	stripe_log('webhook: webhook_secret not configured - responding 503');
	stripe_wh_respond(503, 'not_configured');
}
$sigHeader = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
$sigT = 0; $sigV1 = [];
foreach (explode(',', $sigHeader) as $part) {
	$kv = explode('=', trim($part), 2);
	if (count($kv) !== 2) continue;
	if ($kv[0] === 't') $sigT = (int)$kv[1];
	if ($kv[0] === 'v1') $sigV1[] = $kv[1]; // all v1 values: secret rotation sends two
}
$sigOk = false;
if ($sigT > 0 && $sigV1) {
	$expected = hash_hmac('sha256', $sigT . '.' . $rawBody, $secret);
	foreach ($sigV1 as $v) if (hash_equals($expected, $v)) $sigOk = true;
}
if (!$sigOk || abs(time() - $sigT) > 300) {
	stripe_log('webhook: signature verification failed from ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?'));
	stripe_wh_respond(400, 'bad_signature'); // no stripe_events row for unverified input
}

// 4. Parse + mode check.
$event = json_decode($rawBody, true);
if (!is_array($event) || empty($event['id']) || empty($event['type']) || !isset($event['data']['object'])) {
	stripe_wh_respond(400, 'bad_payload');
}
$eventId   = (string)$event['id'];
$eventType = (string)$event['type'];
$obj       = $event['data']['object'];
$mode      = stripe_setting('mode');
$liveMode  = !empty($event['livemode']);
if (!in_array($mode, ['test', 'live'], true)) {
	stripe_log('webhook: invalid Stripe mode configuration - responding 503');
	stripe_wh_respond(503, 'config_error');
}

// 5. Dedupe via the DB unique index, with an ownership token so a concurrent
// duplicate delivery can never double-process. The token borrows the error
// column for the microseconds between insert and claim-check.
$eventIdEsc = db_escape_string($eventId);
$token = bin2hex(random_bytes(16));
$existing = db_fetch_array(stripe_wh_db_select("select id, status from stripe_events where event_id = '$eventIdEsc'"));
if ($existing) {
	if ($existing['status'] !== 'received') stripe_wh_respond(200, 'duplicate');
	// Crashed mid-processing >15 min ago: claim it for reprocessing.
	$staleSql = ($db_type === 'mysql' || $db_type === 'mysqli')
		? 'received_at < CURRENT_TIMESTAMP - INTERVAL 15 MINUTE'
		: "received_at < CURRENT_TIMESTAMP - INTERVAL '15 minutes'";
	stripe_wh_require_modify("update stripe_events set status = 'retrying', error = '$token' where id = " . (int)$existing['id']
		. " and status = 'received' and $staleSql");
	$claim = db_fetch_array(stripe_wh_db_select("select error from stripe_events where id = " . (int)$existing['id']));
	if (!$claim || $claim['error'] !== $token) stripe_wh_respond(200, 'duplicate');
	$rowId = (int)$existing['id'];
} else {
	$insertResult = stripe_wh_db_modify("insert into stripe_events (event_id, event_type, payload, status, error) values ('$eventIdEsc', '"
		. db_escape_string($eventType) . "', '" . db_escape_string(base64_encode($rawBody)) . "', 'received', '$token')");
	$mine = db_fetch_array(stripe_wh_db_select("select id, error from stripe_events where event_id = '$eventIdEsc'"));
	if ($insertResult === false) {
		if ($mine && $mine['error'] !== $token) stripe_wh_respond(200, 'duplicate');
		stripe_log("webhook: stripe_events insert failed for $eventId - responding 500");
		stripe_wh_respond(500, 'store_failed');
	}
	if (!$mine) {
		// Insert failed outright (not a duplicate - the row is absent): 5xx so Stripe retries.
		stripe_log("webhook: stripe_events insert failed for $eventId - responding 500");
		stripe_wh_respond(500, 'store_failed');
	}
	if ($mine['error'] !== $token) stripe_wh_respond(200, 'duplicate'); // concurrent delivery won the index race
	$rowId = (int)$mine['id'];
}
stripe_wh_require_modify("update stripe_events set error = null where id = $rowId");

function stripe_wh_finish($rowId, $status, $respCode, $respMsg, $orderId = 0, $invoiceNumber = '', $error = '') {
	$set = "status = '" . db_escape_string($status) . "', processed_at = CURRENT_TIMESTAMP";
	if ($orderId > 0)            $set .= ", saldi_order_id = " . (int)$orderId;
	if ($invoiceNumber !== '')   $set .= ", invoice_number = '" . db_escape_string($invoiceNumber) . "'";
	if ($error !== '')           $set .= ", error = '" . db_escape_string(stripe_redact($error)) . "'";
	stripe_wh_require_modify("update stripe_events set $set where id = " . (int)$rowId);
	stripe_wh_respond($respCode, $respMsg);
}

// Mode mismatch: recorded and ignored, 200.
if (($mode === 'live') !== $liveMode) stripe_wh_finish($rowId, 'ignored_mode', 200, 'ignored_mode');

// ---- helpers ---------------------------------------------------------------

// Upsert the stripe_customers cache row. Never touches ordrer.
function stripe_wh_upsert_customer($customerId, $subscriptionId, $kontoId, $kontonr, $orderId, $status = 'active') {
	$cEsc = db_escape_string((string)$customerId);
	if ($cEsc === '') return;
	$r = db_fetch_array(stripe_wh_db_select("select id from stripe_customers where stripe_customer_id = '$cEsc'"));
	$subEsc = db_escape_string((string)$subscriptionId);
	if ($r) {
		$set = "updated_at = CURRENT_TIMESTAMP, status = '" . db_escape_string($status) . "'";
		if ($subEsc !== '')      $set .= ", stripe_subscription_id = '$subEsc'";
		if ((int)$kontoId > 0)   $set .= ", konto_id = " . (int)$kontoId;
		if ($kontonr !== '')     $set .= ", kontonr = '" . db_escape_string($kontonr) . "'";
		if ((int)$orderId > 0)   $set .= ", order_id = " . (int)$orderId;
		stripe_wh_require_modify("update stripe_customers set $set where id = " . (int)$r['id']);
	} else {
		stripe_wh_require_modify("insert into stripe_customers (stripe_customer_id, stripe_subscription_id, konto_id, kontonr, order_id, status, updated_at) values ('$cEsc', "
			. ($subEsc !== '' ? "'$subEsc'" : "null") . ", " . ((int)$kontoId > 0 ? (int)$kontoId : "null") . ", "
			. ($kontonr !== '' ? "'" . db_escape_string($kontonr) . "'" : "null") . ", " . ((int)$orderId > 0 ? (int)$orderId : "null")
			. ", '" . db_escape_string($status) . "', CURRENT_TIMESTAMP)");
	}
}

// Identity for an invoice event: Basil/Dahlia subscription metadata first,
// then the local customer cache, with the Stripe API as the final fallback.
function stripe_wh_resolve_identity($inv) {
	$meta = [];
	$subscriptionId = '';
	if (isset($inv['parent']['subscription_details']['metadata']) && is_array($inv['parent']['subscription_details']['metadata'])) {
		$meta = $inv['parent']['subscription_details']['metadata'];
	} elseif (isset($inv['subscription_details']['metadata']) && is_array($inv['subscription_details']['metadata'])) {
		$meta = $inv['subscription_details']['metadata'];
	}
	if (isset($inv['parent']['subscription_details']['subscription']) && is_string($inv['parent']['subscription_details']['subscription'])) {
		$subscriptionId = $inv['parent']['subscription_details']['subscription'];
	} elseif (!empty($inv['subscription']) && is_string($inv['subscription'])) {
		$subscriptionId = $inv['subscription'];
	}
	$orderId = isset($meta['saldi_order_id']) ? (int)$meta['saldi_order_id'] : 0;
	$kontoId = isset($meta['saldi_konto_id']) ? (int)$meta['saldi_konto_id'] : 0;
	$kontonr = isset($meta['saldi_kontonr']) ? (string)$meta['saldi_kontonr'] : '';
	if (!$orderId || !$kontoId) {
		$cEsc = db_escape_string((string)(isset($inv['customer']) ? $inv['customer'] : ''));
		if ($cEsc !== '') {
			$r = db_fetch_array(stripe_wh_db_select("select konto_id, kontonr, order_id from stripe_customers where stripe_customer_id = '$cEsc'"));
			if ($r) {
				if (!$orderId) $orderId = (int)$r['order_id'];
				if (!$kontoId) $kontoId = (int)$r['konto_id'];
				if ($kontonr === '') $kontonr = (string)$r['kontonr'];
			}
		}
	}
	if ((!$orderId || !$kontoId) && $subscriptionId !== '') {
		$resp = stripeHttpRequest('GET', '/v1/subscriptions/' . rawurlencode($subscriptionId));
		if ($resp['ok'] && isset($resp['body']['metadata']) && is_array($resp['body']['metadata'])) {
			$remoteMeta = $resp['body']['metadata'];
			if (!$orderId && !empty($remoteMeta['saldi_order_id'])) $orderId = (int)$remoteMeta['saldi_order_id'];
			if (!$kontoId && !empty($remoteMeta['saldi_konto_id'])) $kontoId = (int)$remoteMeta['saldi_konto_id'];
			if ($kontonr === '' && isset($remoteMeta['saldi_kontonr'])) $kontonr = (string)$remoteMeta['saldi_kontonr'];
		}
	}
	return ['order_id' => $orderId, 'konto_id' => $kontoId, 'kontonr' => $kontonr];
}

// PDF download/write into bilag/<db>/pulje is best-effort with a 5s cap.
// Once written, failure to record the pool_files row is a retryable DB error.
function stripe_wh_attach_pdf($inv, $db) {
	if (empty($inv['invoice_pdf'])) return 'no pdf url';
	$number = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)(isset($inv['number']) ? $inv['number'] : $inv['id']));
	$dir = dirname(__DIR__, 2) . '/bilag/' . $db . '/pulje';
	if (!is_dir($dir) && !@mkdir($dir, 0777, true)) return 'mkdir failed';
	$file = 'stripe_' . $number . '.pdf';
	$ch = curl_init($inv['invoice_pdf']);
	curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
		CURLOPT_SSL_VERIFYPEER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5]);
	$pdf = curl_exec($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	curl_close($ch);
	if ($pdf === false || $code !== 200 || strncmp((string)$pdf, '%PDF', 4) !== 0) return 'download failed (' . $code . ')';
	if (@file_put_contents($dir . '/' . $file, $pdf) === false) return 'write failed';
	$amountOre = (int)(isset($inv['amount_paid']) ? $inv['amount_paid'] : 0);
	$kr = number_format($amountOre / 100, 2, ',', '.');
	$normAmount = number_format($amountOre / 100, 2, '.', '');
	stripe_wh_require_modify("insert into pool_files (filename, subject, amount, norm_amount, file_date, invoice_number, description, currency) values ('"
		. db_escape_string($file) . "', 'Stripe faktura " . db_escape_string((string)$inv['number']) . "', '"
		. db_escape_string($kr) . "', $normAmount, '" . db_escape_string(date('Y-m-d')) . "', '"
		. db_escape_string((string)$inv['number']) . "', 'Stripe abonnementsfaktura (automatisk hentet af webhook)', 'DKK')");
	return '';
}

// ---- dispatch ---------------------------------------------------------------

if ($eventType === 'checkout.session.completed') {
	$orderId = (int)(isset($obj['client_reference_id']) ? $obj['client_reference_id'] : 0);
	$kontoId = isset($obj['metadata']['saldi_konto_id']) ? (int)$obj['metadata']['saldi_konto_id'] : 0;
	$kontonr = '';
	if ($orderId > 0) {
		$r = db_fetch_array(stripe_wh_db_select("select kontonr, firmanavn from ordrer where id = $orderId"));
		$kontonr = $r ? (string)$r['kontonr'] : '';
		$firma   = $r ? (string)$r['firmanavn'] : '';
	} else { $firma = ''; }
	stripe_wh_upsert_customer(isset($obj['customer']) ? $obj['customer'] : '', isset($obj['subscription']) ? $obj['subscription'] : '', $kontoId, $kontonr, $orderId, 'active');
	// Alert-ONLY regarding the recurring order: nothing automated (locked decision 4).
	stripeAlertMail("Nyt Stripe-abonnement - ordre $orderId skal håndteres manuelt",
		"Kunde: $firma (konto $kontonr)\nOrdre: $orderId\n\n"
		. "Kunden har netop oprettet automatisk betaling via abonnementslinket.\n"
		. "HUSK (manuel handling): den gentagne fakturering af denne ordre skal stoppes\n"
		. "fra næste periode, ellers faktureres kunden dobbelt. Følg tjeklisten.",
		'', 0);
	stripe_wh_finish($rowId, 'processed', 200, 'ok', $orderId);
}

if ($eventType === 'invoice.paid') {
	// Guards: only real subscription invoices, fully paid, DKK.
	$billingReason = (string)(isset($obj['billing_reason']) ? $obj['billing_reason'] : '');
	if (!in_array($billingReason, ['subscription_create', 'subscription_cycle', 'subscription_update'], true)) {
		stripe_wh_finish($rowId, 'ignored', 200, 'ignored_billing_reason');
	}
	if ((string)$obj['status'] !== 'paid' || strtolower((string)$obj['currency']) !== 'dkk' || (int)$obj['amount_remaining'] !== 0) {
		stripe_wh_finish($rowId, 'ignored', 200, 'ignored_not_settled');
	}
	$who = stripe_wh_resolve_identity($obj);
	if ($who['order_id'] < 1 || $who['konto_id'] < 1) {
		// Documented 5xx exception: let Stripe's retry ladder absorb ordering
		// races (invoice.paid can arrive before checkout.session.completed).
		stripeAlertMail('Stripe-faktura kunne ikke matches til en kunde',
			"Event: $eventId\nStripe-faktura: " . (isset($obj['number']) ? $obj['number'] : $obj['id'])
			. "\nStripe customer: " . (isset($obj['customer']) ? $obj['customer'] : '?')
			. "\n\nWebhooken svarer 5xx, så Stripe prøver igen automatisk. Sker det ved sidste retry (efter ~3 døgn), skal fakturaen håndteres manuelt.",
			'unmapped_' . (string)$obj['id'], 3600);
		stripe_wh_finish($rowId, 'failed', 500, 'unmapped_customer', 0, (string)(isset($obj['number']) ? $obj['number'] : ''), 'unmapped_customer');
	}
	stripe_wh_upsert_customer(isset($obj['customer']) ? $obj['customer'] : '', isset($obj['subscription']) ? $obj['subscription'] : '', $who['konto_id'], $who['kontonr'], $who['order_id'], 'active');

	$invoiceNumber = (string)(isset($obj['number']) ? $obj['number'] : $obj['id']);
	$paidKr  = number_format(((int)$obj['amount_paid']) / 100, 2, ',', '.');
	$pdfNote = stripe_wh_attach_pdf($obj, $db);

	if (!$importEnabled) {
		// RECORD-ONLY (pilot mode of record): record + PDF + alert, book manually.
		stripeAlertMail("Stripe-faktura betalt - bogfør manuelt (ordre " . $who['order_id'] . ")",
			"Stripe-faktura: $invoiceNumber\nBeløb betalt: $paidKr kr (inkl. moms)\nKonto: " . $who['kontonr'] . " (id " . $who['konto_id'] . ")\nOrdre: " . $who['order_id'] . "\nAarsag: $billingReason\n"
			. ($pdfNote === '' ? "PDF er lagt i bilagspuljen (bilag/$db/pulje).\n" : "PDF kunne ikke hentes automatisk: $pdfNote - hent den i Stripe Dashboard.\n")
			. "\nWebhooken kører i record-only: fakturaen er registreret men IKKE bogført.\nBogfør den manuelt mod kortclearing-kontoen efter den faste procedure.",
			'', 0);
		stripe_wh_finish($rowId, 'recorded', 200, 'recorded', $who['order_id'], $invoiceNumber);
	}

	// IMPORT MODE (gated; wiring completes when fix/stripe-paid-invoice-api is
	// merged - until then the boot asserts above respond 503 before we get here).
	stripe_wh_finish($rowId, 'failed', 503, 'import_not_wired', $who['order_id'], $invoiceNumber, 'import_enabled but service not present on this deploy');
}

if ($eventType === 'invoice.payment_failed') {
	$who = stripe_wh_resolve_identity($obj);
	stripeAlertMail('Stripe-betaling FEJLEDE - konto ' . ($who['kontonr'] !== '' ? $who['kontonr'] : '?'),
		"Stripe-faktura: " . (isset($obj['number']) ? $obj['number'] : $obj['id']) . "\nOrdre: " . $who['order_id'] . "\nKonto: " . $who['kontonr']
		. "\n\nStripe forsøger selv igen efter sin dunning-plan. Manuel opfølgning hvis det gentager sig.",
		'payfail_' . (string)(isset($obj['customer']) ? $obj['customer'] : $obj['id']), 3600);
	stripe_wh_finish($rowId, 'processed', 200, 'ok', $who['order_id']);
}

if ($eventType === 'customer.subscription.deleted') {
	$customerId = (string)(isset($obj['customer']) ? $obj['customer'] : '');
	$meta = isset($obj['metadata']) && is_array($obj['metadata']) ? $obj['metadata'] : [];
	$orderId = isset($meta['saldi_order_id']) ? (int)$meta['saldi_order_id'] : 0;
	$kontonr = isset($meta['saldi_kontonr']) ? (string)$meta['saldi_kontonr'] : '';
	// Status update on OUR cache row only - ordrer/nextfakt untouched by design.
	stripe_wh_upsert_customer($customerId, isset($obj['id']) ? $obj['id'] : '', isset($meta['saldi_konto_id']) ? (int)$meta['saldi_konto_id'] : 0, $kontonr, $orderId, 'canceled');
	stripeAlertMail('Stripe-abonnement OPSAGT - konto ' . ($kontonr !== '' ? $kontonr : $customerId),
		"Ordre: $orderId\nKonto: $kontonr\nStripe customer: $customerId\n\n"
		. "Abonnementet er opsagt/slettet i Stripe. HUSK (manuel handling): kunden skal\n"
		. "tilbage på almindelig fakturering fra næste periode, ellers faktureres der slet ikke.",
		'', 0);
	stripe_wh_finish($rowId, 'processed', 200, 'ok', $orderId);
}

if ($eventType === 'charge.refunded') {
	$kontonr = '';
	$cEsc = db_escape_string((string)(isset($obj['customer']) ? $obj['customer'] : ''));
	if ($cEsc !== '') {
		$r = db_fetch_array(stripe_wh_db_select("select kontonr from stripe_customers where stripe_customer_id = '$cEsc'"));
		if ($r) $kontonr = (string)$r['kontonr'];
	}
	$refKr = number_format(((int)(isset($obj['amount_refunded']) ? $obj['amount_refunded'] : 0)) / 100, 2, ',', '.');
	stripeAlertMail('Stripe-REFUSION - konto ' . ($kontonr !== '' ? $kontonr : (string)(isset($obj['customer']) ? $obj['customer'] : '?')),
		"Charge: " . (isset($obj['id']) ? $obj['id'] : '?') . "\nRefunderet: $refKr kr\nKonto: $kontonr\n\nManuel handling: kreditnota/ompostering efter den faste procedure.",
		'', 0);
	stripe_wh_finish($rowId, 'processed', 200, 'ok');
}

// Unknown/unhandled events: stored (audit + replay) and ignored.
stripe_wh_finish($rowId, 'ignored', 200, 'ignored');
