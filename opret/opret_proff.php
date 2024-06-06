<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------/opret/opret_mig.php-----lap 4.0.5 ------2022-02-22--------
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2022 saldi.dk aps
// ----------------------------------------------------------------------
// shift to utf-8
#cho __line__."<br>";
$referer=NULL;
$ip=$_SERVER['REMOTE_ADDR'];
$user_agent=$_SERVER['HTTP_USER_AGENT'];
$affiliate=0;
$fp=fopen("https://saldi.dk/referer/referer.csv","r");
while ($linje=fgets($fp)) {
	list($a,$b,$c,$d)=explode("\t",$linje);
#echo "$a==$ip<br>";
	if($a==$ip&&$b==$user_agent) {
		$referer=$c;
		$affiliate=$d*1;
# echo "<FONT color=\"#ffffff\">$a - $b - $c - $d</font>";
	}
}
fclose($fp);
#}
#echo $affiliate;

?>
<DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head><meta content="text/html; charset=UTF-8" http-equiv="content-type"><title>Oprettelsesformular</title>
<script LANGUAGE="JavaScript"  TYPE="text/javascript" SRC="../javascript/overlib.js"></script>
<link rel="stylesheet" href="https://saldi.dk/cms/templates/ja_hedera/css/template.css" type="text/css" />

<style type="text/css">
<!--
tr,p{color: #666666;font-size: 12px;}
textarea{height:80px;width:150px;}
.inputbox{width:150px;}
.inputbox-small{width:40px;}
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

include "../includes/connect.php";
include "../includes/db_query.php";
$pakke="0"; $returadresse="";
$betaling_1=NULL;$betaling_2=NULL;$betaling_3=NULL;$betaling_4=NULL;
if (isset($_GET['pakke'])) {
	$pakke=$_GET['pakke'];
}
if (isset($_GET['kontrol_id'])) {
	$kontrol_id=$_GET['kontrol_id'];
#echo "Kontrol id: $_GET[kontrol_id]<br>";
#cho __line__."<br>";
	createCustomer($kontrol_id);
	exit;
}
#if ($pakke>4) $pakke=1;
#if ($pakke<1 && !strstr($_SERVER['PHP_SELF'],'gratis')) {
#	print "<meta http-equiv=refresh content=0;url=https://ssl2.saldi.dk:88/gratis/opret/opret_mig.php>";
#}

$navn = ''; $firma = ''; $cvr = ''; $adresse = ''; $adresse2 = ''; $postnr = ''; $bynavn = ''; $telefon = ''; $email = '';
if (!$pakke) $pakke = ''; $pakke_1 = ''; $pakke_2 = ''; $pakke_3 = ''; $pakke_4 = ''; $pakke_5 = '';
$regnskab = ''; $brugerantal = 0; $eventuelt = ''; $betingelser = ''; $brugernavn = ''; $kodeord = ''; $kodeord2 = '';

if (isset($_POST['navn']) && isset($_POST['email'])) {
#include "top.php";
	$s_id = db_escape_string($_POST['s_id']);
	$referer = db_escape_string($_POST['referer']);
	$affiliate = db_escape_string($_POST['affiliate']);
	$navn = db_escape_string($_POST['navn']);
	$firma = db_escape_string($_POST['firma']);
	$cvr = db_escape_string($_POST['cvr']);
	$adresse = db_escape_string($_POST['adresse']);
	$adresse2 = db_escape_string($_POST['adresse2']);
	$postnr = db_escape_string($_POST['postnr']);
	$bynavn = db_escape_string($_POST['bynavn']);
	$telefon = db_escape_string($_POST['telefon']);
	$email = db_escape_string($_POST['email']);
	$regnskab = db_escape_string($_POST['regnskab']);
	if ($pakke>=5) $regnskab2 = db_escape_string($_POST['regnskab2']);
	if ($pakke>=3) $brugerantal = db_escape_string($_POST['brugerantal']);
	$betaling = db_escape_string($_POST['betaling']);
	$eventuelt = db_escape_string($_POST['eventuelt']);
	$betingelser = db_escape_string($_POST['betingelser']);
	$brugernavn = $email;
	$kodeord = $telefon;
	$kodeord2 = $telefon;
#	$brugernavn = db_escape_string($_POST['brugernavn']);
#	$kodeord = db_escape_string($_POST['kodeord']);
#	$kodeord2 = db_escape_string($_POST['kodeord2']);


	$to = stripslashes($email);
	$unixdate=date('U');
	$sidste_uge=date("Y-m-d",$unixdate-(60*60*24*7));
	$fejl=0;
/*
	$q1=db_select("select id from regnskab where regnskab='$regnskab'",__FILE__ . " linje " . __LINE__);
	$q2=db_select("select id from kundedata where regnskab='$regnskab' and aktiv = '0'",__FILE__ . " linje " . __LINE__);
	if ($r1 = db_fetch_array($q1)) {
		print "<BODY onLoad=\"javascript:alert('Der er allerede oprettet et regnskab med navnet $regnskab')\">";
		$fejl=1;
	}	elseif ($r2 = db_fetch_array($q2)) {
		print "<BODY onLoad=\"javascript:alert('Der eksisterer et inaktivt regnskab med navnet $regnskab')\">";
		$fejl=1;
	}	elseif ($kodeord!=$kodeord2) {
		print "<BODY onLoad=\"javascript:alert('De 2 kodeord skal være ens')\">";
		$fejl=1;
	}
*/
	if (!$fejl) {
		$dd=date("Y-m-d");
		$kontrol_id="";
		srand((double)microtime()*1000000);
		$chars = 'ABCDEFGHIJKLNMOPQRSTUVXYabcdefghijklmnopqrstuvwxyz0123456789';
		for ($i=0; $i<3; $i++) $kontrol_id.= $chars[rand(0, strlen($chars)-1)];
		$kontrol_id.=date("U");
		for ($i=0; $i<3; $i++) $kontrol_id.= $chars[rand(0, strlen($chars)-1)];
		$qtxt = "INSERT INTO kundedata (firmanavn,cvrnr,tlf,email,oprettet,kontrol_id) values ";
		$qtxt.= "('$firma','$cvr','$telefon' ,'$email','$dd','$kontrol_id')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "SELECT MAX(id) as regnskab from kundedata where kontrol_id = '$kontrol_id' and ";
		$qtxt.= "firmanavn = '$firma' and cvrnr = '$cvr' and tlf = '$telefon' and email = '$email' and oprettet = '$dd'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$regnskab = $r['regnskab'];
	}
	if (!$fejl) {
		/* $date = date("Y-m-d");
		$amount = 349;
		$name = $navn;
		$linkadresse="https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?kontrol_id=".$kontrol_id;
		$ordreLinjer = array("Komplet regnskabspakke", "personlig-support");
		($eventuelt) ? $placeholder = "<p style='margin: 0;'>Eventuelt: ".stripslashes($eventuelt)."</p>" : $placeholder = "";
		include "email.php"; */
		$message = "Tak for din bestilling af Saldi Professionel	.\n";
		$message.= "til kr. 349,- pr.md. (ex. moms)\n";
		$message.= "Klik på nedestående link for at verificere oprettelsen af dit regnskab:\n$linkadresse\n";
		$message.= "Linket er gyldigt i 7 dage.\n\n";
		$message.= "Tilmeldingsoplysninger:\nNavn: ".stripslashes($navn)."\nFirma: ".stripslashes($firma)."\nCvr: ".stripslashes($cvr);
		//\n$Adresse: ".stripslashes($adresse)."\n";
#		if ($adresse2) $message .= ", ".stripslashes($adresse2);
#		$message .= "\nPostnr/By: ".stripslashes($postnr)." ".stripslashes($bynavn)."\nTelefon: ".stripslashes($telefon)."\ne-mail: ".stripslashes($email)."\n";
		$message.= "Regnskab: ".stripslashes($regnskab)."\n";
		$message.= "Din email og telefonnummer anvendes som hhv. brugernavn og adgangskode ved login.\n";
		$message.= "Du kan ændre brugernavn og password under Indstillinger -> Brugere.\n";
		$message.= "Her kan du også tilføje flere brugere.\n";
		if ($eventuelt) $message .= "Eventuelt: ".stripslashes($eventuelt)."\n";
#		$message .= "\nBemærk at navnet på regnskabet skal skrives nøjagtigt som angivet. \nDer skelnes mellem store og små bogstaver.\n";
#		$message .= "Ved første login kommer man direkte ind i oprettelse af 1. regnskabsår.\nDette SKAL oprettes inden regnskabet kan bruges.\n";
		$message .= "Herefter kan du finde dit regnskab på https://saldi.dk/finans \n\n";

#		$message .= "På https://forum.saldi.dk kan der findes svar på de fleste spørgsmål.\n";
		$message .= "Brugervejledningen kan findes her:  https://saldi.dk/dok/index.htm\n";
#		$message .= "og du kan finde en videomanual her: https://flash.saldi.dk \n";
#		$message .= "Du kan opdatere til en professionelt version af Saldi med hotline, backup og sikker server for kun kr. 150,- pr. md.\n";
#		$message .= "Klik her for at læse mere om den professionelle løsning: https://saldi.dk/professionel\n";
		$message .= "Ring gerne på telefon 4690 2208, for mere information \n\n";
		$message .= "Velkommen til og god fornøjelse\n\n";
		$message .= "Med venlig hilsen\n";
		$message .= "saldi.dk aps\n";

#		$message=utf8_decode($message);

		ini_set("include_path", ".:../phpmailer");
		require("class.phpmailer.php");

		$mail = new PHPMailer();
		$mail->SMTPDebug = false;
		$mail->IsSMTP();                                   // send via SMTP
		$mail->Host  = "localhost"; // SMTP servers
		$mail->SMTPAuth = false;     // turn on SMTP authentication
		$afsendermail='saldi@ssl2.saldi.dk';
		$afsendernavn='SALDI ';

		$mail->SetFrom($afsendermail,$afsendernavn);
#		$mail->From  = $afsendermail;
#		$mail->FromName = $afsendernavn;
		$mail->AddAddress($to);
		$mail->AddBCC('brugere@saldi.dk');
		if (strstr($_SERVER['PHP_SELF'],'gratis')) $mail->Subject  =  "Tilmelding som SALDI gratis bruger";
		else $mail->Subject  =  "Bestilling af SALDI abonnement";
		$mail->Charset  =  "utf-8";
		$mail->Body     =  $message;
#		$mail->AltBody  =  "Hermed fremsendes kontoudtog fra $afsendernavn";

		if(!$mail->Send()){
			echo "Fejl i afsendelse til $to<p>";
  			echo "Mailer Error: " . $mail->ErrorInfo;
	 		exit;
		}


		print "<p>Tak for din tilmelding.<br>Der er sendt en e-mail til $email</p>";
#		print "<p>Hvis du ikke har modtaget din tilmeldingsmail inden for 15 minutter, kan du kontrollere om mailen er blever filtreret som uønsket post af dit mailprogram</p>";
#		if ($returadresse == "null") $returadresse="https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?returadresse=null";
#		else $returadresse="https://".$returadresse;
#		print "<meta http-equiv=refresh content=0;url=$returadresse>";
		exit;
	}
}
#elseif (isset($returadresse)) {
#	?<body onload="alert('Beklager - afsendelse mislykkedes, prøv venligst igen')"><?php
#	print "<meta http-equiv=refresh content=0;url=opret_mig.php>";
#}
	?>
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
			alert("Navn skal indeholde minimum 3 karakterer.");
			theForm.navn.focus();
			return (false);
		}
		if (theForm.telefon.value.length < 8) {
			alert("Telefon nummer er ikke korrekt udfyldt");
			theForm.telefon.focus();
			return (false);
		}
		// if (theForm.adresse.value.length < 3) {
		// 	alert("Adresse skal indeholde minimum 3 karakterer.");
		// 	theForm.adresse.focus();
		// 	return (false);
		// }
		// if (theForm.postnr.value.length < 4 || theForm.postnr.value.length > 10) {
		// 	alert("Postnr skal indeholde min. 4 og max. 10 tegn.");
		// 	theForm.postnr.focus();
		// 	return (false);
		// }
		// if (theForm.bynavn.value.length < 2) {
		// 	alert("bynavn skal indeholde minimum 2 karakterer.");
		// 	theForm.bynavn.focus();
		// return (false);
		// }
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
			alert("\"e-mail\" er ikke gyldig");
			theForm.email.focus();
			return (false);
		}
		if (theForm.regnskab.value == "") {
			alert("Navn på regnskab skal udfyldes.");
			theForm.regnskab.focus();
			return (false);
		}
		if (theForm.brugernavn.value == "") {
			alert("Brugernavn skal udfyldes.");
			theForm.brugernavn.focus();
			return (false);
		}
		// if (theForm.kodeord.value.length < 5) {
		// 	alert("Kodeord skal indeholde min. 5 karakterer.");
		// 	theForm.kodeord.focus();
		// 	return (false);
		// }
		// if (theForm.kodeord.value != theForm.kodeord2.value) {
		// 	alert("Kodeord skal være ens.");
		// 	theForm.kodeord.focus();
		// 	return (false);
		// }
		if (!theForm.betingelser.checked) {
			alert("Betingelser skal accepteres");
			theForm.betingelser.focus();
			return (false);
		}
		return (true);
	}
//--></script>
	<!--<form action="opret_mig.php?returadresse=<?php echo $returadresse ?>"  onsubmit="return Form1_Validator(this)" method="POST" name="Form1">-->
	<form action="opret_proff.php" onsubmit="return Form1_Validator(this)" method="POST" name="Form1">
  	<TABLE BORDER="0"  CELLSPACING="0" align=center>
		<?php if ($returadresse != "null") {
#			print "<tr><td><small>";
#			print "<a href=https://$returadresse>Tilbage</a>";
#			print "</small></td></tr>";
#	        	print "<tr><td colspan=2><hr></td></tr>";
		}
		$tekst="Udfyld formularen og klik p&aring; \"Opret regnskab\" S&aring; vil du modtage en mail med et link til dit nye SALDI regnskab,<br>";
		if ($pakke==1) $tekst=$tekst." med max. 500 årlige posteringer og 1 bruger, til kr 45,- pr. md.";
		if ($pakke==2) $tekst=$tekst." med max. 2500 årlige posteringer og 1 bruger, til kr 90,- pr. md.";
		if ($pakke==3) $tekst=$tekst." med max. 10000 årlige posteringer og 2 samtidige brugere til kr 180,- pr. md.";
		if ($pakke==4) $tekst=$tekst." med ubegrænset antal posteringer, 2 samtidige brugere og 2 regnskaber til kr 270,- pr. md.";
		?>
		<tr>
			<td><input type=text name=navn class="inputbox" style="width:250px;" placeholder='Navn *' value="<?php echo "$navn" ?>"></td>
				</tr><tr><td></td></tr>
				<tr>
					<td><input type=text name=firma class="inputbox" style="width:250px;" placeholder='Firmanavn' value="<?php echo "$firma" ?>"></td>
        </tr><tr><td></td></tr>
			<tr>
				<td><input type=text name=cvr class="inputbox" style="width:250px;" placeholder='Evt. CVR nr.' value="<?php echo "$cvr" ?>"></td>
			</tr><tr><td></td></tr>
 		<tr>
			<td><input type=text name=telefon class="inputbox" style="width:250px;" placeholder='Telefon *' value="<?php echo "$telefon" ?>"></td>
		</tr><tr><td></td></tr>
		<tr>
			<td><input type=text name=email class="inputbox" style="width:250px;" placeholder='e-mail *' value="<?php echo "$email" ?>"></td>
		</tr><tr><td></td></tr>
		<tr><td>Accepterer <a onMouseOver="this.style.cursor = 'pointer'" onClick="javascript:betingelser=window.open('Saldi-handelsbetingelser.pdf','betingelser','left=10,top=10,width=1200,height=800,scrollbars=1,resizable=1');betingelser.focus();"><u>betingelser</u></a>
			<?php if ((isset($betingelser))&&($betingelser=='on')) $betingelser="checked"; ?>
			<input type=CHECKBOX name="betingelser" <?php echo "$betingelser" ?>></td>
		</tr><tr><td></td></tr>
		</tr>
			 <td style="text-align: center;">
			 <input style="width:250px;" value="Opret regnskab" name="SUBMIT" class="button" type="submit"><br>
<!--			Udfyld&nbsp;venligst&nbsp;s&aring;&nbsp;meget&nbsp;som&nbsp;muligt<br>
			Felter&nbsp;markeret&nbsp;med&nbsp;*&nbsp;skal&nbsp;udfyldes<br>
			Du&nbsp;modtager&nbsp;en&nbsp;E-mail&nbsp;med&nbsp;et&nbsp;link&nbsp;til&nbsp;dit&nbsp;nye&nbsp;regnskab<br> -->
			<?php #echo "<FONT color=\"#ffffff\">$ip<br>$user_agent<br>$referer<br>$affiliate</font>"?></td>
	 	</tr>
	</TABLE>
<?php
print "<input type=hidden name=s_id value=\"$s_id\"><br>\n";
print "<input type=hidden name=referer value=\"$referer\"><br>\n";
print "<input type=hidden name=affiliate value=\"$affiliate\"><br>\n";
echo "</form>";
function createCustomer($kontrol_id) {
?>
	<center>
		<table valign=top border=0><tbody><tr><td colspan="2" align="center"><img alt="Saldi logo" src="detfriedanske.jpg" style="border: 0px solid ;"></td></tr>
	<tr><td colspan=2><hr style="width: 100%; background-color: 000077; color: blue; height: 5px;"></td></tr>
<?php
	$header="nix";
	$bg="nix";
	include "../includes/connect.php";
	include "../includes/db_query.php";
	$sidste_uge=date("Y-m-d",$unixdate-(60*60*24*7));

	$qtxt = "select * from kundedata where kontrol_id='$kontrol_id'";
#cho "$qtxt<br>";
	$q = db_select( $qtxt,__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$id=$r['id'];
		$regnskab=$r['regnskab'];
		$brugernavn=$r['brugernavn'];
		$kodeord=$r['kodeord'];
		$kontakt=$r['kontakt'];
		$firmanavn=$r['firmanavn'];
		$addr1=$r['addr1'];
		$addr2=$r['addr2'];
		$postnr=$r['postnr'];
		$bynavn=$r['bynavn'];
		$tlf=$r['tlf'];
		$email=$r['email'];
		$cvrnr=$r['cvrnr'];
		if (!$regnskab) $regnskab = $id;
		if (!$brugernavn) $brugernavn = $email;
		if (!$kodeord) $kodeord = $tlf;
	} else {
		print "<BODY onLoad=\"javascript:alert('Aktiveringskode: $kontrol_id findes ikke')\">";
	}
	if ((isset($regnskab))&&(isset($brugernavn))&&(isset($kodeord))) {
		$qtxt="select id from regnskab where regnskab='".db_escape_string($regnskab)."'";
		$query=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		if (!db_fetch_array($query)) {
			db_modify("update kundedata set aktiv=1 where id = $id",__FILE__ . " linje " . __LINE__);
#			print "<table  border=1><tbody>";
			print "<form action=\"../admin/opret.php\"  method=\"POST\" name=\"opret\">\n";
			print "<tr><td colspan=2 align=center>Aktivering af dit regnskab i SALDI</td></tr>\n";
			print "<tr><td colspan=2 align=center><hr></td></tr>\n";
			print "<tr><td>Firma</td><td>$firmanavn</td></tr>\n";
			print "<tr><td>Navn</td><td>$kontakt</td></tr>\n";
			print "<tr><td>Regnskab</td><td>$regnskab</td></tr>\n";
			print "<tr><td>Brugernavn</td><td>$brugernavn</td></tr>\n";

			print "<tr><td colspan=2><hr></td></tr>";
			print "<tr><td colspan=2>Når der klikkes [OK] initialiseres alle tabeller</td></tr>\n";
			print "<tr><td colspan=2>og kontoplan, formularer mm. indlæses.</tr>\n";
			print "<tr><td colspan=2>Dette kan vare flere minutter, så vær tålmodig.</tr>\n";
			print "<tr><td colspan=2>Herefter kan du logge ind i dit regnskab med din mailadresse</tr>\n";
			print "<tr><td colspan=2>som brugernavn og dit telefonnummer som adgangskode.</tr>\n";
			print "<tr><td colspan=2>Velkommen til og god fornøjelse.</tr>\n";
			print "<tr><td colspan=2 align=center><hr></td></tr>";

			$posteringer=300;
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
		} else {
			print "<BODY onLoad=\"javascript:alert('Regnskab ".addslashes($regnskab)." er allerede aktiveret')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/index.php?regnskab=$regnskab&brugernavn=$brugernavn\">";
		}
		print "</div></body></html>";
	}
	exit;
}
?>
</div>
</body>
</html>
