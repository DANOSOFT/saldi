<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head><meta content="text/html; charset=ISO-8859-1" http-equiv="content-type"><title>Oprettelsesformular</title>
<link rel="stylesheet" href="http://saldi.dk/cms/templates/ja_hedera/css/template.css" type="text/css" />

<style type="text/css">
<!--
tr,p{color: #666666;font-size: 12px;}
textarea{height:80px;width:150px;}
.inputbox{width:150px;}
.inputbox-small{width:40px;}
-->
</style>

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

if (isset($_GET['returadresse'])) {
	$returadresse=$_GET['returadresse'];
}
elseif (isset($_GET['kontrol_id'])) {
	$kontrol_id=$_GET['kontrol_id'];
echo "Kontrol id: $_GET[kontrol_id]<br>";
	opret($kontrol_id);	
	exit;
}
#else exit;
#else print "<meta http-equiv=refresh content=0;url=http://www.saldi.dk/bliv_kunde.php>";

$navn = '';
$firma = '';
$cvr = '';
$adresse = '';
$adresse2 = '';
$postnr = '';
$bynavn = '';
$telefon = '';
$email = '';
$pakke = '';
$pakke_1 = '';
$pakke_2 = '';
$pakke_3 = '';
$pakke_4 = '';
$pakke_5 = '';
$regnskab = '';
$brugerantal = 2;
$eventuelt = '';
$betingelser = '';
$brugernavn = '';
$kodeord = '';
$kodeord2 = '';

if (isset($_POST['navn']) && isset($_POST['email'])) {
#include "top.php";
	$navn = ($_POST['navn']);
	$firma = ($_POST['firma']);
	$cvr = ($_POST['cvr']);
	$adresse = ($_POST['adresse']);
	$adresse2 = ($_POST['adresse2']);
	$postnr = ($_POST['postnr']);
	$bynavn = ($_POST['bynavn']);
	$telefon = ($_POST['telefon']);
	$email = ($_POST['email']);
	$pakke = ($_POST['pakke']);
	$regnskab = ($_POST['regnskab']);
	$brugerantal = ($_POST['brugerantal']);
	$eventuelt = ($_POST['eventuelt']);
	$betingelser = ($_POST['betingelser']);
	$brugernavn = ($_POST['brugernavn']);
	$kodeord = ($_POST['kodeord']);
	$kodeord2 = ($_POST['kodeord2']);


	$to = $email;
	
	$subject = "Oprettelse som SALDI kunde";

	include "../includes/connect.php";
	include "../includes/db_query.php";
	$query=db_select("SELECT relname FROM pg_class WHERE relname = 'kundedata'");
	if(! db_fetch_array($query))  db_modify("CREATE TABLE kundedata (id serial NOT NULL, firmanavn varchar,  addr1 varchar, addr2 varchar, postnr varchar, bynavn varchar, kontakt varchar, tlf varchar, email varchar, cvrnr varchar, regnskab varchar, brugernavn varchar, kodeord varchar, kontrol_id varchar, aktiv smallint, PRIMARY KEY (id))");
	$query=db_select("select id from kundedata where regnskab='$regnskab'");
	if ($row = db_fetch_array($query)) {
		print "<BODY onLoad=\"javascript:alert('Der er allerede oprettet et regnskab med navnet $regnskab')\">";
	}
	$query=db_select("select id from regnskab where regnskab='$regnskab'");
	$q2=db_select("select id from kundedata where regnskab='$regnskab'");
	if ($row = db_fetch_array($query)) {
		print "<BODY onLoad=\"javascript:alert('Der er allerede oprettet et regnskab med navnet $regnskab')\">";
	}
	elseif ($row = db_fetch_array($q2)) {
		print "<BODY onLoad=\"javascript:alert('Der er et eksisterer et inaktivt regnskab med navnet $regnskab')\">";
	}
	elseif ($kodeord!=$kodeord2) {
		print "<BODY onLoad=\"javascript:alert('De 2 kodeord skal være ens')\">";
	}
	else { 
	       srand((double)microtime()*1000000);
	        $chars = 'ABCDEFGHIJKLNMOPQRSTUVXYabcdefghijklmnopqrstuvwxyz0123456789';
       		 for ($i=0; $i<16; $i++) {
			$kontrol_id .= $chars[rand(0, strlen($chars)-1)];
		}
		$linkadresse="https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?kontrol_id=".$kontrol_id;
		if (strstr($_SERVER['PHP_SELF'],'gratis')) $message = "Tak for din tilmedling som SALDI gratis bruger.\n";
		else $message = "Tak for din oprettelse som SALDI abonnent.\n";
		$message .= "Klik på nederstående link for at verificere oprettelsen af dit regnskab:\n$linkadresse\n\n";
		$message .= "Tilmeldingsoplysninger:\nNavn: $navn \nFirma: $firma\nCvr: $cvr\nAdresse: $adresse";
		if ($adresse2) $message .= ", $adresse2";
		$message .= "\nPostnr/By: $postnr $bynavn\nTelefon: $telefon\ne-mail: $email\n";
		if (strstr($_SERVER['PHP_SELF'],'gratis')) {
			$message .= "Regnskab: $regnskab\nBrugernavn: $brugernavn\nEventuelt: $eventuelt\n\n";
		}
		else {
			if ($pakke==1) $message .= "Bestilt: Prøveperiode 6 mdr. til kr. 450,- excl. moms.\n";
			if ($pakke==2) $message .= "Bestilt: Prøveperiode 6 mdr. med 1 times kom i gang assistance til kr. 600.,- excl. moms.\n";
			if ($pakke==3) $message .= "Bestilt: 12 mdr. periode med 2 timers kom i gang assistance til kr. 2.160,- excl. moms.\n";
			if ($pakke==4) $message .= "Bestilt: 24 mdr. periode med 5 timers kom i gang assistance til kr. 4.320,- excl. moms.\n";
			if ($pakke==5) $message .= "Bestilt: 36 mdr. periode med 10 timers kom i gang assistance til kr. 7.200,- excl. moms.\n";
			$message .= "Regnskab: $regnskab\nBrugernavn: $brugernavn\nBrugerantal: $brugerantal \nEventuelt: $eventuelt\n\n";
		}
		$message .= "Bemærk at navnet på regnskabet skal skrives nøjagtigt som angivet. \nDer skelnes mellem store og små bogstaver.\n";
		$message .= "Ved første login kommer man direkte ind i oprettelse af 1. regnskabsår.\nDette SKAL oprettes. Ellers vil der opstå fejl.\n";
		if (strstr($_SERVER['PHP_SELF'],'gratis')) $message .= "Herefter kan du finde dit regnskab på http://www.saldi.dk/gratis \n\n";
		else $message .= "Herefter kan du finde dit regnskab på http://www.saldi.dk/finans \n\n";
		$message .= "På http://forum.saldi.dk kan der findes svar på de fleste spørgsmål.\n";
		$message .= "Bemærk at forumet er passwordbeskyttet - Skriv Saldi (med stort S) som både brugernavn og password.\n";
		$message .= "Brugervejledningen kan findes her:  http://www.saldi.dk/dok/index.htm\n";
		if (strstr($_SERVER['PHP_SELF'],'gratis')) {
			$message .= "Herudover kan vi tilbyde tilbyde 15 minutters hotline for kun kr. 125,00 ex. moms.\n";
			$message .= "Ring gerne på telefon 4690 2208, for mere information \n\n";
		}
		else {
			$message .= "Som SALDI kunde har du adgang til hotline og ubegrænset e-mail support. Benyt mailadressen support@saldi.dk.\n";
			$message .= "Har du iøvrigt spørgsmål, eller hvis der er andet vi kan hjælpe med, så ring blot på telefon 4690 2208.\n\n";
		}
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
		if (strstr($_SERVER['PHP_SELF'],'gratis')) $mail->AddBCC('saldi@saldi.dk');
		else $mail->AddBCC('phr@saldi.dk');
		if (strstr($_SERVER['PHP_SELF'],'gratis')) $mail->Subject  =  "Tilmelding som SALDI gratis bruger";
		else $mail->Subject  =  "Bestilling af SALDI abonnement";
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
		
		db_modify("INSERT INTO kundedata (firmanavn,  addr1, addr2, postnr, bynavn, kontakt, tlf , email , cvrnr, regnskab, brugernavn, kodeord, kontrol_id, aktiv)values  ('$firma', '$adresse', '$adresse2', '$postnr', '$bynavn', '$navn', '$telefon' , '$email' , '$cvr', '$regnskab', '$brugernavn', '$kodeord', '$kontrol_id', 0)");
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
		if (theForm.adresse.value.length < 3) {
			alert("Adresse skal indeholde minimum 3 karakterer.");
			theForm.adresse.focus();
			return (false);
		}
		if (theForm.postnr.value.length < 4) {
			alert("Postnr skal indeholde minimum 4 karakterer.");
			theForm.postnr.focus();
			return (false);
		}
		if (theForm.bynavn.value.length < 2) {
			alert("bynavn skal indeholde minimum 2 karakterer.");
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
		if (theForm.kodeord.value.length < 5) {
			alert("Kodeord skal indeholde min. 5 karakterer.");
			theForm.kodeord.focus();
			return (false);
		}
		if (theForm.kodeord.value != theForm.kodeord2.value) {
			alert("Kodeord skal være ens.");
			theForm.kodeord.focus();
			return (false);
		}
		if (!theForm.betingelser.checked) {
			alert("Betingelser skal accepteres");
			theForm.betingelser.focus();
			return (false);
		}
		return (true);
	}
//--></script>
	<!--<form action="opret_mig.php?returadresse=<?php echo $returadresse ?>"  onsubmit="return Form1_Validator(this)" method="POST" name="Form1">-->
	<form action="opret_mig.php"  onsubmit="return Form1_Validator(this)" method="POST" name="Form1">
  	<TABLE BORDER="0"  CELLSPACING="0" align=center>
		<?php if ($returadresse != "null") {  
#			print "<tr><td><small>";
#			print "<a href=http://$returadresse>Tilbage</a>";
#			print "</small></td></tr>";
#	        	print "<tr><td colspan=2><hr></td></tr>";
		} ?>
		<tr>	<td width="100">Navn *</td>
          		<td><input type=text name=navn class="inputbox" size="50" value="<?php echo "$navn" ?>"></td>
        	</tr><tr><td></td></tr>
        	<tr>	<td>Firma / Forening</td>
		     	<td><input type=text name=firma class="inputbox" size="50" value="<?php echo "$firma" ?>"></td>
        	</tr><tr><td></td></tr>
		<tr>	<td>Evt. CVR nr.</td>
		     	<td><input type=text name=cvr class="inputbox" size="50" value="<?php echo "$cvr" ?>"></td>
		</tr><tr><td></td></tr>
		<tr>	<td>Adresse *</td>
		     	<td><input type=text name=adresse class="inputbox" size="50" value="<?php echo "$adresse" ?>"></td>
		     	</tr><tr>	     
		     	<td></td><td><input type=text name=adresse2 class="inputbox" size="50" value="<?php echo "$adresse2" ?>"></td>
		</tr><tr><td></td></tr>
	    	 	<tr>	<td> Postnr & By *</td>
		     	<td><input type=text name=postnr class="inputbox inputbox-small" value="<?php echo "$postnr" ?>"><input type=text name=bynavn class="inputbox" size="44" value="<?php echo "$bynavn" ?>"></td>
		</tr><tr><td></td></tr>
			<tr>	<td>Telefon</td>
		     	<td><input type=text name=telefon class="inputbox" size="50" value="<?php echo "$telefon" ?>"></td>
		</tr><tr><td></td></tr>
		<tr>	<td>E-mail *</td>
		     	<td><input type=text name=email class="inputbox" size="50" value="<?php echo "$email" ?>"></td>
		</tr><tr><td></td></tr>
		<tr><td>&Oslash;nsket navn p&aring; regnskab *</td>
		     	<td><input type=text name=regnskab class="inputbox" size="50" value="<?php echo "$regnskab" ?>"></td>
		</tr><tr><td></td></tr>
		<tr><td>&Oslash;nsket&nbsp;brugernavn&nbsp;for&nbsp;administrator&nbsp;*</td>
		     	<td><input type=text name=brugernavn class="inputbox" size="50" value="<?php echo "$brugernavn" ?>"></td>
		</tr><tr><td></td></tr>
		<tr><td>&Oslash;nsket&nbsp;kodeord&nbsp;for&nbsp;administrator&nbsp;*</td>
		     	<td><input type=password name=kodeord class="inputbox" size="50" value="<?php echo "$kodeord" ?>"></td>
		</tr><tr><td></td></tr>
		<tr><td>Gentag&nbsp;&oslash;nsket&nbsp;kodeord&nbsp;for&nbsp;administrator&nbsp;*</td>
		     	<td><input type=password name=kodeord2 class="inputbox" size="50" value="<?php echo "$kodeord2" ?>"></td>
 <?php if (!strstr($_SERVER['PHP_SELF'],'gratis')) { ?>
			</tr><tr><td></td></tr>
			 <tr><td>&Oslash;nsket antal samtidige <a onMouseOver="this.style.cursor = 'pointer'" onClick="javascript:brugere=window.open('brugere.html','brugere','left=10,top=10,width=400,height=400,scrollbars=1,resizable=1');brugere.focus();"><u>brugere</u></a></td>
			     	<td><input type=text name=brugerantal class="inputbox inputbox-small" size="2"  value="<?php echo "$brugerantal" ?>"value=2></td>
		    	</tr><tr><td></td></tr>
		    	</tr><tr><td></td></tr>
        		<tr>	<td>"Kom igang pakke"</td>
			
			<?php
					if (!isset($pakke)) $pakke=1;
					elseif (!$pakke) $pakke=1;
					if ((isset($pakke))&&($pakke==1)) $pakke_1="checked=\"checked\""; 
			?>
			<td><span title="Prøveperiode 6 mdr. til kr. 450,- excl. moms.">pakke 1&nbsp;<input type=RADIO name="pakke" value="1" <?php echo "$pakke_1" ?>></span>
			<?php if ((isset($pakke))&&($pakke==2)) $pakke_2="checked=\"checked\""; ?><span title="Prøveperiode 6 mdr. med 1 times kom i gang assistance til kr. 600.,- excl. moms.">
			&nbsp;&nbsp;&nbsp;pakke 2&nbsp;<input type=RADIO  name="pakke" value="2" <?php echo "$pakke_2" ?>></span>
			<?php if ((isset($pakke))&&($pakke==3)) $pakke_3="checked=\"checked\""; ?><span title="12 mdr. periode med 2 timers kom i gang assistance til kr. 2.160,- excl. moms.">
			&nbsp;&nbsp;&nbsp;pakke 3&nbsp;<input type=RADIO  name="pakke" value="3" <?php echo "$pakke_3" ?>></span>
			<?php if ((isset($pakke))&&($pakke==4)) $pakke_4="checked=\"checked\""; ?><span title="24 mdr. periode med 5 timers kom i gang assistance til kr. 4.320,- excl. moms.">
			&nbsp;&nbsp;&nbsp;pakke 4&nbsp;<input type=RADIO  name="pakke" value="4" <?php echo "$pakke_4" ?>><span>
			<?php if ((isset($pakke))&&($pakke==5)) $pakke_5="checked=\"checked\""; ?><span title="36 mdr. periode med 10 timers kom i gang assistance til kr. 7.200,- excl. moms.">
			&nbsp;&nbsp;&nbsp;pakke 5&nbsp;<input type=RADIO  name="pakke" value="5" <?php echo "$pakke_5" ?>><span>
			</td>
			</tr><tr><td></td></tr>
<?php } ?>		
		<tr>	<td>Eventuelt</td>
		     	<td><textarea name="eventuelt" rows="3" wrap="Virtual" cols="45"><?php echo "$eventuelt" ?></textarea></td>
		</tr><tr><td></td></tr>
		     	<tr>	<td>Accepterer <a onMouseOver="this.style.cursor = 'pointer'" onClick="javascript:betingelser=window.open('betingelser.html','betingelser','left=10,top=10,width=400,height=400,scrollbars=1,resizable=1');betingelser.focus();"><u>betingelser</u></a></td>
        		<?php if ((isset($betingelser))&&($betingelser=='on')) $betingelser="checked"; ?>
        	<td><input type=CHECKBOX name="betingelser" <?php echo "$betingelser" ?>></td>
		</tr><tr><td></td></tr>
		</tr>
			 <td style="text-align: center;" colspan="2"><input value="Opret regnskab" name="SUBMIT" class="button" type="submit"><br>
			Udfyld&nbsp;venligst&nbsp;s&aring;&nbsp;meget&nbsp;som&nbsp;muligt<br>
			Felter&nbsp;markeret&nbsp;med&nbsp;*&nbsp;skal&nbsp;udfyldes<br>
			Du&nbsp;modtager&nbsp;en&nbsp;E-mail&nbsp;med&nbsp;et&nbsp;link&nbsp;til&nbsp;dit&nbsp;nye&nbsp;regnskab</td>
 	</tr>
	</TABLE>
	</form>
<?php
function opret($kontrol_id) {	
?>	
	<table valign=top border=0><tbody><tr><td colspan=2><img alt="Saldi logo" src="logo.png" style="border: 0px solid ;"></td></tr>
	<tr><td colspan=2><hr style="width: 100%; background-color: blue; color: blue; height: 5px;"></td></tr>
<?php	
	$header="nix";
	$bg="nix";
	include "../includes/connect.php";
	include "../includes/db_query.php";
	
	$query=db_select("select * from kundedata where kontrol_id='$kontrol_id'");
	if ($row = db_fetch_array($query)) {
		$id=$row[id];
		$regnskab=$row['regnskab'];
		$brugernavn=$row['brugernavn'];
		$kodeord=$row['kodeord'];
		$kontakt=$row['kontakt'];
		$firmanavn=$row['firmanavn'];
	}
	else {
		print "<BODY onLoad=\"javascript:alert('Aktiveringskode: $kontrol_id findes ikke')\">";
	}	
	if ((isset($regnskab))&&(isset($brugernavn))&&(isset($kodeord))) {
		$query=db_select("select id from regnskab where regnskab='$regnskab'");
		if (!db_fetch_array($query)) {
			db_modify("update kundedata set aktiv=1 where id = $id");
#			print "<table  border=1><tbody>";
			print "<form action=\"../admin/opret.php\"  method=\"POST\" name=\"opret\">\n";
			print "<tr><td colspan=2 align=center>Aktivering af SALDI regnskab</td></tr>\n";
			print "<tr><td colspan=2 align=center><hr></td></tr>\n";
			print "<tr><td>Firma</td><td>$firmanavn</td></tr>\n";
			print "<tr><td>Navn</td><td>$kontakt</td></tr>\n";
			print "<tr><td>Regnskab</td><td>$regnskab</td></tr>\n";
			print "<tr><td>Brugernavn</td><td>$brugernavn</td></tr>\n";
			
			print "<tr><td colspan=2><hr></td></tr>";
			print "<tr><td colspan=2>Når der klikkes [OK] initialiseres alle tabeller</td></tr>\n";
			print "<tr><td colspan=2>og kontoplan, formularer mm. indlæses.</tr>\n";
			print "<tr><td colspan=2>Dette kan vare flere minutter, så vær tålmodig.</tr>\n";
			print "<tr><td colspan=2>Herefter kan du logge ind i dit regnskab med det</tr>\n";
			print "<tr><td colspan=2>kodeord du angav ved oprettelsen.</tr>\n";
			print "<tr><td colspan=2>Velkommen til og god fornøjelse.</tr>\n";
			print "<tr><td colspan=2 align=center><hr></td></tr>";
			
			print "<input type=hidden name=regnskab value='$regnskab'><br>\n";
			print "<input type=hidden name=admin value='$brugernavn'><br>\n";
			print "<input type=hidden name=passwd value='$kodeord'><br>\n";
			print "<input type=hidden name=passwd2 value='$kodeord'><br>\n";
			print "<input type=hidden name=std_kto_plan value='on'><br>\n";
			
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
</html>


		     
