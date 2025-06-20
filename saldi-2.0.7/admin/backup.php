<?php
// ---------------------/admin/backup.php---lap 2.0.2d-----2008.10.16-----------------------
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$css="../css/standard.css";
$title="Sikkerhedskopi";
$modulnr=11;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

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
	$linje=trim(fgets($fp));
	fwrite($fp,"$timestamp".chr(9)."$db".chr(9)."$dbver".chr(9)."$regnskab".chr(9)."$db_encode".chr(9)."$db_type");
} 
fclose($fp);
print "<!-- Saldi-kommentar for at skjule uddata til siden \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
if ($db_type=='mysql') system ("mysqldump -h $sqhost -u $squser --password=$sqpass -n $db > $dump_filnavn");
else system ("export PGPASSWORD=$sqpass\npg_dump -h $sqhost -U $squser -f $dump_filnavn $db");
system ("tar -cf $tar_filnavn $dump_filnavn $info_filnavn");
system ("gzip $tar_filnavn");
system ("mv $gz_filnavn $dat_filnavn");
print "--> \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
		print "<td width=\"10%\" $top_bund title=\"Klik her for at vende tilbage til hovedmenuen\"><a href=$returside accesskey=L>Luk</a></td>";
		print "<td width=\"80%\" $top_bund>Sikkerhedskopi</td>";
		print "<td width=\"10%\" $top_bund><br></td>";
		print "</tbody></table>";
		print "</td></tr>";
		print "<td align=center valign=top>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

	print "<tr><td align=center> Klik her: </td><td $top_bund  title=\"Her har du mulighed for at sikkerhedskopiere dit regnskab\"> <a href='../temp/$dat_filnavn'>Gem sikkerhedskopi</a></td></tr>";
	print "<tr><td align=center colspan=2> og gem sikkerhedskopien et passende sted</td></tr>";
	print "<tr><td colspan=2><hr></td></tr>";
	print "<tr><td align=center> Klik her: </td><td $top_bund title=\"Her har du mulighed for at genindl&aelig;se en tidligere gemt sikkerhedskopi\"><a href=../admin/restore.php>Indl&aelig;s sikkerhedskopi</a></td></tr>";
	print "<tr><td align=center colspan=2> for at indl&aelig;se en sikkerhedskopi</td></tr>";

print "</tbody></table>";

?>
</body></html>
