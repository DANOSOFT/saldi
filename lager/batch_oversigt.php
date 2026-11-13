<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/batch_oversigt.php --- ver 5.0.0 --- 2026-10-20 ---
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
// 20261008 Sawaneh Settings 4e: the old top-menu ($menu=='T') branches removed; the else legs stay (spec §7.6 R3).
// 20261008 Sawaneh The page on the new page head (page_bar) instead of the blue bar; the print layout untouched.
// Batch overview for a given item. Shows all batches with expiry info.
// 20261011 CL/ASR Link to batch_udfyld.php and count of batches missing batch number/expiry date.

@session_start();
$s_id = session_id();
$css = "../css/standard.css";
$modulnr = 12;

global $menu, $bruger_id, $buttonStyle, $topStyle;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

$title = findtekst('5004|Batch oversigt', $sprog_id);

if (isset($_GET['returside']) && $_GET['returside']) $returside = $_GET['returside'];
elseif ($popup) $returside = "../includes/luk.php";
else $returside = "lagerstatus.php";

$vare_id = intval($_GET['vare_id']);
$query = db_select("select * from varer where id=$vare_id", __FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$varenr = $row['varenr'];
$beskrivelse = $row['beskrivelse'];
$warning_days = get_due_date_warning_days($bruger_id);

if ($menu == 'S') {
	include_once("../includes/stdFunc/pageBar.php");
	page_bar(array('title' => $title, 'back' => $returside));
	print "<table width='100%' cellspacing='2'><tbody>";
	print "<tr><td colspan='8'>";
} else {
	print "<table width='100%' cellspacing='2'><tbody>";
	print "<tr><td colspan='8'>";
	print "<table width='100%' cellspacing='2'><tbody>";
	print "<td width='10%' $top_bund><a href='$returside' accesskey='L'>Luk</a></td>";
	print "<td width='80%' $top_bund>$title</td>";
	print "<td width='10%' $top_bund><br></td>";
	print "</tbody></table>";
}

print "<tr><td><br></td></tr>";
print "<tr><td colspan='8'><b>" . htmlentities($varenr) . " : " . htmlentities($beskrivelse) . "</b></td></tr>";
$self = "batch_oversigt.php?vare_id=$vare_id";
if (isset($_GET['returside']) && $_GET['returside']) $self .= "&returside=" . urlencode($_GET['returside']);
$udfyld = "batch_udfyld.php?vare_id=$vare_id&returside=" . urlencode($self);
$mangler = batch_missing_info_count($vare_id);
print "<tr><td colspan='8'>";
if ($mangler) print "<span style='color:#b45309'>$mangler " . batch_txt('missing') . "</span> &nbsp; ";
print "<a href='$udfyld'><button type='button' class='LightButton'>" . batch_txt('edit') . "</button></a>";
print "</td></tr>";
print "<tr><td><br></td></tr>";

// Column headers
print "<tr>";
print "<td><b>Batch ID</b></td>";
print "<td><b>" . findtekst('5005|Batchnr.', $sprog_id) . "</b></td>";
print "<td><b>" . findtekst('5001|Udl&oslash;bsdato', $sprog_id) . "</b></td>";
print "<td align='right'><b>" . findtekst('5007|Dage til udl&oslash;b', $sprog_id) . "</b></td>";
print "<td align='right'><b>" . findtekst('5008|Restlager', $sprog_id) . "</b></td>";
print "<td><b>" . findtekst('5009|K&oslash;bsdato', $sprog_id) . "</b></td>";
print "<td><b>" . findtekst('5010|Lager', $sprog_id) . "</b></td>";
print "<td><b>" . findtekst('5011|K&oslash;bsordre', $sprog_id) . "</b></td>";
print "</tr>";
print "<tr><td colspan='8'><hr></td></tr>";

// Fetch warehouse names
$lagernavn = array();
$q_lg = db_select("select kodenr, beskrivelse from grupper where art='LG' order by kodenr", __FILE__ . " linje " . __LINE__);
while ($r_lg = db_fetch_array($q_lg)) {
	$lagernavn[$r_lg['kodenr']] = $r_lg['beskrivelse'];
}
if (!isset($lagernavn[1])) $lagernavn[1] = findtekst('5012|Hovedlager', $sprog_id);

// Fetch all batches ordered by FEFO
$qtxt = "SELECT * FROM batch_kob WHERE vare_id = $vare_id AND rest > 0 ORDER BY " . fefo_order_clause();
$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
$total_rest = 0;
while ($row = db_fetch_array($query)) {
	$due_date = $row['due_date'];
	$batch_no = $row['batch_no'];
	$days = days_until_expiry($due_date);
	$status = batch_expiry_status($due_date, $warning_days);
	$rest = $row['rest'];
	$total_rest += $rest;

	// Color coding
	$bg = '';
	if ($status == 'expired') $bg = "style='background-color:#ffcccc;'";
	elseif ($status == 'warning') $bg = "style='background-color:#ffffcc;'";
	elseif ($status == 'ok') $bg = "style='background-color:#ccffcc;'";

	// Order info
	$ordrenr = '';
	if ($row['ordre_id']) {
		$r_o = db_fetch_array(db_select("select ordrenr from ordrer where id=" . intval($row['ordre_id']), __FILE__ . " linje " . __LINE__));
		if ($r_o) $ordrenr = $r_o['ordrenr'];
	}

	$lager_nr = $row['lager'] ? $row['lager'] : 1;
	$lager_name = isset($lagernavn[$lager_nr]) ? $lagernavn[$lager_nr] : $lager_nr;

	print "<tr $bg>";
	print "<td>" . $row['id'] . "</td>";
	print "<td>" . htmlentities($batch_no) . "</td>";
	print "<td>" . ($due_date ? dkdato($due_date) : '-') . "</td>";
	print "<td align='right'>" . ($days !== null ? $days : '-') . "</td>";
	print "<td align='right'>" . dkdecimal($rest) . "</td>";
	print "<td>" . ($row['kobsdate'] ? dkdato($row['kobsdate']) : '-') . "</td>";
	print "<td>" . htmlentities($lager_name) . "</td>";
	print "<td>" . htmlentities($ordrenr) . "</td>";
	print "</tr>";
}

print "<tr><td colspan='8'><hr></td></tr>";
print "<tr><td colspan='4'><b>" . findtekst('5013|Total restlager', $sprog_id) . "</b></td><td align='right'><b>" . dkdecimal($total_rest) . "</b></td><td colspan='3'></td></tr>";

print "</tbody></table>";

?>
</body></html>
