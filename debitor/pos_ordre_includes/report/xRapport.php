<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------ debitor/pos_print/xRapport.php -- lap 3.7.5 -- 2019-03-19 --
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
// 20190312 LN If the report is a X-report make the correct calls
// 20190319 LN Add correct parameter to the following print functions


printReportFunctions($fp, $firmanavn, $cvrnr, $orgNr, $date, $uniqueShopId, $reportArray, $type, $kasse);

fclose($fp);
$bonantal=1;

$tmp="/temp/".$db."/".$bruger_id.".txt";
$url="://".$_SERVER['SERVER_NAME'].=$_SERVER['PHP_SELF'];
$url=str_replace("/debitor/pos_ordre.php","",$url);
if ($_SERVER['HTTPS']) $url="s".$url;
$url="http".$url;
$returside=$url."/debitor/pos_ordre.php";
$bon='';

$fp=fopen("$pfnavn","r");
while($linje=fgets($fp))$bon.=$linje;
$bon=urlencode($bon);
if ($printserver=='box') {
	$filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
	if ($fp=fopen($filnavn,'r')) {
		$printserver=trim(fgets($fp));
		fclose ($fp);
		if ($printserver) setcookie("saldi_printserver",$printserver,time()+60*60*24*7,'/');
	}
}

if ($printserver=='box' || !$printserver) $printserver=$_COOKIE['saldi_printserver'];

$skuffe=0;

print "<meta http-equiv=\"refresh\" content=\"0;URL=http://$printserver/saldiprint.php?printfil=&url=$url&bruger_id=$bruger_id&bon=$bon&bonantal=$bonantal&id=$id&skuffe=$skuffe&returside=$returside&logo=on\">\n";

exit;

?>
