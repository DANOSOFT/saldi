<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/posTxtPrint/postTxtPrintFunc.php --- lap 4.1.1 --- 2025.09.26 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2019-2025 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20190508 LN Move pos_txt_print function to this seperate file
// 20210226 PHR Added $reportNumber
// 20250926 PHR Updated call to function getVatArray

include("pos_ordre_includes/posTxtPrint/posTxtFunctions.php"); #20190506
function pos_txt_print($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling,$type = "standard") {

	if (!$id && $type == "standard") {
		return('No ID');
	}
	global $addr1,$addr2;
	global $bordnr,$bordnavn,$brugernavn,$bruger_id,$bynavn;
	global $charset,$country,$cvrnr;
	global $db,$db_encode,$db_id,$difkto;
	global $f_momssats,$firmanavn;
	global $kasse;
	global $momssats;
	global $postnr,$printserver;
	global $ref,$regnaar,$reportNumber;
	global $tlf,$tracelog;

	if ($tracelog) fwrite ($tracelog, "Printing\n");
	
	$samlet_pris=0;
	if (!$reportNumber) {
		$qtxt="select max(report_number) as repno from report";
		($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$reportNumber=$r['repno']:$reportNumber=0;

	}

   $ToCharset = "cp865";
	$FromCharset = $charset;
#	$convert = new ConvertCharset();
	if($type == "xRapport" || $type == "zRapport") {
		$pfnavn = "../temp/".$db."/". "report" . $bruger_id. ".txt";
	} else {
		$pfnavn="../temp/".$db."/".$bruger_id.".txt";
	}
	$fp=fopen("$pfnavn","w");
    getAdressVar();

    $belob = setAmountTxt($country);
    useCorrectCharset();

	$x=0;
	$betalt=0;
	$fremmedvaluta=0;

	include("pos_ordre_includes/posTxtPrint/posPayments.php"); #20190507
	include("pos_ordre_includes/posTxtPrint/ordrerData.php"); #20190506

	if (getCountry() == "Norway" && $bruger_id != -1) {
		$doNotPrint = setReceiptAsCopied($r, $type, $id);
	}

	if (!isset($tid) || !$tid) $tid=date("H:i");
	if (!isset($betaling) || !$betaling) $betaling="Betalt";
	if ($ref) {
		if ($r=db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$ref'",__FILE__ . " linje " . __LINE__))) {
			$ansat_id=$r['ansat_id']*1;
			if ($r=db_fetch_array(db_select("select navn from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__))) $ref=$r['navn'];
	  }
	}
	if (strpos($betaling,"|")) list($tmp,$betaling)=explode("|",$betaling);
	if (strpos($betaling2,"|")) list($tmp,$betaling2)=explode("|",$betaling2);

	if ($bordnr || $bordnr=='0') { #20150415
		$r = db_fetch_array(db_select("select box2,box3,box4,box7,box10 from grupper where art = 'POS' and kodenr='2' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__));
		($r['box7'])?$bord=explode(chr(9),$r['box7']):$bord=array();
		if (is_numeric($bordnr)) {
#			$bordnavn=$bord[$bordnr];
			setcookie("saldi_bordnr",$bordnr,time()+60*60*24*30);
		}
	}
	if (!$bordnr) $bordnr=$_COOKIE['saldi_bordnr'];
	if (!$kasse) $kasse=find_kasse($kasse);
 	$kasse=trim($kasse); #20150219
	$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr='1' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__));
	$afdelinger=explode(chr(9),$r['box3']);
	$tmp=$kasse-1;
	$afd=$afdelinger[$tmp];
	if ($afd) db_modify("update ordrer set afd='$afd' where id = $id",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__));
	$printer_ip=explode(chr(9),$r['box3']);
	$tmp=$kasse-1;
	$printserver=$printer_ip[$tmp];

	if (!$printserver)$printserver='localhost';
	include("pos_ordre_includes/posTxtPrint/getOrdrelinjer.php"); #20190506

	if (isset($_POST['proforma']) && $_POST['proforma'] == 'Proforma') {
		proformaCount($x, $dkkpris, $kasse);
	}
	$linjeantal = $linecount = $x;
	if ($rvnr) {
		$x++;
		$qtxt = "select * from ordrelinjer where ordre_id = '$id' and varenr = 'R'";
		if($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)));
		$antal[$x]="   ";
		list($pris,$samlet_pris)=explode("|",$r['lev_varenr'],2);
		$dkkpris[$x]=" ".dkdecimal($pris,2);
		$beskrivelse[$x]=$r['beskrivelse'];
		while(strlen($dkkpris[$x])<9) $dkkpris[$x]=" ".$dkkpris[$x];
		while(strlen($beskrivelse[$x])<26) $beskrivelse[$x]=$beskrivelse[$x]." ";
		$linjeantal=$x;
	}
	include_once("pos_ordre_includes/boxCountMethods/findBoxSale.php");
// fucntions is located in: debitor/pos_ordre_includes/helperMethods/helperFuncII.php
	$temp = findBoxSale($kasse, 0,'DKK');
	$omsatning=$temp[1] + $temp[7];
	$reportArray = setupReport($type, $kasse,$reportNumber); #Make report if that was the case
  $uniqueShopId = getUniqueBoxId($kasse);
	$vatRate = getVatArray($id);
	include("pos_ordre_includes/posTxtPrint/setTextVar.php"); #20190507
}


?>
