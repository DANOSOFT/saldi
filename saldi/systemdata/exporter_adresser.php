<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------/systemdata/exporter_adresser.php---lap 3.6.7----2017-04-30--
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2017 saldi.dk aps
// ----------------------------------------------------------------------
//
// 2017.04.30 PHR - Tilføjet bank_reg og bank_konto

@session_start();
$s_id=session_id();
$title="Eksporter adresser";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$art=if_isset($_POST['art']);
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

if (!$art) {
	print "<form name='addr_export' action='exporter_adresser.php' method='post'>";
	print "<tr><td>Vælg eksport</td>";
	print "<td><select name=\"art\">";
	print "<option value='D'>Debitorer</option>";
	print "<option value='K'>Kreditorer</option>";
	print "</select>";
	print "</td>";
	print "<td><input type=\"submit\" name=\"eksporter\" value=\"OK\"><td></tr>";
	print "</form>";
} else {
	if ($art=='K') {
		$lang_art='Kreditorer';
		$filnavn="../temp/".trim($db."/kreditorer_".date("Y-m-d").".csv");
	} else {
		$lang_art='Debitorer';
		$filnavn="../temp/".trim($db."/debitorer_".date("Y-m-d").".csv");
	}
	eksporter($art,$filnavn);
	print "<tr><td align=center> H&oslash;jreklik her: </td><td $top_bund><a href='$filnavn'>$lang_art</a></td></tr>";
	print "<tr><td align=center colspan=2> V&aelig;lg \"gem destination som\"</td></tr>";
}
print "</tbody></table>";
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



function eksporter($art,$filnavn) {
	global $charset;
	
	$fp=fopen($filnavn,"w");
	if (fwrite($fp,chr(34)."kontonr".chr(34).";".chr(34)."firmanavn".chr(34).";".chr(34)."addr1".chr(34).";".chr(34)."addr2".chr(34).";".chr(34)."postnr".chr(34).";".chr(34)."bynavn".chr(34).";".chr(34)."land".chr(34).";".chr(34)."kontakt".chr(34).";".chr(34)."tlf".chr(34).";".chr(34)."fax".chr(34).";".chr(34)."email".chr(34).";".chr(34)."web".chr(34).";".chr(34)."notes".chr(34).";".chr(34)."kreditmax".chr(34).";".chr(34)."betalingsbet".chr(34).";".chr(34)."betalingsdage".chr(34).";".chr(34)."cvrnr".chr(34).";".chr(34)."ean".chr(34).";".chr(34)."institution".chr(34).";".chr(34)."bank_reg".chr(34).";".chr(34)."bank_konto".chr(34).";".chr(34)."gruppe".chr(34).";".chr(34)."kontoansvarlig".chr(34).";".chr(34)."oprettet".chr(34).";".chr(34)."kontakt_navn".chr(34).";".chr(34)."kontakt_addr1".chr(34).";".chr(34)."kontakt_addr2".chr(34).";".chr(34)."kontakt_postnr".chr(34).";".chr(34)."kontakt_bynavn".chr(34).";".chr(34)."kontakt_tlf".chr(34).";".chr(34)."kontakt_fax".chr(34).";".chr(34)."kontakt_email".chr(34).";".chr(34)."kontakt_notes".chr(34)."\r\n")) {
		$q=db_select("select * from adresser where art='$art' order by kontonr",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$ansatte=0;
			if ($r['kontoansvarlig']) {
				$r2=db_fetch_array(db_select("select initialer from ansatte where id='$r[kontoansvarlig]'",__FILE__ . " linje " . __LINE__));
				$kontoansvarlig=$r2['initialer'];
			} else $kontoansvarlig='';
			$kreditmax=dkdecimal($r['kreditmax']);
		$oprettet=dkdato($r['oprettet']);
		$tmp1=str_replace("\n","\\n",chr(34).$r['kontonr'].chr(34).";".chr(34).$r['firmanavn'].chr(34).";".chr(34).$r['addr1'].chr(34).";".chr(34).$r['addr2'].chr(34).";".chr(34)." ".$r['postnr'].chr(34).";".chr(34).$r['bynavn'].chr(34).";".chr(34).$r['land'].chr(34).";".chr(34).$r['kontakt'].chr(34).";".chr(34)." ".$r['tlf'].chr(34).";".chr(34)." ".$r['fax'].chr(34).";".chr(34).$r['email'].chr(34).";".chr(34).$r['web'].chr(34).";".chr(34).$r['notes'].chr(34).";".chr(34).$kreditmax.chr(34).";".chr(34).$r['betalingsbet'].chr(34).";".chr(34).$r['betalingsdage'].chr(34).";".chr(34).$r['cvrnr'].chr(34).";".chr(34).$r['ean'].chr(34).";".chr(34).$r['institution'].chr(34).";".chr(34)." ".$r['bank_reg'].chr(34).";".chr(34)." ".$r['bank_konto'].chr(34).";".chr(34).$r['gruppe'].chr(34).";".chr(34).$kontoansvarlig.chr(34).";".chr(34).$oprettet).chr(34).";";
			$tmp1=str_replace("\r","\\r",$tmp1);
			if ($charset=='UTF-8') $tmp1=utf8_decode($tmp1);
			$q2=db_select("select * from ansatte where konto_id='$r[id]' order by navn",__FILE__ . " linje " . __LINE__);
			while ($r2=db_fetch_array($q2)) {
				$ansatte++;
				$tmp2=str_replace("\n","\\n",chr(34).$r2['navn'].chr(34).";".chr(34).$r2['addr1'].chr(34).";".chr(34).$r2['addr2'].chr(34).";".chr(34)." ".$r2['postnr'].chr(34).";".chr(34).$r2['bynavn'].chr(34).";".chr(34)." ".$r2['tlf'].chr(34).";".chr(34).$r2['fax'].chr(34).";".chr(34).$r2['email'].chr(34).";".chr(34).$r2['notes'].chr(34));
				$tmp2=str_replace("\r","\\r",$tmp2);
			if ($charset=='UTF-8') $tmp2=utf8_decode($tmp2);
				$linje=$tmp1.$tmp2;
#				fwrite($fp, $linje."\r\n");
			}
			if (!$ansatte) {
				$linje=$tmp1.chr(34).chr(34).";".chr(34).chr(34).";".chr(34).chr(34).";".chr(34).chr(34).";".chr(34).chr(34).";".chr(34).chr(34).";".chr(34).chr(34).";".chr(34).chr(34);
			}
			fwrite($fp, $linje."\r\n");
	
		} 
	} 
	fclose($fp);
}


?>
