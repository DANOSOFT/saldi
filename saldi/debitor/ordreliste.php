<?php
// -----------------------------------------------debitor/ordreliste.php-------------lap 1.0.8----------
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
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------
ob_start();
@session_start();
$s_id=session_id();

$modulnr=5;
$title="Ordreliste - Debitorer";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdato.php");
include("../includes/dkdecimal.php");
include("../includes/udvaelg.php");
	
print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Ordreliste - Kunder</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";

$ordrenumre = $_GET['ordrenumre'];
$kundenumre = $_GET['kundenumre'];
$fakturanumre = $_GET['fakturanumre'];
$ordredatoer = $_GET['ordredatoer'];
$lev_datoer = $_GET['lev_datoer'];
$fakturadatoer = $_GET['fakturadatoer'];
$summer = $_GET['summer'];
$firma = $_GET['firma'];
$ref[0] = $_GET['ref'];
$valg=$_GET['valg'];
$sort = $_GET['sort'];
$nysort = $_GET['nysort'];
$kontoid= $_GET['kontoid'];
$genberegn= $_GET['genberegn'];
	
$tidspkt=date("U");
 
if ($submit=$_POST['submit']){
	$ordrenumre = stripslashes($_POST['ordrenumre']);
	$kundenumre = stripslashes($_POST['kundenumre']);
	$fakturanumre = stripslashes($_POST['fakturanumre']);
	$ordredatoer = stripslashes($_POST['ordredatoer']);
	$lev_datoer = stripslashes($_POST['lev_datoer']);
	$fakturadatoer = stripslashes($_POST['fakturadatoer']);
	$summer = stripslashes($_POST['summer']);
	$firma = stripslashes($_POST['firma']);
	$ref[0] = stripslashes($_POST['ref']);
	$valg=$_POST['valg'];
	$sort = $_POST['sort'];
	$nysort = $_POST['nysort'];
	$firma=$_POST['firma'];
	$kontoid=$_POST['kontoid'];
	$firmanavn_ant=$_POST['firmanavn_antal'];
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
	$cookievalue="$ordrenumre;$kundenumre;$fakturanumre;$ordredatoer;$lev_datoer;$fakturadatoer;$summer;$firma;$kontoid;$ref[0];$sort;$valg;$nysort";
	setcookie("deb_ord_lst", $cookievalue);
}
else {
	list ($ordrenumre, $kundenumre, $fakturanumre, $ordredatoer, $lev_datoer, $fakturadatoer, $summer, $firma, $kontoid, $ref[0], $sort, $valg, $nysort) = split(";", $_COOKIE['deb_ord_lst']);#
}
ob_end_flush();	//Sender det "bufferede" output afsted...
	
if (!$valg) {$valg = "ordrer";}
if (!$sort) {$sort = "firmanavn";}
elseif ($nysort==$sort){$sort=$sort." desc";}
elseif ($nysort) {$sort=$nysort;}

if ($valg!='faktura') {
	$fakturanumre='';
	$fakturadatoer='';
}

$hreftext="&ordrenumre=$ordrenumre&kundenumre=$kundenumre&fakturanumre=$fakturanumre&ordredatoer=$ordredatoer&lev_datoer=$lev_datoer&fakturadatoer=$fakturadatoer&summer=$summer&ref=$ref[0]&kontoid=$kontoid";
if ($valg!="faktura") {print "<meta http-equiv=\"refresh\" content=\"60;URL='ordreliste.php?sort=$sort&valg=$valg$hreftext'\">";}
 
 
if ($submit=="Udskriv"){
	$ordre_antal = $_POST['ordre_antal'];
	$ordre_id = $_POST['ordre_id'];
	$skriv = $_POST['skriv'];

	for ($x=1; $x<=$ordre_antal; $x++){
		if ($skriv[$x]=="on") {
			$y++;
			if (!$udskriv) {$udskriv=$ordre_id[$x];}
			else {$udskriv=$udskriv.",".$ordre_id[$x];}
		}
	}
	if ($y>0) {
		print "<BODY onLoad=\"JavaScript:window.open('formularprint.php?id=-1&ordre_antal=$y&skriv=$udskriv&formular=4' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
	}
	else {print "<BODY onLoad=\"javascript:alert('Ingen fakturaer er markeret til udskrivning!')\">";}
}

print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>";
print "<tr><td height = 25 align=center valign=top>";
print "<table width=100% align=center border=0 cellspacing=0 cellpadding=0><tbody>";
print "<td width=25% bgcolor=$bgcolor2>$font<small><a href=../includes/luk.php accesskey=T>Tilbage</a></small></td>";
# print "<td width=50% bgcolor=$bgcolor2 align=center>$font<small>Kundeordrer</small></td>";

print "<td width=50% bgcolor=$bgcolor2 align=center><table border=0	cellspacing=0 cellpadding=0><tbody>";

if ($valg=='tilbud') {print "<td width = 20% align=center bgcolor=$bgcolor>$font<small><a href='ordreliste.php?sort=$sort&valg=tilbud$hreftext'>&nbsp;Tilbud&nbsp;</a></td>";}
else {print "<td width = 20% align=center>$font<small><a href='ordreliste.php?sort=$sort&valg=tilbud$hreftext'>&nbsp;Tilbud&nbsp;</a></td>";}
if ($valg=='ordrer') {print "<td width = 20% align=center bgcolor=$bgcolor>$font<small><a href='ordreliste.php?sort=$sort&valg=ordrer$hreftext'>&nbsp;Ordrer&nbsp;</a></td>";}
else {print "<td width = 20% align=center>$font<small><a href='ordreliste.php?sort=$sort&valg=ordrer$hreftext'>&nbsp;Ordrer&nbsp;</a></td>";}
if ($valg=='faktura') {print "<td width = 20% align=center bgcolor=$bgcolor>$font<small><a href='ordreliste.php?sort=$sort&valg=faktura$hreftext'>&nbsp;Faktura&nbsp;</a></td>";}
else {print "<td width = 20% align=center>$font<small><a href='ordreliste.php?sort=$sort&valg=faktura$hreftext'>&nbsp;Faktura&nbsp;</a></td>";}

print "</tbody></table></td>";

print "<td width=25% align=right bgcolor=$bgcolor2 onClick=\"javascript:ordre=window.open('ordre.php?returside=ordreliste.php','ordre','scrollbars=1,resizable=1');ordre.focus();\">$font<small><a accesskey=N href=ordreliste.php?sort=$sort>Ny</a></small></td>";

print "</td></tr>\n";
#print "<tr><td></td><td align=center><table border=1	cellspacing=0 cellpadding=0><tbody>";
#print "<td width = 20% bgcolor=$bgcolor2 align=center>$font<small><a href=ordreliste.php?valg=tilbud accesskey=T>Tilbud</a></td>";
#print "<td width = 20% bgcolor=$bgcolor5 align=center>$font <small>Ordrer</td>";
#print "<td width = 20% bgcolor=$bgcolor5 align=center>$font <small>Faktura</td>";
#print "</tbody></table></td><td></td</tr>\n";

print "</tbody></table>";
print " </td></tr><tr><td align=center valign=top>";
print "<table cellpadding=1 cellspacing=1 border=0 width=100% valign = top>";

print "<tbody>";
print "	<tr>";
print "<td align=right><small><b>$font<a href='ordreliste.php?nysort=ordrenr&sort=$sort&valg=$valg$hreftext'>Ordrenr.</b></small></td>";
if ($valg=='faktura') {print "	 <td align=right width=50><small><b>$font<a href='ordreliste.php?nysort=fakturanr&sort=$sort&valg=$valg$hreftext'>Faktnr.</b></small></td>";}
 print "	<td width=50></td>";
if ($valg=='tilbud') {print "<td><small><b>$font<a href='ordreliste.php?nysort=ordredate&sort=$sort&valg=$valg$hreftext'>Tilbudsdato</b></small></td>";}
else {
	print "<td><small><b>$font<a href='ordreliste.php?nysort=ordredate&sort=$sort&valg=$valg$hreftext'>Ordredato</b></small></td>";
	print "<td><small><b>$font<a href='ordreliste.php?nysort=levdate&sort=$sort&valg=$valg$hreftext'>Levdato</b></small></td>";
}
if ($valg=='faktura') {print "<td><small><b>$font<a href='ordreliste.php?nysort=fakturadate&sort=$sort&valg=$valg$hreftext'>Fakt.dato</b></small></td>";}
print "<td><small><b>$font<a href='ordreliste.php?nysort=kontonr&sort=$sort&valg=$valg$hreftext'>Kundenr</b></small></td>";
print "<td><small><b>$font<a href='ordreliste.php?nysort=firmanavn&sort=$sort&valg=$valg$hreftext'>Firmanavn</a></b></small></td>";
print "<td><small><b>$font S&aelig;lger</a></b></small></td>";
if ($valg=='tilbud') {print "<td align=right><small><b>$font<a href='ordreliste.php?nysort=sum&sort=$sort&valg=$valg$hreftext'>Tilbudssum</a></b></small></td>";}
elseif ($valg=='ordrer'){print "<td align=right><small><b>$font<a href='ordreliste.php?nysort=sum&sort=$sort&valg=$valg$hreftext'>Ordresum</a></b></small></td>";}
else {
	print "<td align=right><small><b>$font<a href='ordreliste.php?nysort=sum&sort=$sort&valg=$valg$hreftext'>Fakturasum";
	if ($genberegn) print "/db";
	print "</a></b></small></td>";
}
print "</tr>\n";

#################################### Sogefelter ##########################################

print "<form name=ordreliste action=ordreliste.php method=post>";
print "<input type=hidden name=valg value=$valg>";
print "<input type=hidden name=sort value=$sort>";
print "<input type=hidden name=nysort value=$nysort>";
print "<input type=hidden name=kontoid value=$kontoid>";
print "<tr>";
print "<td align=right><span title= 'Angiv et ordrenummer eller angiv to adskilt af kolon (f.eks 345:350)'><input type=text size=5 name=ordrenumre value=$ordrenumre></td>";
if ($valg=='faktura') {print "<td align=right><span title= 'Angiv et fakturanummer eller angiv to adskilt af kolon (f.eks 345:350)'><input type=text size=5 name=fakturanumre value=$fakturanumre></td>";}
 print "<td width=50></td>";
print "<td><span title= 'Angiv en dato eller angiv to adskilt af kolon (f.eks 010605:300605)'><input type=text size=10 name=ordredatoer value=$ordredatoer></td>";
if ($valg!='tilbud') {print "<td><span title= 'Angiv en dato eller angiv to adskilt af kolon (f.eks 010605:300605)'><input type=text size=10 name=lev_datoer value=$lev_datoer></td>";}
if ($valg=='faktura') {print "<td><span title= 'Angiv en dato eller angiv to adskilt af kolon (f.eks 010605:300605)'><input type=text size=10 name=fakturadatoer value=$fakturadatoer></td>";}
print "<td><span title= 'Angiv et kundenr. eller angiv to adskilt af kolon (f.eks 43000000:43999999)'><input type=text size=10 name=kundenumre value=$kundenumre></td>";

$x=0;
if (!$konto_id) {$konto_id=array();}
if ($valg=="tilbud") {$status="status = 0";}
elseif ($valg=="faktura") {$status="status >= 3";}
else {$status="status = 1 or status = 2";}
if (($kontoid)&&(!$firma)){
	$row = db_fetch_array(db_select("select firmanavn from adresser where id = $kontoid"));
	$firma=stripslashes($row[firmanavn]);
}
$query = db_select("select konto_id from ordrer where (art = 'DK' or art = 'DO') and $status order by firmanavn");
while ($row = db_fetch_array($query)) {
	 if (!in_array($row[konto_id], $konto_id)) {
		 $x++;
		 $konto_id[$x]=$row[konto_id];
		 $r2 = db_fetch_array(db_select("select firmanavn from adresser where id = $konto_id[$x]"));
		 $firmanavn[$x]=stripslashes($r2[firmanavn]);
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
$query = db_select("select ref from ordrer where art='DO' order by ref");
while ($row = db_fetch_array($query)) {
	 if (!in_array($row[ref], $ref)) {
		 $x++;
		 $ref[$x]=$row[ref];
	 } 
}
$refantal=$x;	

print "<td><span title= 'V&aelig;lg en referanceperson'><SELECT NAME=ref value=$ref[0]>";
print "<option>$ref[0]</option>";
for ($x=1;$x<=$refantal; $x++) {print "<option>$ref[$x]</option>";}
if ($ref[0]!=$ref[$x]) {print "<option>$ref[$x]</option>";}
if ($ref[0]) {print "</SELECT></td>";}

print "<td align=right><span title= 'Angiv et bel&oslash;b eller angiv to adskilt af kolon (f.eks 10000,00:14999,99)'><input type=text size=10 name=summer value=$summer></td>";
print "<td><input type=submit value=\"OK\" name=\"submit\"></td>";
print "</form></tr>\n";
####################################################################################

$udvaelg='';
if ($ordrenumre) {
	$udvaelg=$udvaelg.udvaelg($ordrenumre, 'ordrenr', 'NR');
}
if ($fakturanumre) {
	$udvaelg=$udvaelg.=udvaelg($fakturanumre, 'fakturanr', 'NR');
}
if ($kundenumre) {
	$udvaelg=$udvaelg.=udvaelg($kundenumre, 'kontonr', 'NR');
}
if ($ordredatoer) {
	$udvaelg=$udvaelg.udvaelg($ordredatoer, 'ordredate', 'DATO');
}
if ($lev_datoer) {
	$udvaelg=$udvaelg.udvaelg($lev_datoer, 'levdate', 'DATO');
}
if ($fakturadatoer){
	$udvaelg=$udvaelg.udvaelg($fakturadatoer, 'fakturadate', 'DATO');
}
if ($ref[0]) {$udvaelg= $udvaelg." and ref='$ref[0]'";}
if ($summer) { $udvaelg=$udvaelg.udvaelg($summer, 'sum', 'BELOB');}

if ($kontoid){
	$udvaelg=$udvaelg.udvaelg($kontoid, 'konto_id', 'NR');
}

if ($valg=="tilbud") { 
	$ialt=0;
	$query = db_select("select * from ordrer where (art = 'DO' or art = 'DK') and status < 1 $udvaelg order by $sort");
	while ($row =db_fetch_array($query)) {
		$ordre="ordre".$row[id];
		if (($tidspkt-($row[tidspkt])>3600)||($row[hvem]==$brugernavn)) {
			$javascript="onClick=\"javascript:$ordre=window.open('ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" ";
			$understreg='<span style="text-decoration: underline;">';
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
		if ($row[art]=='DK'){print "<td align=right $javascript><small>$font (KN)&nbsp;$linjetext $understreg $row[ordrenr]</span><br></small></td>";}
		else {print "<td align=right $javascript><small>$font $linjetext $understreg $row[ordrenr]</span><br></small></td>";}
		print "<td></td>";
		$ordredato=dkdato($row[ordredate]);
		print "<td><small>$font$ordredato<br></small></td>";
#		$levdato=dkdato($row[levdate]);
#		print "<td><small>$font$levdato<br></small></td>";
#		print"<td></td>";
		print "<td><small>$font$row[kontonr]<br></small></td>";
		print "<td><small>$font".stripslashes($row[firmanavn])."<br></small></td>";
		print "<td><small>$font$row[ref]<br></small></td>";
		if ($genberegn==1) {$row[kostpris]=genberegn($row[id]);}
		$sum=bidrag($row[sum], $row[kostpris]);
		print "</tr>\n";
	}
}
elseif ($valg=='ordrer') {
	$ialt=0;
#	print "<tr><td colspan=9><hr></td></tr><tr><td colspan=9 align=center>$font Godkendte ordrer</td></tr><tr><td colspan=9><hr></td></tr>\n";
	$query = db_select("select * from ordrer where	(art = 'DO' or art = 'DK') and (status = 1 or status = 2) $udvaelg order by $sort");
	while ($row =db_fetch_array($query)){
		$ordre="ordre".$row[id];
		if (($tidspkt-($row[tidspkt])>3600)||($row[hvem]==$brugernavn)){
			$javascript="onClick=\"javascript:$ordre=window.open('ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\"";
			$understreg='<span style="text-decoration: underline;">';
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
		if ($row[art]=='DK'){print "<td align=right $javascript><small>$font(KN)&nbsp;$understreg $linjetext $row[ordrenr]</span><br></small></td>";}
		else {print "<td align=right $javascript><small>$font $understreg $linjetext $row[ordrenr]</span><br></small></td>";}
		print "<td></td>";
		$ordredato=dkdato($row[ordredate]);
		print "<td><small>$font$ordredato<br></small></td>";
		$levdato=dkdato($row[levdate]);
		print "<td><small>$font$levdato<br></small></td>";
		print "<td><small>$font$row[kontonr]<br></small></td>";
		print "<td><small>$font".stripslashes($row[firmanavn])."<br></small></td>";
		print "<td><small>$font$row[ref]<br></small></td>";
		if ($genberegn==1) {$row[kostpris]=genberegn($row[id]);}
		$sum=bidrag($row[sum], $row[kostpris]);
		print "</tr>\n";
	}
}
else	{
	print "<form name=fakturaprint action=ordreliste.php method=post>";
	$x=0;
	$ialt=0;
	$query = db_select("select * from ordrer where (art = 'DO' or art = 'DK') and status >= 3 $udvaelg order by $sort");
	while ($row =db_fetch_array($query)) {
		$x++;
		 $ordre="ordre".$row[id];
		$javascript="onClick=\"javascript:$ordre=window.open('ordre.php?&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\"";
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if ($row[art]=='DK'){print "<td align=right $javascript ><small>$font<span style='color: rgb(255, 0, 0); text-decoration: underline;'>$row[ordrenr]<br></span></small></td>";}
		else {print "<td align=right	$javascript> $understreg <small>$font<span style='text-decoration: underline;'> $row[ordrenr]<br></span></small></td>";}
		print "<td align=right><small>$font$row[fakturanr]</small></td>";
		print"<td></td>";
		$ordredato=dkdato($row[ordredate]);
		print "<td><small>$font$ordredato<br></small></td>";
		$levdato=dkdato($row[levdate]);
		print "<td><small>$font$levdato<br></small></td>";
		$faktdato=dkdato($row[fakturadate]);
		print "<td><small>$font$faktdato<br></small></td>";
		print "<td><small>$font$row[kontonr]<br></small></td>";
		print "<td><small>$font".stripslashes($row[firmanavn])."<br></small></td>";
		print "<td><small>$font$row[ref]<br></small></td>";
		if ($genberegn==1) {$row[kostpris]=genberegn($row[id]);}
		$sum=bidrag($row[sum], $row[kostpris]);
		if ($skriv[$x]==on) {print "<td align=right><input type=checkbox name=skriv[$x] checked>";}
		else print "<td align=right><input type=checkbox name=skriv[$x]>";
		print"<input type=hidden name=ordre_id[$x] value=$row[id]>";
		print "</tr>\n";
	}
	print "<input type=hidden name=ordre_antal value=$x>";
	print "<input type=hidden name=valg value=$valg>";
	print "<input type=hidden name=ordrenumre value=$ordrenumre>";
	print "<input type=hidden name=kundenumre value=$kundenumre>";
	print "<input type=hidden name=fakturanumre value=$fakturanumre>";
	print "<input type=hidden name=ordredatoer value=$ordredatoer>";
	print "<input type=hidden name=lev_datoer value=$lev_datoer>";
	print "<input type=hidden name=fakturadatoer value=$fakturadatoer>";
	print "<input type=hidden name=summer value=$summer>";
	print "<input type=hidden name=ref value=$ref[0]>";
	print "<input type=hidden name=ref value=$firma>";
	print "<input type=hidden name=ref value=$kontoid>";
	print "<input type=hidden name=sort value=$sort>";
	print "<input type=hidden name=nysort value=$nysort>";

	print "<tr><td colspan=11 align=right><input type=submit value=\"Udskriv\" name=\"submit\"></td>";
	print "</form></tr>\n";
}
if ($valg=='tilbud') {$cols=7;}
elseif ($valg=='faktura') {$cols=11;}
else {$cols=8;}
print "<tr><td colspan=$cols><hr></td></tr>\n";
$cols=$cols-4;
$dk_db=dkdecimal($ialt-$totalkost);		
if ($ialt!=0) {$dk_dg=dkdecimal(($ialt-$totalkost)*100/$ialt);}		
$ialt=dkdecimal($ialt);
$cols--;
if ($valg=='faktura'){$cols--;}
print "<tr><td colspan=3></td><td align=center colspan=$cols-4>$font<small><span title= 'Klik for at genberegne DB/DG'><b><a href=ordreliste.php?genberegn=1$hreftext accesskey=G>Samlet oms&aelig;tning / db / dg (excl. moms.) </a></td><td align=right colspan=2>$font<small><b>$ialt / $dk_db / $dk_dg%</td></tr>\n";
$cols++;
if ($valg=='faktura'){$cols++;}
$cols=$cols+4;
print "<tr><td colspan=$cols><hr></td></tr>\n";

function genberegn($id) {
	$kostpris=0;
	$q0 = db_select("select id, vare_id, antal, pris from ordrelinjer where ordre_id = $id and posnr>0 and vare_id > 0");
	while ($r0=db_fetch_array($q0)) {
		if ($r1=db_fetch_array(db_select("select provisionsfri, gruppe from varer where id = $r0[vare_id]"))) {
			if ((!$r1[provisionsfri])&&($r1=db_fetch_array(db_select("select box8 from grupper where art = 'VG' and kodenr=$r1[gruppe] and box8 = 'on' ")))) {
				$batch_tjek='0';
				$q1 = db_select("select antal, batch_kob_id from batch_salg where linje_id = $r0[id] and batch_kob_id != 0");	
				while ($r1=db_fetch_array($q1)) {
					if ($r2=db_fetch_array(db_select("select pris, fakturadate, linje_id from batch_kob where id = $r1[batch_kob_id]"))) {
						if ($r2[fakturadate]<'2000-01-01') {$r2=db_fetch_array(db_select("select pris from ordrelinjer where id = $r2[linje_id]"));}
						$batch_tjek=1;
						$kostpris=$kostpris+$r2[pris]*$r1[antal];
					}
				}
				if ($batch_tjek<1) {
					$r2=db_fetch_array(db_select("select kostpris from varer where id = $r0[vare_id]"));	
					$kostpris=$kostpris+$r2[kostpris]*$r0[antal];
				}		
			}
			elseif ($r1[provisionsfri])	{$kostpris=$kostpris+$r0[pris]*$r0[antal];}
			else {	
					$r2=db_fetch_array(db_select("select kostpris from varer where id = $r0[vare_id]"));	
					$kostpris=$kostpris+$r2[kostpris]*$r0[antal];
			}
		}
	} 
	db_modify("update ordrer set kostpris=$kostpris where id = $id");
#echo "$id : $kostpris<br>";
	return $kostpris;
}

function bidrag ($sum,$kostpris){
	global $font;
	global $ialt;
	global $totalkost;
	global $genberegn;

	$ialt=$ialt+$sum;
	$totalkost=$totalkost+$kostpris;
	$dk_db=dkdecimal($sum-$kostpris);		
	if ($sum!=0) {$dk_dg=dkdecimal(($sum-$kostpris)*100/$sum);}		
	$sum=dkdecimal($sum);
	if ($genberegn) {print "<td align=right><small>$font<span title= 'db: $dk_db - dg: $dk_dg%'>$sum/$dk_db/$dk_dg%<br></small></td>";}
	else {print "<td align=right><small>$font<span title= 'db: $dk_db - dg: $dk_dg%'>$sum<br></small></td>";}
}

?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
