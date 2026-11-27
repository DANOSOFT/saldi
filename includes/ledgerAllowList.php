<?php
// --- includes/ledgerAllowList.php --- ver 5.1.1 --- 2026.11.27 ---
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
// 20261127 ASR Created. $allowedLedgers in includes/connect.php: the ledgers this installation opens. Without it every ledger opens, as before.
// 20261127 ASR The master database is read through saldi_install_setting('sqdb'), so it is known where connect.php was not included or was included in a function.
// 20261127 ASR saldi_ledger_refuse_json() for the endpoints that answer in JSON. The master database stays connected for the registry, sessions and settings; it is a working ledger only where it is listed (saldi_ledger_guard()).

include_once(__DIR__ . '/installSettings.php');

if (!function_exists('saldi_allowed_ledgers')) {
	/**
	 * The databases this installation may open as a ledger ($allowedLedgers in includes/connect.php: a list, or one
	 * name, or names separated by commas), or null when the setting is not there and every ledger opens. The master
	 * database is in it only when it is listed; a master-admin login then needs it listed.
	 *
	 * @return array<int, string>|null
	 */
	function saldi_allowed_ledgers(): ?array
	{
		$value = saldi_install_setting('allowedLedgers');
		if ($value === null || $value === false) {
			return null;
		}
		$names = is_array($value) ? $value : explode(',', (string) $value);
		$list = array();
		foreach ($names as $name) {
			$name = saldi_ledger_name($name);
			if ($name !== '') {
				$list[] = $name;
			}
		}
		return $list;
	}

	/** A database name as callers pass it ("'saldi_7'" from older call sites), without quotes and spaces. */
	function saldi_ledger_name($db): string
	{
		return trim(trim((string) $db), "'\"");
	}

	/**
	 * True when this installation may open the database as a ledger or a session: always without $allowedLedgers, else
	 * only a listed one (the master database too).
	 */
	function saldi_ledger_allowed($db): bool
	{
		$list = saldi_allowed_ledgers();
		return $list === null || in_array(saldi_ledger_name($db), $list, true);
	}

	/**
	 * True when this installation may connect to the database (db_connect()): the master database always, as every
	 * request reads it, else as saldi_ledger_allowed(). The master database is $sqdb of includes/connect.php, also
	 * where that file was not included or was included in a function (saldi_install_setting()).
	 */
	function saldi_ledger_connect_allowed($db): bool
	{
		$list = saldi_allowed_ledgers();
		if ($list === null) {
			return true;
		}
		$master = saldi_ledger_name(saldi_install_setting('sqdb'));
		$name = saldi_ledger_name($db);
		return ($master !== '' && $name === $master) || in_array($name, $list, true);
	}

	/**
	 * The refusal text (text 8235 in importfiler/tekster.csv) in the language of the session or the login page. It is
	 * not read with findtekst(): the refusal can come from db_connect(), before any database is there to read it from.
	 */
	function saldi_ledger_refusal_text(): string
	{
		$languageId = (isset($GLOBALS['sprog_id']) && (int) $GLOBALS['sprog_id'] > 0) ? (int) $GLOBALS['sprog_id'] : (int) (isset($_COOKIE['languageId']) ? $_COOKIE['languageId'] : 1);
		$texts = array(
			1 => 'Regnskabet kan ikke åbnes i denne installation.',
			2 => 'This ledger cannot be opened in this installation.',
			3 => 'Regnskapet kan ikke åpnes i denne installasjonen.',
		);
		return isset($texts[$languageId]) ? $texts[$languageId] : $texts[1];
	}

	/**
	 * Refuse the request: HTTP 403 with the refusal text (on the command line the text on STDERR and exit code 1).
	 * Nothing is written anywhere. Does not return.
	 *
	 * @param string $db the database that was asked for, for the server log
	 */
	function saldi_ledger_refuse($db = ''): void
	{
		$name = saldi_ledger_name($db);
		error_log('Saldi: database ' . ($name !== '' ? $name : '(none)') . ' is not in $allowedLedgers of this installation');
		if (PHP_SAPI === 'cli') {
			fwrite(STDERR, 'Database ' . $name . " is not in \$allowedLedgers (includes/connect.php).\n");
			exit(1);
		}
		if (!headers_sent()) {
			http_response_code(403);
			header('Content-Type: text/html; charset=UTF-8');
			header('Cache-Control: no-store');
		}
		$text = htmlspecialchars(saldi_ledger_refusal_text(), ENT_QUOTES, 'UTF-8');
		print "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><title>Saldi</title></head><body><p style=\"font-family:sans-serif;margin:40px\">$text</p></body></html>";
		exit;
	}

	/**
	 * Refuse a request that answers in JSON: HTTP 403 with {"success": false, "error": <the refusal text>}, after any
	 * output buffered so far is dropped. Nothing is written anywhere. Does not return.
	 *
	 * @param string $db the database that was asked for, for the server log
	 */
	function saldi_ledger_refuse_json($db = ''): void
	{
		$name = saldi_ledger_name($db);
		error_log('Saldi: database ' . ($name !== '' ? $name : '(none)') . ' is not in $allowedLedgers of this installation');
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		if (!headers_sent()) {
			http_response_code(403);
			header('Content-Type: application/json');
			header('Cache-Control: no-store');
		}
		echo json_encode(array('success' => false, 'error' => saldi_ledger_refusal_text()));
		exit;
	}

	/**
	 * Refuse the request unless this installation may open the database as a ledger or a session (saldi_ledger_allowed()).
	 * Every place that selects a ledger as the working one ($db) calls it, or saldi_ledger_allowed(), before the ledger
	 * is opened or anything is written for it: db_connect() lets the master database through for the registry, the
	 * sessions and the settings, so only this check keeps an unlisted master database from being the working ledger.
	 */
	function saldi_ledger_guard($db): void
	{
		if (!saldi_ledger_allowed($db)) {
			saldi_ledger_refuse($db);
		}
	}
}
