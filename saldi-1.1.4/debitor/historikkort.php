<?php
// ------------- debitor/historikkort.php ----- (modul nr 6)------ lap 1.1.3 ---05.02.2008-------
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

?>
<script LANGUAGE="JavaScript">
<!--
function Slet()
{
var agree=confirm("Slet handling?");
if (agree)
        return true ;
else
        return false ;
}
// -->
</script>
<?php

$modulnr=6;
$title="Historik";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdato.php");
include("../includes/usdate.php");
include("../includes/db_query.php");

$id = $_GET['id'];
if ($_GET[konto_id]) $id = $_GET['konto_id'];
if ($_GET[historik_id]) $historik_id=$_GET['historik_id'];
$handling=$_GET['handling'];

if ($handling=='slet') {
	db_modify("delete from historik where id = $historik_id");
	$historik_id='';
}

# if($_GET['returside']){
# 	$returside= $_GET['returside'];
# 	$ordre_id = $_GET['ordre_id'];
# 	$fokus = $_GET['fokus'];
#}
#else {$returside="historik.php";}
$returside="historik.php";

if ($_POST['submit']){
	$submit=trim($_POST['submit']);
	$id=$_POST['id'];
	$egen_id=$_POST['egen_id'];
	$historik_id=$_POST['historik_id'];
	$ansat=$_POST['ansat'];
	$kontakt=$_POST['kontakt'];
	if ($_POST[oprettet]) $oprettet=$_POST['oprettet'];
	if ($_POST[kontaktet])$kontaktet=usdate($_POST['kontaktet']);
	else $kontaktet=date("Y-m-d");
	if ($_POST[kontaktes])$kontaktes=usdate($_POST['kontaktes']);
	$notat=addslashes(trim($_POST['note']));

	if ($kontaktes || $notat) {
		$r = db_fetch_array(db_select("select id from ansatte where konto_id = '$egen_id' and navn = '$ansat'"));
		$ansat_id=$r[id];
		$r = db_fetch_array(db_select("select id from ansatte where konto_id = '$id' and navn = '$kontakt'"));
		$kontakt_id=$r[id]*1;
		if ($historik_id) {
			db_modify("update historik set kontakt_id = $kontakt_id, ansat_id = $ansat_id, notat = '$notat', kontaktet = '$kontaktet' where id = $historik_id");
		} else {
			$notedate=date("Y-m-d");
			$r = db_fetch_array(db_select("select id from ansatte where konto_id = '$egen_id' and navn = '$ansat'"));
			$ansat_id=$r[id];
			$r = db_fetch_array(db_select("select id from ansatte where konto_id = '$id' and navn = '$kontakt'"));
			$kontakt_id=$r[id]*1;
			if ($kontaktes) db_modify("insert into historik (konto_id, kontakt_id, ansat_id, notat, notedate, kontaktet, kontaktes) values ($id , $kontakt_id, $ansat_id, '$notat', '$notedate', '$kontaktet', '$kontaktes')");
			else db_modify("insert into historik (konto_id, kontakt_id, ansat_id, notat, notedate, kontaktet) values ($id , $kontakt_id, $ansat_id, '$notat', '$notedate', '$kontaktet')");
		}
		if ($kontaktes) {
			db_modify("update adresser set kontaktet = '$kontaktet', kontaktes = '$kontaktes' where id = $id");
			if ($historik_id) db_modify("update historik set kontaktes = '$kontaktes' where id = $historik_id");
		} else db_modify("update adresser set kontaktet = '$kontaktet' where id = $id");
	}
	$historik_id=0;
}
############################

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<tr><td align=\"center\" valign=\"top\" height=\"1%\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";
print "<td width=\"10%\"$top_bund>$font<small><a href=$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id accesskey=L>Luk</a></small></td>\n";
print "<td width=\"80%\"$top_bund>$font<small>Debitorkort</small></td>\n";
print "<td width=\"10%\"$top_bund>$font<small><a href=debitorkort.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id accesskey=N>Ny</a><br></small></td>\n";
print "</tbody></table>\n";
print "</td></tr>\n";
print "<tr><td height=\"99%\"  width=\"100%\" valign=\"top\">";
print "<table width=\"100%\" cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

$font=$font."<small>";
if ($id > 0){
	$q = db_select("select * from adresser where id = '$id'");
	$r = db_fetch_array($q);
	$kontonr=trim($r['kontonr']);
	$firmanavn=stripslashes(htmlentities(trim($r['firmanavn'])));
	$addr1=stripslashes(htmlentities(trim($r['addr1'])));
	$addr2=stripslashes(htmlentities(trim($r['addr2'])));
	$postnr=trim($r['postnr']);
	$bynavn=stripslashes(htmlentities(trim($r['bynavn'])));
	$land=stripslashes(htmlentities(trim($r['land'])));
	$tlf=trim($r['tlf']);
	$fax=trim($r['fax']);
	$email=trim($r['email']);
	$web=trim($r['web']);
	if ($r[oprettet]) $oprettet=dkdato($r['oprettet']);
	if ($r[kontaktet]) $kontaktet=dkdato($r['kontaktet']);
	if ($r[kontaktes]) $kontaktes=dkdato($r['kontaktes']);
}

print "<form name=historikkort action=historikkort.php method=post>";
print "<input type=hidden name=id value='$id'>";
print "<tr><td colspan=6>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"800\"><tbody>";
print "<tr><td>$font<a href=debitorkort.php?id=$id&returside=historikkort.php>$firmanavn</a></td><td>$font</td><td>$font</td><td>$font Oprettet</td><td>";
if ($oprettet) print "$font $oprettet";
else print "$font <input type=text name=oprettet size=11>";
print "</td></tr>\n";
print "<tr><td>$font $addr1</td><td>$font $addr2</td></tr>\n";
print "<tr><td>$font $postnr $bynavn</td><td>$font $land</td><td>$font</td><td>$font Sidste kontakt</td><td>$font $kontaktet</td></tr>\n";
print "<tr><td>$font Tlf: $tlf</td><td>$font ";
if ($fax) print "Fax: $fax";
print "</td><td>$font</td><td>$font N&aelig;ste kontakt</td><td>$font $kontaktes</td></tr>\n";
if ($email || $web) print "<tr>"; 
if ($email) print "<tr><td>$font mail $email</td>";
if ($web) print "<td width=\"50%\">$font web $web</td>";
if ($email || $web) print "</tr>\n"; 
print "</tbody></table></td></tr>";
print "<tr><td colspan=6><hr></td></tr>";
print "<tr><td><table><tbody>";
if ($historik_id) {
	$r=db_fetch_array(db_select("select * from historik where id = '$historik_id'"));
	$notat=stripslashes($r['notat']);
	$kontaktet=dkdato($r['kontaktet']);
	$kontaktes=dkdato($r['kontaktes']);
	$ansat_id=$r[ansat_id]*1;
	$kontakt_id=$r[kontakt_id]*1;
	$r = db_fetch_array(db_select("select id, navn from ansatte where id = $ansat_id"));
	$ansat=$r['navn'];
	$r = db_fetch_array(db_select("select id, navn from ansatte where id = $kontakt_id"));
	$kontakt=$r['navn'];
} else {$notat=''; $kontaktet=''; $kontaktes=''; $kontakt_id='';}
$ansat_id=$ansat_id*1;
if ($ansat_id) {
	$r=db_fetch_array(db_select("select navn from ansatte where id = $ansat_id and lukket != 'on'"));
	$ansat_navn=$r['navn'];
} else $ansat_navn='';
$r = db_fetch_array(db_select("select id from adresser where art='S'"));
print "<input type=hidden name=egen_id value=$r[id]>";
print "<tr><td colspan =\"2\"><SELECT NAME=ansat value=\"$ansat\">";
if ($ansat_navn) print "<option>$ansat_navn</option>";
$q = db_select("select id, navn from ansatte where konto_id = $r[id] and lukket != 'on' and id != $ansat_id");
while ($r = db_fetch_array($q)){
	print "<option>$r[navn]</option>";
}
print "</SELECT></td></tr>\n";
print "<tr><td colspan =\"2\">$font har talt med</td></tr>\n";
print "<tr><td colspan =\"2\"><SELECT NAME=kontakt value=\"$kontakt\">";
$q = db_select("select id, navn from ansatte where konto_id = $id");
while ($r = db_fetch_array($q)){
	print "<option>$r[navn]</option>";
}
print "</SELECT></td></tr>\n";
if (!$kontaktet) $kontaktet=date("d-m-Y");
print "<tr><td>$font den</td><td><input type=text size=11 name=kontaktet value=$kontaktet></td></tr>\n";
print "<tr><td>$font Kontaktes igen</td>";
print "<td><input type=text size=11 name=kontaktes value=$kontaktes></td></tr>\n";
print "</td></tbody></table></td>";
print "<td colspan=4><textarea name=\"note\" rows=\"7\" cols=\"100\">$notat</textarea></td></tr>\n";
if ($historik_id) {
	print "<input type=hidden name=historik_id value=$historik_id>";
	print "<tr><td align = center><input type=submit accesskey=\"g\" value=\"Opdater\" name=\"submit\"></td></tr>\n";
} else {
	print "<tr><td align = center><input type=submit accesskey=\"g\" value=\"Gem\" name=\"submit\"></td></tr>\n";
}
print "</form>";
$q = db_select("select * from historik where konto_id = $id order by kontaktet desc");
print "<tr><td colspan=6><hr></td></tr>";
while ($r = db_fetch_array($q)){
	$ansat_id=$r[ansat_id]*1;
	$notedato=dkdato($r[notedate]);
	$kontaktet=dkdato($r[kontaktet]);
	if ($r[kontaktes]) $kontaktes=dkdato($r[kontaktes]);
	else $kontaktes='';
	$r1 = db_fetch_array(db_select("select navn from ansatte where id = $ansat_id"));
	$notat=str_replace(" ","&nbsp;",htmlentities(stripslashes($r['notat'])));
	$notat=str_replace("\n","<br>",$notat);

	print "<tr><td>$font $r1[navn]<br>$kontaktet<br>$kontaktes<br>";
#	if ($r[notedate]==date("Y-m-d")) 
	print "<a href=historikkort.php?id=$id&historik_id=$r[id]&handling=ret>&nbsp;&nbsp;ret&nbsp;&nbsp;</a>&nbsp;&nbsp;&nbsp;<a href=historikkort.php?id=$id&historik_id=$r[id]&handling=slet onClick=\"return Slet()\">&nbsp;slet&nbsp;</a>";
	print "</td><td colspan=4>$font $notat</td></tr>";
	print "<tr><td colspan=6><hr></td></tr>";
}
?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
