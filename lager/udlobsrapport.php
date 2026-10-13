<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/udlobsrapport.php --- patch 4.2.0 --- 2026-04-16 ---
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
// Expiry report - shows items expiring within X days or already expired.

@session_start();
$s_id = session_id();
$css = "../css/standard.css";
$modulnr = 12;

global $menu;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

$title = findtekst('5014|Udl&oslash;bsrapport', $sprog_id);

if ($popup) $returside = "../includes/luk.php";
else $returside = "lagerstatus.php";

// Filter: days until expiry
$filter_days = isset($_GET['dage']) ? intval($_GET['dage']) : 30;
$show_expired = isset($_GET['udloebet']) ? $_GET['udloebet'] : '';
$export_csv = isset($_GET['csv']) ? $_GET['csv'] : '';

// Header
if ($menu == 'T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">";
	print "<div class=\"headerbtnLft headLink\"><a href='$returside' accesskey='L'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst('30|Tilbage', $sprog_id) . "</a></div>";
	print "<div class=\"headerTxt\">$title</div>";
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu == 'S') {
	print "<table width='100%' cellspacing='2'><tbody>";
	print "<tr><td colspan='9'>";
	print "<table width='100%' cellspacing='2'><tbody>";
	print "<td width='10%'><a href='$returside' accesskey='L'><button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">" . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>";
	print "<td width='80%' align='center' style='$topStyle'>$title</td>";
	print "<td width='10%' align='center' style='$topStyle'><br></td>";
	print "</tbody></table>";
} else {
	print "<table width='100%' cellspacing='2'><tbody>";
	print "<tr><td colspan='9'>";
	print "<table width='100%' cellspacing='2'><tbody>";
	print "<td width='10%' $top_bund><a href='$returside' accesskey='L'>Luk</a></td>";
	print "<td width='80%' $top_bund>$title</td>";
	print "<td width='10%' $top_bund><br></td>";
	print "</tbody></table>";
}

// Filter form
print "<table width='100%'><tbody><tr>";
print "<td>" . findtekst('5015|Udl&oslash;ber inden', $sprog_id) . ": ";
print "<select onchange=\"window.location='udlobsrapport.php?dage='+this.value\">";
$options = array(7, 14, 30, 60, 90, 0);
$labels = array('7 dage', '14 dage', '30 dage', '60 dage', '90 dage', 'Alle');
for ($i = 0; $i < count($options); $i++) {
	$sel = ($filter_days == $options[$i]) ? ' selected' : '';
	print "<option value='$options[$i]'$sel>$labels[$i]</option>";
}
print "</select>";
print " &nbsp; <a href='udlobsrapport.php?udloebet=1'>" . findtekst('5016|Vis kun udl&oslash;bne', $sprog_id) . "</a>";
print " &nbsp; <a href='udlobsrapport.php?dage=$filter_days&csv=1'>" . findtekst('5017|Eksporter CSV', $sprog_id) . "</a>";
print "</td></tr></tbody></table>";

print "<br>";

// Build query
$today = date('Y-m-d');
if ($show_expired) {
	$qtxt = "SELECT bk.*, v.varenr, v.beskrivelse, v.kostpris, v.enhed
	         FROM batch_kob bk
	         JOIN varer v ON bk.vare_id = v.id
	         WHERE bk.due_date IS NOT NULL AND bk.rest > 0 AND bk.due_date < '$today'
	         ORDER BY bk.due_date ASC, bk.kobsdate ASC";
} elseif ($filter_days > 0) {
	$end_date = date('Y-m-d', strtotime("+$filter_days days"));
	$qtxt = "SELECT bk.*, v.varenr, v.beskrivelse, v.kostpris, v.enhed
	         FROM batch_kob bk
	         JOIN varer v ON bk.vare_id = v.id
	         WHERE bk.due_date IS NOT NULL AND bk.rest > 0 AND bk.due_date <= '$end_date'
	         ORDER BY bk.due_date ASC, bk.kobsdate ASC";
} else {
	$qtxt = "SELECT bk.*, v.varenr, v.beskrivelse, v.kostpris, v.enhed
	         FROM batch_kob bk
	         JOIN varer v ON bk.vare_id = v.id
	         WHERE bk.due_date IS NOT NULL AND bk.rest > 0
	         ORDER BY bk.due_date ASC, bk.kobsdate ASC";
}

// Fetch warehouse names
$lagernavn = array();
$q_lg = db_select("select kodenr, beskrivelse from grupper where art='LG' order by kodenr", __FILE__ . " linje " . __LINE__);
while ($r_lg = db_fetch_array($q_lg)) {
	$lagernavn[$r_lg['kodenr']] = $r_lg['beskrivelse'];
}
if (!isset($lagernavn[1])) $lagernavn[1] = findtekst('5012|Hovedlager', $sprog_id);

$warning_days = get_due_date_warning_days($bruger_id);

// CSV export
if ($export_csv) {
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="udlobsrapport_' . date('Y-m-d') . '.csv"');
	$fp = fopen('php://output', 'w');
	fputcsv($fp, array('Varenr', 'Beskrivelse', 'Batchnr', 'Udlobsdato', 'Dage til udlob', 'Restlager', 'Lagervaerdi', 'Lager'), ';');
	$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$days = days_until_expiry($row['due_date']);
		$lager_nr = $row['lager'] ? $row['lager'] : 1;
		$lager_name = isset($lagernavn[$lager_nr]) ? $lagernavn[$lager_nr] : $lager_nr;
		$value = $row['rest'] * $row['kostpris'];
		fputcsv($fp, array(
			$row['varenr'],
			$row['beskrivelse'],
			$row['batch_no'],
			$row['due_date'],
			$days,
			$row['rest'],
			number_format($value, 2, ',', '.'),
			$lager_name
		), ';');
	}
	fclose($fp);
	exit;
}

// Table
print "<table width='100%' cellspacing='2'><tbody>";
print "<tr>";
print "<td><b>" . findtekst('917|Varenr', $sprog_id) . "</b></td>";
print "<td><b>" . findtekst('914|Beskrivelse', $sprog_id) . "</b></td>";
print "<td><b>" . findtekst('5005|Batchnr.', $sprog_id) . "</b></td>";
print "<td><b>" . findtekst('5001|Udl&oslash;bsdato', $sprog_id) . "</b></td>";
print "<td align='right'><b>" . findtekst('5007|Dage til udl&oslash;b', $sprog_id) . "</b></td>";
print "<td align='right'><b>" . findtekst('5008|Restlager', $sprog_id) . "</b></td>";
print "<td align='right'><b>" . findtekst('5018|Lagerv&aelig;rdi', $sprog_id) . "</b></td>";
print "<td><b>" . findtekst('5010|Lager', $sprog_id) . "</b></td>";
print "</tr>";
print "<tr><td colspan='8'><hr></td></tr>";

$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
$total_value = 0;
$row_count = 0;
while ($row = db_fetch_array($query)) {
	$row_count++;
	$due_date = $row['due_date'];
	$days = days_until_expiry($due_date);
	$status = batch_expiry_status($due_date, $warning_days);
	$rest = $row['rest'];
	$value = $rest * $row['kostpris'];
	$total_value += $value;

	$bg = '';
	if ($status == 'expired') $bg = "style='background-color:#ffcccc;'";
	elseif ($status == 'warning') $bg = "style='background-color:#ffffcc;'";
	elseif ($status == 'ok') $bg = "style='background-color:#ccffcc;'";

	$lager_nr = $row['lager'] ? $row['lager'] : 1;
	$lager_name = isset($lagernavn[$lager_nr]) ? $lagernavn[$lager_nr] : $lager_nr;

	print "<tr $bg>";
	print "<td><a href='varekort.php?id=$row[vare_id]'>" . htmlentities($row['varenr']) . "</a></td>";
	print "<td>" . htmlentities($row['beskrivelse']) . "</td>";
	print "<td>" . htmlentities($row['batch_no']) . "</td>";
	print "<td>" . dkdato($due_date) . "</td>";
	print "<td align='right'>" . $days . "</td>";
	print "<td align='right'>" . dkdecimal($rest) . "</td>";
	print "<td align='right'>" . dkdecimal($value, 2) . "</td>";
	print "<td>" . htmlentities($lager_name) . "</td>";
	print "</tr>";
}

print "<tr><td colspan='8'><hr></td></tr>";
print "<tr><td colspan='5'><b>" . findtekst('5019|I alt', $sprog_id) . ": $row_count " . findtekst('5020|linjer', $sprog_id) . "</b></td>";
print "<td colspan='1'></td>";
print "<td align='right'><b>" . dkdecimal($total_value, 2) . "</b></td>";
print "<td></td></tr>";

print "</tbody></table>";

if ($menu == 'T') {
	print "</div>";
}
?>
</body></html>
