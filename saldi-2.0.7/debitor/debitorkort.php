<?php
// ------------- debitor/debitorkort.php ----- (modul nr 6)------ lap 2.0.7 ----2009-05-18-----------
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

$modulnr=6;
$title="SALDI - debitorkort";
$css="../css/standard.css";

 include("../includes/connect.php");
 include("../includes/online.php");
 include("../includes/std_func.php");

 print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";

 $id = $_GET['id'];
 if($_GET['returside']){
 	$returside= $_GET['returside'];
 	$ordre_id = $_GET['ordre_id'];
 	$fokus = $_GET['fokus'];
} else {
	if ($popup) $returside="../includes/luk.php";
	else $returside="debitor.php";
}

if ($_POST){
 	$submit=addslashes(trim($_POST['submit']));
 	$id=$_POST['id'];
 	if ($submit!="Slet") {
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
 		$mailfakt=addslashes(trim($_POST['mailfakt']));
 		$web=addslashes(trim($_POST['web']));
 		$betalingsbet=addslashes(trim($_POST['betalingsbet']));
 		$cvrnr=addslashes(trim($_POST['cvrnr']));
 		$ean=addslashes(trim($_POST['ean']));
 		$institution=addslashes(trim($_POST['institution']));
 		$betalingsdage=$_POST['betalingsdage'];
 		$kreditmax=usdecimal($_POST['kreditmax']);
 		list ($gruppe) = split (':', $_POST['gruppe']);
		$kontoansvarlig=$_POST['kontoansvarlig'];
 		$bank_reg=$_POST['bank_reg'];
 		$bank_konto=$_POST['bank_konto'];
 		$pbs_nr=$_POST['pbs_nr'];
		$pbs=$_POST['pbs'];
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
 	
		if ($pbs) {
			if (!is_numeric($bank_reg)||strlen($bank_reg)!=4) {
				$pbs="";	
				print "<BODY onLoad=\"javascript:alert('Bank reg skal bestå af et tal på 4 cifre for at PBS kan aktiveres')\">";
			} elseif (!is_numeric($bank_konto)||strlen($bank_konto)!=10) {
				$pbs="";	
				print "<BODY onLoad=\"javascript:alert('Bank konto skal bestå af et tal på 10 cifre for at PBS kan aktiveres')\">";
			} elseif (!is_numeric($cvrnr)||strlen($cvrnr)!=8) {
				$pbs="";	
				print "<BODY onLoad=\"javascript:alert('CVR nr skal bestå af et tal på 8 cifre for at PBS kan aktiveres')\">";
			}
		}
		
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
 			print "<BODY onLoad=\"javascript:alert('Kontonummer $ny_kontonr tildelt automatisk')\">";
		}
 	
############################
 		if(!$betalingsdage){$betalingsdage=0;}
 	 	if(!$kreditmax){$kreditmax=0;}
 	 	if ($id==0) {
 	 	 	$q = db_select("select id from adresser where kontonr = '$ny_kontonr' and art = 'D'");
 	 	 	$r = db_fetch_array($q);
 	 	 	if ($r[id]) {
				print "<BODY onLoad=\"javascript:alert('Der findes allerede en debitor med Kontonr: $ny_kontonr')\">";
 	 	 	 	$id=0;
 	 	 	}
 	 	 	elseif($ny_kontonr) {
				$oprettet=date("Y-m-d");
 	 	 	 	db_modify("insert into adresser (kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf, fax, email, mailfakt, web, betalingsdage, kreditmax, betalingsbet, cvrnr, ean, institution, notes, art, gruppe, kontoansvarlig, oprettet,bank_reg,bank_konto,pbs_nr,pbs) values ('$ny_kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$kontakt', '$tlf', '$fax', '$email','$mailfakt', '$web', '$betalingsdage', '$kreditmax', '$betalingsbet', '$cvrnr', '$ean', '$institution', '$notes', 'D', '$gruppe', '$kontoansvarlig', '$oprettet','$bank_reg','$bank_konto','$pbs_nr','$pbs')");
 	 	 	 	$q = db_select("select id from adresser where kontonr = '$ny_kontonr' and art = 'D'");
 	 	 	 	$r = db_fetch_array($q);
 	 	 	 	$id = $r[id];
				if ($kontakt) db_modify("insert into ansatte(konto_id, navn) values ('$id', '$kontakt')"); 
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
 	 	 	db_modify("update adresser set kontonr = '$kontonr', 	firmanavn = '$firmanavn', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', land = '$land', kontakt = '$kontakt', tlf = '$tlf', fax = '$fax', email = '$email', mailfakt = '$mailfakt', web = '$web', betalingsdage= '$betalingsdage', kreditmax = '$kreditmax', betalingsbet = '$betalingsbet', cvrnr = '$cvrnr', ean = '$ean', institution = '$institution', notes = '$notes', gruppe = '$gruppe', kontoansvarlig = '$kontoansvarlig',bank_reg='$bank_reg',bank_konto='$bank_konto', pbs_nr = '$pbs_nr', pbs = '$pbs'  where id = '$id'");
 	 	 	for ($x=1; $x<=$ans_ant; $x++) {
 	 	 	 	$y=trim($posnr[$x]);
 	 	 	 	if ($y && is_numeric($y) && $ans_id[$x]) db_modify("update ansatte set posnr = '$y' where id = '$ans_id[$x]'");
 	 	 	 	elseif (($y=="-")&&($ans_id[$x])){db_modify("delete from ansatte 	where id = '$ans_id[$x]'");}
 	 	 	 	else {print "<BODY onLoad=\"javascript:alert('Hint! Du skal s&aelig;tte et - (minus) som pos nr for at slette en kontaktperson')\">";}
 	 	 	}
 	 	}
	}	else {
		db_modify("delete from adresser where id = $id");
 		print "<meta http-equiv=\"refresh\" content=\"0;URL=debitor.php?returside=$returside&ordre_id=$ordre_id&id=$konto_id&fokus=$fokus\">";
 	} 
}
$tekst=findtekst(154,$sprog_id);
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
if ($popup) print "<td onClick=\"JavaScript:opener.location.reload();\" width=\"10%\" $top_bund><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L>Luk</a></td>";
else print "<td $top_bund><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L>Luk</a></td>";
print "<td width=\"80%\"$top_bund>Debitorkort</td>";
print "<td width=\"10%\"$top_bund><a href=\"javascript:confirmClose('debitorkort.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=N>Ny</a><br></td>";
print "</tbody></table>";
print "</td></tr>";
print "<td align = center valign = center>";
print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";

if ($id > 0){
 	$q = db_select("select * from adresser where id = '$id'");
 	$r = db_fetch_array($q);
 	$kontonr=trim($r['kontonr']);
 	$firmanavn=htmlentities(trim($r['firmanavn']),ENT_COMPAT,$charset);
 	$addr1=htmlentities(trim($r['addr1']),ENT_COMPAT,$charset);
 	$addr2=htmlentities(trim($r['addr2']),ENT_COMPAT,$charset);
 	$postnr=trim($r['postnr']);
 	$bynavn=htmlentities(trim($r['bynavn']),ENT_COMPAT,$charset);
 	$land=htmlentities(trim($r['land']),ENT_COMPAT,$charset);
# 	$kontakt=htmlentities(trim($r['kontakt']));
 	$tlf=trim($r['tlf']);
 	$fax=trim($r['fax']);
 	$email=trim($r['email']);
 	$mailfakt=trim($r['mailfakt']);
 	$web=trim($r['web']);
 	$kreditmax=$r['kreditmax'];
 	$betalingsdage=$r['betalingsdage'];
 	$betalingsbet=trim($r['betalingsbet']);
 	$cvrnr=trim($r['cvrnr']);
 	$ean=trim($r['ean']);
 	$institution=htmlentities(trim($r['institution']),ENT_COMPAT,$charset);
 	$notes=htmlentities(trim($r['notes']),ENT_COMPAT,$charset);
 	$gruppe=trim($r['gruppe']);
	$bank_konto=trim($r['bank_konto']);
	$bank_reg=trim($r['bank_reg']);
	if ($r['pbs']=='on') $pbs="checked";
	$pbs_nr=trim($r['pbs_nr']);
	$pbs_date=trim($r['pbs_date']);
	$kontoansvarlig=trim($r['kontoansvarlig']);
	if (!$kontoansvarlig) $kontoansvarlig='0';
}
else{
 	$id=0;
 	$betalingsdage=8;
 	$betalingsbet="Netto";
	$kontoansvarlig='0';
	$kontonr=if_isset($_GET['kontonr']);
	$firmanavn=if_isset($_GET['firmanavn']);
	$addr1=if_isset($_GET['addr1']);
	$addr2=if_isset($_GET['addr2']);
	$postnr=if_isset($_GET['postnr']);
	$bynavn=if_isset($_GET['bynavn']);
	$land=if_isset($_GET['land']);
	$kontakt=if_isset($_GET['kontakt']);
	$tlf=if_isset($_GET['tlf']);
	print "<BODY onLoad=\"javascript:docChange = true;\">";
}
$kreditmax=dkdecimal($kreditmax);
print "<form name=debitorkort action=debitorkort.php method=post>";
print "<input type=hidden name=id value='$id'>";
print "<input type=hidden name=kontonr value='$kontonr'>";
print "<input type=hidden name=ordre_id value='$ordre_id'>";
print "<input type=hidden name=returside value='$returside'>";
print "<input type=hidden name=fokus value='$fokus'>";
print "<input type=hidden name=kontakt value='$kontakt'>";
print "<input type=hidden name=pbs_date value='$pbs_date'>";
print "<input type=hidden name=pbs_nr value='$pbs_nr'>";
print "<input type=hidden name=gl_pbs_nr value='$pbs_nr'>";
#print "<input type=hidden name=pbs value='$pbs'>";

print "<tr bgcolor=$bgcolor5><td> Kundenr</td><td><input type=text size=25 name=ny_kontonr value=\"$kontonr\" onchange=\"javascript:docChange = true;\"></td>";
print "<td></td><td> CVR. nr.</td><td><input type=text size=10 name=cvrnr value=\"$cvrnr\" onchange=\"javascript:docChange = true;\"></td></tr>";
print "<tr><td> Navn</td><td><input type=text size=25 name=firmanavn value=\"$firmanavn\" onchange=\"javascript:docChange = true;\"></td>";
print "<td></td><td>Telefon</td><td><input type=text size=10 name=tlf value=\"$tlf\" onchange=\"javascript:docChange = true;\"></td></tr>";
print "<tr bgcolor=$bgcolor5><td>Adresse</td><td><input type=text size=25 name=addr1 value=\"$addr1\" onchange=\"javascript:docChange = true;\"></td>";
print "<td></td><td>Telefax</td><td><input type=text size=10 name=fax value=\"$fax\" onchange=\"javascript:docChange = true;\"></td></tr>";
print "<tr><td> Adresse2</td><td><input type=text size=25 name=addr2 value=\"$addr2\" onchange=\"javascript:docChange = true;\"></td>";
print "<td></td><td>EAN - nr.</td><td><input type=text size=10 name=ean value=\"$ean\" onchange=\"javascript:docChange = true;\"></td></tr>";
print "<tr bgcolor=$bgcolor5><td> Postnr/By</td><td><input type=text size=3 name=postnr value=\"$postnr\" onchange=\"javascript:docChange = true;\">";
print "<input type=text size=19 name=bynavn value=\"$bynavn\" onchange=\"javascript:docChange = true;\"></td>";
print "<td></td><td>Institutionsnr.</td><td><input type=text size=10 name=institution value=\"$institution\" onchange=\"javascript:docChange = true;\"></td></tr>";
print "<tr><td> Land</td><td><input type=text size=25 name=land value=\"$land\" onchange=\"javascript:docChange = true;\"></td>";
print "<td></td><td>Kreditmax</td><td><input type=text size=10 name=kreditmax value=\"$kreditmax\"></td></tr>";
if ($email) {
	if ($mailfakt) $mailfakt="checked";
	print "<tr bgcolor=$bgcolor5><td>e-mail / brug mail</td><td><input type=text size=22 name=email value=\"$email\" onchange=\"javascript:docChange = true;\">";
	print "<span title=\"Afmærk her hvis modtageren skal modtage tilbud, ordrer, fakturaer & rykker pr mail\"><input type=checkbox name=mailfakt $mailfakt></span></td>";
} else print "<tr bgcolor=$bgcolor5><td>e-mail</td><td><input type=text size=25 name=email value=\"$email\" onchange=\"javascript:docChange = true;\"></td>";
	 
print "<td></td><td>Bank reg.</td><td><input type=text size=10 name=bank_reg value=\"$bank_reg\"></td></tr>";
print "<tr><td>Hjemmeside</td><td><input type=text size=25 name=web value=\"$web\" onchange=\"javascript:docChange = true;\"></td>";
print "<td></td><td>Bank konto</td><td><input type=text size=10 name=bank_konto value=\"$bank_konto\"></td></tr>";
print "<tr bgcolor=$bgcolor5><td>Betalingsbetingelse</td>";
print "<td><SELECT NAME=betalingsbet onchange=\"javascript:docChange = true;\" >";
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
 	print "</SELECT>&nbsp;+<input type=text size=2 style=text-align:right name=betalingsdage value=\"$betalingsdage\" onchange=\"javascript:docChange = true;\"></td>";
} else print "</SELECT></td>";
if ($pbs) print "<td></td><td>PBS / nr</td><td><input type=checkbox name=pbs $pbs>&nbsp;$pbs_nr</td></tr>";
else print "<td></td><td>PBS</td><td><input type=checkbox name=pbs $pbs></td></tr>";
print "<tr><td> Debitorgruppe</td>";
if (!$gruppe) {
	if (db_fetch_array(db_select("select id from grupper where art='DIV' and kodenr='2' and box1='on'"))) $gruppe='0';
	else $gruppe=1;
}	
$r = db_fetch_array(db_select("select beskrivelse from grupper where art='DG' and kodenr='$gruppe'"));
print "<td><SELECT NAME=gruppe value=\"$gruppe\"  onchange=\"javascript:docChange = true;\">";
print "<option>$gruppe:$r[beskrivelse]</option>";
$q = db_select("select * from grupper where art='DG' and kodenr!='$gruppe' order by kodenr");

while ($r = db_fetch_array($q)){
 	 print "<option>$r[kodenr]:$r[beskrivelse]</option>";
}
print "</SELECT></td>";
print "<td><br></td>";
print "<td> Kundeansvarlig</td>";
	$r = db_fetch_array(db_select("select initialer from ansatte where id='$kontoansvarlig'"));
print "<td><SELECT NAME=kontoansvarlig value=\"$kontoansvarlig\"  onchange=\"javascript:docChange = true;\">";
if ($r[initialer]) {
	$r = db_fetch_array(db_select("select initialer from ansatte where id='$kontoansvarlig'"));
	print "<option>$r[initialer]</option>";
}
print "<option></option>";
if ($r=db_fetch_array(db_select("select id from adresser where art='S'"))) $q = db_select("select id, initialer from ansatte where konto_id='$r[id]'");
while ($r = db_fetch_array($q)){
 	 print "<option>$r[initialer]</option>";
}
print "</SELECT></td></tr>";

print "<tr><td valign=top> Bem&aelig;rkning</td><td colspan=7><textarea name=\"notes\" rows=\"3\" cols=\"85\">$notes</textarea></td></tr>";
#print "<tr><td> <a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id>Kontaktperson</a></td><td><br></td>";
if ($id) {
print "</tbody></table><table><tbody>";
 	print "<tr bgcolor=$bgcolor5><td>Pos.</td><td>Kontakt</td><td> Lokalnr</td><td>Mobil</td><td> E-mail</td><td> <a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id>Ny</a></td></tr>";
 	$x=0;
 	$q = db_select("select * from ansatte where konto_id = '$id' order by posnr");
 	while ($r = db_fetch_array($q)){
 	 	$x++;
 	 	print "<tr>";
 		print "<td width=10><input type=text size=1 name=posnr[$x] value=\"$x\"></td><td><a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id&id=$r[id]>".htmlentities($r['navn'],ENT_COMPAT,$charset)."</a></td>";
 		print "<td>$r[tlf]</td><td>$r[mobil]</td><td> $r[email]</td></tr>";
 		print "<input type=hidden name=ans_id[$x] value=$r[id]>";
 		if ($x==1) {print "<input type=hidden name=kontakt value='$r[navn]'>";}
	}
	print "<input type=hidden name=ans_ant value=$x>";
	print "<tr><td><br></td></tr>";
}
print "<tr><td><br></td></tr>";

$q = db_select("select id from openpost where konto_id = '$id'");
if (db_fetch_array($q)) $slet="NO";
$q = db_select("select id from ordrer where konto_id = '$id'");
if (db_fetch_array($q)) $slet="NO";
$q = db_select("select id from ansatte where konto_id = '$id'");
if (db_fetch_array($q)) $slet="NO";
 	 	 
if ($slet=="NO") {print "<td colspan=5 align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>";} 	 	 
else {print "<td><br><td align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td><td><br></td><td><input type=submit accesskey=\"s\" value=\"Slet\" name=\"submit\" onclick=\"return confirm('Slet $firmanavn?')\"></td>";}
print "</form>";
print "<tr><td colspan=5><hr></td></tr>";

print "</tbody></table></td></tr><tr><td align = \"center\" valign = \"bottom\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"1\" cellpadding=\"0\"><tbody>";
print "<td width=\"35%\" $top_bund>&nbsp;</td>";
$tekst=findtekst(130,$sprog_id);
if ($popup) print "<td width=\"10%\" $top_bund onClick=\"javascript:historik=window.open('historikkort.php?id=$id&returside=../includes/luk.php','historik','".$jsvars."');historik.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(131,$sprog_id)."</td>\n";
elseif ($returside!="historikkort.php") print "<td width=\"10%\" $top_bund title=\"$tekst\"><a href=historikkort.php?id=$id&returside=debitorkort.php>".findtekst(131,$sprog_id)."</td>\n";
else print "<td width=\"10%\" $top_bund title=\"$tekst\"><a href=historikkort.php?id=$id>".findtekst(131,$sprog_id)."</td>\n";
$tekst=findtekst(132,$sprog_id);
if ($popup) print "<td width=\"10%\" $top_bund onClick=\"javascript:kontokort=window.open('rapport.php?rapportart=kontokort&kontonr=$kontonr','kontokort','".$jsvars."');kontokort.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(133,$sprog_id)."</td>\n";
else print "<td width=\"10%\" $top_bund  title=\"$tekst\"><a href=rapport.php?rapportart=kontokort&kontonr=$kontonr&returside=../debitor/debitorkort.php?id=$id>".findtekst(133,$sprog_id)."</td>\n";
$tekst=findtekst(129,$sprog_id);
if (substr($rettigheder,5,1)=='1') {
	if ($popup) print "<td width=\"10%\" $top_bund onClick=\"javascript:d_ordrer=window.open('ordreliste.php?kontonumre=$kontonr&valg=faktura','d_ordrer','".$jsvars."');d_ordrer.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(134,$sprog_id)."</td>\n";
	else print "<td width=\"10%\" $top_bund  title=\"$tekst\"><a href=ordreliste.php?kontonumre=$kontonr&valg=faktura&returside=../debitor/debitorkort.php?id=$id>".findtekst(134,$sprog_id)."</td>\n";
} else print "<td width=\"10%\" $stor_knap_bg><span style=\"color:#999;\">".findtekst(134,$sprog_id)."</span></td>\n";
print "<td width=\"35%\" $top_bund>&nbsp;</td>";
print "</td></tbody></table></td></tr></tbody></table>";
?>
</body></html>
