<?php
// 20261022 CDX/LH Real PostgreSQL claim, booking rollback, repair and invoice webhook regressions.
// 20261022 CDX/LH Duplicate-capture accounting/alerts, pre-create reconciliation and dunning regressions.
// 20261022 CDX/LH Verify repeated duplicate-alert delivery failures stay retryable and completed replays preserve capture fields.
// 20261022 CDX/LH Isolate durable collection tables across real booking COMMITs and use the shared fixture clock.
// 20261022 CDX/LH Exercise repair-only CLI work, project dimensions, one-off consent and enrolment failure after capture.
// 20261022 CDX/LH Verify three-day reminder protection for page attempts and permanent saved-card authentication protection.
// 20261022 CDX/LH Regress journal creation/reuse against the real tenant table with its sequence behind MAX(id).
// 20261109 CL/ASR Link reminders: "Bogfør" (includes/openpost.php bogfor_rykker) posts nothing for a fee carried to the next invoice and keeps the classic posting, now with projekt '' on the fee's open item.
// 20261109 CL/ASR Review fixes: the same for the "Bogfør" button's copy (includes/rapportfunc.php, in its own process), and the "Ryk alle" copy posts nothing outside the active fiscal year.
// 20261126 CL/ASR Stripe port (#1098): the "Ryk alle" copy (includes/openpost.php) runs in its own process too.
//                 includes/reportFunc/showOpenPosts.php also declares vis_aabne_poster(), so a run with the test database that loaded it first (OpenpostAgingTest) stopped with "Cannot redeclare".
// 20261126 ASR Review I2: booking a reminder a second time (a stale overview or a second click) posts nothing more, in both bogfor_rykker() copies.
// 20261126 ASR Review I3: neither copy books an automatic reminder that was not sent, and both say why.
// 20261126 CL/ASR Review round 1 (I20): a database or delivery error leaves the event 'failed' with its reason, also for a row this delivery inserted ('received'), so Stripe's redelivery is claimed at once.
// 20261126 ASR Payment review (GPT-P-1): a receipt retry after Afmeld, or after a later payment's card was applied, sends only the receipt; the captures before card_state are classified once.
// 20261126 ASR Payment review (GPT-P-2): a capture after the invoice was settled, credited or changed another way is booked without the invoice number as the customer's credit, marked overpaid and alerted, with no receipt or saved card.
// 20261126 ASR Payment review (GPT-P-5): the invoice dispatcher finishes or releases an event row only while it carries the delivery's claim token.
// 20261126 ASR Payment review (GPT-P-4): a refund or dispute alert that is not sent stays retryable (stripe_adjustment_alerts): the redelivery sends it without applying the event again, and so does the repair pass once Stripe has stopped.
// 20261126 ASR Review GPT-R-1: both bogfor_rykker() copies post a reminder fee with the item and debtor groups of the posting year, not with an earlier year's rows.
// 20261126 ASR Payment review (GPT-G-1): an update that stopped after adding card_state and before classifying the earlier captures is completed by the next run, also by a receipt retry, and a payment made in between is not classified as an earlier one.
// 20261126 ASR Payment review (GPT-G-3): the journal line of an overpaid capture carries the reference Stripe-overbetaling-<payment id>, not an empty invoice number.
// 20261126 ASR Payment review (GPT-G-6): a refund whose stripe_adjustment_alerts row cannot be written is not applied either, and a refund applied after the payment's dispute leaves 'disputed'.
// 20261126 ASR Review GPT-F-1: in both copies a booking refused for a stock-tracked line keeps nothing (status, accounts, open item, postings), and books once the item is corrected.
//              A reminder left at status 3 without anything posted is booked; one at status 3 with its fee open item is not booked again.
// 20261126 ASR Review GPT-G-4: in both copies a reminder whose item or debtor group has no row in the posting year is not posted with another year's rows.
//              Nothing is written, the message names the groups and the year, and the reminder is booked with that year's accounts once the groups are set up.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once __DIR__ . '/support/bootstrap.php';
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
        foreach (['stripe_charge_errors', 'stripe_unenrol_mail', 'stripe_adjustment_alerts'] as $table) {
            StripeTestEnvironment::query("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING ALL)");
            $this->tables[] = $table;
        }
        StripeTestEnvironment::query("CREATE TEMP TABLE stripe_events (
            id serial primary key, event_id text, status text, processed_at timestamp,
            saldi_order_id integer, invoice_number text, error text)");
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

    public function testJournalInsertFailureStaysRetryableAndRepairCompletesOnceWithoutCharging(): void
    {
        [$order, $id, $pi] = $this->captured(true);
        $event = $this->event('evt_s1', 'retrying');
        $this->skipWrite('kassekladde', 'INSERT');
        $this->assertSame(['code' => 500, 'message' => 'database_error'], stripe_invoice_webhook_dispatch($event, 'payment_intent.succeeded', $pi, 'evt_s1'));
        $this->assertSame(['failed', 'database_error'], [$this->eventRow($event)['status'], $this->eventRow($event)['error']]);
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

    public function testReceiptRetryAfterAfmeldSendsOnlyTheReceiptAndKeepsTheCustomerUnenrolled(): void
    {
        [$order, $id, $pi] = $this->captured(true);
        $konto = (int)StripeFixtures::row('ordrer', $order)['konto_id'];
        $this->queueEnrolment();
        StripeTestTransport::$mailSucceeds = false;
        $this->assertSame('delivery_error', stripe_payment_apply_succeeded($pi));
        $this->assertSame('pm_s1', StripeFixtures::row('adresser', $konto)['stripe_pm_id']);
        StripeTestTransport::$mailSucceeds = true;
        $this->assertTrue(stripe_unenrol_debtor($konto));
        $mails = count(StripeTestTransport::$mails);
        $this->assertSame(1, stripe_payment_repair()['repaired']);
        $debtor = StripeFixtures::row('adresser', $konto);
        $this->assertSame('', $debtor['stripe'], 'Afmeld stays in force');
        $this->assertNull($debtor['stripe_pm_id']);
        $this->assertCount(2, StripeTestTransport::$requests, 'only the enrolment made with the payment');
        $this->assertCount($mails + 1, StripeTestTransport::$mails, 'the receipt');
        $this->assertNotEmpty(StripeFixtures::row('stripe_payments', $id)['confirmation_sent_at']);
        $this->assertSame(0, stripe_payment_repair()['found']);
        $this->assertSame('applied', StripeFixtures::row('stripe_payments', $id)['card_state']);
    }

    public function testACardNotYetAppliedIsSupersededByAfmeldOrByALaterPaymentsCard(): void
    {
        // Afmeld while the card is still to be applied (Stripe was unreachable): the retry applies nothing.
        [$order, $id, $pi] = $this->captured(true);
        $konto = (int)StripeFixtures::row('ordrer', $order)['konto_id'];
        StripeTestTransport::queue(['ok' => false, 'status' => 503, 'body' => null, 'error' => 'unavailable'], 'POST', '/v1/customers/cus_s1');
        $this->assertSame('delivery_error', stripe_payment_apply_succeeded($pi));
        $this->assertTrue(stripe_unenrol_debtor($konto));
        StripeTestTransport::$responses = [];
        StripeTestTransport::queueBody(['id' => 'cus_s1'], 'POST', '/v1/customers/cus_s1'); // what a retry that applied the card would use
        StripeTestTransport::queueBody(['id' => 'pm_s1'], 'POST', '/v1/payment_methods/pm_s1');
        $this->assertSame(1, stripe_payment_repair()['repaired']);
        $this->assertCount(1, StripeTestTransport::$requests, 'no enrolment after Afmeld');
        $this->assertSame('', StripeFixtures::row('adresser', $konto)['stripe']);
        $this->assertCount(1, StripeTestTransport::$mails, 'the receipt');
        $this->assertSame('superseded', StripeFixtures::row('stripe_payments', $id)['card_state']);

        // A later payment's card was applied first: the earlier payment's retry does not put its older card back.
        StripeTestTransport::reset();
        [$first, $firstId, $firstPi] = $this->captured(true, 'pi_old_card');
        $konto = (int)StripeFixtures::row('ordrer', $first)['konto_id'];
        StripeTestTransport::queue(['ok' => false, 'status' => 503, 'body' => null, 'error' => 'unavailable'], 'POST', '/v1/customers/cus_s1');
        $this->assertSame('delivery_error', stripe_payment_apply_succeeded($firstPi));
        $second = StripeFixtures::invoice($konto, ['nextfakt' => '2026-02-08']);
        $secondPi = $firstPi;
        $secondPi['id'] = 'pi_new_card';
        $secondPi['payment_method'] = 'pm_new';
        $secondPi['metadata']['saldi_ordre_id'] = $second;
        $secondPi['metadata']['saldi_fakturanr'] = StripeFixtures::row('ordrer', $second)['fakturanr'];
        $secondId = stripe_payment_record(stripe_invoice_load($second), $secondPi, 'page', true, 1);
        StripeTestTransport::queueBody(['id' => 'cus_s1'], 'POST', '/v1/customers/cus_s1');
        StripeTestTransport::queueBody(['id' => 'pm_new'], 'POST', '/v1/payment_methods/pm_new');
        $this->assertSame('applied', stripe_payment_apply_succeeded($secondPi));
        $this->assertSame('pm_new', StripeFixtures::row('adresser', $konto)['stripe_pm_id']);
        $requests = count(StripeTestTransport::$requests);
        StripeTestTransport::queueBody(['id' => 'cus_s1'], 'POST', '/v1/customers/cus_s1'); // what a retry that applied the old card would use
        StripeTestTransport::queueBody(['id' => 'pm_s1'], 'POST', '/v1/payment_methods/pm_s1');
        $this->assertSame(1, stripe_payment_repair()['repaired']);
        $this->assertCount($requests, StripeTestTransport::$requests, 'no enrolment for the older card');
        $this->assertSame('pm_new', StripeFixtures::row('adresser', $konto)['stripe_pm_id']);
        $this->assertNotEmpty(StripeFixtures::row('stripe_payments', $firstId)['confirmation_sent_at']);
        $this->assertSame('applied', StripeFixtures::row('stripe_payments', $secondId)['card_state']);
        $this->assertSame('superseded', StripeFixtures::row('stripe_payments', $firstId)['card_state']);
    }

    public function testCapturesMadeBeforeCardStateAreClassifiedOnce(): void
    {
        $konto = StripeFixtures::debtor(['stripe' => 'on', 'stripe_pm_id' => 'pm_now', 'stripe_customer_id' => 'cus_s1', 'stripe_date' => StripeFixtures::INVOICE_DATE]);
        $rows = [];
        foreach ([
            'current card, receipt sent' => ['succeeded', true, 'pm_now', true, 'applied'],
            'current card, receipt pending' => ['succeeded', true, 'pm_now', false, 'applied'],
            'other card, receipt sent' => ['succeeded', true, 'pm_old', true, 'skipped'],
            'other card, receipt pending' => ['succeeded', true, 'pm_old', false, 'superseded'],
            'not captured' => ['pending', true, 'pm_old', false, ''],
            'card not saved' => ['succeeded', false, 'pm_old', false, ''],
        ] as $label => [$status, $save, $pm, $sent, $expected]) {
            $order = StripeFixtures::invoice($konto);
            $rows[$label] = [StripeFixtures::insert('stripe_payments', [
                'ordre_id' => $order, 'fakturanr' => StripeFixtures::row('ordrer', $order)['fakturanr'], 'payment_intent_id' => 'pi_bf_' . $order,
                'amount_ore' => 34000, 'status' => $status, 'save_card' => $save, 'pm_id' => $pm, 'card_state' => null,
                'confirmation_sent_at' => $sent ? '2026-01-09 10:00:00' : null,
            ]), $expected];
        }
        // A payment made after the column is written with '' (still to be applied, stripe_payment_record()) and is not one of them.
        $order = StripeFixtures::invoice($konto);
        $later = StripeFixtures::insert('stripe_payments', [
            'ordre_id' => $order, 'fakturanr' => StripeFixtures::row('ordrer', $order)['fakturanr'], 'payment_intent_id' => 'pi_bf_later',
            'amount_ore' => 34000, 'status' => 'succeeded', 'save_card' => true, 'pm_id' => 'pm_old', 'card_state' => '',
        ]);
        stripe_payment_card_state_backfill();
        foreach ($rows as $label => [$id, $expected]) {
            $this->assertSame($expected, StripeFixtures::row('stripe_payments', $id)['card_state'], $label);
        }
        $this->assertSame('', StripeFixtures::row('stripe_payments', $later)['card_state'], 'a later payment is not classified as an earlier one');
        // Classified once: a second run changes nothing, also after the customer's card changed.
        StripeTestEnvironment::query("UPDATE adresser SET stripe_pm_id = 'pm_old' WHERE id = $1", [$konto]);
        stripe_payment_card_state_backfill();
        foreach ($rows as $label => [$id, $expected]) {
            $this->assertSame($expected, StripeFixtures::row('stripe_payments', $id)['card_state'], $label . ', second run');
        }
        $this->assertSame('', StripeFixtures::row('stripe_payments', $later)['card_state']);
    }

    public function testAnUpdateThatStoppedBeforeClassifyingTheEarlierCapturesIsCompletedByTheNextRun(): void
    {
        // Before card_state: a captured payment saved its card, its receipt failed, and the customer then used Afmeld.
        [$order, $id, $pi] = $this->captured(true);
        $konto = (int)StripeFixtures::row('ordrer', $order)['konto_id'];
        $this->queueEnrolment();
        StripeTestTransport::$mailSucceeds = false;
        $this->assertSame('delivery_error', stripe_payment_apply_succeeded($pi));
        StripeTestTransport::$mailSucceeds = true;
        $this->assertTrue(stripe_unenrol_debtor($konto));
        // And one still under way.
        $inFlight = stripe_payment_record(stripe_invoice_load(StripeFixtures::invoice($konto)), ['id' => 'pi_in_flight', 'customer' => 'cus_s1', 'status' => 'processing'], 'page', true, 1);
        $this->assertSame('processing', StripeFixtures::row('stripe_payments', $inFlight)['status']);
        StripeTestEnvironment::query('ALTER TABLE pg_temp.stripe_payments DROP COLUMN card_state');
        $requests = count(StripeTestTransport::$requests);
        $mails = count(StripeTestTransport::$mails);

        // stripe_charge_schema_ensure() reads information_schema for the current schema: here the session's own tables. The
        // tables it creates there are dropped with the others.
        $this->tables[] = 'stripe_manual_fees';
        $this->tables[] = 'stripe_reminders';
        StripeTestEnvironment::query('SET search_path = pg_temp, public');
        try {
            // The first run adds the column and stops before the earlier captures are classified.
            $this->skipWrite('stripe_payments', 'UPDATE');
            stripe_charge_schema_ensure();
            StripeTestEnvironment::query('DROP TRIGGER s1_skip ON stripe_payments');
            $this->assertNull(StripeFixtures::row('stripe_payments', $id)['card_state'], 'precondition: the column is there, the capture not classified');
            $this->assertNull(StripeFixtures::row('stripe_payments', $inFlight)['card_state']);
            // A payment made before the next run is written as still to be applied.
            [, $later] = $this->captured(true, 'pi_after_column');
            $this->assertSame('', StripeFixtures::row('stripe_payments', $later)['card_state'], 'stripe_payment_record()');

            // The next run classifies the earlier ones, and only those.
            stripe_charge_schema_ensure();
        } finally {
            StripeTestEnvironment::query('RESET search_path');
        }
        $this->assertSame('superseded', StripeFixtures::row('stripe_payments', $id)['card_state'], 'Afmeld came after it');
        $this->assertSame('', StripeFixtures::row('stripe_payments', $inFlight)['card_state'], 'applies its card when it succeeds');
        $this->assertSame('', StripeFixtures::row('stripe_payments', $later)['card_state'], 'not classified as an earlier one');
        // The receipt retry sends only the receipt: the card the customer removed is not put back.
        $this->assertSame(1, stripe_payment_repair()['repaired']);
        $this->assertCount($requests, StripeTestTransport::$requests, 'no enrolment');
        $this->assertSame('', StripeFixtures::row('adresser', $konto)['stripe']);
        $this->assertNull(StripeFixtures::row('adresser', $konto)['stripe_pm_id']);
        $this->assertCount($mails + 1, StripeTestTransport::$mails, 'the receipt');
        $this->assertNotEmpty(StripeFixtures::row('stripe_payments', $id)['confirmation_sent_at']);
    }

    public function testAReceiptRetryClassifiesACaptureFromBeforeCardStateBeforeDecidingItsCard(): void
    {
        // The column was added and the run stopped before the classification; no update has run since.
        [$order, $id, $pi] = $this->captured(true);
        $konto = (int)StripeFixtures::row('ordrer', $order)['konto_id'];
        $this->queueEnrolment();
        StripeTestTransport::$mailSucceeds = false;
        $this->assertSame('delivery_error', stripe_payment_apply_succeeded($pi));
        StripeTestTransport::$mailSucceeds = true;
        $this->assertTrue(stripe_unenrol_debtor($konto));
        StripeTestEnvironment::query('UPDATE stripe_payments SET card_state = NULL WHERE id = $1', [$id]);
        StripeTestTransport::queueBody(['id' => 'cus_s1'], 'POST', '/v1/customers/cus_s1'); // what a retry that applied the card would use
        StripeTestTransport::queueBody(['id' => 'pm_s1'], 'POST', '/v1/payment_methods/pm_s1');
        $this->assertSame(1, stripe_payment_repair()['repaired']);
        $this->assertCount(2, StripeTestTransport::$requests, 'only the enrolment made with the payment');
        $this->assertSame('', StripeFixtures::row('adresser', $konto)['stripe'], 'Afmeld stays in force');
        $this->assertSame('superseded', StripeFixtures::row('stripe_payments', $id)['card_state']);
        $this->assertNotEmpty(StripeFixtures::row('stripe_payments', $id)['confirmation_sent_at']);
    }

    public function testACaptureTheInvoiceNoLongerOwesIsBookedAsTheCustomersCreditAndAlerted(): void
    {
        $scenarios = [
            'paid by bank transfer' => function (int $order, array $open): void {
                StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1', udlign_id = 1 WHERE id = $1", [$open['id']]);
            },
            'credited' => function (int $order, array $open): void {
                StripeFixtures::insert('ordrer', ['art' => 'DK', 'kred_ord_id' => $order, 'konto_id' => (int)$open['konto_id'], 'status' => 4]);
            },
            'partly paid' => function (int $order, array $open): void {
                StripeFixtures::insert('openpost', ['konto_id' => (int)$open['konto_id'], 'konto_nr' => $open['konto_nr'], 'faktnr' => $open['faktnr'],
                    'amount' => '-100.000', 'udlignet' => '0', 'transdate' => StripeFixtures::TODAY, 'valuta' => 'DKK', 'valutakurs' => '100.000', 'projekt' => '']);
            },
        ];
        foreach ($scenarios as $label => $change) {
            StripeTestTransport::reset();
            [$order, $id, $pi] = $this->captured(true, 'pi_over_' . substr(md5($label), 0, 8));
            $open = pg_fetch_assoc(StripeTestEnvironment::query("SELECT * FROM openpost WHERE faktnr = $1 AND amount > 0", [StripeFixtures::row('ordrer', $order)['fakturanr']]));
            $change($order, $open);
            $this->assertSame('applied', stripe_payment_apply_succeeded($pi), $label);
            $payment = StripeFixtures::row('stripe_payments', $id);
            $line = pg_fetch_assoc(StripeTestEnvironment::query('SELECT * FROM kassekladde WHERE id = $1', [$payment['journal_line_id']]));
            $this->assertSame('Stripe-overbetaling-' . $id, $line['faktura'], $label . ': a reference no open item carries, so posting settles nothing');
            $this->assertEqualsWithDelta(340.0, (float)$line['amount'], 0.001, $label . ': the money is in the books');
            $this->assertSame(['F', '58050', 'D', StripeFixtures::row('adresser', (int)$open['konto_id'])['kontonr']],
                [$line['d_type'], (string)$line['debet'], $line['k_type'], (string)$line['kredit']], $label);
            $this->assertStringContainsString('overbetaling', $line['beskrivelse'], $label);
            $this->assertSame((int)$order, (int)$line['ordre_id'], $label);
            $this->assertSame('succeeded', $payment['status'], $label);
            $this->assertSame('overpaid', $payment['last_error'], $label);
            $this->assertSame('skipped', $payment['card_state'], $label . ': no saved card from an overpayment');
            $this->assertNotEmpty($payment['confirmation_sent_at'], $label);
            $this->assertSame([], StripeTestTransport::$requests, $label . ': no enrolment');
            $this->assertCount(1, StripeTestTransport::$mails, $label . ': the bookkeeper alert, no receipt');
            $this->assertSame('alert', StripeTestTransport::$mails[0]['kind'] ?? '', $label);
            $this->assertStringContainsString('overbetaling', StripeTestTransport::$mails[0]['subject'], $label);
            $this->assertSame('', (string)StripeFixtures::row('adresser', (int)$open['konto_id'])['stripe'], $label);
            $this->assertSame(0, stripe_payment_repair()['found'], $label);
        }
    }

    public function testAFailedOverpaymentAlertIsRetriedWithoutAReceiptOrASecondLine(): void
    {
        [$order, $id, $pi] = $this->captured();
        StripeTestEnvironment::query("UPDATE openpost SET udlignet = '1', udlign_id = 1 WHERE faktnr = $1", [StripeFixtures::row('ordrer', $order)['fakturanr']]);
        StripeTestTransport::$mailSucceeds = false;
        $this->assertSame('delivery_error', stripe_payment_apply_succeeded($pi));
        $this->assertNull(StripeFixtures::row('stripe_payments', $id)['confirmation_sent_at']);
        StripeTestTransport::$mailSucceeds = true;
        $this->assertSame(['found' => 1, 'repaired' => 1, 'failed' => 0], stripe_payment_repair());
        $this->assertSame(1, $this->lineCount($order));
        $this->assertCount(2, StripeTestTransport::$mails);
        foreach (StripeTestTransport::$mails as $mail) $this->assertSame('alert', $mail['kind'] ?? '', 'never a receipt');
        $this->assertSame(0, stripe_payment_repair()['found']);
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

    public function testAnUnsentRefundAlertIsSentByTheRedeliveryWithoutApplyingTheRefundAgain(): void
    {
        [$order, $id, $pi] = $this->captured();
        $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
        $journal = StripeFixtures::row('stripe_payments', $id)['journal_line_id'];
        StripeTestTransport::$mails = [];
        StripeTestTransport::$mailSucceeds = false; // no mail transport, or no bookkeeper address
        $refund = $this->event('evt_refund_unsent');
        $this->assertSame(['code' => 500, 'message' => 'delivery_error'], stripe_invoice_webhook_dispatch($refund, 'charge.refunded', ['payment_intent' => 'pi_s1'], 'evt_refund_unsent', null));
        $this->assertSame(['failed', 'delivery_error'], [$this->eventRow($refund)['status'], $this->eventRow($refund)['error']], 'not acknowledged: Stripe sends it again');
        $this->assertSame(['refunded', 'evt_refund_unsent'], [StripeFixtures::row('stripe_payments', $id)['status'], StripeFixtures::row('stripe_payments', $id)['last_event_id']], 'the refund is applied');
        $this->assertSame([null], array_column($this->adjustmentAlerts(), 'sent_at'));
        // A dispute of the same payment comes and is told at once.
        StripeTestTransport::$mailSucceeds = true;
        $this->assertSame(['code' => 200, 'message' => 'ok'], stripe_invoice_webhook_dispatch($this->event('evt_dispute_sent'), 'charge.dispute.created', ['payment_intent' => 'pi_s1'], 'evt_dispute_sent'));
        $this->assertSame('disputed', StripeFixtures::row('stripe_payments', $id)['status']);
        // Stripe's redelivery of the refund sends its alert and does not apply the refund over the later dispute.
        $this->assertSame(['code' => 200, 'message' => 'ok'], stripe_invoice_webhook_dispatch($refund, 'charge.refunded', ['payment_intent' => 'pi_s1'], 'evt_refund_unsent'));
        $this->assertSame('processed', $this->eventRow($refund)['status']);
        $payment = StripeFixtures::row('stripe_payments', $id);
        $this->assertSame(['disputed', 'evt_dispute_sent', $journal], [$payment['status'], $payment['last_event_id'], $payment['journal_line_id']]);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertSame('on', StripeFixtures::row('ordrer', $order)['betalt']);
        $this->assertCount(3, StripeTestTransport::$mails); // the unsent refund alert, the dispute alert, the refund alert again
        $this->assertStringContainsString('refusion', StripeTestTransport::$mails[2]['subject']);
        $this->assertStringContainsString('evt_refund_unsent', StripeTestTransport::$mails[2]['text']);
        $this->assertNotContains(null, array_column($this->adjustmentAlerts(), 'sent_at'));
        // Sent once: a further redelivery and the repair pass send nothing more.
        $this->assertSame(['code' => 200, 'message' => 'ok'], stripe_invoice_webhook_dispatch($refund, 'charge.refunded', ['payment_intent' => 'pi_s1'], 'evt_refund_unsent'));
        $this->assertSame(['found' => 0, 'repaired' => 0, 'failed' => 0], stripe_payment_repair());
        $this->assertCount(3, StripeTestTransport::$mails);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testAnUnsentDisputeAlertIsSentByTheRepairPassOnceStripeHasStopped(): void
    {
        [$order, $id, $pi] = $this->captured();
        $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
        StripeTestTransport::$mails = [];
        StripeTestTransport::$mailSucceeds = false;
        $dispute = $this->event('evt_dispute_unsent');
        $this->assertSame(['code' => 500, 'message' => 'delivery_error'], stripe_invoice_webhook_dispatch($dispute, 'charge.dispute.created', ['payment_intent' => 'pi_s1'], 'evt_dispute_unsent'));
        $this->assertSame('disputed', StripeFixtures::row('stripe_payments', $id)['status']);
        $this->assertSame(['found' => 1, 'repaired' => 0, 'failed' => 0], stripe_payment_repair(true), 'a dry run only counts it');
        $failed = stripe_charge_job(StripeFixtures::TODAY, false, true);
        $this->assertSame([1, 1, 1], [$failed['exit_code'], $failed['stats']['repair_found'], $failed['stats']['repair_failed']], 'still unsent: the job says so');
        $this->assertSame('failed', $this->eventRow($dispute)['status']);
        StripeTestTransport::$mailSucceeds = true;
        $this->assertSame(['found' => 1, 'repaired' => 1, 'failed' => 0], stripe_payment_repair());
        $row = $this->eventRow($dispute);
        $this->assertSame(['processed', null, (string)$order], [$row['status'], $row['error'], $row['saldi_order_id']], 'the event row it left failed is finished');
        $this->assertSame(0, stripe_payment_repair()['found']);
        $this->assertSame('disputed', StripeFixtures::row('stripe_payments', $id)['status']);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertCount(3, StripeTestTransport::$mails); // two unsent alerts, then the one sent by the repair pass
        $this->assertStringContainsString('indsigelse', StripeTestTransport::$mails[2]['subject']);
        $this->assertStringContainsString('pi_s1', StripeTestTransport::$mails[2]['text']);
        $this->assertStringContainsString('evt_dispute_unsent', StripeTestTransport::$mails[2]['text']);
        // A redelivery that comes after all sends nothing more.
        $this->assertSame(['code' => 200, 'message' => 'ok'], stripe_invoice_webhook_dispatch($dispute, 'charge.dispute.created', ['payment_intent' => 'pi_s1'], 'evt_dispute_unsent'));
        $this->assertCount(3, StripeTestTransport::$mails);
        $this->assertSame([], StripeTestTransport::$requests);
    }

    public function testARefundWhoseAlertRowCannotBeWrittenIsNotAppliedAndDoesNotOverwriteALaterDispute(): void
    {
        [$order, $id, $pi] = $this->captured();
        $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
        $before = StripeFixtures::row('stripe_payments', $id);
        StripeTestTransport::$mails = [];
        // Writing the refund's row in stripe_adjustment_alerts fails (the worker stops, or the INSERT is refused).
        $this->skipWrite('stripe_adjustment_alerts', 'INSERT');
        $refund = $this->event('evt_refund_lost');
        $this->assertSame(['code' => 500, 'message' => 'database_error'], stripe_invoice_webhook_dispatch($refund, 'charge.refunded', ['payment_intent' => 'pi_s1'], 'evt_refund_lost'));
        $after = StripeFixtures::row('stripe_payments', $id);
        $this->assertSame([$before['status'], $before['last_event_id']], [$after['status'], $after['last_event_id']], 'the refund is not applied without its row');
        $this->assertSame([], $this->adjustmentAlerts());
        $this->assertSame([], StripeTestTransport::$mails, 'nothing is told before it is recorded');
        $this->assertSame(['failed', 'database_error'], [$this->eventRow($refund)['status'], $this->eventRow($refund)['error']], 'Stripe sends it again');
        StripeTestEnvironment::query('DROP TRIGGER s1_skip ON stripe_adjustment_alerts');

        // A dispute of the payment is applied before the refund comes again.
        $this->assertSame(['code' => 200, 'message' => 'ok'], stripe_invoice_webhook_dispatch($this->event('evt_dispute_later'), 'charge.dispute.created', ['payment_intent' => 'pi_s1'], 'evt_dispute_later'));
        $this->assertSame('disputed', StripeFixtures::row('stripe_payments', $id)['status']);
        // The refund's redelivery is told, and does not put 'refunded' over the dispute.
        $this->assertSame(['code' => 200, 'message' => 'ok'], stripe_invoice_webhook_dispatch($refund, 'charge.refunded', ['payment_intent' => 'pi_s1'], 'evt_refund_lost'));
        $payment = StripeFixtures::row('stripe_payments', $id);
        $this->assertSame(['disputed', 'evt_dispute_later', $before['journal_line_id']], [$payment['status'], $payment['last_event_id'], $payment['journal_line_id']]);
        $this->assertSame(['evt_dispute_later', 'evt_refund_lost'], array_column($this->adjustmentAlerts(), 'event_id'));
        $this->assertNotContains(null, array_column($this->adjustmentAlerts(), 'sent_at'));
        $this->assertCount(2, StripeTestTransport::$mails);
        $this->assertStringContainsString('indsigelse', StripeTestTransport::$mails[0]['subject']);
        $this->assertStringContainsString('refusion', StripeTestTransport::$mails[1]['subject']);
        $this->assertSame(1, $this->lineCount($order));
        $this->assertSame('on', StripeFixtures::row('ordrer', $order)['betalt']);
        $this->assertSame(['found' => 0, 'repaired' => 0, 'failed' => 0], stripe_payment_repair());
    }

    public function testAnAdjustmentAlertIsSentAfterCommitWhileThePaymentIsLocked(): void
    {
        [, $id, $pi] = $this->captured();
        $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
        $parameters = [];
        foreach (['host' => 'SALDI_TEST_PGHOST', 'user' => 'SALDI_TEST_PGUSER', 'password' => 'SALDI_TEST_PGPASSWORD', 'dbname' => 'SALDI_TEST_DB'] as $key => $env) {
            $parameters[] = $key . "='" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)getenv($env)) . "'";
        }
        $other = pg_connect(implode(' ', $parameters), PGSQL_CONNECT_FORCE_NEW);
        $this->assertNotFalse($other, 'a second connection to the tenant');
        $seen = [];
        StripeTestTransport::$beforeAlert = function () use (&$seen, $other, $id): void {
            $seen[] = [pg_transaction_status($GLOBALS['connection']), pg_fetch_result(pg_query($other, "select pg_try_advisory_lock(82101, $id)"), 0, 0)];
        };
        try {
            $this->assertSame(['code' => 200, 'message' => 'ok'], stripe_invoice_webhook_dispatch($this->event('evt_locked'), 'charge.refunded', ['payment_intent' => 'pi_s1'], 'evt_locked'));
        } finally {
            pg_query($other, 'select pg_advisory_unlock_all()');
            pg_close($other);
        }
        $this->assertSame([[PGSQL_TRANSACTION_IDLE, 'f']], $seen, 'committed, and booking or another delivery of the payment waits');
        $this->assertSame('refunded', StripeFixtures::row('stripe_payments', $id)['status']);
        // The lock is released afterwards.
        $this->assertSame('t', pg_fetch_result(StripeTestEnvironment::query("select pg_try_advisory_lock(82101, $id)"), 0, 0));
        StripeTestEnvironment::query("select pg_advisory_unlock(82101, $id)");
    }

    public function testARefundAppliedAfterTheDisputeLeavesDisputedAndADisputeAfterARefundIsShown(): void
    {
        // A worker that read the payment before its dispute applies the refund after it: the payment stays disputed.
        [, $id, $pi] = $this->captured(false, 'pi_stale');
        $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
        $this->assertSame(['code' => 200, 'message' => 'ok'], stripe_invoice_webhook_dispatch($this->event('evt_stale_dispute'), 'charge.dispute.created', ['payment_intent' => 'pi_stale'], 'evt_stale_dispute'));
        $payment = stripe_payment_apply_adjustment('charge.refunded', ['payment_intent' => 'pi_stale'], 'evt_stale_refund');
        $this->assertTrue($payment['alert_sent']);
        $row = StripeFixtures::row('stripe_payments', $id);
        $this->assertSame(['disputed', 'evt_stale_dispute'], [$row['status'], $row['last_event_id']]);
        // A dispute after a refund is shown.
        [, $id, $pi] = $this->captured(false, 'pi_refund_first');
        $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
        stripe_payment_apply_adjustment('charge.refunded', ['payment_intent' => 'pi_refund_first'], 'evt_first_refund');
        stripe_payment_apply_adjustment('charge.dispute.created', ['payment_intent' => 'pi_refund_first'], 'evt_then_dispute');
        $row = StripeFixtures::row('stripe_payments', $id);
        $this->assertSame(['disputed', 'evt_then_dispute'], [$row['status'], $row['last_event_id']]);
        // The same for a second capture of an invoice (duplicate_paid), in last_error.
        [$order, , $first] = $this->captured(false, 'pi_dup_first');
        $this->assertSame('applied', stripe_payment_apply_succeeded($first));
        $second = $first;
        $second['id'] = 'pi_dup_second';
        $extra = stripe_payment_record(stripe_invoice_load($order), $second, 'page', false, 2);
        $this->assertSame('duplicate_paid', stripe_payment_apply_succeeded($second));
        stripe_payment_apply_adjustment('charge.dispute.created', ['payment_intent' => 'pi_dup_second'], 'evt_dup_dispute');
        stripe_payment_apply_adjustment('charge.refunded', ['payment_intent' => 'pi_dup_second'], 'evt_dup_refund');
        $row = StripeFixtures::row('stripe_payments', $extra);
        $this->assertSame(['duplicate_paid', 'duplicate_paid_disputed', 'evt_dup_dispute'], [$row['status'], $row['last_error'], $row['last_event_id']]);
        $this->assertCount(6, $this->adjustmentAlerts(), 'every event is told');
        $this->assertNotContains(null, array_column($this->adjustmentAlerts(), 'sent_at'));
    }

    /** @return list<array<string, string|null>> The rows of stripe_adjustment_alerts. */
    private function adjustmentAlerts(): array
    {
        return pg_fetch_all(StripeTestEnvironment::query('SELECT * FROM stripe_adjustment_alerts ORDER BY event_id')) ?: [];
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
        // The row this first delivery inserted is released too: a redelivery inside 15 minutes would otherwise be a duplicate.
        $this->assertSame(['failed', 'delivery_error'], [$this->eventRow($event)['status'], $this->eventRow($event)['error']]);
        $this->assertSame('duplicate_paid', StripeFixtures::row('stripe_payments', $extra)['status']);
        $this->assertNull(StripeFixtures::row('stripe_payments', $extra)['confirmation_sent_at']);
        $capture = StripeFixtures::row('stripe_payments', $extra);
        $this->assertSame(['code' => 500, 'message' => 'delivery_error'], stripe_invoice_webhook_dispatch($event, 'payment_intent.succeeded', $pi, 'evt_extra_alert'));
        $this->assertSame('failed', $this->eventRow($event)['status']);
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

    public function testAnInvoiceEventIsFinishedOrReleasedOnlyByTheDeliveryHoldingIt(): void
    {
        [$order, $firstId, $pi] = $this->captured();
        $this->assertSame('applied', stripe_payment_apply_succeeded($pi));
        $pi['id'] = 'pi_extra_owned';
        $extra = stripe_payment_record(stripe_invoice_load($order), $pi, 'page', false, 2);
        // A redelivery took the row over from a slow delivery ('tok_slow') and holds it ('tok_new').
        $event = StripeFixtures::insert('stripe_events', ['event_id' => 'evt_taken_over', 'status' => 'retrying', 'error' => 'tok_new']);
        $state = fn(): array => [$this->eventRow($event)['status'], $this->eventRow($event)['error']];
        StripeTestTransport::$mailSucceeds = false;
        $this->assertSame(['code' => 500, 'message' => 'delivery_error'], stripe_invoice_webhook_dispatch($event, 'payment_intent.succeeded', $pi, 'evt_taken_over', 'tok_slow'));
        $this->assertSame(['retrying', 'tok_new'], $state(), 'the slow delivery does not release the row');
        $this->assertSame(['code' => 500, 'message' => 'delivery_error'], stripe_invoice_webhook_dispatch($event, 'payment_intent.succeeded', $pi, 'evt_taken_over', 'tok_new'));
        $this->assertSame(['failed', 'delivery_error'], $state(), 'the delivery holding the row releases it');
        // Stripe's next redelivery claims it, as the webhook does.
        StripeTestEnvironment::query("UPDATE stripe_events SET status = 'retrying', error = 'tok_next' WHERE id = $1", [$event]);
        StripeTestTransport::$mailSucceeds = true;
        $this->assertSame(['code' => 200, 'message' => 'duplicate_paid'], stripe_invoice_webhook_dispatch($event, 'payment_intent.succeeded', $pi, 'evt_taken_over', 'tok_new'));
        $this->assertSame(['retrying', 'tok_next'], $state(), 'a delivery whose claim ended does not finish the row');
        $this->assertSame(['code' => 200, 'message' => 'duplicate_paid'], stripe_invoice_webhook_dispatch($event, 'payment_intent.succeeded', $pi, 'evt_taken_over', 'tok_next'));
        $this->assertSame(['processed', null], $state(), 'the delivery holding the row finishes it and clears its token');
        $this->assertNotNull(StripeFixtures::row('stripe_payments', $extra)['confirmation_sent_at']);
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

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testReminderBookingPostsNothingForACarriedFeeAndKeepsClassicPosting(): void
    {
        require_once __DIR__ . '/../../includes/openpost.php';   // bogfor_rykker()/bogfor_nu() as "Ryk alle" uses them
        $this->assertReminderBooking();

        // Outside the active fiscal year nothing is posted, as "Bogfør" refuses it (includes/rapportfunc.php, 20121106).
        StripeTestEnvironment::query("UPDATE grupper SET box2 = '2025', box4 = '2025' WHERE art = 'RA'");
        $debtor = StripeFixtures::debtor(['gruppe' => 7]);
        $rykker = StripeReminderFixtures::reminder($debtor, [StripeFixtures::invoice($debtor, ['udskriv_til' => 'email'])]);
        ob_start();
        try {
            $this->assertFalse(bogfor_rykker($rykker));
        } finally {
            $output = ob_get_clean();
        }
        $this->assertSame('', $output);
        $this->assertSame(2, (int)StripeFixtures::row('ordrer', $rykker)['status']);
        $this->assertSame([], pg_fetch_all(StripeTestEnvironment::query('SELECT id FROM openpost WHERE refnr = $1', [$rykker])) ?: []);
        $this->assertSame([], pg_fetch_all(StripeTestEnvironment::query('SELECT id FROM transaktioner WHERE ordre_id = $1', [$rykker])) ?: []);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBogforButtonPostsNothingForACarriedFeeAndKeepsClassicPosting(): void
    {
        // The "Bogfør" button's copy (debitor/rapport.php). It declares the same functions as includes/openpost.php, hence its
        // own process, and includes ../includes/reportFunc/showOpenPosts.php from the page's folder.
        $cwd = getcwd();
        chdir(__DIR__ . '/../../debitor');
        try {
            require_once __DIR__ . '/../../includes/rapportfunc.php';
        } finally {
            chdir($cwd);
        }
        $this->assertReminderBooking();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testReminderBookingUsesTheGroupsOfThePostingYear(): void
    {
        require_once __DIR__ . '/../../includes/openpost.php';   // "Ryk alle" and the automatic run
        $this->assertReminderBookingUsesThePostingYear();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBogforButtonUsesTheGroupsOfThePostingYear(): void
    {
        $cwd = getcwd();
        chdir(__DIR__ . '/../../debitor');
        try {
            require_once __DIR__ . '/../../includes/rapportfunc.php';
        } finally {
            chdir($cwd);
        }
        $this->assertReminderBookingUsesThePostingYear();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testReminderBookingWithoutAGroupOfThePostingYearPostsNothing(): void
    {
        require_once __DIR__ . '/../../includes/openpost.php';   // "Ryk alle" and the automatic run
        $this->assertBookingWithoutAGroupOfThePostingYearPostsNothing(true);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBogforButtonWithoutAGroupOfThePostingYearPostsNothing(): void
    {
        $cwd = getcwd();
        chdir(__DIR__ . '/../../debitor');
        try {
            require_once __DIR__ . '/../../includes/rapportfunc.php';
        } finally {
            chdir($cwd);
        }
        $this->assertBookingWithoutAGroupOfThePostingYearPostsNothing(false);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testReminderBookingRefusedForAStockTrackedLineKeepsNothing(): void
    {
        require_once __DIR__ . '/../../includes/openpost.php';   // "Ryk alle" and the automatic run
        $this->assertRefusedBookingKeepsNothingAndUnpostedIsBooked();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBogforButtonRefusedForAStockTrackedLineKeepsNothing(): void
    {
        $cwd = getcwd();
        chdir(__DIR__ . '/../../debitor');
        try {
            require_once __DIR__ . '/../../includes/rapportfunc.php';
        } finally {
            chdir($cwd);
        }
        $this->assertRefusedBookingKeepsNothingAndUnpostedIsBooked();
    }

    /** @return array{status: int, accounts: array, open: array, journal: array} What booking the reminder left. */
    private function bookingOf(int $rykker): array
    {
        return [
            'status' => (int)StripeFixtures::row('ordrer', $rykker)['status'],
            'accounts' => pg_fetch_all(StripeTestEnvironment::query('SELECT id, bogf_konto FROM ordrelinjer WHERE ordre_id = $1 ORDER BY id', [$rykker])) ?: [],
            'open' => pg_fetch_all(StripeTestEnvironment::query('SELECT id, amount FROM openpost WHERE refnr = $1 ORDER BY id', [$rykker])) ?: [],
            'journal' => pg_fetch_all(StripeTestEnvironment::query('SELECT kontonr, debet, kredit FROM transaktioner WHERE ordre_id = $1 ORDER BY debet DESC, kontonr', [$rykker])) ?: [],
        ];
    }

    /** bogfor_rykker() (whichever copy is loaded), with its output. */
    private function book(int $rykker): string
    {
        foreach (['openpost', 'transaktioner'] as $table) {
            StripeTestEnvironment::query("SELECT setval('pg_temp.s1_{$table}_seq', (SELECT coalesce(max(id), 0) + 1 FROM $table))");
        }
        ob_start();
        try {
            bogfor_rykker($rykker);
        } finally {
            $output = ob_get_clean();
        }
        return $output;
    }

    /**
     * A reminder with a bookable fee line and a stock-tracked line: the booking is refused and keeps nothing, so the reminder is
     * not taken for booked. Once the item is in a group that is not stock-tracked, it is booked once. A reminder left at status 3
     * without a fee open item or a posting (earlier code committed the bookable line's status) is booked; one at status 3 with its
     * fee open item is not booked again.
     */
    private function assertRefusedBookingKeepsNothingAndUnpostedIsBooked(): void
    {
        require_once __DIR__ . '/support/reminderFixtures.php';
        if (is_file(__DIR__ . '/../../includes/stripeIncludes/stripeReminder.php')) {
            require_once __DIR__ . '/../../includes/stripeIncludes/stripeReminder.php';
        }
        foreach (['varer', 'formularer', 'stripe_reminders'] as $table) {
            StripeTestEnvironment::query("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING ALL)");
            $this->tables[] = $table;
        }
        StripeFixtures::insert('grupper', ['art' => 'DG', 'kodenr' => 7, 'box2' => '5600', 'fiscal_year' => 5]);
        StripeFixtures::insert('grupper', ['art' => 'VG', 'kodenr' => 9, 'box1' => '', 'box2' => '', 'box3' => '', 'box4' => '1010', 'box8' => '', 'box9' => '', 'fiscal_year' => 5]);
        StripeFixtures::insert('grupper', ['art' => 'VG', 'kodenr' => 10, 'box1' => '', 'box2' => '', 'box3' => '', 'box4' => '1020', 'box8' => 'on', 'box9' => '', 'fiscal_year' => 5]);
        StripeReminderFixtures::forms(100.0, 9);
        $debtor = StripeFixtures::debtor(['gruppe' => 7]);
        $rykker = StripeReminderFixtures::reminder($debtor, [StripeFixtures::invoice($debtor, ['udskriv_til' => 'email'])]);
        $tracked = StripeReminderFixtures::item('Lagerført rente', 25.0, 10);
        StripeFixtures::insert('ordrelinjer', ['ordre_id' => $rykker, 'posnr' => 9, 'vare_id' => $tracked, 'varenr' => 'TRACKED',
            'beskrivelse' => 'Lagerført rente', 'antal' => 1, 'pris' => 25, 'rabat' => 0, 'serienr' => StripeReminderFixtures::REMINDER_DATE]);
        StripeTestEnvironment::query('UPDATE ordrer SET sum = 125 WHERE id = $1', [$rykker]);
        $before = $this->bookingOf($rykker);

        $output = $this->book($rykker);

        $this->assertStringContainsString('lagerf', $output, 'the refusal is shown');
        $this->assertSame($before, $this->bookingOf($rykker), 'nothing of the refused booking is kept: status 2, no account, no open item, no posting');
        $this->assertSame(2, $before['status']);

        StripeTestEnvironment::query('UPDATE varer SET gruppe = 9 WHERE id = $1', [$tracked]);
        $this->assertSame('', $this->book($rykker));
        $booked = $this->bookingOf($rykker);
        $this->assertSame(4, $booked['status'], 'booked once the item is corrected');
        $this->assertCount(1, $booked['open']);
        $this->assertEqualsWithDelta(125.0, (float)$booked['open'][0]['amount'], 0.001);
        $this->assertSame([['5600', '125.000', '0.000'], ['1010', '0.000', '125.000']],
            array_map(static fn(array $r): array => [(string)$r['kontonr'], (string)$r['debet'], (string)$r['kredit']], $booked['journal']));
        $this->book($rykker);
        $this->assertSame($booked, $this->bookingOf($rykker), 'a second booking posts nothing more');

        // Left at status 3 by earlier code: the fee line has its account, nothing is posted. It is booked.
        $left = StripeReminderFixtures::reminder($debtor, [StripeFixtures::invoice($debtor, ['udskriv_til' => 'email'])]);
        StripeTestEnvironment::query('UPDATE ordrer SET status = 3 WHERE id = $1', [$left]);
        StripeTestEnvironment::query('UPDATE ordrelinjer SET bogf_konto = 1010 WHERE ordre_id = $1 AND vare_id > 0', [$left]);
        $this->assertSame('', $this->book($left));
        $recovered = $this->bookingOf($left);
        $this->assertSame(4, $recovered['status'], 'the unposted reminder is booked');
        $this->assertCount(1, $recovered['open']);
        $this->assertSame([['5600', '100.000', '0.000'], ['1010', '0.000', '100.000']],
            array_map(static fn(array $r): array => [(string)$r['kontonr'], (string)$r['debet'], (string)$r['kredit']], $recovered['journal']));

        // At status 3 with its fee open item: booked, not booked again.
        $done = StripeReminderFixtures::reminder($debtor, [StripeFixtures::invoice($debtor, ['udskriv_til' => 'email'])]);
        StripeTestEnvironment::query('UPDATE ordrer SET status = 3 WHERE id = $1', [$done]);
        $row = StripeFixtures::row('ordrer', $done);
        StripeFixtures::insert('openpost', ['konto_id' => $debtor, 'konto_nr' => $row['kontonr'], 'faktnr' => $row['fakturanr'], 'refnr' => $done,
            'amount' => 100, 'beskrivelse' => 'Gebyr mm. fra tidligere rykker', 'udlignet' => '1', 'transdate' => $row['fakturadate'], 'kladde_id' => 0, 'projekt' => '']);
        $doneBefore = $this->bookingOf($done);
        $this->book($done);
        $this->assertSame($doneBefore, $this->bookingOf($done), 'a reminder with its fee open item (here settled) gets no second fee');
    }

    /**
     * grupper has one row per fiscal year. A ledger has last year's rows of the same item and debtor groups, here with other
     * accounts and read first; a reminder of the active year (regnaar 5) is posted with that year's accounts, as an invoice is.
     */
    private function assertReminderBookingUsesThePostingYear(): void
    {
        require_once __DIR__ . '/support/reminderFixtures.php';
        if (is_file(__DIR__ . '/../../includes/stripeIncludes/stripeReminder.php')) {
            require_once __DIR__ . '/../../includes/stripeIncludes/stripeReminder.php';
        }
        foreach (['varer', 'formularer', 'stripe_reminders'] as $table) {
            StripeTestEnvironment::query("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING ALL)");
            $this->tables[] = $table;
        }
        StripeFixtures::insert('grupper', ['art' => 'DG', 'kodenr' => 7, 'box2' => '5500', 'fiscal_year' => 4]);
        StripeFixtures::insert('grupper', ['art' => 'VG', 'kodenr' => 9, 'box1' => '', 'box2' => '', 'box3' => '', 'box4' => '1000', 'box8' => '', 'box9' => '', 'fiscal_year' => 4]);
        StripeFixtures::insert('grupper', ['art' => 'DG', 'kodenr' => 7, 'box2' => '5600', 'fiscal_year' => 5]);
        StripeFixtures::insert('grupper', ['art' => 'VG', 'kodenr' => 9, 'box1' => '', 'box2' => '', 'box3' => '', 'box4' => '1010', 'box8' => '', 'box9' => '', 'fiscal_year' => 5]);
        StripeReminderFixtures::forms(100.0, 9);
        $debtor = StripeFixtures::debtor(['gruppe' => 7]);
        $rykker = StripeReminderFixtures::reminder($debtor, [StripeFixtures::invoice($debtor, ['udskriv_til' => 'email'])]);
        foreach (['openpost', 'transaktioner'] as $table) {
            StripeTestEnvironment::query("SELECT setval('pg_temp.s1_{$table}_seq', (SELECT coalesce(max(id), 0) + 1 FROM $table))");
        }
        $this->assertSame('5', (string)$GLOBALS['regnaar'], 'precondition: the active fiscal year');
        ob_start();
        try {
            bogfor_rykker($rykker);
        } finally {
            $output = ob_get_clean();
        }
        $this->assertSame('', $output);
        $this->assertSame(4, (int)StripeFixtures::row('ordrer', $rykker)['status'], 'booked');
        $journal = pg_fetch_all(StripeTestEnvironment::query('SELECT * FROM transaktioner WHERE ordre_id = $1 ORDER BY debet DESC', [$rykker])) ?: [];
        $this->assertCount(2, $journal);
        $this->assertSame([5600, 100.0, 0.0], [(int)$journal[0]['kontonr'], (float)$journal[0]['debet'], (float)$journal[0]['kredit']], 'the debtor group of the posting year');
        $this->assertSame([1010, 0.0, 100.0], [(int)$journal[1]['kontonr'], (float)$journal[1]['debet'], (float)$journal[1]['kredit']], 'the fee item group of the posting year');
        $feeLine = pg_fetch_assoc(StripeTestEnvironment::query('SELECT bogf_konto FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$rykker]));
        $this->assertSame(1010, (int)$feeLine['bogf_konto']);
    }

    /** @return list<string> The texts of the alerts in a page's output. */
    private static function alerts(string $output): array
    {
        preg_match_all('/<script>alert\((".*?")\);<\/script>/s', $output, $m);
        return array_map(static fn(string $js): string => (string)json_decode($js), $m[1]);
    }

    /**
     * grupper has one row per fiscal year. The ledger has only last year's rows of the fee item's and the debtor's group, with
     * accounts that still exist: a reminder of the active year (regnaar 5) is not posted with them. Nothing is written, the
     * message names the missing groups and the year, and the reminder can be booked once the groups are set up for the year.
     *
     * @param bool $callerTransaction Also call the "Ryk alle" form (includes/openpost.php, in the caller's transaction).
     */
    private function assertBookingWithoutAGroupOfThePostingYearPostsNothing(bool $callerTransaction): void
    {
        require_once __DIR__ . '/support/reminderFixtures.php';
        require_once __DIR__ . '/../../includes/reminderLegal.php';
        if (is_file(__DIR__ . '/../../includes/stripeIncludes/stripeReminder.php')) {
            require_once __DIR__ . '/../../includes/stripeIncludes/stripeReminder.php';
        }
        foreach (['varer', 'formularer', 'stripe_reminders'] as $table) {
            StripeTestEnvironment::query("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING ALL)");
            $this->tables[] = $table;
        }
        StripeFixtures::insert('grupper', ['art' => 'DG', 'kodenr' => 7, 'box2' => '5500', 'fiscal_year' => 4]);
        StripeFixtures::insert('grupper', ['art' => 'VG', 'kodenr' => 9, 'box1' => '', 'box2' => '', 'box3' => '', 'box4' => '1000', 'box8' => '', 'box9' => '', 'fiscal_year' => 4]);
        StripeFixtures::insert('grupper', ['art' => 'VG', 'kodenr' => 11, 'box1' => '', 'box2' => '', 'box3' => '', 'box4' => '1000', 'box8' => '', 'box9' => '', 'fiscal_year' => 4]);
        StripeReminderFixtures::forms(100.0, 9);
        $debtor = StripeFixtures::debtor(['gruppe' => 7]);
        $rykker = StripeReminderFixtures::reminder($debtor, [StripeFixtures::invoice($debtor, ['udskriv_til' => 'email'])]);
        $nr = StripeFixtures::row('ordrer', $rykker)['fakturanr'];
        $this->assertSame('5', (string)$GLOBALS['regnaar'], 'precondition: the active fiscal year');
        $ra = pg_fetch_assoc(StripeTestEnvironment::query("SELECT beskrivelse FROM grupper WHERE art = 'RA' AND kodenr = 5"));
        $year = trim((string)($ra['beskrivelse'] ?? '')) ?: '5';
        $refusal = static fn(string $groups): string => "rykkergebyret kan ikke bogføres, da regnskabsår $year mangler $groups. "
            . "Opret $groups i regnskabsår $year under Indstillinger, og bogfør rykkeren igen.";
        $before = $this->bookingOf($rykker);
        $this->assertSame(2, $before['status']);

        // Neither group has a row in the posting year.
        $this->assertSame(["Rykker $nr: " . $refusal('varegruppe 9, debitorgruppe 7')], self::alerts($this->book($rykker)));
        $this->assertSame($before, $this->bookingOf($rykker), 'nothing is written: status 2, no account, no open item, no posting');
        if ($callerTransaction) {
            try {
                bogfor_rykker($rykker, false);
                $this->fail('"Ryk alle" books a reminder without its groups of the posting year.');
            } catch (RuntimeException $e) {
                $this->assertSame($refusal('varegruppe 9, debitorgruppe 7'), $e->getMessage(), '"Ingen ny rykker for …: <reason>"');
            }
            $this->assertSame($before, $this->bookingOf($rykker));
        }

        // The item group is set up for the year, the debtor group is not.
        StripeFixtures::insert('grupper', ['art' => 'VG', 'kodenr' => 9, 'box1' => '', 'box2' => '', 'box3' => '', 'box4' => '1010', 'box8' => '', 'box9' => '', 'fiscal_year' => 5]);
        $this->assertSame(["Rykker $nr: " . $refusal('debitorgruppe 7')], self::alerts($this->book($rykker)));
        $this->assertSame($before, $this->bookingOf($rykker));

        // The debtor group is set up for the year, but an interest line's item is in a group that is not.
        StripeFixtures::insert('grupper', ['art' => 'DG', 'kodenr' => 7, 'box2' => '5600', 'fiscal_year' => 5]);
        $other = StripeReminderFixtures::reminder($debtor, [StripeFixtures::invoice($debtor, ['udskriv_til' => 'email'])]);
        $untracked = StripeReminderFixtures::item('Rente', 10.0, 11);
        StripeFixtures::insert('ordrelinjer', ['ordre_id' => $other, 'posnr' => 9, 'vare_id' => $untracked, 'varenr' => 'RENTE',
            'beskrivelse' => 'Rente', 'antal' => 1, 'pris' => 10, 'rabat' => 0, 'serienr' => StripeReminderFixtures::REMINDER_DATE]);
        $otherNr = StripeFixtures::row('ordrer', $other)['fakturanr'];
        $otherBefore = $this->bookingOf($other);
        $this->assertSame(["Rykker $otherNr: " . $refusal('varegruppe 11')], self::alerts($this->book($other)));
        $this->assertSame($otherBefore, $this->bookingOf($other));

        // With both groups of the year, the reminder that was refused is booked with that year's accounts, once.
        $this->assertSame('', $this->book($rykker));
        $booked = $this->bookingOf($rykker);
        $this->assertSame(4, $booked['status']);
        $this->assertCount(1, $booked['open']);
        $this->assertSame([['5600', '100.000', '0.000'], ['1010', '0.000', '100.000']],
            array_map(static fn(array $r): array => [(string)$r['kontonr'], (string)$r['debet'], (string)$r['kredit']], $booked['journal']));
        $this->book($rykker);
        $this->assertSame($booked, $this->bookingOf($rykker), 'a second booking posts nothing more');
    }

    /** bogfor_rykker() (whichever copy is loaded) for a link reminder whose fee goes on the next invoice, one whose fee is an open item, and a classic one. */
    private function assertReminderBooking(): void
    {
        require_once __DIR__ . '/support/reminderFixtures.php';
        if (is_file(__DIR__ . '/../../includes/stripeIncludes/stripeReminder.php')) {
            require_once __DIR__ . '/../../includes/stripeIncludes/stripeReminder.php';
        }
        // bogfor_rykker() commits, so every table it or the fixtures write is a temporary copy.
        foreach (['varer', 'formularer', 'stripe_reminders'] as $table) {
            StripeTestEnvironment::query("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING ALL)");
            $this->tables[] = $table;
        }
        StripeFixtures::insert('grupper', ['art' => 'DG', 'kodenr' => 7, 'box2' => '5600', 'fiscal_year' => 5]);
        StripeFixtures::insert('grupper', ['art' => 'VG', 'kodenr' => 9, 'box1' => '', 'box2' => '', 'box3' => '', 'box4' => '1010', 'box8' => '', 'box9' => '', 'fiscal_year' => 5]);
        StripeReminderFixtures::forms(100.0, 9);
        foreach (['next' => true, 'classic' => false, 'no row' => false] as $mode => $carried) {
            $debtor = StripeFixtures::debtor(['gruppe' => 7]);
            $rykker = StripeReminderFixtures::reminder($debtor, [StripeFixtures::invoice($debtor, ['udskriv_til' => 'Link'])], ['interest' => 20]);
            if ($mode !== 'no row') {
                StripeReminderFixtures::linkRow($rykker, ['fee_mode' => $mode, 'interest_ore' => 2000]);
            }
            foreach (['openpost', 'transaktioner'] as $table) {
                // Fixture rows take explicit ids; the posting's own inserts use the temporary sequence after them.
                StripeTestEnvironment::query("SELECT setval('pg_temp.s1_{$table}_seq', (SELECT coalesce(max(id), 0) + 1 FROM $table))");
            }
            ob_start();
            try {
                bogfor_rykker($rykker);
            } finally {
                $output = ob_get_clean();
            }
            $this->assertSame('', $output, $mode);
            $reminder = StripeFixtures::row('ordrer', $rykker);
            $this->assertSame(4, (int)$reminder['status'], $mode . ': moves to "Bogførte rykkere" and can be escalated');
            $open = pg_fetch_all(StripeTestEnvironment::query('SELECT * FROM openpost WHERE refnr = $1', [$rykker])) ?: [];
            $journal = pg_fetch_all(StripeTestEnvironment::query('SELECT * FROM transaktioner WHERE ordre_id = $1 ORDER BY debet DESC', [$rykker])) ?: [];
            if ($carried) {
                $this->assertSame([], $open, $mode);
                $this->assertSame([], $journal, $mode);
                continue;
            }
            $this->assertCount(1, $open, $mode);
            $this->assertSame($reminder['fakturanr'], $open[0]['faktnr'], $mode);
            $this->assertEqualsWithDelta(120.0, (float)$open[0]['amount'], 0.001, $mode);
            $this->assertSame('', $open[0]['projekt'], $mode . ": finans/bogfor.php openpost() settles projekt '', never NULL");
            $this->assertCount(2, $journal, $mode);
            $this->assertSame([5600, 120.0, 0.0], [(int)$journal[0]['kontonr'], (float)$journal[0]['debet'], (float)$journal[0]['kredit']], $mode);
            $this->assertSame([1010, 0.0, 120.0], [(int)$journal[1]['kontonr'], (float)$journal[1]['debet'], (float)$journal[1]['kredit']], $mode);
            // A second Bogfør from a stale overview or a second click posts nothing more.
            ob_start();
            try {
                bogfor_rykker($rykker);
            } finally {
                $output = ob_get_clean();
            }
            $this->assertSame('', $output, $mode);
            $this->assertCount(1, pg_fetch_all(StripeTestEnvironment::query('SELECT id FROM openpost WHERE refnr = $1', [$rykker])) ?: [], $mode . ': booked once');
            $this->assertCount(2, pg_fetch_all(StripeTestEnvironment::query('SELECT id FROM transaktioner WHERE ordre_id = $1', [$rykker])) ?: [], $mode . ': posted once');
        }
        // An automatic reminder that was not sent is not booked: its fee is not owed.
        StripeTestEnvironment::query("CREATE TEMP TABLE reminder_automation_jobs (order_id integer PRIMARY KEY, previous_id integer, mode varchar(12) NOT NULL,
            state varchar(12) NOT NULL, created_at timestamptz NOT NULL DEFAULT current_timestamp, attempted_at timestamptz, sent_at timestamptz,
            sent_date date, channel varchar(12) NOT NULL DEFAULT '', error text NOT NULL DEFAULT '', actor text NOT NULL DEFAULT '')");
        $this->tables[] = 'reminder_automation_jobs';
        $debtor = StripeFixtures::debtor(['gruppe' => 7]);
        $draft = StripeReminderFixtures::reminder($debtor, [StripeFixtures::invoice($debtor, ['udskriv_til' => 'email'])]);
        StripeTestEnvironment::query("INSERT INTO reminder_automation_jobs (order_id, mode, state, actor) VALUES ($1, 'approval', 'approval', 'test')", [$draft]);
        foreach (['openpost', 'transaktioner'] as $table) {
            StripeTestEnvironment::query("SELECT setval('pg_temp.s1_{$table}_seq', (SELECT coalesce(max(id), 0) + 1 FROM $table))");
        }
        ob_start();
        try {
            bogfor_rykker($draft);
        } finally {
            $output = ob_get_clean();
        }
        $this->assertSame(1, preg_match('/<script>alert\((".*")\);<\/script>/s', $output, $alert), 'a notice says why');
        $this->assertStringContainsString('ikke sendt endnu', (string)json_decode($alert[1]));
        $this->assertSame(2, (int)StripeFixtures::row('ordrer', $draft)['status'], 'the draft stays unbooked');
        $this->assertSame([], pg_fetch_all(StripeTestEnvironment::query('SELECT id FROM openpost WHERE refnr = $1', [$draft])) ?: []);
        $this->assertSame([], pg_fetch_all(StripeTestEnvironment::query('SELECT id FROM transaktioner WHERE ordre_id = $1', [$draft])) ?: []);
    }

}
