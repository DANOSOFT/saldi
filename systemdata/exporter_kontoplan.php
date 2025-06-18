<?php
// ----/systemdata/exporter_kontoplan.php-----patch 4.0.8 ----2023-07-22-----
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
// ----------------------------------------------------------------------
// 20190225 MSC - Rettet topmenu design
// 20210713 LOE - Translated these texts to Norsk and English
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding

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
		if ($charset=="UTF-8") $beskrivelse=mb_convert_encoding($beskrivelse, 'ISO-8859-1', 'UTF-8');
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
	print "<div class=\"headerbtnLft\"><a class='button red small' href=diverse.php?sektion=div_io accesskey=L>".findtekst(30, $sprog_id)."</a></div>\n"; #20210713
	print "</div><!-- end of header -->";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_div_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

	print "<tr><td align=center> ".findtekst(1362, $sprog_id).": </td><td><a class='button blue medium' href='$filnavn'>".findtekst(612, $sprog_id)."</a></td></tr>";
	print "<tr><td align=center colspan=2> ".findtekst(1363, $sprog_id)."</td></tr>";
} else {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><a href=diverse.php?sektion=div_io accesskey=L>".findtekst(30, $sprog_id)."</a></td>";
	print "<td width=\"80%\" $top_bund>$title</td>";
	print "<td width=\"10%\" $top_bund><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<td align=center valign=top>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

	print "<tr><td align=center> ".findtekst(1362, $sprog_id).": </td><td $top_bund><a href='$filnavn'>".findtekst(612, $sprog_id)."</a></td></tr>";
	print "<tr><td align=center colspan=2> ".findtekst(1363, $sprog_id)."</td></tr>";
}

print "</tbody></table>";

?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
