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
 * @param int|string $ordreId ordrer.id
 * @return string Date as Y-m-d.
 */
function default_invoice_date($ordreId) {
	$ordreId = (int)$ordreId;
	if ($ordreId > 0) {
		$qtxt = "select ordrer.ordredate from ordrer, shop_ordrer ";
		$qtxt .= "where shop_ordrer.saldi_id = ordrer.id and ordrer.id = '$ordreId'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r && strlen((string)$r['ordredate']) >= 10) {
			return substr($r['ordredate'], 0, 10);
		}
	}
	return date("Y-m-d");
}
