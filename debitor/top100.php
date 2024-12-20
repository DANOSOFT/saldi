<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------includes/top100.php-------lap 2.9.7------2020-11-21---
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
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------
// 20170201	PHR Fjernet fejltekst i bunden.
// 20190205 PHR $sum=dkdecimal(!isset ($r['totalsum'])) ændret til $sum=if_isset ($r['totalsum']); 
// 20201121 PHR added valutakurs 

@session_start();
$s_id=session_id();
$modulnr=12;
$title="Top 100 debitorer";

$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");
include("../includes/db_query.php");

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
} else {
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
	include("../includes/sidemenu.php");
} else {
print "<tr><td colspan=\"4\" height=\"8\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
$tekst="Klik her for at lukke \"Top100\"";
print "<td width=\"10%\" $top_bund title='$tekst'><a href=../debitor/rapport.php accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund>Top 100 i perioden: $fra til $til</td>";
$tekst="Klik her for at v&aelig;lge en anden periode";
print "<td width=\"10%\" $top_bund title='$tekst'><a href=top100.php?periode=$periode&ret=on accesskey=P>Periode<br></a></td>";
print "</tbody></table>";
print "</td></tr>\n";
}
if ($ret) {
	$tekst="Skriv fra &amp; til dato som mmdd&aring;&aring;:mmdd&aring;&aring;. Hvis der kun skrives én dato, s&aelig;ttes dato til dags dato.";
	print "<form name=omsaetning action=top100.php method=post>";
	print "<tr><td colspan=4 align=center title=\"$tekst\">V&aelig;lg periode <input type=text name=periode value=\"$periode\">&nbsp;";	
	print "<input type=submit accesskey=\"O\" value=\"OK\" name=\"submit\"></td></tr>";
	print "<tr><td colspan=4><hr></td></tr>\n";
	print "</form>";
} else {
	$x=0;
	print "<tr><td>Nr.</td><td>Kontonr.</td><td>Firmanavn</td><td align=right>Oms&aelig;tning DKK</td><tr>\n";
	print "<tr><td colspan=4><hr></td></tr>\n";
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
?>
