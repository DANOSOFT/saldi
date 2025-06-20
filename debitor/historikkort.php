<?php
// -- debitor/historikkort.php -------------lap 3.2.2--2011-07-03--
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2011 DANOSOFT ApS
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
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";

$id = $_GET['id']*1;
if ($_GET['konto_id']) $id = $_GET['konto_id'];
if ($_GET['historik_id']) $historik_id=$_GET['historik_id'];
$handling=$_GET['handling'];

if ($handling=='slet') {
	db_modify("delete from historik where id = $historik_id",__FILE__ . " linje " . __LINE__);
	$historik_id='';
}
 if (isset($_GET['returside'])) {
 	$returside= $_GET['returside'];
 	$ordre_id = $_GET['ordre_id'];
 	$fokus = $_GET['fokus'];
} else {
	if ($popup) $returside="../includes/luk.php";
	else $returside="historik.php";
}
$luk=if_isset($_GET['luk']);

if($luk) {
	if ($r=db_fetch_array(db_select("select * from navigator where bruger_id='$bruger_id' and session_id='$s_id' and side='historikkort.php'",__FILE__ . " linje " . __LINE__))) {
		db_modify("delete from navigator where bruger_id='$bruger_id' and session_id='$s_id' and side='historikkort.php'",__FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$r[returside]?valg=historik&konto_id=$r[konto_id]&ordre_id=$r[ordre_id]\">";
	} else print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorliste.php\">";
	exit;
} elseif ($returside) db_modify("insert into navigator(bruger_id,session_id,side,returside,konto_id) values ('$bruger_id','$s_id','historikkort.php','$returside','$id')",__FILE__ . " linje " . __LINE__);


if ($_POST['submit']){
	$submit=trim($_POST['submit']);
	$id=$_POST['id'];
	$egen_id=$_POST['egen_id'];
	$historik_id=$_POST['historik_id'];
	$ansat=$_POST['ansat'];
	$kontakt=$_POST['kontakt'];
	if ($_POST['oprettet']) $oprettet=$_POST['oprettet'];
	if ($_POST['kontaktet'])$kontaktet=usdate($_POST['kontaktet']);
	else $kontaktet=date("Y-m-d");
	$kontaktes=$_POST['kontaktes'];
	if ($kontaktes) $kontaktes=usdate($kontaktes);
	else $kontaktes=NULL;
	$notat=addslashes(trim($_POST['note']));

	if ($kontaktes || $notat) {
		$r = db_fetch_array(db_select("select id from ansatte where konto_id = '$egen_id' and navn = '$ansat'",__FILE__ . " linje " . __LINE__));
		$ansat_id=$r['id']*1;
		$r = db_fetch_array(db_select("select id from ansatte where konto_id = '$id' and navn = '$kontakt'",__FILE__ . " linje " . __LINE__));
		$kontakt_id=$r['id']*1;
		if ($historik_id) {
			db_modify("update historik set kontakt_id = $kontakt_id, ansat_id = $ansat_id, notat = '$notat', kontaktet = '$kontaktet' where id = $historik_id",__FILE__ . " linje " . __LINE__);
		} else {
			$notedate=date("Y-m-d");
			$r = db_fetch_array(db_select("select id from ansatte where konto_id = '$egen_id' and navn = '$ansat'",__FILE__ . " linje " . __LINE__));
			$ansat_id=$r[id]*1;
			$r = db_fetch_array(db_select("select id from ansatte where konto_id = '$id' and navn = '$kontakt'"));
			$kontakt_id=$r[id]*1;
			if ($kontaktes) db_modify("insert into historik (konto_id, kontakt_id, ansat_id, notat, notedate, kontaktet, kontaktes) values ($id , $kontakt_id, $ansat_id, '$notat', '$notedate', '$kontaktet', '$kontaktes')",__FILE__ . " linje " . __LINE__);
			else db_modify("insert into historik (konto_id, kontakt_id, ansat_id, notat, notedate, kontaktet) values ('$id' , '$kontakt_id', '$ansat_id', '$notat', '$notedate', '$kontaktet')",__FILE__ . " linje " . __LINE__);
		}
		if ($kontaktes) {
			db_modify("update adresser set kontaktet = '$kontaktet', kontaktes = '$kontaktes' where id = $id",__FILE__ . " linje " . __LINE__);
			if ($historik_id) db_modify("update historik set kontaktes = '$kontaktes' where id = $historik_id",__FILE__ . " linje " . __LINE__);
		} else db_modify("update adresser set kontaktet = '$kontaktet' where id = $id",__FILE__ . " linje " . __LINE__);
	}
	$historik_id=0;
}
############################
if (!$id) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
if (strstr($returside,'historikkort.php')) $returside="historik.php";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n"; #tabel1 start
print "<tr><td align=\"center\" valign=\"top\" height=\"1%\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"4\" cellpadding=\"0\"><tbody>\n";#tabel2a start
$tekst=findtekst(154,$sprog_id);
#if ($returside=="debitorkort.php") print "<td width=\"10%\" align=center><div class=\"top_bund\"><a href=\"javascript:confirmClose('$returside?id=$id&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L>Luk</a></div></td>\n";
#print "<td width=\"10%\" align=center><div class=\"top_bund\"><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L>Luk</a></div></td>\n";
print "<td width=\"10%\" align=center><div class=\"top_bund\"><a href=\"javascript:confirmClose('historikkort.php?luk=luk.php')\" accesskey=L>Luk</a></div></td>\n";
print "<td width=\"80%\" align=center><div class=\"top_bund\">Historik for debitor</div></td>\n";
print "<td width=\"10%\" align=center><div class=\"top_bund\"><a href=\"javascript:confirmClose('debitorkort.php?returside=historikkort.php&id=$id&ordre_id=$ordre_id&fokus=$fokus','$tekst')\" accesskey=N>Ny</a><br></div></td>\n";
print "</tbody></table>\n";#tabel2a slut
print "</td></tr>\n";
print "<tr><td height=\"99%\"  width=\"100%\" valign=\"top\">";
print "<table width=\"100%\" cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";#tabel2b start

if ($id > 0){
	$q = db_select("select * from adresser where id = '$id'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$kontonr=trim($r['kontonr']);
	$firmanavn=htmlentities(trim($r['firmanavn']),ENT_COMPAT,$charset);
	$addr1=htmlentities(trim($r['addr1']),ENT_COMPAT,$charset);
	$addr2=htmlentities(trim($r['addr2']),ENT_COMPAT,$charset);
	$postnr=trim($r['postnr']);
	$bynavn=htmlentities(trim($r['bynavn']),ENT_COMPAT,$charset);
	$land=htmlentities(trim($r['land']),ENT_COMPAT,$charset);
	$tlf=trim($r['tlf']);
	$fax=trim($r['fax']);
	$email=trim($r['email']);
	$web=trim($r['web']);
	$notes=htmlentities(trim($r['notes']),ENT_COMPAT,$charset);
	if ($r['oprettet']) $oprettet=dkdato($r['oprettet']);
	if ($r['kontaktet']) $kontaktet=dkdato($r['kontaktet']);
	if ($r['kontaktes']) $kontaktes=dkdato($r['kontaktes']);
}

if (db_fetch_array(db_select("select * from grupper where ART = 'FTP' and box1 !='' and box2 !='' and box3 !=''",__FILE__ . " linje " . __LINE__))) $vis_bilag=1;
print "<form name='historikkort' action='historikkort.php?returside=$returside' method='post'>";
print "<input type='hidden' name=\"id\" value='$id'>";
print "<tr><td colspan='6'>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"800\"><tbody>";#tabel3a start;
print "<tr><td title=\"$notes\"><a href=debitorkort.php?id=$id&returside=historikkort.php>$firmanavn</a></td><td></td><td></td><td> Oprettet</td><td>";
#print "<tr><td>$firmanavn</td><td></td><td></td><td> Oprettet</td><td>";
if ($oprettet) print " $oprettet";
else print " <input type=text name=oprettet size=11 onchange=\"javascript:docChange = true;\">";
print "</td></tr>\n";
print "<tr><td> $addr1</td><td> $addr2</td></tr>\n";
print "<tr><td> $postnr $bynavn</td><td> $land</td><td></td><td> Seneste kontakt</td><td> $kontaktet</td></tr>\n";
print "<tr><td> Tlf: $tlf</td><td> ";
if ($fax) print "Fax: $fax";
print "</td><td></td><td> N&aelig;ste kontakt</td><td> $kontaktes</td>\n";
	if (db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '2' and box7='on'",__FILE__ . " linje " . __LINE__))) {
		$url="jobliste.php?kontonr=$kontonr&konto_id=$id&returside=historikkort.php";
		$jobkort="<a href=$url><input type=\"button\" style=\"width:75px\" value=\"jobkort\" onClick=\"window.navigate('$url')\"></a>";
		print "<td>$jobkort</a></td></tr>";
	} else print "<tr>";


if ($email || $web) print "<tr>"; 
if ($email) print "<tr><td> mail $email</td>";
if ($web) print "<td width=\"50%\"> web $web</td>";
if ($email || $web) print "</tr>\n"; 
print "</tbody></table></td></tr>";#tabel3a slut;
print "<tr><td colspan=6><hr></td></tr>";
print "<tr><td width=10%><table border=0 width=100%><tbody>";#tabel3b start;
if ($historik_id) {
	$r=db_fetch_array(db_select("select * from historik where id = '$historik_id'",__FILE__ . " linje " . __LINE__));
	$notat=($r['notat']);
	$kontaktet=dkdato($r['kontaktet']);
	if ($r['kontaktes']) $kontaktes=dkdato($r['kontaktes']);
	else $kontaktes=NULL;
	$ansat_id=$r[ansat_id]*1;
	$kontakt_id=$r[kontakt_id]*1;
	$r = db_fetch_array(db_select("select id, navn from ansatte where id = $ansat_id",__FILE__ . " linje " . __LINE__));
	$ansat=$r['navn'];
	$r = db_fetch_array(db_select("select id, navn from ansatte where id = $kontakt_id",__FILE__ . " linje " . __LINE__));
	$kontakt=$r['navn'];
} else {$notat=''; $kontaktet=''; $kontaktes=''; $kontakt_id='';}
$ansat_id=$ansat_id*1;
if ($ansat_id) {
	$r=db_fetch_array(db_select("select navn from ansatte where id = $ansat_id and lukket != 'on'",__FILE__ . " linje " . __LINE__));
	$ansat_navn=$r['navn'];
} else $ansat_navn='';
$r = db_fetch_array(db_select("select id from adresser where art='S'",__FILE__ . " linje " . __LINE__));
$egen_id=$r['id']*1;
print "<input type=hidden name=egen_id value=$egen_id>";
print "<tr><td colspan =\"2\"><select name='ansat' value=\"$ansat\">";
if ($ansat_navn) print "<option>$ansat_navn</option>";
$q = db_select("select id, navn from ansatte where konto_id = $egen_id and lukket != 'on' and id != $ansat_id",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)){
	print "<option>$r[navn]</option>";
}
print "</SELECT></td></tr>\n";
print "<tr><td colspan =\"2\"> har talt med</td></tr>\n";
print "<tr><td colspan =\"2\"><SELECT NAME=kontakt value=\"$kontakt\">";
$q = db_select("select id, navn, tlf, mobil, email, notes from ansatte where konto_id = $id",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)){
	print "<option title=\"D: $r[tlf] M: $r[mobil] E: $r[email] B: $r[notes]\">$r[navn]</option>\n";
}
print "<option></option>";

print "</SELECT></td></tr>\n";
if (!$kontaktet) $kontaktet=date("d-m-Y");
print "<tr><td>den</td><td><input type=text size=11 name=kontaktet value=\"$kontaktet\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
print "<tr><td> Kontaktes igen</td>";
print "<td><input type=text size=11 name=kontaktes value=\"$kontaktes\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
if ($historik_id) {
	print "<input type=hidden name=historik_id value=$historik_id>";
	print "<td align=right><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td></tr>\n";
} else {
	print "<td colspan=2 align=right><input type=submit accesskey=\"g\" value=\"Gem\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td></tr>\n";
}
print "</td></tbody></table></td>";#tabel3b slut;
print "<td colspan=4><textarea name=\"note\" rows=\"10\" cols=\"100\" onchange=\"javascript:docChange = true;\">$notat</textarea></td></tr>\n";
print "</form>";
$q = db_select("select * from historik where konto_id = $id order by kontaktet desc, id desc",__FILE__ . " linje " . __LINE__);
print "<tr><td colspan=6><hr></td></tr>";
while ($r=db_fetch_array($q)){
	$ansat_id=$r['ansat_id']*1;
	$kontakt_id=$r['kontakt_id']*1;
	$ansat=str_replace(" ","&nbsp;",$r1['navn']);
	$notedato=dkdato($r['notedate']);
	$kontaktet=dkdato($r['kontaktet']);
	if ($r['kontaktes']) $kontaktes=dkdato($r['kontaktes']);
	else $kontaktes='';
	$r1 = db_fetch_array(db_select("select navn from ansatte where id = $ansat_id",__FILE__ . " linje " . __LINE__));
	$ansat=str_replace(" ","&nbsp;",$r1['navn']);
	$r1 = db_fetch_array(db_select("select navn, tlf, mobil, email, notes from ansatte where id = $kontakt_id",__FILE__ . " linje " . __LINE__));
	$kontakt=str_replace(" ","&nbsp;",$r1['navn']);
	$notat=str_replace("  ","&nbsp;&nbsp;",htmlentities($r['notat'],ENT_COMPAT,$charset));
	$notat=str_replace("\n","<br>",$notat);
	$dokument=$r['dokument'];
	print "<tr><td><table border=0 width=100%><tbody>";
	print "<tr><td colspan=2 width=100% >$ansat&nbsp;=&gt;<span title=\"D: $r1[tlf] M: $r1[mobil] E: $r1[email] B: $r1[notes]\">&nbsp;$kontakt</span></td></tr>";
	print "<tr><td colspan=2>$kontaktet &nbsp; $kontaktes</td></tr>";
#	if ($r[notedate]==date("Y-m-d")) 
	print "<tr><td><a href=historikkort.php?id=$id&historik_id=$r[id]&handling=ret>&nbsp;&nbsp;ret&nbsp;&nbsp;</a>&nbsp;&nbsp;&nbsp;<a href=historikkort.php?id=$id&historik_id=$r[id]&handling=slet onClick=\"return Slet()\">&nbsp;slet&nbsp;</a></td>";
	if ($vis_bilag) {
		if ($dokument) print "<td title=\"klik her for at &aring;bne bilaget: $dokument\" align=right><a href=\"../includes/bilag.php?kilde=historik&filnavn=$dokument&kilde_id=$id&bilag_id=$r[id]\"><img style=\"border: 0px solid\" alt=\"clip med papir\" src=\"../ikoner/paper.png\"></a></td>";
		else print "<td title=\"klik her for at vedh&aelig;fte et bilag\" align=right><a href=\"../includes/bilag.php?kilde=historik&&ny=ja&kilde_id=$id&bilag_id=$r[id]\"><img style=\"border: 0px solid\" alt=\"papirclip\" src=\"../ikoner/clip.png\"></a></td>";
	} 
	print "</tbody></table>";

	print "</td><td style='vertical-align:top' width=90%>$notat</td></tr>";
	print "<tr><td colspan=6><hr></td></tr>";
}
?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
