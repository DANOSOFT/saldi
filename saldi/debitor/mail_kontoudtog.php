<?php
@session_start();
$s_id=session_id();
// ------debitor/mail_kontoudtog.php-------lap 3.5.3------2015-03-05--------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2012.09.06 break ændret til break 1
// 2012.10.04 Gmail afviser mails hvor 'from' ikke er *.saldi.dk søg 20121029
// 2013.10.04 Tilføjet AddReplyTo. Søg AddReplyTo
// 2014.01.28 Ændret from til korrekt afsendermail. Søg 20140128 
// 2015.03.05 Finder selv sidste dato hvor saldi var 0. 20150305-1
// 2015.03.05 Kan nu håndtere flere mailadresser adskilt med ;  20150305-2
// 2015.05.05 Hvis der er forvalgte datoer overrules 20150305-1

$modulnr=12;
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/forfaldsdag.php");

if ($_POST['submit']) {
 	$submit=strtolower(trim($_POST['submit']));
	$kontoantal=$_POST['kontoantal'];
	$dato_fra=$_POST['dato_fra'];
	$dato_til=$_POST['dato_til'];
#	$regnaar=$_POST['regnaar'];
	$konto_id=$_POST['konto_id'];
	$email=$_POST['email'];
	$fra=$_POST['fra'];
	$til=$_POST['til'];
}
else {
	$kontoliste=$_GET['kontoliste'];
	$kontoantal=$_GET['kontoantal'];
	($_GET['dato_fra'])?$dato_fra=$_GET['dato_fra']:$dato_fra=NULL; #20150505
	($_GET['dato_til'])?$dato_til=$_GET['dato_til']:$dato_til=NULL; #20150505
#	$dato_til=$_GET['dato_til'];
	for($x=1;$x<=$kontoantal;$x++) { #20150505
		($dato_fra)?$fra[$x]=dkdato(usdate($dato_fra)):$fra[$x]=NULL;
		($dato_til)?$til[$x]=dkdato(usdate($dato_til)):$til[$x]=NULL;
	}
}

if ($submit=="send mail(s)"){
	send_mails($kontoantal, $konto_id, $email, $fra, $til);
	print "<form name=luk action=../includes/luk.php method=post>";	
	print "<div style=\"text-align: center;\"><br><br><input type=submit value=\"Luk\" name=\"submit\">";
	print "</form></div>";
	exit;	
#	print "<body onload=\"javascript:opener.focus();window.close();\">";
}
/*
$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$startmaaned=$row['box1']*1;
$startaar=$row['box2']*1;
$slutmaaned=$row['box3']*1;
$slutaar=$row['box4']*1;
$slutdato=31;
*/
#if ($dato_fra) {$startmaaned=$dato_fra;}
#if ($dato_til) {$slutmaaned=$dato_til;}

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}
	
while (!checkdate($slutmaaned,$slutdato,$slutaar))	{
	$slutdato=$slutdato-1;
	if ($slutdato<28) break 1;
}

$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;


print "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
/*
for ($x=1; $x<=12; $x++) {
	if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
	if ($maaned_til==$md[$x]){$maaned_til=$x;}
	if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
	if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
}

$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$startmaaned=$row['box1']*1;
$startaar=$row['box2']*1;
$slutmaaned=$row['box3']*1;
$slutaar=$row['box4']*1;
$slutdato=31;
*/
$currentdate=date("Y-m-d");
/*
if ($maaned_fra) {$startmaaned=$maaned_fra;}
if ($maaned_til) {$slutmaaned=$maaned_til;}

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}
	
while (!checkdate($slutmaaned,$slutdato,$slutaar))	{
	$slutdato=$slutdato-1;
	if ($slutdato<28) break 1;
}

$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;
*/
print "<form name=kontoudtog action=mail_kontoudtog.php method=post>";

#echo "$kontoantal<br>";
for($x=1; $x<=$kontoantal; $x++) {
	if ($kontoliste) {list($konto_id[$x], $kontoliste)=explode(";", $kontoliste, 2);}
	
#	if (!$fra[$x]) $fra[$x]=$dato_fra;
	if ($fra[$x]) $fromdate[$x]=usdate($fra[$x]);
#	$fra[$x]=dkdato($fromdate[$x]);
#	else $fromdate[$x]= $dato_fra;
	if (!$til[$x]) $til[$x]=$dato_til; 
	$todate[$x]=usdate($til[$x]);
	$til[$x]=dkdato($todate[$x]);
#	else {$todate[$x]= $dato_fra;}
/*	
	if ( $regnaar ) {
		$fra[$x]=dkdato($fromdate[$x]);
	} else { 
		# startdato i foerste regnskabsaar
		$query = db_select("select * from grupper where art='RA' order by box2, box1",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$startmaaned=$row['box1']*1;
		$startaar=$row['box2']*1;
		$fra[$x]=dkdato($startaar. "-" . $startmaaned . "-01");
	}
*/
	$til[$x]=dkdato($todate[$x]);

#echo 	"select * from adresser where id=$konto_id[$x]<br>";
	$query = db_select("select * from adresser where id=$konto_id[$x]",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	if (!$email[$x]){$email[$x]=$row['email'];}
#echo "E $email[$x]<br>";
	print "<tr><td colspan=8><hr style=\"height: 10px; background-color: rgb(200, 200, 200);\"></td></tr>";
	print "<tr><td colspan=\"5\">$row[firmanavn]</td><td colspan=\"2\" align=\"right\">Dato</td><td align=right> ".date('d-m-Y')."</td></tr>\n";
	print "<tr><td colspan=\"5\">$row[addr1]</td><td colspan=\"2\" align=\"right\">Kontonr.</td><td align=right> $row[kontonr]</td></tr>\n";
	if ( $row['addr2'] ) print "<tr><td colspan=\"8\">$row[addr2]</td></tr>\n";
	print "<tr><td colspan=\"8\">$row[postnr] $row[bynavn]</td></tr>\n";
	print "<tr><td colspan=\"8\">Tlf. $row[tlf]</td></tr>\n";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td width=10%> Dato</td><td width=5%> Bilag</td><td width=5%> Faktura</td><td width=40% align=center> Tekst</td><td> Forfaldsdato</td><td width=10% align=right> Debet</td><td width=10% align=right> Kredit</td><td width=10% align=right> Saldo</td></tr>";
	print "<tr><td colspan=8><hr></td></tr>";
	$betalingsbet=trim($row['betalingsbet']);
	$betalingsdage=$row['betalingsdage'];
	$kontosum=0;
	$primo=0;
	$primoprint=0;
	if (!$fromdate[$x]) { #20150305-1
		$y=0;
		$q=db_select("select * from openpost where konto_id=$konto_id[$x] and transdate<='$todate[$x]' order by transdate, faktnr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$td[$y]=$r['transdate'];
			($y)?$am[$y]=$am[$y-1]+afrund($r['amount'],2):$am[$y]=afrund($r['amount'],2);
			$y++;
		}
		for ($y=0;$y<count($td);$y++) {
			if (!$y) $fromdate[$x]=$td[$y];
			if ($am[$y]==0 && $y<count($td)-1) $fromdate[$x]=$td[$y+1]; 
			$fra[$x]=dkdato($fromdate[$x]);
		}
	}
	if (!$fromdate[$x]) {
		$fromdate[$x]=usdate($fra[$x]);
	}
	
	$query = db_select("select * from openpost where konto_id=$konto_id[$x] and transdate<='$todate[$x]' order by transdate, faktnr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$amount=afrund($row['amount'],2);
		$forfaldsdag=NULL;
		$forfaldsdate=NULL;
		if ($row['forfaldsdate']) {
			$forfaldsdate=$row['forfaldsdate'];
			$forfaldsdag=dkdato($forfaldsdate);
		}
	 if ($row['transdate']<$fromdate[$x]) {
			$primoprint=0;
			$kontosum=$kontosum+$amount;
		}		 
		else { 
			if ($primoprint==0) {
				$tmp=dkdecimal($kontosum);
				$linjebg=$bgcolor5; $color='#000000';
				print "<tr bgcolor=\"$linjebg\"><td colspan=\"3\"></td><td> Primosaldo</td><td colspan=\"3\"></td><td align=right> $tmp</td></tr>";
				$primoprint=1;
			}
		    	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\"><td align=left> ".dkdato($row['transdate'])."&nbsp;</td><td> $row[refnr]</td><td align=right> $row[faktnr]&nbsp;</td><td> $row[beskrivelse]</td>";
			
			if ($amount < 0) $tmp=0-$amount;
			else $tmp=$amount;
			$tmp=dkdecimal($tmp);
			if (!$forfaldsdag && $amount > 0) {
				$forfaldsdag=forfaldsdag($row['transdate'], $betalingsbet, $betalingsdage);
				$forfaldsdate=usdate($forfaldsdag);
			}
			if (($row['udlignet']!='1')&&($forfaldsdate<$currentdate)) $stil="<span style='color: rgb(255, 0, 0);'>";
			else $stil="<span style='color: rgb(0, 0, 0);'>";
			if ($amount > 0) {
				print "<td>$stil"."$forfaldsdag</td><td align=right>$stil $tmp</td><td></td>";
				$forfaldsum=$forfaldsum+$amount;
			}
			else {print "<td></td><td></td><td align=right> $tmp</td>";}
			
			$kontosum=$kontosum+$amount;
			$tmp=dkdecimal($kontosum);
			print "<td align=right> $tmp</td>";
			print "</tr>";
		}
	}
	if ($primoprint==0) {
		$tmp=dkdecimal($kontosum);
		print "<tr><td></td><td></td><td></td><td> Primosaldo</td><td></td><td></td><td></td><td align=right> $tmp</td></tr>";
	}
	print "<tr><td colspan=8><hr></td></tr>";
 	print "<tr><td colspan=8> email til: <input type=text name=email[$x] value=$email[$x]> Periode: <input type=text style=\"text-align:right\" size=10 name=fra[$x] value=$fra[$x]> - <input type=text style=\"text-align:right\" size=10 name=til[$x] value=$til[$x]></td></tr>";
	print "<tr><td colspan=8><hr style=\"height: 10px; background-color: rgb(200, 200, 200);\"></td></tr>";
	print "<tr><td colspan=8><hr></td></tr>";
	print "<input type = hidden name=konto_id[$x] value=$konto_id[$x]>";
}
print "<input type = hidden name=kontoantal value=$kontoantal>";
print "<input type = hidden name=dato_fra value=$dato_fra>";
print "<input type = hidden name=dato_til value=$dato_til>";
#print "<input type = hidden name=regnaar value=$regnaar>";
print "<tr><td colspan=10 align=center><input type=submit value=\"&nbsp;&nbsp;&nbsp;&nbsp;Opdat&eacute;r&nbsp;&nbsp;&nbsp;&nbsp;\" name=\"submit\">&nbsp;<input type=submit value=\"Send mail(s)\" name=\"submit\"></td>";

print "</form>\n";

function send_mails($kontoantal, $konto_id, $email, $fra, $til) {
	global $db;
	global $brugernavn;
	global $bgcolor;
	global $bgcolor5;
	global $charset;
	
	ini_set("include_path", ".:../phpmailer");
	require("class.phpmailer.php");
	
	$tmpmappe="../temp/$db/".str_replace(" ","_",$brugernavn);
	mkdir($tmpmappe);

	for($x=1; $x<=$kontoantal; $x++) {
		mkdir("$tmpmappe/$x");
		if (($konto_id[$x])&&($email[$x])&&($fra[$x])&&($til[$x])&&(strpos($email[$x], '@'))) {	
			$fromdate[$x]= usdate($fra[$x]);
			$todate[$x]=usdate($til[$x]);
			$mailtext = "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTMP 4.01 Transitional//EN\">\n";
			$mailtext .= "<html><head><meta content=\"text/html; charset=ISO-8859-15\" http-equiv=\"content-type\">\n";
			$row = db_fetch_array(db_select("select firmanavn from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
			$mailtext .= "<title>Kontoudtog fra $row[firmanavn]</title></head>\n";
		 	$mailtext .= "<body bgcolor=$bgcolor link='#000000' vlink='#000000' alink='#000000' center=''>\n";
			$mailtext .= "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n";
		 	$mailtext .= "<tr><td colspan=\"5\"><b>$row[firmanavn]</b></td><td colspan=\"2\" align=\"right\">Dato</td><td align=right> ".date('d-m-Y')."</td></tr>\n";
			$mailtext .= "<tr><td colspan=8><hr></td></tr>\n";
			$mailtext .= "<tr><td colspan=\"8\" style=\"font-size:115%;font-weight:bold\">Kontoudtog</td></tr>\n";
			$row = db_fetch_array(db_select("select * from adresser where id=$konto_id[$x]",__FILE__ . " linje " . __LINE__));
		 	$mailtext .= "<tr><td colspan=\"5\">$row[firmanavn]</td><td colspan=\"2\" align=\"right\">Kontonr.</td><td align=right> $row[kontonr]</td></tr>\n";
			$mailtext .= "<tr><td colspan=\"8\">$row[addr1]</td></tr>\n";
			if ( $row[addr2] ) $mailtext .= "<tr><td colspan=\"8\">$row[addr2]</td></tr>\n";
			$mailtext .= "<tr><td colspan=\"8\">$row[postnr] $row[bynavn]</td></tr>\n";
			$mailtext .= "<tr><td><br></td></tr>\n";
			$mailtext .= "<tr><td width=\"10%\"> Dato</td><td width=\"5%\"> Bilag</td><td width=\"5%\"> Faktura</td><td width=\"40%\" align=\"center\"> Tekst</td><td> Forfaldsdato</td><td width=\"10%\" align=\"right\"> Debet</td><td width=\"10%\" align=\"right\"> Kredit</td><td width=\"10%\" align=\"right\"> Saldo</td></tr>\n";
			$mailtext .= "<tr><td colspan=8><hr></td></tr>\n";
			$betalingsbet=trim($row['betalingsbet']);
			$betalingsdage=$row['betalingsdage'];
			$kontosum=0;
			$primo=0;
			$primoprint=0;
			$query = db_select("select * from openpost where konto_id=$konto_id[$x] and transdate<='$todate[$x]' order by transdate, faktnr",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
				$amount=afrund($row['amount'],2);
				$forfaldsdag=NULL;
				if ($row['forfaldsdate']) $forfaldsdag=dkdato($row['forfaldsdate']);
				if ($row['transdate']<$fromdate[$x]) {
					$primoprint=0;
					$kontosum=$kontosum+$amount;
				}		 
				else { 
					if ($primoprint==0) {
						$tmp=dkdecimal($kontosum);
						$linjebg=$bgcolor5; $color='#000000';
						$mailtext .= "<tr bgcolor=\"$linjebg\"><td colspan=\"3\"></td><td>Primosaldo</td><td colspan=\"3\"></td><td align=right> $tmp</td></tr>\n";
						$primoprint=1;
					}
				    	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
					elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
					$mailtext .= "<tr bgcolor=\"$linjebg\"><td> ".dkdato($row['transdate'])."&nbsp;</td><td> $row[refnr]&nbsp;</td><td> $row[faktnr]&nbsp;</td><td> $row[beskrivelse]</td>\n";
					if ($amount < 0) $tmp=0-$amount;
					else $tmp=$amount;
					$tmp=dkdecimal($tmp);
					if (!$forfaldsdag) $forfaldsdag=forfaldsdag($row['transdate'], $betalingsbet, $betalingsdage);
					if (($row[udlignet]!='1')&&($forfaldsdag<$currentdate)) $stil="<span style='color: rgb(255, 0, 0);'>";
					else {$stil="<span style='color: rgb(0, 0, 0);'>";}
					if ($amount > 0) {
						$mailtext .= "<td>$stil$forfaldsdag</td><td align=right>$stil $tmp</td><td></td>\n";
						$forfaldsum=$forfaldsum+$amount;
					}
					else $mailtext .= "<td></td><td></td><td align=right>$stil$tmp</td>\n";
			
					$kontosum=$kontosum+$amount;
					$tmp=dkdecimal($kontosum);
					$mailtext .= "<td align=right> $tmp</td>\n";
					$mailtext .= "</tr>\n";
				}
			}
			if ($primoprint==0) {
				$tmp=dkdecimal($kontosum);
				$mailtext .= "<tr><td></td><td></td><td></td><td> Primosaldo</td><td></td><td></td><td></td><td align=right> $tmp</td></tr>\n";
			}
			$mailtext .= "<tr><td colspan=\"8\"><hr></td></tr>\n";
			$row = db_fetch_array(db_select("select * from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
			$mailtext .= "<tr><td colspan=\"8\">\n";
			if ( $row['bank_konto'] ) { 
				$mailtext .= "<p>Et eventuelt udest&aring;ende bedes indbetalt hurtigst muligt p&aring; vores bankkonto med\n";
				if ( $row['bank_reg'] ) $mailtext .= " reg.nr. ".$row['bank_reg']." og";
				$mailtext .= " kontonr. ".$row['bank_konto'];
				if ( $row['bank_navn'] )  $mailtext .= " i ".$row['bank_navn'];
				$mailtext .= ".</p>\n";
			}
			if ( $row['tlf'] ) {
				$mailtext .= "<p>Hvis du har sp&oslash;rgsm&aring;l, s&aring; kontakt os p&aring; telefon ".$row['tlf'];
				$mailtext .= ".</p>\n</td></tr>\n";
			}
			$mailtext .= "<tr><td colspan=\"8\"><hr></td></tr>\n";
			$mailtext .= "<tr><td colspan=\"8\" align=\"center\">\n";
			$mailtext .= "<p style=\"font-size:80%\">".$row['firmanavn'];
			if ( $row['addr1'] ) $mailtext .= " * ".$row['addr1'];
			if ( $row['addr2'] ) $mailtext .= " * ".$row['addr2'];
			if ( $row['postnr'] ) $mailtext .= " * ".$row['postnr']." ".$row['bynavn'];
			if ( $row['tlf'] ) $mailtext .= " * tlf ".$row['tlf'];
			if ( $row['fax'] ) $mailtext .= " * fax ".$row['fax'];
			if ( $row['cvr'] ) $mailtext .= " * cvr ".$row['fax'];
			$mailtext .= "<p>\n</td></tr>\n";
			$mailtext .= "</table></body></html>\n";			
			
#echo "select * from adresser where art='S'<br>";
			$row = db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
			$afsendermail=$row['email'];
			$afsendernavn=$row['firmanavn'];

#echo "AFSM $afsendermail<br>";
			
			if ($charset=="UTF-8") {
				$subjekt=utf8_decode($subjekt);
				$mailtext=utf8_decode($mailtext);
				$afsendernavn=utf8_decode($afsendernavn);
				$afsendermail=utf8_decode($afsendermail);
#echo "MM $mailtext<br>";
			}

			$fp=fopen("$tmpmappe/$x/kontoudtog.html","w");
			fwrite($fp,$mailtext);
			fclose ($fp);

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
			if (strpos($email[$x],";")) { #20150305-2
				$tmp=array();
				$tmp=explode(";",$email[$x]);
				for ($i=0;$i<count($tmp);$i++){
					if (strpos($tmp[$i],"@")) $mail->AddAddress($tmp[$i]); 
				}
			} else $mail->AddAddress($email[$x]); 
			$mail->AddBCC($afsendermail); 
			$mail->AddReplyTo($afsendermail,$afsendernavn);

			$mail->WordWrap = 50;                              // set word wrap
			$mail->AddAttachment("$tmpmappe/$x/kontoudtog.html");      // attachment
			$mail->IsHTML(true);                               // send as HTML

			$mail->Subject  =  "Kontoudtog fra $afsendernavn";
			
			$mailbody = "<html><body>\n";
                        $mailbody .= "<p>Hermed fremsendes kontoudtog fra ".$afsendernavn.".</p>\n";
                        $mailbody .= "<p>Den vedlagte fil er en HTML-fil og kan ses i din webbrowser eksempelvis \n";
			$mailbody .= "ved at dobbeltklikke p&aring; den.</p>\n";
			$mailbody .= "<hr />\n<p>";
			$mailbody .= $row['firmanavn']."<br />\n";
			if ( $row['addr1'] ) $mailbody .= $row['addr1']."<br />\n";
			if ( $row['addr2'] ) $mailbody .= $row['addr2']."<br />\n";
			if ( $row['postnr'] ) $mailbody .= $row['postnr']." ".$row['bynavn']."<br />\n";
			if ( $row['tlf'] ) $mailbody .= "tlf ".$row['tlf'];
			if ( $row['fax'] ) $mailbody .= " * fax ".$row['fax'];
			if ( $row['cvr'] ) $mailbody .= " * cvr ".$row['fax'];
			$mailbody .= "</p></body></html>";

			$mailaltbody = "Hermed fremsendes kontoudtog fra ".$afsendernavn.".\n\n";
                        $mailaltbody .= "Den vedlagte fil er en HTML-fil og kan ses i din webbrowser eksempelvis \n";
			$mailaltbody .= "ved at dobbeltklikke på den.\n";
			$mailaltbody .= "-- \n";
			$mailaltbody .= $row['firmanavn']."\n";
			if ( $row['addr1'] ) $mailaltbody .= $row['addr1']."\n";
			if ( $row['addr2'] ) $mailaltbody .= $row['addr2']."\n";
			if ( $row['postnr'] ) $mailaltbody .= $row['postnr']." ".$row['bynavn']."\n";
			if ( $row['tlf'] ) $mailaltbody .= "tlf ".$row['tlf'];
			if ( $row['fax'] ) $mailaltbody .= " * fax ".$row['fax'];
			if ( $row['cvr'] ) $mailaltbody .= " * cvr ".$row['fax'];
			
			if ($charset=="UTF-8"){
				$mailbody=utf8_decode($mailbody);
				$mailaltbody=utf8_decode($mailaltbody);
			}


			$mail->Body     =  $mailbody;
			$mail->AltBody  =  $mailaltbody;

			if(!$mail->Send()){
 				 echo "Fejl i afsendelse til $email[$x]<p>";
   				echo "Mailer Error: " . $mail->ErrorInfo;
  		 		exit;
			}
			echo "Kontoudtog sendt til $email[$x]<br>";
#			sleep(2);
		}	
	}
	for($x=1; $x<=$kontoantal; $x++) {
		unlink("$tmpmappe/$x/kontoudtog.html");
		rmdir("$tmpmappe/$x");
	}
	#	unlink("$tmpmappe/kontoudtog.html");
	rmdir($tmpmappe);
}
?>

