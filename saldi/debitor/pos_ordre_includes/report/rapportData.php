<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/report/rapportData.php ---------- lap 3.7.4----2019.05.08-------
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
// LN 20190312 Make functions that retrieve all type of payments and sold data to the report

function productGroupDescription($kasse) { # LN 20190212 Make varegrupper for the xRapport
    $idArray = makeOrderIdArray($kasse);
    $query = db_select("select * from varer", $queryVar);
    while ($product = db_fetch_array($query)) {
        $productGroup = $product['gruppe'];
        $productNumber = $product['varenr'];
        $productId = $product['id'];
        $vat = db_fetch_array(db_select("select momssats from ordrelinjer where varenr = '$productNumber'", $queryVar))['momssats'];

        $description = db_fetch_array(db_select("select beskrivelse from grupper where kodenr = '$productGroup' and art = 'VG'", $queryVar))['beskrivelse'];

        if (!empty($description)) {
           $groupArray = setCountAndTotal($description, $productId, $productGroup, $groupArray, $idArray, $vat);
        }
    }
    $groupArray = subtractItemGroupData("Item group description", $groupArray);
    return $groupArray;
}

function setCountAndTotal($description, $productId, $productGroup, $groupArray, $idArray, $vat)
{
    $groupArray[$productGroup]['description'] = $description;
    $salesCount = 0;
    $salesPrice = 0;
    if ($tempArr[$description] != 1) {
        $salesQuery = db_select("select * from batch_salg where vare_id='$productId'", $queryVar);
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
    }
    $tempArr[$description] = 1;
    return $groupArray;
}


function paymentMethods($kasse) {
    $payment;
    $orderQuery = db_select("select * from ordrer where felt_5='$kasse'", $queryVar);
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

function vatPayments($kasse) {
    $vat;
    $idArray = makeOrderIdArray($kasse);
    $ordrelines = db_select("select * from ordrelinjer", $queryVar);
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
    $drawer = db_select("select * from drawer where id='$kasse'", $queryVar);
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

function calculateTurnover($kasse)
{
    $ordreQuery = db_select("select * from ordrer where felt_5 ='$kasse'", $queryVar);
    while ($order = db_fetch_array($ordreQuery)) {
      $tempOrdreId = $order['ordrenr'];
      $price = 0;
      $number = 0;
      $orderLineQuery = db_select("select pris, antal, momssats from ordrelinjer where ordre_id='$tempOrdreId'", $queryVar);
      while ($orderLine = db_fetch_array($orderLineQuery)) {
        $turnover += $orderLine['pris'] * $orderLine['antal'];
        $totalVat += (($orderLine['pris'] * $orderLine['antal']) / 100 * $orderLine['momssats']);
        $turnoverWithVat += ($batch['pris'] * $batch['antal']) + ($moms/100 * ($batch['pris'] * $batch['antal']));
      }
    }
    $turnoverWithVat = $turnover + $totalVat;
    return ["turnoverWithoutVat" => subtractTurnover($turnover, false),
            "turnoverWithVat" => subtractTurnover($turnoverWithVat, true)];
}

?>
