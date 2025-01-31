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

$txt = "         TakeAway bestilling, nr: " . $query['ordrenr'];
fwrite($fp,"$txt\n");
$txt = "		" . date('d/m/Y H:i:s');
fwrite($fp,"$txt\n\n");




$txt = "Navn " . $query['lev_navn'];
$txt = iconv('UTF-8', 'cp865', $txt);
fwrite($fp,"$txt\n");

$txt = "Tlf: " . $query['kontakt_tlf'];
$txt = iconv('UTF-8', 'cp865', $txt);
fwrite($fp,"$txt\n");


if (isset($query['addr1']) && !empty($query['addr1'])) {
		$txt = "Adresse: " . $query['addr1'];
		$txt = iconv('UTF-8', 'cp865', $txt);
		fwrite($fp,"$txt\n");
}

if (isset($query['postnr']) && !empty($query['postnr'])) {
		$txt = "Postnr: " . $query['postnr'];
		$txt = iconv('UTF-8', 'cp865', $txt);
		fwrite($fp,"$txt\n");
}

if (isset($query['bynavn']) && !empty($query['bynavn'])) {
		$txt = "By: " . $query['bynavn'];
		$txt = iconv('UTF-8', 'cp865', $txt);
		fwrite($fp,"$txt\n");
}

fwrite($fp, "\n");

$txt = "Leveringsform: " . $query['lev_addr1'];
fwrite($fp,"$txt\n");

$txt = "Modtaget via: " . $query['modtagelse'];
fwrite($fp,"$txt\n\n");

$txt = "Dato: " . $query['datotid'] . ", tidspunkt: " . $query['tidspkt'];
fwrite($fp,"$txt\n\n");


$txt = "    Antal      Vare \n --------------------------------------------";
fwrite($fp,"$txt\n");

foreach ($query['products'] as $key => $txt) {
		$txt = "     " . iconv('UTF-8', 'cp865',$txt);
		fwrite($fp,"$txt\n");
}

$txt = "--------------------------------------------";
fwrite($fp,"$txt\n");

$takeAwayPay = ($query['betalt'] == 'yes') ? "Ja" : "Nej";
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
exit;

?>
