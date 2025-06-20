<?php
// ----------kreditor/kreditorkort.php---(modul nr. 8)----------------lap 3.5.0---2015.01.22---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2013.02.24 Tilføjet kontofusion
// 2014.03.19 addslashes erstattet med db_escape_string
// 2014.05.27 Tilføjet bet.type SDC 3 - overførsel med kort advisering (ca)
// 2014.05.27 Tilføjet bet.type SDCK020 - FI-kort 71 (SDC) (ca)
// 2015.01.23 Indhente virksomhedsdata fra CVR via CVRapi - tak Niels Rune https://github.com/nielsrune


@session_start();
$s_id=session_id();

$modulnr=8;
$title="Kreditorkort\n";
$css="../css/standard.css";

include("../includes/var_def.php");	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
# window.onbeforeunload = confirmBrowseAway;

print "<script LANGUAGE=\"JavaScript\" type=\"text/javascript\" src=\"../javascript/overlib.js\"></script>";
print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";

$id=isset($_GET['id'])? $_GET['id']:Null;

if($_GET['returside']){
 	$returside= $_GET['returside'];
 	$ordre_id = $_GET['ordre_id'];
 	$fokus = $_GET['fokus'];
} else {
	if ($popup) $returside="../includes/luk.php";
	else $returside="kreditor.php";
}

if ($_POST) {
	$submit=trim($_POST['submit']);
	$id=$_POST['id'];
	if ($submit!="Slet") {
		$kontonr=$_POST['kontonr'];
		$ny_kontonr=db_escape_string(trim($_POST['ny_kontonr']));
		$firmanavn=db_escape_string(trim($_POST['firmanavn']));
		$addr1=db_escape_string(trim($_POST['addr1']));
		$addr2=db_escape_string(trim($_POST['addr2']));
		$postnr=trim($_POST['postnr']);
		$bynavn=db_escape_string(trim($_POST['bynavn']));
		$land=db_escape_string(trim($_POST['land']));
		$kontakt=db_escape_string(trim(isset($_POST['kontakt'])? $_POST['kontakt']:NULL));
		$tlf=trim($_POST['tlf']);
		$fax=trim($_POST['fax']);
		$email=trim($_POST['email']);
		$web=trim($_POST['web']);
		$betalingsbet=$_POST['betalingsbet'];
		$cvrnr=trim($_POST['cvrnr']);
		$betalingsdage=$_POST['betalingsdage'];
		$bank_navn=trim($_POST['bank_navn']);
		$bank_reg=trim($_POST['bank_reg']);
		$bank_konto=trim($_POST['bank_konto']);
		$bank_fi=trim($_POST['bank_fi']);
		list($erh) = explode(" = ",$_POST['erh']);
		$swift=trim($_POST['swift']);
		$kreditmax=usdecimal($_POST['kreditmax']);
		list ($gruppe) = explode (':', $_POST['gruppe']);
		$notes=db_escape_string(trim($_POST['notes']));
		$ordre_id=$_POST['ordre_id'];
		$returside=$_POST['returside'];
		$fokus=$_POST['fokus'];
		$posnr=isset($_POST['posnr'])? $_POST['posnr']:NULL;
		$ans_id=isset($_POST['ans_id'])? $_POST['ans_id']:NULL;
		$ans_ant=isset($_POST['ans_ant'])? $_POST['ans_ant']:NULL;	
		$felt_1 = db_escape_string(trim($_POST['felt_1']));
		$felt_2 = db_escape_string(trim($_POST['felt_2']));
		$felt_3 = db_escape_string(trim($_POST['felt_3']));
		$felt_4 = db_escape_string(trim($_POST['felt_4']));
		$felt_5 = db_escape_string(trim($_POST['felt_5']));
		$lukket=db_escape_string(trim($_POST['lukket']));

		
		if (substr($ny_kontonr,0,1)=="=") {
			$ny_kontonr=str_replace("=","",$ny_kontonr);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=kontofusion.php?returside=$returside&ordre_id=$ordre_id&id=$id&fokus=$fokus&kontonr=$ny_kontonr\">\n";
			exit;
		}
		######### Tjekker om kontonr er integer
		$temp=str_replace(" ","",$ny_kontonr);
		$tmp2='';
		for ($x=0; $x<strlen($temp); $x++) {
			$y=substr($temp,$x,1);
			if ((ord($y)<48)||(ord($y)>57)) $y=0;
			$tmp2=$tmp2.$y;
		}
		$tmp2=$tmp2*1;
		if ($tmp2!=$ny_kontonr) {print "<BODY onLoad=\"javascript:alert('Kontonummer m&aring; kun best&aring; af heltal uden mellemrum')\">\n";}
		$ny_kontonr=$tmp2;
	
 
		## Tildeler aut kontonr hvis det ikke er angivet
		if (($firmanavn)&&(($ny_kontonr < 1)||(!$ny_kontonr))) {
			if (!$id) $id="0";
			$x=0;
			$query = db_select("select kontonr from adresser where art = 'K'	and id != $id order by kontonr",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				$x++;
				$ktoliste[$x]=$row[kontonr];
			}
			$ny_kontonr=1000;
			while(in_array($ny_kontonr, $ktoliste)) $ny_kontonr++;
			print "<BODY onLoad=\"javascript:alert('Kontonummer $ny_kontonr tildelt automatisk')\">\n";
		} 
		if ($postnr && !$bynavn) $bynavn=bynavn($postnr);

############################
		if(!$kreditmax) $kreditmax=0;
		if(!$betalingsdage) $betalingsdage=0;
		if ($id==0) {
			$query = db_select("select id from adresser where kontonr = '$ny_kontonr' and art = 'K'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			if ($row[id]) {
			 print "<BODY onLoad=\"javascript:alert('Der findes allerede en kreditor med Leverand&oslash;rnr: $ny_kontonr')\">\n";
				$id=0;
			} elseif($ny_kontonr) {
				db_modify("insert into adresser (kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,tlf,fax,email,web,betalingsdage,kreditmax,betalingsbet,cvrnr,notes,art,gruppe,bank_navn,bank_reg,bank_konto,bank_fi,erh,swift,felt_1,felt_2,felt_3,felt_4,felt_5,lukket) values ('$ny_kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$tlf','$fax','$email','$web','$betalingsdage','$kreditmax','$betalingsbet','$cvrnr','$notes','K',$gruppe,'$bank_navn','$bank_reg','$bank_konto','$bank_fi','$erh','$swift','$felt_1','$felt_2','$felt_3','$felt_4','$felt_5','$lukket')",__FILE__ . " linje " . __LINE__);
				$query = db_select("select id from adresser where kontonr = '$ny_kontonr' and art = 'K'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$id = $row[id];
			}
		} elseif ($id > 0) {
			if ($ny_kontonr!=$kontonr) {
				$query = db_select("select kontonr from adresser where art = 'K' order by kontonr",__FILE__ . " linje " . __LINE__);
				while ($row = db_fetch_array($query)) {
					$x++;
					$ktoliste[$x]=$row['kontonr'];
				}
				if (in_array($ny_kontonr, $ktoliste)) {
					 print "<BODY onLoad=\"javascript:alert('Kontonummer findes allerede, ikke &aelig;ndret')\">\n";
				} else $kontonr=$ny_kontonr;
			}
			db_modify("update adresser set kontonr = '$kontonr',	firmanavn = '$firmanavn', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', land = '$land', kontakt = '$kontakt', tlf = '$tlf', fax = '$fax', email = '$email', web = '$web', betalingsdage= '$betalingsdage', kreditmax = '$kreditmax', betalingsbet = '$betalingsbet', cvrnr = '$cvrnr', notes = '$notes', gruppe = '$gruppe' ,bank_navn='$bank_navn',bank_reg='$bank_reg',bank_konto='$bank_konto',bank_fi='$bank_fi',erh='$erh',swift='$swift',felt_1='$felt_1',felt_2='$felt_2',felt_3='$felt_3',felt_4='$felt_4',felt_5='$felt_5',lukket='$lukket' where id = '$id'",__FILE__ . " linje " . __LINE__);
			for ($x=1; $x<=$ans_ant; $x++) {
				 $y=trim($posnr[$x]);
				 if ($y && is_numeric($y) && $ans_id[$x]) db_modify("update ansatte set posnr = '$y' where id = '$ans_id[$x]'",__FILE__ . " linje " . __LINE__);
				 elseif (($y=="-")&&($ans_id[$x])) db_modify("delete from ansatte	where id = '$ans_id[$x]'",__FILE__ . " linje " . __LINE__);
			 	else print "<BODY onLoad=\"javascript:alert('Hint!	Du skal s&aelig;tte et - (minus) som pos nr for at slette en kontaktperson')\">\n";
			}
		}
	}	else {
		if(db_fetch_array(db_select("select vare_id from vare_lev where lev_id = '$id'",__FILE__ . " linje " . __LINE__))) {
			print "<BODY onLoad=\"javascript:alert('Det er tilknyttet varer til denne kreditor')\">\n";
		} else {
			db_modify("delete from adresser where id = $id",__FILE__ . " linje " . __LINE__);
			$id=0;
		}
	}
}
$tekst=findtekst(154,$sprog_id);
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";#tabel 1 start
print "<tr bgcolor=$bg><td colspan=\"3\" align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";#tabel 1.1 start
if ($popup) print "<td onClick=\"JavaScript:opener.location.reload();\" width=\"10%\" $top_bund><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L>Luk</a></td>";
else print "<td $top_bund><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">SALDI - Kreditorkort</td>\n";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=\"javascript:confirmClose('kreditorkort.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=N>Ny</a><br></td>\n";
print "</tbody></table>\n";#tabel 1.1 slut
print "</td></tr>\n";
print "<td></td><td align = center valign = center>\n";
print "<table cellpadding=\"0\" cellspacing=\"10\" border=\"1\"><tbody>\n";#tabel 1.2 start

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
	$kontakt=htmlentities(trim($r['kontakt']),ENT_COMPAT,$charset);
	$tlf=trim($r['tlf']);
	$fax=trim($r['fax']);
	$email=trim($r['email']);
	$web=trim($r['web']);
	$kreditmax=$r['kreditmax'];
	$betalingsdage=$r['betalingsdage'];
	$betalingsbet=trim($r['betalingsbet']);
	$bank_navn=trim($r['bank_navn']);
	$bank_reg=trim($r['bank_reg']);
	$bank_konto=trim($r['bank_konto']);
	$bank_fi=trim($r['bank_fi']);
	$erh=trim($r['erh']);
	$swift=trim($r['swift']);
	$cvrnr=trim($r['cvrnr']);
	$notes=htmlentities(trim($r['notes']),ENT_COMPAT,$charset);
	$gruppe=trim($r['gruppe']);
	$felt_1 = htmlentities(trim($r['felt_1']),ENT_COMPAT,$charset);
	$felt_2 = htmlentities(trim($r['felt_2']),ENT_COMPAT,$charset);
	$felt_3 = htmlentities(trim($r['felt_3']),ENT_COMPAT,$charset);
	$felt_4 = htmlentities(trim($r['felt_4']),ENT_COMPAT,$charset);
	$felt_5 = htmlentities(trim($r['felt_5']),ENT_COMPAT,$charset);
	($r['lukket']) ? $lukket='checked' : $lukket='';
}else	{
	$id=0;
	$betalingsdage=8;
	$betalingsbet="Netto\n";
}

#		$addr1=htmlentities(stripslashes(trim($r['addr1'])));

$kreditmax=dkdecimal($kreditmax);
print "<form name=kreditorkort action=kreditorkort.php method=post>\n";
print "<input type=hidden name=id value='$id'>\n";
print "<input type=hidden name=kontonr value='$kontonr'>\n";
print "<input type=hidden name=ordre_id value='$ordre_id'>\n";
print "<input type=hidden name=returside value='$returside'>\n";
print "<input type=hidden name=fokus value='$fokus'>\n";
print "<tr bgcolor=$bg><td valign=\"top\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n"; # tabel 1.2.1 ->
$bg=$bgcolor5;
print "<tr bgcolor=$bg><td>Leverand&oslash;rnr</td><td><input class=\"inputbox\" type=text size=25 name=ny_kontonr value=\"$kontonr\" onchange=\"javascript:docChange = true;\" title=\"Tast CVR-nr. omsluttet af *, +, eller / for at importere data fra Erhvervsstyrelsen (Data leveres af CVR API)\" style=\"background-image: url('../img/search-white.png'); background-repeat: no-repeat; background-position: right;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Navn</td><td><input class=\"inputbox\" type=text size=25 name=firmanavn value=\"$firmanavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Adresse</td><td><input class=\"inputbox\" type=text size=25 name=addr1 value=\"$addr1\"  onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Adresse2</td><td><input class=\"inputbox\" type=text size=25 name=addr2 value=\"$addr2\" onchange=\"javascript:docChange = true;\" ></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Postnr/By</td><td><input class=\"inputbox\" type=text size=3 name=postnr value=\"$postnr\" onchange=\"javascript:docChange = true;\">";
print "<input class=\"inputbox\" type=text size=18 name=bynavn value=\"$bynavn\" onchange=\"javascript:docChange = true;\"></td></tr>";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Land</td><td><input class=\"inputbox\" type=text size=25 name=land value=\"$land\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>e-mail</td><td><input class=\"inputbox\" type=text size=25 name=email value=\"$email\" onchange=\"javascript:docChange = true;\"></td>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Hjemmeside</td><td><input class=\"inputbox\" type=text size=25 name=web value=\"$web\" onchange=\"javascript:docChange = true;\"></td>\n";	
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td> Betalingsbetingelse</td>\n";
print "<td><SELECT NAME=betalingsbet onchange=\"javascript:docChange = true;\">\n";
print "<option>$betalingsbet</option>\n";
if ($betalingsbet!='Forud') print "<option>Forud</option>\n";
if ($betalingsbet!='Kontant') print "<option>Kontant</option>\n";
if ($betalingsbet!='Efterkrav') print "<option>Efterkrav</option>\n";
if ($betalingsbet!='Netto') print "<option>Netto</option>\n";
if ($betalingsbet!='Lb. md.') print "<option>Lb. md.</option>\n";
if (($betalingsbet=='Kontant')||($betalingsbet=='Efterkrav')||($betalingsbet=='Forud')) $betalingsdage='';
elseif (!$betalingsdage) $betalingsdage='Nul';
if ($betalingsdage){
	if ($betalingsdage=='Nul') $betalingsdage=0;
	print "</SELECT>&nbsp;+<input class=\"inputbox\" type=text size=2 style=text-align:right	name=betalingsdage value=\"$betalingsdage\" onchange=\"javascript:docChange = true;\"></td>\n";
} 
print "</td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Kreditorgruppe</td>\n";
if (!$gruppe) {$gruppe=1;}
$q = db_select("select beskrivelse from grupper where art='KG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__);
$r = db_fetch_array($q);
print "<td><SELECT NAME=gruppe value=\"$gruppe\"  onchange=\"javascript:docChange = true;\">\n";
print "<option>$gruppe:$r[beskrivelse]</option>\n";
$q = db_select("select * from grupper where art='KG' and kodenr!='$gruppe' order by kodenr",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)){
	print "<option>$r[kodenr]:$r[beskrivelse]</option>\n";
}
print "</SELECT></td></tr>\n";
print "</tbody></table></td>";#  <- tabel 1.2.1 
print "<td  valign=\"top\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n"; # tabel 1.2.2 ->
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td width=\"25%\"> CVR-nr.</td><td width=\"75%\"><input class=\"inputbox\" type=text size=\"10\" name=cvrnr value=\"$cvrnr\" onchange=\"javascript:docChange = true;\" title=\"Tast CVR-nr. omsluttet af *, +, eller / for at importere data fra Erhvervsstyrelsen (Data leveres af CVR API)\" style=\"background-image: url('../img/search-white.png'); background-repeat: no-repeat; background-position: right;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td> Telefon</td><td><input class=\"inputbox\" type=text size=\"10\" name=tlf value=\"$tlf\" onchange=\"javascript:docChange = true;\" title=\"Tast telefonnr. omsluttet af *, +, eller / for at importere data fra Erhvervsstyrelsen (Data leveres af CVR API)\" style=\"background-image: url('../img/search-white.png'); background-repeat: no-repeat; background-position: right;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td> Telefax</td><td><input class=\"inputbox\" type=text size=\"10\" name=fax value=\"$fax\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td> Bank</td><td><input class=\"inputbox\" type=\"text\" name=\"bank_navn\" size=\"10\" value=\"$bank_navn\"  onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td> Reg.nr</td><td><input class=\"inputbox\" type=\"text\" name=\"bank_reg\" size=\"10\" value=\"$bank_reg\"  onchange=\"javascript:docChange = true;\"></td>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td><span title=\"For udenlandske kreditorer skrives IBAN nummer her\"> Konto</span></td><td><input class=\"inputbox\" type=\"text\" name=\"bank_konto\" size=\"10\" value=\"$bank_konto\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td><span title=\"Anvendes kun ved udenlandske kreditorer som ikke har et IBAN nr.\"> SWIFT nr</span></td><td><input class=\"inputbox\" type=\"text\" name=\"swift\" size=\"10\" value=\"$swift\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>FI&nbsp;kreditor&nbsp;nr.</td><td><input class=\"inputbox\" type=\"text\" name=\"bank_fi\" size=\"10\" value=\"$bank_fi\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Kreditmax</td><td><input class=\"inputbox\" type=text size=10 name=kreditmax value=\"$kreditmax\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Lukket</td><td><input class=\"inputbox\" type=checkbox name=lukket $lukket></td></tr>";
print "</tbody></table></td>";#  <- tabel 1.2.1 
print "<td valign=\"top\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n"; # tabel 1.2.2 ->
print "<tr bgcolor=$bg><td colspan=2 height=25px align=center><b>".findtekst(301,$sprog_id)."</b></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('".findtekst(307,$sprog_id)."', WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(302,$sprog_id)."</td><td><input class=\"inputbox\" type=text name=\"felt_1\" size=\"25\" value=\"$felt_1\"></span></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('".findtekst(307,$sprog_id)."', WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(303,$sprog_id)."</td><td><input class=\"inputbox\" type=text name=\"felt_2\" size=\"25\" value=\"$felt_2\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('".findtekst(309,$sprog_id)."', WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(304,$sprog_id)."</td><td><input type=text class=\"inputbox\" name=\"felt_3\" size=\"25\" value=\"$felt_3\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('".findtekst(310,$sprog_id)."', WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(305,$sprog_id)."</td><td><input class=\"inputbox\" type=text name=\"felt_4\" size=\"25\" value=\"$felt_4\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('".findtekst(311,$sprog_id)."', WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(306,$sprog_id)."</td><td><input type=text class=\"inputbox\" name=\"felt_5\" size=\"25\" value=\"$felt_5\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td height=\"24px\" colspan=\"2\"><br></td></tr>";	
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td height=\"24px\" colspan=\"2\"><br></td></tr>";	
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td height=\"24x\" colspan=\"2\"><br></td></tr>";	
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>ERH&nbsp;kode</td>";
print "<td><SELECT class=\"inputbox\" NAME=erh onchange=\"javascript:docChange = true;\">\n";
if ($erh=='ERH351') print "<option>ERH351 = FI kort 71</option>\n";
if ($erh=='ERH352') print "<option>ERH352 = FI kort 04 & 15</option>\n";
if ($erh=='ERH354') print "<option>ERH354 = FI kort 01 & 41</option>\n";
if ($erh=='ERH355') print "<option>ERH355 = Bankoverf. med straks advisering</option>\n";
if ($erh=='ERH356') print "<option>ERH356 = Bankoverf. med normal advisering</option>\n";
if ($erh=='ERH357') print "<option>ERH357 = FI kort 73</option>\n";
if ($erh=='ERH358') print "<option>ERH358 = FI kort 75</option>\n";
if ($erh=='ERH400') print "<option>ERH400 = Udenlandsk overf&oslash;rsel</option>\n";
if ($erh=='SDC3') print "<option>SDC3 = Bankoverf. med kort advisering</option>\n";
if ($erh=='SDCK020') print "<option>SDCK020 = FI-kort 71 (SDC)</option>\n";
if ($erh!='ERH351') print "<option>ERH351 = FI kort 71</option>\n";
if ($erh!='ERH352') print "<option>ERH352 = FI kort 04 & 15</option>\n";
if ($erh!='ERH354') print "<option>ERH354 = FI kort 01 & 41</option>\n";
if ($erh!='ERH355') print "<option>ERH355 = Bankoverf. med straks advisering</option>\n";
if ($erh!='ERH356') print "<option>ERH356 = Bankoverf. med normal advisering</option>\n";
if ($erh!='ERH357') print "<option>ERH357 = FI kort 73</option>\n";
if ($erh!='ERH358') print "<option>ERH358 = FI kort 75</option>\n";
if ($erh!='ERH400') print "<option>ERH400 = Udenlandsk overf&oslash;rsel</option>\n";
if ($erh!='SDC3') print "<option>SDC3 = Bankoverf. med kort advisering</option>\n";
if ($erh!='SDCK020') print "<option>SDCK020 = FI-kort 71 (SDC)</option>\n";
print "</SELECT></td></tr>\n";
print "</tbody></table></td><td valign=\"top\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n"; # tabel 1.2.1 ->
print "\n";
print "</tbody></table></td></tr>\n";#tabel 1.2.2 slut
print "<tr bgcolor=$bg><td colspan=3><table><tbody></td></tr>\n";#tabel 1.2.3 start
print "<tr bgcolor=$bg><td valign=top width=130> Bem&aelig;rkning</td><td colspan=2><textarea name=\"notes\" rows=\"3\" cols=\"100\">$notes</textarea></td></tr>\n";
if ($id) {
	print "<tr bgcolor=$bg><td></td><td colspan=2><table width=700 border=0><tbody>\n"; #tabel 3.3.1 start
	print "<tr bgcolor=$bg><td> Pos. Kontakt</td><td> Lokalnr. / Mobil</td><td> E-mail</td><td> <a href=ansatte.php?ordre_id=$ordre_id&fokus=$fokus&konto_id=$id&returside=$returside>Ny</a></td></tr>\n";
	$x=0;
	$q = db_select("select * from ansatte where konto_id = '$id' order by posnr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)){
		$x++;
		print "<td><input class=\"inputbox\" type=text size=1 name=posnr[$x] value=\"$x\"> &nbsp;<a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id&id=$r[id]>".htmlentities($r['navn'],ENT_COMPAT,$charset)."</a></td>\n";
		print "<td> $r[tlf] / $r[mobil]</td><td> $r[email]</td></tr>\n";
		print "<input type=hidden name=ans_id[$x] value=$r[id]>\n";
		if ($x==1) print "<input type=hidden name=kontakt value='$r[navn]'>\n";
	}
}
print "<input type=hidden name=ans_ant value=$x>\n";
print "</tbody></table>\n"; #tabel 1.3.1 slut
print "<tr bgcolor=$bg><td colspan=3><br></td></tr>\n";
print "<tr bgcolor=$bg><td colspan=3><br></td></tr>\n";
$q = db_select("select id from openpost where konto_id = '$id'",__FILE__ . " linje " . __LINE__);
if (db_fetch_array($q)) $slet="NO";
$q = db_select("select id from ordrer where konto_id = '$id'",__FILE__ . " linje " . __LINE__);
if (db_fetch_array($q)) $slet="NO";
$q = db_select("select id from ansatte where konto_id = '$id'",__FILE__ . " linje " . __LINE__);
if (db_fetch_array($q)) $slet="NO";
		 
if ($slet=="NO") {print "<tr bgcolor=$bg><td colspan=3 align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>\n";}		
else {print "<tr bgcolor=$bg><td colspan=3 align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\" onclick=\"javascript:docChange = false;\">&nbsp;&nbsp;&nbsp;<input type=submit accesskey=\"s\" value=\"Slet\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>\n";}
print	"</tbody></table>";#tabel 1.2.3 slut
print	"</td></tr>";
print	"</tbody></table>";#tabel 1.2 slut
print	"<tr><td colspan=\"3\" align=\"center\" valign=\"bottom\">";
print	"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"1\" cellpadding=\"0\"><tbody>";#tabel 1.3. start
print "<td width=\"40%\" $top_bund>&nbsp;</td>";
$tekst=findtekst(132,$sprog_id);
if ($popup) print "<td width=\"10%\" $top_bund onClick=\"javascript:kontokort=window.open('rapport.php?rapportart=kontokort&konto_fra=$kontonr&konto_til=$kontonr&returside=../includes/luk.php','kontokort','".$jsvars."');kontokort.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\"><!--tekst 132-->".findtekst(133,$sprog_id)."<!--tekst 133--></td>\n";
		else print "<td width=\"10%\" $top_bund  title=\"$tekst\"><a href=rapport.php?rapportart=kontokort&konto_fra=$kontonr&konto_til=$kontonr&returside=../kreditor/kreditorkort.php?id=$id>".findtekst(133,$sprog_id)."</td>\n";
if (substr($rettigheder,5,1)=='1') {
		$tekst=findtekst(129,$sprog_id);
    if ($popup) print "<td width=\"10%\" $top_bund onClick=\"javascript:d_ordrer=window.open('ordreliste.php?kontonumre=$kontonr&valg=faktura','d_ordrer','".$jsvars."');d_ordrer.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(134,$sprog_id)."</td>\n";
	else print "<td width=\"10%\" $top_bund  title=\"$tekst\"><a href=ordreliste.php?kontonumre=$kontonr&valg=faktura&returside=../kreditor/kreditorkort.php?id=$id>".findtekst(134,$sprog_id)."</td>\n";
} else print "<td width=\"10%\" $stor_knap_bg><span style=\"color:#999;\">".findtekst(134,$sprog_id)."</span></td>\n";
print "<td width=\"40%\" $top_bund>&nbsp;</td>";

#print	"<td style=\"border: 1px solid #b4b4ff; padding: 0pt 0pt 1px;\" align=\"left\" background=\"../img/grey1.gif\" width=\"100%\"><br></td>";
print	"</tbody></table>";#tabel 1.3 slut
print	"</td></tr>";
print	"</tbody></table>\n";#tabel 1 slut
print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/cvrapiopslag.js\"></script>\n";
print	"</body></html>";
?>
