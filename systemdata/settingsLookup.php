<?php
// ---- systemdata/settingsLookup.php --- lap 5.0.0 --- 2026.09.29 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20260929 Sawaneh Settings redesign phase 4a (spec §8.1): type-ahead for the account and item
//                  lookup fields of a generated settings section. Read-only, JSON.
// 20261004 Sawaneh Open to users with read on any settings group (no longer the Indstillinger bit); nothing for others.
// 20261019 Sawaneh Review 2 (PR #803): a type answers only users who can read a section with a field of that type
//                  (the search term stays UTF-8: db_escape_string() hands every query to the database as UTF-8).

ob_start();
@session_start();
$s_id = session_id();
$title = "settingsLookup";
$webservice = true;
$modulnr = 0;
$permission_key = 'any';
$permission_post_read = true;

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/settingsRegistry.php");

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$type = isset($_GET['type']) ? (string) $_GET['type'] : '';
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$results = array();
$charset = (isset($db_encode) && $db_encode !== 'UTF8') ? 'ISO-8859-1' : 'UTF-8';

/**
 * Whether the user can read at least one settings section that has a field of this lookup type.
 */
function settings_lookup_allowed(string $type): bool
{
	if (!in_array($type, array('account', 'creditor', 'item'), true)) {
		return false;
	}
	$groups = getSettingsGroups();
	$sections = getSettingsSections();
	$canRead = function (string $group, string $sectionId) use ($groups, $sections): bool {
		if (isset($sections[$sectionId]['permission'])) {
			$perm = (string) $sections[$sectionId]['permission'];
		} elseif (isset($groups[$group])) {
			$perm = (string) $groups[$group]['permission'];
		} else {
			return false;
		}
		return !function_exists('perm_can') || perm_can($perm, 'read');
	};
	$hasType = function ($node) use (&$hasType, $type): bool {
		if (!is_array($node)) {
			return false;
		}
		if (isset($node['type']) && $node['type'] === $type) {
			return true;
		}
		foreach ($node as $child) {
			if (is_array($child) && $hasType($child)) {
				return true;
			}
		}
		return false;
	};
	foreach (getSettingDefinitions() as $def) {
		if (isset($def['type'], $def['group'], $def['section']) && $def['type'] === $type && $canRead((string) $def['group'], $def['group'] . '.' . $def['section'])) {
			if (empty($def['permission']) || !function_exists('perm_can') || perm_can((string) $def['permission'], 'read')) {
				return true;
			}
		}
	}
	foreach ($sections as $sectionId => $section) {
		if (isset($section['group'], $section['tables']) && $hasType($section['tables']) && $canRead((string) $section['group'], (string) $sectionId)) {
			return true;
		}
	}
	return false;
}

if ($q !== '' && mb_strlen($q) <= 60 && settings_lookup_allowed($type)) {
	$term = mb_strtolower($q, 'UTF-8');
	$like = db_escape_string(str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $term));
	if ($type === 'account') {
		$qtxt = "select kontonr, beskrivelse from kontoplan where regnskabsaar = '" . (int) $regnaar . "' and kontotype in ('D', 'S') ";
		$qtxt .= "and (" . db_cast('kontonr', 'text') . " like '$like%' or lower(beskrivelse) like '%$like%') order by kontonr limit 12";
		$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($query)) {
			$results[] = array('value' => (string) $r['kontonr'], 'name' => mb_convert_encoding((string) $r['beskrivelse'], 'UTF-8', $charset));
		}
	} elseif ($type === 'creditor') {
		// 20261003 Sawaneh G3.4: the debt collection lawyer is a creditor
		$qtxt = "select kontonr, firmanavn from adresser where art = 'K' and (lukket is null or lukket != 'on') ";
		$qtxt .= "and (" . db_cast('kontonr', 'text') . " like '$like%' or lower(firmanavn) like '%$like%') order by kontonr limit 12";
		$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($query)) {
			$results[] = array('value' => (string) $r['kontonr'], 'name' => mb_convert_encoding((string) $r['firmanavn'], 'UTF-8', $charset));
		}
	} elseif ($type === 'item') {
		$qtxt = "select varenr, beskrivelse from varer where (lower(varenr) like '$like%' or lower(beskrivelse) like '%$like%') ";
		$qtxt .= "and (lukket is null or lukket != 'on') order by varenr limit 12";
		$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($query)) {
			$results[] = array('value' => mb_convert_encoding((string) $r['varenr'], 'UTF-8', $charset), 'name' => mb_convert_encoding((string) $r['beskrivelse'], 'UTF-8', $charset));
		}
	}
}

echo json_encode(array('results' => $results));
exit;
