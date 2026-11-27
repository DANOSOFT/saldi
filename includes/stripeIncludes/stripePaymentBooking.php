<?php
// 20261022 CDX/LH Checked, recoverable invoice-payment bookkeeping and post-commit delivery.
// 20261022 CDX/LH Serialize invoice captures; retain duplicate_paid and alert with both intent identities.
// 20261022 CDX/LH Enrol with the verified PaymentIntent customer, including saved-card replacement.
// 20261022 CDX/LH Carry project/department into journal settlement and explicitly enrol the verified PaymentIntent customer.
// 20261022 CDX/LH Allocate legacy journal ids explicitly under the booking lock instead of using its lagging sequence.
// 20261126 ASR Payment review (GPT-P-1): "save the card" is applied once per payment (card_state, stripe_payment_apply_card()), apart from the receipt.
//              A receipt retry only sends the receipt, so it never brings back a card the customer was unenrolled from or that a later card replaced.
// 20261126 ASR Payment review (GPT-P-2): a capture the invoice no longer owes (settled or credited another way, or its open item changed, while the payment was under way) is booked as the customer's credit.
//              Its journal line has no invoice number, so posting it does not settle the invoice a second time; the payment is marked 'overpaid' and the bookkeeper is told, with no receipt and no saved card.
// 20261126 ASR Payment review (GPT-P-4): the bookkeeper alert for a refund or dispute is kept in stripe_adjustment_alerts until it is sent (stripe_payment_adjustment_alert()).
//              The repair pass sends the ones still unsent and finishes the event rows they left 'failed' (stripe_payment_adjustment_alert_retry()).
// 20261126 ASR Payment review (GPT-G-1): stripe_payment_apply_card() classifies a payment from before card_state (NULL) before it decides, so no path applies such a card as undecided.
// 20261126 ASR Payment review (GPT-G-3): the journal line of an overpaid capture carries the reference Stripe-overbetaling-<payment id> instead of an empty invoice number (stripe_payment_overpaid_reference()).
//              Posting matched an empty invoice number like any other, so the credit could settle another open item of the customer without an invoice number; no open item carries the reference.
// 20261126 ASR Payment review (GPT-G-6): the repair pass sends an unsent refund or dispute alert under the payment's lock and only while it is still unsent, as the webhook does.

/** @return bool Whether the current connection uses MySQL. */
function stripe_payment_mysql() {
	return in_array($GLOBALS['db_type'], array('mysql', 'mysqli'), true);
}

/** @return mixed Query result; throws instead of treating a DB failure as an empty result. */
function stripe_payment_select($sql, $trace = '') {
	$result = db_select($sql, $trace ?: __FILE__ . ' linje ' . __LINE__);
	if ($result === false) throw new RuntimeException('Stripe database query failed');
	return $result;
}

/** @return void Throws when the legacy DB layer reports an unsuccessful write. */
function stripe_payment_modify($sql, $trace = '') {
	$result = db_modify($sql, $trace ?: __FILE__ . ' linje ' . __LINE__);
	if (!is_string($result) || strncmp($result, '0' . chr(9), 2) !== 0) {
		throw new RuntimeException('Stripe database write failed');
	}
}

/**
 * Claim a transition by its affected row, including a MySQL equivalent of RETURNING.
 *
 * @return array|null The claimed row id, or null when another caller already transitioned it.
 */
function stripe_payment_update_returning($set, $where) {
	$sql = "update stripe_payments set $set where $where";
	if (!stripe_payment_mysql()) {
		return db_fetch_array(stripe_payment_select($sql . ' returning id')) ?: null;
	}
	stripe_payment_modify($sql);
	if (mysqli_affected_rows($GLOBALS['connection']) < 1) return null;
	return array('claimed' => true);
}

/** @return bool Whether the connection is already inside a transaction (PostgreSQL's own status; on MySQL transaktion()'s depth). */
function stripe_payment_in_transaction() {
	if (stripe_payment_mysql()) return !empty($GLOBALS['db_transaktion_depth']);
	return pg_transaction_status($GLOBALS['connection']) !== PGSQL_TRANSACTION_IDLE;
}

/**
 * Use the existing transaction helper, checking its result (including COMMIT failures).
 *
 * @return void
 */
function stripe_payment_transaction($action) {
	if (transaktion($action) === false) throw new RuntimeException('Stripe transaction ' . $action . ' failed');
}

/** @return string Configured card clearing account, or an empty string. */
function stripe_payment_clearing_account() {
	$order = stripe_payment_mysql() ? 'fiscal_year desc' : 'fiscal_year desc nulls last';
	$row = db_fetch_array(stripe_payment_select("select box10 from grupper where art = 'DIV' and kodenr = '3' order by $order limit 1"));
	$account = $row ? trim((string)$row['box10']) : '';
	return (int)$account > 0 ? $account : '';
}

/**
 * Serialize booking and post-commit side effects for a payment, including repair callers.
 * Session locks survive COMMIT and are always released by the caller's finally block.
 *
 * @return void
 */
function stripe_payment_lock($id, $release = false) {
	$id = (int)$id;
	if (stripe_payment_mysql()) {
		$name = db_escape_string('stripe-payment:' . $GLOBALS['db'] . ':' . $id);
		$sql = $release ? "select RELEASE_LOCK('$name') as locked" : "select GET_LOCK('$name', 30) as locked";
		$row = db_fetch_array(stripe_payment_select($sql));
		if (!$row || (int)$row['locked'] !== 1) throw new RuntimeException('Stripe payment lock failed');
	} else {
		$fn = $release ? 'pg_advisory_unlock' : 'pg_advisory_lock';
		stripe_payment_select("select $fn(82101, $id)");
	}
}

/**
 * Serialize collection and capture decisions for one invoice on the current tenant connection.
 * The separate namespace avoids collisions with payment ids and voucher allocation locks.
 *
 * @return void Throws if an invoice lock cannot be acquired/released.
 */
function stripe_invoice_collection_lock($ordreId, $release = false) {
	$ordreId = (int)$ordreId;
	if (stripe_payment_mysql()) {
		$name = db_escape_string('stripe-invoice:' . $GLOBALS['db'] . ':' . $ordreId);
		$sql = $release ? "select RELEASE_LOCK('$name') as locked" : "select GET_LOCK('$name', 30) as locked";
		$row = db_fetch_array(stripe_payment_select($sql));
		if (!$row || (int)$row['locked'] !== 1) throw new RuntimeException('Stripe invoice lock failed');
	} else {
		$fn = $release ? 'pg_advisory_unlock' : 'pg_advisory_lock';
		stripe_payment_select("select $fn(82102, $ordreId)");
	}
}

/**
 * Notify bookkeeping about a second capture. No invoice flag, journal or receipt is written.
 * Persisted duplicate_paid makes retries and manual reconciliation discoverable.
 *
 * @return string duplicate_paid, or delivery_error so the webhook retries a failed alert.
 */
function stripe_payment_duplicate_alert($payment, $originalPi) {
	if ($payment['confirmation_sent_at']) return 'duplicate_paid';
	$sent = stripeAlertMail('Stripe: dobbelt betaling - faktura ' . $payment['fakturanr'],
		"Faktura: " . $payment['fakturanr'] . "\nOrdre: " . $payment['ordre_id']
		. "\nFørste PaymentIntent: " . $originalPi . "\nEkstra PaymentIntent: " . $payment['payment_intent_id']
		. "\nKunden er trukket to gange. Afstem og refundér den ekstra betaling manuelt. Der er ikke oprettet en ekstra kladdelinje.",
		'duplicate_paid_' . $payment['payment_intent_id'], 3600);
	if (!$sent) return 'delivery_error';
	// For duplicate_paid this marker acknowledges the bookkeeper alert, not a customer receipt.
	$marked = stripe_payment_update_returning('confirmation_sent_at = CURRENT_TIMESTAMP', 'id = ' . (int)$payment['id'] . " and status = 'duplicate_paid' and confirmation_sent_at is null");
	if (!$marked) throw new RuntimeException('Duplicate capture alert marker update failed');
	return 'duplicate_paid';
}

/**
 * Claim and book a verified capture atomically, or repair an existing succeeded claim.
 * On booking failure, retain a succeeded capture with no journal link for the repair pass.
 * Mail and enrolment run after COMMIT under the same payment lock.
 *
 * @param int $id Local payment id.
 * @param string|null $successSet Verified success fields; null for repair-only processing.
 * @return string applied|duplicate|duplicate_paid|database_error|delivery_error.
 */
function stripe_payment_complete($id, $successSet = null) {
	$id = (int)$id;
	$locked = false;
	$invoiceLocked = false;
	$ordreId = 0;
	$journalLocked = false;
	$inTransaction = false;
	$committed = false;
	$booked = false;
	try {
		$identity = db_fetch_array(stripe_payment_select("select ordre_id from stripe_payments where id = $id"));
		if (!$identity) return 'duplicate';
		$ordreId = (int)$identity['ordre_id'];
		stripe_invoice_collection_lock($ordreId);
		$invoiceLocked = true;
		stripe_payment_lock($id);
		$locked = true;
		if (stripe_payment_mysql()) {
			stripe_payment_lock(0); // Serialize Stripe voucher allocation across different payments.
			$journalLocked = true;
		}
		stripe_payment_transaction('begin');
		$inTransaction = true;
		if ($successSet !== null) {
			$payment = db_fetch_array(stripe_payment_select("select * from stripe_payments where id = $id for update"));
			$other = db_fetch_array(stripe_payment_select("select payment_intent_id from stripe_payments where ordre_id = $ordreId and id <> $id
				and (status in ('succeeded', 'refunded', 'disputed') or journal_line_id is not null) order by id limit 1"));
			if ($other && !in_array($payment['status'], array('succeeded', 'refunded', 'disputed'), true)) {
				if ($payment['status'] !== 'duplicate_paid') {
					$duplicateSet = str_replace("status = 'succeeded'", "status = 'duplicate_paid'", $successSet);
					$duplicate = stripe_payment_update_returning($duplicateSet, "id = $id and status not in ('succeeded', 'refunded', 'disputed')");
					if (!$duplicate) throw new RuntimeException('Duplicate capture update returned no row');
				}
				stripe_payment_transaction('commit');
				$inTransaction = false;
				$committed = true;
				return stripe_payment_duplicate_alert($payment, $other['payment_intent_id']);
			}
			$claimed = stripe_payment_update_returning($successSet, "id = $id and status <> 'succeeded' and status not in ('duplicate_paid', 'refunded', 'disputed')");
			if (!$claimed) {
				stripe_payment_transaction('rollback');
				$inTransaction = false;
				return 'duplicate';
			}
		}
		$payment = db_fetch_array(stripe_payment_select("select * from stripe_payments where id = $id for update"));
		if ($payment && $payment['status'] === 'duplicate_paid') {
			$other = db_fetch_array(stripe_payment_select("select payment_intent_id from stripe_payments where ordre_id = $ordreId and id <> $id
				and (status in ('succeeded', 'refunded', 'disputed') or journal_line_id is not null) order by id limit 1"));
			if (!$other) throw new RuntimeException('Original duplicate capture is missing');
			stripe_payment_transaction('commit');
			$inTransaction = false;
			$committed = true;
			return stripe_payment_duplicate_alert($payment, $other['payment_intent_id']);
		}
		if (!$payment || $payment['status'] !== 'succeeded') {
			stripe_payment_transaction('rollback');
			$inTransaction = false;
			return 'duplicate';
		}
		$inv = stripe_invoice_load((int)$payment['ordre_id']);
		if (!$inv) throw new RuntimeException('Captured payment invoice is missing');
		if (!$payment['journal_line_id']) {
			// The money is kept in the books either way. Only a capture that still matches the invoice's open amount settles it.
			$overpaid = stripe_payment_overpaid($inv, $payment);
			$lineId = stripe_journal_add_payment($inv, $payment, $overpaid);
			if (!$lineId) throw new RuntimeException('Journal insert returned no row');
			$sql = "update ordrer set betalt = 'on' where id = " . (int)$payment['ordre_id'];
			if (stripe_payment_mysql()) {
				stripe_payment_modify($sql);
			} else {
				$row = db_fetch_array(stripe_payment_select($sql . ' returning id'));
				if (!$row) throw new RuntimeException('Paid invoice update returned no row');
			}
			$linked = stripe_payment_update_returning("journal_line_id = $lineId" . ($overpaid ? ", last_error = 'overpaid'" : ''), "id = $id and journal_line_id is null");
			if (!$linked) throw new RuntimeException('Journal reference update returned no row');
			$payment['journal_line_id'] = $lineId;
			if ($overpaid) $payment['last_error'] = 'overpaid';
			$booked = true;
		}
		stripe_payment_transaction('commit');
		$inTransaction = false;
		$committed = true;
		if ($booked && function_exists('audit_log_write') && function_exists('tbl_exists')) {
			audit_log_write('stripe.payment_succeeded', 'ordre', (string)$payment['ordre_id'],
				json_encode(array('pi' => $payment['payment_intent_id'], 'amount_ore' => (int)$payment['amount_ore'],
					'card' => stripe_card_label($payment['pm_brand'], $payment['pm_last4']), 'overpaid' => ($payment['last_error'] ?? '') === 'overpaid')), 'stripe');
		}
		if (!$payment['confirmation_sent_at'] && ($payment['last_error'] ?? '') === 'overpaid') {
			if (stripe_bool($payment['save_card'])) stripe_payment_update_returning("card_state = 'skipped'", "id = $id and coalesce(card_state, '') = ''");
			return stripe_payment_overpaid_alert($inv, $payment);
		}
		if (!$payment['confirmation_sent_at']) {
			if (stripe_bool($payment['save_card']) && stripe_payment_apply_card($inv, $payment) === '') return 'delivery_error';
			if (!stripe_mail_confirmation($inv, $payment)) return 'delivery_error';
			$sent = stripe_payment_update_returning('confirmation_sent_at = CURRENT_TIMESTAMP', "id = $id and confirmation_sent_at is null");
			if (!$sent) throw new RuntimeException('Confirmation marker update returned no row');
		}
		return 'applied';
	} catch (Throwable $e) {
		if ($inTransaction && transaktion('rollback') === false) {
			stripe_log('payment rollback failed for ' . $id);
			return 'database_error';
		}
		if ($successSet !== null && !$committed) {
			// Stripe has captured the funds. Keep that fact after rolling back every
			// accounting write, so --repair-only can finish without another charge.
			try {
				stripe_payment_update_returning($successSet, "id = $id and status <> 'succeeded' and status not in ('duplicate_paid', 'refunded', 'disputed')");
			} catch (Throwable $captureError) {
				stripe_log('capture record failed for ' . $id . ': ' . $captureError->getMessage());
			}
		}
		if ($e->getMessage() === 'Missing card clearing account') {
			stripeAlertMail('Stripe-betaling uden kortclearingkonto',
				"Betaling $id kunne ikke bogføres. Sæt DIV/3 box10, og kør stripe_charge_due.php --repair-only.",
				'noclearing_' . $id, 3600);
		}
		stripe_log('payment completion failed for ' . $id . ': ' . $e->getMessage());
		return 'database_error';
	} finally {
		if ($journalLocked) stripe_payment_lock(0, true);
		if ($locked) stripe_payment_lock($id, true);
		if ($invoiceLocked) stripe_invoice_collection_lock($ordreId, true);
	}
}

/**
 * Whether a capture no longer matches what the invoice owes, so it must not settle the invoice: the invoice is no longer fully
 * open (its open item was settled, changed or split, it was credited, or the customer got a credit) or its total is not the
 * captured amount. This happens when it was paid or credited another way while the customer was paying or authenticating.
 *
 * @param array $inv Invoice loaded under the booking lock.
 * @param array $payment The succeeded stripe_payments row.
 * @return bool Whether the capture is an overpayment for manual reconciliation.
 */
function stripe_payment_overpaid($inv, $payment) {
	return empty($inv['fully_open']) || (int)$payment['amount_ore'] !== (int)$inv['total_ore'];
}

/**
 * Tell the bookkeeper about an overpayment once. The customer gets no receipt: the bookkeeper reconciles the credit or
 * refunds it. confirmation_sent_at acknowledges this alert, as for duplicate_paid.
 *
 * @return string applied, or delivery_error so the repair pass tries the alert again.
 */
function stripe_payment_overpaid_alert($inv, $payment) {
	$sent = stripeAlertMail('Stripe: overbetaling - faktura ' . $payment['fakturanr'],
		"Faktura: " . $payment['fakturanr'] . "\nOrdre: " . $payment['ordre_id'] . "\nPaymentIntent: " . $payment['payment_intent_id']
		. "\nBeløb: " . number_format(((int)$payment['amount_ore']) / 100, 2, ',', '.') . " kr."
		. "\nKortbetalingen er gennemført, men fakturaen skylder den ikke længere: den er betalt eller krediteret på anden vis, eller dens åbne post svarer ikke til beløbet."
		. "\nBeløbet er bogført i kladden \"" . stripe_txt('journal_name') . "\" med referencen " . stripe_payment_overpaid_reference($payment) . " i stedet for fakturanummeret,"
		. " så det står som kundens tilgodehavende, når kladden er bogført, og hverken fakturaen eller en anden åben post udlignes automatisk."
		. "\nAfstem det manuelt, og refundér det i Stripe, hvis kunden ikke skal have det til gode. Kunden har ikke fået en kvittering.",
		'overpaid_' . $payment['payment_intent_id'], 3600);
	if (!$sent) return 'delivery_error';
	$marked = stripe_payment_update_returning('confirmation_sent_at = CURRENT_TIMESTAMP', 'id = ' . (int)$payment['id'] . " and confirmation_sent_at is null");
	if (!$marked) throw new RuntimeException('Overpayment alert marker update returned no row');
	return 'applied';
}

/**
 * The invoice reference (kassekladde.faktura) on the journal line of an overpaid capture, and so on the customer's open credit
 * when the journal is posted: "Stripe-overbetaling-<payment id>". The bookkeeper reconciles that credit by hand, so posting it
 * must settle nothing. Both automatic settlements in finans/bogfor.php match an open item by the invoice number: openpost()
 * when the line is posted, and equalizeMatchingRecords() (includes/genberegn.php) right after. No invoice or other open item
 * carries this reference, so neither settles anything with it, and their rules stay as they are for every other posting.
 * An empty invoice number would not do: it is matched like any other, so the credit would settle the customer's open item
 * without an invoice number of the same amount (an opening item, say). The reference also names the payment.
 *
 * @param array $payment The stripe_payments row.
 * @return string The reference.
 */
function stripe_payment_overpaid_reference($payment) {
	return 'Stripe-overbetaling-' . (int)$payment['id'];
}

/**
 * Apply a captured payment's "save the card" to the customer once, apart from its receipt (stripe_payments.card_state).
 * The card is not applied while the switches are off, when the invoice cannot be collected by card or when the payment has no
 * card (skipped). It is not applied either when the customer was unenrolled, or a later payment's card was applied, after
 * this payment (superseded): stripe_enrol_debtor() decides that under the customer's row lock.
 *
 * @param array $inv Loaded invoice.
 * @param array $payment The succeeded stripe_payments row.
 * @return string applied|skipped|superseded, or '' when Stripe could not be reached (the next run tries again).
 */
function stripe_payment_apply_card($inv, $payment) {
	$id = (int)$payment['id'];
	$row = db_fetch_array(stripe_payment_select("select card_state from stripe_payments where id = $id"));
	if ($row && $row['card_state'] === null) {
		// Made before card_state existed and not classified yet (an update that stopped half way): classified first, as the
		// update does (includes/stripeIncludes/stripeChargeSchema.php), never applied as undecided.
		if (!function_exists('stripe_payment_card_state_backfill')) require_once __DIR__ . '/stripeChargeSchema.php';
		stripe_payment_card_state_backfill();
		$row = db_fetch_array(stripe_payment_select("select card_state from stripe_payments where id = $id"));
	}
	$state = trim((string)($row['card_state'] ?? ''));
	if ($state !== '') return $state;
	$debtor = $inv['debtor'] ?? array();
	if (!stripe_invoice_collectable($inv) || !stripe_feature_on('enrolment') || !stripe_feature_on('invoice_payments')
		|| stripe_setting('enabled', 'off') !== 'on' || empty($payment['pm_id']) || !function_exists('stripe_enrol_debtor')) {
		$state = 'skipped';
	} elseif (($debtor['stripe'] ?? '') === 'on' && ($debtor['stripe_pm_id'] ?? '') === $payment['pm_id']
		&& ($debtor['stripe_customer_id'] ?? '') === $payment['stripe_customer_id']) {
		$state = 'applied'; // The customer already has this card.
	} else {
		if (!stripe_enrol_debtor($inv, $payment['pm_id'], $payment['pm_brand'], $payment['pm_last4'], $payment['stripe_customer_id'], $id)) return '';
		$row = db_fetch_array(stripe_payment_select("select card_state from stripe_payments where id = $id"));
		return trim((string)($row['card_state'] ?? ''));
	}
	stripe_payment_update_returning("card_state = '$state'", "id = $id and coalesce(card_state, '') = ''");
	$row = db_fetch_array(stripe_payment_select("select card_state from stripe_payments where id = $id"));
	return trim((string)($row['card_state'] ?? ''));
}

/**
 * Sweep captured payments missing bookkeeping/receipt or a duplicate-capture alert, and refund or dispute alerts that were
 * not sent. No charge API is called.
 *
 * @return array{found: int, repaired: int, failed: int} Repair counts; failures remain discoverable.
 */
function stripe_payment_repair($dryRun = false) {
	$q = stripe_payment_select("select id from stripe_payments where (status = 'succeeded' and (journal_line_id is null or confirmation_sent_at is null)) or (status = 'duplicate_paid' and confirmation_sent_at is null) order by id");
	$ids = array();
	while ($row = db_fetch_array($q)) $ids[] = (int)$row['id'];
	$stats = array('found' => count($ids), 'repaired' => 0, 'failed' => 0);
	foreach ($ids as $id) {
		if ($dryRun) continue;
		$result = stripe_payment_complete($id);
		if ($result === 'applied' || $result === 'duplicate' || $result === 'duplicate_paid') $stats['repaired']++;
		else $stats['failed']++;
	}
	foreach (stripe_payment_adjustment_alert_retry($dryRun) as $key => $count) $stats[$key] += $count;
	return $stats;
}

/**
 * Whether stripe_adjustment_alerts exists. betweenUpdates.php installs it at login, so a ledger that a webhook reaches before
 * anyone has logged in after the update can lack it; a refund or dispute alert then stays retryable through Stripe's
 * redelivery only.
 *
 * @return bool
 */
function stripe_adjustment_alerts_ready() {
	if (stripe_payment_mysql()) return false;
	$row = db_fetch_array(stripe_payment_select("select to_regclass('stripe_adjustment_alerts') is not null as ready"));
	return $row && stripe_bool($row['ready']);
}

/**
 * Tell the bookkeeper about a refund or dispute of an invoice payment, which is reconciled by hand, and mark the alert sent
 * in stripe_adjustment_alerts. An alert that is not sent stays there for the webhook redelivery and the repair pass.
 *
 * @param array $payment The stripe_payments row (fakturanr, ordre_id, payment_intent_id).
 * @param string $eventType charge.refunded or charge.dispute.created.
 * @param string $eventId The Stripe event.
 * @param bool $outbox Whether stripe_adjustment_alerts exists (stripe_adjustment_alerts_ready()).
 * @return bool Whether the alert was sent.
 */
function stripe_payment_adjustment_alert($payment, $eventType, $eventId, $outbox = true) {
	$refund = $eventType === 'charge.refunded';
	$sent = stripeAlertMail('Stripe: ' . ($refund ? 'refusion' : 'indsigelse') . ' - faktura ' . $payment['fakturanr'],
		"Faktura: " . $payment['fakturanr'] . "\nOrdre: " . $payment['ordre_id'] . "\nPaymentIntent: " . $payment['payment_intent_id'] . "\nEvent: $eventId\n"
		. "Afstem manuelt og opret kreditnota/ompostering efter den faste procedure. Ingen automatisk tilbageførsel er bogført.",
		'stripe_adjustment_' . $eventId, 3600);
	if (!$sent) return false;
	if ($outbox) stripe_payment_modify("update stripe_adjustment_alerts set sent_at = CURRENT_TIMESTAMP where event_id = '" . db_escape_string($eventId) . "' and sent_at is null");
	return true;
}

/**
 * Send the refund and dispute alerts that were not sent when their event came, also once Stripe has stopped redelivering
 * the event. An event row such an alert left 'failed' (delivery_error) for the redelivery is finished as processed, unless a
 * redelivery holds it.
 *
 * @return array{found: int, repaired: int, failed: int} Alerts found unsent, sent now, and still unsent.
 */
function stripe_payment_adjustment_alert_retry($dryRun = false) {
	$stats = array('found' => 0, 'repaired' => 0, 'failed' => 0);
	if (!stripe_adjustment_alerts_ready()) return $stats;
	$q = stripe_payment_select("select a.event_id, a.event_type, a.payment_id, p.ordre_id, p.fakturanr, p.payment_intent_id
		from stripe_adjustment_alerts a join stripe_payments p on p.id = a.payment_id where a.sent_at is null order by a.created_at, a.event_id");
	$alerts = array();
	while ($row = db_fetch_array($q)) $alerts[] = $row;
	$stats['found'] = count($alerts);
	foreach ($alerts as $alert) {
		if ($dryRun) continue;
		$locked = false;
		try {
			// The webhook sends the same alert under this lock: one of them sends it, the other finds it sent.
			stripe_payment_lock((int)$alert['payment_id']);
			$locked = true;
			$unsent = db_fetch_array(stripe_payment_select("select event_id from stripe_adjustment_alerts where event_id = '"
				. db_escape_string($alert['event_id']) . "' and sent_at is null"));
			if ($unsent && !stripe_payment_adjustment_alert($alert, $alert['event_type'], $alert['event_id'])) {
				$stats['failed']++;
				continue;
			}
			stripe_payment_modify("update stripe_events set status = 'processed', processed_at = CURRENT_TIMESTAMP, error = null, saldi_order_id = " . (int)$alert['ordre_id']
				. ", invoice_number = '" . db_escape_string((string)$alert['fakturanr']) . "' where event_id = '" . db_escape_string($alert['event_id'])
				. "' and status = 'failed' and error = 'delivery_error'");
			$stats['repaired']++;
		} catch (Throwable $e) {
			stripe_log('adjustment alert retry failed for ' . $alert['event_id'] . ': ' . $e->getMessage());
			$stats['failed']++;
		} finally {
			if ($locked) stripe_payment_lock((int)$alert['payment_id'], true);
		}
	}
	return $stats;
}

/**
 * Find the actual fiscal year containing the payment date; do not trust a browser session year.
 *
 * @return array{start: string, end: string, year: int} Fiscal bounds and RA kodenr.
 */
function stripe_payment_fiscal_bounds($date) {
	$q = stripe_payment_select("select kodenr, box1, box2, box3, box4 from grupper where art = 'RA' order by kodenr desc");
	while ($row = db_fetch_array($q)) {
		$start = sprintf('%04d-%02d-01', (int)$row['box2'], (int)$row['box1']);
		$endMonth = sprintf('%04d-%02d-01', (int)$row['box4'], (int)$row['box3']);
		if (!checkdate((int)$row['box1'], 1, (int)$row['box2']) || !checkdate((int)$row['box3'], 1, (int)$row['box4'])) continue;
		$end = (new DateTimeImmutable($endMonth))->format('Y-m-t');
		if ($start <= $date && $date <= $end) return array('start' => $start, 'end' => $end, 'year' => (int)$row['kodenr']);
	}
	throw new RuntimeException('No fiscal year for captured payment date ' . $date);
}

/**
 * Insert a line while holding the booking transaction. PostgreSQL table locks serialize
 * voucher allocation with journal writes/posting; user-owned journals are never selected.
 *
 * @return int Serial id of the inserted line.
 */
function stripe_payment_journal_insert($inv, $payment, $overpaid = false) {
	$date = substr((string)$payment['succeeded_at'], 0, 10);
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new RuntimeException('Missing capture date');
	$bounds = stripe_payment_fiscal_bounds($date);
	$clearing = stripe_payment_clearing_account();
	if ($clearing === '') throw new RuntimeException('Missing card clearing account');
	if (!stripe_payment_mysql()) {
		stripe_payment_select('lock table kladdeliste, kassekladde, transaktioner in share row exclusive mode');
	}
	$name = db_escape_string(stripe_txt('journal_name'));
	$text = db_escape_string(stripe_txt($overpaid ? 'journal_text_overpaid' : 'journal_text', array('card' => stripe_card_label($payment['pm_brand'], $payment['pm_last4']), 'nr' => $inv['nr'])));
	// An overpayment settles nothing: with a reference no open item carries, its posting stays open as the customer's credit.
	$faktura = db_escape_string($overpaid ? stripe_payment_overpaid_reference($payment) : $inv['nr']);
	$kontonr = db_escape_string((string)($inv['debtor']['kontonr'] ?? $inv['o']['kontonr']));
	$amount = number_format(((int)$payment['amount_ore']) / 100, 2, '.', '');
	// Pre-fix captures could commit a journal line and crash before saving its id.
	// Adopt one exact Stripe line; ambiguous historic duplicates need manual reconciliation.
	$existing = stripe_payment_select("select k.id from kassekladde k join kladdeliste l on l.id = k.kladde_id
		where k.ordre_id = " . (int)$inv['id'] . " and coalesce(k.faktura, '') = '$faktura'
		and l.kladdenote = '$name' and k.beskrivelse = '$text' and k.amount = '$amount'
		and k.debet = '" . db_escape_string($clearing) . "' and k.kredit = '$kontonr' order by k.id");
	$orphan = db_fetch_array($existing);
	if ($orphan) {
		if (db_fetch_array($existing)) throw new RuntimeException('Multiple historic Stripe journal lines require manual reconciliation');
		return (int)$orphan['id'];
	}
	$start = db_escape_string($bounds['start']);
	$end = db_escape_string($bounds['end']);
	$journal = db_fetch_array(stripe_payment_select("select id from kladdeliste where kladdenote = '$name'
		and (bogfort = '-' or bogfort = '' or bogfort is null)
		and coalesce(hvem, '') = '' and coalesce(tidspkt, '') = ''
		and kladdedate >= '$start' and kladdedate <= '$end' order by id desc limit 1 for update"));
	if (!$journal) {
		// Master allocates kladdeliste ids explicitly; its sequence may lag on real ledgers.
		// The table lock above (MySQL: the caller's payment lock 0) protects MAX + 1.
		$journal = db_fetch_array(stripe_payment_select('select coalesce(max(id), 0) + 1 as id from kladdeliste'));
		$newId = (int)$journal['id'];
		$sql = "insert into kladdeliste (id, kladdedate, kladdenote, bogfort, oprettet_af) values ($newId, '" . db_escape_string($date) . "', '$name', '-', 'stripe')";
		if (stripe_payment_mysql()) {
			stripe_payment_modify($sql);
			if (mysqli_affected_rows($GLOBALS['connection']) !== 1) {
				throw new RuntimeException('Journal insert returned no row');
			}
		} else {
			$journal = stripe_payment_insert_id($sql);
		}
	}
	$journalId = (int)$journal['id'];
	// Include posted movements too: journal rows may have been removed after posting.
	$row = db_fetch_array(stripe_payment_select("select coalesce(max(bilag), 0) as n from (
		select bilag from kassekladde where transdate >= '$start' and transdate <= '$end'
		union all select bilag from transaktioner where transdate >= '$start' and transdate <= '$end') fiscal_vouchers"));
	$bilag = (int)$row['n'] + 1;
	$project = db_escape_string((string)($inv['o']['projekt'] ?? ''));
	$department = (int)($inv['o']['afd'] ?? 0);
	$line = stripe_payment_insert_id("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, amount, kladde_id, faktura, ordre_id, projekt, afd)
		values ($bilag, '" . db_escape_string($date) . "', '$text', 'F', '" . db_escape_string($clearing) . "', 'D', '$kontonr', '$amount', $journalId, '"
		. "$faktura', " . (int)$inv['id'] . ", '$project', $department)");
	return (int)$line['id'];
}

/** @return array{id: int|string} Inserted row id, read from this INSERT only. */
function stripe_payment_insert_id($sql) {
	if (stripe_payment_mysql()) {
		stripe_payment_modify($sql);
		$id = mysqli_insert_id($GLOBALS['connection']);
		if (!$id) throw new RuntimeException('Insert returned no serial id');
		return array('id' => $id);
	}
	$row = db_fetch_array(stripe_payment_select($sql . ' returning id'));
	if (!$row) throw new RuntimeException('Insert returned no serial id');
	return $row;
}
