<?php
// 20261022 CDX/LH Real PostgreSQL claim, booking rollback, repair and invoice webhook regressions.
// 20261022 CDX/LH Duplicate-capture accounting/alerts, pre-create reconciliation and dunning regressions.
// 20261022 CDX/LH Verify repeated duplicate-alert delivery failures stay retryable and completed replays preserve capture fields.
// 20261022 CDX/LH Isolate durable collection tables across real booking COMMITs and use the shared fixture clock.
// 20261022 CDX/LH Exercise repair-only CLI work, project dimensions, one-off consent and enrolment failure after capture.
// 20261022 CDX/LH Verify three-day reminder protection for page attempts and permanent saved-card authentication protection.
// 20261022 CDX/LH Regress journal creation/reuse against the real tenant table with its sequence behind MAX(id).

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/stripeIncludes/stripeInvoiceWebhook.php';

/**
 * Booking commits deliberately. Temporary schema copies let production COMMIT/ROLLBACK
 * execute normally without persisting fixtures or depending on a master DB/session.
 */
final class StripeBookingTest extends PHPUnit\Framework\TestCase
{
    private array $tables = [];
    private array $sequences = [];
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
        $GLOBALS['stripe_charge_today'] = StripeFixtures::TODAY;
        $GLOBALS['db_transaktion_depth'] = 0;
        $GLOBALS['db_modify_fejl'] = false;
        foreach (['adresser', 'ordrer', 'ordrelinjer', 'openpost', 'stripe_payments', 'stripe_customers',
            'grupper', 'settings', 'kladdeliste', 'kassekladde', 'transaktioner', 'stripe_collection_attempts'] as $table) {
            StripeTestEnvironment::query("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING ALL)");
            $this->tables[] = $table;
            $sequence = 's1_' . $table . '_seq';
            StripeTestEnvironment::query("CREATE TEMP SEQUENCE $sequence");
            $this->sequences[] = $sequence;
            StripeTestEnvironment::query("ALTER TABLE pg_temp.$table ALTER COLUMN id SET DEFAULT nextval('pg_temp.$sequence')");
        }
        foreach (['stripe_charge_errors', 'stripe_unenrol_mail'] as $table) {
            StripeTestEnvironment::query("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING ALL)");
            $this->tables[] = $table;
        }
        StripeTestEnvironment::query("CREATE TEMP TABLE stripe_events (
            id serial primary key, event_id text, status text, processed_at timestamp,
            saldi_order_id integer, invoice_number text)");
        $this->tables[] = 'stripe_events';
        StripeTestEnvironment::query("INSERT INTO adresser SELECT * FROM public.adresser WHERE art='S'");
        // One deterministic fiscal fixture contains every capture date used by this suite.
        StripeFixtures::insert('grupper', ['art' => 'RA', 'kodenr' => 5, 'box1' => '1', 'box2' => '2000', 'box3' => '12', 'box4' => '2099']);
        StripeFixtures::insert('grupper', ['art' => 'DIV', 'kodenr' => 3, 'box10' => '58050', 'fiscal_year' => 5]);
        StripeTestEnvironment::seedSettings();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->tables) {
                if (pg_transaction_status($GLOBALS['connection']) !== PGSQL_TRANSACTION_IDLE) {
                    StripeTestEnvironment::query('ROLLBACK');
                }
                foreach (array_reverse($this->tables) as $table) {
                    StripeTestEnvironment::query("DROP TABLE IF EXISTS pg_temp.$table CASCADE");
                }
                foreach ($this->sequences as $sequence) {
                    StripeTestEnvironment::query("DROP SEQUENCE IF EXISTS pg_temp.$sequence");
                }
                StripeTestEnvironment::query('DROP FUNCTION IF EXISTS pg_temp.s1_skip_write()');
            }
            stripe_settings_flush();
            unset($GLOBALS['stripe_charge_today']);
            $GLOBALS['db_transaktion_depth'] = 0;
            $GLOBALS['db_modify_fejl'] = false;
            if ($this->timezone !== null) date_default_timezone_set($this->timezone);
        } finally {
            parent::tearDown();
        }
    }

    /** @return array{0: int, 1: int, 2: array} Order id, payment row id and captured Stripe intent. */
    private function captured(bool $save = false, string $piId = 'pi_s1'): array
    {
        $debtor = StripeFixtures::debtor(['stripe_customer_id' => 'cus_s1']);
        $order = StripeFixtures::invoice($debtor, ['nextfakt' => '2026-02-08']);
        $inv = stripe_invoice_load($order, StripeFixtures::TODAY);
        $pi = [
            'id' => $piId, 'customer' => 'cus_s1', 'payment_method' => 'pm_s1',
            'amount' => 34000, 'amount_received' => 34000, 'currency' => 'dkk', 'status' => 'succeeded',
            'metadata' => ['saldi_db' => $GLOBALS['db'], 'saldi_ordre_id' => $order, 'saldi_fakturanr' => $inv['nr']],
            'latest_charge' => ['payment_method_details' => ['card' => ['brand' => 'visa', 'last4' => '4242']]],
        ];
        $id = stripe_payment_record($inv, $pi, 'page', $save, 1);
        return [$order, $id, $pi];
    }

    /** @return int New already-claimed event row. */
    private function event(string $id = 'evt_s1', string $status = 'received'): int
    {
        return StripeFixtures::insert('stripe_events', ['event_id' => $id, 'status' => $status]);
    }

    /** @return array Current event row. */
    private function eventRow(int $id): array
    {
        return pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM stripe_events WHERE id=$1', [$id]));
    }

    /** @return int Number of accounting lines for this invoice. */
    private function lineCount(int $order): int
    {
        $row = pg_fetch_assoc(StripeTestEnvironment::query('SELECT count(*) AS n FROM kassekladde WHERE ordre_id=$1', [$order]));
        return (int)$row['n'];
    }

    /** Assert both Stripe enrolment mutations run after COMMIT, as does the receipt transport. */
    private function queueEnrolment(): void
    {
        StripeTestTransport::queueBody(['id' => 'cus_s1'], 'POST', '/v1/customers/cus_s1');
        StripeTestTransport::queueBody(['id' => 'pm_s1'], 'POST', '/v1/payment_methods/pm_s1');
        StripeTestTransport::$beforeResponse = function (): void {
            $this->assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status($GLOBALS['connection']));
        };
        StripeTestTransport::$beforeMail = function (): void {
            $this->assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status($GLOBALS['connection']));
        };
    }

    public function testPageSyncThenWebhookBooksMailsAndEnrolsOnce(): void
    {
        [$order, $id, $pi] = $this->captured(true);
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_s1');
        $this->queueEnrolment();
        $this->assertSame('applied', stripe_payment_sync('pi_s1'));
        $event = $this->event();
        $this->assertSame(['code' => 200, 'message' => 'duplicate'], stripe_invoice_webhook_dispatch($event, 'payment_intent.succeeded', $pi, 'evt_s1'));
        $this->assertSame('duplicate', $this->eventRow($event)['status']);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertCount(1, StripeTestTransport::$mails);
        $this->assertCount(3, StripeTestTransport::$requests);
        $this->assertNotEmpty(StripeFixtures::row('stripe_payments', $id)['confirmation_sent_at']);
        $this->assertSame('on', StripeFixtures::row('ordrer', $order)['betalt']);
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testOneOffCaptureCreatesOneJournalAndReceiptWithoutSavingCard(): void
    {
        [$order, $id, $pi] = $this->captured(false);
        $this->assertSame('applied', stripe_payment_apply_succeeded($pi, 'evt_one_off'));
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_s1');
        $this->assertSame('duplicate', stripe_payment_sync('pi_s1'));
        $debtor = StripeFixtures::row('adresser', (int)StripeFixtures::row('ordrer', $order)['konto_id']);
        $this->assertSame('', $debtor['stripe']);
        $this->assertEmpty($debtor['stripe_pm_id']);
        $this->assertEmpty($debtor['stripe_date']);
        $this->assertFalse(stripe_bool(StripeFixtures::row('stripe_payments', $id)['save_card']));
        $this->assertSame(1, $this->lineCount($order));
        $this->assertCount(1, StripeTestTransport::$mails);
        $this->assertCount(1, StripeTestTransport::$requests);
    }

    public function testRealJournalWithLaggingSequenceAllocatesMaxPlusOneAndIsReused(): void
    {
        // Other fixtures stay temporary across booking COMMITs. Only this legacy table
        // must be real: shadow tables with synchronized sequences hid the production bug.
        StripeTestEnvironment::query('DROP TABLE pg_temp.kladdeliste');
        $resolved = pg_fetch_assoc(StripeTestEnvironment::query("SELECT 'kladdeliste'::regclass::oid = 'public.kladdeliste'::regclass::oid AS real_table"));
        $this->assertSame('t', $resolved['real_table']);
        $row = pg_fetch_assoc(StripeTestEnvironment::query('SELECT coalesce(max(id), 0) AS id FROM public.kladdeliste'));
        $previousMax = (int)$row['id'];
        $this->assertGreaterThan(1, $previousMax, 'Use the restored tenant journal history, not an empty table.');
        $sequenceRow = pg_fetch_assoc(StripeTestEnvironment::query("SELECT pg_get_serial_sequence('public.kladdeliste', 'id') AS name"));
        $sequence = $sequenceRow['name'];
        $this->assertNotEmpty($sequence);
        // The identifier is trusted PostgreSQL metadata for the fixed public table.
        $sequenceState = pg_fetch_assoc(StripeTestEnvironment::query("SELECT last_value, is_called FROM $sequence"));
        // A separate fiscal year prevents adoption of an existing tenant Stripe journal.
        StripeTestEnvironment::query("UPDATE grupper SET box1='1', box2='2098', box3='12', box4='2098' WHERE art='RA'");
        $name = stripe_txt('journal_name');
        $existing = pg_fetch_assoc(StripeTestEnvironment::query("SELECT count(*) AS n FROM public.kladdeliste
            WHERE kladdenote=$1 AND kladdedate BETWEEN '2098-01-01' AND '2098-12-31'", [$name]));
        $this->assertSame(0, (int)$existing['n']);

        try {
            StripeTestEnvironment::query('SELECT setval($1::regclass, 1, true)', [$sequence]);
            [$order, $id] = $this->captured(false, 'pi_real_journal_first');
            StripeTestEnvironment::query("UPDATE stripe_payments SET status='succeeded', succeeded_at='2098-01-10 10:00:00'
                WHERE id=$1", [$id]);
            $this->assertSame(['found' => 1, 'repaired' => 1, 'failed' => 0], stripe_payment_repair());
            $journal = pg_fetch_assoc(StripeTestEnvironment::query("SELECT * FROM public.kladdeliste
                WHERE kladdenote=$1 AND kladdedate='2098-01-10'", [$name]));
            $this->assertIsArray($journal);
            $this->assertSame($previousMax + 1, (int)$journal['id']);
            $this->assertSame('-', $journal['bogfort']);
            $this->assertSame('stripe', $journal['oprettet_af']);
            $this->assertSame(1, $this->lineCount($order));
            $line = pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM kassekladde WHERE ordre_id=$1', [$order]));
            $this->assertSame((int)$journal['id'], (int)$line['kladde_id']);
            $payment = StripeFixtures::row('stripe_payments', $id);
            $this->assertSame((int)$line['id'], (int)$payment['journal_line_id']);
            $this->assertNotEmpty($payment['confirmation_sent_at']);
            $this->assertSame('on', StripeFixtures::row('ordrer', $order)['betalt']);

            [$secondOrder, $secondId] = $this->captured(false, 'pi_real_journal_second');
            StripeTestEnvironment::query("UPDATE stripe_payments SET status='succeeded', succeeded_at='2098-01-11 10:00:00'
                WHERE id=$1", [$secondId]);
            $this->assertSame('applied', stripe_payment_complete($secondId));
            $secondLine = pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM kassekladde WHERE ordre_id=$1', [$secondOrder]));
            $this->assertSame((int)$journal['id'], (int)$secondLine['kladde_id']);
            $this->assertSame(1, $this->lineCount($secondOrder));
            $count = pg_fetch_assoc(StripeTestEnvironment::query('SELECT count(*) AS n FROM public.kladdeliste WHERE id>$1', [$previousMax]));
            $this->assertSame(1, (int)$count['n']);
            $this->assertCount(2, StripeTestTransport::$mails);
            $this->assertSame([], StripeTestTransport::$requests);
            $after = pg_fetch_assoc(StripeTestEnvironment::query("SELECT last_value FROM $sequence"));
            $this->assertSame(1, (int)$after['last_value'], 'Journal allocation must not advance the legacy sequence.');
        } finally {
            if (pg_transaction_status($GLOBALS['connection']) !== PGSQL_TRANSACTION_IDLE) {
                StripeTestEnvironment::query('ROLLBACK');
            }
            try {
                // No rows matched this identity before the test, including on a failed assertion.
                StripeTestEnvironment::query("DELETE FROM public.kladdeliste WHERE kladdenote=$1
                    AND kladdedate BETWEEN '2098-01-01' AND '2098-12-31' AND oprettet_af='stripe'", [$name]);
            } finally {
                // setval is not transactional: restore both value and first-use state explicitly.
                StripeTestEnvironment::query('SELECT setval($1::regclass, $2::bigint, $3::boolean)',
                    [$sequence, $sequenceState['last_value'], $sequenceState['is_called']]);
            }
        }
    }

    public function testRepairOnlyJobReportsIncompleteCaptureAndRetriesAfterEnrolmentFailure(): void
    {
        [$order, $id, $pi] = $this->captured(true);
        StripeTestTransport::queue(['ok' => false, 'status' => 503, 'body' => null, 'error' => 'customer default unavailable'], 'POST', '/v1/customers/cus_s1');
        $this->assertSame('delivery_error', stripe_payment_apply_succeeded($pi));
        $this->assertSame(1, $this->lineCount($order));
        $this->assertNull(StripeFixtures::row('stripe_payments', $id)['confirmation_sent_at']);
        $this->assertSame([], StripeTestTransport::$mails);
        StripeTestTransport::queue(['ok' => false, 'status' => 503, 'body' => null, 'error' => 'still unavailable'], 'POST', '/v1/customers/cus_s1');
        $failed = stripe_charge_job(StripeFixtures::TODAY, false, true);
        $this->assertSame(1, $failed['exit_code']);
        $this->assertSame(1, $failed['stats']['repair_failed']);
        $this->queueEnrolment();
        $repaired = stripe_charge_job(StripeFixtures::TODAY, false, true);
        $this->assertSame(0, $repaired['exit_code']);
        $this->assertSame(1, $repaired['stats']['repair_found']);
        $this->assertSame(1, $repaired['stats']['repaired']);
        $this->assertNotEmpty(StripeFixtures::row('stripe_payments', $id)['confirmation_sent_at']);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertCount(1, StripeTestTransport::$mails);
        $this->assertSame(0, stripe_charge_job(StripeFixtures::TODAY, false, true)['stats']['repair_found']);
        foreach (StripeTestTransport::$requests as $request) {
            $this->assertNotSame('/v1/payment_intents', $request['path']);
        }
    }

    public function testJournalRetainsInvoiceProjectAndDepartmentForOpenItemSettlement(): void
    {
        [$order, $id, $pi] = $this->captured();
        StripeTestEnvironment::query("UPDATE ordrer SET projekt='821', afd=7 WHERE id=$1", [$order]);
        $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
        $payment = StripeFixtures::row('stripe_payments', $id);
        $line = pg_fetch_assoc(StripeTestEnvironment::query('SELECT projekt, afd, faktura, ordre_id FROM kassekladde WHERE id=$1', [$payment['journal_line_id']]));
        $this->assertSame('821', $line['projekt']);
        $this->assertSame(7, (int)$line['afd']);
        $this->assertSame((string)StripeFixtures::row('ordrer', $order)['fakturanr'], (string)$line['faktura']);
        $this->assertSame($order, (int)$line['ordre_id']);
    }

    public function testCapturedCustomerOverridesAStaleDebtorCustomerDuringPostCommitEnrolment(): void
    {
        [$order, $id, $pi] = $this->captured(true);
        $konto = (int)StripeFixtures::row('ordrer', $order)['konto_id'];
        StripeTestEnvironment::query("UPDATE adresser SET stripe='on', stripe_pm_id='pm_s1', stripe_customer_id='cus_stale', stripe_date=$1 WHERE id=$2", [StripeFixtures::INVOICE_DATE, $konto]);
        $this->queueEnrolment();
        $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
        $this->assertSame('cus_s1', StripeFixtures::row('adresser', $konto)['stripe_customer_id']);
        $this->assertSame('cus_s1', StripeFixtures::row('stripe_payments', $id)['stripe_customer_id']);
        $this->assertSame('/v1/customers/cus_s1', StripeTestTransport::$requests[0]['path']);
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testWebhookThenPageSyncBooksMailsAndEnrolsOnce(): void
    {
        [$order, $id, $pi] = $this->captured(true);
        $this->queueEnrolment();
        $this->assertSame(['code' => 200, 'message' => 'applied'], stripe_invoice_webhook_dispatch($this->event(), 'payment_intent.succeeded', $pi, 'evt_s1'));
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_s1');
        $this->assertSame('duplicate', stripe_payment_sync('pi_s1'));
        $this->assertSame(1, $this->lineCount($order));
        $this->assertCount(1, StripeTestTransport::$mails);
        $this->assertCount(3, StripeTestTransport::$requests);
        $this->assertSame('pm_s1', StripeFixtures::row('adresser', (int)StripeFixtures::row('ordrer', $order)['konto_id'])['stripe_pm_id']);
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testCallerThatReadPendingButLosesTransitionReturnsDuplicate(): void
    {
        [$order, $id, $pi] = $this->captured();
        $slowPi = $pi;
        $slowPi['latest_charge'] = 'ch_s1';
        StripeTestTransport::queueBody(['card' => ['brand' => 'visa', 'last4' => '4242']], 'GET', '/v1/payment_methods/pm_s1');
        $winner = null;
        StripeTestTransport::$beforeResponse = function () use ($pi, &$winner): void {
            StripeTestTransport::$beforeResponse = null;
            $winner = stripe_payment_apply_succeeded($pi, 'evt_winner');
        };
        $this->assertSame('duplicate', stripe_payment_apply_succeeded($slowPi, 'sync'));
        $this->assertSame('applied', $winner);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertCount(1, StripeTestTransport::$mails);
        $this->assertSame('evt_winner', StripeFixtures::row('stripe_payments', $id)['last_event_id']);
    }

    /** A real DB trigger simulates an INSERT/UPDATE which produced no affected row. */
    private function skipWrite(string $table, string $operation, string $condition = ''): void
    {
        StripeTestEnvironment::query('CREATE OR REPLACE FUNCTION pg_temp.s1_skip_write() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RETURN NULL; END $$');
        StripeTestEnvironment::query("CREATE TRIGGER s1_skip BEFORE $operation ON $table FOR EACH ROW $condition EXECUTE FUNCTION pg_temp.s1_skip_write()");
    }

    public function testJournalInsertFailureStaysReceivedAndRepairCompletesOnceWithoutCharging(): void
    {
        [$order, $id, $pi] = $this->captured(true);
        $event = $this->event('evt_s1', 'retrying');
        $this->skipWrite('kassekladde', 'INSERT');
        $this->assertSame(['code' => 500, 'message' => 'database_error'], stripe_invoice_webhook_dispatch($event, 'payment_intent.succeeded', $pi, 'evt_s1'));
        $this->assertSame('received', $this->eventRow($event)['status']);
        $this->assertNull($this->eventRow($event)['processed_at']);
        $payment = StripeFixtures::row('stripe_payments', $id);
        $this->assertSame('succeeded', $payment['status']);
        $this->assertNull($payment['journal_line_id']);
        $this->assertNull($payment['confirmation_sent_at']);
        $this->assertSame('', StripeFixtures::row('ordrer', $order)['betalt']);
        $this->assertSame(0, $this->lineCount($order));
        $this->assertSame([], StripeTestTransport::$mails);
        $this->assertSame([], StripeTestTransport::$requests);
        StripeTestEnvironment::query('DROP TRIGGER s1_skip ON kassekladde');
        $this->queueEnrolment();
        $this->assertSame(['found' => 1, 'repaired' => 1, 'failed' => 0], stripe_payment_repair());
        $this->assertSame(['found' => 0, 'repaired' => 0, 'failed' => 0], stripe_payment_repair());
        $this->assertSame(1, $this->lineCount($order));
        $this->assertSame('on', StripeFixtures::row('ordrer', $order)['betalt']);
        $this->assertCount(1, StripeTestTransport::$mails);
        foreach (StripeTestTransport::$requests as $request) {
            $this->assertNotSame('/v1/payment_intents', $request['path']);
        }
        $this->assertSame('duplicate', stripe_payment_apply_succeeded($pi, 'evt_replay'));
    }

    public function testJournalReferenceFailureRollsBackInvoiceAndLine(): void
    {
        [$order, $id, $pi] = $this->captured();
        $this->skipWrite('stripe_payments', 'UPDATE', 'WHEN (NEW.journal_line_id IS NOT NULL)');
        $this->assertSame('database_error', stripe_payment_apply_succeeded($pi));
        $this->assertSame(0, $this->lineCount($order));
        $this->assertSame('', StripeFixtures::row('ordrer', $order)['betalt']);
        $this->assertNull(StripeFixtures::row('stripe_payments', $id)['journal_line_id']);
        $this->assertSame([], StripeTestTransport::$mails);
        StripeTestEnvironment::query('DROP TRIGGER s1_skip ON stripe_payments');
        $this->assertSame(1, stripe_payment_repair()['repaired']);
        $this->assertSame(1, $this->lineCount($order));
    }

    public function testPaidFlagFailureRollsBackJournalAndCanBeRepaired(): void
    {
        [$order, $id, $pi] = $this->captured();
        $this->skipWrite('ordrer', 'UPDATE', "WHEN (NEW.betalt = 'on')");
        $this->assertSame('database_error', stripe_payment_apply_succeeded($pi));
        $this->assertSame(0, $this->lineCount($order));
        $this->assertSame('', StripeFixtures::row('ordrer', $order)['betalt']);
        $this->assertSame('succeeded', StripeFixtures::row('stripe_payments', $id)['status']);
        $this->assertSame([], StripeTestTransport::$mails);
        StripeTestEnvironment::query('DROP TRIGGER s1_skip ON ordrer');
        $this->assertSame(1, stripe_payment_repair()['repaired']);
        $this->assertSame(1, $this->lineCount($order));
    }

    public function testRepairAdoptsAnUnlinkedHistoricJournalLine(): void
    {
        [$order, $id, $pi] = $this->captured();
        $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
        $lineId = StripeFixtures::row('stripe_payments', $id)['journal_line_id'];
        StripeTestEnvironment::query('UPDATE stripe_payments SET journal_line_id=NULL WHERE id=$1', [$id]);
        $this->assertSame(1, stripe_payment_repair()['repaired']);
        $this->assertSame($lineId, StripeFixtures::row('stripe_payments', $id)['journal_line_id']);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertCount(1, StripeTestTransport::$mails);
    }

    public function testFailedReceiptIsRepairedWithoutAnotherJournalOrEnrolment(): void
    {
        [$order, $id, $pi] = $this->captured(true);
        $this->queueEnrolment();
        StripeTestTransport::$mailSucceeds = false;
        $this->assertSame('delivery_error', stripe_payment_apply_succeeded($pi));
        $this->assertSame(1, $this->lineCount($order));
        $this->assertNull(StripeFixtures::row('stripe_payments', $id)['confirmation_sent_at']);
        StripeTestTransport::$mailSucceeds = true;
        $this->assertSame(1, stripe_payment_repair()['repaired']);
        $this->assertCount(2, StripeTestTransport::$requests);
        $this->assertCount(2, StripeTestTransport::$mails); // failed send followed by one successful receipt
        $this->assertSame(0, stripe_payment_repair()['found']);
        $this->assertSame(1, $this->lineCount($order));
    }

    public function testMissingClearingAccountPreventsIntentAndRepairRemainsPossible(): void
    {
        $unpaidOrder = StripeFixtures::invoice();
        StripeTestEnvironment::query("UPDATE grupper SET box10='' WHERE art='DIV' AND kodenr=3");
        $this->assertSame('missing_clearing_account', stripe_payment_create(stripe_invoice_load($unpaidOrder, StripeFixtures::TODAY))['error']);
        $this->assertSame([], StripeTestTransport::$requests);
        [$order, $id, $pi] = $this->captured();
        $this->assertSame('database_error', stripe_payment_apply_succeeded($pi));
        $this->assertNull(StripeFixtures::row('stripe_payments', $id)['journal_line_id']);
        StripeTestEnvironment::query("UPDATE grupper SET box10='58050' WHERE art='DIV' AND kodenr=3");
        $this->assertSame(1, stripe_payment_repair()['repaired']);
    }

    public function testZeroOrAbsentClearingAccountAlsoPreventsIntentCreation(): void
    {
        $order = StripeFixtures::invoice();
        $inv = stripe_invoice_load($order, StripeFixtures::TODAY);
        StripeTestEnvironment::query("UPDATE grupper SET box10='0' WHERE art='DIV' AND kodenr=3");
        $this->assertSame('missing_clearing_account', stripe_payment_create($inv)['error']);
        StripeTestEnvironment::query("DELETE FROM grupper WHERE art='DIV' AND kodenr=3");
        $this->assertSame('missing_clearing_account', stripe_payment_create($inv)['error']);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testMetadataAmountAndCurrencyMismatchesBecomeUnmappedWithAlert(): void
    {
        foreach (['tenant', 'order', 'currency', 'amount', 'missing_amount'] as $index => $mismatch) {
            [$order, $id, $pi] = $this->captured(false, 'pi_mismatch_' . $index);
            if ($mismatch === 'tenant') $pi['metadata']['saldi_db'] = 'foreign_ledger';
            if ($mismatch === 'order') $pi['metadata']['saldi_ordre_id']++;
            if ($mismatch === 'currency') $pi['currency'] = 'eur';
            if ($mismatch === 'amount') $pi['amount_received']--;
            if ($mismatch === 'missing_amount') unset($pi['amount_received']);
            $this->assertSame('unmapped', stripe_payment_apply_succeeded($pi));
            $this->assertSame('unmapped', StripeFixtures::row('stripe_payments', $id)['status']);
            $this->assertSame(0, $this->lineCount($order));
            $this->assertSame('', StripeFixtures::row('ordrer', $order)['betalt']);
        }
        $this->assertCount(5, StripeTestTransport::$mails);
        foreach (StripeTestTransport::$mails as $mail) $this->assertSame('alert', $mail['kind']);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testMissingLocalRowIsBackfilledUsingExpectedInvoiceAmount(): void
    {
        [$order, $id, $pi] = $this->captured();
        StripeTestEnvironment::query('DELETE FROM stripe_payments WHERE id=$1', [$id]);
        $pi['amount'] = $pi['amount_received'] = 1;
        $this->assertSame('unmapped', stripe_payment_apply_succeeded($pi));
        $row = pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM stripe_payments WHERE ordre_id=$1', [$order]));
        $this->assertSame(34000, (int)$row['amount_ore']);
        $this->assertSame('unmapped', $row['status']);
        $this->assertSame(0, $this->lineCount($order));
    }

    public function testSubscriptionRenewalIntentWithoutInvoiceFieldIsIgnoredWithoutAlert(): void
    {
        $event = $this->event();
        $pi = ['id' => 'pi_subscription', 'metadata' => [], 'status' => 'succeeded'];
        $this->assertSame(['code' => 200, 'message' => 'not_saldi_intent'], stripe_invoice_webhook_dispatch($event, 'payment_intent.succeeded', $pi, 'evt_s1'));
        $this->assertSame('ignored', $this->eventRow($event)['status']);
        $this->assertSame([], StripeTestTransport::$mails);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testTaggedButUnknownInvoiceRaisesAlert(): void
    {
        $event = $this->event();
        $pi = ['id' => 'pi_unknown', 'metadata' => ['saldi_db' => $GLOBALS['db'], 'saldi_ordre_id' => 99999999]];
        $this->assertSame(['code' => 200, 'message' => 'unknown'], stripe_invoice_webhook_dispatch($event, 'payment_intent.succeeded', $pi, 'evt_s1'));
        $this->assertSame('unmapped', $this->eventRow($event)['status']);
        $this->assertCount(1, StripeTestTransport::$mails);
        $this->assertSame('alert', StripeTestTransport::$mails[0]['kind']);
    }

    public function testRefundAndDisputeFlagPaymentAndAlertWithoutReversalOrRecollection(): void
    {
        foreach (['charge.refunded' => 'refunded', 'charge.dispute.created' => 'disputed'] as $type => $status) {
            [$order, $id, $pi] = $this->captured(false, 'pi_' . $status);
            $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
            $journal = StripeFixtures::row('stripe_payments', $id)['journal_line_id'];
            StripeTestTransport::$mails = [];
            $this->assertSame(['code' => 200, 'message' => 'ok'], stripe_invoice_webhook_dispatch($this->event('evt_' . $status), $type, ['payment_intent' => $pi['id']], 'evt_' . $status));
            $payment = StripeFixtures::row('stripe_payments', $id);
            $this->assertSame($status, $payment['status']);
            $this->assertSame($journal, $payment['journal_line_id']);
            $this->assertSame('on', StripeFixtures::row('ordrer', $order)['betalt']);
            $this->assertSame(1, $this->lineCount($order));
            $this->assertCount(1, StripeTestTransport::$mails);
            $this->assertSame('alert', StripeTestTransport::$mails[0]['kind']);
            $this->assertStringContainsString($pi['id'], StripeTestTransport::$mails[0]['text']);
            $this->assertSame('duplicate', stripe_payment_apply_succeeded($pi, 'evt_late_success'));
            $this->assertSame($status, StripeFixtures::row('stripe_payments', $id)['status']);
        }
    }

    public function testRefundBeforeSuccessDeliveryReconcilesCaptureOnce(): void
    {
        [$order, $id, $pi] = $this->captured();
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_s1');
        $this->assertSame(['code' => 200, 'message' => 'ok'], stripe_invoice_webhook_dispatch($this->event(), 'charge.refunded', ['payment_intent' => 'pi_s1'], 'evt_s1'));
        $this->assertSame('refunded', StripeFixtures::row('stripe_payments', $id)['status']);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertSame('duplicate', stripe_payment_apply_succeeded($pi, 'evt_late_success'));
        $this->assertSame('refunded', StripeFixtures::row('stripe_payments', $id)['status']);
        $this->assertCount(2, StripeTestTransport::$mails); // original capture receipt and manual refund alert
        $this->assertSame('alert', StripeTestTransport::$mails[1]['kind']);
        $this->assertSame([], StripeTestTransport::$responses);
    }

    public function testDisputeCanResolveIntentThroughChargeLookup(): void
    {
        [$order, $id, $pi] = $this->captured();
        $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
        StripeTestTransport::queueBody(['payment_intent' => 'pi_s1'], 'GET', '/v1/charges/ch_s1');
        $this->assertSame(['code' => 200, 'message' => 'ok'], stripe_invoice_webhook_dispatch($this->event(), 'charge.dispute.created', ['charge' => 'ch_s1'], 'evt_s1'));
        $this->assertSame('disputed', StripeFixtures::row('stripe_payments', $id)['status']);
    }

    public function testSubscriptionRefundReturnsToLegacyHandler(): void
    {
        $event = $this->event();
        $this->assertNull(stripe_invoice_webhook_dispatch($event, 'charge.refunded', ['payment_intent' => 'pi_subscription'], 'evt_s1'));
        $this->assertSame('received', $this->eventRow($event)['status']);
        $this->assertSame([], StripeTestTransport::$mails);
    }

    public function testFailureAndCancellationCannotOverwriteSucceededPayment(): void
    {
        [$order, $id, $pi] = $this->captured();
        $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
        $pi['last_payment_error'] = ['code' => 'card_declined'];
        $this->assertSame('ignored', stripe_payment_apply_failed($pi, 'evt_late_failure'));
        $this->assertSame('applied', stripe_webhook_payment_intent('payment_intent.canceled', $pi, 'evt_late_cancel'));
        $this->assertSame('succeeded', StripeFixtures::row('stripe_payments', $id)['status']);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertCount(1, StripeTestTransport::$mails);
    }

    public function testFailureHandlerLosingConcurrentSuccessDoesNotOverwriteIt(): void
    {
        [$order, $id, $pi] = $this->captured();
        $failed = $pi;
        $failed['latest_charge'] = 'ch_s1';
        $failed['last_payment_error'] = ['code' => 'card_declined'];
        StripeTestTransport::queueBody(['card' => ['brand' => 'visa', 'last4' => '4242']], 'GET', '/v1/payment_methods/pm_s1');
        StripeTestTransport::$beforeResponse = function () use ($pi): void {
            StripeTestTransport::$beforeResponse = null;
            $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
        };
        $this->assertSame('duplicate', stripe_payment_apply_failed($failed));
        $this->assertSame('succeeded', StripeFixtures::row('stripe_payments', $id)['status']);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertCount(1, StripeTestTransport::$mails);
    }

    public function testVoucherNumberContinuesFiscalYearAndSkipsOwnedAndPostedJournals(): void
    {
        $posted = StripeFixtures::insert('kladdeliste', ['kladdenote' => stripe_txt('journal_name'), 'kladdedate' => '2026-01-01', 'bogfort' => 'V']);
        $owned = StripeFixtures::insert('kladdeliste', ['kladdenote' => stripe_txt('journal_name'), 'kladdedate' => '2026-01-02', 'bogfort' => '-', 'hvem' => 'bookkeeper', 'tidspkt' => 'busy']);
        StripeFixtures::insert('kassekladde', ['bilag' => 51, 'transdate' => '2026-01-01', 'kladde_id' => $posted]);
        StripeFixtures::insert('transaktioner', ['bilag' => 80, 'transdate' => '2026-01-01']);
        // Journal lines use serial ids; journal headers intentionally leave their sequence behind.
        StripeTestEnvironment::query("SELECT setval('pg_temp.s1_kassekladde_seq', (SELECT max(id) FROM kassekladde))");
        [$order, $id, $pi] = $this->captured();
        StripeTestEnvironment::query("UPDATE stripe_payments SET status='succeeded', succeeded_at='2026-10-22 10:00:00', pm_brand='visa', pm_last4='4242' WHERE id=$1", [$id]);
        $this->assertSame(1, stripe_payment_repair()['repaired']);
        $line = pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM kassekladde WHERE ordre_id=$1', [$order]));
        $this->assertSame(81, (int)$line['bilag']);
        $this->assertNotContains((int)$line['kladde_id'], [$posted, $owned]);
        $this->assertSame('340.000', $line['amount']);
        $this->assertSame('58050', $line['debet']);
    }

    public function testNewFiscalYearDoesNotReusePreviousYearsStripeJournal(): void
    {
        StripeTestEnvironment::query("UPDATE grupper SET box4='2026' WHERE art='RA'");
        StripeFixtures::insert('grupper', ['art' => 'RA', 'kodenr' => 6, 'box1' => '1', 'box2' => '2027', 'box3' => '12', 'box4' => '2027']);
        $old = StripeFixtures::insert('kladdeliste', ['kladdenote' => stripe_txt('journal_name'), 'kladdedate' => '2026-01-01', 'bogfort' => '-']);
        StripeFixtures::insert('kassekladde', ['bilag' => 999, 'transdate' => '2026-01-01', 'kladde_id' => $old]);
        StripeTestEnvironment::query("SELECT setval('pg_temp.s1_kassekladde_seq', 1)");
        [$order, $id, $pi] = $this->captured();
        StripeTestEnvironment::query("UPDATE stripe_payments SET status='succeeded', succeeded_at='2027-01-02 10:00:00' WHERE id=$1", [$id]);
        $this->assertSame(1, stripe_payment_repair()['repaired']);
        $line = pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM kassekladde WHERE ordre_id=$1', [$order]));
        $this->assertSame(1, (int)$line['bilag']);
        $this->assertNotSame($old, (int)$line['kladde_id']);
    }

    public function testRepairStillBooksAndReceiptsWhenCollectionSwitchesAreOff(): void
    {
        [$order, $id, $pi] = $this->captured(true);
        StripeTestEnvironment::query("UPDATE stripe_payments SET status='succeeded', succeeded_at='2026-10-22 10:00:00' WHERE id=$1", [$id]);
        StripeTestEnvironment::setting('stripe', 'invoice_payments', 'off');
        StripeTestEnvironment::setting('stripe', 'enabled', 'off');
        $this->assertSame(1, stripe_payment_repair()['repaired']);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertCount(1, StripeTestTransport::$mails);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testRepairDryRunDoesNotBookOrSend(): void
    {
        [$order, $id, $pi] = $this->captured();
        StripeTestEnvironment::query("UPDATE stripe_payments SET status='succeeded', succeeded_at='2026-10-22 10:00:00' WHERE id=$1", [$id]);
        $this->assertSame(['found' => 1, 'repaired' => 0, 'failed' => 0], stripe_payment_repair(true));
        $this->assertSame(0, $this->lineCount($order));
        $this->assertSame([], StripeTestTransport::$mails);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testSecondCapturedIntentIsStoredAsDuplicatePaidWithBothIdsAndNoSecondJournal(): void
    {
        [$order, $firstId, $first] = $this->captured();
        $second = $first;
        $second['id'] = 'pi_s2_second';
        $secondId = stripe_payment_record(stripe_invoice_load($order), $second, 'page', true, 2);
        $this->assertSame('applied', stripe_payment_apply_succeeded($first, 'evt_first'));
        $firstLine = StripeFixtures::row('stripe_payments', $firstId)['journal_line_id'];
        $this->assertSame(['code' => 200, 'message' => 'duplicate_paid'], stripe_invoice_webhook_dispatch($this->event('evt_second'), 'payment_intent.succeeded', $second, 'evt_second'));
        $extra = StripeFixtures::row('stripe_payments', $secondId);
        $this->assertSame('duplicate_paid', $extra['status']);
        $this->assertNotNull($extra['succeeded_at']);
        $this->assertNull($extra['journal_line_id']);
        $this->assertNotNull($extra['confirmation_sent_at']); // acknowledges the bookkeeper alert
        $this->assertSame($firstLine, StripeFixtures::row('stripe_payments', $firstId)['journal_line_id']);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertCount(2, StripeTestTransport::$mails); // one customer receipt and one alert
        $alert = StripeTestTransport::$mails[1];
        $this->assertSame('alert', $alert['kind']);
        $this->assertStringContainsString($first['id'], $alert['text']);
        $this->assertStringContainsString($second['id'], $alert['text']);
        $this->assertSame('duplicate_paid', stripe_payment_apply_succeeded($second, 'evt_second_replay'));
        $this->assertSame($extra, StripeFixtures::row('stripe_payments', $secondId));
        $this->assertSame('ignored', stripe_payment_apply_failed($second, 'evt_late_failure'));
        stripe_webhook_payment_intent('payment_intent.canceled', $second, 'evt_late_cancel');
        $this->assertSame('duplicate_paid', StripeFixtures::row('stripe_payments', $secondId)['status']);
        $this->assertSame(0, stripe_payment_repair()['found']);
        $this->assertCount(2, StripeTestTransport::$mails);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testFailedDuplicateAlertRemainsRetryableWithoutBookingOrEnrolment(): void
    {
        [$order, $firstId, $pi] = $this->captured();
        $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
        $pi['id'] = 'pi_extra_alert_retry';
        $extra = stripe_payment_record(stripe_invoice_load($order), $pi, 'page', true, 2);
        StripeTestTransport::$mailSucceeds = false;
        $event = $this->event('evt_extra_alert');
        $this->assertSame(['code' => 500, 'message' => 'delivery_error'], stripe_invoice_webhook_dispatch($event, 'payment_intent.succeeded', $pi, 'evt_extra_alert'));
        $this->assertSame('received', $this->eventRow($event)['status']);
        $this->assertSame('duplicate_paid', StripeFixtures::row('stripe_payments', $extra)['status']);
        $this->assertNull(StripeFixtures::row('stripe_payments', $extra)['confirmation_sent_at']);
        $capture = StripeFixtures::row('stripe_payments', $extra);
        $this->assertSame(['code' => 500, 'message' => 'delivery_error'], stripe_invoice_webhook_dispatch($event, 'payment_intent.succeeded', $pi, 'evt_extra_alert'));
        $this->assertSame('received', $this->eventRow($event)['status']);
        $this->assertNull($this->eventRow($event)['processed_at']);
        $this->assertSame($capture, StripeFixtures::row('stripe_payments', $extra));
        StripeTestTransport::$mailSucceeds = true;
        $this->assertSame(['code' => 200, 'message' => 'duplicate_paid'], stripe_invoice_webhook_dispatch($event, 'payment_intent.succeeded', $pi, 'evt_extra_alert'));
        $this->assertSame('processed', $this->eventRow($event)['status']);
        $this->assertNotNull(StripeFixtures::row('stripe_payments', $extra)['confirmation_sent_at']);
        $this->assertSame($capture['succeeded_at'], StripeFixtures::row('stripe_payments', $extra)['succeeded_at']);
        $this->assertNull(StripeFixtures::row('stripe_payments', $extra)['journal_line_id']);
        $this->assertCount(4, StripeTestTransport::$mails); // original receipt, two failed alerts, successful alert
        foreach (array_slice(StripeTestTransport::$mails, 1) as $mail) {
            $this->assertSame('alert', $mail['kind']);
            $this->assertStringContainsString('pi_s1', $mail['text']);
            $this->assertStringContainsString($pi['id'], $mail['text']);
        }
        $this->assertSame(['code' => 200, 'message' => 'duplicate_paid'], stripe_invoice_webhook_dispatch($event, 'payment_intent.succeeded', $pi, 'evt_extra_alert'));
        $this->assertCount(4, StripeTestTransport::$mails);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testSecondCaptureIsDetectedAfterFirstWasRefunded(): void
    {
        [$order, $firstId, $first] = $this->captured();
        $this->assertSame('applied', stripe_payment_apply_succeeded($first));
        StripeTestEnvironment::query("UPDATE stripe_payments SET status='refunded' WHERE id=$1", [$firstId]);
        $second = $first;
        $second['id'] = 'pi_after_refund';
        $id = stripe_payment_record(stripe_invoice_load($order), $second, 'page', false, 2);
        $this->assertSame('duplicate_paid', stripe_payment_apply_succeeded($second));
        $this->assertSame('duplicate_paid', StripeFixtures::row('stripe_payments', $id)['status']);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertStringContainsString($first['id'], StripeTestTransport::$mails[1]['text']);
    }

    public function testReconcileBeforeCreateFindsCaptureAndBooksItInsteadOfChargingAgain(): void
    {
        [$order, $id, $pi] = $this->captured();
        StripeTestEnvironment::query("UPDATE stripe_payments SET created_at=CURRENT_TIMESTAMP - interval '2 days' WHERE id=$1", [$id]);
        $stale = stripe_invoice_load($order);
        StripeTestTransport::queueBody($pi, 'GET', '/v1/payment_intents/pi_s1');
        $this->assertSame('already_paid', stripe_payment_create($stale)['error']);
        $this->assertSame('succeeded', StripeFixtures::row('stripe_payments', $id)['status']);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertSame('on', StripeFixtures::row('ordrer', $order)['betalt']);
        $this->assertCount(1, StripeTestTransport::$requests);
        $this->assertCount(1, StripeTestTransport::$mails);
        $this->assertSame('paid', stripe_invoice_load($order)['state']);
    }

    public function testReconciliationBooksBothDeliveredCapturesAsOneJournalAndDuplicateAlert(): void
    {
        [$order, $firstId, $first] = $this->captured();
        $second = $first;
        $second['id'] = 'pi_s2_old_capture';
        $secondId = stripe_payment_record(stripe_invoice_load($order), $second, 'page', false, 2);
        StripeTestTransport::queueBody($first, 'GET', '/v1/payment_intents/pi_s1');
        StripeTestTransport::queueBody($second, 'GET', '/v1/payment_intents/pi_s2_old_capture');
        $this->assertSame('already_paid', stripe_payment_create(stripe_invoice_load($order))['error']);
        $this->assertSame('succeeded', StripeFixtures::row('stripe_payments', $firstId)['status']);
        $this->assertSame('duplicate_paid', StripeFixtures::row('stripe_payments', $secondId)['status']);
        $this->assertCount(2, StripeTestTransport::$requests);
        $this->assertCount(2, StripeTestTransport::$mails);
        $this->assertSame(1, $this->lineCount($order));
    }

    public function testDunningExcludesCardPaidInvoiceWhileItsJournalAndOpenpostAreUnposted(): void
    {
        require_once __DIR__ . '/../../includes/stripeIncludes/stripeDunning.php';
        [$paidOrder, $id, $pi] = $this->captured();
        $ordinary = StripeFixtures::invoice();
        $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
        $open = pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM openpost WHERE konto_id=$1 AND faktnr=$2', [$pi['metadata']['saldi_konto_id'] ?? StripeFixtures::row('ordrer', $paidOrder)['konto_id'], StripeFixtures::row('ordrer', $paidOrder)['fakturanr']]));
        $this->assertSame('0', $open['udlignet']);
        $line = StripeFixtures::row('stripe_payments', $id)['journal_line_id'];
        $journal = pg_fetch_assoc(StripeTestEnvironment::query('SELECT l.bogfort FROM kladdeliste l JOIN kassekladde k ON k.kladde_id=l.id WHERE k.id=$1', [$line]));
        $this->assertSame('-', $journal['bogfort']);
        $predicate = stripe_dunning_openpost_sql();
        $selected = pg_fetch_all(StripeTestEnvironment::query("SELECT openpost.faktnr FROM openpost, adresser WHERE openpost.udlignet='0'
            AND openpost.forfaldsdate<='2026-10-22' AND openpost.amount>0 AND adresser.id=openpost.konto_id AND adresser.art='D' AND $predicate"));
        $this->assertSame([StripeFixtures::row('ordrer', $ordinary)['fakturanr']], array_column($selected, 'faktnr'));
        // A prepared R1 reminder must also be held rather than escalated with another fee.
        $reminder = StripeFixtures::insert('ordrer', ['art' => 'R1', 'status' => 2, 'konto_id' => $open['konto_id'], 'ordredate' => '2026-01-01']);
        StripeFixtures::insert('ordrelinjer', ['ordre_id' => $reminder, 'enhed' => (string)$open['id']]);
        $reminderPredicate = stripe_dunning_reminder_sql();
        $this->assertFalse(pg_fetch_assoc(StripeTestEnvironment::query("SELECT id FROM ordrer WHERE id=$1 AND $reminderPredicate", [$reminder])));
    }

    public function testDunningHoldsPendingProcessingAndActionButReleasesFailedAndCanceled(): void
    {
        require_once __DIR__ . '/../../includes/stripeIncludes/stripeDunning.php';
        [$order, $id, $pi] = $this->captured();
        foreach (['pending' => false, 'processing' => false, 'requires_action' => false, 'failed' => true, 'canceled' => true] as $status => $expected) {
            StripeTestEnvironment::query('UPDATE stripe_payments SET status=$1 WHERE id=$2', [$status, $id]);
            $predicate = stripe_dunning_openpost_sql('op');
            $selected = pg_fetch_assoc(StripeTestEnvironment::query("SELECT op.id FROM openpost op WHERE op.konto_id=$1 AND $predicate", [StripeFixtures::row('ordrer', $order)['konto_id']]));
            $this->assertSame($expected, (bool)$selected, $status);
        }
    }

    public function testDunningReleasesAgedPageAttemptsButProtectsFreshAndSavedCardAuthentication(): void
    {
        require_once __DIR__ . '/../../includes/stripeIncludes/stripeDunning.php';
        [$order, $id, $pi] = $this->captured();
        $konto = StripeFixtures::row('ordrer', $order)['konto_id'];
        $open = pg_fetch_assoc(StripeTestEnvironment::query('SELECT id FROM openpost WHERE konto_id=$1', [$konto]));
        $reminder = StripeFixtures::insert('ordrer', ['art' => 'R1', 'status' => 2, 'konto_id' => $konto]);
        StripeFixtures::insert('ordrelinjer', ['ordre_id' => $reminder, 'enhed' => (string)$open['id']]);
        foreach ([
            ['pending', 'page', '4 days', true],
            ['requires_action', 'page', '4 days', true],
            ['pending', 'page', '1 minute', false],
            ['requires_action', 'page', '1 minute', false],
            ['requires_action', 'auto', '4 days', false],
            ['requires_action', 'backup', '4 days', false],
            ['requires_action', 'manual', '4 days', false],
            ['processing', 'page', '4 days', false],
        ] as [$status, $source, $age, $expected]) {
            StripeTestEnvironment::query("UPDATE stripe_payments SET status=$1, source=$2, created_at=CURRENT_TIMESTAMP - $3::interval WHERE id=$4", [$status, $source, $age, $id]);
            $predicate = stripe_dunning_openpost_sql('op');
            $selected = pg_fetch_assoc(StripeTestEnvironment::query("SELECT op.id FROM openpost op WHERE op.id=$1 AND $predicate", [$open['id']]));
            $this->assertSame($expected, (bool)$selected, "$status/$source/$age");
            $predicate = stripe_dunning_reminder_sql('o');
            $selected = pg_fetch_assoc(StripeTestEnvironment::query("SELECT o.id FROM ordrer o WHERE o.id=$1 AND $predicate", [$reminder]));
            $this->assertSame($expected, (bool)$selected, "reminder: $status/$source/$age");
        }
    }


    public function testRepairRetriesMissingDuplicateAlertWithoutAnotherReceiptOrJournal(): void
    {
        [$order, $id, $first] = $this->captured();
        $this->assertSame('applied', stripe_payment_apply_succeeded($first));
        $second = $first;
        $second['id'] = 'pi_duplicate_repair';
        $extra = stripe_payment_record(stripe_invoice_load($order), $second, 'page', false, 2);
        StripeTestTransport::$mailSucceeds = false;
        $this->assertSame('delivery_error', stripe_payment_apply_succeeded($second));
        StripeTestTransport::$mailSucceeds = true;
        $this->assertSame(['found' => 1, 'repaired' => 1, 'failed' => 0], stripe_payment_repair());
        $this->assertSame(0, stripe_payment_repair()['found']);
        $this->assertSame('duplicate_paid', StripeFixtures::row('stripe_payments', $extra)['status']);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertCount(3, StripeTestTransport::$mails); // original receipt, failed alert, successful alert
        $this->assertSame('alert', StripeTestTransport::$mails[2]['kind']);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testRefundOfDuplicateCaptureRemainsManualWithoutSecondBooking(): void
    {
        [$order, $id, $first] = $this->captured();
        $this->assertSame('applied', stripe_payment_apply_succeeded($first));
        $second = $first;
        $second['id'] = 'pi_extra_refunded';
        $extra = stripe_payment_record(stripe_invoice_load($order), $second, 'page', false, 2);
        $this->assertSame('duplicate_paid', stripe_payment_apply_succeeded($second));
        $this->assertSame(['code' => 200, 'message' => 'ok'], stripe_invoice_webhook_dispatch($this->event('evt_extra_refund'), 'charge.refunded', ['payment_intent' => $second['id']], 'evt_extra_refund'));
        $payment = StripeFixtures::row('stripe_payments', $extra);
        $this->assertSame('duplicate_paid', $payment['status']);
        $this->assertSame('duplicate_paid_refunded', $payment['last_error']);
        $this->assertSame('duplicate_paid', stripe_payment_apply_succeeded($second, 'evt_late_extra_capture'));
        $this->assertSame('duplicate_paid_refunded', StripeFixtures::row('stripe_payments', $extra)['last_error']);
        $this->assertNull($payment['journal_line_id']);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertSame([], StripeTestTransport::$requests);
    }

}
