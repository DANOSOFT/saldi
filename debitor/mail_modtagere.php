<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/mail_modtagere.php --- Patch 4.0.8 --- 2025.04.07 ---
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
// Copyright (c) 2022-2025.dk ApS
// -----------------------------------------------------------------------------------
// 20170613 PHR Tilføjet flere variabler til mailteksten og rettet datoudtræk fra beskrivelse. 
// 20180417 PHR	rettet ',$mailtekst)' til ',$mtxt)' i '$mtxt=str_replace('$kontonr',$kontonr[$x],$mtxt)'
// 20100907	PHR tilføjet and art='D' så den ikke fanger en kreditor...
// 20181228 PHR tilføjet and ordrelinjer.fast_db > '0' så rabatlinjer ikke medregnes i antal.
// 20220511 PHR Removed uft-8 decode
// 20220808 PHR Translated all texts
// 20220825 PHR Sets domainname in emails to servername for all saldi servers 
// 20221124 PHR Added $mail->ReturnPath = $afsendermail;
// 20230718 PHR Added $begin & $end to be used in no dates in 'beskrivelse'
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20250407 PHR will now use phpmailer from composer if old phpmailer is not present

@session_start();
$s_id=session_id();

$modulnr=12;
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/forfaldsdag.php");

$emne=if_isset($_POST['emne']);
$liste_id=if_isset($_GET['liste_id']);
$mailtekst=if_isset($_POST['mailtekst']);
$send_mails=if_isset($_POST['send_mails']);
$testmail=if_isset($_POST['testmail']);
$begin = if_isset($_GET['start']);
$end = if_isset($_GET['slut']);

if ($mailtekst) {
	$qtxt = "select id from settings where var_name = 'mailtext' and var_grp = 'paylist' and user_id='0'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['id']) $qtxt = "update settings set var_value = '". db_escape_string($mailtekst) ."' where id =  '$r[id]'";
	else {
		$qtxt = "insert into settings (var_name, var_grp, var_value, user_id, var_description) ";
		$qtxt.= " values ";
		$qtxt.= "('mailtext','paylist','". db_escape_string($mailtekst) ."','0',";
		$qtxt.= "'text for mails to commission customers when transferring from paylist')";
	}	
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

if (file_exists(".:../phpmailer")) { #20250407
	ini_set("include_path", ".:../phpmailer");
	require("class.phpmailer.php");
} else {
	require_once "../../vendor/autoload.php"; //PHPMailer Object
	$mail = new  PHPMailer\PHPMailer\PHPMailer();
	$mail->SMTPOptions = array(
		'ssl' => array(
			'verify_peer' => false,
			'verify_peer_name' => false,
			'allow_self_signed' => true
		)
	);
}
$qtxt = "select count(bilag_id) as bilag_id from betalinger where liste_id='$liste_id' and bilag_id != 0";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
if ($r['bilag_id']) {
	$x=0;
	$qtxt = "select betalinger.modt_navn,betalinger.betalingsdato,betalinger.belob,";
	$qtxt.= "kassekladde.kredit,kassekladde.beskrivelse from betalinger,kassekladde ";
	$qtxt.= "where betalinger.liste_id='$liste_id' and kassekladde.id = betalinger.bilag_id ";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r= db_fetch_array($q)){
#	$beskrivelse=trim(str_replace('Afregning','',$r['beskrivelse']));
	list($tmp,$slut[$x])=explode(" - ",$r['beskrivelse']);
#if ($x==3) echo "tmp $tmp -> slut $slut[$x]<br>";
		list($tmp,$tmp,$start[$x])=explode(" ",$tmp);
#if ($x==3) echo "$tmp, start $start[$x]<br>";
		$start[$x]=usdate(trim($start[$x]));
		$slut[$x]=usdate(trim($slut[$x]));
#if ($x==3) echo "$beskrivelse start $start[$x] -> slut $slut[$x]<br>";
		$kontonr[$x]=$r['kredit'];
		$belob[$x]=$r['belob'];
		$qtxt="select sum(antal) as antal from ordrelinjer,ordrer where ";
		$qtxt.="(ordrer.art='PO') and ordrer.fakturadate >= '$start[$x]' and ordrer.fakturadate <= '$slut[$x]' and ";
		$qtxt.="ordrelinjer.ordre_id = ordrer.id and ordrelinjer.varenr like '%$kontonr[$x]' and ordrelinjer.fast_db > '0'";
		$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$antal[$x]=(float)$r2['antal'];
		$qtxt="select id,firmanavn,email from adresser where kontonr='$kontonr[$x]' and art='D'	";
		$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$modt_navn[$x]=$r2['firmanavn'];
		$email[$x]=$r2['email'];
		$x++;
	}
} else {
	$x=0;
	$qtxt = "select egen_ref,modt_navn,betalingsdato,belob ";
	$qtxt.= "from betalinger where betalinger.liste_id='$liste_id'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r= db_fetch_array($q)){
		list($a,$b) = explode('-',$r['egen_ref'],2);
		$kontonr[$x] = trim(str_replace('Afr:','',$a));
		$belob[$x]=$r['belob'];
		if ($begin && $end) {
			$start[$x] = usdate($begin);
			$slut[$x]  = usdate($end);
			$qtxt="select sum(antal) as antal from ordrelinjer,ordrer where ";
			$qtxt.="(ordrer.art='PO') and ";
			$qtxt.="ordrer.fakturadate >= '$start[$x]' and ordrer.fakturadate <= '$slut[$x]' and ";
			$qtxt.="ordrelinjer.ordre_id = ordrer.id and ordrelinjer.varenr like '%$kontonr[$x]' and ordrelinjer.fast_db > '0'";
			$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$antal[$x] = (float)if_isset($r2['antal']);
		} else $start[$x] = $slut[$x] = $antal[$x] = NULL;
		$qtxt="select id,firmanavn,email from adresser where kontonr='$kontonr[$x]' and art='D'";
		$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$modt_navn[$x]=$r2['firmanavn'];
		$email[$x]=$r2['email'];
		$x++;
	}
}
	
$mailantal=$x;

if ($testmail) {
	$r=db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
	echo "sender til $r[email]<br>";
	$x=0;
	$mtxt=str_replace('$navn',$modt_navn[$x],$mailtekst);
	$mtxt=str_replace('$name',$modt_navn[$x],$mtxt);
	$mtxt=str_replace('$kontonr',$kontonr[$x],$mtxt);
	$mtxt=str_replace('$account',$kontonr[$x],$mtxt);
	$mtxt=str_replace('$sum',$belob[$x],$mtxt);
	$mtxt=str_replace('$antal',$antal[$x],$mtxt);
	$mtxt=str_replace('$qty',$antal[$x],$mtxt);
	$mtxt=str_replace('$start',dkdato($start[$x]),$mtxt);
	$mtxt=str_replace('$slut',dkdato($slut[$x]),$mtxt);
	$mtxt=str_replace('$end',dkdato($slut[$x]),$mtxt);
	$mtxt=str_replace("\n","<br>",$mtxt);
	send_mail($emne,$mtxt,$r['email'],$r['email'],$r['firmanavn']);
}

if ($send_mails) {
	$r=db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
	for ($x=0;$x<count($modt_navn);$x++) {
		$mtxt=str_replace('$navn',$modt_navn[$x],$mailtekst);
		$mtxt=str_replace('$name',$modt_navn[$x],$mtxt);
		$mtxt=str_replace('$kontonr',$kontonr[$x],$mtxt);
		$mtxt=str_replace('$account',$kontonr[$x],$mtxt);
		$mtxt=str_replace('$sum',$belob[$x],$mtxt);
		$mtxt=str_replace('$antal',$antal[$x],$mtxt);
		$mtxt=str_replace('$qty',$antal[$x],$mtxt);
		$mtxt=str_replace('$start',dkdato($start[$x]),$mtxt);
		$mtxt=str_replace('$slut',dkdato($slut[$x]),$mtxt);
		$mtxt=str_replace('$end',dkdato($slut[$x]),$mtxt);
		$mtxt=str_replace("\n","<br>",$mtxt);
		send_mail($emne,$mtxt,$email[$x],$r['email'],$r['firmanavn']);
	}
}

if (!$emne) $emne = findtekst(2051,$languageID);
if (!$mailtekst) {
	$mailtekst = findtekst(2052,$languageID)."\n\n";
	$mailtekst.= findtekst(2053,$languageID)."\n\n";
	$mailtekst.= findtekst(2054,$languageID)."\n\n";
	$mailtekst.= findtekst(2055,$languageID)."\n\n";
	$mailtekst.= findtekst(2056,$languageID)."\n";
	$r=db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
	$mailtekst.="$r[firmanavn]\n";
}
print "<a href=betalinger.php?liste_id=$liste_id>".findtekst(2059,$languageID)."</a>";
print "<center>";
print "<form name='mail_modtagere' action='mail_modtagere.php?liste_id=$liste_id";
if ($begin && $end) print "&start=$begin&slut=$end";
print "' method='post'>";
print "<table><tbody>";
#print "<tr><td><b>Periode</b></td></tr>";
#print "<tr><td><input style=\"width:100px\" type=\"text\" name=\"start\" value=\"".dkdato($start)."\"> til <input style=\"width:100px\" type=\"text\" name=\"start\" value=\"dkdato($start)\"></td></tr>";
print "<tr><td><b>Emne.</b></td></tr>";
print "<tr><td><input style=\"width:800px\" type=\"text\" name=\"emne\" value=\"$emne\"></td></tr>";
print "<tr><td></td></tr>";
print "<tr><td><b>".findtekst(2057,$languageID)."</b> ".findtekst(2058,$languageID)."</td></tr>";
print "<tr><td>HTML koder accepteres</td></tr>";
#print "<tr><td>Fed="."<"."b"."><b>tekst</b><"."/b"."></td></tr>";
print "<tr><td><textarea rows='16' cols='100' name='mailtekst'>$mailtekst</textarea></td></tr>";
print "<tr><td></td></tr>";
print "<tr><td style=\"text-align:center;\"><input type='submit' name='opdater' value='Opdater'>&nbsp;";
print "<input type='submit' name='send_mails' value='Send mail' onClick=\"return confirm('send $mailantal mails nu?')\">&nbsp;";
print "<input type='submit' name='testmail' value='Send testmail'></td></tr>";
print "<tr><td></td></tr>";
print "<tr><td><b>Eksempel</b> (Første 4 modtagere af $mailantal)</td></tr>";
for ($x=0;$x<$mailantal;$x++) {
	print "<tr><td></td></tr>";
	$eksempel=str_replace('$navn',$modt_navn[$x],$mailtekst);
	$eksempel=str_replace('$kontonr',$kontonr[$x],$eksempel);
	$eksempel=str_replace('$sum',$belob[$x],$eksempel);
	$eksempel=str_replace('$antal',$antal[$x],$eksempel);
	$eksempel=str_replace('$start',dkdato($start[$x]),$eksempel);
	$eksempel=str_replace('$slut',dkdato($slut[$x]),$eksempel);
	$eksempel=str_replace("\n","<br>",$eksempel);
	print "<tr><td>$eksempel</td></tr>";
	print "<tr><td><hr></td></tr>";
	if ($x>2) break 1;
}

print "</tbody></table>";
print "<form>";


function send_mail($subjekt,$mailtekst,$modtager,$afsendermail,$afsendernavn) {
	global $db;
	global $brugernavn;
	global $bgcolor;
	global $bgcolor5;
	global $charset;
	
#	echo $charset;
	
	$mailtekst=str_replace("\n","<br>",$mailtekst);
#	if ($charset == 'UTF-8') {
#		$subjekt=mb_convert_encoding($subjekt, 'ISO-8859-1', 'UTF-8');
#		$mailtekst=mb_convert_encoding($mailtekst, 'ISO-8859-1', 'UTF-8');
#	}
#	echo $mailtekst;
	$tmpmappe="../temp/$db/afr_mail";
	mkdir($tmpmappe);
	if ($subjekt && $mailtekst && $modtager && $afsendermail && $afsendernavn) {	
		$mailtext = "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTMP 4.01 Transitional//EN\">\n";
		$mailtext .= "<html><head><meta content=\"text/html; charset=utf-8\" http-equiv=\"content-type\">\n";
		$mailtext .=$mailtekst;
		$mailtext .= "</html>\n";			
/*
		if ($charset=="UTF-8") {
			$subjekt=mb_convert_encoding($subjekt, 'ISO-8859-1', 'UTF-8');
			$mailtext=mb_convert_encoding($mailtext, 'ISO-8859-1', 'UTF-8');
			$afsendernavn=mb_convert_encoding($afsendernavn, 'ISO-8859-1', 'UTF-8');
			$afsendermail=mb_convert_encoding($afsendermail, 'ISO-8859-1', 'UTF-8');
		}
*/		
#	echo $mailtext;
#		$fp=fopen("$tmpmappe/afregning.html","w");
#		fwrite($fp,$mailtext);
#		fclose ($fp);

		$mail = new PHPMailer();
		$mail->IsSMTP();                                   // send via SMTP
		$mail->Host  = "localhost"; // SMTP servers
		$mail->SMTPAuth = false;     // turn on SMTP authentication

		if (strpos($_SERVER['SERVER_NAME'],'saldi.dk')) { #20121029
			$from = $db."@".$_SERVER['SERVER_NAME']; #20220825 
		} else $from = $afsendermail;
		$splitter=NULL;
		if ($from != $afsendermail) $mail->AddReplyTo($afsendermail);
		$mail->From = $from;
		$mail->FromName = $afsendernavn;
		$mail->ReturnPath = $afsendermail;
		$mail->AddAddress($modtager); 
		if ($from != $afsendermail) $mail->AddBCC($afsendermail); 
		$mail->WordWrap = 50;                              // set word wrap
#		$mail->AddAttachment("$tmpmappe/afregning.html");      // attachment
		$mail->IsHTML(true);                               // send as HTML
		$mail->Subject  =  $subjekt;
		
		$mailbody = "<html><body>\n";
    $mailbody .= "$mailtext\n";
		$mailbody .= "</body></html>";

		$mail->Body     =  $mailbody;
#		$mail->AltBody  =  $mailaltbody;
		if(!$mail->Send()){
			 echo "Fejl i afsendelse til $modtager<p>";
 				echo "Mailer Error: " . $mail->ErrorInfo;
 		 		exit;
		}
		echo "Afregning sendt til $modtager<br>";
#			sleep(2);
	}	
#	unlink("$tmpmappe/afregning.html");
#	rmdir($tmpmappe);
}
?>

