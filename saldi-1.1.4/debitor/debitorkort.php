<?php
// ------------- debitor/debitorkort.php ----- (modul nr 6)------ lap 1.1.2 ---12.10.07-------
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

$modulnr=6;
$title="SALDI - debitorkort";

 include("../includes/connect.php");
 include("../includes/online.php");
 include("../includes/std_func.php");
 include("../includes/db_query.php");

 $id = $_GET['id'];
 if($_GET['returside']){
 	$returside= $_GET['returside'];
 	$ordre_id = $_GET['ordre_id'];
 	$fokus = $_GET['fokus'];
}
else {$returside="debitor.php";}


if ($_POST){
 	$submit=addslashes(trim($_POST['submit']));
 	$id=$_POST['id'];
 	$kontonr=addslashes(trim($_POST['kontonr']));
 	$ny_kontonr=addslashes(trim($_POST['ny_kontonr']));
 	$firmanavn=addslashes(trim($_POST['firmanavn']));
 	$addr1=addslashes(trim($_POST['addr1']));
 	$addr2=addslashes(trim($_POST['addr2']));
 	$postnr=addslashes(trim($_POST['postnr']));
 	$bynavn=addslashes(trim($_POST['bynavn']));
 	$land=addslashes(trim($_POST['land']));
 	$kontakt=addslashes(trim($_POST['kontakt']));
 	$tlf=addslashes(trim($_POST['tlf']));
 	$fax=addslashes(trim($_POST['fax']));
 	$email=addslashes(trim($_POST['email']));
 	$web=addslashes(trim($_POST['web']));
 	$betalingsbet=addslashes(trim($_POST[betalingsbet]));
 	$cvrnr=addslashes(trim($_POST['cvrnr']));
 	$ean=addslashes(trim($_POST['ean']));
 	$institution=addslashes(trim($_POST['institution']));
 	$betalingsdage=$_POST['betalingsdage'];
 	$kreditmax=usdecimal($_POST['kreditmax']);
 	list ($gruppe) = split (':', $_POST['gruppe']);
	$kontoansvarlig=$_POST['kontoansvarlig'];
 	$notes=addslashes(trim($_POST['notes']));
 	$ordre_id=$_POST['ordre_id'];
 	$returside=$_POST['returside'];
 	$fokus=$_POST['fokus'];
 	$posnr=$_POST['posnr'];
 	$ans_id=$_POST['ans_id'];
 	$ans_ant=$_POST['ans_ant'];

 	
 	######### Tjekker om kontonr er integer
 
 	$temp=str_replace(" ","",$ny_kontonr);
 	$tmp2='';
 	for ($x=0; $x<strlen($temp); $x++){
 	 	$y=substr($temp,$x,1);
 	 	if ((ord($y)<48)||(ord($y)>57)) {$y=0;}
 	 	$tmp2=$tmp2.$y;
 	}
 	$tmp2=$tmp2*1;
 	if ($tmp2!=$ny_kontonr) {print "<BODY onLoad=\"javascript:alert('Kontonummer m&aring; kun best&aring; af heltal uden mellemrum')\">";}
 	$ny_kontonr=$tmp2;
 	
	if (!$firmanavn) print "<BODY onLoad=\"javascript:alert('Navn skal angives')\">";
	if ($kontoansvarlig) {
		if ($r = db_fetch_array(db_select("select id from adresser where art = 'S'"))) {
			if ($r = db_fetch_array(db_select("select id from ansatte where initialer = '$kontoansvarlig' and konto_id='$r[id]'"))) $kontoansvarlig=$r['id'];
		}
	} elseif ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box2 = 'on'"))) {
		print "<BODY onLoad=\"javascript:alert('Kundeansvarlig ikke valgt!')\">";
	}  
	if (!$kontoansvarlig) $kontoansvarlig='0';
	if (!$gruppe) {
		print "<BODY onLoad=\"javascript:alert('Debitorgruppe ikke valgt!')\">";
		$gruppe='0';
	}  
 
 	## Tildeler aut kontonr hvis det ikke er angivet
 	$ktoliste=array();
 	if (($firmanavn)&&(($ny_kontonr < 1)||(!$ny_kontonr))) {
 	 	if (!$id) {$id="0";}
 	 	$x=0;
 	 	$q = db_select("select kontonr from adresser where art = 'D' and id != $id order by kontonr");
 	 	while ($r = db_fetch_array($q)) {
 	 	 	$x++;
 	 	 	$ktoliste[$x]=$r[kontonr];
 	}
 	$ny_kontonr=1000;
 	while(in_array($ny_kontonr, $ktoliste)) $ny_kontonr++;
 	print "<BODY onLoad=\"javascript:alert('Kontonummer tildelt automatisk')\">";
}
 	
############################
 	 	
 	if ($submit=="Slet") {db_modify("delete from adresser where id = $id");}
 	elseif ($firmanavn) {
 	 	if(!$betalingsdage){$betalingsdage=0;}
 	 	if(!$kreditmax){$kreditmax=0;}
 	 	if ($id==0) {
 	 	 	$q = db_select("select id from adresser where kontonr = '$ny_kontonr' and art = 'D'");
 	 	 	$r = db_fetch_array($q);
 	 	 	if ($r[id]) {
 	 	 	 	print "<BODY onLoad=\"javascript:alert('Der findes allerede en debitor med Kundenr: $ny_kontonr')\">";
 	 	 	 	$id=0;
 	 	 	}
 	 	 	elseif($ny_kontonr) {
				$oprettet=date("Y-m-d");
 	 	 	 	db_modify("insert into adresser (kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf, fax, email, web, betalingsdage, kreditmax, betalingsbet, cvrnr, ean, institution, notes, art, gruppe, kontoansvarlig, oprettet) values ('$ny_kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$kontakt', '$tlf', '$fax', '$email', '$web', '$betalingsdage', '$kreditmax', '$betalingsbet', '$cvrnr', '$ean', '$institution', '$notes', 'D', '$gruppe', '$kontoansvarlig', '$oprettet')");
 	 	 	 	$q = db_select("select id from adresser where kontonr = '$ny_kontonr' and art = 'D'");
 	 	 	 	$r = db_fetch_array($q);
 	 	 	 	$id = $r[id];
 	 	 	}
 	 	}
 	 	elseif ($id > 0) {
 	 	 	if ($ny_kontonr!=$kontonr) {
 	 	 	 	$q = db_select("select kontonr from adresser where art = 'D' order by kontonr");
 	 	 	 	while ($r = db_fetch_array($q)) {
 	 	 	 	 	$x++;
 	 	 	 	 	$ktoliste[$x]=$r[kontonr];
 	 	 	 	}
 	 	 	 	if (in_array($ny_kontonr, $ktoliste)) {
 	 	 	 	 	 print "<BODY onLoad=\"javascript:alert('Kontonummer findes allerede, ikke &aelig;ndret')\">";
 	 	 	 	}
 	 	 	 	else {$kontonr=$ny_kontonr;}
 	 	 	}
 	 	 	db_modify("update adresser set kontonr = '$kontonr', 	firmanavn = '$firmanavn', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', land = '$land', kontakt = '$kontakt', tlf = '$tlf', fax = '$fax', email = '$email', web = '$web', betalingsdage= '$betalingsdage', kreditmax = '$kreditmax', betalingsbet = '$betalingsbet', cvrnr = '$cvrnr', ean = '$ean', institution = '$institution', notes = '$notes', gruppe = '$gruppe', kontoansvarlig = '$kontoansvarlig'  where id = '$id'");
 	 	 	for ($x=1; $x<=$ans_ant; $x++) {
 	 	 	 	$y=trim($posnr[$x]);
 	 	 	 	if (($y)&&($y!="-")&&($ans_id[$x])){db_modify("update ansatte set posnr = '$y' where id = '$ans_id[$x]'");}
 	 	 	 	elseif (($y=="-")&&($ans_id[$x])){db_modify("delete from ansatte 	where id = '$ans_id[$x]'");}
 	 	 	 	else {print "<BODY onLoad=\"javascript:alert('Hint! Du skal s&aelig;tte et - (minus) som pos nr for at slette en kontaktperson')\">";}
 	 	 	}
 	 	}
 	} 
}

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\"$top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id accesskey=L>Luk</a></small></td>";
print "<td width=\"80%\"$top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>Debitorkort</small></td>";
print "<td width=\"10%\"$top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=debitorkort.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id accesskey=N>Ny</a><br></small></td>";
print "</tbody></table>";
print "</td></tr>";
print "<td align = center valign = center>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

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
# 	$kontakt=stripslashes(htmlentities(trim($r['kontakt'])));
 	$tlf=trim($r['tlf']);
 	$fax=trim($r['fax']);
 	$email=trim($r['email']);
 	$web=trim($r['web']);
 	$kreditmax=$r['kreditmax'];
 	$betalingsdage=$r['betalingsdage'];
 	$betalingsbet=trim($r['betalingsbet']);
 	$cvrnr=trim($r['cvrnr']);
 	$ean=trim($r['ean']);
 	$institution=stripslashes(htmlentities(trim($r['institution'])));
 	$notes=stripslashes(htmlentities(trim($r['notes'])));
 	$gruppe=trim($r['gruppe']);
	$kontoansvarlig=trim($r['kontoansvarlig']);
	if (!$kontoansvarlig) $kontoansvarlig='0';
}
else{
 	$id=0;
 	$betalingsdage=8;
 	$betalingsbet="Netto";
	$kontoansvarlig='0';
}
$kreditmax=dkdecimal($kreditmax);
print "<form name=debitorkort action=debitorkort.php method=post>";
print "<input type=hidden name=id value='$id'>";
print "<input type=hidden name=kontonr value='$kontonr'>";
print "<input type=hidden name=ordre_id value='$ordre_id'>";
print "<input type=hidden name=returside value='$returside'>";
print "<input type=hidden name=fokus value='$fokus'>";
print "<tr><td>$font Kundenr</td><td><input type=text size=25 name=ny_kontonr value=\"$kontonr\"></td>";
print "<td><br></td>";
print "<td>$font Navn</td><td><input type=text size=25 name=firmanavn value=\"$firmanavn\"></td></tr>";
print "<tr><td>$font Adresse</td><td><input type=text size=25 name=addr1 value=\"$addr1\"></td>";
print "<td><br></td>";
print "<td>$font Adresse2</td><td><input type=text size=25 name=addr2 value=\"$addr2\"></td></tr>";
print "<tr><td>$font Postnr</td><td><input type=text size=6 name=postnr value=\"$postnr\"></td>";
print "<td><br></td>";
print "<td>$font By</td><td><input type=text size=25 name=bynavn value=\"$bynavn\"></td></tr>";
print "<tr><td>$font Land</td><td><input type=text size=25 name=land value=\"$land\"></td>";
print "<td><br></td>";
print "<td>$font EAN - nr.</td><td><input type=text size=10 name=ean value=\"$ean\"></td></tr>";
print "<td>$font CVR. nr.</td><td><input type=text size=10 name=cvrnr value=\"$cvrnr\"></td>";
print "<td><br></td>";
print "<td>$font Institutionsnr.</td><td><input type=text size=10 name=institution value=\"$institution\"></td></tr>";
print "<tr><td>$font Telefon</td><td><input type=text size=10 name=tlf value=\"$tlf\"></td>";
print "<td><br></td>";
print "<td>$font Telefax</td><td><input type=text size=10 name=fax value=\"$fax\"></td></tr>";
print "<tr><td>$font e-mail</td><td><input type=text size=25 name=email value=\"$email\"></td>";
print "<td><br></td>";
print "<td>$font Hjemmeside</td><td><input type=text size=25 name=web value=\"$web\"></td></tr>";
print "<tr><td>$font Betalingsbetingelse</td>";
print "<td><SELECT NAME=betalingsbet>";
print "<option>$betalingsbet</option>";
if ($betalingsbet!='Forud') 	{print "<option>Forud</option>"; }
if ($betalingsbet!='Kontant') 	{print "<option>Kontant</option>"; }
if ($betalingsbet!='Efterkrav') 	{print "<option>Efterkrav</option>"; }
if ($betalingsbet!='Netto'){print "<option>Netto</option>"; }
if ($betalingsbet!='Lb. md.'){print "<option>Lb. md.</option>";}
if (($betalingsbet=='Kontant')||($betalingsbet=='Efterkrav')||($betalingsbet=='Forud')) {$betalingsdage='';}

elseif (!$betalingsdage) {$betalingsdage='Nul';}
if ($betalingsdage){
 	if ($betalingsdage=='Nul') {$betalingsdage=0;}
 	print "</SELECT>&nbsp;+<input type=text size=2 style=text-align:right 	name=betalingsdage value=\"$betalingsdage\"></td>";
} else print "</SELECT></td>";
print "<td><br></td>";
print "<td>$font Kreditmax</td><td><input type=text size=10 name=kreditmax value=\"$kreditmax\"></td></tr>";
print "<tr><td>$font Debitorgruppe</td>";
if (!$gruppe) {
	if (db_fetch_array(db_select("select id from grupper where art='DIV' and kodenr='2' and box1='on'"))) $gruppe='0';
	else $gruppe=1;
}	
$r = db_fetch_array(db_select("select beskrivelse from grupper where art='DG' and kodenr='$gruppe'"));
print "<td><SELECT NAME=gruppe value=\"$gruppe\">";
print "<option>$gruppe:$r[beskrivelse]</option>";
$q = db_select("select * from grupper where art='DG' and kodenr!='$gruppe' order by kodenr");

while ($r = db_fetch_array($q)){
 	 print "<option>$r[kodenr]:$r[beskrivelse]</option>";
}
print "</SELECT></td>";
print "<td><br></td>";
print "<td>$font Kundeansvarlig</td>";
	$r = db_fetch_array(db_select("select initialer from ansatte where id='$kontoansvarlig'"));
print "<td><SELECT NAME=kontoansvarlig value=\"$kontoansvarlig\">";
if ($r[initialer]) {
	$r = db_fetch_array(db_select("select initialer from ansatte where id='$kontoansvarlig'"));
	print "<option>$r[initialer]</option>";
}
print "<option></option>";
if ($r=db_fetch_array(db_select("select id from adresser where art='S'"))) $q = db_select("select id, initialer from ansatte where konto_id='$r[id]'");
while ($r = db_fetch_array($q)){
 	 print "<option>$r[initialer]</option>";
}
print "</SELECT></td>";

print "<tr><td valign=top>$font Bem&aelig;rkning</td><td colspan=7><textarea name=\"notes\" rows=\"3\" cols=\"85\">$notes</textarea></td></tr>";
#print "<tr><td>$font <a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id>Kontaktperson</a></td><td><br></td>";
if ($id) {
 	print "<tr><td></td><td>$font Pos. Kontakt</td><td>$font Lokalnr. / Mobil</td><td>$font E-mail</td><td>$font <a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id>Ny</a></td></tr>";
 	$x=0;
 	$q = db_select("select * from ansatte where konto_id = '$id' order by posnr");
 	while ($r = db_fetch_array($q)){
 	 	$x++;
 	 	if ($x > 0) {print "<tr><td><br></td>";}
 		print "<td><input type=text size=1 name=posnr[$x] value=\"$x\">$font &nbsp;<a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id&id=$r[id]>".stripslashes(htmlentities($r[navn]))."</a></td>";
 		print "<td>$font $r[tlf] / $r[mobil]</td><td>$font $r[email]</td></tr>";
 		print "<input type=hidden name=ans_id[$x] value=$r[id]>";
 		if ($x==1) {print "<input type=hidden name=kontakt value='$r[navn]'>";}
	}
	print "<input type=hidden name=ans_ant value=$x>";
	print "<tr><td><br></td></tr>";
}
print "<tr><td><br></td></tr>";

$q = db_select("select id from openpost where konto_id = '$id'");
if (db_fetch_array($q)) {$slet="NO";}
$q = db_select("select id from ordrer where konto_id = '$id'");
if (db_fetch_array($q)) {$slet="NO";}
$q = db_select("select id from ansatte where konto_id = '$id'");
if (db_fetch_array($q)) {$slet="NO";}
 	 	 
if ($slet=="NO") {print "<td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\"></td>";} 	 	 
else {print "<td><br><td align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\"></td><td><br></td><td><input type=submit accesskey=\"s\" value=\"Slet\" name=\"submit\"></td>";}
 
/*
$tidspkt=date("U");
$q = db_select("select * from ordrer where (art = 'DO' or art = 'DK') and konto_id = '$id' order by id");
   while ($row =db_fetch_array($q)) {
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
		 $ordredato=dkdato($row[ordredate]);
		 print "<td><small>$font$ordredato<br></small></td>";
		 print "<td><small>$font$row[kontonr]<br></small></td>";
		 print "<td><small>$font".stripslashes($row[firmanavn])."<br></small></td>";
		 print "<td><small>$font$row[ref]<br></small></td>";
		print "</tr>\n";
	 }
	 print "<tr><td><br></td><td><br></td><td align = center><a href='ordre.php?konto_id=$id&returside=ordreliste.php' target=_blank>Opret ny Ordre</a></td></tr>";

	 function bidrag ($sum,$kostpris){
		 global $font;
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
		 if ($genberegn) {print "<td align=right><small>$font<span title= 'db: $dk_db - dg: $dk_dg%'>$sum/$dk_db/$dk_dg%<br></small></td>";}
		 else {print "<td align=right><small>$font<span title= 'db: $dk_db - dg: $dk_dg%'>$sum<br></small></td>";}
	 }  
*/
?>
</tbody>
</table>
</td></tr>
<tr><td align = "center" valign = "bottom">
 	 	<table width="100%" align="center" border="1" cellspacing="0" cellpadding="0"><tbody>
 	 	 	<td width="100%" bgcolor="<?php print $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
 	 	</tbody></table>
</td></tr>
</tbody></table>
</body></html>
