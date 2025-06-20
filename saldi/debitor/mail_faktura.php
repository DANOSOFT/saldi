<?php
#@session_start();
#$s_id=session_id();
// ------debitor/mail_faktura.php-------lap 2.1.0------2009.10.14--------
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

# $mailantal=1;
# $pfliste[1]="../temp/$db/ordrebek576";
# $email[1]="phr@saldi.dk";		
	
ini_set("include_path", ".:../phpmailer");
require("class.phpmailer.php");
		
for($x=1;$x<=$mailantal;$x++) {
	system ("/usr/bin/ps2pdf ../temp/$db/$pfliste[$x] ../temp/$db/$pfliste[$x].pdf");
	send_mails("../temp/$db/$pfliste[$x].pdf",$email[$x],$mailsprog[$x],$form_nr[$x]);	
#	unlink("../temp/$db/$pfliste[$x]");
#	unlink("../temp/$db/$pfliste[$x].pdf");
}

function send_mails($filnavn,$email,$mailsprog,$form_nr) {
	global $db;
	global $mailantal;
	global $charset;
	
	$q=db_select("select * from formularer where formular='$form_nr' and art='5'");
	while ($r = db_fetch_array($q)) {
		if ($r['xa']=='1') $subjekt=$r['beskrivelse'];	
		elseif ($r['xa']=='2') $mailtext=$r['beskrivelse'];
	}
		
	$row = db_fetch_array(db_select("select * from adresser where art='S'"));
	$afsendermail=$row['email'];
	$afsendernavn=$row['firmanavn'];
	if (!$afsendermail || !$afsendernavn) {
		print "<BODY onLoad=\"javascript:alert('Firmanavn eller e-mail for afsender ikke udfyldt.\\nSe (Indstillinger -> stamdata).\\nMail ikke afsendt!')\">";
		return;
	}
	
	if ($charset=="UTF-8") {
		$subjekt=utf8_decode($subjekt);
		$mailtext=utf8_decode($mailtext);
		$afsendernavn=utf8_decode($afsendernavn);
	}
	
/*
echo "<br>Fra $afsendernavn | $afsendermail <br>";
echo "Til $email<br>";
echo "Emne: $subjekt<br>";
echo "tekst	$mailtext<br>";
*/	
	
	$mail = new PHPMailer();

	$mail->IsSMTP();                                   // send via SMTP
	$mail->Host  = "localhost"; // SMTP servers
	$mail->SMTPAuth = false;     // turn on SMTP authentication
			#	$mail->Username = "jswan";  // SMTP username
			#	$mail->Password = "secret"; // SMTP password
			
	$mail->From     = $afsendermail;
	$mail->FromName = $afsendernavn;
	$mail->AddAddress($email); 
	$mail->AddBCC($afsendermail); 
	#	$mail->AddAddress("ellen@site.com");               // optional name
	#	$mail->AddReplyTo("info@site.com","Information");

	$mail->WordWrap = 50;  // set word wrap
#	$mail->AddAttachment("../temp/$db/mailtext.html");
	$mail->AddAttachment("$filnavn");      // attachment
#	$mail->AddAttachment("/tmp/image.jpg", "new.jpg"); 
	$mail->IsHTML(true);                               // send as HTML
	
	$ren_text=html_entity_decode($mailtext,ENT_COMPAT,$charset);

	$mail->Subject  =  "$subjekt";
	$mail->Body     =  "$mailtext";
	$mail->AltBody  =  "$ren_text";

	if(!$mail->Send()){
			 echo "Fejl i afsendelse til $email<p>";
 				echo "Mailer Error: " . $mail->ErrorInfo;
		 		exit;
	}
	if ($mailantal==1) print "<BODY onLoad=\"javascript:alert('Mail sendt til $email')\">";
	else echo "Mail sendt til $email<br>";
}	
?>

