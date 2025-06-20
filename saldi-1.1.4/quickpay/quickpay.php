<?php
ob_start(); //Starter output buffering
if (isset($_GET['returadresse'])) {
	$returadresse=$_GET['returadresse'];
}
elseif (isset($_GET['kontrol_id'])) {
	$kontrol_id=$_GET['kontrol_id'];
	opret($kontrol_id);	
	exit;
}

echo "XXXXX $_POST[navn]<br>";
if ((isset($_POST['navn']))&&(isset($_POST['email']))&&(isset($returadresse))) {
#include "top.php";
echo "XXXXX<br>";
	
	$navn = ($_POST['navn']);
	$firma = ($_POST['firma']);
	$cvr = ($_POST['cvr']);
	$adresse = ($_POST['adresse']);
	$adresse2 = ($_POST['adresse2']);
	$postnr = ($_POST['postnr']);
	$bynavn = ($_POST['bynavn']);
	$telefon = ($_POST['telefon']);
	$email = ($_POST['email']);
	$pakke_1 = ($_POST['pakke_1']);
	$pakke_2 = ($_POST['pakke_2']);
	$pakke_3 = ($_POST['pakke_3']);
	$pakke_4 = ($_POST['pakke_4']);
	$pakke_5 = ($_POST['pakke_5']);
	$regnskab = ($_POST['regnskab']);
	$brugerantal = ($_POST['brugerantal']);
	$eventuelt = ($_POST['eventuelt']);
	$betingelser = ($_POST['betingelser']);
	$brugernavn = ($_POST['brugernavn']);
	$kodeord = ($_POST['kodeord']);
	$kodeord2 = ($_POST['kodeord2']);

	$to = $email;

	$amount=0;

	if ($pakke_1=='on') {$pakkevalg=1; $amount=30000;}
	if ($pakke_2=='on') {$pakkevalg=2; $amount=60000;}
	if ($pakke_3=='on') {$pakkevalg=3; $amount=150000;}
	if ($pakke_4=='on') {$pakkevalg=4; $amount=300000;}
	if ($pakke_5=='on') {$pakkevalg=5; $amount=500000;}
	if (!$amount) {$pakkevalg=0; $amount=150000;} 

#	if ($brugerantal > 2 && ($pakke_1=='on'||$pakke_2=='on')) $amount = $amount + (18000*($brugerantal-2))
	
	$subject = "Oprettelse som SALDI kunde";
	if (! $pakke_1) $pakke_1 = "Nej tak";
	if (! $pakke_2) $pakke_2 = "Nej tak";
	if (! $pakke_3) $pakke_3 = "Nej tak";
	if (! $pakke_4) $pakke_4 = "Nej tak";
	if (! $pakke_5) $pakke_5 = "Nej tak";

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
		print "<BODY onLoad=\"javascript:alert('Der eksisterer et inaktivt regnskab med navnet $regnskab')\">";
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
			$message .= "Regnskab: $regnskab\nEventuelt: $eventuelt\n\n";
		}
		else {
			if ($pakke_1=='on') $message .= "Bestilt: Prøveperiode 6 mdr. uden import af data til kr. 300,- excl. moms.\n";
			if ($pakke_2=='on') $message .= "Bestilt: Prøveperiode 6 mdr. med 1 times kom i gang assistance / import af data til kr. 600.,- excl. moms.\n";
			if ($pakke_3=='on') $message .= "Bestilt: 12 mdr. periode med 2 timers kom i gang assistance / import af data til kr. 1.500,- excl. moms.\n";
			if ($pakke_4=='on') $message .= "Bestilt: 24 mdr. periode med 5 timers kom i gang assistance / import af data til kr. 3.000,- excl. moms.\n";
			if ($pakke_5=='on') $message .= "Bestilt: 36 mdr. periode med 10 timers kom i gang assistance / import af data til kr. 5.000,- excl. moms.\n";
			$message .= "Regnskab: $regnskab \nBrugerantal: $brugerantal \nEventuelt: $eventuelt\n\n";
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

setcookie("saldi_opret",$navn,$firma,$cvr,$adresse,$adresse2,$postnr,$bynavn,$telefon,$email,$pakkevalg,$regnskab,$brugerantal,$eventuelt,$betingelser,$brugernavn,$kodeord,$message,time()+60*60*24*30);
ob_end_flush();	//Sender det "bufferede" output afsted... 
		
/*
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
		print "<body onload=\"alert('Tak for din tilmelding. Der er sendt en e-mail til $email med instruktioner hvordan du opretter og aktiverer dit nye regnskab')\">";
		print "<meta http-equiv=refresh content=0;url=http://".$returadresse.">";
		exit;
*/

	}
}





	$language = "da	";
$autocapture = "0";
$ordernum = "12345";
$merchant = "20756438";
# $amount = "100";
$currency = "DKK";
$okpage = "http://saldi.dk:88/quickpay/ok.php";
$errorpage = "http://saldi.dk:88/quickpay/error.php";
$resultpage = "http://saldi.dk:88/quickpay/result.php";
// Valgfri elementer
// $ccipage = "http://saldi.dk:88/quickpay/cci.php";
// $cardtype3ds = "";
// $cardtypelock = "";
// $customerEmail = "";
$md5secret = "A6263ZML1qa9s5f6m57huvI5k1QDz4w3b2nt95pW3KPlH364ci817r71UjJEX842";
$md5check = md5($language.$autocapture.$ordernum.$amount.$currency.$merchant.$okpage.$errorpage.$resultpage.$ccipage.$md5secret);
echo "Sum i alt: $amount<br>";
?>
<form action="https://secure.quickpay.dk/quickpay.php" method="post" target="_blank">
<input type="hidden" name="language" value="<?php echo $language ?>" />
<input type="hidden" name="autocapture" value="<?php echo $autocapture ?>" />
<input type="hidden" name="ordernum" value="<?php echo $ordernum ?>" />
<input type="hidden" name="merchant" value="<?php echo $merchant ?>" />
<input type="hidden" name="amount" value="<?php echo $amount ?>" />
<input type="hidden" name="currency" value="<?php echo $currency ?>" />
<input type="hidden" name="okpage" value="<?php echo $okpage ?>" />
<input type="hidden" name="errorpage" value="<?php echo $errorpage ?>" />
<input type="hidden" name="resultpage" value="<?php echo $resultpage ?>" />
<!-- valgfrie elementer
<input type="hidden" name="ccipage" value="<?php echo $ccipage ?>" />
<input type="hidden" name="cardtype3ds" value="<?php echo $cardtype3ds ?>" />
<input type="hidden" name="cardtypelock" value="<?php echo $cardtypelock ?>" />
<input type="hidden" name="CUSTOM_Email" value="<?php echo $customerEmail ?>" />
-->
<input type="hidden" name="md5checkV2" value="<?php echo $md5check ?>" />
<input type="submit" value="Gå til betaling" />
</form> 

