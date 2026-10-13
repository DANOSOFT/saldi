<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/stripeAlertMail.php --- 2026.10.13
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
// 20260807 CL/LH Created: alert channel for the Stripe integration. Email is
//                the ONLY live channel (the notifications UI is dead code,
//                online.php:427-496) - every alert is also appended to
//                temp/.ht_stripe.log as the audit trail. Deliberately NOT
//                std_func's send_email() (mails twice, echoes script tags)
//                and not PHPMailer (hardcoded vendor path in its one api/
//                usage) - plain mail() like debitor/easyUBL.php.
// 20261012 CL/LH Record mail throttles only after acceptance and report delivery failure to retrying callers.
// 20261013 CL/LH (CodeRabbit) Share recipient resolution so callers distinguish missing recipients from transport failure.

include_once(__DIR__ . '/stripeSettings.php');

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

/**
 * Serialize alert delivery and record only mail accepted by the transport.
 * @return bool True for accepted mail or an already accepted throttled notification; false on delivery failure.
 */
function stripe_alert_deliver(callable $send, string $key, int $window): bool
{
	if ($key === '' || $window <= 0) return (bool)$send();
	$dir = dirname(__DIR__, 2) . '/temp';
	if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) return (bool)$send();
	$fp = fopen($dir . '/.ht_stripe_rl_' . md5('alert_' . $key), 'c+');
	if (!$fp) return (bool)$send();
	try {
		if (!flock($fp, LOCK_EX)) return (bool)$send();
		$now = time();
		$raw = stream_get_contents($fp);
		foreach (explode("\n", $raw === false ? '' : $raw) as $line) {
			$stamp = (int)$line;
			if ($stamp > 0 && $stamp > $now - $window) return true;
		}
		// 20261012 CL/LH Failure leaves no stamp, so Stripe/outbox retries can deliver the alert.
		if (!$send()) return false;
		ftruncate($fp, 0);
		rewind($fp);
		if (fwrite($fp, (string)time()) === false) stripe_log('ALERT throttle write failed: ' . $key);
		return true;
	} finally {
		flock($fp, LOCK_UN);
		fclose($fp);
	}
}

/**
 * Resolve bookkeeper_email or the tenant's seller address.
 * @return string Valid alert email address, or an empty string when none is configured.
 */
function stripe_alert_recipient(): string
{
	// 20261013 CL/LH (CodeRabbit) Resolve independently of delivery and its transport result.
	$to = trim(stripe_setting('bookkeeper_email'));
	if (!$to) {
		$r = db_fetch_array(db_select("select email from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__));
		if ($r && filter_var(trim((string)$r['email']), FILTER_VALIDATE_EMAIL)) $to = trim((string)$r['email']);
	}
	return $to && filter_var($to, FILTER_VALIDATE_EMAIL) ? $to : '';
}

if (!function_exists('stripeAlertMail')) {
	/**
	 * Log every alert and deliver to bookkeeper_email or the tenant's seller address.
	 * @return bool Accepted/already accepted mail, or false when sending fails or no recipient exists.
	 */
	function stripeAlertMail($subject, $body, $throttleKey = '', $throttleSec = 3600): bool {
		stripe_log('ALERT ' . $subject . ' :: ' . $body);

		$to = stripe_alert_recipient();
		if ($to === '') return false;

		$host = parse_url(stripe_setting('public_base_url'), PHP_URL_HOST);
		if (!$host) $host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^A-Za-z0-9.-]/', '', $_SERVER['HTTP_HOST']) : 'saldi.dk';
		$from = 'noreply@' . $host;
		$headers = "From: $from\r\nReply-To: $from\r\nContent-Type: text/plain; charset=UTF-8\r\nX-Mailer: Saldi-Stripe\r\n";
		try {
			return stripe_alert_deliver(static function () use ($to, $subject, $body, $headers): bool {
				return mail($to, '[Saldi Stripe] ' . str_replace(["\r", "\n"], ' ', $subject), $body, $headers);
			}, (string)$throttleKey, (int)$throttleSec);
		} catch (Throwable $e) {
			stripe_log('ALERT mail failed: ' . $e->getMessage());
			return false;
		}
	}
}
