<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|
//
// --- systemdata/ansatte.php --- patch 4.0.8 --- 2023-079-25 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20160303 PHR indsat manglende '</form>'
// 20210711 LOE - Translated some texts to Norsk and English from Dansk
// 20220614 MSC - Implementing new design
// 20230925 PHR - PHP8

@session_start();
$s_id=session_id();

$css="../css/standard.css";
$title="Personalekort";
$modulnr=1;

$afd_nr=array();
	
include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

 if ($_GET) {
	$id = $_GET['id'];
	$returside= $_GET['returside'];
	$fokus = $_GET['fokus'];
	$konto_id=$_GET['konto_id'];
 }
if ($_POST) {
	include("ansatte_save.php");
}
if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\"><a href=stamkort.php accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<div class='divSys'>";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTableSys\"><tbody>"; # -> 1
} else {
	$query = db_select("select firmanavn from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><!-- TABEL 1 -> --><tbody>";
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><!-- TABEL 1.1 -> --><tbody>";
	print "<td width=\"10%\" $top_bund<font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$font<a href=stamkort.php?returside=$returside&id=$konto_id&fokus=$fokus accesskey=L>".findtekst(30, $sprog_id)."</a></td>";#20210711 
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$font$row[firmanavn] - ".findtekst(1262, $sprog_id)."</td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$font<a href=ansatte.php?returside=$returside&fokus=$fokus&konto_id=$konto_id accesskey=N>".findtekst(39, $sprog_id)."</a><br></td>";
	print "</tbody></table><!-- <- TABEL1.1 -->";
	print "</td></tr>";
	print "<td align=center valign=center>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><!-- TABEL 1.2 -> --><tbody>";
}

include("ansatte_load.php");

print "<form name=\"ansatte\" action=\"ansatte.php?konto_id=$konto_id\" method=\"post\">";
include("ansatte_body.php");

print "<tr><td><br></td></tr>";
print "<tr><td><br></td></tr>";
print "<td><br></td><td><br></td><td><br></td>";
PRINT "<td align=center><input type=\"submit\" class='green medium button' style='width:150px;' accesskey=\"g\" value=\"".findtekst(471, $sprog_id)."\" name=\"submit\"></td>";
print "</form>";

print "
</tbody>
</table>
</td></tr>
<tr><td align=\"center\" valign=\"bottom\">
</tbody></table>
</div></div>
";


if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

?>
