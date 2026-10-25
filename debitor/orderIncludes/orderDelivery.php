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
		// 20261025 CL/ASR keep an explicit "E-mail med link"; an order without a choice gets the G6.3 default
		if ($stored !== 'Link') $stored = (($stored === '' || $stored === null) && orderLinkDefault()) ? 'Link' : 'email';
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

/**
 * 20261025 CL/ASR "E-mail med link" (Udskriv til = Link) next to the classic e-mail: available when the
 * invoice & payment page is switched on in this ledger.
 */
function orderLinkAvailable() {
	static $available = null;
	if ($available === null) {
		$available = false;
		if (is_file(__DIR__ . '/../../includes/stripeIncludes/stripeInvoice.php')) {
			include_once(__DIR__ . '/../../includes/stripeIncludes/stripeInvoice.php');
			$available = function_exists('stripe_invoice_link_available') && stripe_invoice_link_available();
		}
	}
	return $available;
}

/** G6.3 default for new e-mail orders is the link mail. */
function orderLinkDefault() {
	return orderLinkAvailable() && stripe_invoice_format() === 'link';
}

/**
 * The "E-mail med link" option; a stored Link order always renders it (selected), even if the page is off.
 *
 * @return string HTML option or ''.
 */
function orderLinkDeliveryOption($selected) {
	global $sprog_id;
	$stored = $selected === 'Link';
	if (!$stored && !orderLinkAvailable()) {
		return '';
	}
	return "<option value='Link'" . ($stored ? ' selected' : '') . " title=\"" . htmlspecialchars(findtekst('7125', $sprog_id), ENT_QUOTES, 'UTF-8') . "\">"
		. htmlspecialchars(findtekst('7124', $sprog_id), ENT_QUOTES, 'UTF-8') . "</option>\n";
}

/**
 * 20261025 CL/ASR When "E-mail med link" is chosen but this order cannot use the payment page, say so
 * next to the dropdown instead of silently mailing the classic PDF (e.g. Kontant terms, PBS, not DKK).
 *
 * @return string HTML notice or ''.
 */
function orderLinkNotice($selected, $orderId) {
	global $sprog_id;
	if ($selected !== 'Link' || (int)$orderId <= 0) {
		return '';
	}
	$reason = 'ledger';
	if (orderLinkAvailable()) {
		$o = db_fetch_array(db_select('select * from ordrer where id = ' . (int)$orderId, __FILE__ . ' linje ' . __LINE__));
		if (!$o) {
			return '';
		}
		$reason = stripe_invoice_uncollectable_reason(array('o' => $o, 'is_credit' => ($o['art'] ?? '') === 'DK'));
	}
	if ($reason === '') {
		return '';
	}
	$texts = array('ledger' => '7129', 'credit' => '7130', 'currency' => '7131', 'terms' => '7132', 'pbs' => '7133', 'debtor' => '7134', 'no_card' => '7135', 'subscriber' => '7136');
	$why = findtekst($texts[$reason] ?? '7129', $sprog_id);
	return "<div style='margin-top:4px;max-width:260px;font-size:12px;line-height:1.35;color:#a15c07'>"
		. htmlspecialchars(findtekst('7128', $sprog_id) . ' ' . $why, ENT_QUOTES, 'UTF-8') . "</div>";
}

