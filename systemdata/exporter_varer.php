<?php
//                      ___   _   _   ___  _     ___  _ _
//                     / __| / \ | | |   \| |   |   \| / /
//                     \__ \/ _ \| |_| |) | | _ | |) |  <
//                     |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------/systemdata/exporter_varer.php-----ver 5.1.0 ----2026-11-02---
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
// 20130412 Rettet i formatet
// 20140516 Sat " om alle tekster.
// 20140526 .'"'. manglede efter enhed i overskrift
// 20141119 Fjernet "*1" efter dkdecimal. Søg *1
// 20161124 erstattet <tab> med ; som skilletegn
// 20170509 Tilføjet varemærke (trademark);
// 20210714 LOE - Translated some text.
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20260916 Sawaneh Declared $permission_key (roles & permissions, phase 3)
// 20261008 Sawaneh The page on the new page head (page_bar) instead of the blue bar: title, Tilbage only outside the shell.
// 20261031 CL/ASR Tilbage goes to Indstillinger > Import & eksport (settingsSection.php?s=import_export.data), not via the old diverse.php?sektion=div_io redirect.
// 20261101 CL/ASR Tilbage is the shared settings button above the title (settings_back_button(), css/saldi-kit.css), as on every settings page, instead of page_bar's link.
//                 It goes to the settings page the user came from, or the hub; Alt+L kept.
// 20261102 CL/ASR Tilbage goes to the hub, a fixed target, so it never loops between settings pages; settings_back_button() adds Alt+L itself.
//                 The kit is linked as ?v=20261102.


@session_start();
$s_id=session_id();
$title="Eksporter varer";
$css="../css/standard.css";

include("../includes/connect.php");
$permission_key = 'settings.import_export';
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
$returside="settingsSection.php?s=import_export.data";

$filnavn="../temp/$db/varer.csv";

$fp=fopen($filnavn,"w");

$overskrift='"varenr";"stregkode";"varemærke";"beskrivelse";"kostpris";"salgspris";"vejl_pris";"notes";"enhed";"udgået";"gruppe";"min_lager";"max_lager";"lokation";"varenr_alias";"lager_antal";"beskrivelse_alias"';
if ($charset=="UTF-8") $overskrift=mb_convert_encoding($overskrift, 'ISO-8859-1', 'UTF-8');

if (fwrite($fp, "$overskrift\r\n")) {
	$q=db_select("select id,varenr,stregkode,trademark,beskrivelse,kostpris,salgspris,retail_price,notes,enhed,lukket,gruppe,min_lager,max_lager,location,varenr_alias,beskrivelse_alias from varer order by varenr",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$varenr=$r['varenr'];
		$lukket=$r['lukket'];
		$beskrivelse=str_replace('"',"''",$r['beskrivelse']);
		$stregkode=$r['stregkode'];
		$kostpris=dkdecimal($r['kostpris'],2);# *1;
		$salgspris=dkdecimal($r['salgspris'],2);#*1;
		$retail_price=dkdecimal($r['retail_price'],2);#*1;
		$min_lager=dkdecimal($r['min_lager'],2);#*1;
		$max_lager=dkdecimal($r['max_lager'],2);#*1;
		$lager_antal = 0;
		$query = db_select("SELECT COALESCE(SUM(beholdning), 0) AS lager_antal FROM lagerstatus WHERE vare_id = '$r[id]'", __FILE__ . " line " . __LINE__);
		if (db_num_rows($query) > 0) {
			$row = db_fetch_array($query);
			$lager_antal = $row['lager_antal'];
		}
		$linje='"'.$varenr.'";"'.$stregkode.'";"'.$r['trademark'].'";"'.$beskrivelse.'"'.';'.$kostpris.';'.$salgspris.';'.$retail_price.';'.'"'.$r['notes'].'";"'.$r['enhed'].'"'.';"'.$r['lukket'].'"'.';'.$r['gruppe'].';'.$min_lager.';'.$max_lager.';'.'"'.$r['location'].'";"'.$r['varenr_alias'].'";'.$lager_antal.';"'.$r['beskrivelse_alias'].'"';
		$linje=str_replace("\n","",$linje);
		if ($charset=="UTF-8") $linje=mb_convert_encoding($linje, 'ISO-8859-1', 'UTF-8');
		fwrite($fp, $linje."\r\n");
	} 
} 
fclose($fp);

include_once("../includes/stdFunc/pageBar.php");
include_once("../includes/settings/components.php"); // settings_back_button(): Tilbage above the title, as on every settings page
print "<link rel='stylesheet' href='../css/saldi-kit.css?v=20261102'><div class='sk' style='display:flex;padding:14px 16px 0;margin-bottom:-6px'>" . settings_back_button() . "</div>\n";
page_bar(array('title' => findtekst(1383, $sprog_id)));
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1 
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1


print "</tr></tbody></table></td></tr>";
print "<td align=center valign=top>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
$x =findtekst(1363, $sprog_id);
print "<tr><td align=center> ".findtekst(1362, $sprog_id).": </td><td $top_bund><a href='$filnavn'>".findtekst(609, $sprog_id)."</a></td></tr>";
print "<tr><td align=center colspan=2> ".findtekst(1390, $sprog_id)." $x </td></tr>";

print "</tbody></table>";
?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
