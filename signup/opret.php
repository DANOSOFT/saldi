<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- signup/opret.php --- ver 5.1.0 --- 2026-11-02 ---
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
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// The endpoint the website's "Prøv gratis"/price-page form posts to (plan.md, section 1).
//
//   POST (form fields or JSON): name, cvr, company, email, phone, pakke, source, website (honeypot)
//   -> 200 {ok:true, resend:"<key>", email:"<as typed>"}   the mail is on its way (or the request
//                                                          was silently dropped: bot / too many)
//   -> 422 {ok:false, errors:{field: message}}            validation failed
//   -> 429 {ok:false, errors:{_: message}}                too many signups (this address or the server),
//                                                          or too many CVR lookups from this address
//
// Browsers get the same as an HTML page ("Tjek jeres mail") when they do not ask for JSON, and a
// GET shows a plain fallback form, so the flow can be exercised without the website. All
// validation is server side; the CVR number is looked up here, never trusted from the client.
// The page never reveals whether an e-mail address is already known. The address itself is shown
// in full: the customer just typed it (masking is for pages reached through a link, see nytlink.php).
//
// 20261014 CL/PS Created.
// 20261102 CL/ASR Refuses POSTs from foreign origins.
//                 Limits are claimed (recorded, then counted) after validation, so typos do not use them up, and include a server ceiling, max_signups_per_hour.
//                 The form's own CVR lookups count against the CVR limit; when it is used up the signup waits instead of skipping the check.
//                 A c/o address from the CVR register is kept as on a letter: addr1 "c/o <name>", the street in addr2 (as on the kreditor card).
//                 The per-mailbox mail cap is claimed before the mail is sent.

chdir(__DIR__);
include(__DIR__ . "/../includes/signupIncludes/signupBootstrap.php");

signup_cors();
$json = signup_wants_json();
$method = ifset($_SERVER, 'REQUEST_METHOD', 'GET');

if ($method !== 'POST') {
	if ($json) {
		signup_json(array('ok' => false, 'errors' => array('_' => 'Brug POST')), 405);
	}
	signup_fallback_form(array(), array());
}

$in = signup_input();

signup_refuse_foreign_origin($json);

// Bots fill every field, including the one people cannot see.
if (trim((string)ifset($in, 'website', '')) !== '') {
	signup_done(array('ok' => true, 'resend' => '', 'email' => signup_clean_text(ifset($in, 'email', ''), 60)), $json);
}

$v = signup_validate_form($in);
$d = $v['data'];
$errors = $v['errors'];

// Company details: from the CVR register when a number was given, otherwise what the customer typed.
$company = array('firmanavn' => $d['firmanavn'], 'addr1' => '', 'addr2' => '', 'postnr' => '', 'bynavn' => '');
$nameFromCvr = false; // the mail only says "hentet fra CVR" when it really was
if (!$errors && $d['cvr'] !== '') {
	// Counted like cvr.php's lookups, so the form cannot be used to flood the register. A used-up limit makes the
	// signup wait: going on as if the register were down would let a client skip the check.
	if (!signup_claim_event('cvr', 40, 3600)) {
		signup_too_many('Der er slået mange CVR-numre op fra jeres adresse den seneste time. Prøv igen senere, eller ring til os.', $json);
	}
	$cvr = signup_lookup_cvr($d['cvr']);
	if ($cvr['status'] === 'found') {
		$nameFromCvr = ($cvr['name'] !== '');
		$company['firmanavn'] = $nameFromCvr ? $cvr['name'] : $d['firmanavn'];
		$company['addr1'] = $cvr['address'];
		$company['addr2'] = ifset($cvr, 'address2', '');
		$company['postnr'] = $cvr['zipcode'];
		$company['bynavn'] = $cvr['city'];
	} elseif ($cvr['status'] === 'notfound') {
		$errors['cvr'] = 'Vi kunne ikke finde CVR-nummeret i CVR-registret. Tjek cifrene, eller opret jer med virksomhedsnavn i stedet.';
	}
	// 'error' (Datafordeleren down, or no API key on this server): go on with the number and the name the customer gave
	if ($company['firmanavn'] === '') {
		$company['firmanavn'] = 'CVR ' . $d['cvr'];
	}
}

if ($errors) {
	if ($json) {
		signup_json(array('ok' => false, 'errors' => $errors), 422);
	}
	signup_fallback_form($in, $errors);
}

// Limits are claimed (recorded, then counted), so parallel requests cannot all slip past them: 5 per hour from
// one address, and a ceiling for the whole server (max_signups_per_hour) that protects the mail reputation. Only
// submissions that passed validation count, so a customer's typos do not lock them (or everybody) out.
if (!signup_claim_event('opret', 5, 3600) || !signup_claim_event('opret_alle', (int)signup_setting('max_signups_per_hour', '60'), 3600, null, true)) {
	signup_too_many('Der er oprettet for mange regnskaber den seneste time. Prøv igen senere, eller ring til os.', $json);
}

// Many mails to one mailbox within a day: answer as if sent, but send nothing (no enumeration). Claimed
// before sending, per mailbox (signup_email_key: +tags and Gmail dots count as one).
if (!signup_claim_event('mail', (int)signup_setting('max_mails_per_day', '5'), 86400, $d['email'])) {
	signup_log('mail limit reached for ' . $d['email']);
	signup_done(array('ok' => true, 'resend' => '', 'email' => $d['email']), $json);
}

$regnskab = signup_normalize_name($company['firmanavn']);
if ($regnskab === '') {
	$regnskab = signup_normalize_name($d['navn']);
}
$pending = signup_create_pending(array(
	'navn' => $d['navn'], 'email' => $d['email'], 'tlf' => $d['tlf'], 'cvrnr' => $d['cvr'],
	'firmanavn' => $company['firmanavn'], 'addr1' => $company['addr1'], 'addr2' => $company['addr2'], 'postnr' => $company['postnr'], 'bynavn' => $company['bynavn'],
	'regnskab' => $regnskab, 'pakke' => $d['pakke'], 'source' => $d['source'], 'navn_fra_cvr' => $nameFromCvr ? 1 : 0,
));
$row = db_fetch_array(db_select("SELECT * FROM signup_pending WHERE id = " . (int)$pending['id'], __FILE__ . " linje " . __LINE__, true));
if (!$row || !signup_send_activation_mail($row, $pending['token'], 'welcome')) {
	$msg = 'Vi kunne ikke sende mailen lige nu. Prøv igen om lidt, eller ring til os.';
	if ($json) {
		signup_json(array('ok' => false, 'errors' => array('_' => $msg)), 500);
	}
	signup_page('Mailen kunne ikke sendes', signup_icon('err') . '<h1>Mailen kunne ikke sendes</h1><p class="lead">' . signup_h($msg) . '</p>' . signup_help_line(), '', 500);
}

signup_done(array('ok' => true, 'resend' => $pending['resend'], 'email' => $d['email']), $json);

/**
 * Ends the request with the "check your inbox" answer: JSON for the website, otherwise the page
 * with a "Send mailen igen" button (signup/nytlink.php).
 *
 * @param array $data array{ok: bool, resend: string, email: string}
 * @param bool  $json
 * @return void
 */
function signup_done(array $data, $json) {
	if ($json) {
		signup_json($data);
	}
	$again = ($data['resend'] !== '')
		? '<form method="post" action="nytlink.php"><input type="hidden" name="resend" value="' . signup_h($data['resend']) . '"><button type="submit" class="btn">Send mailen igen</button></form>'
		: '';
	signup_page('Tjek jeres mail',
		signup_icon('ok') . '<h1>Tjek jeres mail</h1>'
		. '<p class="lead">Vi har sendt et aktiveringslink til <strong>' . signup_h($data['email']) . '</strong>. Klik på linket i mailen, vælg jeres adgangskode, og I er i gang. Kig også i spam-mappen, hvis mailen ikke dukker op inden for et par minutter.</p>'
		. $again . signup_help_line());
}

/**
 * Ends the request with 429 and a message, as JSON or as a page.
 *
 * @param string $msg
 * @param bool   $json
 * @return void
 */
function signup_too_many($msg, $json) {
	if ($json) {
		signup_json(array('ok' => false, 'errors' => array('_' => $msg)), 429);
	}
	signup_page('For mange forsøg', signup_icon('warn') . '<h1>For mange forsøg</h1><p class="lead">' . signup_h($msg) . '</p>' . signup_help_line(), '', 429);
}

/**
 * A plain version of the website's form, for exercising the flow without the website.
 *
 * @param array $in     the values to show again
 * @param array $errors field => message
 * @return void
 */
function signup_fallback_form(array $in, array $errors) {
	$h = 'signup_h';
	$val = function ($k) use ($in, $h) { return $h((string)ifset($in, $k, '')); };
	$err = function ($k) use ($errors, $h) { return isset($errors[$k]) ? '<p class="check err">' . $h($errors[$k]) . '</p>' : ''; };
	$pakke = (string)ifset($_GET, 'pakke', ifset($in, 'pakke', 'business'));
	$opts = '';
	foreach (signup_packages() as $key => $p) {
		$opts .= '<option value="' . $h($key) . '"' . ($key === $pakke ? ' selected' : '') . '>' . $h($p['name'] . ' – ' . $p['price'] . ' kr./md.') . '</option>';
	}
	$content = '<h1>Opret jeres Saldi-regnskab</h1><p class="lead">30 dage gratis. Prøveperioden starter, når I aktiverer regnskabet fra mailen.</p>'
		. '<form method="post" action="opret.php" novalidate>'
		. '<div class="field"><label for="name">Navn</label><div class="input"><input id="name" name="name" type="text" autocomplete="name" value="' . $val('name') . '"></div>' . $err('name') . '</div>'
		. '<div class="field"><label for="cvr">CVR-nummer</label><div class="input"><input id="cvr" name="cvr" type="text" inputmode="numeric" value="' . $val('cvr') . '"></div>' . $err('cvr') . '</div>'
		. '<div class="field"><label for="company">Virksomhedsnavn (hvis I ikke har et CVR-nummer)</label><div class="input"><input id="company" name="company" type="text" autocomplete="organization" value="' . $val('company') . '"></div></div>'
		. '<div class="field"><label for="email">E-mail</label><div class="input"><input id="email" name="email" type="email" autocomplete="email" value="' . $val('email') . '"></div>' . $err('email') . '</div>'
		. '<div class="field"><label for="phone">Telefon (valgfrit)</label><div class="input"><input id="phone" name="phone" type="tel" autocomplete="tel" value="' . $val('phone') . '"></div>' . $err('phone') . '</div>'
		. '<div class="field"><label for="pakke">Pakke</label><div class="input"><select id="pakke" name="pakke">' . $opts . '</select></div>' . $err('pakke') . '</div>'
		. '<div class="field" hidden aria-hidden="true"><label for="website">Websted</label><input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></div>'
		. '<input type="hidden" name="source" value="fallback">'
		. '<button type="submit" class="btn">Opret regnskab</button>'
		. '<p class="fine">Når I trykker Opret, accepterer I Saldis handelsbetingelser og databehandleraftale.</p>'
		. '</form>';
	signup_page('Opret regnskab', $content, '', $errors ? 422 : 200);
}
