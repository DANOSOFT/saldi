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
 * 20261025 CL/ASR Why this order cannot use "E-mail med link": '' = it can. 'ledger' when the payment page is
 * off here; otherwise the stripe_invoice_uncollectable_reason() codes (terms, currency, credit, pbs, ...).
 * $override holds posted order fields that are not saved yet (betalingsbet, valuta).
 *
 * @return string Reason code or ''.
 */
function orderLinkBlockReason($orderId, $override = array()) {
	if ((int)$orderId <= 0) {
		return '';
	}
	if (!orderLinkAvailable()) {
		return 'ledger';
	}
	$o = db_fetch_array(db_select('select * from ordrer where id = ' . (int)$orderId, __FILE__ . ' linje ' . __LINE__));
	if (!$o) {
		return '';
	}
	foreach ($override as $field => $value) {
		if (is_string($value) && trim($value) !== '') $o[$field] = $value;
	}
	return stripe_invoice_uncollectable_reason(array('o' => $o, 'is_credit' => ($o['art'] ?? '') === 'DK'));
}

/**
 * Text for a blocked "E-mail med link": why, and what to do instead. Draft orders can still change
 * payment terms or currency; an issued invoice can only go as the classic e-mail.
 *
 * @return string Plain text.
 */
function orderLinkBlockText($reason, $invoiced) {
	global $sprog_id;
	$why = array('ledger' => '7129', 'credit' => '7130', 'currency' => '7131', 'terms' => '7132', 'pbs' => '7133', 'debtor' => '7134', 'no_card' => '7135', 'subscriber' => '7136');
	$todo = $invoiced ? array() : array('terms' => '7138', 'currency' => '7139', 'debtor' => '7141');
	$todo['no_card'] = '7140';
	return findtekst('7128', $sprog_id) . ' ' . findtekst($why[$reason] ?? '7129', $sprog_id) . ' '
		. findtekst($todo[$reason] ?? '7142', $sprog_id);
}

/**
 * The "E-mail med link" option. A stored Link order renders it selected; when this order cannot use the
 * payment page ($block) it is shown disabled with a short hint, so it cannot be chosen.
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
