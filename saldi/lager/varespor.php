<?php

// ------------lager/varespor.php---------------------patch 3.5.8--2015.09.02--
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------------
//
// 20140626 Tilføjet lagerregulering og ændret variabelnavn for dækningsbidrag.
// 20150902	Linjer med 0 i antal undertrykkes og linjer uden ordre_id vises som Lagerreguleret

@session_start();
$s_id=session_id();
$css="../css/standard.css";
$title="Varespor";
$modulnr=12;
 
$kobsantal=0;$kobssum=0;
 
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if ($popup) $returside="../includes/luk.php";
else $returside="lagerstatus.php";

$vare_id=$_GET['vare_id'];

$query = db_select("select * from varer where id=$vare_id",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
print "<table width=100% cellspacing=2><tbody>";
print "<tr><td colspan=5>";
print "<table width=100% cellspacing=2><tbody>";
print "<td width=10% $top_bund><a href=$returside accesskey=L>Luk</a></td>";
print "<td width=80% $top_bund>$title</td>";
print "<td width=10% $top_bund><br></td>";
print "</tbody></table>";
print "<tr><td><br></td></tr>";
print "<tr><td colspan=5><b>$row[varenr] : $row[enhed] : $row[beskrivelse]</b></td></tr>";
print "<tr><td><br></td></tr>";

########################################################################################

print "<tr><td colspan=5 align=center><b>=== K&Oslash;BT ===</b></td></tr>";
print "<tr><td>Dato</td>
	<td align=right>Antal
	<td align=right>Firmanavn</td>
	<td align=right>K&oslash;bsordre</td>
	<td align=right>K&oslash;bspris</td></tr>";

print "<tr><td colspan=5><hr></td></tr>";

$kontosum=0;
$z=0;
$kobsliste=array();
$query = db_select("select * from batch_kob where vare_id=$vare_id and antal != '0' order by fakturadate",__FILE__ . " linje " . __LINE__);# 20150902
while ($row = db_fetch_array($query)) {
	if ($row['ordre_id']) {
		$q1 = db_select("select ordrenr, firmanavn from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
		$r1 = db_fetch_array($q1); 
	} else $r1=NULL;
	print "<tr><td>".dkdato($row['fakturadate'])."</td>
		<td align=right>".dkdecimal($row['antal'])."</td>";
		if ($r1['firmanavn']) print "<td align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$row[ordre_id]&returside=../includes/luk.php','k_ordre','$jsvars')\"><u>$r1[firmanavn]</u></td>";
		else print "<td align=\"right\">Lagerreguleret</td>";
		print "<td align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$row[ordre_id]&returside=../includes/luk.php','k_ordre','$jsvars')\"><u>$r1[ordrenr]</u></td>";
	$kobsantal=$kobsantal+$row['antal'];
	$kobspris=$row['pris']*$row['antal'];	 
	$kobssum=$kobssum+$kobspris;
	$tmp=dkdecimal($kobspris);
	print "<td align=right>$tmp</td>";
}
$tmp=dkdecimal($kobssum);
print "<tr><td colspan=5><hr></td></tr>";
print "<tr><td>K&oslash;bt i alt</td>
		<td align=right>".dkdecimal($kobsantal)."</td>
		<td align=right colspan=3>$tmp</td>";
		
print "<tr><td colspan=5><hr></td></tr>";
print "<tr><td colspan=5><br></td></tr>";
print "<tr><td colspan=5><br></td></tr>";

########################################################################################
print "<tr><td colspan=5 align=center><b>=== BESTILT ===</b></td></tr>";
print "<tr><td>Dato</td>
	<td align=right>Antal</td>
	<td align=right>Firmanavn</td>
	<td align=right>Ordre</td>
	<td align=right>K&oslash;bspris</td></tr>";

$kobssum=0;$kobsantal=0;
$q = db_select("select id, firmanavn, levdate, ordrenr, art from ordrer where status > 0 and status < 3 and (art = 'KO' or art = 'KK') order by levdate,ordrenr",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$antal=0;
	$kobspris=0;
	if ($r['id']) {
		$q1 = db_select("select antal, pris from ordrelinjer where ordre_id=$r[id] and vare_id = $vare_id",__FILE__ . " linje " . __LINE__);
		while ($r1 = db_fetch_array($q1)) {
			$antal=$antal+$r1['antal'];
			$pris=$r1['pris'];
			$kobspris=$kobspris+$pris*$antal;
		}
	} 
	if ($antal) {
	print "<tr><td>".dkdato($r['levdate'])."</td>
		<td align=right>$antal</td>
		<td align=right onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$r[id]&returside=../includes/luk.php','k_ordre','$jsvars')\"><u>$r[firmanavn]</u></td>
		<td align=right onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$r[id]&returside=../includes/luk.php','k_ordre','$jsvars')\"><u>$r[ordrenr]</u></td>";
	$kobsantal=$kobsantal+$antal;
	$kobssum=$kobssum+$kobspris;
	$tmp=dkdecimal($kobspris);
	print "<td align=right>$tmp</td>";
	$dbd=$kobspris-$kobspris;
	$antal=$antal+$antal;
}
}
$tmp=dkdecimal($kobssum);
print "<tr><td colspan=5><hr></td></tr>";
print "<tr><td>Bestilt i alt</td>
	<td align=right>".dkdecimal($kobsantal)."</td>
	<td align=right colspan=3>$tmp</td>";

print "<tr><td colspan=5><hr></td></tr>";
print "<tr><td colspan=5><br></td></tr>";
print "<tr><td colspan=5><br></td></tr>";


########################################################################################
print "<tr><td colspan=5 align=center><b>=== SOLGT ===</b></td></tr>";
print "<tr><td>Dato</td>
	<td align=right>Antal</td>
	<td align=right>Firmanavn</td>
	<td align=right>Salgsordre</td>
	<td align=right>Salgspris</td></tr>";
print "<tr><td colspan=5><hr></td></tr>";

$salgssum=0;
$salgsantal=0;

$query = db_select("select * from batch_salg where vare_id=$vare_id order by fakturadate",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	if ($row['ordre_id']) {
		$q1 = db_select("select ordrenr, firmanavn from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
		$r1 = db_fetch_array($q1); 
	} else $r1=NULL;
	print "<tr><td>".dkdato($row['fakturadate'])."</td>
		<td align=right>".dkdecimal($row['antal'])."</td>";
	if ($row['ordre_id'])	{
		print "<td align=right onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:d_ordre=window.open('../debitor/ordre.php?id=$row[ordre_id]&returside=../includes/luk.php','d_ordre','$jsvars')\"><u>$r1[firmanavn]</u></td>
		<td align=right onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:d_ordre=window.open('../debitor/ordre.php?id=$row[ordre_id]&returside=../includes/luk.php','d_ordre','$jsvars')\"><u>$r1[ordrenr]</u></td>";
	} else {
		print "<td align=\"right\">Lagerregulering</td><td></td>";
	}
	$salgsantal=$salgsantal+$row['antal'];
	$salgspris=$row['pris']*$row['antal'];	 
	$salgssum=$salgssum+$salgspris;
	$tmp=dkdecimal($salgspris);
	print "<td align=right>$tmp</td>";
	$dbd=$salgspris-$kobspris;
	$antal=$antal+$row['antal'];
}
$tmp=dkdecimal($salgssum);
print "<tr><td colspan=5><hr></td></tr>";
print "<tr><td>Solgt i alt</td>
	<td align=right>".dkdecimal($salgsantal)."</td>
	<td align=right colspan=3>$tmp</td>";

print "<tr><td colspan=5><hr></td></tr>";
print "<tr><td colspan=5><br></td></tr>";
print "<tr><td colspan=5><br></td></tr>";

########################################################################################

print "<tr><td colspan=5 align=center><b>=== ORDREBEHOLDNING ===</b></td></tr>";
print "<tr><td>Dato</td>
	<td align=right>Antal</td>
	<td align=right>Firmanavn</td>
	<td align=right>Ordre</td>
	<td align=right>Salgspris</td></tr>";

$salgssum=0;$salgsantal=0;
$q = db_select("select id, firmanavn, levdate, ordrenr, art from ordrer where status > 0 and status < 3 and (art = 'DO' or art = 'DK') order by levdate",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$antal=0;
	$salgspris=0;
	if ($r['id']) {
		$q1 = db_select("select antal, pris from ordrelinjer where ordre_id=$r[id] and vare_id = $vare_id",__FILE__ . " linje " . __LINE__);
		while ($r1 = db_fetch_array($q1)) {
			$antal=$antal+$r1['antal'];
			$pris=$r1['pris'];
			$salgspris=$salgspris+$pris*$antal;
		}
	}
	if ($antal) {
	print "<tr><td>".dkdato($r['levdate'])."</td>
		<td align=right>$antal</td>
		<td align=right onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:d_ordre=window.open('../debitor/ordre.php?id=$r[id]&returside=../includes/luk.php','d_ordre','$jsvars')\"><u>$r[firmanavn]</u></td>
		<td align=right onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:d_ordre=window.open('../debitor/ordre.php?id=$r[id]&returside=../includes/luk.php','d_ordre','$jsvars')\"><u>$r[ordrenr]</u></td>";
	$salgsantal=$salgsantal+$antal;
	$salgssum=$salgssum+$salgspris;
	$tmp=dkdecimal($salgspris);
	print "<td align=right>$tmp</td>";
	$dbd=$salgspris-$kobspris;
	$antal=$antal+$antal;
}
}
$tmp=dkdecimal($salgssum);
print "<tr><td colspan=5><hr></td></tr>";
print "<tr><td>Ordrebeh. i alt</td>
	<td align=right>".dkdecimal($salgsantal)."</td>
	<td align=right colspan=3>$tmp</td>";

print "<tr><td colspan=5><hr></td></tr>";

##########################################################################

print "</tbody></table>";



?>
</html>

