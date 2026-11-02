<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- signup/nytlink.php --- ver 5.1.0 --- 2026-11-02 ---
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
// "Få et nyt link" / "Send mailen igen" (plan.md, section 2): issues a new activation token,
// which invalidates the old one, and mails it. The pending signup is identified by one of
//   resend=<key>   the key signup/opret.php returned to the "check your inbox" page
//   t=<token>      an activation token, current or expired (the link in the mail)
//   email=<addr>   typed by the customer when nothing else is at hand
// The answer is the same whether or not anything was sent (no enumeration), and the number of
// mails per signup and per client address is limited. GET with t= shows the page with the
// button; POST does the work; JSON when the client asks for it.
//
// 20261014 CL/PS Created.
// 20261102 CL/ASR Refuses POSTs from foreign origins.
//                 At most one new link per signup a minute and the per-mailbox daily mail cap, both claimed before sending.

chdir(__DIR__);
include(__DIR__ . "/../includes/signupIncludes/signupBootstrap.php");

signup_cors();
$json = signup_wants_json();
$method = ifset($_SERVER, 'REQUEST_METHOD', 'GET');
$in = signup_input();
$token = (string)ifset($in, 't', ifset($_GET, 't', ''));

if ($method !== 'POST') {
	$row = $token !== '' ? signup_find_pending_by_token($token) : null;
	if ($row && $row['status'] === 'pending') {
		signup_page('Nyt aktiveringslink',
			signup_icon('warn') . '<h1>Få et nyt aktiveringslink</h1>'
			. '<p class="lead">Vi sender et nyt link til <strong>' . signup_h(signup_mask_email($row['email'])) . '</strong>. Det gamle link holder op med at virke.</p>'
			. '<form method="post" action="nytlink.php"><input type="hidden" name="t" value="' . signup_h($token) . '"><button type="submit" class="btn">Send et nyt link</button></form>' . signup_help_line());
	}
	signup_page('Nyt aktiveringslink',
		signup_icon('warn') . '<h1>Få et nyt aktiveringslink</h1>'
		. '<p class="lead">Skriv den e-mail, I oprettede jer med, så sender vi et nyt link.</p>'
		. '<form method="post" action="nytlink.php"><div class="field"><label for="email">E-mail</label><div class="input"><input id="email" name="email" type="email" autocomplete="email" required></div></div>'
		. '<button type="submit" class="btn">Send et nyt link</button></form>' . signup_help_line());
}

if ($method === 'POST') {
	signup_refuse_foreign_origin($json);
}

if (!signup_claim_event('nytlink', 10, 3600)) {
	$msg = 'Der er sendt mange mails fra jeres adresse den seneste time. Prøv igen senere, eller ring til os.';
	if ($json) {
		signup_json(array('ok' => false, 'errors' => array('_' => $msg)), 429);
	}
	signup_page('For mange forsøg', signup_icon('warn') . '<h1>For mange forsøg</h1><p class="lead">' . signup_h($msg) . '</p>' . signup_help_line(), '', 429);
}

$row = null;
$email = signup_clean_text(ifset($in, 'email', ''), 60);
$resend = (string)ifset($in, 'resend', '');
if ($resend !== '') {
	$row = signup_find_pending_by_resend($resend);
} elseif ($token !== '') {
	$row = signup_find_pending_by_token($token);
} elseif ($email !== '' && signup_valid_email($email)) {
	$qtxt = "SELECT * FROM signup_pending WHERE status = 'pending' AND lower(email) = " . signup_q(mb_strtolower($email)) . " ORDER BY id DESC LIMIT 1";
	$row = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, true)) ?: null;
}

$sent = false;
// At most one new link per signup a minute (claimed, so parallel requests cannot all pass) and the same daily
// cap per mailbox as opret.php.
if ($row && signup_may_resend($row) && signup_claim_event('resend', 1, 60, 'signup' . (int)$row['id'])
	&& signup_claim_event('mail', (int)signup_setting('max_mails_per_day', '5'), 86400, $row['email'])) {
	$newToken = signup_reissue_token($row);
	$row = db_fetch_array(db_select("SELECT * FROM signup_pending WHERE id = " . (int)$row['id'], __FILE__ . " linje " . __LINE__, true));
	$sent = signup_send_activation_mail($row, $newToken, 'newlink');
} elseif ($row) {
	signup_log('resend refused for signup ' . $row['id'] . ' (status ' . $row['status'] . ', ' . $row['mails_sent'] . ' mails)');
}
$shown = $row ? signup_mask_email($row['email']) : ($email !== '' ? signup_mask_email($email) : 'jeres e-mail');

if ($json) {
	signup_json(array('ok' => true, 'email' => $shown));
}
signup_page('Tjek jeres mail',
	signup_icon('ok') . '<h1>Tjek jeres mail</h1>'
	. '<p class="lead">Hvis der findes en oprettelse, der venter på at blive aktiveret, har vi sendt et nyt link til <strong>' . signup_h($shown) . '</strong>. Kig også i spam-mappen. Der kan gå et minut, før mailen dukker op.</p>'
	. signup_help_line());
