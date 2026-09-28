<?php
// --- lager/lagerstatusValue.php --- patch 5.0.0 --- 2026-09-08 ---
// 20260908 CDX/LH Cancel linked purchase credits before valuing the remaining stock.

/**
 * Build the purchase history query using the report's item, warehouse and date scope.
 * A null cutoff preserves the report's current-date inclusion of future deliveries.
 *
 * @param int $itemId
 * @param int $warehouse Zero selects all warehouses.
 * @param string $dateType Report date selection: levdate or fakturadate.
 * @param string|null $cutoffDate ISO date, or null for the current report date.
 * @return string
 */
function lagerstatusPurchaseValueSql($itemId, $warehouse, $dateType, $cutoffDate)
{
	$itemId = (int)$itemId;
	$warehouse = (int)$warehouse;
	$sql = "select bk.id,bk.linje_id,bk.antal,bk.pris,ol.kred_linje_id from batch_kob bk ";
	$sql .= "left join ordrelinjer ol on ol.id=bk.linje_id where bk.vare_id=$itemId";
	if ($warehouse) {
		$sql .= " and bk.lager=$warehouse";
	}
	if ($cutoffDate !== null) {
		// The page supplies usdate() output. Cast its components before SQL interpolation.
		if (!preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $cutoffDate)) {
			throw new InvalidArgumentException('Stock valuation cutoff must be an ISO date.');
		}
		list($year, $month, $day) = array_map('intval', explode('-', $cutoffDate));
		$cutoffDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
		$column = $dateType === 'fakturadate' ? 'fakturadate' : 'kobsdate';
		$sql .= " and bk.$column <= '$cutoffDate'";
	}
	return $sql . " order by bk.kobsdate desc, bk.id desc";
}

/**
 * Value stock from purchase batches already ordered newest first and scoped by the report.
 * Linked negative quantities first cancel receipts on their original order line, including
 * split deliveries, so a partial stock valuation cannot retain half a cancelled purchase.
 * Positive copied lines may also have kred_linje_id; they are receipts, not credits.
 *
 * Unlinked credits, missing original receipts and any excess credited quantity retain the
 * existing signed-walk behavior. There is no reliable original purchase to cancel in those
 * cases; this function deliberately does not infer a match from price or chronology.
 *
 * @param array<int, array{id: int|string, linje_id: int|string|null, antal: float|string|null,
 *     pris: float|string|null, kred_linje_id: int|string|null}> $batches
 * @param float $remainingStock
 * @return float Purchase value before the report's display rounding.
 */
function lagerstatusPurchaseValue($batches, $remainingStock)
{
	if ($remainingStock <= 0) {
		return 0.0;
	}

	$receiptsByLine = array();
	foreach ($batches as $index => $batch) {
		$batches[$index]['antal'] = (float)$batch['antal'];
		$lineId = (int)$batch['linje_id'];
		if ($batch['antal'] > 0 && $lineId > 0) {
			$receiptsByLine[$lineId][] = $index;
		}
	}
	foreach ($batches as $creditIndex => $credit) {
		$originalLine = (int)$credit['kred_linje_id'];
		if ($credit['antal'] >= 0 || !isset($receiptsByLine[$originalLine])) {
			continue;
		}
		$quantity = -$credit['antal'];
		foreach ($receiptsByLine[$originalLine] as $receiptIndex) {
			$cancelled = min($quantity, $batches[$receiptIndex]['antal']);
			// batch_kob quantities are numeric(15,3); avoid fractional subtraction residue.
			$batches[$receiptIndex]['antal'] = round($batches[$receiptIndex]['antal'] - $cancelled, 3);
			$quantity = round($quantity - $cancelled, 3);
			if ($quantity <= 0) {
				break;
			}
		}
		$batches[$creditIndex]['antal'] = -$quantity;
	}

	$quantity = 0.0;
	$value = 0.0;
	foreach ($batches as $batch) {
		if ($quantity + $batch['antal'] <= $remainingStock) {
			$quantity += $batch['antal'];
			$value += $batch['antal'] * (float)$batch['pris'];
		} elseif ($quantity < $remainingStock) {
			$value += ($remainingStock - $quantity) * (float)$batch['pris'];
			$quantity = $remainingStock;
		}
		if ($quantity >= $remainingStock) {
			break;
		}
	}
	return $quantity ? $value : 0.0;
}
