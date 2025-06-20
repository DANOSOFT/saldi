<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------------systemdata/regnskabsaar.php--------- lap 3.6.6 -- 2016-12-02 --
// LICENS
//
// // Dette program er fri software. Du kan gendistribuere det og / eller
// // modificere det under betingelserne i GNU General Public License (GPL)
// // som er udgivet af "The Free Software Foundation", enten i version 2
// // af denne licens eller en senere version, efter eget valg.
// // Fra og med version 3.2.2 dog under iagttagelse af følgende:
// // 
// // Programmet må ikke uden forudgående skriftlig aftale anvendes
// // i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
// //
// // Dette program er udgivet med haab om at det vil vaere til gavn,
// // men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// // GNU General Public Licensen for flere detaljer.
// //
// // En dansk oversaettelse af licensen kan laeses her:
// // http://www.saldi.dk/dok/GNU_GPL_v2.html
// //
// // Copyright (c) 2003-2017 saldi.dk ApS
// ----------------------------------------------------------------------------
// 20150327 CA  Topmenudesign tilføjet                             søg 20150327
// 20161202 PHR Små designændringer
// 2019.02.21 MSC - Rettet topmenu design
// 2019.02.25 MSC - Rettet topmenu design

@session_start();
$s_id=session_id();
$css="../css/standard.css";
$modulnr=1;
$title="Regnskabsaar";
$aktiver=NULL; $bgcolor=NULL; $bgcolor1=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$aktiver=if_isset($_GET['aktiver']);
$set_alle=if_isset($_GET['set_alle']);

if ($set_alle) db_modify("update brugere set regnskabsaar = '$set_alle'",__FILE__ . " linje " . __LINE__);
if ($aktiver) {
	include("../includes/connect.php");
	db_modify("update online set regnskabsaar = '$aktiver' where session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
	if ($revisor) db_modify("update revisor set regnskabsaar = '$aktiver' where brugernavn = '$brugernavn' and db_id='$db_id'",__FILE__ . " linje " . __LINE__);
	include("../includes/online.php");
	if (!$revisor) db_modify("update brugere set regnskabsaar = '$aktiver' where id = '$bruger_id'",__FILE__ . " linje " . __LINE__);
}

if ($menu=='T') {  # 20150327 start
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
($bgcolor1!=$bgcolor)?$bgcolor1=$bgcolor:$bgcolor1=$bgcolor5;
print "<tbody>";
print "<tr bgcolor='$bgcolor1'>";
print "<td width = 8%><b>ID</b></td>";
print "<td width = 40%><b>Beskrivelse</a></b></td>";
print "<td width = 9%><b>Start md.</a></b></td>";
print "<td width = 9%><b>Start &aring;r</a></b></td>";
print "<td width = 9%><b>Slut md.</a></b></td>";
print "<td width = 9%><b>Slut &aring;r</a></b></td>";
print "<td width = 8%><b><br></a></b></td>";
print "<td width = 8%><b><br></a></b></td>";
print "<tr>";
print "<td colspan='8'><hr></td>";
print "</tr>";
print "</tr>";
$set_alle=0;
$q = db_select("select id,regnskabsaar from brugere",__FILE__ . " linje " . __LINE__);
while($r = db_fetch_array($q)) {
	if ($regnaar != $r['regnskabsaar']) $set_alle=1;
}

$x=0;
$query = db_select("select * from grupper where art = 'RA' order by box2,box1",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	$x++;
	($bgcolor1!=$bgcolor)?$bgcolor1=$bgcolor:$bgcolor1=$bgcolor5;
	print "<tr bgcolor=\"$bgcolor1\">";
	$title="Klik her for at redigere/opdatere regnskabsår $row[kodenr]";
	print "<td><a href='regnskabskort.php?id=$row[id]' title=\"$title\"> $row[kodenr]</a><br></td>";
	print "<td> $row[beskrivelse]<br></td>";
	print "<td> $row[box1]<br></td>";
	print "<td> $row[box2]<br></td>";
	print "<td> $row[box3]<br></td>";
	print "<td> $row[box4]<br></td>";
	if (($row['kodenr']!=$regnaar)&&($row['box5']=='on')) {
		print "<td><a href='regnskabsaar.php?aktiver=$row[kodenr]'> S&aelig;t aktivt</a><br></td><td></td>";
	}
	elseif ($row['kodenr']!=$regnaar) print "<td> Lukket</td><td></td>";
	else {
		print "<td><font color=#ff0000>Aktivt</font></td><td>";
		if ($set_alle) {
			$title="Klik for at sætte regnskabsår $regnaar aktivt for alle brugere";
			$title2="Sæt regnskabsår $regnaar aktivt for alle brugere?";
			print "<a href=\"regnskabsaar.php?set_alle=$regnaar\" title=\"$title\" onclick=\"return confirm('$title2')\"> S&aelig;t alle</a>";
		}
		print "</td>";
	}
	print "</tr>";
}
($bgcolor1!=$bgcolor)?$bgcolor1=$bgcolor:$bgcolor1=$bgcolor5;
print "<td  bgcolor='$bgcolor1' colspan='8'><br></td>";
print "<tr><td colspan=\"8\" style=\"text-align:center\"><a href=\"regnskabskort.php\"  title=\"".findtekst(507,$sprog_id)."\"><button class='button green medium'>".findtekst(508,$sprog_id)."</button></a></td></tr>";
if ($x<1) print "<meta http-equiv=refresh content=0;url=regnskabskort.php>";
?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
