<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//
// --- systemdata/regnskabsaar.php --- ver 4.1.1 --- 2025-05-03 --
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
	echo "Sletter";
	print "<script>javascript:document.body.style.cursor = 'wait'</script>";
	include_once("fiscalYearInc/deleteFiscalYear.php");
	deleteFinancialYear($deleteYear);
	print "<script>javascript:document.body.style.cursor = 'default'</script>";
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
print "<td width = 40%><b>" . findtekst(914, $sprog_id) . "</a></b></td>"; #20210709
print "<td width = 9%><b>" . findtekst(1208, $sprog_id) . "</a></b></td>";
print "<td width = 9%><b>" . findtekst(1209, $sprog_id) . "</a></b></td>";
print "<td width = 9%><b>" . findtekst(1210, $sprog_id) . "</a></b></td>";
print "<td width = 9%><b>" . findtekst(1211, $sprog_id) . "</a></b></td>";
print "<td width = 8%><b><br></a></b></td>";
print "<td width = 8%><b><br></a></b></td>";
print "<tr>";
print "<td colspan='8'><hr></td>";
print "</tr>";
print "</tr>";
$set_alle = 0;
$q = db_select("select id,regnskabsaar from brugere", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if ($regnaar != $r['regnskabsaar'])
		$set_alle = 1;
}

$x = 0;
$deleted = array();
$query = db_select("select * from grupper where art = 'RA' order by box2,box1", __FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	$x++;
	($row['box10'] == 'on') ? $deleted[$x] = 1 : $deleted[$x] = 0;
	($bgcolor1 != $bgcolor) ? $bgcolor1 = $bgcolor : $bgcolor1 = $bgcolor5;
	print "<tr bgcolor=\"$bgcolor1\">";
	$title = "" . findtekst(1793, $sprog_id) . " $row[kodenr]";  #20210805
	print "<td>";
	if ($row['box10'] != 'on')
		print "<a href='regnskabskort.php?id=$row[id]' title=\"$title\"> $row[kodenr]</a>";
	else
		print $row['kodenr'];
	print "<br></td>";
	print "<td> $row[beskrivelse]<br></td>";
	print "<td> $row[box1]<br></td>";
	print "<td> $row[box2]<br></td>";
	print "<td> $row[box3]<br></td>";
	print "<td> $row[box4]<br></td>";
	if ($row['box10'] == 'on') {
		print "<td> Slettet<br></td><td></td>";
	} elseif ($row['kodenr'] != $regnaar && $row['box5'] == 'on') {
		print "<td><a href='regnskabsaar.php?aktiver=$row[kodenr]'> " . findtekst(1213, $sprog_id) . "</a><br></td><td></td>";
	} elseif ($row['kodenr'] != $regnaar) {
		print "<td>" . findtekst(387, $sprog_id) . "</td><td>";
		if (($x == 1 || $deleted[$x - 1] == '1') && $row['box5'] != 'on') {
			$txt1 = "Sletter transaktioner med tilhørende bilag, ordrer og fakturaer fra regnskabsåret, ";
			$txt1 .= "varer er oprettet i regnskabsåret og ikke har været handlet siden ";
			$txt1 .= "samt kunder og leverandører som er urørte i efterfølgende år";
			$txt2 = "Vil du slette dette regnskabsår ?";
			print "<a href='regnskabsaar.php?deleteYear=$row[kodenr]' title='$txt1' onclick=\"return confirm('$txt2')\">";
			print findtekst(2365, $sprog_id) . "</a>";
		}
		print "</td>";
	} else {
		print "<td><font color=#ff0000>" . findtekst(1214, $sprog_id) . "</font></td><td>";
		if ($set_alle) {
			$title = "" . findtekst(1794, $sprog_id) . " $regnaar " . findtekst(1795, $sprog_id) . "";
			$title2 = "" . findtekst(1796, $sprog_id) . " $regnaar " . findtekst(1795, $sprog_id) . "?";
			print "<a href=\"regnskabsaar.php?set_alle=$regnaar\" title=\"$title\" onclick=\"return confirm('$title2')\"> " . findtekst(1212, $sprog_id) . "</a>";
		}
		print "</td>";
	}
	print "</tr>";
}
($bgcolor1 != $bgcolor) ? $bgcolor1 = $bgcolor : $bgcolor1 = $bgcolor5;
print "<td  bgcolor='$bgcolor1' colspan='8'><br></td>";
print "<tr><td colspan=\"8\" style=\"text-align:center\"><a href=\"regnskabskort.php\"  title=\"" . findtekst(507, $sprog_id) . "\"><button class='button green medium'>" . findtekst(508, $sprog_id) . "</button></a></td></tr>";
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