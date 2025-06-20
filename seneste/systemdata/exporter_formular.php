<?php
// ---------/systemdata/exporter_kontoplan.php---ver. 3.2.2----2011-06-30-----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$title="Eksporter formularer";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$regnskabsaar=$_GET['aar'];

$returside="../diverse.php";

$filnavn="../temp/".$db."/"."formularer_".date("Y-m-d").".csv";

$fp=fopen($filnavn,"w");
if (fwrite($fp,"formular".chr(9)."art".chr(9)."beskrivelse".chr(9)."justering".chr(9)."xa".chr(9)."ya".chr(9)."xb".chr(9)."yb".chr(9)."str".chr(9)."color".chr(9)."font".chr(9)."fed".chr(9)."kursiv".chr(9)."side".chr(9)."sprog\r\n")) {
	$q=db_select("select * from formularer order by sprog,formular,art",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$beskrivelse=$r['beskrivelse'];
		if ($charset=="UTF-8") $beskrivelse=utf8_decode($beskrivelse);
		$r['xa']*=1;$r['ya']*=1;$r['xb']*=1;$r['yb']*=1;$r['str']*=1;
		$linje=str_replace("\n","",$r['formular'].chr(9).$r['art'].chr(9).$beskrivelse.chr(9).$r['justering'].chr(9).$r['xa'].chr(9).$r['ya'].chr(9).$r['xb'].chr(9).$r['yb'].chr(9).$r['str'].chr(9).$r['color'].chr(9).$r['font'].chr(9).$r['fed'].chr(9).$r['kursiv'].chr(9).$r['side'].chr(9).$r['sprog']);
		fwrite($fp, $linje."\r\n");
	} 
} 
fclose($fp);

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

print "<tr><td align=center> H&oslash;jreklik her: </td><td $top_bund><a href='$filnavn'>Formularkopi</a></td></tr>";
print "<tr><td align=center colspan=2> V&aelig;lg \"gem destination som\"</td></tr>";

print "</tbody></table>";

?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
