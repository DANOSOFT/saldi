<?php
// ------------- debitor/jobkortprint.php ----- (modul nr 6)------ lap 2.0.2 ----2008-07-03-------
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

$feltantal=NULL;$returside=NULL;$ordre_id=NULL;$fokus=NULL;$ny=NULL;

$title="Jobkortprint";
$modulnr=6;
$kortnr=1;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/db_query.php");

$font="Verdana, Arial, Helvetica";

$id=if_isset($_GET['id']); 
$id=$id*1;
$r=db_fetch_array(db_select("select konto_id from jobkort where id = '$id'",__FILE__ . " linje " . __LINE__));
$konto_id=$r['konto_id'];
$r=db_fetch_array(db_select("select * from jobkort where id = '$id'",__FILE__ . " linje " . __LINE__));
$kontonr=trim($r['kontonr']);
$firmanavn=htmlentities($r['firmanavn']);
$addr1=htmlentities($r['addr1']);
$addr2=htmlentities($r['addr2']);
$postnr=htmlentities($r['postnr']);
$bynavn=htmlentities($r['bynavn']);
$tlf=htmlentities($r['tlf']);
$felt_1=htmlentities($r['felt_1']);
$felt_2=htmlentities($r['felt_2']);
$felt_3=htmlentities($r['felt_3']);
$felt_4=htmlentities($r['felt_4']);
$felt_5=htmlentities($r['felt_5']);
$felt_6=htmlentities($r['felt_6']);
$felt_7=htmlentities($r['felt_7']);
$felt_8=htmlentities($r['felt_8']);
$felt_9=htmlentities($r['felt_9']);
$felt_10=htmlentities($r['felt_10']);
$felt_11=htmlentities($r['felt_11']);
if ($felt_3=="on") $felt_3="Ja";
else $felt_3="nej";
if ($felt_5=="on") $felt_5="Ja";
else $felt_5="nej";
if ($felt_7=="on") $felt_7="Ja";
else $felt_7="nej";
if ($felt_9=="on") $felt_9="Ja";
else $felt_9="nej";

for($x=1;$x<=11;$x++) $felt_indhold[$x][1]=NULL;
$q = db_select("select * from jobkort_felter where job_id = '$id' order by feltnr, subnr",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$z++;
	$x=$r['feltnr']*1;
	$y=$r['subnr']*1;
	$felt_id[$x][$y]=$r['id'];
	$felt_indhold[$x][$y]=htmlentities($r['indhold']);
}
$feltantal=$z;


print "<CENTER>";
print "<TABLE width=702 height=900 BORDER=\"1\" CELLSPACING=\"0\" CELLPADDING=\"0\" VALIGN=\"TOP\"><TBODY><TR>"; # Tabel 1 start
print "<TD bgcolor=\"FFFFFF\">";
print "<Table width=696><tr><td colspan=\"2\">"; #Tabel 1.1 start
print "<Table CELLPADDING=\"0\" cellspacing=\"1\" BORDER=\"0\" width=\"690\">"; #Tabel 1.1.1 start
print "<tr><td colspan=\"6\"><FONT FACE=$font SIZE=\"4\"><center><b>".findtekst(28,$sprog_id)."</font></b></td></tr>";
print "<tr><td colspan=\"3\"><FONT FACE=$font SIZE=\"2\">".findtekst(6,$sprog_id)." ".$id."</font></td>";
print "<td colspan=\"3\" align=\"right\"><FONT FACE=$font SIZE=\"2\">".findtekst(27,$sprog_id)." ".$felt_1."</font></td></tr>";
print "<tr><td colspan=\"3\"><FONT FACE=$font SIZE=\"2\">".$tlf."</font></td></tr>";
print "<tr><td colspan=\"3\"><FONT FACE=$font SIZE=\"2\">".$firmanavn."</font></td></tr>";
print "<tr><td colspan=\"3\"><FONT FACE=$font SIZE=\"2\">".$addr1."</font></td></tr>";
print "<tr><td colspan=\"3\"><FONT FACE=$font SIZE=\"2\">".$addr2."</font></td></tr>";
print "<tr><td colspan=\"3\"><FONT FACE=$font SIZE=\"2\">".$postnr." ".$bynavn."</font></td></tr>";
print "<tr><td colspan=\"6\"><br><br></td></tr>";
print "</tbody></table>"; # tabel 1.1.1 slut;
print "<Table CELLPADDING=\"0\" cellspacing=\"1\" BORDER=\"1\" width=\"688\">"; #Tabel 1.1.2 start

print "<tr><td width=115><FONT FACE=$font SIZE=\"2\">".findtekst(7,$sprog_id)."</font></td>";
print "<td width=230 colspan=\"2\"><FONT FACE=$font SIZE=\"2\">".$felt_2."<br></font></td>";
print "<td width=115><FONT FACE=$font SIZE=\"2\">".findtekst(8,$sprog_id)."</font></td>";
print "<td width=230 colspan=\"2\"><FONT FACE=$font SIZE=\"2\">".$felt_3."<br></font></td></tr>";

print "<tr><td><FONT FACE=$font SIZE=\"2\">".findtekst(9,$sprog_id)."</font></td>";
print "<td colspan=\"2\"><FONT FACE=$font SIZE=\"2\">".$felt_4."<br></font></td>";
print "<td><FONT FACE=$font SIZE=\"2\">".findtekst(10,$sprog_id)."</font></td>";
print "<td colspan=\"2\"><FONT FACE=$font SIZE=\"2\">".$felt_5."<br></font></td></tr>";

print "<tr><td><FONT FACE=$font SIZE=\"2\">".findtekst(11,$sprog_id)."</font></td>";
print "<td colspan=\"2\"><FONT FACE=$font SIZE=\"2\">".$felt_6."<br></font></td>";
print "<td><FONT FACE=$font SIZE=\"2\">".findtekst(12,$sprog_id)."</font></td>";
print "<td colspan=\"2\"><FONT FACE=$font SIZE=\"2\">".$felt_7."<br></font></td></tr>";

print "<tr><td><FONT FACE=$font SIZE=\"2\">".findtekst(13,$sprog_id)."</font></td>";
print "<td colspan=\"2\"><FONT FACE=$font SIZE=\"2\">".$felt_8."<br></font></td>";
print "<td><FONT FACE=$font SIZE=\"2\">".findtekst(14,$sprog_id)."</font></td>";
print "<td colspan=\"2\"><FONT FACE=$font SIZE=\"2\">".$felt_9."<br></font></td></tr>";

print "<tr><td><FONT FACE=$font SIZE=\"2\">".findtekst(15,$sprog_id)."</font></td>";
print "<td colspan=\"2\"><FONT FACE=$font SIZE=\"2\">".$felt_10."<br></font></td>";
print "<td><FONT FACE=$font SIZE=\"2\">".findtekst(16,$sprog_id)."</font></td>";
print "<td colspan=\"2\"><FONT FACE=$font SIZE=\"2\">".$felt_11."<br></font></td></tr>";
print "</tbody></table>"; # tabel 1.1.2 slut;

print "<Table CELLPADDING=\"0\" cellspacing=\"1\" BORDER=\"0\" width=\"688\">"; #Tabel 1.1.3 start
print "<tr><td colspan=\"6\"><br><br></td></tr>";
print "<tr><td colspan=\"6\"><FONT FACE=$font SIZE=\"2\"><u>".findtekst(17,$sprog_id).":</u><br>".$felt_indhold[1][1]."</font></td></tr>";
print "<tr><td colspan=\"6\"><br><br></td></tr>";
print "</tbody></table>"; # tabel 1.1.3 slut;

print "<Table CELLPADDING=\"0\" cellspacing=\"1\" BORDER=\"1\" width=\"688\">"; #Tabel 1.1.4 start

print "<tr><td width=115><FONT FACE=$font SIZE=\"2\">".findtekst(18,$sprog_id)."</font></td>";
print "<td width=115><FONT FACE=$font SIZE=\"2\">".findtekst(19,$sprog_id)."</font></td>";
print "<td width=115><FONT FACE=$font SIZE=\"2\">".findtekst(20,$sprog_id)."</font></td>";
print "<td width=115><FONT FACE=$font SIZE=\"2\">".findtekst(21,$sprog_id)."</font></td>";
print "<td width=115><FONT FACE=$font SIZE=\"2\">".findtekst(22,$sprog_id)."</font></td>";
print "<td width=115><FONT FACE=$font SIZE=\"2\">".findtekst(23,$sprog_id)."</font></td></tr>";

$x=1;
while (isset($felt_id[2][$x])|isset($felt_id[3][$x])|isset($felt_id[4][$x])|isset($felt_id[5][$x])|isset($felt_id[6][$x])|isset($felt_id[7][$x])) {
	for($i=2;$i<=7;$i++) if (!isset($felt_indhold[$i][$x])) $felt_indhold[$i][$x]=NULL;
	$sum5=$sum5+$felt_indhold[5][$x];
	$sum6=$sum6+$felt_indhold[6][$x];
	$sum7=$sum7+$felt_indhold[7][$x];
	print "<tr><td width=115><FONT FACE=$font SIZE=\"2\">".dkdato($felt_indhold[2][$x])."<br></font></td>";
	print "<td width=115><FONT FACE=$font SIZE=\"2\">".$felt_indhold[3][$x]."<br></font></td>";
	print "<td width=115><FONT FACE=$font SIZE=\"2\">".$felt_indhold[4][$x]."<br></font></td>";
	print "<td width=115 align=right><FONT FACE=$font SIZE=\"2\">".dkdecimal($felt_indhold[5][$x])."<br></font></td>";
	print "<td width=115 align=right><FONT FACE=$font SIZE=\"2\">".dkdecimal($felt_indhold[6][$x])."<br></font></td>";
	print "<td width=115 align=right><FONT FACE=$font SIZE=\"2\">".dkdecimal($felt_indhold[7][$x])."<br></font></td></tr>";
	$x++;
}
if ($x>2) {
#	$sum5=dkdecimal($sum5);$sum6=dkdecimal($sum6);$sum7=dkdecimal($sum7);
	print	"<td colspan=3><FONT FACE=$font SIZE=\"2\">I alt</font></td>";
	print "<td align=right><FONT FACE=$font SIZE=\"2\">".dkdecimal($sum5)."</font></td>";
	print "<td align=right><FONT FACE=$font SIZE=\"2\">".dkdecimal($sum6)."</font></td>";
	print "<td align=right><FONT FACE=$font SIZE=\"2\">".dkdecimal($sum7)."</font></td></tr>";
}
print "</tbody></table>"; # tabel 1.1.4 slut;
print "<tr><td colspan=\"6\"><br><br></td></tr>";
print "<Table CELLPADDING=\"0\" cellspacing=\"1\" BORDER=\"0\" width=\"688\">"; #Tabel 1.1.5 start
print "<tr><td colspan=\"6\"><FONT FACE=$font SIZE=\"2\"><u>".findtekst(24,$sprog_id).":</u><br>".$felt_indhold[8][1]."<br></font></td></tr>";
print "</tbody></table>"; # tabel 1.1.5 slut;
print "<Table CELLPADDING=\"0\" cellspacing=\"1\" BORDER=\"0\" width=\"688\">"; #Tabel 1.1.6 start
print "<tr><td colspan=\"6\"><br><br></td></tr>";
print "<tr><td colspan=\"6\"><FONT FACE=$font SIZE=\"2\"><u>".findtekst(25,$sprog_id).":</u><br>".$felt_indhold[9][1]."<br></font></td></tr>";
print "</tbody></table>"; # tabel 1.1.6 slut;
print "<Table CELLPADDING=\"0\" cellspacing=\"1\" BORDER=\"0\" width=\"688\">"; #Tabel 1.1.7 start
print "<tr><td colspan=\"6\"><br><br></td></tr>";
print "<tr><td colspan=\"6\"><FONT FACE=$font SIZE=\"2\"><u>".findtekst(26,$sprog_id).":</u><br>".$felt_indhold[10][1]."<br></font></td></tr>";
print "</tbody></table>"; # tabel 1.1.7 slut;

print "</tbody></table>"; # tabel 1.1 slut;
print "</tbody></table>"; # tabel 1 slut;
/*
function skriv ($tekst, $left, $top)
	print "<div style=\"position:absolute; left:".$left."px; top:".$top."px\">".$tekst."</div>";
}
*/
?>

</body>
</html>
