<?php
// 20261022 CDX/LH Execute preserved subscription dispatch branches with real tenant SQL and captured finish/mail boundaries.

require_once __DIR__ . '/bootstrap.php';

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

final class StripePilotTest extends StripeDatabaseTestCase
{
    /**
     * Execute the existing subscription handlers, redirecting their checked DB wrappers
     * to the same checked tenant queries. Missing invoice_pdf deliberately avoids external PDF I/O.
     *
     * @return array{status: string, respCode: int, respMsg: string, orderId: int, invoiceNumber: string, error: string}
     */
    private function dispatch(string $eventType, array $obj): array
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
        $db = $GLOBALS['db'];
        $importEnabled = false;
        try {
            eval($branches);
        } catch (StripePilotFinished $finished) {
            return $finished->result;
        }
        $this->fail('Expected a terminal pilot response');
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
}
