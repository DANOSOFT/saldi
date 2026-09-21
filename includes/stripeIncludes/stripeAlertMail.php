<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/stripeAlertMail.php --- 2026.08.07
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
// 20260807 CL/LH Created: alert channel for the Stripe integration. Email is
//                the ONLY live channel (the notifications UI is dead code,
//                online.php:427-496) - every alert is also appended to
//                temp/.ht_stripe.log as the audit trail. Deliberately NOT
//                std_func's send_email() (mails twice, echoes script tags)
//                and not PHPMailer (hardcoded vendor path in its one api/
//                usage) - plain mail() like debitor/easyUBL.php.

include_once(__DIR__ . '/stripeSettings.php');
include_once(__DIR__ . '/stripeRateLimit.php');

if (!function_exists('stripe_log')) {
	// One line, timestamped, secrets redacted. Never throws.
	function stripe_log($line) {
		$dir = dirname(__DIR__, 2) . '/temp';
		if (!is_dir($dir) && !@mkdir($dir, 0777, true)) return;
		@file_put_contents($dir . '/.ht_stripe.log',
			date('Y-m-d H:i:s') . ' ' . stripe_redact(str_replace(["\r", "\n"], ' ', (string)$line)) . "\n",
			FILE_APPEND | LOCK_EX);
	}
}

if (!function_exists('stripeAlertMail')) {
	// Logs always; mails when a recipient is configured (bookkeeper_email,
	// falling back to the regnskab's own address, adresser art='S').
	// $throttleKey suppresses repeats (default one identical alert per hour).
	function stripeAlertMail($subject, $body, $throttleKey = '', $throttleSec = 3600) {
		stripe_log('ALERT ' . $subject . ' :: ' . $body);

		$to = trim(stripe_setting('bookkeeper_email'));
		if (!$to) {
			$r = db_fetch_array(db_select("select email from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__));
			if ($r && filter_var(trim((string)$r['email']), FILTER_VALIDATE_EMAIL)) $to = trim($r['email']);
		}
		if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
		if ($throttleKey !== '' && !stripe_rate_limit('alert_' . $throttleKey, 1, $throttleSec)) return false;

		$host = parse_url(stripe_setting('public_base_url'), PHP_URL_HOST);
		if (!$host) $host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^A-Za-z0-9.-]/', '', $_SERVER['HTTP_HOST']) : 'saldi.dk';
		$from = 'noreply@' . $host;
		$headers = "From: $from\r\nReply-To: $from\r\nContent-Type: text/plain; charset=UTF-8\r\nX-Mailer: Saldi-Stripe\r\n";
		return @mail($to, '[Saldi Stripe] ' . str_replace(["\r", "\n"], ' ', $subject), $body, $headers);
	}
}
