<?php
// --- debitor/pos_ordre_includes/boxCountMethods/assignCashReport.php --- patch 5.0.1 --- 2026.09.07 ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260907 CDX/PHR Link all included orders, including previously posted sales, to the cash report.

/** @return void */
function assignCashReport($orderIds, $reportNumber) {
	$reportNumber = (int)$reportNumber;
	$orderIds = array_filter(array_unique(array_map('intval', $orderIds)), function($id) { return $id > 0; });
	if (!$orderIds || $reportNumber <= 0) {
		return;
	}
	$qtxt = "update ordrer set report_number = '$reportNumber' where id in (" . implode(',', $orderIds) . ") ";
	$qtxt.= "and coalesce(report_number, 0) = 0";
	db_modify($qtxt, __FILE__ . ' line ' . __LINE__);
}
