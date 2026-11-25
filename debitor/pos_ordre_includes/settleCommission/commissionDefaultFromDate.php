<?php
// --- debitor/pos_ordre_includes/settleCommission/commissionDefaultFromDate.php --- ver 5.1.0 --- 2026.11.09 ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261109 CL/SS SST-919 Default first day of the cash count commission settlement.

/**
 * The date of the register's last settled sale, or the configured first date when that is later.
 * Sales already settled on that day are skipped by their settletime.
 *
 * @param int    $kasse       Cash register number (ordrer.felt_5).
 * @param string $settingDate items/commissionFromDate as Y-m-d, or '' when not set.
 * @return string Y-m-d, or '' when there is neither a setting nor a settled sale.
 */
function commissionDefaultFromDate($kasse, $settingDate) {
	$fromDate = (string)$settingDate;
	$qtxt = "select max(fakturadate) as settled from ordrer where settletime > '0' and felt_5 = '" . (int)$kasse . "'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if ($r && $r['settled'] && $r['settled'] > $fromDate) {
		$fromDate = $r['settled'];
	}
	return $fromDate;
}
