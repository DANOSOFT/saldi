<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

// 20261026 CL/MJ SD-772 follow-up Pins what happens to a POS sale's department, till, user and
//                brand when an open sale is redrawn on a different terminal. SD-758 gives the sale
//                the creating terminal's department and brand; vis_pos_linjer() then rewrites the
//                department and the till on every redraw with the drawing terminal's, and leaves
//                the brand alone. That divergence was read in the code during the review of #900
//                and is pinned here by running the real function. Synthetic data, no tenant.
// 20261027 CL/MJ Why the row matters: levering() in includes/ordrefunc.php reads the department
//                back with `select afd,art,ref from ordrer where id = $id` and posts the stock
//                movement with it, so whatever the last redraw wrote is what gets booked. That
//                is a code reading, not something these tests execute - posting to the ledger is
//                out of scope here - so it is recorded as a note rather than asserted.

final class PosRedrawDepartmentCharacterizationTest extends TestCase
{
    private const REGNAAR = 2026;

    /** The sale as opret_posordre() left it: created on till 1, department 5, brand A. */
    private const CREATED_AFD = 5;
    private const CREATED_KASSE = '1';
    private const CREATED_USER = 'kasse1';
    private const CREATED_BRAND = 'havemoebelland';

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
     * Redraws the sale once and returns the child's report.
     *
     * @return array{result: ?string, reached: string, tables: array, sql: list<string>,
     *               calls: list<array>, warnings: list<string>, printed: string}
     */
    private function redraw(array $spec): array
    {
        $this->specFile = tempnam(sys_get_temp_dir(), 'sd772vis') . '.json';
        file_put_contents($this->specFile, json_encode($this->withDefaults($spec), JSON_THROW_ON_ERROR));

        $child = CharacterizationEnv::runChild(__DIR__ . '/support/run_vis_pos_linjer.php', [$this->specFile]);
        self::assertSame('', $child['stderr'], 'the runner wrote to stderr: ' . $child['stderr']);

        $report = json_decode($child['stdout'], true);
        self::assertIsArray($report, 'the runner did not report JSON: ' . $child['stdout']);
        self::assertSame('returned', $report['reached'], 'vis_pos_linjer did not return');
        return $report;
    }

    /** One open sale created on till 1, one line of 100 at 25%, and the groups the path reads. */
    private function withDefaults(array $spec): array
    {
        $year = (string)self::REGNAAR;
        $spec['args'] = ($spec['args'] ?? []) + ['id' => 1, 'momssats' => 25, 'show' => 1];
        $spec['vars'] = ($spec['vars'] ?? []) + ['regnaar' => self::REGNAAR];
        $spec['ordrer'] = $spec['ordrer'] ?? [[
            'id' => 1, 'ordrenr' => '7001', 'art' => 'PO', 'status' => '0',
            'afd' => self::CREATED_AFD, 'felt_5' => self::CREATED_KASSE,
            'sprog' => self::CREATED_BRAND, 'hvem' => self::CREATED_USER,
            'momssats' => '25', 'valuta' => 'DKK', 'sum' => '0', 'moms' => '0',
        ]];
        $spec['ordrelinjer'] = $spec['ordrelinjer'] ?? [[
            'id' => 1, 'ordre_id' => 1, 'vare_id' => 1, 'posnr' => 1, 'varenr' => 'V1',
            'beskrivelse' => 'Vare 1', 'pris' => '100', 'kostpris' => '40', 'antal' => '1',
            'momssats' => '25', 'rabat' => '0', 'm_rabat' => '0', 'saet' => '0',
            'leveret' => '0', 'vat_price' => '0',
        ]];
        $spec['varer'] = $spec['varer'] ?? [[
            'id' => 1, 'varenr' => 'V1', 'beskrivelse' => 'Vare 1', 'salgspris' => '100',
            'gruppe' => '1', 'beholdning' => '10', 'min_lager' => '0',
        ]];
        $spec['grupper'] = $spec['grupper'] ?? [
            // box14 is useTotalPrice; off, so the total-price rewrite in ordrelinjerData is skipped.
            ['art' => 'DIV', 'kodenr' => '3', 'fiscal_year' => $year, 'box14' => ''],
            // The installation-wide rounding account pos_afrund() falls back to.
            ['art' => 'OreDif', 'kodenr' => '1', 'fiscal_year' => $year, 'box2' => ''],
        ];
        return $spec;
    }

    /** The one order row, after the redraw. */
    private function order(array $report): array
    {
        self::assertCount(1, $report['tables']['ordrer']);
        return $report['tables']['ordrer'][0];
    }

    /** The statement vis_pos_linjer ends on, or null if it never ran. */
    private function finalUpdate(array $report): ?string
    {
        $updates = array_values(array_filter(
            $report['sql'],
            static fn(string $sql): bool => str_starts_with($sql, 'update ordrer set sum=')
        ));
        return $updates[0] ?? null;
    }

    // ---------------------------------------------------------------- the department moves

    public function testARedrawOnAnotherTerminalTakesTheSaleToThatTerminalsDepartment(): void
    {
        $report = $this->redraw(['vars' => ['afd' => 9, 'kasse' => 2, 'brugernavn' => 'kasse2']]);

        self::assertSame(
            9,
            (int)$this->order($report)['afd'],
            'the sale was created in department 5 on till 1 and redrawn on till 2 in department 9'
        );
    }

    public function testTheRedrawAlsoTakesTheTillAndTheUser(): void
    {
        $report = $this->redraw(['vars' => ['afd' => 9, 'kasse' => 2, 'brugernavn' => 'kasse2']]);
        $order = $this->order($report);

        self::assertSame('2', (string)$order['felt_5'], 'felt_5 holds the till');
        self::assertSame('kasse2', $order['hvem']);
    }

    /**
     * The point of the whole exercise. SD-758 sets the department and the brand together at
     * creation, but only the department is rewritten on a redraw, so a sale continued on a
     * terminal in another department keeps the first terminal's brand.
     */
    public function testTheBrandStaysWithTheCreatingTerminalWhileTheDepartmentLeaves(): void
    {
        $report = $this->redraw(['vars' => ['afd' => 9, 'kasse' => 2, 'brugernavn' => 'kasse2']]);
        $order = $this->order($report);

        self::assertSame(self::CREATED_BRAND, $order['sprog'], 'the brand is never rewritten');
        self::assertSame(9, (int)$order['afd'], 'the department is');
    }

    public function testTheUpdateCarriesTheDrawingTerminalsDepartmentNotTheOrdersOwn(): void
    {
        $report = $this->redraw(['vars' => ['afd' => 9, 'kasse' => 2, 'brugernavn' => 'kasse2']]);

        self::assertSame(
            "update ordrer set sum= '100',moms='25',hvem = 'kasse2',afd='9',felt_5='2' where id = '1'",
            $this->finalUpdate($report)
        );
    }

    // ------------------------------------------------------------------- where it does not

    public function testARedrawOnTheCreatingTerminalLeavesTheSaleWhereItIs(): void
    {
        $report = $this->redraw([
            'vars' => ['afd' => self::CREATED_AFD, 'kasse' => 1, 'brugernavn' => self::CREATED_USER],
        ]);
        $order = $this->order($report);

        self::assertSame(self::CREATED_AFD, (int)$order['afd']);
        self::assertSame(self::CREATED_KASSE, (string)$order['felt_5']);
        self::assertSame(self::CREATED_USER, $order['hvem']);
    }

    /** A settled sale is read-only here: the update is guarded by status < 3. */
    public function testASettledSaleIsNotRewritten(): void
    {
        $settled = [[
            'id' => 1, 'ordrenr' => '7001', 'art' => 'PO', 'status' => '3',
            'afd' => self::CREATED_AFD, 'felt_5' => self::CREATED_KASSE,
            'sprog' => self::CREATED_BRAND, 'hvem' => self::CREATED_USER,
            'momssats' => '25', 'valuta' => 'DKK', 'sum' => '100', 'moms' => '25',
        ]];

        $report = $this->redraw([
            'ordrer' => $settled,
            'vars' => ['afd' => 9, 'kasse' => 2, 'brugernavn' => 'kasse2'],
        ]);
        $order = $this->order($report);

        self::assertNull($this->finalUpdate($report), 'no update is issued for a settled sale');
        self::assertSame(self::CREATED_AFD, (int)$order['afd']);
        self::assertSame(self::CREATED_KASSE, (string)$order['felt_5']);
        self::assertSame(self::CREATED_USER, $order['hvem']);
    }

    /**
     * What the ledger would read. levering() takes the department off the order row rather
     * than from the page, so the row is the whole of the exposure - which is why a redraw
     * matters at all, and why this asserts the row rather than grepping ordrefunc.php for
     * the select. A source grep fails on a reformat and passes if levering() stops reading
     * the column, which is the wrong way round. See the header for that trace.
     */
    public function testTheDepartmentLeftOnTheRowIsTheDrawingTerminals(): void
    {
        $report = $this->redraw(['vars' => ['afd' => 9, 'kasse' => 2, 'brugernavn' => 'kasse2']]);

        self::assertSame(9, (int)$this->order($report)['afd']);
    }
}
