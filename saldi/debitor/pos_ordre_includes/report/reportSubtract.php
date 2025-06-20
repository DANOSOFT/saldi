<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/report/reportSubtract.php ---------- lap 3.7.4----2019.05.08-------
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
// LN 20190306 Subtract current report from previous reports

function subtractItemGroupData($type, $dataArray) {
    foreach ($dataArray as &$index) {
        foreach($index as $subkey => &$value) {
            if ($subkey == "description") {
                $description = $value;
            } elseif ($subkey == "count") {
                $count = makeTotalCount($type, $description)['count'];
                $value = $value - $count;
            } elseif ($subkey == "sellPrice") {
                $total = makeTotalCount($type, $description)['total'];
                $value = $value - $total;
            }
        }
    }
    return $dataArray;
}

function makeTotalCount($type, $description)
{
    $total = 0;
    $count = 0;
    $reportQueryCount = db_select("select SUM(count) from report where type='$type' and description = '$description'", $queryVar);
    $reportQueryTotal = db_select("select SUM(total) from report where type='$type' and description = '$description'", $queryVar);
    if ($report = db_fetch_array($reportQueryCount)) {
        $count = $report['sum'];
    }
    if ($report = db_fetch_array($reportQueryTotal)) {
        $total = $report['sum'];
    }
    return ['count' => $count, 'total' => $total];
}

function subtractTurnover($totalTurnover, $vat)
{
    if ($vat == true) {
      $oldTurnover = db_fetch_array(db_select("select SUM(total) from report where type='Turnover with vat'", $queryVar))['sum'];
    } else {
      $oldTurnover = db_fetch_array(db_select("select SUM(total) from report where type='Turnover without vat'", $queryVar))['sum'];
    }
    $newTurnover = $totalTurnover - $oldTurnover;
    return $newTurnover;
}

function subtractData($type, $dataArray) {
    foreach ($dataArray as &$index) {
        foreach($index as $subkey => &$value) {
            if (in_array($subkey, ['payment', 'vat', 'kasse', 'count', 'numberOfReceipts'])) {
                $description = $value;
            } elseif (in_array($subkey, ['price', 'pris', 'openings', 'totalPrice', 'discount'])) {
                $total = makeTotal($type, $description);
                $value = number_format($value, 2, '.', '');
                $total = number_format($total, 2, '.', '');
                $value = $value - $total;
            }
        }
    }
    return $dataArray;
}

function makeTotal($type, $description)
{
    $reportQueryTotal = db_select("select SUM(total) from report where type='$type' and description='$description'", $queryVar);
    if ($report = db_fetch_array($reportQueryTotal)) {
        $total = $report['sum'];
    }
    return $total;
}

function subtractReceiptData($type, $dataArray) {
    foreach ($dataArray as $key => &$value) {
        if ($key == 'count') {
            $count = makeTotalReceipt($type, "count");
            $value = $value - $count;
        } elseif ($key == 'totalPrice') {
           $total = makeTotalReceipt($type, "total");
           if ($value < 0) {
                $value = round(($value - (-$total)), 2);
            } else {
                $value = $value - $total;
            }
        }
    }
    return $dataArray;
}

function makeTotalReceipt($type, $field)
{
    if ($field == "total") {
        $reportQueryTotal = db_select("select SUM(total) from report where type='$type'", $queryVar);
        if ($report = db_fetch_array($reportQueryTotal)) {
            $returnValue = $report['sum'];
        }
    } elseif ($field == "count") {
        $reportQueryCount = db_select("select SUM(count) from report where type='$type'", $queryVar);
        if ($report = db_fetch_array($reportQueryCount)) {
            $returnValue = $report['sum'];
        }
    }
    return $returnValue;
}

?>
