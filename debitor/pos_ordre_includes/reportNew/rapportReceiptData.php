<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/report/rapportReceiptData.php ---------- lap 3.7.4----2019.05.08-------
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
// LN 20190312 Make functions that handles all data that deals with the receipts

function receiptCount($kasse) {
    $idArray = makeOrderIdArray($kasse);
    $receipts = db_select("select * from pos_betalinger where amount>'0'", __FILE__ . " linje " . __LINE__);
    $receiptArray['count'] = 0;
    while($receipt = db_fetch_array($receipts)) {
        $price = $receipt['amount'];
        $price = abs($price);
        if (in_array($receipt['ordre_id'], $idArray) ) {
            $receiptArray['count']++;
            $receiptArray['totalPrice'] += $price;
        }
    }
    $receiptArray = subtractReceiptData("Receipt", $receiptArray);
    return $receiptArray;
}

function copiedReceipts($kasse)
{
    $orders = db_select("select * from ordrer where felt_5='$kasse'", __FILE__ . " linje " . __LINE__);
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

function ordersWithDiscount($kasse)
{
    $idArray = makeOrderIdArray($kasse);
    $ordrelines = db_select("select * from ordrelinjer where rabat>'0'", __FILE__ . "linje" . __LINE__);
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

function cancelledOrders($kasse)
{
    $delOrder = db_select("select * from deleted_order where kasse='$kasse'", __FILE__ . "linje" . __LINE__);
    $deletedArray['count'] = 0;
    $deletedArray['totalPrice'] = 0;
    while ($order = db_fetch_array($delOrder)) {
        $price = number_format($order['price'], 2, ',', '');
        $price = abs($price);
        $deletedArray['count']++;
        $deletedArray['totalPrice'] += $price;
    }
    $deletedArray = subtractReceiptData("Cancelled receipt", $deletedArray);
    return $deletedArray;
}

function correctionOrders($kasse)
{
    $corrections = db_select("select * from corrections where kasse='$kasse'", __FILE__ . "linje" . __LINE__);
    $correctedArray['count'] = 0;
    $correctedArray['totalPrice'] = 0;
    while ($order = db_fetch_array($corrections)) {
        $price = number_format($order['price'], 2, ',', '');
        $price = abs($price);
        $correctedArray['count']++;
        $correctedArray['totalPrice'] += $price;
    }
    $correctedArray = subtractReceiptData("Corrected receipt", $correctedArray);
    return $correctedArray;
}

function priceCorrectionOrders($kasse)
{
    $priceCorrections = db_select("select * from price_correction where kasse='$kasse'", __FILE__ . "linje" . __LINE__);
    $priceCorrectionArray['count'] = 0;
    $priceCorrectionArray['totalPrice'] = 0;
    while ($order = db_fetch_array($priceCorrections)) {
        $price = number_format($order['price'], 2, ',', '');
        $price = abs($price);
        $priceCorrectionArray['count']++;
        $priceCorrectionArray['totalPrice'] += $price;
    }
    $priceCorrectionArray = subtractReceiptData("Corrected price receipt", $priceCorrectionArray);
    return $priceCorrectionArray;
}

function saleWithoutVat($kasse)
{
    $idArray = makeOrderIdArray($kasse);
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

function proformaReceipts($kasse)
{
    $proformaQuery = db_select("select * from proforma where id='$kasse'", __FILE__ . " linje " . __LINE__);
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
