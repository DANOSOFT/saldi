<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------/systemdata/exporter_adresser.php-----patch 4.0.8 ----2023-07-22--
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
//
// 20170430 PHR  Tilføjet bank_reg og bank_konto
// 20210714 LOE  Translated some text.

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
if ($popup) print "<td width=\"10%\" $top_bund><a href=../includes/luk.php accesskey=L>".findtekst(30, $sprog_id)."</a></td>"; #20210714
else print "<td width=\"10%\" $top_bund><a href=diverse.php?sektion=div_io accesskey=L>".findtekst(30, $sprog_id)."</a></td>";
print "<td width=\"80%\" $top_bund>".findtekst(1384, $sprog_id)."</td>";
print "<td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";
print "<td align=center valign=top>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

if (!$art) {
	print "<form name='addr_export' action='exporter_adresser.php' method='post'>";
	print "<tr><td>".findtekst(1390, $sprog_id)." ".findtekst(1355, $sprog_id)."</td>";
	print "<td><select name=\"art\">";
	print "<option value='D'>".findtekst(908, $sprog_id)."</option>";
	print "<option value='K'>".findtekst(507, $sprog_id)."</option>";
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
	print "<tr><td align=center> ".findtekst(1362, $sprog_id).": </td><td $top_bund><a href='$filnavn'>$lang_art</a></td></tr>";
	print "<tr><td align=center colspan=2> ".findtekst(1363, $sprog_id)."</td></tr>";
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
