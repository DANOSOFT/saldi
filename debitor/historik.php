<?php

// -----------debitor/historik.php-----lap 2.0.7-------2010-04-12-----------
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
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------

	
@session_start();
$s_id=session_id();

$vis_alt=0; $vis_liste="0";$sort=NULL;$ny_sort=NULL;

$css="../css/standard.css";
$title="Kunde & emne historik";
$modulnr=6;	

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

if (isset($_GET['ny_sort'])) $ny_sort = strtolower($_GET['ny_sort']);
if (isset($_GET['sort'])) $sort = str_replace("_"," ",strtolower($_GET['sort']));
if ($ny_sort && $ny_sort == $sort) $sort=$sort." desc";
elseif ($ny_sort) $sort=$ny_sort;
#print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Kunde & emne histotik</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";
if (!$sort) $sort = $_COOKIE['saldi_hist_sort'];
setcookie("saldi_hist_sort",$sort,time()+60*60*24*30);

print "

<div align=\"center\">

<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
	<tr><td height = \"25\" align=\"center\" valign=\"top\">
		<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>
			<td width=\"10%\"$top_bund><a href=$returside accesskey=L>Luk</a></td>
			<td width=\"30%\"$top_bund><br></td>
			<td width=\"10%\"$top_bund><a href=debitor.php title =\"Klik her for at skifte til debitoroversigten\">Debitorer</a></td>
			<td width=\"10%\"$knap_ind>Historik</a></td>
			<td width=\"30%\"$top_bund><br></td>";
if ($popup) print "<td width=\"10%\"$top_bund onClick=\"javascript:visning=window.open('grpvisning.php?side=historik','visning','scrollbars=1,resizable=1');visning.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\"> <u>Visning</u></td>";
else print "<td width=\"10%\"$top_bund><a href=grpvisning.php?side=historik>Visning</td>";
print " </tr>
			</tbody></table>
	</td></tr>
 <tr><td valign=\"top\">
<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">
<tbody>";

if ($r = db_fetch_array(db_select("select * from grupper where art = 'DGV' and (box1 = '$brugernavn' or kodenr = '$bruger_id')",__FILE__ . " linje " . __LINE__))) {
	$vis_liste=$r['box2'];
} else {
	db_modify("insert into grupper(beskrivelse, art, kode,kodenr)values('debitorvisning', 'DGV', '$brugernavn','$bruger_id')",__FILE__ . " linje " . __LINE__);
}
if (!strstr($vis_liste,"1")) $vis_alt=1; #strpos ser ikke hvis 1. ciffer = 1.
$q0 = db_select("select * from grupper where art = 'DG' order by beskrivelse",__FILE__ . " linje " . __LINE__);
$x=-1;
$tmp=str_replace(" ","_",$sort);
while ($r0 = db_fetch_array($q0)) {
	$x++;
	if (substr($vis_liste,$x,1)=='1' || ($vis_alt==1 && !$x)) {
		if (!$vis_alt) {
			print "<tr><td colspan=3><b>$r0[beskrivelse]</b></td></tr>";	
			print "<tr><td colspan=10><hr></td></tr>";
		}
			print "<tr>
			<td><b><a href=historik.php?ny_sort=kontonr&sort=$tmp>Kundenr</b></td>
			<td><b><a href=historik.php?ny_sort=firmanavn&sort=$tmp>Navn</a></b></td>
			<td><b><a href=historik.php?ny_sort=postnr&sort=$tmp>Postnr</a></b></td>
			<td><b><a href=historik.php?ny_sort=bynavn&sort=$tmp>By</a></b></td>
			<td><b><a href=historik.php?ny_sort=tlf&sort=$tmp>Telefon</a></b></td>
			<td><b><a href=historik.php?ny_sort=Oprettet&sort=$tmp>Oprettet</a></b></td>
			<td><b><a href=historik.php?ny_sort=kontaktet&sort=$tmp>Kontaktet</a></b></td>
			<td><b><a href=historik.php?ny_sort=kontaktes&sort=$tmp>Kontaktes</a></b></td>
			<td><b>sidst</b></td>
			<td><b>Ansv</b></td>
		</tr>";
		if (!$vis_alt) $gruppe="and adresser.gruppe=$r0[kodenr]";	
		if ($sort) {
			if ($sort=='initialer' || $sort=='initialer desc') $q1 = db_select("select adresser.* from adresser, ansatte where adresser.art = 'D' $gruppe and ansatte.konto_id=adresser.id order by ansatte.$sort",__FILE__ . " linje " . __LINE__);
			else $q1 = db_select("select * from adresser where art = 'D' $gruppe order by $sort",__FILE__ . " linje " . __LINE__);
		} else $q1 = db_select("select * from adresser where art = 'D' $gruppe order by kontaktes, firmanavn",__FILE__ . " linje " . __LINE__);
		while ($r1 = db_fetch_array($q1)) {
			if ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
			else {$linjebg=$bgcolor; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\">";
			if ($popup) { 
				$pre="<td onClick=\"javascript:historikkort=window.open('historikkort.php?id=$r1[id]&returside=../includes/luk.php','historikkort','".$jsvars."');historikkort.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\";><u>";
				$post="<br></u></td>";
			} else {
				$pre="<td><a href=historikkort.php?id=$r1[id]>";
				$post="<br></a></td>";
			}	
			print "$pre $r1[kontonr] $post";
			print "$pre".htmlentities($r1['firmanavn'],ENT_COMPAT,$charset)."$post
			$pre".htmlentities($r1['postnr'],ENT_COMPAT,$charset)."$post
			$pre".htmlentities($r1['bynavn'],ENT_COMPAT,$charset)."$post
			$pre".htmlentities($r1['tlf'],ENT_COMPAT,$charset)."$post
			$pre".dkdato($r1['oprettet'])."$post
			$pre".dkdato($r1['kontaktet'])."$post
			$pre".dkdato($r1['kontaktes'])."$post";
			
			if ($r1['kontaktet']) {
				$r2=db_fetch_array(db_select("select ansatte.initialer as initialer from historik,ansatte where historik.konto_id = '$r1[id]' and ansatte.id = historik.ansat_id and historik.kontaktet = '$r1[kontaktet]'",__FILE__ . " linje " . __LINE__));
				print "$pre".htmlentities($r2['initialer'],ENT_COMPAT,$charset)."$post";
			} else print "<td><br></td>";
			
			$tmp=$r1['kontoansvarlig']*1;
			$r2=db_fetch_array(db_select("select initialer from ansatte where id = $tmp",__FILE__ . " linje " . __LINE__));
			print "$pre".htmlentities($r2['initialer'],ENT_COMPAT,$charset)."$post";
			print "</tr>";
		}
		print "<tr><td><br></td></tr><tr><td><br></td></tr>";
	}
}
?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
