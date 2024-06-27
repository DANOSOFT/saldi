<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/importAccountMap.php --- lap 4.1.0 --- 2024-01-03 ---
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
// Copyright (c) 2003 - 2024 Saldi.dk ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

ini_set("auto_detect_line_endings", true);

$css="../css/standard.css";

$title="Importer_kontoplan";
	
$komma=$semikolon=$tabulator=NULL;
$feltnavn=array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$fileName = if_isset($_GET['fileName'],NULL);

print "<div align=\"center\">";

if ($fileName) importData($fileName);

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
#if ($popup) print "<td width=\"10%\" $top_bund><a href=../includes/luk.php accesskey=L>Luk</a></td>"; 
# else 
print "<td width=\"10%\" $top_bund><a href=diverse.php?sektion=div_io accesskey=L>".findtekst(30, $sprog_id)."</a></td>"; #20210713
print "<td width=\"80%\" $top_bund>$title</td>";
print "<td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";

if (isset ($_FILES['uploadedfile']['name']) && basename($_FILES['uploadedfile']['name'])) {
	$fileName="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
	if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $fileName)) {
	importData($fileName);
	} else echo "Der er sket en fejl under hentningen, prøv venligst igen";
	upload();
}
upload();
print "</tbody></table>";
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
#cho "$qtxt<br>";
		db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
	}
	alert("Mapping fil importeret og kontoplan opdateret");
	print "<meta http-equiv=\"refresh\" content=\"0;URL= diverse.php?sektion=div_io\">\n";
}
?>
