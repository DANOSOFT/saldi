<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/formFuncIncludes/sendMail.php --- ver 5.0.0 --- 2026-10-03 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//20260603 CL/PHR Bilagsvedhæftning opdateret: tjekker nu documents-tabellen
//                  (source=debitorOrdrer) før fallback til gammelt bilag-system
//
// 20211028 PHR moved this function rom ../formfunc,php  
// 20221124 PHR Added $mail->ReturnPath = $afsendermail;
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20250603 PHR enhanced use of variables in mailtext. 
// 20260818 CL/LH Preserved plain-text mail bodies when anchor flattening fails.
// 20260819 Sawaneh 'Mail sent to' confirmation translated via findtekst and
//                  recipient escaped before output.
// 20260909 Sawaneh SST-759: Rejected recipient feedback names the offending character
//                  and offers the ASCII suggestion via emailLookalike.php.
// 20261001 CDX/LH Split SMTP into a result-returning core with unchanged web feedback.
// 20261001 CDX/LH Freeze MIME before transmission, track SMTP acceptance and serialize the browser duplicate guard.
// 20261001 CDX/LH Resolve the existing invoice payment-link mail token without minting payments.

/** Preserve browser feedback; REST callers use the non-terminating core below. */
function send_mails($ordre_id,$filnavn,$email,$mailsprog,$form_nr,$subjekt,$mailtext,$mailbilag,$mailnr) {
    global $webservice, $sprog_id, $mailantal;
    print "<!--function send_mails start-->";
    $result = send_mails_result($ordre_id,$filnavn,$email,$mailsprog,$form_nr,$subjekt,$mailtext,$mailbilag,$mailnr);
    if (!$result['ok']) {
        if ($result['error'] === 'invalid_email') {
            include_once __DIR__ . '/emailLookalike.php';
            return emailRecipientRejected($result['recipient'], $ordre_id, $sprog_id, $mailantal);
        }
        if ($result['error'] === 'Missing sender mail') {
            if (!$webservice) {
                print "<BODY onLoad=\"javascript:alert('Firmanavn eller e-mail for afsender ikke udfyldt.\\nSe (Indstillinger -> stamdata).\\nMail ikke afsendt!')\">";
            }
            return 'Missing sender mail';
        }
        if ($result['error'] === 'duplicate_send') {
            alert('Vent 30 sekunder inden du sender samme mail igen');
            return;
        }
        echo $result['error'] . '<br>';
        exit;
    }
    if (!$webservice && $mailantal > 1 && $mailnr == $mailantal) {
        $tekst = "$mailantal mails sendt\\nBCC til " . $result['sender'];
        if ($result['user_mail']) {
            $tekst .= '\\nBCC til ' . $result['user_mail'] . '.';
        } else {
            $tekst .= '.';
        }
        alert($tekst);
    }
    $email = $result['recipient'];
    echo findtekst('3370|Mail sendt til', $sprog_id) . ' ' . htmlspecialchars($email, ENT_QUOTES) . '<br>';
    return "Mail sent to $email";
}

/** @return array{ok:bool,error:string|null,recipient:string,outcome:string,stage:string,recipients:array<string,bool>} */
function send_mails_result($ordre_id,$filnavn,$email,$mailsprog,$form_nr,$subjekt,$mailtext,$mailbilag,$mailnr) {
	$prepared = send_mails_prepare_result($ordre_id,$filnavn,$email,$mailsprog,$form_nr,$subjekt,$mailtext,$mailbilag,$mailnr);
	return send_mails_transmit_result($prepared);
}

/**
 * Resolve templates and attachments and freeze MIME without opening a mail transport.
 * Database-idempotent callers bypass the browser's 30-second guard.
 * @return array{ok:bool,error:string|null,recipient:string,mail?:PHPMailer\PHPMailer\PHPMailer,sender?:string,user_mail?:string|null}
 */
function send_mails_prepare_result($ordre_id,$filnavn,$email,$mailsprog,$form_nr,$subjekt,$mailtext,$mailbilag,$mailnr, bool $browserGuard = true) {

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
	global $sprog_id;

	$email=str_replace(' ','',$email);
	if (strpos($email,';')) $emails=explode(';',$email);
	elseif (strpos($email,',')) $emails=explode(',',$email);
	else $emails[0]=$email;
	for ($x=0;$x<count($emails);$x++) {
		if (!filter_var($emails[$x], FILTER_VALIDATE_EMAIL)) { #20200122
			return ['ok' => false, 'error' => 'invalid_email', 'recipient' => $emails[$x]];
		}
	}
	$bilag=$brugermail=$mail_bilag=NULL;
	
	$ordre_id = (int)$ordre_id;
	$form_nr = db_escape_string((string)$form_nr);
	$ansat_id = (int)$ansat_id;
	$formularsprogSql = db_escape_string((string)$formularsprog);
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

	$qtxt="select * from formularer where formular='$form_nr' and art='5' and lower(sprog)='".strtolower($formularsprogSql)."'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!$subjekt && $r['xa']=='1') $subjekt=$r['beskrivelse'];
		elseif (!$mailtext && $r['xa']=='2') $mailtext=$r['beskrivelse'];
		elseif ($r['xa']=='3') $bilagnavn=$r['beskrivelse']; #2013.11.21 Finder bilag-navn
	}
	
	# Load language-specific sender email and name from settings table
	# Determine language ID: 0 for Danish/default, actual ID for other languages
	$lang_id = 0; // Default to 0 for Danish
	
	if ($formularsprog && strtolower($formularsprog) != 'dansk') {
		$qtxt = "select kodenr from grupper where art = 'VSPR' and lower(box1) = lower('$formularsprogSql')";
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
	$lang_sender_email = ifset($r, 'var_value');
	error_log("DEBUG: Found lang_sender_email='$lang_sender_email' for lang_id='$lang_id'");
	
	# Load sender name for this language
	$lang_sender_name = NULL;
	$qtxt = "select var_value from settings where var_name = 'sender_name' and var_grp = 'email_settings' and group_id = '$lang_id'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$lang_sender_name = ifset($r, 'var_value');
	error_log("DEBUG: Found lang_sender_name='$lang_sender_name' for lang_id='$lang_id'");
	if (strpos($mailtext,'$firmanavn')) {
		$qtxt = "select firmanavn from ordrer where id = '$ordre_id'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$mailtext = str_replace('$firmanavn',$r['firmanavn'],$mailtext);
		}
	}
	if (class_exists('PaymentLinkMailToken', false) && PaymentLinkMailToken::get((int)$ordre_id) !== null
		&& strpos($mailtext,'$betalingslink') === false) {
		$mailtext .= "\n\n" . '$betalingslink';
	}
	if (strpos($mailtext,'$betalingslink') !== false || strpos($subjekt,'$betalingslink') !== false) {
		require_once __DIR__ . '/../payments/paymentLinkToken.php';
		$mailtext = str_replace('$betalingslink', paymentLinkHtml(paymentLinkUrl((int)$ordre_id)), $mailtext);
		$subjekt = str_replace('$betalingslink', '', $subjekt);
	}
	# 20260817 CL/LH $abonnementslink: signed Stripe subscription link derived from
	# THIS order at send time (never stored on the order - the recurring clone in
	# debitor/genfakturer.php drops stored fields). Must be substituted BEFORE the
	# generic $-token resolvers below, which would otherwise look the token up as
	# an ordrer column. Styled link in the HTML body; the AltBody build flattens
	# anchors to "text: url". Disabled/unconfigured/no mappable lines -> ''.
	if (strpos($mailtext,'$abonnementslink') !== false || strpos($subjekt,'$abonnementslink') !== false) {
		include_once(__DIR__ . "/subscriptionLink.php");
		$abonnementslink = subscriptionLinkUrl($ordre_id);
		# 20260820 CL/LH styled CTA block (shared builder) instead of a naked anchor.
		$mailtext = str_replace('$abonnementslink', subscriptionLinkHtml($abonnementslink), $mailtext);
		$subjekt  = str_replace('$abonnementslink', '', $subjekt);
	}
	$mailtext = str_replace("\n\r","\n\r<br>",$mailtext);

	(isset($bilagnavn) && $bilagnavn)?$bilagnavn=$bilagnavn:$bilagnavn="Bilag"; #2013.11.21 Hvis bilag-navn er tom, insættes 'Bilag' som navn
	if ($sag_id) $subjekt=$subjekt." vedr.: $lev_addr1, $lev_postnr $lev_bynavn"; #2013.11.27 Her tilføjes leveringsaddr. til subjekt hvis der er sag_id
	
	$qtxt="select * from adresser where art='S'";
	$row = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$afsendermail=$row['email'];
	$afsendernavn=$row['firmanavn'];
	
	// Debug logging
	$debug_file = "../temp/$db/pbs_email_debug.log";
	$debug_msg = "\n" . date("Y-m-d H:i:s") . " - sendMail.php: Checking sender information\n";
	$debug_msg .= "Database query result:\n";
	$debug_msg .= "  email: " . var_export($row['email'], true) . "\n";
	$debug_msg .= "  firmanavn: " . var_export($row['firmanavn'], true) . "\n";
	$debug_msg .= "Initial afsendermail: " . var_export($afsendermail, true) . "\n";
	$debug_msg .= "Initial afsendernavn: " . var_export($afsendernavn, true) . "\n";
	
	# Use language-specific sender email if available, otherwise use default
	if ($lang_sender_email && trim($lang_sender_email) != '') {
		$afsendermail = $lang_sender_email;
		$debug_msg .= "Using language-specific email: '$afsendermail'\n";
		error_log("DEBUG: Using language-specific email: '$afsendermail'");
	} else {
		$debug_msg .= "Using default email: '$afsendermail' (lang_sender_email was empty or null)\n";
		error_log("DEBUG: Using default email: '$afsendermail' (lang_sender_email was empty or null)");
	}
	
	# Use language-specific sender name if available, otherwise use default
	if ($lang_sender_name && trim($lang_sender_name) != '') {
		$afsendernavn = $lang_sender_name;
		$debug_msg .= "Using language-specific sender name: '$afsendernavn'\n";
		error_log("DEBUG: Using language-specific sender name: '$afsendernavn'");
	} else {
		$debug_msg .= "Using default sender name: '$afsendernavn' (lang_sender_name was empty or null)\n";
		error_log("DEBUG: Using default sender name: '$afsendernavn' (lang_sender_name was empty or null)");
	}
	
	$afsendermail=str_replace(",",";",$afsendermail);
	$afsendermails=explode(";",$afsendermail);
	$from=$afsendermails[0];
	
	$debug_msg .= "After processing:\n";
	$debug_msg .= "  afsendermail (raw): " . var_export($afsendermail, true) . "\n";
	$debug_msg .= "  afsendermails array: " . var_export($afsendermails, true) . "\n";
	$debug_msg .= "  afsendermails[0]: " . var_export($afsendermails[0], true) . "\n";
	$debug_msg .= "  afsendernavn: " . var_export($afsendernavn, true) . "\n";
	$debug_msg .= "  from: " . var_export($from, true) . "\n";
	
	($row['felt_1'])?$smtp=$row['felt_1']:$smtp='localhost';
	($row['felt_2'])?$smtp_user=$row['felt_2']:$smtp_user=NULL;
	($row['felt_3'])?$smtp_pwd=$row['felt_3']:$smtp_pwd=NULL;
	($row['felt_4'])?$smtp_enc=$row['felt_4']:$smtp_enc=NULL;

	if ($row['mailfakt'] && $ansat_id) {
		$r = db_fetch_array(db_select("select * from ansatte where id='$ansat_id'",__FILE__ . " linje " . __LINE__));
		$brugermail=$r['email'];
	}
	
	$debug_msg .= "Validation check:\n";
	$debug_msg .= "  !afsendermails[0]: " . var_export(!$afsendermails[0], true) . "\n";
	$debug_msg .= "  !afsendernavn: " . var_export(!$afsendernavn, true) . "\n";
	$debug_msg .= "  Condition result (!afsendermails[0] || !afsendernavn): " . var_export((!$afsendermails[0] || !$afsendernavn), true) . "\n";
	
	if (!$afsendermails[0] || !$afsendernavn) {
		$debug_msg .= "ERROR: Missing sender information - email or company name is empty!\n";
		file_put_contents($debug_file, $debug_msg, FILE_APPEND);
		return ['ok' => false, 'error' => 'Missing sender mail', 'recipient' => $email];
	}
	
	$debug_msg .= "Sender information OK - proceeding with email\n";
	file_put_contents($debug_file, $debug_msg, FILE_APPEND);
	$fakturanavn=basename($filnavn);
	
	if ($ordre_id) {
		// Nyt documents-system (source=debitorOrdrer)
		$sth_path = dirname(dirname(dirname(__FILE__)));
		$newDocFolder = null;
		if      (file_exists("$sth_path/documents")) $newDocFolder = "$sth_path/documents";
		elseif  (file_exists("$sth_path/owncloud"))  $newDocFolder = "$sth_path/owncloud";
		elseif  (file_exists("$sth_path/bilag"))     $newDocFolder = "$sth_path/bilag";

		if ($newDocFolder) {
			$qtxt = "SELECT filename, filepath FROM documents WHERE source = 'debitorOrdrer' AND source_id = '$ordre_id' ORDER BY id LIMIT 1";
			if ($docRow = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				$srcFile = $newDocFolder . '/' . $db . '/' . ltrim($docRow['filepath'], '/') . '/' . $docRow['filename'];
				if (file_exists($srcFile)) {
					$mailbilag = $docRow['filename'];
					$til = "../temp/$db/$mailbilag";
					system("cp '" . str_replace("'", "'\\''", $srcFile) . "' '$til'\n");
				}
			}
		}

		// Fallback: gammelt bilag-system (bilag/{db}/bilag/ordrer/bilag_{id}[_*])
		if (!$mailbilag) {
			$r = db_fetch_array(db_select("select * from grupper where art='bilag'", __FILE__ . " linje " . __LINE__));
			if ($box6 = $r['box6']) {
				$oldFiles = glob("../bilag/$db/bilag/ordrer/bilag_{$ordre_id}") ?: array();
				$oldFiles = array_merge($oldFiles, glob("../bilag/$db/bilag/ordrer/bilag_{$ordre_id}_*") ?: array());
				if ($oldFiles) {
					$fra = $oldFiles[0];
					$mailbilag = basename($fra);
					$til = "../temp/$db/$mailbilag";
					system("cp '$fra' '$til'\n");
				}
			} else {
				$r2 = db_fetch_array(db_select("select * from grupper where art='FTP'", __FILE__ . " linje " . __LINE__));
				$box1 = $r2['box1']; $box2 = $r2['box2']; $box3 = $r2['box3']; $mappe = $r2['box4'];
				$ftpfilnavn = "bilag_" . $ordre_id;
				$fp = fopen("../temp/$db/ftpscript.$bruger_id", "w");
				if ($fp) { fwrite($fp, "cd $mappe\ncd ordrer\nget $ftpfilnavn\nbye\n"); fclose($fp); }
				$kommando = "cd ../temp/$db\n$exec_path/ncftp ftp://$box2:$box3@$box1 < ftpscript.$bruger_id > ftplog\nmv \"$ftpfilnavn\" \"$mailbilag\"\n";
				system($kommando);
			}
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
				# 20260817 CL/LH tier-1 allowlist: token must be a real ordrer
				# column - closes the SQL interpolation surface (unknown -> '').
				include_once(__DIR__ . "/mailTokenAllowlist.php");
				if (ordrer_mail_token_allowed($var)) {
					$r=db_fetch_array(db_select("select $var from ordrer where id='$ordre_id'",__FILE__ . " linje " . __LINE__));
					$ordliste[$a]=$r[$var];
				} else {
					$ordliste[$a]='';
				}
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
				# 20260817 CL/LH tier-1 allowlist (as in the subject resolver above)
				include_once(__DIR__ . "/mailTokenAllowlist.php");
				if (ordrer_mail_token_allowed($var)) {
					$qtxt="select $var from ordrer where id='$ordre_id'";
					#cho "$qtxt<br>";
					$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$ordliste[$a]=$r[$var];
				} else {
					$ordliste[$a]='';
				}
				if ($br) {
					$ordliste[$a].="<br>".$br;
				}
			} 
			$mailtext.=$ordliste[$a]." ";
		}
	}
	if ($browserGuard && !send_mails_reserve_browser($db, $ordre_id, $email, $subjekt, $filnavn)) {
		return ['ok' => false, 'error' => 'duplicate_send', 'recipient' => $email];
	}

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
	send_mails_load_mailer();
	$mail = new  PHPMailer\PHPMailer\PHPMailer();
	$mail->setSMTPInstance(new class extends PHPMailer\PHPMailer\SMTP {
		public string $deliveryStage = 'before_data';
		/** @var array<string,bool> RCPT acceptance, including CC and BCC. */
		public array $recipientOutcomes = [];

		public function recipient($address, $dsn = '') {
			$accepted = parent::recipient($address, $dsn);
			$this->recipientOutcomes[$address] = $accepted;
			return $accepted;
		}

		protected function sendCommand($command, $commandstring, $expect) {
			if ($command === 'DATA END') {
				$this->deliveryStage = 'data_sent';
			}
			$result = parent::sendCommand($command, $commandstring, $expect);
			if ($command === 'DATA END') {
				$code = (int)substr($this->getLastReply(), 0, 3);
				if ($result) {
					$this->deliveryStage = 'accepted';
				} elseif ($code >= 400 && $code <= 599) {
					$this->deliveryStage = 'rejected';
				}
			}
			return $result;
		}
	});
	$mail->SMTPOptions = array( 
	'ssl' => array( 
		'verify_peer' => false, 
		'verify_peer_name' => false, 
		'allow_self_signed' => true 
	) 
	);
	$mail->CharSet = 'UTF-8';
	$mail->SMTPDebug  = 0;                              // 0=off, 2=verbose (use 2 only for debugging)
	if ($smtp!='localhost') {
		$mail->IsSMTP();                               // send via external SMTP server
		$mail->Host  = $smtp;
		$mail->Timeout    = 10;                        // 10 sec connect timeout (default 300 is too long)
		if ($smtp_user) {
			$mail->SMTPAuth = true;     // turn on SMTP authentication
			$mail->Username = $smtp_user;  // SMTP username
			$mail->Password = $smtp_pwd; // SMTP password
			if ($smtp_enc) $mail->SMTPSecure = $smtp_enc; // SMTP kryptering
		}
	} else {
		$mail->IsMail();                               // use PHP mail() - fastest for localhost
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
	# 20260817 CL/LH flatten anchors to "text: url" BEFORE the tag conversion -
	# the plain-text part otherwise carries raw <a ...> markup (spam scoring).
	# Also repairs the existing $betalingslink button's plain-text rendering.
	$ren_text=preg_replace('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is','$2: $1',$ren_text) ?? $ren_text;
	$ren_text=str_replace("<br>","\n",$ren_text);
	$ren_text=str_replace("<b>","*",$ren_text);
	$ren_text=str_replace("</b>","*",$ren_text);
	$ren_text=str_replace("<hr>","------------------------------",$ren_text);
	# 20260820 CL/LH the CTA block's <table> markup (and any other template HTML)
	# must not reach the plain-text part raw - anchors are already flattened above.
	$ren_text=strip_tags($ren_text);
	$mail->Subject  =  "$subjekt";
	$mail->Body     =  "$mailtext";
	$mail->AltBody  =  "$ren_text";
	$ok = $mail->preSend();
	return ['ok' => $ok, 'error' => $ok ? null : 'Mailer Error: ' . $mail->ErrorInfo,
		'recipient' => $email, 'sender' => $afsendermail, 'user_mail' => $brugermail, 'mail' => $mail];
}

/**
 * Transmit only prepared MIME. No order, template or attachment is read here.
 * A false PHPMailer result can follow delivery to an accepted subset of recipients.
 * @return array{ok:bool,error:string|null,recipient:string,sender?:string,user_mail?:string|null,outcome:string,stage:string,recipients:array<string,bool>}
 */
function send_mails_transmit_result(array $prepared): array {
	if (!$prepared['ok']) {
		unset($prepared['mail']);
		return array_merge($prepared, ['outcome' => 'failed', 'stage' => 'preparation', 'recipients' => []]);
	}
	$mail = $prepared['mail'];
	unset($prepared['mail']);
	$ok = $mail->postSend();
	$smtp = $mail->getSMTPInstance();
	$stage = $mail->Mailer === 'smtp' ? $smtp->deliveryStage : 'mail';
	$outcome = $ok ? 'sent' : (in_array($stage, ['before_data', 'rejected'], true) ? 'failed' : 'unknown');
	return array_merge($prepared, ['ok' => $ok, 'error' => $ok ? null : 'Mailer Error: ' . $mail->ErrorInfo,
		'outcome' => $outcome, 'stage' => $stage, 'recipients' => $smtp->recipientOutcomes]);
}

/** Load the first installation autoloader that actually provides PHPMailer. @return void */
function send_mails_load_mailer(): void {
	foreach ([__DIR__ . '/../../../vendor/autoload.php', __DIR__ . '/../vendor/autoload.php',
		__DIR__ . '/../../vendor/autoload.php'] as $autoload) {
		if (class_exists(PHPMailer\PHPMailer\PHPMailer::class) && class_exists(PHPMailer\PHPMailer\SMTP::class)) {
			return;
		}
		if (is_file($autoload)) {
			require_once $autoload;
		}
	}
	if (!class_exists(PHPMailer\PHPMailer\PHPMailer::class) || !class_exists(PHPMailer\PHPMailer\SMTP::class)) {
		throw new RuntimeException('PHPMailer is not installed');
	}
}

/** Serialize the browser guard and atomically publish complete PHP for legacy readers. @return bool */
function send_mails_reserve_browser(string $database, int $orderId, string $email, string $subject, string $filename): bool {
	$directory = __DIR__ . '/../../temp/' . $database;
	$lock = fopen($directory . '/mailchk.lock', 'c');
	if ($lock === false) {
		throw new RuntimeException('Cannot open mail guard lock');
	}
	$temporary = null;
	try {
		if (!flock($lock, LOCK_EX)) {
			throw new RuntimeException('Cannot lock mail guard');
		}
		$path = $directory . '/mailchk.php';
		$chkOid = $chkMail = $chkSubj = $chkFil = $chkTid = null;
		if (is_file($path)) {
			try {
				include $path;
			} catch (ParseError $error) {
				// Replace an already corrupted legacy guard with a complete record below.
			}
		}
		$now = time();
		if ($orderId == $chkOid && $email == $chkMail && $subject == $chkSubj && $filename == $chkFil && $now < $chkTid + 30) {
			return false;
		}
		$content = "<?php\n";
		foreach (['chkOid' => $orderId, 'chkMail' => $email, 'chkSubj' => $subject, 'chkTid' => $now, 'chkFil' => $filename] as $name => $value) {
			$content .= '$' . $name . '=' . var_export($value, true) . ";\n";
		}
		$temporary = tempnam($directory, 'mailchk-');
		if ($temporary === false || file_put_contents($temporary, $content) !== strlen($content) || !rename($temporary, $path)) {
			throw new RuntimeException('Cannot publish mail guard');
		}
		if (function_exists('opcache_invalidate')) {
			opcache_invalidate($path, true);
		}
		return true;
	} finally {
		if (is_string($temporary) && is_file($temporary)) {
			unlink($temporary);
		}
		flock($lock, LOCK_UN);
		fclose($lock);
	}
}
