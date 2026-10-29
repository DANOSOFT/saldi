<?php
// 20261022 CDX/LH Persist request identity/parameters before Stripe; recover ambiguous responses safely.
// 20261022 CDX/LH Bind retries to the caller's source/card/consent, reconcile mismatches and reject terminal HTTP errors; enforce cards.
// 20261027 CL/ASR Automatic payment is offered on every customer invoice, not only on recurring ones: the consent covers
//                 the customer's next invoices, and a one-off invoice (e.g. the one carrying the manual-payment fee) must
//                 let the customer turn it on and avoid the fee.

/** @return string Copenhagen calendar date, with an explicit process clock for CLI/tests. */
function stripe_today() {
	return $GLOBALS['stripe_charge_today'] ?? (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
}

/** @return string Calendar arithmetic unaffected by DST or the process timezone. */
function stripe_date_add($date, $days) {
	return (new DateTimeImmutable(substr((string)$date, 0, 10), new DateTimeZone('Europe/Copenhagen')))
		->modify(sprintf('%+d days', (int)$days))->format('Y-m-d');
}

/** @return bool Whether save consent is offered and accepted for this invoice: any customer invoice (not a credit note). */
function stripe_invoice_save_allowed($inv) {
	return stripe_feature_on('enrolment') && ($inv['recurring'] || $inv['enrolled']
		|| ((string)($inv['o']['art'] ?? '') === 'DO' && empty($inv['is_credit'])));
}

/** @return string Primary failure date; persisted job clock takes precedence over a DB timestamp. */
function stripe_attempt_date($row) {
	return substr((string)(($row['attempt_date'] ?? '') ?: $row['created_at']), 0, 10);
}

/** @return array<int, array<string, mixed>> Primary declines only, in chronological order. Backup and technical errors consume no slot. */
function stripe_primary_declines($inv) {
	$rows = array_values(array_filter($inv['payments'], function ($p) {
		return $p['source'] === 'auto' && stripe_payment_attempt_failed($p) && empty($p['action_required_at']);
	}));
	usort($rows, function ($a, $b) { return (int)$a['id'] <=> (int)$b['id']; });
	return $rows;
}

/** @return string|null Next scheduled primary date, or null after the third primary decline. */
function stripe_auto_next_date($inv) {
	$failed = stripe_primary_declines($inv);
	if (count($failed) >= 3) return null;
	if (!$failed) return stripe_setting('charge_on', 'due') === 'invoice_date' ? substr((string)$inv['o']['fakturadate'], 0, 10) : $inv['due'];
	return stripe_date_add(stripe_attempt_date($failed[count($failed) - 1]), count($failed) === 1 ? 3 : 4);
}

/** @return array<string, mixed>|null Request that has no durable Stripe response yet. Caller holds the invoice lock. */
function stripe_attempt_unresolved($ordreId) {
	return db_fetch_array(stripe_payment_select("select * from stripe_collection_attempts where ordre_id = " . (int)$ordreId
		. " and status in ('prepared', 'requesting', 'technical', 'retryable') order by id limit 1")) ?: null;
}

/** @return bool Whether the stored request has exactly the caller's collection and consent settings. */
function stripe_attempt_matches($request, $source, $saveCard, $confirmPm) {
	$params = json_decode($request['params'], true);
	return is_array($params) && $request['source'] === $source && stripe_bool($request['save_card']) === (bool)$saveCard
		&& (string)($params['payment_method'] ?? '') === $confirmPm
		&& (string)($params['confirm'] ?? '') === ($confirmPm !== '' ? 'true' : '')
		&& (string)($params['off_session'] ?? '') === ($confirmPm !== '' ? 'true' : '')
		&& ($params['setup_future_usage'] ?? null) === ($saveCard ? 'off_session' : null)
		&& ($params['payment_method_types'] ?? array()) === array('card');
}

/**
 * Resolve a different caller's reservation before ordinary invoice reconciliation. Caller holds the lock.
 * An absent intent does not prove that an ambiguous request failed; never replay its body for this caller.
 *
 * @return string Empty when replacement is safe; otherwise in_progress, already_paid or reconcile_failed.
 */
function stripe_attempt_reconcile_caller($inv, $source, $saveCard, $confirmPm) {
	$request = stripe_attempt_unresolved($inv['id']);
	if (!$request || stripe_attempt_matches($request, $source, $saveCard, $confirmPm)) return '';
	$id = (int)$request['id'];
	if ($request['status'] === 'prepared') {
		stripe_payment_modify("update stripe_collection_attempts set status = 'abandoned' where id = $id");
		return '';
	}
	$params = json_decode($request['params'], true);
	if (!is_array($params)) return 'reconcile_failed';
	$pi = stripe_attempt_lookup($request, $params);
	if (!$pi) return 'in_progress';
	if (in_array(($pi['status'] ?? ''), array('requires_payment_method', 'requires_confirmation', 'requires_action'), true)) {
		$cancel = stripeHttpRequest('POST', '/v1/payment_intents/' . rawurlencode($pi['id']) . '/cancel');
		if (!$cancel['ok'] || ($cancel['body']['id'] ?? '') !== $pi['id']) return 'reconcile_failed';
		$pi = $cancel['body'];
	}
	stripe_attempt_record($inv, $request, $pi);
	$result = stripe_payment_apply_status($pi);
	if ($result === 'canceled') return '';
	if (in_array($result, array('applied', 'duplicate', 'duplicate_paid'), true)) return 'already_paid';
	return in_array($result, array('processing', 'requires_action', 'requires_payment_method', 'requires_confirmation'), true)
		? 'in_progress' : 'reconcile_failed';
}

/**
 * Allocate a durable request before sending it. Its body and idempotency key never change on retry.
 * The collection identity is separate from the three primary schedule numbers.
 *
 * @return array<string, mixed> Persisted request row.
 */
function stripe_attempt_prepare($inv, $source, $saveCard, $confirmPm) {
	global $db;
	$existing = stripe_attempt_unresolved($inv['id']);
	if ($existing) {
		if (!stripe_attempt_matches($existing, $source, $saveCard, $confirmPm)) throw new RuntimeException('Attempt caller mismatch');
		return $existing;
	}
	$cus = stripe_customer_for_debtor($inv);
	if ($cus === '') throw new RuntimeException('customer');
	$identity = 1;
	foreach ($inv['payments'] as $p) $identity = max($identity, (int)$p['attempt'] + 1);
	$last = db_fetch_array(stripe_payment_select('select max(identity_no) as n from stripe_collection_attempts where ordre_id = ' . (int)$inv['id']));
	$identity = max($identity, (int)($last['n'] ?? 0) + 1);
	$primary = count(stripe_primary_declines($inv)) + ($source === 'backup' ? 0 : 1);
	$primary = max(1, $primary);
	$params = array('amount' => $inv['total_ore'], 'currency' => strtolower($inv['currency']), 'customer' => $cus,
		'description' => stripe_txt('invoice') . ' ' . $inv['nr'],
		'metadata' => array('saldi_ordre_id' => $inv['id'], 'saldi_fakturanr' => $inv['nr'],
			'saldi_konto_id' => (int)$inv['o']['konto_id'], 'saldi_db' => (string)$db, 'saldi_source' => $source,
			'saldi_attempt_identity' => $identity, 'saldi_primary_attempt' => $primary),
		'payment_method_types' => array('card'));
	if ($confirmPm !== '') {
		$params['payment_method'] = $confirmPm;
		$params['confirm'] = 'true';
		$params['off_session'] = 'true';
	}
	if ($saveCard) $params['setup_future_usage'] = 'off_session';
	stripe_payment_modify("insert into stripe_collection_attempts (ordre_id, identity_no, primary_no, source, save_card, params, status, charge_date) values ("
		. (int)$inv['id'] . ", $identity, $primary, '" . db_escape_string($source) . "', " . ($saveCard ? 'true' : 'false')
		. ", '" . db_escape_string(json_encode($params)) . "', 'prepared', '" . db_escape_string(stripe_today()) . "')");
	$row = stripe_attempt_unresolved($inv['id']);
	if (!$row) throw new RuntimeException('Attempt reservation failed');
	return $row;
}

/**
 * Find a lost response through the customer's intent list, paging to its end.
 * After the idempotency retention window, an absent result stays blocked for manual reconciliation.
 *
 * @return array<string, mixed>|null Matching intent or null when none was returned.
 */
function stripe_attempt_lookup($request, $params) {
	$cursor = '';
	do {
		$query = array('customer' => $params['customer'], 'limit' => 100);
		if ($cursor !== '') $query['starting_after'] = $cursor;
		$r = stripeHttpRequest('GET', '/v1/payment_intents', $query);
		if (!$r['ok']) throw new RuntimeException('Attempt lookup failed');
		foreach (($r['body']['data'] ?? array()) as $pi) {
			if ((string)($pi['metadata']['saldi_db'] ?? '') === (string)$GLOBALS['db']
				&& (int)($pi['metadata']['saldi_ordre_id'] ?? 0) === (int)$request['ordre_id']
				&& (int)($pi['metadata']['saldi_attempt_identity'] ?? 0) === (int)$request['identity_no']) return $pi;
			$cursor = (string)$pi['id'];
		}
		if (!empty($r['body']['has_more']) && empty($r['body']['data'])) throw new RuntimeException('Incomplete intent list');
	} while (!empty($r['body']['has_more']));
	return null;
}

/** @return string action|decline|technical Error class; only explicit card errors are declines. */
function stripe_payment_error_kind($pi, $response = array()) {
	$error = $pi['last_payment_error'] ?? ($response['body']['error'] ?? array());
	$code = (string)($error['code'] ?? ($response['error_code'] ?? ''));
	if (($pi['status'] ?? '') === 'requires_action' || $code === 'authentication_required') return 'action';
	$http = (int)($response['status'] ?? 200);
	if ($http === 0 || $http === 429 || $http >= 500) return 'technical';
	if (($error['type'] ?? '') === 'card_error' || $code === 'card_declined' || !empty($error['decline_code'])) return 'decline';
	return 'technical';
}

/** @return int Local payment id after durably linking the recovered response to its original reservation. */
function stripe_attempt_record($inv, $request, $pi) {
	$number = in_array($request['source'], array('auto', 'backup'), true) ? (int)$request['primary_no'] : (int)$request['identity_no'];
	$paymentId = stripe_payment_record($inv, $pi, $request['source'], stripe_bool($request['save_card']), $number);
	stripe_payment_modify("update stripe_payments set attempt_date = '" . db_escape_string($request['charge_date']) . "' where id = $paymentId");
	stripe_payment_modify("update stripe_collection_attempts set status = 'recorded', payment_intent_id = '" . db_escape_string($pi['id']) . "' where id = " . (int)$request['id']);
	return $paymentId;
}

/**
 * Execute/recover a reservation. Never re-POST an ambiguous create after Stripe may prune its key.
 * Technical responses retain the same reservation; terminal cancellation gets a new reservation.
 *
 * @return array{ok: bool, status?: string, pi?: string, client_secret?: string, setup_future_usage?: string|null, error?: string, error_code?: string, kind?: string}
 */
function stripe_attempt_send($inv, $request) {
	$params = json_decode($request['params'], true);
	if (!is_array($params)) throw new RuntimeException('Invalid persisted attempt');
	$id = (int)$request['id'];
	$pi = null;
	if (!in_array($request['status'], array('prepared', 'retryable'), true)) {
		$pi = stripe_attempt_lookup($request, $params);
		$age = stripe_payment_mysql() ? 'CURRENT_TIMESTAMP - INTERVAL 23 HOUR' : "CURRENT_TIMESTAMP - interval '23 hours'";
		$expiry = db_fetch_array(stripe_payment_select("select case when request_started_at <= $age then 1 else 0 end as expired from stripe_collection_attempts where id = $id"));
		if (!$pi && (int)$expiry['expired'] === 1) return array('ok' => false, 'error' => 'ambiguous_create', 'kind' => 'technical');
	}
	if (!$pi) {
		if ((int)$params['amount'] !== (int)$inv['total_ore'] || strtolower((string)$params['currency']) !== strtolower((string)$inv['currency'])) {
			return array('ok' => false, 'error' => 'attempt_amount_changed', 'kind' => 'technical');
		}
		stripe_payment_modify("update stripe_collection_attempts set status = 'requesting', request_started_at = coalesce(request_started_at, CURRENT_TIMESTAMP) where id = $id");
		$prefix = isset($params['off_session']) ? 'auto' : 'pi';
		$key = $prefix . ':' . $GLOBALS['db'] . ':' . $inv['id'] . ':' . $request['identity_no'] . ':' . (stripe_bool($request['save_card']) ? 's' : 'n');
		$response = stripeHttpRequest('POST', '/v1/payment_intents', $params, array('idempotency_key' => $key));
		$pi = $response['ok'] ? $response['body'] : ($response['body']['error']['payment_intent'] ?? null);
		if (!$response['ok'] && (!is_array($pi) || empty($pi['id']))) {
			$http = (int)($response['status'] ?? 0);
			$retryStatus = $http === 429 ? 'retryable' : (($http === 0 || ($http >= 500 && $http < 600)) ? 'technical' : 'rejected');
			stripe_payment_modify("update stripe_collection_attempts set status = '$retryStatus' where id = $id");
			return array('ok' => false, 'error' => $response['error'] ?: 'stripe', 'error_code' => $response['error_code'] ?? '', 'kind' => 'technical');
		}
	}
	if (!is_array($pi) || empty($pi['id'])) throw new RuntimeException('Stripe response lacks intent');
	if (isset($response) && !$response['ok'] && empty($pi['last_payment_error'])) {
		$error = $response['body']['error'] ?? array();
		$pi['last_payment_error'] = array_intersect_key($error, array_flip(array('code', 'type', 'decline_code', 'payment_method')));
	}
	// The primary schedule is independent of page/cancellation/backup identity allocation.
	$paymentId = stripe_attempt_record($inv, $request, $pi);
	$result = array('ok' => true, 'pi' => (string)$pi['id'], 'client_secret' => (string)($pi['client_secret'] ?? ''),
		'status' => (string)$pi['status'], 'setup_future_usage' => $params['setup_future_usage'] ?? null);
	if (($pi['status'] ?? '') === 'requires_action' || !empty($pi['last_payment_error'])) {
		$kind = stripe_payment_error_kind($pi, $response ?? array());
		if ($kind === 'technical') {
			stripe_payment_modify("update stripe_payments set status = 'technical_error', last_error = 'technical_error' where id = $paymentId and status not in ('succeeded', 'duplicate_paid', 'refunded', 'disputed', 'unmapped')");
			return array('ok' => false, 'error' => 'technical_error', 'kind' => 'technical');
		}
		$outcome = stripe_payment_apply_status($pi);
		if (in_array($outcome, array('database_error', 'delivery_error', 'error'), true)) return array('ok' => false, 'error' => $outcome, 'kind' => 'technical');
		$result['kind'] = $kind;
		if ($kind === 'action') $result['status'] = 'requires_action';
		else { $result['ok'] = false; $result['error'] = (string)($pi['last_payment_error']['code'] ?? 'stripe'); }
	}
	return $result;
}
