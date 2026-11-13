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

include_once(__DIR__ . '/journalHistory.php');
include_once(__DIR__ . '/../../includes/docsIncludes/poolAccountInfo.php');

if (!function_exists('contraSuggestionPick')) {
	/**
	 * The account to suggest from "sidste 5 posteringer": the newest finance account, and how many of the newest bilag in a row went to it.
	 *
	 * @param array $rows sidste_5_forslag()'s rows, newest first.
	 * @return array{kontonr: string, tekst: string, count: int}|null Null when no finance account was used.
	 */
	function contraSuggestionPick(array $rows) {
		$pick = null;
		$bilag = array();
		foreach ($rows as $row) {
			if (strtoupper((string)($row['art'] ?? 'F')) !== 'F' || !(int)($row['kontonr'] ?? 0)) continue;
			$kontonr = (string)(int)$row['kontonr'];
			if ($pick === null) {
				$pick = array('kontonr' => $kontonr, 'tekst' => (string)($row['tekst'] ?? ''), 'count' => 0);
			} elseif ($kontonr !== $pick['kontonr']) {
				break;
			}
			// A bilag spread over several lines ("Fordeling") counts once
			$bilag[(string)($row['bilag'] ?? '') . '|' . (string)($row['dato'] ?? '')] = true;
		}
		if ($pick === null) return null;
		$pick['count'] = count($bilag);
		return $pick;
	}
}

if (!function_exists('contraSuggestionReason')) {
	/**
	 * "Foreslået fordi …" for a suggestion from history.
	 *
	 * @return string
	 */
	function contraSuggestionReason($count, $kontonr, $sprog_id) {
		if ((int)$count > 1) {
			$text = findtekst('5372|Foreslået fordi de sidste $antal bilag fra denne kreditor blev bogført på konto $konto', $sprog_id);
		} else {
			$text = findtekst('5373|Foreslået fordi det seneste bilag fra denne kreditor blev bogført på konto $konto', $sprog_id);
		}
		return str_replace(array('$antal', '$konto'), array((string)(int)$count, (string)$kontonr), $text);
	}
}

if (!function_exists('contraSuggestionFromHistory')) {
	/**
	 * The account the kreditor's bilag went to most recently: posted entries first, then journal lines (sidste_5_forslag()).
	 *
	 * @return array|null See contraSuggestion().
	 */
	function contraSuggestionFromHistory($kreditor, $kladdeId, $regnaar, $sprog_id) {
		$history = sidste_5_forslag($kreditor, 'K', 'D', $kladdeId, 'UTF-8', $sprog_id);
		$pick = contraSuggestionPick($history['rows']);
		if ($pick === null) return null;
		// An account that is closed or missing in this fiscal year is not suggested
		$info = poolAccountInfo('F', $pick['kontonr'], $regnaar);
		if ($info['name'] === '') return null;
		// Closed (kontoplan.lukket set) counts as the journal counts it: its save refuses the account
		$closed = db_fetch_array(db_select("select lukket from kontoplan where kontonr = '" . db_escape_string($pick['kontonr']) . "' and regnskabsaar = '" . (int)$regnaar . "'", __FILE__ . " linje " . __LINE__));
		if ($closed && $closed['lukket']) return null;
		return array(
			'account' => $pick['kontonr'],
			'type' => 'F',
			'name' => $info['name'],
			'moms' => $info['moms'],
			'text' => $pick['tekst'],
			'count' => $pick['count'],
			'reason' => contraSuggestionReason($pick['count'], $pick['kontonr'], $sprog_id),
			'source' => 'history',
		);
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
	 * @return array{account: string, type: string, name: string, moms: string, text: string, count: int, reason: string, source: string}|null
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
	 * The user's "Udfyld modkonto automatisk" from the journal's gear box (grupper art 'KASKL', box3, option modk_auto). Off by default.
	 *
	 * @param int|null $userId
	 * @return bool
	 */
	function contraSuggestionUserAuto($userId) {
		if ($userId === null || (int)$userId === 0) return false;
		$r = db_fetch_array(db_select("select box3 from grupper where art = 'KASKL' and kode = '1' and kodenr = '" . (int)$userId . "'", __FILE__ . " linje " . __LINE__));
		if (!$r) return false;
		return in_array('modk_auto', array_map('trim', explode(',', (string)$r['box3'])), true);
	}
}
