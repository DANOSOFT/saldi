<?php
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

function productGroupDescription() { # LN 20190212 Make varegrupper for the xRapport
    $query = db_select("select * from varer", __FILE__. "linje". __LINE__); 
    while ($product = db_fetch_array($query)) {
        $productGroup = $product['gruppe'];
        $productId = $product['id'];
//         alert("product group = $productGroup");
//         alert("product id = $productId");
        $vat = db_fetch_array(db_select("select momssats from ordrelinjer where varenr = '$productGroup'", __FILE__ . " linje " . __LINE__))['momssats'];
        
        $description = db_fetch_array(db_select("select beskrivelse from grupper where kodenr = '$productGroup' and art = 'VG'", __FILE__ . " linje " . __LINE__))['beskrivelse'];

        $groupArray[$productGroup]['description'] = $description;  # LN pos_ordre, xRapport
        
        if ($tempArr[$description] != 1) {
            $salesQuery = db_select("select * from batch_salg where vare_id='$productId'", __FILE__ . "linje" . __LINE__);
            while ($batchSale = db_fetch_array($salesQuery)) {
                $salesCount += $batchSale['antal'];
                $salesPrice = $batchSale['pris'] * $salesCount;
            }
            $salesPrice = truncate_number(($salesPrice / (100-$vat)) * 100);

            $groupArray[$productGroup]['count'] = isset($salesCount) ? "x$salesCount" : 0;
            $groupArray[$productGroup]['sellPrice'] += $salesPrice;        # Varegrupper 
        }
        $tempArr[$description] = 1;
    } 
    return $groupArray;
}


function paymentMethods() {
    $orderQuery = db_select("select * from ordrer",__FILE__ . " linje " . __LINE__);
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

function vatPayments() {
    $ordrelines = db_select("select * from ordrelinjer", __FILE__ . " linje " . __LINE__);
    $total = 0;
    while($order = db_fetch_array($ordrelines)) {
        $vat = $order['momssats'];
        if (isset($vat)) {
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


function receiptCount() {
    $receipts = db_select("select * from pos_betalinger", __FILE__ . " linje " . __LINE__);
    $receiptArray['numberOfReceipts'] = 0;
    while($receipt = db_fetch_array($receipts)) {
        $price = $receipt['amount'];
        if ($price >= 0) {
            $receiptArray['numberOfReceipts']++;
            $receiptArray['totalPrice'] += $price;
        }
    }
    return $receiptArray;
}


























?>

