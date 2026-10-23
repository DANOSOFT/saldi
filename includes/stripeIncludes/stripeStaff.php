<?php
// 20261022 CDX/LH Validate debitorkort Afmeld before mutation and show the current saved card from local payment data.

/**
 * Apply master's session-bound token/hash_equals pattern to Afmeld.
 *
 * @param string $method HTTP method.
 * @param array $input Posted form fields.
 * @param mixed $sessionToken Session token, never taken from the request.
 * @return bool Whether the state-changing Afmeld request is valid.
 */
function stripe_staff_afmeld_allowed($method, $input, $sessionToken) {
	$posted = $input['csrf_token'] ?? null;
	return $method === 'POST' && !empty($input['stripe_afmeld']) && is_string($sessionToken)
		&& $sessionToken !== '' && is_string($posted) && $posted !== '' && hash_equals($sessionToken, $posted);
}

/**
 * Handle the saved-card removal with a mandatory token check at the mutation boundary too.
 *
 * @return bool Whether Afmeld and its outstanding invoice delivery succeeded.
 */
function stripe_staff_unenrol($kontoId, $method, $input, $sessionToken) {
	if ((int)$kontoId <= 0 || !stripe_staff_afmeld_allowed($method, $input, $sessionToken)) {
		return false;
	}
	include_once(__DIR__ . '/stripeEnrol.php');
	if (is_file(__DIR__ . '/../auditLog.php')) {
		include_once(__DIR__ . '/../auditLog.php');
	}
	return stripe_unenrol_debtor((int)$kontoId, 'debitorkort');
}

/**
 * Read the stored details for this debtor's current saved PM, never an unrelated/latest card.
 * Older tenants without the invoice-payment table can still open debitorkort.
 *
 * @param array $debtor Loaded adresser row.
 * @return string Card brand and masked last four, or empty when no matching details were stored.
 */
function stripe_staff_saved_card($debtor) {
	if (($debtor['stripe'] ?? '') !== 'on' || empty($debtor['stripe_pm_id']) || empty($debtor['id'])) {
		return '';
	}
	$table = db_fetch_array(db_select("select column_name from information_schema.columns where table_name = 'stripe_payments' and column_name = 'pm_last4'", __FILE__ . ' linje ' . __LINE__));
	if (!$table) {
		return '';
	}
	$q = "select p.pm_brand, p.pm_last4 from stripe_payments p join ordrer o on o.id = p.ordre_id
		where o.konto_id = " . (int)$debtor['id'] . " and p.pm_id = '" . db_escape_string($debtor['stripe_pm_id']) . "'
		and p.stripe_customer_id = '" . db_escape_string((string)($debtor['stripe_customer_id'] ?? '')) . "'
		and coalesce(p.pm_brand, '') <> '' and coalesce(p.pm_last4, '') <> '' order by p.id desc limit 1";
	$card = db_fetch_array(db_select($q, __FILE__ . ' linje ' . __LINE__));
	if (!$card) {
		return '';
	}
	include_once(__DIR__ . '/stripeInvoice.php');
	return stripe_card_label($card['pm_brand'], $card['pm_last4']);
}
