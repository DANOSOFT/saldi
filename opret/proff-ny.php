<!-/proff.php * 2010-05-14 -->
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

/*  �ndringer af 18. maj 2008 af Line Wied, Wied Webdesign:
 *	- Hvis formen postes korrekt bliver svaret echo'et istedet for en  
 *	javascript-alert.
 *	- Fjernet inline <style> og <font> tags. 
 *	- Tilf�jet eksternt og internt stylesheet.
 *  - Fjernet henvisninger til $returadresse, da den ikke skulle bruges.
 *	- Tilf�jet class="inputbox" til alle inputbokse og
 *	  class="button" til submit-knap.  
 */

# Priser
$prisprbruger=25; # Pr. samtidig bruger pr. md
$prispr1000posteringer=25; # Pr. 1000 posteringer pr. md
$faktureringsgebyr=12;
$prisaftenkursus=750;
$rabatprisaftenkursus=538;
$priskomigang=1600;

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
$posteringer = '1000'; $fakt_interval="Hel&aring;rlig"; $valg_kursus="Nej tak";


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
	$valgt_kursus = $_POST['valgt_kursus'];
	$samlet_pris = $_POST['samlet_pris'];
	$brugernavn = $email;
	$kodeord = $telefon;

	$to = stripslashes($email);
	
	$subject = "Bestilling af SALDI-abonnement";

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
#		print "<BODY onLoad=\"javascript:alert('De 2 kodeord skal v�re ens')\">";
	}	else { 
		if (!$brugerantal) $brugerantal='1';
		srand((double)microtime()*1000000);
	  $chars = 'ABCDEFGHIJKLNMOPQRSTUVXYabcdefghijklmnopqrstuvwxyz0123456789';
    for ($i=0; $i<16; $i++) $kontrol_id .= $chars[rand(0, strlen($chars)-1)];
		$kontrol_id=$kontrol_id.":".$brugerantal;
		$linkadresse="https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?kontrol_id=".$kontrol_id;
		$message = "Tak for din bestilling af ";
		
		$tmp=substr($fakt_interval,0,3);
		
		if ($tmp == 'Hve') {
			$message = $message."1 m�neds SALDI-abonnement ";
			$maanedspris = $brugerantal*$prisprbruger+$faktureringsgebyr;
		}	
		if ($tmp == 'Kva') {
			$message = $message."3 m�neders SALDI-abonnement ";
			$maanedspris = $brugerantal*$prisprbruger+$faktureringsgebyr/3;
		}	
		if ($tmp == 'Hal') {
			$message = $message."6 m�neders SALDI-abonnement ";
			$maanedspris = $brugerantal*$prisprbruger+$faktureringsgebyr/6;
		}	
		if ($tmp == 'Hel') {
			$message = $message."12 m�neders SALDI-abonnement ";
			$maanedspris = $brugerantal*$prisprbruger+$faktureringsgebyr/12;
		}	

		
		if ($posteringer < 1001) {
			$message = $message."med 1.000 �rlige posteringer\n";
			$maanedspris += $prispr1000posteringer;
		} elseif ($posteringer < 2001) {
			$message = $message."med 2.000 �rlige posteringer\n";
			$maanedspris += 2*$prispr1000posteringer;
		} elseif ($posteringer < 3001) {
			$message = $message."med 3.000 �rlige posteringer\n";
			$maanedspris += 3*$prispr1000posteringer;
		} elseif ($posteringer < 4001) {
			$message = $message."med 4.000 �rlige posteringer\n";
			$maanedspris += 4*$prispr1000posteringer;
		} elseif ($posteringer < 5001) {
			$message = $message."med 5.000 �rlige posteringer\n";
			$maanedspris += 5*$prispr1000posteringer;
		} elseif ($posteringer < 6001) {
			$message = $message."med 6.000 �rlige posteringer\n";
			$maanedspris += 6*$prispr1000posteringer;
		} elseif ($posteringer < 7001) {
			$message = $message."med 7.000 �rlige posteringer\n";
			$maanedspris += 7*$prispr1000posteringer;
		} elseif ($posteringer < 8001) {
			$message = $message."med 8.000 �rlige posteringer\n";
			$maanedspris += 8*$prispr1000posteringer;
		} elseif ($posteringer < 9001) {
			$message = $message."med 9.000 �rlige posteringer\n";
			$maanedspris += 9*$prispr1000posteringer;
#		elseif ($posteringer < 10001) $message = $message."med 10.000 �rlige posteringer\n";
		} else {
			$message = $message."med ubegr�nset antal �rlige posteringer\n";
			$maanedspris += 10*$prispr1000posteringer;
		}

		if ($brugerantal >= 2) $message .= "og $brugerantal samtidige brugere ";
	
		if ($valgt_kursus=="Nej tak") {
			$message .= "til kr. $maanedspris excl. moms pr. md.\n";
		} else {
			$message .= "til kr. $maanedspris excl. moms pr. md samt kurset:\n";
			$message .= "   $valgt_kursus\n";
		}
		
		$message .= "\nKlik p� nedenst�ende link for at bekr�fte bestillingen og verificere\noprettelsen af dit regnskab:\n$linkadresse\n\n";
#		$message .= "Linket er gyldigt i 7 dage.\n\n";
		$message .= "Tilmeldingsoplysninger:\n";
		$message .= "Navn:       ".stripslashes($navn)."\n";
		$message .= "Firma:      ".stripslashes($firma)."\n";
		$message .= "CVR-nr.:    ".stripslashes($cvr)."\n";
		$message .= "Adresse:    ".stripslashes($adresse)."\n";
		if ($adresse2) $message .= "            ".stripslashes($adresse2);
		$message .= "\nPostnr/By:  ".stripslashes($postnr)." ".stripslashes($bynavn)."\n";
		$message .= "Telefon:    ".stripslashes($telefon)."\n";
		$message .= "E-mail:     ".stripslashes($email)."\n";
		$message .= "Regnskab:   ".stripslashes($regnskab)."\n\n";
		$message .= "Din email og telefonnummer anvendes som hhv. brugernavn og adgangskode ved f�rste login.\n"; 
		$message .= "Du opfordres til at �ndre brugernavn og is�r adgangskode under Indstillinger -> Brugere.\n";
		$message .= "Her kan du ogs� tilf�je flere brugere.\n";
		if ($eventuelt) $message .= "Eventuelt: ".stripslashes($eventuelt)."\n";
		$message .= "\nBem�rk at navnet p� regnskabet skal skrives n�jagtigt som angivet. \nDer skelnes mellem store og sm� bogstaver.\n";
		$message .= "Ved f�rste login kommer du direkte ind i oprettelse af 1. regnskabs�r.\nDette SKAL oprettes inden regnskabet kan bruges.\n";
		$tmp=htmlentities(stripslashes($regnskab));
		$tmp=str_replace(" ","%20",$tmp);
		$tmp=str_replace("$","%24",$tmp);
		$tmp=str_replace("%","%25",$tmp);
		$tmp=str_replace("&","%26",$tmp);
		$tmp=str_replace("+","%2B",$tmp);
		$tmp=str_replace("?","%3F",$tmp);
		$tmp=str_replace("%26amp;","%26",$tmp);
		$message .= "Herefter kan du finde dit regnskab p�:\nhttp://www.saldi.dk/finans?regnskab=$tmp \n\n";
		$message .= "P� http://forum.saldi.dk kan der findes svar p� de fleste sp�rgsm�l.\n";
		$message .= "Bem�rk at forummet er beskyttet med adgangskode - Skriv Saldi (med stort S) som b�de brugernavn og adgangskode.\n";
		$message .= "Brugervejledningen kan findes her:  http://www.saldi.dk/dok/index.htm\nog du kan finde en videomanual her: http://flash.saldi.dk \n";
		$message .= "Som SALDI-kunde har du adgang til hotline og ubegr�nset support pr. email. Benyt mailadressen support@saldi.dk.\n";
		$message .= "Har du i �vrigt sp�rgsm�l, eller hvis der er andet, vi kan hj�lpe med, s� ring blot p� telefon 4690 2208.\n\n";
		$message .= "Velkommen til og god forn�jelse\n\n";
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
		if (! substr($valgt_kursus,0,3)=="Nej") {
			$mail->AddBCC('kursus@saldi.dk');
		}
		$mail->Subject  =  "Bestilling af SALDI-Abonnement";
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
		echo "<p>Tak for din tilmelding. Der er sendt en e-mail til $email med instruktioner om, hvordan du opretter og aktiverer dit nye regnskab.</p>\n";	
#		if ($returadresse == "null") $returadresse="https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?returadresse=null";
#		else $returadresse="http://".$returadresse;
#		print "<meta http-equiv=refresh content=0;url=$returadresse>";
		exit;
	}
}

#elseif (isset($returadresse)) {
#	?<body onload="alert('Beklager - afsendelse mislykkedes, pr�v venligst igen')"><?php
#	print "<meta http-equiv=refresh content=0;url=opret_mig.php>";
#}
#
# JavaScript er genereret i PHP, saa PHP-variabler kan benyttes
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/overlib.js\"></script>\n";
print "<script Language=\"JavaScript\">\n";
print "	<!--\n";
print "	function Form1_Validator(theForm) {\n";
print "		var alertsay = \"\"; \n";
print "		if (theForm.navn.value == \"\") {\n";
print "			alert(\"Navn skal udfyldes.\");\n";
print "			theForm.navn.focus();\n";
print "			return (false);\n";
print "		}\n";
print "		if (theForm.navn.value.length < 3) {\n";
print "			alert(\"Navn skal indeholde minimum 3 bogstaver, cifre eller tegn.\");\n";
print "			theForm.navn.focus();\n";
print "			return (false);\n";
print "		}\n";
print "		if (theForm.telefon.value.length < 8) {\n";
print "			alert(\"Telefonnummer er ikke korrekt udfyldt\");\n";
print "			theForm.telefon.focus();\n";
print "			return (false);\n";
print "		}\n";
print "		if (theForm.adresse.value.length < 3) {\n";
print "			alert(\"Adresse skal indeholde minimum 3 bogstaver, cifre eller tegn.\");\n";
print "			theForm.adresse.focus();\n";
print "			return (false);\n";
print "		}\n";
print "		if (theForm.postnr.value.length < 4) {\n";
print "			alert(\"Postnr skal indeholde minimum 4 bogstaver, cifre eller tegn.\");\n";
print "			theForm.postnr.focus();\n";
print "			return (false);\n";
print "		}\n";
print "		if (theForm.bynavn.value.length < 2) {\n";
print "			alert(\"Bynavn skal indeholde minimum 2 bogstaver, cifre eller tegn.\");\n";
print "			theForm.bynavn.focus();\n";
print "		return (false);\n";
print "		}\n";
print "		if (theForm.email.value == \"\") {\n";
print "			alert(\"\\\"e-mail\\\" skal udfyldes.\");\n";
print "			theForm.email.focus();\n";
print "			return (false);\n";
print "		}\n";
print "		var checkemail = \"@.\";\n";
print "		var checkStr = theForm.email.value;\n";
print "		var emailValid = false;\n";
print "		var emailAt = false;\n";
print "		var emailPeriod = false;\n";
print "		for (i = 0;  i < checkStr.length;  i++) {\n";
print "			ch = checkStr.charAt(i);\n";
print "			for (j = 0;  j < checkemail.length;  j++) 	{\n";
print "				if (ch == checkemail.charAt(j) && ch == \"@\")\n";
print "				emailAt = true;\n";
print "				if (ch == checkemail.charAt(j) && ch == \".\")\n";
print "				emailPeriod = true;\n";
print "				if (emailAt && emailPeriod)\n";
print "				break;\n";
print "				if (j == checkemail.length)\n";
print "				break;\n";
print "			}\n";
print "			if (emailAt && emailPeriod) {\n";
print "				emailValid = true\n";
print "				break;\n";
print "			}\n";
print "		}\n";
print "		if (!emailValid) {\n";
print "			alert(\"Den angivne e-mail-adresse er ikke ikke gyldigt. Uden gyldig e-mail-adresse kan regnskabet ikke aktiveres.\");\n";
print "			theForm.email.focus();\n";
print "			return (false);\n";
print "		}\n";
print "		if (theForm.regnskab.value == \"\") {\n";
print "			alert(\"Navn p&aring; regnskab skal udfyldes.\");\n";
print "			theForm.regnskab.focus();\n";
print "			return (false);\n";
print "		}\n";
print "		if (!theForm.betingelser.checked) {\n";
print "			alert(\"Betingelser skal accepteres!\");\n";
print "			theForm.betingelser.focus();\n";
print "			return (false);\n";
print "		}\n";
print "		return (true);\n";
print "	}\n";
print "	\n";
print "function kursuspris(kursus, periode)\n";
print "{\n";
print "	kursus = (kursus.substr(0,3));\n";
print "	periode = (periode.substr(0,3));\n";
print "	if (kursus == 'Aft') {\n";
print " 		if (periode == 'Hel') {\n";
print "        		return ".$rabatprisaftenkursus.";\n";
print "		} else {\n";
print "			return ".$prisaftenkursus.";\n";
print "		}\n";
print "	}\n";
print "	if (kursus == 'Kom') return ".$priskomigang.";\n";
print "	return 0;\n";
print "}\n";
print "	\n";
print "function periode2fordelinger(string)\n";
print "{\n";
print "	string = (string.substr(0,3))\n";
print "	if (string == 'Hel') return 1;\n";
print "	if (string == 'Hal') return 2;\n";
print "	if (string == 'Kva') return 4;\n";
print "	if (string == 'Hve') return 12;\n";
print "}\n";
print "\n";
print "function postpris(posteringer)\n";
print "{\n";
print "	if (posteringer < 1001) return ".$prispr1000posteringer.";\n";
print "	if (posteringer < 2001) return ".(2*$prispr1000posteringer).";\n";
print "	if (posteringer < 3001) return ".(3*$prispr1000posteringer).";\n";
print "	if (posteringer < 4001) return ".(4*$prispr1000posteringer).";\n";
print "	if (posteringer < 5001) return ".(5*$prispr1000posteringer).";\n";
print "	if (posteringer < 6001) return ".(6*$prispr1000posteringer).";\n";
print "	if (posteringer < 7001) return ".(7*$prispr1000posteringer).";\n";
print "	if (posteringer < 8001) return ".(8*$prispr1000posteringer).";\n";
print "	if (posteringer < 9001) return ".(9*$prispr1000posteringer).";\n";
print "	return ".(10*$prispr1000posteringer).";\n";
print "}\n";
print "\n";
print "function beregn()\n";
print "{\n";
print "	maaneder_pr_aar = 12;\n";
print "	fakturagebyr = ".$faktureringsgebyr."; \n";
print "	bruger_pr_md = ".$prisprbruger.";\n";
print "	string = (bruger_pr_md*Math.round(document.Form1.brugerantal.value)); \n";
print "	string = string+(fakturagebyr/maaneder_pr_aar)*(periode2fordelinger(document.Form1.fakt_interval.value));\n";
print "	string = string+postpris(document.Form1.posteringer.value);\n";
print "	document.Form1.maanedspris.value=string + \",00\";\n";
print "\n";
print "//	if ( periode2fordelinger(document.Form1.fakt_interval.value) == 0 )\n";
print "//	{\n";
print "//		string = maaneder_pr_aar*string;\n";
print "//		document.Form1.fakturapris.value=string + \",00\";\n";
print "//	} else {\n";
print "		string = (maaneder_pr_aar/periode2fordelinger(document.Form1.fakt_interval.value))*string;\n";
print "		document.Form1.fakturapris.value=string + \",00\";\n";
print "         string = string + kursuspris(document.Form1.valgt_kursus.value,document.Form1.fakt_interval.value);\n";
print " 	document.Form1.samletfaktura.value=string + \",00\";\n";
print "//	}\n";
print "}\n";
print "\n";
print "//-->\n";
print "</script>\n";

print	"<form action=\"proff.php\"  onsubmit=\"return Form1_Validator(this)\" method=\"POST\" name=\"Form1\">\n";
print "<TABLE BORDER=\"0\"  CELLSPACING=\"0\" align=center>\n";
$tekst="Udfyld formularen og klik p&aring; \"Opret regnskab\". S&aring; vil du modtage en mail med en link til dit nye regnskab i SALDI.<br>\n";
$tekst.="  <noscript><span style=\"color:#f00\">JavaScript er enten sl&aring;et fra eller ikke unders&oslash;ttet, s&aring; beregning af priser kan ikke foretages.<br />\n";
$tekst.="  Du kan dog sagtens bestille, men brugen af SALDI kr&aelig;ver at JavaScript er sl&aring;et til.</span></noscript>\n";
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
	$spantekst="<big>V&aelig;lg hvor mange posteringer du forventer at foretage &aring;rligt!<br>Et bilag eller en faktura giver normalt 3 posteringer.<br>1.000 &aring;rlige posteringer koster kr. ".$prispr1000posteringer.",- pr. m&aring;ned.<br>Du kan opgradere senere hvis behovet opst&aring;r.</big>"; 
	print "<tr><td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\">Antal posteringer &aring;rligt</span></td>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><input type=\"text\" class=\"inputbox inputbox-medium\" style=\"text-align:right\" name=\"posteringer\" value=\"$posteringer\" ONBLUR=\"beregn()\"> ".$prispr1000posteringer." kr. pr. 1.000 pr. m&aring;ned</span></td></tr>\n";
	$spantekst="<big>I Saldi kan du oprette flere brugere, uden merpris.<br>Her skal du beslutte hvor mange der skal v&aelig;re logget ind p&aring; samme tid.<br>Du kan &aelig;ndre antal senere, hvis behovet opst&aring;r.</big>"; 
	print "<tr><td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\">Antal samtidige brugere</span></td>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><input type=\"text\" class=\"inputbox inputbox-medium\" style=\"text-align:right\" name=\"brugerantal\" value=\"$brugerantal\" ONBLUR=\"beregn()\"></span> ".$prisprbruger." kr. pr. bruger pr. m&aring;ned</td></tr>\n";
	$spantekst="<big>V&aelig;lg hvor ofte du vil modtage faktura.<br>Der till&aelig;gges kr. ".$faktureringsgebyr.",- i faktureringsgebyr p&aring; hver faktura.</big>"; 
	print "<tr><td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\">Fakturering</span></td>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><SELECT name=\"fakt_interval\" ONMOUSEOUT=\"beregn()\">\n";
	print "<option value=\"$fakt_interval\">$fakt_interval</option>\n";
	if ($fakt_interval != 'Hver m&aring;ned') print "<option value=\"Hver m&aring;ned\">Hver m&aring;ned</option>\n";
	if ($fakt_interval != 'Kvartalsvis') print "<option value=\"Kvartalsvis\">Kvartalsvis</option>\n";
	if ($fakt_interval != 'Halv&aring;rlig') print "<option value=\"Halv&aring;rlig\">Halv&aring;rlig</option>\n";
	if ($fakt_interval != 'Hel&aring;rlig') print "<option value=\"Hel&aring;rlig\">Hel&aring;rlig</option>\n";
	print "</SELECT></span>\n";
	$spantekst="<big>Her kan du se det fakturabel&oslash;b, du vil blive faktureret hver periode,<br /> for det valgte antal posteringer og brugere.<br /><br />Bel&oslash;bet er incl. eventuelle gebyrer og excl. moms.<br>Klik i feltet for at opdatere prisen.	</big>";
	print " &aacute; <span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><input readonly=\"readonly\" name=\"fakturapris\" style=\"text-align:right\" class=\"inputbox inputbox-medium\"></span>\n";
	$spantekst="<big>Der till&aelig;gges kr. ".$faktureringsgebyr.",- i faktureringsgebyr p&aring; hver faktura.</big>"; 
	print "<span onmouseover=\"return overlib('$spantekst', WIDTH=800);\"> her af faktureringsgebyr p&aring; ".$faktureringsgebyr." kr.</span></td></tr>\n";
#			print "<tr><td>Pris mr. md</td><td>45,00</td></tr>\n";

#	print "<tr>\n";
#	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\">Du faktureres</span></td>\n";
#	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><input readonly=\"readonly\" name=\"fakturapris\" style=\"text-align:right\" class=\"inputbox inputbox-medium\"></span>\n";
#	print "  <noscript><span style=\"color:#f00\"> Bel&oslash;bet bliver ikke beregnet pga. manglende JavaScript-underst&oslash;ttelse.</span></noscript></td>\n";
#	print "</tr>\n";
			
	$spantekst="<big>Her kan du se den m&aring;nedlige omkostning med det valgte antal posteringer / brugere.<br>Bel&oslash;bet er incl. eventuelle gebyrer og excl. moms.<br>Klik i feltet for at opdatere prisen.	</big>";
	print "<tr>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\">Pris pr. m&aring;ned</span></td>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><input readonly=\"readonly\" name=\"maanedspris\" style=\"text-align:right\" class=\"inputbox inputbox-medium\"></span>\n";
	print "  <noscript><span style=\"color:#f00\"> Bel&oslash;bet bliver ikke beregnet pga. manglende JavaScript-underst&oslash;ttelse.</span></noscript></td>\n";
	print "</tr>\n";

	$valgt_kursus="Nej tak";
	$spantekst="<big>Her v&aelig;lger du eventuelt, hvilket kursus du vil have i tilknytning til dit abonnement.<br />Hvis du ikke har erfaring med bogf&oslash;ring, s&aring; anbefales det kraftigt, da det vil<br />spare dig for meget senere hen.</big>";
	print "<tr><td valign=\"top\"><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\">Kursus</span></td>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><SELECT name=\"valgt_kursus\" ONMOUSEOUT=\"beregn()\">\n";
	print "<option value=\"$valgt_kursus\">$valgt_kursus</option>\n";
	if ($valgt_kursus != 'Nej tak') print "<option value=\"Nej tak\">Nej tak</option>\n";
	if ($valgt_kursus != 'Aftenkursus K&oslash;benhavn 30. august: 750,00') print "<option value=\"Aftenkursus K&oslash;benhavn 30. august: 750,00\">Aftenkursus K&oslash;benhavn 30. august: 750,00</option>\n";
	if ($valgt_kursus != 'Aftenkursus K&oslash;benhavn 26. oktober: 750,00') print "<option value=\"Aftenkursus K&oslash;benhavn 26. oktober: 750,00\">Aftenkursus K&oslash;benhavn 26. oktober: 750,00</option>\n";
	if ($valgt_kursus != 'Aftenkursus K&oslash;benhavn 16. december: 750,00') print "<option value=\"Aftenkursus K&oslash;benhavn 16. december: 750,00\">Aftenkursus K&oslash;benhavn 16. december: 750,00</option>\n";
	if ($valgt_kursus != 'Kom i gang-kursus. Angiv sted og datoer i Eventuelt: 1.600,00') print "<option value=\"Kom i gang-kursus. Angiv sted og datoer i Eventuelt: 1.600,00\">Kom i gang-kursus. Angiv sted og datoer i Eventuelt: 1.600,00</option>\n";
	print "</SELECT></span><br />\n";
	print "Se mere p&aring; <a href=\"http://saldi.dk/kurser\">Aftenkurser</a> og <a href=\"http://saldi.dk/komigang\">Kom i gang-kursus</a>.</td></tr>\n";
			
	$spantekst="<big>Her kan du se det samlede bel&oslash;b for den f&oslash;rste faktura for det valgte antal posteringer og brugere samt eventuelt kursus.<br>Bel&oslash;bet er incl. eventuelle gebyrer men excl. moms og eventuel transport.<br>Klik i feltet for at opdatere prisen.</big>";
	print "<tr>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\">Bel&oslash;b for f&oslash;rste faktura</span></td>\n";
	print "<td><span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><input readonly=\"readonly\" name=\"samletfaktura\" style=\"text-align:right\" class=\"inputbox inputbox-medium\"></span>\n";
	print "  <noscript><span style=\"color:#f00\"> Bel&oslash;bet bliver ikke beregnet pga. manglende JavaScript-underst&oslash;ttelse.</span></noscript></td>\n";
	print "</tr>\n";
			
print "<tr><td></td></tr><tr><td valign=\"top\">Eventuelt</td><td><textarea name=\"eventuelt\" rows=\"3\" wrap=\"Virtual\" cols=\"45\">$eventuelt</textarea></td>\n";
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


		     
