<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/report/rapportReceiptData.php --- lap 4.0.0 --- 2021.03.07 ---
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
// LN 20190312 Make functions that handles all data that deals with the receipts
// 20210307 PHR Added $date to allmost all functions as it was countion all orders from day zero

function receiptCount($kasse,$date) {
	$idArray = makeOrderIdArray($kasse,$date);
	$receipts = db_select("select * from pos_betalinger where amount>'0'", __FILE__ . " linje " . __LINE__);
	$receiptArray['count'] = 0;
	while($receipt = db_fetch_array($receipts)) {
		$price = $receipt['amount'];
		$price = abs($price);
		if (in_array($receipt['ordre_id'], $idArray) ) {
			$receiptArray['count']++;
			(isset($receiptArray['totalPrice']))?$receiptArray['totalPrice'] += $price : $receiptArray['totalPrice'] = $price;
		}
	}
	$receiptArray = subtractReceiptData("Receipt", $receiptArray);
	return $receiptArray;
}

function copiedReceipts($kasse,$date)
{
	$orders = db_select("select * from ordrer where felt_5='$kasse' and fakturadate = '$date'", __FILE__ . " linje " . __LINE__);
	$copiedArray['count'] = 0;
	$copiedArray['totalPrice'] = 0;
	while($order = db_fetch_array($orders)) {
		$price = $order['sum'];
		$price = $price + $order['moms'];
		$price = abs($price);
		if ($order['copied'] == 't') {
			$copiedArray['count'] += 1;
			$copiedArray['totalPrice'] += $price;
		}
	}
	$copiedArray = subtractReceiptData("Copied receipt", $copiedArray);
	return $copiedArray;
}

function returnedReceipts($kasse)
{
	$orders = db_select("select * from returnings where kasse='$kasse'", __FILE__ . " linje " . __LINE__);
	$returnedArray['count'] = 0;
	$returnedArray['totalPrice'] = 0;
	while($order = db_fetch_array($orders)) {
		$price = $order['price'];
		$price = abs($price);
		$returnedArray['count'] += 1;
		$returnedArray['totalPrice'] += $price;
	}
	$returnedArray = subtractReceiptData("Returned receipt", $returnedArray);
	return $returnedArray;
}

function ordersWithDiscount($kasse,$date) {
	$idArray = makeOrderIdArray($kasse,$date);
	$qtxt = "select ordrelinjer.* from ordrelinjer,ordrer where ";
	$qtxt.= "rabat>'0' and ordrer.id = ordrelinjer.ordre_id and ordrer.fakturadate = '$date'";
	$ordrelines = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	$discountArray['count'] = 0;
	$discountArray['totalPrice'] = 0;
	while ($order = db_fetch_array($ordrelines)) {
		if (in_array($order['ordre_id'], $idArray)) {
			$discount = (($order['antal'] * $order['vat_price']) / 100 * $order['rabat']);
			$discountArray['count'] += 1;
			$discountArray['totalPrice'] += $discount;
		}
	}
	$discountArray = subtractReceiptData("Discount receipt", $discountArray);
	return $discountArray;
}

function cancelledOrders($kasse) {
	$qtxt = "select * from deleted_order where kasse='$kasse' and report_number = '0'";
	$delOrder = db_select($qtxt, __FILE__ . "linje" . __LINE__);
	$deletedArray['count'] = 0;
	$deletedArray['totalPrice'] = 0;
	while ($order = db_fetch_array($delOrder)) {
		$price = (float)number_format($order['price'], 2, ',', '');
		$price = abs($price);
		$deletedArray['count']++;
		$deletedArray['totalPrice'] += $price;
	}
	$deletedArray = subtractReceiptData("Cancelled receipt", $deletedArray);
	return $deletedArray;
}

function correctionOrders($kasse) {
	$qtxt = "select * from corrections where kasse='$kasse' and report_number = '0'";
	$corrections = db_select($qtxt, __FILE__ . "linje" . __LINE__);
	$correctedArray['count'] = 0;
	$correctedArray['totalPrice'] = 0;
	while ($order = db_fetch_array($corrections)) {
		$price = (float)number_format($order['price'], 2, ',', '');
		$price = abs($price);
		$correctedArray['count']++;
		$correctedArray['totalPrice'] += $price;
	}
	$correctedArray = subtractReceiptData("Corrected receipt", $correctedArray);
	return $correctedArray;
}

function priceCorrectionOrders($kasse) {
		$qtxt = "select * from price_correction where kasse='$kasse' and report_number = '0'";
	$priceCorrections = db_select($qtxt, __FILE__ . "linje" . __LINE__);
	$priceCorrectionArray['count'] = 0;
	$priceCorrectionArray['totalPrice'] = 0;
	while ($order = db_fetch_array($priceCorrections)) {
		$price = (float)number_format($order['price'], 2, ',', '');
		$price = abs($price);
		$priceCorrectionArray['count']++;
		$priceCorrectionArray['totalPrice'] += $price;
	}
		$priceCorrectionArray = subtractReceiptData("Corrected price receipt", $priceCorrectionArray);
		return $priceCorrectionArray;
}

function saleWithoutVat($kasse,$date) {
	$idArray = makeOrderIdArray($kasse,$date);
	$orderQuery = db_select("select * from ordrelinjer", __FILE__ . " linje " . __LINE__);
	$saleWithoutVatArray['count'] = 0;
	$saleWithoutVatArray['totalPrice'] = 0;
	while($order = db_fetch_array($orderQuery)) {
		$vat = $order['momssats'];
		if (isset($vat) && in_array($order['ordre_id'], $idArray)) {
			$tempTotalPrice = $order['vat_price'] * $order['antal'];
			$price = $tempTotalPrice - ($tempTotalPrice / 100 * $vat);
			$price = abs($price);
			$saleWithoutVatArray['count']++;
			$saleWithoutVatArray['totalPrice'] += $price;
		}
	}
	$saleWithoutVatArray = subtractReceiptData("Sale without vat receipt", $saleWithoutVatArray);
	return $saleWithoutVatArray;
}

function proformaReceipts($kasse) {
	$qtxt = "select * from proforma where id='$kasse' and report_number = '0'";
	$proformaQuery = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	$proformaArray['count'] = 0;
	$proformaArray['totalPrice'] = 0;
	while($proforma = db_fetch_array($proformaQuery)) {
		$price = $proforma['price'];
		$price = abs($price);
		$proformaArray['count'] += $proforma['count'];
		$proformaArray['totalPrice'] += $price;
	}
	$proformaArray = subtractReceiptData("Proforma receipt", $proformaArray);
	return $proformaArray;

}



?>
