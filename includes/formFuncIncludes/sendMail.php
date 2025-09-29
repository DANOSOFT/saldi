<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/formFuncIncludes/sendMail.php --- patch 4.1.1 --- 2025-0607-31 ---
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20211028 PHR moved this function rom ../formfunc,php  
// 20221124 PHR Added $mail->ReturnPath = $afsendermail;
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20250603 PHR enhanced use of variables in mailtext. 

function send_mails($ordre_id,$filnavn,$email,$mailsprog,$form_nr,$subjekt,$mailtext,$mailbilag,$mailnr) {
print "<!--function send_mails start-->";
	global $charset;
	global $db,$db_id,$deb_valuta,$deb_valutakurs;
	global $mailantal;
	global $formular,$formularsprog;
	global $webservice;
	global $ansat_id;
	global $bruger_id;
	global $exec_path;
#	global $id; // hent 'mail_bilag' fra ordrer + leveringsaddr.
	global $returside;

	$email=str_replace(' ','',$email);
	if (strpos($email,';')) $emails=explode(';',$email);
	elseif (strpos($email,',')) $emails=explode(',',$email);
	else $emails[0]=$email;
	for ($x=0;$x<count($emails);$x++) {
		if (!filter_var($emails[$x], FILTER_VALIDATE_EMAIL)) { #20200122
			alert("Invalid email format in $emails[$x]");
			return ("Invalid email format in $emails[$x]");
		}
	}
	$bilag=$brugermail=$mail_bilag=NULL;
	
	$ordre_id*=1; #21040423
 	$qtxt="select mail_bilag,lev_addr1,lev_postnr,lev_bynavn,sag_id from ordrer where id='$ordre_id'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$mail_bilag=$r['mail_bilag'];
	$lev_addr1=$r['lev_addr1']; # 2013.11.27 Henter leveringsaddr.
	$lev_postnr=$r['lev_postnr'];
	$lev_bynavn=$r['lev_bynavn'];
	$sag_id=$r['sag_id']; # 2013.11.27 Henter sags_id 
	//$mail_bilag='on'; // skal hentes fra ordrer
	
	#2013.11.19 Her finder vi hvilket bilag der skal hentes
	if($formular<=1) $bilag="tilbud_bilag"; 
	if($formular==2) $bilag="ordrer_bilag";
	if($formular==4) $bilag="faktura_bilag";
	if ($bilag) $mail_bilag='on'; #20191216

	if(!$bilag || !file_exists("../logolib/$db_id/$bilag.pdf")) { #2013.11.21 Hvis fil(bilag) IKKE eksistere sættes $mail_bilag til NULL, selvom $mail_bilag er sat til 'on'
		$mail_bilag=NULL;
	}
	
	$emails=array();
	$email=str_replace(",",";",$email);
	if (strpos($email,";")) {
		$emails=explode(";",$email);
	} else $emails[0]=$email;

	$qtxt="select * from formularer where formular='$form_nr' and art='5' and lower(sprog)='".strtolower($formularsprog)."'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!$subjekt && $r['xa']=='1') $subjekt=$r['beskrivelse'];
		elseif (!$mailtext && $r['xa']=='2') $mailtext=$r['beskrivelse'];
		elseif ($r['xa']=='3') $bilagnavn=$r['beskrivelse']; #2013.11.21 Finder bilag-navn
	}
	
	# Load language-specific sender email from settings table
	# Determine language ID: 0 for Danish/default, actual ID for other languages
	$lang_id = 0; // Default to 0 for Danish
	
	if ($formularsprog && strtolower($formularsprog) != 'dansk') {
		$qtxt = "select kodenr from grupper where art = 'VSPR' and lower(box1) = lower('$formularsprog')";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r) {
			$lang_id = $r['kodenr'];
		}
	}
	
	error_log("DEBUG: formularsprog='$formularsprog', lang_id='$lang_id'");
	
	# Load sender email for this language
	$lang_sender_email = NULL;
	$qtxt = "select var_value from settings where var_name = 'sender_email' and var_grp = 'email_settings' and group_id = '$lang_id'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$lang_sender_email = $r['var_value'];
	error_log("DEBUG: Found lang_sender_email='$lang_sender_email' for lang_id='$lang_id'");
	if (strpos($mailtext,'$firmanavn')) {
		$qtxt = "select firmanavn from ordrer where id = '$ordre_id'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$mailtext = str_replace('$firmanavn',$r['firmanavn'],$mailtext);
		}
	}
	$mailtext = str_replace("\n\r","\n\r<br>",$mailtext);

	(isset($bilagnavn) && $bilagnavn)?$bilagnavn=$bilagnavn:$bilagnavn="Bilag"; #2013.11.21 Hvis bilag-navn er tom, insættes 'Bilag' som navn
	if ($sag_id) $subjekt=$subjekt." vedr.: $lev_addr1, $lev_postnr $lev_bynavn"; #2013.11.27 Her tilføjes leveringsaddr. til subjekt hvis der er sag_id
	
	$qtxt="select * from adresser where art='S'";
	$row = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$afsendermail=$row['email'];
	$afsendernavn=$row['firmanavn'];
	
	# Use language-specific sender email if available, otherwise use default
	if ($lang_sender_email && trim($lang_sender_email) != '') {
		$afsendermail = $lang_sender_email;
		error_log("DEBUG: Using language-specific email: '$afsendermail'");
	} else {
		error_log("DEBUG: Using default email: '$afsendermail' (lang_sender_email was empty or null)");
	}
	
	$afsendermail=str_replace(",",";",$afsendermail);
	$afsendermails=explode(";",$afsendermail);
	$from=$afsendermails[0];
	($row['felt_1'])?$smtp=$row['felt_1']:$smtp='localhost';
	($row['felt_2'])?$smtp_user=$row['felt_2']:$smtp_user=NULL;
	($row['felt_3'])?$smtp_pwd=$row['felt_3']:$smtp_pwd=NULL;
	($row['felt_4'])?$smtp_enc=$row['felt_4']:$smtp_enc=NULL;

	if ($row['mailfakt'] && $ansat_id) {
		$r = db_fetch_array(db_select("select * from ansatte where id='$ansat_id'",__FILE__ . " linje " . __LINE__));
		$brugermail=$r['email'];
	}
	if (!$afsendermails[0] || !$afsendernavn) {
		if (!$webservice) {
			print "<BODY onLoad=\"javascript:alert('Firmanavn eller e-mail for afsender ikke udfyldt.\\nSe (Indstillinger -> stamdata).\\nMail ikke afsendt!')\">";
		}
		return("Missing sender mail");
	}
	$fakturanavn=basename($filnavn);
	
	if ($mailbilag && $ordre_id) {
		$ftpfilnavn="bilag_".$ordre_id;
		$r=db_fetch_array(db_select("select * from grupper where art='bilag'",__FILE__ . " linje " . __LINE__));
			if($box6=$r['box6']) {
			$mappe='bilag';
			$undermappe="ordrer";
			$bilagfilnavn="bilag_".$bilag_id;
			$google_docs=$r['box7'];
			$fra="../bilag/".$db."/".$mappe."/".$undermappe."/".$ftpfilnavn;
			$til="../temp/".$db."/".$mailbilag;
			system ("cp '$fra' '$til'\n");
		} else {
			$r=db_fetch_array(db_select("select * from grupper where art='FTP'",__FILE__ . " linje " . __LINE__));
			$box1=$r['box1'];
			$box2=$r['box2'];
			$box3=$r['box3'];
			$mappe=$r['box4'];
			$undermappe="ordrer";
			$ftpfilnavn="bilag_".$ordre_id;
			$fp=fopen("../temp/$db/ftpscript.$bruger_id","w");
			if ($fp) {
			fwrite ($fp, "cd $mappe\ncd $undermappe\nget $ftpfilnavn\nbye\n");
			}
			fclose($fp);
			$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$box1." < ftpscript.$bruger_id > ftplog\nmv \"$ftpfilnavn\" \"$mailbilag\"\n";
			system ($kommando);
		}
	}
	
	if (strpos($subjekt,'$')!== false) {
		$ordliste=explode(" ",$subjekt);
		$subjekt='';
		for ($a=0;$a<count($ordliste);$a++) {
			if (substr($ordliste[$a],0,1)=='$') {
				$var=substr($ordliste[$a],1);
				if (strpos($var,';')) {
					list($var,$tmp) = explode(';',$var,2);
				}
				if (strpos($var,',')) {
					list($var,$tmp) = explode(',',$var,2);
				}
				$var=trim($var);
				$r=db_fetch_array(db_select("select $var from ordrer where id='$ordre_id'",__FILE__ . " linje " . __LINE__));
				$ordliste[$a]=$r[$var];
			} 
			$subjekt.=$ordliste[$a]." ";
		}
	}
	if (strpos($mailtext,'$')!== false) {
		$mailtext=str_replace('<br>$','<br> $',$mailtext);
		$ordliste=explode(" ",$mailtext);
		$mailtext='';
		for ($a=0;$a<count($ordliste);$a++) {
			if (substr($ordliste[$a],0,1)=='$') {
				$var=substr($ordliste[$a],1);
				$br='';
				if (strpos($var,'<br>')) {
					list($var,$br)=explode("<br>",$var,2);
					if (!$br) $br=" ".$br; #Eller æder den linjeskiftet hvis der ikke er noget efter <br>  
				}
				if (strpos($var,';')) {
					list($var,$tmp) = explode(';',$var,2);
				}
				if (strpos($var,',')) {
					list($var,$tmp) = explode(',',$var,2);
				}
				$var=trim($var);
				$qtxt="select $var from ordrer where id='$ordre_id'";
				#cho "$qtxt<br>";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$ordliste[$a]=$r[$var];
				if ($br) {
					$ordliste[$a].="<br>".$br;
				}
			} 
			$mailtext.=$ordliste[$a]." ";
		}
	}
	$tidspkt=date('U');
	if (file_exists("../temp/$db/mailchk.php")) {
		include ("../temp/$db/mailchk.php");
		if ($ordre_id == $chkOid && $email == $chkMail && $subjekt == $chkSubj && $filnavn == $chkFil && $tidspkt < $chkTid+30) {
			alert("Vent 30 sekunder inden du sender samme mail igen");
			return;
			exit;
		}
	}
	$chkfil=fopen("../temp/$db/mailchk.php",'w');
	fwrite ($chkfil, "<?php\n");
	fwrite ($chkfil, "$"."chkOid='$ordre_id';\n");
	fwrite ($chkfil, "$"."chkMail='$email';\n");
	fwrite ($chkfil, "$"."chkSubj='$subjekt';\n");
	fwrite ($chkfil, "$"."chkTid='$tidspkt';\n");
	fwrite ($chkfil, "$"."chkFil='$filnavn';\n");
	fwrite ($chkfil, "?>\n");
	fclose($chkfil);	
	
	if ($charset=="UTF-8" || $webservice) {
#		$subjekt=mb_convert_encoding($subjekt, 'ISO-8859-1', 'UTF-8');
#		$mailtext=mb_convert_encoding($mailtext, 'ISO-8859-1', 'UTF-8');
#		$bilagnavn=mb_convert_encoding($bilagnavn, 'ISO-8859-1', 'UTF-8');
#		$afsendernavn=mb_convert_encoding($afsendernavn, 'ISO-8859-1', 'UTF-8');
	}
	if (file_exists ("../temp/$db/mailCheck.txt")) {
		$fp=fopen("../temp/$db/mailCheck.txt","r");
		while($line=fgets($fp)) {
			$chk[$x]=$line;
		}
	}
	require_once "../../vendor/autoload.php"; //PHPMailer Object
	$mail = new  PHPMailer\PHPMailer\PHPMailer();
	$mail->SMTPOptions = array( 
	'ssl' => array( 
		'verify_peer' => false, 
		'verify_peer_name' => false, 
		'allow_self_signed' => true 
	) 
	);
	$mail->CharSet = 'UTF-8';
	$mail->IsSMTP();                                   // send via SMTP
	$mail->SMTPDebug  = 2;
	$mail->Host  = $smtp; // SMTP servers 
	if ($smtp!='localhost') {
		if ($smtp_user) {
			$mail->SMTPAuth = true;     // turn on SMTP authentication
			$mail->Username = $smtp_user;  // SMTP username
			$mail->Password = $smtp_pwd; // SMTP password
			if ($smtp_enc) $mail->SMTPSecure = $smtp_enc; // SMTP kryptering
		}
	} else {
		$mail->SMTPAuth = false;
#	if (strpos($_SERVER['SERVER_NAME'],'saldi.dk')) $mail->Sender = 'mailer@saldi.dk';
		if (strpos($_SERVER['SERVER_NAME'],'saldi.dk')) { #20121016
			$from = $db.'@'.$_SERVER['SERVER_NAME'];
		}
	}
#xit;
	$mail->From = $from;
	$mail->FromName = $afsendernavn;
	$mail->ReturnPath = $afsendermail;
	$mail->AddReplyTo($afsendermails[0],$afsendernavn);
	$mail->AddAddress($emails[0]);
	for ($i=1;$i<count($emails);$i++) $mail->AddCC($emails[$i]); 
	for ($i=0;$i<count($afsendermails);$i++) $mail->AddBCC($afsendermails[$i]); 
#	$mail->AddBCC($afsendermail);
	if ($brugermail) $mail->AddBCC($brugermail);
	#	$mail->AddAddress("ellen@site.com");               // optional name
	#	$mail->AddReplyTo("info@site.com","Information");

	$mail->WordWrap = 50;  // set word wrap
#	$mail->AddAttachment("$mappe/mailtext.html");
	$mail->AddAttachment("$filnavn","$fakturanavn","base64","application/pdf");      // attachment
	if ($mailbilag) $mail->AddAttachment("../temp/$db/$mailbilag","$mailbilag","base64","application/pdf");      // attachment
	if ($mail_bilag) $mail->AddAttachment("../logolib/$db_id/$bilag.pdf","$bilagnavn.pdf"); // kun hvis checkbox er 'on'.
	#	$mail->AddAttachment("/tmp/image.jpg", "new.jpg");
	$mail->IsHTML(true);                               // send as HTML

	$ren_text=html_entity_decode($mailtext,ENT_COMPAT,$charset);
	$ren_text=str_replace("<br>","\n",$ren_text);
	$ren_text=str_replace("<b>","*",$ren_text);
	$ren_text=str_replace("</b>","*",$ren_text);
	$ren_text=str_replace("<hr>","------------------------------",$ren_text);
	$mail->Subject  =  "$subjekt";
	$mail->Body     =  "$mailtext";
	$mail->AltBody  =  "$ren_text";
	$svar=NULL;
	print "<!--";
	if(!$mail->Send()){
 		$svar = "Mailer Error: " . $mail->ErrorInfo;
	}
	print "-->";
	if ($svar) {
		echo $svar."<br>";
		exit;
	}
	if (!$webservice) {
/*
	if ($mailantal>=100) {
			if ($brugermail) $tekst="Mail sendt til $email\\nBCC til $afsendermail\\nBCC til $brugermail.";
			else $tekst="Mail sendt til $email\\nBCC til $afsendermail.";
			alert($tekst);
		}	else 
	*/	
		if ($mailantal>1 && $mailnr==$mailantal) {
			if ($brugermail) $tekst="$mailantal mails sendt\\nBCC til $afsendermail\\nBCC til $brugermail.";
			else $tekst="$mailantal mails sendt\\nBCC til $afsendermail.";
			alert($tekst);
		}
	}
	echo "Mail sent to $email<br>";
	return("Mail sent to $email");
	print "<!--function send_mails slut-->";
}

?>
