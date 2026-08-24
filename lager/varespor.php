<?php

// ------------lager/varespor.php---------------------patch 5.0.0--2026.08.19--
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
// Copyright (c) 2004-2026 DANOSOFT ApS
// ----------------------------------------------------------------------------
//
// 20140626 Tilføjet lagerregulering og ændret variabelnavn for dækningsbidrag.
// 20150902	Linjer med 0 i antal undertrykkes og linjer uden ordre_id vises som Lagerreguleret
// 20260819 CDX/PHR Saml fragmenterede batchlinjer, vis lager og saml lagerreguleringer nederst.

function varesporBatchRows($table, $vareId, $excludeZero = false) {
	if ($table !== 'batch_kob' && $table !== 'batch_salg') {
		return array();
	}
	$where = "vare_id=" . (int)$vareId;
	if ($excludeZero) {
		$where .= " and antal != '0'";
	}
	$q = db_select("select * from $table where $where order by fakturadate,id", __FILE__ . " linje " . __LINE__);
	$groups = array();
	$order = array();
	while ($row = db_fetch_array($q)) {
		$lineId = (int)$row['linje_id'];
		$stockNo = (int)$row['lager'];
		if ($lineId > 0) {
			$key = 'line:' . $lineId;
		} elseif (!empty($row['modtime'])) {
			$key = 'time:' . $row['modtime'] . ':stock:' . $stockNo;
		} else {
			$key = 'row:' . $row['id'];
		}
		if (!isset($groups[$key])) {
			$groups[$key] = $row;
			$groups[$key]['antal'] = 0;
			$groups[$key]['total_price'] = 0;
			$groups[$key]['stock_numbers'] = array();
			$order[] = $key;
		}
		$groups[$key]['antal'] += (float)$row['antal'];
		$groups[$key]['total_price'] += (float)$row['pris'] * (float)$row['antal'];
		if ($stockNo > 0) {
			$groups[$key]['stock_numbers'][$stockNo] = $stockNo;
		}
	}
	$result = array();
	foreach ($order as $key) {
		ksort($groups[$key]['stock_numbers'], SORT_NUMERIC);
		$groups[$key]['stock_display'] = implode(', ', $groups[$key]['stock_numbers']);
		$result[] = $groups[$key];
	}
	return $result;
}

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

$vare_id=(int)$_GET['vare_id'];

$stockNames = array();
$stockQuery = db_select("select kodenr,beskrivelse from grupper where art='LG' order by kodenr", __FILE__ . " linje " . __LINE__);
while ($stockRow = db_fetch_array($stockQuery)) {
	$stockNames[(int)$stockRow['kodenr']] = $stockRow['beskrivelse'];
}
$showStock = count($stockNames) > 1;

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

$batchColspan = $showStock ? 6 : 5;
print "<tr><td colspan=$batchColspan align=center><b>=== ".strtoupper(findtekst('2744|Tilgang', $sprog_id))." ===</b></td></tr>";
print "<tr><td>".findtekst('438|Dato', $sprog_id)."</td>
	<td align=right>".findtekst('916|Antal', $sprog_id)."</td>
	" . ($showStock ? "<td align=right>Lager</td>" : "") . "
	<td align=right>".findtekst('28|Firmanavn', $sprog_id)."</td>
	<td align=right>".findtekst('1515|Købsordre', $sprog_id)."</td>
	<td align=right>".findtekst('978|Købspris', $sprog_id)."</td></tr>";

print "<tr><td colspan=$batchColspan><hr></td></tr>";

$kontosum=0;
$z=0;
$kobsliste=array();
$stockAdjustments=array();
foreach (varesporBatchRows('batch_kob', $vare_id, true) as $row) {
	if (!$row['ordre_id']) {
		$row['signed_antal'] = abs((float)$row['antal']);
		$stockAdjustments[] = $row;
		continue;
	}
	if ($row['ordre_id']) {
		$q1 = db_select("select ordrenr, firmanavn from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
		$r1 = db_fetch_array($q1); 
	} else $r1=NULL;
	print "<tr><td>".dkdato($row['fakturadate'])."</td>
		<td align=right>".dkdecimal($row['antal'])."</td>";
		if ($showStock) {
			$stockTitle = array();
			foreach ($row['stock_numbers'] as $stockNo) if (isset($stockNames[$stockNo])) $stockTitle[] = $stockNames[$stockNo];
			print "<td align='right' title='".htmlspecialchars(implode(', ', $stockTitle), ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($row['stock_display'], ENT_QUOTES, 'UTF-8')."</td>";
		}
		if ($r1['firmanavn']) print "<td align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$row[ordre_id]&returside=../includes/luk.php','k_ordre','$jsvars')\"><u>$r1[firmanavn]</u></td>";
		print "<td align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$row[ordre_id]&returside=../includes/luk.php','k_ordre','$jsvars')\"><u>$r1[ordrenr]</u></td>";
	$kobsantal=$kobsantal+$row['antal'];
	$kobspris=$row['total_price'];
	$kobssum=$kobssum+$kobspris;
	$tmp=dkdecimal($kobspris);
	print "<td align=right>$tmp</td>";
}
$tmp=dkdecimal($kobssum);
print "<tr><td colspan=$batchColspan><hr></td></tr>";
print "<tr><td>".findtekst('2238|Købt i alt', $sprog_id)."</td>
		<td align=right>".dkdecimal($kobsantal)."</td>
		<td align=right colspan=".($showStock ? 4 : 3).">$tmp</td>";
		
print "<tr><td colspan=$batchColspan><hr></td></tr>";
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
print "<tr><td colspan=$batchColspan align=center><b>=== ".strtoupper(findtekst('2745|Afgang', $sprog_id))." ===</b></td></tr>";
print "<tr><td>".findtekst('438|Dato', $sprog_id)."</td>
	<td align=right>".findtekst('916|Antal', $sprog_id)."</td>
	" . ($showStock ? "<td align=right>Lager</td>" : "") . "
	<td align=right>".findtekst('28|Firmanavn', $sprog_id)."</td>
	<td align=right>".findtekst('643|Faktura', $sprog_id)."</td>
	<td align=right>".findtekst('949|Salgspris', $sprog_id)."</td></tr>";
print "<tr><td colspan=$batchColspan><hr></td></tr>";

$salgssum=0;
$salgsantal=0;

foreach (varesporBatchRows('batch_salg', $vare_id) as $row) {
	if (!$row['ordre_id']) {
		$row['signed_antal'] = abs((float)$row['antal']) * -1;
		$stockAdjustments[] = $row;
		continue;
	}
	if ($row['ordre_id']) {
		$q1 = db_select("select ordrenr,firmanavn,fakturanr from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
		$r1 = db_fetch_array($q1); 
	} else $r1=NULL;
	print "<tr><td>".dkdato($row['fakturadate'])."</td>
		<td align=right>".dkdecimal($row['antal'])."</td>";
	if ($showStock) {
		$stockTitle = array();
		foreach ($row['stock_numbers'] as $stockNo) if (isset($stockNames[$stockNo])) $stockTitle[] = $stockNames[$stockNo];
		print "<td align='right' title='".htmlspecialchars(implode(', ', $stockTitle), ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($row['stock_display'], ENT_QUOTES, 'UTF-8')."</td>";
	}
	if ($row['ordre_id'])	{
		print "<td align=right onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:d_ordre=window.open('../debitor/ordre.php?id=$row[ordre_id]&returside=../includes/luk.php','d_ordre','$jsvars')\"><u>$r1[firmanavn]</u></td>
		<td align=right onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:d_ordre=window.open('../debitor/ordre.php?id=$row[ordre_id]&returside=../includes/luk.php','d_ordre','$jsvars')\"><u>$r1[fakturanr]</u></td>";
	}
	$salgsantal=$salgsantal+$row['antal'];
	$salgspris=$row['total_price'];
	$salgssum=$salgssum+$salgspris;
	$tmp=dkdecimal($salgspris);
	print "<td align=right>$tmp</td>";
	$dbd=$salgspris-$kobspris;
	$antal=$antal+$row['antal'];
}
$tmp=dkdecimal($salgssum);
print "<tr><td colspan=$batchColspan><hr></td></tr>";
print "<tr><td>".findtekst('2241|Solgt i alt', $sprog_id)."</td>
		<td align=right>".dkdecimal($salgsantal)."</td>
		<td align=right colspan=".($showStock ? 4 : 3).">$tmp</td>";

print "<tr><td colspan=$batchColspan><hr></td></tr>";
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

usort($stockAdjustments, function($a, $b) {
	$dateCompare = strcmp((string)$a['fakturadate'], (string)$b['fakturadate']);
	if ($dateCompare) return $dateCompare;
	return (int)$a['id'] - (int)$b['id'];
});

print "<tr><td colspan=$batchColspan><br></td></tr>";
print "<tr><td colspan=$batchColspan align=center><b>=== LAGERREGULERINGER ===</b></td></tr>";
print "<tr><td>".findtekst('438|Dato', $sprog_id)."</td>
	<td align=right>".findtekst('916|Antal', $sprog_id)."</td>
	" . ($showStock ? "<td align=right>Lager</td>" : "") . "
	<td colspan=".($showStock ? 3 : 3)."></td></tr>";
print "<tr><td colspan=$batchColspan><hr></td></tr>";

$adjustmentTotal = 0;
foreach ($stockAdjustments as $row) {
	$adjustmentTotal += $row['signed_antal'];
	print "<tr><td>".dkdato($row['fakturadate'])."</td>
		<td align=right>".dkdecimal($row['signed_antal'])."</td>";
	if ($showStock) {
		$stockTitle = array();
		foreach ($row['stock_numbers'] as $stockNo) if (isset($stockNames[$stockNo])) $stockTitle[] = $stockNames[$stockNo];
		print "<td align='right' title='".htmlspecialchars(implode(', ', $stockTitle), ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($row['stock_display'], ENT_QUOTES, 'UTF-8')."</td>";
	}
	print "<td colspan=3></td></tr>";
}
print "<tr><td colspan=$batchColspan><hr></td></tr>";
print "<tr><td>I alt</td><td align=right>".dkdecimal($adjustmentTotal)."</td><td colspan=".($showStock ? 4 : 3)."></td></tr>";
print "<tr><td colspan=$batchColspan><hr></td></tr>";

##########################################################################

print "</tbody></table>";



?>
</html>
