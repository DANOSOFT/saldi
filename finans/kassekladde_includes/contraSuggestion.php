<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/contraSuggestion.php --- ver 5.0.0 --- 2026-10-03 ---
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
// 20261003 CL/SZ SD-722 Created: the contra account suggested for a kreditor, with VAT code, text and the reason it is suggested.
//                Not tied to the pool page: contraSuggestionLookup.php serves it, so the bilag app can show the same suggestion.
//                The sources are tried in order; a fixed "Standardmodkonto" (release plan 2.2) goes in front of the history when it is built.
//                A closed account (kontoplan.lukket) is not suggested; poolAccountInfo() only left out missing accounts and headings.
// 20261026 CL/LH The suggestion is the account most of the last 5 bilag went to (was: the newest account and its run); the other
//                accounts come along as "alternatives" with their counts, and "total" says how many bilag were looked at.
//                "Udfyld modkonto automatisk" is on by default; off is stored as modk_off (was: on stored as modk_auto).
//                The history is read with up to 100 rows, so the 5 bilag are 5 bilag also when some are spread over several lines;
//                a bilag is its journal, number and date. Every alternative brings its own reason.

include_once(__DIR__ . '/journalHistory.php');
include_once(__DIR__ . '/../../includes/docsIncludes/poolAccountInfo.php');

if (!function_exists('contraSuggestionRank')) {
	/**
	 * The finance accounts of the newest $maxBilag bilag, the one most bilag went to first; a tie goes to the newest.
	 * A bilag (journal, number and date) spread over several lines ("Fordeling") counts once per account.
	 *
	 * @param array $rows     sidste_5_forslag()'s rows, newest first.
	 * @param int   $maxBilag The bilag to look at.
	 * @return array{accounts: array<array{kontonr: string, tekst: string, count: int}>, total: int}
	 *         total is the number of bilag looked at, also those that went to a debitor or kreditor.
	 */
	function contraSuggestionRank(array $rows, $maxBilag = 5) {
		$accounts = array();
		$seen = array();
		$all = array();
		foreach ($rows as $row) {
			$key = (string)($row['kladde_id'] ?? '') . '|' . (string)($row['bilag'] ?? '') . '|' . (string)($row['dato'] ?? '');
			// The rows come by date, not grouped by bilag: an older bilag is skipped, a chosen bilag's later rows still count
			if (!isset($all[$key]) && count($all) >= (int)$maxBilag) continue;
			$all[$key] = true;
			if (strtoupper((string)($row['art'] ?? 'F')) !== 'F' || !(int)($row['kontonr'] ?? 0)) continue;
			$kontonr = (string)(int)$row['kontonr'];
			if (!isset($accounts[$kontonr])) {
				$accounts[$kontonr] = array('kontonr' => $kontonr, 'tekst' => (string)($row['tekst'] ?? ''), 'count' => 0, 'order' => count($accounts));
			}
			if (empty($seen[$kontonr][$key])) {
				$seen[$kontonr][$key] = true;
				$accounts[$kontonr]['count']++;
			}
		}
		$accounts = array_values($accounts);
		usort($accounts, function ($a, $b) {
			return $b['count'] - $a['count'] ?: $a['order'] - $b['order'];
		});
		foreach ($accounts as &$account) unset($account['order']);
		unset($account);
		return array('accounts' => $accounts, 'total' => count($all));
	}
}

if (!function_exists('contraSuggestionPick')) {
	/**
	 * The account to suggest from "sidste 5 posteringer": the one most of the newest bilag went to (contraSuggestionRank()).
	 *
	 * @param array $rows sidste_5_forslag()'s rows, newest first.
	 * @return array{kontonr: string, tekst: string, count: int}|null Null when no finance account was used.
	 */
	function contraSuggestionPick(array $rows) {
		$rank = contraSuggestionRank($rows);
		return $rank['accounts'][0] ?? null;
	}
}

if (!function_exists('contraSuggestionReason')) {
	/**
	 * "Foreslået fordi …" for a suggestion from history.
	 *
	 * @param int      $count How many of the bilag went to the account.
	 * @param int|null $total How many bilag were looked at; null or $count when they all went there.
	 * @return string
	 */
	function contraSuggestionReason($count, $kontonr, $sprog_id, $total = null) {
		if ($total !== null && (int)$total > (int)$count) {
			$text = findtekst('7663|Foreslået fordi $antal af de sidste $total bilag fra denne kreditor blev bogført på konto $konto', $sprog_id);
		} elseif ((int)$count > 1) {
			$text = findtekst('5372|Foreslået fordi de sidste $antal bilag fra denne kreditor blev bogført på konto $konto', $sprog_id);
		} else {
			$text = findtekst('5373|Foreslået fordi det seneste bilag fra denne kreditor blev bogført på konto $konto', $sprog_id);
		}
		return str_replace(array('$antal', '$total', '$konto'), array((string)(int)$count, (string)(int)$total, (string)$kontonr), $text);
	}
}

if (!function_exists('contraSuggestionFromHistory')) {
	/**
	 * The accounts the kreditor's newest bilag went to: posted entries first, then journal lines (sidste_5_forslag()).
	 * The one most bilag went to is the suggestion; the others come along as its alternatives.
	 *
	 * @return array|null See contraSuggestion().
	 */
	function contraSuggestionFromHistory($kreditor, $kladdeId, $regnaar, $sprog_id) {
		// More rows than bilag: a bilag spread over several lines must not push the older ones out
		$history = sidste_5_forslag($kreditor, 'K', 'D', $kladdeId, 'UTF-8', $sprog_id, 100);
		$rank = contraSuggestionRank($history['rows']);
		$found = array();
		foreach ($rank['accounts'] as $pick) {
			// An account that is closed or missing in this fiscal year is not suggested
			$info = poolAccountInfo('F', $pick['kontonr'], $regnaar);
			if ($info['name'] === '') continue;
			// Closed (kontoplan.lukket set) counts as the journal counts it: its save refuses the account
			$closed = db_fetch_array(db_select("select lukket from kontoplan where kontonr = '" . db_escape_string($pick['kontonr']) . "' and regnskabsaar = '" . (int)$regnaar . "'", __FILE__ . " linje " . __LINE__));
			if ($closed && $closed['lukket']) continue;
			$found[] = array(
				'account' => $pick['kontonr'],
				'type' => 'F',
				'name' => $info['name'],
				'moms' => $info['moms'],
				'text' => $pick['tekst'],
				'count' => $pick['count'],
				'reason' => contraSuggestionReason($pick['count'], $pick['kontonr'], $sprog_id, $rank['total']),
			);
		}
		if (!$found) return null;
		$suggestion = array_shift($found);
		$suggestion['total'] = $rank['total'];
		$suggestion['source'] = 'history';
		$suggestion['alternatives'] = $found;
		return $suggestion;
	}
}

if (!function_exists('contraSuggestionSources')) {
	/**
	 * The sources in the order they are asked. Each takes ($kreditor, $kladdeId, $regnaar, $sprog_id) and returns a suggestion or null.
	 *
	 * @return callable[]
	 */
	function contraSuggestionSources() {
		return array('contraSuggestionFromHistory');
	}
}

if (!function_exists('contraSuggestion')) {
	/**
	 * The contra account to suggest for kreditor $kreditor. No history gives no suggestion.
	 *
	 * @param string|int $kreditor Kreditor account number.
	 * @param int        $kladdeId The journal being worked in (its own lines are not history).
	 * @return array{account: string, type: string, name: string, moms: string, text: string, count: int, total: int, reason: string, source: string,
	 *         alternatives: array<array{account: string, type: string, name: string, moms: string, text: string, count: int, reason: string}>}|null
	 */
	function contraSuggestion($kreditor, $kladdeId, $regnaar, $sprog_id) {
		$kreditor = trim((string)$kreditor);
		if (!ctype_digit($kreditor)) return null;
		foreach (contraSuggestionSources() as $source) {
			$suggestion = call_user_func($source, $kreditor, (int)$kladdeId, (int)$regnaar, $sprog_id);
			if ($suggestion) return $suggestion;
		}
		return null;
	}
}

if (!function_exists('contraSuggestionUserAuto')) {
	/**
	 * The user's "Udfyld modkonto automatisk" from the journal's gear box (grupper art 'KASKL', box3). On by default; stored as modk_off when off.
	 *
	 * @param int|null $userId
	 * @return bool
	 */
	function contraSuggestionUserAuto($userId) {
		if ($userId === null || (int)$userId === 0) return true;
		$r = db_fetch_array(db_select("select box3 from grupper where art = 'KASKL' and kode = '1' and kodenr = '" . (int)$userId . "'", __FILE__ . " linje " . __LINE__));
		if (!$r) return true;
		return !in_array('modk_off', array_map('trim', explode(',', (string)$r['box3'])), true);
	}
}
