<?php
// 20261022 CDX/LH Preserve stored Stripe delivery across legacy mail flags, local printing and partial order saves.

/**
 * Resolve legacy delivery flags without overriding a stored automatic-payment instruction.
 *
 * @return string Selected delivery mode.
 */
function orderDeliveryValue($stored, $mail, $oioubl, $pbs) {
	if ($stored === 'Stripe') {
		return 'Stripe';
	}
	if ($mail) {
		$stored = 'email';
	}
	if ($oioubl) {
		$stored = 'oioubl';
	}
	if ($pbs) {
		$stored = 'PBS';
	}
	return $stored ?: 'PDF';
}

/**
 * A partial save keeps the stored mode; only an explicit nonempty choice can replace Stripe.
 *
 * @param array $input Order form fields.
 * @param int $id Existing order id.
 * @return string Unescaped delivery value for the caller to escape before its existing UPDATE.
 */
function orderDeliveryPostedValue($input, $id) {
	if (isset($input['udskriv_til']) && is_string($input['udskriv_til']) && $input['udskriv_til'] !== '') {
		return $input['udskriv_til'];
	}
	$r = db_fetch_array(db_select('select udskriv_til from ordrer where id = ' . (int)$id, __FILE__ . ' linje ' . __LINE__));
	return (string)($r['udskriv_til'] ?? 'PDF');
}

/**
 * Offer Stripe to enrolled debtors and always render a stored Stripe instruction as selected.
 *
 * @return string HTML option, or an empty string for an unenrolled debtor using another mode.
 */
function orderStripeDeliveryOption($selected, $kontoId) {
	$stored = $selected === 'Stripe';
	if (!$stored) {
		$column = db_fetch_array(db_select("select column_name from information_schema.columns where table_name = 'adresser' and column_name = 'stripe'", __FILE__ . ' linje ' . __LINE__));
		if (!$column || (int)$kontoId <= 0) {
			return '';
		}
		$d = db_fetch_array(db_select('select stripe from adresser where id = ' . (int)$kontoId, __FILE__ . ' linje ' . __LINE__));
		if (($d['stripe'] ?? '') !== 'on') {
			return '';
		}
	}
	return "<option value='Stripe'" . ($stored ? ' selected' : '')
		. " title='Trækkes automatisk på kundens kort på forfaldsdatoen. Ingen fakturamail.'>Stripe</option>\n";
}
