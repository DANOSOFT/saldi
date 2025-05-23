<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/_varerInsert.php -----patch 4.1.1 ----2025-05-22--------------
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
if (!empty($_POST['selectedRows'])) {
    foreach ($_POST['selectedRows'] as $rowJson) {
        $row = json_decode($rowJson, true);

        if (!is_array($row)) continue;
	$db = if_isset($row, NULL, 'db') ? filter_var(if_isset($row, NULL, 'db'), FILTER_SANITIZE_STRING) : '';
	$varenr = if_isset($row, NULL, 'varenr') ? filter_var(if_isset($row, NULL, 'varenr'), FILTER_SANITIZE_STRING) : '';
	$stregkode = if_isset($row, NULL, 'stregkode') ? filter_var(if_isset($row, NULL, 'stregkode'), FILTER_SANITIZE_STRING) : '';
	$trademark = if_isset($row, NULL, 'varemærke') ? filter_var(if_isset($row, NULL, 'varemærke'), FILTER_SANITIZE_STRING) : '';
	$beskrivelse = if_isset($row, NULL, 'beskrivelse') ? filter_var(if_isset($row, NULL, 'beskrivelse'), FILTER_SANITIZE_STRING) : '';
	$kostpris = if_isset($row, 0, 'kostpris') ? filter_var(if_isset($row, 0, 'kostpris'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0;
	$salgspris = if_isset($row, 0, 'salgspris') ? filter_var(if_isset($row, 0, 'salgspris'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0;
	$retail_price = if_isset($row, 0, 'vejl_pris') ? filter_var(if_isset($row, 0, 'vejl_pris'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0;
	$notes = if_isset($row, NULL, 'notes') ? filter_var(if_isset($row, NULL, 'notes'), FILTER_SANITIZE_STRING) : '';
	$enhed = if_isset($row, NULL, 'enhed') ? filter_var(if_isset($row, NULL, 'enhed'), FILTER_SANITIZE_STRING) : '';
	$gruppe = if_isset($row, 0, 'gruppe') ? filter_var(if_isset($row, 0, 'gruppe'), FILTER_SANITIZE_STRING) : 0;
	$min_lager = if_isset($row, 0, 'min_lager') ? filter_var(if_isset($row, 0, 'min_lager'), FILTER_SANITIZE_NUMBER_INT) : 0;
	$max_lager = if_isset($row, 0, 'max_lager') ? filter_var(if_isset($row, 0, 'max_lager'), FILTER_SANITIZE_NUMBER_INT) : 0;
	$location = if_isset($row, NULL, 'lokation') ? filter_var(if_isset($row, NULL, 'lokation'), FILTER_SANITIZE_STRING) : '';
	$fokus = if_isset($row, NULL, 'fokus') ? filter_var(if_isset($row, NULL, 'fokus'), FILTER_SANITIZE_STRING) : '';
	$id = if_isset($row, NULL, 'id') ? filter_var(if_isset($row, NULL, 'id'), FILTER_SANITIZE_NUMBER_INT) : '';
	$bordnr = if_isset($row, NULL, 'bordnr') ? filter_var(if_isset($row, NULL, 'bordnr'), FILTER_SANITIZE_NUMBER_INT) : '';

	//Check for a specific type of pricelist supplied by the user
	$sdata = if_isset($row, NULL, 'sdata') ? filter_var(if_isset($row, NULL, 'sdata'), FILTER_SANITIZE_STRING) : '';
	$beskrivelse = if_isset($row,$beskrivelse,'Text');
	$varenr=if_isset($row,$varenr,'Varenr');
	$kostpris = if_isset($row,$kostpris,'Kostpris');
	$salgspris = if_isset($row,$salgspris,'Salgspris');



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
		

	if($salgspris==NULL || $salgspris==0){
		$salgspris=$kostpris;
	}
	
	

###############

			$qr = db_select("select * from varer where varenr = '$varenr'",__FILE__ . " linje " . __LINE__);
			if (!$chk = db_fetch_array($qr)) {
				if($beskrivelse==NULL && $varenr==NULL && $kostpris==NULL ){
					print "<script>alert('Please ensure your data is valid, check your delimiter!');</script>";
					print "<meta http-equiv=\"refresh\" content=\"1;URL=ordreliste.php\">\n";
					exit;
				}
				error_log("Insert new item: $varenr, $stregkode, $trademark, $beskrivelse, $kostpris, $salgspris, $notes, $enhed, $gruppe, $min_lager, $max_lager, $location");
				// Insert new item
				
			    db_modify("INSERT INTO varer (varenr, stregkode, trademark, beskrivelse, kostpris, salgspris, notes, enhed, gruppe, min_lager, max_lager, location) 
				VALUES ('$varenr','$stregkode','$trademark','".db_escape_string($beskrivelse)."','$kostpris','$salgspris','".db_escape_string($notes)."','$enhed','$gruppe',2,2,'$location')",__FILE__ . " linje " . __LINE__);

				$qra = db_select("select * from varer where varenr = '$varenr'",__FILE__ . " linje " . __LINE__);
				if ($r = db_fetch_array($qra)) {
				$rid = if_isset($r,NULL,'id');
				}
				$vare_id = if_isset($r,NULL,'id');	

				if(if_isset($id,NULL)){
		
					include_once("../includes/ordrefunc.php");
					opret_ordrelinje($id, $vare_id, $varenr, $antal, $beskrivelse, $salgpris, $rabat_ny, $procent, $art, $momsfri, $posnr, $linje_id, $incl_moms, $kdo, $rabatart, $kopi, $saet, $fast_db, $lev_varenr, $lager, $linje);
				}
      
			}
		}

	} else {
      echo "No rows selected.";
	}
	

$qra = db_select("select * from varer where varenr = '$varenr'",__FILE__ . " linje " . __LINE__);
if ($r = db_fetch_array($qra)) {
$rid = if_isset($r,NULL,'id');
}

$ul = "ordre.php?";
$nav = $ul.$sdata.'&vare_id='.$rid.'&id='.$id; //complete from returned database value vare_id

#$nav = $ul.$sdata; //use without vare_id
print "<meta http-equiv=\"refresh\" content=\"1;URL=$nav\">\n";
exit;
?>
