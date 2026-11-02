<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/importAccountMap.php --- ver 5.1.0 --- 2026-11-02 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003 - 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261102 CL/ASR The same head as the other import pages under Indstillinger > Import & eksport: the shared Tilbage button top-left above the
//                 title (settings_back_button(), css/saldi-kit.css; to the hub, Alt+L) and the title on the page head (page_bar), named as the
//                 Import & eksport page links it, instead of the grey bar with "Importer_kontoplan" and Tilbage via the old diverse.php redirect.
//                 After an import the page returns to Import & eksport directly (settingsSection.php?s=import_export.data).
// 20261102 CL/ASR The standard settings layout (settings_layout_open(), includes/settings/layout.php; owner, 2 Nov 2026: every page reached from Indstillinger keeps the left menu).
//                 The left menu of Finans with Import & eksport marked, Tilbage above the title back to Import & eksport, the breadcrumb Indstillinger / Finans / Import & eksport / this page.
//                 The page's own Tilbage and page_bar title are gone; the standard-map link, the upload form and the import are unchanged.
//                 The div that centres the page's content opens inside the content column, so the title and the menu are not centred.

@session_start();
$s_id=session_id();

ini_set("auto_detect_line_endings", true);

$css="../css/standard.css";

$title="Importer_kontoplan";
	
$komma=$semikolon=$tabulator=NULL;
$feltnavn=array();

include("../includes/connect.php");
$permission_key = 'settings.import_export';
include("../includes/online.php");
include("../includes/std_func.php");

// 20260928 Sawaneh Security 4.0 (A3): the GET parameter may only select the shipped standard map.
$fileName = (if_isset($_GET['fileName'],NULL) && basename((string) $_GET['fileName']) === 'stdAccountMap.csv') ? __DIR__ . '/../importfiler/stdAccountMap.csv' : NULL;

// The standard settings layout (includes/settings/layout.php): Tilbage, the title and the left menu with the page's row marked.
include_once("../includes/settings/layout.php");
settings_layout_open();
print "<div align=\"center\">";

if ($fileName) importData($fileName);

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";

if (isset ($_FILES['uploadedfile']['name']) && basename($_FILES['uploadedfile']['name'])) {
	$fileName="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
	if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $fileName)) {
	importData($fileName);
	} else echo "Der er sket en fejl under hentningen, prøv venligst igen";
	upload();
}
upload();
print "</tbody></table>";
settings_layout_close();
#####################################################################################################
function upload(){

print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td width=100% align=center>Klik <a href = 'importAccountMap.php?fileName=../importfiler/stdAccountMap.csv'>her</a> ";
print "for at importere mapping fil til Saldi standardkontoplan fra før 2024</td></tr>";
print "<tr><td width=100% align=center><br>eller<br><br></td></tr>";
print "<tr><td width=100% align=center>upload en csv fil med to kolonner.</td></tr>";
print "<tr><td width=100% align=center>1. kolonne skal være nuværende konto og 2. kolonne den tilsvarende offentlige </td></tr>";
print "<tr><td width=100% align=center>Flere eksisterende konti kan mappe til samme konto.<br><br></td></tr>";
print "<form enctype=\"multipart/form-data\" action=\"importAccountMap.php\" method=\"POST\">";
print	 "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
print "<tr><td width=100% align=center> ".findtekst(1364, $sprog_id).": <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" name=\"hent\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "</tbody></table>";
print "</td></tr>";
}


function importData($fileName) {
	global $regnaar;

	$lines = explode("\n",file_get_contents($fileName));
	for ($y=1; $y<4; $y++) {
		$tmp=$lines[$y];
		while ($tmp=substr(strstr($tmp,";"),1)) {$semikolon++;}
		$tmp=$lines[$y];
		while ($tmp=substr(strstr($tmp,","),1)) {$komma++;}
		$tmp=$lines[$y];
		while ($tmp=substr(strstr($tmp,chr(9)),1)) {$tabulator++;}
		$tmp='';
		if (($komma>$semikolon)&& ($komma>$tabulator)) {$tmp='Komma'; $feltantal=$komma;}
		elseif (($semikolon>$tabulator)&&($semikolon>$komma)) {$tmp='Semikolon'; $feltantal=$semikolon;}
		elseif (($tabulator>$semikolon)&&($tabulator>$komma)) {$tmp='Tabulator'; $feltantal=$tabulator;}
		if (!$splitter) $splitter=$tmp;
	}

	print "<tr><td colspan=$cols><hr></td></tr>\n";
	if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
	elseif ($splitter=='Komma') {$splitter=',';}
	elseif ($splitter=='Tabulator') {$splitter=chr(9);}

	for ($x=0;$x<count($lines);$x++) {
		$account = explode($splitter,trim($lines[$x]));
		$account[0]=(int)$account[0];
		$account[1]=(int)$account[1];
		$qtxt = "update kontoplan set map_to = '$account[1]' where kontonr = '$account[0]' and regnskabsaar = '$regnaar'";
		db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
	}
	alert("Mapping fil importeret og kontoplan opdateret");
	print "<meta http-equiv=\"refresh\" content=\"0;URL=settingsSection.php?s=import_export.data\">\n";
}
?>
