<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- index/glemt_kode.php ---  ver 5.1.1 --- 2026.11.24 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20150127 tilføjet $bruger_id da man eller får en tom mail hvis brugernavnet ikke er en 
//		mailadresse og der ikke er tilknyttet en mailadresse 20150137 
// 20220226 PHR Added 	$mail->CharSet = '$charset';
// 20250312 LOE Added a Content Security Policy (CSP) header snippet to mitigate DOM-based XSS attacks.
// 20250313 LOE // converts any special HTML characters (like <, >, &, etc.) into their corresponding HTML entities for $regnskab and $brugernavn and updated some others 
// 20250616 PHR Removed preg_match as it prevented special chars in user / account name and enabled composer | phpmailer
// 20260225 PHR Some enhancements...
// 20261103 CL/ASR The password-reset token in brugere.tmp_kode starts with 'reset|', the shared format the user system reads (invitations use 'invite|').
// 20261105 CL/LH Recovery mail goes through the regnskab's own SMTP server: the stamdata lookup reads felt_1-felt_4 and firmanavn, not only email.
//                It fell back to localhost on every regnskab, so no recovery mail was sent where localhost has no mail server.
//                Only that server gets the login (felt_2/felt_3) and the stamdata e-mail as sender, set by stamdata_smtp(); Saldi's own on localhost stays anonymous.
// 20261105 CL/MMR SD-807 The page, its messages and the help text use the language chosen on the login page (languageId cookie, 1-3).
//                 Send and Retur are recognised by their button name, so the translated button texts still work.
//                 The BCC line and the send error are translated too; the send error escapes the address.
// 20261106 CL/MMR SD-812 The mail with the temporary password, the user list and the account list use the same language; the names in it are escaped.
// 20261106 CL/MMR SD-812 Review (Saul): the account list sentence reads «er tilknyttet:» (new text 8489), and the plain-text part of the mail also decodes apostrophes.
// 20261113 CDX/NTR Keep the reset token prefix in translated temporary-password emails.
// 20261114 CL/ASR New layout, the login page's box (css/login.css), with the messages on the page in place of alert() and the texts in the login's language.
//                 The mail goes to the login's own e-mail first (brugere.email, as the two-factor code does), then the employee's, then the ledger's. The temporary password comes from random_int(). The screen shows the address masked.
// 20261114 CL/ASR The logo is the dark-gradient logo for light backgrounds from the final logo pack of October 2026 (img/saldi-moerk-logo-lys-baggrund.svg).
// 20261114 CL/ASR css/login.css ?cssver=3: the button has the dark gradient of the logo.
// 20261114 CL/ASR css/login.css ?cssver=4: a light sheen slides across the button from left to right when it is pressed.
// 20261124 CL/ASR The include of includes/twoFactor.php is resolved from this file's folder (__DIR__), not the caller's working directory.
// 20261124 CL/ASR Comments state the current rule; no code change.

$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: script-src 'self' 'nonce-$nonce'; object-src 'none';");

$css="../css/login.css?cssver=4";

include("../includes/connect.php");
include("../includes/db_query.php");
include("../includes/std_func.php");
include_once(__DIR__ . "/../includes/twoFactor.php");
include_once(__DIR__ . '/../includes/stdFunc/stamdataSmtp.php');

if ($db_encode=="UTF8") $charset="UTF-8";
else $charset="ISO-8859-1";

$languageId = (isset($_COOKIE['languageId']) && in_array((int) $_COOKIE['languageId'], array(1, 2, 3), true)) ? (int) $_COOKIE['languageId'] : 1;
$h = function ($value) use ($charset) {
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, $charset);
};
$t = function ($text) use ($languageId, $h) {
	return $h(findtekst($text, $languageId));
};

/**
 * A temporary password of ten characters from the system's random source, without the characters that are easy
 * to mix up when typed from a mail (0 O, 1 l I).
 */
function glemt_kode_ny(): string
{
	$alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	$kode = '';
	for ($i = 0; $i < 10; $i++) {
		$kode .= $alphabet[random_int(0, strlen($alphabet) - 1)];
	}
	return $kode;
}

// What the visitor typed, as typed: escaped for SQL where it is looked up and for HTML where it is shown.
$regnskabIn = '';
$brugerIn = '';
if (isset($_GET['regnskab'])) $regnskabIn = trim((string) $_GET['regnskab']);
if (isset($_GET['navn'])) $brugerIn = trim((string) $_GET['navn']);

/** @var array{0: string, 1: string}|null $notice 'ok' or 'err' and the text, already escaped. */
$notice = null;
$sent = false;

if (isset($_POST['send'])) {
	$regnskabIn = isset($_POST['regnskab']) ? trim((string) $_POST['regnskab']) : '';
	$brugerIn = isset($_POST['navn']) ? trim((string) $_POST['navn']) : '';
	$regnskab = db_escape_string($regnskabIn);
	$brugernavn = db_escape_string($brugerIn);

	$db = NULL;
	$bruger_id = NULL;
	$ansat_id = NULL;
	$email = NULL;
	$navn = NULL;
	$firmamail = NULL;
	$firmanavn = NULL;
	$brugere = NULL;
	$regnskaber = NULL;
	$stamdata = NULL;
	$brugerFundet = NULL;

	if ($regnskab) {
		$qtxt = "select * from regnskab where regnskab = '$regnskab'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
			$db=$r['db'];
		} elseif ($r = db_fetch_array(db_select("select * from regnskab where lower(regnskab) = '".strtolower($regnskab)."' or  upper(regnskab) = '".strtoupper($regnskab)."'",__FILE__ . " linje " . __LINE__))) {
			$db=$r['db'];
			$regnskab= db_escape_string($r['regnskab']);
		}
	}

	if ($db) {
		if ($db!=$sqdb) {
			$connection = db_connect ("'$sqhost'", "'$squser'", "'$sqpass'", "'$db'");
			if (!isset($connection)) die( "Unable to connect to SQL");
		}
		if ($brugernavn && $r = db_fetch_array(db_select("select * from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__))){
			$brugerFundet = $r;
		} elseif ($brugernavn && $r = db_fetch_array(db_select("select * from brugere where lower(brugernavn) = '".strtolower($brugernavn)."' or  upper(brugernavn) = '".strtoupper($brugernavn)."'",__FILE__ . " linje " . __LINE__))) {
			$brugerFundet = $r;
		} elseif (!$brugernavn) {
			$q=db_select("select * from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				($brugere)?$brugere.=", ".$r['brugernavn']:$brugere=$r['brugernavn'];
			}
		}
		if ($brugerFundet) {
			$bruger_id = (int) $brugerFundet['id'];
			$ansat_id = isset($brugerFundet['ansat_id']) ? (int) $brugerFundet['ansat_id'] : 0;
			$brugernavn = db_escape_string($brugerFundet['brugernavn']);
			// The login's own e-mail comes first: it is the one the user system asks for and the two-factor code uses.
			if (isset($brugerFundet['email']) && twofactor_email_ok($brugerFundet['email'])) {
				$email = strtolower(trim((string) $brugerFundet['email']));
			}
			if ($ansat_id) {
				if ($r = db_fetch_array(db_select("select email,navn from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__))) {
					if (!$email && twofactor_email_ok($r['email'])) $email = trim((string) $r['email']);
					$navn=$r['navn'];
				}
			}
			if (!$email && twofactor_email_ok($brugerFundet['brugernavn'])) $email = strtolower(trim((string) $brugerFundet['brugernavn']));
		}
		if ($db!=$sqdb && $row = db_fetch_array(db_select("select firmanavn, email, felt_1, felt_2, felt_3, felt_4 from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))){
			$firmanavn = isset($row['firmanavn']) ? $row['firmanavn'] : null;
			$firmamail = (isset($row['email']) && twofactor_email_ok($row['email'])) ? trim((string) $row['email']) : null;
			$stamdata = $row; // its email and felt_1-felt_4 pick the mail server and sender in stamdata_smtp()
		}
	} elseif (!$regnskab && $brugernavn && strpos($brugernavn,'@')) {
		$q=db_select("select * from regnskab where lower(email)='".strtolower($brugernavn)."'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			($regnskaber)?$regnskaber.=", ".$r['regnskab']:$regnskaber=$r['regnskab'];
			$firmamail=$brugerIn;
			$firmanavn="saldi.dk";
		}
	}
	
	$tidspunkt=date("U");
	$dd=date("d-m-Y",$tidspunkt);
	$tp=date("H:i",$tidspunkt);
	$mailtext='';
	$subjekt='';
	$kodeSendt = false;

	if (!$regnskab && !$brugernavn) {
		$notice = array('err', $t('login.forgot.empty|Skriv regnskabets navn, eller skriv din e-mail i Brugernavn.'));
	} elseif ($regnskab && !$db) {
		$notice = array('err', $h(sprintf(findtekst('login.forgot.no_ledger|Regnskabet %s findes ikke.', $languageId), $regnskabIn)));
	} elseif ($db && $brugernavn && !$bruger_id) {
		$notice = array('err', $h(sprintf(findtekst('login.forgot.no_user|Brugernavnet %s findes ikke i regnskabet %s.', $languageId), $brugerIn, $regnskabIn)));
	} elseif ($db && !$email && !$firmamail) {
		$notice = array('err', $t('login.forgot.no_mail|Der står ingen e-mail på hverken brugeren eller regnskabet. Kontakt jeres administrator.'));
	} elseif ($db && $bruger_id) {
		$subjekt = findtekst('8466|Midlertidig adgangskode til Saldi', $languageId);
		$tmp_kode=glemt_kode_ny();
		if (!$email && $firmamail) {
			$email=$firmamail;
			$firmamail=NULL;
		}
		if ($navn) $mailtext .= sprintf(findtekst('8467|Kære %s', $languageId), $h($navn)) . "<br><br>";
		if ($brugernavn) {
			$mailtext .= sprintf(findtekst('8468|I dag den %s kl. %s er der blevet rekvireret en midlertidig adgangskode til brugeren %s i regnskabet %s', $languageId), $dd, $tp, $h($brugerFundet['brugernavn']), $h($regnskabIn)) . " <br><br>";
		} else {
			$mailtext .= sprintf(findtekst('8469|I dag den %s kl. %s er der blevet rekvireret en midlertidig adgangskode til regnskabet %s', $languageId), $dd, $tp, $h($regnskabIn)) . " <br><br>";
		}
		$tidspunkt+=3600;
		$dd=date("d-m-Y",$tidspunkt);
		$tp=date("H:i",$tidspunkt);
		$mailtext .= sprintf(findtekst('8470|Indtil den %s kl. %s kan anvendes adgangskoden: %s', $languageId), $dd, $tp, $tmp_kode) . "<br><br>";
		$mailtext .= findtekst('8471|Efter login kan adgangskoden ændres under "Indstillinger -> Brugere"', $languageId) . "<br><br>";
		$tmp_kode="reset|".$tidspunkt."|".$tmp_kode; # 20260929 Sawaneh common tmp_kode format
		db_modify("update brugere set tmp_kode='$tmp_kode' where id='$bruger_id'",__FILE__ . " linje " . __LINE__);
		$kodeSendt = true;
	} elseif ($db) {
		$subjekt = sprintf(findtekst('8472|Brugerliste til regnskabet %s', $languageId), $regnskabIn);
		$mailtext .= sprintf(findtekst('8473|I dag den %s kl. %s er der blevet rekvireret en brugerliste til regnskabet %s', $languageId), $dd, $tp, $h($regnskabIn)) . " <br><br>";
		$mailtext .= sprintf(findtekst('8474|Brugerne er: %s.', $languageId), $h($brugere)) . "\t<br><br>";
	} elseif ($regnskaber) {
		$subjekt = findtekst('8475|Saldi regnskab', $languageId);
		$mailtext .= sprintf(findtekst('8476|I dag den %s kl. %s er der blevet rekvireret en liste over regnskaber tilknyttet %s', $languageId), $dd, $tp, $h($firmamail)) . "<br><br>";
		$mailtext .= sprintf(findtekst('8489|%s er tilknyttet: %s.', $languageId), $h($firmamail), $h($regnskaber)) . "\t<br><br>";
	} elseif ($brugernavn && strpos($brugernavn,'@')) {
		$notice = array('err', $t('login.forgot.unknown_mail|Der er ikke noget regnskab med den e-mail.'));
	} else {
		$notice = array('err', $t('login.forgot.empty|Skriv regnskabets navn, eller skriv din e-mail i Brugernavn.'));
	}

	if (!$notice) {
		$mailtext .= findtekst('8478|God fornøjelse fra dit Saldi team', $languageId) . "<br>";
		require_once "../../vendor/autoload.php"; //PHPMailer Object
		$mail = new  PHPMailer\PHPMailer\PHPMailer();
		$mail->SMTPOptions = array(
		'ssl' => array(
			'verify_peer' => false,
			'verify_peer_name' => false,
			'allow_self_signed' => true
		)
		);
		$mail->IsSMTP();                                   // send via SMTP
		$mail->CharSet  =  "$charset";
		stamdata_smtp($mail, $stamdata, 'kan_ikke_besvares@saldi.dk'); // the regnskab's own SMTP server with its login and sender, else Saldi's own on localhost
		$mail->FromName = $firmanavn;
		if ($firmamail && $firmanavn) $mail->AddReplyTo($firmamail,$firmanavn);
		$til = $email ? $email : $firmamail;
		$mail->AddAddress($til);
		$kopi = ($email && $firmamail && strtolower($email) !== strtolower($firmamail));
		if ($kopi) $mail->AddBCC($firmamail);
		$mail->WordWrap = 50;  // set word wrap
		$mail->IsHTML(true);                               // send as HTML

		$ren_text=html_entity_decode($mailtext,ENT_QUOTES,$charset);
		$ren_text=str_replace("<br>","\n",$ren_text);
		$ren_text=str_replace("<b>","*",$ren_text);
		$ren_text=str_replace("</b>","*",$ren_text);
		$ren_text=str_replace("<hr>","------------------------------",$ren_text);

		$mail->Subject  =  "$subjekt";
		$mail->Body     =  "$mailtext";
		$mail->AltBody  =  "$ren_text";

		if(!$mail->Send()){
			error_log('glemt_kode.php: ' . $mail->ErrorInfo);
			$notice = array('err', $t('login.forgot.mail_failed|E-mailen kunne ikke sendes. Prøv igen senere.'));
		} else {
			// The address is shown masked.
			$tekst = sprintf(findtekst('login.forgot.sent|Vi har sendt en e-mail til %s. Tjek også spam.', $languageId), twofactor_mask_email($til));
			if ($kodeSendt) $tekst .= ' ' . findtekst('login.forgot.code_valid|Koden gælder i 1 time.', $languageId);
			if ($kopi) $tekst .= ' ' . findtekst('login.forgot.copy|En kopi er sendt til regnskabets e-mail.', $languageId);
			$notice = array('ok', $h($tekst));
			$sent = true;
		}
	}
	// Back on the master database for the texts below.
	include("../includes/connect.php");
}

$loginUrl = 'index.php?regnskab=' . rawurlencode($regnskabIn) . '&navn=' . rawurlencode($brugerIn);
$selfUrl = 'glemt_kode.php?regnskab=' . rawurlencode($regnskabIn) . '&navn=' . rawurlencode($brugerIn);
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=<?= $h($charset) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $t('login.forgot.title|Glemt adgangskode') ?></title>
<link rel="stylesheet" type="text/css" href="<?= $h($css) ?>">
<link rel="shortcut icon" href="../../index/favicon.ico">
</head>
<body class="loginPage">
<div id="main">
	<div class="loginHolder">
		<div class="loginBox">
			<div class="loginForm">
				<a href="https://saldi.dk"><img class="logoimg" src="../img/saldi-moerk-logo-lys-baggrund.svg" width="100" alt="Saldi"></a>
				<div class="loginIntro">
					<h2><?= $t('login.forgot.title|Glemt adgangskode') ?></h2>
<?php if (!$sent) { ?>
					<p><?= $t('login.forgot.intro|Skriv regnskab og brugernavn, så sender vi en midlertidig adgangskode til din e-mail.') ?></p>
<?php } ?>
<?php if ($notice) { ?>
					<p class="loginMsg<?= $notice[0] === 'err' ? ' loginMsgErr' : '' ?>" role="<?= $notice[0] === 'err' ? 'alert' : 'status' ?>"><?= $notice[1] ?></p>
<?php } ?>
				</div>
<?php if ($sent) { ?>
				<div>
					<div class="loginAction">
						<a class="button blue" href="<?= $h($loginUrl) ?>" tabindex="1"><?= $t('login.back|Tilbage til login') ?></a>
					</div>
					<div class="loginLinks">
						<a class="linkbutton" href="<?= $h($selfUrl) ?>" tabindex="2"><?= $t('login.forgot.again|Send igen') ?></a>
					</div>
				</div>
<?php } else { ?>
				<form name="glemt" method="POST" action="glemt_kode.php">
					<label for="regnskab"><?= $t('2682|Regnskab') ?></label>
					<input class="textinput" type="text" id="regnskab" name="regnskab" value="<?= $h($regnskabIn) ?>" maxlength="60" tabindex="1">
					<label for="navn"><?= $t('225|Brugernavn') ?></label>
					<input class="textinput" type="text" id="navn" name="navn" value="<?= $h($brugerIn) ?>" maxlength="80" tabindex="2">
					<div class="loginAction">
						<button class="button blue" type="submit" name="send" value="1" tabindex="3"><?= $t('login.forgot.send|Send e-mail') ?></button>
					</div>
					<div class="loginLinks">
						<a class="linkbutton" href="<?= $h($loginUrl) ?>" tabindex="4"><?= $t('login.back|Tilbage til login') ?></a>
					</div>
				</form>
				<details class="loginHelp">
					<summary><?= $t('login.forgot.more|Glemt brugernavnet eller regnskabets navn?') ?></summary>
					<p><?= $t('login.forgot.help_user|Glemt brugernavnet? Lad feltet Brugernavn stå tomt, så sender vi en liste over regnskabets brugere til regnskabets e-mail.') ?></p>
					<p><?= $t('login.forgot.help_ledger|Glemt regnskabets navn? Lad feltet Regnskab stå tomt, og skriv din e-mail i Brugernavn, så sender vi navnet på dit regnskab.') ?></p>
					<p><?= $t('login.forgot.help_nomail|Står der ingen e-mail på din bruger, sendes koden til regnskabets e-mail.') ?></p>
				</details>
<?php } ?>
			</div>
		</div>
	</div>
</div>
<?php if (!$sent) { ?>
<script nonce="<?= $h($nonce) ?>">
	(function () {
		var ledger = document.getElementById('regnskab');
		var user = document.getElementById('navn');
		(ledger.value === '' ? ledger : user).focus();
	})();
</script>
<?php } ?>
</body>
</html>
