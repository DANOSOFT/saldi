<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverseIncludes/stripeValg.php --- 2026.10.25
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
// 20260807 CL/LH Created: Stripe-abonnement settings sektion (stripe_valg).
//                Exactly eight settings rows, var_grp='stripe' - the contract in
//                doc/stripe/INTERFACE_CONTRACT.md is the source of truth.
//                Secrets are write-only: never rendered back into the HTML;
//                a blank secret field means "unchanged". Invalid values are
//                rejected with an alert, never silently sanitized.
// 20261022 CDX/LH Complete native master invoice settings and rollout controls; preserve CSRF, escaping and collected errors.
// 20261023 CDX/LH Pass raw settings values to the redesign setter, which handles SQL escaping internally.
// 20261025 CL/ASR Invoice format label = default for new e-mail orders; one-time switch of existing customers/recurring orders to E-mail med link.

/**
 * Injected by systemdata/diverse.php via online.php and the page setup:
 * @var string $bgcolor
 * @var int $sprog_id
 * @var string $csrf_token
 * @var string $buttonStyle
 */

include_once(__DIR__ . '/../../includes/stripeIncludes/stripeSettings.php');
include_once(__DIR__ . '/../../includes/stripeIncludes/stripeInvoice.php'); // stripe_invoice_link_available(), stripe_link_conversion()

/**
 * Save tenant Stripe settings after validating the existing CSRF token.
 * @return void
 */
function stripeValgSave() {
	global $sprog_id;
	$input = $_POST;

	$posted_token = ($input['csrf_token'] ?? null);
	if (!is_string($posted_token) || $posted_token === '' || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $posted_token)) {
		print "<script>alert('Sessionen er udløbet - indstillingerne blev ikke gemt. Prøv igen.');</script>";
		return;
	}
	$fejl = [];

	# enabled - the explicit master switch (default off). Off = no new signups:
	# links render a friendly not-active page and no Checkout Sessions are
	# created. The webhook is deliberately NOT gated by this - events for
	# already-running subscriptions must never be dropped.
	update_settings_value('enabled', 'stripe', ($input['stripe_enabled'] ?? null) ? 'on' : 'off', 'Stripe-abonnement aktiv (til/fra)');

	# mode
	$mode = ($input['stripe_mode'] ?? null);
	if ($mode == 'test' || $mode == 'live') {
		update_settings_value('mode', 'stripe', $mode, 'Stripe mode: test eller live');
	} elseif ($mode) {
		$fejl[] = 'Mode skal være test eller live';
	}

	# secret_key - blank means unchanged
	$secret_key = trim((string)($input['stripe_secret_key'] ?? ''));
	if ($secret_key !== '') {
		if (preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/', $secret_key)) {
			update_settings_value('secret_key', 'stripe', $secret_key, 'Stripe API secret key');
		} else {
			$fejl[] = 'Secret key afvist: forventet sk_test_... / sk_live_... (feltet er IKKE gemt)';
		}
	}

	# webhook_secret - blank means unchanged. The secret is opaque to us (only
	# used as HMAC key), so validate just the prefix and printable-ASCII-no-
	# whitespace - a masked copy (whsec_ + bullet chars) still gets rejected.
	$webhook_secret = trim((string)($input['stripe_webhook_secret'] ?? ''));
	if ($webhook_secret !== '') {
		if (preg_match('/^whsec_[\x21-\x7E]+$/', $webhook_secret)) {
			update_settings_value('webhook_secret', 'stripe', $webhook_secret, 'Stripe webhook signing secret');
		} else {
			$fejl[] = 'Webhook secret afvist: forventet whsec_ efterfulgt af selve nøglen - kopieret mens den var skjult (prikker)? (feltet er IKKE gemt)';
		}
	}

	# link_secret - generated once; explicit regeneration only (invalidates all
	# unclicked links in outstanding invoice mails, hence the checkbox + warning).
	if (!stripe_setting('link_secret') || ($input['stripe_regen_link_secret'] ?? null)) {
		update_settings_value('link_secret', 'stripe', bin2hex(random_bytes(32)), 'HMAC secret for signede abonnementslinks');
	}

	# target_db - mirrored from the resolver, never from POST (contract 4.0.2)
	update_settings_value('target_db', 'stripe', stripeConfigDb(), 'Spejl af stripeConfigDb() - informativ, laeses aldrig som sandhed');

	# Invoice payment rollout switches. Missing checkboxes are explicitly off.
	foreach (array('invoice_payments', 'enrolment', 'signup_enabled') as $feature) {
		update_settings_value($feature, 'stripe', !empty($input['stripe_' . $feature]) ? 'on' : 'off', 'Stripe ' . $feature);
	}

	$publishable_key = trim((string)($input['stripe_publishable_key'] ?? ''));
	if ($publishable_key !== '') {
		if (preg_match('/^pk_(test|live)_[A-Za-z0-9]+$/', $publishable_key)) {
			update_settings_value('publishable_key', 'stripe', $publishable_key, 'Stripe publishable key');
		} else {
			$fejl[] = findtekst('7121|Publishable key afvist: forventet pk_test_... eller pk_live_... (feltet er IKKE gemt)', $sprog_id);
		}
	}
	$charge_on = (string)($input['stripe_charge_on'] ?? 'due');
	if (in_array($charge_on, array('due', 'invoice_date'), true)) {
		update_settings_value('charge_on', 'stripe', $charge_on, 'Automatisk traek: due | invoice_date');
	} else {
		$fejl[] = findtekst('7122|Automatisk træk afvist: vælg forfaldsdato eller fakturadato', $sprog_id);
	}
	update_settings_value('auto_post_journal', 'stripe', !empty($input['stripe_auto_post_journal']) ? 'on' : 'off', 'Bogfoer Stripe-kladden automatisk (reserveret)');

	$invoice_format = (string)($input['invoice_format'] ?? 'pdf');
	if (in_array($invoice_format, array('pdf', 'link'), true)) {
		update_settings_value('invoice_format', 'email_settings', $invoice_format, 'Fakturaformat');
	} else {
		$fejl[] = findtekst('7123|Fakturaformat afvist: vælg PDF eller link', $sprog_id);
	}
	update_settings_value('invoice_link_text', 'email_settings', trim((string)($input['invoice_link_text'] ?? '')), 'Tekst i link-mailen');
	// 20261025 CL/ASR one-time switch of customers/recurring orders from "E-mail" to "E-mail med link"
	if (!empty($input['stripe_convert_link']) && function_exists('stripe_invoice_link_available') && stripe_invoice_link_available()) {
		stripe_link_conversion(true);
	}

	# vat_rate_id
	$vat_rate_id = trim((string)($input['stripe_vat_rate_id'] ?? ''));
	if ($vat_rate_id === '' || preg_match('/^txr_[A-Za-z0-9]+$/', $vat_rate_id)) {
		update_settings_value('vat_rate_id', 'stripe', $vat_rate_id, 'Stripe tax rate id (txr_...) for 25% dansk moms');
	} else {
		$fejl[] = 'Tax rate id afvist: forventet txr_... (feltet er IKKE gemt)';
	}

	# public_base_url - https origin, optional path (e.g. https://ssl12.saldi.dk/lui), no trailing slash
	$base_url = rtrim(trim((string)($input['stripe_public_base_url'] ?? '')), '/');
	if ($base_url === '' || (strpos($base_url, 'https://') === 0 && parse_url($base_url, PHP_URL_HOST) && !parse_url($base_url, PHP_URL_QUERY))) {
		update_settings_value('public_base_url', 'stripe', $base_url, 'Offentlig base-URL for abonnementslinks og webhook');
	} else {
		$fejl[] = 'Base-URL afvist: skal starte med https:// og må ikke have query-parametre (feltet er IKKE gemt)';
	}

	# bookkeeper_email
	$bookkeeper = trim((string)($input['stripe_bookkeeper_email'] ?? ''));
	if ($bookkeeper === '' || filter_var($bookkeeper, FILTER_VALIDATE_EMAIL)) {
		update_settings_value('bookkeeper_email', 'stripe', $bookkeeper, 'Alarm-modtager for Stripe-abonnementer');
	} else {
		$fejl[] = 'Bogholder-email afvist: ugyldig adresse (feltet er IKKE gemt)';
	}

	stripe_settings_flush();
	if ($fejl) {
		print "<script>alert('" . htmlspecialchars(implode('\n', $fejl), ENT_QUOTES, 'UTF-8') . "');</script>";
	}
}

/**
 * Render native master controls for the tenant Stripe settings.
 * @return void
 */
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

	$enabled = (stripe_setting('enabled', 'off') == 'on');
	print "<tr><td title='Hovedafbryder. Uden flueben viser abonnementslinks en venlig ikke-aktiv-side og der oprettes ingen nye abonnementer. Webhooken modtager stadig events for allerede oprettede abonnementer.'>Aktiv:</td>";
	print "<td colspan='3'><input type='checkbox' name='stripe_enabled' value='1'" . ($enabled ? " checked" : "") . "></td></tr>";

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

	foreach (array(
		'invoice_payments' => array(7107, 7108),
		'enrolment' => array(7109, 7110),
		'signup_enabled' => array(7111, 7112)
	) as $feature => $text_ids) {
		$label = htmlspecialchars(findtekst((string)$text_ids[0], $sprog_id), ENT_QUOTES, 'UTF-8');
		$help = htmlspecialchars(findtekst((string)$text_ids[1], $sprog_id), ENT_QUOTES, 'UTF-8');
		print "<tr><td title='$help'>$label:</td><td colspan='3'><input type='checkbox' name='stripe_$feature' value='1'" . (stripe_feature_on($feature) ? " checked" : "") . "></td></tr>";
	}

	$publishable = htmlspecialchars(stripe_setting('publishable_key'), ENT_QUOTES, 'UTF-8');
	print "<tr><td title='" . htmlspecialchars(findtekst('7114', $sprog_id), ENT_QUOTES, 'UTF-8') . "'>" . findtekst('7113', $sprog_id) . ":</td>";
	print "<td colspan='3'><input type='text' style='text-align:left;width:300px;' name='stripe_publishable_key' value='$publishable' placeholder='pk_test_...'></td></tr>";
	$charge_on = stripe_setting('charge_on', 'due');
	print "<tr><td title='" . htmlspecialchars(findtekst('7116', $sprog_id), ENT_QUOTES, 'UTF-8') . "'>" . findtekst('7115', $sprog_id) . ":</td>";
	print "<td colspan='3'><select name='stripe_charge_on'><option value='due'" . ($charge_on != 'invoice_date' ? " selected" : "") . ">" . findtekst('7117', $sprog_id) . "</option><option value='invoice_date'" . ($charge_on == 'invoice_date' ? " selected" : "") . ">" . findtekst('7118', $sprog_id) . "</option></select></td></tr>";
	print "<tr><td>" . findtekst('7119', $sprog_id) . ":</td>";
	print "<td colspan='3'><input type='checkbox' name='stripe_auto_post_journal' value='1'" . (stripe_setting('auto_post_journal', 'off') == 'on' ? " checked" : "") . "> " . findtekst('7120', $sprog_id) . "</td></tr>";

	// Display the stored preference even while invoice payments are switched off.
	$r = db_fetch_array(db_select("select var_name, var_value from settings where var_grp = 'email_settings' and var_name = 'invoice_format' and (user_id is null or user_id = 0)", __FILE__ . " linje " . __LINE__));
	$invoice_format = $r ? trim((string)$r['var_value']) : 'pdf';
	$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'email_settings' and var_name = 'invoice_link_text' and (user_id is null or user_id = 0)", __FILE__ . " linje " . __LINE__));
	$invoice_link_text = htmlspecialchars($r ? (string)$r['var_value'] : '', ENT_QUOTES, 'UTF-8');
	print "<tr><td colspan='4'><b>" . findtekst('7100', $sprog_id) . "</b></td></tr>";
	print "<tr><td title='" . htmlspecialchars(findtekst('7127', $sprog_id), ENT_QUOTES, 'UTF-8') . "'>" . findtekst('7126', $sprog_id) . ":</td>";
	print "<td colspan='3'><select name='invoice_format'><option value='pdf'" . ($invoice_format !== 'link' ? " selected" : "") . ">" . findtekst('7103', $sprog_id) . "</option><option value='link'" . ($invoice_format === 'link' ? " selected" : "") . ">" . findtekst('7104', $sprog_id) . "</option></select></td></tr>";
	print "<tr><td title='" . htmlspecialchars(findtekst('7106', $sprog_id), ENT_QUOTES, 'UTF-8') . "'>" . findtekst('7105', $sprog_id) . ":</td>";
	print "<td colspan='3'><textarea name='invoice_link_text' rows='4' cols='60'>$invoice_link_text</textarea></td></tr>";
	// 20261025 CL/ASR existing customers/subscriptions still on the classic e-mail: offer to switch them once
	if (function_exists('stripe_invoice_link_available') && stripe_invoice_link_available()) {
		$linkCandidates = stripe_link_conversion(false);
		if ($linkCandidates['customers'] + $linkCandidates['orders'] > 0) {
			print "<tr><td title='" . htmlspecialchars(findtekst('7152', $sprog_id), ENT_QUOTES, 'UTF-8') . "'>" . findtekst('7151', $sprog_id) . ":</td>";
			print "<td colspan='3'><label><input type='checkbox' name='stripe_convert_link' value='1'> "
				. htmlspecialchars(str_replace(array('%kunder', '%ordrer'), array($linkCandidates['customers'], $linkCandidates['orders']), findtekst('7153', $sprog_id)), ENT_QUOTES, 'UTF-8') . "</label></td></tr>";
		}
	}

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
	# Tabellen oprettes først ved regnskabs-login (betweenUpdates) - guard, saa
	# siden ikke doer paa en frisk deploy hvor login endnu ikke er sket.
	$cat_tbl = db_fetch_array(db_select("select to_regclass('stripe_catalog') as t", __FILE__ . " linje " . __LINE__));
	if (!$cat_tbl || empty($cat_tbl['t'])) {
		print "<tr><td colspan='6'><hr></td></tr>";
		print "<tr><td colspan='4'>Stripe-tabellerne er ikke oprettet i dette regnskab endnu - log ud og ind igen, så oprettes de automatisk.</td></tr>";
		return;
	}
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
