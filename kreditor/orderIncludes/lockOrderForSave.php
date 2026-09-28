<?php
// 20260908 CDX/LH Serialize creditor saves and splits with receipt/posting (SST-765).

/**
 * Lock existing headers in id order before any save/split writes.
 * The caller owns the transaction and must commit or roll back after this call.
 *
 * @param int $orderId Source order, or zero when creating an order.
 * @param int|string $expectedStatus Status submitted with the source form.
 * @param int|null $destinationId Null for a normal save, zero for a split to a new order.
 * @param bool $allowPosted Whether copying/crediting a posted source is allowed.
 * @param array $lineIds Submitted source line ids; reject lines moved since the form opened.
 * @return bool Whether the locked orders still permit the requested operation.
 */
function lockCreditorOrderForSave($orderId, $expectedStatus, $destinationId = null, $allowPosted = false, $lineIds = array()) {
	$sourceLineIds = array();
	if ($lineIds === null || $lineIds === '') $lineIds = array();
	if (!is_array($lineIds)) {
		return false;
	}
	foreach ($lineIds as $lineId) {
		if ($lineId === '' || $lineId === null) {
			continue;
		}
		$lineId = filter_var($lineId, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)));
		if ($lineId === false) {
			return false;
		}
		if ($lineId) {
			$sourceLineIds[$lineId] = $lineId;
		}
	}
	$orderId = (int)$orderId;
	if ($orderId < 0) {
		return false;
	}
	if (!$orderId) {
		return $destinationId === null && !$sourceLineIds && !$allowPosted;
	}
	// Copy/credit allocates an order number later. Take its allocator lock before
	// row locks so another posting cannot deadlock with a table-lock upgrade.
	if ($allowPosted) {
		db_modify("LOCK TABLE ordrer IN EXCLUSIVE MODE", __FILE__ . " linje " . __LINE__);
	}
	$orderIds = array($orderId);
	if ($destinationId !== null) {
		$destinationId = (int)$destinationId;
		if ($destinationId < 0 || $destinationId === $orderId) {
			return false;
		}
		if ($destinationId) {
			$orderIds[] = $destinationId;
		}
	}
	sort($orderIds, SORT_NUMERIC);
	$qtxt = "select id, status, art from ordrer where id in (" . implode(',', $orderIds) . ") order by id for update";
	$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	$orders = array();
	while ($row = db_fetch_array($query)) {
		$orders[(int)$row['id']] = $row;
	}
	if (!isset($orders[$orderId]) || $orders[$orderId]['status'] != $expectedStatus) {
		return false;
	}
	if (!$allowPosted && $orders[$orderId]['status'] > 2) {
		return false;
	}
	if ($sourceLineIds) {
		$qtxt = "select count(*) as line_count from ordrelinjer where ordre_id = $orderId and id in (" . implode(',', $sourceLineIds) . ")";
		$row = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if (!$row || (int)$row['line_count'] !== count($sourceLineIds)) {
			return false;
		}
	}
	if ($destinationId) {
		return isset($orders[$destinationId]) && $orders[$destinationId]['status'] < 3
			&& in_array($orders[$destinationId]['art'], array('KO', 'KK'), true);
	}
	return true;
}
