<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- debitor/pos_ordre_includes/helperMethods/helperFuncII.php ---- lap 3.8.9----2020.02.11-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2020 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20190312 LN Make various helper functions for the pos_ordre and the report files
// 20190319 LN Added new function to return a unque box id
// 20200211	PHR	function getUniqueBoxId. Corrected function as it destroyed the global $db var.

function getVatArray($linjeantal, $dkkpris, $vatArray) {
	for($x=1; $x<=$linjeantal; $x++) { # 20190123 LN
		$price = $dkkpris[$x];
		$price = floatval(str_replace(',', '.', str_replace('.', '', $price)));
		$vatPercentage = $vatArray[$x];
		$vat = $price / 100 * $vatPercentage; 
		$vatRate[$vatPercentage]['percentage'] = truncate_number($vatPercentage);
		$vatRate[$vatPercentage]['base'] += $price - $vat;
		$vatRate[$vatPercentage]['vat'] += $vat;
		$vatRate[$vatPercentage]['total'] += truncate_number($price); 
	}
	ksort($vatRate);
	return $vatRate;
}

function makeOrderIdArray($kasse) {
	$orderQuery = db_select("select ordrenr from ordrer where felt_5 = '$kasse'", __FILE__ . "linje" . __LINE__);
	while ($order = db_fetch_array($orderQuery)) {
		$ordreIdArr[] = $order['ordrenr'];
	}
	ksort($ordreIdArr);
	return $ordreIdArr;
}

function getUniqueBoxId($kasse) {
	global $db,$db_id;
/*	$db = (int) filter_var($db, FILTER_SANITIZE_NUMBER_INT);
	$uri = $_SERVER['REQUEST_URI'];
	if (strpos($uri, "bizbeta")) {
		$uniqueShopId = "BI-";
	} elseif (strpos($uri, "grillbar")) {
		$uniqueShopId = "GR-";
	} else {
		$uniqueShopId = "BI-";
	}
	$uniqueShopId .= "$db-"; */
	$uniqueShopId = substr($db,0,2) . "-$db-";
	if ($kasse < 10) {
		$uniqueShopId .= sprintf("%02d", $kasse);
	} else {
		$uniqueShopId .= $kasse;
	}
	return $uniqueShopId;
}



?>

