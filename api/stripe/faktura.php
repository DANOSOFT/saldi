<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/stripe/faktura.php --- 2026.10.12
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
// 20261007 CL/LH Created: hosted invoice page ("Se faktura og betal").
//                Contract: doc/stripe/INVOICE_PAYMENT.md.
// 20261012 CL/LH Explain collection holds and hide payment until staff releases them.
// 20261012 CL/LH Render committed payment outcomes and confirm the current intent amount.
// 20261012 CL/LH Versioned consent text, effective autopay state, confirmed outcomes only,
//                truthful card-storage text and a reachable "Fjern kort" link (AP-15/21/30, LIFE-32/37).
//
// GET  ?id&sig                   = verify the pay1 HMAC, show the invoice with
//                                  an embedded Stripe Payment Element. No
//                                  Stripe calls and no payment/card writes
//                                  (mail scanners); the rate limiter writes
//                                  its counter files in temp/.
// GET  ?id&sig&payment_intent=pi = return from Stripe: retrieve the intent,
//                                  record it (receipt, card enrichment,
//                                  settlement), show the outcome.
// POST JSON {id,sig,save,terms}  = create/reuse the PaymentIntent and return
//                                  its client secret to the page script.
//                                  terms = consent version shown on the page.
//
// Every dynamic value goes through htmlspecialchars. The only script is the
// nonce'd inline block below plus js.stripe.com (CSP enforced).
// 20261012 CL/LH Document the boot status injected by stripeBootstrap.php.

/**
 * Injected by includes/stripeIncludes/stripeBootstrap.php, included below:
 * @var bool $stripe_boot_ok
 */

ob_start();

$nonce = base64_encode(random_bytes(16));
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://js.stripe.com 'nonce-$nonce'; "
	. "frame-src https://js.stripe.com https://hooks.stripe.com; connect-src 'self' https://api.stripe.com https://m.stripe.network; "
	. "img-src 'self' data: https://*.stripe.com; style-src 'self' 'unsafe-inline'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

if (isset($_GET['db']) || isset($_POST['db']) || isset($_REQUEST['db'])) {
	http_response_code(400);
	ob_end_clean();
	print 'Bad request';
	exit;
}

include(__DIR__ . "/../../includes/connect.php");
include(__DIR__ . "/../../includes/stripeIncludes/stripeBootstrap.php");
include(__DIR__ . "/../../includes/stripeIncludes/stripePage.php");
include(__DIR__ . "/../../includes/stripeIncludes/stripeLink.php");
include(__DIR__ . "/../../includes/stripeIncludes/stripeRateLimit.php");
include(__DIR__ . "/../../includes/stripeIncludes/stripeAlertMail.php");
include(__DIR__ . "/../../includes/stripeIncludes/stripeHttp.php");
include(__DIR__ . "/../../includes/stripeIncludes/invoicePaymentRuntime.php");

use Saldi\Stripe\InvoicePaymentMath as M;
use Saldi\Stripe\InvoicePaymentConsent as Consent;

function fp_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function fp_json($status, $data) {
	http_response_code($status);
	while (ob_get_level()) ob_end_clean();
	header('Content-Type: application/json; charset=utf-8');
	print json_encode($data, JSON_UNESCAPED_UNICODE);
	exit;
}

function fp_fail($status, $headline, $text) {
	stripe_status_page($status, 'warn', $headline, [$text]);
}

// 20261012 CL/LH One plain sentence per reason the invoice cannot be paid by card here.
function fp_block_text($reason, $fakturanr) {
	$texts = [
		'pbs' => 'Faktura ' . $fakturanr . ' opkræves via Betalingsservice, så du skal ikke betale den her.',
		'subscription' => 'Du betaler Danosoft via et kortabonnement, så faktura ' . $fakturanr . ' kan ikke betales med kort her.',
		'opted_out' => 'Kortbetaling er slået fra for din konto, så faktura ' . $fakturanr . ' betales via bankoverførsel.',
		'disabled' => 'Kortbetaling er ikke mulig lige nu, så faktura ' . $fakturanr . ' betales via bankoverførsel.',
		'collection_hold' => 'Faktura ' . $fakturanr . ' er sat på hold efter en refusion eller indsigelse og bliver ikke trukket på kort, før Danosoft har afstemt den.',
		'currency' => 'Faktura ' . $fakturanr . ' er ikke i danske kroner og kan ikke betales med kort her.',
	];
	return $texts[$reason] ?? '';
}

function fp_card_name($card) {
	return ucfirst((string)$card['brand']) . ' •••• ' . (string)$card['last4'];
}

// 20261012 CL/LH Keep "Fjern kort" reachable on paid, processing, held and blocked pages (AP-21).
function fp_card_box($card, $kontoId) {
	if (!$card) return '';
	$url = stripe_card_url((int)$kontoId);
	return "<p class='muted'>Gemt kort: " . fp_h(fp_card_name($card))
		. ($url !== '' ? " &middot; <a href='" . fp_h($url) . "'>Fjern kort</a>" : '') . "</p>";
}

// Wider two-column shell; same tokens as stripePage.php so every Stripe page
// looks like one product. $script is emitted with the CSP nonce.
function fp_page($title, $left, $right, $nonce, $script = '') {
	http_response_code(200);
	while (ob_get_level()) ob_end_clean();
	print "<!DOCTYPE html><html lang='da'><head><meta charset='utf-8'>"
		. "<meta name='viewport' content='width=device-width, initial-scale=1'>"
		. "<meta name='robots' content='noindex,nofollow'>"
		. "<title>" . fp_h($title) . "</title><style>
body{margin:0;padding:40px 16px 24px;background:#f3f5f9;font-family:'Inter','Segoe UI',system-ui,-apple-system,Arial,sans-serif;color:#1f2a44}
.page{max-width:1040px;margin:0 auto}
.logo{display:block;height:30px;margin:0 auto 26px}
.grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(0,1fr);gap:24px;align-items:start}
.card{background:#fff;border:1px solid #e3e8f2;border-radius:14px;padding:32px 36px 28px;box-shadow:0 1px 2px rgba(22,62,128,.06),0 12px 32px rgba(22,62,128,.07)}
h1{margin:0 0 6px;font-size:22px;line-height:1.3;font-weight:700;color:#163e80}
h2{margin:0 0 14px;font-size:18px;line-height:1.35;font-weight:700;color:#163e80}
p{margin:0 0 12px;font-size:15px;line-height:1.6;color:#475069}
a{color:#2563eb}
.eyebrow{margin:0 0 6px;font-size:13px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#6b7590}
.meta{display:flex;flex-wrap:wrap;gap:6px 24px;margin:0 0 20px;font-size:14px;color:#475069}
.meta b{color:#1f2a44;font-weight:600}
table{width:100%;border-collapse:collapse;margin:8px 0 16px}
th{font-size:12px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#6b7590;text-align:left;padding:0 0 10px;border-bottom:1px solid #e3e8f2}
td{font-size:15px;color:#1f2a44;padding:12px 0;border-bottom:1px solid #eef1f7;vertical-align:top}
td.r,th.r{text-align:right;padding-left:12px;white-space:nowrap;font-variant-numeric:tabular-nums}
td.txt{color:#6b7590;font-size:14px}
tr.sum td{border-bottom:none;padding:6px 0;color:#475069}
tr.total td{font-weight:700;color:#163e80;border-top:2px solid #163e80;border-bottom:none;padding:14px 0 0;font-size:16px}
.terms{font-size:14px;color:#475069;margin:18px 0 0}
.box{background:#f6f8fc;border:1px solid #e3e8f2;border-radius:10px;padding:16px 20px;margin:18px 0 0}
.box p{margin:0;font-size:14px}
.box dl{display:grid;grid-template-columns:auto 1fr;gap:4px 16px;margin:8px 0 0;font-size:14px}
.box dt{color:#6b7590}.box dd{margin:0;color:#1f2a44;font-weight:600;font-variant-numeric:tabular-nums}
.amount{font-size:30px;font-weight:700;color:#163e80;margin:0;font-variant-numeric:tabular-nums}
.due{font-size:14px;color:#6b7590;margin:2px 0 20px}
#payment-element{min-height:120px;margin:0 0 18px}
.opt{display:flex;gap:12px;align-items:flex-start;background:#f6f8fc;border:1px solid #e3e8f2;border-radius:10px;padding:14px 16px;margin:0 0 18px;cursor:pointer}
.opt input{margin:3px 0 0;width:18px;height:18px;flex:none;accent-color:#2563eb}
.opt b{display:block;font-size:15px;color:#1f2a44;margin:0 0 2px}
.opt span{font-size:13px;line-height:1.5;color:#6b7590}
.btn{display:block;box-sizing:border-box;width:100%;background:#2563eb;color:#fff;text-align:center;font-size:15px;font-weight:600;font-family:inherit;padding:14px 24px;border:none;border-radius:10px;cursor:pointer}
.btn[disabled]{opacity:.6;cursor:default}
.err{display:none;margin:0 0 14px;padding:12px 14px;border-radius:10px;background:#fdf2f2;border:1px solid #f5c2c2;color:#9b1c1c;font-size:14px}
.err.on{display:block}
.saved{margin:0 0 18px;padding:12px 14px;border-radius:10px;background:#eef6f0;border:1px solid #cfe8d6;font-size:14px;color:#1f5130}
.saved.warn{background:#fbf3e6;border-color:#f1dcb5;color:#7a4a06}
.trust{display:flex;align-items:center;gap:7px;margin-top:18px;padding-top:16px;border-top:1px solid #eef1f7;font-size:13px;color:#6b7590}
.muted{font-size:13px;line-height:1.6;color:#8a93a8;margin:10px 0 0}
.site{margin:22px 0 0;font-size:13px;color:#8a93a8;text-align:center}
.site a{color:#6b7590}
@media (max-width:860px){.grid{grid-template-columns:1fr}}
@media (max-width:480px){body{padding:24px 12px 20px}.card{padding:26px 20px 22px}}
</style></head><body><div class='page'>"
		. "<img class='logo' src='../../img/SaldiMainLogo.png' alt='Saldi'>"
		. "<div class='grid'><div class='card'>" . $left . "</div><div class='card'>" . $right . "</div></div>"
		. "<p class='site'>" . fp_h(STRIPE_PAGE_SENDER) . " &middot; <a href='mailto:" . fp_h(STRIPE_PAGE_SUPPORT) . "'>" . fp_h(STRIPE_PAGE_SUPPORT) . "</a></p>"
		. "</div>";
	if ($script !== '') print "<script src='https://js.stripe.com/v3/'></script><script nonce='" . fp_h($nonce) . "'>" . $script . "</script>";
	print "</body></html>";
	exit;
}

// ---------- request handling ----------
if (!$stripe_boot_ok) fp_fail(503, 'Midlertidigt utilgængelig', 'Prøv igen om lidt, eller besvar fakturamailen.');

$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
$ip     = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';

if ($isPost) {
	$raw  = (string)file_get_contents('php://input', false, null, 0, 4096);
	$in   = json_decode($raw, true);
	$in   = is_array($in) ? $in : [];
	$order_id = (int)($in['id'] ?? 0);
	$sig      = (string)($in['sig'] ?? '');
	$save     = !empty($in['save']);
	$terms    = (string)($in['terms'] ?? '');
} else {
	$order_id = (int)($_GET['id'] ?? 0);
	$sig      = (string)($_GET['sig'] ?? '');
}

if (!stripe_rate_limit('pay_ip_' . $ip, 120, 600)) {
	if ($isPost) fp_json(429, ['error' => 'For mange forsøg. Vent et par minutter og prøv igen.']);
	fp_fail(429, 'For mange forsøg', 'Vent et par minutter og prøv igen.');
}
if (!stripe_payload_verify('pay1', $order_id, $sig)) {
	stripe_log("faktura: bad signature for order $order_id from $ip");
	if ($isPost) fp_json(403, ['error' => 'Linket er ugyldigt.']);
	fp_fail(403, 'Linket er ugyldigt', 'Linket er ugyldigt eller udløbet. Brug linket fra fakturamailen, eller besvar mailen.');
}
if (!stripe_rate_limit('pay_ord_' . $order_id, 60, 600)) {
	if ($isPost) fp_json(429, ['error' => 'For mange forsøg. Vent et par minutter og prøv igen.']);
	fp_fail(429, 'For mange forsøg', 'Vent et par minutter og prøv igen.');
}

try {
	$svc = stripe_invoice_payment_service();
} catch (Throwable $e) {
	stripe_log('faktura: service unavailable: ' . $e->getMessage());
	if ($isPost) fp_json(503, ['error' => 'Kortbetaling er midlertidigt utilgængelig. Prøv igen om lidt.']);
	fp_fail(503, 'Midlertidigt utilgængelig', 'Prøv igen om lidt, eller betal via bankoverførsel som angivet på fakturaen.');
}

// ---------- POST: start the payment ----------
if ($isPost) {
	if (!stripe_same_origin()) fp_json(403, ['error' => 'Ugyldig forespørgsel.']);
	if (!stripe_rate_limit('pay_post_' . $order_id, 15, 600)) fp_json(429, ['error' => 'For mange forsøg. Vent et par minutter og prøv igen.']);
	try {
		// 20261012 CL/LH The service refuses a save when the shown consent version is not the current one.
		$r = $svc->startPayment($order_id, $save, $save ? $terms : null);
		fp_json(200, ['status' => $r['status'], 'message' => $r['message'], 'clientSecret' => $r['client_secret'], 'paymentIntent' => $r['payment_intent'], 'amount' => $r['amount'], 'currency' => $r['currency']]);
	} catch (\Saldi\Stripe\InvoicePaymentApiError $e) {
		stripe_log("faktura: start failed order $order_id: " . $e->getMessage() . ' (' . $e->stripeCode . ')');
		fp_json(502, ['error' => 'Betalingen kunne ikke startes. Prøv igen om lidt.']);
	} catch (RuntimeException $e) {
		fp_json(409, ['error' => $e->getMessage()]);
	} catch (Throwable $e) {
		stripe_log("faktura: start error order $order_id: " . $e->getMessage());
		fp_json(500, ['error' => 'Betalingen kunne ikke startes. Prøv igen om lidt.']);
	}
}

// ---------- GET: return from Stripe ----------
$flash = '';
if (isset($_GET['payment_intent'])) {
	try {
		$status = $svc->confirmReturn($order_id, (string)$_GET['payment_intent']);
	} catch (Throwable $e) {
		stripe_log("faktura: return check failed order $order_id: " . $e->getMessage());
		$status = 'unknown';
	}
	// 20261012 CL/LH State only confirmed outcomes: receipt, card saved or not, autopay active or not (AP-30).
	if ($status === 'succeeded') {
		$inv  = $svc->invoice($order_id);
		$card = $inv ? $inv['card'] : null;
		$kontoId = $inv ? (int)$inv['order']['konto_id'] : 0;
		$pay  = $svc->payment((string)$_GET['payment_intent']);
		$texts = ['Vi har modtaget din betaling' . ($inv ? ' for faktura ' . $inv['order']['fakturanr'] : '') . '.'];
		// Stripe sends receipt_email only in live mode.
		if ($inv && $inv['email'] !== '' && (string)stripe_setting('mode') === 'live') $texts[] = 'Stripe sender en kvittering til ' . $inv['email'] . '.';
		if ($pay && (int)$pay['save_card'] === 1) {
			$saved = $card && (int)$card['consent_order_id'] === $order_id && (int)$card['autopay'] === 1 && empty($card['broken_at']);
			if (!$saved) $texts[] = 'Dit kort er ikke gemt endnu. Betal næste faktura som normalt, indtil kortet står som gemt på fakturasiden.';
			elseif (!$svc->autopayReasons($kontoId)) $texts[] = 'Dit kort er gemt. Fremtidige fakturaer fra Danosoft trækkes automatisk på forfaldsdatoen. Du får stadig fakturaen på mail.';
			else $texts[] = 'Dit kort er gemt, men automatisk betaling er ikke aktiv lige nu. Betal næste faktura som normalt.';
		}
		stripe_status_page(200, 'ok', 'Tak for din betaling', $texts, fp_card_box($card, $kontoId), '');
	}
	if ($status === 'processing') {
		stripe_status_page(200, 'info', 'Betalingen behandles', ['Betalingen er ikke gennemført endnu. Stripe behandler den stadig.', 'Du skal ikke betale igen.'], '', '');
	}
	if ($status === 'requires_payment_method') $flash = 'Betalingen blev afvist. Prøv igen, eller brug et andet kort.';
	elseif ($status === 'unknown') $flash = 'Vi kunne ikke få svar om betalingen. Vent lidt, og genindlæs siden, før du prøver igen.';
	else $flash = 'Betalingen blev ikke gennemført. Prøv igen.';
}

// ---------- GET: the invoice page (no Stripe calls, no payment/card writes) ----------
$inv = $svc->invoice($order_id);
if (!$inv) fp_fail(404, 'Fakturaen findes ikke', 'Brug linket fra fakturamailen, eller besvar mailen.');

$kontoId = (int)$inv['order']['konto_id'];
$cardBox = fp_card_box($inv['card'], $kontoId);
if ($inv['reason'] === 'collection_hold') {
	stripe_status_page(200, 'info', 'Opkrævning sat på hold', [fp_block_text('collection_hold', $inv['order']['fakturanr'])], $cardBox, 'Har du spørgsmål, så besvar fakturamailen.');
}
if ($inv['reason'] === 'paid') {
	stripe_status_page(200, 'ok', 'Fakturaen er betalt', ['Faktura ' . $inv['order']['fakturanr'] . ' er betalt. Tak.'], $cardBox, '');
}
if ($inv['reason'] === 'received') {
	stripe_status_page(200, 'ok', 'Betaling modtaget', ['Vi har modtaget din kortbetaling for faktura ' . $inv['order']['fakturanr'] . '. Tak.'], $cardBox, '');
}
if ($inv['reason'] === 'in_flight') {
	stripe_status_page(200, 'info', 'Betalingen behandles', ['Der er allerede en betaling i gang for faktura ' . $inv['order']['fakturanr'] . '. Du skal ikke betale igen.'], $cardBox, '');
}
if ($inv['reason'] === 'nothing_due') {
	stripe_status_page(200, 'info', 'Intet at betale', ['Faktura ' . $inv['order']['fakturanr'] . ' har ingen åben saldo.'], $cardBox, 'Har du spørgsmål, så besvar fakturamailen.');
}

$pk = (string)stripe_setting('publishable_key');
$mode = (string)stripe_setting('mode');
$canPay = $inv['payable'];
if ($canPay && !preg_match('/^pk_' . preg_quote($mode, '/') . '_[A-Za-z0-9]+$/', $pk)) {
	stripe_log("faktura: publishable_key missing or not $mode - card payment hidden");
	$canPay = false;
}

$o = $inv['order'];
$seller = $inv['seller'];
$fakturanr = (string)$o['fakturanr'];
$dueDate = M::danishDate($inv['dueDate']);

// Left column: the invoice.
$rows = '';
foreach ($inv['lines'] as $l) {
	if ($l['textOnly']) {
		if (trim($l['text']) !== '') $rows .= "<tr><td class='txt' colspan='4'>" . fp_h($l['text']) . "</td></tr>";
		continue;
	}
	$rows .= "<tr><td>" . fp_h($l['text']) . "</td><td class='r'>" . fp_h(trim($l['qty'] . ' ' . $l['unit'])) . "</td>"
		. "<td class='r'>" . fp_h(M::amount($l['price'])) . "</td><td class='r'>" . fp_h(M::amount($l['total'])) . "</td></tr>";
}
$vatLabel = 'Moms';
if ($inv['net'] > 0) {
	$pct = $inv['vat'] * 100 / $inv['net'];
	if (abs($pct - round($pct)) < 0.05) $vatLabel = 'Moms ' . (int)round($pct) . ' %';
}
$sums = "<tr class='sum'><td colspan='3'>Subtotal</td><td class='r'>" . fp_h(M::amount($inv['net'])) . "</td></tr>"
	. "<tr class='sum'><td colspan='3'>" . fp_h($vatLabel) . "</td><td class='r'>" . fp_h(M::amount($inv['vat'])) . "</td></tr>"
	. "<tr class='total'><td colspan='3'>Total DKK</td><td class='r'>" . fp_h(M::amount($inv['total'])) . "</td></tr>";
if ($inv['due'] !== $inv['total']) {
	$sums .= "<tr class='sum'><td colspan='3'>Til betaling</td><td class='r'><b>" . fp_h(M::amount($inv['due'])) . "</b></td></tr>";
}
$left = "<p class='eyebrow'>" . fp_h($seller['firmanavn'] ?? STRIPE_PAGE_SENDER) . "</p>"
	. "<h1>Faktura " . fp_h($fakturanr) . "</h1>"
	. "<div class='meta'><span>Kunde: <b>" . fp_h($o['firmanavn']) . "</b></span>"
	. "<span>Fakturadato: <b>" . fp_h(M::danishDate((string)$o['fakturadate'])) . "</b></span>"
	. ($dueDate !== '' ? "<span>Forfald: <b>" . fp_h($dueDate) . "</b></span>" : '') . "</div>"
	. "<table><tr><th>Beskrivelse</th><th class='r'>Antal</th><th class='r'>Pris</th><th class='r'>Beløb</th></tr>" . $rows . $sums . "</table>"
	. ($inv['terms'] !== '' ? "<p class='terms'>Betalingsbetingelser: " . fp_h($inv['terms']) . "</p>" : '');
$reg = trim((string)($seller['bank_reg'] ?? ''));
$kto = trim((string)($seller['bank_konto'] ?? ''));
$bank = '';
if ($reg !== '' && $kto !== '') {
	$bank = "<div class='box'><p><b>" . ($canPay ? 'Foretrækker du bankoverførsel?' : 'Betal via bankoverførsel') . "</b></p><dl>"
		. "<dt>Reg.nr.</dt><dd>" . fp_h($reg) . "</dd><dt>Kontonr.</dt><dd>" . fp_h($kto) . "</dd>"
		. "<dt>Reference</dt><dd>" . fp_h($fakturanr) . "</dd><dt>Beløb</dt><dd>" . fp_h(M::display($inv['due'])) . "</dd></dl></div>";
}
// 20261012 CL/LH A Betalingsservice invoice must not invite a second payment by bank transfer.
if ($inv['reason'] !== 'pbs') $left .= $bank;

// Right column: card payment, or the bank transfer when card payment is off.
$amountTxt = M::amount($inv['due']);
if (!$canPay) {
	$why = fp_block_text($inv['reason'], $fakturanr);
	$right = "<p class='eyebrow'>Betaling</p><h2>Til betaling</h2>"
		. "<p class='amount'>" . fp_h(M::display($inv['due'])) . "</p>"
		. ($dueDate !== '' ? "<p class='due'>Forfald " . fp_h($dueDate) . "</p>" : '')
		. ($why !== '' ? "<p>" . fp_h($why) . "</p>" : '')
		. ($inv['reason'] !== 'pbs' ? "<p>" . ($bank !== '' ? 'Betal via bankoverførsel med fakturanummeret som reference.' : 'Betal som angivet på fakturaen.') . "</p>" : '')
		. $cardBox
		. "<p class='muted'>Har du spørgsmål, så besvar fakturamailen.</p>";
	fp_page('Faktura ' . $fakturanr, $left, $right, $nonce);
}

// 20261012 CL/LH Say "trækkes automatisk" only when the worker will really attempt this invoice (AP-21, LIFE-37, LIFE-32).
$card = $inv['card'];
$auto = $svc->autopayStatus($order_id);
$offerSave = stripe_setting('autopay_enabled', 'off') === 'on';
$saved = '';
if ($card) {
	$name = fp_h(fp_card_name($card));
	$today = M::localDate(time());
	$cutoff = M::danishDate((string)$auto['cutoff']);
	$ok = false;
	switch ($auto['state']) {
		case 'effective':
			if ($inv['dueDate'] === '') {
				$msg = "Dit kort $name er gemt, men fakturaen har ingen gyldig forfaldsdato. Den bliver derfor ikke trukket automatisk. Betal den her eller via bankoverførsel.";
			} elseif ($auto['lastFailed']) {
				$msg = "Det seneste automatiske træk på dit kort $name blev afvist. Vi prøver igen. Du kan også betale nu.";
			} elseif ($inv['dueDate'] > $today) {
				$ok = true;
				$msg = "Dit kort $name trækkes automatisk den " . fp_h($dueDate) . ". Du kan også betale nu.";
			} else {
				$ok = true;
				$msg = "Forfaldsdatoen er nået. Vi forsøger at trække beløbet automatisk på dit kort $name. Du kan også betale nu.";
			}
			break;
		case 'not_covered':
			$msg = "Dit kort $name er gemt til automatisk betaling af fakturaer med fakturadato fra " . fp_h($cutoff) . ". Denne faktura er ældre og bliver ikke trukket automatisk. Betal den her eller via bankoverførsel.";
			break;
		case 'broken':
			$msg = "Dit gemte kort $name blev afvist og bliver ikke trukket automatisk igen. Betal her" . ($offerSave ? ', og sæt flueben for at gemme et nyt kort.' : ' eller via bankoverførsel.');
			break;
		case 'paused':
			$msg = "Dit kort $name er gemt, men automatisk betaling er sat på pause. Denne faktura bliver ikke trukket automatisk. Betal den her eller via bankoverførsel.";
			break;
		case 'exhausted':
			$msg = "Vi har forsøgt at trække fakturaen på dit kort $name uden held og prøver ikke igen. Betal den her eller via bankoverførsel.";
			break;
		default:
			$msg = "Dit kort $name er gemt, men automatisk betaling er ikke slået til. Denne faktura bliver ikke trukket automatisk.";
	}
	$cardUrl = stripe_card_url($kontoId);
	$saved = "<div class='saved" . ($ok ? '' : ' warn') . "'>" . $msg
		. ($cardUrl !== '' ? " <a href='" . fp_h($cardUrl) . "'>Fjern kort</a>" : '') . "</div>";
}
// 20261012 CL/LH The consent box is shown only while automatic payment can actually run; its text is Consent::text().
$consentBox = '';
if ($offerSave) {
	$consentBox = "<label class='opt'><input type='checkbox' id='save'><span><b>" . fp_h(Consent::TITLE) . "</b>"
		. fp_h(Consent::TEXT)
		. ($card ? '<br>Det nye kort erstatter dit gemte kort.' : '') . "</span></label>";
}
$returnUrl = rtrim((string)stripe_setting('public_base_url'), '/') . '/api/stripe/faktura.php?id=' . $order_id . '&sig=' . $sig;
$right = "<p class='eyebrow'>Betaling</p><h2>Betal med kort</h2>"
	. "<p class='amount'>" . fp_h(M::display($inv['due'])) . "</p>"
	. ($dueDate !== '' ? "<p class='due'>Forfald " . fp_h($dueDate) . "</p>" : "<p class='due'></p>")
	. $saved
	. "<form id='pay' data-pk='" . fp_h($pk) . "' data-amount='" . (int)$inv['due'] . "' data-id='" . $order_id . "' data-sig='" . fp_h($sig) . "'"
	. " data-ret='" . fp_h($returnUrl) . "' data-label='" . fp_h($amountTxt) . "' data-terms='" . fp_h(Consent::VERSION) . "'>"
	. "<div id='err' class='err" . ($flash !== '' ? ' on' : '') . "' role='alert'>" . fp_h($flash) . "</div>"
	. "<div id='payment-element'></div>"
	. $consentBox
	. "<button class='btn' id='btn' type='submit' disabled>Betal " . fp_h($amountTxt) . " kr.</button>"
	. "</form>"
	. "<div class='trust'><svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='#6b7590' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><rect x='4' y='11' width='16' height='10' rx='2'/><path d='M8 11V7a4 4 0 0 1 8 0v4'/></svg>"
	. "<span>Sikker betaling via Stripe. Stripe opbevarer dine kortoplysninger. Saldi gemmer kun korttype, de sidste fire cifre og udløbsdato.</span></div>"
	. ($inv['email'] !== '' && $mode === 'live' ? "<p class='muted'>Stripe sender en kvittering til " . fp_h($inv['email']) . ".</p>" : '');

$script = <<<'JS'
(function () {
  var form = document.getElementById('pay'), btn = document.getElementById('btn'), save = document.getElementById('save'), err = document.getElementById('err');
  var d = form.dataset, busy = false;
  // 20261012 CL/LH The consent box is absent while automatic payment is off.
  function saving() { return !!(save && save.checked); }
  function label() { btn.textContent = 'Betal ' + d.label + ' kr.' + (saving() ? ' og gem kortet' : ''); }
  function fail(msg) { err.textContent = msg; err.className = 'err on'; busy = false; btn.disabled = false; }
  if (typeof Stripe !== 'function') { fail('Betalingsformularen kunne ikke indlæses. Genindlæs siden, eller betal via bankoverførsel.'); btn.disabled = true; return; }
  var stripe = Stripe(d.pk, { locale: 'da' });
  var opts = { mode: 'payment', amount: parseInt(d.amount, 10), currency: 'dkk', paymentMethodTypes: ['card'], locale: 'da',
    appearance: { theme: 'stripe', variables: { colorPrimary: '#2563eb', colorText: '#1f2a44', borderRadius: '10px', fontFamily: 'Segoe UI, system-ui, -apple-system, Arial, sans-serif' } } };
  if (saving()) opts.setupFutureUsage = 'off_session';
  var elements = stripe.elements(opts);
  var pe = elements.create('payment', { layout: 'tabs' });
  pe.mount('#payment-element');
  pe.on('ready', function () { btn.disabled = false; });
  if (save) save.addEventListener('change', function () { elements.update({ setupFutureUsage: saving() ? 'off_session' : null }); label(); });
  label();
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (busy) return;
    busy = true; btn.disabled = true; err.className = 'err';
    elements.submit().then(function (s) {
      if (s.error) throw { msg: s.error.message };
      return fetch('faktura.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: parseInt(d.id, 10), sig: d.sig, save: saving(), terms: saving() ? d.terms : '' }) });
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) throw { msg: j.error || 'Betalingen kunne ikke startes. Prøv igen.' };
        if (j.status === 'succeeded') {
          window.location.href = d.ret + '&payment_intent=' + encodeURIComponent(j.paymentIntent);
          return new Promise(function () {});
        }
        if (j.status !== 'ready') throw { msg: j.message || 'Betaling er allerede i gang.', stop: true };
        if (!j.clientSecret || j.currency !== 'dkk' || !Number.isInteger(j.amount)) throw { msg: 'Betalingsbeløbet kunne ikke bekræftes.' };
        // 20261012 CL/LH A changed bank balance requires confirmation of the newly displayed amount.
        if (j.amount !== parseInt(d.amount, 10)) {
          d.amount = String(j.amount);
          d.label = (j.amount / 100).toLocaleString('da-DK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          document.querySelector('.amount').textContent = d.label + ' kr.';
          label();
          return new Promise(function (resolve) {
            elements.on('update-end', resolve);
            elements.update({ amount: j.amount });
          }).then(function () { throw { msg: 'Restbeløbet er ændret. Kontrollér beløbet og tryk Betal igen.' }; });
        }
        return stripe.confirmPayment({ elements: elements, clientSecret: j.clientSecret, confirmParams: { return_url: d.ret }, redirect: 'if_required' });
      });
    }).then(function (res) {
      if (res.error) throw { msg: res.error.message || 'Betalingen blev ikke gennemført.' };
      window.location.href = d.ret + '&payment_intent=' + encodeURIComponent(res.paymentIntent.id);
    }).catch(function (x) { fail(x && x.msg ? x.msg : 'Betalingen kunne ikke gennemføres. Prøv igen.'); if (x && x.stop) btn.disabled = true; });
  });
})();
JS;
fp_page('Faktura ' . $fakturanr, $left, $right, $nonce, $script);
