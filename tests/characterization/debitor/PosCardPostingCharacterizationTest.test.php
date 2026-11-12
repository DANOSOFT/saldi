<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

// 20261112 CL/SS MB-49 POS sales posted per sale by the real bogfor()/bogfor_nu() on a fresh installed tenant.
//                A 0-kr item paid by card is a cash withdrawal: the card is debited and the cash account credited with the card amount.
//                A card payment that posts nothing (a card name without a card account) used to leave the sale at status 4 with no rows,
//                invisible to the cash count. It is now refused and rolled back.
final class PosCardPostingCharacterizationTest extends TestCase
{
    private const CASH_ACCOUNT = 58200;
    private const CARD_ACCOUNT = 58050;

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
        foreach ([self::CASH_ACCOUNT => 'Kasse', self::CARD_ACCOUNT => 'Dankort'] as $account => $name) {
            if (!CharacterizationEnv::one($t, 'SELECT id FROM kontoplan WHERE kontonr = $1 AND regnskabsaar = $2', [$account, $year])) {
                pg_query_params($t, "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, regnskabsaar, moms, saldo) VALUES ($1, $2, 'S', $3, '', 0)",
                    [$account, $name, $year]);
            }
        }
        pg_query_params($t, "DELETE FROM grupper WHERE art = 'POS' AND kodenr IN ('1', '2') AND fiscal_year = $1", [$year]);
        pg_query_params($t, "INSERT INTO grupper (beskrivelse, art, kodenr, fiscal_year, box1, box2, box4, box5, box6, box7, box9)
            VALUES ('POS', 'POS', '1', $1, '1', $2, '1', 'Dankort', $3, '1', 'on')", [$year, (string)self::CASH_ACCOUNT, (string)self::CARD_ACCOUNT]);
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
     * A settled POS sale with one line and one payment, as settlePOS.php leaves it before posting.
     */
    private static function sale(float $price, string $payment, float $amount): int
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
        return $id;
    }

    /**
     * Posts a sale in a child process.
     *
     * @return array{bogfor: string, status: int, rows: list<array{0:int, 1:float, 2:float}>}
     */
    private static function post(int $id): array
    {
        $res = CharacterizationEnv::runChild(__DIR__ . '/../support/run_pos_card_posting.php', [CharacterizationEnv::testDb(), $id]);
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = $lines ? json_decode((string)end($lines), true) : null;
        self::assertIsArray($json, "runner output: {$res['stdout']} {$res['stderr']}");
        $json['rows'] = array_map(static fn(array $row): array => [(int)$row[0], (float)$row[1], (float)$row[2]], $json['rows']);
        return $json;
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
}
