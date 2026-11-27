<?php
// 20261022 CDX/LH Preserve stored Stripe delivery across legacy mail flags, local printing and partial order saves.
// 20261025 CL/ASR 'Link' delivery: option, G6.3 default, block reasons (hard vs temporary), notice and terms script.
// 20261126 CL/ASR Review round 1 (I10): "Stripe" is offered only when the ledger charges saved cards (stripe_card_charging_on()) and the customer has one.
//                 A stored Stripe that cannot be charged stays selected and says it goes out as e-mail (texts 8251, 8252).
// 20261126 CL/ASR Review round 1 (I17): orderInvoicedStripeKept() and orderPrintModeShown(): on an invoiced order whose Udskriv til is Stripe, another choice prints in that mode without being saved.
// 20261126 ASR Review CARD2-2: a credit note (art DK) is not offered Stripe, as nothing charges or refunds it; a stored Stripe on one stays selected and says it is printed as PDF (texts 8270, 8271).
// 20261126 ASR Review PAY-R2-5: without a Base-URL in the Stripe settings E-mail med link is not offered; a stored Link says that the Base-URL is missing (reason base_url, text 8274).

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
 * An invoiced order (status 3 or more) whose stored Udskriv til is Stripe keeps it when another mode is chosen: the
 * charge job, the payment page's state and the reminder hold all read the stored 'Stripe', so saving the choice (to
 * print a copy as PDF, say) silently stopped the automatic charge. The choice prints or sends in that mode only.
 *
 * @param int $id Order id.
 * @param string $chosen The Udskriv til chosen in this request.
 * @return array|null The stored udskriv_til and pbs to write back, or null when the choice is saved.
 */
function orderInvoicedStripeKept($id, $chosen) {
	if ($chosen === 'Stripe' || (int)$id <= 0) {
		return null;
	}
	$r = db_fetch_array(db_select('select status, udskriv_til, pbs from ordrer where id = ' . (int)$id, __FILE__ . ' linje ' . __LINE__));
	if (!$r || (int)$r['status'] < 3 || trim((string)$r['udskriv_til']) !== 'Stripe') {
		return null;
	}
	return $r;
}

/**
 * The Udskriv til shown on an invoiced order: the print mode chosen in this request over a kept Stripe
 * (orderInvoicedStripeKept()), so the next Udskriv/Send uses it; the stored Stripe is shown when the page is opened again.
 *
 * @param string $delivery The order's Udskriv til.
 * @return string
 */
function orderPrintModeShown($delivery) {
	$mode = $GLOBALS['orderPrintMode'] ?? '';
	return ($delivery === 'Stripe' && is_string($mode) && $mode !== '') ? $mode : $delivery;
}

/**
 * Offer Stripe to enrolled debtors and always render a stored Stripe instruction as selected. Stripe is offered only
 * when this ledger charges saved cards (stripe_card_charging_on()) and the customer has a saved card; a stored Stripe
 * that cannot be charged stays selected (so it is not lost) and says that the invoice goes out as e-mail, as
 * stripe_invoice_delivery() then sends it. A credit note is never offered Stripe: the charge job leaves credit notes
 * alone and nothing refunds a card from it. A stored Stripe on one stays selected and says that it is printed as PDF,
 * which the print engine does with it (only an invoice with Stripe is mailed, includes/formfunk.php).
 *
 * @param string $selected The order's Udskriv til.
 * @param int $kontoId The customer (adresser.id).
 * @param string $art The order's art; 'DK' is a credit note.
 * @return string HTML option, or an empty string for an unenrolled debtor using another mode.
 */
function orderStripeDeliveryOption($selected, $kontoId, $art = '') {
	global $sprog_id;
	$stored = $selected === 'Stripe';
	if ($art === 'DK') {
		if (!$stored) {
			return '';
		}
		$cs = !empty($GLOBALS['charset']) ? $GLOBALS['charset'] : 'UTF-8';
		return "<option value='Stripe' selected title='" . htmlspecialchars(findtekst('8271', $sprog_id), ENT_QUOTES, $cs) . "'>"
			. htmlspecialchars(findtekst('8270', $sprog_id), ENT_QUOTES, $cs) . "</option>\n";
	}
	$chargeable = orderStripeChargeable($kontoId);
	if (!$stored && !$chargeable) {
		return '';
	}
	if ($stored && !$chargeable) {
		$cs = !empty($GLOBALS['charset']) ? $GLOBALS['charset'] : 'UTF-8';
		return "<option value='Stripe' selected title='" . htmlspecialchars(findtekst('8251', $sprog_id), ENT_QUOTES, $cs) . "'>"
			. htmlspecialchars(findtekst('8252', $sprog_id), ENT_QUOTES, $cs) . "</option>\n";
	}
	return "<option value='Stripe'" . ($stored ? ' selected' : '')
		. " title='Trækkes automatisk på kundens kort på forfaldsdatoen. Ingen fakturamail.'>Stripe</option>\n";
}

/**
 * Whether an invoice to this customer can be charged automatically: the ledger charges saved cards and the customer
 * has one (adresser.stripe on with a payment method).
 *
 * @return bool
 */
function orderStripeChargeable($kontoId) {
	if ((int)$kontoId <= 0 || !is_file(__DIR__ . '/../../includes/stripeIncludes/stripeSettings.php')) {
		return false;
	}
	include_once(__DIR__ . '/../../includes/stripeIncludes/stripeSettings.php');
	if (!stripe_card_charging_on()) {
		return false;
	}
	$column = db_fetch_array(db_select("select count(*) as n from information_schema.columns where table_name = 'adresser' and column_name in ('stripe', 'stripe_pm_id')"
		. saldi_schema_filter(), __FILE__ . ' linje ' . __LINE__));
	if (!$column || (int)$column['n'] < 2) {
		return false;
	}
	$d = db_fetch_array(db_select('select stripe, stripe_pm_id from adresser where id = ' . (int)$kontoId, __FILE__ . ' linje ' . __LINE__));
	return ($d['stripe'] ?? '') === 'on' && trim((string)($d['stripe_pm_id'] ?? '')) !== '';
}

/**
 * 20261025 CL/ASR "E-mail med link" (Udskriv til = Link) next to the classic e-mail: available when the
 * invoice & payment page is switched on in this ledger.
 */
function orderLinkAvailable() {
	global $db;
	static $available = array();
	$key = (string)$db;
	if (!isset($available[$key])) {
		$available[$key] = false;
		if (is_file(__DIR__ . '/../../includes/stripeIncludes/stripeInvoice.php')) {
			include_once(__DIR__ . '/../../includes/stripeIncludes/stripeInvoice.php');
			$available[$key] = function_exists('stripe_invoice_link_available') && stripe_invoice_link_available();
		}
	}
	return $available[$key];
}

/** G6.3 default for new e-mail orders is the link mail. */
function orderLinkDefault() {
	return orderLinkAvailable() && stripe_invoice_format() === 'link';
}

/**
 * 20261025 CL/ASR Why this order cannot use "E-mail med link": '' = it can. 'ledger' when the payment page is
 * off here, 'base_url' when only the Base-URL is missing in the Stripe settings; otherwise the
 * stripe_invoice_uncollectable_reason() codes (terms, currency, credit, pbs, ...).
 * $override holds posted order fields that are not saved yet (betalingsbet). Cached per request.
 *
 * @return string Reason code or ''.
 */
function orderLinkBlockReason($orderId, $override = array()) {
	global $db;
	static $cache = array();
	if ((int)$orderId <= 0) {
		return '';
	}
	if (!orderLinkAvailable()) {
		return function_exists('stripe_invoice_link_base_missing') && stripe_invoice_link_base_missing() ? 'base_url' : 'ledger';
	}
	$key = $db . '|' . (int)$orderId . '|' . json_encode($override);
	if (!array_key_exists($key, $cache)) {
		$o = db_fetch_array(db_select('select * from ordrer where id = ' . (int)$orderId, __FILE__ . ' linje ' . __LINE__));
		if (!$o) {
			return '';
		}
		foreach ($override as $field => $value) {
			if (is_string($value) && trim($value) !== '') $o[$field] = $value;
		}
		$cache[$key] = stripe_invoice_uncollectable_reason(array('o' => $o));
	}
	return $cache[$key];
}

/**
 * A block the user fixes on the order itself (payment terms, currency, credit note, customer, PBS): the option
 * is disabled and a stored Link becomes E-mail. Other reasons (payment page off, card opt-out, Stripe
 * subscriber) can change without touching the order: a stored Link is kept and sent as the classic e-mail
 * meanwhile (formfunk falls back), so the choice is not lost.
 *
 * @return bool Whether the reason is fixed on the order.
 */
function orderLinkHardBlock($reason) {
	return in_array($reason, array('terms', 'currency', 'credit', 'debtor', 'pbs'), true);
}

/**
 * Text for a blocked "E-mail med link": why, and what to do instead. Draft orders can still change
 * payment terms or currency; an issued invoice can only go as the classic e-mail.
 *
 * @return string Plain text.
 */
function orderLinkBlockText($reason, $invoiced) {
	global $sprog_id;
	$why = array('ledger' => '7129', 'base_url' => '8274', 'credit' => '7130', 'currency' => '7131', 'terms' => '7132', 'pbs' => '7133', 'debtor' => '7134', 'no_card' => '7135', 'subscriber' => '7136');
	$todo = $invoiced ? array() : array('terms' => '7138', 'currency' => '7139', 'debtor' => '7141');
	$todo['no_card'] = '7140';
	return findtekst('7150', $sprog_id) . ' ' . findtekst($why[$reason] ?? '7129', $sprog_id) . ' '
		. findtekst($todo[$reason] ?? '7142', $sprog_id);
}

/**
 * The "E-mail med link" option. A stored Link order renders it selected (callers turn a hard-blocked Link into
 * E-mail first); otherwise, when this order cannot use the payment page ($block), it is disabled with a hint.
 *
 * @return string HTML option or ''.
 */
function orderLinkDeliveryOption($selected, $block = '') {
	global $sprog_id;
	$stored = $selected === 'Link';
	if (!$stored && !orderLinkAvailable()) {
		return '';
	}
	$label = findtekst('7124', $sprog_id);
	$hints = array('terms' => '7143', 'currency' => '7144', 'credit' => '7145', 'pbs' => '7146', 'debtor' => '7147', 'no_card' => '7148', 'subscriber' => '7149');
	if (!$stored && isset($hints[$block])) {
		return "<option value='Link' disabled>" . htmlspecialchars($label . ' (' . findtekst($hints[$block], $sprog_id) . ')', ENT_QUOTES, 'UTF-8') . "</option>\n";
	}
	return "<option value='Link'" . ($stored ? ' selected' : '') . " title=\"" . htmlspecialchars(findtekst('7125', $sprog_id), ENT_QUOTES, 'UTF-8') . "\">"
		. htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</option>\n";
}

/**
 * Notice under the dropdown when "E-mail med link" was chosen (or is the G6.3 default) but is blocked:
 * the order goes as the classic e-mail, and the text says what to change. Credit notes only get the hint.
 * The empty element is kept for orderLinkTermsScript().
 *
 * @return string HTML notice or ''.
 */
function orderLinkNotice($block, $chosen, $invoiced) {
	$show = $chosen && $block !== '' && $block !== 'credit';
	if (!$show && !orderLinkAvailable()) {
		return '';
	}
	return "<div id='orderLinkNotice' style='margin-top:4px;max-width:260px;font-size:12px;line-height:1.35;color:#a15c07"
		. ($show ? '' : ';display:none') . "'>"
		. ($show ? htmlspecialchars(orderLinkBlockText($block, $invoiced), ENT_QUOTES, 'UTF-8') : '') . "</div>";
}

/**
 * Draft orders: choosing Kontant or Kreditkort under payment terms disables "E-mail med link" at once (and
 * switches a Link choice to E-mail with the notice); choosing other terms again restores it. Only when the
 * terms are the sole possible block. $chosen = Link was the choice before a block switched it to E-mail.
 * The save path checks again (orderLinkBlockReason with posted values).
 *
 * @return string HTML script or ''.
 */
function orderLinkTermsScript($orderId, $chosen) {
	global $sprog_id;
	if ((int)$orderId <= 0 || orderLinkBlockReason($orderId, array('betalingsbet' => 'Netto')) !== '') {
		return '';
	}
	$js = json_encode(array(
		'label' => findtekst('7124', $sprog_id),
		'hint' => findtekst('7124', $sprog_id) . ' (' . findtekst('7143', $sprog_id) . ')',
		'notice' => orderLinkBlockText('terms', false),
		'chosen' => (bool)$chosen
	), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	return "<script>(function () {
	var t = $js;
	var first = document.querySelector(\"select[name='udskriv_til']\");
	if (t.chosen && first && first.value !== 'Link') first.setAttribute('data-link-was', '1');
	document.addEventListener('change', function (e) {
		var box = document.getElementById('orderLinkNotice');
		if (e.target && e.target.name === 'udskriv_til') {
			e.target.removeAttribute('data-link-was');
			if (box) { box.textContent = ''; box.style.display = 'none'; }
			return;
		}
		if (!e.target || e.target.name !== 'betalingsbet') return;
		var sel = document.querySelector(\"select[name='udskriv_til']\");
		var opt = sel && sel.querySelector(\"option[value='Link']\");
		if (!opt) return;
		var blocked = /^(kontant|kreditkort)$/i.test(String(e.target.value).trim());
		opt.disabled = blocked;
		opt.text = blocked ? t.hint : t.label;
		if (blocked && sel.value === 'Link') { sel.value = 'email'; sel.setAttribute('data-link-was', '1'); }
		if (!blocked && sel.getAttribute('data-link-was') === '1') { sel.value = 'Link'; sel.removeAttribute('data-link-was'); }
		if (box) { box.textContent = blocked && sel.getAttribute('data-link-was') === '1' ? t.notice : ''; box.style.display = box.textContent ? '' : 'none'; }
	});
})();</script>";
}
