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


$temp = $header['firmanavn'];
$txt = "$temp";
while(strlen($txt)*2<88) $txt=" ".$txt." ";
$txt = iconv('UTF-8', 'cp865', $txt);
fwrite($fp,"$txt\n");

$txt = $header['addr1'];
while(strlen($txt)*2<88) $txt=" ".$txt." ";
$txt = iconv('UTF-8', 'cp865', $txt);
fwrite($fp,"$txt\n");

$txt = $header['bynavn'];
while(strlen($txt)*2<88) $txt=" ".$txt." ";
$txt = iconv('UTF-8', 'cp865', $txt);
fwrite($fp,"$txt\n");

$txt = "Tlf: " . $header['tlf'];
while(strlen($txt)*2<88) $txt=" ".$txt." ";
$txt = iconv('UTF-8', 'cp865', $txt);
fwrite($fp,"$txt\n");

$txt = "CVR: " . $header['cvrnr'];
while(strlen($txt)*2<88) $txt=" ".$txt." ";
$txt = iconv('UTF-8', 'cp865', $txt);
fwrite($fp,"$txt\n\n");




$txt = "Stk Tekst                               Beløb";
$txt = iconv('UTF-8', 'cp865', $txt);
fwrite($fp,"$txt\n");
fwrite($fp,"------------------------------------------------\n");

foreach ($query['products'] as $key => $txt) {
	$txt = iconv('UTF-8', 'cp865', $txt);
	fwrite($fp,"$txt\n");
}

//fwrite($fp,"------------------------------------------------");
//fwrite($fp,"$txt\n");
//
//$sum = number_format($query['sum'], '2', ',', '.');
//$txt = "Ialt DKK 			      $sum \n";
//fwrite($fp,$txt);
//$vat = number_format($query['moms'], '2', ',', '.');
//$txt = "Heraf moms 			        $vat \n";
//fwrite($fp,$txt);

fwrite($fp,"------------------------------------------------\n");

$cash = number_format($query['betalt'], '2', ',', '.');
$valuta = $query['valuta'];
fwrite($fp,"Ialt $valuta                                 $cash\n");
$vat = number_format($query['moms'], '2', ',', '.');
fwrite($fp,"Heraf moms                                 $vat\n");


fwrite($fp,"------------------------------------------------\n");
$cash = number_format($query['cash'], '2', ',', '.');
fwrite($fp,"$query[felt_1]                               $cash\n");
$returnings = number_format($query['returnings'], '2', ',', '.');
fwrite($fp,"Retur                                     $returnings\n");
fwrite($fp,"------------------------------------------------\n");


fwrite($fp,"Husk denne bon er dit bilag\n\n");
fwrite($fp,"Kasse: $query[felt_5]             Bonnr: $query[fakturanr]\n");
fwrite($fp,"Dato : $query[levdate]    kl:    $query[tidspkt]\n\n");
//fwrite($fp,"Bord : $bordnavn\n");

fwrite($fp,"***********************************************\n\n");
$txt="		TAK FOR BESØGET";
$txt = iconv('UTF-8', 'cp865', $txt);
fwrite($fp,"$txt\n\n");
fwrite($fp,"***********************************************\n\n");


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
