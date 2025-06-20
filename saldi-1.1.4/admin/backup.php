<?php
// ---------------------/admin/backup.php---lap 1.1.3-----30.01.2008-----------------------
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
$title="Sikkerhedskopi";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

$returside="../index/menu.php";

$dump_filnavn="../temp/".trim($db."_".date("Y-m-d"));
$gz_filnavn="../temp/".trim($db."_".date("Y-m-d")).".gz";
$dat_filnavn="../temp/".trim($db."_".date("Y-m-d")).".sdat";

print "<!-- Saldi-kommentar for at skjule uddata til siden \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
system("export PGPASSWORD=$sqpass\npg_dump -h $sqhost -U $squser -f $dump_filnavn $db");
system ("gzip $dump_filnavn");
system ("mv $gz_filnavn $dat_filnavn");
print "--> \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
		print "<td width=\"10%\" $top_bund title=\"Klik her for at vende tilbage til hovedmenuen\">$font<small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>";
		print "<td width=\"80%\" $top_bund>$font<small>Sikkerhedskopi</small></td>";
		print "<td width=\"10%\" $top_bund>$font<small><br></small></td>";
		print "</tbody></table>";
		print "</td></tr>";
		print "<td align=center valign=top>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

	print "<tr><td align=center>$font Klik her: </td><td $top_bund  title=\"Her har du mulighed for at sikkerhedskopiere dit regnskab\"> $font<a href='../temp/$dat_filnavn'>Gem sikkerhedskopi</a></td></tr>";
	print "<tr><td align=center colspan=2>$font og gem sikkerhedskopien et passende sted</td></tr>";
	print "<tr><td colspan=2><hr></td></tr>";
	print "<tr><td align=center>$font Klik her: </td><td $top_bund title=\"Her har du mulighed for at genindl&aelig;se en tidligere gemt sikkerhedskopi\">$font<a href=../admin/restore.php>Indl&aelig;s sikkerhedskopi</a></td></tr>";
	print "<tr><td align=center colspan=2>$font for at indl&aelig;se en sikkerhedskopi</td></tr>";

print "</tbody></table>";

?>
</body></html>
