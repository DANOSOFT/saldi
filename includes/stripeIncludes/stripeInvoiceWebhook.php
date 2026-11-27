<?php
// 20261022 CDX/LH Invoice-only webhook dispatch; retry DB failures, ignore foreign intents, flag refunds/disputes.
// 20261022 CDX/LH Keep duplicate-capture refunds/disputes manual without creating a second journal line.
// 20261126 CL/ASR Review round 1 (I20): a database or delivery error leaves the event row 'failed' with that reason (stripe_invoice_webhook_release()), so the webhook lets Stripe's redelivery claim it at once.
// 20261126 CL/ASR Review round 1 (SM-3): the stripe_payments check filters on this database's schema (saldi_schema_filter()).
// 20261126 CL/ASR Review round 1 (S3, review): stripe_invoice_webhook_release()'s docblock says when a row it could not release is taken over (a claimed one too, since S3).
// 20261126 ASR Payment review (GPT-P-5): the dispatcher finishes or releases the event row only while it still carries the webhook delivery's claim token (stripe_invoice_webhook_owner_sql()).
//              A delivery whose claim was taken over no longer overwrites the row of the delivery that took it.
// 20261126 ASR Payment review (GPT-P-4): a refund or dispute is applied to the payment once per event, and its bookkeeper alert is kept in stripe_adjustment_alerts until it is sent.
//              An alert that is not sent answers 500 and leaves the event 'failed' (delivery_error), so Stripe's redelivery sends it without applying the event again; the repair pass sends it too.
// 20261126 ASR Payment review (GPT-G-6): the refund or dispute status and its stripe_adjustment_alerts row are written in one checked transaction, under the payment's lock and row lock, after the check for the event's row.
//              The alert is sent after COMMIT. A dispute outranks a refund, so a refund applied after it (a redelivery or a stale worker) leaves 'disputed'.

include_once(__DIR__ . '/stripeInvoice.php');
include_once(__DIR__ . '/stripeEnrol.php');

/**
 * The condition that the webhook delivery still holds the event row: it keeps its claim token in stripe_events.error from
 * the insert or claim until the row is finished or released. A delivery whose claim was taken over (15 minutes, see
 * stripe_wh_claimable_sql() in api/stripe/webhook.php) then writes nothing to the row.
 *
 * @param string|null $claimToken The delivery's token; null when the caller holds the row by other means (tests, tools).
 * @return string SQL to append to the row's WHERE clause, '' without a token.
 */
function stripe_invoice_webhook_owner_sql($claimToken) {
	return ($claimToken === null || $claimToken === '') ? '' : " and error = '" . db_escape_string((string)$claimToken) . "'";
}

/**
 * Handle invoice events on the already verified/claimed webhook connection. Subscription
 * refunds return null so the existing pilot branch handles them unchanged.
 *
 * @param string|null $claimToken The webhook delivery's claim token: the row is finished or released only while it carries it.
 * @return array{code: int, message: string}|null HTTP response, or null for the legacy dispatcher.
 */
function stripe_invoice_webhook_dispatch($rowId, $eventType, $obj, $eventId, $claimToken = null) {
	$isIntent = strpos($eventType, 'payment_intent.') === 0;
	$isAdjustment = in_array($eventType, array('charge.refunded', 'charge.dispute.created'), true);
	if (!$isIntent && !$isAdjustment) return null;
	$rowId = (int)$rowId;
	try {
		$orderId = (int)($obj['metadata']['saldi_ordre_id'] ?? 0);
		$nr = (string)($obj['metadata']['saldi_fakturanr'] ?? '');
		if ($isIntent) {
			if (!$orderId) {
				$status = 'ignored';
				$message = 'not_saldi_intent';
			} else {
				$message = stripe_webhook_payment_intent($eventType, $obj, $eventId);
				if (in_array($message, array('database_error', 'delivery_error'), true)) {
					stripe_invoice_webhook_release($rowId, $message, $claimToken);
					return array('code' => 500, 'message' => $message);
				}
				$status = $message === 'duplicate' ? 'duplicate' : ($message === 'ignored' ? 'ignored' : 'processed');
				if ($message === 'unknown') {
					stripeAlertMail('Stripe-betaling uden Saldi-faktura',
						"PaymentIntent " . (string)($obj['id'] ?? '?') . " ($eventType) kunne ikke knyttes til en faktura.",
						'unmapped_pi_' . (string)($obj['id'] ?? ''), 3600);
				}
				if ($message === 'unknown' || $message === 'unmapped') $status = 'unmapped';
			}
		} else {
			$payment = stripe_payment_apply_adjustment($eventType, $obj, $eventId);
			if (!$payment) return null;
			$orderId = (int)$payment['ordre_id'];
			$nr = (string)$payment['fakturanr'];
			if (empty($payment['alert_sent'])) {
				// Applied, but the bookkeeper has not been told: retryable, apart from the applied status.
				stripe_invoice_webhook_release($rowId, 'delivery_error', $claimToken);
				return array('code' => 500, 'message' => 'delivery_error');
			}
			$status = 'processed';
			$message = 'ok';
		}
		// The finish clears the delivery's claim token, and applies only while the row still carries it.
		stripe_payment_modify("update stripe_events set status = '" . db_escape_string($status)
			. "', processed_at = CURRENT_TIMESTAMP" . (stripe_invoice_webhook_owner_sql($claimToken) !== '' ? ', error = null' : '')
			. ", saldi_order_id = " . ($orderId ?: 'null')
			. ", invoice_number = '" . db_escape_string($nr) . "' where id = $rowId" . stripe_invoice_webhook_owner_sql($claimToken));
		return array('code' => 200, 'message' => $message);
	} catch (Throwable $e) {
		// Only release the claim on failure; retain its ownership while processing
		// so simultaneous retries cannot both enter a live invoice handler.
		try {
			stripe_invoice_webhook_release($rowId, 'database_error', $claimToken);
		} catch (Throwable $resetError) {
			stripe_log('invoice webhook retry reset failed: ' . $resetError->getMessage());
		}
		stripe_log('invoice webhook database failure: ' . $e->getMessage());
		return array('code' => 500, 'message' => 'database_error');
	}
}

/**
 * Release an event whose processing failed with a retryable error and is answered 500. A row left 'received' counts
 * as still in progress for 15 minutes, so a redelivery inside that window was answered 'duplicate' and Stripe stopped
 * retrying. 'failed' with the reason lets the webhook claim the redelivery at once (stripe_wh_claimable_sql()).
 *
 * @param int $rowId The stripe_events row this delivery inserted or claimed.
 * @param string $reason database_error or delivery_error.
 * @param string|null $claimToken The delivery's claim token: a row another delivery has taken over is left to it.
 * @return void Throws when the write fails. The row then stays 'received' (inserted by this delivery) or 'retrying'
 *              (claimed by it), and Stripe's next redelivery takes it over 15 minutes after it was received or claimed
 *              (stripe_wh_claimable_sql() in api/stripe/webhook.php).
 */
function stripe_invoice_webhook_release($rowId, $reason, $claimToken = null) {
	stripe_payment_modify("update stripe_events set status = 'failed', error = '" . db_escape_string($reason) . "' where id = " . (int)$rowId
		. " and status in ('received', 'retrying')" . stripe_invoice_webhook_owner_sql($claimToken));
}

/**
 * Flag a captured invoice payment for manual reconciliation; do not reverse its journal,
 * clear betalt or re-enable automatic collection. Subscription refunds remain with the pilot.
 * The flag is applied once per event, together with its row in stripe_adjustment_alerts, in one checked transaction under the
 * payment's lock (stripe_payment_lock(), as booking) and row lock: the event's row is looked for first, so an event delivered
 * again, or applied by a worker whose claim was taken over, changes nothing, and a failed write leaves neither. The bookkeeper
 * alert is sent after COMMIT, still under the payment's lock, so an event delivered again after an unsent alert only sends the
 * alert. Without stripe_adjustment_alerts (a ledger not logged in since the update) the redelivery applies the event again.
 *
 * @return array|null Matched stripe_payments row with alert_sent (whether the bookkeeper's alert is sent), or null when this
 *                    is not an invoice payment.
 */
function stripe_payment_apply_adjustment($eventType, $obj, $eventId) {
	$pi = $obj['payment_intent'] ?? '';
	if (is_array($pi)) $pi = $pi['id'] ?? '';
	if ($pi === '' && $eventType === 'charge.dispute.created' && !empty($obj['charge'])) {
		$charge = $obj['charge'];
		if (!is_array($charge)) {
			$response = stripeHttpRequest('GET', '/v1/charges/' . rawurlencode((string)$charge));
			if (!$response['ok']) throw new RuntimeException('Dispute charge lookup failed');
			$charge = $response['body'];
		}
		$pi = $charge['payment_intent'] ?? '';
		if (is_array($pi)) $pi = $pi['id'] ?? '';
	}
	if ($pi === '') return null;
	$exists = db_fetch_array(stripe_payment_select("select column_name from information_schema.columns where table_name = 'stripe_payments' and column_name = 'payment_intent_id'" . saldi_schema_filter()));
	if (!$exists) return null;
	$row = db_fetch_array(stripe_payment_select("select * from stripe_payments where payment_intent_id = '" . db_escape_string((string)$pi) . "'"));
	if (!$row) return null;
	// Refund/dispute delivery can overtake the success event. Reconcile the original
	// capture before flagging it, otherwise a later success could never book its receipt.
	if (!$row['journal_line_id'] && !in_array($row['status'], array('duplicate_paid', 'refunded', 'disputed'), true)) {
		if ($row['status'] === 'succeeded') {
			stripe_payment_complete((int)$row['id']);
		} else {
			$response = stripeHttpRequest('GET', '/v1/payment_intents/' . rawurlencode((string)$pi), array('expand' => array('latest_charge')));
			if (!$response['ok'] || ($response['body']['status'] ?? '') !== 'succeeded') throw new RuntimeException('Adjustment capture lookup failed');
			stripe_payment_apply_succeeded($response['body'], $eventId);
		}
		$row = db_fetch_array(stripe_payment_select("select * from stripe_payments where id = " . (int)$row['id']));
		if (!$row || !$row['journal_line_id']) throw new RuntimeException('Adjustment capture bookkeeping failed');
	}
	$status = $eventType === 'charge.refunded' ? 'refunded' : 'disputed';
	$outbox = stripe_adjustment_alerts_ready();
	$id = (int)$row['id'];
	$eventEsc = db_escape_string($eventId);
	$queued = null;
	stripe_payment_lock($id);
	try {
		$own = !stripe_payment_in_transaction();
		if ($own) stripe_payment_transaction('begin');
		try {
			$row = db_fetch_array(stripe_payment_select("select * from stripe_payments where id = $id for update"));
			if (!$row) throw new RuntimeException('Adjusted payment is missing');
			$queued = $outbox ? db_fetch_array(stripe_payment_select("select sent_at from stripe_adjustment_alerts where event_id = '$eventEsc'")) : null;
			if (!$queued) {
				$set = stripe_payment_adjustment_set($row, $status);
				if ($set !== '' && !stripe_payment_update_returning("$set, last_event_id = '$eventEsc'", "id = $id")) {
					throw new RuntimeException('Adjustment status update returned no row');
				}
				if ($outbox && !db_fetch_array(stripe_payment_select("insert into stripe_adjustment_alerts (event_id, payment_id, event_type)
					values ('$eventEsc', $id, '" . db_escape_string($eventType) . "') returning event_id"))) {
					throw new RuntimeException('Adjustment alert insert returned no row');
				}
			}
			if ($own) stripe_payment_transaction('commit');
		} catch (Throwable $e) {
			if ($own) transaktion('rollback');
			throw $e;
		}
		$row['alert_sent'] = ($queued && $queued['sent_at']) || stripe_payment_adjustment_alert($row, $eventType, $eventId, $outbox);
	} finally {
		stripe_payment_lock($id, true);
	}
	return $row;
}

/**
 * The change a refund or dispute event makes to its payment, as a SET clause. A duplicate_paid row keeps its status and
 * records the adjustment in last_error. A dispute outranks a refund: a refund applied after the payment's dispute (a
 * redelivery of an older event, or a worker that read the payment before the dispute) leaves 'disputed', and its alert is
 * still sent. The same status again, or a higher one, is written with the new event.
 *
 * @param array $row The stripe_payments row, read under its row lock.
 * @param string $status refunded or disputed.
 * @return string The SET clause, or '' when the event leaves the status as it is.
 */
function stripe_payment_adjustment_set($row, $status) {
	$rank = array('' => 0, 'refunded' => 1, 'disputed' => 2);
	if ($row['status'] === 'duplicate_paid') {
		$current = preg_match('/^duplicate_paid_(refunded|disputed)$/', (string)$row['last_error'], $m) ? $m[1] : '';
		return $rank[$status] >= $rank[$current] ? "last_error = 'duplicate_paid_$status'" : '';
	}
	$current = in_array($row['status'], array('refunded', 'disputed'), true) ? $row['status'] : '';
	return $rank[$status] >= $rank[$current] ? "status = '$status'" : '';
}
