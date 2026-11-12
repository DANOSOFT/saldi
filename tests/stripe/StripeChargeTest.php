<?php
// 20261022 CDX/LH Deterministic charge schedules, request recovery, authentication, backup, enrolment and Afmeld regressions.
// 20261022 CDX/LH Use the private opaque PDF destination for Afmeld attachment fixtures.
// 20261022 CDX/LH Cover full job exit/repair semantics, manual authentication, abandoned 3DS, replacement after decline and eligibility changes.
// 20261022 CDX/LH Separate retryable rate limits from rejected creates and cover ambiguous auto requests after Afmeld.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/stripeIncludes/stripeInvoiceWebhook.php';

/** Uses temporary tables because production success/enrolment work commits transactions. */
final class StripeChargeTest extends PHPUnit\Framework\TestCase
{
    private array $tables = [];
    private array $sequences = [];
    private array $pdfs = [];
    private ?string $timezone = null;

    protected function setUp(): void
    {
        parent::setUp();
        $reason = StripeTestEnvironment::unavailableReason();
        if ($reason !== null) $this->markTestSkipped($reason);
        StripeTestEnvironment::connect();
        StripeTestTransport::reset();
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Copenhagen');
        $GLOBALS['stripe_charge_today'] = '2026-10-22';
        $GLOBALS['db_transaktion_depth'] = 0;
        $GLOBALS['db_modify_fejl'] = false;
        foreach (['adresser', 'ordrer', 'ordrelinjer', 'openpost', 'stripe_payments', 'stripe_customers',
            'grupper', 'settings', 'kladdeliste', 'kassekladde', 'transaktioner', 'stripe_collection_attempts'] as $table) {
            StripeTestEnvironment::query("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING ALL)");
            $this->tables[] = $table;
            $sequence = 's3_' . $table . '_seq';
            StripeTestEnvironment::query("CREATE TEMP SEQUENCE $sequence");
            $this->sequences[] = $sequence;
            StripeTestEnvironment::query("ALTER TABLE pg_temp.$table ALTER COLUMN id SET DEFAULT nextval('pg_temp.$sequence')");
        }
        foreach (['stripe_charge_errors', 'stripe_unenrol_mail'] as $table) {
            StripeTestEnvironment::query("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING ALL)");
            $this->tables[] = $table;
        }
        StripeTestEnvironment::query("CREATE TEMP TABLE stripe_events (id serial primary key, event_id text, status text, processed_at timestamp, saldi_order_id integer, invoice_number text)");
        $this->tables[] = 'stripe_events';
        StripeTestEnvironment::query("INSERT INTO adresser SELECT * FROM public.adresser WHERE art='S'");
        StripeFixtures::insert('grupper', ['art' => 'RA', 'kodenr' => 5, 'box1' => '1', 'box2' => '2000', 'box3' => '12', 'box4' => '2099']);
        StripeFixtures::insert('grupper', ['art' => 'DIV', 'kodenr' => 3, 'box10' => '58050', 'fiscal_year' => 5]);
        StripeTestEnvironment::seedSettings();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->tables) {
                if (pg_transaction_status($GLOBALS['connection']) !== PGSQL_TRANSACTION_IDLE) StripeTestEnvironment::query('ROLLBACK');
                foreach (array_reverse($this->tables) as $table) StripeTestEnvironment::query("DROP TABLE IF EXISTS pg_temp.$table CASCADE");
                foreach ($this->sequences as $sequence) StripeTestEnvironment::query("DROP SEQUENCE IF EXISTS pg_temp.$sequence");
                foreach ($this->pdfs as $pdf) unlink($pdf);
                stripe_settings_flush();
                unset($GLOBALS['stripe_charge_today']);
                $GLOBALS['db_transaktion_depth'] = 0;
                $GLOBALS['db_modify_fejl'] = false;
                date_default_timezone_set($this->timezone);
            }
        } finally {
            parent::tearDown();
        }
    }

    /** @return int Invoice issued on 22 October, due 30 October, enrolled before issue. */
    private function invoice(array $debtor = [], array $order = []): int
    {
        $konto = StripeFixtures::debtor($debtor + ['stripe' => 'on', 'stripe_customer_id' => 'cus_s3', 'stripe_pm_id' => 'pm_primary', 'stripe_date' => '2026-10-22']);
        $id = StripeFixtures::invoice($konto, $order + ['udskriv_til' => 'Stripe', 'nextfakt' => '2026-11-22']);
        StripeTestEnvironment::query("UPDATE ordrer SET fakturadate='2026-10-22' WHERE id=$1", [$id]);
        $o = StripeFixtures::row('ordrer', $id);
        StripeTestEnvironment::query("UPDATE openpost SET transdate='2026-10-22', forfaldsdate='2026-10-30' WHERE konto_id=$1 AND faktnr=$2", [$konto, $o['fakturanr']]);
        return $id;
    }

    /** @return array Realistic Stripe intent including card details, avoiding unrelated PM lookup requests. */
    private function intent(int $order, string $id, string $status = 'processing', string $error = '', string $source = 'auto'): array
    {
        $inv = stripe_invoice_load($order);
        $pi = ['id' => $id, 'status' => $status, 'customer' => 'cus_s3', 'payment_method' => 'pm_primary',
            'amount' => 34000, 'amount_received' => $status === 'succeeded' ? 34000 : 0, 'currency' => 'dkk',
            'client_secret' => TestCredentials::password('stripe_s3_client'),
            'metadata' => ['saldi_db' => $GLOBALS['db'], 'saldi_ordre_id' => $order, 'saldi_fakturanr' => $inv['nr'], 'saldi_source' => $source],
            'latest_charge' => ['payment_method_details' => ['card' => ['brand' => 'visa', 'last4' => '4242']]]];
        if ($error !== '') $pi['last_payment_error'] = ['code' => $error, 'type' => $error === 'api_error' ? 'api_error' : 'card_error',
            'payment_method' => ['id' => 'pm_primary', 'card' => ['brand' => 'visa', 'last4' => '4242']]];
        return $pi;
    }

    /** @return void Synchronous Stripe 402 containing its original intent. */
    private function decline(array $pi): void
    {
        StripeTestTransport::queue(['ok' => false, 'status' => 402, 'body' => ['error' => ['type' => 'card_error',
            'code' => $pi['last_payment_error']['code'], 'payment_intent' => $pi]], 'error' => 'card error',
            'error_code' => $pi['last_payment_error']['code']], 'POST', '/v1/payment_intents');
    }

    /** @return array Local payment rows, ordered by insertion. */
    private function payments(int $order): array
    {
        return pg_fetch_all(StripeTestEnvironment::query('SELECT * FROM stripe_payments WHERE ordre_id=$1 ORDER BY id', [$order])) ?: [];
    }

    /** @return array Captured customer mails only. */
    private function customerMails(): array
    {
        return array_values(array_filter(StripeTestTransport::$mails, static fn($m) => !isset($m['kind'])));
    }

    /** @return array Captured alert mails only. */
    private function alerts(): array
    {
        return array_values(array_filter(StripeTestTransport::$mails, static fn($m) => ($m['kind'] ?? '') === 'alert'));
    }

    /** @return void Queue retirement of a declined original intent. */
    private function retire(array $pi): void
    {
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/' . $pi['id']);
        $pi['status'] = 'canceled';
        unset($pi['last_payment_error']);
        StripeTestTransport::queueBody($pi, 'POST', '/v1/payment_intents/' . $pi['id'] . '/cancel');
    }

    public function testProcessLockExcludesSecondHolderAndCanBeReacquired(): void
    {
        $ledger = 's3_lock_' . bin2hex(random_bytes(6));
        $first = stripe_charge_job_lock($ledger);
        try {
            $this->assertIsResource($first);
            $this->assertFalse(stripe_charge_job_lock($ledger));
        } finally {
            fclose($first);
        }
        $second = stripe_charge_job_lock($ledger);
        $this->assertIsResource($second);
        fclose($second);
    }

    public function testDueDateUsesStoredValueOrCopenhagenCalendarDaysAcrossDst(): void
    {
        date_default_timezone_set('Pacific/Honolulu');
        $this->assertSame('2026-10-30', stripe_invoice_due(['fakturadate' => '2026-10-22', 'betalingsdage' => 8, 'betalingsbet' => 'Netto']));
        $this->assertSame('2026-11-02', stripe_invoice_due(['fakturadate' => '2026-10-22', 'betalingsdage' => 8, 'forfaldsdate' => '2026-11-02']));
        $this->assertSame('2026-03-31', stripe_date_add('2026-03-28', 3));
        $this->assertSame('2026-10-27', stripe_date_add('2026-10-20', 7));
        $order = $this->invoice();
        StripeTestEnvironment::query("UPDATE openpost SET forfaldsdate='2026-11-02' WHERE konto_id=$1", [StripeFixtures::row('ordrer', $order)['konto_id']]);
        $this->assertSame('2026-11-02', stripe_invoice_load($order)['due']);
        $this->assertSame(0, stripe_charge_run('2026-10-30', true)['due']);
        $this->assertSame(1, stripe_charge_run('2026-11-02', true)['due']);
    }

    public function testOffSessionIsCardOnlyAndPaidIsCountedFromCapturedState(): void
    {
        $order = $this->invoice();
        $pi = $this->intent($order, 'pi_paid', 'succeeded');
        StripeTestTransport::queueBody($pi, 'POST', '/v1/payment_intents');
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_paid');
        $this->assertSame(0, stripe_charge_run('2026-10-29')['charged']);
        $this->assertSame([], StripeTestTransport::$requests);
        $stats = stripe_charge_run('2026-10-30');
        $this->assertSame(1, $stats['succeeded']);
        $this->assertSame(0, $stats['errors']);
        $params = StripeTestTransport::$requests[0]['params'];
        $this->assertSame('true', $params['off_session']);
        $this->assertSame('true', $params['confirm']);
        $this->assertSame(['card'], $params['payment_method_types']);
        $this->assertSame('on', StripeFixtures::row('ordrer', $order)['betalt']);
        $this->assertSame(0, stripe_charge_run('2026-10-30')['charged']);
        $this->assertCount(2, StripeTestTransport::$requests);
    }

    public function testDeclineBackupAndThreePrimaryAttemptsUseDayZeroPlusThreePlusSeven(): void
    {
        $order = $this->invoice();
        $first = $this->intent($order, 'pi_first', 'requires_payment_method', 'card_declined');
        $backup = $this->intent($order, 'pi_backup', 'requires_payment_method', 'card_declined', 'backup');
        $backup['payment_method'] = 'pm_backup';
        $backup['last_payment_error']['payment_method']['id'] = 'pm_backup';
        $this->decline($first);
        StripeTestTransport::queueBody(['data' => [['id' => 'pm_backup', 'metadata' => ['saldi_role' => 'backup']]]], 'GET', '/v1/customers/cus_s3/payment_methods');
        $this->retire($first);
        $this->decline($backup);
        $stats = stripe_charge_run('2026-10-30');
        $this->assertSame(1, $stats['declined']);
        $this->assertSame(0, $stats['errors']);
        $this->assertSame([1, 1], array_map(static fn($p) => (int)$p['attempt'], $this->payments($order)));
        $this->assertCount(1, $this->customerMails());
        $this->assertStringContainsString('2. november 2026', $this->customerMails()[0]['text']);
        $this->assertStringContainsString('på dit gemte kort og på dit reservekort', $this->customerMails()[0]['text']);
        $this->assertStringNotContainsString('rykker', $this->customerMails()[0]['text']);
        $this->assertCount(0, $this->alerts());
        // Retire the backup; it must not move the primary retry date.
        $this->retire($backup);
        $this->assertSame(0, stripe_charge_run('2026-11-01')['charged']);
        $second = $this->intent($order, 'pi_second', 'requires_payment_method', 'card_declined');
        $this->decline($second);
        $this->assertSame(1, stripe_charge_run('2026-11-02')['charged']);
        $this->assertCount(0, $this->alerts());
        $this->assertStringContainsString('6. november 2026', $this->customerMails()[1]['text']);
        $this->retire($second);
        $this->assertSame(0, stripe_charge_run('2026-11-05')['charged']);
        $third = $this->intent($order, 'pi_third', 'requires_payment_method', 'card_declined');
        $this->decline($third);
        $this->assertSame(1, stripe_charge_run('2026-11-06')['charged']);
        $this->assertSame([1, 1, 2, 3], array_map(static fn($p) => (int)$p['attempt'], $this->payments($order)));
        $this->assertCount(1, $this->alerts());
        $this->assertStringContainsString(stripe_txt('mail_note_failed_last'), $this->customerMails()[2]['text']);
        foreach ($this->customerMails() as $mail) {
            $this->assertStringNotContainsString('prøver vi igen ikke igen', $mail['text']);
            $this->assertStringNotContainsString('card_declined', $mail['text']);
        }
        $this->retire($third);
        $this->assertSame(0, stripe_charge_run('2026-11-20')['charged']);
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testNetworkFailureReusesPersistedIdentityAndBodyOnNextRun(): void
    {
        $order = $this->invoice();
        StripeTestTransport::queue(['ok' => false, 'status' => 0, 'body' => null, 'error' => 'network'], 'POST', '/v1/payment_intents');
        $this->assertSame(1, stripe_charge_run('2026-10-30')['errors']);
        $this->assertSame([], $this->payments($order));
        $this->assertSame([], $this->customerMails());
        $request = StripeTestTransport::$requests[0];
        $reservation = pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM stripe_collection_attempts WHERE ordre_id=$1', [$order]));
        $this->assertSame('technical', $reservation['status']);
        StripeTestTransport::queueBody(['data' => [], 'has_more' => false], 'GET', '/v1/payment_intents');
        StripeTestTransport::queueBody($this->intent($order, 'pi_recovered'), 'POST', '/v1/payment_intents');
        $stats = stripe_charge_run('2026-10-31');
        $this->assertSame(0, $stats['errors']);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame($request['idempotency_key'], StripeTestTransport::$requests[2]['idempotency_key']);
        $this->assertSame($request['params'], StripeTestTransport::$requests[2]['params']);
        $this->assertSame(1, (int)$this->payments($order)[0]['attempt']);
    }

    public function testTechnicalAutoRequestAfterAfmeldCannotReplayItsOffSessionBodyForThePage(): void
    {
        $order = $this->invoice();
        StripeTestTransport::queue(['ok' => false, 'status' => 503, 'body' => null, 'error' => 'lost response'], 'POST', '/v1/payment_intents');
        $this->assertSame(1, stripe_charge_run('2026-10-30')['errors']);
        $first = StripeTestTransport::$requests[0];
        $this->assertSame('true', $first['params']['off_session']);
        $this->assertTrue(stripe_unenrol_debtor((int)StripeFixtures::row('ordrer', $order)['konto_id']));
        $this->assertFalse(stripe_invoice_load($order)['enrolled']);
        StripeTestTransport::queueBody(['data' => [], 'has_more' => false], 'GET', '/v1/payment_intents');
        $this->assertSame('in_progress', stripe_payment_create(stripe_invoice_load($order))['error']);
        $this->assertCount(2, StripeTestTransport::$requests);
        $this->assertSame('GET', StripeTestTransport::$requests[1]['method']);

        $old = $this->intent($order, 'pi_auto_lost', 'requires_confirmation');
        $old['metadata'] = $first['params']['metadata'];
        StripeTestTransport::queueBody(['data' => [$old], 'has_more' => false], 'GET', '/v1/payment_intents');
        $canceled = $old;
        $canceled['status'] = 'canceled';
        StripeTestTransport::queueBody($canceled, 'POST', '/v1/payment_intents/pi_auto_lost/cancel');
        StripeTestTransport::queueBody($this->intent($order, 'pi_after_afmeld', 'requires_payment_method'), 'POST', '/v1/payment_intents');
        $this->assertTrue(stripe_payment_create(stripe_invoice_load($order))['ok']);
        $page = StripeTestTransport::$requests[4];
        $this->assertArrayNotHasKey('off_session', $page['params']);
        $this->assertArrayNotHasKey('confirm', $page['params']);
        $this->assertArrayNotHasKey('payment_method', $page['params']);
        $this->assertNotSame($first['idempotency_key'], $page['idempotency_key']);
        $this->assertSame('canceled', $this->payments($order)[0]['status']);
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testChangedCallerReconcilesALostCaptureWithoutAnotherCreate(): void
    {
        $order = $this->invoice();
        StripeTestTransport::queue(['ok' => false, 'status' => 503, 'body' => null, 'error' => 'lost response'], 'POST', '/v1/payment_intents');
        $this->assertSame(1, stripe_charge_run('2026-10-30')['errors']);
        $this->assertTrue(stripe_unenrol_debtor((int)StripeFixtures::row('ordrer', $order)['konto_id']));
        $pi = $this->intent($order, 'pi_already_captured', 'succeeded');
        $pi['metadata'] = StripeTestTransport::$requests[0]['params']['metadata'];
        StripeTestTransport::queueBody(['data' => [$pi], 'has_more' => false], 'GET', '/v1/payment_intents');
        $this->assertSame('already_paid', stripe_payment_create(stripe_invoice_load($order))['error']);
        $this->assertCount(2, StripeTestTransport::$requests);
        $this->assertCount(1, $this->payments($order));
        $payment = $this->payments($order)[0];
        $this->assertSame('succeeded', $payment['status']);
        $this->assertGreaterThan(0, (int)$payment['journal_line_id']);
        $this->assertNotEmpty($payment['confirmation_sent_at']);
        $this->assertFalse(stripe_invoice_load($order)['enrolled']);
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testUnresolvedRequestCannotChargeAnOldAmountAfterInvoiceChanges(): void
    {
        $order = $this->invoice();
        StripeTestTransport::queue(['ok' => false, 'status' => 0, 'body' => null, 'error' => 'network'], 'POST', '/v1/payment_intents');
        stripe_charge_run('2026-10-30');
        StripeTestEnvironment::query('UPDATE ordrer SET sum=400, moms=100 WHERE id=$1', [$order]);
        StripeTestEnvironment::query('UPDATE openpost SET amount=500 WHERE konto_id=$1', [StripeFixtures::row('ordrer', $order)['konto_id']]);
        StripeTestTransport::queueBody(['data' => [], 'has_more' => false], 'GET', '/v1/payment_intents');
        $this->assertSame(1, stripe_charge_run('2026-10-31')['errors']);
        $this->assertCount(2, StripeTestTransport::$requests);
        $this->assertSame('GET', StripeTestTransport::$requests[1]['method']);
        $this->assertSame([], $this->payments($order));
    }

    public function testTechnicalErrorDoesNotStopTheNextInvoice(): void
    {
        $first = $this->invoice();
        $second = $this->invoice();
        StripeTestTransport::queue(['ok' => false, 'status' => 503, 'body' => null, 'error' => 'network'], 'POST', '/v1/payment_intents');
        StripeTestTransport::queueBody($this->intent($second, 'pi_next_invoice'), 'POST', '/v1/payment_intents');
        $stats = stripe_charge_run('2026-10-30');
        $this->assertSame(1, $stats['errors']);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(2, $stats['charged']);
        $this->assertSame([], $this->payments($first));
        $this->assertCount(1, $this->payments($second));
        $this->assertSame([], StripeTestTransport::$mails);
    }

    public function testLostSuccessfulResponseIsFoundBeforeAnyNewPost(): void
    {
        $order = $this->invoice();
        StripeTestTransport::queue(['ok' => false, 'status' => 503, 'body' => null, 'error' => 'lost response'], 'POST', '/v1/payment_intents');
        $this->assertSame(1, stripe_charge_run('2026-10-30')['errors']);
        $request = StripeTestTransport::$requests[0];
        $pi = $this->intent($order, 'pi_lost', 'succeeded');
        $pi['metadata'] = $request['params']['metadata'];
        StripeTestTransport::queueBody(['data' => [$pi], 'has_more' => false], 'GET', '/v1/payment_intents');
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_lost');
        $this->assertSame(1, stripe_charge_run('2026-11-01')['succeeded']);
        $posts = array_filter(StripeTestTransport::$requests, static fn($r) => $r['method'] === 'POST');
        $this->assertCount(1, $posts);
        $this->assertCount(1, $this->payments($order));
    }

    public function testAmbiguousCreateOlderThanKeyRetentionNeverPostsAndAlertsAfterTwoDays(): void
    {
        $order = $this->invoice();
        StripeTestTransport::queue(['ok' => false, 'status' => 0, 'body' => null, 'error' => 'network'], 'POST', '/v1/payment_intents');
        stripe_charge_run('2026-10-30');
        StripeTestEnvironment::query("UPDATE stripe_collection_attempts SET request_started_at=CURRENT_TIMESTAMP-interval '2 days' WHERE ordre_id=$1", [$order]);
        StripeTestTransport::queueBody(['data' => [], 'has_more' => false], 'GET', '/v1/payment_intents');
        $this->assertSame(1, stripe_charge_run('2026-11-01')['errors']);
        $this->assertCount(1, $this->alerts());
        $this->assertSame('GET', StripeTestTransport::$requests[1]['method']);
        $this->assertCount(2, StripeTestTransport::$requests);
        $this->assertSame([], $this->customerMails());
    }

    public function testRateLimitRetriesItsKeyButRejectedRequestAllocatesANewIdentity(): void
    {
        foreach ([429, 400] as $http) {
            $order = $this->invoice();
            StripeTestTransport::queue(['ok' => false, 'status' => $http, 'body' => ['error' => ['type' => 'invalid_request_error']], 'error' => 'configuration'], 'POST', '/v1/payment_intents');
            $before = count(StripeTestTransport::$requests);
            $this->assertSame(1, stripe_charge_run('2026-10-30')['errors']);
            $this->assertSame([], $this->payments($order));
            $first = StripeTestTransport::$requests[$before];
            StripeTestEnvironment::query("UPDATE stripe_collection_attempts SET request_started_at=CURRENT_TIMESTAMP-interval '2 days' WHERE ordre_id=$1", [$order]);
            StripeTestTransport::queueBody($this->intent($order, 'pi_http_' . $http), 'POST', '/v1/payment_intents');
            $this->assertSame(1, stripe_charge_run('2026-11-01')['pending']);
            $next = StripeTestTransport::$requests[$before + 1];
            if ($http === 429) {
                $this->assertSame($first['idempotency_key'], $next['idempotency_key']);
            } else {
                $this->assertNotSame($first['idempotency_key'], $next['idempotency_key']);
                $this->assertSame(2, $next['params']['metadata']['saldi_attempt_identity']);
            }
            // Disable this debtor before the next fixture so it cannot consume its responses.
            StripeTestEnvironment::query("UPDATE adresser SET stripe='' WHERE id=$1", [StripeFixtures::row('ordrer', $order)['konto_id']]);
        }
        $this->assertCount(4, StripeTestTransport::$requests);
        $this->assertSame([], StripeTestTransport::$mails);
    }

    public function testKnownTechnicalIntentIsRetriedByConfirmationWithoutNewIntentOrDecline(): void
    {
        $order = $this->invoice();
        $pi = $this->intent($order, 'pi_technical', 'requires_payment_method', 'api_error');
        $id = stripe_payment_record(stripe_invoice_load($order), $pi, 'auto', false, 1);
        StripeTestEnvironment::query("UPDATE stripe_payments SET status='technical_error', last_error='api_error' WHERE id=$1", [$id]);
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_technical');
        $recovered = $this->intent($order, 'pi_technical');
        StripeTestTransport::queueBody($recovered, 'POST', '/v1/payment_intents/pi_technical/confirm');
        $stats = stripe_charge_run('2026-10-31');
        $this->assertSame(0, $stats['errors']);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(0, $stats['declined']);
        $this->assertSame(0, $stats['charged']);
        $this->assertSame('processing', $this->payments($order)[0]['status']);
        $this->assertSame([], stripe_primary_declines(stripe_invoice_load($order)));
        $this->assertCount(1, $this->payments($order));
        $this->assertSame('true', StripeTestTransport::$requests[1]['params']['off_session']);
        $this->assertSame([], StripeTestTransport::$mails);
    }

    public function testReservationExistsBeforeStripeSeesTheRequest(): void
    {
        $order = $this->invoice();
        StripeTestTransport::$beforeResponse = function () use ($order): void {
            $row = pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM stripe_collection_attempts WHERE ordre_id=$1', [$order]));
            $this->assertSame('requesting', $row['status']);
            $this->assertNotEmpty($row['request_started_at']);
            $this->assertSame(StripeTestTransport::$requests[0]['params'], json_decode($row['params'], true));
            $this->assertSame([], $this->payments($order));
        };
        StripeTestTransport::queueBody($this->intent($order, 'pi_reserved'), 'POST', '/v1/payment_intents');
        $this->assertSame(1, stripe_charge_run('2026-10-30')['pending']);
        StripeTestTransport::$beforeResponse = null;
    }

    public function testAuthenticationRequiredSendsActionMailStopsRetriesAndResumesOriginalIntent(): void
    {
        $order = $this->invoice();
        $pi = $this->intent($order, 'pi_action', 'requires_payment_method', 'authentication_required');
        $this->decline($pi);
        $stats = stripe_charge_run('2026-10-30');
        $this->assertSame(1, $stats['requires_action']);
        $this->assertSame(0, $stats['declined']);
        $this->assertSame('requires_action', $this->payments($order)[0]['status']);
        $this->assertSame('requires_action', stripe_invoice_load($order)['state']);
        $this->assertCount(1, $this->customerMails());
        $this->assertStringContainsString(stripe_invoice_url($order), $this->customerMails()[0]['text']);
        $this->assertStringNotContainsString('authentication_required', $this->customerMails()[0]['text']);
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_action');
        $this->assertSame(0, stripe_charge_run('2026-11-02')['charged']);
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_action');
        $this->assertSame(0, stripe_charge_run('2026-11-06')['charged']);
        $this->assertSame([], $this->alerts());
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_action');
        stripe_charge_run('2026-11-07');
        $this->assertCount(1, $this->alerts());
        $this->assertCount(1, $this->customerMails());
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_action');
        $pi['status'] = 'requires_action';
        unset($pi['last_payment_error']);
        StripeTestTransport::queueBody($pi, 'POST', '/v1/payment_intents/pi_action/confirm');
        $resumed = stripe_payment_resume($order);
        $this->assertTrue($resumed['ok']);
        $this->assertSame('pi_action', $resumed['pi']);
        $this->assertSame($pi['client_secret'], $resumed['client_secret']);
        $creates = array_filter(StripeTestTransport::$requests, static fn($r) => $r['path'] === '/v1/payment_intents' && $r['method'] === 'POST');
        $this->assertCount(1, $creates);
        $this->assertCount(1, $this->payments($order));
    }

    public function testDirectRequiresActionResponseIsNeverClassifiedAsSuccessOrDecline(): void
    {
        $order = $this->invoice();
        StripeTestTransport::queueBody($this->intent($order, 'pi_direct_action', 'requires_action'), 'POST', '/v1/payment_intents');
        $stats = stripe_charge_run('2026-10-30');
        $this->assertSame(1, $stats['requires_action']);
        $this->assertSame(0, $stats['succeeded']);
        $this->assertSame(0, $stats['declined']);
        $this->assertCount(1, $this->customerMails());
    }

    public function testAsyncPrimaryFailureTriggersBackupAndFailureMailExactlyOnce(): void
    {
        $order = $this->invoice();
        $pi = $this->intent($order, 'pi_async');
        StripeTestTransport::queueBody($pi, 'POST', '/v1/payment_intents');
        $stats = stripe_charge_run('2026-10-30');
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(0, $stats['succeeded']);
        $GLOBALS['stripe_charge_today'] = '2026-10-30';
        $pi = $this->intent($order, 'pi_async', 'requires_payment_method', 'card_declined');
        StripeTestTransport::queueBody(['data' => [['id' => 'pm_backup', 'metadata' => ['saldi_role' => 'backup']]]], 'GET', '/v1/customers/cus_s3/payment_methods');
        $this->retire($pi);
        $backup = $this->intent($order, 'pi_async_backup', 'requires_payment_method', 'card_declined', 'backup');
        $this->decline($backup);
        $event = StripeFixtures::insert('stripe_events', ['event_id' => 'evt_async', 'status' => 'retrying']);
        $outcome = stripe_invoice_webhook_dispatch($event, 'payment_intent.payment_failed', $pi, 'evt_async');
        $this->assertSame(200, $outcome['code']);
        $this->assertSame([1, 1], array_map(static fn($p) => (int)$p['attempt'], $this->payments($order)));
        $this->assertCount(1, $this->customerMails());
        $this->assertSame('ignored', stripe_webhook_payment_intent('payment_intent.payment_failed', $pi, 'evt_async_repeat'));
        $this->assertSame('duplicate', stripe_webhook_payment_intent('payment_intent.payment_failed', $backup, 'evt_backup_repeat'));
        $this->assertCount(1, $this->customerMails());
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testAsyncDeclinePaidWithBackupSendsReceiptWithoutFailureMail(): void
    {
        $order = $this->invoice();
        $GLOBALS['stripe_charge_today'] = '2026-10-30';
        $pi = $this->intent($order, 'pi_async_paid');
        stripe_payment_record(stripe_invoice_load($order), $pi, 'auto', false, 1);
        $pi['status'] = 'requires_payment_method';
        $pi['last_payment_error'] = ['code' => 'card_declined', 'type' => 'card_error',
            'payment_method' => ['id' => 'pm_primary', 'card' => ['brand' => 'visa', 'last4' => '4242']]];
        StripeTestTransport::queueBody(['data' => [['id' => 'pm_backup', 'metadata' => ['saldi_role' => 'backup']]]], 'GET', '/v1/customers/cus_s3/payment_methods');
        $this->retire($pi);
        $backup = $this->intent($order, 'pi_backup_paid', 'succeeded', '', 'backup');
        StripeTestTransport::queueBody($backup, 'POST', '/v1/payment_intents');
        StripeTestTransport::queueBody($backup, 'GET', '/v1/payment_intents/pi_backup_paid');
        $this->assertSame('applied', stripe_webhook_payment_intent('payment_intent.payment_failed', $pi, 'evt_async_paid'));
        $this->assertSame('on', StripeFixtures::row('ordrer', $order)['betalt']);
        $this->assertCount(1, $this->customerMails());
        $this->assertStringContainsString('Tak for din betaling', $this->customerMails()[0]['subject']);
        $this->assertCount(0, $this->alerts());
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testAuthenticationMailFailureRetriesWithoutAnotherIntent(): void
    {
        $order = $this->invoice();
        $pi = $this->intent($order, 'pi_action_mail', 'requires_action');
        StripeTestTransport::$mailSucceeds = false;
        StripeTestTransport::queueBody($pi, 'POST', '/v1/payment_intents');
        $this->assertSame(1, stripe_charge_run('2026-10-30')['errors']);
        $this->assertNull($this->payments($order)[0]['action_mail_sent_at']);
        StripeTestTransport::$mailSucceeds = true;
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_action_mail');
        $stats = stripe_charge_run('2026-10-31');
        $this->assertSame(0, $stats['errors']);
        $this->assertSame(1, $stats['requires_action']);
        $this->assertNotEmpty($this->payments($order)[0]['action_mail_sent_at']);
        $this->assertCount(2, $this->customerMails());
        $this->assertCount(1, $this->payments($order));
        $this->assertCount(2, StripeTestTransport::$requests);
    }

    public function testProcessingAndLookupFailureAreReconciledBeforeAnyAutomaticCharge(): void
    {
        $order = $this->invoice();
        $pi = $this->intent($order, 'pi_old_processing');
        $id = stripe_payment_record(stripe_invoice_load($order), $pi, 'auto', false, 1);
        StripeTestEnvironment::query("UPDATE stripe_payments SET created_at=CURRENT_TIMESTAMP-interval '5 days' WHERE id=$1", [$id]);
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_old_processing');
        $this->assertSame(0, stripe_charge_run('2026-10-30')['charged']);
        StripeTestTransport::queue(['ok' => false, 'status' => 503, 'body' => null, 'error' => 'lookup unavailable'], 'GET', '/v1/payment_intents/pi_old_processing');
        $this->assertSame(1, stripe_charge_run('2026-11-01')['errors']);
        $this->assertCount(2, StripeTestTransport::$requests);
        $this->assertSame('processing', $this->payments($order)[0]['status']);
    }

    public function testCanceledAutoAttemptGetsFreshIdentityWithoutConsumingPrimarySlot(): void
    {
        $order = $this->invoice();
        StripeTestTransport::queueBody($this->intent($order, 'pi_canceled_first'), 'POST', '/v1/payment_intents');
        stripe_charge_run('2026-10-30');
        $firstKey = StripeTestTransport::$requests[0]['idempotency_key'];
        StripeTestTransport::queueBody($this->intent($order, 'pi_canceled_first', 'canceled'), 'GET', '/v1/payment_intents/pi_canceled_first');
        StripeTestTransport::queueBody($this->intent($order, 'pi_after_cancel'), 'POST', '/v1/payment_intents');
        $this->assertSame(1, stripe_charge_run('2026-10-31')['charged']);
        $this->assertNotSame($firstKey, StripeTestTransport::$requests[2]['idempotency_key']);
        $this->assertSame([1, 1], array_map(static fn($p) => (int)$p['attempt'], $this->payments($order)));
    }

    public function testChargeSelectionExcludesInvoicesBeforeEnrolment(): void
    {
        $old = $this->invoice(['stripe_date' => '2026-10-23']);
        $current = $this->invoice();
        $this->assertSame(1, stripe_charge_run('2026-10-30', true)['due']);
        $this->assertFalse(stripe_auto_charge_allowed(stripe_invoice_load($old)));
        $this->assertTrue(stripe_auto_charge_allowed(stripe_invoice_load($current)));
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testEnrolmentUsesPiCustomerAndChangesOnlyCurrentTemplate(): void
    {
        $order = $this->invoice(['stripe' => '', 'stripe_pm_id' => null, 'stripe_customer_id' => 'cus_wrong'], ['udskriv_til' => 'email']);
        $inv = stripe_invoice_load($order);
        $old = StripeFixtures::order((int)$inv['o']['konto_id'], ['ordrenr' => $inv['o']['ordrenr'], 'nextfakt' => '2026-09-22']);
        // A historical issued row can retain a future nextfakt; only the newest source is a template.
        StripeTestEnvironment::query("UPDATE ordrer SET nextfakt='2026-11-22', fakturanr='999999', status=4, fakturadate='2026-09-22' WHERE id=$1", [$old]);
        $template = StripeFixtures::order((int)$inv['o']['konto_id'], ['ordrenr' => $inv['o']['ordrenr'], 'nextfakt' => '2026-11-22']);
        StripeTestTransport::queueBody(['id' => 'cus_pi'], 'POST', '/v1/customers/cus_pi');
        StripeTestTransport::queueBody(['id' => 'pm_new'], 'POST', '/v1/payment_methods/pm_new');
        $this->assertTrue(stripe_enrol_debtor($inv, 'pm_new', 'visa', '4242', 'cus_pi'));
        $debtor = StripeFixtures::row('adresser', (int)$inv['o']['konto_id']);
        $this->assertSame('cus_pi', $debtor['stripe_customer_id']);
        $this->assertSame('pm_new', $debtor['stripe_pm_id']);
        $this->assertSame('Link', StripeFixtures::row('ordrer', $old)['udskriv_til']); // fixture default since 20261025
        $this->assertSame('email', StripeFixtures::row('ordrer', $order)['udskriv_til']);
        $this->assertSame('Stripe', StripeFixtures::row('ordrer', $template)['udskriv_til']);
        $this->assertSame('2026-11-22', StripeFixtures::row('ordrer', $template)['nextfakt']);
        $this->assertSame('pm_new', StripeTestTransport::$requests[0]['params']['invoice_settings']['default_payment_method']);
    }

    public function testEnrolmentDefaultFailureDoesNotPersistCardOrSwitchDelivery(): void
    {
        $order = $this->invoice(['stripe' => '', 'stripe_pm_id' => null], ['udskriv_til' => 'email']);
        $inv = stripe_invoice_load($order);
        StripeTestTransport::queue(['ok' => false, 'status' => 400, 'body' => null, 'error' => 'wrong customer'], 'POST', '/v1/customers/cus_pi');
        $this->assertFalse(stripe_enrol_debtor($inv, 'pm_new', 'visa', '4242', 'cus_pi'));
        $this->assertSame('', StripeFixtures::row('adresser', (int)$inv['o']['konto_id'])['stripe']);
        $this->assertSame('email', StripeFixtures::row('ordrer', $order)['udskriv_til']);
    }

    public function testCustomerCreationHasDebtorScopedStableKey(): void
    {
        $order = $this->invoice(['stripe' => '', 'stripe_pm_id' => null, 'stripe_customer_id' => null]);
        $inv = stripe_invoice_load($order);
        StripeTestTransport::queueBody(['id' => 'cus_created'], 'POST', '/v1/customers');
        $this->assertSame('cus_created', stripe_customer_for_debtor($inv));
        $this->assertSame('cus:' . $GLOBALS['db'] . ':' . $inv['o']['konto_id'], StripeTestTransport::$requests[0]['idempotency_key']);
        $this->assertSame('cus_created', stripe_customer_for_debtor(stripe_invoice_load($order)));
        $this->assertCount(1, StripeTestTransport::$requests);
    }

    public function testReplaceCardUsageMatchesConsentAndSuccessfulPaymentUpdatesDefault(): void
    {
        $order = $this->invoice();
        $pi = $this->intent($order, 'pi_replace', 'requires_payment_method', '', 'page');
        StripeTestTransport::queueBody($pi, 'POST', '/v1/payment_intents');
        $GLOBALS['stripe_charge_today'] = '2026-10-30';
        $res = stripe_payment_create(stripe_invoice_load($order), 'page', true);
        $this->assertTrue($res['ok']);
        $this->assertSame('off_session', $res['setup_future_usage']);
        $this->assertSame('off_session', StripeTestTransport::$requests[0]['params']['setup_future_usage']);
        $pi['status'] = 'succeeded';
        $pi['amount_received'] = 34000;
        $pi['payment_method'] = 'pm_replacement';
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_replace');
        StripeTestTransport::queueBody(['id' => 'cus_s3'], 'POST', '/v1/customers/cus_s3');
        StripeTestTransport::queueBody(['id' => 'pm_replacement'], 'POST', '/v1/payment_methods/pm_replacement');
        $this->assertSame('applied', stripe_payment_sync('pi_replace'));
        $debtor = StripeFixtures::row('adresser', (int)stripe_invoice_load($order)['o']['konto_id']);
        $this->assertSame('pm_replacement', $debtor['stripe_pm_id']);
        $this->assertSame('2026-10-22', $debtor['stripe_date']);
        $this->assertSame('pm_replacement', StripeTestTransport::$requests[2]['params']['invoice_settings']['default_payment_method']);
    }

    public function testUncheckedReplacementHasNoFutureUsageAndAOneOffInvoiceCanSaveTheCard(): void
    {
        // 20261027 a one-off invoice offers automatic payment too, so saving the card there is honoured.
        foreach ([['stripe' => 'on'], ['stripe' => '', 'stripe_pm_id' => null]] as $debtor) {
            $order = $this->invoice($debtor, ['nextfakt' => null]);
            StripeTestTransport::queueBody($this->intent($order, 'pi_usage_' . $order, 'requires_payment_method', '', 'page'), 'POST', '/v1/payment_intents');
            $save = $debtor['stripe'] === '';
            $res = stripe_payment_create(stripe_invoice_load($order), 'page', $save);
            $this->assertTrue($res['ok']);
            $params = StripeTestTransport::$requests[count(StripeTestTransport::$requests) - 1]['params'];
            if ($save) {
                $this->assertSame('off_session', $res['setup_future_usage']);
                $this->assertSame('off_session', $params['setup_future_usage']);
            } else {
                $this->assertNull($res['setup_future_usage']);
                $this->assertArrayNotHasKey('setup_future_usage', $params);
            }
        }
    }

    public function testUnenrolmentResetsAndMailsIssuedUnpaidInvoicesWithoutNextfakt(): void
    {
        $order = $this->invoice([], ['nextfakt' => null]);
        $inv = stripe_invoice_load($order);
        $this->assertTrue(stripe_unenrol_debtor((int)$inv['o']['konto_id']));
        $this->assertSame('Link', StripeFixtures::row('ordrer', $order)['udskriv_til']);
        $this->assertSame('', StripeFixtures::row('adresser', (int)$inv['o']['konto_id'])['stripe']);
        $this->assertCount(1, $this->customerMails());
        $this->assertStringContainsString(stripe_invoice_url($order), $this->customerMails()[0]['text']);
        $this->assertSame(0, stripe_unenrol_mail_retry());
        $this->assertCount(1, $this->customerMails());
        $this->assertSame(0, stripe_charge_run('2026-10-30')['charged']);
    }

    public function testUnenrolmentMailFailureRemainsQueuedForRetry(): void
    {
        $order = $this->invoice();
        $inv = stripe_invoice_load($order);
        StripeTestTransport::$mailSucceeds = false;
        $this->assertFalse(stripe_unenrol_debtor((int)$inv['o']['konto_id']));
        $pending = pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM stripe_unenrol_mail WHERE ordre_id=$1', [$order]));
        $this->assertNull($pending['sent_at']);
        StripeTestTransport::$mailSucceeds = true;
        $this->assertSame(0, stripe_unenrol_mail_retry());
        $sent = pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM stripe_unenrol_mail WHERE ordre_id=$1', [$order]));
        $this->assertNotEmpty($sent['sent_at']);
        $this->assertCount(2, $this->customerMails());
    }

    public function testUnenrolmentFallsBackToClassicAttachmentForNonpayableInvoice(): void
    {
        $order = $this->invoice();
        $inv = stripe_invoice_load($order);
        StripeTestEnvironment::query('UPDATE openpost SET amount=240 WHERE konto_id=$1', [$inv['o']['konto_id']]);
        $pdf = stripe_invoice_pdf_target($order);
        file_put_contents($pdf, '%PDF-1.4' . str_repeat(' fixture ', 20));
        $this->pdfs[] = $pdf;
        $this->assertTrue(stripe_unenrol_debtor((int)$inv['o']['konto_id']));
        $this->assertCount(1, $this->customerMails());
        $this->assertSame([$pdf], $this->customerMails()[0]['attachments']);
        $this->assertSame('Link', StripeFixtures::row('ordrer', $order)['udskriv_til']);
    }

    public function testManualPayNowAuthenticationCompletesOriginalIntentOnceAndLateEventsCannotUndoIt(): void
    {
        $order = $this->invoice();
        $GLOBALS['stripe_charge_today'] = '2026-10-30';
        $pi = $this->intent($order, 'pi_manual_action', 'requires_payment_method', 'authentication_required', 'manual');
        $this->decline($pi);
        $result = stripe_payment_create(stripe_invoice_load($order), 'manual', false, 'pm_primary');
        $this->assertTrue($result['ok']);
        $this->assertSame('requires_action', $result['status']);
        $this->assertSame($pi['client_secret'], $result['client_secret']);
        $this->assertSame('', StripeFixtures::row('ordrer', $order)['betalt']);
        $this->assertCount(1, $this->customerMails());
        $this->assertStringContainsString('godkender', $this->customerMails()[0]['text']);
        $this->assertStringNotContainsString('rykker', $this->customerMails()[0]['text']);
        $this->assertSame([], stripe_primary_declines(stripe_invoice_load($order)));

        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_manual_action');
        $action = $pi;
        $action['status'] = 'requires_action';
        unset($action['last_payment_error']);
        StripeTestTransport::queueBody($action, 'POST', '/v1/payment_intents/pi_manual_action/confirm');
        $this->assertSame('pi_manual_action', stripe_payment_resume($order)['pi']);
        $paid = $this->intent($order, 'pi_manual_action', 'succeeded', '', 'manual');
        StripeTestTransport::queueBody($paid, 'GET', '/v1/payment_intents/pi_manual_action');
        $this->assertSame('applied', stripe_payment_sync('pi_manual_action'));
        $event = StripeFixtures::insert('stripe_events', ['event_id' => 'evt_manual_paid', 'status' => 'received']);
        $this->assertSame(200, stripe_invoice_webhook_dispatch($event, 'payment_intent.succeeded', $paid, 'evt_manual_paid')['code']);
        $this->assertSame('ignored', stripe_webhook_payment_intent('payment_intent.requires_action', $action, 'evt_late_action'));
        $this->assertSame('ignored', stripe_webhook_payment_intent('payment_intent.payment_failed', $pi, 'evt_late_failure'));
        $this->assertSame('paid', stripe_invoice_load($order)['state']);
        $this->assertSame(0, stripe_charge_run('2026-11-06')['charged']);
        $this->assertCount(1, $this->payments($order));
        $this->assertCount(2, $this->customerMails()); // authentication request, then receipt
        $line = pg_fetch_assoc(StripeTestEnvironment::query('SELECT count(*) AS n FROM kassekladde WHERE ordre_id=$1', [$order]));
        $this->assertSame(1, (int)$line['n']);
        $creates = array_filter(StripeTestTransport::$requests, static fn($r) => $r['method'] === 'POST' && $r['path'] === '/v1/payment_intents');
        $this->assertCount(1, $creates);
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testAbandonedPageAuthenticationDoesNotBookAndCanceledAuthenticationCannotAutoRetry(): void
    {
        $order = $this->invoice();
        $pi = $this->intent($order, 'pi_abandoned', 'requires_action', '', 'page');
        stripe_payment_record(stripe_invoice_load($order), $pi, 'page', false, 1);
        $this->assertSame('requires_action', stripe_webhook_payment_intent('payment_intent.requires_action', $pi, 'evt_page_action'));
        $this->assertSame('', StripeFixtures::row('ordrer', $order)['betalt']);
        $this->assertSame([], $this->customerMails());
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_abandoned');
        $this->assertSame('in_progress', stripe_payment_create(stripe_invoice_load($order))['error']);
        $pi['status'] = 'canceled';
        stripe_webhook_payment_intent('payment_intent.canceled', $pi, 'evt_page_abandoned');
        $this->assertFalse(stripe_auto_charge_allowed(stripe_invoice_load($order)));
        $this->assertSame(0, stripe_charge_run('2026-11-20')['charged']);
        StripeTestTransport::queueBody($this->intent($order, 'pi_page_retry', 'requires_payment_method', '', 'page'), 'POST', '/v1/payment_intents');
        $this->assertTrue(stripe_payment_create(stripe_invoice_load($order), 'page', true)['ok']);
        $this->assertCount(2, $this->payments($order));
        $line = pg_fetch_assoc(StripeTestEnvironment::query('SELECT count(*) AS n FROM kassekladde WHERE ordre_id=$1', [$order]));
        $this->assertSame(0, (int)$line['n']);
        $this->assertSame([], StripeTestTransport::$mails);
    }

    public function testReplacementAfterAutomaticDeclineRetiresOldIntentAndUsesEffectiveConsent(): void
    {
        $order = $this->invoice();
        $GLOBALS['stripe_charge_today'] = '2026-10-30';
        $failed = $this->intent($order, 'pi_auto_before_replace', 'requires_payment_method', 'card_declined');
        stripe_payment_record(stripe_invoice_load($order), $failed, 'auto', false, 1);
        $this->assertSame('applied', stripe_payment_apply_failed($failed));
        $this->assertSame('failed', stripe_invoice_load($order)['state']);
        $this->retire($failed);
        $replacement = $this->intent($order, 'pi_actual_replacement', 'requires_payment_method', '', 'page');
        StripeTestTransport::queueBody($replacement, 'POST', '/v1/payment_intents');
        $result = stripe_payment_create(stripe_invoice_load($order), 'page', true);
        $this->assertTrue($result['ok']);
        $this->assertSame('off_session', $result['setup_future_usage']);
        $this->assertSame('off_session', StripeTestTransport::$requests[2]['params']['setup_future_usage']);
        $replacement['status'] = 'succeeded';
        $replacement['amount_received'] = 34000;
        $replacement['payment_method'] = 'pm_actual_replacement';
        StripeTestTransport::queueBody(['id' => 'cus_s3'], 'POST', '/v1/customers/cus_s3');
        StripeTestTransport::queueBody(['id' => 'pm_actual_replacement'], 'POST', '/v1/payment_methods/pm_actual_replacement');
        $this->assertSame('applied', stripe_payment_apply_succeeded($replacement));
        $debtor = StripeFixtures::row('adresser', (int)StripeFixtures::row('ordrer', $order)['konto_id']);
        $this->assertSame('pm_actual_replacement', $debtor['stripe_pm_id']);
        $this->assertSame('2026-10-22', $debtor['stripe_date']);
        $this->assertSame('pm_actual_replacement', StripeTestTransport::$requests[3]['params']['invoice_settings']['default_payment_method']);
        $this->assertCount(1, $this->customerMails());
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testPageAttemptAndDueJobShareOneCollectionAcrossSources(): void
    {
        $order = $this->invoice();
        $pi = $this->intent($order, 'pi_page_wins', 'requires_payment_method', '', 'page');
        StripeTestTransport::queueBody($pi, 'POST', '/v1/payment_intents');
        $this->assertTrue(stripe_payment_create(stripe_invoice_load($order), 'page', true)['ok']);
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_page_wins');
        $stats = stripe_charge_run('2026-10-30');
        $this->assertSame(0, $stats['charged']);
        $this->assertSame(1, $stats['pending']);
        $this->assertCount(1, $this->payments($order));
        $this->assertCount(2, StripeTestTransport::$requests);
        $this->assertSame([], StripeTestTransport::$mails);
    }

    public function testChargeRunnerRechecksAllEligibilityExclusionsIncludingOptOutAndPartialBalance(): void
    {
        foreach (['subscriber', 'pbs_debtor', 'pbs_order', 'currency', 'opt_out', 'partial', 'credit', 'cash'] as $reason) {
            $debtor = $reason === 'pbs_debtor' ? ['pbs_nr' => '123'] : ($reason === 'opt_out' ? ['stripe_fravalg' => 'on'] : []);
            $values = $reason === 'pbs_order' ? ['pbs' => 'BS'] : ($reason === 'currency' ? ['valuta' => 'EUR'] : ($reason === 'credit' ? ['art' => 'DK'] : ($reason === 'cash' ? ['betalingsbet' => 'Kontant'] : [])));
            $order = $this->invoice($debtor, $values);
            $inv = stripe_invoice_load($order);
            if ($reason === 'subscriber') {
                StripeFixtures::insert('stripe_customers', ['konto_id' => $inv['o']['konto_id'], 'stripe_customer_id' => 'cus_pilot', 'stripe_subscription_id' => 'sub_pilot', 'status' => 'active']);
            }
            if ($reason === 'partial') {
                StripeTestEnvironment::query('UPDATE openpost SET amount=240 WHERE konto_id=$1', [$inv['o']['konto_id']]);
            }
        }
        $this->assertSame(0, stripe_charge_run('2026-10-30')['charged']);
        $this->assertSame([], StripeTestTransport::$requests);
        $this->assertSame([], StripeTestTransport::$mails);
    }

    public function testFullJobDryRunDoesNotReconcileCollectOrSendAndTechnicalFailureExitsNonzero(): void
    {
        $order = $this->invoice();
        $before = pg_fetch_assoc(StripeTestEnvironment::query('SELECT count(*) AS n FROM stripe_collection_attempts'));
        $dry = stripe_charge_job('2026-10-30', true);
        $this->assertSame(0, $dry['exit_code']);
        $this->assertSame(1, $dry['stats']['due']);
        $this->assertSame([], StripeTestTransport::$requests);
        $this->assertSame([], StripeTestTransport::$mails);
        $this->assertSame($before, pg_fetch_assoc(StripeTestEnvironment::query('SELECT count(*) AS n FROM stripe_collection_attempts')));
        StripeTestTransport::queue(['ok' => false, 'status' => 503, 'body' => null, 'error' => 'unavailable'], 'POST', '/v1/payment_intents');
        $failed = stripe_charge_job('2026-10-30');
        $this->assertSame(1, $failed['exit_code']);
        $this->assertSame(1, $failed['stats']['errors']);
        $this->assertSame([], $this->payments($order));
        $source = file_get_contents(__DIR__ . '/../../tools/stripe_charge_due.php');
        $this->assertStringContainsString("exit(\$result['exit_code']);", $source);
        $this->assertStringContainsString('stripe_charge_job($today, $dryRun,', $source);
    }

    public function testRepairOnlyJobRetriesOutboxWithSwitchesOffAndDoesNotCollectAnotherDueInvoice(): void
    {
        $order = $this->invoice();
        $other = $this->invoice();
        $konto = (int)StripeFixtures::row('ordrer', $order)['konto_id'];
        StripeTestTransport::$mailSucceeds = false;
        $this->assertFalse(stripe_unenrol_debtor($konto));
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'off');
        StripeTestEnvironment::setting('stripe', 'enrolment', 'off');
        $inv = stripe_invoice_load($order);
        $pdf = stripe_invoice_pdf_target($order);
        file_put_contents($pdf, '%PDF-1.4' . str_repeat(' outbox fixture ', 20));
        $this->pdfs[] = $pdf;
        $failed = stripe_charge_job('2026-10-30', false, true);
        $this->assertSame(1, $failed['exit_code']);
        $this->assertSame(1, $failed['stats']['mail_failed']);
        StripeTestTransport::$mailSucceeds = true;
        $sent = stripe_charge_job('2026-10-30', false, true);
        $this->assertSame(0, $sent['exit_code']);
        $this->assertSame(0, $sent['stats']['mail_failed']);
        $this->assertSame([$pdf], $this->customerMails()[2]['attachments']);
        $this->assertNotEmpty(pg_fetch_assoc(StripeTestEnvironment::query('SELECT sent_at FROM stripe_unenrol_mail WHERE ordre_id=$1', [$order]))['sent_at']);
        $this->assertSame([], $this->payments($other));
        $this->assertSame([], StripeTestTransport::$requests);
        $this->assertSame(0, stripe_charge_job('2026-10-30', false, true)['exit_code']);
        $this->assertCount(3, $this->customerMails());
    }
}
