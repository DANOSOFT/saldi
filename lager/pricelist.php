<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------------lager/pricelist.php--- lap 4.0.1 --- 2025-05-03 ----
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
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20250503 LOE reordered mix-up text_id from tekster.csv in findtekst()
// 20260707 SZ Added Grid Framework sticky header and footer to Price List report
// 20260711 SZ Added Grid Framework sticky header+footer with pagination and internal-scroll grid
// 20260711 SZ Fixed duplicate entries in Category dropdown: grupper has one row per fiscal_year,
//             the VG group queries were missing that filter (same fix pattern as rapport.php/lagerstatus.php)

 
@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Prisliste";

$linjebg=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

$returside="rapport.php";

global $menu;

$zero_stock  		= false;
$show_all_products	= false;
$show_antal    		= false;
$show_enhed    		= false;
$show_kostpris    	= true;
$show_tier_price    = true;
$show_salgspris 	= true;
$show_retail_price  = true;
$custom_text		= "";

$varegruppe = if_isset($_GET['varegruppe']);
if ($varegruppe == "0:Alle") $varegruppe=NULL;
else {
	setcookie("saldi_pricelist", $varegruppe);
	$returside="rapport.php?varegruppe=$varegruppe";
}
// 20260714 SZ - GET baseline (covers pagination/back-button/direct-link navigation - a plain GET
// request with no $_POST at all) first, then an actual search-form submit (POST) overlays on top -
// same GET-first/POST-overlay shape as includes/salgsstat.php's input handling. Replaces the previous
// if($_POST)/elseif($_GET csv|autoprint)/elseif(default) fork, whose gaps meant every filter below
// silently reset to its hard-coded default on a plain pagination click (see $plBaseUrl further down,
// which already carries all of these as GET params for exactly this purpose).
$csv       = if_isset($_GET['csv']);
$autoprint = if_isset($_GET['autoprint']);
if (isset($_GET['lagervalg']))         $lagervalg         = $_GET['lagervalg'];
if (isset($_GET['zero_stock']))        $zero_stock        = $_GET['zero_stock'];
if (isset($_GET['show_all_products'])) $show_all_products = $_GET['show_all_products'];
if (isset($_GET['show_antal']))        $show_antal        = $_GET['show_antal'];
if (isset($_GET['show_enhed']))        $show_enhed        = $_GET['show_enhed'];
if (isset($_GET['show_kostpris']))     $show_kostpris     = $_GET['show_kostpris'];
if (isset($_GET['show_tier_price']))   $show_tier_price   = $_GET['show_tier_price'];
if (isset($_GET['show_salgspris']))    $show_salgspris    = $_GET['show_salgspris'];
if (isset($_GET['show_retail_price'])) $show_retail_price = $_GET['show_retail_price'];
if (isset($_GET['custom_text']))       $custom_text       = $_GET['custom_text'];

if (isset($_POST['submit'])) {
	$submit 			= $_POST['submit'];
	$varegruppe 		= $_POST['varegruppe'];
	$lagervalg  		= $_POST['lagervalg'];
	$zero_stock     	= $_POST['zero_stock'];
	$show_all_products 	= $_POST['show_all_products'];
	$show_antal    		= $_POST['show_antal'];
	$show_enhed    		= $_POST['show_enhed'];
	$show_kostpris    	= $_POST['show_kostpris'];
	$show_tier_price    = $_POST['show_tier_price'];
	$show_salgspris 	= $_POST['show_salgspris'];
	$show_retail_price  = $_POST['show_retail_price'];
	$custom_text  		= $_POST['custom_text'];
	setcookie("saldi_pricelist", $varegruppe);
} elseif (!$varegruppe)  {
	$varegruppe=($_COOKIE['saldi_pricelist']);
	if (!$varegruppe) $varegruppe="0:Alle";
}

$x=0;
$q1= db_select("select kodenr, box9 from grupper where art = 'VG' and box8 = 'on' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__);
while ($r1=db_fetch_array($q1)) {
	$x++;
	$lagervare[$x]=$r1['kodenr'];
}
$lager[1]=1;
$lagernavn[1]='';
$x=0;
$q1= db_select("select kodenr,beskrivelse from grupper where art = 'LG' order by kodenr",__FILE__ . " linje " . __LINE__);
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
		$qtxt = "select varer.id,varer.varenr,varer.enhed,varer.beskrivelse,varer.salgspris,varer.kostpris,varer.tier_price,varer.retail_price,varer.varianter,varer.gruppe,";
		$qtxt.= "pricelist.beholdning ";
		$qtxt.= "from varer,pricelist where ".((!$show_all_products)?"on_price_list='1' and ":"")." varer.gruppe='$a' and pricelist.vare_id=varer.id and pricelist.lager='$lagervalg' ";
		if (!$zero_stock) $qtxt.= "and pricelist.beholdning != '0' ";
		$qtxt.="order by varer.varenr";
	} else {
	   $qtxt = "select * from varer where ".((!$show_all_products)?"on_price_list='1' and ":"")." gruppe='$a' ";
	   if (!$zero_stock) $qtxt.= "and beholdning != '0' ";
	    $qtxt.= "order by varenr";
	}
} else {
	if ($lagervalg) {
		$qtxt =" select varer.id,varer.varenr,varer.enhed,varer.beskrivelse,varer.salgspris,varer.kostpris,varer.tier_price,varer.retail_price,varer.varianter,varer.gruppe,";
		$qtxt.= "pricelist.beholdning ";
		$qtxt.= "from varer,pricelist where ".((!$show_all_products)?"on_price_list='1' and ":"")." pricelist.vare_id=varer.id and pricelist.lager='$lagervalg' ";
		if (!$zero_stock) $qtxt.= "and pricelist.beholdning != '0' ";
		$qtxt.= " order by varer.varenr";
	} else {
	    $qtxt = "select * from varer ";
	    if (!$zero_stock) {
	    	$qtxt.= "where ".((!$show_all_products)?"on_price_list='1' and ":"")." beholdning != '0' ";
	    } elseif (!$show_all_products) {
	    	$qtxt.=" where on_price_list='1' ";
		}
	    $qtxt.= "order by varenr";
	}
}
$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r2=db_fetch_array($q2)){
	if (in_array($r2['gruppe'], $lagervare)) {
		$x++;
		$vare_id[$x] 		= $r2['id'];
		$varenr[$x] 		= stripslashes($r2['varenr']);
		$enhed[$x] 			= stripslashes($r2['enhed']);
		$beholdning[$x] 	= $r2['beholdning'];
		$varianter[$x] 		= $r2['varianter']; #20180204
		$beskrivelse[$x] 	= stripslashes($r2['beskrivelse']);
		$salgspris[$x] 		= $r2['salgspris'];
		$kostpris[$x] 		= $r2['kostpris'];
		$retail_price[$x] 	= $r2['retail_price'];
		$tier_price[$x] 	= $r2['tier_price'];
	}
}
$vareantal=$x;

$url_part = "varegruppe=$varegruppe&lagervalg=$lagervalg&zero_stock=$zero_stock&show_all_products=$show_all_products&show_kostpris=$show_kostpris&show_antal=$show_antal&show_enhed=$show_enhed&show_tier_price=$show_tier_price&show_salgspris=$show_salgspris&show_retail_price=$show_retail_price&custom_text=".urlencode($custom_text)."";

$plGridMode = ($menu=='S' && !$autoprint);

if ($autoprint) {// Print friendly
	print "<table border=0 cellpadding=0 cellspacing=0 width=100%><tbody>";
	print "<tr><td align='center'>".ucfirst(findtekst('2082|Prisliste', $sprog_id))."<br>";
	if ($custom_text) print $custom_text;
	print "</td></tr><tr><td><hr></td></tr>";
	print "</tbody></table>";

} else {
	if ($plGridMode) {
		$plTilbageIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
		print "<style>html,body{margin:0;padding:0;height:100%;overflow:hidden;}</style>\n";
		print "<div id='plPageFlex' style='display:flex;flex-direction:column;height:100vh;box-sizing:border-box;'>\n";
		print "<div style='flex:0 0 auto;padding:8px 8px 0 8px;box-sizing:border-box;background-color:$bgcolor;'>\n";
		print "<table width='100%' align='center' border='0' cellspacing='4' cellpadding='0'><tbody><tr>";

		print "<td width='10%' align='left'><a href='$returside' accesskey=L>
			   <button style='$buttonStyle; width:100%; display:flex; align-items:center; gap:5px; justify-content:flex-start; padding-left:3px;' onMouseOver=\"this.style.cursor='pointer'\">$plTilbageIcon".findtekst(30, $sprog_id)."</button></a></td>";

		print "<td width='80%' align='center' style='$topStyle'>".ucfirst(findtekst(2082, $sprog_id))."</td>";

		print "<td width='10%' align='center'><a href='pricelist.php?csv=1&".$url_part."' title='".findtekst(2084, $sprog_id)."'>
			   <button style='$buttonStyle; width:100%; min-height:20px; display:flex; align-items:center; gap:5px; justify-content:center;' onMouseOver=\"this.style.cursor='pointer'\">CSV</button></a></td>";

		print "</tr></tbody></table>\n";
		if ($custom_text) print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><tr><td width=100% style='$topStyle' align=center>"
								 .$custom_text."</td></tr></tbody></table>\n";
	} elseif ($menu=='S') {
		print "<table border=0 cellpadding=0 cellspacing=0 width=100%><tbody>";
		print "<tr><td>";
		print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><tr>";

		print "<td width='10%'><a href='$returside' accesskey=L>
			   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst(30, $sprog_id)."</button></a></td>";

		print "<td width='80%' align='center' style='$topStyle'>".ucfirst(findtekst(2082, $sprog_id))."</td>";

		print "<td width='10%'><a href='pricelist.php?csv=1&".$url_part."' title='".findtekst(2084, $sprog_id)."'>
			   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">CSV</button></a></td>";

		print "</tr></tbody></table>\n";
		if ($custom_text) print "<tr><td><table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><tr><td width=100% style=$topStyle align=center>"
								 .$custom_text."</td></td></tr></tbody></td></tr></table></td></tr>";
		print "</td></tr>";
	} else {
		print "<table border=0 cellpadding=0 cellspacing=0 width=100%><tbody>";
		print "<tr><td>";
		print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><tr>";
		print "<td width=10% $top_bund><a href=$returside accesskey=L>".findtekst(30, $sprog_id)."</a></td>"; #20210708
		print "<td width=80% $top_bund align=center>".ucfirst(findtekst(2082, $sprog_id))."</td>";
		print "<td width=10% $top_bund><a href='pricelist.php?csv=1&".$url_part."' title='".findtekst(2084, $sprog_id)."'>CSV</a></td>";
		print "</tr></tbody></table>\n";
		if ($custom_text) print "<tr><td><table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><tr><td width=100% $top_bund align=center>".$custom_text."</td></td></tr></tbody></td></tr></table></td></tr>";
		print "</td></tr>";
	}
	if ($plGridMode) {
		print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody><tr><td align=\"center\"><form action=pricelist.php method=post>";
	} else {
		print "<tr><td align=\"center\"><form action=pricelist.php method=post>";
	}
	if (count($lager)) {
		print " ".findtekst('608|Lager', $sprog_id).": <select class=\"inputbox\" name=\"lagervalg\">";
		for ($x=0;$x<=count($lager);$x++){
			if ($lagervalg==$lager[$x]) print "<option value='$lager[$x]'>$lagernavn[$x]</option>";
		}
		for ($x=0;$x<=count($lager);$x++){
			if ($lagervalg!=$lager[$x]) print "<option value='$lager[$x]'>$lagernavn[$x]</option>";
		}
		print "</select>";
	}
	print " ".findtekst('429|Varegruppe', $sprog_id).": <select class=\"inputbox\" name=\"varegruppe\">";
	if ($varegruppe) print "<option>$varegruppe</option>";
	if ($varegruppe!="0:Alle") print "<option>0:Alle</option>";

	$q = db_select("select * from grupper where art = 'VG' and fiscal_year = '$regnaar' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)){
		if ($varegruppe!=$row['kodenr'].":".$row['beskrivelse']) {print "<option>$row[kodenr]:$row[beskrivelse]</option>";}
	}
	print "</select>";

	print " &nbsp".findtekst(2099, $sprog_id).": <input type='text' name='custom_text' value='$custom_text'>";

	($zero_stock)?$zero_stock="checked='checked'":$zero_stock=NULL;
	print " &nbsp"."<span title='".findtekst(1656, $sprog_id)."'><input type='checkbox' name='zero_stock' $zero_stock>".findtekst(2096, $sprog_id)."</span>";

	($show_all_products)?$show_all_products="checked='checked'":$show_all_products=NULL;
	print " &nbsp"."<input type='checkbox' name='show_all_products' $show_all_products>".findtekst(2090, $sprog_id);

	//($show_kostpris)?$show_kostpris="checked='checked'":$show_kostpris=NULL;
	//print " &nbsp"."<input type='checkbox' name='show_kostpris' $show_kostpris>".findtekst(2095, $sprog_id);

	($show_antal)?$show_antal="checked='checked'":$show_antal=NULL;
	print " &nbsp"."<input type='checkbox' name='show_antal' $show_antal>".findtekst(2097, $sprog_id);

	($show_enhed)?$show_enhed="checked='checked'":$show_enhed=NULL;
	print " &nbsp"."<input type='checkbox' name='show_enhed' $show_enhed>".findtekst(2302, $sprog_id);

	($show_tier_price)?$show_tier_price="checked='checked'":$show_tier_price=NULL;
	print " &nbsp"."<input type='checkbox' name='show_tier_price' $show_tier_price>".findtekst(2091, $sprog_id);

	($show_salgspris)?$show_salgspris="checked='checked'":$show_salgspris=NULL;
	print " &nbsp"."<input type='checkbox' name='show_salgspris' $show_salgspris>".findtekst(2092, $sprog_id);

	($show_retail_price)?$show_retail_price="checked='checked'":$show_retail_price=NULL;
	print " &nbsp"."<input type='checkbox' name='show_retail_price' $show_retail_price>".findtekst(2093, $sprog_id);

	print " &nbsp"."<input type='submit' name='submit' value='".findtekst(2087, $sprog_id)."'>";
	
	print " &nbsp"."<a href='pricelist.php?autoprint=1&".$url_part."' target='_blank'>".findtekst(2098, $sprog_id)."</a>";

	if ($plGridMode) {
		print "</form></td></tr></tbody></table>\n";
		print "</div>\n"; // close flex:0 header wrapper (title bar + filter form)
	} else {
		print "</form></td></tr>";
		print "<tr><td><hr></td></tr>";
		print "</tbody></table>";
	}
}

if ($plGridMode) {
	$plColW = array('varenr'=>8);
	if ($show_enhed) $plColW['enhed']=5;
	$plColW['beskrivelse']=0; // filled below
	if ($show_antal) $plColW['antal']=8;
	if ($show_tier_price) $plColW['tier']=8;
	if ($show_salgspris) $plColW['salgspris']=8;
	if ($show_retail_price) $plColW['retail']=8;
	$plFixedSum = array_sum($plColW);
	$plColW['beskrivelse'] = max(20, 100 - $plFixedSum);
	$plColgroupHtml = "<colgroup>";
	foreach ($plColW as $w) $plColgroupHtml .= "<col style='width:{$w}%'>";
	$plColgroupHtml .= "</colgroup>";

	print "<div id='plGridWrapper' style='flex:1 1 auto;overflow-y:auto;overscroll-behavior:contain;background-color:$bgcolor;padding:0 8px 8px 8px;box-sizing:border-box;'>\n";
	print "<style>
	#plGridTable { border-collapse:separate; border-spacing:0; width:100%; table-layout:fixed; }
	#plGridTable td { box-sizing:border-box; padding:4px; }
	#plGridTable tr.pl-col-title-row td { position:sticky; top:0; z-index:10; background-color:$bgcolor; box-sizing:border-box; }
	</style>\n";
	print "<table id='plGridTable' width='100%' cellpadding='0' cellspacing='0' border='0'>$plColgroupHtml<tbody>\n";
	print "<tr class='pl-col-title-row'>";
} else {
	print "<table border=0 cellpadding=0 cellspacing=0 width=100%><tbody>";
	print "<tr>";
}
print "<td width=8%>".findtekst(917, $sprog_id).".</td>";
if ($show_enhed) print "<td width=5%>".findtekst(945, $sprog_id)."</td>";
print "<td>".findtekst(914, $sprog_id)."</td>";
if ($show_antal) print "<td align=right width=8%>".findtekst(916, $sprog_id)."</td>";
//print "".(($show_kostpris)?"<td align=right width=8%>".findtekst(950, $sprog_id)."</td>":"")."";
//print "".(($show_kostpris)?"<td align=right width=8%>".findtekst(950, $sprog_id)."</td>":"")."";
if ($show_tier_price) print "<td align=right width=8%>".findtekst(2088, $sprog_id)."</td>";
if ($show_salgspris) print "<td align=right width=8%>".findtekst(949, $sprog_id)."</td>";
if ($show_retail_price) print "<td align=right width=8%>".findtekst(2085, $sprog_id)."</td>";
print "</tr>";

if ($plGridMode) {
	$plValidPageSizes = array(50,100,250,500,100000);
	$plPerPage = (int) (isset($_GET['pl_per_page']) ? $_GET['pl_per_page'] : 0);
	if (!in_array($plPerPage, $plValidPageSizes)) $plPerPage = 50;
	$plPage = (int) (isset($_GET['pl_page']) ? $_GET['pl_page'] : 0);
	if ($plPage < 1) $plPage = 1;
	$plTotalRows = $vareantal;
	$plTotalPages = max(1, ceil($plTotalRows / $plPerPage));
	if ($plPage > $plTotalPages) $plPage = $plTotalPages;
	$plPageStart = ($plPage - 1) * $plPerPage;
	$plPageEnd = $plPageStart + $plPerPage;
}

if ($csv) {
	$fp=fopen("../temp/$db/pricelist.csv","w");
	$linje =	"Varenr".";".
				(($show_enhed)?"Enhed".";":"").
				"Beskrivelse".";".
				(($show_antal)?"Antal".";":"").
				//(($show_kostpris)?"Købspris".";":"").
				//(($show_kostpris)?"Kostpris".";":"").
				(($show_tier_price)?"B2B pris".";":"").
				(($show_salgspris)?"Salgspris".";":"").
				(($show_retail_price)?"Vejl.pris".";":"").
				"";
	$linje=mb_convert_encoding($linje, 'ISO-8859-1', 'UTF-8');
	fwrite($fp,"$linje\n");
}
 
for($x=1; $x<=$vareantal; $x++) {
	$batch_k_antal[$x]=0;$batch_t_antal[$x]=0;$batch_pris[$x]=0;$batch_s_antal[$x]=0;
	$qtxt="select sum(antal) as antal from batch_kob where vare_id=$vare_id[$x]";
	if ($lagervalg) $qtxt.=" and lager='$lagervalg'";
	$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$batch_k_antal[$x]=$r1['antal'];
	$batch_t_antal[$x]=$r1['antal'];
	$qtxt="select sum(antal) as antal from batch_salg where vare_id=$vare_id[$x]";
	if ($lagervalg) $qtxt.=" and lager='$lagervalg'";
	$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$batch_s_antal[$x]=$r1['antal'];
	$batch_t_antal[$x]-=$r1['antal'];

	if ($batch_k_antal[$x]||$batch_s_antal[$x]||$beholdning[$x]) {
		if ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		else {$linjebg=$bgcolor; $color='#000000';}
		if ($plGridMode) ob_start();
		print "<tr bgcolor=\"$linjebg\">";
		print "<td>".$varenr[$x]."<br></td>";
		if ($show_enhed) print "<td>".$enhed[$x]."<br></td>";
		print "<td>".$beskrivelse[$x]."<br></td>";
		if ($show_antal) print "<td align=right>".str_replace(".",",",$batch_t_antal[$x]*1)."<br></td>";
		//if ($show_kostpris) print "<td align=right>".dkdecimal($batch_pris[$x])."<br></td>";
		//if ($show_kostpris) print "<td align=right>".dkdecimal($kostpris[$x])."<br></td>";
		if ($show_tier_price) print "<td align=right>".dkdecimal($tier_price[$x])."<br></td>";
		if ($show_salgspris) print "<td align=right>".dkdecimal($salgspris[$x])."<br></td>";
		if ($show_retail_price) print "<td align=right>".dkdecimal($retail_price[$x])."<br></td>";
		print "</tr>";
		if ($plGridMode) {
			$plRowHtml = ob_get_clean();
			if ($x-1 >= $plPageStart && $x-1 < $plPageEnd) print $plRowHtml;
		}
		if ($csv) {
			$linje = 	"$varenr[$x]".";".
						(($show_enhed)?"".$enhed[$x].";":"").
						"$beskrivelse[$x]".";".
						(($show_antal)?"".dkdecimal($batch_t_antal[$x]).";":"").
						//(($show_kostpris)?"".dkdecimal($batch_pris[$x]).";":"").
						//(($show_kostpris)?"".dkdecimal($kostpris[$x]).";":"").
						(($show_tier_price)?"".dkdecimal($tier_price[$x]).";":"").
						(($show_salgspris)?"".dkdecimal($salgspris[$x]).";":"").
						(($show_retail_price)?"".dkdecimal($retail_price[$x]).";":"").
						"";
			$linje=mb_convert_encoding($linje, 'ISO-8859-1', 'UTF-8');
			fwrite($fp,"$linje\n");
		}
	} 
}
if ($csv){
	fclose($fp);
	print "<BODY onLoad=\"JavaScript:window.open('../temp/$db/pricelist.csv' ,'' ,'$jsvars');\">\n";
}
print "<tr><td colspan=9><hr></td></tr>";

if ($plGridMode) {
	// Close #plGridTable/#plGridWrapper opened above, then print the fixed Grid Framework footer
	// with real (server-side) pagination - same link/markup/CSS technique lager/lagerstatus.php uses.
	print "</tbody></table>"; // close plGridTable
	print "</div>\n"; // close plGridWrapper

	$plTxt1 = lcfirst(findtekst('2767|Af', $sprog_id));
	$plTxt2 = findtekst('2125|Linjer pr. side', $sprog_id);
	$plOffsetFrom = $plTotalRows ? (($plPage - 1) * $plPerPage) + 1 : 0;
	$plOffsetTo = min($plTotalRows, $plPage * $plPerPage);
	$plBaseUrl = "pricelist.php?" . http_build_query(array(
		'varegruppe' => $varegruppe,
		'lagervalg' => $lagervalg,
		'zero_stock' => $zero_stock,
		'show_all_products' => $show_all_products,
		'show_antal' => $show_antal,
		'show_enhed' => $show_enhed,
		'show_tier_price' => $show_tier_price,
		'show_salgspris' => $show_salgspris,
		'show_retail_price' => $show_retail_price,
		'custom_text' => $custom_text,
	));
	$plPrevIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="#000000"><path d="M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z"/></svg>';
	$plNextIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="#000000"><path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z"/></svg>';

	print "<style>
#plPageFooterBar { position:fixed; left:0; right:0; bottom:0; width:100%; margin:0; z-index:1000; background-color:$bgcolor; border-top:1px solid #b8bec8; padding:6px 12px; display:flex; align-items:center; justify-content:flex-end; gap:20px; flex-wrap:wrap; box-sizing:border-box; line-height:1; }
#plPageFooterBar #plNavButtons { display:flex; align-items:center; gap:3px; }
#plPageFooterBar #plNavButtons .navbutton { height:20px; min-width:20px; padding:0 4px; display:inline-flex; align-items:center; justify-content:center; background:#f0f0f0; color:#000; border:1px solid #b8bec8; border-radius:4px; text-decoration:none; }
#plPageFooterBar #plNavButtons a.navbutton { cursor:pointer; }
#plPageFooterBar #plNavButtons span.navbutton { opacity:0.5; }
#plPageFooterBar #plNavButtons .navbutton.current { text-decoration:underline; }
</style>\n";
	print "<div id='plPageFooterBar'>";
	print "<span id='plPageStatus'>" . ($plTotalRows ? "$plOffsetFrom-$plOffsetTo $plTxt1 $plTotalRows" : "0 $plTxt1 0") . "</span>";
	print "<span>$plTxt2 <select id='plPageSize' onchange=\"window.location.href='" . htmlspecialchars($plBaseUrl, ENT_QUOTES) . "&pl_page=1&pl_per_page=' + this.value;\">";
	foreach ($plValidPageSizes as $plOpt) {
		$plSel = ($plOpt == $plPerPage) ? " selected" : "";
		$plLabel = ($plOpt == 100000) ? "Alle" : $plOpt;
		print "<option value='$plOpt'$plSel>$plLabel</option>";
	}
	print "</select></span>";
	print "<span id='plNavButtons'>";
	if ($plPage > 1)
		print "<a class='navbutton' href='" . htmlspecialchars($plBaseUrl, ENT_QUOTES) . "&pl_page=" . ($plPage - 1) . "&pl_per_page=$plPerPage'>$plPrevIcon</a>";
	else
		print "<span class='navbutton'>$plPrevIcon</span>";
	$plPageRange = 2;
	$plStartPage = max(1, $plPage - $plPageRange);
	$plEndPage = min($plTotalPages, $plPage + $plPageRange);
	if ($plStartPage > 1) {
		print "<a class='navbutton' href='" . htmlspecialchars($plBaseUrl, ENT_QUOTES) . "&pl_page=1&pl_per_page=$plPerPage'>1</a>";
		if ($plStartPage > 2)
			print "<span>...</span>";
	}
	for ($plP = $plStartPage; $plP <= $plEndPage; $plP++) {
		if ($plP == $plPage)
			print "<span class='navbutton current'>$plP</span>";
		else
			print "<a class='navbutton' href='" . htmlspecialchars($plBaseUrl, ENT_QUOTES) . "&pl_page=$plP&pl_per_page=$plPerPage'>$plP</a>";
	}
	if ($plEndPage < $plTotalPages) {
		if ($plEndPage < $plTotalPages - 1)
			print "<span>...</span>";
		print "<a class='navbutton' href='" . htmlspecialchars($plBaseUrl, ENT_QUOTES) . "&pl_page=$plTotalPages&pl_per_page=$plPerPage'>$plTotalPages</a>";
	}
	if ($plPage < $plTotalPages)
		print "<a class='navbutton' href='" . htmlspecialchars($plBaseUrl, ENT_QUOTES) . "&pl_page=" . ($plPage + 1) . "&pl_per_page=$plPerPage'>$plNextIcon</a>";
	else
		print "<span class='navbutton'>$plNextIcon</span>";
	print "</span>";
	print "</div>\n";
	print "</div>\n"; // close plPageFlex
} else {
?>
</tbody></table>
</body></html>
<?php
}
