<?php
// ---------/systemdata/exporter_varer.php---lap 3.4.4--2014-11-19------------------------
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
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20130412 Rettet i formatet
// 20140516 Sat " om alle tekster.
// 20140526 .'"'. manglede efter enhed i overskrift
// 20141119 Fjernet "*1" efter dkdecimal. Søg *1

@session_start();
$s_id=session_id();
$title="Eksporter varer";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$returside="../diverse.php";

$filnavn="../temp/varer.csv";

$fp=fopen($filnavn,"w");

$overskrift='"'."varenr".'"'.chr(9).'"'."stregkode".'"'.chr(9).'"'."beskrivelse".'"'.chr(9).'"'."kostpris".'"'.chr(9).'"'."salgspris".'"'.chr(9).'"'."vejl_pris".'"'.chr(9).'"'."notes".'"'.chr(9).'"'."enhed".'"'.chr(9).'"'."gruppe".'"'.chr(9).'"'."min_lager".'"'.chr(9).'"'."max_lager".'"'.chr(9).'"'."lokation".'"';
if ($charset=="UTF-8") $overskrift=utf8_decode($overskrift);

if (fwrite($fp, "$overskrift\r\n")) {
	$q=db_select("select varenr,stregkode,beskrivelse,kostpris,salgspris,retail_price,notes,enhed,gruppe,min_lager,max_lager,location from varer order by varenr",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$varenr=$r['varenr'];
		$beskrivelse=str_replace('"',"''",$r['beskrivelse']);
		$stregkode=$r['stregkode'];
		$kostpris=dkdecimal($r['kostpris']);# *1;
		$salgspris=dkdecimal($r['salgspris']);#*1;
		$retail_price=dkdecimal($r['retail_price']);#*1;
		$min_lager=dkdecimal($r['min_lager']);#*1;
		$max_lager=dkdecimal($r['max_lager']);#*1;

		$linje='"'.$varenr.'"'.chr(9).'"'.$stregkode.'"'.chr(9).'"'.$beskrivelse.'"'.chr(9).$kostpris.chr(9).$salgspris.chr(9).$retail_price.chr(9).'"'.$r['notes'].'"'.chr(9).'"'.$r['enhed'].'"'.chr(9).$r['gruppe'].chr(9).$min_lager.chr(9).$max_lager.chr(9).'"'.$r['location'].'"';
		$linje=str_replace("\n","",$linje);
		if ($charset=="UTF-8") $linje=utf8_decode($linje);
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

print "<tr><td align=center> H&oslash;jreklik her: </td><td $top_bund><a href='$filnavn'>varer</a></td></tr>";
print "<tr><td align=center colspan=2> V&aelig;lg \"gem destination som\"</td></tr>";

print "</tbody></table>";

?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
