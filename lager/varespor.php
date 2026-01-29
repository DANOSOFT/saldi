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
$modulnr=12;

global $menu;
 
$kobsantal=0;$kobssum=0;
 
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

$title=findtekst('2236|Varespor', $sprog_id);

if ($popup) $returside="../includes/luk.php";
else $returside="lagerstatus.php";

$vare_id=$_GET['vare_id'];

$query = db_select("select * from varer where id=$vare_id",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);

if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\"><a href=$returside accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst('30|Tilbage', $sprog_id)."</a></div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
	print "<table width=100% cellspacing=2><tbody>";
} elseif ($menu=='S') {
	print "<table width=100% cellspacing=2><tbody>";
	print "<tr><td colspan=5>";
	print "<table width=100% cellspacing=2><tbody>";

	print "<td width=10%><a href=$returside accesskey=L>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst('30|Tilbage', $sprog_id)."</button></a></td>";

	print "<td width=80% align='center' style='$topStyle'>$title</td>";
	print "<td width=10% align='center' style='$topStyle'><br></td>";

	print "</tbody></table>";
} else {
	print "<table width=100% cellspacing=2><tbody>";
	print "<tr><td colspan=5>";
	print "<table width=100% cellspacing=2><tbody>";
	print "<td width=10% $top_bund><a href=$returside accesskey=L>Luk</a></td>";
	print "<td width=80% $top_bund>$title</td>";
	print "<td width=10% $top_bund><br></td>";
	print "</tbody></table>";
}
print "<tr><td><br></td></tr>";
print "<tr><td colspan=5><b>$row[varenr] : $row[enhed] : $row[beskrivelse]</b></td></tr>";
print "<tr><td><br></td></tr>";

########################################################################################

print "<tr><td colspan=5 align=center><b>=== ".strtoupper(findtekst('2744|Tilgang', $sprog_id))." ===</b></td></tr>";
print "<tr><td>".findtekst('438|Dato', $sprog_id)."</td>
	<td align=right>".findtekst('916|Antal', $sprog_id)."</td>
	<td align=right>".findtekst('28|Firmanavn', $sprog_id)."</td>
	<td align=right>".findtekst('1515|Købsordre', $sprog_id)."</td>
	<td align=right>".findtekst('978|Købspris', $sprog_id)."</td></tr>";

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
		else print "<td align=\"right\">".findtekst('2237|Lagerreguleret', $sprog_id)."</td>";
		print "<td align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$row[ordre_id]&returside=../includes/luk.php','k_ordre','$jsvars')\"><u>$r1[ordrenr]</u></td>";
	$kobsantal=$kobsantal+$row['antal'];
	$kobspris=$row['pris']*$row['antal'];	 
	$kobssum=$kobssum+$kobspris;
	$tmp=dkdecimal($kobspris);
	print "<td align=right>$tmp</td>";
}
$tmp=dkdecimal($kobssum);
print "<tr><td colspan=5><hr></td></tr>";
print "<tr><td>".findtekst('2238|Købt i alt', $sprog_id)."</td>
		<td align=right>".dkdecimal($kobsantal)."</td>
		<td align=right colspan=3>$tmp</td>";
		
print "<tr><td colspan=5><hr></td></tr>";
print "<tr><td colspan=5><br></td></tr>";
print "<tr><td colspan=5><br></td></tr>";

########################################################################################
print "<tr><td colspan=5 align=center><b>=== ".strtoupper(findtekst('976|Bestilt', $sprog_id))." ===</b></td></tr>";
print "<tr><td>".findtekst('438|Dato', $sprog_id)."</td>
	<td align=right>".findtekst('916|Antal', $sprog_id)."</td>
	<td align=right>".findtekst('28|Firmanavn', $sprog_id)."</td>
	<td align=right>".findtekst('605|Ordre', $sprog_id)."</td>
	<td align=right>".findtekst('978|Købspris', $sprog_id)."</td></tr>";

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
print "<tr><td>".findtekst('2239|Bestilt i alt', $sprog_id)."</td>
	<td align=right>".dkdecimal($kobsantal)."</td>
	<td align=right colspan=3>$tmp</td>";

print "<tr><td colspan=5><hr></td></tr>";
print "<tr><td colspan=5><br></td></tr>";
print "<tr><td colspan=5><br></td></tr>";


########################################################################################
print "<tr><td colspan=5 align=center><b>=== ".strtoupper(findtekst('2745|Afgang', $sprog_id))." ===</b></td></tr>";
print "<tr><td>".findtekst('438|Dato', $sprog_id)."</td>
	<td align=right>".findtekst('916|Antal', $sprog_id)."</td>
	<td align=right>".findtekst('28|Firmanavn', $sprog_id)."</td>
	<td align=right>".findtekst('643|Faktura', $sprog_id)."</td>
	<td align=right>".findtekst('949|Salgspris', $sprog_id)."</td></tr>";
print "<tr><td colspan=5><hr></td></tr>";

$salgssum=0;
$salgsantal=0;

$query = db_select("select * from batch_salg where vare_id=$vare_id order by fakturadate",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	if ($row['ordre_id']) {
		$q1 = db_select("select ordrenr,firmanavn,fakturanr from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
		$r1 = db_fetch_array($q1); 
	} else $r1=NULL;
	print "<tr><td>".dkdato($row['fakturadate'])."</td>
		<td align=right>".dkdecimal($row['antal'])."</td>";
	if ($row['ordre_id'])	{
		print "<td align=right onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:d_ordre=window.open('../debitor/ordre.php?id=$row[ordre_id]&returside=../includes/luk.php','d_ordre','$jsvars')\"><u>$r1[firmanavn]</u></td>
		<td align=right onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:d_ordre=window.open('../debitor/ordre.php?id=$row[ordre_id]&returside=../includes/luk.php','d_ordre','$jsvars')\"><u>$r1[fakturanr]</u></td>";
	} else {
		print "<td align=\"right\">".findtekst('2237|Lagerreguleret', $sprog_id)."</td><td></td>";
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
print "<tr><td>".findtekst('2241|Solgt i alt', $sprog_id)."</td>
	<td align=right>".dkdecimal($salgsantal)."</td>
	<td align=right colspan=3>$tmp</td>";

print "<tr><td colspan=5><hr></td></tr>";
print "<tr><td colspan=5><br></td></tr>";
print "<tr><td colspan=5><br></td></tr>";

########################################################################################

print "<tr><td colspan=5 align=center><b>=== ".strtoupper(findtekst('2240|Ordrebeholdning', $sprog_id))." ===</b></td></tr>";
print "<tr><td>".findtekst('438|Dato', $sprog_id)."</td>
	<td align=right>".findtekst('916|Antal', $sprog_id)."</td>
	<td align=right>".findtekst('28|Firmanavn', $sprog_id)."</td>
	<td align=right>".findtekst('605|Ordre', $sprog_id)."</td>
	<td align=right>".findtekst('949|Salgspris', $sprog_id)."</td></tr>";

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
print "<tr><td>".findtekst('2242|Ordrebeh. i alt', $sprog_id)."</td>
	<td align=right>".dkdecimal($salgsantal)."</td>
	<td align=right colspan=3>$tmp</td>";

print "<tr><td colspan=5><hr></td></tr>";

##########################################################################

print "</tbody></table>";



?>
</html>

