<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html><head>
<meta content="text/html; charset=UTF-8" http-equiv="content-type"><title>Saldi affiliate</title>

<?php
#print "<html><head><meta content=\"text/html; charset=UTF-8\"><http-equiv=\"content-type\"><title>Saldi affiliate</title></head>";
print "<link rel=\"stylesheet\" href=\"../css/standard.css\" type=\"text/css\" />";
print "<script LANGUAGE=\"JavaScript\"  TYPE=\"text/javascript\" SRC=\"../javascript/overlib.js\"></script>";
#print "<body style=\"background-color: rgb(153, 153, 153); color: rgb(0, 0, 0);\">";
print "</head><body><center>";
print "<table border=\"0\" cellpadding=\"0\" cellspacing=\"0\"><tbody>\n";
#print "<tr><td width=\"15%\" height=\"20%\" bgcolor=\"#003466\"></td><td color=\"#ffffff\" bgcolor=\"#003466\" colspan=\"2\" valign=\"top\">\n";
#print "&nbsp;&nbsp;<a href=http://saldi.dk><img style=\"width: 276px; height: 58px;\" alt=\"logo\" src=\"http://saldi.dk//img/detfriedanske.jpg\"></a><br>\n";
#print "<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href=http://saldi.dk>Forside</a><br></td></tr>\n";
#print "<tr><td width=\"15%\" height=\"80%\" bgcolor=\"#999999\"></td><td width=\"70%\" height=\"80%\" bgcolor=\"#ffffff\" align=\"center\">\n";
#print "<table><tbody>";

$betingelser='';
$fp=fopen("affiliatebetingelser.html","r");
while($linje=fgets($fp)) {
	$betingelser.=$linje;
}
fclose($fp);

if (isset($_GET['opret'])) $opret=$_GET['opret'];
elseif (isset($_POST['opret'])) $opret=$_POST['opret'];
else $opret=NULL;
if (isset($_POST['godkend'])) $godkend=$_POST['godkend'];
else $godkend=NULL;
if (isset($_POST['godkendt'])) $godkendt=$_POST['godkendt'];
else $godkendt=NULL;
if (isset($_GET['login'])) $login=$_GET['login'];
elseif (isset($_POST['login'])) $login=$_POST['login'];
else $login=NULL;
if (isset($_GET['email'])) $email=$_GET['email'];
elseif (isset($_POST['email'])) $email=$_POST['email'];
else $email=NULL;

if (isset($_POST['affiliate_id'])) $affiliate_id=$_POST['affiliate_id'];
else $affiliate_id=NULL;
if (isset($_POST['firmanavn'])) $firmanavn=$_POST['firmanavn'];
else $firmanavn=NULL;
if (isset($_POST['cvrnr'])) $cvrnr=$_POST['cvrnr'];
else $cvrnr=NULL;
if (isset($_POST['telefon'])) $telefon=$_POST['telefon'];
else $telefon=NULL;
if (isset($_POST['navn'])) $navn=$_POST['navn'];
else $navn=NULL;
if (isset($_POST['adresse'])) $adresse=$_POST['adresse'];
else $adresse=NULL;
if (isset($_POST['adresse2'])) $adresse2=$_POST['adresse2'];
else $adresse2=NULL;
if (isset($_POST['postnr'])) $postnr=$_POST['postnr'];
else $postnr=NULL;
if (isset($_POST['bynavn'])) $bynavn=$_POST['bynavn'];
else $bynavn=NULL;
if (isset($_POST['postnr'])) $postnr=$_POST['postnr'];
else $postnr=NULL;
if (isset($_POST['website'])) $website=$_POST['website'];
else $website=NULL;
if (isset($_POST['email2'])) $email2=$_POST['email2'];
else $email2=NULL;
if (isset($_POST['kode'])) $kode=$_POST['kode'];
else $kode=NULL;
if (isset($_POST['kode2'])) $kode2=$_POST['kode2'];
else $kode2=NULL;
if (isset($_POST['gensend'])) $gensend=$_POST['gensend'];
else $gensend=NULL;
if (isset($_POST['accept'])) $accept=$_POST['accept'];
else $accept=NULL;
if ($accept) $accept='checked';

#$email=str_replace('@hotmail.dk','@hotmail.com',$email);
$email=str_replace('@gmail.dk','@gmail.com',$email);
$email=str_replace(',','.',$email);

#$gensend=0;
$fp=fopen('.affiliates.txt','r');
while ($linje=trim(fgets($fp))) {
	list($affiliate_id_,$firmanavn_,$cvrnr_,$telefon_,$navn_,$adresse_,$adresse2_,$postnr_,$bynavn_,$website_,$email_,$kode_,$startdato_)=explode("\t",$linje);
	if ($website && $website==$website_) {
		print "<BODY onLoad=\"javascript:alert('$website er allerede oprettet som affiliate')\">";
		$opret=1;
	} elseif ($login=='Login' && $affiliate_id==$affiliate_id_ && $email==$email_ && $kode==$kode_) {
		$firmanavn=$firmanavn_;
		$cvrnr=$cvrnr_;
		$telefon=$telefon_;
		$navn=$navn_;
		$adresse=$adresse_;
		$adresse2=$adresse2_;
		$postnr=$postnr_;
		$bynavn=$bynavn_;
		$website=$website_;
		$email=$email_;
		($startdato_)?$startdato=$startdato_:$startdato="15.09.2011";
		$login=2;
	}
}
fclose($fp);
if ($login==2) {
	$fp=fopen('http://saldi.dk/referer/referer.csv','r');
	$linkbesog=0;
	$webbesog=0;
	$tmpsite=(str_replace("www.","",$website));
	while ($linje=trim(fgets($fp))) {
		list($ip,$user_agent,$referer,$affiliate)=explode("\t",$linje);
		if ($affiliate_id==$affiliate) {
			$linkbesog++;
		} elseif (strstr($referer,$tmpsite)) {
			$webbesog++;
		}
	}
	fclose($fp);
}
if ($login=='Login' && !$gensend) {
	print "<BODY onLoad=\"javascript:alert('Kombinationen affiliate id, email og adgangskode ikke fundet')\">";
	$login=1;
}

if ($godkend) {
	if (!$telefon) {
		print "<BODY onLoad=\"javascript:alert('Telefon skal udfyldes')\">";
		$opret=1;
	}
	if ($firmanavn && !$cvrnr) {
		print "<BODY onLoad=\"javascript:alert('CVR nr. skal udfyldes')\">";
		$opret=1;
	}
	if (!$firmanavn && !$cvrnr) {
		print "<BODY onLoad=\"javascript:alert('CPR nr. skal udfyldes af hensyn til udbetaling')\">";
		$opret=1;
	}
	if (!$navn) {
		print "<BODY onLoad=\"javascript:alert('Navn skal udfyldes')\">";
		$opret=1;
	}
	if (!$adresse) {
		print "<BODY onLoad=\"javascript:alert('Adresse 1 skal udfyldes')\">";
		$opret=1;
	}
	if (!$postnr) {
		print "<BODY onLoad=\"javascript:alert('Postnr skal udfyldes')\">";
		$opret=1;
	}
	if (!$bynavn) {
		print "<BODY onLoad=\"javascript:alert('Bynavn skal udfyldes')\">";
		$opret=1;
	}
	if (!$email) {
		print "<BODY onLoad=\"javascript:alert('E-mail skal udfyldes')\">";
		$opret=1;
	} elseif (!strpos($email,'@') || !strpos($email,'.')) {
		print "<BODY onLoad=\"javascript:alert('E-mail ikke korrekt')\">";
		$opret=1;
	}
	if ($email!=$email2) {
		print "<BODY onLoad=\"javascript:alert('E-mail er ikke ens')\">";
		$opret=1;
	}
	if (!$kode) {
		print "<BODY onLoad=\"javascript:alert('Adgangskode skal udfyldes')\">";
		$opret=1;
	}
	if ($kode!=$kode2) {
		print "<BODY onLoad=\"javascript:alert('Adgangskoder er ikke ens')\">";
		$opret=1;
	}
	if (strpos($email,'@hotmail.dk')) {
		print "<BODY onLoad=\"javascript:alert('Du har angivet hotmail.dk i din mailadresse - skal det ikke være hotmail.com?')\">";
	}
	if (!$accept) {
		print "<BODY onLoad=\"javascript:alert('Betingelser ikke accepteret')\">";
		$opret=1;
	}
}

if ($login==1) {
	print "<tr><td colspan=\"2\"><b>Login på din affiliate konto</b><br><br></td></tr>\n";
	print "<form name=\"tilmeld affiliate\" action=\"affiliate.php\" method=\"post\">\n"; 
	print "<tr><td>Affiliate ID</td><td><input type=\"text\" style=\"width:300px; text-align:right\" name=\"affiliate_id\" value=\"$affiliate_id\"></td></tr>\n";
	print "<tr><td>Email</td><td><input type=\"text\" style=\"width:300px; align:right\" name=\"email\" value=\"$email\"></td></tr>\n";
	print "<tr><td>Adgangskode</td><td><input type=\"password\" style=\"width:300px; align:right\" name=\"kode\" value=\"$kode\"></td></tr>\n";
	print "<tr><td>Gensend adgangskode og affiliate ID</td><td><input type=\"checkbox\" name=\"gensend\"></td></tr>\n";
	print "<tr><td colspan=\"2\" align=\"center\"><hr><input type=\"submit\" style=\"width:80px;\" name=\"login\" value=\"Login\"></td></tr>\n";
	print "</form>";
} elseif ($login=='2') {
	print "<tr><td colspan=\"2\"><b>Kontoinformation</b><br><br></td></tr>\n";
	print "<tr><td>Affiliate ID</td><td>$affiliate_id</td></tr>\n";
	print "<tr><td>Firmanavn</td><td>$firmanavn</td></tr>\n";
	print "<tr><td>CVR/CPR nr</td><td>$cvrnr</td></tr>\n";
	print "<tr><td>Telefon</td><td>$telefon</td></tr>\n";
	print "<tr><td>Navn</td><td>$navn</td></tr>\n";
	print "<tr><td>Adresse 1</td><td>$adresse</td></tr>\n";
	print "<tr><td>Adresse 2</td><td>$adresse2</td></tr>\n";
	print "<tr><td>Postnr / By</td><td>$postnr $bynavn</td></tr>\n";
	print "<tr><td>Webside</td><td>$website</td></tr>\n";
	print "<tr><td>E-mail</td><td>$email</td></tr>\n";
	print "<tr><td>Startdato</td><td>$startdato</td></tr>\n";
	print "<tr><td colspan=\"2\"><hr></td></tr>\n";
	print "<tr><td>Besøg fra webside</td><td>$webbesog</td></tr>\n";
	print "<tr><td>Besøg via andre links</td><td>$linkbesog</td></tr>\n";
#	print "<tr><td>Oprettede gratisregnskaber </td><td>0</td></tr>\n";
	print "<tr><td>Oprettede gratis regnskaber:</td><td><iframe src=\"https://ssl2.saldi.dk/gratis/opret/find_affiliate.php?website=$tmpsite&affiliate=$affiliate_id&dato=$startdato\" marginheight=\"0\" frameborder=\"0\" height=\"13px\"></iframe></td></tr>";
	print "<tr><td>Oprettede proff: regnskaber:</td><td><iframe src=\"https://ssl.saldi.dk/finans/opret/find_affiliate.php?website=$tmpsite&affiliate=$affiliate_id&dato=$startdato\" marginheight=\"0\" frameborder=\"0\" height=\"13px\"></iframe></td></tr>\n";
	print "<tr><td>Saldo</td><td>0,00</td></tr>\n";

	print "<form name=\"tilmeld affiliate\" action=\"affiliate.php\" method=\"post\">\n"; 
	print "<input type=\"hidden\" name=\"firmanavn\" value=\"$firmanavn\">\n";
	print "<input type=\"hidden\" name=\"cvrnr\" value=\"$cvrnr\">\n";
	print "<input type=\"hidden\" name=\"telefon\" value=\"$telefon\">\n";
	print "<input type=\"hidden\" name=\"navn\" value=\"$navn\">\n";
	print "<input type=\"hidden\" name=\"adresse\" value=\"$adresse\">\n";
	print "<input type=\"hidden\" name=\"adresse2\" value=\"$adresse2\">\n";
	print "<input type=\"hidden\" name=\"postnr\" value=\"$postnr\">";
	print "<input type=\"hidden\" name=\"bynavn\" value=\"$bynavn\">\n";
	print "<input type=\"hidden\" name=\"website\" value=\"$website\">\n";
	print "<input type=\"hidden\" name=\"email\" value=\"$email\">\n";
	print "<input type=\"hidden\" name=\"email2\" value=\"$email2\">\n";
	print "</form>";
} elseif ($opret) {
	print "<tr><td colspan=\"2\">Udfyld nedenstående skema for at blive oprettet som affiliate<br><br></td></tr>\n";
	print "<form name=\"tilmeld affiliate\" action=\"affiliate.php\" method=\"post\">\n"; 
	print "<tr><td>Firmanavn</td><td><input type=\"text\" style=\"width:300px;\" name=\"firmanavn\" value=\"$firmanavn\"></td></tr>\n";
	print "<tr><td>CVR/CPR nr *</td><td><input type=\"text\" style=\"width:300px;\" name=\"cvrnr\" value=\"$cvrnr\"></td></tr>\n";
	print "<tr><td title=\"Dit telefonnummer\">Telefon *</td><td><input type=\"text\" style=\"width:300px;\" name=\"telefon\" value=\"$telefon\"></td></tr>\n";
	print "<tr><td>Navn *</td><td><input type=\"text\" style=\"width:300px;\" name=\"navn\" value=\"$navn\"></td></tr>\n";
	print "<tr><td>Adresse 1 *</td><td><input type=\"text\" style=\"width:300px;\" name=\"adresse\" value=\"$adresse\"></td></tr>\n";
	print "<tr><td>Adresse 2</td><td><input type=\"text\" style=\"width:300px;\" name=\"adresse2\" value=\"$adresse2\"></td></tr>\n";
	print "<tr><td>Postnr / By *</td><td><input type=\"text\" style=\"width:50px;\" name=\"postnr\" value=\"$postnr\"><input type=\"text\" style=\"width:250px;\" name=\"bynavn\" value=\"$bynavn\"></td></tr>\n";
	print "<tr><td>Webside</td><td><input type=\"text\" style=\"width:300px;\" name=\"website\" value=\"$website\"></td></tr>\n";
	print "<tr><td>E-mail *</td><td><input type=\"text\" style=\"width:300px;\" name=\"email\" value=\"$email\"></td></tr>\n";
	print "<tr><td>Gentag e-mail *</td><td><input type=\"text\" style=\"width:300px;\" name=\"email2\" value=\"$email2\"></td></tr>\n";
	print "<tr><td>Ønsket adgangskode *</td><td><input type=\"password\" style=\"width:300px;\" name=\"kode\" value=\"$kode\"></td></tr>\n";
	print "<tr><td>Gentag adgangskode *</td><td><input type=\"password\" style=\"width:300px;\" name=\"kode2\" value=\"$kode2\"></td></tr>\n";
	print "<tr><td><span onMouseOver=\"this.style.cursor = 'pointer'\" onclick=\"javascript:window.open('affiliatebetingelser.html', '', ',statusbar=0,menubar=0,titlebar=0,toolbar=0,scrollbars=1,resizable=1,top=0,left=0,width=800,height=400');\"><u>Accepter betingelser</u></span> *</td><td><input type=\"checkbox\" name=\"accept\" $accept></td></tr>\n";
#	print "<input type=\"hidden\" name=\"godkend\" value='OK'>\n";
	print "<tr><td colspan=\"2\" align=\"center\"><hr><input type=\"submit\" style=\"width:80px;\" name=\"godkend\" value=\"Send\"></td></tr>\n";
	print "</form>";
} 
elseif ($godkend) {
	print "<tr><td colspan=\"2\" align=\"center\">Bekræft venligst at nedenstående oplysninger er korrekte<br><br></td></tr>\n";
	print "<tr><td>Firmanavn</td><td>$firmanavn</td></tr>\n";
	print "<tr><td>CVR nr</td><td>$cvrnr</td></tr>\n";
	print "<tr><td title=\"Dit telefonnummer\">Telefon *</td><td>$telefon</td></tr>\n";
	print "<tr><td>Navn *</td><td>$navn</td></tr>\n";
	print "<tr><td>Adresse 1 *</td><td>$adresse</td></tr>\n";
	print "<tr><td>Adresse 2</td><td>$adresse2</td></tr>\n";
	print "<tr><td>Postnr / By *</td><td>$postnr $bynavn</td></tr>\n";
	print "<tr><td>Webside</td><td>$website</td></tr>\n";
	print "<tr><td>E-mail *</td><td>$email</td></tr>\n";
	print "<form name=\"tilmeld affiliate\" action=\"affiliate.php\" method=\"post\">\n"; 
	print "<input type=\"hidden\" name=\"firmanavn\" value=\"$firmanavn\">\n";
	print "<input type=\"hidden\" name=\"cvrnr\" value=\"$cvrnr\">\n";
	print "<input type=\"hidden\" name=\"telefon\" value=\"$telefon\">\n";
	print "<input type=\"hidden\" name=\"navn\" value=\"$navn\">\n";
	print "<input type=\"hidden\" name=\"adresse\" value=\"$adresse\">\n";
	print "<input type=\"hidden\" name=\"adresse2\" value=\"$adresse2\">\n";
	print "<input type=\"hidden\" name=\"postnr\" value=\"$postnr\">";
	print "<input type=\"hidden\" name=\"bynavn\" value=\"$bynavn\">\n";
	print "<input type=\"hidden\" name=\"website\" value=\"$website\">\n";
	print "<input type=\"hidden\" name=\"email\" value=\"$email\">\n";
	print "<input type=\"hidden\" name=\"email2\" value=\"$email2\">\n";
	print "<input type=\"hidden\" name=\"kode\" value=\"$kode\">\n";
	print "<input type=\"hidden\" name=\"kode2\" value=\"$kode2\">\n";
	print "<tr><td colspan=\"2\" align=\"center\"><hr><input type=\"submit\" style=\"width:80px;\" name=\"opret\" value=\"Ret\"><input type=\"submit\" style=\"width:80px;\" name=\"godkendt\" value=\"Godkend\"></td></tr>\n";
	print "<tr><td colspan=\"2\"><hr></td></tr>\n";
	print "</form>\n";
	if ($website) {
		print "<tr><td colspan=\"2\" align=\"center\"><small>Når du godkender bekræfter du at du har rettighederne til $website og</small></td></tr>\n";
		print "<tr><td colspan=\"2\" align=\"center\"><small>accepterer at modtage op til 12 årlige mails som omhandler nyheder i Saldi.</small></td></tr>\n";
		print "<tr><td colspan=\"2\" align=\"center\"><small>Du kan til enhver tid framelde nyhedsmailen</small></td></tr>\n";
	}	else {
		print "<tr><td colspan=\"2\" align=\"center\"><small>Når du godkender bekræfter du samtidig at modtage op til 12 årlige mails</small></td></tr>\n";
		print "<tr><td colspan=\"2\" align=\"center\"><small>Når du godkender accepterer du samtidig at modtage op til 12 årlige mails</small></td></tr>\n";
		print "<tr><td colspan=\"2\" align=\"center\"><small>som omhandler nyheder i Saldi. Du kan til enhver tid framelde nyhedsmailen</small></td></tr>\n";
	}
} elseif ($godkendt || $gensend) {
	$oprettet=0;
	$fp=fopen('.affiliates.txt','r');
	while ($linje=fgets($fp)) {
		list($affiliate_id_,$firmanavn_,$cvrnr_,$telefon_,$navn_,$adresse_,$adresse2_,$postnr_,$bynavn_,$website_,$email_,$kode_,$startdato_)=explode("\t",$linje);
		if ($gensend) {
			if ($email && $email==$email_) {
				list($affiliate_id,$firmanavn,$cvrnr,$telefon,$navn,$adresse,$adresse2,$postnr,$bynavn,$website,$email_,$kode,$startdato)=explode("\t",$linje);
				$oprettet=1;
			}
		} elseif ($website && $website==$website_) {
			$oprettet=1;
			$affiliate_id=$affiliate_id_*1;
		}
	}
	fclose($fp);
	if (!$oprettet) {
		$startdato=date("d.m.Y");
		$affiliate_id=$affiliate_id_*1;
		$affiliate_id++;
		$fp=fopen('.affiliates.txt','a');
		fwrite($fp,"$affiliate_id\t$firmanavn\t$cvrnr\t$telefon\t$navn\t$adresse\t$adresse2\t$postnr\t$bynavn\t$website\t$email\t$kode\t$startdato\n");
		fclose($fp);
	}
	if (!$gensend) print "<tr><td colspan=\"2\">Tak for din tilmelding - Dit affiliate id er $affiliate_id.</td></tr>\n";
	print "<tr><td colspan=\"2\">Der er sendt en bekr&aelig;ftelsesmail til $email</td></tr>\n";
	print "<tr><td colspan=\"2\">Hvis du ikke modtager noget så tjek din spam mappe.</td></tr>\n";
	print "<tr><td colspan=\"2\" align=\"center\"><a href=\"$_PHP_SELF?login=1&email=$email\">Gå til login</a></td></tr>\n";

	$message="Tak for din tilmelding som Saldi affiliate\r\n\r\n";
	$message.="Dit affiliate ID er $affiliate_id\r\n";
	
	$message.="Firmanavn = $firmanavn\r\n";
	if ($firmanavn) $message.="CVR nr = $cvrnr\r\n";
	else $message.="CPR nr = $cvrnr\r\n";
	$message.="Telefon = $telefon\r\n";
	$message.="Navn = $navn\r\n";
	$message.="Adresse = $adresse\r\n";
	$message.="Adresse2 = $adresse2\r\n";
	$message.="Postnr / By = $postnr";
	$message.=" $bynavn\r\n";
	$message.="Website = $website\r\n";
	$message.="Email = $email\r\n";
	$message.="Adgangskode = $kode\r\n";

	if ($website) {
		$message.="\r\n\r\nFra i dag vil alle besøg på saldi.dk som kommer ved klik på et link på $website\r\n";
		$message.="eller ved klik på link til: http://saldi.dk?id=$affiliate_id fra et andet site\r\n";
	} else {
		$message.="Fra dd. vil alle besøg på saldi.dk som kommer ved klik på et link til http://saldi.dk?id=$affiliate_id\r\n";
	}
	$message.="blive registreret på din konto. Hvis besøget fører til at det oprettes et Saldi abomnnement vil 50%\r\n";
	$message.="af fakturabeløbet for den 1. faktura blive tilskrevet din konto.\r\n";
	$message.=" Tilskrivningen sker løbende i løbet af den 1. abonnementsperiode indtil perioden udløber eller abonnementet opsiges\r\n";
	if ($website) {
		$message.="Hvis der kommer besøg fra $website med andet id end $affiliate_id vil tilskrivningen ske til ejeren af det pågældende id\r\n";
	}
	$message.="\r\nPå saldi.dk/referer/affiliate.php kan du logge ind og følge med i hvad der er tilskrevet på din konto.\r\n";

	$message=utf8_decode($message);

#	$message.="\r\n\r\n".$message2;

	$headers='From: mailserver@saldi.dk' . "\r\n";
	$headers.='Bcc: affiliate@saldi.dk' . "\r\n";
  $headers.='Reply-To: saldi@saldi.dk' . "\r\n";
	$headers.='Content-type: text; charset=iso-8859-1' . "\r\n";
  $headers.='X-Mailer: PHP/' . phpversion(). "\r\n";

	$to = "$email";
#	$to = "peter@rude.net";
	$subject = "Tilmelding til Saldi affiliate program";
#	$subject=utf8_encode($subject);

	mail ($to, $subject, $message, $headers) or print " Der er sket en fejl ved afsendelsen, pr&oslash;v venligst igen\n";
} else { 
#<big><b>Bliv Affiliate.</b></big><br>
#<br><br><br><br><br>
print "<tr><td>
At være affiliate betyder at du hjælper med at udbrede kendskabet til Saldi og at vi belønner<br>
dig for de nye kunder det afstedkommer.<br>
<br>
Når du er tilmeldt vores affiliate program registerer vi din webside og du får et personligt id.<br>
Vi kan herefter registrere hvilke nye kunder som har fundet frem til saldi.dk ved at klikke på<br>
et link på din hjemmeside eller på et link i et indlæg du har skrevet andetsteds på internettet.<br>
<br>
Når en ny bruger herefter opretter sig&nbsp; vil du opnå en provision på 50% at kundens 1. betaling.<br>
(Som regel kr. 300,- i provision pr. oprettelse)<br>
<br>
Du vil altid kunne logge ind med dit id og din adgangskode, så du kan følge med i hvor mange der<br>
har oprettet sig efter en henvisning fra din side eller et indlæg du har skrevet.<br><br><br><br>
</td></tr>
<tr><td align=\"center\"><a href=affiliate.php?opret=1><b><big>Klik her for at blive oprettet som affiliate.</big></b></a></td></tr>
<tr><td align=\"center\"><a href=affiliate.php?login=1><b><big>Klik her for at tjekke din affiliate konto.</big></b></a></td></tr>";
} 
#</tbody></table>
#</td><td width="15%" height="80%" bgcolor="#999999"></td></tr>
?>
</tbody></table>;
</center></body></html>