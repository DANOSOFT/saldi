<?php
// ----------------systemdata/settingsSearch.php --- Settings search Phase 1 --- 2026-11-03 ----
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260709 SZ Created: JSON lookup endpoint backing the Settings search box
// 20260710 SZ Added 3-tier label/keyword/word-fallback matching + Norwegian label support
// 20260916 Sawaneh Declared $permission_key (roles & permissions, phase 3)
// 20260928 Sawaneh Results limited to settings groups the user may open (phase 4).
// 20260930 CL/NTR Added 'masterDb' visibility rule so master-only pages are not offered in other accounts.
// 20261102 CL/ASR A renamed page is found by its old name with the "Tidligere:" tag: the entry's 'renamedFrom', or the 'renamed_from' of
//                 the section it opens under the section's own name (Brugere & roller is Brugere, Ansatte is Personer, owner 2 Nov 2026).
//                 A field of one employee (Adresse 2 · <name>) is no longer listed when only the section's name matched, so "Personer"
//                 finds the page, not every employee's fields (nor for a match in the help text only: "Ekstra felter" listed Slet medarbejder).
// 20261102 CL/ASR Brugere & ansatte is found once (owner, 2 Nov 2026: one item): an entry with 'searchHideIf' (the employee section) is left out
//                 for a user who may open that group (usersRoles.php is shown instead), a page already listed under the same url is not listed
//                 twice, and a page named as its permission group is shown under its hub group (Virksomhed), not "Brugere & ansatte" twice.
// 20261102 CL/ASR Review: an entry's 'searchGroup' names its group (Ekstra felter: Brugere & ansatte, not Organisation), and a field of the
//                 employee section is shown under the hub group the section sits in (Virksomhed / Brugere & ansatte, not Organisation / ...).
// 20261103 CL/ASR QA: search offers only what the hub offers this user and company: a page must be among the entries of
//                 settings_accessible_groups() (settings_entry_available(): hosted, Stripe operator, bank feature, booking licence, and
//                 an entry's own key such as Backup), and a field's section must have its licence module. A section without
//                 'legacy' (Backup) no longer raises PHP warnings that broke the JSON. Labels are entity-decoded; row actions
//                 (add/remove one till, card, person ...) and the Diverse page, which only reloads the hub, are not offered.
// JSON lookup endpoint backing the Settings search box (see settingsRegistry.php).

ob_start();

@session_start();
$s_id = session_id();
$title = "settingsSearch";
$webservice = true;

include("../includes/connect.php");
$permission_key = 'any';
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

/**
 * Whether a registry entry may be offered to the current user.
 *
 * Checks the entry's 'requiresReseller' flag and its 'visibilityRule'
 * ('posModule', 'docubizz' or 'masterDb', see settingsRegistry.php).
 *
 * @param array $entry Registry entry from getSettingsRegistry()
 * @return bool True when the entry should appear in the search results
 */
function settingsEntryIsVisible($entry) {
	global $revisorregnskab, $forhandlerregnskab, $db, $sqdb;

	if (!empty($entry['requiresReseller']) && !($revisorregnskab || $forhandlerregnskab)) {
		return false;
	}

	if (!empty($entry['visibilityRule'])) {
		switch ($entry['visibilityRule']) {
			case 'masterDb':
				// admin_settings.php logs out anyone not in the master database
				if ($db != $sqdb) return false;
				break;
			case 'posModule':
				if (!settings_has_module('pos')) return false;
				break;
			case 'masterDb':
				global $db, $sqdb;
				if (!isset($db) || !isset($sqdb) || $db !== $sqdb) return false;
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

$accessibleGroups = function_exists('settings_accessible_groups') ? settings_accessible_groups() : null;
// The entries the hub would offer this user in this company (available here and permitted), by key.
$allowedKeys = array();
foreach ($accessibleGroups !== null ? $accessibleGroups : array() as $accessible) {
	foreach ($accessible['entries'] as $accessibleEntry) {
		$allowedKeys[(string) $accessibleEntry['key']] = true;
	}
}
foreach (getSettingsRegistry() as $entry) {
	if ((string) $entry['key'] === 'diverse_overview') {
		continue; // the old Diverse address only reloads the hub
	}
	// Phase 4 (spec S3): only pages of groups the user may open; personal settings always.
	$entryGroup = isset($entry['group']) ? $entry['group'] : '';
	if ($accessibleGroups !== null && $entryGroup !== 'personal' && !isset($accessibleGroups[$entryGroup])) {
		continue;
	}
	if (!settingsEntryIsVisible($entry) || (function_exists('settings_entry_available') && !settings_entry_available($entry))) {
		continue;
	}
	if ($accessibleGroups !== null && $entryGroup !== 'personal' && !isset($allowedKeys[(string) $entry['key']])) {
		continue; // e.g. Backup for a role with Import & eksport but not the backup key
	}
	if (!empty($entry['searchHideIf']) && $accessibleGroups !== null && isset($accessibleGroups[$entry['searchHideIf']])) {
		continue; // another entry of the same name is shown for this user (Brugere & ansatte: usersRoles.php, not the employee section)
	}

	$label = settingsEntryLabel($entry, $sprog_id);
	if (isset($entry['textId'])) {
		$entry['keywords'][] = settings_text_all_languages($entry['textId']);
	}
	foreach (array('labelDa', 'labelEn', 'labelNo') as $other) {
		if (!empty($entry[$other])) {
			$entry['keywords'][] = $entry[$other];
		}
	}
	if (isset($entry['section'])) {
		$allSections = getSettingsSections();
		foreach (isset($allSections[$entry['section']]['legacy']) ? $allSections[$entry['section']]['legacy'] : array() as $path) {
			$entry['keywords'][] = trim(settings_legacy_all_languages($path));
		}
	}
	// The page's name before the hub (registry 'renamedFrom', or a renamed section's 'renamed_from'): searched in every language.
	$renamed = null;
	if (isset($entry['renamedFrom'])) {
		$renamed = $entry['renamedFrom'];
	} elseif (isset($entry['section'], $entry['textId'])) {
		$renamedSections = getSettingsSections();
		$renamedSection = isset($renamedSections[$entry['section']]) ? $renamedSections[$entry['section']] : array();
		if (!empty($renamedSection['renamed_from']) && (string) $renamedSection['label'] === (string) $entry['textId']) {
			$renamed = $renamedSection['renamed_from'];
		}
	}
	$renamedAll = '';
	if ($renamed !== null) {
		$renamedAll = is_array($renamed) ? mb_strtolower(implode(' | ', $renamed), 'UTF-8') : settings_text_all_languages($renamed);
		if ($renamedAll !== '') {
			$entry['keywords'][] = $renamedAll;
		}
	}
	$match = settingsEntryMatch($entry, $label, $search);
	if ($match === null) {
		continue;
	}

	// A hit through an old menu name carries the "Tidligere: ..." tag, as single settings do (spec §8.10).
	$legacyTag = '';
	if ($match['type'] !== 'label' && isset($entry['section']) && $search !== '') {
		$legacySections = getSettingsSections();
		foreach (isset($legacySections[$entry['section']]['legacy']) ? $legacySections[$entry['section']]['legacy'] : array() as $path) {
			if (mb_strpos(settings_legacy_all_languages($path), mb_strtolower($search)) !== false) {
				$legacyTag = settings_legacy_text($path, $sprog_id);
				break;
			}
		}
	}
	if ($legacyTag === '' && $match['type'] !== 'label' && $renamedAll !== '' && $search !== '' && mb_strpos($renamedAll, mb_strtolower($search)) !== false) {
		$legacyTag = function_exists('settings_hub_text') ? settings_hub_text($renamed, (int) $sprog_id) : (is_array($renamed) ? $renamed['da'] : findtekst((string) $renamed, $sprog_id));
	}
	$groupLabel = ($entryGroup === 'personal') ? findtekst('5500|Personlige indstillinger', $sprog_id) : (isset($accessibleGroups[$entryGroup]) ? findtekst($accessibleGroups[$entryGroup]['def']['label'], $sprog_id) : '');
	if (($groupLabel !== '' && mb_strtolower($groupLabel) === mb_strtolower($label)) || !empty($entry['searchHideIf'])) {
		// "Brugere & ansatte · Brugere & ansatte" says nothing: the hub group it sits in (Virksomhed) instead.
		$hubLoc = function_exists('settings_hub_locate') ? settings_hub_locate((string) $entry['key']) : null;
		$hubAll = $hubLoc !== null ? getSettingsHub() : array();
		if ($hubLoc !== null && isset($hubAll[$hubLoc['group']])) {
			$groupLabel = settings_hub_text($hubAll[$hubLoc['group']]['label'], (int) $sprog_id);
		}
	}
	if (!empty($entry['searchGroup'])) {
		$groupLabel = findtekst((string) $entry['searchGroup'], $sprog_id); // Ekstra felter: Brugere & ansatte, as its siblings Roller and Audit-log
	}
	$result = array(
		'key' => $entry['key'],
		'url' => $entry['url'],
		'label' => html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
		'legacy' => $legacyTag,
		'category' => $entry['category'],
		'group' => $groupLabel,
		'matchType' => $match['type'],
		'matchedTerm' => $match['matchedTerm'],
	);

	if ($match['type'] === 'label') {
		$label_matches[] = $result;
	} else {
		$keyword_matches[] = $result;
	}
}

// One result per page: a second entry with the same url (an alias found by another word) adds nothing.
$results = array();
$seenUrls = array();
foreach (array_merge($label_matches, $keyword_matches) as $result) {
	if (isset($seenUrls[$result['url']])) {
		continue;
	}
	$seenUrls[$result['url']] = true;
	$results[] = $result;
}
$results = array_slice($results, 0, 20);

// 20260929 Sawaneh Phase 4a (spec §8.5, §8.10): single settings from registry v2, ranked
// exact label > label prefix > label > keyword > old menu name > help text, filtered by the
// user's permission keys. A hit on an old menu name carries the "Tidligere: ..." tag.
$fields = array();
if ($search !== '' && function_exists('getSettingDefinitions')) {
	$needle = mb_strtolower($search);
	$sections = getSettingsSections();
	$groups = getSettingsGroups();
	foreach (getSettingDefinitions() as $key => $def) {
		$personal = ($def['group'] === 'personal');
		if (!$personal && function_exists('perm_can') && !perm_can($def['permission'], 'read')) {
			continue;
		}
		if ($def['visible_if'] && $def['visible_if'][0] === 'module' && !settings_has_module((string) $def['visible_if'][1])) {
			continue;
		}
		$sectionId = $def['group'] . '.' . $def['section'];
		// The section's licence module (PoS, hosted ...), as the section page itself requires.
		if (!empty($sections[$sectionId]['module']) && !settings_has_module((string) $sections[$sectionId]['module'])) {
			continue;
		}
		// Row actions (add or remove one till, card, person ...) have no place to land on; the section is found by its name.
		if ($def['type'] === 'action' && (isset($def['per']) || preg_match('/\.(add|remove)(_|\.|$)/', (string) $key))) {
			continue;
		}
		$label = findtekst((string) $def['label'], $sprog_id);
		if (isset($def['label_suffix'])) {
			$label .= ' · ' . $def['label_suffix']; // 20261003 Sawaneh G6.3: a sender field carries its form language
		}
		$labelLc = mb_strtolower($label);
		// Every part matches in every language Saldi ships: people search for the name they remember.
		$legacy = !empty($def['legacy']) ? settings_legacy_text($def['legacy'], $sprog_id) : '';
		$legacyAll = !empty($def['legacy']) ? settings_legacy_all_languages($def['legacy']) : '';
		$labelAll = settings_text_all_languages($def['label']);
		$helpAll = isset($def['help']) ? settings_text_all_languages($def['help']) : '';
		$sectionAll = isset($sections[$sectionId]) ? settings_text_all_languages($sections[$sectionId]['label']) : '';
		$rank = null;
		$tag = '';
		if ($labelLc === $needle) {
			$rank = 0;
		} elseif (mb_strpos($labelLc, $needle) === 0) {
			$rank = 1;
		} elseif (mb_strpos($labelLc, $needle) !== false) {
			$rank = 2;
		} else {
			foreach ($def['keywords'] as $keyword) {
				if (mb_strpos(mb_strtolower($keyword), $needle) !== false) {
					$rank = 3;
					break;
				}
			}
			if ($rank === null && mb_strpos($labelAll, $needle) !== false) {
				$rank = 2;
			}
			// A field of one employee is not listed for the section's name alone (every employee's fields would follow "Personer").
			$perEmployee = isset($def['per']) && ($def['per'] === 'employee' || $def['per'] === 'employee_user');
			if ($rank === null && !$perEmployee && mb_strpos($sectionAll, $needle) !== false) {
				$rank = 3;
			}
			if ($rank === null && $legacyAll !== '' && mb_strpos($legacyAll, $needle) !== false) {
				$rank = 4;
				$tag = $legacy;
			}
			if ($rank === null && !$perEmployee && $helpAll !== '' && mb_strpos($helpAll, $needle) !== false) {
				$rank = 5;
			}
		}
		if ($rank === null) {
			continue;
		}
		$fieldGroup = $personal ? findtekst('5500|Personlige indstillinger', $sprog_id) : findtekst($groups[$def['group']]['label'], $sprog_id);
		if ($sectionId === 'organisation.employees' && function_exists('settings_hub_locate') && ($hubLoc = settings_hub_locate($sectionId)) !== null) {
			$hubAll = getSettingsHub(); // Brugere & ansatte sits in Virksomhed on the hub; "Organisation" is a name the owner sees nowhere
			if (isset($hubAll[$hubLoc['group']])) {
				$fieldGroup = settings_hub_text($hubAll[$hubLoc['group']]['label'], (int) $sprog_id);
			}
		}
		$fields[] = array(
			'key'      => $key,
			'label'    => html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
			'url'      => $personal ? 'personalSettings.php' : settings_section_url($sectionId, $key),
			'group'    => $fieldGroup,
			'section'  => isset($sections[$sectionId]) ? findtekst((string) $sections[$sectionId]['label'], $sprog_id) : '',
			'legacy'   => $tag,
			'personal' => $personal,
			'rank'     => $rank,
		);
	}
	usort($fields, function ($a, $b) {
		return ($a['rank'] - $b['rank']) ?: strcmp($a['group'] . $a['label'], $b['group'] . $b['label']);
	});
}

echo json_encode(array(
	'results'     => $results,
	'fields'      => $fields,
	'query'       => $search,
	'legacyLabel' => findtekst('5723|Tidligere:', $sprog_id),
	'personalLabel' => findtekst('5742|Personlig', $sprog_id),
));
exit;
?>
