<?php

// -----------------------------------------------lager/varespor.php------------patch 1.0.9---
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
 
$title="Varerapport";
$modulnr=12;
 
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/dkdato.php");
include("../includes/dkdecimal.php");

$vare_id=$_GET['vare_id'];

$query = db_select("select * from varer where id=$vare_id");
$row = db_fetch_array($query);
print "<table><tbody>";
print "<tr><td colspan=4 bgcolor=$bgcolor2>$font<small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>";
print "<tr><td><br></td></tr>";
print "<tr><td colspan=4>$font<small>$row[varenr] : $row[enhed] : $row[beskrivelse]</small></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td width=12.5%>$font<small>Dato</td>
	<td align=right width=12.5%>$font<small>Antal</small>
	<td align=right width=12.5%>$font<small>Købsordre</small></td>
	<td align=right width=12.5%>$font<small>K&oslash;bspris</small></td></tr>";
print "<tr><td colspan=4><hr></td></tr>";

$kontosum=0;
$z=0;
$kobsliste=array();
$query = db_select("select * from batch_kob where vare_id=$vare_id order by fakturadate");
while ($row = db_fetch_array($query)) {
	if ($row[ordre_id]) {
		$q1 = db_select("select ordrenr from ordrer where id=$row[ordre_id]");
		$r1 = db_fetch_array($q1); 
	}
	print "<tr><td>$font<small>".dkdato($row[fakturadate])."</small></td>
		<td align=right>$font<small>$row[antal]</small></td>
		<td align=right onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$row[ordre_id]','k_ordre','width=800,height=400,scrollbars=1,resizable=1')\">$font<small><u>$r1[ordrenr]</u></small></td>";
	$kobsantal=$kobsantal+$row[antal];
	$kobspris=$row[pris]*$row[antal];	 
	$kobssum=$kobssum+$kobspris;
	$tmp=dkdecimal($kobspris);
	print "<td align=right>$font<small>$tmp</small></td>";
}
$tmp=dkdecimal($kobssum);
print "<tr><td colspan=4><hr></td></tr>";
print "<tr><td>$font<small>K&oslash;bt i alt</small></td>
		<td align=right>$font<small>$kobsantal</small></td>
		<td align=right colspan=2>$font<small>$tmp</small></td>";
		
print "<tr><td><br></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td>$font<small>Dato</td>
	<td align=right>$font<small>Antal</small></td>
	<td align=right>$font<small>Salgsordre</small></td>
	<td align=right>$font<small>Salgspris</small></td><tr>";
print "<tr><td colspan=4><hr></td></tr>";
$query = db_select("select * from batch_salg where vare_id=$vare_id order by fakturadate");
while ($row = db_fetch_array($query)) {
	if ($row[ordre_id]) {
		$q1 = db_select("select ordrenr from ordrer where id=$row[ordre_id]");
		$r1 = db_fetch_array($q1); 
	}
	print "<tr><td>$font<small>".dkdato($row[fakturadate])."</small></td>
		<td align=right>$font<small>$row[antal]</small></td>
		<td align=right onClick=\"javascript:k_ordre=window.open('../debitor/ordre.php?id=$row[ordre_id]','k_ordre','width=800,height=400,scrollbars=1,resizable=1')\">$font<small><u>$r1[ordrenr]</u></small></td>";
	$salgsantal=$salgsantal+$row[antal];
	$salgspris=$row[pris]*$row[antal];	 
	$salgssum=$salgssum+$salgspris;
	$tmp=dkdecimal($salgspris);
	print "<td align=right>$font<small>$tmp</small></td>";
	$db=$salgspris-$kobspris;
	$antal=$antal+$row[antal];
}
$tmp=dkdecimal($salgssum);
print "<tr><td colspan=4><hr></td></tr>";
print "<tr><td>$font<small>Solgt i alt</small></td>
		<td align=right>$font<small>$salgsantal</small></td>
		<td align=right colspan=2>$font<small>$tmp</small></td>";
print "</tbody></table>";

?>
</html>

