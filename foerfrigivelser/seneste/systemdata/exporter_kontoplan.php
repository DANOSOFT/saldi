<?php
// --------------------------------------/systemdata/exporter_kontoplan.php---lap 1.1.4----------------------------
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$title="Eksporter kontoplan";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/db_query.php");

$regnskabsaar=$_GET['aar'];

$returside="../diverse.php";

$filnavn="../temp/".trim($db."_ktoplan_".date("Y-m-d").".csv");

$fp=fopen($filnavn,"w");
if (fwrite($fp, "kontonr".chr(9)."beskrivelse".chr(9)."kontotype".chr(9)."momskode".chr(9)."fra_konto\r\n")) {
	$q=db_select("select * from kontoplan where regnskabsaar='$regnskabsaar' order by kontonr");
	while ($r=db_fetch_array($q)) {
		$linje=str_replace("\n","",$r[kontonr].chr(9).$r[beskrivelse].chr(9).$r[kontotype].chr(9).$r[moms].chr(9).$r[fra_kto]);
		fwrite($fp, $linje."\r\n");
	} 
} 
fclose($fp);

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund>$font<small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>";
print "<td width=\"80%\" $top_bund>$font<small>$title</small></td>";
print "<td width=\"10%\" $top_bund>$font<small><br></small></td>";
print "</tbody></table>";
print "</td></tr>";
print "<td align=center valign=top>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

print "<tr><td align=center>$font Højreklik her: </td><td $top_bund>$font<a href='$filnavn'>Kontoplan</a></td></tr>";
print "<tr><td align=center colspan=2>$font V&aelig;lg \"gem destination som\"</td></tr>";

print "</tbody></table>";

?>
</tbody>
</table>
</td></tr>
<tr><td align = "center" valign = "bottom">
<table width="100%" align="center" border="1" cellspacing="0" cellpadding="0"><tbody>
<td width="100%" bgcolor="<?php echo $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
