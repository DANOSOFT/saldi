<?php
PHR 20210307 Is this file used at all ??
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre.php ---------- lap 3.7.4----2019.01.07-------
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
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190219 Make functions to print the different parts of the X-rapport

function makeOrderIdArray($kasse)
{
    $orderQuery = db_select("select ordrenr from ordrer where felt_5 = '$kasse'", __FILE__ . "linje" . __LINE__);
    while ($order = db_fetch_array($orderQuery)) {
        $ordreIdArr[] = $order['ordrenr'];
    }
    ksort($ordreIdArr);
    return $ordreIdArr;
}

function productGroupDescription($kasse) { # LN 20190212 Make varegrupper for the xRapport
    $idArray = makeOrderIdArray($kasse);
    $query = db_select("select * from varer", __FILE__. "linje". __LINE__); 
    while ($product = db_fetch_array($query)) {
        $productGroup = $product['gruppe'];
        $productId = $product['id'];
        $vat = db_fetch_array(db_select("select momssats from ordrelinjer where varenr = '$productGroup'", __FILE__ . " linje " . __LINE__))['momssats'];
        
        
        $description = db_fetch_array(db_select("select beskrivelse from grupper where kodenr = '$productGroup' and art = 'VG'", __FILE__ . " linje " . __LINE__))['beskrivelse'];

        $groupArray[$productGroup]['description'] = $description;  # LN pos_ordre, xRapport
        
        if ($tempArr[$description] != 1) {
            $salesQuery = db_select("select * from batch_salg where vare_id='$productId'", __FILE__ . "linje" . __LINE__);
            while ($batchSale = db_fetch_array($salesQuery)) {
                if (in_array($batchSale['ordre_id'], $idArray)) {
                    $salesCount += $batchSale['antal'];
                    $salesPrice = $batchSale['pris'] * $salesCount;
                }
            }
            $salesPrice = truncate_number(($salesPrice / (100-$vat)) * 100);

            $groupArray[$productGroup]['count'] = isset($salesCount) ? "x$salesCount" : 0;
            $groupArray[$productGroup]['sellPrice'] += $salesPrice;        # Varegrupper 
        }
        $tempArr[$description] = 1;
    } 
    return $groupArray;
}


function paymentMethods($kasse) {
    $orderQuery = db_select("select * from ordrer where felt_5='$kasse'",__FILE__ . " linje " . __LINE__);
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
    return $paymentArray;
}

function vatPayments($kasse) {
    $idArray = makeOrderIdArray($kasse);
    $ordrelines = db_select("select * from ordrelinjer", __FILE__ . " linje " . __LINE__);
    $total = 0;
    while($order = db_fetch_array($ordrelines)) {
        $vat = $order['momssats'];
        if (isset($vat) && in_array($order['ordre_id'], $idArray)) {
            $price = $order['pris'] * $order['antal'];
            $vatArray[$vat]['vat'] = $vat;
            $vatArray[$vat]['price'] += $price;
            $total += $price;
        }
    }
    $vatArray['sum']['payment'] = "SUM";
    $vatArray['sum']['price'] = $total;
    return $vatArray;
}


function receiptCount($kasse) {
    $idArray = makeOrderIdArray($kasse);
    $receipts = db_select("select * from pos_betalinger", __FILE__ . " linje " . __LINE__);
    $receiptArray['numberOfReceipts'] = 0;
    while($receipt = db_fetch_array($receipts)) {
        $price = $receipt['amount'];
        if ($price >= 0 && in_array($receipt['ordre_id'], $idArray) ) {
            $receiptArray['numberOfReceipts']++;
            $receiptArray['totalPrice'] += $price;
        }
    }
    return $receiptArray;
}


function drawCount($kasse)
{
    $drawer = db_select("select * from drawer where id='$kasse'", __FILE__ . " linje " . __LINE__);
    while($draw = db_fetch_array($drawer)) {
        $box = $draw['id'];
        $drawArray[$box]['kasse'] = $box;
        $drawArray[$box]['openings'] = $draw['openings'];
    }
    return $drawArray;
}


function copiedReceipts($kasse)
{
    $orders = db_select("select * from ordrer where felt_5='$kasse'", __FILE__ . " linje " . __LINE__);
    $copiedArray['count'] = 0;
    $copiedArray['price'] = 0;
    while($order = db_fetch_array($orders)) {
        if ($order['copied'] == 't') {
            $copiedArray['count'] += 1;
            $copiedArray['price'] += $order['sum'];
        }
    }
    return $copiedArray;
}

function returnedReceipts($kasse)
{
    $orders = db_select("select * from ordrer where felt_5='$kasse'", __FILE__ . " linje " . __LINE__);
    $returnedArray['count'] = 0;
    $returnedArray['price'] = 0;
    while($order = db_fetch_array($orders)) {
        if ($order['sum'] < 0) {
            $returnedArray['count'] += 1;
            $returnedArray['price'] += $order['sum'];
        }
    }
    return $returnedArray;
}

function ordersWithDiscount($kasse)
{
    $idArray = makeOrderIdArray($kasse);
    $ordrelines = db_select("select * from ordrelinjer", __FILE__ . "linje" . __LINE__);    
    $discountArray['count'] = 0;
    $discountArray['discount'] = 0;
    while ($order = db_fetch_array($ordrelines)) {
        if ($order['rabat'] > 0 && in_array($order['ordre_id'], $idArray)) {
            $price = number_format($order['pris'], 2, ',', '');
            $discount = ($price / $order['rabat'] * 100) - $order['pris'];
            $discountArray['count'] += 1;
            $discountArray['discount'] += $discount;
        }
    }
    return $discountArray;
}   

function cancelledOrders($kasse)
{
    $delOrder = db_select("select * from deleted_order where kasse='$kasse'", __FILE__ . "linje" . __LINE__);
    $deletedArray['count'] = 0;
    $deletedArray['totalPrice'] = 0;
    while ($order = db_fetch_array($delOrder)) {
        $price = number_format($order['price'], 2, ',', '');
        $deletedArray['count']++;
        $deletedArray['totalPrice'] += $price;
    }
    return $deletedArray;
}

function correctionOrders($kasse)
{
    $corrections = db_select("select * from corrections where kasse='$kasse'", __FILE__ . "linje" . __LINE__);
    $correctedArray['count'] = 0;
    $correctedArray['totalPrice'] = 0;
    while ($order = db_fetch_array($corrections)) {
        $price = number_format($order['price'], 2, ',', '');
        $correctedArray['count']++;
        $correctedArray['totalPrice'] += $price;
    }
    return $correctedArray;
}

function priceCorrectionOrders($kasse)
{
    $priceCorrections = db_select("select * from price_correction where kasse='$kasse'", __FILE__ . "linje" . __LINE__);
    $priceCorrectionArray['count'] = 0;
    $priceCorrectionArray['totalPrice'] = 0;
    while ($order = db_fetch_array($priceCorrections)) {
        $price = number_format($order['price'], 2, ',', '');
        $priceCorrectionArray['count']++;
        $priceCorrectionArray['totalPrice'] += $price;
    }
    return $priceCorrectionArray;
}

function saleWithoutVat($kasse)
{
    $idArray = makeOrderIdArray($kasse);
    $salesQuery = db_select("select * from batch_salg", __FILE__ . "linje" . __LINE__);
    $saleWithoutVatArray['count'] = 0;
    $saleWithoutVatArray['totalPrice'] = 0;
    while($salesOrder = db_fetch_array($salesQuery)) {
        if (in_array($salesOrder['ordre_id'], $idArray)) {
            $price = number_format($salesOrder['pris'], 2, ',', '');
            $saleWithoutVatArray['count']++;
            $saleWithoutVatArray['totalPrice'] += $price;
        }
    }
    return $saleWithoutVatArray;
}



?>

