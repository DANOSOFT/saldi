<?php
// ----------/admin/backup.php---lap 3.3.0--2013-01-05---11:14---------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2013 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$css="../css/standard.css";
$title="Sikkerhedskopi";
$modulnr=11;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

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
$fp=fopen($info_filnavn,"w");
if ($fp) {
	fwrite($fp,"$timestamp".chr(9)."$db".chr(9)."$dbver".chr(9)."$regnskab".chr(9)."$db_encode".chr(9)."$db_type");
} 
fclose($fp);
if ($db_type=='mysql') $dumpcmd="$exec_path/mysqldump -h $sqhost -u $squser --password=$sqpass -n $db --result-file=../temp/$dump_filnavn";
else $dumpcmd="export PGPASSWORD=$sqpass\n$exec_path/pg_dump -h $sqhost -U $squser -f $dump_filnavn $db";
print "<!-- Saldi-kommentar for at skjule uddata til siden \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
system ($dumpcmd);
system ("tar -cf $tar_filnavn $dump_filnavn $info_filnavn");
system ("gzip $tar_filnavn");
system ("mv $gz_filnavn $dat_filnavn");
print "--> \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres

print "<div align=\"center\">";
if ($menu=='T') {
#	print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">";
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">\n";
	print "<div class=\"headerbtnLft\"></div>\n";
#	print "<span class=\"headerTxt\">Systemsetup</span>\n";     
#	print "<div class=\"headerbtnRght\"><!--<a href=\"index.php?page=../debitor/debitorkort.php;title=debitor\" class=\"button green small right\">Ny debitor</a>--></div>";       
	print "</div><!-- end of header -->";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontent\">\n";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\"><tbody>"; # -> 1
} else {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund title=\"Klik her for at vende tilbage til hovedmenuen\"><a href=$returside accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund>Sikkerhedskopi</td>";
	print "<td width=\"10%\" $top_bund><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
}
print "<td align=\"center\" valign=\"middle\">";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
print "<tr><td align=center> Klik her: </td><td $top_bund  title=\"Her har du mulighed for at sikkerhedskopiere dit regnskab\"> <a href='../temp/$dat_filnavn'>Gem sikkerhedskopi</a></td></tr>";
print "<tr><td align=center colspan=2> og gem sikkerhedskopien et passende sted</td></tr>";
print "<tr><td colspan=2><hr></td></tr>";
print "<tr><td align=center> Klik her: </td><td $top_bund title=\"Her har du mulighed for at genindl&aelig;se en tidligere gemt sikkerhedskopi\"><a href=../admin/restore.php>Indl&aelig;s sikkerhedskopi</a></td></tr>";
print "<tr><td align=center colspan=2> for at indl&aelig;se en sikkerhedskopi</td></tr>";
print "</tbody></table></div>";

?>
</body></html>
