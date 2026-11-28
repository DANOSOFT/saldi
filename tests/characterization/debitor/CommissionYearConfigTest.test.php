<?php
// tests/characterization/debitor/CommissionYearConfigTest.test.php
//
// The day close's reclass of own commission (debitor/pos_ordre_includes/settleCommission/moveToOwnAccount.php) on a
// ledger that has a row per fiscal year for the item group (VG) and the VAT codes (SM), as real ledgers have.
// Finding p1-pos-close-F5: the reclass read any year's row of the item group and of the VAT codes. With another year's
// group pointing to a sales account without a VAT code, a sale of 100 + VAT 25 bought in at 60 was reclassified as a
// margin sale: VAT 33 instead of the 25 charged, commission 32 instead of 40. Now the rows of the fiscal year the
// commission is settled in are used; a row without a fiscal year (0 or NULL, older ledgers) serves every year, and only
// when the year has neither is the nearest other year's row used, an earlier year before a later one.
//
// Runs on PosTenderDayCloseTest's fixture (three fiscal years, the tills, the own commission set-up) and its child-process
// runner, support/run_pos_day_close.php, in a tenant of its own; that test's private helpers are called by reflection.
//
// History:
// 20261119 CL/ASR Created.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/PosTenderDayCloseTest.test.php';

final class CommissionYearConfigTest extends TestCase
{
    /** The commission item's group, as PosTenderDayCloseTest::setUpCommission() creates it in every year. */
    private const GROUP = 991;
    /** A sales account with VAT code 8 of its own, for the item group in the case of the sales account's VAT code. */
    private const GROUP_SALES = 58191;
    private const GROUP_SALES_VAT_CODE = 8;

    /** @var resource|\PgSql\Connection */
    private static $tenant;
    private static object $fixture;
    private static int $year;
    private static int $openYear;
    private static int $closedYear;
    private static int $vatAccount;
    private static int $till;
    private static int $sales;
    private static int $commission;
    private static int $commissionVat;
    private static int $marginSales;

    public static function setUpBeforeClass(): void
    {
        PosTenderDayCloseTest::setUpBeforeClass();
        $class = new ReflectionClass(PosTenderDayCloseTest::class);
        self::$tenant = $class->getStaticPropertyValue('tenant');
        self::$year = $class->getStaticPropertyValue('year');
        self::$openYear = $class->getStaticPropertyValue('openYear');
        self::$closedYear = $class->getStaticPropertyValue('closedYear');
        self::$vatAccount = $class->getStaticPropertyValue('vatAccount');
        self::$till = $class->getConstant('TILL');
        self::$sales = $class->getConstant('SALES');
        self::$commission = $class->getConstant('COMMISSION');
        self::$commissionVat = $class->getConstant('COMMISSION_VAT');
        self::$marginSales = $class->getConstant('MARGIN_SALES');
        self::$fixture = $class->newInstanceWithoutConstructor();
    }

    public static function tearDownAfterClass(): void
    {
        PosTenderDayCloseTest::tearDownAfterClass();
    }

    /** Calls one of PosTenderDayCloseTest's private helpers on its fixture. */
    private function fixture(string $method, mixed ...$args): mixed
    {
        return (new ReflectionMethod(PosTenderDayCloseTest::class, $method))->invoke(self::$fixture, ...$args);
    }

    /** Adds the account to the chart of every fiscal year. */
    private function accountEveryYear(int $kontonr, string $name, string $type, string $vatCode): void
    {
        foreach ([self::$closedYear, self::$openYear, self::$year] as $year) {
            CharacterizationEnv::rows(self::$tenant, 'INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, regnskabsaar, primo, saldo)
                VALUES ($1, $2, $3, $4, $5, 0, 0)', [$kontonr, $name, $type, $vatCode, $year]);
        }
    }

    /**
     * Replaces the commission item group's rows with these, inserted in this order, so a lookup without an order meets the
     * first one first.
     *
     * @param list<array{0:?int, 1:int}> $rows fiscal year (null or 0 for a row without one) and sales account (box4)
     */
    private function groupRows(array $rows): void
    {
        CharacterizationEnv::rows(self::$tenant, "DELETE FROM grupper WHERE art = 'VG' AND kodenr = " . self::GROUP);
        foreach ($rows as [$year, $salesAccount]) {
            CharacterizationEnv::rows(self::$tenant, "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box4, fiscal_year)
                VALUES ('Kommission', '', " . self::GROUP . ", 'VG', $1, $2)", [(string)$salesAccount, $year]);
        }
    }

    /**
     * Adds the rows of a sales VAT code at 25 %, inserted in this order.
     *
     * @param list<array{0:int, 1:int}> $rows fiscal year and VAT account (box1)
     */
    private function vatCodeRows(int $code, array $rows): void
    {
        foreach ($rows as [$year, $vatAccount]) {
            CharacterizationEnv::rows(self::$tenant, "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, fiscal_year)
                VALUES ('Salgsmoms', 'S', $1, 'SM', $2, '25', $3)", [$code, (string)$vatAccount, $year]);
        }
    }

    /**
     * The reclass of a commission of 40 (sale 100 ex VAT - cost 60) whose VAT 10, charged on the sale at 25 %, moves from
     * $fromVatAccount to $toVatAccount, as PosTenderDayCloseTest::commissionRows() lists it.
     *
     * @return list<array{0:string, 1:string, 2:int, 3:float, 4:float}>
     */
    private function reclass(string $date, int $till, int $salesAccount, int $fromVatAccount, int $toVatAccount): array
    {
        $rows = [
            [$date, "Kommisionssalg, Kasse $till", $salesAccount, 40.0, 0.0],
            [$date, "Kommisionssalg, Kasse $till", self::$commission, 0.0, 40.0],
            [$date, "Moms af kommisionssalg, Kasse $till", $fromVatAccount, 10.0, 0.0],
            [$date, "Moms af kommisionssalg, Kasse $till", $toVatAccount, 0.0, 10.0],
        ];
        usort($rows, static fn(array $a, array $b): int => [$a[2], $a[3]] <=> [$b[2], $b[3]]);
        return $rows;
    }

    /** Closes the till and checks that the close went through. */
    private function close(int $till): void
    {
        $close = $this->fixture('runner', 'close', (string)$till, $this->fixture('regnstart'), '0');
        self::assertFalse($close['exit'], json_encode($close));
    }

    /** The sale of 100 + VAT 25 on the item group's sales account is moved as charged: sales 60, commission 40, VAT 25. */
    private function assertMovedAsCharged(int $till, int $sale, string $date, int $salesAccount = 0): void
    {
        $salesAccount = $salesAccount ?: self::$sales;
        self::assertSame($this->reclass($date, $till, $salesAccount, self::$vatAccount, self::$vatAccount), $this->fixture('commissionRows', $till),
            'the commission 40 and the VAT 10 charged on it are moved with the item group of the year the commission is settled in');
        $expected = [self::$till => 125.0, $salesAccount => -60.0, self::$vatAccount => -25.0, self::$commission => -40.0];
        ksort($expected);
        self::assertSame($expected, $this->fixture('postings', null, $till), 'the VAT account holds the 25 charged on the sale');
        self::assertSame(4, $this->fixture('orderStatus', $sale));
    }

    public function testTheCommissionIsMovedWithTheItemGroupOfTheActiveYear(): void
    {
        // The finding's case. Before: the earlier years' row of the item group, whose sales account has no VAT code, made the
        // reclass treat today's sale as a margin sale: margin sales D 40, commission C 32, VAT C 8 on top of the 25 charged.
        $item = $this->fixture('setUpCommission');
        try {
            $this->accountEveryYear(self::$marginSales, 'Salg brugtmoms', 'D', '');
            $this->groupRows([[self::$closedYear, self::$marginSales], [self::$openYear, self::$marginSales], [self::$year, self::$sales]]);
            $sale = $this->fixture('commissionSale', 1, $item);

            $this->close(1);

            $this->assertMovedAsCharged(1, $sale, date('Y-m-d'));
        } finally {
            $this->fixture('removeCommission');
        }
    }

    public function testTheCommissionOfASaleFromBeforeTheYearSwitchIsMovedWithThatYearsItemGroup(): void
    {
        // Last year's sale was posted with VAT 25 through last year's item group; the group now has a sales account without a
        // VAT code in the active year. The commission is settled in last year, so last year's group applies. Before: another
        // year's row made it a margin sale in last year (VAT 33).
        $lastYear = (date('Y') - 1) . '-12-31';
        $item = $this->fixture('setUpCommission');
        try {
            $this->accountEveryYear(self::$marginSales, 'Salg brugtmoms', 'D', '');
            $this->groupRows([[self::$closedYear, self::$marginSales], [self::$year, self::$marginSales], [self::$openYear, self::$sales]]);
            $sale = $this->fixture('commissionSale', 2, $item, $lastYear);

            $this->close(2);

            $this->assertMovedAsCharged(2, $sale, $lastYear);
        } finally {
            $this->fixture('removeCommission');
        }
    }

    public function testTheCommissionAccountsVatCodeIsTheActiveYears(): void
    {
        // The commission account's VAT code 9 is booked on COMMISSION_VAT in the active year's chart, on the sales VAT account
        // in earlier years. Before: an earlier year's row moved the 10 back onto the sales VAT account (D 10, C 10).
        $item = $this->fixture('setUpCommission', 'S9');
        try {
            $this->accountEveryYear(self::$commissionVat, 'Salgsmoms kommission', 'S', '');
            $this->vatCodeRows(9, [[self::$closedYear, self::$vatAccount], [self::$openYear, self::$vatAccount], [self::$year, self::$commissionVat]]);
            $sale = $this->fixture('commissionSale', 3, $item);

            $this->close(3);

            $today = date('Y-m-d');
            self::assertSame($this->reclass($today, 3, self::$sales, self::$vatAccount, self::$commissionVat), $this->fixture('commissionRows', 3),
                'the VAT 10 charged on the commission moves to the account of the commission account\'s VAT code in the active year');
            $expected = [self::$till => 125.0, self::$sales => -60.0, self::$vatAccount => -15.0, self::$commission => -40.0, self::$commissionVat => -10.0];
            ksort($expected);
            self::assertSame($expected, $this->fixture('postings', null, 3), 'the VAT accounts hold the 25 charged on the sale: 15 + 10');
            self::assertSame(4, $this->fixture('orderStatus', $sale));
        } finally {
            $this->fixture('removeCommission');
        }
    }

    public function testTheSalesAccountsVatCodeIsTheActiveYears(): void
    {
        // The item group's sales account has VAT code 8, booked on the sales VAT account in the active year's chart and on
        // another account in earlier years. Before: an earlier year's row debited that other account 10, which the sale never
        // credited, and credited the sales VAT account 10 more than it was charged (35 instead of 25).
        $t = self::$tenant;
        $item = $this->fixture('setUpCommission', null, self::GROUP_SALES);
        try {
            $this->accountEveryYear(self::GROUP_SALES, 'Salg kommissionsvarer', 'D', 'S' . self::GROUP_SALES_VAT_CODE);
            $this->accountEveryYear(self::$commissionVat, 'Salgsmoms, tidligere konto', 'S', '');
            $this->vatCodeRows(self::GROUP_SALES_VAT_CODE, [[self::$closedYear, self::$commissionVat], [self::$openYear, self::$commissionVat], [self::$year, self::$vatAccount]]);
            $sale = $this->fixture('commissionSale', 5, $item);
            CharacterizationEnv::rows($t, 'UPDATE ordrelinjer SET bogf_konto = $1 WHERE ordre_id = $2', [self::GROUP_SALES, $sale]);

            $this->close(5);

            $this->assertMovedAsCharged(5, $sale, date('Y-m-d'), self::GROUP_SALES);
        } finally {
            $this->fixture('removeCommission');
            CharacterizationEnv::rows($t, "DELETE FROM grupper WHERE art = 'SM' AND kodenr = " . self::GROUP_SALES_VAT_CODE);
            CharacterizationEnv::rows($t, 'DELETE FROM kontoplan WHERE kontonr = $1', [self::GROUP_SALES]);
        }
    }

    /** @return array<string, array{0:?int, 1:int}> */
    public static function rowsWithoutAFiscalYear(): array
    {
        return ['fiscal_year 0' => [0, 6], 'fiscal_year NULL' => [null, 9]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rowsWithoutAFiscalYear')]
    public function testAnItemGroupRowWithoutAFiscalYearServesEveryYear(?int $noYear, int $till): void
    {
        // An older ledger's item group row without a fiscal year serves every year, also before a row of another year.
        // Before: the other years' row came first and made the sale a margin sale (VAT 33).
        $item = $this->fixture('setUpCommission');
        try {
            $this->accountEveryYear(self::$marginSales, 'Salg brugtmoms', 'D', '');
            $this->groupRows([[self::$closedYear, self::$marginSales], [self::$openYear, self::$marginSales], [$noYear, self::$sales]]);
            $sale = $this->fixture('commissionSale', $till, $item);

            $this->close($till);

            $this->assertMovedAsCharged($till, $sale, date('Y-m-d'));
        } finally {
            $this->fixture('removeCommission');
        }
    }

    public function testWithoutARowOfItsOwnTheYearUsesTheNearestEarlierYearsItemGroup(): void
    {
        // The active year has no row of the item group and there is none without a year: last year's row applies, not the
        // year before's. Before: the first row met, the year before's, made the sale a margin sale (VAT 33).
        $item = $this->fixture('setUpCommission');
        try {
            $this->accountEveryYear(self::$marginSales, 'Salg brugtmoms', 'D', '');
            $this->groupRows([[self::$closedYear, self::$marginSales], [self::$openYear, self::$sales]]);
            $sale = $this->fixture('commissionSale', 10, $item);

            $this->close(10);

            $this->assertMovedAsCharged(10, $sale, date('Y-m-d'));
        } finally {
            $this->fixture('removeCommission');
        }
    }

    public function testWithoutARowOfItsOwnAnEarlierYearComesBeforeALaterYear(): void
    {
        // Last year has no row of the item group: the year before's row applies before the active year's, as far from it.
        // Before: the first row met, the active year's, made last year's sale a margin sale (VAT 33).
        $lastYear = (date('Y') - 1) . '-12-31';
        $item = $this->fixture('setUpCommission');
        try {
            $this->accountEveryYear(self::$marginSales, 'Salg brugtmoms', 'D', '');
            $this->groupRows([[self::$year, self::$marginSales], [self::$closedYear, self::$sales]]);
            $sale = $this->fixture('commissionSale', 11, $item, $lastYear);

            $this->close(11);

            $this->assertMovedAsCharged(11, $sale, $lastYear);
        } finally {
            $this->fixture('removeCommission');
        }
    }
}
