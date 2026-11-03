<?php	
// -------/systemdata/importer_formular.php-----ver 5.1.0 ----2026-11-02--
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
// 20150122 MAX_FILE_SIZE ændret fra 100000 til 200000
// 20210713 Added this file and also translated some text
// 20260916 SS Declared $permission_key (roles & permissions, phase 3)
// 20261008 SS The page on the new page head (page_bar) instead of the blue bar: title, Tilbage only outside the shell.
// 20261031 CL/ASR Tilbage goes to Indstillinger > Import & eksport (settingsSection.php?s=import_export.data), not via the old diverse.php?sektion=div_io redirect.
// 20261101 CL/ASR Tilbage is the shared settings button above the title (settings_back_button(), css/saldi-kit.css), as on every settings page, instead of page_bar's link.
//                 It goes to the settings page the user came from, or the hub; Alt+L kept.
// 20261102 CL/ASR Tilbage goes to the hub, a fixed target, so it never loops between settings pages; settings_back_button() adds Alt+L itself.
//                 The kit is linked as ?v=20261102.
//                 The title is the name the Import & eksport page links it by, not "Importer_formularer".
// 20261102 CL/ASR The standard settings layout (settings_layout_open(), includes/settings/layout.php; owner, 2 Nov 2026: every page reached from Indstillinger keeps the left menu).
//                 The left menu of Finans with Import & eksport marked, Tilbage above the title back to Import & eksport, the breadcrumb Indstillinger / Finans / Import & eksport / this page.
//                 The page's own Tilbage and page_bar title are gone; its form and steps are unchanged.

@session_start();
$s_id=session_id();

$title="Importer formularer";
$css="../css/standard.css";
$returside="settingsSection.php?s=import_export.data";
include("../includes/connect.php");
$permission_key = 'settings.import_export';
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/formularimport.php");
include("../includes/std_func.php"); #20210713 
include("../includes/topline_settings.php");
// The standard settings layout (includes/settings/layout.php): Tilbage, the title and the left menu with the page's row marked.
include_once("../includes/settings/layout.php");
settings_layout_open();
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1 
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1


print "</tr></tbody></table></td></tr>";

if ($_POST) {
	$submit=$_POST['submit'];
	$filnavn=$_POST['filnavn'];

	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			formularimport($filnavn,0);
		} else echo findtekst(1370, $sprog_id);
		if ($popup) {
			print "<div style=\"text-align: center;\">".findtekst(1368, $sprog_id)."<br></div>";
			print "<meta http-equiv=\"refresh\" content=\"3;URL=../includes/luk.php\">";
			exit;
		} else  print "<BODY onLoad=\"JavaScript:alert('Formularimport succesfuld')\">";
	} else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		exit;
	}
} else {
	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"importer_formular.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"200000\">";
	print "<tr><td width=100% align=center> ".findtekst(1369, $sprog_id).": <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"".findtekst(1078, $sprog_id)."\" /></td></tr>";
	print "<tr><td></form></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}
?>
</tbody></table>
<?php settings_layout_close(); ?>
</body></html>
