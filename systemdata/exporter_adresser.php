<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------/systemdata/exporter_adresser.php-----ver 5.1.1 ----2026-11-13--
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// 20170430 PHR  Tilføjet bank_reg og bank_konto
// 20210714 LOE  Translated some text.
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20260916 SS Declared $permission_key (roles & permissions, phase 3)
// 20261008 SS The page on the new page head (page_bar) instead of the blue bar: title, Tilbage only outside the shell.
// 20261031 CL/ASR Tilbage goes to Indstillinger > Import & eksport (settingsSection.php?s=import_export.data), not via the old diverse.php?sektion=div_io redirect.
// 20261101 CL/ASR Tilbage is the shared settings button above the title (settings_back_button(), css/saldi-kit.css), as on every settings page, instead of page_bar's link.
//                 It goes to the settings page the user came from, or the hub; Alt+L kept.
// 20261102 CL/ASR Tilbage goes to the hub, a fixed target, so it never loops between settings pages; settings_back_button() adds Alt+L itself.
//                 The kit is linked as ?v=20261102.
// 20261102 CL/ASR The standard settings layout (settings_layout_open(), includes/settings/layout.php; owner, 2 Nov 2026: every page reached from Indstillinger keeps the left menu).
//                 The left menu of Finans with Import & eksport marked, Tilbage above the title back to Import & eksport, the breadcrumb Indstillinger / Finans / Import & eksport / this page.
//                 The page's own Tilbage and page_bar title are gone; its form and steps are unchanged.
//                 The old blue-grey bottom bar under the content is gone; in the content column it was an empty strip right under the form.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
@session_start();
$s_id=session_id();
$title="Eksporter adresser";
$css="../css/standard.css";

include("../includes/connect.php");
$permission_key = 'settings.import_export';
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
$art=if_isset($_POST['art']);
$returside="settingsSection.php?s=import_export.data";
// The standard settings layout (includes/settings/layout.php): Tilbage, the title and the left menu with the page's row marked.
include_once("../includes/settings/layout.php");
settings_layout_open();
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1 
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1


print "</tr></tbody></table></td></tr>";
print "<td align=center valign=top>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

if (!$art) {
	print "<form name='addr_export' action='exporter_adresser.php' method='post'>";
	print "<tr><td>".findtekst(1390, $sprog_id)." ".findtekst(1355, $sprog_id)."</td>";
	print "<td><select name=\"art\">";
	print "<option value='D'>".findtekst(908, $sprog_id)."</option>";
	print "<option value='K'>".findtekst(607, $sprog_id)."</option>";
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
print "</tbody></table>";
settings_layout_close();
print "</body></html>";



function eksporter($art,$filnavn) {
	global $charset;
	
	$fp=fopen($filnavn,"w");
	if (fwrite($fp,chr(34)."kontonr".chr(34).";".chr(34)."firmanavn".chr(34).";".chr(34)."addr1".chr(34).";".chr(34)."addr2".chr(34).";".chr(34)."postnr".chr(34).";".chr(34)."bynavn".chr(34).";".chr(34)."land".chr(34).";".chr(34)."kontakt".chr(34).";".chr(34)."tlf".chr(34).";".chr(34)."mobile".chr(34).";".chr(34)."email".chr(34).";".chr(34)."web".chr(34).";".chr(34)."notes".chr(34).";".chr(34)."kreditmax".chr(34).";".chr(34)."betalingsbet".chr(34).";".chr(34)."betalingsdage".chr(34).";".chr(34)."cvrnr".chr(34).";".chr(34)."ean".chr(34).";".chr(34)."institution".chr(34).";".chr(34)."bank_reg".chr(34).";".chr(34)."bank_konto".chr(34).";".chr(34)."gruppe".chr(34).";".chr(34)."kontoansvarlig".chr(34).";".chr(34)."oprettet".chr(34).";".chr(34)."kontakt_navn".chr(34).";".chr(34)."kontakt_addr1".chr(34).";".chr(34)."kontakt_addr2".chr(34).";".chr(34)."kontakt_postnr".chr(34).";".chr(34)."kontakt_bynavn".chr(34).";".chr(34)."kontakt_tlf".chr(34).";".chr(34)."kontakt_mobile".chr(34).";".chr(34)."kontakt_email".chr(34).";".chr(34)."kontakt_notes".chr(34)."\r\n")) {
		$q=db_select("select * from adresser where art='$art' order by kontonr",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$ansatte=0;
			if ($r['kontoansvarlig']) {
				$r2=db_fetch_array(db_select("select initialer from ansatte where id='$r[kontoansvarlig]'",__FILE__ . " linje " . __LINE__));
				$kontoansvarlig=$r2['initialer'];
			} else $kontoansvarlig='';
			$kreditmax=dkdecimal($r['kreditmax']);
		$oprettet=dkdato($r['oprettet']);
		$tmp1=str_replace("\n","\\n",chr(34).$r['kontonr'].chr(34).";".chr(34).$r['firmanavn'].chr(34).";".chr(34).$r['addr1'].chr(34).";".chr(34).$r['addr2'].chr(34).";".chr(34)." ".$r['postnr'].chr(34).";".chr(34).$r['bynavn'].chr(34).";".chr(34).$r['land'].chr(34).";".chr(34).$r['kontakt'].chr(34).";".chr(34)." ".$r['tlf'].chr(34).";".chr(34)." ".$r['mobile'].chr(34).";".chr(34).$r['email'].chr(34).";".chr(34).$r['web'].chr(34).";".chr(34).$r['notes'].chr(34).";".chr(34).$kreditmax.chr(34).";".chr(34).$r['betalingsbet'].chr(34).";".chr(34).$r['betalingsdage'].chr(34).";".chr(34).$r['cvrnr'].chr(34).";".chr(34).$r['ean'].chr(34).";".chr(34).$r['institution'].chr(34).";".chr(34)." ".$r['bank_reg'].chr(34).";".chr(34)." ".$r['bank_konto'].chr(34).";".chr(34).$r['gruppe'].chr(34).";".chr(34).$kontoansvarlig.chr(34).";".chr(34).$oprettet).chr(34).";";
			$tmp1=str_replace("\r","\\r",$tmp1);
			if ($charset=='UTF-8') $tmp1=mb_convert_encoding($tmp1, 'ISO-8859-1', 'UTF-8');
			$q2=db_select("select * from ansatte where konto_id='$r[id]' order by navn",__FILE__ . " linje " . __LINE__);
			while ($r2=db_fetch_array($q2)) {
				$ansatte++;
				$tmp2=str_replace("\n","\\n",chr(34).$r2['navn'].chr(34).";".chr(34).$r2['addr1'].chr(34).";".chr(34).$r2['addr2'].chr(34).";".chr(34)." ".$r2['postnr'].chr(34).";".chr(34).$r2['bynavn'].chr(34).";".chr(34)." ".$r2['tlf'].chr(34).";".chr(34).$r2['mobile'].chr(34).";".chr(34).$r2['email'].chr(34).";".chr(34).$r2['notes'].chr(34));
				$tmp2=str_replace("\r","\\r",$tmp2);
			if ($charset=='UTF-8') $tmp2=mb_convert_encoding($tmp2, 'ISO-8859-1', 'UTF-8');
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
