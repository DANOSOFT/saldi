<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/twoFactor.php --- ver 5.1.1 --- 2026-11-25 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261114 CL/ASR Created. One place for the two-factor rules that index/login.php, personal settings and Brugere & roller share: which ways a code can reach a user, which one is used, and the code sent last.
// 20261125 CL/ASR twofactor_send() reads its texts with findtekst_utf8(): on a LATIN9 ledger findtekst()'s text made send_sms()'s JSON fail and the e-mail arrive without the code.
//
// A user with two-factor login (brugere.twofactor) gets a code by text message or by e-mail. Which of the two is the
// user's own choice, kept in the ledger's settings table (var_grp '2fa', var_name 'method', user_id). Without a
// choice the rule is the old one: a text message when there is a phone number, else an e-mail. At login the user can
// ask for the code the other way, when both are there.
//
// The code sent last is kept in the same table (var_name 'code') as "expire|code|method|smsAt|emailAt", and not in
// brugere.tmp_kode any more: that field also holds the temporary password from Glemt adgangskode, and a two-factor
// code written over it made the temporary password useless for a user with two-factor login.
//
// The functions below the line "database" read and write the ledger's database, so they are called while the
// request is connected to it.

if (!defined('TWOFACTOR_TTL')) {
	define('TWOFACTOR_TTL', 180);
	define('TWOFACTOR_RESEND_WAIT', 30);
}

if (!function_exists('twofactor_is_on')) {
	/** brugere.twofactor as PostgreSQL, MySQL and the forms hand it over. */
	function twofactor_is_on($value): bool
	{
		return in_array($value, array('t', true, '1', 1, 'on'), true);
	}

	/** A number a text message can be sent to: at least eight digits. */
	function twofactor_phone_ok($tlf): bool
	{
		return strlen((string) preg_replace('/\D/', '', (string) $tlf)) >= 8;
	}

	function twofactor_email_ok($email): bool
	{
		return filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL) !== false;
	}

	/**
	 * The ways a code can reach the user.
	 *
	 * @return array<string, string> 'sms' and/or 'email' => where it goes. SMS first, the order used without a choice.
	 */
	function twofactor_methods($tlf, $email): array
	{
		$methods = array();
		if (twofactor_phone_ok($tlf)) {
			$methods['sms'] = trim((string) $tlf);
		}
		if (twofactor_email_ok($email)) {
			$methods['email'] = trim((string) $email);
		}
		return $methods;
	}

	/**
	 * The way to use: what was asked for at login, else the user's own choice, else the first that is there.
	 *
	 * @param array<string, string> $methods From twofactor_methods().
	 * @return string 'sms', 'email' or '' when no code can be sent at all.
	 */
	function twofactor_pick(array $methods, $preferred = '', $requested = ''): string
	{
		foreach (array((string) $requested, (string) $preferred) as $wanted) {
			if ($wanted !== '' && isset($methods[$wanted])) {
				return $wanted;
			}
		}
		foreach ($methods as $method => $to) {
			return (string) $method;
		}
		return '';
	}

	/** A phone number for the screen: only the last two digits. Plain ASCII, the page may be ISO-8859-1. */
	function twofactor_mask_phone($tlf): string
	{
		$digits = (string) preg_replace('/\D/', '', (string) $tlf);
		return '** ** ** ' . substr($digits, -2);
	}

	/** An e-mail address for the screen: the first character and the domain. */
	function twofactor_mask_email($email): string
	{
		$email = trim((string) $email);
		$at = strrpos($email, '@');
		if ($at === false || $at < 1) {
			return '***';
		}
		return mb_substr($email, 0, 1, 'UTF-8') . '***' . substr($email, $at);
	}

	/** Six digits from the system's random source. */
	function twofactor_new_code(): string
	{
		return (string) random_int(100000, 999999);
	}

	/**
	 * The stored "expire|code|method|smsAt|emailAt" as an array; an empty or damaged value is "no code".
	 *
	 * @return array{expire: int, code: string, method: string, sent: array{sms: int, email: int}}
	 */
	function twofactor_state_parse($stored): array
	{
		$parts = explode('|', (string) $stored);
		$method = isset($parts[2]) ? (string) $parts[2] : '';
		return array(
			'expire' => isset($parts[0]) ? (int) $parts[0] : 0,
			'code'   => (isset($parts[1]) && ctype_digit((string) $parts[1])) ? (string) $parts[1] : '',
			'method' => in_array($method, array('sms', 'email'), true) ? $method : '',
			'sent'   => array('sms' => isset($parts[3]) ? (int) $parts[3] : 0, 'email' => isset($parts[4]) ? (int) $parts[4] : 0),
		);
	}

	/** @param array{expire: int, code: string, method: string, sent: array{sms: int, email: int}} $state */
	function twofactor_state_format(array $state): string
	{
		return (int) $state['expire'] . '|' . (ctype_digit((string) $state['code']) ? $state['code'] : '') . '|'
			. (in_array($state['method'], array('sms', 'email'), true) ? $state['method'] : '') . '|'
			. (int) $state['sent']['sms'] . '|' . (int) $state['sent']['email'];
	}

	/**
	 * What may go on to the page of what sending a text message printed: the notices of send_sms(), which are
	 * script tags with one alert() each. The rest is dropped: send_sms() includes includes/online.php, which prints
	 * a whole page head.
	 */
	function twofactor_notices($printed): string
	{
		if (!preg_match_all("#<script>alert\\('(?:[^'\\\\]|\\\\.)*'\\);?</script>#s", (string) $printed, $found)) {
			return '';
		}
		return implode("\n", $found[0]);
	}

	// ------------------------------------------------------------------ database (the ledger's)

	/** The user's own choice of 'sms' or 'email'; '' when none is made. */
	function twofactor_preferred(int $userId): string
	{
		$method = (string) get_settings_value('method', '2fa', '', $userId);
		return in_array($method, array('sms', 'email'), true) ? $method : '';
	}

	/** Stores the choice; '' removes it, so the old rule (SMS when there is a number) decides again. */
	function twofactor_set_preferred(int $userId, string $method): void
	{
		if (in_array($method, array('sms', 'email'), true)) {
			update_settings_value('method', '2fa', $method, 'Two-factor login: how the code is sent', $userId);
		} else {
			db_modify("delete from settings where var_grp = '2fa' and var_name = 'method' and user_id = $userId", __FILE__ . " linje " . __LINE__);
		}
	}

	/**
	 * What login needs to know about a user's two-factor login.
	 *
	 * The e-mail is the login's own (brugere.email), else the employee's, else the user name when that is an address:
	 * the same places Glemt adgangskode looks.
	 *
	 * @return array{on: bool, tlf: string, email: string, methods: array<string, string>, preferred: string}
	 */
	function twofactor_user(int $userId): array
	{
		// select * : company databases that predate the email/tlf/twofactor columns still answer.
		$u = db_fetch_array(db_select("select * from brugere where id = $userId", __FILE__ . " linje " . __LINE__));
		$u = ($u ?: array()) + array('brugernavn' => '', 'email' => '', 'tlf' => '', 'twofactor' => 'f', 'ansat_id' => 0);
		$email = trim((string) $u['email']);
		if ($email === '' && (int) $u['ansat_id'] > 0) {
			$a = db_fetch_array(db_select("select email from ansatte where id = " . (int) $u['ansat_id'], __FILE__ . " linje " . __LINE__));
			if ($a) {
				$email = trim((string) $a['email']);
			}
		}
		if ($email === '' && twofactor_email_ok($u['brugernavn'])) {
			$email = trim((string) $u['brugernavn']);
		}
		$on = twofactor_is_on($u['twofactor']);
		return array(
			'on'        => $on,
			'tlf'       => trim((string) $u['tlf']),
			'email'     => $email,
			'methods'   => twofactor_methods($u['tlf'], $email),
			'preferred' => $on ? twofactor_preferred($userId) : '',
		);
	}

	/** @return array{expire: int, code: string, method: string, sent: array{sms: int, email: int}} */
	function twofactor_state(int $userId): array
	{
		return twofactor_state_parse(get_settings_value('code', '2fa', '', $userId));
	}

	/** @param array{expire: int, code: string, method: string, sent: array{sms: int, email: int}} $state */
	function twofactor_save_state(int $userId, array $state): void
	{
		update_settings_value('code', '2fa', twofactor_state_format($state), 'Two-factor login: the code sent last', $userId);
	}

	/**
	 * Sends a code. A text message goes through send_sms(), which needs the session's row in the master table
	 * online (it reads the ledger from it), so index/login.php adds that row around this call.
	 *
	 * @param string $output Filled with the notices send_sms() printed (script tags, see twofactor_notices()).
	 * @return bool True when the message was handed over.
	 */
	function twofactor_send(string $method, string $to, string $code, $languageId, &$output = ''): bool
	{
		$languageId = in_array((int) $languageId, array(2, 3), true) ? (int) $languageId : 1;
		// send_sms() JSON-encodes the text and send_email() sends UTF-8. findtekst() gives a LATIN9 ledger's own encoding,
		// which json_encode() refuses and htmlspecialchars(..., 'UTF-8') turns into '', so the texts are read as UTF-8.
		if (!function_exists('findtekst_utf8')) include_once(__DIR__ . '/stdFunc/findTxtUtf8.php');
		$text = sprintf(findtekst_utf8('login.tfa.message|Din kode til Saldi er %s. Den gælder i 3 minutter.', $languageId), $code);
		$sent = false;
		ob_start();
		try {
			if ($method === 'sms') {
				$sent = (send_sms('Saldi', $to, $text) === true);
			} elseif ($method === 'email') {
				$subject = findtekst_utf8('login.tfa.mail_subject|Din kode til Saldi', $languageId);
				$body = '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p><p>'
					. htmlspecialchars(findtekst_utf8('login.tfa.mail_warning|Har du ikke selv forsøgt at logge ind, så skift din adgangskode.', $languageId), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
				$sent = (send_email($to, $subject, $body) === true);
			}
		} catch (Throwable $error) {
			$sent = false;
		}
		$output = twofactor_notices(ob_get_clean());
		return $sent;
	}
}
