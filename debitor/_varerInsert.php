<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/_varerInsert.php -----patch 4.1.1 ----2025-05-18--------------
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
$db = if_isset($_GET, NULL, 'db') ? filter_var(if_isset($_GET, NULL, 'db'), FILTER_SANITIZE_STRING) : '';
$varenr = if_isset($_GET, NULL, 'varenr') ? filter_var(if_isset($_GET, NULL, 'varenr'), FILTER_SANITIZE_STRING) : '';
$stregkode = if_isset($_GET, NULL, 'stregkode') ? filter_var(if_isset($_GET, NULL, 'stregkode'), FILTER_SANITIZE_STRING) : '';
$trademark = if_isset($_GET, NULL, 'varemærke') ? filter_var(if_isset($_GET, NULL, 'varemærke'), FILTER_SANITIZE_STRING) : '';
$beskrivelse = if_isset($_GET, NULL, 'beskrivelse') ? filter_var(if_isset($_GET, NULL, 'beskrivelse'), FILTER_SANITIZE_STRING) : '';
$kostpris = if_isset($_GET, 0, 'kostpris') ? filter_var(if_isset($_GET, 0, 'kostpris'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0;
$salgspris = if_isset($_GET, 0, 'salgspris') ? filter_var(if_isset($_GET, 0, 'salgspris'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0;
$retail_price = if_isset($_GET, 0, 'vejl_pris') ? filter_var(if_isset($_GET, 0, 'vejl_pris'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0;
$notes = if_isset($_GET, NULL, 'notes') ? filter_var(if_isset($_GET, NULL, 'notes'), FILTER_SANITIZE_STRING) : '';
$enhed = if_isset($_GET, NULL, 'enhed') ? filter_var(if_isset($_GET, NULL, 'enhed'), FILTER_SANITIZE_STRING) : '';
$gruppe = if_isset($_GET, 0, 'gruppe') ? filter_var(if_isset($_GET, 0, 'gruppe'), FILTER_SANITIZE_STRING) : 0;
$min_lager = if_isset($_GET, 0, 'min_lager') ? filter_var(if_isset($_GET, 0, 'min_lager'), FILTER_SANITIZE_NUMBER_INT) : 0;
$max_lager = if_isset($_GET, 0, 'max_lager') ? filter_var(if_isset($_GET, 0, 'max_lager'), FILTER_SANITIZE_NUMBER_INT) : 0;
$location = if_isset($_GET, NULL, 'lokation') ? filter_var(if_isset($_GET, NULL, 'lokation'), FILTER_SANITIZE_STRING) : '';
$fokus = if_isset($_GET, NULL, 'fokus') ? filter_var(if_isset($_GET, NULL, 'fokus'), FILTER_SANITIZE_STRING) : '';
$id = if_isset($_GET, NULL, 'id') ? filter_var(if_isset($_GET, NULL, 'id'), FILTER_SANITIZE_NUMBER_INT) : '';
$bordnr = if_isset($_GET, NULL, 'bordnr') ? filter_var(if_isset($_GET, NULL, 'bordnr'), FILTER_SANITIZE_NUMBER_INT) : '';

//Check for a specific type of pricelist supplied by the user
$sdata = if_isset($_GET, NULL, 'sdata') ? filter_var(if_isset($_GET, NULL, 'sdata'), FILTER_SANITIZE_STRING) : '';
$beskrivelse = if_isset($_GET,$beskrivelse,'Text');
$varenr=if_isset($_GET,$varenr,'Varenr');
$kostpris = if_isset($_GET,$kostpris,'Kostpris');
$salgspris = if_isset($_GET,$salgspris,'Salgspris');



$queryString = $_SERVER['QUERY_STRING'];  
	$sdataPos = strpos($queryString, 'sdata=&');
	if ($sdataPos !== false) {
		$sdata = substr($queryString, $sdataPos + 7);  
	} else {
		$sdata = '';
	}


$kostpris = is_numeric($kostpris) && !empty($kostpris) ? (float)$kostpris : NULL;
$salgspris = is_numeric($salgspris) && !empty($salgspris) ? (float)$salgspris : NULL;
$retail_price = is_numeric($retail_price) && !empty($retail_price) ? (float)$retail_price : 0;
$min_lager = is_numeric($min_lager) && !empty($min_lager) ? (int)$min_lager : 0;
$max_lager = is_numeric($max_lager) && !empty($max_lager) ? (int)$max_lager : 0;
$varenr = is_numeric($varenr) && !empty($varenr) ? (int)$varenr : NULL;
	

if($retail_price==NULL || $retail_price==0){
	$retail_price=$salgspris;
}
	
#varenr; 002438


	$qr = db_select("select * from varer where varenr = '$varenr'",__FILE__ . " linje " . __LINE__);
	if (!$chk = db_fetch_array($qr)) {
		
	   db_modify("INSERT INTO varer (varenr, stregkode, trademark, beskrivelse, kostpris, salgspris, notes, enhed, gruppe, min_lager, max_lager, location) 
	    VALUES ('$varenr','$stregkode','$trademark','".db_escape_string($beskrivelse)."','$kostpris','$salgspris','".db_escape_string($notes)."','$enhed','$gruppe',2,2,'$location')",__FILE__ . " linje " . __LINE__);
	   	
    }
	
	

	
//echo "Location: ". $location;
$qra = db_select("select * from varer where varenr = '$varenr'",__FILE__ . " linje " . __LINE__);
if ($r = db_fetch_array($qra)) {
$rid = if_isset($r,NULL,'id');
}

$ul = "ordre.php?";
$nav = $ul.$sdata.'&vare_id='.$rid; //complete from returned database value vare_id
#$nav = $ul.$sdata; //use without vare_id
print "<meta http-equiv=\"refresh\" content=\"1;URL=$nav\">\n";
exit;
?>