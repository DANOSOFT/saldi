<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- api/stripe/kort.php --- 2026.10.12
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
// 20261007 CL/LH Created: "Fjern kort" page for invoice autopay.
//                Contract: doc/stripe/INVOICE_PAYMENT.md.
//
// 20261012 CL/LH Show the effective autopay state and confirm only the removal
//                outcomes that actually happened (AP-21, AP-30).
//
// GET  ?k&sig        = verify the card1 HMAC and show the saved card with a
//                      confirm button. No Stripe calls and no card writes; the
//                      rate limiter writes its counter files in temp/.
// POST k, sig        = stop autopay and detach the card at Stripe.
//
// The link is in every hosted invoice page that has a saved card; staff
// can remove the card from debitorkort.php as well.

ob_start();

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

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

function kp_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function kp_fail($status, $headline, $text) {
	stripe_status_page($status, 'warn', $headline, [$text], '', '');
}

if (!$stripe_boot_ok) kp_fail(503, 'Midlertidigt utilgængelig', 'Prøv igen om lidt, eller skriv til os.');

$isPost   = ($_SERVER['REQUEST_METHOD'] === 'POST');
$ip       = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
$konto_id = (int)($isPost ? ($_POST['k'] ?? 0) : ($_GET['k'] ?? 0));
$sig      = (string)($isPost ? ($_POST['sig'] ?? '') : ($_GET['sig'] ?? ''));

if (!stripe_rate_limit('card_ip_' . $ip, 60, 600)) kp_fail(429, 'For mange forsøg', 'Vent et par minutter og prøv igen.');
if (!stripe_payload_verify('card1', $konto_id, $sig)) {
	stripe_log("kort: bad signature for konto $konto_id from $ip");
	kp_fail(403, 'Linket er ugyldigt', 'Brug linket på din faktura, eller skriv til os, så fjerner vi kortet.');
}
if (!stripe_rate_limit('card_k_' . $konto_id, 20, 600)) kp_fail(429, 'For mange forsøg', 'Vent et par minutter og prøv igen.');

try {
	$svc = stripe_invoice_payment_service();
} catch (Throwable $e) {
	stripe_log('kort: service unavailable: ' . $e->getMessage());
	kp_fail(503, 'Midlertidigt utilgængelig', 'Prøv igen om lidt, eller skriv til os, så fjerner vi kortet.');
}

$doneNote = 'Du får stadig dine fakturaer på mail og kan betale dem med kort eller bankoverførsel.';

if ($isPost) {
	if (!stripe_same_origin()) kp_fail(403, 'Ugyldig forespørgsel', 'Åbn linket igen, og prøv en gang til.');
	try {
		$result = $svc->removeCardResult($konto_id, 'kunde');
	} catch (Throwable $e) {
		stripe_log("kort: remove failed konto $konto_id: " . $e->getMessage());
		// 20261012 CL/LH The stop may have committed before the error; report what is stored now.
		$still = null;
		try { $still = $svc->card($konto_id); } catch (Throwable $e2) { $still = ['unknown' => true]; }
		if ($still === null) {
			stripe_status_page(200, 'info', 'Automatisk betaling er stoppet', ['Vi trækker ikke længere fakturaer på kortet.', 'Vi kunne ikke bekræfte, at kortet er slettet hos Stripe. Danosoft sørger for at slette det.'], '', $doneNote);
		}
		if (isset($still['unknown'])) kp_fail(500, 'Fjernelsen kunne ikke bekræftes', 'Der opstod en fejl. Åbn linket igen om lidt for at se, om kortet stadig er gemt, eller skriv til os, så fjerner vi kortet.');
		kp_fail(500, 'Kortet blev ikke fjernet', 'Der opstod en fejl. Prøv igen om lidt, eller skriv til os, så fjerner vi kortet.');
	}
	if (!$result['stopped']) {
		stripe_status_page(200, 'info', 'Intet kort gemt', ['Der er ikke gemt et kort til automatisk betaling.'], '', $doneNote);
	}
	if ($result['detached']) {
		stripe_status_page(200, 'ok', 'Kortet er fjernet', ['Automatisk betaling er stoppet, og kortet er slettet hos Stripe.'], '', $doneNote);
	}
	stripe_status_page(200, 'ok', 'Automatisk betaling er stoppet', ['Vi trækker ikke længere fakturaer på kortet.', 'Kortet er endnu ikke slettet hos Stripe. Danosoft sørger for at slette det.'], '', $doneNote);
}

$card = $svc->card($konto_id);
if (!$card) stripe_status_page(200, 'info', 'Intet kort gemt', ['Der er ikke gemt et kort til automatisk betaling.'], '', $doneNote);

$cust  = db_fetch_array(db_select("select firmanavn from adresser where id = " . $konto_id, __FILE__ . " linje " . __LINE__));
$brand = ucfirst((string)$card['brand']);
$exp   = sprintf('%02d/%02d', (int)$card['exp_month'], (int)$card['exp_year'] % 100);
// 20261012 CL/LH Effective state, not just the stored flag (AP-21).
$reasons = $svc->autopayReasons($konto_id);
$cutoff  = $card['consent_at'] !== null ? \Saldi\Stripe\InvoicePaymentMath::danishDate(\Saldi\Stripe\InvoicePaymentMath::localDate((int)$card['consent_at'])) : '';
if (in_array('card_broken', $reasons, true)) {
	$state = 'Kortet er afvist og trækkes ikke længere automatisk.';
} elseif (in_array('pbs', $reasons, true)) {
	$state = 'Dine fakturaer opkræves via Betalingsservice, så kortet trækkes ikke automatisk.';
} elseif (in_array('subscription', $reasons, true)) {
	$state = 'Du betaler via et kortabonnement, så kortet trækkes ikke automatisk for fakturaer.';
} elseif (in_array('optout', $reasons, true) || in_array('disabled', $reasons, true)) {
	$state = 'Kortbetaling af fakturaer er slået fra, så kortet trækkes ikke automatisk.';
} elseif (in_array('autopay_disabled', $reasons, true)) {
	$state = 'Automatisk betaling er sat på pause, så kortet trækkes ikke lige nu.';
} elseif ($reasons) {
	$state = 'Automatisk betaling er ikke slået til for kortet.';
} else {
	$state = 'Fakturaer fra Danosoft med fakturadato fra ' . $cutoff . ' trækkes automatisk på forfaldsdatoen.';
}

stripe_page('Fjern kort',
	($cust ? "<p class='eyebrow'>" . kp_h(trim((string)$cust['firmanavn'])) . "</p>" : '')
	. "<h1>Gemt betalingskort</h1>"
	. "<div class='box'><p><b>" . kp_h($brand) . " &bull;&bull;&bull;&bull; " . kp_h($card['last4']) . "</b> &middot; udløber " . kp_h($exp) . "</p>"
	. "<p class='muted' style='margin:6px 0 0'>" . kp_h($state) . "</p></div>"
	. "<p style='margin:18px 0'>Fjerner du kortet, stopper den automatiske betaling, og kortet slettes hos Stripe. Du får stadig fakturaen på mail og kan betale den med kort eller bankoverførsel.</p>"
	. "<form method='post' action='kort.php'>"
	. "<input type='hidden' name='k' value='" . $konto_id . "'>"
	. "<input type='hidden' name='sig' value='" . kp_h($sig) . "'>"
	. "<button class='btn' type='submit'>Fjern kortet</button>"
	. "</form>");
