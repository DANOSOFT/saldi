<?php

ini_set('display_errors','On');
include ("saldi_connect.php");
include("soapfunc.php");
list($fejl,$s_id)=explode(chr(9),logon($regnskab,$brugernavn,$adgangskode));
if ($fejl) {
	echo "Fejl $s_id<br>";
	$subject="Fejl i arrangement $id<br>";
	$headers='From: mailserver@webvisor.dk' . "\r\n";
#	$headers.='Reply-To: '.$klubmail."\r\n";
	$headers.='Content-type: text; charset=iso-8859-1' . "\r\n";
	$headers.='X-Mailer: PHP/' . phpversion(). "\r\n";
	exit;
} else {
	echo "Login OK $s_id<br>";
}
echo "Logger af<br>";
logoff($s_id);
echo "slut<br>";
?>
 
