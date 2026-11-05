<?php
// ----/systemdata/exporter_kontoplan.php-----ver 5.1.0 ----2026-11-02-----
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
// 20190225 MSC - Rettet topmenu design
// 20210713 LOE - Translated these texts to Norsk and English
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20260825 Sawaneh JOB-086: fiscal year from the request is int-cast before use in SQL
// 20260916 SS Declared $permission_key (roles & permissions, phase 3)
// 20260928 SS Phase 4: top-menu branch removed; side-menu layout is the only one.
// 20261008 SS The page on the new page head (page_bar) instead of the blue bar: title, Tilbage only outside the shell.
// 20261009 SS The page-head title through findtekst so the English and Norwegian UI show it translated.
// 20261031 CL/ASR Tilbage goes to Indstillinger > Import & eksport (settingsSection.php?s=import_export.data), not via the old diverse.php?sektion=div_io redirect.
// 20261101 CL/ASR Tilbage is the shared settings button above the title (settings_back_button(), css/saldi-kit.css), as on every settings page, instead of page_bar's link.
//                 It goes to the settings page the user came from, or the hub; Alt+L kept.
// 20261102 CL/ASR Tilbage goes to the hub, a fixed target, so it never loops between settings pages; settings_back_button() adds Alt+L itself.
//                 The kit is linked as ?v=20261102.
// 20261102 CL/ASR The standard settings layout (settings_layout_open(), includes/settings/layout.php; owner, 2 Nov 2026: every page reached from Indstillinger keeps the left menu).
//                 The left menu of Finans with Import & eksport marked, Tilbage above the title back to Import & eksport, the breadcrumb Indstillinger / Finans / Import & eksport / this page.
//                 The page's own Tilbage and page_bar title are gone; its form and steps are unchanged.

@session_start();
$s_id=session_id();
$title="Eksporter kontoplan";
$css="../css/standard.css";

include("../includes/connect.php");
$modulnr = 1; // 20260928 Sawaneh Security 4.0 (A8)
$permission_key = 'settings.import_export';
include("../includes/online.php");
include("../includes/std_func.php");
$title = findtekst('1355|Eksportér', $sprog_id) . ' ' . findtekst('612|Kontoplan', $sprog_id);
include("../includes/topline_settings.php");
$regnskabsaar = intval($_GET['aar'] ?? 0);

$returside="settingsSection.php?s=import_export.data";

$filnavn="../temp/".trim($db."_ktoplan_".date("Y-m-d").".csv");

$fp=fopen($filnavn,"w");
if (fwrite($fp, "kontonr".chr(9)."beskrivelse".chr(9)."kontotype".chr(9)."momskode".chr(9)."fra_konto\r\n")) {
	$q=db_select("select * from kontoplan where regnskabsaar='$regnskabsaar' order by kontonr",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$beskrivelse=$r['beskrivelse'];
		if ($charset=="UTF-8") $beskrivelse=mb_convert_encoding($beskrivelse, 'ISO-8859-1', 'UTF-8');
		$linje=str_replace("\n","",$r['kontonr'].chr(9).$beskrivelse.chr(9).$r['kontotype'].chr(9).$r['moms'].chr(9).$r['fra_kto']);
		fwrite($fp, $linje."\r\n");
	} 
} 
fclose($fp);

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

print "<tr><td align=center> ".findtekst(1362, $sprog_id).": </td><td $top_bund><a href='$filnavn'>".findtekst(612, $sprog_id)."</a></td></tr>";
print "<tr><td align=center colspan=2> ".findtekst(1363, $sprog_id)."</td></tr>";

print "</tbody></table>";

?>
</tbody>
</table>
</td></tr>
</tbody></table>
<?php settings_layout_close(); ?>
</body></html>
