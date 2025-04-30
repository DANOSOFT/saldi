<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------/admin/backup.php---lap 3.8.9--2025-04-27---------------------
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// 2019.07.04 RG (Rune Grysbæk) Mysqli implementation 
// 2020.03.08 PHR SQL dump created on demand.
// 20250427 LOE User can now delete backup file saved if they changed their minds.

@session_start();
$s_id=session_id();

$css="../css/standard.css";
$title="Sikkerhedskopi";
$modulnr=11;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

if (!isset($exec_path)) $exec_path="/usr/bin";

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

$dump_filnavn="../temp/".trim($db.".sql");
$info_filnavn="../temp/backup.info";
$tar_filnavn="../temp/".trim($db."_".date("Ymd-Hi")).".tar";
$gz_filnavn="../temp/".trim($db."_".date("Ymd-Hi")).".tar.gz";
$dat_filnavn="../temp/".trim($db."_".date("Ymd-Hi")).".sdat";
$timestamp=date("Ymd-Hi");
$r=db_fetch_array(db_select("select box1 from grupper where art = 'VE'",__FILE__ . " linje " . __LINE__));
$dbver=$r['box1'];
if (isset($_GET['backup']) && $_GET['backup']==1) {
	$fp=fopen($info_filnavn,"w");
	if ($fp) {
		fwrite($fp,"$timestamp".chr(9)."$db".chr(9)."$dbver".chr(9)."$regnskab".chr(9)."$db_encode".chr(9)."$db_type");
	} 
	fclose($fp);
	if ($db_type=='mysql' or $db_type=='mysqli') $dumpcmd="$exec_path/mysqldump -h $sqhost -u $squser --password=$sqpass -n $db --result-file=../temp/$dump_filnavn"; #RG_mysqli
	else $dumpcmd="export PGPASSWORD=$sqpass\n$exec_path/pg_dump -h $sqhost -U $squser -f $dump_filnavn $db";
	print "<!-- RUNEDEBUG $dumpcmd -->";
	print "<!-- Saldi-kommentar for at skjule uddata til siden \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
	system ($dumpcmd);
	system ("tar -cf $tar_filnavn $dump_filnavn $info_filnavn");
	system ("gzip $tar_filnavn");
	system ("mv $gz_filnavn $dat_filnavn");
	print "--> \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
}

print "<div align='center'>";
if ($menu=='T') {
#	print "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>";
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id='header'>";
	print "<div class='headerbtnLft'></div>\n";
#	print "<span class='headerTxt'>Systemsetup</span>\n";     
#	print "<div class='headerbtnRght'><!--<a href='index.php?page=../debitor/debitorkort.php;title=debitor' class='button green small right'>Ny debitor</a>--></div>";       
	print "</div><!-- end of header -->";
	print "<div id='leftmenuholder'>";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class='maincontent'>\n";

	print "<table border='0' cellspacing='0' id='dataTable' class='dataTable'><tbody>"; # -> 1
} elseif ($menu=='S') {
	print "<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'><tbody>";
	print "<tr><td align='center' valign='top'>";
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";

	print "<td width='10%' title='".findtekst('1673|Klik her for at vende tilbage til hovedmenuen', $sprog_id)."'>";
	print "<a href=$returside accesskey=L><button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
		   .findtekst('30|Tilbage', $sprog_id)."</button></a></td>";

	print "<td width='80%' align='center' style='$topStyle'>".findtekst('614|Sikkerhedskopi', $sprog_id)."</td>";

	print "<td width='10%' align='center' style='$topStyle'><br></td>";

	print "</tbody></table>";
	print "</td></tr>";
} else {
	print "<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'><tbody>";
	print "<tr><td align='center' valign='top'>";
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";
	print "<td width='10%' $top_bund title='".findtekst('1673|Klik her for at vende tilbage til hovedmenuen', $sprog_id)."'>";
	print "<a href=$returside accesskey=L>".findtekst('30|Tilbage', $sprog_id)."</a></td>";
	print "<td width='80%' $top_bund>".findtekst('614|Sikkerhedskopi', $sprog_id)."</td>";
	print "<td width='10%' $top_bund><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
}

if ($menu=='T' || $menu=='S') {
	$style = "";
	$buttonStart = "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
	$buttonEnd = "</button>";
} else {
	$style = $top_bund;
	$buttonStart = "";
	$buttonEnd = "";
}

########################Handle the cancel instruction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
	$file_path = $_POST['file_path'];
	
	if (file_exists($file_path)) {
		unlink($file_path);
	} else {
		error_log("Error: Attempted to delete a non-existing file: $file_path");
	}
}
############################
print "<td align='center' valign='middle'>";
print "<table cellpadding='1' cellspacing='1' border='0'><tbody>";

if (file_exists("../temp/$dat_filnavn")) {
    print "<tr><td align=center>".findtekst('1241|Klik her', $sprog_id).": </td>";
    print "<td $style title='".findtekst('1674|Her har du mulighed for danne en sikkerhedskopi som du kan gemme.', $sprog_id).".'>";
    print "<a href='../temp/$dat_filnavn'> $buttonStart";
    print findtekst('3|Gem', $sprog_id)." ".findtekst('614|Sikkerhedskopi', $sprog_id);
    print "$buttonEnd </a></td></tr>";
    print "<tr><td align=center colspan=2>".findtekst('1242|og gem sikkerhedskopien et passende sted.', $sprog_id)."</td></tr>";
    print "<tr><td align=center colspan=2>".findtekst('1243|Hvis din browser forsøger at åbne filen', $sprog_id)."</td></tr>";
    print "<tr><td align=center colspan=2>".findtekst('1244|skal du højreklikke og vælge gem som', $sprog_id)."</td></tr>";
    
    // Add Cancel button below

    print "<tr><td align=center colspan=2>";
    print "<form action='' method='post'>";
    print "<input type='hidden' name='file_path' value='../temp/$dat_filnavn' />";
    print "<input type='submit' name='cancel' value='Cancel' />";
    print "</form>";
    print "</td></tr>";
	
} else {
    print "<tr><td align=center>".findtekst('1241|Klik her', $sprog_id).": </td><td $style  title='".findtekst('1675|Her har du mulighed for at gemme sikkerhedskopien på din computer', $sprog_id)."'>";
    print "<a href='backup.php?backup=1'> $buttonStart";
    print findtekst('1245|Dan sikkerhedskopi', $sprog_id);
    print "$buttonEnd </a></td></tr>";
    print "<tr><td align=center colspan=2>".findtekst('1246|for at danne en sikkerhedskopi', $sprog_id)."<br>".findtekst('1676|som du kan gemme på din computer', $sprog_id).".</td></tr>";
    print "<tr><td colspan=2><hr></td></tr>";
    print "<tr><td align=center> ".findtekst('1241|Klik her', $sprog_id).": </td>";
    print "<td $style title='".findtekst('1677|Her har du mulighed for at genindlæse en tidligere gemt sikkerhedskopi', $sprog_id)."'>";
    print "<a href=../admin/restore.php> $buttonStart";
    print findtekst('1247|Indlæs sikkerhedskopi', $sprog_id);
    print "$buttonEnd </a></td></tr>";
    print "<tr><td align=center colspan=2>".findtekst('1248|for at indlæse en sikkerhedskopi<br>fra din computer.', $sprog_id)."</td></tr>";
}
print "</tbody></table></tbody></table>";

print "</div></div>";


if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

?>
