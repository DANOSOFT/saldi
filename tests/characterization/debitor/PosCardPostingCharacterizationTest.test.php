<?php
// 20261112 CL/SS MB-49 POS sales posted per sale by the real bogfor()/bogfor_nu() on a fresh installed tenant.
//                A 0-kr item paid by card is a cash withdrawal: the card is debited and the cash account credited with the card amount.
//                A card payment that posts nothing (a card name without a card account) used to leave the sale at status 4 with no rows, invisible to the cash count.
//                It is now refused and rolled back.
// 20261116 CL/SS MB-49: card sales the day-end count posts together write their rows with ordre_id 0, and are posted, not refused.
// 20261117 CL/SS MB-49: a row another checkout commits on the same till during the posting does not hide a sale that posted nothing.
// 20261118 CL/SS MB-49: an exchange whose lines cancel out posts on both sales accounts; an amount discount counts in kroner in pos_lines_amount().
// 20261118 CL/SS MB-49: at the day-end count, another sale's rows do not hide a withdrawal that posts nothing; card payments that cancel out within a sale pass.
//                The trigger is dropped even when the child's result fails its assertion.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class PosCardPostingCharacterizationTest extends TestCase
{
    private const CASH_ACCOUNT = 58200;
    private const CARD_ACCOUNT = 58050;
    private const SECOND_CARD_ACCOUNT = 58060;

    /** @var resource|\PgSql\Connection */
    private static $tenant;
    private static int $regnaar;
    private static int $itemId;
    private static int $salesAccount;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant(static::class);
        $t = self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        self::$regnaar = (int)CharacterizationEnv::one($t, "SELECT max(kodenr) AS ra FROM grupper WHERE art = 'RA'")['ra'];
        $year = self::$regnaar;
        foreach ([self::CASH_ACCOUNT => 'Kasse', self::CARD_ACCOUNT => 'Dankort', self::SECOND_CARD_ACCOUNT => 'Mastercard'] as $account => $name) {
            if (!CharacterizationEnv::one($t, 'SELECT id FROM kontoplan WHERE kontonr = $1 AND regnskabsaar = $2', [$account, $year])) {
                pg_query_params($t, "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, regnskabsaar, moms, saldo) VALUES ($1, $2, 'S', $3, '', 0)",
                    [$account, $name, $year]);
            }
        }
        pg_query_params($t, "DELETE FROM grupper WHERE art = 'POS' AND kodenr IN ('1', '2') AND fiscal_year = $1", [$year]);
        pg_query_params($t, "INSERT INTO grupper (beskrivelse, art, kodenr, fiscal_year, box1, box2, box4, box5, box6, box7, box9)
            VALUES ('POS', 'POS', '1', $1, '1', $2, '1', $3, $4, '1', 'on')",
            [$year, (string)self::CASH_ACCOUNT, "Dankort\tMastercard", self::CARD_ACCOUNT . "\t" . self::SECOND_CARD_ACCOUNT]);
        pg_query_params($t, "INSERT INTO grupper (beskrivelse, art, kodenr, fiscal_year, box6) VALUES ('POS', 'POS', '2', $1, '')", [$year]);
        $vg = CharacterizationEnv::one($t, "SELECT kodenr, box4 FROM grupper WHERE art = 'VG' AND fiscal_year = $1 AND box4 != '' ORDER BY kodenr LIMIT 1", [$year]);
        self::$salesAccount = (int)$vg['box4'];
        self::$itemId = CharacterizationEnv::nextId($t, 'varer');
        pg_query_params($t, "INSERT INTO varer (id, varenr, beskrivelse, enhed, salgspris, kostpris, gruppe) VALUES ($1, 'k', 'Kontanthævning', 'stk', 0, 0, $2)",
            [self::$itemId, $vg['kodenr']]);
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant) {
            pg_close(self::$tenant);
            self::$tenant = null;
            CharacterizationEnv::teardownTenant();
        }
    }

    /**
     * A settled POS sale with one line and its payments, as settlePOS.php leaves it before posting.
     *
     * @param list<array{0: string, 1: float}> $more further payments
     */
    private static function sale(float $price, string $payment, float $amount, array $more = []): int
    {
        $t = self::$tenant;
        $id = CharacterizationEnv::nextId($t, 'ordrer');
        $nr = (int)CharacterizationEnv::one($t, "SELECT coalesce(max(ordrenr), 0) + 1 AS nr FROM ordrer WHERE art = 'PO'")['nr'];
        $today = date('Y-m-d');
        pg_query_params($t, "INSERT INTO ordrer (id, art, status, ordrenr, ordredate, levdate, fakturadate, sum, moms, betalt, felt_1, felt_2, felt_3, felt_4,
            felt_5, ref, tidspkt, valuta, momssats, konto_id, kontonr, firmanavn, betalingsbet)
            VALUES ($1, 'PO', 2, $2, $3, $3, $3, $4, $5, $6, $7, $8, '', '0', '1', '', '12:00', 'DKK', 25, 0, '', '', 'Kontant')",
            [$id, $nr, $today, $price, $price * 0.25, $amount, $payment, (string)$amount]);
        pg_query_params($t, "INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, beskrivelse, antal, pris, rabat, posnr, momsfri, momssats, kostpris, leveres)
            VALUES ($1, $2, 'k', 'Kontanthævning', 1, $3, 0, 1, '', 25, 0, 1)", [$id, self::$itemId, $price]);
        pg_query_params($t, "INSERT INTO pos_betalinger (ordre_id, betalingstype, amount, valuta, valutakurs) VALUES ($1, $2, $3, 'DKK', 100)",
            [$id, $payment, $amount]);
        foreach ($more as [$type, $paid]) {
            pg_query_params($t, "INSERT INTO pos_betalinger (ordre_id, betalingstype, amount, valuta, valutakurs) VALUES ($1, $2, $3, 'DKK', 100)",
                [$id, $type, $paid]);
        }
        return $id;
    }

    /**
     * Posts a sale in a child process.
     *
     * @param int|string $id an ordre_id, or a comma list the day-end count posts together
     * @return array{bogfor: string, status: int, rows: list<array{0:int, 1:float, 2:float}>}
     */
    private static function post(int|string $id): array
    {
        $res = CharacterizationEnv::runChild(__DIR__ . '/../support/run_pos_card_posting.php', [CharacterizationEnv::testDb(), (string)$id]);
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = $lines ? json_decode((string)end($lines), true) : null;
        self::assertIsArray($json, "runner output: {$res['stdout']} {$res['stderr']}");
        $json['lines'] = (float)$json['lines'];
        $json['rows'] =array_map(static fn(array $row): array => [(int)$row[0], (float)$row[1], (float)$row[2]], $json['rows']);
        return $json;
    }

    /**
     * Posts sales together as the day-end count does, on a till that does not post each sale.
     *
     * @return array{bogfor: string, status: int, rows: list<array{0:int, 1:float, 2:float}>}
     */
    private static function postAtDayEnd(string $ids): array
    {
        $t = self::$tenant;
        pg_query_params($t, "UPDATE grupper SET box9 = '' WHERE art = 'POS' AND kodenr = '1' AND fiscal_year = $1", [self::$regnaar]);
        try {
            return self::post($ids);
        } finally {
            pg_query_params($t, "UPDATE grupper SET box9 = 'on' WHERE art = 'POS' AND kodenr = '1' AND fiscal_year = $1", [self::$regnaar]);
        }
    }

    /**
     * Cash withdrawal: a 0-kr item paid by card debits the card and credits the cash account with the card amount.
     */
    public function testACashWithdrawalPaidByCardDebitsTheCardAndCreditsTheCash(): void
    {
        $result = self::post(self::sale(0, 'Dankort', 400));
        self::assertSame('OK', $result['bogfor']);
        self::assertSame(4, $result['status']);
        self::assertSame([[self::CARD_ACCOUNT, 400.0, 0.0], [self::CASH_ACCOUNT, 0.0, 400.0]], $result['rows']);
    }

    /**
     * A card name without a card account posts nothing, so the sale is refused and stays unposted for the cash count.
     */
    public function testACardPaymentThatPostsNothingIsRefusedAndNotMarkedPosted(): void
    {
        $result = self::post(self::sale(0, 'Visa', 400));
        self::assertStringContainsString('Betalingen med Visa kan ikke bogføres', $result['bogfor']);
        self::assertNotSame(4, $result['status'], 'a sale with nothing posted must not get status 4');
        self::assertSame([], $result['rows']);
    }

    /**
     * Card payments that cancel each other out (Dankort 400 in, Mastercard 400 back) still write rows, so the sale is posted, not refused.
     */
    public function testCardPaymentsThatCancelOutArePostedNotRefused(): void
    {
        $result = self::post(self::sale(0, 'Dankort', 400, [['Mastercard', -400]]));
        self::assertSame('OK', $result['bogfor']);
        self::assertSame(4, $result['status']);
        self::assertContains([self::CARD_ACCOUNT, 400.0, 0.0], $result['rows']);
        self::assertNotSame([], array_filter($result['rows'], static fn(array $row): bool => $row[0] === self::SECOND_CARD_ACCOUNT));
    }

    /**
     * A normal card sale posts as before: card debit, sales and VAT credits.
     */
    public function testANormalCardSalePostsAsBefore(): void
    {
        $result = self::post(self::sale(100, 'Dankort', 125));
        self::assertSame('OK', $result['bogfor']);
        self::assertSame(4, $result['status']);
        self::assertContains([self::CARD_ACCOUNT, 125.0, 0.0], $result['rows']);
        self::assertContains([self::$salesAccount, 0.0, 100.0], $result['rows']);
        self::assertSame(array_sum(array_column($result['rows'], 1)), array_sum(array_column($result['rows'], 2)), 'debit and credit balance');
    }

    /**
     * A free 0-kr sale with no card amount, and one paid with cash, have nothing to post and are finished as before.
     */
    public function testAFreeSaleWithNothingToPostIsFinishedAsBefore(): void
    {
        foreach ([['Dankort', 0.0], ['Kontant', 0.0], ['Kontant', 400.0]] as [$payment, $amount]) {
            $result = self::post(self::sale(0, $payment, $amount));
            self::assertSame('OK', $result['bogfor'], "$payment $amount");
            self::assertSame(4, $result['status'], "$payment $amount");
            self::assertSame([], $result['rows'], "$payment $amount");
        }
    }

    /**
     * A till that posts at the day-end count posts its card sales together; the rows carry ordre_id 0, and the sales are posted, not refused.
     */
    public function testCardSalesPostedTogetherAtTheDayEndCountArePostedNotRefused(): void
    {
        $result = self::postAtDayEnd(self::sale(100, 'Dankort', 125) . ',' . self::sale(100, 'Dankort', 125));
        self::assertSame('OK', $result['bogfor']);
        self::assertSame(4, $result['status']);
        self::assertContains([self::CARD_ACCOUNT, 250.0, 0.0], $result['rows']);
        self::assertSame(array_sum(array_column($result['rows'], 1)), array_sum(array_column($result['rows'], 2)), 'debit and credit balance');
    }

    /**
     * At the day-end count, a Dankort sale's rows do not hide a cash withdrawal paid with a card that has no card account:
     * the withdrawal posts nothing, so the count is refused and rolled back.
     */
    public function testADayEndSaleWithRowsDoesNotHideAWithdrawalThatPostsNothing(): void
    {
        $result = self::postAtDayEnd(self::sale(100, 'Dankort', 125) . ',' . self::sale(0, 'Visa', 400));
        self::assertStringContainsString('Betalingen med Visa kan ikke bogføres', $result['bogfor']);
        self::assertNotSame(4, $result['status'], 'a refused count must not mark the sales posted');
        self::assertSame([], $result['rows']);
    }

    /**
     * At the day-end count, an exchange whose Visa payments cancel out (+125, -125) has nothing to lose, so it is posted, not refused.
     */
    public function testADayEndExchangeWhoseCardPaymentsCancelOutIsPosted(): void
    {
        [$otherItem, $otherAccount] = self::itemOnAnotherSalesAccount();
        $exchange = self::order([[self::$itemId, 1, 100, 0, ''], [$otherItem, -1, 100, 0, '']], 'Visa', 125);
        pg_query_params(self::$tenant, "INSERT INTO pos_betalinger (ordre_id, betalingstype, amount, valuta, valutakurs) VALUES ($1, 'Visa', -125, 'DKK', 100)", [$exchange]);
        $result = self::postAtDayEnd(self::sale(100, 'Dankort', 125) . ',' . $exchange);
        self::assertSame('OK', $result['bogfor']);
        self::assertSame(4, $result['status']);
        self::assertContains([$otherAccount, 100.0, 0.0], $result['rows']);
        self::assertSame(array_sum(array_column($result['rows'], 1)), array_sum(array_column($result['rows'], 2)), 'debit and credit balance');
    }

    /**
     * At the day-end count, a priced sale paid with a card that has no card account still posts on the cash account, as before.
     */
    public function testADayEndPricedSaleWithoutACardAccountPostsAsBefore(): void
    {
        $result = self::postAtDayEnd(self::sale(100, 'Dankort', 125) . ',' . self::sale(100, 'Visa', 125));
        self::assertSame('OK', $result['bogfor']);
        self::assertSame(4, $result['status']);
        self::assertContains([self::CARD_ACCOUNT, 125.0, 0.0], $result['rows']);
        self::assertContains([self::CASH_ACCOUNT, 125.0, 0.0], $result['rows']);
        self::assertSame(array_sum(array_column($result['rows'], 1)), array_sum(array_column($result['rows'], 2)), 'debit and credit balance');
    }

    /**
     * Another checkout on the same till commits a row while this sale is being posted. That row is not this sale's posting,
     * so a card payment that posted nothing is still refused.
     */
    public function testARowAnotherCheckoutCommitsOnTheTillMeanwhileDoesNotHideASaleThatPostedNothing(): void
    {
        $t = self::$tenant;
        $lock = 4911;
        $id = self::sale(0, 'Visa', 400);
        $child = null;
        $result = null;
        try {
            // Holds the posting at bogfor_nu()'s "update ordrelinjer set projekt='' where projekt is NULL", which runs after it starts and before its check.
            pg_query($t, 'CREATE FUNCTION mb49_hold() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
                IF current_query() LIKE $q$update ordrelinjer set projekt=\'\' where projekt is NULL%$q$ THEN PERFORM pg_advisory_xact_lock(' . $lock . '); END IF;
                RETURN NULL; END $$');
            pg_query($t, 'CREATE TRIGGER mb49_hold BEFORE UPDATE ON ordrelinjer FOR EACH STATEMENT EXECUTE FUNCTION mb49_hold()');
            // Session lock here; the child waits for it as a transaction lock inside its posting.
            pg_query($t, "SELECT pg_advisory_lock($lock)");
            $child = self::startPost($id);
            $waiting = false;
            for ($i = 0; $i < 300 && !$waiting; $i++) {
                usleep(100000);
                $waiting = (bool)CharacterizationEnv::one($t, "SELECT 1 AS w FROM pg_locks WHERE locktype = 'advisory' AND objid = $lock AND NOT granted");
            }
            self::assertTrue($waiting, 'the posting never reached the held step');
            $other = @pg_query_params($t, "INSERT INTO transaktioner (bilag, transdate, beskrivelse, kontonr, debet, kredit, kladde_id, afd, logdate, logtime, ordre_id, kasse_nr)
                VALUES (0, $1, 'Other checkout', $2, 50, 0, 0, 0, $1, '12:00', 0, 1)", [date('Y-m-d'), self::CASH_ACCOUNT]);
            self::assertNotFalse($other, pg_last_error($t));
        } finally {
            pg_query($t, "SELECT pg_advisory_unlock_all()");
            try {
                $result = $child ? self::finishPost($child) : null;
            } finally {
                pg_query($t, 'DROP TRIGGER IF EXISTS mb49_hold ON ordrelinjer');
                pg_query($t, 'DROP FUNCTION IF EXISTS mb49_hold()');
                pg_query($t, "DELETE FROM transaktioner WHERE beskrivelse = 'Other checkout'");
            }
        }
        self::assertStringContainsString('Betalingen med Visa kan ikke bogføres', $result['bogfor']);
        self::assertNotSame(4, $result['status'], 'a sale with nothing posted must not get status 4');
        self::assertSame([], $result['rows']);
    }

    /**
     * An exchange (+100 on one item, -100 on an item with another sales account) totals 0 and is paid with card 0.
     * Its lines carry an amount, so afslut() lets it through, and the posting writes balanced rows on both sales accounts.
     */
    public function testAnExchangeWhoseLinesCancelOutPostsOnBothSalesAccounts(): void
    {
        [$otherItem, $otherAccount] = self::itemOnAnotherSalesAccount();
        $result = self::post(self::order([[self::$itemId, 1, 100, 0, ''], [$otherItem, -1, 100, 0, '']], 'Dankort', 0));
        self::assertSame(200.0, $result['lines']);
        self::assertSame('OK', $result['bogfor']);
        self::assertSame(4, $result['status']);
        self::assertContains([self::$salesAccount, 0.0, 100.0], $result['rows']);
        self::assertContains([$otherAccount, 100.0, 0.0], $result['rows']);
        self::assertSame(array_sum(array_column($result['rows'], 1)), array_sum(array_column($result['rows'], 2)), 'debit and credit balance');
    }

    /**
     * An amount discount counts in kroner, as bogfor_nu() posts it: a 50 kr item with 50 kr off carries no amount,
     * and an exchange at 200 with 100 kr off each way carries 200.
     */
    public function testLineAmountsCountAnAmountDiscountInKroner(): void
    {
        [$otherItem] = self::itemOnAnotherSalesAccount();
        self::assertSame(0.0, self::post(self::order([[self::$itemId, 1, 50, 50, 'amount']], 'Dankort', 0))['lines']);
        self::assertSame(200.0, self::post(self::order([[self::$itemId, 1, 200, 100, 'amount'], [$otherItem, -1, 200, 100, 'amount']], 'Dankort', 0))['lines']);
    }

    /**
     * A second 0-kr item in a product group whose sales account differs from the first item's.
     *
     * @return array{0: int, 1: int} item id and its sales account
     */
    private static function itemOnAnotherSalesAccount(): array
    {
        $t = self::$tenant;
        $vg = CharacterizationEnv::one($t, "SELECT kodenr, box4 FROM grupper WHERE art = 'VG' AND fiscal_year = $1 AND box4 != '' AND box4 != $2 ORDER BY kodenr LIMIT 1",
            [self::$regnaar, (string)self::$salesAccount]);
        self::assertNotNull($vg, 'the tenant needs a second product group with its own sales account');
        $item = CharacterizationEnv::one($t, "SELECT id FROM varer WHERE varenr = 'k2'");
        if (!$item) {
            $item = ['id' => CharacterizationEnv::nextId($t, 'varer')];
            pg_query_params($t, "INSERT INTO varer (id, varenr, beskrivelse, enhed, salgspris, kostpris, gruppe) VALUES ($1, 'k2', 'Byttevare', 'stk', 0, 0, $2)",
                [$item['id'], $vg['kodenr']]);
        }
        return [(int)$item['id'], (int)$vg['box4']];
    }

    /**
     * A settled POS sale with the given lines that sums to 0, paid with one payment.
     *
     * @param list<array{0: int, 1: float, 2: float, 3: float, 4: string}> $lines item id, quantity, price, discount, discount type
     */
    private static function order(array $lines, string $payment, float $amount): int
    {
        $t = self::$tenant;
        $id = self::sale(0, $payment, $amount);
        pg_query_params($t, 'DELETE FROM ordrelinjer WHERE ordre_id = $1', [$id]);
        foreach ($lines as $n => [$item, $qty, $price, $discount, $type]) {
            pg_query_params($t, "INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, beskrivelse, antal, pris, rabat, rabatart, posnr, momsfri, momssats, kostpris, leveres)
                SELECT $1, id, varenr, beskrivelse, $2, $3, $4, $5, $6, '', 25, 0, $2 FROM varer WHERE id = $7", [$id, $qty, $price, $discount, $type, $n + 1, $item]);
        }
        return $id;
    }

    /**
     * Starts posting a sale in a child process without waiting for it.
     *
     * @return array{proc: resource, out: resource, err: resource}
     */
    private static function startPost(int $id): array
    {
        $err = tmpfile();
        $cmd = [PHP_BINARY, __DIR__ . '/../support/run_pos_card_posting.php', CharacterizationEnv::testDb(), (string)$id];
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => $err], $pipes);
        self::assertIsResource($proc, 'could not start child php');
        fclose($pipes[0]);
        return ['proc' => $proc, 'out' => $pipes[1], 'err' => $err];
    }

    /**
     * Waits for a child started by startPost() and returns its result like post().
     *
     * @param array{proc: resource, out: resource, err: resource} $child
     * @return array{bogfor: string, status: int, rows: list<array{0:int, 1:float, 2:float}>}
     */
    private static function finishPost(array $child): array
    {
        $stdout = (string)stream_get_contents($child['out']);
        fclose($child['out']);
        proc_close($child['proc']);
        rewind($child['err']);
        $stderr = (string)stream_get_contents($child['err']);
        fclose($child['err']);
        $lines = array_values(array_filter(explode("\n", trim($stdout))));
        $json = $lines ? json_decode((string)end($lines), true) : null;
        self::assertIsArray($json, "runner output: $stdout $stderr");
        $json['rows'] = array_map(static fn(array $row): array => [(int)$row[0], (float)$row[1], (float)$row[2]], $json['rows']);
        return $json;
    }
}
