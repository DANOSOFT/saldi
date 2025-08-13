<?php
// ----/systemdata/exporter_kontoplan.php-----patch 4.0.8 ----2023-07-22----
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
// 20210713  LOE - Translated some texts here
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
@session_start();
$s_id=session_id();
$title="Eksporter formularer";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$regnskabsaar=$_GET['aar'];

$returside="diverse.php?sektion=div_io";

$filnavn="../temp/".$db."/"."formularer_".date("Y-m-d").".csv";

$fp=fopen($filnavn,"w");
if (fwrite($fp,"formular".chr(9)."art".chr(9)."beskrivelse".chr(9)."justering".chr(9)."xa".chr(9)."ya".chr(9)."xb".chr(9)."yb".chr(9)."str".chr(9)."color".chr(9)."font".chr(9)."fed".chr(9)."kursiv".chr(9)."side".chr(9)."sprog\r\n")) {
	$q=db_select("select * from formularer order by sprog,formular,art",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$beskrivelse=$r['beskrivelse'];
		if ($charset=="UTF-8") $beskrivelse=mb_convert_encoding($beskrivelse, 'ISO-8859-1', 'UTF-8');
		$r['xa']*=1;$r['ya']*=1;$r['xb']*=1;$r['yb']*=1;$r['str']*=1;
		$linje=str_replace("\n","",$r['formular'].chr(9).$r['art'].chr(9).$beskrivelse.chr(9).$r['justering'].chr(9).$r['xa'].chr(9).$r['ya'].chr(9).$r['xb'].chr(9).$r['yb'].chr(9).$r['str'].chr(9).$r['color'].chr(9).$r['font'].chr(9).$r['fed'].chr(9).$r['kursiv'].chr(9).$r['side'].chr(9).$r['sprog']);
		fwrite($fp, $linje."\r\n");
	} 
} 
fclose($fp);

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1 
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1

print "<td width=\"170px\"><a href=\"$returside\" accesskey=\"L\">
       <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst(30, $sprog_id)."</button></a></td>

       <td align='center' style='$topStyle'>".findtekst(1374, $sprog_id)."<br></td>

       <td width=\"170px\" style='$topStyle'><br></td></tr>
       </tbody></table></td></tr>"; # <- tabel 1.1.1

print "</tr></tbody></table></td></tr>";
print "<td align=center valign=top>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

print "<tr><td align=center> ".findtekst(1362, $sprog_id).": </td><td $top_bund><a href='$filnavn'>".findtekst(1373, $sprog_id)."</a></td></tr>";
print "<tr><td align=center colspan=2> ".findtekst(1363, $sprog_id)."</td></tr>";

print "</tbody></table>";

?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
