<?php
// 20261022 CDX/LH Execute preserved subscription dispatch branches with real tenant SQL and captured finish/mail boundaries.
// 20261126 CL/ASR Review round 1 (I8): dispatch() runs the webhook's ledger gate first and can run as another (not pinned) ledger.
// 20261126 CL/ASR Review round 1 (I20): which existing stripe_events rows a redelivery may claim.
// 20261126 CL/ASR Review round 1 (S3, review): the webhook's claim itself runs (claim()); a claimed row still 'retrying' 15 minutes after the claim is taken over, a fresh claim is not.
// 20261126 ASR Payment review (GPT-P-5): a row held by a delivery that has not finished is answered 409 in_progress, not 200 duplicate; the claimer keeps its token on the row, and only the delivery holding it finishes the row (the webhook's stripe_wh_finish()).

require_once __DIR__ . '/support/bootstrap.php';

/** Captures the terminal HTTP boundary without loading the endpoint's master DB/session bootstrap. */
final class StripePilotFinished extends RuntimeException
{
    public array $result;

    public function __construct(array $result)
    {
        parent::__construct('Pilot dispatch completed');
        $this->result = $result;
    }
}

/** @return never Stand-in for the HTTP exit only; the production branch decisions execute unchanged. */
function stripe_wh_finish($rowId, $status, $respCode, $respMsg, $orderId = 0, $invoiceNumber = '', $error = '')
{
    throw new StripePilotFinished(compact('status', 'respCode', 'respMsg', 'orderId', 'invoiceNumber', 'error'));
}

/** @return never Stand-in for the early HTTP exit (no stripe_events row), as stripe_wh_finish() above. */
function stripe_wh_respond($status, $msg)
{
    throw new StripePilotFinished(['status' => '', 'respCode' => $status, 'respMsg' => $msg, 'orderId' => 0, 'invoiceNumber' => '', 'error' => '']);
}

final class StripePilotTest extends StripeDatabaseTestCase
{
    /**
     * Execute the existing subscription handlers, redirecting their checked DB wrappers
     * to the same checked tenant queries. Missing invoice_pdf deliberately avoids external PDF I/O.
     *
     * @return array{status: string, respCode: int, respMsg: string, orderId: int, invoiceNumber: string, error: string}
     */
    private function dispatch(string $eventType, array $obj, ?string $ledger = null): array
    {
        $source = file_get_contents(__DIR__ . '/../../api/stripe/webhook.php');
        if (!function_exists('stripe_wh_upsert_customer')) {
            $start = strpos($source, 'function stripe_wh_upsert_customer(');
            $helpers = substr($source, $start, strpos($source, '// ---- dispatch', $start) - $start);
            eval(str_replace(['stripe_wh_db_select(', 'stripe_wh_require_modify('], ['stripe_payment_select(', 'stripe_payment_modify('], $helpers));
        }
        $start = strpos($source, "if (\$eventType === 'checkout.session.completed')");
        $branches = substr($source, $start, strpos($source, '// Invoice intents/adjustments share', $start) - $start);
        $start = strpos($source, "if (\$eventType === 'charge.refunded')");
        $branches .= substr($source, $start, strpos($source, '// Unknown/unhandled events:', $start) - $start);
        $branches = str_replace(['stripe_wh_db_select(', 'stripe_wh_require_modify('], ['stripe_payment_select(', 'stripe_payment_modify('], $branches);
        $rowId = 0;
        $eventId = 'evt_pilot_regression';
        $db = $ledger ?? $GLOBALS['db'];
        $importEnabled = false;
        try {
            eval($this->gateSource($source));
            eval($branches);
        } catch (StripePilotFinished $finished) {
            return $finished->result;
        }
        $this->fail('Expected a terminal pilot response');
    }

    /**
     * The webhook's ledger gate as it runs before the event is recorded; its functions are declared once.
     *
     * @return string Statements that set $objectLedger and may respond 200 other_ledger.
     */
    private function gateSource(string $source): string
    {
        $start = strpos($source, 'function stripe_wh_object_ledger(');
        $call = strpos($source, '$objectLedger = stripe_wh_object_ledger($obj);', $start);
        if (!function_exists('stripe_wh_object_ledger')) {
            eval(substr($source, $start, $call - $start));
        }
        return substr($source, $call, strpos($source, '$mode ', $call) - $call);
    }

    /** @return array|null The gate's early response, or null when the event goes on to its handler. */
    private function gate(string $eventType, array $obj, string $db): ?array
    {
        $source = file_get_contents(__DIR__ . '/../../api/stripe/webhook.php');
        try {
            eval($this->gateSource($source));
        } catch (StripePilotFinished $finished) {
            return $finished->result;
        }
        return null;
    }

    /** @return array<string, mixed> Subscription invoice in the pinned Dahlia parent-metadata shape. */
    private function invoiceEvent(int $id): array
    {
        $o = StripeFixtures::row('ordrer', $id);
        return ['id' => 'in_pilot', 'number' => 'PILOT-821', 'customer' => 'cus_pilot_regression',
            'billing_reason' => 'subscription_cycle', 'status' => 'paid', 'currency' => 'dkk',
            'amount_remaining' => 0, 'amount_paid' => 34000,
            'parent' => ['subscription_details' => ['subscription' => 'sub_pilot_regression', 'metadata' => [
                'saldi_order_id' => $id, 'saldi_konto_id' => $o['konto_id'], 'saldi_kontonr' => $o['kontonr']]]]];
    }

    public function testExistingCheckoutAndPaidSubscriptionStillRecordWithoutInvoiceCollectionOrRecurrenceChanges(): void
    {
        $id = StripeFixtures::invoice(null, ['nextfakt' => '2026-11-15']);
        $before = StripeFixtures::row('ordrer', $id);
        StripeTestEnvironment::setting('stripe', 'signup_enabled', 'off');
        $checkout = $this->dispatch('checkout.session.completed', ['client_reference_id' => (string)$id,
            'metadata' => ['saldi_konto_id' => $before['konto_id']], 'customer' => 'cus_pilot_regression', 'subscription' => 'sub_pilot_regression']);
        $this->assertSame('processed', $checkout['status']);
        $this->assertSame(200, $checkout['respCode']);
        $paid = $this->dispatch('invoice.paid', $this->invoiceEvent($id));
        $this->assertSame('recorded', $paid['status']);
        $this->assertSame($id, $paid['orderId']);
        $this->assertSame('PILOT-821', $paid['invoiceNumber']);
        $rows = pg_fetch_all(StripeTestEnvironment::query("SELECT * FROM stripe_customers WHERE stripe_customer_id='cus_pilot_regression'"));
        $this->assertCount(1, $rows);
        $this->assertSame('sub_pilot_regression', $rows[0]['stripe_subscription_id']);
        $this->assertSame($before, StripeFixtures::row('ordrer', $id));
        $this->assertFalse(stripe_invoice_collectable(stripe_invoice_load($id)));
        $this->assertSame([], StripeTestTransport::$requests);
        $this->assertCount(2, StripeTestTransport::$mails);
        $this->assertStringContainsString('bogfør manuelt', StripeTestTransport::$mails[1]['subject']);
    }

    public function testSubscriptionInvoiceBeforeCheckoutUpsertsIdentityAndRenewalCanUseCache(): void
    {
        $id = StripeFixtures::invoice();
        $event = $this->invoiceEvent($id);
        $this->assertSame('recorded', $this->dispatch('invoice.paid', $event)['status']);
        unset($event['parent']['subscription_details']['metadata']);
        $this->assertSame($id, $this->dispatch('invoice.paid', $event)['orderId']);
        $this->assertSame([], StripeTestTransport::$requests);
        $this->assertCount(2, StripeTestTransport::$mails);
    }

    public function testSubscriptionFailureRefundAndDeletionKeepManualPilotBehaviorWithRolloutSwitchesOff(): void
    {
        $id = StripeFixtures::invoice(null, ['nextfakt' => '2026-11-15']);
        $event = $this->invoiceEvent($id);
        $this->dispatch('invoice.paid', $event);
        StripeTestTransport::reset();
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'off');
        StripeTestEnvironment::setting('stripe', 'enrolment', 'off');
        StripeTestEnvironment::setting('stripe', 'signup_enabled', 'off');
        $before = StripeFixtures::row('ordrer', $id);
        $this->assertSame(200, $this->dispatch('invoice.payment_failed', $event)['respCode']);
        $this->assertSame(200, $this->dispatch('charge.refunded', ['id' => 'ch_pilot', 'customer' => $event['customer'], 'amount_refunded' => 34000])['respCode']);
        $deleted = $this->dispatch('customer.subscription.deleted', ['id' => 'sub_pilot_regression', 'customer' => $event['customer'],
            'metadata' => $event['parent']['subscription_details']['metadata']]);
        $this->assertSame('processed', $deleted['status']);
        $cache = pg_fetch_assoc(StripeTestEnvironment::query("SELECT status FROM stripe_customers WHERE stripe_customer_id='cus_pilot_regression'"));
        $this->assertSame('canceled', $cache['status']);
        $this->assertSame($before, StripeFixtures::row('ordrer', $id));
        $this->assertCount(3, StripeTestTransport::$mails);
        $this->assertStringContainsString('FEJLEDE', StripeTestTransport::$mails[0]['subject']);
        $this->assertStringContainsString('REFUSION', StripeTestTransport::$mails[1]['subject']);
        $this->assertStringContainsString('OPSAGT', StripeTestTransport::$mails[2]['subject']);
        $this->assertSame([], StripeTestTransport::$requests);
    }
    public function testUntaggedSubscriptionEventsAreProcessedOnlyInThePinnedLedger(): void
    {
        $id = StripeFixtures::invoice(null, ['nextfakt' => '2026-11-15']);
        $before = StripeFixtures::row('ordrer', $id);
        $paid = $this->invoiceEvent($id);
        $untagged = [
            'checkout.session.completed' => ['client_reference_id' => (string)$id, 'metadata' => ['saldi_konto_id' => $before['konto_id']],
                'customer' => 'cus_pilot_regression', 'subscription' => 'sub_pilot_regression'],
            'invoice.paid' => $paid,
            'invoice.payment_failed' => $paid,
            'customer.subscription.deleted' => ['id' => 'sub_pilot_regression', 'customer' => 'cus_pilot_regression',
                'metadata' => $paid['parent']['subscription_details']['metadata']],
        ];
        foreach ($untagged as $type => $obj) {
            $other = $this->dispatch($type, $obj, 'other_ledger_b');
            $this->assertSame(['', 200, 'other_ledger'], [$other['status'], $other['respCode'], $other['respMsg']], $type);
        }
        $this->assertFalse(pg_fetch_assoc(StripeTestEnvironment::query("SELECT id FROM stripe_customers WHERE stripe_customer_id='cus_pilot_regression'")));
        $this->assertSame([], StripeTestTransport::$mails);
        $this->assertSame($before, StripeFixtures::row('ordrer', $id));

        // Tagged with that ledger, it is its own subscription; untagged in the pinned ledger, the pilot's.
        $tagged = $paid;
        $tagged['parent']['subscription_details']['metadata']['saldi_db'] = 'other_ledger_b';
        $this->assertSame('recorded', $this->dispatch('invoice.paid', $tagged, 'other_ledger_b')['status']);
        $this->assertSame('other_ledger', $this->dispatch('invoice.paid', $tagged)['respMsg'], 'tagged with another ledger');
        $this->assertSame('recorded', $this->dispatch('invoice.paid', $paid)['status']);

        // Intents, refunds and disputes pass the gate untagged: their handlers match them on their own ids.
        foreach (['payment_intent.succeeded', 'charge.refunded', 'charge.dispute.created'] as $type) {
            $this->assertNull($this->gate($type, ['id' => 'x_untagged'], 'other_ledger_b'), $type);
        }
    }

    public function testPilotRefundAlertGoesOnlyToThePinnedLedgerOrOneThatKnowsTheCustomer(): void
    {
        $refund = ['id' => 'ch_pilot', 'customer' => 'cus_pilot_unknown', 'amount_refunded' => 34000];
        $other = $this->dispatch('charge.refunded', $refund, 'other_ledger_b');
        $this->assertSame(['ignored', 200, 'other_ledger'], [$other['status'], $other['respCode'], $other['respMsg']]);
        $this->assertSame([], StripeTestTransport::$mails);

        $tagged = $refund + ['metadata' => ['saldi_db' => 'other_ledger_b']];
        $this->assertSame('processed', $this->dispatch('charge.refunded', $tagged, 'other_ledger_b')['status']);
        $this->assertCount(1, StripeTestTransport::$mails);

        StripeFixtures::insert('stripe_customers', ['stripe_customer_id' => 'cus_pilot_known', 'kontonr' => '4711', 'status' => 'active']);
        $known = $this->dispatch('charge.refunded', ['customer' => 'cus_pilot_known'] + $refund, 'other_ledger_b');
        $this->assertSame('processed', $known['status']);
        $this->assertStringContainsString('4711', StripeTestTransport::$mails[1]['subject']);

        $this->assertSame('processed', $this->dispatch('charge.refunded', $refund)['status'], 'the pinned ledger');
        $this->assertCount(3, StripeTestTransport::$mails);
        $this->assertStringContainsString('REFUSION', StripeTestTransport::$mails[2]['subject']);
    }

    public function testARetryableOrAbandonedRowIsClaimedAndARowStillBeingProcessedIsAnswered409(): void
    {
        $source = file_get_contents(__DIR__ . '/../../api/stripe/webhook.php');
        // [status, error, received ago, claimed ago (processed_at) or null, what the redelivery gets]
        $rows = [
            'failed unmatched subscription invoice' => ['failed', 'unmapped_customer', '0 minutes', null, 'claimed'],
            'failed invoice database error' => ['failed', 'database_error', '0 minutes', null, 'claimed'],
            'failed invoice delivery error' => ['failed', 'delivery_error', '0 minutes', null, 'claimed'],
            'failed import not wired' => ['failed', 'import_enabled but service not present on this deploy', '0 minutes', null, 'duplicate'],
            'received, still in progress' => ['received', 'tok_first', '1 minute', null, 'in_progress'],
            'received, process died' => ['received', 'tok_first', '20 minutes', null, 'claimed'],
            'retrying, claimed a minute ago' => ['retrying', 'tok_claimer', '20 minutes', '1 minute', 'in_progress'],
            'retrying, claim died or not released' => ['retrying', 'tok_claimer', '20 minutes', '20 minutes', 'claimed'],
            'retrying, claimed before the claim time was kept' => ['retrying', null, '20 minutes', null, 'claimed'],
            'recorded' => ['recorded', null, '20 minutes', null, 'duplicate'],
            'processed' => ['processed', null, '20 minutes', '20 minutes', 'duplicate'],
        ];
        foreach ($rows as $name => [$status, $error, $age, $claimedAgo, $expected]) {
            $eventId = 'evt_claim_' . md5($name);
            StripeTestEnvironment::query("INSERT INTO stripe_events (event_id, event_type, status, error, received_at, processed_at)
                VALUES ($1, 'invoice.paid', $2, $3, CURRENT_TIMESTAMP - $4::interval, CURRENT_TIMESTAMP - $5::interval)",
                [$eventId, $status, $error, $age, $claimedAgo]);
            $answer = $this->claim($source, $eventId);
            $this->assertSame($expected, is_array($answer) ? 'claimed' : $answer, $name);
            if (is_array($answer)) {
                $row = pg_fetch_assoc(StripeTestEnvironment::query("SELECT status, error, processed_at > CURRENT_TIMESTAMP - interval '1 minute' AS fresh
                    FROM stripe_events WHERE event_id = $1", [$eventId]));
                $this->assertSame(['retrying', $answer['token'], 't'], [$row['status'], $row['error'], $row['fresh']], $name . ': claimed now, the token kept on the row');
                $this->assertSame('in_progress', $this->claim($source, $eventId), $name . ': a second redelivery right after the claim is not acknowledged');
            }
        }
    }

    public function testOnlyTheDeliveryHoldingTheRowFinishesIt(): void
    {
        $source = file_get_contents(__DIR__ . '/../../api/stripe/webhook.php');
        StripeTestEnvironment::query("INSERT INTO stripe_events (event_id, event_type, status, error, received_at, processed_at)
            VALUES ('evt_taken_over', 'invoice.paid', 'retrying', 'tok_slow', CURRENT_TIMESTAMP - interval '30 minutes', CURRENT_TIMESTAMP - interval '20 minutes')");
        // The slow delivery's claim is 20 minutes old: a redelivery takes the row over.
        $taken = $this->claim($source, 'evt_taken_over');
        $this->assertIsArray($taken);
        $finish = $this->finisher($source);
        // The slow delivery finishes after all: Stripe gets its answer, the row stays the new delivery's.
        $this->assertSame([200, 'recorded'], $finish($taken['rowId'], 'tok_slow', 'recorded', 200, 'recorded'));
        $this->assertSame([500, 'unmapped_customer'], $finish($taken['rowId'], 'tok_slow', 'failed', 500, 'unmapped_customer', 'unmapped_customer'));
        $row = pg_fetch_assoc(StripeTestEnvironment::query("SELECT status, error FROM stripe_events WHERE id = $1", [$taken['rowId']]));
        $this->assertSame(['retrying', $taken['token']], [$row['status'], $row['error']]);
        // The delivery holding the row finishes it, and the finish clears its token.
        $this->assertSame([200, 'recorded'], $finish($taken['rowId'], $taken['token'], 'recorded', 200, 'recorded'));
        $row = pg_fetch_assoc(StripeTestEnvironment::query("SELECT status, error FROM stripe_events WHERE id = $1", [$taken['rowId']]));
        $this->assertSame(['recorded', null], [$row['status'], $row['error']]);
        $this->assertSame('duplicate', $this->claim($source, 'evt_taken_over'));
    }

    /**
     * Run the webhook's claim of an existing stripe_events row, as a redelivery of $eventId does.
     *
     * @return array{rowId: int, token: string}|string The claimed row and this delivery's token, or the answer:
     *                                                  'in_progress' (409) or 'duplicate' (200).
     */
    private function claim(string $source, string $eventId)
    {
        if (!function_exists('stripe_wh_claimable_sql')) {
            $start = strpos($source, 'function stripe_wh_claimable_sql(');
            eval(substr($source, $start, strpos($source, '$existing = ', $start) - $start));
        }
        $start = strpos($source, '$existing = db_fetch_array(');
        $claimCode = substr($source, $start, strpos($source, "} else {\n\t\$insertResult", $start) - $start) . '}';
        $claimCode = str_replace(['stripe_wh_db_select(', 'stripe_wh_require_modify('], ['stripe_payment_select(', 'stripe_payment_modify('], $claimCode);
        $eventIdEsc = db_escape_string($eventId);
        $token = bin2hex(random_bytes(16));
        $db_type = 'postgresql';
        $rowId = null;
        try {
            eval($claimCode);
        } catch (StripePilotFinished $finished) {
            $answer = [$finished->result['respCode'], $finished->result['respMsg']];
            $this->assertContains($answer, [[409, 'in_progress'], [200, 'duplicate']]);
            return $answer[1];
        }
        return ['rowId' => (int)$rowId, 'token' => $token];
    }

    /**
     * The webhook's own stripe_wh_finish() (the test file declares a stand-in under that name), as the delivery holding
     * $token runs it.
     *
     * @return callable(int, string, string, int, string, string=): array{0: int, 1: string} The HTTP answer it gave.
     */
    private function finisher(string $source): callable
    {
        if (!function_exists('stripe_wh_finish_as_webhook')) {
            $start = strpos($source, 'function stripe_wh_finish(');
            $code = substr($source, $start, strpos($source, "\n}\n", $start) + 3 - $start);
            eval(str_replace(['function stripe_wh_finish(', 'stripe_wh_require_modify('], ['function stripe_wh_finish_as_webhook(', 'stripe_payment_modify('], $code));
        }
        return static function (int $rowId, string $token, string $status, int $code, string $message, string $error = ''): array {
            $GLOBALS['token'] = $token;
            try {
                stripe_wh_finish_as_webhook($rowId, $status, $code, $message, 0, '', $error);
            } catch (StripePilotFinished $finished) {
                return [$finished->result['respCode'], $finished->result['respMsg']];
            } finally {
                unset($GLOBALS['token']);
            }
            return [0, ''];
        };
    }
}
