<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/loginThrottle.php --- ver 5.1.1 --- 2026-11-24 ---
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
// 20261114 CL/ASR Created. Failed logins are counted per user and per IP address.
// 20261114 CL/ASR login_throttle_message() has the text for a user with two-factor login and nowhere to send the code ('tfa_nocontact').
// 20261124 CL/ASR Comments state the current rule; no code change.
//
// The first LOGIN_THROTTLE_TRIES wrong attempts for a user are answered at once. Then that user's login pauses for
// 5 minutes, 15 minutes the next time and 1 hour after that. A temporary password from "Glemt adgangskode" still
// works during the pause (index/login.php), and a successful login clears the count. An IP address with
// LOGIN_THROTTLE_IP_TRIES failures in LOGIN_THROTTLE_IP_WINDOW seconds pauses too, whatever the user names.
//
// The counts live in the master database's settings table (var_grp 'login_throttle'), one row per user or address:
// var_name is 'u:' or 'i:' plus a sha1, var_value is "count|level|pausedUntil" and var_description the time of the
// last failure. No row names a user or an address in clear text. Every function reads and writes with $global = true,
// so it works whether the request is connected to the master database or to a ledger.

if (!defined('LOGIN_THROTTLE_TRIES')) {
	define('LOGIN_THROTTLE_TRIES', 5);
	define('LOGIN_THROTTLE_FORGET', 7200);
	define('LOGIN_THROTTLE_IP_TRIES', 30);
	define('LOGIN_THROTTLE_IP_WINDOW', 600);
}

if (!function_exists('login_throttle_pauses')) {
	/** Seconds of pause for the 1st, 2nd and every later time a user runs out of attempts. */
	function login_throttle_pauses(): array
	{
		return array(300, 900, 3600);
	}

	/** The row name of a user in a ledger: both lower-cased, as the login matches them without regard to case. */
	function login_throttle_user_key($regnskab, $brugernavn): string
	{
		return 'u:' . sha1(mb_strtolower(trim((string) $regnskab), 'UTF-8') . '|' . mb_strtolower(trim((string) $brugernavn), 'UTF-8'));
	}

	function login_throttle_ip_key($ip): string
	{
		return 'i:' . sha1((string) $ip);
	}

	/**
	 * @return array{id: int, n: int, level: int, until: int, last: int} id 0 when there is no row.
	 */
	function login_throttle_read(string $key): array
	{
		$state = array('id' => 0, 'n' => 0, 'level' => 0, 'until' => 0, 'last' => 0);
		$qtxt = "select id, var_value, var_description from settings where var_grp = 'login_throttle' and var_name = '" . db_escape_string($key) . "' order by id";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__, true);
		$r = $q ? db_fetch_array($q) : null;
		if ($r) {
			$parts = explode('|', (string) $r['var_value']);
			$state['id'] = (int) $r['id'];
			$state['n'] = isset($parts[0]) ? (int) $parts[0] : 0;
			$state['level'] = isset($parts[1]) ? (int) $parts[1] : 0;
			$state['until'] = isset($parts[2]) ? (int) $parts[2] : 0;
			$state['last'] = (int) $r['var_description'];
		}
		return $state;
	}

	function login_throttle_write(string $key, array $state): void
	{
		$value = (int) $state['n'] . '|' . (int) $state['level'] . '|' . (int) $state['until'];
		$last = (int) $state['last'];
		if ($state['id']) {
			$qtxt = "update settings set var_value = '$value', var_description = '$last' where id = '" . (int) $state['id'] . "'";
		} else {
			$qtxt = "insert into settings (var_name, var_grp, var_value, var_description) values ('" . db_escape_string($key) . "', 'login_throttle', '$value', '$last')";
		}
		db_modify($qtxt, __FILE__ . " linje " . __LINE__, true);
	}

	/**
	 * Whether a login for this user from this address is paused right now.
	 *
	 * @return array{by: string, until: int} by is '' (not paused), 'ip' or 'user'.
	 */
	function login_throttle_status($regnskab, $brugernavn, $ip): array
	{
		$now = time();
		if ((string) $ip !== '') {
			$ipState = login_throttle_read(login_throttle_ip_key($ip));
			if ($ipState['until'] > $now) {
				return array('by' => 'ip', 'until' => $ipState['until']);
			}
		}
		if ((string) $brugernavn !== '') {
			$userState = login_throttle_read(login_throttle_user_key($regnskab, $brugernavn));
			if ($userState['until'] > $now) {
				return array('by' => 'user', 'until' => $userState['until']);
			}
		}
		return array('by' => '', 'until' => 0);
	}

	/**
	 * Counts one failed attempt. Pass an empty $brugernavn to count the address only (an unknown ledger).
	 *
	 * @return array{until: int, remaining: int} until > 0 when this failure started a pause; remaining is the
	 *                                           number of attempts the user has left before one (-1 without a user).
	 */
	function login_throttle_fail($regnskab, $brugernavn, $ip): array
	{
		$now = time();
		$result = array('until' => 0, 'remaining' => -1);
		// Rows nobody has touched for a day are of no use any more.
		db_modify("delete from settings where var_grp = 'login_throttle' and var_description < '" . ($now - 86400) . "'", __FILE__ . " linje " . __LINE__, true);
		if ((string) $brugernavn !== '') {
			$key = login_throttle_user_key($regnskab, $brugernavn);
			$state = login_throttle_read($key);
			if ($state['last'] && $now - $state['last'] > LOGIN_THROTTLE_FORGET) {
				$state['n'] = 0;
				$state['level'] = 0;
			}
			$state['n']++;
			$state['last'] = $now;
			if ($state['n'] >= LOGIN_THROTTLE_TRIES) {
				$pauses = login_throttle_pauses();
				$state['until'] = $now + $pauses[min($state['level'], count($pauses) - 1)];
				$state['level']++;
				$state['n'] = 0;
				$result['until'] = $state['until'];
				$result['remaining'] = 0;
			} else {
				$result['remaining'] = LOGIN_THROTTLE_TRIES - $state['n'];
			}
			login_throttle_write($key, $state);
		}
		if ((string) $ip !== '') {
			$key = login_throttle_ip_key($ip);
			$state = login_throttle_read($key);
			// For an address, level holds the start of the window being counted.
			if (!$state['level'] || $now - $state['level'] > LOGIN_THROTTLE_IP_WINDOW) {
				$state['n'] = 0;
				$state['level'] = $now;
			}
			$state['n']++;
			$state['last'] = $now;
			if ($state['n'] >= LOGIN_THROTTLE_IP_TRIES) {
				$state['until'] = $now + LOGIN_THROTTLE_IP_WINDOW;
				$state['n'] = 0;
				$state['level'] = 0;
				if (!$result['until']) {
					$result['until'] = $state['until'];
				}
			}
			login_throttle_write($key, $state);
		}
		return $result;
	}

	/** A successful login: the user's count and pause are gone. The address keeps its own count. */
	function login_throttle_success($regnskab, $brugernavn): void
	{
		if ((string) $brugernavn === '') {
			return;
		}
		$qtxt = "delete from settings where var_grp = 'login_throttle' and var_name = '" . db_escape_string(login_throttle_user_key($regnskab, $brugernavn)) . "'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__, true);
	}

	/**
	 * The text for the login page. It travels in the form field fejltxt, so it stays under 80 characters and uses
	 * no quotes, semicolons or ampersands (sanitize_input() in index/login.php).
	 *
	 * @param string $kind       'wrong' (wrong user name or password), 'paused' or 'tfa_nocontact'.
	 * @param mixed  $languageId 1 Danish, 2 English, 3 Norwegian; anything else is Danish.
	 * @param int    $number     For 'wrong': attempts left (shown from 2 and down). For 'paused': when the pause ends.
	 */
	function login_throttle_message(string $kind, $languageId, int $number = -1): string
	{
		$lang = in_array((int) $languageId, array(2, 3), true) ? (int) $languageId : 1;
		$texts = array(
			1 => array('wrong' => 'Fejl i brugernavn eller adgangskode', 'left' => '. %d forsøg tilbage.', 'paused' => 'For mange forsøg. Prøv igen kl. %s, eller brug Glemt adgangskode.'),
			2 => array('wrong' => 'Wrong user name or password', 'left' => '. %d attempts left.', 'paused' => 'Too many attempts. Try again at %s, or use Forgot password.'),
			3 => array('wrong' => 'Feil i brukernavn eller passord', 'left' => '. %d forsøk igjen.', 'paused' => 'For mange forsøk. Prøv igjen kl. %s, eller bruk Glemt passord.'),
		);
		if ($kind === 'paused') {
			return sprintf($texts[$lang]['paused'], date('H.i', $number + 59));
		}
		if ($kind === 'tfa_nocontact') {
			// Two-factor login is on, but there is nowhere to send the code (index/login.php).
			$none = array(
				1 => 'Tofaktor mangler mobilnummer eller e-mail. Kontakt jeres administrator.',
				2 => 'Two-factor has no mobile number or email. Contact your administrator.',
				3 => 'Tofaktor mangler mobilnummer eller e-post. Kontakt administratoren deres.',
			);
			return $none[$lang];
		}
		$text = $texts[$lang]['wrong'];
		if ($number >= 1 && $number <= 2) {
			$text .= sprintf($texts[$lang]['left'], $number);
		}
		return $text;
	}
}
