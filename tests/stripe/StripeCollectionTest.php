<?php
// 20261022 CDX/LH Collection reconciliation, cancellation identity, full-open amounts and shared eligibility regressions.
// 20261022 CDX/LH Cover caller-bound stalled requests, rejected creates, card-only requests and debtor credits.

require_once __DIR__ . '/bootstrap.php';

final class StripeCollectionTest extends StripeDatabaseTestCase
{
    /** @return int Invoice backed by an already known Stripe customer, avoiding unrelated API calls. */
    private function invoice(array $order = [], array $debtor = []): int
    {
        return StripeFixtures::invoice(StripeFixtures::debtor($debtor + ['stripe_customer_id' => 'cus_s2']), $order);
    }

    /** @return array<string, mixed> Deferred intent fixture; its client secret is generated per process. */
    private function intent(string $id, string $status = 'requires_payment_method'): array
    {
        return ['id' => $id, 'customer' => 'cus_s2', 'status' => $status, 'amount' => 34000,
            'currency' => 'dkk', 'client_secret' => TestCredentials::password('stripe_s2_client')];
    }

    /** @return int Persisted unresolved attempt, aged using the database clock. */
    private function attempt(int $order, string $pi, string $status = 'pending', int $number = 1, bool $stale = true): int
    {
        $id = stripe_payment_record(stripe_invoice_load($order), $this->intent($pi), 'page', false, $number);
        StripeTestEnvironment::query("UPDATE stripe_payments SET status=$1, created_at=CURRENT_TIMESTAMP - interval '" . ($stale ? '2 days' : '1 minute') . "' WHERE id=$2", [$status, $id]);
        return $id;
    }

    public function testOldProcessingIntentIsReconciledAndCannotBeReplaced(): void
    {
        $order = $this->invoice();
        $id = $this->attempt($order, 'pi_processing');
        $inv = stripe_invoice_load($order);
        $this->assertNull($inv['pending']);
        StripeTestTransport::queueBody($this->intent('pi_processing', 'processing'), 'GET', '/v1/payment_intents/pi_processing');
        $result = stripe_payment_create($inv);
        $this->assertSame('in_progress', $result['error']);
        $this->assertSame('processing', StripeFixtures::row('stripe_payments', $id)['status']);
        $this->assertSame('pending', stripe_invoice_load($order)['state']);
        $this->assertCount(1, StripeTestTransport::$requests);
    }

    public function testAllNonterminalIntentsAreFetchedIncludingFailedRowsEvenAfterOneBlocks(): void
    {
        $order = $this->invoice();
        $first = $this->attempt($order, 'pi_action');
        $second = $this->attempt($order, 'pi_failed', 'failed', 2);
        $canceled = $this->attempt($order, 'pi_canceled', 'canceled', 3);
        StripeTestTransport::queueBody($this->intent('pi_action', 'requires_action') + ['last_payment_error' => ['code' => 'authentication_required']], 'GET', '/v1/payment_intents/pi_action');
        StripeTestTransport::queueBody($this->intent('pi_failed', 'canceled'), 'GET', '/v1/payment_intents/pi_failed');
        $this->assertSame('in_progress', stripe_payment_create(stripe_invoice_load($order))['error']);
        $this->assertSame('requires_action', StripeFixtures::row('stripe_payments', $first)['status']);
        $this->assertSame('canceled', StripeFixtures::row('stripe_payments', $second)['status']);
        $this->assertSame('canceled', StripeFixtures::row('stripe_payments', $canceled)['status']);
        $this->assertCount(2, StripeTestTransport::$requests);
        $this->assertSame([], StripeTestTransport::$responses);
        $this->assertSame([], StripeTestTransport::$mails);
    }

    public function testLookupFailurePreventsEveryCreateAndStillChecksOtherRows(): void
    {
        $order = $this->invoice();
        $this->attempt($order, 'pi_error');
        $this->attempt($order, 'pi_other', 'pending', 2);
        StripeTestTransport::queue(['ok' => false, 'status' => 503, 'body' => null, 'error' => 'offline'], 'GET', '/v1/payment_intents/pi_error');
        StripeTestTransport::queueBody($this->intent('pi_other', 'canceled'), 'GET', '/v1/payment_intents/pi_other');
        $this->assertSame('reconcile_failed', stripe_payment_create(stripe_invoice_load($order))['error']);
        $this->assertCount(2, StripeTestTransport::$requests);
    }

    public function testPendingAgeUsesDatabaseClockAcrossPhpTimezonesAndRecentIntentIsChecked(): void
    {
        $order = $this->invoice();
        $id = $this->attempt($order, 'pi_recent', 'pending', 1, false);
        date_default_timezone_set('Pacific/Honolulu');
        $this->assertSame('pending', stripe_invoice_load($order)['state']);
        StripeTestTransport::queueBody($this->intent('pi_recent'), 'GET', '/v1/payment_intents/pi_recent');
        $this->assertSame('in_progress', stripe_payment_create(stripe_invoice_load($order), 'page', true)['error']);
        $this->assertCount(1, StripeTestTransport::$requests);
        StripeTestEnvironment::query("UPDATE stripe_payments SET created_at=CURRENT_TIMESTAMP - interval '16 minutes' WHERE id=$1", [$id]);
        $this->assertNotSame('pending', stripe_invoice_load($order)['state']);
    }

    public function testAbandonedIntentIsCanceledBeforeReplacementWithNewIdentity(): void
    {
        $order = $this->invoice();
        $old = $this->attempt($order, 'pi_old', 'pending', 4);
        StripeTestTransport::queueBody($this->intent('pi_old'), 'GET', '/v1/payment_intents/pi_old');
        StripeTestTransport::queueBody($this->intent('pi_old', 'canceled'), 'POST', '/v1/payment_intents/pi_old/cancel');
        StripeTestTransport::queueBody($this->intent('pi_new'), 'POST', '/v1/payment_intents');
        $this->assertTrue(stripe_payment_create(stripe_invoice_load($order))['ok']);
        $this->assertSame('canceled', StripeFixtures::row('stripe_payments', $old)['status']);
        $this->assertSame('pi:' . $GLOBALS['db'] . ':' . $order . ':5:n', StripeTestTransport::$requests[2]['idempotency_key']);
        $new = pg_fetch_assoc(StripeTestEnvironment::query("SELECT * FROM stripe_payments WHERE payment_intent_id='pi_new'"));
        $this->assertSame(5, (int)$new['attempt']);
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testCancellationWithoutLastErrorThroughSyncAdvancesAttemptAndKey(): void
    {
        $order = $this->invoice();
        StripeTestTransport::queueBody($this->intent('pi_first'), 'POST', '/v1/payment_intents');
        $this->assertTrue(stripe_payment_create(stripe_invoice_load($order))['ok']);
        $firstKey = StripeTestTransport::$requests[0]['idempotency_key'];
        StripeTestTransport::queueBody($this->intent('pi_first', 'canceled'), 'GET', '/v1/payment_intents/pi_first');
        $this->assertSame('canceled', stripe_payment_sync('pi_first'));
        StripeTestTransport::queueBody($this->intent('pi_second'), 'POST', '/v1/payment_intents');
        $this->assertTrue(stripe_payment_create(stripe_invoice_load($order))['ok']);
        $this->assertNotSame($firstKey, StripeTestTransport::$requests[2]['idempotency_key']);
        $this->assertSame('pi:' . $GLOBALS['db'] . ':' . $order . ':2:n', StripeTestTransport::$requests[2]['idempotency_key']);
    }

    public function testCancellationFailureOrNewProcessingStateCannotStartReplacement(): void
    {
        foreach (['error', 'processing'] as $outcome) {
            $order = $this->invoice();
            $pi = 'pi_cancel_' . $outcome;
            $this->attempt($order, $pi);
            StripeTestTransport::queueBody($this->intent($pi), 'GET', '/v1/payment_intents/' . $pi);
            if ($outcome === 'error') {
                StripeTestTransport::queue(['ok' => false, 'status' => 409, 'body' => null, 'error' => 'cannot_cancel'], 'POST', '/v1/payment_intents/' . $pi . '/cancel');
            } else {
                StripeTestTransport::queueBody($this->intent($pi, 'processing'), 'POST', '/v1/payment_intents/' . $pi . '/cancel');
            }
            $this->assertFalse(stripe_payment_create(stripe_invoice_load($order))['ok']);
        }
        $this->assertCount(4, StripeTestTransport::$requests);
    }

    public function testFullyOpenAmountIsPayableAndPartialBalanceBecomesManual(): void
    {
        $order = $this->invoice();
        $inv = stripe_invoice_load($order, StripeFixtures::TODAY);
        $this->assertSame(34000, $inv['open_rest_ore']);
        $this->assertTrue(stripe_invoice_payable($inv));
        StripeTestEnvironment::query('UPDATE openpost SET amount=240 WHERE faktnr=$1 AND konto_id=$2', [$inv['nr'], $inv['o']['konto_id']]);
        $partial = stripe_invoice_load($order, StripeFixtures::TODAY);
        $this->assertSame('manual', $partial['state']);
        $this->assertFalse(stripe_invoice_payable($partial));
        // Collection must reject a stale fully-open snapshot too.
        $this->assertSame('not_payable', stripe_payment_create($inv)['error']);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testOneOreToleranceIsInclusiveAndTwoOreDifferenceIsManual(): void
    {
        $order = $this->invoice();
        $inv = stripe_invoice_load($order);
        foreach (['339.99' => true, '340.01' => true, '339.98' => false, '340.02' => false] as $amount => $payable) {
            StripeTestEnvironment::query('UPDATE openpost SET amount=$1 WHERE faktnr=$2 AND konto_id=$3', [$amount, $inv['nr'], $inv['o']['konto_id']]);
            $this->assertSame($payable, stripe_invoice_payable(stripe_invoice_load($order)), $amount);
        }
    }

    public function testMissingOpenpostAndSplitSettlementCannotBePaidOrReportedFullyPaid(): void
    {
        $order = $this->invoice();
        $inv = stripe_invoice_load($order);
        StripeTestEnvironment::query('DELETE FROM openpost WHERE faktnr=$1 AND konto_id=$2', [$inv['nr'], $inv['o']['konto_id']]);
        $this->assertSame('manual', stripe_invoice_load($order)['state']);
        StripeFixtures::insert('openpost', ['konto_id' => $inv['o']['konto_id'], 'faktnr' => $inv['nr'], 'amount' => 340, 'udlignet' => '1']);
        StripeFixtures::insert('openpost', ['konto_id' => $inv['o']['konto_id'], 'faktnr' => '', 'amount' => 240, 'udlignet' => '0']);
        $split = stripe_invoice_load($order);
        $this->assertSame('manual', $split['state']);
        $this->assertFalse($split['paid_bank']);
        $this->assertFalse(stripe_invoice_payable($split));
    }

    public function testCashAndCreditCardTermsAlwaysUseClassicDeliveryAndManualState(): void
    {
        foreach (['Kontant', 'kOnTaNt', 'Kreditkort', 'KREDITKORT'] as $terms) {
            $order = $this->invoice(['betalingsbet' => $terms]);
            $inv = stripe_invoice_load($order);
            // Even an incorrectly retained full open item cannot authorize a second collection.
            $this->assertFalse(stripe_invoice_collectable($inv));
            $this->assertFalse(stripe_invoice_payable($inv));
            $this->assertSame('manual', $inv['state']);
            $this->assertSame('pdf', stripe_invoice_delivery($order));
            $this->assertSame('not_collectable', stripe_payment_create($inv)['error']);
        }
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testSubscribersAreExcludedFromEveryInvoiceEntryPointWithoutCustomerMutation(): void
    {
        foreach (['active', 'trialing', 'past_due'] as $status) {
            $order = $this->invoice(['nextfakt' => '2026-02-08', 'udskriv_til' => 'Stripe']);
            $before = stripe_invoice_load($order);
            StripeFixtures::insert('stripe_customers', ['stripe_customer_id' => 'cus_subscription_' . $status,
                'stripe_subscription_id' => 'sub_' . $status, 'konto_id' => $before['o']['konto_id'], 'status' => $status]);
            $inv = stripe_invoice_load($order);
            $this->assertSame('manual', $inv['state']);
            $this->assertFalse(stripe_invoice_collectable($inv));
            $this->assertFalse(stripe_invoice_payable($inv));
            $this->assertSame('pdf', stripe_invoice_delivery($order));
            $this->assertFalse(stripe_mail_invoice_link($before));
            $this->assertFalse(stripe_enrol_debtor($before, 'pm_replacement'));
            $this->assertSame('not_collectable', stripe_payment_create($before)['error']);
            $this->assertSame($before['debtor'], stripe_invoice_load($order)['debtor']);
        }
        $this->assertSame([], StripeTestTransport::$requests);
        $this->assertSame([], StripeTestTransport::$mails);
    }

    public function testOneOffStripeCustomerAndCanceledSubscriptionRemainEligible(): void
    {
        $order = $this->invoice();
        $inv = stripe_invoice_load($order);
        foreach (['active' => null, 'canceled' => 'sub_old'] as $status => $subscription) {
            StripeFixtures::insert('stripe_customers', ['stripe_customer_id' => 'cus_' . $status,
                'stripe_subscription_id' => $subscription, 'konto_id' => $inv['o']['konto_id'], 'status' => $status]);
        }
        $this->assertTrue(stripe_invoice_collectable($inv));
        $this->assertSame('link', stripe_invoice_delivery($order));
    }

    public function testPbsMandateOrderPbsAndForeignCurrencyAreExcluded(): void
    {
        foreach ([[[], ['pbs_nr' => '123']], [['pbs' => 'BS'], []], [['valuta' => 'EUR'], []]] as [$orderValues, $debtorValues]) {
            $order = $this->invoice($orderValues + ['nextfakt' => '2026-02-08'], $debtorValues);
            $inv = stripe_invoice_load($order);
            $this->assertFalse(stripe_invoice_collectable($inv));
            $this->assertFalse(stripe_invoice_payable($inv));
            $this->assertSame('manual', $inv['state']);
            $this->assertSame('pdf', stripe_invoice_delivery($order));
            $this->assertFalse(stripe_enrol_debtor($inv, 'pm_new'));
            $this->assertSame('not_collectable', stripe_payment_create($inv, 'auto', false, 'pm_old')['error']);
        }
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testCopiedTenantCannotGenerateLinksDeliverEnrolOrCollect(): void
    {
        $order = $this->invoice(['nextfakt' => '2026-02-08']);
        $inv = stripe_invoice_load($order);
        $db = $GLOBALS['db'];
        try {
            $GLOBALS['db'] = 'other_ledger';
            $this->assertFalse(stripe_invoice_collectable($inv));
            $this->assertFalse(stripe_invoice_payable($inv));
            $this->assertSame('manual', stripe_invoice_state($inv));
            $this->assertSame('', stripe_invoice_url($order));
            $this->assertSame('pdf', stripe_invoice_format());
            $this->assertSame('pdf', stripe_invoice_delivery($order));
            $this->assertFalse(stripe_enrol_debtor($inv, 'pm_new'));
            $this->assertSame('not_collectable', stripe_payment_create($inv)['error']);
        } finally {
            $GLOBALS['db'] = $db;
            stripe_settings_flush();
        }
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testSecondTabWithDifferentSaveChoiceCannotCreateAnotherLiveIntent(): void
    {
        $order = $this->invoice(['nextfakt' => '2026-02-08']);
        $stalePage = stripe_invoice_load($order);
        StripeTestTransport::queueBody($this->intent('pi_first_tab'), 'POST', '/v1/payment_intents');
        $this->assertTrue(stripe_payment_create($stalePage, 'page', false)['ok']);
        StripeTestTransport::queueBody($this->intent('pi_first_tab'), 'GET', '/v1/payment_intents/pi_first_tab');
        $this->assertSame('in_progress', stripe_payment_create($stalePage, 'page', true)['error']);
        $creates = array_filter(StripeTestTransport::$requests, static fn($r) => $r['method'] === 'POST' && $r['path'] === '/v1/payment_intents');
        $this->assertCount(1, $creates);
        $count = pg_fetch_assoc(StripeTestEnvironment::query('SELECT count(*) AS n FROM stripe_payments WHERE ordre_id=$1', [$order]));
        $this->assertSame(1, (int)$count['n']);
    }

    public function testRetiringDeclinedIntentKeepsAutomaticRetryHistory(): void
    {
        $order = $this->invoice();
        $id = $this->attempt($order, 'pi_declined', 'failed');
        StripeTestEnvironment::query("UPDATE stripe_payments SET source='auto', last_error='card_declined' WHERE id=$1", [$id]);
        $declined = $this->intent('pi_declined') + ['last_payment_error' => ['code' => 'card_declined']];
        StripeTestTransport::queueBody($declined, 'GET', '/v1/payment_intents/pi_declined');
        StripeTestTransport::queueBody($this->intent('pi_declined', 'canceled'), 'POST', '/v1/payment_intents/pi_declined/cancel');
        StripeTestTransport::queueBody($this->intent('pi_recovery'), 'POST', '/v1/payment_intents');
        $this->assertTrue(stripe_payment_create(stripe_invoice_load($order))['ok']);
        $previous = StripeFixtures::row('stripe_payments', $id);
        $this->assertSame('canceled', $previous['status']);
        $this->assertSame('auto', $previous['source']);
        $this->assertTrue(stripe_payment_attempt_failed($previous));
        $this->assertFalse(stripe_payment_attempt_failed(['status' => 'canceled', 'last_error' => null]));
    }

    public function testChangedSaveConsentBlocksAmbiguousCreateThenCancelsRecoveredIntentBeforeReplacement(): void
    {
        $order = $this->invoice(['nextfakt' => '2026-02-08']);
        StripeTestTransport::queue(['ok' => false, 'status' => 0, 'body' => null, 'error' => 'network'], 'POST', '/v1/payment_intents');
        $this->assertFalse(stripe_payment_create(stripe_invoice_load($order), 'page', true)['ok']);
        $first = StripeTestTransport::$requests[0];
        $this->assertSame('off_session', $first['params']['setup_future_usage']);
        StripeTestTransport::queueBody(['data' => [], 'has_more' => false], 'GET', '/v1/payment_intents');
        $this->assertSame('in_progress', stripe_payment_create(stripe_invoice_load($order), 'page', false)['error']);
        $this->assertCount(2, StripeTestTransport::$requests);
        $this->assertSame('GET', StripeTestTransport::$requests[1]['method']);
        $this->assertArrayNotHasKey('setup_future_usage', StripeTestTransport::$requests[1]['params']);

        $old = $this->intent('pi_saved_stall') + ['metadata' => $first['params']['metadata']];
        StripeTestTransport::queueBody(['data' => [$old], 'has_more' => false], 'GET', '/v1/payment_intents');
        StripeTestTransport::queueBody($this->intent('pi_saved_stall', 'canceled'), 'POST', '/v1/payment_intents/pi_saved_stall/cancel');
        StripeTestTransport::queueBody($this->intent('pi_without_save'), 'POST', '/v1/payment_intents');
        $result = stripe_payment_create(stripe_invoice_load($order), 'page', false);
        $this->assertTrue($result['ok']);
        $this->assertNull($result['setup_future_usage']);
        $last = StripeTestTransport::$requests[4];
        $this->assertArrayNotHasKey('setup_future_usage', $last['params']);
        $this->assertArrayNotHasKey('off_session', $last['params']);
        $this->assertNotSame($first['idempotency_key'], $last['idempotency_key']);
        $rows = pg_fetch_all(StripeTestEnvironment::query('SELECT status, identity_no FROM stripe_collection_attempts WHERE ordre_id=$1 ORDER BY id', [$order]));
        $this->assertSame(['recorded', 'recorded'], array_column($rows, 'status'));
        $this->assertSame([1, 2], array_map('intval', array_column($rows, 'identity_no')));
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testDifferentCallerCannotReplaceRecoveredProcessingOrFailedLookupOrCancel(): void
    {
        $order = $this->invoice(['nextfakt' => '2026-02-08']);
        StripeTestTransport::queue(['ok' => false, 'status' => 503, 'body' => null, 'error' => 'lost'], 'POST', '/v1/payment_intents');
        stripe_payment_create(stripe_invoice_load($order), 'page', true);
        StripeTestEnvironment::query("UPDATE stripe_collection_attempts SET status='requesting' WHERE ordre_id=$1", [$order]);
        StripeTestTransport::queue(['ok' => false, 'status' => 503, 'body' => null, 'error' => 'lookup failed'], 'GET', '/v1/payment_intents');
        $this->assertSame('reconcile_failed', stripe_payment_create(stripe_invoice_load($order), 'page', false)['error']);
        $old = $this->intent('pi_cannot_cancel') + ['metadata' => StripeTestTransport::$requests[0]['params']['metadata']];
        StripeTestTransport::queueBody(['data' => [$old], 'has_more' => false], 'GET', '/v1/payment_intents');
        StripeTestTransport::queue(['ok' => false, 'status' => 409, 'body' => null, 'error' => 'cancel failed'], 'POST', '/v1/payment_intents/pi_cannot_cancel/cancel');
        $this->assertSame('reconcile_failed', stripe_payment_create(stripe_invoice_load($order), 'page', false)['error']);
        $pi = $this->intent('pi_still_processing', 'processing') + ['metadata' => StripeTestTransport::$requests[0]['params']['metadata']];
        StripeTestTransport::queueBody(['data' => [$pi], 'has_more' => false], 'GET', '/v1/payment_intents');
        $this->assertSame('in_progress', stripe_payment_create(stripe_invoice_load($order), 'page', false)['error']);
        $this->assertCount(5, StripeTestTransport::$requests);
        $this->assertSame('processing', stripe_invoice_load($order)['pending']['status']);
    }

    public function testPreparedRequestWithDifferentConsentIsAbandonedWithoutSendingIt(): void
    {
        $order = $this->invoice(['nextfakt' => '2026-02-08']);
        $inv = stripe_invoice_load($order);
        stripe_invoice_collection_lock($order);
        try {
            $prepared = stripe_attempt_prepare($inv, 'page', true, '');
        } finally {
            stripe_invoice_collection_lock($order, true);
        }
        StripeTestTransport::queueBody($this->intent('pi_prepared_replacement'), 'POST', '/v1/payment_intents');
        $this->assertTrue(stripe_payment_create($inv, 'page', false)['ok']);
        $row = pg_fetch_assoc(StripeTestEnvironment::query('SELECT status FROM stripe_collection_attempts WHERE id=$1', [$prepared['id']]));
        $this->assertSame('abandoned', $row['status']);
        $this->assertCount(1, StripeTestTransport::$requests);
        $this->assertSame(2, StripeTestTransport::$requests[0]['params']['metadata']['saldi_attempt_identity']);
        $this->assertArrayNotHasKey('setup_future_usage', StripeTestTransport::$requests[0]['params']);
    }

    public function testReservationMatchIncludesSourceCardConfirmationAndOffSession(): void
    {
        $request = ['source' => 'auto', 'save_card' => false, 'params' => json_encode([
            'payment_method' => 'pm_original', 'confirm' => 'true', 'off_session' => 'true', 'payment_method_types' => ['card'],
        ])];
        $this->assertTrue(stripe_attempt_matches($request, 'auto', false, 'pm_original'));
        $this->assertFalse(stripe_attempt_matches($request, 'backup', false, 'pm_original'));
        $this->assertFalse(stripe_attempt_matches($request, 'auto', false, 'pm_replacement'));
        $this->assertFalse(stripe_attempt_matches($request, 'auto', true, 'pm_original'));
        foreach (['confirm', 'off_session'] as $field) {
            $params = json_decode($request['params'], true);
            unset($params[$field]);
            $changed = array_replace($request, ['params' => json_encode($params)]);
            $this->assertFalse(stripe_attempt_matches($changed, 'auto', false, 'pm_original'));
        }
    }

    public function testTerminalCreateErrorsReleaseTheInvoiceWithANewIdentityAndKey(): void
    {
        foreach ([400, 401, 403, 404, 422] as $http) {
            $order = $this->invoice();
            $before = count(StripeTestTransport::$requests);
            StripeTestTransport::queue(['ok' => false, 'status' => $http, 'body' => ['error' => ['type' => 'invalid_request_error']], 'error' => 'configuration'], 'POST', '/v1/payment_intents');
            $this->assertFalse(stripe_payment_create(stripe_invoice_load($order))['ok']);
            $rejected = pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM stripe_collection_attempts WHERE ordre_id=$1', [$order]));
            $this->assertSame('rejected', $rejected['status']);
            StripeTestTransport::queueBody($this->intent('pi_after_' . $http), 'POST', '/v1/payment_intents');
            $this->assertTrue(stripe_payment_create(stripe_invoice_load($order))['ok']);
            $first = StripeTestTransport::$requests[$before];
            $next = StripeTestTransport::$requests[$before + 1];
            $this->assertNotSame($first['idempotency_key'], $next['idempotency_key']);
            $this->assertSame(2, $next['params']['metadata']['saldi_attempt_identity']);
        }
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testPageAndManualCreatesEnforceCardPaymentMethods(): void
    {
        foreach (['page', 'manual'] as $source) {
            $order = $this->invoice(['udskriv_til' => 'Stripe'], ['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_date' => StripeFixtures::INVOICE_DATE]);
            StripeTestEnvironment::query('UPDATE openpost SET forfaldsdate=$1 WHERE konto_id=$2', [StripeFixtures::TODAY, StripeFixtures::row('ordrer', $order)['konto_id']]);
            StripeTestTransport::queueBody($this->intent('pi_card_' . $source), 'POST', '/v1/payment_intents');
            $this->assertTrue(stripe_payment_create(stripe_invoice_load($order), $source, false, $source === 'manual' ? 'pm_saved' : '')['ok']);
            $params = StripeTestTransport::$requests[count(StripeTestTransport::$requests) - 1]['params'];
            $this->assertSame(['card'], $params['payment_method_types']);
            $this->assertArrayNotHasKey('automatic_payment_methods', $params);
        }
    }

    public function testPartialCreditNoteMakesTheOriginalManualEvenWhenItsOpenpostIsUnchanged(): void
    {
        $order = $this->invoice();
        $before = stripe_invoice_load($order);
        $credit = StripeFixtures::invoice((int)$before['o']['konto_id'], ['art' => 'DK', 'kred_ord_id' => $order, 'sum' => -40, 'moms' => -10]);
        $this->assertSame('manual', stripe_invoice_load($order)['state']);
        $this->assertSame(34000, stripe_invoice_load($order)['open_rest_ore']);
        $this->assertFalse(stripe_payment_create($before)['ok']);
        // The credit-order link alone must also block, even after the negative post is settled.
        StripeTestEnvironment::query("UPDATE openpost SET udlignet='1' WHERE konto_id=$1 AND faktnr=$2", [$before['o']['konto_id'], StripeFixtures::row('ordrer', $credit)['fakturanr']]);
        $this->assertSame('manual', stripe_invoice_load($order)['state']);
        $this->assertFalse(stripe_payment_create($before)['ok']);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testUnmatchedAccountPaymentBlocksEveryCollectionCallerUnderTheLock(): void
    {
        $order = $this->invoice(['udskriv_til' => 'Stripe'], ['stripe' => 'on', 'stripe_pm_id' => 'pm_saved', 'stripe_date' => StripeFixtures::INVOICE_DATE]);
        StripeTestEnvironment::query('UPDATE openpost SET forfaldsdate=$1 WHERE konto_id=$2', [StripeFixtures::TODAY, StripeFixtures::row('ordrer', $order)['konto_id']]);
        $before = stripe_invoice_load($order);
        $payment = StripeFixtures::insert('openpost', ['konto_id' => $before['o']['konto_id'], 'faktnr' => '', 'amount' => -50, 'udlignet' => '0']);
        $this->assertSame('manual', stripe_invoice_load($order)['state']);
        $this->assertFalse(stripe_invoice_payable(stripe_invoice_load($order)));
        foreach (['page', 'auto', 'manual'] as $source) {
            $this->assertFalse(stripe_payment_create($before, $source, false, $source === 'page' ? '' : 'pm_saved')['ok']);
        }
        $this->assertSame([], StripeTestTransport::$requests);
        StripeTestEnvironment::query("UPDATE openpost SET udlignet='1' WHERE id=$1", [$payment]);
        $this->assertTrue(stripe_invoice_payable(stripe_invoice_load($order)));
    }

}
