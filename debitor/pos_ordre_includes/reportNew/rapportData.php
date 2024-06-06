<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/report/rapportData.php --- lap 3.7.4 --- 2019.05.08 ---
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
// Copyright (c) 2019-2021 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190312 Make functions that retrieve all type of payments and sold data to the report
// 20210226 PHR Added $reportNumber


function productGroupDescription($kasse, $reportNumber) { # LN 20190212 Make varegrupper for the xRapport
	$idArray = makeOrderIdArray($kasse);
	$query = db_select("select * from varer", __FILE__ . " linje " . __LINE__);
	while ($product = db_fetch_array($query)) {
		$productGroup = $product['gruppe'];
		$productNumber = $product['varenr'];
		$productId = $product['id'];
		$qtxt = "select ordrelinjer.momssats from ordrelinjer,ordrer where ordrelinjer.varenr = '$productNumber' ";
		$qtxt.= "and ordrelinjer.ordre_id = ordrer.id and ordrer.report_number = '$reportNumber'";
		($r=db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))?$vat = $r['momssats']:$vat = 0;
		$qtxt = "select beskrivelse from grupper where kodenr = '$productGroup' and art = 'VG'";
		($r=db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))?$description = ['beskrivelse']:$description='';
		if ($description) {
			if (!is_array($groupArray)) $groupArray=array();
			$groupArray = setCountAndTotal($description, $productId, $productGroup, $groupArray, $idArray, $vat, $reportNumber);
		}
	}
	$groupArray = subtractItemGroupData("Item group description", $groupArray);
	return $groupArray;
}

function setCountAndTotal($description, $productId, $productGroup, $groupArray, $idArray, $vat, $reportNumber) {
	$groupArray[$productGroup]['description'] = $description;
	$salesCount = 0;
	$salesPrice = 0;
#	if (!is_array($tempArr)) $tempArr=array();
#	if (!isset($tempArr[$description])) $tempArr[$description]='';
#	if ($tempArr[$description] != 1) {
			$qtxt = "select batch_salg.* from batch_salg,ordrer where vare_id='$productId' ";
			$qtxt.= "and batch_salg.ordre_id = ordrer.id and ordrer.report_number = '$reportNumber'";
			$salesQuery = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			while ($batchSale = db_fetch_array($salesQuery)) {
					if (in_array($batchSale['ordre_id'], $idArray)) {
							$salesCount += $batchSale['antal'];
							$salesPrice = $batchSale['pris'] * $salesCount;
					}
			}
			$salesPrice = truncate_number(($salesPrice / (100)) * (100+$vat));
			$salesPrice = round($salesPrice/5) * 5;
			$groupArray[$productGroup]['count'] += ($salesCount > 0) ? $salesCount : 0;
			$groupArray[$productGroup]['sellPrice'] += $salesPrice;
#	}
#	$tempArr[$description] = 1;
	return $groupArray;
}


function paymentMethods($kasse,$reportNumber) {
	$payment;
	$qtxt = "select * from ordrer where felt_5='$kasse' and report_number = '$reportNumber'";
	$orderQuery = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	$total = 0;
	while ($order = db_fetch_array($orderQuery)) {
			$payment = $order['felt_1'];
			if ($payment != '') {
					$paymentArray[$payment]['payment'] = $payment;
					$paymentArray[$payment]['price'] += $order['betalt'];
					$total += $order['betalt'];
			}
	}

	$paymentArray['sum']['payment'] = "SUM";
	$paymentArray['sum']['price'] = $total;

	$paymentArray = subtractData("Payment method", $paymentArray);
	return $paymentArray;
}

function vatPayments($kasse,$reportNumber) {

	$vat;
	$idArray = makeOrderIdArray($kasse,$reportNumber);
	$qtxt = "select ordrelinjer.* from ordrelinjer,ordrer where ordrelinjer.ordre_id = ordrer.id and ordrer.report_number = '$reportNumber'";
	$ordrelines = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	$total = 0;
	while($order = db_fetch_array($ordrelines)) {
			$vat = $order['momssats'];
			if (isset($vat) && in_array($order['ordre_id'], $idArray)) {
					$tempTotalPrice = $order['vat_price'] * $order['antal'];
					$price = $tempTotalPrice - ($tempTotalPrice / 100 * $vat);
					$vatArray[$vat]['vat'] = $vat;
					$vatArray[$vat]['price'] += $price;
					$total += $price;
			}
	}
	$vatArray['sum']['payment'] = "SUM";
	$vatArray['sum']['price'] = $total;
#	$vatArray = subtractData("Vat payment", $vatArray);
	return $vatArray;
}


function drawCount($kasse) {
	
	$drawer = db_select("select * from drawer where id='$kasse'", __FILE__ . " linje " . __LINE__);
	$drawArray[$kasse]['kasse'] = $kasse;
	$drawArray[$kasse]['openings'] = 0;
	while($draw = db_fetch_array($drawer)) {
		$box = $draw['id'];
		$drawArray[$box]['kasse'] = $box;
		$drawArray[$box]['openings'] = $draw['openings'];
	}
	$drawArray = subtractData("Draw opening", $drawArray);
	return $drawArray;
}

function calculateTurnover($kasse,$reportNumber) {

	$qtxt="select * from ordrer where felt_5 ='$kasse' and report_number = '$reportNumber' order by id";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$tempOrdreId = $r['id'];
		$invoice=$r['fakturanr'];
		$price = 0;
		$number = 0;
		$oSum=$r['sum'];
		$oVat=$r['moms'];
		$singleoSum=0;
		$singleoVat=0;
		$qtxt="select pris, antal, rabat, momsfri, momssats from ordrelinjer where ordre_id='$tempOrdreId'";
		$q2 = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$lineSum = $r2['pris'] * $r2['antal'] - ($r2['pris'] * $r2['antal'] * $r2['rabat'] / 100) ;
			$singleoSum += $lineSum;
			if (!$r2['momsfri']) $singleoVat += ($lineSum / 100 * $r2['momssats']);
			$turnoverWithVat += ($r2['pris'] * $r2['antal']) + ($moms/100 * ($r2['pris'] * $r2['antal']));      
		}
		$turnover += $singleoSum;
		$totalVat += $singleoVat;
		$diff = $oSum+$oVat - ($singleoSum+$singleoVat);
#		if (abs($diff) > 0.1) echo __line__." $invoice : $oSum+$oVat != $singleoSum+$singleoVat   ($diff)<br>"; 
	}
	$turnoverWithVat = $turnover + $totalVat;
	return ["turnoverWithoutVat" => subtractTurnover($turnover, false), "turnoverWithVat" => subtractTurnover($turnoverWithVat, true)];
}

function calculateTurnoverx($kasse) {
	global $regnaar;
	
	$qtxt = "select box1,box2,box3,box4 from grupper where art='RA' and kodenr = '$regnaar'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)); 
	$mb=$r['box1'];	
	$yb=$r['box2'];	
	$me=$r['box3'];	
	$ye=$r['box4'];	
	
#cho "$yb,$mb,$ye,$me<br>";
		
	$acYbegin=$yb."-".$mb."-01";
	$acYend=$ye.$me;
#	if ($acYbegin > date('Ym') || $acYend > date('Ym'))  
	
	
	$qtxt = "select sum(sum) as turnover, sum(moms) as totalvat from ";
	$qtxt.= "ordrer where felt_5 ='$kasse' and status > 2 and fakturadate >= '$acYbegin'";
#cho "$qtxt<br>";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)); 
	$turnover = $r['turnover'];
	$totalVat = $r['totalvat'];

/*
$ordreQuery = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($order = db_fetch_array($ordreQuery)) {
		$tempOrdreId = $order['ordrenr'];
		$price = 0;
		$number = 0;
		$orderLineQuery = db_select("select pris, antal, momssats from ordrelinjer where ordre_id='$tempOrdreId'", __FILE__ . " linje " . __LINE__);
		while ($orderLine = db_fetch_array($orderLineQuery)) {
			$turnover += $orderLine['pris'] * $orderLine['antal'];
			$totalVat += (($orderLine['pris'] * $orderLine['antal']) / 100 * $orderLine['momssats']);
			$turnoverWithVat += ($batch['pris'] * $batch['antal']) + ($moms/100 * ($batch['pris'] * $batch['antal']));      
		}
	}
*/	
	$turnoverWithVat = $turnover + $totalVat;
#cho "$turnoverWithVat = $turnover + $totalVat<br>";
#xit;
	return ["turnoverWithoutVat" => subtractTurnover($turnover, false), "turnoverWithVat" => subtractTurnover($turnoverWithVat, true)];
}

?>
