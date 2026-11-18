<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/ebCoverage.php --- ver 5.1.1 --- 2026-11-16 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
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
//
// 20261006 CL/LH Initial version. Read-only feed coverage for a ledger account, used by the CSV bank import (finans/bankimport.php) to warn that overlapping lines may duplicate bank feed lines.
//                Deliberately standalone (no provider code) so the CSV import does not load the bank client.
// 20261028 CDX/LH Remove the retired onboarding state from bank feed coverage.
// 20261116 CL/LH Texts through findtekst() (bank.coverage.*) in the page's charset; no automatic import wording.

if (!function_exists('ebFeedCoverageForLedger')) {
	/**
	 * Bank feed accounts mapped to a ledger account, with the period the feed covers.
	 * Requires includes/connect.php and the tenant connection (online.php) to be loaded by the caller.
	 *
	 * @param int $kontonr kontoplan.kontonr of the bank account in the chart.
	 * @return array<int, array{
	 *   display_name: string,      Account name from the bank.
	 *   display_identity: string,  Masked account number.
	 *   state: string,             bank_accounts.state.
	 *   import_from: string,       First date the feed imports (Y-m-d).
	 *   fetched_through: string    Last date fully fetched (Y-m-d), '' when nothing fetched yet.
	 * }>
	 */
	function ebFeedCoverageForLedger(int $kontonr): array {
		$out = array();
		if ($kontonr <= 0 || !tbl_exists('bank_accounts')) {
			return $out;
		}
		$qtxt = "SELECT display_name, display_identity, state, import_from, fetched_through FROM bank_accounts"
			. " WHERE ledger_account = " . (int)$kontonr
			. " AND import_from IS NOT NULL AND state IN ('active', 'paused', 'disconnected') ORDER BY id";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($q && ($r = db_fetch_array($q))) {
			$out[] = array(
				'display_name'     => (string)$r['display_name'],
				'display_identity' => (string)$r['display_identity'],
				'state'            => (string)$r['state'],
				'import_from'      => substr((string)$r['import_from'], 0, 10),
				'fetched_through'  => substr((string)$r['fetched_through'], 0, 10),
			);
		}
		return $out;
	}

	/**
	 * Informational notice for the CSV bank import when the chosen ledger account is fed by the bank feed.
	 *
	 * @param int $kontonr kontoplan.kontonr chosen as bank posting account in the CSV import.
	 * @return string HTML ('' when the account is not covered).
	 */
	function ebFeedCoverageNotice(int $kontonr): string {
		global $sprog_id;
		// The CSV import page and the tenant's texts use the tenant's charset.
		$charset = ($GLOBALS['db_encode'] ?? 'UTF8') === 'UTF8' ? 'UTF-8' : 'ISO-8859-1';
		$lines = array();
		foreach (ebFeedCoverageForLedger($kontonr) as $c) {
			$from = ebCoverageDate($c['import_from']);
			$name = trim($c['display_name'] . ' ' . $c['display_identity']);
			if ($c['fetched_through'] !== '') {
				$text = sprintf(findtekst('bank.coverage.fetched|Konto %s hentes via bankforbindelsen (%s) fra %s til %s', $sprog_id), $kontonr, $name, $from, ebCoverageDate($c['fetched_through']));
			} else {
				$text = sprintf(findtekst('bank.coverage.not_fetched|Konto %s hentes via bankforbindelsen (%s) fra %s (endnu ikke hentet)', $sprog_id), $kontonr, $name, $from);
			}
			if ($c['state'] === 'disconnected') {
				$text .= '. ' . findtekst('bank.coverage.disconnected|Forbindelsen er nu afbrudt', $sprog_id);
			}
			$lines[] = htmlspecialchars($text . '.', ENT_QUOTES | ENT_SUBSTITUTE, $charset);
		}
		if (!$lines) {
			return '';
		}
		return "<div style='background:#fff6dd;border:1px solid #e8cf7a;border-radius:6px;padding:6px 10px;margin:6px 0;text-align:left'>"
			. implode('<br>', $lines) . '<br>'
			. htmlspecialchars(findtekst('bank.coverage.duplicates|Linjer i CSV-filen inden for denne periode kan give dobbeltposteringer af bankposteringer, der allerede er hentet.', $sprog_id), ENT_QUOTES | ENT_SUBSTITUTE, $charset)
			. "</div>";
	}

	/**
	 * Y-m-d as dd-mm-yyyy.
	 *
	 * @param string $date Date (Y-m-d).
	 * @return string
	 */
	function ebCoverageDate(string $date): string {
		return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) ? "$m[3]-$m[2]-$m[1]" : $date;
	}
}
