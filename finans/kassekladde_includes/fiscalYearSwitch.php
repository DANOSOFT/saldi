<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/fiscalYearSwitch.php --- ver 5.0.0 --- 2026.10.12 ---
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
// Copyright (c) 2003-2026 saldi.dk ApS
// ----------------------------------------------------------------------
// 20261012 CL/SZ SD-730 Switch the active fiscal year from the journal header, as the dashboard does, and come back to the same journal.

/**
 * Switch the active fiscal year when the journal is opened with ?aktiver_regnaar=<kodenr>, then
 * redirect to the same journal. Only open years (active_fiscal_years()) are accepted. Must run
 * before any output, with the tenant database selected (right after online.php).
 */
function fiscalYearSwitchHandle(array $get, $regnaar, $brugernavn, $bruger_id, $revisor, $db_id, $s_id)
{
	$year = (int)ifset($get, 'aktiver_regnaar', 0);
	if (!$year) {
		return;
	}
	$kladde_id = (int)ifset($get, 'kladde_id', 0);
	$back = 'kassekladde.php' . ($kladde_id ? "?kladde_id=$kladde_id&tjek=$kladde_id" : '');
	$open = array_map(function ($r) { return (int)$r['kodenr']; }, active_fiscal_years());
	if ($year != (int)$regnaar && in_array($year, $open, true)) {
		if (!$revisor) {
			db_modify("update brugere set regnskabsaar = '$year' where id = '" . (int)$bruger_id . "'", __FILE__ . " linje " . __LINE__);
		}
		include __DIR__ . '/../../includes/connect.php';
		db_modify("update online set regnskabsaar = '$year' where session_id = '" . db_escape_string($s_id) . "'", __FILE__ . " linje " . __LINE__);
		if ($revisor) {
			$qtxt = "update revisor set regnskabsaar = '$year' where brugernavn = '" . db_escape_string($brugernavn) . "' and db_id = '" . (int)$db_id . "'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	}
	header('Location: ' . $back, true, 303);
	exit;
}

/**
 * The "Regnskabsår" cell for the journal header: a select with the open years on a journal that can
 * still be edited, nothing on a posted one. A closed active year is shown, disabled, as "Lukket".
 * The select has no name, so a save never posts it; unsaved lines get the usual "not saved" prompt.
 */
function fiscalYearSwitchCell($regnaar, $kladde_id, $bogfort, $sprog_id)
{
	if ($bogfort && $bogfort != '-') {
		return '';
	}
	$regnaar = (int)$regnaar;
	$options = '';
	$found = false;
	foreach (active_fiscal_years() as $r) {
		$selected = ((int)$r['kodenr'] == $regnaar) ? ' selected' : '';
		if ($selected) $found = true;
		$options .= "<option value='" . (int)$r['kodenr'] . "'$selected>" . htmlspecialchars($r['beskrivelse'], ENT_QUOTES) . "</option>";
	}
	if (!$found) {
		$r = db_fetch_array(db_select("select beskrivelse from grupper where art = 'RA' and kodenr = '$regnaar'", __FILE__ . " linje " . __LINE__));
		$name = $r ? $r['beskrivelse'] : $regnaar;
		$options = "<option value='$regnaar' selected disabled>" . htmlspecialchars($name, ENT_QUOTES) . " (" . findtekst('387|Lukket', $sprog_id) . ")</option>" . $options;
	}
	$kladde_id = (int)$kladde_id;
	$cell = "<td style='padding-left: 10px; white-space: nowrap; vertical-align: middle;'><b>" . findtekst('778|Regnskabsår', $sprog_id) . ":</b> ";
	$cell .= "<select id='kkRegnaar' class='inputbox' style='vertical-align: middle;' onchange='kkSwitchYear(this)'>$options</select></td>";
	$cell .= "<script>
	function kkSwitchYear(s) {
		if (typeof docChange !== 'undefined' && docChange && !confirm(bilagUnsavedTxt)) { s.value = '$regnaar'; return; }
		document.location = 'kassekladde.php?aktiver_regnaar=' + encodeURIComponent(s.value) + '&kladde_id=$kladde_id';
	}
</script>";
	return $cell;
}
