<?php
// ----------------systemdata/settingsSearch.php --- Settings search Phase 1 --- 2026-07-09 ----
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260709 SZ Created: JSON lookup endpoint backing the Settings search box
// 20260710 SZ Added 3-tier label/keyword/word-fallback matching + Norwegian label support
// JSON lookup endpoint backing the Settings search box (see settingsRegistry.php).

ob_start();

@session_start();
$s_id = session_id();
$title = "settingsSearch";
$webservice = true;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("settingsRegistry.php");

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

if (!isset($bruger_id) || !$bruger_id) {
	echo json_encode(array('error' => 'Session expired'));
	exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

function settingsEntryIsVisible($entry) {
	global $revisorregnskab, $forhandlerregnskab;

	if (!empty($entry['requiresReseller']) && !($revisorregnskab || $forhandlerregnskab)) {
		return false;
	}

	if (!empty($entry['visibilityRule'])) {
		switch ($entry['visibilityRule']) {
			case 'posModule':
				if (!file_exists("../debitor/pos_ordre.php")) return false;
				break;
			case 'docubizz':
				$q = db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box6='on'", __FILE__ . " linje " . __LINE__);
				if (!db_fetch_array($q)) return false;
				break;
		}
	}

	return true;
}

function settingsEntryLabel($entry, $sprog_id) {
	if (isset($entry['textId'])) {
		return findtekst($entry['textId'], $sprog_id);
	}
	if ($sprog_id == 3 && !empty($entry['labelNo'])) {
		return $entry['labelNo'];
	}
	if ($sprog_id == 2 && !empty($entry['labelEn'])) {
		return $entry['labelEn'];
	}
	// Norwegian without a labelNo draft falls back to English rather than Danish -
	// it's an honest "no Norwegian copy yet" rather than silently showing Danish text.
	if ($sprog_id == 3 && !empty($entry['labelEn'])) {
		return $entry['labelEn'];
	}
	return $entry['labelDa'];
}

// Matches a search term against an entry in three passes, from strongest to weakest:
//   1. the whole query appears in the label itself (best match)
//   2. the whole query appears inside a single keyword (e.g. "order layout" -> Formularer)
//   3. every individual word of the query appears somewhere across the label + keywords,
//      even if scattered across different keywords (e.g. "auditor access" -> Brugere, where
//      "auditor" and "access" come from two different keyword entries)
// Returns null when nothing matches at all.
function settingsEntryMatch($entry, $label, $search) {
	$search = trim($search);
	if ($search === '') {
		return array('type' => 'label', 'matchedTerm' => null);
	}

	$search_lc = mb_strtolower($search);
	$label_lc = mb_strtolower($label);
	$keywords = !empty($entry['keywords']) ? $entry['keywords'] : array();

	if (mb_strpos($label_lc, $search_lc) !== false) {
		return array('type' => 'label', 'matchedTerm' => null);
	}

	foreach ($keywords as $keyword) {
		if (mb_strpos(mb_strtolower($keyword), $search_lc) !== false) {
			return array('type' => 'keyword', 'matchedTerm' => $keyword);
		}
	}

	$words = preg_split('/\s+/', $search_lc, -1, PREG_SPLIT_NO_EMPTY);
	if (count($words) > 1) {
		$haystack = $label_lc;
		foreach ($keywords as $keyword) {
			$haystack .= ' | ' . mb_strtolower($keyword);
		}

		foreach ($words as $word) {
			if (mb_strpos($haystack, $word) === false) {
				return null;
			}
		}

		$matchedKeyword = null;
		foreach ($keywords as $keyword) {
			$keyword_lc = mb_strtolower($keyword);
			foreach ($words as $word) {
				if (mb_strpos($keyword_lc, $word) !== false) {
					$matchedKeyword = $keyword;
					break 2;
				}
			}
		}

		return array('type' => 'keyword', 'matchedTerm' => $matchedKeyword);
	}

	return null;
}

$label_matches = array();
$keyword_matches = array();

foreach (getSettingsRegistry() as $entry) {
	if (!settingsEntryIsVisible($entry)) {
		continue;
	}

	$label = settingsEntryLabel($entry, $sprog_id);
	$match = settingsEntryMatch($entry, $label, $search);
	if ($match === null) {
		continue;
	}

	$result = array(
		'key' => $entry['key'],
		'url' => $entry['url'],
		'label' => $label,
		'category' => $entry['category'],
		'matchType' => $match['type'],
		'matchedTerm' => $match['matchedTerm'],
	);

	if ($match['type'] === 'label') {
		$label_matches[] = $result;
	} else {
		$keyword_matches[] = $result;
	}
}

$results = array_slice(array_merge($label_matches, $keyword_matches), 0, 20);

echo json_encode(array('results' => $results, 'query' => $search));
exit;
?>
