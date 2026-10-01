<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/report/xRapport.php -- ver 5.0.0 -- 2026-10-01 --
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
// Copyright (c) 2003-2026 Danosoft ApS
// --------------------------------------------------------------------------
// 20190312 LN If the report is a X-report make the correct calls
// 20190319 LN Add correct parameter to the following print functions
// 20261001 MJ SST-825 Always print the redirect to the print server. a5fcb2f3 wrapped it in
//             if ($printpopup), but this file runs inside pos_txt_print(), which never sets
//             that variable - so the X-report sent nothing to the browser and nothing to the
//             printer, while the Z-report kept working. Header path corrected: the file moved
//             out of debitor/pos_print/ long ago.


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

// SST-825 This redirect used to be wrapped in if ($printpopup). The file is included
// from posTxtPrint/setTextVar.php, inside pos_txt_print(), which declares
// global $printserver but never global $printpopup and never assigns it - so the
// condition was an undefined local, always false. Nothing was written to the browser
// before the exit below: a blank page in the till and no receipt. zRapport.php, on the
// same path, prints this line unconditionally, which is why Z-reports always worked.
// Whether printing is switched off at all is already decided one level up, by
// setTextVar.php's deactivateBonprint check, before this file is ever included.
print "<meta http-equiv=\"refresh\" content=\"0;URL=" . ($printserver == 'android' ? "saldiprint://" : "http://$printserver") . "/saldiprint.php?printfil=&url=$url&bruger_id=$bruger_id&bon=$bon&bonantal=$bonantal&id=$id&skuffe=$skuffe&returside=$returside&logo=on\">\n";

exit;

?>
