<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------------- admin/admin_settings.php --- patch 5.0.0 --- 2026.03.26 ---
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
// 20260320 PHR cleanup (pdftk)
// 20260326 PHR Fixed error in weasyprint
// 20261010 CL/ASR Rendered in the admin-layer shell; save logic unchanged. Missing programs are shown as a notice.

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
	$weasyprintId = if_isset($_POST, NULL, 'weasyprintId');
	$weasyprint = if_isset($_POST, NULL, 'weasyprint');
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
	if ($weasyprintId) $qtxt="update settings set var_value='$weasyprint' where id='$weasyprintId'";
	else $qtxt="insert into settings (var_name,var_value,var_description) values ('weasyprint','$weasyprint','Program til konvertering af HTML til PDF')";
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
	$ps2pdf=$weasyprint=$pdftk=$ftp=$dbdump=$zip=$unzip=$tar=$alertText=NULL;
	$ps2pdfId=$weasyprintId=$pdftkId=$ftpId=$dbdumpId=$zipId=$unzipId=$tarId=$alertTextId=NULL;
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
	} elseif ($r['var_name']=='weasyprint') {
		$weasyprintId=$r['id'];
		$weasyprint=$r['var_value'];
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

include("../includes/topline_settings.php");
include("../includes/partnerScope.php");
include("vr_ui.php");
partner_tables_ensure();
$vr_missing = array(); $vr_saved = isset($_POST['gem']);
$td=" align=\"center\" height=\"35\"";
$txt = findtekst('1926|ikke fundet!', $sprog_id); #20210917
foreach (array($ps2pdf, $weasyprint, $pdftk, $ftp, $dbdump, $zip, $unzip, $tar) as $vr_p) if ($vr_p && !file_exists($vr_p)) $vr_missing[] = "$vr_p $txt";

if (!$ps2pdf) $ps2pdf=system("which ps2pdf");
if (!$weasyprint) $weasyprint=system("which weasyprint");
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
vr_open(array(array('Administrationspanel', 'admin_panel.php'), findtekst('122|Indstillinger', $sprog_id)), findtekst('122|Indstillinger', $sprog_id), vr_t('Indstillinger for installationen: stier til eksterne programmer, tekst ved uforudset hændelse og infotekst på kundernes dashboard.', 'Installation settings: paths to external programs, the unexpected-event text and the news snippet on customer dashboards.'));
if ($vr_saved) vr_note(array(findtekst('3|Gem', $sprog_id).': '.vr_t('indstillingerne er gemt','settings saved')));
if ($vr_missing) vr_note($vr_missing, 'warn');
print "<form name=\"admin_settings\" action=\"admin_settings.php\" method=\"post\">";
foreach (array('ps2pdfId'=>$ps2pdfId,'weasyprintId'=>$weasyprintId,'pdftkId'=>$pdftkId,'ftpId'=>$ftpId,'dbdumpId'=>$dbdumpId,'zipId'=>$zipId,'unzipId'=>$unzipId,'tarId'=>$tarId,'alertTextId'=>$alertTextId) as $k => $v) print "<input type=\"hidden\" name=\"$k\" value=\"".vr_h($v)."\">";
print "<div class=\"vr-grid2\"><section class=\"vr-sect\"><h2>".vr_t('Eksterne programmer','External programs')."</h2><div class=\"vr-card\"><div class=\"vr-form\" style=\"max-width:none\">";
foreach (array(array('1917|Program til konvertering af PostScript til PDF','ps2pdf',$ps2pdf),array('1918|Program til konvertering af HTML til PDF','weasyprint',$weasyprint),array('1919|Program til sammenlægning af PDF filer','pdftk',$pdftk),array('1920|Program til FTP','ftp',$ftp),array('1921|Program til databasedump','dbdump',$dbdump),array('1922|Program til komprimering af filer','zip',$zip),array('1923|Program til dekomprimering af filer','unzip',$unzip),array('1924|Program til pakning af filer','tar',$tar)) as $f)
	print "<label>".findtekst($f[0], $sprog_id)."<input class=\"vr-inp\" name=\"$f[1]\" value=\"".vr_h(trim((string)$f[2]))."\"".($f[2] && !file_exists(trim((string)$f[2])) ? " style=\"border-color:var(--bad)\"" : "")."></label>";
print "</div></div></section><section class=\"vr-sect\"><h2>".vr_t('Tekster','Texts')."</h2><div class=\"vr-card\"><div class=\"vr-form\" style=\"max-width:none\">";
print "<label>".findtekst('1925|Tekst ved \'uforudset hændelse\'', $sprog_id)."<input class=\"vr-inp\" name=\"alertText\" value=\"".vr_h($alertText)."\"></label>";
print "<label>".findtekst('2952|Infotekst på dashboard', $sprog_id)."<input class=\"vr-inp\" name=\"newssnippet\" value=\"".vr_h($newssnippet)."\"></label>";
print "<div><button type=\"submit\" class=\"vr-btn vr-primary\" name=\"gem\" value=\"1\">".findtekst('3|Gem', $sprog_id)."</button></div></div></div></section></div></form>";
vr_close();
print "</body></html>";
?>
