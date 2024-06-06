<!-- opret/messetilbud.php * 2008-09-16 -->
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
if (isset($_GET['pakke'])) {
	$pakke=$_GET['pakke'];
}
if (isset($_GET['kontrol_id'])) {
	$kontrol_id=$_GET['kontrol_id'];
#echo "Kontrol id: $_GET[kontrol_id]<br>";
	opret($kontrol_id);	
	exit;
}

$navn = ''; $firma = ''; $cvr = ''; $adresse = ''; $adresse2 = ''; $postnr = ''; $bynavn = ''; $telefon = ''; $email = '';
$regnskab = ''; $brugerantal = 0; $eventuelt = ''; $betingelser = ''; $brugernavn = ''; $kodeord = ''; $kodeord2 = '';

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
	$tilbud = addslashes($_POST['tilbud']);
	$eventuelt = addslashes($_POST['eventuelt']);
	$betingelser = addslashes($_POST['betingelser']);
#	$brugernavn = addslashes($_POST['brugernavn']);
#	$kodeord = addslashes($_POST['kodeord']);
#	$kodeord2 = addslashes($_POST['kodeord2']);
	$brugernavn = $email;
	$kodeord = $telefon;

	$to = stripslashes($email);
	
	$subject = "Oprettelse som SALDI kunde";

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
		$kontrol_id=$pakke.":".$kontrol_id.":".$brugerantal;
		$linkadresse="https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?kontrol_id=".$kontrol_id;
		$message = "Tak for din bestilling af ";
		
		if ($tilbud==1) $message=$message." 12 måneders SALDI abonnement med 4 timers kursus til kr. 1.500,-\n";
		if ($tilbud==2) $message=$message." 12 måneders SALDI abonnement til kr. 300,-\n";
		if ($tilbud==3) $message=$message." 6 måneders SALDI abonnement med 4 timers kursus til kr. 1.200,-\n";
		if ($tilbud==4) $message=$message." 6 måneders SALDI abonnement til kr. 200,-\n";

		$message .= "\nKlik på nederstående link for at bekræfte bestillingen og verificere oprettelsen af dit regnskab:\n$linkadresse\n";
		$message .= "Linket er gyldigt i 7 dage\n\n";
		$message .= "Tilmeldingsoplysninger:\nNavn: ".stripslashes($navn)."\nFirma: ".stripslashes($firma)."\nCvr: ".stripslashes($cvr)."\nAdresse: ".stripslashes($adresse)."\n";
		if ($adresse2) $message .= ", ".stripslashes($adresse2);
		$message .= "\nPostnr/By: ".stripslashes($postnr)." ".stripslashes($bynavn)."\nTelefon: ".stripslashes($telefon)."\ne-mail: ".stripslashes($email)."\n";
		$message .= "Regnskab: ".stripslashes($regnskab)."\n";
		$message .= "Din email og telefonnummer anvendes som hhv. brugernavn og password ved login.\n"; 
		$message .= "Du kan ændre brugernavn og password under Indstillinger -> Brugere.\n";
		$message .= "Her kan du også tilføje flere brugere.\n";
		if ($eventuelt) $message .= "Eventuelt: ".stripslashes($eventuelt)."\n";
		$message .= "\nBemærk at navnet på regnskabet skal skrives nøjagtigt som angivet. \nDer skelnes mellem store og små bogstaver.\n";
		$message .= "Ved første login kommer du direkte ind i oprettelse af 1. regnskabsår.\nDette SKAL oprettes inden regnskabet kan bruges.\n";
		$tmp=htmlentities(stripslashes($regnskab));
		$message .= "Herefter kan du finde dit regnskab på http://www.saldi.dk/finans?regnskab=$tmp \n\n";
		$message .= "På http://forum.saldi.dk kan der findes svar på de fleste spørgsmål.\n";
		$message .= "Bemærk at forumet er passwordbeskyttet - Skriv Saldi (med stort S) som både brugernavn og password.\n";
		$message .= "Brugervejledningen kan findes her:  http://www.saldi.dk/dok/index.htm\nog du kan finde en videomanual her: http://flash.saldi.dk \n";
		$message .= "Som SALDI kunde har du adgang til hotline og ubegrænset e-mail support. Benyt mailadressen support@saldi.dk.\n";
		$message .= "Har du iøvrigt spørgsmål, eller hvis der er andet vi kan hjælpe med, så ring blot på telefon 4690 2208.\n\n";
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
		$mail->Subject  =  "Bestilling af SALDI messetilbud";
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
		if (!theForm.betingelser.checked) {
			alert("Betingelser skal accepteres");
			theForm.betingelser.focus();
			return (false);
		}
		return (true);
	}
//--></script>
	<!--<form action="opret_mig.php?returadresse=<?php echo $returadresse ?>"  onsubmit="return Form1_Validator(this)" method="POST" name="Form1">-->
	<form action="messetilbud.php"  onsubmit="return Form1_Validator(this)" method="POST" name="Form1">
  	<TABLE BORDER="0"  CELLSPACING="0" align=center>
	<?php	
		$tekst="Udfyld formularen og klik på \"Opret regnskab\" Så vil du modtage en mail med en link til dit nye SALDI regnskab,<br>";
		?>
		<tr>	<td colspan=2><?php echo $tekst ?></td>
        	</tr><tr><td colspan=2><hr></td></tr>
        	<tr><td width="100">Navn *</td>
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
			<tr>	<td>Telefon *</td>
		     	<td><input type=text name=telefon class="inputbox" size="50" value="<?php echo "$telefon" ?>"></td>
		</tr><tr><td></td></tr>
		<tr>	<td title="Skriv en fungerende e-mail - Aktiveringskode sendes til denne mail adresse">e-mail *</td>
		     	<td title="Skriv en fungerende e-mail - Aktiveringskode sendes til denne mail adresse"><input type=text name=email class="inputbox" size="50" value="<?php echo "$email" ?>"></td>
		</tr><tr><td></td></tr>
		<tr><td title="Skriv et navn du kan huske, f.eks firmanavn. Du skal bruge dette navn når du logger ind">&Oslash;nsket navn p&aring; regnskab *</td>
		     	<td title="Skriv et navn du kan huske, f.eks firmanavn. Du skal bruge dette navn når du logger ind"><input type=text name=regnskab class="inputbox" size="50" value="<?php echo "$regnskab" ?>"></td>
		</tr><tr><td></td></tr>
<!--		
						<tr><td>&Oslash;nsket&nbsp;brugernavn&nbsp;for&nbsp;administrator&nbsp;*</td>
		     	<td><input type=text name=brugernavn class="inputbox" size="50" value="<?php echo "$brugernavn" ?>"></td>
		</tr><tr><td></td></tr>
		<tr><td>&Oslash;nsket&nbsp;kodeord&nbsp;for&nbsp;administrator&nbsp;*</td>
		     	<td><input type=password name=kodeord class="inputbox" size="50" value="<?php echo "$kodeord" ?>"></td>
		</tr><tr><td></td></tr>
		<tr><td>Gentag&nbsp;&oslash;nsket&nbsp;kodeord&nbsp;for&nbsp;administrator&nbsp;*</td>
		     	<td><input type=password name=kodeord2 class="inputbox" size="50" value="<?php echo "$kodeord2" ?>"></td>
--> 
			<tr><td>&Oslash;nsket tilbud:</span></td>
			<?php
 	 if (!isset($tilbud)) $tilbud=1;
					elseif (!$tilbud) $tilbud=1;
					if ((isset($tilbud))&&($tilbud==1)) $tilbud_1="checked=\"checked\""; 
			?>
			<td><input type=RADIO name="tilbud" value="1" <?php echo "$tilbud_1" ?>><span title="12 m&aring;neders abonnement med 4 timers kursus til kr. 1.500,-">12 m&aring;neders abonnement med 4 timers kursus til kr. 1.500,-</span></td></tr>
			<tr><td></td><td><?php if ((isset($tilbud))&&($tilbud==2)) $tilbud_2="checked=\"checked\""; ?><input type=RADIO  name="tilbud" value="2" <?php echo "$tilbud_2" ?>><span title="12 m&aring;neders abonnement til kr. 300,-"><span title="12 m&aring;neders abonnement til kr. 300,-">12 m&aring;neders abonnement til kr. 300,-</span></td></tr>
<?php
/*
			<tr><td></td><td><?php if ((isset($tilbud))&&($tilbud==3)) $tilbud_3="checked=\"checked\""; ?><input type=RADIO  name="tilbud" value="3" <?php echo "$tilbud_3" ?>><span title="6 m&aring;neders abonnement med 4 timers kursus til kr. 1.200,-">6 m&aring;neders abonnement med 4 timers kursus til kr. 1.200,-</span></td></tr>
			<tr><td></td><td><?php if ((isset($tilbud))&&($tilbud==4)) $tilbud_4="checked=\"checked\""; ?><input type=RADIO  name="tilbud" value="4" <?php echo "$tilbud_4" ?>><span title="6 m&aring;neders abonnement til kr. 200,-">6 m&aring;neders abonnement til kr. 200,-</span></td></tr>
*/
?>
			</td>
			</tr><tr><td></td></tr>
		<tr>	<td>Eventuelt</td>
		     	<td><textarea name="eventuelt" rows="3" wrap="Virtual" cols="45"><?php echo "$eventuelt" ?></textarea></td>
		</tr><tr><td></td></tr>
		     	<tr>	<td>Accepterer <a onMouseOver="this.style.cursor = 'pointer'" onClick="javascript:betingelser=window.open('messebetingelser.htm','betingelser','left=10,top=10,width=400,height=400,scrollbars=1,resizable=1');betingelser.focus();"><u>betingelser</u></a></td>
        		<?php if ((isset($betingelser))&&($betingelser=='on')) $betingelser="checked"; ?>
        	<td><input type=CHECKBOX name="betingelser" <?php echo "$betingelser" ?>></td>
		</tr><tr><td><br></td></tr>
		</tr><td><br></td>
			 <td style="text-align: left;" colspan="2"><input value="Opret regnskab" name="SUBMIT" class="button" type="submit"><br>
			</td></tr><tr><td  colspan="3">
			<br>
			Udfyld&nbsp;venligst&nbsp;s&aring;&nbsp;meget&nbsp;som&nbsp;muligt.<br>
			Felter&nbsp;markeret&nbsp;med&nbsp;*&nbsp;skal&nbsp;udfyldes.<br>
			Alle&nbsp;tilbud&nbsp;er&nbsp;incl.&nbsp;500 årlige posteringer.<br>
			Du&nbsp;modtager&nbsp;en&nbsp;e-mail&nbsp;med&nbsp;et&nbsp;link&nbsp;til&nbsp;dit&nbsp;nye&nbsp;regnskab.<br>
 	
			
			Alle&nbsp;priser&nbsp;er&nbsp;excl.&nbsp;moms.</td>
	
	</tr>
	</TABLE>
	</form>
<?php
function opret($kontrol_id) {	
?>	
	<center>
		<table valign=top border=0><tbody><tr><td colspan=2><img alt="Saldi logo" src="logo.png" style="border: 0px solid ;"></td></tr>
	<tr><td colspan=2><hr style="width: 100%; background-color: blue; color: blue; height: 5px;"></td></tr>
<?php	
	$header="nix";
	$bg="nix";
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
		print "<BODY onLoad=\"javascript:alert('Aktiveringskode: $kontrol_id findes ikke')\">";
	}	
	if ((isset($regnskab))&&(isset($brugernavn))&&(isset($kodeord))) {
		$query=db_select("select id from regnskab where regnskab='".addslashes($regnskab)."'",__FILE__ . " linje " . __LINE__);
		if (!db_fetch_array($query)) {
			$aktiveret=date("Y-m-d");
			db_modify("update kundedata set aktiv='1',aktiveret='$aktiveret' where id = $id",__FILE__ . " linje " . __LINE__);
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
			
			$posteringer=500;
			$brugerantal=1;
			
			if (!$firmanavn) {
				$firmanavn=$kontakt;
				$kontakt="";
			}
			
			print "<input type=hidden name=regnskab value=\"$regnskab\"><br>\n";
			print "<input type=hidden name=brugernavn value=\"$brugernavn\"><br>\n";
			print "<input type=hidden name=passwd value=\"$kodeord\"><br>\n";
			print "<input type=hidden name=passwd2 value=\"$kodeord\"><br>\n";
			print "<input type=hidden name=brugerantal value=\"$brugerantal\"><br>\n";
			print "<input type=hidden name=posteringer value=\"$posteringer\"><br>\n";
			print "<input type=hidden name=firmanavn value=\"$firmanavn\"><br>\n";
			print "<input type=hidden name=addr1 value=\"$addr1\"><br>\n";
			print "<input type=hidden name=addr1 value=\"$addr2\"><br>\n";
			print "<input type=hidden name=postnr value=\"$postnr\"><br>\n";
			print "<input type=hidden name=bynavn value=\"$bynavn\"><br>\n";
			print "<input type=hidden name=tlf value=\"$tlf\"><br>\n";
			print "<input type=hidden name=email value=\"$email\"><br>\n";
			print "<input type=hidden name=cvrnr value=\"$cvrnr\"><br>\n";
			print "<input type=hidden name=kontakt value=\"$kontakt\"><br>\n";
			print "<input type=hidden name=fra_formular value=\"ja\"><br>\n";
			print "<input type=hidden name=std_kto_plan value=\"on\"><br>\n";
			
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


		     
