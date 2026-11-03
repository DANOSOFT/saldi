<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/helperMethods/helperFuncII.php --- ver 5.1.0 --- 2026-10-29 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2019-2026 Danosoft ApS
// -------------------------------------------------------------------------------------------------
//
// 20190312 LN Make various helper functions for the pos_ordre and the report files
// 20190319 LN Added new function to return a unque box id
// 20200211	PHR	function getUniqueBoxId. Corrected function as it destroyed the global $db var.
// 20250926 PHR Updated function getVatArray
// 20261029 CL/MMR SST-896 makeOrderIdArray() selects only register sales, as posbogfor() and findBoxSale() do.
//                 An ordinary invoice gets the register number in felt_5 when its department has a register, so the X/Z-report counted it in turnover, VAT and item groups.
//                 New reportOrderArtFilter() holds the shared art condition; debtor orders still count when sets are enabled.

function getVatArray($id) {
	// called from debitor/pos_ordre_includes/posTxtPrint/postTxtPrintFunc.php

	$vatRate = array();
	$qtxt = "select * from ordrelinjer where ordre_id = '$id'";
	$q = db_select($qtxt, __FILE__ . "linje" . __LINE__);
	while ($r = db_fetch_array($q)) {
		$price = ($r['pris'] - ($r['pris'] * $r['rabat'] / 100)) * $r['antal'];
		$vatPercentage = $r['momssats'];
		if ($vatPercentage) {
			$price+= ($price*$vatPercentage/100);
		}
		$vat = $price / 100 * $vatPercentage;
		$vatRate[$vatPercentage]['percentage'] = truncate_number($vatPercentage);
		if (!isset($vatRate[$vatPercentage]['base']))  $vatRate[$vatPercentage]['base']  = 0; 
		if (!isset($vatRate[$vatPercentage]['vat']))   $vatRate[$vatPercentage]['vat']   = 0; 
		if (!isset($vatRate[$vatPercentage]['total'])) $vatRate[$vatPercentage]['total'] = 0; 
		$vatRate[$vatPercentage]['base'] += $price - $vat;
		$vatRate[$vatPercentage]['vat'] += $vat;
		$vatRate[$vatPercentage]['total'] += truncate_number($price); 
	}
	ksort($vatRate);
	return $vatRate;
}

/**
 * SQL condition limiting ordrer to the sales a register report covers.
 *
 * Matches posbogfor() in pos_ordre.php and findBoxSale(): POS orders only, plus debtor orders
 * handed in at the register when sets are enabled (POS kodenr 2 box12, vis_saet).
 *
 * @param int|string $regnaar Fiscal year whose POS settings decide whether sets are enabled.
 * @param string $alias Table alias or name to prefix the column with, '' for none.
 * @return string Condition starting with "and ".
 */
function reportOrderArtFilter($regnaar, $alias = '') {
	$col = ($alias ? $alias . '.' : '') . 'art';
	$qtxt = "select box12 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '" . (int)$regnaar . "'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	// Same truthiness as posbogfor() and findBoxSale(): '' and '0' both mean sets are off.
	$includeDebtorOrders = $r && (bool)trim((string)$r['box12']);
	return $includeDebtorOrders ? " and ($col = 'PO' or $col like 'D%')" : " and $col = 'PO'";
}

function makeOrderIdArray($kasse,$date) {
	global $regnaar;
	$ordreIdArr = array();
	$qtxt = "select id from ordrer where felt_5 = '" . (int)$kasse . "' and fakturadate = '" . db_escape_string($date) . "'";
	$qtxt.= reportOrderArtFilter($regnaar);
	$orderQuery = db_select($qtxt, __FILE__ . "linje" . __LINE__);
	while ($order = db_fetch_array($orderQuery)) {
		$ordreIdArr[] = $order['id'];
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

