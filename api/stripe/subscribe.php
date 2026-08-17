<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/stripe/subscribe.php --- 2026.08.07
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
// 20260807 CL/LH Created: signed subscription link endpoint.
//                Contract: doc/stripe/INTERFACE_CONTRACT.md.
//
// GET  = verify HMAC, load the order, match every line against stripe_catalog,
//        render a Danish confirm page. ZERO Stripe calls, ZERO writes - mail
//        scanners that fetch the link must never mint sessions or fire alerts.
// POST = re-verify + re-match, then create the Checkout Session and redirect.
//
// This is the codebase's first unauthenticated third-party HTML surface:
// no findtekst()/alert()/std_func rendering here (alert() does no escaping),
// every dynamic value goes through htmlspecialchars, zero JavaScript.

ob_start(); // std_func's alert()/injecttjek() print HTML on error paths - keep the buffer ours

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

// The tenant is pinned by config - a db parameter is always an attack or a bug.
if (isset($_GET['db']) || isset($_POST['db']) || isset($_REQUEST['db'])) {
	http_response_code(400);
	ob_end_clean();
	print 'Bad request';
	exit;
}

include("../../includes/connect.php");
include("../../includes/stripeIncludes/stripeBootstrap.php");
include("../../includes/stripeIncludes/stripeLink.php");
include("../../includes/stripeIncludes/stripeRateLimit.php");
include("../../includes/stripeIncludes/stripeAlertMail.php");
include("../../includes/stripeIncludes/stripeHttp.php");

// ---------- page shells (self-contained, Danish, no JS) ----------
function stripe_page($title, $bodyHtml, $status = 200) {
	http_response_code($status);
	ob_end_clean();
	print "<!DOCTYPE html><html lang='da'><head><meta charset='utf-8'>";
	print "<meta name='viewport' content='width=device-width, initial-scale=1'>";
	print "<meta name='robots' content='noindex,nofollow'>";
	print "<title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>";
	print "<style>body{font-family:Arial,Helvetica,sans-serif;background:#f4f4f4;margin:0;padding:2em 1em}
.card{max-width:560px;margin:0 auto;background:#fff;border:1px solid #ddd;border-radius:6px;padding:2em}
h1{font-size:1.3em;margin-top:0}table{width:100%;border-collapse:collapse;margin:1em 0}
td,th{padding:.4em .2em;text-align:left;border-bottom:1px solid #eee}td.r,th.r{text-align:right}
.total td{font-weight:bold;border-top:2px solid #333;border-bottom:none}
.btn{display:inline-block;background:#356e35;color:#fff;border:none;border-radius:4px;padding:.7em 1.6em;font-size:1em;cursor:pointer}
.muted{color:#666;font-size:.85em}</style></head><body><div class='card'>" . $bodyHtml . "</div></body></html>";
	exit;
}
function stripe_fail_page($status, $headline, $text) {
	stripe_page($headline, "<h1>" . htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') . "</h1><p>"
		. htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . "</p>", $status);
}
function stripe_park_page() {
	// Deliberately vague: no varenr, prices or reasons leak to the visitor.
	stripe_page('Kontakt os', "<h1>Vi kan ikke oprette abonnementet automatisk</h1>"
		. "<p>Der er en detalje på din aftale, som vi lige skal se på manuelt, før automatisk betaling kan sættes op.</p>"
		. "<p>Vi er allerede blevet adviseret og vender tilbage til dig. Du kan også blot besvare fakturamailen.</p>"
		. "<p class='muted'>Din faktura er stadig gyldig og kan betales som hidtil.</p>", 200);
}

// ---------- order matching (shared by GET and POST) ----------
// Returns ['ok'=>bool,'reason'=>str,'detail'=>str,'lines'=>[],'total_ore'=>int,
//          'interval'=>str,'interval_count'=>int]
function stripe_match_order($order_id) {
	$res = ['ok' => false, 'reason' => '', 'detail' => '', 'lines' => [], 'total_ore' => 0, 'interval' => '', 'interval_count' => 0];
	$q = db_select("select varenr, beskrivelse, antal, pris, rabat from ordrelinjer where ordre_id = " . (int)$order_id . " order by posnr, id", __FILE__ . " linje " . __LINE__);
	$raw = [];
	while ($r = db_fetch_array($q)) $raw[] = $r;

	foreach ($raw as $r) {
		$varenr = trim((string)$r['varenr']);
		if ($varenr === '') continue; // text/comment lines are fine to skip
		if ((float)$r['rabat'] != 0.0) {
			$res['reason'] = 'DISCOUNT'; $res['detail'] = "varenr $varenr har rabat " . $r['rabat'];
			return $res;
		}
		$antal = (float)$r['antal'];
		if ($antal < 1 || abs($antal - round($antal)) > 0.0001) {
			$res['reason'] = 'QUANTITY'; $res['detail'] = "varenr $varenr har antal " . $r['antal'];
			return $res;
		}
		$cat = db_fetch_array(db_select("select stripe_price_id, unit_ore, billing_interval, interval_count, currency from stripe_catalog where active = true and varenr = '" . db_escape_string($varenr) . "'", __FILE__ . " linje " . __LINE__));
		if (!$cat || trim((string)$cat['stripe_price_id']) === '') {
			$res['reason'] = 'UNMAPPED'; $res['detail'] = "varenr $varenr har ingen aktiv Stripe-mapping";
			return $res;
		}
		$lineOre = (int)round(((float)$r['pris']) * 100);
		if ($lineOre !== (int)$cat['unit_ore']) {
			$res['reason'] = 'PRICE_MISMATCH';
			$res['detail'] = "varenr $varenr: ordre $lineOre øre vs katalog " . (int)$cat['unit_ore'] . " øre";
			return $res;
		}
		if (strtoupper($cat['currency']) !== 'DKK') {
			$res['reason'] = 'CURRENCY'; $res['detail'] = "varenr $varenr er ikke DKK i kataloget";
			return $res;
		}
		if ($res['interval'] === '') {
			$res['interval'] = $cat['billing_interval'];
			$res['interval_count'] = (int)$cat['interval_count'];
		} elseif ($res['interval'] !== $cat['billing_interval'] || $res['interval_count'] !== (int)$cat['interval_count']) {
			$res['reason'] = 'MIXED_INTERVAL'; $res['detail'] = "varenr $varenr har andet interval end resten af ordren";
			return $res;
		}
		$res['lines'][] = [
			'varenr' => $varenr,
			'beskrivelse' => trim((string)$r['beskrivelse']) !== '' ? trim((string)$r['beskrivelse']) : $varenr,
			'antal' => (int)round($antal),
			'unit_ore' => (int)$cat['unit_ore'],
			'price_id' => trim($cat['stripe_price_id']),
		];
		$res['total_ore'] += (int)$cat['unit_ore'] * (int)round($antal);
	}
	if (!$res['lines']) { $res['reason'] = 'NO_ITEMS'; $res['detail'] = 'ordren har ingen linjer med varenr'; return $res; }
	if (count($res['lines']) > 20) { $res['reason'] = 'TOO_MANY_ITEMS'; $res['detail'] = count($res['lines']) . ' linjer (Stripe-max er 20)'; return $res; }
	$res['ok'] = true;
	return $res;
}

function stripe_kr($ore) { return number_format($ore / 100, 2, ',', '.'); }

// ---------- request handling ----------
if (!$stripe_boot_ok) stripe_fail_page(503, 'Midlertidigt utilgængelig', 'Prøv igen om lidt, eller besvar fakturamailen.');

// Master switch (stripe_valg "Aktiv"): off = no new signups, full stop.
if (stripe_setting('enabled', 'off') !== 'on') {
	stripe_fail_page(503, 'Ikke tilgængelig', 'Abonnementstilmelding er ikke aktiveret. Din faktura er stadig gyldig og kan betales som hidtil - besvar fakturamailen ved spørgsmål.');
}

$isPost   = ($_SERVER['REQUEST_METHOD'] === 'POST');
$order_id = (int)($isPost ? (isset($_POST['id']) ? $_POST['id'] : 0) : (isset($_GET['id']) ? $_GET['id'] : 0));
$sig      = (string)($isPost ? (isset($_POST['sig']) ? $_POST['sig'] : '') : (isset($_GET['sig']) ? $_GET['sig'] : ''));
$ip       = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';

if (!stripe_rate_limit('sub_ip_' . $ip, 120, 600) || !stripe_rate_limit('sub_ord_' . $order_id, 30, 600)) {
	stripe_fail_page(429, 'For mange forsøg', 'Vent et par minutter og prøv igen.');
}
if ($order_id < 1 || !stripe_link_verify($order_id, $sig)) {
	stripe_log("subscribe: bad signature for order $order_id from $ip");
	stripe_fail_page(403, 'Linket er ugyldigt', 'Linket er ugyldigt eller udløbet. Brug linket fra din seneste faktura, eller besvar fakturamailen.');
}

// Order-level guards. NB: nextfakt is deliberately never selected - no stripe
// code may read or write it (interface contract / locked decision).
$order = db_fetch_array(db_select("select id, art, konto_id, kontonr, firmanavn, email, valuta, shop_status from ordrer where id = " . $order_id, __FILE__ . " linje " . __LINE__));
$parkReason = '';
if (!$order) {
	stripe_log("subscribe: order $order_id not found ($ip)");
	stripe_fail_page(404, 'Ordren findes ikke', 'Brug linket fra din seneste faktura, eller besvar fakturamailen.');
}
if ($order['art'] !== 'DO')                          $parkReason = 'NOT_DEBTOR_ORDER';
elseif ((int)$order['konto_id'] < 1)                 $parkReason = 'NO_DEBTOR';
elseif ($order['shop_status'] === 'stripe_paid_bridge') $parkReason = 'BRIDGE_ORDER';
elseif (trim((string)$order['valuta']) !== '' && strtoupper(trim($order['valuta'])) !== 'DKK') $parkReason = 'CURRENCY';

// Already subscribed?
if (!$parkReason) {
	$sub = db_fetch_array(db_select("select stripe_subscription_id from stripe_customers where konto_id = " . (int)$order['konto_id'] . " and status in ('active','trialing','past_due')", __FILE__ . " linje " . __LINE__));
	if ($sub) {
		stripe_page('Abonnement findes allerede', "<h1>Du har allerede et aktivt abonnement</h1>"
			. "<p>Automatisk betaling er allerede sat op for jeres aftale, så du behøver ikke foretage dig noget.</p>"
			. "<p class='muted'>Er det en fejl, eller ønsker du at ændre noget, så besvar fakturamailen.</p>");
	}
}

$match = $parkReason ? ['ok' => false, 'reason' => $parkReason, 'detail' => ''] : stripe_match_order($order_id);

if (!$match['ok']) {
	if ($isPost) {
		// Alert on POST only: a human clicked through. GET parks stay silent so
		// mail scanners can never mailbomb the bookkeeper (one alert/order/hour).
		stripeAlertMail(
			"Abonnementslink parkeret - ordre $order_id",
			"Ordre: $order_id (" . $order['firmanavn'] . ", konto " . $order['kontonr'] . ")\n"
			. "Aarsag: " . $match['reason'] . "\n" . ($match['detail'] !== '' ? "Detalje: " . $match['detail'] . "\n" : '')
			. "\nKunden har forsøgt at oprette abonnement via fakturalinket og har fået 'kontakt os'-siden.\n"
			. "Ret ordren/kataloget og send fakturaen igen, eller håndtér kunden manuelt.",
			'park_' . $order_id, 3600);
	} else {
		stripe_log("subscribe: parked GET order $order_id reason " . $match['reason'] . ' ' . $match['detail']);
	}
	stripe_park_page();
}

if (!$isPost) {
	// ---------- GET: confirm page (no writes, no Stripe calls) ----------
	$rows = '';
	foreach ($match['lines'] as $l) {
		$rows .= "<tr><td>" . htmlspecialchars($l['beskrivelse'], ENT_QUOTES, 'UTF-8') . "</td>"
			. "<td class='r'>" . (int)$l['antal'] . "</td>"
			. "<td class='r'>" . stripe_kr($l['unit_ore']) . " kr</td>"
			. "<td class='r'>" . stripe_kr($l['unit_ore'] * $l['antal']) . " kr</td></tr>";
	}
	$per = ($match['interval'] === 'month' && $match['interval_count'] === 1) ? 'pr. måned'
		: 'pr. ' . ($match['interval_count'] > 1 ? $match['interval_count'] . ' x ' : '') . ($match['interval'] === 'year' ? 'år' : $match['interval']);
	$firm = htmlspecialchars($order['firmanavn'], ENT_QUOTES, 'UTF-8');
	stripe_page('Bekræft dit abonnement',
		"<h1>Dit Saldi-abonnement</h1>"
		. "<p>$firm</p>"
		. "<table><tr><th>Ydelse</th><th class='r'>Antal</th><th class='r'>Stk.</th><th class='r'>I alt</th></tr>"
		. $rows
		. "<tr class='total'><td colspan='3'>I alt $per, ekskl. moms</td><td class='r'>" . stripe_kr($match['total_ore']) . " kr</td></tr></table>"
		. "<p>Beløbet svarer til din seneste faktura. Fremover trækkes det automatisk på dit betalingskort, og du modtager stadig faktura på mail.</p>"
		. "<form method='post' action='subscribe.php'>"
		. "<input type='hidden' name='id' value='" . (int)$order_id . "'>"
		. "<input type='hidden' name='sig' value='" . htmlspecialchars($sig, ENT_QUOTES, 'UTF-8') . "'>"
		. "<button class='btn' type='submit'>Fortsæt til betaling</button>"
		. "</form>"
		. "<p class='muted'>Du sendes videre til vores betalingspartner Stripe. Moms (25%) lægges til ved betaling. Abonnementet kan opsiges når som helst ved at besvare fakturamailen.</p>");
}

// ---------- POST: create the Checkout Session ----------
$vat_rate_id = stripe_setting('vat_rate_id');
$base_url    = stripe_setting('public_base_url');
if (!preg_match('/^txr_[A-Za-z0-9]+$/', $vat_rate_id) || !$base_url) {
	stripeAlertMail("Abonnementslink parkeret - ordre $order_id",
		"Aarsag: STRIPE_CONFIG (vat_rate_id/public_base_url mangler i stripe_valg)\nOrdre: $order_id", 'park_' . $order_id, 3600);
	stripe_park_page();
}

$email = trim((string)$order['email']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
	$r = db_fetch_array(db_select("select email from adresser where id = " . (int)$order['konto_id'], __FILE__ . " linje " . __LINE__));
	$email = $r ? trim((string)$r['email']) : '';
}

$params = [
	'mode' => 'subscription',
	'locale' => 'da',
	'payment_method_types' => ['card'],
	'client_reference_id' => (string)$order_id,
	'billing_address_collection' => 'required',
	'tax_id_collection' => ['enabled' => 'true'],
	'success_url' => $base_url . '/api/stripe/tak.php',
	'cancel_url' => $base_url . '/api/stripe/afbrudt.php?id=' . $order_id . '&sig=' . $sig,
	'metadata' => [
		'saldi_order_id' => (string)$order_id,
		'saldi_konto_id' => (string)(int)$order['konto_id'],
	],
	'subscription_data' => [
		'default_tax_rates' => [$vat_rate_id],
		// Copied by Stripe onto every invoice the subscription generates -
		// this is what makes RENEWAL invoices resolvable in the webhook.
		'metadata' => [
			'saldi_order_id' => (string)$order_id,
			'saldi_konto_id' => (string)(int)$order['konto_id'],
			'saldi_kontonr' => (string)$order['kontonr'],
		],
	],
];
if (filter_var($email, FILTER_VALIDATE_EMAIL)) $params['customer_email'] = $email;
$li = [];
foreach ($match['lines'] as $l) $li[] = ['price' => $l['price_id'], 'quantity' => (string)$l['antal']];
$params['line_items'] = $li;

$lineSig = '';
foreach ($match['lines'] as $l) $lineSig .= $l['varenr'] . ':' . $l['antal'] . ':' . $l['unit_ore'] . ';';
$resp = stripeHttpRequest('POST', '/v1/checkout/sessions', $params,
	['idempotency_key' => 'saldi-sub-' . $order_id . '-' . date('Ymd') . '-' . substr(sha1($lineSig), 0, 16)]);

if (!$resp['ok'] || !is_array($resp['body']) || empty($resp['body']['url'])) {
	stripe_log("subscribe: session create failed order $order_id: " . $resp['error'] . ' (' . $resp['error_code'] . ') req ' . $resp['request_id']);
	stripeAlertMail("Abonnementslink parkeret - ordre $order_id",
		"Aarsag: STRIPE_API\nOrdre: $order_id (" . $order['firmanavn'] . ")\nFejl: " . $resp['error'] . " (" . $resp['error_code'] . ")\nRequest: " . $resp['request_id'],
		'park_' . $order_id, 3600);
	stripe_park_page();
}

ob_end_clean();
header('Location: ' . $resp['body']['url'], true, 303);
exit;
