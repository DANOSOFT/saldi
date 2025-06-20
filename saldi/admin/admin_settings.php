<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------ admin/admin_settings.php --------------- lap 3.7.9 --- 2019-04-11 ---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2018-2019 saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20190411 PHR Added alertText

@session_start();
$s_id=session_id();
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if (isset($_POST['gem'])) {
	$ps2pdfId=if_isset($_POST['ps2pdfId']);
	$ps2pdf=if_isset($_POST['ps2pdf']);
	$html2pdfId=if_isset($_POST['html2pdfId']);
	$html2pdf=if_isset($_POST['html2pdf']);
	$pdfmergeId=if_isset($_POST['pdfmergeId']);
	$pdfmerge=if_isset($_POST['pdfmerge']);
	$ftpId=if_isset($_POST['ftpId']);
	$ftp=if_isset($_POST['ftp']);
	$dbdumpId=if_isset($_POST['dbdumpId']);
	$dbdump=if_isset($_POST['dbdump']);
	$zipId=if_isset($_POST['zipId']);
	$zip=if_isset($_POST['zip']);
	$unzipId=if_isset($_POST['unzipId']);
	$unzip=if_isset($_POST['unzip']);
	$tarId=if_isset($_POST['tarId']);
	$tar=if_isset($_POST['tar']);
	$alertTextId=if_isset($_POST['alertTextId']);
	$alertText=if_isset($_POST['alertText']);
	
	if ($ps2pdfId) $qtxt="update settings set var_value='$ps2pdf' where id='$ps2pdfId'";
	else $qtxt="insert into settings (var_name,var_value,var_description) values ('ps2pdf','$ps2pdf','Program til konvertering af PostScript til PDF')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($html2pdfId) $qtxt="update settings set var_value='$html2pdf' where id='$html2pdfId'";
	else $qtxt="insert into settings (var_name,var_value,var_description) values ('html2pdf','$html2pdf','Program til konvertering af HTML til PDF')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($pdfmergeId) $qtxt="update settings set var_value='$pdfmerge' where id='$pdfmergeId'";
	else $qtxt="insert into settings (var_name,var_value,var_description) values ('pdfmerge','$pdfmerge','Program til sammenlægning af PDF filer')";
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
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
} else {
	$ps2pdf=$html2pdf=$pdfmerge=$ftp=$dbdump=$zip=$unzip=$tar=$alertText=NULL;
	$ps2pdfId=$html2pdfId=$pdfmergeId=$ftpId=$dbdumpId=$zipId=$unzipId=$tarId=$alertTextId=NULL;
}

if ($db != $sqdb) {
	print "<BODY onLoad=\"javascript:alert('Hmm du har vist ikke noget at g&oslash;re her! Dit IP nummer, brugernavn og regnskab er registreret!')\">\n";
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
	} elseif ($r['var_name']=='pdfmerge') {
		$pdfmergeId=$r['id'];
		$pdfmerge=$r['var_value'];
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
	}
}

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>\n";
print "  <td $top_bund width=\"10%\"><a href='../index/admin_menu.php'>Luk</a></td>\n";
print "  <td $top_bund width=\"35%\">&nbsp;</td>\n";
print "  <td $top_bund width=\"10%\" align = \"center\"></td>\n";
print "<td $top_bund width=\"35%\">&nbsp;</td>";
print "<td $top_bund width=\"10%\" align = \"right\"></td>\n";
print "</tr></tbody></table></td></tr>\n<tr><td align=\"center\" valign=\"center\">\n";

$td=" align=\"center\" height=\"35\"";

if ($ps2pdf && !file_exists($ps2pdf)) echo "$ps2pdf ikke fundet!";
if ($html2pdf && !file_exists($html2pdf)) echo "$html2pdf ikke fundet!";
if ($pdfmerge && !file_exists($pdfmerge)) echo "$pdfmerge ikke fundet!";
if ($ftp && !file_exists($ftp)) echo "$ftp ikke fundet!";
if ($dbdump && !file_exists($dbdump)) echo "$dbdump ikke fundet!";
if ($zip && !file_exists($zip)) echo "$zip ikke fundet!";
if ($unzip && !file_exists($unzip)) echo "$unzip ikke fundet!";
if ($tar && !file_exists($tar)) echo "$tar ikke fundet!";

if (!$ps2pdf) $ps2pdf=system("which ps2pdf");
if (!$html2pdf) $html2pdf=system("which weasyprint");
if (!$pdfmerge) $pdfmerge=system("which pdftk");
if (!$ftp) $ftp=system("which ncftp");
if (!$dbdump) {
	if ($db_type=='postgresql') $dbdump=system("which pg_dump");
	else $dbdump=system("which mysqldump");
}
if (!$zip) $zip=system("which gzip");
if (!$unzip) $unzip=system("which gunzip");
if (!$tar) $tar=system("which tar");
if (!$alertText) $alertText="uforudset hændelse, Kontakt Salditeamet på 4690 2208";

print "<form name='admin_settings' action='admin_settings.php' method='post'>";
print "<input type='hidden' name='ps2pdfId' value='$ps2pdfId'>";
print "<input type='hidden' name='html2pdfId' value='$html2pdfId'>";
print "<input type='hidden' name='pdfmergeId' value='$pdfmergeId'>";
print "<input type='hidden' name='ftpId' value='$ftpId'>";
print "<input type='hidden' name='dbdumpId' value='$dbdumpId'>";
print "<input type='hidden' name='zipId' value='$zipId'>";
print "<input type='hidden' name='unzipId' value='$unzipId'>";
print "<input type='hidden' name='tarId' value='$tarId'>";
print "<input type='hidden' name='alertTextId' value='$alertTextId'>";
print "<table align=\"center\" border=\"0\" cellspacing=\"5\" cellpadding=\"0\"><tbody>";
print "<tr><td colspan=\"2\" height=\"35\" align=\"center\" background=\"../img/blaa2hvid_bg.gif\">";
print "<big<big><big><b>SALDI</b></big></big></big></td></tr>";
print "<tr><td  colspan=\"2\" height=\"35\" align=\"center\"><b><big>Indstillinger</big></b></td></tr>";
print "<tr><td>Program til konvertering af PostScript til PDF</td><td><input style='width:400px' name='ps2pdf' value='$ps2pdf'></td></tr>"; 
print "<tr><td>Program til konvertering af HTML til PDF</td><td><input style='width:400px' name='html2pdf' value='$html2pdf'></td></tr>"; 
print "<tr><td>Program til sammenlægning af PDF filer</td><td><input style='width:400px' name='pdfmerge' value='$pdfmerge'></td></tr>"; 
print "<tr><td>Program til FTP</td><td><input style='width:400px' name='ftp' value='$ftp'></td></tr>"; 
print "<tr><td>Program til databasedump</td><td><input style='width:400px' name='dbdump' value='$dbdump'></td></tr>";
print "<tr><td>Program til komprimering af filer</td><td><input style='width:400px' name='zip' value='$zip'></td></tr>";
print "<tr><td>Program til dekomprimering af filer</td><td><input style='width:400px' name='unzip' value='$unzip'></td></tr>";
print "<tr><td>Program til pakning af filer</td><td><input style='width:400px' name='tar' value='$tar'></td></tr>";
print "<tr><td>Tekst ved 'uforudset hændelse'</td><td><input style='width:400px' name='alertText' value='$alertText'></td></tr>";
print "<tr><td colspan=\"2\" height=\"35\" align=\"center\"><input type='submit' name='gem' value='gem'></b></td></tr>";
print "</tbody></table>";
print "</form>";
print "</td></tr>";


print "<tr><td align=\"center\" valign=\"bottom\">";
print "<div class=top_bund><small>SALDI&nbsp;version&nbsp;$version&nbsp;-&nbsp;Copyright&nbsp;&copy;&nbsp;$copyright&nbsp;DANOSOFT&nbsp;aps</small></div></td></tr>\n";
print "</td></tr>";
print "</tbody></table>";
print "</body></html>";

?>
