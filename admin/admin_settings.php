<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------------- admin/admin_settings.php --- patch 5.0.0 --- 2026.02.16 ---
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
// Copyright (c) 2003-2026 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20190411 PHR Added alertText
// 20210917 LOE Translated some texts
// 20210921 Added this block of code to set language
// 20240522 MMK Newssnippet
// 20250503 LOE Updated files with new if_isset function implementation to prevent exessive error logs
// 20260212 PHR pdfmerge replaced by pdftk and some errors

@session_start();
$s_id=session_id();
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$languages = array();

if (isset($_POST['gem'])) {
	$ps2pdfId = if_isset($_POST, NULL, 'ps2pdfId');
	$ps2pdf = if_isset($_POST, NULL, 'ps2pdf');
	$html2pdfId = if_isset($_POST, NULL, 'html2pdfId');
	$html2pdf = if_isset($_POST, NULL, 'html2pdf');
	$pdftkId = if_isset($_POST, NULL, 'pdftkId');
	$pdftk = if_isset($_POST, NULL, 'pdftk');
	$ftpId = if_isset($_POST, NULL, 'ftpId');
	$ftp = if_isset($_POST, NULL, 'ftp');
	$dbdumpId = if_isset($_POST, NULL, 'dbdumpId');
	$dbdump = if_isset($_POST, NULL, 'dbdump');
	$zipId = if_isset($_POST, NULL, 'zipId');
	$zip = if_isset($_POST, NULL, 'zip');
	$unzipId = if_isset($_POST, NULL, 'unzipId');
	$unzip = if_isset($_POST, NULL, 'unzip');
	$tarId = if_isset($_POST, NULL, 'tarId');
	$tar = if_isset($_POST, NULL, 'tar');
	$alertTextId = if_isset($_POST, NULL, 'alertTextId');
	$alertText = if_isset($_POST, NULL, 'alertText');
	$lang = if_isset($_POST, NULL, 'LanguageName'); //20210920
	$languageId = (int)if_isset($_POST, 0, 'LanguageId'); //20210920
	$newssnippet = if_isset($_POST, NULL, 'newssnippet');	
	$sprog_id = (int)$languageId;
/*
	    $qtxt="select * from online where sprog ='$lang'and brugernavn = '$brugernavn'";  #20210921
		if (!$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
			$qtxt="update online set sprog = '$lang' where brugernavn = '$brugernavn' and session_id = '$s_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
*/
	if ($ps2pdfId) $qtxt="update settings set var_value='$ps2pdf' where id='$ps2pdfId'";
	else $qtxt="insert into settings (var_name,var_value,var_description) values ('ps2pdf','$ps2pdf','Program til konvertering af PostScript til PDF')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($html2pdfId) $qtxt="update settings set var_value='$html2pdf' where id='$html2pdfId'";
	else $qtxt="insert into settings (var_name,var_value,var_description) values ('html2pdf','$html2pdf','Program til konvertering af HTML til PDF')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($pdftkId) $qtxt="update settings set var_value='$pdftk' where id='$pdftkId'";
	else $qtxt="insert into settings (var_name,var_value,var_description) values ('pdftk','$pdftk','Program til sammenlægning af PDF filer')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($ftpId) $qtxt="update settings set var_value='$ftp' where id='$ftpId'";
	else $qtxt="insert into settings (var_name,var_value,var_description) values ('ftp','$ftp','Program til FTP')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($dbdumpId) $qtxt="update settings set var_value='$dbdump' where id='$dbdumpId'";
	else $qtxt="insert into settings (var_name,var_value,var_description) values ('dbdump','$dbdump','Program til databasedump')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($zipId) $qtxt="update settings set var_value='$zip' where id='$zipId'";
	else $qtxt="insert into settings (var_name,var_value,var_description) values ('zip','$zip','Program til komprimering af filer')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($unzipId) $qtxt="update settings set var_value='$unzip' where id='$unzipId'";
	else $qtxt="insert into settings (var_name,var_value,var_description) values ('unzip','$unzip','Program til dekomprimering af filer')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($tarId) $qtxt="update settings set var_value='$tar' where id='$tarId'";
	else $qtxt="insert into settings (var_name,var_value,var_description) values ('tar','$tar','Program til pakning af filer')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($alertTextId) {
		$qtxt="delete from settings where var_name='alertText' and id!='$alertTextId'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="update settings set var_value='$alertText' where id='$alertTextId'";
	} else {
		$qtxt="insert into settings (var_name,var_value,var_description) values ";
		$qtxt.="('alertText','".db_escape_string($alertText)."','".db_escape_string('Alert text if: unpredicted event')."')";
	}
	update_settings_value("nyhed", "dashboard", $newssnippet, "The news snippet showen to all admin accounts on this system");
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="update settings set var_value='$languageId' where var_name='languageId'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="update online set language_id='$languageId' where session_id = '$s_id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	
} else {
	$ps2pdf=$html2pdf=$pdftk=$ftp=$dbdump=$zip=$unzip=$tar=$alertText=NULL;
	$ps2pdfId=$html2pdfId=$pdftkId=$ftpId=$dbdumpId=$zipId=$unzipId=$tarId=$alertTextId=NULL;
}

if ($db != $sqdb) {
	$txt1 = findtekst('1905|Hmm du har vist ikke noget at gøre her! Dit IP nummer, brugernavn og regnskab er registreret!', $sprog_id);
	print "<BODY onLoad=\"javascript:alert('$txt1')\">\n";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">\n";
	exit;
}

$q = db_select("select * from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
$r = db_fetch_array($q);
if ($brugerId=$r['id']) {
	$rettigheder=$r['rettigheder'];
#	if (strstr($rettigheder,",")=='0') echo "NUL<br>";
	list($admin,$oprette,$slette,$tmp)=explode(",",$rettigheder,4);
}
$q=db_select("select * from settings",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($r['var_name']=='ps2pdf') {
		$ps2pdfId=$r['id'];
		$ps2pdf=$r['var_value'];
	} elseif ($r['var_name']=='html2pdf') {
		$html2pdfId=$r['id'];
		$html2pdf=$r['var_value'];
	} elseif ($r['var_name']=='pdftk') {
		$pdftkId=$r['id'];
		$pdftk=$r['var_value'];
	} elseif ($r['var_name']=='ftp') {
		$ftpId=$r['id'];
		$ftp=$r['var_value'];
	} elseif ($r['var_name']=='dbdump') {
		$dbdumpId=$r['id'];
		$dbdump=$r['var_value'];
	}elseif ($r['var_name']=='zip') {
		$zipId=$r['id'];
		$zip=$r['var_value'];
	} elseif ($r['var_name']=='unzip') {
		$unzipId=$r['id'];
		$unzip=$r['var_value'];
	} elseif ($r['var_name']=='tar') {
		$tarId=$r['id'];
		$tar=$r['var_value'];
	} elseif ($r['var_name']=='alertText') {
		$alertTextId=$r['id'];
		$alertText=$r['var_value'];
	} elseif ($r['var_name']=='languageId') {
		$languageId=$r['var_value'];
	} elseif ($r['var_name']=='languages') {
		$languages=explode(chr(9),$r['var_value']);
	}
}

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>\n";
print "<td $top_bund width=\"10%\"><a href='../index/admin_menu.php'>".findtekst('30|Tilbage', $sprog_id)."</a></td>\n"; 
print "<td $top_bund width=\"35%\">&nbsp;</td>\n";
print "<td $top_bund width=\"10%\" align = \"center\"></td>\n";
print "<td $top_bund width=\"35%\">&nbsp;</td>";
print "<td $top_bund width=\"10%\" align = \"right\"></td>\n";
print "</tr></tbody></table></td></tr>\n<tr><td align=\"center\" valign=\"center\">\n";
$td=" align=\"center\" height=\"35\"";
$txt = findtekst('1926|ikke fundet!', $sprog_id); #20210917
if ($ps2pdf && !file_exists($ps2pdf)) echo "$ps2pdf $txt";
if ($html2pdf && !file_exists($html2pdf)) echo "$html2pdf $txt";
if ($pdftk && !file_exists($pdftk)) echo "$pdftk $txt";
if ($ftp && !file_exists($ftp)) echo "$ftp $txt";
if ($dbdump && !file_exists($dbdump)) echo "$dbdump $txt";
if ($zip && !file_exists($zip)) echo "$zip $txt";
if ($unzip && !file_exists($unzip)) echo "$unzip $txt";
if ($tar && !file_exists($tar)) echo "$tar $txt";

if (!$ps2pdf) $ps2pdf=system("which ps2pdf");
if (!$html2pdf) $html2pdf=system("which weasyprint");
if (!$pdftk) $pdftk=system("which pdftk");
if (!$ftp) $ftp=system("which ncftp");
if (!$dbdump) {
	if ($db_type=='postgresql') $dbdump=system("which pg_dump");
	else $dbdump=system("which mysqldump");
}
if (!$zip) $zip=system("which gzip");
if (!$unzip) $unzip=system("which gunzip");
if (!$tar) $tar=system("which tar");
if (!$alertText) $alertText=findtekst('534|Uforudset hændelse, kontakt salditeamet på telefon 4690 2208', $sprog_id); #20210917
$newssnippet = get_settings_value("nyhed", "dashboard", "");

#include("../includes/languages.php"); #20210920
print "<form name='admin_settings' action='admin_settings.php' method='post'>";
print "<input type='hidden' name='ps2pdfId' value='$ps2pdfId'>";
print "<input type='hidden' name='html2pdfId' value='$html2pdfId'>";
print "<input type='hidden' name='pdftkId' value='$pdftkId'>";
print "<input type='hidden' name='ftpId' value='$ftpId'>";
print "<input type='hidden' name='dbdumpId' value='$dbdumpId'>";
print "<input type='hidden' name='zipId' value='$zipId'>";
print "<input type='hidden' name='unzipId' value='$unzipId'>";
print "<input type='hidden' name='tarId' value='$tarId'>";
print "<input type='hidden' name='alertTextId' value='$alertTextId'>";
print "<table align=\"center\" border=\"0\" cellspacing=\"5\" cellpadding=\"0\"><tbody>";
print "<tr><td colspan=\"2\" height=\"35\" align=\"center\" background=\"../img/blaa2hvid_bg.gif\">";
print "<big<big><big><b>SALDI</b></big></big></big></td></tr>";
print "<tr><td  colspan=\"2\" height=\"35\" align=\"center\"><b><big>".findtekst('122|Indstillinger', $sprog_id)."</big></b></td></tr>";
print "<tr><td>".findtekst('1917|Program til konvertering af PostScript til PDF', $sprog_id)."</td><td><input style='width:400px' name='ps2pdf' value='$ps2pdf'></td></tr>"; 
print "<tr><td>".findtekst('1918|Program til konvertering af HTML til PDF', $sprog_id)."</td><td><input style='width:400px' name='html2pdf' value='$html2pdf'></td></tr>"; 
print "<tr><td>".findtekst('1919|Program til sammenlægning af PDF filer', $sprog_id)."</td><td><input style='width:400px' name='pdftk' value='$pdftk'></td></tr>";
print "<tr><td>".findtekst('1920|Program til FTP', $sprog_id)."</td><td><input style='width:400px' name='ftp' value='$ftp'></td></tr>"; 
print "<tr><td>".findtekst('1921|Program til databasedump', $sprog_id)."</td><td><input style='width:400px' name='dbdump' value='$dbdump'></td></tr>";
print "<tr><td>".findtekst('1922|Program til komprimering af filer', $sprog_id)."</td><td><input style='width:400px' name='zip' value='$zip'></td></tr>";
print "<tr><td>".findtekst('1923|Program til dekomprimering af filer', $sprog_id)."</td><td><input style='width:400px' name='unzip' value='$unzip'></td></tr>";
print "<tr><td>".findtekst('1924|Program til pakning af filer', $sprog_id)."</td><td><input style='width:400px' name='tar' value='$tar'></td></tr>";
print "<tr><td>".findtekst('1925|Tekst ved \'uforudset hændelse\'', $sprog_id)."</td><td><input style='width:400px' name='alertText' value='$alertText'></td></tr>";
print "<tr><td>".findtekst('2952|Infotekst på dashboard', $sprog_id)."</td><td><input style='width:400px' name='newssnippet' value='$newssnippet'></td></tr>";

##################### #20210920
/*
print "<tr><td title='".findtekst('2|Vælg aktivt sprog', $sprog_id)."'>".findtekst('436|Skift', $sprog_id)." ".lcfirst(findtekst('801|Sprog', $sprog_id))."</td>";
print"<td> <SELECT class ='inputbox' NAME = 'LanguageId' title=''>";
/*
foreach ($languages as $k => $v) {
	print "<option  value='$v'>$v</option>";
}

for ($l=1;$l<count($languages);$l++) {
	if ($languageId == $l) print "<option  value='$l'>$languages[$l]</option>";
}
for ($l=1;$l<count($languages);$l++) {
	if ($languageId != $l) print "<option value='$l'>$languages[$l]</option>";
}
print "</SELECT></td></tr>";
*/
#####################
print "<tr><td colspan=\"2\" height=\"35\" align=\"center\"><input type='submit' name='gem' value='".findtekst('3|Gem', $sprog_id)."'></b></td></tr>";
print "</tbody></table>";
print "</form>";
print "</td></tr>";
print "<tr><td align=\"center\" valign=\"bottom\">";
print "<div class=top_bund><small>SALDI&nbsp;version&nbsp;$version&nbsp;-&nbsp;Copyright&nbsp;&copy;&nbsp;$copyright&nbsp;DANOSOFT&nbsp;aps</small></div></td></tr>\n";
print "</td></tr>";
print "</tbody></table>";
print "</body></html>";

?>

