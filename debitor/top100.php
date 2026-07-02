<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------includes/top100.php-------lap 2.9.7------2020-11-21---
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
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20170201	PHR Fjernet fejltekst i bunden.
// 20190205 PHR $sum=dkdecimal(!isset ($r['totalsum'])) ændret til $sum=if_isset ($r['totalsum']); 
// 20201121 PHR added valutakurs 

@session_start();
$s_id=session_id();
$modulnr=12;
$title="Top 100";

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
	<span class=\"headerTxt\">Top 100</span>";     
	print "<div class=\"headerbtnRght\"></div>";       
	print "</div><!-- end of header -->";
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<table class='dataTable2' cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\"><tbody>\n";
} elseif ($menu=='S') {
	$tekst = findtekst('2181|Klik her for at lukke Top100', $sprog_id);
	$tilbage_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';

	print "<style>html,body{margin:0;padding:0;height:100%;overflow:hidden;}</style>\n";
	print "<div id='t1PageFlex' style='display:flex;flex-direction:column;height:100vh;box-sizing:border-box;'>\n";
	print "<div style='flex:0 0 auto;padding:8px 8px 0 8px;box-sizing:border-box;background-color:$bgcolor;'>\n";
	print "<table bgcolor='#eeeef0' width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
	print "<tr><td colspan=\"3\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>";

	print "<td width=\"10%\" align='left' title='$tekst'><a href=../debitor/rapport.php accesskey=L>
		   <button style='$buttonStyle; width:100%; display:flex; align-items:center; gap:5px; justify-content:flex-start; padding-left:3px;' onMouseOver=\"this.style.cursor='pointer'\">$tilbage_icon"
		   .findtekst('30|Tilbage', $sprog_id)."</button></a></td>";

	print "<td width='80%' style=$topStyle align=center>".findtekst('2225|Top 100 i perioden', $sprog_id).": $fra ".findtekst('904|til', $sprog_id)." $til</td>";

	$tekst = findtekst('1813|Klik her for at vælge en anden periode', $sprog_id);
	print "<td width='10%' align='center' title='$tekst' style='$topStyle'><a href=top100.php?periode=$periode&ret=on accesskey=P>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
		   .findtekst('899|Periode', $sprog_id)."</button></a></td>";

	print "</tbody></table>";
	print "</td></tr></tbody></table>";
	print "</div>\n";

	if (!$ret) {
		$t1ColgroupHtml = "<colgroup><col style='width:5%'><col style='width:15%'><col style='width:55%'><col style='width:25%'></colgroup>";
		print "<style>
#t1HeaderTitleTable { width:100%; table-layout:fixed; border-collapse:collapse; background-color:$bgcolor; }
#t1HeaderTitleTable td { padding:6px 0px; border-bottom:2px solid #ddd; }
#t1GridWrapper { flex:1 1 auto; min-height:0; overflow-y:auto; width:100%; background-color:$bgcolor; padding:0 8px; box-sizing:border-box; }
#t1GridTable { border-collapse:collapse; width:100%; table-layout:fixed; }
</style>\n";
		print "<div style='flex:0 0 auto;padding:0 8px;box-sizing:border-box;background-color:$bgcolor;'>\n";
		print "<table id='t1HeaderTitleTable' cellpadding=\"0\" cellspacing=\"0\" border=\"0\">$t1ColgroupHtml<tbody><tr>";
		print "<td align=\"left\"><b>Nr.</b></td><td align=\"left\"><b>".findtekst(276,$sprog_id)."</b></td><td align=\"left\"><b>".findtekst(360,$sprog_id)."</b></td><td align=\"right\" class='text-right'><b>".findtekst(1166,$sprog_id)." DKK</b></td>";
		print "</tr></tbody></table>";
		print "</div>\n";

		print "<div id='t1GridWrapper'><table id='t1GridTable' cellpadding=\"1\" cellspacing=\"1\" border=\"0\">$t1ColgroupHtml<tbody>\n";
	} else {
		print "<div id='t1GridWrapper' style='flex:1 1 auto;min-height:0;overflow-y:auto;width:100%;background-color:$bgcolor;padding:0 8px;box-sizing:border-box;'><table width=\"100%\" cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>\n";
	}
} else {
	print "<tr><td colspan=\"4\" height=\"8\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
	$tekst = findtekst('2181|Klik her for at lukke Top100', $sprog_id);
	print "<td width=\"10%\" $top_bund title='$tekst'><a href=../debitor/rapport.php accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund>Top 100 i perioden: $fra til $til</td>";
	$tekst="Klik her for at v&aelig;lge en anden periode";
	print "<td width=\"10%\" $top_bund title='$tekst'><a href=top100.php?periode=$periode&ret=on accesskey=P>Periode<br></a></td>";
	print "</tbody></table>";
	print "</td></tr>\n";
}
if ($ret) {
	$tekst="".findtekst(1167,$sprog_id)."";
	print "<form name=omsaetning action=top100.php method=post>";
	print "<tr><td colspan=4 align=center style=\"padding:0 8px\" title=\"$tekst\">".findtekst(1168,$sprog_id)." <input type=text name=periode value=\"$periode\">&nbsp;";	
	print "<input type=submit accesskey=\"O\" value=\"OK\" name=\"submit\"></td></tr>";
	print "<tr><td colspan=4><hr></td></tr>\n";
	print "</form>";
} else {
	$x=0;
	if ($menu != 'S') {
		print "<tr><td>Nr.</td><td>".findtekst(276,$sprog_id)."</td><td>".findtekst(360,$sprog_id)."</td><td align=right>".findtekst(1166,$sprog_id)." DKK</td><tr>\n";
		print "<tr><td colspan=4><hr></td></tr>\n";
	}
	$q = db_select("select konto_id, sum(sum*valutakurs/100) as totalsum from ordrer where (art='DO' or art= 'DK') and fakturadate>='$from' and fakturadate<='$to' and status >= '3' group by konto_id order by sum(sum*valutakurs/100) desc limit 100",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		if ($x<=100) {
			$sum=if_isset ($r['totalsum']);
			$r2=db_fetch_array(db_select("select * from adresser where id='$r[konto_id]'",__FILE__ . " linje " . __LINE__));
			if ((!isset($linjebg))||($linjebg!=$bgcolor)) {$linjebg=$bgcolor; $color='#000000';}
			else {$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\"><td>$x</td>";
			print "<td>$r2[kontonr]</td><td>$r2[firmanavn]</td><td align=right>".dkdecimal($sum,2)."</td></tr>\n";
		}
	}
	print "</tbody></table>";
}
if ($menu=='S') {
	print "</div>\n"; // close #t1GridWrapper
	if (!$ret) {
		print "<script>(function(){
	function t1AlignHeader(){
		var wrap = document.getElementById('t1GridWrapper');
		var headerRow = document.querySelector('#t1HeaderTitleTable tr');
		if (!wrap || !headerRow) return;
		var lastTd = headerRow.cells[headerRow.cells.length - 1];
		if (!lastTd) return;
		lastTd.style.paddingRight = (wrap.scrollHeight > wrap.clientHeight) ? '16px' : '';
	}
	window.addEventListener('load', t1AlignHeader);
	window.addEventListener('resize', t1AlignHeader);
	t1AlignHeader();
})();</script>\n";
	}
	print "</div>\n"; // close #t1PageFlex
}
?>
