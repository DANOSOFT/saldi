<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

// 20261026 CL/MJ SD-772 (M-PREP for S12) Pins opret_posordre() by running it, before S12 edits the
//                POS creation path. PosSaleHeaderTest (SD-758) reads its INSERT out of the shipped
//                source as text, because the function writes a lock file, fetches shop orders,
//                prints redirects and exit()s; in a child process (support/run_opret_posordre.php)
//                none of that is in the way, so the sale it writes is read back from the row.
//                Covered here and nowhere else: the double-click lock. Synthetic data, no tenant.
// 20261027 CL/LH SD-772 review: the spec file is the one tempnam() created (no orphan per test), and
//                the lock is measured from the second the call started, so a clock tick during
//                the call cannot turn the "now + 1" pin red.

final class OpretPosordreCharacterizationTest extends TestCase
{
    private const REGNAAR = 2026;

    /** Departments and brands per cash register, tab-separated, as the settings page stores them. */
    private const DEPARTMENTS = "5\t6\t7";
    private const BRANDS = "havemoebelland\tudehygge";

    private string $specFile = '';

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is required for the isolated POS fixtures.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->specFile !== '' && file_exists($this->specFile)) {
            unlink($this->specFile);
        }
    }

    /**
     * Creates one sale and returns the child's report.
     *
     * @return array{result: ?int, reached: string, tables: array, sql: list<string>,
     *               calls: list<array>, warnings: list<string>, printed: string, lock: ?int,
     *               elapsed: ?int}
     */
    private function create(array $spec): array
    {
        $this->specFile = (string)tempnam(sys_get_temp_dir(), 'sd772opret');
        file_put_contents($this->specFile, json_encode($this->withDefaults($spec), JSON_THROW_ON_ERROR));

        $child = CharacterizationEnv::runChild(__DIR__ . '/support/run_opret_posordre.php', [$this->specFile]);
        self::assertSame('', $child['stderr'], 'the runner wrote to stderr: ' . $child['stderr']);

        $report = json_decode($child['stdout'], true);
        self::assertIsArray($report, 'the runner did not report JSON: ' . $child['stdout']);
        return $report;
    }

    /** Three terminals: departments 5, 6 and 7, brands on the first two only. */
    private function withDefaults(array $spec): array
    {
        $spec += ['kasse' => 1, 'konto_id' => 0, 'order_number' => 7001];
        $spec['vars'] = ($spec['vars'] ?? []) + ['regnaar' => self::REGNAAR, 'brugernavn' => 'kasse1',
            'bruger_id' => 7];
        $year = (string)self::REGNAAR;
        $supplied = $spec['grupper'] ?? [];
        $defaults = [
            // box3 is the department per terminal, box7 the VAT code per terminal.
            ['art' => 'POS', 'kodenr' => '1', 'fiscal_year' => $year, 'box3' => self::DEPARTMENTS,
                'box7' => "2\t2\t2"],
            // box4 is the payment terminal's address, box13 the default table number.
            ['art' => 'POS', 'kodenr' => '2', 'fiscal_year' => $year, 'box4' => '', 'box13' => ''],
            // kodenr 4 is POS_SALE_SETTINGS_KODENR: the brand per terminal.
            ['art' => 'POS', 'kodenr' => '4', 'fiscal_year' => $year, 'box1' => self::BRANDS],
            ['art' => 'SM', 'kodenr' => '2', 'box2' => '25'],
        ];
        $key = static fn(array $row) => ($row['art'] ?? '') . '/' . ($row['kodenr'] ?? '');
        $keys = array_map($key, $supplied);
        $kept = array_filter($defaults, static fn(array $row) => !in_array($key($row), $keys, true));
        $spec['grupper'] = array_merge(array_values($kept), $supplied);
        $spec['formularer'] = $spec['formularer'] ?? [
            ['formular' => 1, 'art' => '3', 'sprog' => 'havemoebelland'],
            ['formular' => 1, 'art' => '3', 'sprog' => 'udehygge'],
        ];
        return $spec;
    }

    private function sale(array $report): array
    {
        self::assertCount(1, $report['tables']['ordrer'], 'expected exactly one sale');
        return $report['tables']['ordrer'][0];
    }

    private function calledFunctions(array $report): array
    {
        return array_map(static fn(array $call) => $call[0], $report['calls']);
    }

    // ------------------------------------------------- the sale the function actually writes

    public function testANewSaleIsWrittenAsAnOpenPosOrderAndItsIdReturned(): void
    {
        $report = $this->create([]);
        $sale = $this->sale($report);

        self::assertSame('PO', $sale['art']);
        self::assertSame('0', (string)$sale['status'], 'a new sale is open');
        self::assertSame('7001', (string)$sale['ordrenr']);
        self::assertSame((int)$sale['id'], $report['result'], 'the new sale id is what the caller gets');
    }

    public function testTheSaleCarriesTheTerminalsDepartmentAndBrand(): void
    {
        $report = $this->create(['kasse' => 1]);
        $sale = $this->sale($report);

        self::assertSame(5, (int)$sale['afd'], 'the first terminal books to department 5');
        self::assertSame('havemoebelland', $sale['sprog'], 'the brand is the order form variant');
    }

    public function testEachTerminalGetsItsOwnDepartmentAndBrand(): void
    {
        $report = $this->create(['kasse' => 2]);
        $sale = $this->sale($report);

        self::assertSame(6, (int)$sale['afd']);
        self::assertSame('udehygge', $sale['sprog']);
        self::assertSame('2', (string)$sale['felt_5'], 'felt_5 is the till the sale was rung up on');
    }

    public function testATerminalWithNoBrandConfiguredStillGetsItsDepartment(): void
    {
        $report = $this->create(['kasse' => 3]);
        $sale = $this->sale($report);

        self::assertSame(7, (int)$sale['afd']);
        self::assertSame('', $sale['sprog'], 'only two of the three terminals have a brand');
    }

    public function testAnUnconfiguredInstallationWritesNeitherDepartmentNorBrand(): void
    {
        $report = $this->create([
            'grupper' => [
                ['art' => 'POS', 'kodenr' => '1', 'fiscal_year' => (string)self::REGNAAR,
                    'box3' => '', 'box7' => ''],
                ['art' => 'POS', 'kodenr' => '4', 'fiscal_year' => (string)self::REGNAAR, 'box1' => ''],
            ],
        ]);
        $sale = $this->sale($report);

        self::assertSame(0, (int)$sale['afd']);
        self::assertSame('', $sale['sprog']);
        self::assertSame('0', (string)$sale['momssats'], 'no VAT code for this till means no VAT');
    }

    public function testATerminalBeyondTheConfiguredListGetsNothingButIsNotSilentAboutIt(): void
    {
        $report = $this->create(['kasse' => 9]);
        $sale = $this->sale($report);

        self::assertSame(0, (int)$sale['afd']);
        self::assertSame('', $sale['sprog']);
        // pos_terminal_sale_defaults() reads the department and brand lists through ifset()
        // and stays quiet past their end - SD-758 pinned that. opret_posordre() itself indexes
        // the payment-terminal list (line 101) and the VAT-code list (line 143) directly, so a
        // till past the end of those warns twice. Legacy floor: recorded, not fixed here.
        self::assertSame([
            'Undefined array key 8 in saleHeaderFunctions.php',
            'Undefined array key 8 in saleHeaderFunctions.php',
        ], array_values(array_filter(
            self::withoutLineNumbers($report['warnings']),
            static fn(string $w) => str_contains($w, 'Undefined array key 8')
        )));
    }

    public function testABrandCarryingAQuoteReachesTheDatabaseIntact(): void
    {
        // The brand is free text from the settings page and is interpolated into the INSERT.
        $report = $this->create([
            'grupper' => [['art' => 'POS', 'kodenr' => '4', 'fiscal_year' => (string)self::REGNAAR,
                'box1' => "O'Brien\tudehygge"]],
        ]);

        self::assertSame("O'Brien", $this->sale($report)['sprog'], 'the sale was lost to the quote');
    }

    public function testTheVatRateComesFromTheTerminalsOwnVatCode(): void
    {
        $report = $this->create([
            'grupper' => [
                ['art' => 'POS', 'kodenr' => '1', 'fiscal_year' => (string)self::REGNAAR,
                    'box3' => self::DEPARTMENTS, 'box7' => "2\t3\t2"],
                ['art' => 'SM', 'kodenr' => '3', 'box2' => '10'],
            ],
            'kasse' => 2,
        ]);

        self::assertSame('10', (string)$this->sale($report)['momssats']);
    }

    public function testTheOperatorIsStampedOnTheSaleTwice(): void
    {
        $report = $this->create(['vars' => ['brugernavn' => 'kassemedarbejder']]);
        $sale = $this->sale($report);

        self::assertSame('kassemedarbejder', $sale['hvem']);
        self::assertSame('kassemedarbejder', $sale['ref']);
        self::assertSame(date('Y-m-d'), $sale['ordredate']);
    }

    // ------------------------------------------------------------- the double-click lock

    public function testASecondCreateWithinTheLockWindowIsRefusedWithoutWritingASale(): void
    {
        // ../temp/<db>/<kasse>.tid holds a timestamp one second ahead. A till that posts the
        // create twice - a double click, or the browser retrying - must not open two sales.
        $report = $this->create(['lock' => 5]);

        self::assertSame([], $report['tables']['ordrer'], 'the second click opened another sale');
        self::assertSame([], $report['calls'], 'nothing was allocated or fetched');
        self::assertStringContainsString(
            'http-equiv="refresh"',
            $report['printed'],
            'the till is sent back to the sale it already has'
        );
        self::assertSame('calling', $report['reached'], 'the function exits rather than returning');
    }

    public function testAnExpiredLockLetsTheNextSaleThrough(): void
    {
        $report = $this->create(['lock' => -5]);

        self::assertCount(1, $report['tables']['ordrer']);
        self::assertSame('', $report['printed'], 'no redirect when the sale is allowed');
    }

    public function testCreatingASaleArmsTheLockForTheNextSecond(): void
    {
        $report = $this->create([]);

        // The lock is written as "now + 1" partway through the call. Counted from the second the
        // call started that is 1, plus one for each second boundary the call happened to cross.
        self::assertGreaterThanOrEqual(1, $report['lock'], 'the lock is set one second into the future');
        self::assertLessThanOrEqual(
            1 + $report['elapsed'],
            $report['lock'],
            'the lock is set one second into the future'
        );
    }

    public function testTheLockIsHeldPerTerminalNotPerInstallation(): void
    {
        // The lock file is named after the cash register, so a busy till must not block the
        // one next to it.
        $report = $this->create(['lock' => 5, 'lock_kasse' => 1, 'kasse' => 2]);

        self::assertCount(
            1,
            $report['tables']['ordrer'],
            'terminal 2 was blocked by terminal 1\'s lock'
        );
    }

    // --------------------------------------------------------------- order of operations

    public function testShopOrdersAreFetchedBeforeTheSaleIsNumbered(): void
    {
        $report = $this->create([]);

        self::assertSame(['hent_shop_ordrer', 'get_next_order_number'], $this->calledFunctions($report));
    }

    public function testTheOrderNumberIsTakenFromThePosSeries(): void
    {
        $report = $this->create(['order_number' => 9123]);

        self::assertContains(['get_next_order_number', 'PO'], $report['calls']);
        self::assertSame('9123', (string)$this->sale($report)['ordrenr']);
    }

    // ----------------------------------------------------------------------- the event

    public function testCreatingASaleLogsTheOpeningEventAgainstIt(): void
    {
        $report = $this->create(['kasse' => 2, 'vars' => ['bruger_id' => 42]]);

        self::assertCount(1, $report['tables']['pos_events']);
        $event = $report['tables']['pos_events'][0];
        self::assertSame('13003', $event['ev_type']);
        self::assertSame('2', $event['cash_register_id']);
        self::assertSame('42', $event['employee_id']);
        self::assertSame((string)$this->sale($report)['id'], $event['order_id']);
    }

    public function testARefusedSaleLogsNoEvent(): void
    {
        $report = $this->create(['lock' => 5]);

        self::assertSame([], $report['tables']['pos_events']);
    }

    // --------------------------------------------------------------------- table numbers

    public function testASaleWithNoTableNumberIsWrittenAgainstTableZero(): void
    {
        $report = $this->create([]);

        self::assertSame('0', (string)$this->sale($report)['nr']);
    }

    public function testTheTillsDefaultTableNumberIsUsedWhenTheRequestNamesNone(): void
    {
        $report = $this->create([
            'grupper' => [['art' => 'POS', 'kodenr' => '2', 'fiscal_year' => (string)self::REGNAAR,
                'box4' => '', 'box13' => "11\t22\t33"]],
            'kasse' => 2,
        ]);

        self::assertSame('22', (string)$this->sale($report)['nr']);
    }

    public function testAnOpenSaleOnTheSameTableIsRedirectedToAndASecondOneOpenedAnyway(): void
    {
        // The redirect is printed but the function does not stop, so the till is sent to the
        // older sale while a new one is left behind it. Pinned as it stands: whatever S12 does
        // to this path, it should be on purpose.
        $report = $this->create([
            'get' => ['bordnr' => '4'],
            'ordrer' => [['id' => 90, 'art' => 'PO', 'nr' => '4', 'felt_5' => '1', 'status' => '0']],
        ]);

        self::assertStringContainsString('id=90', $report['printed'], 'the till is sent to the open sale');
        self::assertCount(2, $report['tables']['ordrer'], 'the function carried on and opened another');
    }

    /**
     * The recorded warnings with the trailing line number stripped. The message and the file
     * are the behaviour being pinned; the line is not, and the S12 refactor these tests exist
     * to protect will move it. Asserting on `:317` makes the test fail on an inserted line
     * and report it as a behaviour change.
     *
     * @param list<string> $warnings
     * @return list<string>
     */
    private static function withoutLineNumbers(array $warnings): array
    {
        return array_values(array_map(
            static fn(string $w): string => (string)preg_replace('/:\d+$/', '', $w),
            $warnings
        ));
    }
}
