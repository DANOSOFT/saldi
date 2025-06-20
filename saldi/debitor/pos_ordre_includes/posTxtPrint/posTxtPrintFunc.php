<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------- debitor/pos_ordre_includes/posTxtPrint/postTxtPrintFunc.php ---- lap 3.7.4----2019.05.08-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20190508 LN Move pos_txt_print function to this seperate file

include("pos_ordre_includes/posTxtPrint/posTxtFunctions.php"); #20190506

function pos_txt_print($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling, $type = "standard") {
	if (!$id && $type == "standard") {
		return('No ID');
	}
	global $db;
	global $db_id;
	global $brugernavn;
	global $bruger_id;
	global $momssats;
	global $db_encode;
	global $printserver;
	global $difkto;
	global $bordnavn;
	global $regnaar;

	global $firmanavn;
	global $addr1;
	global $addr2;
	global $postnr;
	global $bynavn;
	global $tlf;
	global $cvrnr;
	global $country;

	global $f_momssats;

	$samlet_pris=0;

	#	$udskriv_bon=1;
 	include("../includes/ConvertCharset.class.php");

    $ToCharset = "cp865";
	$FromCharset = getFromCharset();
	$convert = new ConvertCharset();

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

	if (getCountry() == "Norway") {
		$doNotPrint = setReceiptAsCopied($r, $type, $id);
	}

	if (!$tid) $tid=date("H:i");
	if (!$betaling) $betaling="Betalt";
	if ($ref) {
		if ($r=db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$ref'",__FILE__ . " linje " . __LINE__))) {
			$ansat_id=$r['ansat_id']*1;
			if ($r=db_fetch_array(db_select("select navn from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__))) $ref=$r['navn'];
	  }
	}
	if (strpos($betaling,"|")) list($tmp,$betaling)=explode("|",$betaling);
	if (strpos($betaling2,"|")) list($tmp,$betaling2)=explode("|",$betaling2);

	if ($bordnr || $bordnr=='0') { #20150415
		$r = db_fetch_array(db_select("select box2,box3,box4,box7,box10 from grupper where art = 'POS' and kodenr='2'",__FILE__ . " linje " . __LINE__));
		($r['box7'])?$bord=explode(chr(9),$r['box7']):$bord=NULL;
		$bordnavn=$bord[$bordnr];
		setcookie("saldi_bordnr",$bordnr,time()+60*60*24*30);
	}
	if (!$bordnr) $bordnr=$_COOKIE['saldi_bordnr'];
	if (!$kasse) $kasse=find_kasse($kasse);
 	$kasse=trim($kasse); #20150219
	$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr='1'",__FILE__ . " linje " . __LINE__));
	$afdelinger=explode(chr(9),$r['box3']);
	$tmp=$kasse-1;
	$afd=$afdelinger[$tmp];
	if ($afd) db_modify("update ordrer set afd='$afd' where id = $id",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
	$printer_ip=explode(chr(9),$r['box3']);
	$tmp=$kasse-1;
	$printserver=$printer_ip[$tmp];

	if (!$printserver)$printserver='localhost';
	include("pos_ordre_includes/posTxtPrint/getOrdrelinjer.php"); #20190506

	if (isset($_POST['proforma']) && $_POST['proforma'] == 'Proforma') {
		proformaCount($x, $dkkpris, $kasse);
	}
	$linjeantal=$x;
	if ($rvnr) {
		$x++;
		if($r=db_fetch_array(db_select("select * from ordrelinjer where ordre_id = '$id' and varenr = 'R'",__FILE__ . " linje " . __LINE__)));
		$antal[$x]="   ";
		list($pris,$samlet_pris)=explode("|",$r['lev_varenr'],2);
		$dkkpris[$x]=" ".dkdecimal($pris,2);
		$beskrivelse[$x]=$r['beskrivelse'];
		while(strlen($dkkpris[$x])<9) $dkkpris[$x]=" ".$dkkpris[$x];
		while(strlen($beskrivelse[$x])<26) $beskrivelse[$x]=$beskrivelse[$x]." ";
		$linjeantal=$x;
	}
	$temp = find_kassesalg($kasse, 0,'DKK');
	$omsatning=$temp[1] + $temp[7];
	$reportArray = setupReport($type, $kasse); #Make report if that was the case
  $uniqueShopId = getUniqueBoxId($kasse);
	$vatRate = getVatArray($linjeantal, $dkkpris, $vatArray);
	include("pos_ordre_includes/posTxtPrint/setTextVar.php"); #20190507
}


?>
