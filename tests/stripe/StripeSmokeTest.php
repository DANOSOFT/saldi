<?php
// 20261022 CDX/LH Stripe master-port smoke tests against real PostgreSQL invoice rows.
// 20261022 CDX/LH Exercise payable with invoice context, required by the shared eligibility guard.
// 20261022 CDX/LH Assert signup defaults off independently of invoice collection and enrolment.
// 20261022 CDX/LH Give synthetic pending/paid state fixtures their required status, matching persisted payment rows.

require_once __DIR__ . '/bootstrap.php';

final class StripeSmokeTest extends StripeDatabaseTestCase
{
    public function testSignedLinkRoundTripAndTampering(): void
    {
        $id = StripeFixtures::invoice();
        $signature = stripe_link_sign($id);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);
        $this->assertTrue(stripe_link_verify($id, $signature));
        $this->assertFalse(stripe_link_verify($id + 1, $signature));
        $this->assertFalse(stripe_link_verify($id, str_repeat('z', 64)));
        $this->assertFalse(stripe_link_verify($id, substr($signature, 1)));
        StripeTestEnvironment::setting('stripe', 'link_secret', null);
        $this->assertSame('', stripe_link_sign($id));
        $this->assertFalse(stripe_link_verify($id, $signature));
    }

    public function testFixtureInvoiceHasExpectedAmountDueDateAndOpenItem(): void
    {
        $id = StripeFixtures::invoice();
        $invoice = stripe_invoice_load($id, StripeFixtures::TODAY);
        $this->assertNotNull($invoice);
        $this->assertSame(34000, $invoice['total_ore']);
        $this->assertSame('DKK', $invoice['currency']);
        $this->assertSame(StripeFixtures::DUE_DATE, $invoice['due']);
        $this->assertSame('open', $invoice['state']);
        $this->assertSame('Netto', $invoice['o']['betalingsbet']);
        $this->assertSame(8, (int)$invoice['o']['betalingsdage']);
        $this->assertCount(2, $invoice['lines']);
        $this->assertSame(200.0, $invoice['lines'][0]['sum']);
        $this->assertSame(72.0, $invoice['lines'][1]['sum']);
        $open = pg_fetch_assoc(StripeTestEnvironment::query(
            'SELECT * FROM openpost WHERE konto_id=$1 AND faktnr=$2',
            [$invoice['o']['konto_id'], $invoice['nr']]));
        $this->assertSame(34000, (int)round((float)$open['amount'] * 100));
        $this->assertSame($invoice['due'], $open['forfaldsdate']);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testUninvoicedOrderDoesNotLoadAsInvoice(): void
    {
        $id = StripeFixtures::order(StripeFixtures::debtor());
        $this->assertNull(stripe_invoice_load($id, StripeFixtures::TODAY));
    }

    public function testNextfaktDistinguishesOpenRecurringAndEnrolmentSwitchHidesIt(): void
    {
        $id = StripeFixtures::invoice(null, ['nextfakt' => '2026-02-08']);
        $invoice = stripe_invoice_load($id, StripeFixtures::TODAY);
        $this->assertTrue($invoice['recurring']);
        $this->assertSame('open_recurring', $invoice['state']);
        StripeTestEnvironment::setting('stripe', 'enrolment', 'off');
        $this->assertSame('open', stripe_invoice_load($id, StripeFixtures::TODAY)['state']);
        $this->assertTrue(stripe_invoice_payable(stripe_invoice_load($id, StripeFixtures::TODAY)));
        StripeTestEnvironment::setting('stripe', 'enrolment', 'on');
        $this->assertSame('open_recurring', stripe_invoice_load($id, StripeFixtures::TODAY)['state']);
    }

    public function testStateClockIsExplicitAndIndependentOfExecutionDate(): void
    {
        $id = StripeFixtures::invoice();
        $this->assertSame('open', stripe_invoice_load($id, '2026-01-16')['state']);
        $this->assertSame('overdue', stripe_invoice_load($id, '2026-01-17')['state']);
    }

    public function testFeatureDefaultsAndUnknownSwitchFailClosed(): void
    {
        $id = StripeFixtures::invoice();
        foreach (['invoice_payments', 'enrolment', 'signup_enabled'] as $name) {
            StripeTestEnvironment::setting('stripe', $name, null);
        }
        $this->assertFalse(stripe_feature_on('invoice_payments'));
        $this->assertFalse(stripe_feature_on('enrolment'));
        $this->assertFalse(stripe_feature_on('signup_enabled'));
        $this->assertFalse(stripe_feature_on('invented_feature'));
        $this->assertSame('pdf', stripe_invoice_format());
        $this->assertFalse(stripe_invoice_payable(stripe_invoice_load($id, StripeFixtures::TODAY)));
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'unexpected');
        $this->assertFalse(stripe_feature_on('invoice_payments'));
    }

    public function testSignupSwitchIsIndependentAndDefaultsOff(): void
    {
        $this->assertFalse(stripe_feature_on('signup_enabled'));
        StripeTestEnvironment::setting('stripe', 'signup_enabled', 'off');
        $this->assertFalse(stripe_feature_on('signup_enabled'));
        $this->assertTrue(stripe_feature_on('invoice_payments'));
        $this->assertTrue(stripe_feature_on('enrolment'));
        StripeTestEnvironment::setting('stripe', 'signup_enabled', 'on');
        $this->assertTrue(stripe_feature_on('signup_enabled'));
    }

    public function testInvoiceSwitchGatesFormatDeliveryAndPaymentEvenWithStaleState(): void
    {
        $id = StripeFixtures::invoice();
        $invoice = stripe_invoice_load($id, StripeFixtures::TODAY);
        $this->assertSame('link', stripe_invoice_format());
        $this->assertSame('link', stripe_invoice_delivery($id));
        $this->assertTrue(stripe_invoice_payable(stripe_invoice_load($id, StripeFixtures::TODAY)));
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'off');
        $this->assertSame('pdf', stripe_invoice_format());
        $this->assertSame('pdf', stripe_invoice_delivery($id));
        $this->assertSame('nocard', stripe_invoice_load($id, StripeFixtures::TODAY)['state']);
        foreach (['open', 'open_recurring', 'overdue', 'failed', 'enrolled'] as $state) {
            $gated = stripe_invoice_load($id, StripeFixtures::TODAY);
            $gated['state'] = $state;
            $this->assertFalse(stripe_invoice_payable($gated), $state);
        }
        $this->assertFalse(stripe_payment_create($invoice)['ok']);
        $this->assertFalse(stripe_mail_invoice_link($invoice));
        $this->assertSame([], StripeTestTransport::$requests);
        $this->assertSame([], StripeTestTransport::$mails);
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'on');
        $this->assertSame('link', stripe_invoice_format());
        $this->assertTrue(stripe_invoice_payable(stripe_invoice_load($id, StripeFixtures::TODAY)));
    }

    public function testMissingOrClassicFormatAndSharedEnabledSwitchStillUsePdf(): void
    {
        $id = StripeFixtures::invoice();
        StripeTestEnvironment::setting('email_settings', 'invoice_format', null);
        $this->assertSame('pdf', stripe_invoice_format());
        StripeTestEnvironment::setting('email_settings', 'invoice_format', 'pdf');
        $this->assertSame('pdf', stripe_invoice_format());
        StripeTestEnvironment::setting('email_settings', 'invoice_format', 'link');
        $this->assertSame('link', stripe_invoice_format());
        StripeTestEnvironment::setting('stripe', 'enabled', 'off');
        $this->assertSame('pdf', stripe_invoice_format());
        $this->assertFalse(stripe_invoice_payable(stripe_invoice_load($id, StripeFixtures::TODAY)));
    }

    public function testInvoiceSwitchDoesNotHideAlreadyPaidOrPendingStates(): void
    {
        $id = StripeFixtures::invoice();
        $invoice = stripe_invoice_load($id, StripeFixtures::TODAY);
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'off');
        StripeTestEnvironment::setting('stripe', 'enrolment', 'off');
        $invoice['pending'] = ['payment_intent_id' => 'pi_existing', 'status' => 'pending'];
        $this->assertSame('pending', stripe_invoice_state($invoice, StripeFixtures::TODAY));
        $invoice['paid'] = ['payment_intent_id' => 'pi_existing', 'status' => 'succeeded'];
        $this->assertSame('paid', stripe_invoice_state($invoice, StripeFixtures::TODAY));
        $this->assertFalse(stripe_invoice_payable($invoice));
        $invoice['paid'] = null;
        $this->assertFalse(stripe_invoice_payable($invoice));
    }

    public function testEnrolmentDisabledRefusesSavedCardCollectionAndEnrolment(): void
    {
        $debtor = StripeFixtures::debtor(['stripe_customer_id' => 'cus_fixture']);
        $id = StripeFixtures::invoice($debtor, ['nextfakt' => '2026-02-08']);
        $invoice = stripe_invoice_load($id, StripeFixtures::TODAY);
        $before = StripeFixtures::row('adresser', $debtor);
        StripeTestEnvironment::setting('stripe', 'enrolment', 'off');
        $this->assertFalse(stripe_enrol_debtor($invoice, 'pm_fixture'));
        foreach (['auto', 'backup', 'manual'] as $source) {
            $this->assertFalse(stripe_payment_create($invoice, $source, false, 'pm_fixture')['ok']);
        }
        $this->assertSame($before, StripeFixtures::row('adresser', $debtor));
        $this->assertSame([], StripeTestTransport::$requests);
        $this->assertSame([], StripeTestTransport::$mails);
    }

    public function testDisabledEnrolmentForcesSaveCardOffButAllowsOneOffPayment(): void
    {
        $debtor = StripeFixtures::debtor(['stripe_customer_id' => 'cus_fixture']);
        $id = StripeFixtures::invoice($debtor, ['nextfakt' => '2026-02-08']);
        StripeTestEnvironment::setting('stripe', 'enrolment', 'off');
        $invoice = stripe_invoice_load($id, StripeFixtures::TODAY);
        StripeTestTransport::queueBody($this->intent(), 'POST', '/v1/payment_intents');
        $result = stripe_payment_create($invoice, 'page', true);
        $this->assertTrue($result['ok']);
        $this->assertCount(1, StripeTestTransport::$requests);
        $request = StripeTestTransport::$requests[0];
        $this->assertSame(34000, $request['params']['amount']);
        $this->assertArrayNotHasKey('setup_future_usage', $request['params']);
        $this->assertSame('pi:' . $GLOBALS['db'] . ':' . $id . ':1:n', $request['idempotency_key']);
        $payment = pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM stripe_payments WHERE ordre_id=$1', [$id]));
        $this->assertFalse(stripe_bool($payment['save_card']));
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testEnabledEnrolmentRetainsSaveCardPaymentAndRouting(): void
    {
        $debtor = StripeFixtures::debtor(['stripe_customer_id' => 'cus_fixture']);
        $id = StripeFixtures::invoice($debtor, ['nextfakt' => '2026-02-08']);
        $invoice = stripe_invoice_load($id, StripeFixtures::TODAY);
        StripeTestTransport::queueBody($this->intent(), 'POST', '/v1/payment_intents');
        $this->assertTrue(stripe_payment_create($invoice, 'page', true)['ok']);
        $this->assertSame('off_session', StripeTestTransport::$requests[0]['params']['setup_future_usage']);
        StripeTestTransport::queueBody(['id' => 'cus_fixture'], 'POST', '/v1/customers/cus_fixture');
        StripeTestTransport::queueBody(['id' => 'pm_fixture'], 'POST', '/v1/payment_methods/pm_fixture');
        $this->assertTrue(stripe_enrol_debtor($invoice, 'pm_fixture', 'visa', '4242'));
        $debtorRow = StripeFixtures::row('adresser', $debtor);
        $this->assertSame('on', $debtorRow['stripe']);
        $this->assertSame('pm_fixture', $debtorRow['stripe_pm_id']);
        $this->assertSame('Stripe', $debtorRow['udskriv_til']);
        $this->assertSame('Stripe', StripeFixtures::row('ordrer', $id)['udskriv_til']);
        $this->assertSame('stripe', stripe_invoice_delivery($id));
        StripeTestEnvironment::setting('stripe', 'enrolment', 'off');
        $this->assertSame('pdf', stripe_invoice_delivery($id));
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testMailTemplatesRemainLoadedWithFakeSenderAndCaptureRealLinkMail(): void
    {
        $id = StripeFixtures::invoice();
        $invoice = stripe_invoice_load($id, StripeFixtures::TODAY);
        StripeTestEnvironment::setting('email_settings', 'invoice_link_text', "Hej [Fornavn], her er jeres faktura.");
        $this->assertTrue(stripe_mail_invoice_link($invoice));
        $this->assertCount(1, StripeTestTransport::$mails);
        $mail = StripeTestTransport::$mails[0];
        $this->assertSame('debtor@stripe-tests.invalid', $mail['to']);
        $this->assertStringContainsString('Hej Anne, her er jeres faktura.', $mail['text']);
        $this->assertStringContainsString(stripe_invoice_url($id), $mail['text']);
        $this->assertStringContainsString('340,00 kr.', $mail['html']);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    /** Canned deferred card intent; the fake client secret grants access to nothing. */
    private function intent(): array
    {
        return [
            'id' => 'pi_fixture', 'customer' => 'cus_fixture', 'amount' => 34000,
            'currency' => 'dkk', 'status' => 'requires_payment_method',
            'client_secret' => TestCredentials::password('stripe_client'),
        ];
    }
}
