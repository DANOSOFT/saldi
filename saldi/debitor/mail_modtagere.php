<?php
@session_start();
$s_id=session_id();
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------debitor/mail_modtagere.php---------------------Patch 3.7.4-----2018.12.28---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2016-2018 saldi.dk aps
// -----------------------------------------------------------------------------------
// 20170613 PHR Tilføjet flere variabler til mailteksten og rettet datoudtræk fra beskrivelse. 
// 20180417 PHR	rettet ',$mailtekst)' til ',$mtxt)' i '$mtxt=str_replace('$kontonr',$kontonr[$x],$mtxt)'
// 20100907	PHR tilføjet and art='D' så den ikke fanger en kreditor...
// 20181228 PHR tilføjet and ordrelinjer.fast_db > '0' så rabatlinjer ikke medregnes i antal.

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

ini_set("include_path", ".:../phpmailer");
require("class.phpmailer.php");

$x=0;
$qtxt="select betalinger.modt_navn,betalinger.betalingsdato,betalinger.belob,kassekladde.kredit,kassekladde.beskrivelse from betalinger,kassekladde ";
$qtxt.="where betalinger.liste_id='$liste_id' and kassekladde.id = betalinger.bilag_id ";
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
if ($kontonr==1030) echo "$qtxt<br>";	
	$antal[$x]=$r2['antal']*1;
	$qtxt="select id,firmanavn,email from adresser where kontonr='$kontonr[$x]' and art='D'	";
	$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$modt_navn[$x]=$r2['firmanavn'];
	$email[$x]=$r2['email'];
	$x++;
}
$mailantal=$x;
if ($testmail) {
	$r=db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
	echo "sender til $r[email]<br>";
	$x=0;
	$mtxt=str_replace('$navn',$modt_navn[$x],$mailtekst);
	$mtxt=str_replace('$kontonr',$kontonr[$x],$mtxt);
	$mtxt=str_replace('$sum',$belob[$x],$mtxt);
	$mtxt=str_replace('$antal',$antal[$x],$mtxt);
	$mtxt=str_replace('$start',dkdato($start[$x]),$mtxt);
	$mtxt=str_replace('$slut',dkdato($slut[$x]),$mtxt);
	$mtxt=str_replace("\n","<br>",$mtxt);
	send_mail($emne,$mtxt,$r['email'],$r['email'],$r['firmanavn']);
}

if ($send_mails) {
	$r=db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
	for ($x=0;$x<count($modt_navn);$x++) {
		$mtxt=str_replace('$navn',$modt_navn[$x],$mailtekst);
		$mtxt=str_replace('$kontonr',$kontonr[$x],$mtxt);
		$mtxt=str_replace('$sum',$belob[$x],$mtxt);
		$mtxt=str_replace('$antal',$antal[$x],$mtxt);
		$mtxt=str_replace('$start',$start[$x],$mtxt);
		$mtxt=str_replace('$slut',$slut[$x],$mtxt);
		$mtxt=str_replace("\n","<br>",$mtxt);
		send_mail($emne,$mtxt,$email[$x],$r['email'],$r['firmanavn']);
	}
}


if (!$emne) $emne="Afregning";
if (!$mailtekst) {
	$r=db_fetch_array(db_select("select * from adresser where art='S'"));
	$mailtekst='Kære $navn (Konto: $kontonr)'. "\n".'Du har i peroden $start til $slut opnået et tilgodehavende på kr. $sum'."\n";
	$mailtekst.='I perioden er der solgt i alt $antal varer fra din stand'."\n\n";
	$mailtekst.="Beløbet vil blive overført til din konto en af de nærmeste dage\n\n";
	$mailtekst.="Med venlig hilsen\n";
	$mailtekst.="$r[firmanavn]\n";
}
print "<a href=betalinger.php?liste_id=$liste_id>Tilbage til betalingsliste</a>";
print "<center>";
print "<form name=\"mail_modtagere\" action=\"mail_modtagere.php?liste_id=$liste_id\" method=\"post\">";
print "<table><tbody>";
#print "<tr><td><b>Periode</b></td></tr>";
#print "<tr><td><input style=\"width:100px\" type=\"text\" name=\"start\" value=\"".dkdato($start)."\"> til <input style=\"width:100px\" type=\"text\" name=\"start\" value=\"dkdato($start)\"></td></tr>";
print "<tr><td><b>Emne.</b></td></tr>";
print "<tr><td><input style=\"width:800px\" type=\"text\" name=\"emne\" value=\"$emne\"></td></tr>";
print "<tr><td></td></tr>";
print "<tr><td><b>Mailtekst.</b> Du kan bruge $"."navn som navn på modtager,$"."kontonr som kontonr $"."sum som det beløb der overføres,<br>$"."antal som antal solgte enheder samt $"."start og $"."slut som hhv start og slutdato</td></tr>";
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
#	if ($x>2) break 1;
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
#		$subjekt=utf8_decode($subjekt);
#		$mailtekst=utf8_decode($mailtekst);
#	}
#	echo $mailtekst;
	$tmpmappe="../temp/$db/afr_mail";
	mkdir($tmpmappe);
	if ($subjekt && $mailtekst && $modtager && $afsendermail && $afsendernavn) {	
		$mailtext = "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTMP 4.01 Transitional//EN\">\n";
		$mailtext .= "<html><head><meta content=\"text/html; charset=ISO-8859-15\" http-equiv=\"content-type\">\n";
		$mailtext .=$mailtekst;
		$mailtext .= "</html>\n";			
		if ($charset=="UTF-8") {
			$subjekt=utf8_decode($subjekt);
			$mailtext=utf8_decode($mailtext);
			$afsendernavn=utf8_decode($afsendernavn);
			$afsendermail=utf8_decode($afsendermail);
		}
#	echo $mailtext;
#		$fp=fopen("$tmpmappe/afregning.html","w");
#		fwrite($fp,$mailtext);
#		fclose ($fp);

		$mail = new PHPMailer();
		$mail->IsSMTP();                                   // send via SMTP
		$mail->Host  = "localhost"; // SMTP servers
		$mail->SMTPAuth = false;     // turn on SMTP authentication

		if (strpos($_SERVER['SERVER_NAME'],'saldi.dk')) { #20121029
			if ($_SERVER['SERVER_NAME']=='ssl.saldi.dk') $mail->From = $db.'@ssl.saldi.dk'; #20140128
			elseif ($_SERVER['SERVER_NAME']=='ssl2.saldi.dk') $mail->From = $db.'@ssl2.saldi.dk'; #20140128
			elseif ($_SERVER['SERVER_NAME']=='ssl3.saldi.dk') $mail->From = $db.'@ssl3.saldi.dk'; #20140128
			else $mail->From = 'kanikkebesvares@saldi.dk'; #20140128
			$mail->FromName = $afsendernavn;
		} else {
			$mail->From = $afsendermail;
			$mail->FromName = $afsendernavn;
		}
		$splitter=NULL;
		$mail->AddAddress($modtager); 
		$mail->AddBCC($afsendermail); 
		$mail->AddReplyTo($afsendermail,$afsendernavn);

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

