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

$id = if_isset($id, NULL);
$vare_id = if_isset($vare_id, NULL);
$varenr = if_isset($varenr, NULL);
$antal = if_isset($antal, NULL);
$beskrivelse = if_isset($beskrivelse, NULL);
$salgpris = if_isset($salgpris, NULL);
$rabat_ny = if_isset($rabat_ny, NULL);
$procent = if_isset($procent, NULL);
$art = if_isset($art, NULL);
$momsfri = if_isset($momsfri, NULL);
$posnr = if_isset($posnr, NULL);
$linje_id = if_isset($linje_id, NULL);
$incl_moms = if_isset($incl_moms, NULL);
$kdo = if_isset($kdo, NULL);
$rabatart = if_isset($rabatart, NULL);
$kopi = if_isset($kopi, NULL);
$saet = if_isset($saet, NULL);
$fast_db = if_isset($fast_db, NULL);
$lev_varenr = if_isset($lev_varenr, NULL);
$lager = if_isset($lager, NULL);
$linje = if_isset($linje, NULL);




	// Check and retrieve the parameters from the URL
	if (!empty($_POST['selectedRows'])) {

		$encodingUsed1 = db_fetch_array(
		db_select("SELECT box11 FROM grupper WHERE art = 'PL' AND box12 = 'Yes' ORDER BY beskrivelse", __FILE__ . " linje " . __LINE__)
	);

	if (isset($encodingUsed1[0]) && !empty($encodingUsed1[0])) {
		$encodingUsed = $encodingUsed1[0];
	} else {
		$encodingUsed = 'utf-8';
	}


foreach ($_POST['selectedRows'] as $rowJson) {
    $row = json_decode($rowJson, true);
    if (!is_array($row)) continue;

  
    array_walk_recursive($row, function (&$value) use ($encodingUsed) {
        if (is_string($value)) {
            $value = mb_convert_encoding($value, 'UTF-8', $encodingUsed);
        }
    });
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
	$gruppe = if_isset($row, 0, 'gruppe') ? filter_var(if_isset($row, 0, 'gruppe'), FILTER_SANITIZE_NUMBER_INT) : 0;
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


	$kostpris = is_numeric($kostpris) && !empty($kostpris) ? (float)$kostpris : 0;
	$salgspris = is_numeric($salgspris) && !empty($salgspris) ? (float)$salgspris : 0;
	$retail_price = is_numeric($retail_price) && !empty($retail_price) ? (float)$retail_price : 0;
	$min_lager = is_numeric($min_lager) && !empty($min_lager) ? (int)$min_lager : 0;
	$max_lager = is_numeric($max_lager) && !empty($max_lager) ? (int)$max_lager : 0;
	$gruppe = is_numeric($gruppe) && !empty($gruppe) ? (int)$gruppe : 0;
	$varenr = is_string($varenr) && trim($varenr) !== '' ? $varenr : NULL;

		

	
	
    if(!$gruppe || $gruppe == 0){

		$check = "select box8 from grupper where  box12='Yes'";
		$check1 = db_fetch_array(db_select($check,__FILE__ . " linje " . __LINE__));
		if ($check1) {
			$beskrivelseg = $check1[0]; //name of the group
		} 
         error_log("Beskrivelseg: " . var_export($beskrivelseg, true));
		$qtxt = "select kodenr from grupper where beskrivelse = '$beskrivelseg'";
		$q1 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($q1) {
			$gruppe = $q1[0]; //kodenr of the group
		} else {
			echo "<script>alert('gruppe not found');</script>";	
			exit;
		}

	}
	
###############


			$qr = db_select("select * from varer where varenr = '$varenr'",__FILE__ . " linje " . __LINE__);
			if (!$chk = db_fetch_array($qr)) {
				if($beskrivelse==NULL && $varenr==NULL && $kostpris==NULL ){
					print "<script>alert('Please ensure your data is valid, check your delimiter!');</script>";
					print "<meta http-equiv=\"refresh\" content=\"1;URL=ordreliste.php\">\n";
					exit;
				}
				
				// Insert new item
				
			    db_modify("INSERT INTO varer (varenr, stregkode, trademark, beskrivelse, kostpris, salgspris, notes, enhed, gruppe, min_lager, max_lager, location,lukket) 
				VALUES ('$varenr','$stregkode','$trademark','".db_escape_string($beskrivelse)."','$kostpris','$salgspris','".db_escape_string($notes)."','$enhed','$gruppe',2,2,'$location','0')",__FILE__ . " linje " . __LINE__);

				$qra = db_select("select * from varer where varenr = '$varenr'",__FILE__ . " linje " . __LINE__);
				if ($r = db_fetch_array($qra)) {
					$rid = if_isset($r,NULL,'id');
				}
				$vare_id = if_isset($r,NULL,'id');	
				error_log("Inserted new item with ID: $vare_id");
				if(if_isset($id,NULL)){
		
					include_once("../includes/ordrefunc.php");
					opret_ordrelinje($id, $vare_id, $varenr, $antal, $beskrivelse, $salgpris, $rabat_ny, $procent, $art, $momsfri, $posnr, $linje_id, $incl_moms, $kdo, $rabatart, $kopi, $saet, $fast_db, $lev_varenr, $lager, $linje);
				}else{
					error_log("No order ID provided for insertion of item: $varenr");
				}
      
			}else{
				error_log("Item already exists: , updating existing item.");
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
