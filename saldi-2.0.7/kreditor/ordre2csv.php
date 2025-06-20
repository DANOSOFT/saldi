<?php
// -------------kreditor/ordre2csv.php----------lap 2.0.5------2009-02-24----
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

@session_start();
$s_id=session_id();
$title="Ordreeksport";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$ordre_id=if_isset($_GET['id']);
if (!$ordre_id) $ordre_id=0;

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #A
print "<tr><td valign=top>";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; #B1
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=../includes/luk.php accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$title</td>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
print "</tbody></table></td></tr>"; #B1 slut
print "<tr><td valign=top>";
print "<table width=\"400\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; #B2

$filnavn="../temp/".$db."/".$ordre_id.".csv";
#echo "Filnavn $filnavn<br>";
$fp=fopen($filnavn,"w");

fwrite($fp,"Pos".chr(9)."Vores varenummer".chr(9)."Deres varenummer".chr(9)."Beskrivelse".chr(9)."Antal".chr(9)."Pris".chr(9)."Rabat".chr(9)."I alt".chr(9)."\n");
$q=db_select("select * from ordrelinjer where ordre_id = $ordre_id order by posnr");
while ($r=db_fetch_array($q)) {
	$beskrivelse=str_replace(chr(9)," ",$r['beskrivelse']);
	$varenr=str_replace(chr(9)," ",$r['varenr']);
	$lev_vnr=str_replace(chr(9)," ",$r['lev_vnr']);
	if ($charset=='UTF-8') {
		$beskrivelse=utf8_decode($beskrivelse); 
	$varenr=utf8_decode($varenr);
	$lev_vnr=utf8_decode($lev_vnr);
	}
	$antal=dkdecimal($r['antal']);
	$pris=dkdecimal($r['pris']);
	$rabat=dkdecimal($r['rabat']);
	$ialt=dkdecimal($r['pris']*$r['antal']-($r['pris']*$r['antal']/100*$r['rabat']));
	
	fwrite($fp,$r[posnr].chr(9).$varenr.chr(9).$lev_vnr.chr(9).$beskrivelse.chr(9).$antal.chr(9).$pris.chr(9).$rabat.chr(9).$ialt."\n");
}
fclose($fp);
	
print "<tr><td align=center> Klik her: </td><td $top_bund title=\"&Aring;bner csv filen. H&oslash;jreklik for at gemme\"> <a href=\"$filnavn\">&Aring;ben ordrefil</a></td></tr>";

print "</tbody></table></td></tr>"; #B2 slut
print "</tbody></table>"; #A slut
?>
</body></html>
