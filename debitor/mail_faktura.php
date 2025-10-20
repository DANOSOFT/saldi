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
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding

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
	
	$q=db_select("select * from formularer where formular='$form_nr' and art='5' and lower(sprog)='".strtolower($mailsprog)."'");
	while ($r = db_fetch_array($q)) {
		if ($r['xa']=='1') $subjekt=$r['beskrivelse'];	
		elseif ($r['xa']=='2') $mailtext=$r['beskrivelse'];
	}
	
	# Load language-specific sender email and name from settings table
	# Determine language ID: 0 for Danish/default, actual ID for other languages
	$lang_id = 0; // Default to 0 for Danish
	
	if ($mailsprog && strtolower($mailsprog) != 'dansk') {
		$qtxt = "select kodenr from grupper where art = 'VSPR' and lower(box1) = lower('$mailsprog')";
		$r = db_fetch_array(db_select($qtxt));
		if ($r) {
			$lang_id = $r['kodenr'];
		}
	}
	
	error_log("DEBUG: mailsprog='$mailsprog', lang_id='$lang_id'");
	
	# Load sender email for this language
	$lang_sender_email = NULL;
	$qtxt = "select var_value from settings where var_name = 'sender_email' and var_grp = 'email_settings' and group_id = '$lang_id'";
	$r = db_fetch_array(db_select($qtxt));
	$lang_sender_email = $r['var_value'];
	error_log("DEBUG: Found lang_sender_email='$lang_sender_email' for lang_id='$lang_id'");
	
	# Load sender name for this language
	$lang_sender_name = NULL;
	$qtxt = "select var_value from settings where var_name = 'sender_name' and var_grp = 'email_settings' and group_id = '$lang_id'";
	$r = db_fetch_array(db_select($qtxt));
	$lang_sender_name = $r['var_value'];
	error_log("DEBUG: Found lang_sender_name='$lang_sender_name' for lang_id='$lang_id'");
		
	$row = db_fetch_array(db_select("select * from adresser where art='S'"));
	$afsendermail=$row['email'];
	$afsendernavn=$row['firmanavn'];
	
	# Use language-specific sender email if available, otherwise use default
	if ($lang_sender_email && trim($lang_sender_email) != '') {
		$afsendermail = $lang_sender_email;
	}
	
	# Use language-specific sender name if available, otherwise use default
	if ($lang_sender_name && trim($lang_sender_name) != '') {
		$afsendernavn = $lang_sender_name;
	}
	if (!$afsendermail || !$afsendernavn) {
		print "<BODY onLoad=\"javascript:alert('Firmanavn eller e-mail for afsender ikke udfyldt.\\nSe (Indstillinger -> stamdata).\\nMail ikke afsendt!')\">";
		return;
	}
	
	if ($charset=="UTF-8") {
		$subjekt=mb_convert_encoding($subjekt, 'ISO-8859-1', 'UTF-8');
		$mailtext=mb_convert_encoding($mailtext, 'ISO-8859-1', 'UTF-8');
		$afsendernavn=mb_convert_encoding($afsendernavn, 'ISO-8859-1', 'UTF-8');
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

