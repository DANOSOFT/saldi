<?php
$referer=NULL;
$ip=$_SERVER['REMOTE_ADDR'];
$user_agent=$_SERVER['HTTP_USER_AGENT'];
$affiliate=0;
$fp=fopen("http://saldi.dk/referer/referer.csv","r");
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
?>
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

/*  �ndringer af 18. maj 2008 af Line Wied, Wied Webdesign:
 *	- Hvis formen postes korrekt bliver svaret echo'et istedet for en  
 *	javascript-alert.
 *	- Fjernet inline <style> og <font> tags. 
 *	- Tilf�jet eksternt og internt stylesheet.
 *  - Fjernet henvisninger til $returadresse, da den ikke skulle bruges.
 *	- Tilf�jet class="inputbox" til alle inputbokse og
 *	  class="button" til submit-knap.  
 */

$pakke="0"; $returadresse=""; 
$betaling=NULL;$betaling_1=NULL;$betaling_2=NULL;$betaling_3=NULL;$betaling_4=NULL;$kontrol_id=NULL;
if (isset($_GET['pakke'])) {
	$pakke=$_GET['pakke'];
}
if (isset($_GET['kontrol_id'])) {
	$kontrol_id=$_GET['kontrol_id'];
#echo "Kontrol id: $_GET[kontrol_id]<br>";
	opret($kontrol_id);	
	exit;
}
if ($pakke>4) $pakke=1;
if ($pakke<1 && !strstr($_SERVER['PHP_SELF'],'gratis')) {
	print "<meta http-equiv=refresh content=0;url=http://ssl2.saldi.dk:88/gratis/opret/opret_mig.php>";
}

$navn = ''; $firma = ''; $cvr = ''; $adresse = ''; $adresse2 = ''; $postnr = ''; $bynavn = ''; $telefon = ''; $email = '';
if (!$pakke) $pakke = ''; $pakke_1 = ''; $pakke_2 = ''; $pakke_3 = ''; $pakke_4 = ''; $pakke_5 = ''; 
$regnskab = ''; $brugerantal = 0; $eventuelt = ''; $betingelser = ''; $brugernavn = ''; $kodeord = ''; $kodeord2 = '';

if (isset($_POST['navn']) && isset($_POST['email'])) {
#include "top.php";
	$s_id = addslashes($_POST['s_id']);
	$referer = addslashes($_POST['referer']);
	$affiliate = addslashes($_POST['affiliate']);
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
	if ($pakke>=5) $regnskab2 = addslashes($_POST['regnskab2']);
	if ($pakke>=3) $brugerantal = addslashes($_POST['brugerantal']);
	$betaling = addslashes($_POST['betaling']);
	$eventuelt = addslashes($_POST['eventuelt']);
	$betingelser = addslashes($_POST['betingelser']);
	$brugernavn = addslashes($_POST['brugernavn']);
	$kodeord = addslashes($_POST['kodeord']);
	$kodeord2 = addslashes($_POST['kodeord2']);


	$to = stripslashes($email);
	
	$subject = "Oprettelse som SALDI kunde";

	include "../includes/connect.php";
	include "../includes/db_query.php";
	$query=db_select("SELECT relname FROM pg_class WHERE relname = 'kundedata'",__FILE__ . " linje " . __LINE__);
	if(! db_fetch_array($query))  db_modify("CREATE TABLE kundedata (id serial NOT NULL, firmanavn varchar,  addr1 varchar, addr2 varchar, postnr varchar, bynavn varchar, kontakt varchar, tlf varchar, email varchar, cvrnr varchar, regnskab varchar, brugernavn varchar, kodeord varchar, kontrol_id varchar, aktiv smallint, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	$query=db_select("select id from kundedata where regnskab='$regnskab'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		print "<BODY onLoad=\"javascript:alert('Der er allerede oprettet et regnskab med navnet $regnskab')\">";
	}
	$query=db_select("select id from regnskab where regnskab='$regnskab'",__FILE__ . " linje " . __LINE__);
	$q2=db_select("select id from kundedata where regnskab='$regnskab'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		print "<BODY onLoad=\"javascript:alert('Der er allerede oprettet et regnskab med navnet $regnskab')\">";
	}
	elseif ($row = db_fetch_array($q2)) {
		print "<BODY onLoad=\"javascript:alert('Der eksisterer et inaktivt regnskab med navnet $regnskab')\">";
	}
	elseif ($kodeord!=$kodeord2) {
		print "<BODY onLoad=\"javascript:alert('De 2 kodeord skal v�re ens')\">";
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
		$message .= "Klik p� nederst�ende link for at verificere oprettelsen af dit regnskab:\n$linkadresse\n";
		$message .= "Linket er gyldigt i 7 dage.\n\n";
		$message .= "Tilmeldingsoplysninger:\nNavn: ".stripslashes($navn)."\nFirma: ".stripslashes($firma)."\nCvr: ".stripslashes($cvr)."\nAdresse: ".stripslashes($adresse)."\n";
		if ($adresse2) $message .= ", ".stripslashes($adresse2);
		$message .= "\nPostnr/By: ".stripslashes($postnr)." ".stripslashes($bynavn)."\nTelefon: ".stripslashes($telefon)."\ne-mail: ".stripslashes($email)."\n";
		if (strstr($_SERVER['PHP_SELF'],'gratis')) {
			$message .= "Regnskab: ".stripslashes($regnskab)."\nBrugernavn: ".stripslashes($brugernavn)."\nEventuelt: ".stripslashes($eventuelt)."\n\n";
		}
		else {
			if ($pakke=='1') { 
				$posteringer = "max. 500 �rlige";
				$pris = 45;
				$brugere = "og 1 bruger";
			}
			if ($pakke=='2') { 
				$posteringer = "max. 2500 �rlige";
				$pris = 90;
				$brugere = "og 1 bruger";
			} 
			if ($pakke=='3') { 
				$posteringer = "max. 15000 �rlige";
				$pris=180+($brugerantal*90);
				$tmp=$brugerantal+2;
				$brugere = "og $tmp samtidige brugere";
			} 
			if ($pakke=='4') { 
				$posteringer = "ubegr�nset antal";
				$pris=270+($brugerantal*90);
				$tmp=$brugerantal+2;
				$brugere = ", $tmp samtidige brugere og 2 regnskaber";
			} 
				
#				$message .= "Bestilt: Saldi regnskab med max. 2500 �rlige posteringer og 1 bruger, til kr 90,- pr. md\n";
#			if ($pakke=='3') $message .= "Bestilt: Saldi regnskab med max. 15000 �rlige posteringer og 2 samtidige brugere til kr 180,- pr. md\n";
#			if ($pakke=='4') $message .= "Bestilt: Saldi regnskab med ubegr�nset antal posteringer, 2 samtidige brugere og 2 regnskaber til kr 270,- pr. md\n";
			
			$message .= "Bestilt: Saldi regnskab med $posteringer posteringer $brugere til kr $pris,- pr. md.\n";	
			
			if ($pakke!='4')	$message .= "Regnskab: ".stripslashes($regnskab)."\nBrugernavn: ".stripslashes($brugernavn)."\n"; #Brugerantal: $brugerantal \nEventuelt: $eventuelt\n\n";
			else $message .= "Regnskab 1: ".stripslashes($regnskab)."\nRegnskab 2: ".stripslashes($regnskab2)."\nBrugernavn: ".stripslashes($brugernavn)."\n"; #Brugerantal: $brugerantal \nEventuelt: $eventuelt\n\n";
			if ($brugerantal>=1) $message .= "Ekstra brugere: $brugerantal \n";
			if ($betaling ==1) $message .= "Fakturering : Hver m�ned.\n";
			if ($betaling ==2) $message .= "Fakturering : Hver 3. m�ned.\n";
			if ($betaling ==3) $message .= "Fakturering : Halv�rlig.\n";
			if ($betaling ==4) $message .= "Fakturering : �rlig.\n";
			if ($eventuelt) $message .= "Eventuelt: $eventuelt\n";
		}
		$message .= "\nBem�rk at navnet p� regnskabet skal skrives n�jagtigt som angivet. \nDer skelnes mellem store og sm� bogstaver.\n";
		$message .= "Ved f�rste login oprettes 1. regnskabs�r automatisk i indev�rende �r.\n";
		$message .= "Start og slut p� 1 regnskabs�r kan �ndres under Indstillinger -> Regnskabs�r. Klik p� Id nr 1.\n";
		$message .= "Du finder dit regnskab p� http://www.saldi.dk/finans \n\n";
		$message .= "P� http://forum.saldi.dk kan der findes svar p� de fleste sp�rgsm�l.\n";
		$message .= "Brugervejledningen kan findes her:  http://www.saldi.dk/dok/index.htm\n";
#		$message .= "og du kan finde en videomanual her: http://flash.saldi.dk \n";
		$message .= "Som SALDI kunde har du adgang til hotline og ubegr�nset e-mail support. Benyt mailadressen support@saldi.dk.\n";
		$message .= "Har du i�vrigt sp�rgsm�l, eller hvis der er andet vi kan hj�lpe med, s� ring blot p� telefon 4690 2208.\n\n";
		$message .= "Velkommen til og god forn�jelse\n\n";
		$message .= "Med venlig hilsen\n";
		$message .= "Saldi.dk ApS\n";

		
		ini_set("include_path", ".:../phpmailer");
		require("class.phpmailer.php");

		$mail = new PHPMailer();
		$mail->IsSMTP();                                   // send via SMTP
		$mail->Host  = "localhost"; // SMTP servers
		$mail->SMTPAuth = false;     // turn on SMTP authentication
		$afsendermail='saldi@ssl.saldi.dk';
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
		$dd=date("Y-m-d");
		if ($ip=='95.166.170.121') echo "INSERT INTO kundedata (firmanavn,  addr1, addr2, postnr, bynavn, kontakt, tlf , email , cvrnr, regnskab, brugernavn, kodeord, kontrol_id, aktiv, oprettet,slettet,referer,affiliate)values('$firma', '$adresse', '$adresse2', '$postnr', '$bynavn', '$navn', '$telefon' , '$email' , '$cvr', '$regnskab', '$brugernavn', '$kodeord', '$kontrol_id', '0','$dd','','$referer','$affiliate')";
		else db_modify("INSERT INTO kundedata (firmanavn,addr1,addr2,postnr,bynavn,kontakt,tlf,email,cvrnr,regnskab,brugernavn,kodeord,kontrol_id,aktiv,oprettet,slettet,referer,affiliate)values('$firma','$adresse','$adresse2','$postnr','$bynavn','$navn','$telefon','$email','$cvr','$regnskab','$brugernavn','$kodeord','$kontrol_id',0,'$dd','','$referer','$affiliate')",__FILE__ . " linje " . __LINE__);
#	print "<body onload=\"alert('Tak for din tilmelding. Der er sendt en e-mail til $email med instruktioner hvordan du opretter og aktiverer dit nye regnskab')\">";
		echo "<p>Tak for din tilmelding. Der er sendt en e-mail til $email med instruktioner hvordan du opretter og aktiverer dit nye regnskab</p>";	
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
			alert("Navn p� regnskab skal udfyldes.");
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
			alert("Kodeord skal v�re ens.");
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
	<form action="opret_mig.php?pakke=<?php echo $pakke ?>"  onsubmit="return Form1_Validator(this)" method="POST" name="Form1">
  	<TABLE BORDER="0"  CELLSPACING="0" align=center>
		<?php if ($returadresse != "null") {  
#			print "<tr><td><small>";
#			print "<a href=http://$returadresse>Tilbage</a>";
#			print "</small></td></tr>";
#	        	print "<tr><td colspan=2><hr></td></tr>";
		}
		$tekst="Udfyld formularen og klik p� \"Opret regnskab\" S� vil du modtage en mail med en link til dit nye SALDI regnskab,<br>";
		if ($pakke==1) $tekst=$tekst." med max. 500 �rlige posteringer og 1 bruger, til kr 45,- pr. md.";
		if ($pakke==2) $tekst=$tekst." med max. 2500 �rlige posteringer og 1 bruger, til kr 90,- pr. md.";
		if ($pakke==3) $tekst=$tekst." med max. 15000 �rlige posteringer og 2 samtidige brugere til kr 180,- pr. md.";
		if ($pakke==4) $tekst=$tekst." med ubegr�nset antal posteringer, 2 samtidige brugere og 2 regnskaber til kr 270,- pr. md.";
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
		<tr>	<td>E-mail *</td>
		     	<td><input type=text name=email class="inputbox" size="50" value="<?php echo "$email" ?>"></td>
		</tr><tr><td></td></tr>
		<tr><td>&Oslash;nsket navn p&aring; regnskab *</td>
		     	<td><input type=text name=regnskab class="inputbox" size="50" value="<?php echo "$regnskab" ?>"></td>
		</tr><tr><td></td></tr>
		<?php if ($pakke == '4') { ?>
				<tr><td>&Oslash;nsket navn p&aring; regnskab 2 *</td>
		     	<td><input type=text name=regnskab class="inputbox" size="50" value="<?php echo "$regnskab" ?>"></td>
		</tr><tr><td></td></tr>
		<?php } ?>					
		<tr><td>&Oslash;nsket&nbsp;brugernavn&nbsp;for&nbsp;administrator&nbsp;*</td>
		     	<td><input type=text name=brugernavn class="inputbox" size="50" value="<?php echo "$brugernavn" ?>"></td>
		</tr><tr><td></td></tr>
		<tr><td>&Oslash;nsket&nbsp;kodeord&nbsp;for&nbsp;administrator&nbsp;*</td>
		     	<td><input type=password name=kodeord class="inputbox" size="50" value="<?php echo "$kodeord" ?>"></td>
		</tr><tr><td></td></tr>
		<tr><td>Gentag&nbsp;&oslash;nsket&nbsp;kodeord&nbsp;for&nbsp;administrator&nbsp;*</td>
		     	<td><input type=password name=kodeord2 class="inputbox" size="50" value="<?php echo "$kodeord2" ?>"></td>
 <?php if ($pakke > 2) { ?>
			</tr><tr><td></td></tr>
			 <tr><td><span title="V&aelig;lg ekstra samtidige brugere udover 2. Prisen for ekstra brugere udg&oslash;r kr 90,- pr. bruger/md.">Ekstra brugere</span></td>
			     	<td><input type=text name=brugerantal class="inputbox inputbox-small" size="2"  value="<?php echo "$brugerantal" ?>"></td>
		    	</tr><tr><td></td></tr>
		    	</tr><tr><td></td></tr>
<?php 
 } 
 if ($pakke >= 1) {
 ?>        		
					<tr><td><span title="V&aelig;lg &oslash;nsket faktureringsinterval - administrationsgebyr = kr. 5,- pr faktura.">Faktureringsperiode</span></td>
			<?php
 	 if (!isset($betaling)) $betaling=3;
					elseif (!$betaling) $betaling=1;
					if ((isset($betaling))&&($betaling==1)) $betaling_1="checked=\"checked\""; 
		
					?>
			<td><span title="Fakturering hver m&aring;ned">m&aring;nedlig<input type=RADIO name="betaling" value="1" <?php echo "$betaling_1" ?>></span>
			<?php if ((isset($betaling))&&($betaling==2)) $betaling_2="checked=\"checked\""; ?><span title="Fakturering hver 6. m&aring;ned">
			&nbsp;&nbsp;&nbsp;&frac14; &aring;rlig<input type=RADIO  name="betaling" value="2" <?php echo "$betaling_2" ?>></span>
			<?php if ((isset($betaling))&&($betaling==3)) $betaling_3="checked=\"checked\""; ?><span title="Fakturering hver 6. m&aring;ned">
			&nbsp;&nbsp;&nbsp;&frac12; &aring;rlig<input type=RADIO  name="betaling" value="3" <?php echo "$betaling_3" ?>></span>
			<?php if ((isset($betaling))&&($betaling==4)) $betaling_4="checked=\"checked\""; ?><span title="Fakturering hver 12. m&aring;ned">
			&nbsp;&nbsp;&nbsp;hel&aring;rlig<input type=RADIO  name="betaling" value="4" <?php echo "$betaling_4" ?>></span>
			<?php
 }
					?>
			
					</td>
			</tr><tr><td></td></tr>
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
<?php	
print "<input type=hidden name=s_id value=\"$s_id\"><br>\n";
print "<input type=hidden name=referer value=\"$referer\"><br>\n";
print "<input type=hidden name=affiliate value=\"$affiliate\"><br>\n";
echo "</form>";
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
	}
	else {
		print "<BODY onLoad=\"javascript:alert('Aktiveringskode: $kontrol_id findes ikke')\">";
	}	
	if ((isset($regnskab))&&(isset($brugernavn))&&(isset($kodeord))) {
		$query=db_select("select id from regnskab where regnskab='".addslashes($regnskab)."'",__FILE__ . " linje " . __LINE__);
		if (!db_fetch_array($query)) {
			db_modify("update kundedata set aktiv=1 where id = $id",__FILE__ . " linje " . __LINE__);
#			print "<table  border=1><tbody>";
			print "<form action=\"../admin/opret.php\"  method=\"POST\" name=\"opret\">\n";
			print "<tr><td colspan=2 align=center>Aktivering af SALDI regnskab</td></tr>\n";
			print "<tr><td colspan=2 align=center><hr></td></tr>\n";
			print "<tr><td>Firma</td><td>$firmanavn</td></tr>\n";
			print "<tr><td>Navn</td><td>$kontakt</td></tr>\n";
			print "<tr><td>Regnskab</td><td>$regnskab</td></tr>\n";
			print "<tr><td>Brugernavn</td><td>$brugernavn</td></tr>\n";
			
			print "<tr><td colspan=2><hr></td></tr>";
			print "<tr><td colspan=2>N�r der klikkes [OK] initialiseres alle tabeller</td></tr>\n";
			print "<tr><td colspan=2>og kontoplan, formularer mm. indl�ses.</tr>\n";
			print "<tr><td colspan=2>Dette kan vare flere minutter, s� v�r t�lmodig.</tr>\n";
			print "<tr><td colspan=2>Herefter kan du logge ind i dit regnskab med det</tr>\n";
			print "<tr><td colspan=2>kodeord du angav ved oprettelsen.</tr>\n";
			print "<tr><td colspan=2>Velkommen til og god forn�jelse.</tr>\n";
			print "<tr><td colspan=2 align=center><hr></td></tr>";
			
			print "<input type=hidden name=regnskab value=\"$regnskab\"><br>\n";
			print "<input type=hidden name=brugernavn value=\"$brugernavn\"><br>\n";
			print "<input type=hidden name=passwd value=\"$kodeord\"><br>\n";
			print "<input type=hidden name=passwd2 value=\"$kodeord\"><br>\n";
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


		     
