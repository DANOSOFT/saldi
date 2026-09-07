<?php
// --- debitor/pos_ordre_includes/boxCountMethods/cashCountSnapshot.php --- patch 5.0.1 --- 2026.09.07 ---
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260907 CDX/PHR Detect changed sales before approving a cash count.

/**
 * Fingerprint calculated balances, independently of the amounts counted by the cashier.
 *
 * @param array<string, array|string> $sales findBoxSale results keyed by currency.
 * @return string
 */
function cashCountSignature($sales) {
	$balances = array();
	foreach ($sales as $currency => $sale) {
		if (!is_array($sale)) {
			$balances[$currency] = $sale;
			continue;
		}
		$balances[$currency] = array(
			(float)$sale[0], (float)$sale[1],
			(string)$sale[4], (string)$sale[5],
			array_map('floatval', explode(chr(9), (string)$sale[6])),
			(float)$sale[7]
		);
	}
	ksort($balances);
	return hash('sha256', serialize($balances));
}

/** @return bool Whether the form still represents the current calculated balances. */
function cashCountIsCurrent($register, $baseCurrency, $signature) {
	if (!is_string($signature) || $signature === '') {
		return false;
	}
	include_once(__DIR__ . '/findBoxSale.php');
	$currencies = array($baseCurrency);
	$q = db_select("select box1 from grupper where art = 'VK' and box4 = '1' order by box1", __FILE__ . ' line ' . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['box1']) {
			$currencies[] = $r['box1'];
		}
	}
	$sales = array();
	foreach (array_unique($currencies) as $currency) {
		$sales[$currency] = findBoxSale((int)$register, 0, db_escape_string($currency));
	}
	return hash_equals(cashCountSignature($sales), $signature);
}
