<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------debitor/postnr.php--------------2026-01-20---
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
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$modulnr=12;
$title="Top 100 Postnr";

$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");
include("../includes/db_query.php");
include("../includes/topline_settings.php");
include("../includes/row-hover-style.js.php");

$periode=if_isset($_GET['periode'])? $_GET['periode']:Null;
$ret=if_isset($_GET['ret'])? $_GET['ret']:Null;
if (isset($_POST['periode'])) $periode=$_POST['periode'];

$day=date("d");
$month=date("m");
$year=date("y");

$tmp=$year-1;
if ($tmp<10) $tmp="0".$tmp;
if (!$periode) $periode = "$day"."$month"."$tmp".":"."$day"."$month"."$year";	
list($fra,$til)=explode(":",$periode);
if (!$til) $til=date("dmY");
$from=usdate($fra);
$to=usdate($til);
$fra=dkdato($from);
$til=dkdato($to);
if ($menu=='T') {
	print "<center><table width = 75% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
} elseif ($menu != 'S') {
	print "<center><table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
}
if ($menu=='T') {
	$leftbutton="<a class='button red small' title=\"Klik her for at komme til startsiden\" href=\"../debitor/rapport.php\" accesskey=\"L\">Luk</a>";
	$rightbutton=NULL;
	$vejledning=NULL;
	include("../includes/top_header.php");
	include("../includes/top_menu.php");
	print "<div id=\"header\">
	<div class=\"headerbtnLft\">$leftbutton</div>
	<span class=\"headerTxt\">Top 100 Postnr</span>";
	print "<div class=\"headerbtnRght\"></div>";
	print "</div><!-- end of header -->";
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<table class='dataTable2' cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\"><tbody>\n";
} elseif ($menu=='S') {
	// Grid Framework sticky header — mirrors includes/rapportfunc.php's kontosaldo()
	// $menu=='S' branch (the proven, already-debugged pattern for this exact layout: a
	// blue header bar + column-title row that must stay fixed above a separately
	// scrolling data table, no footer). html/body scrolling is disabled so the page can
	// never scroll past the fixed top region; only #pnGridWrapper scrolls. The header
	// titles stay pixel-aligned with the data columns via a shared colgroup, plus a small
	// JS compensation for the scrollbar width #pnGridWrapper grows once it overflows.
	$tekst = findtekst('2181|Klik her for at lukke Top100', $sprog_id);
	// Same back icon + left-aligned flex button as the Grid Framework header in
	// includes/rapportfunc.php's kontokort() (General Ledger), $menu=='S' branch.
	$tilbage_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';

	print "<style>html,body{margin:0;padding:0;height:100%;overflow:hidden;}</style>\n";
	print "<div id='pnPageFlex' style='display:flex;flex-direction:column;height:100vh;box-sizing:border-box;'>\n";
	print "<div style='flex:0 0 auto;padding:8px 8px 0 8px;box-sizing:border-box;background-color:$bgcolor;'>\n";
	print "<table bgcolor='#eeeef0' width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
	print "<tr><td colspan=\"5\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B

	print "<td width=\"10%\" align='left' title='$tekst'><a href=../debitor/rapport.php accesskey=L>
		   <button style='$buttonStyle; width:100%; display:flex; align-items:center; gap:5px; justify-content:flex-start; padding-left:3px;' onMouseOver=\"this.style.cursor='pointer'\">$tilbage_icon"
		   .findtekst('30|Tilbage', $sprog_id)."</button></a></td>"; #20210702

	print "<td width='80%' style=$topStyle align=center>Top Postnr i perioden: $fra - $til</td>";

	$tekst = findtekst('1813|Klik her for at vælge en anden periode', $sprog_id);
	print "<td width='10%' align='center' title='$tekst' style='$topStyle'><a href=postnr.php?periode=$periode&ret=on accesskey=P>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
		   .findtekst('899|Periode', $sprog_id)."</button></a></td>";

	print "</tbody></table>";
	print "</td></tr></tbody></table>";
	print "</div>\n"; // <- close flex:0 wrapper around the blue header bar

	if (!$ret) {
		// Same column widths on both tables (via colgroup), so the title row lines up
		// exactly with the data columns below it.
		$pnColgroupHtml = "<colgroup><col style='width:6%'><col style='width:14%'><col style='width:40%'><col style='width:18%'><col style='width:22%'></colgroup>";
		print "<style>
#pnHeaderTitleTable { width:100%; table-layout:fixed; border-collapse:collapse; background-color:$bgcolor; }
#pnHeaderTitleTable td { padding:6px 0px; border-bottom:2px solid #ddd; }
#pnGridWrapper { flex:1 1 auto; min-height:0; overflow-y:auto; width:100%; background-color:$bgcolor; padding:0 8px; box-sizing:border-box; }
#pnGridTable { border-collapse:collapse; width:100%; table-layout:fixed; }
</style>\n";
		// Column-title row sits in normal flow (flex:0 0 auto), outside the scrollable
		// area — same approach as the blue bar above it, so it can never scroll away.
		print "<div style='flex:0 0 auto;padding:0 8px;box-sizing:border-box;background-color:$bgcolor;'>\n";
		print "<table id='pnHeaderTitleTable' cellpadding=\"0\" cellspacing=\"0\" border=\"0\">$pnColgroupHtml<tbody><tr>";
		print "<td align=\"left\"><b>Nr.</b></td><td align=\"left\"><b>Postnr</b></td><td align=\"left\"><b>By</b></td><td align=\"right\" class='text-right'><b>Antal</b></td><td align=\"right\" class='text-right'><b>" . findtekst(1166, $sprog_id) . " DKK</b></td>";
		print "</tr></tbody></table>";
		print "</div>\n";

		print "<div id='pnGridWrapper'><table id='pnGridTable' cellpadding=\"1\" cellspacing=\"1\" border=\"0\">$pnColgroupHtml<tbody>\n";
	} else {
		print "<div id='pnGridWrapper' style='flex:1 1 auto;min-height:0;overflow-y:auto;width:100%;background-color:$bgcolor;padding:0 8px;box-sizing:border-box;'><table width=\"100%\" cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>\n";
	}
} else {
	print "<tr><td colspan=\"4\" height=\"8\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
	$tekst = findtekst('2181|Klik her for at lukke Top100', $sprog_id);
	print "<td width=\"10%\" $top_bund title='$tekst'><a href=../debitor/rapport.php accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund>Top 100 Postnr i perioden: $fra til $til</td>";
	$tekst="Klik her for at v&aelig;lge en anden periode";
	print "<td width=\"10%\" $top_bund title='$tekst'><a href=postnr.php?periode=$periode&ret=on accesskey=P>Periode<br></a></td>";
	print "</tbody></table>";
	print "</td></tr>\n";
}
if ($ret) {
	$tekst="".findtekst(1167,$sprog_id)."";
	print "<form name=omsaetning action=postnr.php method=post>";
	print "<tr><td colspan=4 align=center style=\"padding:0 8px\" title=\"$tekst\">".findtekst(1168,$sprog_id)." <input type=text name=periode value=\"$periode\">&nbsp;";	
	print "<input type=submit accesskey=\"O\" value=\"OK\" name=\"submit\"></td></tr>";
	print "<tr><td colspan=4><hr></td></tr>\n";
	print "</form>";
} else {
	$x=0;
	if ($menu != 'S') {
		// For $menu=='S' the column-title row is already printed above, inside the
		// sticky header container alongside the blue bar.
		print "<tr><td>Nr.</td><td>Postnr</td><td>By</td><td align=right>Antal</td><td align=right>".findtekst(1166,$sprog_id)." DKK</td><tr>\n";
		print "<tr><td colspan=5><hr></td></tr>\n";
	}
	$q = db_select("select postnr, bynavn, count(*) as antal, sum(sum*valutakurs/100) as totalsum from ordrer where (art='DO' or art= 'DK') and fakturadate>='$from' and fakturadate<='$to' and status >= '3' and postnr != '' group by postnr, bynavn order by sum(sum*valutakurs/100) desc limit 100",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		if ($x<=100) {
			$sum=if_isset ($r['totalsum']);
			$antal = $r['antal'];
			$postnr = $r['postnr'];
			$bynavn = $r['bynavn'];
			if ((!isset($linjebg))||($linjebg!=$bgcolor)) {$linjebg=$bgcolor; $color='#000000';}
			else {$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\"><td>$x</td>";
			print "<td>$postnr</td><td>$bynavn</td><td align=right>$antal</td><td align=right>".dkdecimal($sum,2)."</td></tr>\n";
		}
	}
	print "</tbody></table>";
}
if ($menu=='S') {
	print "</div>\n"; // <- close #pnGridWrapper
	if (!$ret) {
		// When #pnGridWrapper grows a vertical scrollbar, its width eats into the last
		// (DKK) column, shifting the data left of where the header sits. Compensate by
		// padding the header's last cell to match, so the columns stay aligned.
		print "<script>(function(){
	function pnAlignHeader(){
		var wrap = document.getElementById('pnGridWrapper');
		var headerRow = document.querySelector('#pnHeaderTitleTable tr');
		if (!wrap || !headerRow) return;
		var lastTd = headerRow.cells[headerRow.cells.length - 1];
		if (!lastTd) return;
		lastTd.style.paddingRight = (wrap.scrollHeight > wrap.clientHeight) ? '16px' : '';
	}
	window.addEventListener('load', pnAlignHeader);
	window.addEventListener('resize', pnAlignHeader);
	pnAlignHeader();
})();</script>\n";
	}
	print "</div>\n"; // <- close #pnPageFlex
}
?>
