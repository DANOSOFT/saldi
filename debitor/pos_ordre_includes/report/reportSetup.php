<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/report/reportSetup.php --- ver 5.0.0 --- 2026.10.27 ---
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
// Copyright (c) 2019-2026 Danosoft ApS
// ----------------------------------------------------------------------
// LN 20190306 Make the most basic setup for the report
// 20210307 PHR Added $date to allmost all functions as it was countion all orders from day zero
// 20261027 CL/MMR SST-897 The Z-report locks the register's proforma row before it reads the data and keeps the lock until the report is saved.
//                 A second Z-report for the same register waits and reads the counter after the first one took its numbers off.


include("pos_ordre_includes/report/rapportData.php"); #20190219
include("pos_ordre_includes/report/rapportReceiptData.php"); #20190219
include("pos_ordre_includes/report/reportSubtract.php"); #20190219
include("pos_ordre_includes/report/saveReport.php"); #20190219
include("pos_ordre_includes/report/printReportFunc.php");
include("pos_ordre_includes/report/printFunctions.php");
include("pos_ordre_includes/report/printFuncII.php");

function setupReport($type, $kasse) {
	$date = date("Y-m-d");
	if (in_array($type, ['zRapport', 'xRapport'])) { #Make array to the report
		if ($type == 'zRapport') {
			// The proforma counter is read here and taken off in saveLastReport(). Lock the register's row first, so a
			// second Z-report or a proforma print (proformaCount()) for this register waits until this report is saved.
			transaktion('begin');
			db_select("select id from proforma where id = '" . (int)$kasse . "' for update", __FILE__ . " linje " . __LINE__);
		}
		$reportArray = retrieveReportData($kasse,$date);
		if ($type == 'zRapport') {
			$_SESSION['zreport'] = "disabled";
			saveLastReport($reportArray);
			transaktion('commit');
		}
		return $reportArray;
	}
}


function retrieveReportData($kasse,$date) {
	$turnoverValues = calculateTurnover($kasse,$date);
	return ['groupArray' => productGroupDescription($kasse,$date), 'paymentArray' => paymentMethods($kasse,$date),
					'vatArray' => vatPayments($kasse,$date), 'receiptArray' => receiptCount($kasse,$date),
					'drawArray' => drawCount($kasse), 'copiedReceiptsArray' => copiedReceipts($kasse,$date),
					'turnoverWithoutVat' => $turnoverValues['turnoverWithoutVat'],
					'turnoverWithVat' => $turnoverValues['turnoverWithVat'],
					'proformaReceiptsArray' => proformaReceipts($kasse),
					'returnedReceiptsArray' => returnedReceipts($kasse),
					'discountArray' => ordersWithDiscount($kasse,$date),
					'cancelledOrderArray' => cancelledOrders($kasse),
					'correctionArray' => correctionOrders($kasse),
					'priceCorrectionArray' => priceCorrectionOrders($kasse,$date)];
}

function getReportType($key) {
	switch($key) {
		case "groupArray":            return "Item group description";
		case "paymentArray":          return "Payment method";
		case "vatArray":              return "Vat payment";
		case "turnoverWithoutVat":    return "Turnover without vat";
		case "receiptArray":          return "Receipt";
		case "drawArray":             return "Draw opening";
		case "copiedReceiptsArray":   return "Copied Receipt";
		case "proformaReceiptsArray": return "Proforma receipt";
		case "returnedReceiptsArray": return "Returned receipt";
		case "discountArray":         return "Discount receipt";
		case "cancelledOrderArray":   return "Cancelled receipt";
		case "correctionArray":       return "Corrected receipt";
		case "priceCorrectionArray":  return "Corrected price";
		case "saleWithoutVatArray":   return "Sale without vat";
	}
}

function getReportId() {
	$id = 0;
	$queryCheck = db_select("select id from report", __FILE__ . "linje" . __LINE__);
	$reportCheck = db_fetch_array($queryCheck);
	if (isset($reportCheck)) {
		$reports = db_select("select id from report", __FILE__ . "linje" . __LINE__);
		while ($report = db_fetch_array($reports)) {
			$id = ($report['id'] > $id) ? $report['id'] : $id;
		}
		return $id + 1;
	} else {
		return 0;
	}
}

function getReportNumber($header) {
	if (isset($_SESSION['reportNumber'])) {
		return $_SESSION['reportNumber'];
	} elseif ($header == true) {
		$query = db_fetch_array(db_select("select MAX(report_number) from report", __FILE__ . "linje" . __LINE__));
		$reportNumber = $query['max'];
		return $reportNumber;
	} else {
		$reportNumber = 0;
		$queryCheck = db_select("select * from report", __FILE__ . "linje" . __LINE__);
		$reportCheck = db_fetch_array($queryCheck);
		if (isset($reportCheck)) {
			$reports = db_select("select * from report", __FILE__ . "linje" . __LINE__);
			while ($report = db_fetch_array($reports)) {
				$reportNumber = $report['report_number'];
			}
			$_SESSION['reportNumber'] = $reportNumber + 1;
			return $reportNumber + 1;
		} else {
			return 0;
		}
	}
 }

function setReportType($xReport, $zReport) {
	if($xReport == true) {
		$reportVar = "xRapport";
	} elseif ($zReport == true) {
		$reportVar = "zRapport";
	}
	return $reportVar;
}

?>
