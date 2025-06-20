<?php

// ---------------------------------------------debitor/historik.php-----lap 1.1.4-------12.12.2007-----------
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
// Copyright (c) 2004-2007 DANOSOFT ApS
// ----------------------------------------------------------------------

	
@session_start();
$s_id=session_id();

$title="Kunde & emne historik";
$modulnr=6;	
$sort=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
if (isset($_GET['sort'])) $sort = $_GET['sort'];
#print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Kunde & emne histotik</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";

print "

<div align=\"center\">

<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
	<tr><td height = \"25\" align=\"center\" valign=\"top\">
		<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>
			<td width=\"10%\"$top_bund>$font<small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>
			<td width=\"30%\"$top_bund>$font<small><br></small></td>
			<td width=\"10%\"$top_bund>$font<small><a href=debitor.php title =\"Klik her for at skifte til debitoroversigten\">Debitorer</a></small></td>
			<td width=\"10%\"$knap_ind>$font<small>Historik</a></small></td>
			<td width=\"30%\"$top_bund>$font<small><br></small></td>
			<td width=\"10%\"$top_bund onClick=\"javascript:visning=window.open('historikvisning.php','visning','scrollbars=1,resizable=1');visning.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\">$font <small><u>Visning</u></td> 
			 </tr>
			</tbody></table>
	</td></tr>
 <tr><td valign=\"top\">
<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">
<tbody>";

if ($r = db_fetch_array(db_select("select * from grupper where art = 'HV' and box1 = '$brugernavn'"))) {
	$vis_liste=$r['box2'];
} else {
	db_modify("insert into grupper(beskrivelse, art, box1, box2)values('historikvisning', 'HV', '$brugernavn', '1')");
	$vis_liste='1';
}


$q0 = db_select("select * from grupper where art = 'DG' order by beskrivelse");
$x=-1;
while ($r0 = db_fetch_array($q0)) {
	$x++;
	if (substr($vis_liste,$x,1)=='1') {
		print "<tr><td><small>$font<b>$r0[beskrivelse]</b></small></td></tr>";	
		print "<tr><td colspan=9><hr></td></tr>";
		print "<tr>
			<td><small><b>$font <a href=historik.php?sort=kontonr>Kundenr</b></small></td>
			<td><small><b>$font <a href=historik.php?sort=firmanavn>Navn</a></b></small></td>
			<td><small><b>$font <a href=historik.php?sort=postnr>Postnr</a></b></small></td>
			<td><small><b>$font <a href=historik.php?sort=bynavn>By</a></b></small></td>
			<td><small><b>$font <a href=historik.php?sort=tlf>Telefon</a></b></small></td>
			<td><small><b>$font <a href=historik.php?sort=Oprettet>Oprettet</a></b></small></td>
			<td><small><b>$font <a href=historik.php?sort=kontaktet>Kontaktet</a></b></small></td>
			<td><small><b>$font <a href=historik.php?sort=kontaktes>Kontaktes</a></b></small></td>
			<td><small><b>$font <a href=historik.php?sort=kontaktes>Init.</a></b></small></td>
		</tr>";

		if ($sort) $q1 = db_select("select * from adresser where art = 'D' and gruppe=$r0[kodenr] order by $sort");
		else $q1 = db_select("select * from adresser where art = 'D' and gruppe=$r0[kodenr]order by kontaktes, firmanavn");
		while ($r1 = db_fetch_array($q1)) {
			print "<tr>";
			print "<td><small>$font <a href=historikkort.php?id=$r1[id]>$r1[kontonr]</a><br></small></td>
				<td><small>$font <a href=historikkort.php?id=$r1[id]>".htmlentities(stripslashes($r1['firmanavn']))."<br></a></small></td>
				<td><small>$font <a href=historikkort.php?id=$r1[id]>".htmlentities(stripslashes($r1['postnr']))."<br></a></small></td>
				<td><small>$font <a href=historikkort.php?id=$r1[id]>".htmlentities(stripslashes($r1['bynavn']))."<br></a></small></td>
				<td><small>$font <a href=historikkort.php?id=$r1[id]>".htmlentities(stripslashes($r1['tlf']))."<br></a></small></td>
				<td><small>$font <a href=historikkort.php?id=$r1[id]>".htmlentities(stripslashes($r1['oprettet']))."<br></a></small></td>
				<td><small>$font <a href=historikkort.php?id=$r1[id]>".htmlentities(stripslashes($r1['kontaktet']))."<br></a></small></td>
				<td><small>$font <a href=historikkort.php?id=$r1[id]>".htmlentities(stripslashes($r1['kontaktes']))."<br></a></small></td>";
			$tmp=$r1['kontoansvarlig']*1;
			$r2=db_fetch_array(db_select("select initialer from ansatte where id = $tmp"));
			print "<td><small>$font <a href=historikkort.php?id=$r1[id]>".htmlentities(stripslashes($r2['initialer']))."<br></a></small></td>";
			print "</tr>";
		}
	
	}
}
?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
