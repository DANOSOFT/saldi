<?php
// 20261022 CDX/LH Invoice-only webhook dispatch; retry DB failures, ignore foreign intents, flag refunds/disputes.
// 20261022 CDX/LH Keep duplicate-capture refunds/disputes manual without creating a second journal line.

include_once(__DIR__ . '/stripeInvoice.php');
include_once(__DIR__ . '/stripeEnrol.php');

/**
 * Handle invoice events on the already verified/claimed webhook connection. Subscription
 * refunds return null so the existing pilot branch handles them unchanged.
 *
 * @return array{code: int, message: string}|null HTTP response, or null for the legacy dispatcher.
 */
function stripe_invoice_webhook_dispatch($rowId, $eventType, $obj, $eventId) {
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
					stripe_payment_modify("update stripe_events set status = 'received' where id = $rowId and status = 'retrying'");
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
			$status = 'processed';
			$message = 'ok';
		}
		stripe_payment_modify("update stripe_events set status = '" . db_escape_string($status)
			. "', processed_at = CURRENT_TIMESTAMP, saldi_order_id = " . ($orderId ?: 'null')
			. ", invoice_number = '" . db_escape_string($nr) . "' where id = $rowId");
		return array('code' => 200, 'message' => $message);
	} catch (Throwable $e) {
		// Only release the inherited stale claim on failure; retain its ownership while
		// processing so simultaneous retries cannot both enter a live invoice handler.
		try {
			stripe_payment_modify("update stripe_events set status = 'received' where id = $rowId and status = 'retrying'");
		} catch (Throwable $resetError) {
			stripe_log('invoice webhook retry reset failed: ' . $resetError->getMessage());
		}
		stripe_log('invoice webhook database failure: ' . $e->getMessage());
		return array('code' => 500, 'message' => 'database_error');
	}
}

/**
 * Flag a captured invoice payment for manual reconciliation; do not reverse its journal,
 * clear betalt or re-enable automatic collection. Subscription refunds remain with the pilot.
 *
 * @return array|null Matched stripe_payments row, or null when this is not an invoice payment.
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
	$exists = db_fetch_array(stripe_payment_select("select column_name from information_schema.columns where table_name = 'stripe_payments' and column_name = 'payment_intent_id'"));
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
	$adjustmentSet = $row['status'] === 'duplicate_paid' ? "last_error = 'duplicate_paid_$status'" : "status = '$status'";
	stripe_payment_modify("update stripe_payments set $adjustmentSet, last_event_id = '" . db_escape_string($eventId) . "' where id = " . (int)$row['id']);
	stripeAlertMail('Stripe: ' . ($status === 'refunded' ? 'refusion' : 'indsigelse') . ' - faktura ' . $row['fakturanr'],
		"Faktura: " . $row['fakturanr'] . "\nOrdre: " . $row['ordre_id'] . "\nPaymentIntent: $pi\nEvent: $eventId\n"
		. "Afstem manuelt og opret kreditnota/ompostering efter den faste procedure. Ingen automatisk tilbageførsel er bogført.",
		'stripe_adjustment_' . $eventId, 3600);
	return $row;
}
