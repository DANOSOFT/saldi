<?php
// ------------/systemdata/exporter_debitor.php---ver 5.1.0----2026-11-02--
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
// Copyright (c) 2004-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20260916 SS Declared $permission_key (roles & permissions, phase 3)
// 20261008 SS The page on the new page head (page_bar) instead of the blue bar: title, Tilbage only outside the shell.
// 20261009 SS The page-head title through findtekst so the English and Norwegian UI show it translated.
// 20261031 CL/ASR Tilbage goes to Indstillinger > Import & eksport (settingsSection.php?s=import_export.data), not via the old diverse.php?sektion=div_io redirect.
// 20261101 CL/ASR Tilbage is the shared settings button above the title (settings_back_button(), css/saldi-kit.css), as on every settings page, instead of page_bar's link.
//                 It goes to the settings page the user came from, or the hub; Alt+L kept.
// 20261102 CL/ASR Tilbage goes to the hub, a fixed target, so it never loops between settings pages; settings_back_button() adds Alt+L itself.
//                 The kit is linked as ?v=20261102.
// 20261102 CL/ASR Fix of a fatal error that was already on master: the address fields were read as $r[kontonr] (a constant in PHP 8, "Undefined constant"), so the export died before the page was printed on a ledger with customers.
//                 The keys are quoted.
// 20261102 CL/ASR The standard settings layout (settings_layout_open(), includes/settings/layout.php; owner, 2 Nov 2026: every page reached from Indstillinger keeps the left menu).
//                 The left menu of Finans with Import & eksport marked, Tilbage above the title back to Import & eksport, the breadcrumb Indstillinger / Finans / Import & eksport / this page.
//                 The page's own Tilbage and page_bar title are gone; its form and steps are unchanged.
//                 The old blue-grey bottom bar under the content is gone; in the content column it was an empty strip right under the form.

@session_start();
$s_id=session_id();
$title="Eksporter debitorer";
$css="../css/standard.css";

include("../includes/connect.php");
$permission_key = 'settings.import_export';
include("../includes/online.php");
include("../includes/std_func.php");
$title = findtekst('1355|Eksportér', $sprog_id) . ' ' . findtekst('908|Debitorer', $sprog_id);

$returside="settingsSection.php?s=import_export.data";


$filnavn="../temp/".trim($db."_debitorer_".date("Y-m-d").".csv");

$fp=fopen($filnavn,"w");

if (fwrite($fp,"kontonr".chr(9)."firmanavn".chr(9)."addr1".chr(9)."addr2".chr(9)."postnr".chr(9)."bynavn".chr(9)."land".chr(9)."kontakt".chr(9)."tlf".chr(9)."mobile".chr(9)."email".chr(9)."web".chr(9)."notes".chr(9)."kreditmax".chr(9)."betalingsbet".chr(9)."betalingsdage".chr(9)."cvrnr".chr(9)."ean".chr(9)."institution".chr(9)."gruppe".chr(9)."kontoansvarlig".chr(9)."oprettet".chr(9)."kontakt_navn".chr(9)."kontakt_addr1".chr(9)."kontakt_addr2".chr(9)."kontakt_postnr".chr(9)."kontakt_bynavn".chr(9)."kontakt_tlf".chr(9)."kontakt_mobile".chr(9)."kontakt_email".chr(9)."kontakt_notes]\r\n")) {
	$q=db_select("select * from adresser where art='D' order by kontonr",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$ansatte=0;
		if ($r['kontoansvarlig']) {
			$r2=db_fetch_array(db_select("select initialer from ansatte where id='$r[kontoansvarlig]'",__FILE__ . " linje " . __LINE__));
			$kontoansvarlig=$r2['initialer'];
		} else $kontoansvarlig='';
		$kreditmax=dkdecimal($r['kreditmax']);
		$oprettet=dkdato($r['oprettet']);
		
		$tmp1=str_replace("\n","\\n",$r['kontonr'].chr(9).chr(32).$r['firmanavn'].chr(32).chr(9).chr(32).$r['addr1'].chr(32).chr(9).chr(32).$r['addr2'].chr(32).chr(9).chr(32).$r['postnr'].chr(32).chr(9).chr(32).$r['bynavn'].chr(32).chr(9).chr(32).$r['land'].chr(32).chr(9).chr(32).$r['kontakt'].chr(32).chr(9).chr(32).$r['tlf'].chr(32).chr(9).chr(32).$r['mobile'].chr(32).chr(9).chr(32).$r['email'].chr(32).chr(9).chr(32).$r['web'].chr(32).chr(9).chr(32).$r['notes'].chr(32).chr(9).$kreditmax.chr(9).chr(32).$r['betalingsbet'].chr(32).chr(9).$r['betalingsdage'].chr(9).chr(32).$r['cvrnr'].chr(32).chr(9).chr(32).$r['ean'].chr(32).chr(9).chr(32).$r['institution'].chr(32).chr(9).$r['gruppe'].chr(9).chr(32).$kontoansvarlig.chr(32).chr(9).chr(32).$oprettet);
		$tmp1=str_replace("\r","\\r",$tmp1);
		if ($charset=='UTF-8') $tmp1=mb_convert_encoding($tmp1, 'ISO-8859-1', 'UTF-8');
		$q2=db_select("select * from ansatte where konto_id='$r[id]' order by navn",__FILE__ . " linje " . __LINE__);
		while ($r2=db_fetch_array($q2)) {
			$ansatte++;
			$tmp2=str_replace("\n","\\n",$r2['navn'].chr(32).chr(9).chr(32).$r2['addr1'].chr(32).chr(9).chr(32).$r2['addr2'].chr(32).chr(9).chr(32).$r2['postnr'].chr(32).chr(9).chr(32).$r2['bynavn'].chr(32).chr(9).chr(32).$r2['tlf'].chr(32).chr(9).chr(32).$r2['mobile'].chr(32).chr(9).chr(32).$r2['email'].chr(32).chr(9).chr(32).$r2['notes']);
			$tmp2=str_replace("\r","\\r",$tmp2);
		if ($charset=='UTF-8') $tmp2=mb_convert_encoding($tmp2, 'ISO-8859-1', 'UTF-8');
			$linje=$tmp1.chr(32).chr(9).chr(32).$tmp2;
			fwrite($fp, $linje."\r\n");
		}
		if (!$ansatte) {
			$linje=$tmp1.chr(32).chr(9).chr(32).chr(32).chr(9).chr(32).chr(32).chr(9).chr(32).chr(32).chr(9).chr(32).chr(32).chr(9).chr(32).chr(32).chr(9).chr(32).chr(32).chr(9).chr(32).chr(32).chr(9).chr(32).chr(32).chr(9).chr(32);
			fwrite($fp, $linje."\r\n");
		}
	} 
} 
fclose($fp);

// The standard settings layout (includes/settings/layout.php): Tilbage, the title and the left menu with the page's row marked.
include_once("../includes/settings/layout.php");
settings_layout_open(array('title' => $title));
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1 
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1


print "</tr></tbody></table></td></tr>";
print "<td align=center valign=top>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

print "<tr><td align=center> H&oslash;jreklik her: </td><td $top_bund><a href='$filnavn'>Adresser</a></td></tr>";
print "<tr><td align=center colspan=2> V&aelig;lg \"gem destination som\"</td></tr>";

print "</tbody></table>";

?>
</tbody>
</table>
</td></tr>
</tbody></table>
<?php settings_layout_close(); ?>
</body></html>
