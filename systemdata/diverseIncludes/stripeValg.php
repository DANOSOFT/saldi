<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverseIncludes/stripeValg.php --- 2026.08.07
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
// 20260807 CL/LH Created: Stripe-abonnement settings sektion (stripe_valg).
//                Exactly eight settings rows, var_grp='stripe' - the contract in
//                doc/stripe/INTERFACE_CONTRACT.md is the source of truth.
//                Secrets are write-only: never rendered back into the HTML;
//                a blank secret field means "unchanged". Invalid values are
//                rejected with an alert, never silently sanitized.

include_once(__DIR__ . '/../../includes/stripeIncludes/stripeSettings.php');

function stripeValgSave() {
	global $csrf_token;

	$posted_token = if_isset($_POST['csrf_token']);
	if (!$posted_token || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $posted_token)) {
		print "<script>alert('Sessionen er udløbet - indstillingerne blev ikke gemt. Prøv igen.');</script>";
		return;
	}
	$fejl = [];

	# mode
	$mode = if_isset($_POST['stripe_mode']);
	if ($mode == 'test' || $mode == 'live') {
		update_settings_value('mode', 'stripe', db_escape_string($mode), 'Stripe mode: test eller live');
	} elseif ($mode) {
		$fejl[] = 'Mode skal være test eller live';
	}

	# secret_key - blank means unchanged
	$secret_key = trim((string)if_isset($_POST['stripe_secret_key'], ''));
	if ($secret_key !== '') {
		if (preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/', $secret_key)) {
			update_settings_value('secret_key', 'stripe', db_escape_string($secret_key), 'Stripe API secret key');
		} else {
			$fejl[] = 'Secret key afvist: forventet sk_test_... / sk_live_... (feltet er IKKE gemt)';
		}
	}

	# webhook_secret - blank means unchanged
	$webhook_secret = trim((string)if_isset($_POST['stripe_webhook_secret'], ''));
	if ($webhook_secret !== '') {
		if (preg_match('/^whsec_[A-Za-z0-9]+$/', $webhook_secret)) {
			update_settings_value('webhook_secret', 'stripe', db_escape_string($webhook_secret), 'Stripe webhook signing secret');
		} else {
			$fejl[] = 'Webhook secret afvist: forventet whsec_... (feltet er IKKE gemt)';
		}
	}

	# link_secret - generated once; explicit regeneration only (invalidates all
	# unclicked links in outstanding invoice mails, hence the checkbox + warning).
	if (!stripe_setting('link_secret') || if_isset($_POST['stripe_regen_link_secret'])) {
		update_settings_value('link_secret', 'stripe', bin2hex(random_bytes(32)), 'HMAC secret for signede abonnementslinks');
	}

	# target_db - mirrored from the resolver, never from POST (contract 4.0.2)
	update_settings_value('target_db', 'stripe', db_escape_string(stripeConfigDb()), 'Spejl af stripeConfigDb() - informativ, laeses aldrig som sandhed');

	# vat_rate_id
	$vat_rate_id = trim((string)if_isset($_POST['stripe_vat_rate_id'], ''));
	if ($vat_rate_id === '' || preg_match('/^txr_[A-Za-z0-9]+$/', $vat_rate_id)) {
		update_settings_value('vat_rate_id', 'stripe', db_escape_string($vat_rate_id), 'Stripe tax rate id (txr_...) for 25% dansk moms');
	} else {
		$fejl[] = 'Tax rate id afvist: forventet txr_... (feltet er IKKE gemt)';
	}

	# public_base_url - https origin, optional path (e.g. https://ssl12.saldi.dk/lui), no trailing slash
	$base_url = rtrim(trim((string)if_isset($_POST['stripe_public_base_url'], '')), '/');
	if ($base_url === '' || (strpos($base_url, 'https://') === 0 && parse_url($base_url, PHP_URL_HOST) && !parse_url($base_url, PHP_URL_QUERY))) {
		update_settings_value('public_base_url', 'stripe', db_escape_string($base_url), 'Offentlig base-URL for abonnementslinks og webhook');
	} else {
		$fejl[] = 'Base-URL afvist: skal starte med https:// og må ikke have query-parametre (feltet er IKKE gemt)';
	}

	# bookkeeper_email
	$bookkeeper = trim((string)if_isset($_POST['stripe_bookkeeper_email'], ''));
	if ($bookkeeper === '' || filter_var($bookkeeper, FILTER_VALIDATE_EMAIL)) {
		update_settings_value('bookkeeper_email', 'stripe', db_escape_string($bookkeeper), 'Alarm-modtager for Stripe-abonnementer');
	} else {
		$fejl[] = 'Bogholder-email afvist: ugyldig adresse (feltet er IKKE gemt)';
	}

	stripe_settings_flush();
	if ($fejl) {
		print "<script>alert('" . htmlspecialchars(implode('\n', $fejl), ENT_QUOTES, 'UTF-8') . "');</script>";
	}
}

function stripeValg() {
	global $bgcolor, $sprog_id, $csrf_token, $buttonStyle;

	$mode        = stripe_setting('mode');
	$has_secret  = stripe_setting('secret_key') ? true : false;
	$has_whsec   = stripe_setting('webhook_secret') ? true : false;
	$has_link    = stripe_setting('link_secret') ? true : false;
	$vat_rate_id = htmlspecialchars(stripe_setting('vat_rate_id'), ENT_QUOTES, 'UTF-8');
	$base_url    = htmlspecialchars(stripe_setting('public_base_url'), ENT_QUOTES, 'UTF-8');
	$bookkeeper  = htmlspecialchars(stripe_setting('bookkeeper_email'), ENT_QUOTES, 'UTF-8');
	$target_db   = htmlspecialchars(stripeConfigDb(), ENT_QUOTES, 'UTF-8');

	print "<form name=diverse action=diverse.php?sektion=stripe_valg method=post autocomplete=off>";
	print "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') . "'>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr><td colspan='4'><b>Stripe abonnement</b></td></tr>";
	print "<tr><td><br></td></tr>";

	print "<tr><td title='Regnskabet som webhook og links er låst til. Sættes via SALDI_STRIPE_DB eller includes/stripeIncludes/.ht_stripe_db på serveren - ikke her.'>Regnskab (låst):</td>";
	print "<td colspan='3'>" . ($target_db ? $target_db : "<b style='color:red'>IKKE KONFIGURERET - integrationen er inaktiv</b>") . "</td></tr>";

	print "<tr><td>Mode:</td><td colspan='3'><select name='stripe_mode'>";
	print "<option value=''" . ($mode == '' ? " selected" : "") . "></option>";
	print "<option value='test'" . ($mode == 'test' ? " selected" : "") . ">test</option>";
	print "<option value='live'" . ($mode == 'live' ? " selected" : "") . ">live</option>";
	print "</select></td></tr>";

	print "<tr><td title='Stripe API secret key (sk_test_... eller sk_live_...). Feltet viser aldrig den gemte nøgle - tomt felt betyder uændret.'>Secret key:</td>";
	print "<td colspan='3'><input type='password' style='text-align:left;width:300px;' name='stripe_secret_key' value='' placeholder='" . ($has_secret ? "(gemt - tomt felt = uændret)" : "sk_test_...") . "'></td></tr>";

	print "<tr><td title='Webhook signing secret (whsec_...) fra Stripe Dashboard, når webhook-endpointet registreres. Tomt felt betyder uændret.'>Webhook secret:</td>";
	print "<td colspan='3'><input type='password' style='text-align:left;width:300px;' name='stripe_webhook_secret' value='' placeholder='" . ($has_whsec ? "(gemt - tomt felt = uændret)" : "whsec_...") . "'></td></tr>";

	print "<tr><td title='HMAC-nøglen der signerer abonnementslinks i fakturamails. Genereres automatisk ved første gem.'>Link-nøgle:</td>";
	print "<td colspan='3'>" . ($has_link ? "Genereret" : "Genereres ved første gem") . " &nbsp; <label><input type='checkbox' name='stripe_regen_link_secret' value='1'> Generér ny (ADVARSEL: alle links i allerede udsendte mails holder op med at virke)</label></td></tr>";

	print "<tr><td title='Stripe tax rate (txr_...) for 25% dansk moms - oprettes i Stripe Dashboard og indsættes her. Uden den parkeres alle links.'>Tax rate id:</td>";
	print "<td colspan='3'><input type='text' style='text-align:left;width:300px;' name='stripe_vat_rate_id' value='$vat_rate_id' placeholder='txr_...'></td></tr>";

	print "<tr><td title='Den offentlige base-URL for denne installation, fx https://ssl12.saldi.dk/lui - bruges i links og webhook-URL.'>Base-URL:</td>";
	print "<td colspan='3'><input type='text' style='text-align:left;width:300px;' name='stripe_public_base_url' value='$base_url' placeholder='https://...'></td></tr>";

	print "<tr><td title='Modtager af alle alarmer: nye abonnementer, betalte fakturaer, fejl. Tom = regnskabets egen mailadresse.'>Bogholder-email:</td>";
	print "<td colspan='3'><input type='text' style='text-align:left;width:300px;' name='stripe_bookkeeper_email' value='$bookkeeper'></td></tr>";

	if ($base_url) {
		print "<tr><td title='Denne URL registreres som webhook-endpoint i Stripe Dashboard.'>Webhook-URL:</td>";
		print "<td colspan='3'><input type='text' style='text-align:left;width:400px;' value='" . $base_url . "/api/stripe/webhook.php' readonly onclick='this.select()'></td></tr>";
	}

	print "<tr><td><br></td></tr>";
	print "<tr><td colspan='4'><input type='submit' value='" . findtekst('471|Gem/opdatér', $sprog_id) . "'></td></tr>";
	print "</form>";

	# Read-only visning af varenr <-> Stripe price mappingen (redigeres via seed-
	# scriptet admin/stripe_setup.php; en redigerbar grid-side kommer separat).
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr><td colspan='4'><b>Varenr &harr; Stripe price (læsning)</b></td></tr>";
	print "<tr><td><b>Varenr</b></td><td><b>Stripe price</b></td><td align='right'><b>Pris/md ekskl. moms</b></td><td><b>Interval</b></td><td><b>Aktiv</b></td></tr>";
	$q = db_select("select varenr, stripe_price_id, unit_ore, billing_interval, interval_count, active from stripe_catalog order by active desc, varenr", __FILE__ . " linje " . __LINE__);
	$rows = 0;
	while ($r = db_fetch_array($q)) {
		$rows++;
		$kr = number_format(((int)$r['unit_ore']) / 100, 2, ',', '.');
		$interval_txt = ((int)$r['interval_count'] > 1 ? $r['interval_count'] . ' x ' : '') . $r['billing_interval'];
		print "<tr><td>" . htmlspecialchars($r['varenr'], ENT_QUOTES, 'UTF-8') . "</td>";
		print "<td>" . htmlspecialchars($r['stripe_price_id'], ENT_QUOTES, 'UTF-8') . "</td>";
		print "<td align='right'>$kr kr</td>";
		print "<td>" . htmlspecialchars($interval_txt, ENT_QUOTES, 'UTF-8') . "</td>";
		print "<td>" . ($r['active'] == 't' || $r['active'] == '1' || $r['active'] === true ? 'Ja' : 'Nej') . "</td></tr>";
	}
	if (!$rows) print "<tr><td colspan='5'>Ingen mapping endnu - kør admin/stripe_setup.php</td></tr>";
}
