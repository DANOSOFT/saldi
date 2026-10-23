<?php
// 20261022 CDX/LH Reconcile every live invoice intent and retire abandoned attempts before replacement.
// 20261022 CDX/LH Authentication stays resumable and technical failures never retire as bank declines.

/**
 * Apply the current Stripe status without allowing late events to erase a capture.
 *
 * @return string Transition outcome or current Stripe status, as used by sync callers.
 */
function stripe_payment_apply_status($pi, $eventId = 'sync') {
	$status = (string)($pi['status'] ?? '');
	if ($status === 'succeeded') return stripe_payment_apply_succeeded($pi, $eventId);
	if ($status === 'requires_action' || (in_array($status, array('requires_payment_method', 'requires_confirmation'), true) && ($pi['last_payment_error']['code'] ?? '') === 'authentication_required')) return stripe_payment_require_action($pi, $eventId);
	if ($status === 'requires_payment_method' && !empty($pi['last_payment_error'])) {
		return stripe_payment_apply_failed($pi, $eventId);
	}
	if (!in_array($status, array('canceled', 'processing', 'requires_action', 'requires_payment_method', 'requires_confirmation'), true)) return 'error';
	$localStatus = in_array($status, array('requires_payment_method', 'requires_confirmation'), true) ? 'pending' : $status;
	stripe_payment_modify("update stripe_payments set status = '" . db_escape_string($localStatus)
		. "', last_event_id = '" . db_escape_string($eventId) . "' where payment_intent_id = '" . db_escape_string((string)$pi['id'])
		. "' and status not in ('succeeded', 'duplicate_paid', 'refunded', 'disputed', 'unmapped', 'canceled')");
	return $status;
}

/**
 * Refresh all non-terminal intents, even failed attempts (Stripe can still confirm them).
 * Caller holds the invoice lock through reconciliation, creation and local recording.
 * A stale unconfirmed intent is canceled at Stripe before a replacement can be created.
 *
 * @return string Empty when a new attempt is safe; otherwise an error refusing collection.
 */
function stripe_payment_reconcile_invoice($ordreId) {
	$ordreId = (int)$ordreId;
	$recent = stripe_payment_mysql() ? 'created_at > CURRENT_TIMESTAMP - INTERVAL 15 MINUTE' : "created_at > CURRENT_TIMESTAMP - interval '15 minutes'";
	$q = stripe_payment_select("select *, ($recent) as recent from stripe_payments where ordre_id = $ordreId
		and status not in ('succeeded', 'duplicate_paid', 'refunded', 'disputed', 'canceled') order by id");
	$rows = array();
	while ($row = db_fetch_array($q)) $rows[] = $row;
	$blocked = '';
	$lookupFailed = false;
	$retire = array();
	foreach ($rows as $row) {
		$resp = stripeHttpRequest('GET', '/v1/payment_intents/' . rawurlencode($row['payment_intent_id']), array('expand' => array('latest_charge')));
		if (!$resp['ok'] || ($resp['body']['id'] ?? '') !== $row['payment_intent_id']) {
			$blocked = 'reconcile_failed';
			$lookupFailed = true;
			continue;
		}
		$pi = $resp['body'];
		if (($pi['status'] ?? '') === 'requires_payment_method' && !empty($pi['last_payment_error'])
			&& stripe_payment_error_kind($pi) === 'technical' && in_array($row['source'], array('auto', 'backup'), true)) {
			$invoice = stripe_invoice_load($ordreId);
			if (!stripe_auto_charge_allowed($invoice) || !stripe_invoice_payable($invoice)
				|| (int)($pi['amount'] ?? 0) !== (int)$invoice['total_ore']
				|| strtolower((string)($pi['currency'] ?? '')) !== strtolower((string)$invoice['currency'])) {
				$lookupFailed = true;
				continue;
			}
			$method = $pi['last_payment_error']['payment_method'] ?? ($pi['payment_method'] ?? $row['pm_id']);
			$pm = is_array($method) ? (string)($method['id'] ?? '') : (string)$method;
			if ($pm === '') { $lookupFailed = true; continue; }
			// Retrying confirmation on the SAME PI cannot create a second invoice charge.
			// The persisted payment id identifies it; a new calendar day can escape a cached 5xx.
			$retry = stripeHttpRequest('POST', '/v1/payment_intents/' . rawurlencode($pi['id']) . '/confirm',
				array('payment_method' => $pm, 'off_session' => 'true'),
				array('idempotency_key' => 'confirm:' . $GLOBALS['db'] . ':' . $row['id'] . ':' . stripe_today()));
			$retriedPi = $retry['ok'] ? $retry['body'] : ($retry['body']['error']['payment_intent'] ?? null);
			if (!$retriedPi || ($retriedPi['id'] ?? '') !== $pi['id'] || (!$retry['ok'] && stripe_payment_error_kind($retriedPi, $retry) === 'technical')) {
				$lookupFailed = true;
				continue;
			}
			$pi = $retriedPi;
		}
		$result = stripe_payment_apply_status($pi);
		$status = (string)($pi['status'] ?? '');
		if ($result === 'requires_action') $status = 'requires_action';
		if (in_array($result, array('database_error', 'delivery_error', 'unmapped', 'unknown', 'error'), true) || $row['status'] === 'unmapped') {
			$blocked = 'reconcile_failed';
			$lookupFailed = true;
		} elseif ($status === 'succeeded') {
			$blocked = 'already_paid';
		} elseif (in_array($status, array('processing', 'requires_action'), true)) {
			$blocked = 'in_progress';
		} elseif (in_array($status, array('requires_payment_method', 'requires_confirmation'), true)) {
			if (!empty($pi['last_payment_error']) && stripe_payment_error_kind($pi) === 'technical') {
				$blocked = 'reconcile_failed';
				$lookupFailed = true;
				continue;
			}
			if (stripe_bool($row['recent']) && empty($pi['last_payment_error']) && $row['status'] !== 'failed') {
				$blocked = 'in_progress';
			} else {
				$retire[] = $row['payment_intent_id'];
			}
		}
	}
	// Check durable captures too: refunds/disputes and unmapped captures need manual handling.
	$captured = db_fetch_array(stripe_payment_select("select id from stripe_payments where ordre_id = $ordreId
		and status in ('succeeded', 'duplicate_paid', 'refunded', 'disputed', 'unmapped') limit 1"));
	if ($captured) return 'already_paid';
	if ($lookupFailed) return 'reconcile_failed';
	if ($blocked !== '') return $blocked;
	foreach ($retire as $piId) {
		$resp = stripeHttpRequest('POST', '/v1/payment_intents/' . rawurlencode($piId) . '/cancel');
		if (!$resp['ok'] || ($resp['body']['id'] ?? '') !== $piId) return 'reconcile_failed';
		$result = stripe_payment_apply_status($resp['body']);
		if (($resp['body']['status'] ?? '') !== 'canceled' || $result !== 'canceled') return 'in_progress';
	}
	return '';
}

/**
 * Preserve the charge job's failure history after retiring a failed PI at Stripe.
 * A customer cancellation without a decline does not consume a scheduled retry.
 *
 * @return bool Whether this row records a failed collection attempt.
 */
function stripe_payment_attempt_failed($payment) {
	return empty($payment['action_required_at']) && ($payment['status'] === 'failed' || ($payment['status'] === 'canceled' && !empty($payment['last_error']) && !in_array($payment['last_error'], array('authentication_required', 'technical_error', 'api_error', 'network', 'rate_limit'), true)));
}
