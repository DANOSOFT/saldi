<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- index/glemt_kode.php ---  ver 5.1.0 --- 2026.11.05 ---
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
// 20261105 CL/MMR SD-807 The page, its messages and the help text use the language chosen on the login page (languageId cookie, 1-3).
//                 Send and Retur are recognised by their button name, so the translated button texts still work.
//                 The BCC line and the send error are translated too; the send error escapes the address.
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: script-src 'self' 'nonce-$nonce'; object-src 'none';");


$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/db_query.php");
include("../includes/std_func.php");

// Nobody is logged in here, so the texts use the language chosen on the login page.
$sprog_id = (int) ifset($_COOKIE, 'languageId', 1);
if ($sprog_id < 1 || $sprog_id > 3) {
	$sprog_id = 1;
}

if ($db_encode=="UTF8") $charset="UTF-8";
else $charset="ISO-8859-1";
if (file_exists("../doc/vejledning.pdf")) $vejledning="../doc/vejledning.pdf";
else $vejledning="http://saldi.dk/dok/komigang.html";

$ansat_id=NULL;
$db=NULL;
$email=NULL;
$firmamail=NULL;
$brugernavn=NULL;
$regnskab=NULL;
$regnskaber=NULL;
$navn=NULL;

#if (isset ($_GET['regnskab'])) $regnskab = html_entity_decode(stripslashes($_GET['regnskab']),ENT_COMPAT,$charset);
#if (isset ($_GET['navn'])) $brugernavn = html_entity_decode(stripslashes($_GET['navn']),ENT_COMPAT,$charset);

#Sanitize Inputs with Strict Type Checking 20250313
if (isset($_GET['regnskab'])) { # && preg_match('/^[a-zA-Z0-9_-]+$/', $_GET['regnskab'])) {
#    $regnskab = htmlspecialchars($_GET['regnskab'], ENT_QUOTES, 'UTF-8');
	$regnskab = db_escape_string($_GET['regnskab']);
} else {
	$regnskab = '';
}

if (isset($_GET['navn'])) { # && preg_match('/^[a-zA-Z0-9_-]+$/', $_GET['navn'])) {
#    $brugernavn = htmlspecialchars($_GET['navn'], ENT_QUOTES, 'UTF-8');
		$brugernavn = db_escape_string($_GET['navn']);
} else {
		$brugernavn = '';
}
	

if (isset($_POST['retur'])) {
	print "<meta http-equiv=\"refresh\" content=\"0;url=index.php\">\n";
} elseif (isset($_POST['send'])) {
	// if (isset ($_POST['regnskab'])) $regnskab = db_escape_string($_POST['regnskab']);
	// if (isset ($_POST['navn'])) $brugernavn = db_escape_string($_POST['navn']);
	if (isset($_POST['regnskab'])) { # && preg_match('/^[a-zA-Z0-9_-]+$/', $_POST['regnskab'])) {
		$regnskab = db_escape_string($_POST['regnskab']);
	} else {
		$regnskab = '';
	}
	
	if (isset($_POST['navn'])) { #&& preg_match('/^[a-zA-Z0-9_-]+$/', $_POST['navn'])) {
		$brugernavn = db_escape_string($_POST['navn']);
	} else {
		$brugernavn = '';
	}
	


	if ($regnskab) {
		$qtxt = "select * from regnskab where regnskab = '$regnskab'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
			$db=$r['db'];
		} elseif ($r = db_fetch_array(db_select("select * from regnskab where lower(regnskab) = '".strtolower($regnskab)."' or  upper(regnskab) = '".strtoupper($regnskab)."'",__FILE__ . " linje " . __LINE__))){
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
			$bruger_id=$r['id'];
			$ansat_id=$r['ansat_id'];
		} elseif ($brugernavn && $r = db_fetch_array(db_select("select * from brugere where lower(brugernavn) = '".strtolower($brugernavn)."' or  upper(brugernavn) = '".strtoupper($brugernavn)."'",__FILE__ . " linje " . __LINE__))){
			$bruger_id=$r['id'];
			$ansat_id=$r['ansat_id'];
			$brugernavn=$r['brugernavn'];
		} elseif (!$brugernavn) {
			$brugere=NULL;
			$q=db_select("select * from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
			($brugere)?$brugere.=", ".$r['brugernavn']:$brugere=$r['brugernavn'];
			}
		}
		if ($ansat_id) {
			if ($r = db_fetch_array(db_select("select email,navn from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__))) {
				$email=$r['email'];
				$navn=$r['navn'];
			}
		} elseif (strpos($brugernavn,'@')) $email=strtolower($brugernavn);
		
		if ($db!=$sqdb && $row = db_fetch_array(db_select("select email from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))){
			// $firmanavn=$row['firmanavn'];
			// $firmamail=$row['email'];
			$firmanavn = isset($row['firmanavn']) ? $row['firmanavn'] : null;
			$firmamail = isset($row['email']) ? $row['email'] : null;

			// ($row['felt_1'])?$smtp=$row['felt_1']:$smtp='localhost';
			// ($row['felt_2'])?$smtp_user=$row['felt_2']:$smtp_user=NULL;
			// ($row['felt_3'])?$smtp_pwd=$row['felt_3']:$smtp_pwd=NULL;
			$smtp = isset($row['felt_1']) ? $row['felt_1'] : 'localhost';
			$smtp_user = isset($row['felt_2']) ? $row['felt_2'] : NULL;
			$smtp_pwd = isset($row['felt_3']) ? $row['felt_3'] : NULL;

		}
	} elseif ($brugernavn && strpos($brugernavn,'@')) {
		$regnskaber=NULL;
		$q=db_select("select * from regnskab where lower(email)='".strtolower($brugernavn)."'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			($regnskaber)?$regnskaber.=", ".$r['regnskab']:$regnskaber=$r['regnskab'];
			$firmamail=$brugernavn;
			$firmanavn="saldi.dk";
		}
	} else $email=  $firmamail;
	
	
	$alerttxt=NULL;
	if ($regnskab && !$db) $alerttxt = sprintf(findtekst('8436|Regnskab %s ikke fundet', $sprog_id), $regnskab);
	elseif ($regnskab && !$db && !$email && $ansat_id && $firmamail) $alerttxt="$brugernavn ikke fundet tilnkyttet email i $regnskab\n Midlertidig adgangskode sendt til $firmamail\n";
	elseif (!$email  && !$firmamail) $alerttxt = sprintf(findtekst('8437|Hverken bruger %s eller regnskab %s er tilknyttet en mailadresse', $sprog_id), $brugernavn, $regnskab);
	elseif ($brugernavn && !$bruger_id && $db) $alerttxt = sprintf(str_replace('\n', "\n", findtekst('8438|Brugernavnet %s findes ikke i regnskabet %s!\n Lad feltet være tomt for at få sendt en liste over oprettede brugere', $sprog_id)), $brugernavn, $regnskab);
	else $alerttxt=NULL;
	#if ($alerttxt) print "<BODY onLoad=\"javascript:alert('$alerttxt')\">";
	if ($alerttxt) print "<script nonce=\"$nonce\">window.onload = function() { alert(" . json_encode(mb_convert_encoding($alerttxt, 'UTF-8', $charset), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . "); }</script>"; # 20250313
	else {
		
	#		if (!file_exists ("../temp/$db")) system ("mkdir ../temp/$db\n");
#		$filnavn="../temp/$db/pw.txt";
		$tidspunkt=date("U");
		$dd=date("d-m-Y",$tidspunkt);
		$tp=date("H:i",$tidspunkt);
		$mailtext='';
		if (($bruger_id || $ansat_id || $email || $db==$sqdb) && $db) { #20150127
			$subjekt="Midlertidig adgangskode til Saldi";
			$tmp_kode=substr(str_shuffle( "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ" ),5,10);
			if (!$email && $firmamail) {
				$email=$firmamail;
				$firmamail=NULL;
			}
			$tidspunkt=date("U");
			$dd=date("d-m-Y",$tidspunkt);
			$tp=date("H:i",$tidspunkt);
#		$fp=fopen("$filnavn","w");
			if ($navn) $mailtext.="Kære $navn<br><br>";
			$mailtext.="I dag den $dd kl. $tp er der blevet rekvireret en midlertidig adgangskode til"; 
			if ($brugernavn) $mailtext.=" brugeren $brugernavn i";
			$mailtext.=" regnskabet $regnskab <br><br>";
			$tidspunkt+=3600;
			$dd=date("d-m-Y",$tidspunkt);
			$tp=date("H:i",$tidspunkt);
			$mailtext.="Indtil den $dd kl. $tp kan anvendes adgangskoden: $tmp_kode<br><br>";
			$mailtext.="Efter login kan adgangskoden ændres under \"Indstillinger -> Brugere\"<br><br>";
			$tmp_kode=$tidspunkt."|".$tmp_kode;
			#$tmp_kode=$tidspunkt."_".$tmp_kode;
			$i = 0;
			$feltnavne=array();
			$q = db_select("select * from brugere",__FILE__ . " linje " . __LINE__);
			while ($i < db_num_fields($q)) { 
				$feltnavne[$i] = db_field_name($q,$i); 
				$i++; 
			}
			if (!in_array('tmp_kode',$feltnavne)) {
				db_modify("ALTER TABLE brugere ADD tmp_kode text",__FILE__ . " linje " . __LINE__);
			}
			#var_dump($tmp_kode.':'.$bruger_id); exit;
			db_modify("update brugere set tmp_kode='$tmp_kode' where id='$bruger_id'",__LINE__);
		} elseif (!$ansat_id && !$regnskaber && $db) {
			$subjekt="Brugerliste til regnskabet $regnskab";
			$mailtext.="I dag den $dd kl. $tp er der blevet rekvireret en brugerliste til regnskabet $regnskab <br><br>Brugerne er: $brugere.	<br><br>";
		} elseif ($regnskaber) {
			$subjekt="Saldi regnskab";
			$mailtext.="I dag den $dd kl. $tp er der blevet rekvireret en liste over regnskaber tilknyttet $firmamail<br><br>$firmamail et tilknyttet : $regnskaber.	<br><br>";
		}
		$mailtext.="God fornøjelse fra dit Saldi team<br>";
#		fclose ($fp);
/*
		ini_set("include_path", ".:../phpmailer");
		require("class.phpmailer.php");
		if ($charset=="UTF-8" || $webservice) {
#			$subjekt=utf8_decode($subjekt);
#			$mailtext=utf8_decode($mailtext);
#			$firmanavn=utf8_decode($firmanavn);
		}
		$mail = new PHPMailer();
*/
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
		if (!$smtp) $smtp='localhost';
		$mail->Host  = $smtp; // SMTP servers 
		if ($smtp_user) {
			$mail->SMTPAuth = true;     // turn on SMTP authentication
			$mail->Username = $smtp_user;  // SMTP username
			$mail->Password = $smtp_pwd; // SMTP password
		} else $mail->SMTPAuth = false;
		$mail->From = 'kan_ikke_besvares@saldi.dk';
		$mail->FromName = $firmanavn;
		if ($firmamail && $firmanavn) $mail->AddReplyTo($firmamail,$firmanavn);
		if ($email) $mail->AddAddress($email);
		elseif ($firmamail) $mail->AddAddress($firmamail);
#		for ($i=1;$i<count($emails);$i++) $mail->AddCC($emails[$i]); 
		if ($email && $firmamail) $mail->AddBCC($firmamail);
		if ($brugermail) $mail->AddBCC($brugermail);
		$mail->WordWrap = 50;  // set word wrap
#		$mail->AddAttachment("$filnavn","$fakturanavn","base64","application/pdf");      // attachment
		$mail->IsHTML(true);                               // send as HTML

		$ren_text=html_entity_decode($mailtext,ENT_COMPAT,$charset);
		$ren_text=str_replace("<br>","\n",$ren_text);
		$ren_text=str_replace("<b>","*",$ren_text);
		$ren_text=str_replace("</b>","*",$ren_text);
		$ren_text=str_replace("<hr>","------------------------------",$ren_text);

		$mail->CharSet  =  "$charset";
		$mail->Subject  =  "$subjekt";
		$mail->Body     =  "$mailtext";
		$mail->AltBody  =  "$ren_text";

#echo "$subjekt<br>$mailtext<br>";
		
		if(!$mail->Send()){
echo htmlspecialchars(findtekst('8450|Fejl i afsendelse til', $sprog_id) . " $email", ENT_QUOTES, $charset) . "<p>";
echo "Mailer Error: " . $mail->ErrorInfo;
			$svar = "Mailer Error: " . $mail->ErrorInfo;
			return ($svar);
#		exit;
		}
		if ($email && $firmamail) $tekst = findtekst('3370|Mail sendt til', $sprog_id) . " $email\n" . findtekst('8449|BCC til', $sprog_id) . " $firmamail.";
		elseif ($email) $tekst = findtekst('3370|Mail sendt til', $sprog_id) . " $email.";
		elseif ($firmamail) $tekst = findtekst('3370|Mail sendt til', $sprog_id) . " $firmamail.";
#		print "<BODY onLoad=\"javascript:alert('$tekst')\">";
		print "<script nonce=\"$nonce\">window.onload = function() { alert(" . json_encode(mb_convert_encoding($tekst, 'UTF-8', $charset), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . "); }</script>";
	}
}
PRINT "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
<html>\n
<head><title>" . findtekst('8435|Glemt kode', $sprog_id) . "</title>";
if ($css) PRINT "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\">";
print "<meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\"></head>\n";
print "<body><table style=\"width:100%;height:100%;\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";# Tabel 1 ->
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; #Tabel 1.1 ->
print "<tr><td  style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" width=\"45%\"> Ver $version</td>";
print "<td style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;;background:url(../img/grey1.gif)\" width=\"10%\" align = \"center\"> <a href=\"$vejledning\" target=\"_blank\">" . findtekst('92|Vejledning', $sprog_id) . "</a></td>\n";
print "<td style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;;background:url(../img/grey1.gif)\" width=\"45%\" align = \"right\">&nbsp;</td></tr>\n";
print "</tbody></table></td></tr><tr><td align=\"center\" valign=\"middle\">\n"; # <- tabel 1.1 slut
print "<table width=\"350\" align=\"center\" border=\"5\" cellspacing=\"5\" cellpadding=\"5\"><tbody>"; # tabel 1.2 ->
print "<tr><td><FORM name=\"login\" METHOD=\"POST\" ACTION=\"glemt_kode.php\" onSubmit=\"return handleLogin(this);\"><table width=\"100%\" align=center border=\"0\" cellspacing=\"0\" cellpadding=\"1\"><tbody>"; # tabel 1.2.1 ->
if (isset($mastername)&&$mastername) $tmp="<big><big><big><b>$mastername</b></big></big></big>";   
elseif (strpos($_SERVER['PHP_SELF'],"beta")) $tmp="<big><big><big><b>!!! BETA !!!</b></big></big></big>";
else $tmp="<big><big><big><b>SALDI</b></big></big></big>";
print "<tr><td colspan=\"2\">";
print "<table width=\"100%\"><tbody><tr><td width=\"10%\">"; # tabel 1.2.1.1 ->
print "";
if (file_exists("../img/logo.png")) print "<img style=\"border:0px solid;width:50px;heigth:50px\" alt=\"\" src=\"../img/logo.png\">";
print "</td><td width=\"80%\" align=\"center\">$tmp</td><td width=\"10%\" align=\"right\">";
if (file_exists("../img/logo.png")) print "<img style=\"border:0px solid;width:50px;heigth:50px\" alt=\"\" src=\"../img/logo.png\"></td></tr>\n";
print "</tbody></table></td></tr>"; # <- tabel 1.2.1.1
print "<tr><td colspan=\"2\"><hr></td></tr>\n";
print "<tr><td>".findtekst('2682|Regnskab', $sprog_id)."</td>";
print "<td width=\"2%\">";
print "<input class=\"inputbox\" style=\"width:160px\" type=\"TEXT\" NAME=\"regnskab\" value=\"$regnskab\">";
print "</tr><tr><td>".findtekst(323,$sprog_id)."</td><td><INPUT class=\"inputbox\" style=\"width:160px\" TYPE=\"TEXT\" NAME=\"navn\" value=\"$brugernavn\"></td></tr>\n";
print "<tr><td><br></td>";
print "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"send\" value=\"" . htmlspecialchars(findtekst('2310|Send', $sprog_id), ENT_QUOTES, $charset) . "\"><input type=\"submit\" name=\"retur\" value=\"" . htmlspecialchars(findtekst('2956|Retur', $sprog_id), ENT_QUOTES, $charset) . "\"></td></tr>\n";
print "</tbody></table></FORM></td></tr>\n"; # <- tabel 1.2.1
print	"</tbody></table></td></tr>\n"; # <- tabel 1.2
print "<tr><td width=\"100%\"><table><tbody>"; # tabel 1.3 ->
print "<tr><td width=\"10%\"></td><td width=\"80%\">" . findtekst('8439|Skriv navn på regnskab, dit brugernavn og klik send, så vil det blive sendt en mail med en midlertidig adgangskode til den mailadresse som hører til brugernavnet.<br><br>Er det ikke tilknyttet en mail til brugernavnet, sendes mailen til den mailadresse der er registreret på regnskabet.<br><br>Har du glemt navnet på regnskabet, skal du skrive din mailadresse i feltet "Brugernavn" og klikke på send, så bliver navn på det regnskab som er knyttet til mailadressen sendt sammen med brugernavn til mailadressen.<br><br>Har du glemt dit brugernavn, så skriv navnet på dit regnskab i feltet "Regnskab" og der vil blive sendt en liste over brugere til den mail som er knyttet til regnskabet.', $sprog_id) . "</td><td width=\"10%\"></td></tr>\n";
print	"</tbody></table></td></tr>\n"; # <- tabel 1.3
print "<tr><td align=\"center\" valign=\"bottom\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.3 ->
print "<td width=\"20%\" style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" align=\"left\">&nbsp;Copyright&nbsp;&copy;&nbsp;2003-2025&nbsp;DANOSOFT&nbsp;ApS</td>";
print "<td width=\"60%\" style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" align=\"center\">" . sprintf(findtekst('8440|Et %s regnskab', $sprog_id), "<a href=\"http://www.saldi.dk\" target=\"blank\">SALDI</a>") . "</td>";
print "<td width=\"20%\" style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" align=\"left\"><br></td>";
print "</tr></tbody></table>"; # <- tabel 1.3
print "</td></tr>\n";
print "</tbody></table>"; # <- tabel 1
if (!isset($_COOKIE['saldi_std'])) {
	print "<script language=\"javascript\" type=\"text/javascript\">";  
	print "document.login.regnskab.focus();";
	print "</script>";
} else {
	print "<script language=\"javascript\" type=\"text/javascript\">";
	print "document.login.login.focus();";
	print "</script>";
}
?>
</body></html>
