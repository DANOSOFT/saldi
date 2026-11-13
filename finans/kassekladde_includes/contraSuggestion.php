<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/contraSuggestion.php --- ver 5.1.0 --- 2026-11-10 ---
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
// 20261026 CL/LH The suggestion is the account most of the last 5 bilag went to (was: the newest account and its run); the other accounts come along as "alternatives" with their counts, and "total" says how many bilag were looked at.
//                "Udfyld modkonto automatisk" is on by default; off is stored as modk_off (was: on stored as modk_auto).
//                The history is read with up to 100 rows, so the 5 bilag are 5 bilag also when some are spread over several lines; a bilag is its journal, number and date.
//                Every alternative brings its own reason.
// 20261110 CDX/NTR Document ranking, suggestion payloads and the automatic-fill preference.

include_once(__DIR__ . '/journalHistory.php');
include_once(__DIR__ . '/../../includes/docsIncludes/poolAccountInfo.php');

if (!function_exists('contraSuggestionRank')) {
	/**
	 * Rank finance accounts by the number of distinct bilag in the history window.
	 *
	 * The first $maxBilag distinct journal/number/date combinations form the window.
	 * Later rows of an admitted bilag still count, even after the window is full.
	 * A split bilag counts once per account, so account counts can sum to more than total.
	 * Ties keep first-seen account order; input order supplies recency within each history source.
	 *
	 * @param array<array{kladde_id?: int|string|null, bilag?: int|string|null, dato?: string, kontonr?: int|string|null, art?: string, tekst?: string}> $rows History in priority order; missing art defaults to F.
	 * @param int $maxBilag Maximum distinct bilag to admit; zero or less admits none.
	 * @return array{
	 *   accounts: array<array{
	 *     kontonr: string, Nonzero finance account number, normalized to an integer string.
	 *     tekst: string, Description from the first row for this account.
	 *     count: int, Distinct admitted bilag using this account.
	 *   }>, Accounts sorted by count descending, then first appearance.
	 *   total: int, Distinct admitted bilag, including those using only customer or supplier accounts.
	 * }
	 * @see sidste_5_forslag()
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
	 * Pick the highest-ranked finance account from the first five distinct history bilag.
	 *
	 * @param array<array{kladde_id?: int|string|null, bilag?: int|string|null, dato?: string, kontonr?: int|string|null, art?: string, tekst?: string}> $rows History in the order returned by sidste_5_forslag().
	 * @return array{
	 *   kontonr: string, Selected finance account number.
	 *   tekst: string, Its first history description.
	 *   count: int, Distinct bilag using the account.
	 * }|null Null when the window contains no nonzero finance account.
	 * @see contraSuggestionRank()
	 */
	function contraSuggestionPick(array $rows) {
		$rank = contraSuggestionRank($rows);
		return $rank['accounts'][0] ?? null;
	}
}

if (!function_exists('contraSuggestionReason')) {
	/**
	 * Translate the reason for a history suggestion and substitute its counts and account.
	 *
	 * @param int $count Distinct bilag using the suggested account.
	 * @param string|int $kontonr Suggested finance account number.
	 * @param int $sprog_id Language ID passed to findtekst().
	 * @param int|null $total Bilag in the window; a larger total selects the "N of M" text, otherwise the single/all text is used.
	 * @return string Localized reason with $antal, $total and $konto replaced.
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
	 * Build a suggestion and ranked alternatives from a supplier's history.
	 *
	 * Reads up to 100 history rows, posted entries before journal lines, and ranks the
	 * first five distinct bilag. Missing accounts, headings and closed accounts in the
	 * selected fiscal year are omitted; total still includes all bilag in the window.
	 *
	 * @param string|int $kreditor Supplier account number.
	 * @param int $kladdeId Current journal, excluded from unposted history.
	 * @param int $regnaar Fiscal year used to resolve account names, VAT codes and availability.
	 * @param int $sprog_id Language ID for the reason texts.
	 * @return array{
	 *   account: string, Highest-ranked available finance account number.
	 *   type: string, Always F for history suggestions.
	 *   name: string, Account name for the selected fiscal year.
	 *   moms: string, Account VAT code, or an empty string.
	 *   text: string, First history description for the account.
	 *   count: int, Distinct bilag using the suggested account.
	 *   total: int, Distinct bilag in the history window before account filtering.
	 *   reason: string, Localized explanation of the suggestion.
	 *   source: string, Always history for this source.
	 *   alternatives: array<array{
	 *     account: string, Alternative finance account number.
	 *     type: string, Always F.
	 *     name: string, Account name for the selected fiscal year.
	 *     moms: string, Account VAT code, or an empty string.
	 *     text: string, First history description for this account.
	 *     count: int, Distinct bilag using this account.
	 *     reason: string, Localized explanation for this alternative.
	 *   }>, Remaining available accounts in rank order; may be empty.
	 * }|null Null when no ranked account is available in the fiscal year.
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
	 * Return the first available contra-account suggestion from the configured sources.
	 *
	 * @param string|int $kreditor Kreditor account number.
	 * @param int        $kladdeId The journal being worked in (its own lines are not history).
	 * @param int $regnaar Fiscal year used to check account availability.
	 * @param int $sprog_id Language ID for the reason texts.
	 * @return array{
	 *   account: string, Suggested finance account number.
	 *   type: string, Suggested account type (F for history).
	 *   name: string, Display name of the suggested account.
	 *   moms: string, Account VAT code, or an empty string.
	 *   text: string, History description for the account.
	 *   count: int, Distinct bilag supporting this account.
	 *   total: int, Distinct bilag examined by the source.
	 *   reason: string, Localized explanation for the suggestion.
	 *   source: string, Source identifier, currently history.
	 *   alternatives: array<array{
	 *     account: string, Alternative finance account number.
	 *     type: string, Alternative account type.
	 *     name: string, Alternative display name.
	 *     moms: string, Alternative VAT code, or an empty string.
	 *     text: string, Alternative history description.
	 *     count: int, Distinct bilag supporting the alternative.
	 *     reason: string, Localized explanation for the alternative.
	 *   }>, Remaining candidates in rank order.
	 * }|null Null for a nonnumeric supplier number or when every source returns no suggestion.
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
	 * Read the user's automatic contra-account fill preference from KASKL box3.
	 *
	 * Only the modk_off token disables it; the legacy modk_auto token is ignored.
	 * This preference alone does not authorize filling saved rows or single-bilag suggestions.
	 *
	 * @param int|null $userId User ID; null or zero uses the enabled default without a query.
	 * @return bool True unless the user's settings explicitly contain modk_off; missing settings default to true.
	 */
	function contraSuggestionUserAuto($userId) {
		if ($userId === null || (int)$userId === 0) return true;
		$r = db_fetch_array(db_select("select box3 from grupper where art = 'KASKL' and kode = '1' and kodenr = '" . (int)$userId . "'", __FILE__ . " linje " . __LINE__));
		if (!$r) return true;
		return !in_array('modk_off', array_map('trim', explode(',', (string)$r['box3'])), true);
	}
}
