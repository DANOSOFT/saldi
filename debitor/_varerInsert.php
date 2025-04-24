<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/_varerInsert.php -----patch 4.1.0 ----2025-02-27--------------
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20250107 LOE created
@session_start();
$s_id=session_id();

include("../includes/std_func.php");

include("../includes/connect.php");
include("../includes/online.php");

// Check and retrieve the parameters from the URL
$db = isset($_GET['db']) ? filter_var($_GET['db'], FILTER_SANITIZE_STRING) : '';
$varenr = isset($_GET['varenr']) ? filter_var($_GET['varenr'], FILTER_SANITIZE_STRING) : '';
$stregkode = isset($_GET['stregkode']) ? filter_var($_GET['stregkode'], FILTER_SANITIZE_STRING) : '';
$trademark  = isset($_GET['varemærke']) ? filter_var($_GET['varemærke'], FILTER_SANITIZE_STRING) : '';
$beskrivelse = isset($_GET['beskrivelse']) ? filter_var($_GET['beskrivelse'], FILTER_SANITIZE_STRING) : '';
$kostpris = isset($_GET['kostpris']) ? filter_var($_GET['kostpris'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : '';
$salgspris = isset($_GET['salgspris']) ? filter_var($_GET['salgspris'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : '';
$retail_price = isset($_GET['vejl_pris']) ? filter_var($_GET['vejl_pris'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : '';
$notes = isset($_GET['notes']) ? filter_var($_GET['notes'], FILTER_SANITIZE_STRING) : '';
$enhed = isset($_GET['enhed']) ? filter_var($_GET['enhed'], FILTER_SANITIZE_STRING) : '';
$gruppe = isset($_GET['gruppe']) ? filter_var($_GET['gruppe'], FILTER_SANITIZE_STRING) : '';
$min_lager = isset($_GET['min_lager']) ? filter_var($_GET['min_lager'], FILTER_SANITIZE_NUMBER_INT) : '';
$max_lager = isset($_GET['max_lager']) ? filter_var($_GET['max_lager'], FILTER_SANITIZE_NUMBER_INT) : '';
$location = isset($_GET['lokation']) ? filter_var($_GET['lokation'], FILTER_SANITIZE_STRING) : '';
$fokus = isset($_GET['fokus']) ? filter_var($_GET['fokus'], FILTER_SANITIZE_STRING) : '';
$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT) : '';
$bordnr = isset($_GET['bordnr']) ? filter_var($_GET['bordnr'], FILTER_SANITIZE_NUMBER_INT) : '';
$queryString = $_SERVER['QUERY_STRING'];  
	$sdataPos = strpos($queryString, 'sdata=&');
	if ($sdataPos !== false) {
		$sdata = substr($queryString, $sdataPos + 7);  
	} else {
		$sdata = '';
	}


$kostpris = is_numeric($kostpris) && !empty($kostpris) ? (float)$kostpris : NULL;
$salgspris = is_numeric($salgspris) && !empty($salgspris) ? (float)$salgspris : NULL;
$retail_price = is_numeric($retail_price) && !empty($retail_price) ? (float)$retail_price : NULL;
$min_lager = is_numeric($min_lager) && !empty($min_lager) ? (int)$min_lager : NULL;
$max_lager = is_numeric($max_lager) && !empty($max_lager) ? (int)$max_lager : NULL;

	
// echo 'Type of $kostpris: ' . gettype($kostpris) . "<br>";
// echo 'Type of $salgspris: ' . gettype($salgspris) . "<br>";
// echo 'Type of $retail_price: ' . gettype($retail_price) . "<br>";
// echo 'Type of $min_lager: ' . gettype($min_lager) . "<br>";
// echo 'Type of $max_lager: ' . gettype($max_lager) . "<br>";
// if($retail_price==''){
// 	$retail_price=$salgspris;
// }
	
#varenr; 002438


/*
	echo "varenr: " . htmlspecialchars($varenr) . "<br>";
	echo "retail_price: " . htmlspecialchars($retail_price) . "<br>";
	echo "beskrivelse: " . htmlspecialchars($beskrivelse) . "<br>";
	echo "kostpris: " . htmlspecialchars($kostpris) . "<br>";
	echo "salgspris: " . htmlspecialchars($salgspris) . "<br>";
	echo "max_lager: " . htmlspecialchars($max_lager) . "<br>";
	echo "location: " . htmlspecialchars($location) . "<br>";

	#echo "nothing is here"; exit;
	*/
	$qr = db_select("select * from varer where varenr = '$varenr'",__FILE__ . " linje " . __LINE__);
	if (!$chk = db_fetch_array($qr)) {
		
		db_modify("INSERT INTO varer (varenr, stregkode, trademark, beskrivelse, kostpris, salgspris, notes, enhed, gruppe, min_lager, max_lager, location) 
		VALUES ('$varenr','$stregkode','$trademark','".db_escape_string($beskrivelse)."','$kostpris','$salgspris','".db_escape_string($notes)."','$enhed','$gruppe',2,2,'$location')",__FILE__ . " linje " . __LINE__);
	}
	
	

	
//echo "Location: ". $location;
$qra = db_select("select * from varer where varenr = '$varenr'",__FILE__ . " linje " . __LINE__);
if ($r = db_fetch_array($qra)) {
$rid = $r['id'];
}

$ul = "ordre.php?";
$nav = $ul.$sdata.'&vare_id='.$rid; //complete from returned database value vare_id
#$nav = $ul.$sdata; //use without vare_id
print "<meta http-equiv=\"refresh\" content=\"1;URL=$nav\">\n";
exit;
?>