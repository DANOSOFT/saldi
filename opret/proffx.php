<!-- opret/proff.php * 2009-06-06 -->
<DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head><meta content="text/html; charset=ISO-8859-1" http-equiv="content-type"><title>Oprettelsesformular</title>
<link rel="stylesheet" href="http://saldi.dk/cms/templates/ja_hedera/css/template.css" type="text/css" />

<style type="text/css">
<!--
tr,p{color: #666666;font-size: 12px;}
textarea{height:80px;width:150px;}
.inputbox{width:150px;}
.inputbox-small{width:40px;}
.inputbox-medium{width:75px;}

-->
</style>
<center>
</head>
<body>
<?php # 20080401

/*  Ændringer af 18. maj 2008 af Line Wied, Wied Webdesign:
 *	- Hvis formen postes korrekt bliver svaret echo'et istedet for en  
 *	javascript-alert.
 *	- Fjernet inline <style> og <font> tags. 
 *	- Tilføjet eksternt og internt stylesheet.
 *  - Fjernet henvisninger til $returadresse, da den ikke skulle bruges.
 *	- Tilføjet class="inputbox" til alle inputbokse og
 *	  class="button" til submit-knap.  
 */

$returadresse=""; 
$tilbud=NULL;$tilbud_1=NULL;$tilbud_2=NULL;$tilbud_3=NULL;$tilbud_4=NULL;$kontrol_id=NULL;
if (isset($_GET['kontrol_id'])) {
	$kontrol_id=$_GET['kontrol_id'];
#echo "Kontrol id: $_GET[kontrol_id]<br>";
	opret($kontrol_id);	
	exit;
}

$navn = ''; $firma = ''; $cvr = ''; $adresse = ''; $adresse2 = ''; $postnr = ''; $bynavn = ''; $telefon = ''; $email = '';
$regnskab = ''; $brugerantal = '1'; $eventuelt = ''; $betingelser = ''; $brugernavn = ''; $kodeord = ''; $kodeord2 = '';; 
$posteringer = '1000'; $fakt_interval="Hel&aring;rlig";


if (isset($_POST['navn']) && isset($_POST['email'])) {
#include "top.php";
	$navn = addslashes($_POST['navn']);
	$firma = addslashes($_POST['firma']);
	$cvr = addslashes($_POST['cvr']);
	$adresse = addslashes($_POST['adresse']);
	$adresse2 = addslashes($_POST['adresse2']);
	$postnr = addslashes($_POST['postnr']);
	$bynavn = addslashes($_POST['bynavn']);
	$telefon = addslashes($_POST['telefon']);
	$email = addslashes($_POST['email']);
	$regnskab = addslashes($_POST['regnskab']);
	$eventuelt = addslashes($_POST['eventuelt']);
	$betingelser = addslashes($_POST['betingelser']);
	$posteringer = $_POST['posteringer']*1;
	$brugerantal = $_POST['brugerantal']*1;
	$fakt_interval = $_POST['fakt_interval'];
	$maanedspris = $_POST['maanedspris'];
	$brugernavn = $email;
	$kodeord = $telefon;

	$to = stripslashes($email);
	
	$subject = "Bestilling af SALDI abonnement";

	include "../includes/connect.php";
	include "../includes/db_query.php";
	$query=db_select("SELECT relname FROM pg_class WHERE relname = 'kundedata'",__FILE__ . " linje " . __LINE__);
	if(! db_fetch_array($query))  db_modify("CREATE TABLE kundedata (id serial NOT NULL, firmanavn varchar,addr1 varchar, addr2 varchar, postnr varchar, bynavn varchar, kontakt varchar, tlf varchar, email varchar, cvrnr varchar, regnskab varchar, brugernavn varchar, kodeord varchar, kontrol_id varchar, aktiv smallint, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	$query=db_select("select id from kundedata where regnskab='$regnskab'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		print "<BODY onLoad=\"javascript:alert('Der er allerede oprettet et regnskab med navnet $regnskab')\">";
	}
	$query=db_select("select id from regnskab where regnskab='$regnskab'",__FILE__ . " linje " . __LINE__);
	$q2=db_select("select id from kundedata where regnskab='$regnskab'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		print "<BODY onLoad=\"javascript:alert('Der er allerede oprettet et regnskab med navnet $regnskab')\">";
	}	elseif ($row = db_fetch_array($q2)) {
		print "<BODY onLoad=\"javascript:alert('Der er et eksisterer et inaktivt regnskab med navnet $regnskab')\">";
#	} elseif ($kodeord!=$kodeord2) {
#		print "<BODY onLoad=\"javascript:alert('De 2 kodeord skal være ens')\">";
	}	else { 
		if (!$brugerantal) $brugerantal='1';
		srand((double)microtime()*1000000);
	  $chars = 'ABCDEFGHIJKLNMOPQRSTUVXYabcdefghijklmnopqrstuvwxyz0123456789';
    for ($i=0; $i<16; $i++) $kontrol_id .= $chars[rand(0, strlen($chars)-1)];
		$kontrol_id=$kontrol_id.":".$brugerantal;
		$linkadresse="https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?kontrol_id=".$kontrol_id;
		$message = "Tak for din bestilling af ";
		
		$tmp=substr($fakt_interval,0,3);
		
		if ($tmp == 'Hve') $message = $message."1 måneds SALDI abonnement ";
		if ($tmp == 'Kva') $message = $message."3 måneders SALDI abonnement ";
		if ($tmp == 'Hal') $message = $message."6 måneders SALDI abonnement ";
		if ($tmp == 'Hel') $message = $message."12 måneders SALDI abonnement ";

		
		if ($posteringer < 1001) $message = $message."med 1.000 årlige posteringer\n";
		elseif ($posteringer < 2001) $message = $message."med 2.000 årlige posteringer\n";
		elseif ($posteringer < 3001) $message = $message."med 3.000 årlige posteringer\n";
		elseif ($posteringer < 4001) $message = $message."med 4.000 årlige posteringer\n";
		elseif ($posteringer < 5001) $message = $message."med 5.000 årlige posteringer\n";
		elseif ($posteringer < 6001) $message = $message."med 6.000 årlige posteringer\n";
		elseif ($posteringer < 7001) $message = $message."med 7.000 årlige posteringer\n";
		elseif ($posteringer < 8001) $message = $message."med 8.000 årlige posteringer\n";
		elseif ($posteringer < 9001) $message = $message."med 9.000 årlige posteringer\n";
#		elseif ($posteringer < 10001) $message = $message."med 10.000 årlige posteringer\n";
		else $message = $message."med ubegrænset antal årlige posteringer\n";

		if ($brugerantal >= 2) $message .= "og $brugerantal samtidige brugere ";
		
		$message .= "til kr. $maanedspris excl. moms pr. md.\n";
		
		$message .= "\nKlik på nedestående link for at bekræfte bestillingen og verificere\noprettelsen af dit regnskab:\n$linkadresse\n\n";
#		$message .= "Linket er gyldigt i 7 dage\n\n";
		$message .= "Tilmeldingsoplysninger:\nNavn: ".stripslashes($navn)."\nFirma: ".stripslashes($firma)."\nCvr: ".stripslashes($cvr)."\nAdresse: ".stripslashes($adresse)."\n";
		if ($adresse2) $message .= "         ".stripslashes($adresse2);
		$message .= "\nPostnr/By: ".stripslashes($postnr)." ".stripslashes($bynavn)."\nTelefon: ".stripslashes($telefon)."\ne-mail: ".stripslashes($email)."\n";
		$message .= "Regnskab: ".stripslashes($regnskab)."\n\n";
		$message .= "Din email og telefonnummer anvendes som hhv. brugernavn og password ved login.\n"; 
		$message .= "Du kan ændre brugernavn og password under Indstillinger -> Brugere.\n";
		$message .= "Her kan du også tilføje flere brugere.\n";
		if ($eventuelt) $message .= "Eventuelt: ".stripslashes($eventuelt)."\n";
		$message .= "\nBemærk at navnet på regnskabet skal skrives nøjagtigt som angivet. \nDer skelnes mellem store og små bogstaver.\n";
		$message .= "Ved første login kommer du direkte ind i oprettelse af 1. regnskabsår.\nDette SKAL oprettes inden regnskabet kan bruges.\n";
		$tmp=htmlentities(stripslashes($regnskab));
		$tmp=str_replace(" ","%20",$tmp);
		$message .= "Herefter kan du finde dit regnskab på:\nhttp://www.saldi.dk/finans?regnskab=$tmp \n\n";
		$message .= "På http://forum.saldi.dk kan der findes svar på de fleste spørgsmål.\n";
		$message .= "Bemærk at forummet er passwordbeskyttet - Skriv Saldi (med stort S) som både brugernavn og password.\n";
		$message .= "Brugervejledningen kan findes her:  http://www.saldi.dk/dok/index.htm\nog du kan finde en videomanual her: http://flash.saldi.dk \n";
		$message .= "Som SALDI-kunde har du adgang til hotline og ubegrænset e-mail support. Benyt mailadressen support@saldi.dk.\n";
		$message .= "Har du i øvrigt spørgsmål, eller hvis der er andet vi kan hjælpe med, så ring blot på telefon 4690 2208.\n\n";
		$message .= "Velkommen til og god fornøjelse\n\n";
		$message .= "Med venlig hilsen\n";
		$message .= "DANOSOFT ApS\n";

		ini_set("include_path", ".:../phpmailer");
		require("class.phpmailer.php");

		$mail = new PHPMailer();
		$mail->IsSMTP();                                   // send via SMTP
		$mail->Host  = "localhost"; // SMTP servers
		$mail->SMTPAuth = false;     // turn on SMTP authentication
		$afsendermail='saldi@saldi.dk';
		$afsendernavn='SALDI ';

		$mail->From  = $afsendermail;
		$mail->FromName = $afsendernavn;
		$mail->AddAddress($to);
		$mail->AddBCC('phr@saldi.dk');
		$mail->Subject  =  "Bestilling af SALDI Abonnement";
		$mail->Body     =  $message;
#		$mail->AltBody  =  "Hermed fremsendes kontoudtog fra $afsendernavn";
 
		if(!$mail->Send()){
			echo "Fejl i afsendelse til $to<p>";
  			echo "Mailer Error: " . $mail->ErrorInfo;
	 		exit;
		}


#		ini_set(sendmail_from,'saldi@saldi.dk'); # Overruler php.ini
#		mail ($to, $subject, $message) or print "Der er sket en fejl ved afsendelsen, pr&oslash;v venligst igen\n";
#		mail ('phr@saldi.dk', $subject, $message) or print "Der er sket en fejl ved afsendelsen, pr&oslash;v venligst igen\n";
#		ini_restore(sendmail_from); # Resetter fra php.ini
		$oprettet=date("Y-m-d");
		db_modify("INSERT INTO kundedata (firmanavn,addr1,addr2,postnr,bynavn,kontakt,tlf,email,cvrnr,regnskab,brugernavn,kodeord,kontrol_id,aktiv,oprettet)values  ('$firma','$adresse','$adresse2','$postnr','$bynavn','$navn','$telefon','$email','$cvr','$regnskab','$brugernavn','$kodeord','$kontrol_id',0,'$oprettet')",__FILE__ . " linje " . __LINE__);
#	print "<body onload=\"alert('Tak for din tilmelding. Der er sendt en e-mail til $email med instruktioner hvordan du opretter og aktiverer dit nye regnskab')\">";
		echo "<p>Tak for din tilmelding. Der er sendt en e-mail til $email med instruktioner hvordan du opretter og aktiverer dit nye regnskab</p>";	
#		if ($returadresse == "null") $returadresse="https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?returadresse=null";
#		else $returadresse="http://".$returadresse;
#		print "<meta http-equiv=refresh content=0;url=$returadresse>";
		exit;
	}
}

#elseif (isset($returadresse)) {
#	?<body onload="alert('Beklager - afsendelse mislykkedes, prøv venligst igen')"><?php
#	print "<meta http-equiv=refresh content=0;url=opret_mig.php>";
#}
?>
<script LANGUAGE="JavaScript" SRC="../javascript/overlib.js"></script>
<script Language="JavaScript">
	<!--
	function Form1_Validator(theForm) {
		var alertsay = ""; 
		if (theForm.navn.value == "") {
			alert("Navn skal udfyldes.");
			theForm.navn.focus();
			return (false);
		}
		if (theForm.navn.value.length < 3) {
			alert("Navn skal indeholde minimum 3 bogstaver, cifre eller tegn.");
			theForm.navn.focus();
			return (false);
		}
		if (theForm.telefon.value.length < 8) {
			alert("Telefonnummer er ikke korrekt udfyldt");
			theForm.telefon.focus();
			return (false);
		}
		if (theForm.adresse.value.length < 3) {
			alert("Adresse skal indeholde minimum 3 bogstaver, cifre eller tegn.");
			theForm.adresse.focus();
			return (false);
		}
		if (theForm.postnr.value.length < 4) {
			alert("Postnr skal indeholde minimum 4 bogstaver, cifre eller tegn.");
			theForm.postnr.focus();
			return (false);
		}
		if (theForm.bynavn.value.length < 2) {
			alert("bynavn skal indeholde minimum 2 bogstaver, cifre eller tegn.");
			theForm.bynavn.focus();
		return (false);
		}
		if (theForm.email.value == "") {
			alert("\"e-mail\" skal udfyldes");
			theForm.email.focus();
			return (false);
		}
		var checkemail = "@.";
		var checkStr = theForm.email.value;
		var emailValid = false;
		var emailAt = false;
		var emailPeriod = false;
		for (i = 0;  i < checkStr.length;  i++) {
			ch = checkStr.charAt(i);
			for (j = 0;  j < checkemail.length;  j++) 	{
				if (ch == checkemail.charAt(j) && ch == "@")
				emailAt = true;
				if (ch == checkemail.charAt(j) && ch == ".")
				emailPeriod = true;
				if (emailAt && emailPeriod)
				break;
				if (j == checkemail.length)
				break;
			}
			if (emailAt && emailPeriod) {
				emailValid = true
				break;
			}
		}
		if (!emailValid) {
			alert("\"e-mail\" er ikke en gyldig e-mailadresse.");
			theForm.email.focus();
			return (false);
		}
		if (theForm.regnskab.value == "") {
			alert("Navn p&aring; regnskab skal udfyldes.");
			theForm.regnskab.focus();
			return (false);
		}
		if (!theForm.betingelser.checked) {
			alert("Betingelser skal accepteres");
			theForm.betingelser.focus();
			return (false);
		}
		return (true);
	}
	
function periode2fordelinger(string)
{
	string = (string.substr(0,3))	
	if (string == 'Hel') return 1;
	if (string == 'Hal') return 2;
	if (string == 'Kva') return 4;
	if (string == 'Hve') return 12;
}

function postpris(posteringer)
{
	if (posteringer < 1001) return 25;
	if (posteringer < 2001) return 50;
	if (posteringer < 3001) return 75;
	if (posteringer < 4001) return 100;
	if (posteringer < 5001) return 125;
	if (posteringer < 6001) return 150;
	if (posteringer < 7001) return 175;
	if (posteringer < 8001) return 200;
	if (posteringer < 9001) return 225;
	return 250;
}

function beregn()
{
	maaneder_pr_aar = 12;
	fakturagebyr = 12; 
	bruger_pr_md = 25;
	string = (bruger_pr_md*Math.round(document.Form1.brugerantal.value)); 
	string = string+(fakturagebyr/maaneder_pr_aar)*(periode2fordelinger(document.Form1.fakt_interval.value));
	string = string+postpris(document.Form1.posteringer.value);
	document.Form1.maanedspris.value=string + ",00";

//	if ( periode2fordelinger(document.Form1.fakt_interval.value) == 0 )
//	{
//		string = maaneder_pr_aar*string;
//		document.Form1.fakturapris.value=string + ",00";
//	} else {
		string = (maaneder_pr_aar/periode2fordelinger(document.Form1.fakt_interval.value))*string;
		document.Form1.fakturapris.value=string + ",00";
//	}
}

//-->
</script>
<?php

print	"<form action=\"proff.php\"  onsubmit=\"return Form1_Validator(this)\" method=\"POST\" name=\"Form1\">\n";
print "<TABLE BORDER=\"0\"  CELLSPACING=\"0\" align=center>\n";
$tekst="Udfyld formularen og klik p&aring; \"Opret regnskab\" S&aring; vil du modtage en mail med en link til dit nye SALDI regnskab,<br>\n";
print "<tr><td colspan=2>$tekst</td>
        	</tr><tr><td colspan=2><hr></td></tr>
        	<tr><td width=\"100\">Navn *</td>
          		<td><input type=text name=navn class=\"inputbox\" size=\"50\" value=\"$navn\" ONFOCUS=\"beregn()\"></td>
        	</tr><tr><td></td></tr>
        	<tr>	<td>Firma / Forening</td>
		     	<td><input type=text name=firma class=\"inputbox\" size=\"50\" value=\"\"$firma\"></td>
        	</tr><tr><td></td></tr>
		<tr>	<td>Evt. CVR nr.</td>
		     	<td><input type=text name=cvr class=\"inputbox\" size=\"50\" value=\"\"$cvr\"></td>
		</tr><tr><td></td></tr>
		<tr>	<td>Adresse *</td>
		     	<td><input type=text name=adresse class=\"inputbox\" size=\"50\" value=\"\"$adresse\"></td>
		     	</tr><tr>	     
		     	<td></td><td><input type=text name=adresse2 class=\"inputbox\" size=\"50\" value=\"\"$adresse2\"></td>
		</tr><tr><td></td></tr>
	    	 	<tr>	<td> Postnr & By *</td>
		     	<td><input type=text name=postnr class=\"inputbox inputbox-small\" value=\"\"$postnr\"><input type=text name=bynavn class=\"inputbox\" size=\"44\" value=\"\"$bynavn\"></td>
		</tr><tr><td></td></tr>
			<tr>	<td>Telefon *</td>
		     	<td><input type=text name=telefon class=\"inputbox\" size=\"50\" value=\"\"$telefon\"></td>
		</tr><tr><td></td></tr>\n";
$spantekst="<big>Skriv en fungerende e-mail.<br>Aktiveringskode og faktura sendes til denne mail adresse.<br></big>"; 
	print "<tr><td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\">e-mail *</span></td>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><input type=text name=email class=\"inputbox\" value=\"$email\"></td>\n";
	print "</tr><tr><td></td></tr>\n";
	$spantekst="<big>Skriv et navn du kan huske, f.eks firmanavn.<br>Du skal bruge dette navn n&aring;r du logger ind.<br>Bem&aelig;rk at der skelnes mellen store og sm&aring; bogstaver.<br>Det valgte navn vil fremg&aring; af den e-mail du modtager.</big>"; 
	print "<tr><td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\">&Oslash;nsket navn p&aring; regnskab *</span></td>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><input type=text name=regnskab class=\"inputbox\" value=\"$regnskab\"</span></td>\n";
	print "</tr><tr><td></td></tr>\n";
	$spantekst="<big>V&aelig;lg hvor mange posteringer du forventer at foretage &aring;rligt!<br>Et bilag eller en faktura giver normalt 3 posteringer.<br>1.000 &aring;rlige posteringer koster kr. 25,- pr. m&aring;ned.<br>Du kan opgradere senere hvis behovet opst&aring;r.</big>"; 
	print "<tr><td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\">Antal posteringer &aring;rligt</span></td>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><input type=\"text\" class=\"inputbox inputbox-medium\" style=\"text-align:right\" name=\"posteringer\" value=\"$posteringer\" ONBLUR=\"beregn()\"> 25 kr. pr. 1.000 pr. m&aring;ned</span></td></tr>\n";
	$spantekst="<big>I Saldi kan du oprette flere brugere, uden merpris.<br>Her skal du beslutte hvor mange der skal v&aelig;re logget ind p&aring; samme tid.<br>Du kan &aelig;ndre antal senere, hvis behovet opst&aring;r.</big>"; 
	print "<tr><td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\">Antal samtidige brugere</span></td>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><input type=\"text\" class=\"inputbox inputbox-medium\" style=\"text-align:right\" name=\"brugerantal\" value=\"$brugerantal\" ONBLUR=\"beregn()\"></span> 25 kr. pr. bruger pr. m&aring;ned</td></tr>\n";
	$spantekst="<big>V&aelig;lg hvor ofte du vil modtage faktura.<br>Der till&aelig;gges kr. 12,- i faktureringsgebyr p&aring; hver faktura.<br>Ved hel&aring;rlig fakturering till&aelig;gges intet gebyr.</big>"; 
	print "<tr><td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\">Fakturering</span></td>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><SELECT name=\"fakt_interval\" ONMOUSEOUT=\"beregn()\">\n";
	print "<option value=\"$fakt_interval\">$fakt_interval</option>\n";
	if ($fakt_interval != 'Hver m&aring;ned') print "<option value=\"Hver m&aring;ned\">Hver m&aring;ned</option>\n";
	if ($fakt_interval != 'Kvartalsvis') print "<option value=\"Kvartalsvis\">Kvartalsvis</option>\n";
	if ($fakt_interval != 'Halv&aring;rlig') print "<option value=\"Halv&aring;rlig\">Halv&aring;rlig</option>\n";
	if ($fakt_interval != 'Hel&aring;rlig') print "<option value=\"Hel&aring;rlig\">Hel&aring;rlig</option>\n";
	print "</SELECT>Faktureringsgebyr 12 kr.</span></td></tr>\n";
#			print "<tr><td>Pris mr. md</td><td>45,00</td></tr>\n";

	$spantekst="<big>Her kan du se fakturabel&oslash;bet for det valgte antal posteringer og brugere.<br>Bel&oslash;bet er incl. eventuelle gebyrer og excl. moms.<br>Klik i feltet for at opdatere prisen.	</big>";
	print "<tr>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\">Pris pr. faktura</span></td>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><input readonly=\"readonly\" name=\"fakturapris\" style=\"text-align:right\" class=\"inputbox inputbox-medium\"></span></td>\n";
	print "</tr>\n";
			
	$spantekst="<big>Her kan du se den m&aring;nedlige omkostning med det valgte antal posteringer / brugere.<br>Bel&oslash;bet er incl. eventuelle gebyrer og excl. moms.<br>Klik i feltet for at opdatere prisen.	</big>";
	print "<tr>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\">Pris pr. m&aring;ned</span></td>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><input readonly=\"readonly\" name=\"maanedspris\" style=\"text-align:right\" class=\"inputbox inputbox-medium\"></span></td>\n";
	print "</tr>\n";
			
print "<tr><td></td></tr><tr><td>Eventuelt</td><td><textarea name=\"eventuelt\" rows=\"3\" wrap=\"Virtual\" cols=\"45\">$eventuelt</textarea></td>\n";
print "</tr><tr><td></td></tr>\n";
$spantekst="<big>Klik her for at l&aelig;se betingelserne for anvendelse af SALDI.</big>";
print "<tr><td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><a onMouseOver=\"this.style.cursor = 'pointer'\" onClick=\"javascript:betingelser=window.open('betingelser.htm','betingelser','left=10,top=10,width=400,height=400,scrollbars=1,resizable=1');betingelser.focus();\"><u>Accepterer betingelser</u></a></span></td>\n";
if ((isset($betingelser))&&($betingelser=='on')) $betingelser="checked\n";
$spantekst="<big>Klik her for at acceptere betingelserne for anvendelse af SALDI.<br>Feltet skal v&aelig;re afm&aelig;rket f&oslash;r bestillingen kan gennemf&oslash;res</big>";
print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><input type=CHECKBOX name=\"betingelser\" $betingelser></span></td>
		</tr><tr><td><br></td></tr>
		</tr><td><br></td>\n";
print "<td style=\"text-align: left;\" colspan=\"2\"><input value=\"Opret regnskab\" name=\"SUBMIT\" class=\"button\" type=\"submit\"><br>
			</td></tr><tr><td colspan=\"3\" align=left>
			<br>\n";
print "Udfyld&nbsp;venligst&nbsp;s&aring;&nbsp;meget&nbsp;som&nbsp;muligt.<br>
			Felter&nbsp;markeret&nbsp;med&nbsp;*&nbsp;skal&nbsp;udfyldes.<br>
			Du&nbsp;modtager&nbsp;en&nbsp;e-mail&nbsp;med&nbsp;et&nbsp;link&nbsp;til&nbsp;dit&nbsp;nye&nbsp;regnskab.<br>
 	
			
			Alle&nbsp;priser&nbsp;er&nbsp;excl.&nbsp;moms.</td>\n";
	
print "</tr>
	</TABLE>
	</form>\n";

function opret($kontrol_id) {	
?>	
	<center>
		<table valign=top border=0><tbody><tr><td colspan=2 align=center><span style="font-family: Arial Black; color: blue;"><big><big><big><big>SALDI</big></big></big></big></span></td></tr>
<?php	
	$header="nix\n";
	$bg="nix\n";
	include "../includes/connect.php";
	include "../includes/db_query.php";
	
	$query=db_select("select * from kundedata where kontrol_id='$kontrol_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$id=$row['id'];
		$regnskab=$row['regnskab'];
		$brugernavn=$row['brugernavn'];
		$kodeord=$row['kodeord'];
		$kontakt=$row['kontakt'];
		$firmanavn=$row['firmanavn'];
		$addr1=$row['addr1'];
		$addr2=$row['addr2'];
		$postnr=$row['postnr'];
		$bynavn=$row['bynavn'];
		$tlf=$row['tlf'];
		$email=$row['email'];
		$cvrnr=$row['cvrnr'];
	}
	else {
		print "<BODY onLoad=\"javascript:alert('Aktiveringskode: $kontrol_id findes ikke')\">\n";
	}	
	if ((isset($regnskab))&&(isset($brugernavn))&&(isset($kodeord))) {
		$query=db_select("select id from regnskab where regnskab='".addslashes($regnskab)."'",__FILE__ . " linje " . __LINE__);
		if (!db_fetch_array($query)) {
			$aktiveret=date("Y-m-d");
			db_modify("update kundedata set aktiv='1',aktiveret='$aktiveret' where id = $id",__FILE__ . " linje " . __LINE__);
			print "<table  border=0><tbody>";
			print "<form action=\"../admin/opret.php\"  method=\"POST\" name=\"opret\">\n";
			print "<tr><td colspan=2 align=center>Aktivering af SALDI regnskab</td></tr>\n";
			print "<tr><td colspan=2 align=center><hr></td></tr>\n";
			print "<tr><td>Firma</td><td>$firmanavn</td></tr>\n";
			print "<tr><td>Navn</td><td>$kontakt</td></tr>\n";
			print "<tr><td>Regnskab</td><td>$regnskab</td></tr>\n";
			print "<tr><td>Brugernavn</td><td>$brugernavn</td></tr>\n";
			
			print "<tr><td colspan=2><hr></td></tr>";
			print "<tr><td colspan=2>N&aring;r der klikkes [OK] initialiseres alle tabeller</td></tr>\n";
			print "<tr><td colspan=2>og kontoplan, formularer mm. indl&aelig;ses.</tr>\n";
			print "<tr><td colspan=2>Dette kan vare flere minutter, s&aring; v&aelig;r t&aring;lmodig.</tr>\n";
			print "<tr><td colspan=2>Herefter kan du logge ind i dit regnskab med</tr>\n";
			print "<tr><td colspan=2>din email som brugernavn og dit telefonnummer som kodeord.</tr>\n";
			print "<tr><td colspan=2>Du kan &aelig;ndre brugernavn og kodeord under Indstillinger -> Brugere.</tr>\n";
			print "<tr><td colspan=2>Velkommen til og god forn&oslash;jelse.</tr>\n";
			print "<tr><td colspan=2 align=center><hr></td></tr>";
			
			$posteringer=1000;
			$brugerantal=1;
			
			if (!$firmanavn) {
				$firmanavn=$kontakt;
				$kontakt="";
			}
			
			print "<input type=hidden name=regnskab value=\"$regnskab\">\n";
			print "<input type=hidden name=brugernavn value=\"$brugernavn\">\n";
			print "<input type=hidden name=passwd value=\"$kodeord\">\n";
			print "<input type=hidden name=passwd2 value=\"$kodeord\">\n";
			print "<input type=hidden name=brugerantal value=\"$brugerantal\">\n";
			print "<input type=hidden name=posteringer value=\"$posteringer\">\n";
			print "<input type=hidden name=firmanavn value=\"$firmanavn\">\n";
			print "<input type=hidden name=addr1 value=\"$addr1\">\n";
			print "<input type=hidden name=addr2 value=\"$addr2\">\n";
			print "<input type=hidden name=postnr value=\"$postnr\">\n";
			print "<input type=hidden name=bynavn value=\"$bynavn\">\n";
			print "<input type=hidden name=tlf value=\"$tlf\">\n";
			print "<input type=hidden name=email value=\"$email\">\n";
			print "<input type=hidden name=cvrnr value=\"$cvrnr\">\n";
			print "<input type=hidden name=kontakt value=\"$kontakt\">\n";
			print "<input type=hidden name=fra_formular value=\"ja\">\n";
			print "<input type=hidden name=std_kto_plan value=\"on\">\n";
			
			print "<tr><td colspan=2 align=center><input type=SUBMIT value='OK' name='SUBMIT'></td></tr>\n";
			print "</tbody></table>\n";
		}			
		else {
			print "<BODY onLoad=\"javascript:alert('Regnskab $regnskab er allerede aktiveret')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../\">";
		}
	
	print "</div></body></html>";

	}
	exit;
}
?>
</div>
</body>
<script Language="JavaScript">
document.Form1.navn.focus();
</script>		
</html>


		     
