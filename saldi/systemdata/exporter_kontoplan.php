<?php
// ---------/systemdata/exporter_kontoplan.php---lap 2.0.9--2009-08-19------------------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2019.02.25 MSC - Rettet topmenu design

@session_start();
$s_id=session_id();
$title="Eksporter kontoplan";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$regnskabsaar=$_GET['aar'];

$returside="../diverse.php";

$filnavn="../temp/".trim($db."_ktoplan_".date("Y-m-d").".csv");

$fp=fopen($filnavn,"w");
if (fwrite($fp, "kontonr".chr(9)."beskrivelse".chr(9)."kontotype".chr(9)."momskode".chr(9)."fra_konto\r\n")) {
	$q=db_select("select * from kontoplan where regnskabsaar='$regnskabsaar' order by kontonr",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$beskrivelse=$r['beskrivelse'];
		if ($charset=="UTF-8") $beskrivelse=utf8_decode($beskrivelse);
		$linje=str_replace("\n","",$r['kontonr'].chr(9).$beskrivelse.chr(9).$r['kontotype'].chr(9).$r['moms'].chr(9).$r['fra_kto']);
		fwrite($fp, $linje."\r\n");
	} 
} 
fclose($fp);

if ($menu=='T') {
	$border="0";
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">\n";
	print "<div class=\"headerbtnLft\"><a class='button red small' href=diverse.php?sektion=div_io accesskey=L>Luk</a></div>\n";
	print "</div><!-- end of header -->";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_div_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

	print "<tr><td align=center> H&oslash;jreklik her: </td><td><a class='button blue medium' href='$filnavn'>Kontoplan</a></td></tr>";
	print "<tr><td align=center colspan=2> V&aelig;lg \"gem destination som\"</td></tr>";
} else {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><a href=diverse.php?sektion=div_io accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund>$title</td>";
	print "<td width=\"10%\" $top_bund><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<td align=center valign=top>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

	print "<tr><td align=center> H&oslash;jreklik her: </td><td $top_bund><a href='$filnavn'>Kontoplan</a></td></tr>";
	print "<tr><td align=center colspan=2> V&aelig;lg \"gem destination som\"</td></tr>";
}

print "</tbody></table>";

?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
