<?php
// ---------------debitor/ordreliste.php---lap 2.0.7------2009-05-17-----
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
ob_start();
@session_start();
$s_id=session_id();

$check_all=NULL; $ny_sort=NULL;

print "
<script LANGUAGE=\"JavaScript\">
<!--
function MasseFakt()
{
	var agree = confirm(\"Faktur&eacute;r alt som kan leveres?\");
	if (agree)
		return true ;
	else
    return false ;
}
// -->
</script>
";
$css="../css/standard.css";
$modulnr=5;
$title="Ordreliste - Debitorer";
$dk_dg=NULL; $vis_projekt=NULL;
$firmanavn=NULL; $firmanavn_ant=NULL; $hurtigfakt=NULL; $konto_id=NULL; $linjebg=NULL; $checked=NULL; $totalkost=NULL; $understreg=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");
	
print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Ordreliste - Kunder</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";


$ordrenumre = if_isset($_GET['ordrenumre']);
$kontonumre = if_isset($_GET['kontonumre']);
$fakturanumre = if_isset($_GET['fakturanumre']);
$ordredatoer = if_isset($_GET['ordredatoer']);
$lev_datoer = if_isset($_GET['lev_datoer']);
$fakturadatoer = if_isset($_GET['fakturadatoer']);
$genfaktdatoer = if_isset($_GET['genfaktdatoer']);
$summer = if_isset($_GET['summer']);
$firma = if_isset($_GET['firma']);
$ref[0] = if_isset($_GET['ref']);
$projekt[0] = if_isset($_GET['projekt']);
$valg= if_isset($_GET['valg']);
$sort = if_isset($_GET['sort']);
$nysort = if_isset($_GET['nysort']);
$kontoid= if_isset($_GET['kontoid']);
$genberegn= if_isset($_GET['genberegn']);

if ($r=db_fetch_array(db_select("select id from adresser where art = 'S' and pbs_nr > '0'",__FILE__ . " linje " . __LINE__))) {
 $pbs=1;
} else $pbs=0;
$id = $_GET['id'];
if($_GET['returside']){
 	$returside= $_GET['returside'];
	if ($r=db_fetch_array(db_select("select id from grupper where art = 'OLV' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__))) {
		db_modify("update grupper set box2='$returside' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
	} else db_modify("insert into grupper (beskrivelse,kode,kodenr,art,box2) values ('Ordrelistevisning','$brugernavn','$bruger_id','OLV','$returside')",__FILE__ . " linje " . __LINE__);
} else {
	$r=db_fetch_array(db_select("select box2 from grupper where art = 'OLV' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__)); 
	$returside=$r['box2'];
}
if (!$returside) {
	if ($popup) $returside= "../includes/luk.php";
	else $returside= "../index/menu.php";
}
$tidspkt=date("U");
 
#if (isset($_POST)) {
if ($submit=if_isset($_POST['submit'])) {
	if (strstr($submit, "Genfaktur")) $submit="Genfakturer";
	$ordrenumre = stripslashes(if_isset($_POST['ordrenumre']));
	$kontonumre = stripslashes(if_isset($_POST['kontonumre']));
	$fakturanumre = stripslashes(if_isset($_POST['fakturanumre']));
	$ordredatoer = stripslashes(if_isset($_POST['ordredatoer']));
	$lev_datoer = stripslashes(if_isset($_POST['lev_datoer']));
	$fakturadatoer = stripslashes(if_isset($_POST['fakturadatoer']));
	$genfaktdatoer = stripslashes(if_isset($_POST['genfaktdatoer']));
	$summer = stripslashes(if_isset($_POST['summer']));
	$firma = stripslashes(if_isset($_POST['firma']));
	$ref[0] = stripslashes(if_isset($_POST['ref']));
	$projekt[0] = stripslashes(if_isset($_POST['projekt']));
	$valg=if_isset($_POST['valg']);
	$sort = if_isset($_POST['sort']);
	$nysort = if_isset($_POST['nysort']);
	$firma=if_isset($_POST['firma']);
	$kontoid=if_isset($_POST['kontoid']);
	$firmanavn_ant=if_isset($_POST['firmanavn_antal']);
}

if (($firma)&&($firmanavn_ant>0)) {
	for ($x=1; $x<=$firmanavn_ant; $x++) {
		$tmp="firmanavn$x";
		if ($firma==$_POST[$tmp]) {
			$tmp="konto_id$x";
			$kontoid=$_POST[$tmp];
		}
	}
}
elseif ($firmanavn_ant>0) {$kontoid='';}
if ($valg) {
	$cookievalue="$ordrenumre;$kontonumre;$fakturanumre;$ordredatoer;$lev_datoer;$fakturadatoer;$genfaktdatoer;$summer;$firma;$kontoid;$ref[0];$sort;$valg;$nysort";
	setcookie("deb_ord_lst", $cookievalue);
}
else {
	list ($ordrenumre, $kontonumre, $fakturanumre, $ordredatoer, $lev_datoer, $fakturadatoer, $genfaktdatoer, $summer, $firma, $kontoid, $ref[0], $sort, $valg, $nysort) = split(";", $_COOKIE['deb_ord_lst']);#
}
ob_end_flush();	//Sender det "bufferede" output afsted...
		
if (!$valg) $valg = "ordrer";
if (!$sort) $sort = "firmanavn";
if ($valg=="ordrer" && $sort=="fakturanr") $sort="ordrenr";
elseif ($nysort==$sort) $sort=$sort." desc";
elseif ($nysort) $sort=$nysort;
$sort=str_replace("ordrer.","",$sort); #2008.02.05

if ($valg!='faktura') {
	$fakturanumre='';
	$fakturadatoer='';
	$genfaktdatoer='';
}
if ($valg=="tilbud") {$status="status = 0";}
elseif ($valg=="faktura") {$status="status >= 3";}
else {$status="(status = 1 or status = 2)";}

if (db_fetch_array(db_select("select distinct id from ordrer where projekt > '0' and $status",__FILE__ . " linje " . __LINE__))) $vis_projekt='on';
if (db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '3' and box4='on'",__FILE__ . " linje " . __LINE__))) $hurtigfakt='on';


$hreftext="&ordrenumre=$ordrenumre&kontonumre=$kontonumre&fakturanumre=$fakturanumre&ordredatoer=$ordredatoer&lev_datoer=$lev_datoer&fakturadatoer=$fakturadatoer&genfaktdatoer=$genfaktdatoer&summer=$summer&ref=$ref[0]&kontoid=$kontoid";
if ($valg!="faktura") print "<meta http-equiv=\"refresh\" content=\"60;URL='ordreliste.php?sort=$sort&valg=$valg$hreftext'\">";
 
 
if ($submit=="Udskriv"){
	$ordre_antal = if_isset($_POST['ordre_antal']);
	$ordre_id = if_isset($_POST['ordre_id']);
	$checked = if_isset($_POST['checked']);
	
	for ($x=1; $x<=$ordre_antal; $x++){
		if ($checked[$x]=="on") {
			$y++;
			if (!$udskriv) $udskriv=$ordre_id[$x];
			else $udskriv=$udskriv.",".$ordre_id[$x];
		}
	}
	if ($y>0) {
		print "<BODY onLoad=\"JavaScript:window.open('formularprint.php?id=-1&ordre_antal=$y&skriv=$udskriv&formular=4' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
	}
	else print "<BODY onLoad=\"javascript:alert('Ingen fakturaer er markeret til udskrivning!')\">";
}
if (isset($_POST['check'])||isset($_POST['uncheck'])) {
	$ordre_antal = if_isset($_POST['ordre_antal']);
	$ordre_id = if_isset($_POST['ordre_id']);
	if (isset($_POST['check'])) $check_all='on';
}

if ($submit=="Genfakturer"){
	$ordre_antal = if_isset($_POST['ordre_antal']);
	$ordre_id = if_isset($_POST['ordre_id']);
	$checked = if_isset($_POST['checked']);

	for ($x=1; $x<=$ordre_antal; $x++){
		if ($checked[$x]=="on") {
			$y++;
			if (!$genfakt) $genfakt=$ordre_id[$x];
			else $genfakt=$genfakt.",".$ordre_id[$x];
		}
	}
	if ($y>0) {
		print "<BODY onLoad=\"JavaScript:window.open('genfakturer.php?id=-1&ordre_antal=$y&genfakt=$genfakt' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
	}
	else print "<BODY onLoad=\"javascript:alert('Ingen fakturaer er markeret til genfakturering!')\">";
}
print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>";
print "<tr><td height = 25 align=center valign=top>";
print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><td width=10% $top_bund>";
#if ($popup) print "<a href=../includes/luk.php accesskey=L>Luk</a></td>";
#else print "<a href=\"../index/menu.php\" accesskey=\"L\">Luk</a></td>";
print "<a href=$returside accesskey=L>Luk</a></td>";
print "<td width=80% $top_bund align=center><table border=0 cellspacing=2 cellpadding=0><tbody>";
if ($valg=='tilbud'&&!$hurtigfakt) {print "<td width = 20% align=center $knap_ind><a href='ordreliste.php?sort=$sort&valg=tilbud$hreftext'>&nbsp;Tilbud&nbsp;</a></td>";}
elseif (!$hurtigfakt) {print "<td width = 20% align=center><a href='ordreliste.php?sort=$sort&valg=tilbud$hreftext'>&nbsp;Tilbud&nbsp;</a></td>";}
if ($valg=='ordrer') {print "<td width = 20% align=center $knap_ind><a href='ordreliste.php?sort=$sort&valg=ordrer$hreftext'>&nbsp;Ordrer&nbsp;</a></td>";}
else {print "<td width = 20% align=center><a href='ordreliste.php?sort=$sort&valg=ordrer$hreftext'>&nbsp;Ordrer&nbsp;</a></td>";}
if ($valg=='faktura') print "<td width = 20% align=center $knap_ind><a href='ordreliste.php?sort=$sort&valg=faktura$hreftext'>&nbsp;Faktura&nbsp;</a></td>";
else print "<td width = 20% align=center><a href='ordreliste.php?sort=$sort&valg=faktura$hreftext'>&nbsp;Faktura&nbsp;</a></td>";
if ($valg=='pbs') print "<td width = 20% align=center $knap_ind><a href='ordreliste.php?sort=$sort&valg=pbs$hreftext'>&nbsp;PBS&nbsp;</a></td>";
elseif ($pbs) print "<td width = 20% align=center><a href='ordreliste.php?sort=$sort&valg=pbs$hreftext'>&nbsp;PBS&nbsp;</a></td>";

print "</tbody></table></td>";
if ($valg=='pbs') {
	if ($popup) print "<td width=10% $top_bund onClick=\"javascript:ordre=window.open('pbs_import.php?returside=ordreliste.php','ordre','scrollbars=1,resizable=1');ordre.focus();\"><a accesskey=N href=ordreliste.php?sort=$sort>Import PBS</a></td>";
	else  print "<td width=10% $top_bund><a href=pbs_import.php?returside=ordreliste.php>Import PBS</a></td>";
} else {
	if ($popup) print "<td width=10% $top_bund onClick=\"javascript:ordre=window.open('ordre.php?returside=ordreliste.php','ordre','scrollbars=1,resizable=1');ordre.focus();\"><a accesskey=N href=ordreliste.php?sort=$sort>Ny</a></td>";
	else  print "<td width=10%  $top_bund><a href=ordre.php?returside=ordreliste.php>Ny</a></td>";
}
print "</td></tr>\n";
#print "<tr><td></td><td align=center><table border=1	cellspacing=0 cellpadding=0><tbody>";
#print "<td width = 20%$top_bund align=center><a href=ordreliste.php?valg=tilbud accesskey=T>Tilbud</a></td>";
#print "<td width = 20% bgcolor=$bgcolor5 align=center> Ordrer</td>";
#print "<td width = 20% bgcolor=$bgcolor5 align=center> Faktura</td>";
#print "</tbody></table></td><td></td</tr>\n";

print "</tbody></table>";
print " </td></tr>\n<tr><td align=center valign=top>";

if ($valg=='pbs') {
	include("pbsliste.php");
	exit;
}

print "<table cellpadding=1 cellspacing=1 border=0 width=100% valign = top><tbody>";
print "	<tr>";
print "<td align=right><b><a href='ordreliste.php?nysort=ordrenr&sort=$sort&valg=$valg$hreftext'>Ordrenr.</b></td>";
if ($valg=='faktura') {print " <td align=right width=50><b><a href='ordreliste.php?nysort=fakturanr&sort=$sort&valg=$valg$hreftext'>Faktnr.</b></td>";}
 print "	<td width=50></td>";
if ($valg=='tilbud') {print "<td><b><a href='ordreliste.php?nysort=ordredate&sort=$sort&valg=$valg$hreftext'>Tilbudsdato</b></td>";}
else {
	print "<td><b><a href='ordreliste.php?nysort=ordredate&sort=$sort&valg=$valg$hreftext'>Ordredato</b></td>";
	print "<td><b><a href='ordreliste.php?nysort=levdate&sort=$sort&valg=$valg$hreftext'>Levdato</b></td>";
}
if ($valg=='faktura') {
	print "<td><b><a href='ordreliste.php?nysort=fakturadate&sort=$sort&valg=$valg$hreftext'>Fakt.dato</b></td>";
	print "<td title='Dato for genfakturerering ved f.eks. abonnementskunder'><b><a href='ordreliste.php?nysort=nextfakt&sort=$sort&valg=$valg$hreftext'>Genfakt</b></td>";
}
print "<td><b><a href='ordreliste.php?nysort=kontonr&sort=$sort&valg=$valg$hreftext'>kontonr</b></td>";
print "<td><b><a href='ordreliste.php?nysort=firmanavn&sort=$sort&valg=$valg$hreftext'>Firmanavn</a></b></td>";
print "<td><b> S&aelig;lger</a></b></td>";
if ($vis_projekt) print "<td><b> Projektnr.</a></b></td>";
if ($valg=='tilbud') {print "<td align=right><b><a href='ordreliste.php?nysort=sum&sort=$sort&valg=$valg$hreftext'>Tilbudssum</a></b></td>";}
elseif ($valg=='ordrer'){print "<td align=right><b><a href='ordreliste.php?nysort=sum&sort=$sort&valg=$valg$hreftext'>Ordresum</a></b></td>";}
else {
	print "<td align=right><b><a href='ordreliste.php?nysort=sum&sort=$sort&valg=$valg$hreftext'>Fakturasum";
	if ($genberegn) print "/db";
	print "</a></b></td>";
}
print "</tr>\n";

#################################### Sogefelter ##########################################

print "<form name=ordreliste action=ordreliste.php method=post>";
print "<input type=hidden name=valg value=$valg>";
print "<input type=hidden name=sort value='$ny_sort'>";
print "<input type=hidden name=nysort value='$sort'>";
print "<input type=hidden name=kontoid value=$kontoid>";
print "<tr>";
print "<td align=right><span title= 'Angiv et ordrenummer eller angiv to adskilt af kolon (f.eks 345:350)'><input type=text size=5 name=ordrenumre value=$ordrenumre></td>";
if ($valg=='faktura') print "<td align=right><span title= 'Angiv et fakturanummer eller angiv to adskilt af kolon (f.eks 345:350)'><input type=text size=5 name=fakturanumre value=$fakturanumre></td>";
 print "<td width=50></td>";
print "<td><span title= 'Angiv en dato eller angiv to adskilt af kolon (f.eks 010605:300605)'><input type=text size=10 name=ordredatoer value=$ordredatoer></td>";
if ($valg!='tilbud') {print "<td><span title= 'Angiv en dato eller angiv to adskilt af kolon (f.eks 010605:300605)'><input type=text size=10 name=lev_datoer value=$lev_datoer></td>";}
if ($valg=='faktura') {
	print "<td><span title= 'Angiv en dato eller angiv to adskilt af kolon (f.eks 010605:300605)'><input type=text size=10 name=fakturadatoer value=$fakturadatoer></td>";
	print "<td><span title= 'Angiv en dato eller angiv to adskilt af kolon (f.eks 010605:300605)'><input type=text size=10 name=genfaktdatoer value=$genfaktdatoer></td>";
}
print "<td><span title= 'Angiv et kontonr. eller angiv to adskilt af kolon (f.eks 43000000:43999999)'><input type=text size=10 name=kontonumre value=$kontonumre></td>";
$x=0;
if (!$konto_id) {$konto_id=array();}
if (($kontoid)&&(!$firma)){
	$row = db_fetch_array(db_select("select firmanavn from adresser where id = $kontoid",__FILE__ . " linje " . __LINE__));
	$firma=stripslashes($row['firmanavn']);
}
$query = db_select("select konto_id from ordrer where (art = 'DK' or art = 'DO') and $status order by firmanavn",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	 if (!in_array($row['konto_id'], $konto_id)) {
		 $x++;
		 $konto_id[$x]=$row['konto_id'];
		 $r2 = db_fetch_array(db_select("select firmanavn from adresser where id = $konto_id[$x]",__FILE__ . " linje " . __LINE__));
		 $firmanavn[$x]=stripslashes($r2['firmanavn']);
		 if (strlen($firmanavn[$x])>35){$firmanavn[$x]=substr($firmanavn[$x],0,30)."...";}
		 print "<input type=hidden name=firmanavn$x value='$firmanavn[$x]'>";
		 print "<input type=hidden name=konto_id$x value=$konto_id[$x]>";
	 } 
}
$firmanavn_antal=$x;	
print "<input type=hidden name=firmanavn_antal value=$firmanavn_antal>";
 
print "<td><span title= 'V&aelig;lg et firma'><SELECT NAME=firma value=$firma>";
print "<option>$firma</option>";
print "<option>$nbsp</option>";
for ($x=1;$x<=$firmanavn_antal; $x++) {
	print "<option>$firmanavn[$x]</option>";
}
print "</SELECT></td>";


$x=0;
if (!$ref) {$ref=array();}
$query = db_select("select ref from ordrer where art='DO' order by ref",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	 if (!in_array($row['ref'], $ref)) {
		 $x++;
		 $ref[$x]=$row['ref'];
	 } 
}

$refantal=$x;	
print "<td><span title= 'V&aelig;lg en referanceperson'><SELECT NAME=ref value=$ref[0]>";
print "<option>$ref[0]</option>";
for ($x=1;$x<=$refantal; $x++) {print "<option>$ref[$x]</option>";}
if ($ref[0]!=$ref[$x]) {print "<option>$ref[$x]</option>";}
if ($ref[0]) {print "</SELECT></td>";}

if ($vis_projekt) {
	$x=0;
	if (!$projekt) {$projekt=array();}
	print "<td><span title= 'V&aelig;lg et projektnr'><SELECT NAME=projekt value=$projekt[0]>";
	$q = db_select("select kodenr, beskrivelse from grupper where art='PRJ' order by box2",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		if ($projekt[0]!=$r['kodenr']) print "<option title='$r[beskrivelse]'>$r[kodenr]</option>";
		else print "<option selected='selected' title='$r[beskrivelse]'>$r[kodenr]</option>";
	}
	if (!$projekt[0]) print "<option selected='selected'></option>";
	else print "<option></option>";
}
print "<td align=right><span title= 'Angiv et bel&oslash;b eller angiv to adskilt af kolon (f.eks 10000,00:14999,99)'><input type=text size=10 name=summer value=$summer></td>";
print "<td><input type=submit value=\"OK\" name=\"submit\"></td>";
print "</form></tr>\n";
####################################################################################
$udvaelg=NULL;
if ($ordrenumre) {
	$udvaelg=$udvaelg.udvaelg($ordrenumre, 'ordrer.ordrenr', 'NR');
}
if ($fakturanumre) {
	$udvaelg=$udvaelg.=udvaelg($fakturanumre, 'ordrer.fakturanr', 'NR');
}

if ($kontonumre) {
	$udvaelg=$udvaelg.=udvaelg($kontonumre, 'ordrer.kontonr', 'NR');
}
if ($ordredatoer) {
	$udvaelg=$udvaelg.udvaelg($ordredatoer, 'ordrer.ordredate', 'DATO');
}
if ($lev_datoer) {
	$udvaelg=$udvaelg.udvaelg($lev_datoer, 'ordrer.levdate', 'DATO');
}
if ($fakturadatoer){
	$udvaelg=$udvaelg.udvaelg($fakturadatoer, 'ordrer.fakturadate', 'DATO');
}
if ($genfaktdatoer){
	$udvaelg=$udvaelg.udvaelg($genfaktdatoer, 'ordrer.nextfakt', 'DATO');
}
if ($ref[0]) {$udvaelg= $udvaelg." and ordrer.ref='$ref[0]'";}
if ($projekt[0]) {$udvaelg= $udvaelg." and ordrer.projekt='$projekt[0]'";}
if ($summer) { $udvaelg=$udvaelg.udvaelg($summer, 'ordrer.sum', 'BELOB');}

if ($kontoid){
	$udvaelg=$udvaelg.udvaelg($kontoid, 'ordrer.konto_id', 'NR');
}
if (strstr($sort,'fakturanr')) {
	if ($db_type=='mysql') $sort=str_replace("fakturanr","CAST(ordrer.fakturanr AS SIGNED)",$sort); 
	else $sort=str_replace("fakturanr","to_number(textcat('0',ordrer.fakturanr),text(99999999))",$sort);
} else $sort="ordrer.".$sort;
# if (strstr($udvaelg,'fakturanr')) $udvaelg=str_replace("fakturanr","fakturanr::varchar::numeric",$udvaelg);
$ordreliste="";
if ($valg=="tilbud") { 
	$ialt=0;
	$query = db_select("select * from ordrer where (art = 'DO' or art = 'DK') and status < 1 $udvaelg order by $sort",__FILE__ . " linje " . __LINE__);
	while ($row =db_fetch_array($query)) {
		if ($ordreliste) $ordreliste=$ordreliste.",".$row['id'];
		else $ordreliste=$row['id'];
		$ordre="ordre".$row['id'];
		$sum=$row['sum'];
		$kostpris=$row['kostpris'];
		$valutakurs=$row['valutakurs'];
		if (($tidspkt-($row[tidspkt])>3600)||($row[hvem]==$brugernavn)) {
			if ($popup) {
				$javascript="onClick=\"javascript:$ordre=window.open('ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" ";
				$understreg='<span style="text-decoration: underline;">';
				$hrefslut="";
			} else {
				$javascript="";
				$understreg="<a href=ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php>";
				$hrefslut="</a>";
			}
			$linjetext="";
		}
		else {
			$javascript="onClick=\"javascript:$ordre.focus();\"";
			$understreg='';
			$linjetext="<span title= 'Ordre er l&aring;st af $row[hvem]'>";
		}
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if ($row['art']=='DK'){print "<td align=right $javascript> (KN)&nbsp;$linjetext $understreg $row[ordrenr]$hrefslut</span><br></td>";}
		else {print "<td align=right $javascript> $linjetext $understreg $row[ordrenr]$hrefslut</span><br></td>";}
		print "<td></td>";
		$ordredato=dkdato($row['ordredate']);
		print "<td>$ordredato<br></td>";
#		$levdato=dkdato($row['levdate']);
#		print "<td>$levdato<br></td>";
#		print"<td></td>";
		print "<td>$row[kontonr]<br></td>";
		print "<td>".stripslashes($row['firmanavn'])."<br></td>";
		print "<td>$row[ref]<br></td>";
		if ($vis_projekt) print "<td>$row[projekt]<br></td>";
		if ($genberegn) $kostpris=genberegn($row['id']);
		if ($valutakurs && $valutakurs!=100) {
			$sum=$sum*$valutakurs/100;
			$kostpris=$kostpris*$valutakurs/100;
		} 
		$sum=bidrag($sum, $kostpris,'1');
		print "</tr>\n";
	}
}
elseif ($valg=='ordrer') {
	$ialt=0;
	if ($hurtigfakt) $query = db_select("select * from ordrer where (art = 'DO' or art = 'DK') and (status < 3) $udvaelg order by $sort",__FILE__ . " linje " . __LINE__);
	else $query = db_select("select * from ordrer where (art = 'DO' or art = 'DK') and (status = 1 or status = 2) $udvaelg order by $sort",__FILE__ . " linje " . __LINE__);
	while ($row =db_fetch_array($query)){
		if ($ordreliste) $ordreliste=$ordreliste.",".$row['id'];
		else $ordreliste=$row['id'];
		$ordre="ordre".$row['id'];
		$sum=$row['sum'];
		$kostpris=$row['kostpris'];
		$valutakurs=$row['valutakurs'];
		if (($tidspkt-($row['tidspkt'])>3600)||($row['hvem']==$brugernavn)){
			if ($popup) {
				$javascript="onClick=\"javascript:$ordre=window.open('ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\"";
				$understreg='<span style="text-decoration: underline;">';
				$hrefslut="";
			} else {
				$javascript="";
				$understreg="<a href=ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php>";
				$hrefslut="</a>";
			}
			$linjetext="";
		}
		else 	{
			$javascript='';
			$understreg='';
			$linjetext="<span title= 'Kladde er l&aring;st af $row[hvem]'>";
		}
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if ($row['art']=='DK'){print "<td align=right $javascript>(KN)&nbsp;$understreg $linjetext $row[ordrenr]</span><br>$hrefslut</td>";}
		else {print "<td align=right $javascript> $understreg $linjetext $row[ordrenr]</span><br>$hrefslut</td>";}
		print "<td></td>";
		$ordredato=dkdato($row['ordredate']);
		print "<td>$ordredato<br></td>";
		$levdato=dkdato($row['levdate']);
		print "<td>$levdato<br></td>";
		print "<td>$row[kontonr]<br></td>";
		print "<td>".stripslashes($row['firmanavn'])."<br></td>";
		print "<td>$row[ref]<br></td>";
		if ($vis_projekt) print "<td>$row[projekt]<br></td>";
		if ($genberegn) {$row['kostpris']=genberegn($row['id']);}
		if ($valutakurs && $valutakurs!=100) {
			$sum=$sum*$valutakurs/100;
			$kostpris=$kostpris*$valutakurs/100;
		} 
		$sum=bidrag($sum, $kostpris,'1');
		print "</tr>\n";
	}
}
else	{
	$ordre_id=array();
	$r=db_fetch_array(db_select("select box5 from grupper where art = 'DIV' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
	$straks_bogf=$r['box5'];
	print "<form name=fakturaprint action=ordreliste.php method=post>";
	$x=0;
	$ialt=0;
	if ($straks_bogf) $query = db_select("select ordrer.*, openpost.udlignet from ordrer, openpost where (ordrer.art = 'DO' or ordrer.art = 'DK') and ((ordrer.status > 3 and openpost.faktnr=ordrer.fakturanr and openpost.konto_id=ordrer.konto_id and ordrer.sum+ordrer.moms=openpost.amount) or ordrer.status=3) $udvaelg order by $sort",__FILE__ . " linje " . __LINE__);
	else $query = db_select("select ordrer.* from ordrer where (ordrer.art = 'DO' or ordrer.art = 'DK') and ordrer.status >= 3 $udvaelg order by $sort",__FILE__ . " linje " . __LINE__);	
	while ($row=db_fetch_array($query)) {
		if (!in_array($row['id'],$ordre_id)) { #tilfoejet 16.04.09 sammen med arrayet ordre_id - noedloesning query v. straks_bogf skal gennemgaas.
			$x++;
		  $ordre_id[$x]=$row['id'];
			if ($ordreliste) $ordreliste=$ordreliste.",".$row['id'];
			else $ordreliste=$row['id'];
			$ordre="ordre".$row['id'];
			$sum=$row['sum'];
			$moms=$row['moms']*1;
			$kostpris=$row['kostpris'];
			$valutakurs=$row['valutakurs'];
			$javascript="onClick=\"javascript:$ordre=window.open('ordre.php?&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\"";
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			else {$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\">";
			if ($popup) {
				if ($row['art']=='DK'){print "<td align=right $javascript ><span style='color: rgb(255, 0, 0); text-decoration: underline;'>$row[ordrenr]<br></span></td>";}
				else {print "<td align=right	$javascript> $understreg <span style='text-decoration: underline;'> $row[ordrenr]<br></span></td>";}
			} else {
				if ($row['art']=='DK') print "<td align=right><a href=ordre.php?&id=$row[id]&returside=ordreliste.php><span style='color: rgb(255, 0, 0);'>$row[ordrenr]<br></a></span></td>";
				else print "<td align=right><a href=ordre.php?&id=$row[id]&returside=ordreliste.php>$row[ordrenr]<br></a></td>";
			}
			print "<td align=right>$row[fakturanr]</td>";
			print"<td></td>";
			$ordredato=dkdato($row['ordredate']);
			print "<td>$ordredato<br></td>";
			$levdato=dkdato($row['levdate']);
			print "<td>$levdato<br></td>";
			$faktdato=dkdato($row['fakturadate']);
			print "<td>$faktdato<br></td>";
			$genfakt='';
			if ($row['nextfakt']) $genfakt=dkdato($row['nextfakt']);
			print "<td>$genfakt<br></td>";
			print "<td>$row[kontonr]<br></td>";
			print "<td>".stripslashes($row['firmanavn'])."<br></td>";
			print "<td>$row[ref]<br></td>";
			if ($vis_projekt) print "<td>$row[projekt]<br></td>";
			if ($genberegn) $kostpris=genberegn($row['id']);
			if ($valutakurs && $valutakurs!=100) {
				$sum=$sum*$valutakurs/100;
				$kostpris=$kostpris*$valutakurs/100;
			} 
			if ($straks_bogf) $udlignet=$row['udlignet'];
			elseif ($r2=db_fetch_array(db_select("select udlignet from openpost where faktnr='$row[fakturanr]' and konto_id='$row[konto_id]' and amount=$sum+$moms",__FILE__ . " linje " . __LINE__))) {
				$udlignet=$r2['udlignet'];	
			} else $udlignet=0;
			$sum=bidrag($sum, $kostpris, $udlignet);
			if ($checked[$x]=='on' || $check_all) $checked[$x]='checked';
			print "<td align=right><input type=\"checkbox\" name=\"checked[$x]\" $checked[$x]></td>";
			print "<input type=hidden name=ordre_id[$x] value=$row[id]>";
			print "</tr>\n";
		}
	}	
		$colspan=12;
		if ($vis_projekt) $colspan++;
		if ($check_all) print "<tr><td align=right colspan=$colspan><input type=\"submit\" name=\"uncheck\" value=\"".findtekst(90,$sprog_id)."\">";
		else print "<tr><td align=right colspan=$colspan><input type=\"submit\" name=\"check\" value=\"".findtekst(89,$sprog_id)."\">";
		print "	</td></tr>\n";
		print "<input type=hidden name=ordre_antal value='$x'>";
		print "<input type=hidden name=valg value='$valg'>";
		print "<input type=hidden name=ordrenumre value='$ordrenumre'>";
		print "<input type=hidden name=kontonumre value='$kontonumre'>";
		print "<input type=hidden name=fakturanumre value='$fakturanumre'>";
		print "<input type=hidden name=ordredatoer value='$ordredatoer'>";
		print "<input type=hidden name=lev_datoer value='$lev_datoer'>";
		print "<input type=hidden name=fakturadatoer value='$fakturadatoer'>";
		print "<input type=hidden name=genfaktdatoer value='$genfaktdatoer'>";
		print "<input type=hidden name=summer value='$summer'>";
		print "<input type=hidden name=ref value='$ref[0]'>";
		print "<input type=hidden name=firma value='$firma'>";
		print "<input type=hidden name=kontoid value='$kontoid'>";
		print "<input type=hidden name=sort value='$sort'>";
		print "<input type=hidden name=nysort value='$nysort'>";
	
		print "<tr><td colspan=$colspan align=right>";
		if ($genfaktdatoer) print "<input type=submit value=\"Genfaktur&eacute;r\" name=\"submit\">&nbsp;";
		if ( strlen("which ps2pdf")) {
			print "<input type=submit value=\"Udskriv\" name=\"submit\"></td>";
		} else {
			print "<input type=submit value=\"Udskriv\" name=\"submit\" disabled=\"disabled\"></td>";
		}
		print "</form></tr>\n";
}
if ($r=db_fetch_array(db_select("select id from grupper where art = 'OLV' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__))) {
	db_modify("update grupper set box1='$ordreliste' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
} else db_modify("insert into grupper (beskrivelse,kode,kodenr,art,box1) values ('Ordrelistevisning','$brugernavn','$bruger_id','OLV','$ordreliste')",__FILE__ . " linje " . __LINE__);

if ($valg=='tilbud') {$cols=7;}
elseif ($valg=='faktura') {$cols=12;}
else {$cols=8;}
if ($vis_projekt) $cols++;
print "<tr><td colspan=$cols><hr></td></tr>\n";
$cols=$cols-4;
$dk_db=dkdecimal($ialt-$totalkost);		
if ($ialt!=0) {$dk_dg=dkdecimal(($ialt-$totalkost)*100/$ialt);}		
$ialt=dkdecimal($ialt);
$cols--;
if ($valg=='faktura'){$cols--;}
print "<tr><td colspan=3></td>";
print "<td align=center colspan=$cols-4><span title= 'Klik for at genberegne DB/DG'><b><a href=ordreliste.php?genberegn=1$hreftext accesskey=G>Samlet oms&aelig;tning / db / dg (excl. moms.) </a></td><td align=right colspan=2><b>$ialt / $dk_db / $dk_dg%</td></tr>\n";
if ($genberegn==1) print "<meta http-equiv=\"refresh\" content=\"0;URL='ordreliste.php?genberegn=2$hreftext'\">";
$cols++;
if ($valg=='faktura'){$cols++;}
$cols=$cols+4;
print "<tr><td colspan=$cols><hr></td></tr>\n";
$r=db_fetch_array(db_select("select box1 from grupper where art='MFAKT' and kodenr='1'",__FILE__ . " linje " . __LINE__));
if ($r['box1'] && $valg=='ordrer' && $ialt!="0,00") print "<tr><td colspan=$cols align=right><span title='Klik her for at fakturere alle ordrer p&aring; listen'><a href=massefakt.php target=\"_blank\" onClick=\"return MasseFakt()\">Faktur&eacute;r alt</a></span></td></tr>";

function genberegn($id) {
	$kostpris=0;
	$q0 = db_select("select id, vare_id, antal, pris from ordrelinjer where ordre_id = $id and posnr>0 and vare_id > 0",__FILE__ . " linje " . __LINE__);
	while ($r0=db_fetch_array($q0)) {
		if ($r1=db_fetch_array(db_select("select provisionsfri, gruppe from varer where id = $r0[vare_id]",__FILE__ . " linje " . __LINE__))) {
			if ((!$r1[provisionsfri])&&($r1=db_fetch_array(db_select("select box9 from grupper where art = 'VG' and ".nr_cast("kodenr")."=$r1[gruppe] and box9 = 'on' ",__FILE__ . " linje " . __LINE__)))) {
				$batch_tjek='0';
				$q1 = db_select("select antal, batch_kob_id from batch_salg where linje_id = $r0[id] and batch_kob_id != 0",__FILE__ . " linje " . __LINE__);	
				while ($r1=db_fetch_array($q1)) {
					if ($r2=db_fetch_array(db_select("select pris, fakturadate, linje_id from batch_kob where id = $r1[batch_kob_id]",__FILE__ . " linje " . __LINE__))) {
						if ($r2['fakturadate']<'2000-01-01') $r2=db_fetch_array(db_select("select pris from ordrelinjer where id = $r2[linje_id]",__FILE__ . " linje " . __LINE__));
						$batch_tjek=1;
						$tmpp=$r2['pris']*$r1['antal'];
						$kostpris=$kostpris+$r2['pris']*$r1['antal'];
					}
				}
				if ($batch_tjek<1) {
					$r2=db_fetch_array(db_select("select kostpris from varer where id = $r0[vare_id]",__FILE__ . " linje " . __LINE__));	
					$kostpris=$kostpris+$r2['kostpris']*$r0['antal'];
				}		
			}
			elseif ($r1[provisionsfri]) {$kostpris=$kostpris+$r0['pris']*$r0['antal'];}
			else {	
					$r2=db_fetch_array(db_select("select kostpris from varer where id = $r0[vare_id]",__FILE__ . " linje " . __LINE__));	
					$kostpris=$kostpris+$r2['kostpris']*$r0['antal'];
			}
		}
	} 
	db_modify("update ordrer set kostpris=$kostpris where id = $id",__FILE__ . " linje " . __LINE__);
	return $kostpris;
}

function bidrag ($sum,$kostpris,$udlignet){
	global $ialt;
	global $totalkost;
	global $genberegn;

	$ialt=$ialt+$sum;
	$totalkost=$totalkost+$kostpris;
	$dk_db=dkdecimal($sum-$kostpris);		
	$sum=round($sum,2);
	$kostpris=round($kostpris,2);
	if ($sum) $dk_dg=dkdecimal(($sum-$kostpris)*100/$sum);		
	else $dk_dg='0,00';
	$sum=dkdecimal($sum);
	if ($genberegn) {print "<td align=right><span title= 'db: $dk_db - dg: $dk_dg%'>$sum/$dk_db/$dk_dg%<br></span></td>";}
	else {
		if ($udlignet) $span="style='color: #000000;' title='db: $dk_db - dg: $dk_dg%'";
		else $span="style='color: #FF0000;' title='Ikke udlignet\r\ndb: $dk_db - dg: $dk_dg%'";
		print "<td align=right><span $span>$sum<br></span></td>";
	}
}

?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
