<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//
// --- systemdata/regnskabsaar.php --- ver 5.0.0 --- 2026-01-30 --
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------------
// 20150327 CA  Topmenudesign tilføjet                             søg 20150327
// 20161202 PHR Små designændringer
// 20190221 MSC - Rettet topmenu design
// 20190225 MSC - Rettet topmenu design
// 20210709 LOE - Translated some of the texts
// 20210805 LOE - Updated the title texts
// 20220103 PHR - "Set all" now updates online.php.
// 20220501 PHR - Corrected error in set all.
// 20240524 PHR - Fiscal year can now be deleted.
// 20250503 LOE reordered mix-up text_id from tekster.csv in findtekst()
// 20250903 PHR	Changed 5 year calculation to include months.
// 20260130 PHR - Improved check for empty fiscal year before deletion and used ICONSVG icons.

@session_start();
$s_id = session_id();
$css = "../css/standard.css";
$modulnr = 1;
$title = "Regnskabsaar";
$aktiver = NULL;
$bgcolor = NULL;
$bgcolor1 = NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$aktiver = if_isset($_GET['aktiver']);
$deleteYear = if_isset($_GET['deleteYear']);
$deleteEmptyYear = if_isset($_GET['deleteEmptyYear']);
$set_alle = if_isset($_GET['set_alle']);

if ($set_alle) {
	db_modify("update brugere set regnskabsaar = '$set_alle'", __FILE__ . " linje " . __LINE__);
	include("../includes/connect.php");
	db_modify("update online set regnskabsaar = '$set_alle' where db = '$db'", __FILE__ . " linje " . __LINE__);
	include("../includes/online.php");
}
if ($aktiver) {
	include("../includes/connect.php");
	db_modify("update online set regnskabsaar = '$aktiver' where session_id = '$s_id'", __FILE__ . " linje " . __LINE__);
	if ($revisor) {
		$qtxt = "update revisor set regnskabsaar = '$aktiver' where brugernavn = '$brugernavn' and db_id='$db_id'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	include("../includes/online.php");
	if (!$revisor)
		db_modify("update brugere set regnskabsaar = '$aktiver' where id = '$bruger_id'", __FILE__ . " linje " . __LINE__);
}
if ($deleteYear) {
	print "<script>javascript:document.body.style.cursor = 'wait'</script>";
	include_once("fiscalYearInc/deleteFiscalYear.php");
	deleteFinancialYear($deleteYear);
	print "<script>javascript:document.body.style.cursor = 'default'</script>";
}

// Delete empty fiscal year (no transactions in the fiscal year period)
if ($deleteEmptyYear) {
	$qtxt = "SELECT * FROM grupper WHERE art = 'RA' AND kodenr = '$deleteEmptyYear'";
	$yearData = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));

	if ($yearData) {
		$startDate = $yearData['box2'] . '-' . str_pad($yearData['box1'], 2, '0', STR_PAD_LEFT) . '-01';
		$endMonth = $yearData['box3'];
		$endYear = $yearData['box4'];
		// Get last day of end month
		$lastDay = date('t', strtotime("$endYear-$endMonth-01"));
		$endDate = $endYear . '-' . str_pad($endMonth, 2, '0', STR_PAD_LEFT) . '-' . $lastDay;

		$qtxt = "SELECT id FROM transaktioner WHERE transdate >= '$startDate' AND transdate <= '$endDate' LIMIT 1";
		$transactionData = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		
		if (!db_fetch_array($transactionData)) {
			// First delete all chart of accounts entries for this fiscal year
			$qtxt = "DELETE FROM kontoplan WHERE regnskabsaar = '$deleteEmptyYear'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			
			$qtxt = "DELETE FROM grupper WHERE art = 'RA' AND kodenr = '$deleteEmptyYear'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			
			print "<script>window.location.href = 'regnskabsaar.php';</script>";
			exit;
		}
	}
}

if ($menu == 'T') {  # 20150327 start
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">\n";
	print "<div class=\"headerbtnLft\"></div>\n";
	print "</div><!-- end of header -->";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<table border=\"1\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable2\">";
} else {
	include("top.php");
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\">";
}  # 20150327 stop

#print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" width=\"70%\"><tbody>";
($bgcolor1 != $bgcolor) ? $bgcolor1 = $bgcolor : $bgcolor1 = $bgcolor5;
print "<tbody>";
print "<tr bgcolor='$bgcolor1'>";
print "<td width = 8%><b>ID</b></td>";
print "<td width = 35%><b>" . findtekst('914|Beskrivelse', $sprog_id) . "</a></b></td>"; #20210709
print "<td width = 9%><b>" . findtekst('1208|Start md.', $sprog_id) . "</a></b></td>";
print "<td width = 9%><b>" . findtekst('1209|Start år', $sprog_id) . "</a></b></td>";
print "<td width = 9%><b>" . findtekst('1210|Slut md.', $sprog_id) . "</a></b></td>";
print "<td width = 9%><b>" . findtekst('1211|Slut år', $sprog_id) . "</a></b></td>";
print "<td width = 8%><b><br></a></b></td>";
print "<td width = 8%><b><br></a></b></td>";
print "<td width = 5%><b><br></a></b></td>"; // Delete column
print "<tr>";
print "<td colspan='9'><hr></td>";
print "</tr>";
print "</tr>";
$set_alle = 0;
$q = db_select("select id,regnskabsaar from brugere", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if ($regnaar != $r['regnskabsaar'])
		$set_alle = 1;
}

// $delete_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';

$delete_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';

$trash_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>';

$x = 0;
$deleted = array();
$deleteDate = array();
$isEmpty = array(); // Track if fiscal year has no transactions
$fiscalYears = array();


$query = db_select("SELECT * FROM grupper WHERE art = 'RA' ORDER BY box2, box1", __FILE__ . " linje " . __LINE__);

while($row = db_fetch_array($query)) {
	$fiscalYears[] = $row;
}

$newestEmptyYear  = null;
$emptyYears = array();

// Find newest empty fiscal year
foreach(array_reverse($fiscalYears) as $year){
	$startDate = $year['box2'] . '-' . str_pad($year['box1'], 2, '0', STR_PAD_LEFT) . '-01';
	$endMonth = $year['box3'];
	$endYear = $year['box4'];
	$lastDay = date('t', strtotime("$endYear-$endMonth-01"));
	$endDate = $endYear . '-' . str_pad($endMonth, 2, '0', STR_PAD_LEFT) . '-' . $lastDay;

	$qtxt = "SELECT id from transaktioner WHERE transdate >= '$startDate' AND transdate <= '$endDate' LIMIT 1";
	$transactionData = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	$hasTransactions = db_fetch_array($transactionData);

	if ($hasTransactions) {
		break;
	}else {
		$emptyYears[$year['kodenr']] = true;

		if($newestEmptyYear === null) {
			$newestEmptyYear = $year['kodenr'];
		}
	} 

}
// TODO: Only delete newest empty fiscal year, Once deleted then you can delete the next one.

foreach($fiscalYears as $index => $row){
	$x = $index + 1;

	// Check is this year is marked as empty
	$isEmpty[$x] = isset($emptyYears[$row['kodenr']]);

	$canDeleteThisyear =  ($newestEmptyYear !== null && $row['kodenr'] == $newestEmptyYear );


	// Check if current fiscal year has transactions
	// $startDate = $row['box2'] . '-' . str_pad($row['box1'], 2, '0', STR_PAD_LEFT) . '-01';
	// $endMonth = $row['box3'];
	// $endYear = $row['box4'];
	// $lastDay = date('t', strtotime("$endYear-$endMonth-01"));
	// $endDate = $endYear . '-' . str_pad($endMonth, 2, '0', STR_PAD_LEFT) . '-' . $lastDay;

	// $qtxt = "SELECT id from transaktioner WHERE transdate >= '$startDate' AND transdate <= '$endDate' LIMIT 1";
	// $transactionData = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	// $hasNoTransactions = !db_fetch_array($transactionData);

	// // Check if Following fiscal years haev transactions
	// $followingYearsEmpty = true;
	// for ($i = $index + 1; $i < count($fiscalYears); $i++){
	// 	$nextYear = $fiscalYears[$i];

	// 	$nextStartDate = $nextYear['box2'] . '-' . str_pad($nextYear['box1'], 2, '0', STR_PAD_LEFT) . '-01';
	// 	$nextEndMonth = $nextYear['box3'];
	// 	$nextEndYear = $nextYear['box4'];
	// 	$nextLastDay = date('t', strtotime("$nextEndYear-$nextEndMonth-01"));
	// 	$nextEndDate = $nextEndYear . '-' . str_pad($nextEndMonth, 2, '0', STR_PAD_LEFT) . '-' . $nextLastDay;

	// 	$qtxt = "SELECT id from transaktioner WHERE transdate >= '$nextStartDate' AND transdate <= '$nextEndDate' LIMIT 1";
	// 	$nextTransactionData = db_select($qtxt, __FILE__ . " linje " . __LINE__);

	// 	// $followingYearsEmpty = $followingYearsEmpty && !db_fetch_array($nextTransactionData);

	// 	if(db_fetch_array($nextTransactionData)){
	// 		$followingYearsEmpty = false;
	// 		break;
	// 	}
	// }

	// $isEmpty[$x] = $hasNoTransactions && $followingYearsEmpty;
	
	if ($row['box10'] == '' && $row['box4'] < date('Y')-5) {
		$qtxt = "select id from kontoplan where regnskabsaar = '$x'";
		if (!$r2 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			db_modify("update grupper set box10 = 'on' where id = '$row[id]'", __FILE__ . " linje " . __LINE__);
			$row['box10'] = 'on';
		}
	}
	if ($row['box10'] == 'on') {
		$deleted[$x] = 1;
		$deleteDate[$x] = 'før 2025-07-01';
	} elseif ($row['box10'] > '1') {
		$deleted[$x] = $row['box10'];
		$deleteDate[$x] = date('Y-m-d',$deleted[$x]);
	} else {
		$deleted[$x] = $deleteDate[$x] = '';
	}
	($bgcolor1 != $bgcolor) ? $bgcolor1 = $bgcolor : $bgcolor1 = $bgcolor5;
	print "<tr bgcolor=\"$bgcolor1\">";
	$title = "" . findtekst('1793|Klik her for at redigere/opdatere regnskabsår', $sprog_id) . " $row[kodenr]";  #20210805
	print "<td>";
	if ($row['box10'] == '')
		print "<a href='regnskabskort.php?id=$row[id]' title=\"$title\"> $row[kodenr]</a>";
	else
	print $row['kodenr'];
	print "<br></td>";
	print "<td> $row[beskrivelse]<br></td>";
	print "<td> $row[box1]<br></td>";
	print "<td> $row[box2]<br></td>";
	print "<td> $row[box3]<br></td>";
	print "<td> $row[box4]<br></td>";
	(date('Ym') - $row['box4'].$row['box3'] > 500)?$showDelete=1:$showDelete=0;

	if ($deleted[$x]) {
		print "<td> Slettet</td><td>$deleteDate[$x]<br></td><td></td>";
	} elseif ($row['kodenr'] != $regnaar && $row['box5'] == 'on') {
		print "<td><a href='regnskabsaar.php?aktiver=$row[kodenr]'> " . findtekst('1213|Sæt aktivt', $sprog_id) . "</a><br></td><td></td>";
		// Show delete button only for newest empty fiscal year
		if ($canDeleteThisyear) {
			$deleteTitle = ($sprog_id == 2) ? "Delete empty fiscal year" : "Slet tomt regnskabsår";
			$deleteConfirm = ($sprog_id == 2) ? "Are you sure you want to delete this empty fiscal year?" : "Er du sikker på at du vil slette dette tomme regnskabsår?";
			$emptyText = ($sprog_id == 2) ? "(Empty)" : "(Tom)";
			print "<td style='border:none; display:flex; justify-content:center; align-items:center;'>
			 			<span style='color:#999; font-size:11px; margin-right:5px;'> $emptyText </span> 
						<a href='regnskabsaar.php?deleteEmptyYear=$row[kodenr]' title='$deleteTitle' onclick=\"return confirm('$deleteConfirm')\" style='font-weight:bold; text-decoration:none; cursor: pointer;'> 
							 $trash_icon
						</a> 
				   </td>";
		} elseif($isEmpty[$x]){
			$emptyText = ($sprog_id == 2) ? "(Empty)" : "(Tom)";
            print "<td style='text-align:center;'>
						<span style='color:#999; font-size:11px;'>$emptyText</span>
				   </td>";

		}
		else {
			print "<td></td>";
		}
	} elseif ($row['kodenr'] != $regnaar) {
		print "<td>" . findtekst('387|Lukket', $sprog_id) . "</td><td>";
		if (($x == 1 || $deleted[$x - 1]) && $row['box5'] != 'on' && $showDelete) {
			$txt1 = "Sletter transaktioner med tilhørende bilag, ordrer og fakturaer fra regnskabsåret, ";
			$txt1.= "varer er oprettet i regnskabsåret og ikke har været handlet siden ";
			$txt1.= "samt kunder og leverandører som er urørte i efterfølgende år";
			$txt2 = "Vil du slette dette regnskabsår ?";
			print "<a href='regnskabsaar.php?deleteYear=$row[kodenr]' title='$txt1' onclick=\"return confirm('$txt2')\">";
			print findtekst('1099|Slet', $sprog_id) . "</a>";
		}
		print "</td>";
		// Show delete button if fiscal year is empty
		if ($canDeleteThisyear) {
			$deleteTitle = ($sprog_id == 2) ? "Delete empty fiscal year" : "Slet tomt regnskabsår";
			$deleteConfirm = ($sprog_id == 2) ? "Are you sure you want to delete this empty fiscal year?" : "Er du sikker på at du vil slette dette tomme regnskabsår?";
			$emptyText = ($sprog_id == 2) ? "(Empty)" : "(Tom)";
			print "<td style='border:none; display:flex; justify-content:center; align-items:center;'> 
						<span style='color:#999; font-size:11px; margin-right:5px;'> $emptyText</span>
						<a href='regnskabsaar.php?deleteEmptyYear=$row[kodenr]' title='$deleteTitle' onclick=\"return confirm('$deleteConfirm')\" style='font-weight:bold; text-decoration:none;'> 
							$trash_icon
						 </a>
				   </td>";
		}  elseif($isEmpty[$x]){
			$emptyText = ($sprog_id == 2) ? "(Empty)" : "(Tom)";
			print "<td style='text-align:center;'>
						<span style='color:#999; font-size:11px;'>$emptyText</span>
				   </td>";
		} else {
			print "<td></td>";
		}
	} else {
		print "<td><font color=#ff0000>" . findtekst('1214|Aktivt', $sprog_id) . "</font></td><td>";
		if ($set_alle) {
			$title = "" . findtekst('1794|Klik for at sætte regnskabsår', $sprog_id) . " $regnaar " . findtekst('1795|aktivt for alle brugere', $sprog_id) . "";
			$title2 = "" . findtekst('1796|Sæt regnskabsår', $sprog_id) . " $regnaar " . findtekst('1795|aktivt for alle brugere', $sprog_id) . "?";
			print "<a href=\"regnskabsaar.php?set_alle=$regnaar\" title=\"$title\" onclick=\"return confirm('$title2')\"> " . findtekst('1212|Sæt alle', $sprog_id) . "</a>";
		}
		print "</td>";
		// Show delete button if active fiscal year is empty
		if ($canDeleteThisyear) {
			$deleteTitle = ($sprog_id == 2) ? "Delete empty fiscal year" : "Slet tomt regnskabsår";
			$deleteConfirm = ($sprog_id == 2) ? "Are you sure you want to delete this empty fiscal year?" : "Er du sikker på at du vil slette dette tomme regnskabsår?";
			$emptyText = ($sprog_id == 2) ? "(Empty)" : "(Tom)";
			print "<td style='border:none; display:flex; justify-content:center; align-items:center;'> 
						<span style='color:#999; font-size:11px; margin-right:5px;'> $emptyText </span>
						<a href='regnskabsaar.php?deleteEmptyYear=$row[kodenr]' title='$deleteTitle' onclick=\"return confirm('$deleteConfirm')\" style='font-weight:bold; text-decoration:none;'> 
							$trash_icon 
						</a>
				   </td>";
		}   elseif($isEmpty[$x]){
			$emptyText = ($sprog_id == 2) ? "(Empty)" : "(Tom)";
			print "<td style='text-align:center;'>
						<span style='color:#999; font-size:11px;'>$emptyText</span>
				   </td>";
		} else {
			print "<td></td>";
		}
	}
	print "</tr>";
}
($bgcolor1 != $bgcolor) ? $bgcolor1 = $bgcolor : $bgcolor1 = $bgcolor5;
print "<td  bgcolor='$bgcolor1' colspan='9'><br></td>";
print "<tr><td colspan=\"9\" style=\"text-align:center\"><a href=\"regnskabskort.php\"  title=\"" . findtekst('507|Klik her for at oprette nyt regnskabsår.', $sprog_id) . "\"><button class='button green medium'>" . findtekst('508|Opret nyt regnskabsår', $sprog_id) . "</button></a></td></tr>";
if ($x < 1)
	print "<meta http-equiv=refresh content=0;url=regnskabskort.php>";
?>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</body>

</html>
