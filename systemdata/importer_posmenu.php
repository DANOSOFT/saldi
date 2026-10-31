<?php
	@session_start();
	$s_id=session_id();

// ---- /systemdata/importer_posmenu.php ------- ver 5.1.0 -- 2026-10-31 --
// LICENSE
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2015-2026 Danosoft ApS
// --------------------------------------------------------------------------
// 20211119 CA  Import PoS menus
// 20260916 Sawaneh Declared $permission_key (roles & permissions, phase 3)
// 20261008 Sawaneh The page on the new page head (page_bar) instead of the blue bar: title, Tilbage only outside the shell.
// 20261031 CL/ASR Tilbage goes to Indstillinger > Import & eksport (settingsSection.php?s=import_export.data), not via the old diverse.php?sektion=div_io redirect.

$saldifileformat="saldi_posmenus";
$title="Importer POS menuer";
$css="../css/standard.css";
include("../includes/connect.php");
$permission_key = 'settings.import_export';
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/posmenu_import.php");
include("../includes/std_func.php"); #20210713 
include("../includes/topline_settings.php");
$returside="settingsSection.php?s=import_export.data";

include_once("../includes/stdFunc/pageBar.php");
page_bar(array('title' => findtekst(1934, $sprog_id), 'back' => $returside));
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1 
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1


print "</tr></tbody></table></td></tr>";

if ($_POST) {
	$submit=$_POST['submit'];
	$filnavn=$_POST['filnavn'];

	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$saldifileformat."_".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			posmenu_import($filnavn);
		} else {
			echo findtekst(1370, $sprog_id); # An error occurred while retrieving, please try again
		}
		if ($popup) {
			print "<div style=\"text-align: center;\">".findtekst(1935, $sprog_id)."<br></div>"; # Import of POS menus successful
			print "<meta http-equiv=\"refresh\" content=\"3;URL=../includes/luk.php\">";
			exit;
		} else  print "<BODY onLoad=\"JavaScript:alert('".findtekst(1935, $sprog_id)."')\">"; # Import of POS menus successful
	} else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		exit;
	}
} else {
	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"importer_posmenu.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"200000\">";
	print "<tr><td width=100% align=center> ".findtekst(1936, $sprog_id).": <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>"; # Select POS menus file
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"".findtekst(1078, $sprog_id)."\" /></td></tr>"; # Fetch
	print "<tr><td></form></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}
?>
</tbody></table>
</body></html>
