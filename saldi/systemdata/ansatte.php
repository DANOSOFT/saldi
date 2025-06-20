<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|
//
// -------systemdata/ansatte.php--------lap 3.6.2-------2016-03-03-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2016 saldi.dk aps
// ----------------------------------------------------------------------
// 20160303 PHR indsat manglende '</form>'

@session_start();
$s_id=session_id();

$css="../css/standard.css";
$title="Personalekort";
$modulnr=1;
	
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
#	print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">";
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">\n";
	print "<div class=\"headerbtnLft\"></div>\n";
#	print "<span class=\"headerTxt\">Systemsetup</span>\n";     
#	print "<div class=\"headerbtnRght\"><!--<a href=\"index.php?page=../debitor/debitorkort.php;title=debitor\" class=\"button green small right\">Ny debitor</a>--></div>";       
	print "</div><!-- end of header -->";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontent\">\n";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\"><tbody>"; # -> 1
} else {
	$query = db_select("select firmanavn from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><!-- TABEL 1 -> --><tbody>";
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><!-- TABEL 1.1 -> --><tbody>";
	print "<td width=\"10%\" $top_bund<font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$font<a href=stamkort.php?returside=$returside&id=$konto_id&fokus=$fokus accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$font$row[firmanavn] - Ansatte</td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$font<a href=ansatte.php?returside=$returside&fokus=$fokus&konto_id=$konto_id accesskey=N>Ny</a><br></td>";
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
PRINT "<td align=center><input type=\"submit\" accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
print "</form>";
?>
</tbody>
</table><!-- <- TABEL 1.2 -->
</td></tr>
<tr><td align="center" valign="bottom">
</tbody></table><!-- <- TABEL 1 -->
</body></html>
