<?php
// --- debitor/orderIncludes/defaultInvoiceDate.php --- 2026-10-06 ---
// 20261006 CL/LH SST-802: Default invoice date for manual invoicing; webshop orders keep their order date.

/**
 * Invoice date to use when "Fakturér" is pressed with an empty Fakturadato field.
 *
 * Webshop orders (insert_shop_order() in api/rest_api.php) are created without a
 * fakturadate. The automatic path, fakturer_ordre(), falls back to ordredate, so the
 * manual path must do the same; otherwise the same kind of order ends up with the
 * purchase date or the booking date depending on which path happened to invoice it,
 * and can land in a different accounting period. Orders created in Saldi itself keep
 * today's date as before.
 *
 * A part split off a webshop order with "Opdel ordre" has no shop_ordrer row of its own but keeps
 * the ordrenr and art of the original, so the mapping is looked up through those as well.
 *
 * @param int|string $ordreId   ordrer.id
 * @param string     $ordredate Order date submitted in the same request (Y-m-d), used instead of the
 *                              stored one so an edited date and "Fakturer" in one submit agree.
 * @return string Date as Y-m-d.
 */
function default_invoice_date($ordreId, $ordredate = '') {
	$ordreId = (int)$ordreId;
	if ($ordreId > 0) {
		$qtxt = "select o.ordredate from ordrer o where o.id = '$ordreId' and exists (";
		$qtxt .= "select 1 from shop_ordrer s, ordrer o2 where s.saldi_id = o2.id and o2.ordrenr = o.ordrenr and o2.art = o.art)";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r) {
			if (preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$ordredate)) {
				return substr($ordredate, 0, 10);
			}
			if (preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$r['ordredate'])) {
				return substr($r['ordredate'], 0, 10);
			}
		}
	}
	return date("Y-m-d");
}
