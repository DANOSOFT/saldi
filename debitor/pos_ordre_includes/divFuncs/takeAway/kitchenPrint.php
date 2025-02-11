<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------ debitor/pos_print/pos_print.php -- lap 3.7.5 -- 2019-03-19 --
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2019 saldi.dk aps
// --------------------------------------------------------------------------
// 2019.26.07 LN Make receipt print for the kitchen


global $FromCharset,$ToCharset;

if (isset($_SESSION['takeAwayOrderId'])) {
		$txt = "   Dette er en ændring af bestilling nr: " . $_SESSION['takeAwayOrderId'];
		$txt = iconv('UTF-8', 'cp865', $txt);
} else {
		$txt = "         TakeAway bestilling, nr: " . $ordreNr;
}

$txt=chr(27)."G1".$txt.chr(27)."G0"; #Fed
fwrite($fp,"$txt\n");

$txt = "		" . date('d/m/Y H:i:s');
fwrite($fp,"$txt\n\n");




$txt = "Navn: " . $_POST['takeAwayName'];
$txt = iconv('UTF-8', 'cp865', $txt);
fwrite($fp,"$txt\n");

$txt = " Tlf: " . $_POST['takeAwayPhone'];
$txt = iconv('UTF-8', 'cp865', $txt);
fwrite($fp,"$txt\n");


if (isset($_POST['takeAwayAdress']) && !empty($_POST['takeAwayAdress'])) {
		$txt = "Adresse: " . $_POST['takeAwayAdress'];
		$txt = iconv('UTF-8', 'cp865', $txt);
		fwrite($fp,"$txt\n");
}

if (isset($_POST['takeAwayPostNr']) && !empty($_POST['takeAwayPostNr'])) {
		$txt = "Postnr: " . $_POST['takeAwayPostNr'];
		$txt = iconv('UTF-8', 'cp865', $txt);
		fwrite($fp,"$txt\n");
}

if (isset($_POST['takeAwayCity']) && !empty($_POST['takeAwayCity'])) {
		$txt = "By: " . $_POST['takeAwayCity'];
		$txt = iconv('UTF-8', 'cp865', $txt);
		fwrite($fp,"$txt\n");
}

fwrite($fp, "\n");

$txt = "Leveringsform: " . $_POST['takeAwayDeliver'];
fwrite($fp,"$txt\n");

$txt = "Modtaget via: " . $_POST['takeAwayReceived'];
fwrite($fp,"$txt\n\n");

$txt = "Dato: " . $_POST['takeAwayDate'] . ", tidspunkt: " . $_POST['takeAwayHour'] . ":" . $_POST['takeAwayMin'];
fwrite($fp,"$txt\n\n");


$txt = "    Antal      Vare \n --------------------------------------------";
fwrite($fp,"$txt\n");

foreach ($_POST['takeAwayOrders'] as $key => $txt) {
		$txt = "     " . iconv('UTF-8', 'cp865',$txt);
		fwrite($fp,"$txt\n");
}

$txt = "--------------------------------------------";
fwrite($fp,"$txt\n");

$takeAwayPay = ($_POST['takeAwayPay'] == 'yes') ? "Ja" : "Nej";
$txt = "Er ordren betalt? " . $takeAwayPay;
fwrite($fp,"$txt\n\n");


fclose($fp);

$url="://".$_SERVER['SERVER_NAME'].=$_SERVER['PHP_SELF'];
$url=str_replace("/debitor/pos_ordre.php","",$url);
if ($_SERVER['HTTPS']) $url="s".$url;
$url="http".$url;
$returside=$url."/debitor/pos_ordre.php";
$bon='';
$fp=fopen("$pfnavn","r");
while($linje=fgets($fp))$bon.=$linje;
$bon=urlencode($bon);


print "<meta http-equiv=\"refresh\" content=\"0;URL=http://$printserver/saldiprint.php?printfil=&url=$url&bon=$bon&returside=$returside\">\n";

?>
