<?php
@session_start();
$s_id=session_id();
// ------------------------------------------------------------debitor/mail_kontoudtog.php-------patch 1.0.2---
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
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------
$modulnr=12;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdecimal.php");
include("../includes/dkdato.php");
include("../includes/usdate.php");
include("../includes/forfaldsdag.php");

if ($_POST['submit']) {
 	$submit=strtolower(trim($_POST['submit']));
	$kontoantal=$_POST['kontoantal'];
	$maaned_fra=$_POST['maaned_fra'];
	$maaned_til=$_POST['maaned_til'];
	$regnaar=$_POST['regnaar'];
	$konto_id=$_POST['konto_id'];
	$email=$_POST['email'];
	$fra=$_POST['fra'];
	$til=$_POST['til'];
}
else {
	$kontoliste=$_GET['kontoliste'];
	$kontoantal=$_GET['kontoantal'];
	$maaned_fra=$_GET['maaned_fra'];
	$maaned_til=$_GET['maaned_til'];
	$regnaar=$_GET['regnaar'];
}

if ($submit=="send mail(s)"){
	send_mails($kontoantal, $konto_id, $email, $fra, $til);
	print "<form name=luk action=../includes/luk.php method=post>";	
	print "<div style=\"text-align: center;\"><br><br><input type=submit value=\"Luk\" name=\"submit\">";
	print "</form></div>";
	exit;	
#	print "<body onload=\"javascript:opener.focus();window.close();\">";
}

$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'");
$row = db_fetch_array($query);
$startmaaned=$row[box1]*1;
$startaar=$row[box2]*1;
$slutmaaned=$row[box3]*1;
$slutaar=$row[box4]*1;
$slutdato=31;

if ($maaned_fra) {$startmaaned=$maaned_fra;}
if ($maaned_til) {$slutmaaned=$maaned_til;}

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}
	
while (!checkdate($slutmaaned,$slutdato,$slutaar))	{
	$slutdato=$slutdato-1;
	if ($slutdato<28){break;}
}

$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;


print "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";

for ($x=1; $x<=12; $x++) {
	if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
	if ($maaned_til==$md[$x]){$maaned_til=$x;}
	if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
	if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
}

$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'");
$row = db_fetch_array($query);
$startmaaned=$row[box1]*1;
$startaar=$row[box2]*1;
$slutmaaned=$row[box3]*1;
$slutaar=$row[box4]*1;
$slutdato=31;

$currentdate=date("Y-m-d");

if ($maaned_fra) {$startmaaned=$maaned_fra;}
if ($maaned_til) {$slutmaaned=$maaned_til;}

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}
	
while (!checkdate($slutmaaned,$slutdato,$slutaar))	{
	$slutdato=$slutdato-1;
	if ($slutdato<28){break;}
}

$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

print "<form name=kontoudtog action=mail_kontoudtog.php method=post>";

for($x=1; $x<=$kontoantal; $x++) {
	if ($kontoliste) {list($konto_id[$x], $kontoliste)=split(";", $kontoliste, 2);}
	
	if ($fra[$x]) {$fromdate[$x]= usdate($fra[$x]);}
	else {$fromdate[$x]= $regnstart;}
	if ($til[$x]) {$todate[$x]=usdate($til[$x]);} 
	else {$todate[$x]= $currentdate;}
	
	$fra[$x]=dkdato($fromdate[$x]);
	$til[$x]=dkdato($todate[$x]);
	
	$query = db_select("select * from adresser where id=$konto_id[$x]");
	$row = db_fetch_array($query);
	if (!$email[$x]){$email[$x]=$row[email];}
	print "<tr><td colspan=8><hr style=\"height: 10px; background-color: rgb(200, 200, 200);\"></td></tr>";
	print "<tr><td colspan=3>$font<small><small>$row[firmanavn]</small></small></td></tr>";
	print "<tr><td colspan=3>$font <small><small>$row[addr1]</small></small></td></tr>";
	print "<tr><td colspan=3>$font <small><small>$row[addr2]</small></small></td><td colspan=3 align=right>$font<small><small>Kontonr</small></small></td><td align=right>$font <small><small>$row[kontonr]</small></small></td></tr>";
	print "<tr><td colspan=3>$font <small><small>$row[postnr] $row[bynavn]</small></small></td><td colspan=3 align=right>$font <small><small>Dato</small></small></td><td align=right>$font <small><small>".date('d-m-Y')."</small></small></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td width=10%>$font <small><small>Dato</td><td width=5%>$font <small><small>Bilag</small></small></td><td width=5%>$font <small><small>Faktura</small></small></td><td width=40% align=center>$font <small><small>Tekst</small></small></td><td>$font <small><small>Forfaldsdato</small></small></td><td width=10% align=right>$font <small><small>Debet</small></small></td><td width=10% align=right>$font <small><small>Kredit</small></small></td><td width=10% align=right>$font <small><small>Saldo</small></small></td></tr>";
	print "<tr><td colspan=8><hr></td></tr>";
	$betalingsbet=trim($row[betalingsbet]);
	$betalingsdage=$row[betalingsdage];
	$kontosum=0;
	$primo=0;
	$primoprint=0;
	$query = db_select("select * from openpost where konto_id=$konto_id[$x] and transdate<='$todate[$x]' order by transdate, faktnr");
	while ($row = db_fetch_array($query)) {
		 if ($row[transdate]<$fromdate[$x]) {
			$primoprint=0;
			$kontosum=$kontosum+$row[amount];
		}		 
		else { 
			if ($primoprint==0) {
				$tmp=dkdecimal($kontosum);
				$linjebg=$bgcolor5; $color='#000000';
				print "<tr bgcolor=\"$linjebg\"><td></td><td><td></td></td><td align=center>$font <small><small>Primosaldo</small></small></td><td></td><td></td><td></td><td align=right><small><small>$font $tmp</small></small></td></tr>";
				$primoprint=1;
			}
		    	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\"><td align=left><small><small>$font ".dkdato($row[transdate])."&nbsp;</small></small></td><td><small><small>$font $row[refnr]</small></small></td><td align=right><small><small>$font $row[faktnr]&nbsp;</small></small></td><td><small><small>$font $row[beskrivelse]</small></small></td>";
			if ($row[amount] < 0) {$tmp=0-$row[amount];}
			else {$tmp=$row[amount];}
			$tmp=dkdecimal($tmp);
			$forfaldsdag=usdate(forfaldsdag($row[transdate], $betalingsbet, $betalingsdage));
			if (($row[udlignet]!='1')&&($forfaldsdag<$currentdate)){$stil="<span style='color: rgb(255, 0, 0);'>";}
			else {$stil="<span style='color: rgb(0, 0, 0);'>";}
			if ($row[amount] > 0) {
				print "<td>$font  $stil<small><small>".dkdato($forfaldsdag)."</small></small></td><td align=right>$stil<small><small>$font $tmp</small></small></td><td></td>";
				$forfaldsum=$forfaldsum+$row[amount];
			}
			else {print "<td></td><td></td><td align=right>$font <small><small>$tmp</small></small></td>";}
			
			$kontosum=$kontosum+$row[amount];
			$tmp=dkdecimal($kontosum);
			print "<td align=right><small><small>$font $tmp</small></small></td>";
			print "</tr>";
		}
	}
	if ($primoprint==0) {
		$tmp=dkdecimal($kontosum);
		print "<tr><td></td><td></td><td></td><td>$font <small><small>Primosaldo</small></small></td><td></td><td></td><td></td><td align=right>$font <small><small>$tmp</small></small></td></tr>";
	}
	print "<tr><td colspan=8><hr></td></tr>";
 	print "<tr><td colspan=8><small><small>$font email til: <input type=text name=email[$x] value=$email[$x]> Periode: <input type=text style=\"text-align:right\" size=10 name=fra[$x] value=$fra[$x]> - <input type=text style=\"text-align:right\" size=10 name=til[$x] value=$til[$x]></small></small></td></tr>";
	print "<tr><td colspan=8><hr style=\"height: 10px; background-color: rgb(200, 200, 200);\"></td></tr>";
	print "<tr><td colspan=8><hr></td></tr>";
	print "<input type = hidden name=konto_id[$x] value=$konto_id[$x]>";
}
print "<input type = hidden name=kontoantal value=$kontoantal>";
print "<input type = hidden name=maaned_fra value=$maaned_fra>";
print "<input type = hidden name=maaned_til value=$maaned_til>";
print "<input type = hidden name=regnaar value=$regnaar>";
print "<tr><td colspan=10 align=center><small><small><input type=submit value=\"&nbsp;&nbsp;&nbsp;&nbsp;Opdater&nbsp;&nbsp;&nbsp;&nbsp;\" name=\"submit\">&nbsp;<input type=submit value=\"Send mail(s)\" name=\"submit\"></td>";

print "</form>\n";

function send_mails($kontoantal, $konto_id, $email, $fra, $til) {
	global $db;
	global $brugernavn;
	global $font;
	global $bgcolor;
	global $bgcolor5;

	

	ini_set("include_path", ".:../phpmailer");
	require("class.phpmailer.php");
	
	$tmpmappe="../temp/$db".str_replace(" ","_",$brugernavn);
	mkdir($tmpmappe);

	for($x=1; $x<=$kontoantal; $x++) {
		if (($konto_id[$x])&&($email[$x])&&($fra[$x])&&($til[$x])&&(strpos($email[$x], '@'))) {	
			$fromdate[$x]= usdate($fra[$x]);
			$todate[$x]=usdate($til[$x]);
			$mailtext = "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTMP 4.01 Transitional//EN\">\n";
			$mailtext .= "<html><head><meta content=\"text/html; charset=ISO-8859-1\" http-equiv=\"content-type\"><title></title></head>\n";
		 	$mailtext .= "<body bgcolor=$bgcolor link='#000000' vlink='#000000' alink='#000000' center=''>\n";
			$mailtext .= "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n";
			$mailtext .= "<tr><td colspan=8><hr></td></tr>\n";
			$row = db_fetch_array(db_select("select * from adresser where id=$konto_id[$x]"));
		 	$mailtext .= "<tr><td colspan=3>$font<small><small>$row[firmanavn]</small></small></td></tr>\n";
			$mailtext .= "<tr><td colspan=3>$font <small><small>$row[addr1]</small></small></td></tr>\n";
			$mailtext .= "<tr><td colspan=3>$font <small><small>$row[addr2]</small></small></td><td colspan=3 align=right>$font<small><small>Kontonr</small></small></td><td align=right>$font <small><small>$row[kontonr]</small></small></td></tr>\n";
			$mailtext .= "<tr><td colspan=3>$font <small><small>$row[postnr] $row[bynavn]</small></small></td><td colspan=3 align=right>$font <small><small>Dato</small></small></td><td align=right>$font <small><small>".date('d-m-Y')."</small></small></td></tr>\n";
			$mailtext .= "<tr><td><br></td></tr>\n";
			$mailtext .= "<tr><td><br></td></tr>\n";
			$mailtext .= "<tr><td width=10%>$font <small><small>Dato</td><td width=5%>$font <small><small>Bilag</small></small></td><td width=5%>$font <small><small>Faktura</small></small></td><td width=40% align=center>$font <small><small>Tekst</small></small></td><td>$font <small><small>Forfaldsdato</small></small></td><td width=10% align=right>$font <small><small>Debet</small></small></td><td width=10% align=right>$font <small><small>Kredit</small></small></td><td width=10% align=right>$font <small><small>Saldo</small></small></td></tr>\n";
			$mailtext .= "<tr><td colspan=8><hr></td></tr>\n";
			$betalingsbet=trim($row[betalingsbet]);
			$betalingsdage=$row[betalingsdage];
			$kontosum=0;
			$primo=0;
			$primoprint=0;
			$query = db_select("select * from openpost where konto_id=$konto_id[$x] and transdate<='$todate[$x]' order by transdate, faktnr");
			while ($row = db_fetch_array($query)) {
				 if ($row[transdate]<$fromdate[$x]) {
					$primoprint=0;
					$kontosum=$kontosum+$row[amount];
				}		 
				else { 
					if ($primoprint==0) {
						$tmp=dkdecimal($kontosum);
						$linjebg=$bgcolor5; $color='#000000';
						$mailtext .= "<tr bgcolor=\"$linjebg\"><td></td><td><td></td></td><td align=center>$font <small><small>Primosaldo</small></small></td><td></td><td></td><td></td><td align=right><small><small>$font $tmp</small></small></td></tr>\n";
						$primoprint=1;
					}
				    	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
					elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
					$mailtext .= "<tr bgcolor=\"$linjebg\"><td><small><small>$font ".dkdato($row[transdate])."&nbsp;</small></small></td><td><small><small>$font $row[refnr]&nbsp;</small></small></td><td><small><small>$font $row[faktnr]&nbsp;</small></small></td><td><small><small>$font $row[beskrivelse]</small></small></td>\n";
					if ($row[amount] < 0) {$tmp=0-$row[amount];}
					else {$tmp=$row[amount];}
					$tmp=dkdecimal($tmp);
					$forfaldsdag=usdate(forfaldsdag($row[transdate], $betalingsbet, $betalingsdage));
					if (($row[udlignet]!='1')&&($forfaldsdag<$currentdate)){$stil="<span style='color: rgb(255, 0, 0);'>";}
					else {$stil="<span style='color: rgb(0, 0, 0);'>";}
					if ($row[amount] > 0) {
						$mailtext .= "<td>$font  $stil<small><small>".dkdato($forfaldsdag)."</small></small></td><td align=right>$stil<small><small>$font $tmp</small></small></td><td></td>\n";
						$forfaldsum=$forfaldsum+$row[amount];
					}
					else {$mailtext .= "<td></td><td></td><td align=right>$stil$font<small><small>$tmp</small></small></td>\n";}
			
					$kontosum=$kontosum+$row[amount];
					$tmp=dkdecimal($kontosum);
					$mailtext .= "<td align=right><small><small>$font $tmp</small></small></td>\n";
					$mailtext .= "</tr>\n";
				}
			}
			if ($primoprint==0) {
				$tmp=dkdecimal($kontosum);
				$mailtext .= "<tr><td></td><td></td><td></td><td>$font <small><small>Primosaldo</small></small></td><td></td><td></td><td></td><td align=right>$font <small><small>$tmp</small></small></td></tr>\n";
			}
			$mailtext .= "<tr><td colspan=8><hr></td></tr>\n";
			$mailtext .= "</table></body></html>\n";			

			$fp=fopen("$tmpmappe/kontoudtog.html","w");
			fwrite($fp,$mailtext);
			fclose ($fp);

			$mail = new PHPMailer();

			$mail->IsSMTP();                                   // send via SMTP
			$mail->Host  = "localhost"; // SMTP servers
			$mail->SMTPAuth = false;     // turn on SMTP authentication
			#	$mail->Username = "jswan";  // SMTP username
			#	$mail->Password = "secret"; // SMTP password
			
			$row = db_fetch_array(db_select("select * from adresser where art='S'"));
			$afsendermail=$row['email'];
			$afsendernavn=$row['firmanavn'];

			$mail->From     = $afsendermail;
			$mail->FromName = $afsendernavn;
			$mail->AddAddress($email[$x]); 
#	$mail->AddAddress("ellen@site.com");               // optional name
#	$mail->AddReplyTo("info@site.com","Information");

			$mail->WordWrap = 50;                              // set word wrap
			$mail->AddAttachment("$tmpmappe/kontoudtog.html");      // attachment
#	$mail->AddAttachment("/tmp/image.jpg", "new.jpg"); 
			$mail->IsHTML(true);                               // send as HTML

			$mail->Subject  =  "Kontoudtog fra $afsendernavn";
			$mail->Body     =  "Hermed fremsendes kontoudtog fra $afsendernavn";
			$mail->AltBody  =  "Hermed fremsendes kontoudtog fra $afsendernavn";

			if(!$mail->Send()){
 				 echo "Fejl i afsendelse til $email[$x]<p>";
   				echo "Mailer Error: " . $mail->ErrorInfo;
  		 		exit;
			}
			echo "Kontoudtog sendt til $email[$x]";
		}	
	}
	unlink("$tmpmappe/kontoudtog.html");
	rmdir($tmpmappe);
}
?>

