<?php
// -----------index/glemt_kode.php-----------lap 3.5.0------2015-01-27---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2015.01.27 tilføjet $bruger_id da man eller får en tom mail hvis brugernavnet ikke er en 
//		mailadresse og der ikke er tilknyttet en mailadresse 20150137 

$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/db_query.php");
include("../includes/std_func.php");

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

if (isset ($_GET['regnskab'])) $regnskab = html_entity_decode(stripslashes($_GET['regnskab']),ENT_COMPAT,$charset);
if (isset ($_GET['navn'])) $brugernavn = html_entity_decode(stripslashes($_GET['navn']),ENT_COMPAT,$charset);
		
if (isset($_POST['retur']) && $_POST['retur']=='Retur') {
	print "<meta http-equiv=\"refresh\" content=\"0;url=index.php\">\n";
} elseif (isset($_POST['send']) && $_POST['send']=='Send') {
	if (isset ($_POST['regnskab'])) $regnskab = db_escape_string($_POST['regnskab']);
	if (isset ($_POST['navn'])) $brugernavn = db_escape_string($_POST['navn']);

	if ($regnskab) {
		if ($r = db_fetch_array(db_select("select * from regnskab where regnskab = '$regnskab'",__FILE__ . " linje " . __LINE__))){
			$db=$r['db'];
		} elseif ($r = db_fetch_array(db_select("select * from regnskab where lower(regnskab) = '".strtolower($regnskab)."' or  upper(regnskab) = '".strtoupper($regnskab)."'",__FILE__ . " linje " . __LINE__))){
			$db=$r['db'];
			$regnskab=$r['regnskab'];
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
		
		if ($db!=$sqdb && $r = db_fetch_array(db_select("select email from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))){
			$firmanavn=$row['firmanavn'];
			$firmamail=$r['email'];
			($row['felt_1'])?$smtp=$row['felt_1']:$smtp='localhost';
			($row['felt_2'])?$smtp_user=$row['felt_2']:$smtp_user=NULL;
			($row['felt_3'])?$smtp_pwd=$row['felt_3']:$smtp_pwd=NULL;
		}
	} elseif ($brugernavn && strpos($brugernavn,'@')) {
		$regnskaber=NULL;
		$q=db_select("select * from regnskab where lower(email)='".strtolower($brugernavn)."'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			($regnskaber)?$regnskaber.=", ".$r['regnskab']:$regnskaber=$r['regnskab'];
			$firmamail=$brugernavn;
			$firmanavn="saldi.dk";
		}
	} else $email=

	$alerttxt=NULL;
	if ($regnskab && !$db) $alerttxt="Regnskab $regnskab ikke fundet\\\n";
	elseif ($regnskab && !$db && !$email && $ansat_id && $firmamail) $alerttxt="$brugernavn ikke fundet tilnkyttet email i $regnskab\\n Midlertidig adgangskode sendt til $firmamail\\n";	
	elseif (!$email  && !$firmamail) $alerttxt="Hverken bruger $brugernavn eller regnskab $regnskab er tilknyttet en mailadresse";
	elseif ($brugernavn && !$bruger_id && $db) $alerttxt="Brugernavnet $brugernavn findes ikke i regnskabet $regnskab!\\n Lad feltet være tomt for at få sendt en liste over oprettede brugere";
	
	if ($alerttxt) print "<BODY onLoad=\"javascript:alert('$alerttxt')\">";
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
			db_modify("update brugere set tmp_kode='$tmp_kode' where id='$bruger_id'");
		} elseif (!$ansat_id && !$regnskaber && $db) {
			$subjekt="Brugerliste til regnskabet $regnskab";
			$mailtext.="I dag den $dd kl. $tp er der blevet rekvireret en brugerliste til regnskabet $regnskab <br><br>Brugerne er: $brugere.	<br><br>";
		} elseif ($regnskaber) {
			$subjekt="Saldi regnskab";
			$mailtext.="I dag den $dd kl. $tp er der blevet rekvireret en liste over regnskaber tilknyttet $firmamail<br><br>$firmamail et tilknyttet : $regnskaber.	<br><br>";
		}
		$mailtext.="God fornøjelse fra dit Saldi team<br>";
#		fclose ($fp);
		
		ini_set("include_path", ".:../phpmailer");
		require("class.phpmailer.php");
		if ($charset=="UTF-8" || $webservice) {
			$subjekt=utf8_decode($subjekt);
			$mailtext=utf8_decode($mailtext);
			$firmanavn=utf8_decode($firmanavn);
		}
				$mail = new PHPMailer();
		$mail->IsSMTP();                                   // send via SMTP
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

		$mail->Subject  =  "$subjekt";
		$mail->Body     =  "$mailtext";
		$mail->AltBody  =  "$ren_text";

#echo "$subjekt<br>$mailtext<br>";
		
		if(!$mail->Send()){
echo "Fejl i afsendelse til $email<p>";
echo "Mailer Error: " . $mail->ErrorInfo;
			$svar = "Mailer Error: " . $mail->ErrorInfo;
			return ($svar);
#		exit;
		}
		if ($email && $firmamail) $tekst="Mail sendt til $email\\nBCC til $firmamail.";
		elseif ($email) $tekst="Mail sendt til $email.";
		elseif ($firmamail) $tekst="Mail sendt til $firmamail.";
		print "<BODY onLoad=\"javascript:alert('$tekst')\">";
	}
}
PRINT "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
<html>\n
<head><title>Glemt kode</title>";
if ($css) PRINT "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\">";
print "<meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\"></head>\n";
print "<body><table style=\"width:100%;height:100%;\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";# Tabel 1 ->
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; #Tabel 1.1 ->
print "<tr><td  style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" width=\"45%\"> Ver $version</td>";
print "<td style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;;background:url(../img/grey1.gif)\" width=\"10%\" align = \"center\"> <a href=\"$vejledning\" target=\"_blank\">Vejledning</a></td>\n";
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
print "<tr><td>".findtekst(322,$sprog_id)."</td>";
print "<td width=\"2%\">";
print "<input class=\"inputbox\" style=\"width:160px\" type=\"TEXT\" NAME=\"regnskab\" value=\"$regnskab\">";
print "</tr><tr><td>".findtekst(323,$sprog_id)."</td><td><INPUT class=\"inputbox\" style=\"width:160px\" TYPE=\"TEXT\" NAME=\"navn\" value=\"$brugernavn\"></td></tr>\n";
print "<tr><td><br></td>";
print "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"send\" value=\"Send\"><input type=\"submit\" name=\"retur\" value=\"Retur\"></td></tr>\n";
print "</tbody></table></FORM></td></tr>\n"; # <- tabel 1.2.1
print	"</tbody></table></td></tr>\n"; # <- tabel 1.2
print "<tr><td width=\"100%\"><table><tbody>"; # tabel 1.3 ->
print "<tr><td width=\"10%\"></td><td width=\"80%\">Skriv navn på regnskab, dit brugernavn og klik send, så vil det blive sendt en mail med en midlertidig adgangskode 
	til den mailadresse som hører til brugernavnet.<br><br>\nEr det ikke tilknyttet en mail til brugernavnet, sendes mailen til den mailadresse der er registreret
	på regnskabet.<br><br>\nHar du glemt navnet på regnskabet, skal du skrive din mailadresse i feltet \"Brugernavn\" og klikke på send, så bliver navn på det 
	regnskab som er knyttet til mailadressen sendt sammen med brugernavn til mailadressen.<br><br>\nHar du glemt dit brugernavn, så skriv navnet på dit regnskab i feltet 
	\"Regnskab\" og der vil blive sendt en liste over brugere til den mail som er knyttet til regnskabet\n.
	</td><td width=\"10%\"></td></tr>\n";
print	"</tbody></table></td></tr>\n"; # <- tabel 1.3
print "<tr><td align=\"center\" valign=\"bottom\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.3 ->
print "<td width=\"20%\" style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" align=\"left\">&nbsp;Copyright&nbsp;&copy;&nbsp;2003-2014&nbsp;DANOSOFT&nbsp;ApS</td>";
print "<td width=\"60%\" style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/grey1.gif);\" align=\"center\">Et <a href=\"http://www.saldi.dk\" target=\"blank\">SALDI</a> regnskab</td>";
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
