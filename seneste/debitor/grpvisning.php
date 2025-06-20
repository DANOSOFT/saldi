<?php

// --------debitor/grpvisning.php-----lap 2.0.8-------2009.06.04-----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

	
@session_start();
$s_id=session_id();

$title="Kunde & emne visning";
$modulnr=6;
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/std_func.php");

$side=if_isset($_GET['side']);
$sort=if_isset($_GET['sort']);

if ($popup) $returside="../includes/luk.php"; 
else $returside="$side.php";
	
if ($side=='historik') $box='box2';
elseif ($side=='debitor') $box='box3';

if (isset($_POST) && $_POST) {
	$dg_antal=if_isset($_POST['dg_antal']);
	$vis_liste='';
	for ($x=0; $x<=$dg_antal; $x++) {
		$tmp="box"."$x";
		$tmp2=if_isset($_POST[$tmp]);
		if ($tmp2=='on') $vis_liste=$vis_liste.'1';
		else $vis_liste=$vis_liste.'0';
	}
# brugernavn i box1 er en pre 2.0.7 ting - fremover skal der identificeres paa kodenr / bruger_id.
	db_modify("update grupper set $box='$vis_liste', kodenr = '$bruger_id', kode = '$brugernavn' where art = 'DGV' and (box1 = '$brugernavn' or kodenr = '$bruger_id')",__FILE__ . " linje " . __LINE__);
	if ($popup) print "<BODY onLoad=\"javascript=opener.location.reload();\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
}

print "<div align=\"center\">
<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
	<tr><td height = \"25\" align=\"center\" valign=\"top\">
		<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"4\" cellpadding=\"0\"><tbody>
			<td width=\"10%\" align=center><div class=\"top_bund\"><a href=$returside accesskey=L>Luk</a></div></td>
			<td width=\"80%\" align=center><div class=\"top_bund\">$title</a></div></td>
			<td width=\"10%\" align=center><div class=\"top_bund\"><br></div></td>
			 </tr>
			</tbody></table>
	</td></tr>
 <tr><td valign=\"top\">
<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">
<tbody>";

print "<form name=grpvisning action=grpvisning.php?sort=$sort&side=$side method=post>";
if ($r = db_fetch_array(db_select("select * from grupper where art = 'DGV' and (box1 = '$brugernavn' or kodenr = '$bruger_id')",__FILE__ . " linje " . __LINE__))) {
	$vis_liste=$r[$box];
} else {
	db_modify("insert into grupper(beskrivelse, art, kode, kodenr, $box)values('historikvisning', 'HV','$brugernavn', '$bruger_id', '1')",__FILE__ . " linje " . __LINE__);
	$vis_liste='0';
}

print "<tr><td colspan=3>V&aelig;lg hvilke kundegrupper der skal v&aelig;re synlige p&aring; oversigten</td></tr>";
print "<tr><td colspan=3>Hvis intet er valgt, vil alt blive vist!</td></tr>";
print "<tr><td colspan=3><hr></td></tr>";
$q = db_select("select * from grupper where art = 'DG' order by beskrivelse",__FILE__ . " linje " . __LINE__);
$x=-1;
while ($r = db_fetch_array($q)) {
	$x++;
	if (substr($vis_liste,$x,1)=='1') $tmp='checked';
	else $tmp='';
	print "<tr><td><input name= box$x type=checkbox $tmp> $r[beskrivelse]</td></tr>";
}
print "<input type=hidden name=dg_antal value=$x>";
print "<tr><td colspan=3 align = center><input type=submit accesskey=\"a\" value=\"OK\" name=\"submit\"></td></tr>\n";

?>
</tbody></table>

</body></html>
