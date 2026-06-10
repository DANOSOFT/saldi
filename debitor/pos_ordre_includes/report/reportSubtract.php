<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/report/reportSubtract.php --- lap 4.0.0 --- 2021.03.07 ---
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
// LN 20190306 Subtract current report from previous reports
// 20210307 PHR set varuous functions to return zero as calling functions når just count for 1 day.

function subtractItemGroupData($type, $dataArray) {
	foreach ($dataArray as &$index) {
		foreach($index as $subkey => &$value) {
			if (isset($value) && $subkey == "description") {
				$description = $value;
			} elseif (isset($description) && $subkey == "count") {
				$count = makeTotalCount($type, $description)['count'];
				$value = $value - $count;
			} elseif (isset($description) && $subkey == "sellPrice") {
				$total = makeTotalCount($type, $description)['total'];
				$value = $value - $total;
			}
		}
	}
	return $dataArray;
}

function makeTotalCount($type, $description) {
	$total = 0;
	$count = 0;
	/*
	$qtxt = " select SUM(count) from report where type='$type' and description = '$description'";
	$qtxt.= "and date >= '2019-01-01'";
	$reportQueryCount = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	$qtxt = "select SUM(total) from report where type='$type' and description = '$description'";
	$qtxt.= " and date >= '2019-01-01'";
	$reportQueryTotal = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	if ($report = db_fetch_array($reportQueryCount)) {
		$count = $report['sum'];
	}
	if ($report = db_fetch_array($reportQueryTotal)) {
		$total = $report['sum'];
	}
	*/
	return ['count' => $count, 'total' => $total];
}

function subtractTurnover($totalTurnover, $vat) {
	if ($vat == true) $qtxt = "select SUM(total) from report where type='Turnover with vat'";
	else $qtxt = "select SUM(total) from report where type='Turnover without vat'";
	$qtxt.= " and date >= '2021-01-01'";
	($r=db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)))?$oldTurnover = $r['sum']:$oldTurnover=0;
	$newTurnover = $totalTurnover;# - $oldTurnover; " 20210307 Removed OldTurnover
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

function makeTotal($type, $description) {
	$qtxt = "select SUM(total) from report where type='$type' and description='$description'";
#	$qtxt.= "and date >= '$date'";
	$reportQueryTotal = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    if ($report = db_fetch_array($reportQueryTotal)) {
        $total = $report['sum'];
    }
		$total=0; #20210307
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

function makeTotalReceipt($type, $field) {
    if ($field == "total") {
			$qtxt = "select SUM(total) from report where type='$type'";
#			$qtxt.= "and date = '$date'";
			$reportQueryTotal = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        if ($report = db_fetch_array($reportQueryTotal)) {
            $returnValue = $report['sum'];
        }
    } elseif ($field == "count") {
				$qtxt = "select SUM(count) from report where type='$type'";
#			$qtxt.= "and date = '$date'";
        $reportQueryCount = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        if ($report = db_fetch_array($reportQueryCount)) {
            $returnValue = $report['sum'];
        }
    }
    $returnValue = 0; #20210307
    return $returnValue;
}

?>
