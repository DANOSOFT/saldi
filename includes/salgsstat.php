<?php
//                         ___   _   _   ___  _  
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |    
//                        |___/_/ \_|___|___/|_|
//
// ----------includes/salgsstat.php------patch 4.0.7--- 2023.12.21 --
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------------
// 
// 20160309	- ændret $antal[$x][$y] til $r['antal'] da antal ikke skal summeres ved sumberegning
// 20210329 - Loe translated with findtekst function some of these texts
// 20220905 MSC - Implementing new design
// 01-05-2023 PBLM Fixed minor errors
// 20231213 MSC - Copy pasted new design into code
// 20260704 SZ Added Grid Framework sticky header and footer to Sales Statistics report
// 20260704 SZ Fixed misaligned columns in Sales Statistics report list

@session_start();
$s_id=session_id();
$modulnr=12;
$title="Salgsstatistik";

$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");
include("../includes/db_query.php");
include("../includes/topline_settings.php");


$dato_fra=if_isset($_GET['dato_fra']);
$dato_til=if_isset($_GET['dato_til']);
$konto_fra=if_isset($_GET['konto_fra']);
$konto_til=if_isset($_GET['konto_til']);
$kontonr=if_isset($_GET['kontonr']);
$firmanavn=if_isset($_GET['firmanavn']);
$adresse=if_isset($_GET['adresse']);
$postnr=if_isset($_GET['postnr']);
$bynavn=if_isset($_GET['bynavn']);
$varenr=if_isset($_GET['varenr']);
$varetekst=if_isset($_GET['varetekst']);
$detaljer=if_isset($_GET['detaljer']);
$ret=if_isset($_GET['ret']);
$art=if_isset($_GET['art']);
// Real (server-side) pagination for the Grid Framework ($menu=='S') footer — same $ss_page/$ss_per_page
// query-param pattern kontokort() uses for Finance -> Reports -> General Ledger's footer.
$ssValidPageSizes=array(50,100,250,500,100000);
$ssPerPage=(int) if_isset($_GET['ss_per_page']);
if (!in_array($ssPerPage,$ssValidPageSizes)) $ssPerPage=50;
$ssPage=(int) if_isset($_GET['ss_page']);
if ($ssPage<1) $ssPage=1;
if ($ret) {
	begraens($dato_fra,$dato_til,$konto_fra,$konto_til,$kontonr,$firmanavn,$adresse,$postnr,$bynavn,$varenr,$varetekst,$detaljer,$art);
	exit;
}
if (isset($_POST['find']) && $_POST['find']) {
	$kontonr=if_isset($_POST['kontonr']);
	$firmanavn=if_isset($_POST['firmanavn']);
	$adresse=if_isset($_POST['adresse']);
	$postnr=if_isset($_POST['postnr']);
	$bynavn=if_isset($_POST['bynavn']);
	$varenr=if_isset($_POST['varenr']);
	$varetekst=if_isset($_POST['varetekst']);
	$detaljer=if_isset($_POST['detaljer']);
}
($detaljer)?$summeret=NULL:$summeret='on';

$day=date("d");
$month=date("m");
$year=date("y");

$tmp=$year-1;
if ($tmp<10) $tmp="0".$tmp;
list($fra,$til)=(isset($periode) ? explode(":",$periode) : 0);
if (!$til) $til=date("dmY");
$rtekst=findtekst('1813|Klik her for at vælge en anden periode', $sprog_id);

if ($popup) $luk="../includes/luk.php";
elseif ($art=='D') $luk="../debitor/rapport.php";
else $luk="../kreditor/rapport.php";

if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\"><a href=$luk accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst('30|Tilbage', $sprog_id)."</a></div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu=='S') {
	// Grid Framework header — mirrors Debtors -> Reports -> Open items / Account balance / General ledger
	// (openpost()/kontosaldo()/kontokort() in includes/rapportfunc.php). The whole page is one flex
	// column: header bar (auto height) + column-title row (auto height, printed just below, before the
	// account loop) + scrollable grid (flex:1) + fixed footer (printed after the account loop). No
	// external stylesheet is loaded here (same as the three reference reports) — every style needed is
	// inline/embedded below.
	$ssTilbageIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
	$ssLukTekst = findtekst('2704|Klik her for at lukke \"Top100\"', $sprog_id);

	print "<style>html,body{margin:0;padding:0;height:100%;overflow:hidden;}</style>\n";
	print "<div id='ssPageFlex' style='display:flex;flex-direction:column;height:100vh;box-sizing:border-box;'>\n";
	print "<div style='flex:0 0 auto;padding:8px 8px 0 8px;box-sizing:border-box;background-color:$bgcolor;'>\n";
	// The Back button is taller than a plain text button because its 20x20 SVG icon forces a larger
	// minimum content height; height:100% on a <td> child doesn't reliably work (percentage heights
	// only resolve against an explicit, not row-stretched/auto, parent height). Instead, give the
	// Search button the same 20px minimum content height directly via min-height — sidesteps the
	// parent-height quirk entirely and matches the Back button's actual rendered size.
	print "<table width='100%' align='center' border='0' cellspacing='4' cellpadding='0'><tbody><tr>";
	print "<td width='10%' align='left' title='$ssLukTekst'><a href='$luk' accesskey=L>
		   <button style='$buttonStyle; width:100%; display:flex; align-items:center; gap:5px; justify-content:flex-start; padding-left:3px;' onMouseOver=\"this.style.cursor='pointer'\">$ssTilbageIcon" . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>";
	print "<td width='80%' align='center' style='$topStyle'>" . findtekst('922|Salgstatsstik', $sprog_id) . "</td>";
	print "<td width='10%' align='center' title='$rtekst'><a href='salgsstat.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kontonr=$kontonr&firmanavn=$firmanavn&adresse=$adresse&postnr=$postnr&bynavn=$bynavn&varenr=$varenr&varetekst=$varetekst&detaljer=$detaljer&art=$art&ret=on' accesskey=B>
		   <button style='$buttonStyle; width:100%; min-height:20px; display:flex; align-items:center; gap:5px; justify-content:center;' onMouseOver=\"this.style.cursor='pointer'\">" . findtekst('913|Søg', $sprog_id) . "</button></a></td>";
	print "</tr></tbody></table>";
	print "</div>\n"; // <- close flex:0 wrapper around the blue header bar

	// Same colgroup widths drive both the column-title row and every data row below it (they live in
	// the same #ssGridTable), so the header can never drift out of alignment with the data — this is
	// the fix for the reported "header columns don't match data columns" bug. The previous code built
	// the column-title row in a *separate* <table> that never included a "Dato" column at all, while
	// the data rows below (in "vis detaljer" mode) had one prepended as their first cell — shifting
	// every subsequent data column one position right of its header. Column count/order here now
	// matches the data rows exactly for both modes.
	if ($summeret) {
		$ssColgroupHtml = "<colgroup><col style='width:12%'><col style='width:43%'><col style='width:15%'><col style='width:15%'><col style='width:15%'></colgroup>";
	} else {
		$ssColgroupHtml = "<colgroup><col style='width:10%'><col style='width:10%'><col style='width:30%'><col style='width:12%'><col style='width:13%'><col style='width:12%'><col style='width:13%'></colgroup>";
	}
	print "<style>
#ssGridWrapper { flex:1 1 auto; min-height:0; overflow-y:auto; overscroll-behavior:contain; width:100%; background-color:$bgcolor; padding:0 8px 68px 8px; box-sizing:border-box; }
#ssGridTable { border-collapse:separate; border-spacing:0; width:100%; table-layout:fixed; }
#ssGridTable th { position:sticky; top:0; z-index:10; padding:6px 4px; background-color:$bgcolor; box-sizing:border-box; text-align:left; }
#ssGridTable td { box-sizing:border-box; padding:4px; }
#ssGridTable th.text-right { text-align:right; }
#ssGridTable tr.ss-account-row td { padding:2px 4px; }
#ssGridTable tbody.ss-account-block ~ tbody.ss-account-block tr.ss-account-row:first-child td { padding-top:10px; border-top:2px solid #ddd; }
#ssGridTable tr.ss-col-title-row th { border-top:2px solid #ddd; }
#ssGridTable tr.ss-col-title-row.ss-stuck th { border-top:none; border-bottom:2px solid #ddd; }
</style>\n";
	// Sticky is applied per-<th> rather than on <thead> — more reliably supported across browsers when
	// combined with table-layout:fixed + percentage colgroup widths (same technique kontokort() uses).
	// The <th> row itself is NOT printed here — per explicit request it stays matching the old table's
	// placement (below the first account's "Account No:/Company Name:" block, not above it) — see the
	// $ssHeaderPrinted flag in the account loop below, which prints it once, in that position.
	print "<div id='ssGridWrapper'><table id='ssGridTable' width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\">$ssColgroupHtml";
	$ssHeaderPrinted = false;
} else {
	include_once '../includes/oldDesign/header.php';
	print "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
	print "<tr><td colspan=\"4\" height=\"8\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
	$tekst="Klik her for at lukke \"Top100\"";
	print "<td width=\"10%\" $top_bund title='$tekst'><a href=\"$luk\" accesskey=L>".findtekst('30|Tilbage', $sprog_id)."</a></td>";
	print "<td width=\"80%\" $top_bund>".findtekst('922|Salgstatsstik', $sprog_id)."</td>";
	print "<td width=\"10%\" $top_bund title='$rtekst'><a href=salgsstat.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kontonr=$kontonr&firmanavn=$firmanavn&adresse=$adresse&postnr=$postnr&bynavn=$bynavn&varenr=$varenr&varetekst=$varetekst&detaljer=$detaljer&art=$art&ret=on accesskey=B>".findtekst('913|Søg', $sprog_id)."<br></a></td>";
	print "</tbody></table>";
	print "</td></tr>\n";
	print "<tr><td width=\"100%\">"; 
}
#$art='D';
/*
$qtxt="select * from adresser where art = $art order by firmanavn";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while($r=db_fetch_array($q)){
	$konto_id[$x]=$r['konto_id'];
	$kontonr[$x]=$r['kontonr'];
	$firmanavn[$x]=$r['firmanavn'];
	$x;
}
*/
$x=0;
$y=0;
$q_konto_id=array();

$qtxt="select ordrelinjer.vare_id,ordrelinjer.varenr,ordrelinjer.beskrivelse,ordrelinjer.antal,ordrelinjer.pris,ordrelinjer.rabat,";
$qtxt.="ordrer.konto_id,ordrer.kontonr,ordrer.firmanavn,ordrer.id,ordrer.fakturadate from ordrer,ordrelinjer,adresser ";
$qtxt.="where ordrer.status>='3' and ordrelinjer.vare_id !='0' and ordrelinjer.ordre_id=ordrer.id and adresser.id=ordrer.konto_id and adresser.art='$art' ";
if ($dato_fra && $dato_til) $qtxt.="and ordrer.fakturadate>='".usdate($dato_fra)."' and ordrer.fakturadate<='".usdate($dato_til)."' ";
if ($konto_fra && $konto_til) $qtxt.="and ordrer.kontonr>='$konto_fra' and ordrer.kontonr<='$konto_til' ";
elseif ($kontonr) $qtxt.="and ordrer.kontonr like '".str_replace('*','%',$kontonr)."' ";
if ($firmanavn) $qtxt.="and lower(ordrer.firmanavn) like '".str_replace('*','%',strtolower($firmanavn))."' ";
if ($adresse) $qtxt.="and ordrer.adresse like '".str_replace('*','%',strtolower($adresse))."' ";
if ($postnr) $qtxt.="and ordrer.postnr like '".str_replace('*','%',strtolower($postnr))."' ";
if ($bynavn) $qtxt.="and ordrer.bynavn like '".str_replace('*','%',strtolower($bynavn))."' ";
if ($varenr) $qtxt.="and ordrelinjer.varenr like '".str_replace('*','%',strtolower($varenr))."' ";
if ($varetekst) $qtxt.="and ordrelinjer.beskrivelse like '".str_replace('*','%',strtolower($varetekst))."' ";
$qtxt.="order by ordrer.kontonr,ordrelinjer.varenr";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while($r=db_fetch_array($q)){
	if (isset($q_konto_id[$x]) && $q_konto_id[$x] && $q_konto_id[$x]!=$r['konto_id']) {
		$x++;
		$y=0;
		$q_vare_id[$x]=array();
	}
	$q_konto_id[$x]=$r['konto_id'];
	$q_kontonr[$x]=$r['kontonr'];
	$q_firmanavn[$x]=$r['firmanavn'];
	if ($summeret) {
		if (isset($q_vare_id[$x][$y]) && $q_vare_id[$x][$y] && $q_vare_id[$x][$y]!=$r['vare_id']) {
			$q_pris[$x][$y]=($q_antal[$x][$y] != 0) ? $q_sum[$x][$y]/$q_antal[$x][$y] : $q_sum[$x][$y];
			$y++;
		}
		$q_vare_id[$x][$y]=$r['vare_id'];
		$q_varenr[$x][$y]=$r['varenr'];
		$q_beskrivelse[$x][$y]=$r['beskrivelse'];
#		$antal[$x][$y]=$r['antal'];
		$q_pris[$x][$y]=$r['pris'];
		$q_rabat[$x][$y]=$r['rabat'];
		(isset($q_antal[$x][$y])) ? $q_antal[$x][$y]+=$r['antal'] : $q_antal[$x][$y]=$r['antal'];
		(isset($q_sum[$x][$y])) ? $q_sum[$x][$y]+=$r['antal']*($q_pris[$x][$y]-($q_pris[$x][$y]/100*$q_rabat[$x][$y])) : $q_sum[$x][$y]=$r['antal']*($q_pris[$x][$y]-($q_pris[$x][$y]/100*$q_rabat[$x][$y])); #20160309
	} else {
		($r['fakturadate'])?$q_faktdato[$x][$y]=dkdato($r['fakturadate']):$q_faktdato[$x][$y]='Ikke faktureret';
		$q_vare_id[$x][$y]=$r['vare_id'];
		$q_varenr[$x][$y]=$r['varenr'];
		$q_beskrivelse[$x][$y]=$r['beskrivelse'];
#		$antal[$x][$y]=$r['antal'];
		$q_pris[$x][$y]=$r['pris'];
		$q_rabat[$x][$y]=$r['rabat'];
		$q_antal[$x][$y]=$r['antal'];
		$q_sum[$x][$y]=$q_antal[$x][$y]*($q_pris[$x][$y]-($q_pris[$x][$y]/100*$q_rabat[$x][$y]));
		$y++;
	}
} 
($summeret)?$cols='5':$cols='7';

if ($menu=='S') {
	// Every matching row is already sitting in the $q_* arrays from the query above (this report has
	// no per-row DB lookups to skip, unlike General Ledger), so "server-side" pagination here means:
	// compute which global row-range the requested page covers, then only PRINT rows in that range
	// during the account loop below — same end result (only the current page's rows ever reach the
	// browser, real page-reload links, not client-side re-slicing) as kontokort()'s footer.
	$ssTotalRows=0;
	for ($ssCx=0;$ssCx<count($q_konto_id);$ssCx++) {
		$ssTotalRows+=count($q_vare_id[$ssCx]);
	}
	$ssTotalPages=max(1,ceil($ssTotalRows/$ssPerPage));
	if ($ssPage>$ssTotalPages) $ssPage=$ssTotalPages;
	$ssPageStart=($ssPage-1)*$ssPerPage;
	$ssPageEnd=$ssPageStart+$ssPerPage;
	$ssGlobalRowIndex=0;
}

if ($menu=='T') {
	print "<center style='padding-bottom:5px;'>	<input onclick=\"location.href='#nav'\" style='width:450px;' type=\"button\" title='Klik her for at søge' value=\"".findtekst('913|Søg', $sprog_id)."\">";
	print "<div class='expandableSearch' id='nav' style='padding-top:5px;'>";
	begraens($dato_fra,$dato_til,$konto_fra,$konto_til,$kontonr,$firmanavn,$adresse,$postnr,$bynavn,$varenr,$varetekst,$detaljer,$art);
	print "</div>";
	print "</center>";
} else {
	print "";
}

for ($x=0;$x<count($q_konto_id);$x++) {
	if ($menu=='S') {
		// Grid Framework data rows — printed straight into the page-level #ssGridTable opened once
		// before this loop started (see the $menu=='S' header block above). Real server-side pagination
		// (see $ssPageStart/$ssPageEnd computed above, same technique kontokort() uses for General
		// Ledger): only rows whose running global index falls inside the requested page window are
		// printed at all, and an account's group-title row is skipped entirely if none of its rows
		// land on the current page.
		$ssAccountTotal=count($q_vare_id[$x]);
		$ssAccountStart=$ssGlobalRowIndex;
		$ssAccountEnd=$ssGlobalRowIndex+$ssAccountTotal;
		if ($ssAccountEnd>$ssPageStart && $ssAccountStart<$ssPageEnd) {
			print "<tbody class='ss-account-block'>";
			// Same two-line "Account No: / Company Name:" labeled format as the original table
			// (not the single merged "Company • Account" row the other Grid Framework reports use) —
			// kept as-is per explicit request, only the header/footer/column-alignment were in scope.
			print "<tr class='ss-account-row'><td colspan='$cols'><b>".findtekst('284|Kontonr', $sprog_id).":</b> $q_kontonr[$x]</td></tr>";
			// Hardcoded rather than findtekst('360|Firmanavn', ...) — the live database's tekster table
			// (or a session-cached copy of it) was returning "Company_name" instead of "Company Name"
			// here, even though the CSV translation source has the correct value; this bypasses that
			// stale/incorrect lookup entirely for this specific label.
			print "<tr class='ss-account-row'><td colspan='$cols'><b>Company Name:</b> ".stripslashes($q_firmanavn[$x])."</td></tr>";
			if (!$ssHeaderPrinted) {
				// Column-title row prints once, right here — below the first visible account's
				// "Account No:/Company Name:" block, matching the old table's placement, not above it.
				// It stays sticky (see #ssGridTable th CSS above) so it pins to the top of the scroll
				// area once the page is scrolled far enough for it to reach the top.
				print "<tr class='ss-col-title-row'>";
				if (!$summeret) print "<th>" . findtekst('635|Dato', $sprog_id) . "</th>";
				print "<th>" . findtekst('917|Varenr.', $sprog_id) . "</th>";
				print "<th>" . findtekst('914|Beskrivelse', $sprog_id) . "</th>";
				print "<th class='text-right'>" . findtekst('916|Antal', $sprog_id) . "</th>";
				print "<th class='text-right'>" . findtekst('915|Pris', $sprog_id) . "</th>";
				if (!$summeret) print "<th class='text-right'>" . findtekst('428|Rabat', $sprog_id) . "</th>";
				print "<th class='text-right'>Sum</th>";
				print "</tr>";
				$ssHeaderPrinted = true;
			}
			for ($y=0;$y<$ssAccountTotal;$y++) {
				$ssRowIndex=$ssGlobalRowIndex+$y;
				if ($ssRowIndex>=$ssPageStart && $ssRowIndex<$ssPageEnd) {
					print "<tr class='ss-data-row'>";
					if (!$summeret) print "<td>".$q_faktdato[$x][$y]."</td>";
					print "<td>".$q_varenr[$x][$y]."</td>";
					print "<td>".stripslashes($q_beskrivelse[$x][$y])."</td>";
					print "<td align=\"right\">".dkdecimal($q_antal[$x][$y])."</td>";
					print "<td align=\"right\">".dkdecimal($q_pris[$x][$y])."</td>";
					if (!$summeret) {
						print "<td align=\"right\">".dkdecimal($q_rabat[$x][$y])."</td>";
					}
					print "<td align=\"right\">".dkdecimal($q_sum[$x][$y])."</td>";
					print "</tr>";
				}
			}
			print "</tbody>";
		}
		$ssGlobalRowIndex+=$ssAccountTotal;
		continue;
	}
	print"<div class='dataTablediv'><table width=\"100%\" class='dataTable'><tbody>";
#	print "<tr><td>$konto_id[$x]</td></tr>";
	if ($menu=='T') {
		if ($x) print "<br>";
	} else {
		if ($x) print "<tr><td colspan=\"$cols\"><hr></td></tr>";
	}
	print "<tr><td width=10%><b>".findtekst('284|Kontonr', $sprog_id).":</b></td><td>$q_kontonr[$x]</td></tr>";
	print "<tr><td width=10%><b>".findtekst('360|Firmanavn', $sprog_id).":</b></td><td>$q_firmanavn[$x]</td></tr>";
	if (isset($periode)) print "<tr><td><b>".findtekst('899|Periode.', $sprog_id)."</b></td><td>$periode</td></tr>";
	print "<tr>";
	if (!$summeret) print "</td><td align=\"left\"><b>".findtekst('635|Dato', $sprog_id)."</b></td>";

	if ($menu=='T') {
		print "<tr><td colspan=10 class='border-hr-bottom'></td></tr>\n";
	} else {
		print "<tr><td colspan=10><hr></td></tr>\n";
	}

	print"<table width=\"100%\" class='dataTableNTH'><thead>";
	print "<th>".findtekst('917|Varenr.', $sprog_id)."</th><th>".findtekst('914|Beskrivelse', $sprog_id)."</th><th class='text-right'>".findtekst('916|Antal', $sprog_id)."</th><th class='text-right'>".findtekst('915|Pris', $sprog_id)."</th>";
	if (!$summeret) print "<th class='text-right'>".findtekst('428|Rabat', $sprog_id)."</th>";
	print "<th class='text-right'>Sum</th></tr></thead><tbody>";
	for ($y=0;$y<count($q_vare_id[$x]);$y++) {
		print "<tr>";
		if (!$summeret) print "</td><td align=\"left\">".$q_faktdato[$x][$y]."</td>";
		print "<td>".$q_varenr[$x][$y]."</td>";
		print "<td>".$q_beskrivelse[$x][$y]."</td>";
		print "<td align=\"right\">".dkdecimal($q_antal[$x][$y])."</td>";
		print "<td align=\"right\">".dkdecimal($q_pris[$x][$y])."</td>";
		if (!$summeret) {
			print "<td align=\"right\">".dkdecimal($q_rabat[$x][$y])."</td>";
		}
		print "<td align=\"right\">".dkdecimal($q_sum[$x][$y])."</td>";
		print "</tr>";
	}
	print "</tbody><tfoot><tr><td></td><tr></tfoor>";
	print "</table></div><br>";

	}

	if ($menu=='S') {
		if (!$ssHeaderPrinted) {
			// No account had any row on this page (zero matching results, or an out-of-range page) —
			// print the column titles anyway so the grid never shows with no header at all.
			print "<tbody><tr>";
			if (!$summeret) print "<th>" . findtekst('635|Dato', $sprog_id) . "</th>";
			print "<th>" . findtekst('917|Varenr.', $sprog_id) . "</th>";
			print "<th>" . findtekst('914|Beskrivelse', $sprog_id) . "</th>";
			print "<th class='text-right'>" . findtekst('916|Antal', $sprog_id) . "</th>";
			print "<th class='text-right'>" . findtekst('915|Pris', $sprog_id) . "</th>";
			if (!$summeret) print "<th class='text-right'>" . findtekst('428|Rabat', $sprog_id) . "</th>";
			print "<th class='text-right'>Sum</th>";
			print "</tr></tbody>\n";
		}
		// Close #ssGridTable/#ssGridWrapper opened in the header block, then print the fixed Grid
		// Framework footer with REAL (server-side) pagination — same $ssPageStart/$ssPageEnd-driven
		// print-time filtering as the account loop above, and the same link/markup/CSS technique
		// kontokort() uses for Finance -> Reports -> General Ledger's footer (#kkPageFooterBar):
		// a page-size <select> that navigates via a full page reload, and prev/page-number/next
		// <a> links — not client-side JS re-slicing.
		print "</table>"; // close #ssGridTable
		print "</div>\n"; // close #ssGridWrapper

		// Detect when the sticky column-title row is actually pinned to the top of #ssGridWrapper
		// (vs. sitting in its normal resting position right below "Company Name:") and only then add
		// the 'ss-stuck' class that turns on its border-bottom (see #ssGridTable th CSS above) — so the
		// divider only appears once scrolling has pinned the row, not while the page is at rest.
		print "<script>(function(){
	var wrap = document.getElementById('ssGridWrapper');
	var titleRow = document.querySelector('#ssGridTable tr.ss-col-title-row');
	if (!wrap || !titleRow || typeof IntersectionObserver === 'undefined') return;
	var observer = new IntersectionObserver(function(entries){
		entries.forEach(function(entry){
			titleRow.classList.toggle('ss-stuck', entry.intersectionRatio < 1);
		});
	}, { root: wrap, threshold: [1], rootMargin: '-1px 0px 0px 0px' });
	observer.observe(titleRow);
})();</script>\n";

		$ssTxt1 = lcfirst(findtekst('2767|Af', $sprog_id));
		$ssTxt2 = findtekst('2125|Linjer pr. side', $sprog_id);
		$ssOffsetFrom = (($ssPage - 1) * $ssPerPage) + 1;
		$ssOffsetTo = min($ssTotalRows, $ssPage * $ssPerPage);
		$ssBaseUrl = "salgsstat.php?" . http_build_query(array(
			'dato_fra' => $dato_fra,
			'dato_til' => $dato_til,
			'konto_fra' => $konto_fra,
			'konto_til' => $konto_til,
			'kontonr' => $kontonr,
			'firmanavn' => $firmanavn,
			'adresse' => $adresse,
			'postnr' => $postnr,
			'bynavn' => $bynavn,
			'varenr' => $varenr,
			'varetekst' => $varetekst,
			'detaljer' => $detaljer,
			'art' => $art,
			'ss_per_page' => $ssPerPage,
		));
		$ssPrevIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="#000000"><path d="M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z"/></svg>';
		$ssNextIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="16px" viewBox="0 -960 960 960" width="16px" fill="#000000"><path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z"/></svg>';

		print "<style>
#ssPageFooterBar { position:fixed; left:0; right:0; bottom:0; width:100%; margin:0; z-index:1000; background-color:$bgcolor; border-top:1px solid #b8bec8; padding:6px 12px; display:flex; align-items:center; justify-content:flex-end; gap:20px; flex-wrap:wrap; box-sizing:border-box; line-height:1; }
#ssPageFooterBar #ssNavButtons { display:flex; align-items:center; gap:3px; }
#ssPageFooterBar #ssNavButtons .navbutton { height:20px; min-width:20px; padding:0 4px; display:inline-flex; align-items:center; justify-content:center; background:#f0f0f0; color:#000; border:1px solid #b8bec8; border-radius:4px; text-decoration:none; }
#ssPageFooterBar #ssNavButtons a.navbutton { cursor:pointer; }
#ssPageFooterBar #ssNavButtons span.navbutton { opacity:0.5; }
#ssPageFooterBar #ssNavButtons .navbutton.current { text-decoration:underline; }
</style>\n";
		print "<div id='ssPageFooterBar'>";
		print "<span id='ssPageStatus'>" . ($ssTotalRows ? "$ssOffsetFrom-$ssOffsetTo $ssTxt1 $ssTotalRows" : "0 $ssTxt1 0") . "</span>";
		print "<span>$ssTxt2 <select id='ssPageSize' onchange=\"window.location.href='" . htmlspecialchars($ssBaseUrl, ENT_QUOTES) . "&ss_page=1&ss_per_page=' + this.value;\">";
		foreach (array(50, 100, 250, 500, 100000) as $ssOpt) {
			$ssSel = ($ssOpt == $ssPerPage) ? " selected" : "";
			$ssLabel = ($ssOpt == 100000) ? "Alle" : $ssOpt;
			print "<option value='$ssOpt'$ssSel>$ssLabel</option>";
		}
		print "</select></span>";
		print "<span id='ssNavButtons'>";
		if ($ssPage > 1)
			print "<a class='navbutton' href='" . htmlspecialchars($ssBaseUrl, ENT_QUOTES) . "&ss_page=" . ($ssPage - 1) . "'>$ssPrevIcon</a>";
		else
			print "<span class='navbutton'>$ssPrevIcon</span>";
		$ssPageRange = 2;
		$ssStartPage = max(1, $ssPage - $ssPageRange);
		$ssEndPage = min($ssTotalPages, $ssPage + $ssPageRange);
		if ($ssStartPage > 1) {
			print "<a class='navbutton' href='" . htmlspecialchars($ssBaseUrl, ENT_QUOTES) . "&ss_page=1'>1</a>";
			if ($ssStartPage > 2)
				print "<span>...</span>";
		}
		for ($ssP = $ssStartPage; $ssP <= $ssEndPage; $ssP++) {
			if ($ssP == $ssPage)
				print "<span class='navbutton current'>$ssP</span>";
			else
				print "<a class='navbutton' href='" . htmlspecialchars($ssBaseUrl, ENT_QUOTES) . "&ss_page=$ssP'>$ssP</a>";
		}
		if ($ssEndPage < $ssTotalPages) {
			if ($ssEndPage < $ssTotalPages - 1)
				print "<span>...</span>";
			print "<a class='navbutton' href='" . htmlspecialchars($ssBaseUrl, ENT_QUOTES) . "&ss_page=$ssTotalPages'>$ssTotalPages</a>";
		}
		if ($ssPage < $ssTotalPages)
			print "<a class='navbutton' href='" . htmlspecialchars($ssBaseUrl, ENT_QUOTES) . "&ss_page=" . ($ssPage + 1) . "'>$ssNextIcon</a>";
		else
			print "<span class='navbutton'>$ssNextIcon</span>";
		print "</span>";
		print "</div>\n";
		print "</div>\n"; // close #ssPageFlex
	}

	if ($menu=='T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}

function begraens($dato_fra,$dato_til,$konto_fra,$konto_til,$kontonr,$firmanavn,$adresse,$postnr,$bynavn,$varenr,$varetekst,$detaljer,$art) {
	global $db;
	global $menu;
	($detaljer)?$detaljer='checked':$detaljer=NULL;
	print "<center>";
	print "<form name=\"".findtekst('918|Salgsstat', $sprog_id)."\" action=\"salgsstat.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kontonr=$kontonr&firmanavn=$firmanavn&adresse=$adresse&postnr=$postnr&bynavn=$bynavn&varenr=$varenr&varetekst=$varetekst&detaljer=$detaljer&art=$art\" method=\"post\">";
	print "<table width=25%><tbody>";
	print "<tr><td width=50%><b>".findtekst('284|Kontonr', $sprog_id).":</b></td><td><input type=\"text\" name=\"kontonr\" value=\"$kontonr\"></td></tr>"; #20210329
	print "<tr><td width=50%><b>".findtekst('360|Firmanavn', $sprog_id).":</b></td><td><input type=\"text\" name=\"firmanavn\" value=\"$firmanavn\"></td></tr>";
	print "<tr><td width=50%><b>".findtekst('140|Adresse', $sprog_id).":</b></td><td><input type=\"text\" name=\"adresse\" value=\"$adresse\"></td></tr>";
	print "<tr><td width=50%><b>".findtekst('650|Postnr.', $sprog_id).":</b></td><td><input type=\"text\" name=\"postnr\" value=\"$postnr\"></td></tr>";
	print "<tr><td width=50%><b>".findtekst('910|Bynavn', $sprog_id).":</b></td><td><input type=\"text\" name=\"bynavn\" value=\"$bynavn\"></td></tr>";
	print "<tr><td width=50%><b>".findtekst('917|Varenr.', $sprog_id).":</b></td><td><input type=\"text\" name=\"varenr\" value=\"$varenr\"></td></tr>";
	print "<tr><td width=50%><b>".findtekst('919|Varetekst', $sprog_id).":</b></td><td><input type=\"text\" name=\"varetekst\" value=\"$varetekst\"></td></tr>";
	print "<tr><td width=50%><b>".findtekst('920|Vis detaljer', $sprog_id).":</b></td><td align=\"right\"><label class='checkContainerVisning' style='padding-left: 20px;'><input type=\"checkbox\" name=\"detaljer\" $detaljer><span class='checkmarkVisning'></span></label></td></tr>";
	print "<tr><td>&nbsp;</td></tr>";
	print "<tr><td colspan=\"2\" align=\"center\">";
	if ($menu=='T') {
		print "<input type=\"submit\" name=\"find\" value=\"".findtekst('913|Søg', $sprog_id)."\">";
		print "&nbsp;•&nbsp;";
		print "<input onclick=\"location.href='#luk'\" type=\"button\" value=\"".findtekst('159|Fortryd', $sprog_id)."\">";
	} else {
		print "<input style=\"width:80px\" type=\"submit\" name=\"find\" value=\"".findtekst('913|Søg', $sprog_id)."\">";
		print "&nbsp;";
		print "<input style=\"width:80px\" type=\"submit\" name=\"fortryd\" value=\"".findtekst('159|Fortryd', $sprog_id)."\">";
	}
	print "</td></tr>";

	print "</tbody></table>";
}
?>
