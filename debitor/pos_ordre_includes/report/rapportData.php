<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/report/rapportData.php --- lap 4.0.0 --- 2021.03.07 ---
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
// 20210307 PHR Added $date to allmost all functions as it was countion all orders from day zero

function productGroupDescription($kasse,$date) { # LN 20190212 Make varegrupper for the xRapport
	$idArray = makeOrderIdArray($kasse,$date);
	$itemId = $groupArray = array();
	$x=0;
	$qtxt = "select distinct(vare_id) as vare_id from ordrer,ordrelinjer where "; #added 20210307
	$qtxt.= "ordrer.fakturadate = '$date' and ordrelinjer.ordre_id = ordrer.id";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$itemId[$x] = $r['vare_id'];
		$x++;
	}
	$query = db_select("select * from varer", __FILE__ . " linje " . __LINE__);
	while ($product = db_fetch_array($query)) {
		if (in_array($product['id'],$itemId)) { #added 20210307
			$productGroup = $product['gruppe'];
			$productNumber = $product['varenr'];
			$productId = $product['id'];
			$qtxt = "select ordrelinjer.momssats from ordrelinjer,ordrer where varenr = '$productNumber'";
			$qtxt.= "and ordrer.id=ordrelinjer.ordre_id and ordrer.fakturadate = '$date'";
#cho "$qtxt<br>";		
			$vat = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))['momssats'];
			$qtxt = "select beskrivelse from grupper where kodenr = '$productGroup' and art = 'VG'";
#cho "$qtxt<br>";		
			$description = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))['beskrivelse'];

			if (!empty($description)) {
				$groupArray = setCountAndTotal($description, $productId, $productGroup, $groupArray, $idArray, $vat,$date);
			}
		}
	}
	$groupArray = subtractItemGroupData("Item group description", $groupArray);
	return $groupArray;
}


function setCountAndTotal($description, $productId, $productGroup, $groupArray, $idArray, $vat, $date) {
	$groupArray[$productGroup]['description'] = $description;
	$salesCount = 0;
	$salesPrice = 0;
	$qtxt = "select * from batch_salg where vare_id='$productId' and fakturadate = '$date'";
	$salesQuery = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($batchSale = db_fetch_array($salesQuery)) {
		if (in_array($batchSale['ordre_id'], $idArray)) {
			$salesCount += $batchSale['antal'];
			$salesPrice += $batchSale['pris'] * $batchSale['antal'];
		}
	}
	$salesPrice = truncate_number(($salesPrice / (100)) * (100+$vat));
	$salesPrice = round($salesPrice/5) * 5;
	$groupArray[$productGroup]['count'] += ($salesCount > 0) ? $salesCount : 0;
	$groupArray[$productGroup]['sellPrice'] += $salesPrice;
	return $groupArray;
}


function paymentMethods($kasse,$date) {
	$payment='';
	$paymentArray[$payment]=array();
	$total = 0;
	$orderQuery = db_select("select * from ordrer where felt_5='$kasse' and fakturadate = '$date'", __FILE__ . " linje " . __LINE__);
	while ($order = db_fetch_array($orderQuery)) {
		$payment = $order['felt_1'];
		if ($payment != '') {
			$paymentArray[$payment]['payment'] = $payment;
			if (isset($paymentArray[$payment]['price'])) $paymentArray[$payment]['price'] += $order['betalt'];
			else $paymentArray[$payment]['price'] = $order['betalt'];
			$total += $order['betalt'];
		}
	}

    $paymentArray['sum']['payment'] = "SUM";
    $paymentArray['sum']['price'] = $total;

    $paymentArray = subtractData("Payment method", $paymentArray);
    return $paymentArray;
}

function vatPayments($kasse,$date) {
    $vat;
    $idArray = makeOrderIdArray($kasse,$date);
#    $qtxt = "select * from ordrelinjer"; # made by LSN
    $qtxt = "select ordrelinjer.* from ordrelinjer,ordrer where ordrer.id=ordrelinjer.ordre_id and ordrer.fakturadate =  '$date'";
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
    $vatArray = subtractData("Vat payment", $vatArray);
    return $vatArray;
}


function drawCount($kasse)
{
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

function calculateTurnover($kasse,$date) {
		$turnover=$totalVat=0;
		$qtxt = "select * from ordrer where felt_5 ='$kasse' and fakturadate = '$date'";
    $ordreQuery = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    while ($order = db_fetch_array($ordreQuery)) {
      $tempOrdreId = $order['id'];
      $price = 0;
      $number = 0;
      $qtxt = "select pris, antal, rabat, momssats from ordrelinjer where ordre_id='$tempOrdreId'";
      $orderLineQuery = db_select($qtxt, __FILE__ . " linje " . __LINE__);
      while ($orderLine = db_fetch_array($orderLineQuery)) {
				$amount = $orderLine['pris'] * $orderLine['antal']-($orderLine['pris'] * $orderLine['antal'] * $orderLine['rabat'] / 100);
        $turnover += $amount ;
        $totalVat += ($amount * $orderLine['momssats']  / 100);
#        $turnoverWithVat += ($batch['pris'] * $batch['antal']) + ($moms/100 * ($batch['pris'] * $batch['antal']));
      }
    }
 		$turnoverWithVat = $turnover + $totalVat;
#		return 	[$turnover,$turnoverWithVat];
    return ["turnoverWithoutVat" => subtractTurnover($turnover, false), 
            "turnoverWithVat" => subtractTurnover($turnoverWithVat, true)]; #reportSubtract.php
}

?>
