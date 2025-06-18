<?php
// ------------- debitor/jobkortprint.php ----- (modul nr 6)------ lap 2.0.9 ----2009-06-25-------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$feltantal=NULL;$returside=NULL;$ordre_id=NULL;$fokus=NULL;$ny=NULL;

$title="Jobkortprint";
$modulnr=6;
$kortnr=1;
$css="../css/standard.css";

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
$firmanavn=htmlentities($r['firmanavn'],ENT_QUOTES,$charset);
$addr1=htmlentities($r['addr1'],ENT_QUOTES,$charset);
$addr2=htmlentities($r['addr2'],ENT_QUOTES,$charset);
$postnr=htmlentities($r['postnr'],ENT_QUOTES,$charset);
$bynavn=htmlentities($r['bynavn'],ENT_QUOTES,$charset);
$tlf=htmlentities($r['tlf'],ENT_QUOTES,$charset);
$felt_1=htmlentities($r['felt_1'],ENT_QUOTES,$charset);
$felt_2=htmlentities($r['felt_2'],ENT_QUOTES,$charset);
$felt_3=htmlentities($r['felt_3'],ENT_QUOTES,$charset);
$felt_4=htmlentities($r['felt_4'],ENT_QUOTES,$charset);
$felt_5=htmlentities($r['felt_5'],ENT_QUOTES,$charset);
$felt_6=htmlentities($r['felt_6'],ENT_QUOTES,$charset);
$felt_7=htmlentities($r['felt_7'],ENT_QUOTES,$charset);
$felt_8=htmlentities($r['felt_8'],ENT_QUOTES,$charset);
$felt_9=htmlentities($r['felt_9'],ENT_QUOTES,$charset);
$felt_10=htmlentities($r['felt_10'],ENT_QUOTES,$charset);
$felt_11=htmlentities($r['felt_11'],ENT_QUOTES,$charset);
if ($felt_3=="on") $felt_3="&#x2713;";
if ($felt_5=="on") $felt_5="&#x2713;";
if ($felt_7=="on") $felt_7="&#x2713;";
if ($felt_9=="on") $felt_9="&#x2713;";

$r=db_fetch_array(db_select("select fax,tlf from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__));
$fax=stripslashes($r['fax']);
$tlf=stripslashes($r['tlf']);

for($x=1;$x<=11;$x++) $felt_indhold[$x][1]=NULL;
$q = db_select("select * from jobkort_felter where job_id = '$id' order by feltnr, subnr",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$z++;
	$x=$r['feltnr']*1;
	$y=$r['subnr']*1;
	$felt_id[$x][$y]=$r['id'];
	$felt_indhold[$x][$y]=htmlentities($r['indhold'],ENT_QUOTES,$charset);
}
$feltantal=$z;

print "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"da-dk\" lang=\"da-dk\">";
print "<head>";
print "<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\"/>";
print "</head>";
print "<CENTER>";
print "<TABLE width=702 height=900 BORDER=\"0\" CELLSPACING=\"0\" CELLPADDING=\"0\" VALIGN=\"TOP\"><TBODY><TR>"; # Tabel 1 start
print "<TD bgcolor=\"FFFFFF\" valign=\"top\">";
print "<Table width=696><tr><td colspan=\"2\">"; #Tabel 1.1 start
print "<Table CELLPADDING=\"0\" cellspacing=\"1\" BORDER=\"0\" width=\"690\">"; #Tabel 1.1.1 start
print "<tr><td colspan=\"3\"><FONT FACE=$font SIZE=\"4\"><center><b>".findtekst(28,$sprog_id)."</font></b></td></tr>";
print "<tr><td width=\"80%\"><FONT FACE=$font SIZE=\"2\">".findtekst(6,$sprog_id)." ".$id."</font></td>";
print "<td width=\"20%\"><FONT FACE=$font SIZE=\"2\">".findtekst(27,$sprog_id)."</font></td><td align=\"right\"><FONT FACE=$font SIZE=\"2\">".$felt_1."</font></td></tr>";
print "<tr><td><FONT FACE=$font SIZE=\"2\">".$kontonr."</font></td></tr>";
print "<tr><td><FONT FACE=$font SIZE=\"2\">".$firmanavn."</font></td>";
print "<td><align=\"right\"><FONT FACE=$font SIZE=\"2\">".findtekst(377,$sprog_id)."<!--tekst 377--></font></td><td align=\"right\"><FONT FACE=$font SIZE=\"2\">$tlf</font></td></tr>";
print "<tr><td><FONT FACE=$font SIZE=\"2\">".$addr1."</font></td>";
print "<td><align=\"right\"><FONT FACE=$font SIZE=\"2\">".findtekst(378,$sprog_id)."<!--tekst 378--></font></td><td align=\"right\"><FONT FACE=$font SIZE=\"2\">$fax</font></td></tr>";
print "<tr><td><FONT FACE=$font SIZE=\"2\">".$addr2."</font></td></tr>";
print "<tr><td><FONT FACE=$font SIZE=\"2\">".$postnr." ".$bynavn."</font></td></tr>";
print "<tr><td colspan=\"3\"><br><br></td></tr>";
print "</tbody></table>"; # tabel 1.1.1 slut;
print "<Table CELLPADDING=\"0\" cellspacing=\"1\" BORDER=\"1\" width=\"688\">"; #Tabel 1.1.2 start

print "<tr><td width=\"12.5%\"><FONT FACE=$font SIZE=\"2\"><b>".findtekst(7,$sprog_id)."</b></font></td>";
print "<td width=\"12.5%\"><FONT FACE=$font SIZE=\"2\">".$felt_2."<br></font></td>";
print "<td width=\"12.5%\"><FONT FACE=$font SIZE=\"2\"><b>".findtekst(9,$sprog_id)."</b></font></td>";
print "<td width=\"12.5%\"><FONT FACE=$font SIZE=\"2\">".$felt_4."<br></font></td>";
print "<td width=\"12.5%\"><FONT FACE=$font SIZE=\"2\"><b>".findtekst(13,$sprog_id)."</font></b></td>";
print "<td width=\"12.5%\"><FONT FACE=$font SIZE=\"2\">".$felt_8."<br></font></td>";
print "<td width=\"12.5%\"><FONT FACE=$font SIZE=\"2\"><b>".findtekst(15,$sprog_id)."</b></font></td>";
print "<td width=\"12.5%\" colspan=\"2\"><FONT FACE=$font SIZE=\"2\">".$felt_10."<br></font></td></tr>";

print "<tr><td><FONT FACE=$font SIZE=\"2\"><b>".findtekst(12,$sprog_id)."</b></font></td>";
print "<td><FONT FACE=$font SIZE=\"2\">".$felt_7."<br></font></td>";
print "<td><FONT FACE=$font SIZE=\"2\"><b>".findtekst(10,$sprog_id)."</b></font></td>";
print "<td><FONT FACE=$font SIZE=\"2\">".$felt_5."<br></font></td>";
print "<td><FONT FACE=$font SIZE=\"2\"><b>".findtekst(14,$sprog_id)."</b></font></td>";
print "<td><FONT FACE=$font SIZE=\"2\">".$felt_9."<br></font></td>";
print "<td width=\"12.5%\"><FONT FACE=$font SIZE=\"2\"><b>".findtekst(8,$sprog_id)."</b></font></td>";
print "<td width=\"12.5%\"><FONT FACE=$font SIZE=\"2\">".$felt_3."<br></font></td></tr>";

print "<tr><td><FONT FACE=$font SIZE=\"2\"><b>".findtekst(11,$sprog_id)."</b></font></td>";
print "<td colspan=\"7\"><FONT FACE=$font SIZE=\"2\">".$felt_6."<br></font></td><tr>";

print "<tr><td><FONT FACE=$font SIZE=\"2\"><b>".findtekst(16,$sprog_id)."</b></font></td>";
print "<td colspan=\"7\"><FONT FACE=$font SIZE=\"2\">".$felt_11."<br></font></td></tr>";
print "</tbody></table>"; # tabel 1.1.2 slut;

print "<Table CELLPADDING=\"0\" cellspacing=\"1\" BORDER=\"0\" width=\"688\">"; #Tabel 1.1.3 start
print "<tr><td colspan=\"6\"></td></tr>";
print "<tr><td colspan=\"6\"><FONT FACE=$font SIZE=\"2\"><b>".findtekst(17,$sprog_id).":</b><br>".$felt_indhold[1][1]."</font></td></tr>";
print "<tr><td colspan=\"6\" height=\"100\"></td></tr>";
print "</tbody></table>"; # tabel 1.1.3 slut;

print "<Table CELLPADDING=\"0\" cellspacing=\"1\" BORDER=\"1\" width=\"688\">"; #Tabel 1.1.4 start

print "<tr><td width=\"115\"><FONT FACE=$font SIZE=\"2\">".findtekst(18,$sprog_id)."</font></td>";
print "<td width=\"115\"><FONT FACE=$font SIZE=\"2\">".findtekst(19,$sprog_id)."</font></td>";
print "<td width=\"115\"><FONT FACE=$font SIZE=\"2\">".findtekst(20,$sprog_id)."</font></td>";
print "<td width=\"115\"><FONT FACE=$font SIZE=\"2\">".findtekst(21,$sprog_id)."</font></td>";
print "<td width=\"115\"><FONT FACE=$font SIZE=\"2\">".findtekst(22,$sprog_id)."</font></td>";
print "<td width=\"115\"><FONT FACE=$font SIZE=\"2\">".findtekst(23,$sprog_id)."</font></td></tr>";

$x=1;
#while (isset($felt_id[2][$x])|isset($felt_id[3][$x])|isset($felt_id[4][$x])|isset($felt_id[5][$x])|isset($felt_id[6][$x])|isset($felt_id[7][$x])) {
while ($x<=5) {	
	for($i=2;$i<=7;$i++) if (!isset($felt_indhold[$i][$x])) $felt_indhold[$i][$x]=NULL;
	$sum5=$sum5+$felt_indhold[5][$x];
	$sum6=$sum6+$felt_indhold[6][$x];
	$sum7=$sum7+$felt_indhold[7][$x];
	print "<tr><td width=\"115\"><FONT FACE=$font SIZE=\"2\">".dkdato($felt_indhold[2][$x])."<br></font></td>";
	print "<td width=\"115\"><FONT FACE=$font SIZE=\"2\">".$felt_indhold[3][$x]."<br></font></td>";
	print "<td width=\"115\"><FONT FACE=$font SIZE=\"2\">".$felt_indhold[4][$x]."<br></font></td>";
	if ($felt_indhold[5][$x]) $tmp=dkdecimal($felt_indhold[5][$x]);
	else $tmp='';
	print "<td width=\"115\" align=right><FONT FACE=$font SIZE=\"2\">$tmp<br></font></td>";
	if ($felt_indhold[6][$x]) $tmp=dkdecimal($felt_indhold[6][$x]);
	else $tmp='';
	print "<td width=\"115\" align=right><FONT FACE=$font SIZE=\"2\">$tmp<br></font></td>";
	if ($felt_indhold[7][$x]) $tmp=dkdecimal($felt_indhold[7][$x]);
	else $tmp='';
	print "<td width=\"115\" align=right><FONT FACE=$font SIZE=\"2\">$tmp<br></font></td></tr>";
	$x++;
}
if ($x>2) {
#	$sum5=dkdecimal($sum5);$sum6=dkdecimal($sum6);$sum7=dkdecimal($sum7);
	print	"<td colspan=3><FONT FACE=$font SIZE=\"2\">I alt</font></td>";
	if ($sum5) $tmp=dkdecimal($sum5);
	else $tmp='';
	print "<td align=right><FONT FACE=$font SIZE=\"2\">$tmp<br></font></td>";
	if ($sum6) $tmp=dkdecimal($sum6);
	else $tmp='';
	print "<td align=right><FONT FACE=$font SIZE=\"2\">$tmp<br></font></td>";
	if ($sum7) $tmp=dkdecimal($sum7);
	else $tmp='';
	print "<td align=right><FONT FACE=$font SIZE=\"2\">$tmp<br></font></td></tr>";
}
print "</tbody></table>"; # tabel 1.1.4 slut;
print "<tr><td colspan=\"6\"></td></tr>";
print "<Table CELLPADDING=\"0\" cellspacing=\"1\" BORDER=\"0\" width=\"688\">"; #Tabel 1.1.5 start
print "<tr><td colspan=\"6\" height=\"120\" valign=\"top\"><FONT FACE=$font SIZE=\"2\"><b>".findtekst(24,$sprog_id).":</b><br>".$felt_indhold[8][1]."<br></font></td></tr>";
print "</tbody></table>"; # tabel 1.1.5 slut;
print "<Table CELLPADDING=\"0\" cellspacing=\"1\" BORDER=\"0\" width=\"688\">"; #Tabel 1.1.6 start
print "<tr><td colspan=\"6\"><br><hr></td></tr>";
print "<tr><td colspan=\"6\" height=\"120\" valign=\"top\"><FONT FACE=$font SIZE=\"2\"><b>".findtekst(25,$sprog_id).":</b><br>".$felt_indhold[9][1]."<br></font></td></tr>";
print "</tbody></table>"; # tabel 1.1.6 slut;
print "<Table CELLPADDING=\"0\" cellspacing=\"1\" BORDER=\"0\" width=\"688\">"; #Tabel 1.1.7 start
print "<tr><td colspan=\"6\"><br><hr></td></tr>";
print "<tr><td colspan=\"6\"><FONT FACE=$font SIZE=\"2\"><b>".findtekst(26,$sprog_id).":</b><br>".$felt_indhold[10][1]."<br></font></td></tr>";
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
