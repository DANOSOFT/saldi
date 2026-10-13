<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/stripe/webhook.php --- 2026.10.13
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
// 20260807 CL/LH Created: Stripe webhook receiver. Contract:
//                doc/stripe/INTERFACE_CONTRACT.md.
// 20260818 CL/LH Hardened claims, DB failures, and invoice identity.
// 20261007 CL/LH payment_intent.* events for the hosted invoice page and the
//                autopay worker go to InvoicePaymentService::handleEvent().
// 20261012 CL/LH Lease webhook events, isolate invoice routing/import, and hold refunds/disputes.
// 20261012 CL/LH Alert on missing event migrations, retry booking mail failures and resolve unbound invoice corrections.
// 20261013 CL/LH (CodeRabbit) Record subscription payments without a recipient instead of retrying indefinitely.
//
// PILOT MODE OF RECORD = record-only: verify + dedupe + record + PDF + alert;
// nothing is booked. The import path is gated behind the import_enabled
// setting AND the boot asserts (par. 6 of the contract) - on this branch the
// asserts apply only to subscription invoice.paid imports. Missing import
// prerequisites never block unrelated subscription or invoice-payment events.
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


/**
 * Injected by includes/connect.php and stripeBootstrap.php below.
 * @var string $sqhost
 * @var string $squser
 * @var string $sqpass
 * @var string $db_type
 * @var string $db
 * @var bool $stripe_boot_ok
 */
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
require_once __DIR__ . '/../../includes/stripeIncludes/StripeEventLedger.php';
require_once __DIR__ . '/../../includes/stripeIncludes/stripeWebhookRouting.php';

if (!$stripe_boot_ok) stripe_wh_respond(503, 'unavailable');

function stripe_wh_db_select($qtext) {
	http_response_code(500);
	$query = db_select($qtext, __FILE__ . " linje " . __LINE__);
	if ($query === false) {
		stripe_log('webhook: database read failed - responding 500');
		stripe_wh_retry(500, 'database_failed');
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
		stripe_wh_retry(500, 'database_failed');
	}
}

// 20261012 CL/LH Import readiness is checked only in the subscription import branch.
$importEnabled = (stripe_setting('import_enabled', 'off') === 'on');

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
if (!is_array($event) || empty($event['id']) || empty($event['type']) || !is_array($event['data']['object'] ?? null)) {
	stripe_wh_respond(400, 'bad_payload');
}
$eventId   = (string)$event['id'];
$eventType = (string)$event['type'];
$obj       = $event['data']['object'];
$eventCreated = (int)($event['created'] ?? 0);

// 20261012 CL/LH Signature -> race-safe lease -> mode -> dispatch; no invoice runtime here.
try {
	if (in_array($db_type, ['mysql', 'mysqli'], true)) throw new RuntimeException('Stripe-webhook kræver PostgreSQL');
	$eventPdo = new PDO('pgsql:host=' . $sqhost . ';dbname=' . stripeConfigDb(), $squser, $sqpass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
	$eventLedger = new \Saldi\Stripe\StripeEventLedger($eventPdo);
	$claim = $eventLedger->receive($eventId, $eventType, base64_encode($rawBody), $eventCreated);
} catch (Throwable $e) {
	stripe_log('webhook: event store failed: ' . $e->getMessage());
	stripe_wh_event_store_alert();
	stripe_wh_respond(503, 'store_failed');
}
if ($claim['action'] === 'duplicate') stripe_wh_respond(200, 'duplicate');
if ($claim['action'] === 'busy') stripe_wh_respond(503, 'busy');
$rowId = $claim['id'];
$claimToken = $claim['token'];
set_exception_handler(static function (Throwable $e) {
	stripe_log('webhook: dispatch failed: ' . $e->getMessage());
	stripe_wh_retry(503, 'retry', $e->getMessage());
});

/** Alert operators without consuming the hourly throttle on a failed mail. */
function stripe_wh_event_store_alert(): void {
	try {
		stripeAlertMail('Stripe-webhook kan ikke gemme events - log ind i regnskabet, så databasen opdateres',
			'Regnskab: ' . stripeConfigDb() . "\nWebhooken svarer 503, så Stripe prøver igen. Log ind på /master og kontrollér stripe_events-kolonnerne.",
			'event-store-' . stripeConfigDb(), 3600);
	} catch (Throwable $e) {
		stripe_log('webhook: event store alert failed: ' . $e->getMessage());
	}
}

/** Fail an owned event immediately; infrastructure errors before a claim still return 5xx. */
function stripe_wh_retry($respCode, $message, $error = '') {
	global $eventLedger, $rowId, $claimToken;
	if (isset($eventLedger, $rowId, $claimToken)) {
		try {
			$eventLedger->fail((int)$rowId, $claimToken, stripe_redact($error !== '' ? $error : $message));
		} catch (Throwable $e) {
			stripe_log('webhook: could not release failed lease: ' . $e->getMessage());
			stripe_wh_event_store_alert();
		}
	}
	stripe_wh_respond($respCode, $message);
}

/** Complete only while this delivery still owns the lease; all retryable paths use fail(). */
function stripe_wh_finish($rowId, $status, $respCode, $respMsg, $orderId = 0, $invoiceNumber = '', $error = '') {
	global $eventLedger, $claimToken;
	if ($respCode >= 500) stripe_wh_retry($respCode, $respMsg, $error);
	$fields = ['error' => $error !== '' ? stripe_redact($error) : null];
	if ($orderId > 0) $fields['saldi_order_id'] = (int)$orderId;
	if ($invoiceNumber !== '') $fields['invoice_number'] = $invoiceNumber;
	try {
		if (!$eventLedger->finish((int)$rowId, $claimToken, $status, $fields)) stripe_wh_respond(503, 'busy');
	} catch (Throwable $e) {
		stripe_wh_event_store_alert();
		stripe_wh_retry(503, 'store_failed', $e->getMessage());
	}
	stripe_wh_respond($respCode, $respMsg);
}

$mode = stripe_setting('mode');
$liveMode = !empty($event['livemode']);
if (!in_array($mode, ['test', 'live'], true)) stripe_wh_retry(503, 'config_error');
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

/**
 * Download the subscription PDF, then insert or overwrite its pool metadata.
 * @return string Empty on success, otherwise a best-effort download diagnostic.
 */
function stripe_wh_attach_pdf($inv, $db) {
	if (empty($inv['invoice_pdf'])) return 'no pdf url';
	$number = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)(isset($inv['number']) ? $inv['number'] : $inv['id']));
	$dir = dirname(__DIR__, 2) . '/bilag/' . $db . '/pulje';
	if (!is_dir($dir) && !mkdir($dir, 0777, true)) return 'mkdir failed';
	$file = 'stripe_' . $number . '.pdf';
	$ch = curl_init($inv['invoice_pdf']);
	curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
		CURLOPT_SSL_VERIFYPEER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5]);
	$pdf = curl_exec($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	curl_close($ch);
	if ($pdf === false || $code !== 200 || strncmp((string)$pdf, '%PDF', 4) !== 0) return 'download failed (' . $code . ')';
	if (file_put_contents($dir . '/' . $file, $pdf, LOCK_EX) === false) return 'write failed';
	$amountOre = (int)(isset($inv['amount_paid']) ? $inv['amount_paid'] : 0);
	$kr = number_format($amountOre / 100, 2, ',', '.');
	$normAmount = number_format($amountOre / 100, 2, '.', '');
	// 20261012 CL/LH A retried invoice overwrites its PDF metadata instead of adding pool rows.
	$filename = db_escape_string($file);
	$invoiceNumber = db_escape_string((string)($inv['number'] ?? $inv['id']));
	$existing = db_fetch_array(stripe_wh_db_select("select id from pool_files where filename='$filename'"));
	$set = "subject='Stripe faktura $invoiceNumber', amount='" . db_escape_string($kr) . "', norm_amount=$normAmount, file_date='" . db_escape_string(date('Y-m-d')) . "', invoice_number='$invoiceNumber', description='Stripe abonnementsfaktura (automatisk hentet af webhook)', currency='DKK'";
	if ($existing) {
		stripe_wh_require_modify("update pool_files set $set where id=" . (int)$existing['id']);
	} else {
		stripe_wh_require_modify("insert into pool_files (filename, subject, amount, norm_amount, file_date, invoice_number, description, currency) values ('$filename', 'Stripe faktura $invoiceNumber', '" . db_escape_string($kr) . "', $normAmount, '" . db_escape_string(date('Y-m-d')) . "', '$invoiceNumber', 'Stripe abonnementsfaktura (automatisk hentet af webhook)', 'DKK')");
	}
	return '';
}

/**
 * Resolve invoice corrections without requiring invoice tables for retained subscriptions.
 * @return bool Whether an invoice payment was held; errors respond with retryable 503.
 */
function stripe_wh_invoice_charge($event, $charge) {
	global $eventPdo, $mode;
	$intent = $charge['payment_intent'] ?? '';
	$intentId = is_array($intent) ? (string)($intent['id'] ?? '') : (string)$intent;
	try {
		// 20261012 CL/LH Some correction snapshots supply only a charge id; resolve its intent before routing.
		if ($intentId === '' && !empty($charge['id'])) {
			$response = stripeHttpRequest('GET', '/v1/charges/' . rawurlencode((string)$charge['id']), ['expand' => ['payment_intent']]);
			if (empty($response['ok']) || !is_array($response['body'] ?? null)) throw new RuntimeException('Charge til rettelsen kunne ikke hentes');
			$intent = $response['body']['payment_intent'] ?? '';
			$charge['payment_intent'] = $intent;
			$intentId = is_array($intent) ? (string)($intent['id'] ?? '') : (string)$intent;
		}
		if ($intentId === '') return false;
		$present = $eventPdo->query("SELECT to_regclass('stripe_invoice_payments')")->fetchColumn();
		$known = false;
		if ($present) {
			$q = $eventPdo->prepare('SELECT id FROM stripe_invoice_payments WHERE mode=? AND payment_intent_id=?');
			$q->execute([$mode, $intentId]);
			$known = (bool)$q->fetchColumn();
		}
		$pi = null;
		if (!$known) {
			// 20261012 CL/LH A lost creation response leaves the durable attempt unbound; metadata still owns the correction.
			$pi = is_array($intent) && isset($intent['metadata']) ? $intent : null;
			if ($pi === null) {
				$response = stripeHttpRequest('GET', '/v1/payment_intents/' . rawurlencode($intentId), ['expand' => ['latest_charge']]);
				if (empty($response['ok']) || !is_array($response['body'] ?? null)) throw new RuntimeException('Betalingsreference til rettelsen kunne ikke hentes');
				$pi = $response['body'];
			}
			if (!stripe_wh_is_invoice_payment($pi, stripeConfigDb())) return false;
			if (!$present) throw new RuntimeException('Fakturabetalingens rettelse afventer klargøring af databasen');
		}
		include_once __DIR__ . '/../../includes/stripeIncludes/invoicePaymentRuntime.php';
		return stripe_invoice_payment_service()->chargeEvent($event, $charge, $pi);
	} catch (Throwable $e) {
		stripe_wh_retry(503, 'retry', $e->getMessage());
	}
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
		stripe_wh_retry(500, 'unmapped_customer');
	}
	stripe_wh_upsert_customer(isset($obj['customer']) ? $obj['customer'] : '', isset($obj['subscription']) ? $obj['subscription'] : '', $who['konto_id'], $who['kontonr'], $who['order_id'], 'active');

	$invoiceNumber = (string)(isset($obj['number']) ? $obj['number'] : $obj['id']);
	$paidKr  = number_format(((int)$obj['amount_paid']) / 100, 2, ',', '.');
	// 20261012 CL/LH Session advisory lock serializes retry side effects for one Stripe invoice.
	try {
		$eventPdo->prepare('SELECT pg_advisory_lock(hashtext(?))')->execute(['stripe-sub-invoice-' . (string)$obj['id']]);
	} catch (Throwable $e) { stripe_wh_retry(503, 'store_failed', $e->getMessage()); }
	$pdfNote = stripe_wh_attach_pdf($obj, $db);

	if (!$importEnabled) {
		// RECORD-ONLY (pilot mode of record): record + PDF + alert, book manually.
		$mailAccepted = stripeAlertMail("Stripe-faktura betalt - bogfør manuelt (ordre " . $who['order_id'] . ")",
			"Stripe-faktura: $invoiceNumber\nBeløb betalt: $paidKr kr (inkl. moms)\nKonto: " . $who['kontonr'] . " (id " . $who['konto_id'] . ")\nOrdre: " . $who['order_id'] . "\nAarsag: $billingReason\n"
			. ($pdfNote === '' ? "PDF er lagt i bilagspuljen (bilag/$db/pulje).\n" : "PDF kunne ikke hentes automatisk: $pdfNote - hent den i Stripe Dashboard.\n")
			. "\nWebhooken kører i record-only: fakturaen er registreret men IKKE bogført.\nBogfør den manuelt mod kortclearing-kontoen efter den faste procedure.",
			'subscription-paid-' . (string)$obj['id'], PHP_INT_MAX);
		// 20261013 CL/LH (CodeRabbit) Only transport failures with a recipient can recover through mail retries.
		if (!$mailAccepted) {
			if (stripe_alert_recipient() !== '') stripe_wh_retry(503, 'mail_failed', 'Mail til manuel bogføring kunne ikke sendes');
			stripe_log("webhook: no_alert_recipient for event $eventId invoice $invoiceNumber");
			stripe_wh_finish($rowId, 'recorded', 200, 'recorded', $who['order_id'], $invoiceNumber, 'no_alert_recipient');
		}
		stripe_wh_finish($rowId, 'recorded', 200, 'recorded', $who['order_id'], $invoiceNumber);
	}

	// 20261012 CL/LH import_enabled prerequisites belong only to this import route.
	$svc = dirname(__DIR__, 2) . '/restapi/services/ExternalPaidInvoiceImportService.php';
	$idxChk = db_fetch_array(stripe_wh_db_select("SELECT indexname FROM pg_indexes WHERE tablename='ordrer' AND indexname='ordrer_stripe_paid_invoice_uidx'"));
	if (!file_exists($svc) || !$idxChk) {
		stripeAlertMail('Stripe webhook: import_enabled men installationen er ikke komplet',
			"Abonnementsimporten kan ikke udføres. Dette event svarer 503 og forsøges igen; øvrige events behandles fortsat. Slå import_enabled fra for record-only.", 'boot_assert', 3600);
		stripe_wh_retry(503, 'import_prereqs_missing');
	}
	stripe_wh_retry(503, 'import_not_wired', 'Abonnementsimporten er ikke tilsluttet på denne installation');
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
	if (stripe_wh_invoice_charge($event, $obj)) stripe_wh_finish($rowId, 'processed', 200, 'ok');
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

// 20261012 CL/LH Every dispute correction retains the receipt and requires manual accounting, even when closed.
if (str_starts_with($eventType, 'charge.dispute.')) {
	try {
		$charge = $obj['charge'] ?? '';
		if (!is_array($charge)) {
			$response = stripeHttpRequest('GET', '/v1/charges/' . rawurlencode((string)$charge));
			if (!$response['ok']) throw new RuntimeException('Charge til indsigelse kunne ikke hentes');
			$charge = $response['body'];
		}
		if (stripe_wh_invoice_charge($event, $charge)) stripe_wh_finish($rowId, 'processed', 200, 'ok');
	} catch (Throwable $e) {
		stripe_wh_retry(503, 'retry', $e->getMessage());
	}
	stripe_wh_finish($rowId, 'ignored', 200, 'ignored');
}

// 20261012 CL/LH Reject foreign/subscription PaymentIntents before loading or constructing runtime.
if (strpos($eventType, 'payment_intent.') === 0) {
	if (!stripe_wh_is_invoice_payment($obj, stripeConfigDb())) stripe_wh_finish($rowId, 'ignored', 200, 'ignored');
	$piOrder = (int)($obj['metadata']['saldi_order_id'] ?? 0);
	try {
		include_once __DIR__ . '/../../includes/stripeIncludes/invoicePaymentRuntime.php';
		$result = stripe_invoice_payment_service()->handleEvent($event);
	} catch (Throwable $e) {
		stripe_log("webhook: invoice payment $eventId failed: " . $e->getMessage());
		stripe_wh_retry(503, 'retry', mb_substr($e->getMessage(), 0, 300));
	}
	stripe_wh_finish($rowId, $result === 'ignored' ? 'ignored' : 'processed', 200, $result === 'ignored' ? 'ignored' : 'ok', $piOrder);
}

// Unknown/unhandled events: stored (audit + replay) and ignored.
stripe_wh_finish($rowId, 'ignored', 200, 'ignored');
