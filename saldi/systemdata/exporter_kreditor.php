<?php
//                         ___   _   _   __  _
//                        / __| / \ | | |  \| |
//                        \__ \/ _ \| |_| | | |
//                        |___/_/ \_|___|__/|_|
//
// ------------/systemdata/exporter_dkreditor.php---lap 3.6.6----2017-01-09--
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2017 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$title="Eksporter kreditorer";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$returside="../diverse.php";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
if ($popup) print "<td width=\"10%\" $top_bund><a href=../includes/luk.php accesskey=L>Luk</a></td>"; 
else print "<td width=\"10%\" $top_bund><a href=diverse.php?sektion=div_io accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund>$title</td>";
print "<td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";
print "<td align=center valign=top>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

if (!$_POST['art']) {
print "<tr><td>Vælg eksport</td>";
print "<td><select navn=\"art\">";
print "<option value='D'>Debitorer</option>";
print "<option value='K'>Kreditorer</option>";
print "</select>";
print "</td>";
print "<td><input type=\"submit\" name=\"eksporter\" value=\"OK\"><td></tr>";
} else {
eksporter($art);
print "<tr><td align=center> H&oslash;jreklik her: </td><td $top_bund><a href='$filnavn'>Kreditorer</a></td></tr>";
print "<tr><td align=center colspan=2> V&aelig;lg \"gem destination som\"</td></tr>";
print "</tbody></table>";
}
print "</tbody>";
print "</table>";
print "</td></tr>";
print "<tr><td align = \"center\" valign = \"bottom\">";
print "<table width=\"100%\" align=\"center\" border=\"1\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<td width=\"100%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
print "</tbody></table>";
print "</td></tr>";
print "</tbody></table>";
print "</body></html>";



function eksporter($art) {
	$filnavn="../temp/".trim($db."_kreditorer_".date("Y-m-d").".csv");

	$fp=fopen($filnavn,"w");

	if (fwrite($fp,"kontonr".";"."firmanavn".";"."addr1".";"."addr2".";"."postnr".";"."bynavn".";"."land".";"."kontakt".";"."tlf".";"."fax".";"."email".";"."web".";"."notes".";"."kreditmax".";"."betalingsbet".";"."betalingsdage".";"."cvrnr".";"."ean".";"."institution".";"."gruppe".";"."kontoansvarlig".";"."oprettet".";"."kontakt_navn".";"."kontakt_addr1".";"."kontakt_addr2".";"."kontakt_postnr".";"."kontakt_bynavn".";"."kontakt_tlf".";"."kontakt_fax".";"."kontakt_email".";"."kontakt_notes]\r\n")) {
		$q=db_select("select * from adresser where art='K' order by kontonr",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$ansatte=0;
			if ($r['kontoansvarlig']) {
				$r2=db_fetch_array(db_select("select initialer from ansatte where id='$r[kontoansvarlig]'",__FILE__ . " linje " . __LINE__));
				$kontoansvarlig=$r2['initialer'];
			} else $kontoansvarlig='';
			$kreditmax=dkdecimal($r['kreditmax']);
		$oprettet=dkdato($r['oprettet']);
		
			$tmp1=str_replace("\n","\\n",$r['kontonr'].";".chr(32).$r['firmanavn'].chr(32).";".chr(32).$r['addr1'].chr(32).";".chr(32).$r['addr2'].chr(32).";".chr(32).$r['postnr'].chr(32).";".chr(32).$r['bynavn'].chr(32).";".chr(32).$r['land'].chr(32).";".chr(32).$r['kontakt'].chr(32).";".chr(32).$r['tlf'].chr(32).";".chr(32).$r['fax'].chr(32).";".chr(32).$r['email'].chr(32).";".chr(32).$r['web'].chr(32).";".chr(32).$r['notes'].chr(32).";".$kreditmax.";".chr(32).$r['betalingsbet'].chr(32).";".$r['betalingsdage'].";".chr(32).$r['cvrnr'].chr(32).";".chr(32).$r['ean'].chr(32).";".chr(32).$r['institution'].chr(32).";".$r['gruppe'].";".chr(32).$kontoansvarlig.chr(32).";".chr(32).$oprettet);
			$tmp1=str_replace("\r","\\r",$tmp1);
			if ($charset=='UTF-8') $tmp1=utf8_decode($tmp1);
			$q2=db_select("select * from ansatte where konto_id='$r[id]' order by navn",__FILE__ . " linje " . __LINE__);
			while ($r2=db_fetch_array($q2)) {
				$ansatte++;
				$tmp2=str_replace("\n","\\n",$r2['navn'].chr(32).";".chr(32).$r2['addr1'].chr(32).";".chr(32).$r2['addr2'].chr(32).";".chr(32).$r2['postnr'].chr(32).";".chr(32).$r2['bynavn'].chr(32).";".chr(32).$r2['tlf'].chr(32).";".chr(32).$r2['fax'].chr(32).";".chr(32).$r2['email'].chr(32).";".chr(32).$r2['notes']);
				$tmp2=str_replace("\r","\\r",$tmp2);
			if ($charset=='UTF-8') $tmp2=utf8_decode($tmp2);
				$linje=$tmp1.chr(32).";".chr(32).$tmp2;
				fwrite($fp, $linje."\r\n");
			}
			if (!$ansatte) {
				$linje=$tmp1.chr(32).";".chr(32).chr(32).";".chr(32).chr(32).";".chr(32).chr(32).";".chr(32).chr(32).";".chr(32).chr(32).";".chr(32).chr(32).";".chr(32).chr(32).";".chr(32).chr(32).";".chr(32);
				fwrite($fp, $linje."\r\n");
			}
		} 
	} 
	fclose($fp);
}


?>
