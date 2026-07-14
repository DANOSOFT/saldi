<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------------lager/lagerstatus.php--- lap 5.0.0 --- 2026-02-06 ----
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
// Copyright (c) 2003-2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20140128 Ved søgning på modtaget / leveret tjekkes ikke for dato hvis angivet dato = dags dato da det gav forkert lagerantal for 
//          leverancer med leveringsdato > dd. Søg 20140128   
// 20161005 Lagerstatus opdateres ved ajourføring 20161005
// 20161222 $opdater flyttet sammen med $ret_behold;
// 20171102 CSV fil utf8_dekodes og dseparer med ;  
// 20180204 Div. tilretninger i forhold til varianter så beholdninger opdateres ikke ved diff'er - skal laves?  20180204
// 20180327 Større omskrivning omkring købspriser & kostpriser.
// 20210728 LOE Translated some texts here
// 20221010 PHR Zero stock was omitted in CSV
// 20221124 PHR	Added select between levdate (deelvery date) and fakturadate (invoicedate). 
// 20240910 PHR 'lagervalg' was omitted in CSV
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20251209 PHR closed products in now hidden by default
// 20260206 PHR	fiscal_year
// 20260217 PHR Removed fiscal_year from 'LG' serach
// 20260707 SZ Added Grid Framework sticky header and footer to Stock Status report
// 20260710 SZ Added Grid Framework sticky header+footer with pagination and internal-scroll grid

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Lagerstatus";

$linjebg=NULL;
$kostvalue=0;$lagervalue=0;$salgsvalue=0;
$dateType = 'levdate';

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

db_modify("update varer set lukket = '0' where lukket is NULL or lukket = ''",__FILE__ . " linje " . __LINE__);

# if ($popup) $returside="../includes/luk.php";
# else $returside="rapport.php";
$scv = $dato = $dateType = $opdater = $lagervalg = $ret_behold = $zStock = $saldi_lagerstatus = $showClosed = $varegruppe = NULL;

$returside="rapport.php";

(isset($_GET['opdater']))    ? $opdater     = $_GET['opdater']    : $opdater    = NULL;
(isset($_GET['ret_behold'])) ? $ret_behold  = $_GET['ret_behold'] : $ret_behold = NULL;
(isset($_GET['varegruppe'])) ? $varegruppe  = $_GET['varegruppe'] : $varegruppe = NULL;
if ($varegruppe == "0:Alle") $varegruppe=NULL;
else {
	setcookie("saldi_lagerstatus", $varegruppe);
	$returside="rapport.php?varegruppe=$varegruppe";
}
// 20260714 SZ - GET baseline (covers pagination/back-button/direct-link navigation, which is a plain
// GET request with no $_POST at all) first, then an actual search-form submit (POST) overlays on top -
// same GET-first/POST-overlay shape as includes/salgsstat.php's input handling. This also fixes
// showClosed reading from $_POST here (a typo - every other field in this branch reads $_GET), which
// meant showClosed silently reset on every plain GET navigation, incl. Grid Framework pagination.
if (isset($_GET['dato']) && $_GET['dato']) {
	$dato       = $_GET['dato'];
	$dateType   = 'levdate';
	$varegruppe = $_GET['varegruppe'];
	$lagervalg  = $_GET['lagervalg'];
	$zStock     = $_GET['zStock'];
	$showClosed = $_GET['showClosed'];
} elseif (!$varegruppe)  {
	$dato       = date("d-m-Y");
	$dateType   = 'levdate';
	$varegruppe = ($_COOKIE['saldi_lagerstatus']);
	if (!$varegruppe) $varegruppe = "0:Alle";
}
if (isset($_POST['dato']) && $_POST['dato']) {
	$dato       = $_POST['dato'];
	$dateType   = $_POST['dateType'];
	$varegruppe = $_POST['varegruppe'];
	$lagervalg  = $_POST['lagervalg'];
	$zStock     = $_POST['zStock'];
	$showClosed = $_POST['showClosed'];
	setcookie("saldi_lagerstatus", $varegruppe);
}
if (!$dateType) $dateType   = 'levdate';
$csv=if_isset($_GET['csv']);

$dd=date("Y-m-d");
$date=usdate($dato);
$dato=dkdato($date);

if ($date != $dd) $zStock = 'on';

$x=0;
$qtxt = "select kodenr, box9 from grupper where art = 'VG' and box8 = 'on' and fiscal_year = '$regnaar'";
$q1= db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r1=db_fetch_array($q1)) {
	$x++;
	$lagervare[$x]=$r1['kodenr'];
	$batchvare[$x]=$r1['box9'];
}
$lager[1]=1;
$lagernavn[1]='';
$x=0;
$qtxt = "select kodenr,beskrivelse from grupper where art = 'LG' order by kodenr";
$q1= db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r1=db_fetch_array($q1)) {
	$x++;
	$lager[$x]=$r1['kodenr'];
	$lagernavn[$x]=$r1['beskrivelse'];
}
if (count($lager)>=1) {
	$lager[0]=0;
	$lagernavn[0]='Alle';
	db_modify("update batch_kob set lager='1' where lager='0' or lager is NULL",__FILE__ . " linje " . __LINE__);
	db_modify("update batch_salg set lager='1' where lager='0' or lager is NULL",__FILE__ . " linje " . __LINE__);
}

$x=0;
list($a,$b)=explode(":",$varegruppe);

if ($a) {
	if ($lagervalg) {
		$qtxt = "select varer.id,varer.varenr,varer.enhed,varer.beskrivelse,varer.salgspris,varer.kostpris,varer.varianter,varer.gruppe,";
		$qtxt.= "lagerstatus.beholdning ";
		$qtxt.= "from varer,lagerstatus where varer.gruppe='$a' and lagerstatus.vare_id=varer.id and lagerstatus.lager='$lagervalg' ";
		if (!$zStock) $qtxt.= "and lagerstatus.beholdning != '0' ";
		if (!$showClosed) $qtxt.= "and varer.lukket = '0' ";
		$qtxt.="order by varer.varenr";
	} else {
	   $qtxt = "select * from varer where gruppe='$a' ";
	   if (!$zStock) $qtxt.= "and beholdning != '0' ";
	   if (!$showClosed) $qtxt.= "and varer.lukket = '0' ";
	   $qtxt.= "order by varenr";
	}
} else {
	if ($lagervalg) {
		$qtxt =" select varer.id,varer.varenr,varer.enhed,varer.beskrivelse,varer.salgspris,varer.kostpris,varer.varianter,varer.gruppe,";
		$qtxt.= "lagerstatus.beholdning ";
		$qtxt.= "from varer,lagerstatus where lagerstatus.vare_id=varer.id and lagerstatus.lager='$lagervalg' ";
		if (!$zStock) $qtxt.= "and lagerstatus.beholdning != '0' ";
	   if (!$showClosed) $qtxt.= "and varer.lukket = '0' ";
		$qtxt.= " order by varer.varenr";
	} else {
		$qtxt = "select * from varer ";
		if (!$zStock) {
			$qtxt.= "where beholdning != '0' ";
			if (!$showClosed) $qtxt.= "and varer.lukket = '0' ";
		} elseif (!$showClosed) $qtxt.= "where varer.lukket = '0' ";
		$qtxt.= "order by varenr";
	}
}
$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r2=db_fetch_array($q2)){
	if (in_array($r2['gruppe'], $lagervare)) {
		$x++;
		$vare_id[$x]=$r2['id'];
		$varenr[$x]=stripslashes($r2['varenr']);
		$enhed[$x]=stripslashes($r2['enhed']);
		$beholdning[$x]=$r2['beholdning'];
		$varianter[$x]=$r2['varianter']; #20180204
		$beskrivelse[$x]=stripslashes($r2['beskrivelse']);
		$salgspris[$x]=$r2['salgspris'];
		$kostpris[$x]=$r2['kostpris'];
	}
}
$vareantal=$x;
global $menu;

// 20260710 SZ Added Grid Framework sticky header+footer (mirrors includes/salgsstat.php /
//             lager/rapport.php): fixed blue header bar + filter form, internal-scroll data grid
//             with a sticky colgroup-driven column-title row, and a fixed footer with real
//             (server-side) pagination. $menu != 'S' keeps the old, unstyled chrome unchanged.
$lsGridMode = ($menu=='S');
$lsCsvHref = "lagerstatus.php?dato=$dato&varegruppe=$varegruppe&csv=1&zStock=$zStock&showClosed=$showClosed&lagervalg=$lagervalg";

if ($lsGridMode) {
	$lsTilbageIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';

	print "<style>html,body{margin:0;padding:0;height:100%;overflow:hidden;}</style>\n";
	print "<div id='lsPageFlex' style='display:flex;flex-direction:column;height:100vh;box-sizing:border-box;'>\n";
	print "<div style='flex:0 0 auto;padding:8px 8px 0 8px;box-sizing:border-box;background-color:$bgcolor;'>\n";
	print "<table width='100%' align='center' border='0' cellspacing='4' cellpadding='0'><tbody><tr>";
	print "<td width='10%' align='left'><a href='$returside' accesskey=L>
		   <button style='$buttonStyle; width:100%; display:flex; align-items:center; gap:5px; justify-content:flex-start; padding-left:3px;' onMouseOver=\"this.style.cursor='pointer'\">$lsTilbageIcon" . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>";
	print "<td width='80%' align='center' style='$topStyle'>" . ucfirst(findtekst('992|Lagerstatus', $sprog_id)) . "</td>";
	print "<td width='10%' align='center'><a href='$lsCsvHref' title=\"".findtekst('1655|Klik her for at eksportere til csv', $sprog_id)."\">
		   <button style='$buttonStyle; width:100%; min-height:20px; display:flex; align-items:center; gap:5px; justify-content:center;' onMouseOver=\"this.style.cursor='pointer'\">CSV</button></a></td>";
	print "</tr></tbody></table>\n";
} else {
	print "<table border=0 cellpadding=0 cellspacing=0 width=100%><tbody>";
	print "<tr><td colspan=9><table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
	print "<tr>";
	print "<td width=10% $top_bund><a href=$returside accesskey=L>".findtekst('30|Tilbage', $sprog_id)."</a></td>"; #20210708
	print "<td width=80% $top_bund align=center>".ucfirst(findtekst('992|Lagerstatus', $sprog_id))."</td>";
	print "<td width=10% $top_bund><a href='$lsCsvHref' ";
	print "title=\"".findtekst('1655|Klik her for at eksportere til csv', $sprog_id)."\">CSV</a></td>";
	print "</tr></td></tbody></table>\n";
}

($zStock)?$zStock="checked='checked'":$zStock=NULL;
($showClosed)?$showClosed="checked='checked'":$showClosed=NULL;

if (!$lsGridMode) print "<form action=lagerstatus.php method=post>";
if ($lsGridMode) print "<form action=lagerstatus.php method=post><table width='100%' cellpadding='2' cellspacing='0'><tbody><tr><td align='center'>";
else print "<tr><td colspan=\"7\" align=\"center\">";
if (count($lager)) {
	print findtekst('608|Lager', $sprog_id).": <select class=\"inputbox\" name=\"lagervalg\">";
	for ($x=0;$x<count($lager);$x++){
		if ($lagervalg==$lager[$x]) print "<option value='$lager[$x]'>$lager[$x] : $lagernavn[$x]</option>";
	}
	for ($x=0;$x<count($lager);$x++){
		if ($lagervalg!=$lager[$x]) print "<option value='$lager[$x]'>$lager[$x] : $lagernavn[$x]</option>";
	}
	print "</select>";
}
print "&nbsp;".findtekst('429|Varegruppe', $sprog_id).": <select class=\"inputbox\" name=\"varegruppe\">";
if ($varegruppe) print "<option>$varegruppe</option>";
if ($varegruppe!="0:Alle") print "<option>0:Alle</option>";
$qtxt = "select * from grupper where art = 'VG' and fiscal_year = '$regnaar' order by kodenr";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($q)){
	if ($varegruppe!=$row['kodenr'].":".$row['beskrivelse']) {print "<option>$row[kodenr]:$row[beskrivelse]</option>";}
}
print "</select>";
print "&nbsp;".findtekst('438|Dato', $sprog_id).":<input class=\"inputbox\" type=\"text\" name=\"dato\" value=\"$dato\" size=\"10\">";
print "&nbsp;".findtekst('2094|Dato', $sprog_id).":<select class=\"inputbox\" type=\"text\" name=\"dateType\">";
if ($dateType == 'levdate') {
	print "<option value='levdate'>Leveringsdato</option>";
	print "<option value='fakturadate'>Fakturadato</option>";
} else {
	print "<option value='fakturadate'>Fakturadato</option>";
	print "<option value='levdate'>Leveringsdato</option>";
}
print "</select>";
print "&nbsp;<span title='".findtekst('1656|Medtag varer, hvor beholdningen er 0', $sprog_id)."'>0 ".strtolower(findtekst('608|Lager', $sprog_id)).":<input type=\"checkbox\" name=\"zStock\" $zStock>";
print "&nbsp;<span title='Medtag udgåede varer''>Udgåede:<input type=\"checkbox\" name=\"showClosed\" $showClosed></span>";
if ($lsGridMode) {
	print "&nbsp;<input type=submit value=OK></td></tr></tbody></table></form>\n";
	print "</div>\n"; // <- close flex:0 wrapper around header bar + filter form
} else {
	print "</td>";
	print "<td  colspan=6 align=right><input type=submit value=OK></form></td></tr>";
	print "<tr><td colspan=9><hr></td></tr>";
}

// Real (server-side) pagination for the Grid Framework footer - same $ls_page/$ls_per_page
// query-param pattern the other Grid Framework reports use. $vareantal is the candidate item
// count before the per-item "has any activity" filter below (line ~369 in the original), so a
// page may show slightly fewer than $lsPerPage rows if some candidates in that index range are
// filtered out - an accepted approximation, same tradeoff made for lager/rapport.php's pagination.
if ($lsGridMode) {
	$lsValidPageSizes=array(50,100,250,500,100000);
	$lsPerPage=(int) (isset($_GET['ls_per_page']) ? $_GET['ls_per_page'] : 0);
	if (!in_array($lsPerPage,$lsValidPageSizes)) $lsPerPage=50;
	$lsPage=(int) (isset($_GET['ls_page']) ? $_GET['ls_page'] : 0);
	if ($lsPage<1) $lsPage=1;
	$lsTotalRows=$vareantal;
	$lsTotalPages=max(1,ceil($lsTotalRows/$lsPerPage));
	if ($lsPage>$lsTotalPages) $lsPage=$lsTotalPages;
	$lsPageStart=($lsPage-1)*$lsPerPage;
	$lsPageEnd=$lsPageStart+$lsPerPage;

	print "<style>
#lsGridWrapper { flex:1 1 auto; min-height:0; overflow-y:auto; overscroll-behavior:contain; width:100%; background-color:$bgcolor; padding:0 8px 68px 8px; box-sizing:border-box; }
#lsGridTable { border-collapse:separate; border-spacing:0; width:100%; table-layout:fixed; }
#lsGridTable th { position:sticky; top:0; z-index:10; padding:6px 4px; background-color:$bgcolor; box-sizing:border-box; text-align:left; }
#lsGridTable td { box-sizing:border-box; padding:4px; }
#lsGridTable th.text-right { text-align:right; }
</style>\n";
	$lsColgroupHtml = "<colgroup><col style='width:10%'><col style='width:6%'><col style='width:34%'><col style='width:8%'><col style='width:8%'><col style='width:8%'><col style='width:9%'><col style='width:9%'><col style='width:8%'></colgroup>";
	print "<div id='lsGridWrapper'><table id='lsGridTable' width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\">$lsColgroupHtml<tbody>";
	print "<tr class='ls-col-title-row'><th>".findtekst('917|Varenr.', $sprog_id).".</th><th>".findtekst('945|Enhed', $sprog_id)."</th><th>".findtekst('914|Beskrivelse', $sprog_id)."</th>
	<th class='text-right'><span title='".findtekst('1657|Antal enheder købt før den', $sprog_id)." $dato'>".findtekst('2744|Tilgang', $sprog_id)."</span></th>
	<th class='text-right'><span title='".findtekst('1658|Antal enheder solgt før den', $sprog_id)." $dato'>".findtekst('2745|Afgang', $sprog_id)."</span></th>
	<th class='text-right'><span title='".findtekst('1659|Lagerbeholdning pr', $sprog_id).". $dato'>".findtekst('916|Antal', $sprog_id)."</span></th>
	<th class='text-right'><span title='".findtekst('1660|Købsværdi af lagerbeholdning (Reel købspris)', $sprog_id)."'>".findtekst('978|Købspris', $sprog_id)."</span></th>
	<th class='text-right'><span title='".findtekst('1661|Kostpris af lagerbeholdning (fra varekort)', $sprog_id)."'>".findtekst('950|Kostpris', $sprog_id)."</span></th>
	<th class='text-right'><span title='".findtekst('1662|Salgsværdi af lagerbeholdning (fra varekort)', $sprog_id)."'>".findtekst('949|Salgspris', $sprog_id)."</span></th></tr>";
} else {
	print "<tr><td width=8%>".findtekst('917|Varenr.', $sprog_id).".</td><td width=5%>".findtekst('945|Enhed', $sprog_id)."</td><td width=48%>".findtekst('914|Beskrivelse', $sprog_id)."</td>
	<td align=right width=5%><span title='".findtekst('1657|Antal enheder købt før den', $sprog_id)." $dato'>".findtekst('2744|Tilgang', $sprog_id)."</span></td>
	<td align=right width=5%><span title='".findtekst('1658|Antal enheder solgt før den', $sprog_id)." $dato'>".findtekst('2745|Afgang', $sprog_id)."</span></td>
	<td align=right width=5%><span title='".findtekst('1659|Lagerbeholdning pr', $sprog_id).". $dato'>".findtekst('916|Antal', $sprog_id)."</span></td>
	<td align=right width=8%><span title='".findtekst('1660|Købsværdi af lagerbeholdning (Reel købspris)', $sprog_id)."'>".findtekst('978|Købspris', $sprog_id)."</span></td>
	<td align=right width=8%><span title='".findtekst('1661|Kostpris af lagerbeholdning (fra varekort)', $sprog_id)."'>".findtekst('950|Kostpris', $sprog_id)."</span></td>
	<td align=right width=8%><span title='".findtekst('1662|Salgsværdi af lagerbeholdning (fra varekort)', $sprog_id)."'>".findtekst('949|Salgspris', $sprog_id)."</span></td></tr>";
}

if ($csv) {
	$fp=fopen("../temp/$db/lagerstatus.csv","w");
	$linje="Varenr".";"."Enhed".";"."Beskrivelse".";"."Købt".";"."Solgt".";"."Antal".";"."Købspris".";"."Kostpris".";"."Salgspris";
#	$linje=mb_convert_encoding($linje, 'ISO-8859-1', 'UTF-8');
	fwrite($fp,"$linje\n");
}
 
for($x=1; $x<=$vareantal; $x++) {
	// 20260710 SZ - capture this item's row so it can be skipped when outside the current Grid
	// Framework page window (real server-side pagination, matching includes/salgsstat.php /
	// lager/rapport.php). ob_start() only intercepts print/echo output - the db_modify() stock
	// corrections and fwrite($fp,...) CSV export below are unaffected and still run for every
	// item regardless of page, exactly as before.
	if ($lsGridMode) ob_start();
	$handlet[$x]=0;
	$batch_k_antal[$x]=0;$batch_t_antal[$x]=0;$batch_pris[$x]=0;$batch_s_antal[$x]=0;
	$qtxt="select sum(antal) as antal from batch_kob where vare_id=$vare_id[$x]";
	if ($lagervalg) $qtxt.=" and lager='$lagervalg'";
	($dateType == 'levdate')?$dt = 'kobsdate':$dt = $dateType;
	if ($date!=$dd) $qtxt.=" and $dt <= '$date'";
	$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$batch_k_antal[$x]=$r1['antal'];
	$batch_t_antal[$x]=$r1['antal'];
	$qtxt="select sum(antal) as antal from batch_salg where vare_id=$vare_id[$x]";
	if ($lagervalg) $qtxt.=" and lager='$lagervalg'";
	($dateType == 'levdate')?$dt = 'salgsdate':$dt = $dateType;
	if ($date!=$dd) $qtxt.=" and $dt <= '$date'";
	$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$batch_s_antal[$x]=$r1['antal'];
	$batch_t_antal[$x]-=$r1['antal'];

/*
	if ($vare_id[$x]==454) #cho "Bt $batch_t_antal[$x]<br>";		
	$qtxt="select * from batch_kob where vare_id=$vare_id[$x]"; #20140128
	if ($lagervalg)	$qtxt.=" and lager='$lagervalg'"; #20140128
	if ($date!=$dd) $qtxt.=" and kobsdate <= '$date'";
	$qtxt.=" order by kobsdate desc";
if ($vare_id[$x]==454) #cho "BP $qtxt	<br>";		
	$antal=0;
	$pris=0;
	$q1=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r1=db_fetch_array($q1)){
		if ($antal+=$r1['antal']<=$batch_t_antal[$x]) {
			$pris+=$r1['antal']*$r1['pris'];
			$antal+=$r1['antal'];
		} else {
			$pris+=($batch_t_antal[$x]-$antal)*$r1['pris'];
		}
	}
	$batch_pris[$x]=$pris;
*/	
/*
		$batch_k_antal[$x]=$batch_k_antal[$x]+$r1['antal'];
		$batch_t_antal[$x]=$batch_t_antal[$x]+$r1['antal'];
		$batch_pris[$x]=$batch_pris[$x]+($r1['pris']*$r1['antal']);
if ($vare_id[$x]==454) #cho "BP $batch_pris[$x]<br>";		
		$handlet[$x]=1;
		if (isset($batchvare[$x]) && $batchvare[$x]) {
			$qtxt="select * from batch_salg where batch_kob_id=$r1[id]"; #20140128
			if ($date!=$dd) $qtxt.=" and salgsdate <= '$date'";
			$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r2=db_fetch_array($q2)){
				$batch_s_antal[$x]=$batch_s_antal[$x]+$r2['antal'];
				$batch_t_antal[$x]=$batch_t_antal[$x]-$r2['antal'];
				$batch_pris[$x]=$batch_pris[$x]-($r1['pris']*$r2['antal']);
			}
		}	
#	db_modify("update varer set beholdning = '$batch_t_antal[$x]' where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);  
	}
*/

	if (!isset($batchvare[$x])) $batchvare[$x]=NULL;
	if (!$batchvare[$x]) {
/*
	$tmp=$batch_t_antal[$x];
		$qtxt="select * from batch_salg where vare_id=$vare_id[$x]"; #20140128
		if ($lagervalg) $qtxt.=" and lager='$lagervalg'";
		if ($date!=$dd) $qtxt.=" and salgsdate <= '$date'";
		$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r2=db_fetch_array($q2)){
			$batch_s_antal[$x]=afrund($batch_s_antal[$x]+$r2['antal'],2);
			$batch_t_antal[$x]=afrund($batch_t_antal[$x]-$r2['antal'],2);
			$handlet[$x]=1;
#			$batch_pris[$x]=$batch_pris[$x]-($r1['pris']*$r2['antal']);
		}
		if ($tmp*$batch_t_antal[$x]!=0) $batch_pris[$x]=$batch_pris[$x]/$tmp*$batch_t_antal[$x];
		else $batch_pris[$x]=0;
*/	
	if ($batch_k_antal[$x]) {
		$pris=0;
		$antal=0;
		$qtxt="select antal,pris from batch_kob where vare_id=$vare_id[$x] and antal >= 1"; #20140128
		if ($lagervalg) $qtxt.=" and lager='$lagervalg'";
		($dateType == 'levdate')?$dt = 'kobsdate':$dt = $dateType;
		if ($date!=$dd) $qtxt.=" and $dt <= '$date'";
		$qtxt.=" order by kobsdate desc";
		$q1=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while($r1=db_fetch_array($q1)) {
			if ($antal+$r1['antal'] <= $batch_t_antal[$x]) {
				$antal+=$r1['antal'];
				$pris+=$r1['antal']*$r1['pris'];
			} elseif ($antal < $batch_t_antal[$x] && $antal+$r1['antal'] > $batch_t_antal[$x]) {
				$pris+=$r1['pris']*($batch_t_antal[$x]-$antal);
				$antal=$batch_t_antal[$x];
			}
		}
		($antal)?$batch_pris[$x]=$pris:$batch_pris[$x]=0;
	}
	}
	if (isset($_GET['ajour']) && $_GET['ajour']==1 && $batch_t_antal[$x] != $beholdning[$x]) {
		$diff=$batch_t_antal[$x];
		db_modify("update varer set beholdning = '$batch_t_antal[$x]' where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
		$ls_id=array();
		$ls=0;
		$q2=db_select("select * from lagerstatus where vare_id='$vare_id[$x]' order by lager,variant_id",__FILE__ . " linje " . __LINE__);
		while ($r2=db_fetch_array($q2)){
			$diff-=$r2['beholdning'];
			$ls_id[$ls]=$r2['id'];
			$ls_lager[$ls]=$r2['lager'];
			$ls_variant[$x]=$r2['variant_id'];
			$ls++;
		}
		if ($diff && !$varianter[$x]) { #20161005 + 20180204 
			db_modify("insert into lagerstatus(vare_id,beholdning,lager) values ('$vare_id[$x]','$diff','$tmp')",__FILE__ . " linje " . __LINE__);
		}
	}
	if ($batch_k_antal[$x]||$batch_s_antal[$x]||$beholdning[$x]||$handlet[$x]) {
		if ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		else {$linjebg=$bgcolor; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if ($popup) print "<td onClick=\"javascript:varespor=window.open('varespor.php?vare_id=$vare_id[$x]','varespor','$jsvars')\" onMouseOver=\"this.style.cursor = 'pointer'\"><u>$varenr[$x]</u><br></td>";
		else print "<td><a href=varespor.php?vare_id=$vare_id[$x]>$varenr[$x]<br></td>";
		print	"<td>$enhed[$x]<br></td><td>$beskrivelse[$x]<br></td>
		<td align=right>".str_replace(".",",",$batch_k_antal[$x]*1)."<br></td><td align=right>".str_replace(".",",",$batch_s_antal[$x]*1)."<br></td>";
		if ($date==$dd && afrund($batch_t_antal[$x],1)!=afrund($beholdning[$x],1) && !$lagervalg) {
			if ($ret_behold==2 || ($opdater && $vare_id[$x]==$opdater)) {
				if (count($lager) >= 1) {
					$ny_beholdning[$x]=0;
					for ($y=1;$y<count($lager);$y++) {
						$r2=db_fetch_array(db_select("select sum(antal) as antal from batch_kob where vare_id='$vare_id[$x]' and lager='$lager[$y]'",__FILE__ . " linje " . __LINE__));
						$lagerbeh[$y]=$r2['antal'];
						$r2=db_fetch_array(db_select("select sum(antal) as antal from batch_salg where vare_id='$vare_id[$x]' and lager='$lager[$y]'",__FILE__ . " linje " . __LINE__));
						$lagerbeh[$y]-=$r2['antal'];
						$ny_beholdning[$x]+=$lagerbeh[$y];
						if (!$varianter[$x]) { #20180204
							db_modify("update lagerstatus set beholdning = '$lagerbeh[$y]' where lager='$lager[$y]' and vare_id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
						}
						db_modify("update varer set beholdning = '$ny_beholdning[$x]' where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
					}	
				}  
/* else { #overflødig ->
				$r2=db_fetch_array(db_select("select sum(antal) as antal from batch_kob where vare_id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
					$lagerbeh[$y]=$r2['antal'];
					$r2=db_fetch_array(db_select("select sum(antal) as antal from batch_salg where vare_id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
					$lagerbeh[$y]-=$r2['antal'];
					$ny_beholdning[$x]+=$lagerbeh[$y];
					$qtxt="select id from lagerstatus where vare_id='$vare_id[$x]'";
					$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					if ($r2['id']) {
						$qtxt="delete from lagerstatus where vare_id='$vare_id[$x]' and id !='$r2[id]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt="update lagerstatus set beholdning = '$ny_beholdning[$x]' where id='$r2[id]'";
					} else $qtxt="insert into lagerstatus(vare_id,beholdning,lager) values ('$vare_id[$x]','$ny_beholdning[$x]','0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
*/
#				db_modify("update varer set beholdning = '$ny_beholdning[$x]' where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
				$beholdning[$x]=$ny_beholdning[$x];
				print "<td align=right>".str_replace(".",",",$batch_t_antal[$x]*1)."<br></td>";
			} else {
				print "<td align=right title=\"".findtekst('980|Beholdning', $sprog_id)." (".str_replace(".",",",$beholdning[$x]*1).") ".findtekst('1663|stemmer ikke med det antal som er købt og solgt. Klik her for at opdatere beholdning', $sprog_id)."\"><a href=".$_SERVER['PHP_SELF']."?opdater=$vare_id[$x] onclick=\"return confirm('Opdater lagerbeholdning fra ".dkdecimal($beholdning[$x],2)." til ".dkdecimal($batch_t_antal[$x],2)." for denne vare?')\"><span style=\"color: rgb(255, 0, 0);\">".str_replace(".",",",$batch_t_antal[$x]*1)."</span></a><br></td>";
				$ret_behold=1;
			}
		} else print "<td align=right>".str_replace(".",",",$batch_t_antal[$x]*1)."<br></td>";
		
		print "<td align=right>".dkdecimal($batch_pris[$x])."<br></td>
		<td align=right title='stkpris:".dkdecimal($kostpris[$x])."'>".dkdecimal($kostpris[$x]*$batch_t_antal[$x])."<br></td>
		<td align=right>".dkdecimal($salgspris[$x]*$batch_t_antal[$x])."<br></td></tr>";
		if ($csv) {
			$linje="$varenr[$x]".";"."$enhed[$x]".";"."$beskrivelse[$x]".";"."$batch_k_antal[$x]".";"."$batch_s_antal[$x]".";".$batch_t_antal[$x].";".dkdecimal($batch_pris[$x]).";".dkdecimal($kostpris[$x]*$batch_t_antal[$x]).";".dkdecimal($salgspris[$x]*$batch_t_antal[$x]);
#			$linje=mb_convert_encoding($linje, 'ISO-8859-1', 'UTF-8');
			fwrite($fp,"$linje\n");
		}
		$lagervalue=$lagervalue+$batch_pris[$x];$kostvalue=$kostvalue+$kostpris[$x]*$batch_t_antal[$x]; $salgsvalue=$salgsvalue+($salgspris[$x]*$batch_t_antal[$x]);
	}
	if ($lsGridMode) {
		$lsRowHtml = ob_get_clean();
		if ($x-1 >= $lsPageStart && $x-1 < $lsPageEnd) print $lsRowHtml;
	}
}
if ($csv){
	fclose($fp);
	print "<BODY onLoad=\"JavaScript:window.open('../temp/$db/lagerstatus.csv' ,'' ,'$jsvars');\">\n";
}
print "<tr><td colspan=9><hr></td></tr>";
print "<tr><td colspan=2><br></td><td>".findtekst('2235|Samlet lagerværdi pr.', $sprog_id)." $dato<br></td><td align=right><br></td><td align=right><br></td>
<td align=right><br></td><td align=right>".dkdecimal($lagervalue)."<br></td>
<td align=right>".dkdecimal($kostvalue)."<br></td>
<td align=right>".dkdecimal($salgsvalue)."<br></td></tr>";
if ($ret_behold==1) print "<tr><td><a href=\"lagerstatus.php?varegruppe=$varegruppe&ret_behold=2\">Ret skæve lagertal</a></td></tr>";

if ($lsGridMode) {
	// Close #lsGridTable/#lsGridWrapper opened above, then print the fixed Grid Framework footer
	// with real (server-side) pagination - same link/markup/CSS technique includes/salgsstat.php
	// and lager/rapport.php use for their own page-footer bars.
	print "</tbody></table>"; // close #lsGridTable
	print "</div>\n"; // close #lsGridWrapper

	$lsTxt1 = lcfirst(findtekst('2767|Af', $sprog_id));
	$lsTxt2 = findtekst('2125|Linjer pr. side', $sprog_id);
	$lsOffsetFrom = $lsTotalRows ? (($lsPage - 1) * $lsPerPage) + 1 : 0;
	$lsOffsetTo = min($lsTotalRows, $lsPage * $lsPerPage);
	$lsBaseUrl = "lagerstatus.php?" . http_build_query(array(
		'dato' => $dato,
		'varegruppe' => $varegruppe,
		'lagervalg' => $lagervalg,
		'dateType' => $dateType,
		'zStock' => $zStock,
		'showClosed' => $showClosed,
	));
	$lsPrevIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="#000000"><path d="M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z"/></svg>';
	$lsNextIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="#000000"><path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z"/></svg>';

	print "<style>
#lsPageFooterBar { position:fixed; left:0; right:0; bottom:0; width:100%; margin:0; z-index:1000; background-color:$bgcolor; border-top:1px solid #b8bec8; padding:6px 12px; display:flex; align-items:center; justify-content:flex-end; gap:20px; flex-wrap:wrap; box-sizing:border-box; line-height:1; }
#lsPageFooterBar #lsNavButtons { display:flex; align-items:center; gap:3px; }
#lsPageFooterBar #lsNavButtons .navbutton { height:20px; min-width:20px; padding:0 4px; display:inline-flex; align-items:center; justify-content:center; background:#f0f0f0; color:#000; border:1px solid #b8bec8; border-radius:4px; text-decoration:none; }
#lsPageFooterBar #lsNavButtons a.navbutton { cursor:pointer; }
#lsPageFooterBar #lsNavButtons span.navbutton { opacity:0.5; }
#lsPageFooterBar #lsNavButtons .navbutton.current { text-decoration:underline; }
</style>\n";
	print "<div id='lsPageFooterBar'>";
	print "<span id='lsPageStatus'>" . ($lsTotalRows ? "$lsOffsetFrom-$lsOffsetTo $lsTxt1 $lsTotalRows" : "0 $lsTxt1 0") . "</span>";
	print "<span>$lsTxt2 <select id='lsPageSize' onchange=\"window.location.href='" . htmlspecialchars($lsBaseUrl, ENT_QUOTES) . "&ls_page=1&ls_per_page=' + this.value;\">";
	foreach (array(50, 100, 250, 500, 100000) as $lsOpt) {
		$lsSel = ($lsOpt == $lsPerPage) ? " selected" : "";
		$lsLabel = ($lsOpt == 100000) ? "Alle" : $lsOpt;
		print "<option value='$lsOpt'$lsSel>$lsLabel</option>";
	}
	print "</select></span>";
	print "<span id='lsNavButtons'>";
	if ($lsPage > 1)
		print "<a class='navbutton' href='" . htmlspecialchars($lsBaseUrl, ENT_QUOTES) . "&ls_page=" . ($lsPage - 1) . "&ls_per_page=$lsPerPage'>$lsPrevIcon</a>";
	else
		print "<span class='navbutton'>$lsPrevIcon</span>";
	$lsPageRange = 2;
	$lsStartPage = max(1, $lsPage - $lsPageRange);
	$lsEndPage = min($lsTotalPages, $lsPage + $lsPageRange);
	if ($lsStartPage > 1) {
		print "<a class='navbutton' href='" . htmlspecialchars($lsBaseUrl, ENT_QUOTES) . "&ls_page=1&ls_per_page=$lsPerPage'>1</a>";
		if ($lsStartPage > 2)
			print "<span>...</span>";
	}
	for ($lsP = $lsStartPage; $lsP <= $lsEndPage; $lsP++) {
		if ($lsP == $lsPage)
			print "<span class='navbutton current'>$lsP</span>";
		else
			print "<a class='navbutton' href='" . htmlspecialchars($lsBaseUrl, ENT_QUOTES) . "&ls_page=$lsP&ls_per_page=$lsPerPage'>$lsP</a>";
	}
	if ($lsEndPage < $lsTotalPages) {
		if ($lsEndPage < $lsTotalPages - 1)
			print "<span>...</span>";
		print "<a class='navbutton' href='" . htmlspecialchars($lsBaseUrl, ENT_QUOTES) . "&ls_page=$lsTotalPages&ls_per_page=$lsPerPage'>$lsTotalPages</a>";
	}
	if ($lsPage < $lsTotalPages)
		print "<a class='navbutton' href='" . htmlspecialchars($lsBaseUrl, ENT_QUOTES) . "&ls_page=" . ($lsPage + 1) . "&ls_per_page=$lsPerPage'>$lsNextIcon</a>";
	else
		print "<span class='navbutton'>$lsNextIcon</span>";
	print "</span>";
	print "</div>\n";
	print "</div>\n"; // close #lsPageFlex
} else {
?>
</tbody></table>
</body></html>
<?php
}
