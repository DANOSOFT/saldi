<?php
// 20261116 CDX/ASR Execute the reminder creation controller with missing customers and trailing batch separators.
// 20261126 CL/ASR Stripe port (#1134): the slice also starts with $fristIkkeUdloebet empty, as the page sets it above the slice (R2/R3 refusal, 20261105).
// 20261117 CDX/ASR Exercise the shared creation service inside the fixture transaction.
// 20261126 CL/ASR Stripe port (#1147): the test calls reminder_create_batch() instead of a slice of the page, so the $fristIkkeUdloebet set-up is gone.
//                 New test: the R2/R3 refusal of this line (20261105) moved into reminder_create_batch(), which returns the refused løbenr as 'not_due'.
// 20261126 ASR Review I12: creation takes the reminder advisory lock and no table lock on ordrer.
// 20261126 ASR Review I1: a debtor or chain that cannot be created is rolled back on its own and named in the notes; the rest of the batch is created.
// 20261126 ASR Review I2: a level whose next level exists gets no second one.
// 20261126 ASR Review I3: an automatic reminder that was not sent has no sending date and is not escalated.
// 20261126 ASR Review GPT-R-2: interest is calculated in the reminder's currency, on a 1st reminder and on a next level, and the next level's sum equals its lines.
// 20261126 ASR Review GPT-R-3: a new chain for an invoice whose closed chain had fees on 3 reminders, or a fee less than 10 days ago, is made without a fee, and the notice says why.
// 20261126 ASR Review GPT-G-7: a closed chain of three fee drafts that were never mailed or printed does not keep the fee off a new chain; the same chain mailed does.
// 20261126 ASR Review R3-G7-1: an R1 that "Ryk alle" booked and escalated without it going out counts with its mailed R2 and R3, so a new chain is made without a fee.
// 20261126 ASR Review CL2-1: a booked level without fee lines gets its next level from Ny rykker also when its sum is '0.000' (as the reminder page writes it), after its fee line was deleted, with a fee item priced 0, or with a sum left on it.
//              A level with a fee still needs its fee open item, and the next level lists it.
require_once __DIR__ . '/support/bootstrap.php';
require_once __DIR__ . '/support/reminderFixtures.php';
require_once __DIR__ . '/../../includes/stdFunc/dunningCutoff.php';
require_once __DIR__ . '/../../includes/reminderCreate.php';
require_once __DIR__ . '/../../includes/opdat_func/reminderAutomation.php';

final class ReminderCreationPageTest extends StripeDatabaseTestCase
{
    public function testMissingOrChangedReportLedgerIsRefused(): void
    {
        $this->assertFalse(reminder_request_ledger_matches(null, 'saldi_6'));
        $this->assertFalse(reminder_request_ledger_matches('saldi_1091', 'saldi_6'));
        $this->assertFalse(reminder_request_ledger_matches(['saldi_6'], 'saldi_6'));
        $this->assertTrue(reminder_request_ledger_matches('saldi_1091', 'saldi_1091'));
    }

    public function testSelectedBatchSkipsMissingDebtorsAndHandlesUnsetGroupsWithoutWarnings(): void
    {
        foreach (['ordrer', 'ordrelinjer'] as $table) {
            StripeTestEnvironment::query("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING ALL)");
            StripeTestEnvironment::query("CREATE TEMP SEQUENCE batch_{$table}_seq START 900000000");
            StripeTestEnvironment::query("ALTER TABLE pg_temp.$table ALTER COLUMN id SET DEFAULT nextval('pg_temp.batch_{$table}_seq')");
        }
        StripeTestEnvironment::query('CREATE TEMP TABLE stripe_payments (LIKE public.stripe_payments INCLUDING ALL)');   // the dunning hold joins it to ordrer by id
        StripeReminderFixtures::forms();
        StripeTestEnvironment::setting('stripe', 'reminder_format', 'classic');
        $first = StripeFixtures::debtor(['gruppe' => null]);
        $second = StripeFixtures::debtor(['gruppe' => 1, 'kontoansvarlig' => 999999999]);
        StripeFixtures::invoice($first);
        StripeFixtures::invoice($second);
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }, E_WARNING | E_NOTICE);
        try {
            $created = reminder_create_batch([$first, 999999999, $second], [], date('Y-m-d'), date('Y-m-d'), 'reminder-test');
            $legalNotes = $created['notes'];
        } finally {
            restore_error_handler();
        }
        $rows = pg_fetch_all(StripeTestEnvironment::query("SELECT konto_id,art,ref,tidspkt FROM ordrer WHERE art='R1' ORDER BY konto_id"));
        $this->assertCount(2, $rows);
        $this->assertSame([$first, $second], array_map('intval', array_column($rows, 'konto_id')));
        $this->assertSame('', $rows[1]['ref']);
        $this->assertNotEmpty($rows[0]['tidspkt']);
        $this->assertCount(1, $legalNotes);
        $this->assertStringContainsString('999999999', $legalNotes[0]);
        $this->assertSame('2', pg_fetch_result(StripeTestEnvironment::query("SELECT count(*) FROM ordrelinjer WHERE vare_id > 0"), 0, 0));
    }

    public function testNextLevelBeforeItsDeadlineIsReturnedAsNotDue(): void
    {
        foreach (['ordrer', 'ordrelinjer'] as $table) {
            StripeTestEnvironment::query("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING ALL)");
            StripeTestEnvironment::query("CREATE TEMP SEQUENCE due_{$table}_seq START 900000000");
            StripeTestEnvironment::query("ALTER TABLE pg_temp.$table ALTER COLUMN id SET DEFAULT nextval('pg_temp.due_{$table}_seq')");
        }
        StripeTestEnvironment::query('CREATE TEMP TABLE stripe_payments (LIKE public.stripe_payments INCLUDING ALL)');   // the dunning hold joins it to ordrer by id
        StripeReminderFixtures::forms();
        StripeTestEnvironment::setting('stripe', 'reminder_format', 'classic');
        if (!pg_fetch_assoc(StripeTestEnvironment::query("SELECT id FROM grupper WHERE art='DIV' AND kodenr='4'"))) {
            StripeFixtures::insert('grupper', ['art' => 'DIV', 'kodenr' => 4, 'fiscal_year' => 5]);
        }
        StripeTestEnvironment::query("UPDATE grupper SET box5='0',box6='15',box7='15' WHERE art='DIV' AND kodenr='4'");
        $runDate = '2026-02-20';
        $debtor = StripeFixtures::debtor(['gruppe' => 1]);
        // R1 of 8 Feb: 12 days old, so renteloven § 9 b (10 days) would allow R2, but with 15 days for R2 it is not due before 23 Feb.
        $early = StripeReminderFixtures::reminder($debtor, [StripeReminderFixtures::invoiceOn($debtor, '2026-01-01', ['udskriv_til' => 'email'])],
            ['date' => '2026-02-08', 'fee' => 0]);
        StripeTestEnvironment::query('UPDATE ordrer SET sum = NULL WHERE id = $1', [$early]);
        $result = reminder_create_batch([], [$early], $runDate, $runDate, 'reminder-test');
        $this->assertSame([], $result['ids']);
        $this->assertSame([(string)StripeFixtures::row('ordrer', $early)['ordrenr']], $result['not_due']);
        $this->assertSame('0', pg_fetch_result(StripeTestEnvironment::query("SELECT count(*) FROM ordrer WHERE art='R2'"), 0, 0));
        // R1 of 20 Jan: due (31 days), so R2 is created.
        $due = StripeReminderFixtures::reminder($debtor, [StripeReminderFixtures::invoiceOn($debtor, '2026-01-02', ['udskriv_til' => 'email'])],
            ['date' => '2026-01-20', 'fee' => 0]);
        // Without a fee line ny_rykker.php never writes the sum (NULL), so there is no fee open item to carry over.
        StripeTestEnvironment::query('UPDATE ordrer SET sum = NULL WHERE id = $1', [$due]);
        $result = reminder_create_batch([], [$due], $runDate, $runDate, 'reminder-test');
        $this->assertSame([], $result['not_due']);
        $this->assertCount(1, $result['ids']);
        $this->assertSame('R2', StripeFixtures::row('ordrer', $result['ids'][0])['art']);
    }

    /**
     * Temporary copies of ordrer/ordrelinjer with their own id sequences, so the test's reminders are its own.
     * An empty copy of stripe_payments too: the dunning hold joins it to ordrer by id, and the copies' ids start low.
     */
    private function isolateReminderTables(string $prefix): void
    {
        foreach (['ordrer', 'ordrelinjer'] as $table) {
            StripeTestEnvironment::query("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING ALL)");
            StripeTestEnvironment::query("CREATE TEMP SEQUENCE {$prefix}_{$table}_seq START 900000000");
            StripeTestEnvironment::query("ALTER TABLE pg_temp.$table ALTER COLUMN id SET DEFAULT nextval('pg_temp.{$prefix}_{$table}_seq')");
        }
        StripeTestEnvironment::query('CREATE TEMP TABLE stripe_payments (LIKE public.stripe_payments INCLUDING ALL)');
        StripeReminderFixtures::forms();
        StripeTestEnvironment::setting('stripe', 'reminder_format', 'classic');
    }

    /** @return resource|\PgSql\Connection A second connection to the test tenant. */
    private function secondConnection()
    {
        $parameters = [];
        foreach (['host' => 'SALDI_TEST_PGHOST', 'user' => 'SALDI_TEST_PGUSER', 'password' => 'SALDI_TEST_PGPASSWORD', 'dbname' => 'SALDI_TEST_DB'] as $key => $env) {
            $parameters[] = $key . "='" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)getenv($env)) . "'";
        }
        $second = pg_connect(implode(' ', $parameters), PGSQL_CONNECT_FORCE_NEW);
        $this->assertNotFalse($second);
        return $second;
    }

    public function testCreationTakesTheReminderLockInsteadOfLockingTheOrderTable(): void
    {
        $this->isolateReminderTables('lock');
        $debtor = StripeFixtures::debtor(['gruppe' => 1]);
        StripeFixtures::invoice($debtor);
        $created = reminder_create_batch([$debtor], [], date('Y-m-d'), date('Y-m-d'), 'reminder-test');
        $this->assertCount(1, $created['ids']);
        $tableLocks = StripeTestEnvironment::query("SELECT count(*) FROM pg_locks WHERE pid = pg_backend_pid() AND locktype = 'relation'
            AND relation = 'ordrer'::regclass AND mode = 'ShareRowExclusiveLock'");
        $this->assertSame('0', pg_fetch_result($tableLocks, 0, 0), 'no table lock that holds back order saves, invoicing and POS sales');
        $second = $this->secondConnection();
        try {
            $other = pg_query($second, "SELECT pg_try_advisory_lock(hashtext(current_database()), hashtext('reminder_create'))");
            $this->assertSame('f', pg_fetch_result($other, 0, 0), 'another reminder run waits until this transaction ends');
        } finally {
            pg_close($second);
        }
    }

    public function testADebtorOrChainThatFailsIsRolledBackAloneAndNamed(): void
    {
        $this->isolateReminderTables('alone');
        StripeTestEnvironment::query("UPDATE grupper SET box5='0',box6='10',box7='10' WHERE art='DIV' AND kodenr='4'");
        StripeTestEnvironment::query("DELETE FROM grupper WHERE art='DG' AND kodenr=977");
        StripeFixtures::insert('grupper', ['art' => 'DG', 'kodenr' => 977, 'box3' => 'XYZ', 'fiscal_year' => 5]);
        $noRate = StripeFixtures::debtor(['gruppe' => 977]);
        $fine = StripeFixtures::debtor(['gruppe' => 1]);
        StripeFixtures::invoice($noRate);
        StripeFixtures::invoice($fine);
        // An R1 whose fee was never booked: its next level would need the fee's open item.
        $unbooked = StripeReminderFixtures::reminder($fine, [StripeReminderFixtures::invoiceOn($fine, '2026-01-01', ['udskriv_til' => 'email'])],
            ['date' => '2026-01-20']);
        $runDate = '2026-02-20';
        $result = reminder_create_batch([$noRate, $fine], [5 => $unbooked], $runDate, $runDate, 'reminder-test');
        $this->assertCount(1, $result['ids'], 'the debtor that can be reminded gets its reminder');
        $this->assertSame((string)$fine, StripeFixtures::row('ordrer', $result['ids'][0])['konto_id']);
        $this->assertCount(2, $result['notes']);
        $kontonr = StripeFixtures::row('adresser', $noRate)['kontonr'];
        $this->assertStringContainsString("kunde $kontonr: Der mangler valutakurs for XYZ", $result['notes'][0]);
        $nr = StripeFixtures::row('ordrer', $unbooked)['fakturanr'];
        $this->assertStringContainsString("Ingen ny rykker for $nr: Det forrige rykkergebyr skal bogføres", $result['notes'][1]);
        $this->assertSame('0', pg_fetch_result(StripeTestEnvironment::query("SELECT count(*) FROM ordrer WHERE art = 'R2'"), 0, 0), 'the half-made R2 is rolled back');
        $this->assertSame('0', pg_fetch_result(StripeTestEnvironment::query("SELECT count(*) FROM ordrer WHERE art = 'R1' AND konto_id = $1", [$noRate]), 0, 0));
        $this->assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status($GLOBALS['connection']), 'the caller\'s transaction goes on');
    }

    public function testALevelWhoseNextLevelExistsGetsNoSecondOne(): void
    {
        $this->isolateReminderTables('twice');
        StripeTestEnvironment::query("UPDATE grupper SET box5='0',box6='10',box7='10' WHERE art='DIV' AND kodenr='4'");
        $debtor = StripeFixtures::debtor(['gruppe' => 1]);
        $r1 = StripeReminderFixtures::reminder($debtor, [StripeReminderFixtures::invoiceOn($debtor, '2026-01-01', ['udskriv_til' => 'email'])],
            ['date' => '2026-01-20', 'fee' => 0, 'status' => 4]);
        StripeTestEnvironment::query('UPDATE ordrer SET sum = NULL WHERE id = $1', [$r1]);
        $runDate = '2026-02-20';
        $first = reminder_create_batch([], [$r1], $runDate, $runDate, 'reminder-test');
        $this->assertCount(1, $first['ids']);
        $lines = pg_fetch_result(StripeTestEnvironment::query('SELECT count(*) FROM ordrelinjer WHERE ordre_id = $1', [$first['ids'][0]]), 0, 0);
        $second = reminder_create_batch([], [$r1], $runDate, $runDate, 'reminder-test');
        $this->assertSame([], $second['ids']);
        $nr = StripeFixtures::row('ordrer', $r1)['fakturanr'];
        $this->assertCount(1, $second['notes']);
        $this->assertStringContainsString("Ingen ny rykker for $nr: rykkeren har allerede en 2. rykker", $second['notes'][0]);
        $this->assertSame('1', pg_fetch_result(StripeTestEnvironment::query("SELECT count(*) FROM ordrer WHERE art = 'R2'"), 0, 0));
        $this->assertSame($lines, pg_fetch_result(StripeTestEnvironment::query('SELECT count(*) FROM ordrelinjer WHERE ordre_id = $1', [$first['ids'][0]]), 0, 0), 'the R2 keeps its lines');
    }

    public function testAnAutomaticReminderThatWasNotSentHasNoSendingDateAndIsNotEscalated(): void
    {
        $this->isolateReminderTables('unsent');
        reminder_automation_schema_ensure();
        StripeTestEnvironment::query("UPDATE grupper SET box5='0',box6='10',box7='10' WHERE art='DIV' AND kodenr='4'");
        $debtor = StripeFixtures::debtor(['gruppe' => 1]);
        $draft = StripeReminderFixtures::reminder($debtor, [StripeReminderFixtures::invoiceOn($debtor, '2026-01-01', ['udskriv_til' => 'email'])],
            ['date' => '2026-01-20', 'fee' => 0, 'status' => 4]);
        StripeTestEnvironment::query('UPDATE ordrer SET sum = NULL WHERE id = $1', [$draft]);
        StripeTestEnvironment::query("INSERT INTO reminder_automation_jobs(order_id,mode,state,actor) VALUES($1,'approval','approval','test')", [$draft]);
        $runDate = '2026-02-20';

        $this->assertSame('', reminder_sent_date($draft), 'drafted is not sent');
        $this->assertTrue(reminder_automation_unsent($draft));
        $this->assertSame('unsent', reminder_escalation_skip($draft, true, $runDate));
        $result = reminder_create_batch([], [$draft], $runDate, $runDate, 'reminder-test');
        $this->assertSame([], $result['ids']);
        $nr = StripeFixtures::row('ordrer', $draft)['fakturanr'];
        $this->assertSame(["Ingen ny rykker for $nr: den automatiske rykker er ikke sendt endnu. Godkend den i rykkerflowet, eller slet den."], $result['notes']);

        StripeTestEnvironment::query("UPDATE reminder_automation_jobs SET state='sent',sent_date='2026-01-22' WHERE order_id=$1", [$draft]);
        $this->assertSame('2026-01-22', reminder_sent_date($draft));
        $this->assertFalse(reminder_automation_unsent($draft));
        $this->assertCount(1, reminder_create_batch([], [$draft], $runDate, $runDate, 'reminder-test')['ids'], 'a sent reminder is escalated');
    }

    /** @return list<int> openpost.id of the fee open items (refnr = $previous) that the reminder lists. */
    private static function listedFeeItems(int $rykkerId, int $previous): array
    {
        return array_map('intval', array_column(pg_fetch_all(StripeTestEnvironment::query(
            'SELECT o.id FROM ordrelinjer l JOIN openpost o ON l.enhed = cast(o.id AS text) WHERE l.ordre_id = $1 AND o.refnr = $2',
            [$rykkerId, $previous])) ?: [], 'id'));
    }

    /**
     * Review CL2-1: Ny rykker on a booked R1 without fee lines. The reminder page (debitor/rykker.php) writes the sum of a
     * reminder it shows, '0.000' for one without fee lines, and that string is true in PHP. Such an R1 has no fee open item
     * (bogfor_nu() posts nothing for a sum of 0), so its next level was refused for good.
     */
    public function testABookedLevelWithoutFeeLinesGetsItsNextLevelWhateverItsSum(): void
    {
        $this->isolateReminderTables('feefree');
        StripeTestEnvironment::query("UPDATE grupper SET box5='0',box6='10',box7='10' WHERE art='DIV' AND kodenr='4'");
        $runDate = '2026-02-20';
        $r1 = function (float $fee) : array {
            $debtor = StripeFixtures::debtor(['gruppe' => 1]);
            $id = StripeReminderFixtures::reminder($debtor, [StripeReminderFixtures::invoiceOn($debtor, '2026-01-01', ['udskriv_til' => 'email'])],
                ['date' => '2026-01-20', 'fee' => $fee, 'status' => 4]);
            return [$debtor, $id];
        };
        // Made without a fee, then shown in the reminder page: sum '0.000'.
        [, $noFee] = $r1(0);
        $this->assertSame('0.000', StripeFixtures::row('ordrer', $noFee)['sum'], 'precondition: the sum the reminder page writes');
        // Its fee line deleted with "Slet valgte", and the page shown again.
        [, $deleted] = $r1(100);
        StripeTestEnvironment::query('DELETE FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$deleted]);
        StripeTestEnvironment::query('UPDATE ordrer SET sum = 0 WHERE id = $1', [$deleted]);
        // A fee item priced 0: a fee line of 0,00.
        [, $zeroPriced] = $r1(0);
        StripeFixtures::insert('ordrelinjer', ['ordre_id' => $zeroPriced, 'posnr' => 9, 'vare_id' => StripeReminderFixtures::$items['fee'],
            'varenr' => 'FEE0', 'beskrivelse' => 'Rykkergebyr', 'antal' => 1, 'pris' => 0, 'rabat' => 0, 'serienr' => '2026-01-20']);
        // A sum left on a level without fee lines, and no open item for it.
        [, $staleSum] = $r1(0);
        StripeTestEnvironment::query('UPDATE ordrer SET sum = 100 WHERE id = $1', [$staleSum]);
        // A level with a fee (made before any batch: the fixtures take the next free id, creation the temporary sequence).
        [$debtor, $withFee] = $r1(100);

        foreach (['no fee' => $noFee, 'fee line deleted' => $deleted, 'fee item priced 0' => $zeroPriced, 'sum left without fee lines' => $staleSum] as $case => $id) {
            $result = reminder_create_batch([], [$id], $runDate, $runDate, 'reminder-test');
            $this->assertSame([], $result['notes'], $case);
            $this->assertCount(1, $result['ids'], "$case: its 2nd reminder is made");
            $this->assertSame('R2', StripeFixtures::row('ordrer', $result['ids'][0])['art'], $case);
            $this->assertSame([], self::listedFeeItems((int)$result['ids'][0], $id), "$case: no earlier fee to list");
        }

        // A level with a fee still needs its fee open item first, and its next level lists it.
        $nr = StripeFixtures::row('ordrer', $withFee)['fakturanr'];
        $refused = reminder_create_batch([], [$withFee], $runDate, $runDate, 'reminder-test');
        $this->assertSame([], $refused['ids']);
        $this->assertSame(["Ingen ny rykker for $nr: Det forrige rykkergebyr skal bogføres før næste rykker."], $refused['notes']);
        $feeItem = StripeFixtures::insert('openpost', ['konto_id' => $debtor, 'konto_nr' => StripeFixtures::row('adresser', $debtor)['kontonr'],
            'faktnr' => $nr, 'refnr' => $withFee, 'amount' => 100, 'beskrivelse' => 'Gebyr mm. fra tidligere rykker', 'udlignet' => '0',
            'transdate' => '2026-01-20', 'kladde_id' => 0, 'projekt' => '']);
        $escalated = reminder_create_batch([], [$withFee], $runDate, $runDate, 'reminder-test');
        $this->assertSame([], $escalated['notes']);
        $this->assertCount(1, $escalated['ids']);
        $this->assertSame([$feeItem], self::listedFeeItems((int)$escalated['ids'][0], $withFee), 'the R2 lists the R1 fee');
    }
    /** EUR at 750 (DKK per 100 EUR) from 2000, as the reminder's rate. */
    private static function euroRate(): void
    {
        StripeTestEnvironment::query("DELETE FROM valuta WHERE gruppe IN (SELECT cast(kodenr AS int) FROM grupper WHERE art = 'VK' AND box1 = 'EUR')");
        StripeTestEnvironment::query("DELETE FROM grupper WHERE art = 'VK' AND box1 = 'EUR'");
        StripeFixtures::insert('grupper', ['art' => 'VK', 'kodenr' => 91, 'box1' => 'EUR', 'beskrivelse' => 'Euro', 'fiscal_year' => 5]);
        StripeTestEnvironment::query("INSERT INTO valuta (gruppe, valdate, kurs) VALUES (91, '2000-01-01', 750)");
    }

    /** @return array<string, float> The reminder's interest and fee line prices and its sum. */
    private static function reminderAmounts(int $rykkerId): array
    {
        $out = ['interest' => 0.0, 'fee' => 0.0, 'sum' => (float)StripeFixtures::row('ordrer', $rykkerId)['sum']];
        $q = StripeTestEnvironment::query('SELECT vare_id, pris FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$rykkerId]);
        while ($line = pg_fetch_assoc($q)) {
            $out[(int)$line['vare_id'] === StripeReminderFixtures::$items['interest'] ? 'interest' : 'fee'] += (float)$line['pris'];
        }
        return $out;
    }

    public function testInterestOnAnOpenItemInAnotherCurrencyIsInTheRemindersCurrency(): void
    {
        $this->isolateReminderTables('currency');
        StripeTestEnvironment::query("UPDATE grupper SET box3 = 'DKK' WHERE art = 'DG' AND kodenr = '1'");
        StripeTestEnvironment::query("UPDATE formularer SET str = 1 WHERE beskrivelse = 'GEBYR' AND formular = 6");   // 1 % a month
        $debtor = StripeFixtures::debtor(['gruppe' => 1]);
        $invoice = StripeFixtures::invoice($debtor, ['udskriv_til' => 'email']);
        // EUR 1.000,00 at 750: 7.500,00 kr., due a month before the reminder.
        StripeTestEnvironment::query("UPDATE openpost SET amount = 1000, valuta = 'EUR', valutakurs = 750, forfaldsdate = '2026-01-10' WHERE id = $1",
            [StripeReminderFixtures::openpost($invoice)['id']]);
        $result = reminder_create_batch([$debtor], [], '2026-02-05', '2026-02-05', 'reminder-test');
        $this->assertCount(1, $result['ids']);
        $r1 = StripeFixtures::row('ordrer', $result['ids'][0]);
        $this->assertSame('DKK', $r1['valuta'], 'precondition: a reminder in kroner');
        $amounts = self::reminderAmounts((int)$r1['id']);
        $this->assertEqualsWithDelta(75.0, $amounts['interest'], 0.001, '1 % of 7.500,00 kr., not of 1.000,00');
        $this->assertEqualsWithDelta(100.0, $amounts['fee'], 0.001);
        $this->assertEqualsWithDelta($amounts['interest'] + $amounts['fee'], $amounts['sum'], 0.001, 'the sum is its lines');
    }

    public function testNextLevelInterestAndSumAreInTheRemindersCurrency(): void
    {
        $this->isolateReminderTables('nextcurrency');
        self::euroRate();
        StripeTestEnvironment::query("UPDATE grupper SET box5='0',box6='0',box7='0' WHERE art='DIV' AND kodenr='4'");
        StripeTestEnvironment::query("UPDATE formularer SET str = 1 WHERE beskrivelse = 'GEBYR' AND formular = 7");   // 1 % a month on a 2nd reminder
        $debtor = StripeFixtures::debtor(['gruppe' => 1]);
        $invoice = StripeReminderFixtures::invoiceOn($debtor, '2025-12-01', ['udskriv_til' => 'email']);
        StripeTestEnvironment::query('UPDATE openpost SET amount = 750 WHERE id = $1', [StripeReminderFixtures::openpost($invoice)['id']]);   // 750,00 kr. = EUR 100,00
        // A 1st reminder in EUR without fee or interest (no fee open item to carry over).
        $r1 = StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-01-20', 'fee' => 0, 'valuta' => 'EUR', 'valutakurs' => 750]);
        StripeTestEnvironment::query('UPDATE ordrer SET sum = NULL WHERE id = $1', [$r1]);
        $result = reminder_create_batch([], [$r1], '2026-02-25', '2026-02-25', 'reminder-test');
        $this->assertSame([], $result['notes']);
        $this->assertCount(1, $result['ids']);
        $amounts = self::reminderAmounts($result['ids'][0]);
        // Two months at 1 % on EUR 100,00 (compound): EUR 2,01. The fee item's 100 kr. is EUR 13,33.
        $this->assertEqualsWithDelta(2.01, $amounts['interest'], 0.001, 'interest on EUR 100,00, not on 750');
        $this->assertEqualsWithDelta(13.33, $amounts['fee'], 0.001);
        $this->assertEqualsWithDelta($amounts['interest'] + $amounts['fee'], $amounts['sum'], 0.001, 'the sum is its lines');
    }
    /** @return int Lines with an item (fee or interest) on the reminder. */
    private static function itemLines(int $rykkerId): int
    {
        return (int)pg_fetch_result(StripeTestEnvironment::query('SELECT count(*) FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0', [$rykkerId]), 0, 0);
    }

    public function testANewChainAfterThreeFeesForTheInvoiceIsMadeWithoutAFee(): void
    {
        $this->isolateReminderTables('claimlimit');
        StripeTestEnvironment::query("UPDATE grupper SET box5='0',box6='0',box7='0' WHERE art='DIV' AND kodenr='4'");
        $debtor = StripeFixtures::debtor(['gruppe' => 1]);
        $invoice = StripeReminderFixtures::invoiceOn($debtor, '2025-12-01', ['udskriv_til' => 'email']);
        $nr = StripeFixtures::row('ordrer', $invoice)['fakturanr'];
        $r = ['debtor' => $debtor, 'invoice' => $invoice, 'reminder' => StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-01-05'])];
        $chain = [$r['reminder'], StripeReminderFixtures::nextLevel($r, 2, '2026-01-20'), StripeReminderFixtures::nextLevel($r, 3, '2026-02-05')];
        StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on', status = 4 WHERE id = ANY($1::int[])", ['{' . implode(',', $chain) . '}']);

        $result = reminder_create_batch([$debtor], [], '2026-03-01', '2026-03-01', 'reminder-test');
        $this->assertCount(1, $result['ids'], 'the unpaid invoice is reminded again');
        $again = (int)$result['ids'][0];
        $this->assertSame(0, self::itemLines($again), 'without a fee');
        $againNr = StripeFixtures::row('ordrer', $again)['fakturanr'];
        $this->assertSame(["Rykker $againNr: Der er allerede opkrævet rykkergebyr for 3 rykkere for faktura $nr (renteloven § 9 b). Rykkeren er oprettet uden gebyr."],
            $result['notes']);
        $this->assertSame('', reminder_legal_check($again, '2026-03-01'), 'it may be sent');

        // Its next level gets no fee either.
        $next = reminder_create_batch([], [$again], '2026-03-15', '2026-03-15', 'reminder-test');
        $this->assertCount(1, $next['ids']);
        $this->assertSame(0, self::itemLines((int)$next['ids'][0]));
        $this->assertStringContainsString('Rykkeren er oprettet uden gebyr.', implode("\n", $next['notes']));
    }

    public function testANewChainAfterThreeFeeDraftsThatWereNeverSentGetsItsFee(): void
    {
        $this->isolateReminderTables('claimdrafts');
        require_once __DIR__ . '/../../includes/opdat_func/reminderDispatch.php';
        reminder_dispatch_schema_ensure('postgresql');
        reminder_dispatch_since_ensure('postgresql');   // every reminder of this test is made while the dispatch day is recorded
        StripeTestEnvironment::query("UPDATE grupper SET box5='0',box6='0',box7='0' WHERE art='DIV' AND kodenr='4'");
        $made = function (array $dates, bool $sent): array {
            $debtor = StripeFixtures::debtor(['gruppe' => 1]);
            $invoice = StripeReminderFixtures::invoiceOn($debtor, '2025-12-01', ['udskriv_til' => 'email']);
            $r = ['debtor' => $debtor, 'invoice' => $invoice, 'reminder' => StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => $dates[0]])];
            $chain = [$r['reminder'], StripeReminderFixtures::nextLevel($r, 2, $dates[1]), StripeReminderFixtures::nextLevel($r, 3, $dates[2])];
            if ($sent) {
                foreach ($chain as $id) reminder_dispatch_record($id, 'classic', (string)StripeFixtures::row('ordrer', $id)['fakturadate']);
            }
            StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on', status = 4 WHERE id = ANY($1::int[])", ['{' . implode(',', $chain) . '}']);
            return [$debtor, StripeFixtures::row('ordrer', $invoice)['fakturanr']];
        };
        // Both chains before any creation: the fixtures take the next free id, creation the temporary sequence.
        [$drafted] = $made(['2026-01-05', '2026-01-20', '2026-02-05'], false);
        [$reminded, $nr] = $made(['2026-01-05', '2026-01-20', '2026-02-05'], true);

        // Three drafts with a fee, made and closed without being mailed or printed: the new chain gets its fee.
        $result = reminder_create_batch([$drafted], [], '2026-03-01', '2026-03-01', 'reminder-test');
        $this->assertCount(1, $result['ids']);
        $this->assertSame([], $result['notes']);
        $this->assertSame(1, self::itemLines((int)$result['ids'][0]), 'the fee line');
        $this->assertSame('', reminder_legal_check((int)$result['ids'][0], '2026-03-01'));

        // The same chain mailed: the new chain is made without a fee, as before.
        $again = reminder_create_batch([$reminded], [], '2026-03-01', '2026-03-01', 'reminder-test');
        $this->assertCount(1, $again['ids']);
        $this->assertSame(0, self::itemLines((int)$again['ids'][0]), 'without a fee');
        $againNr = StripeFixtures::row('ordrer', (int)$again['ids'][0])['fakturanr'];
        $this->assertSame(["Rykker $againNr: Der er allerede opkrævet rykkergebyr for 3 rykkere for faktura $nr (renteloven § 9 b). Rykkeren er oprettet uden gebyr."],
            $again['notes']);
    }

    /**
     * "Ryk alle" (reminder_create_batch() with $bookPrevious, as debitor/ny_rykker.php calls it) books an R1 that was never
     * mailed or printed and escalates it; its next levels go out. includes/openpost.php is loaded for the booking, so the test
     * runs in its own process (see ReminderAutomationTest).
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testALevelBookedByRykAlleWithoutGoingOutCountsTowardsTheFeesOfTheNextChain(): void
    {
        $this->isolateReminderTables('claimbooked');
        foreach (['openpost', 'transaktioner'] as $table) {   // the booking's open items and postings get ids of their own
            StripeTestEnvironment::query("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING ALL)");
            StripeTestEnvironment::query("CREATE TEMP SEQUENCE claimbooked_{$table}_seq START 900000000");
            StripeTestEnvironment::query("ALTER TABLE pg_temp.$table ALTER COLUMN id SET DEFAULT nextval('pg_temp.claimbooked_{$table}_seq')");
        }
        require_once __DIR__ . '/../../includes/opdat_func/reminderDispatch.php';
        reminder_dispatch_schema_ensure('postgresql');
        reminder_dispatch_since_ensure('postgresql');   // every reminder of this test is made while the dispatch day is recorded
        StripeTestEnvironment::query("UPDATE grupper SET box5='0',box6='0',box7='0' WHERE art='DIV' AND kodenr='4'");
        // Fiscal year 5 = 2026, with the fee item's group and the debtor group of that year.
        StripeTestEnvironment::query("DELETE FROM grupper WHERE art='RA'");
        StripeFixtures::insert('grupper', ['art' => 'RA', 'kodenr' => 5, 'box1' => '01', 'box2' => '2026', 'box3' => '12', 'box4' => '2026', 'box5' => 'on', 'fiscal_year' => 5]);
        StripeTestEnvironment::query("UPDATE grupper SET box8='',box4='1000',fiscal_year=5 WHERE art='VG' AND kodenr=1");
        StripeTestEnvironment::query("UPDATE grupper SET box2='56000',box3='DKK',fiscal_year=5 WHERE art='DG' AND kodenr=1");
        $GLOBALS['regnaar'] = '5';
        $debtor = StripeFixtures::debtor(['gruppe' => 1]);
        $invoice = StripeReminderFixtures::invoiceOn($debtor, '2025-12-01', ['udskriv_til' => 'email']);
        $nr = StripeFixtures::row('ordrer', $invoice)['fakturanr'];
        $r1 = StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-01-05']);   // made, never mailed or printed

        // "Ryk alle" 20.01: R1 is booked (its fee open item and postings), closed and escalated; R2 lists the fee and is mailed.
        $first = reminder_create_batch([], [$r1], '2026-01-20', '2026-01-20', 'reminder-test', true);
        $this->assertSame([], $first['notes']);
        $this->assertCount(1, $first['ids']);
        $r2 = (int)$first['ids'][0];
        $booked = StripeFixtures::row('ordrer', $r1);
        $this->assertSame(['4', 'on'], [$booked['status'], $booked['betalt']], 'R1 is booked and closed');
        $fee = pg_fetch_assoc(StripeTestEnvironment::query('SELECT id, amount FROM openpost WHERE refnr = $1', [$r1]));
        $this->assertSame('100.000', $fee['amount'] ?? null, "R1's fee open item");
        $this->assertSame('1', pg_fetch_result(StripeTestEnvironment::query('SELECT count(*) FROM ordrelinjer WHERE ordre_id = $1 AND enhed = $2', [$r2, (string)$fee['id']]), 0, 0),
            'R2 lists it');
        $this->assertSame('', reminder_dispatch_date($r1), 'precondition: R1 itself did not go out');
        reminder_dispatch_record($r2, 'classic', '2026-01-20');

        // "Ryk alle" 05.02: R2 is booked and escalated; R3 is mailed, and the chain is closed with Afslut while the invoice is unpaid.
        $second = reminder_create_batch([], [$r2], '2026-02-05', '2026-02-05', 'reminder-test', true);
        $this->assertSame([], $second['notes']);
        $this->assertCount(1, $second['ids']);
        $r3 = (int)$second['ids'][0];
        $this->assertSame(1, self::itemLines($r3), 'R3 has its fee');
        reminder_dispatch_record($r3, 'classic', '2026-02-05');
        StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on' WHERE id = $1", [$r3]);

        // A new chain on 10.03: fees on three reminders were claimed for the invoice, so it is made without a fee.
        $again = reminder_create_batch([$debtor], [], '2026-03-10', '2026-03-10', 'reminder-test');
        $this->assertCount(1, $again['ids']);
        $new = (int)$again['ids'][0];
        $this->assertSame(0, self::itemLines($new), 'without a fee');
        $newNr = StripeFixtures::row('ordrer', $new)['fakturanr'];
        $this->assertContains("Rykker $newNr: Der er allerede opkrævet rykkergebyr for 3 rykkere for faktura $nr (renteloven § 9 b). Rykkeren er oprettet uden gebyr.",
            $again['notes']);
        $this->assertSame(3, reminder_claim_fee_history($new)['count']);
    }

    public function testANewChainLessThanTenDaysAfterAFeeForTheInvoiceIsMadeWithoutAFee(): void
    {
        $this->isolateReminderTables('claimspacing');
        $debtor = StripeFixtures::debtor(['gruppe' => 1]);
        $invoice = StripeReminderFixtures::invoiceOn($debtor, '2025-12-01', ['udskriv_til' => 'email']);
        $nr = StripeFixtures::row('ordrer', $invoice)['fakturanr'];
        $closed = StripeReminderFixtures::reminder($debtor, [$invoice], ['date' => '2026-02-25']);
        // Ten days before: the fee is put on as before.
        $debtor2 = StripeFixtures::debtor(['gruppe' => 1]);
        $invoice2 = StripeReminderFixtures::invoiceOn($debtor2, '2025-12-01', ['udskriv_til' => 'email']);
        $closed2 = StripeReminderFixtures::reminder($debtor2, [$invoice2], ['date' => '2026-02-19']);
        StripeTestEnvironment::query("UPDATE ordrer SET betalt = 'on', status = 4 WHERE id IN ($1, $2)", [$closed, $closed2]);

        $result = reminder_create_batch([$debtor], [], '2026-03-01', '2026-03-01', 'reminder-test');
        $this->assertCount(1, $result['ids']);
        $this->assertSame(0, self::itemLines((int)$result['ids'][0]));
        $this->assertStringContainsString("Faktura $nr fik en rykker med gebyr den 25.02.2026. En ny rykker med gebyr kan tidligst sendes den 07.03.2026", $result['notes'][0]);
        $this->assertStringEndsWith('Rykkeren er oprettet uden gebyr.', $result['notes'][0]);

        $later = reminder_create_batch([$debtor2], [], '2026-03-01', '2026-03-01', 'reminder-test');
        $this->assertSame([], $later['notes']);
        $this->assertSame(1, self::itemLines((int)$later['ids'][0]), 'the fee line');
    }
}
