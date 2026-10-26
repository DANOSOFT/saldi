<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

// 20261026 CL/MJ SD-772 (M-PREP for S12) Pins what debitor/pos_ordre_includes/exitFunc/settlePOS.php
//                does today, before S12 edits the POS exit/settle path: line totals and VAT,
//                posnr renumbering, the invoice number, the pos_betalinger row and its pos_events
//                type, the order of levering() and bogfor(), and the three variables the file uses
//                without ever assigning them. The real file runs unchanged in a child process
//                (support/run_settle_pos.php) against SQLite; no tenant, no ledger, synthetic data.

final class SettlePosCharacterizationTest extends TestCase
{
    /** The terminal's own difference account, per cash register, in grupper art='POS' kodenr='2'. */
    private const TERMINAL_ORE_ACCOUNT = '1001';

    /** The installation-wide fallback the rounding uses when it is given no account. */
    private const GLOBAL_ORE_ACCOUNT = '9999';

    private const REGNAAR = 2026;

    private string $specFile = '';

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is required for the isolated settle fixtures.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->specFile !== '' && file_exists($this->specFile)) {
            unlink($this->specFile);
        }
    }

    // ------------------------------------------------------------------ driving the real file

    /**
     * Runs settlePOS.php over one spec and returns the child's report.
     *
     * @return array{result: array, reached: string, tables: array, sql: list<string>,
     *               calls: list<array>, warnings: list<string>, printed: string}
     */
    private function settle(array $spec): array
    {
        $this->specFile = tempnam(sys_get_temp_dir(), 'sd772') . '.json';
        file_put_contents($this->specFile, json_encode($this->withDefaults($spec), JSON_THROW_ON_ERROR));

        $child = CharacterizationEnv::runChild(__DIR__ . '/support/run_settle_pos.php', [$this->specFile]);
        self::assertSame('', $child['stderr'], 'the runner wrote to stderr: ' . $child['stderr']);

        $report = json_decode($child['stdout'], true);
        self::assertIsArray($report, 'the runner did not report JSON: ' . $child['stdout']);
        return $report;
    }

    /** A plain cash sale: one VAT-rated line, the two grupper rows the settle reads, no discounts. */
    private function withDefaults(array $spec): array
    {
        $spec += ['lines' => [$this->line()], 'post' => ['sum' => 250]];
        $spec['vars'] = ($spec['vars'] ?? []) + ['id' => 1, 'kasse' => 1, 'regnaar' => self::REGNAAR,
            'betaling' => 'Kontant', 'modtaget' => 250];
        $spec['order'] = ($spec['order'] ?? []) + ['id' => 1, 'status' => 0, 'konto_id' => 0,
            'momssats' => 25];
        $supplied = $spec['grupper'] ?? [];
        $defaults = [
            // box9 is tab-separated per cash register; terminal 1 gets its own difference account.
            ['art' => 'POS', 'kodenr' => '2', 'box9' => self::TERMINAL_ORE_ACCOUNT . "\t1002"],
            // box8 is the item a discount line is booked on; empty means "reuse the sold item".
            ['art' => 'POS', 'kodenr' => '1', 'fiscal_year' => (string)self::REGNAAR, 'box8' => ''],
            ['art' => 'OreDif', 'box2' => self::GLOBAL_ORE_ACCOUNT],
        ];
        // A row the spec supplies replaces the default for that art/kodenr instead of joining it:
        // the settle reads these with a plain select and would simply take whichever came first.
        $key = static fn(array $row) => ($row['art'] ?? '') . '/' . ($row['kodenr'] ?? '');
        $keys = array_map($key, $supplied);
        $kept = array_filter($defaults, static fn(array $row) => !in_array($key($row), $keys, true));
        $spec['grupper'] = array_merge(array_values($kept), $supplied);
        return $spec;
    }

    /** JSON gives back 200 where PHP produced 200.0; compare the amounts as amounts. */
    private static function assertAmount(float $expected, $actual, string $message = ''): void
    {
        self::assertSame($expected, (float)$actual, $message);
    }

    private function line(array $overrides = []): array
    {
        return $overrides + ['id' => 10, 'ordre_id' => 1, 'vare_id' => 5, 'varenr' => 'A1',
            'beskrivelse' => 'Stol', 'antal' => 2, 'pris' => 100, 'momssats' => 25, 'momsfri' => '',
            'rabat' => 0, 'rabatart' => 'percent', 'rabatgruppe' => '', 'm_rabat' => 0,
            'folgevare' => 0, 'tilfravalg' => '', 'saet' => '', 'kostpris' => 40, 'fast_db' => 0,
            'variant_id' => 0];
    }

    private function order(array $report): array
    {
        self::assertCount(1, $report['tables']['ordrer'], 'the sale disappeared');
        return $report['tables']['ordrer'][0];
    }

    /** The boundary calls in the order they were made, without their arguments. */
    private function calledFunctions(array $report): array
    {
        return array_map(static fn(array $call) => $call[0], $report['calls']);
    }

    private function queriesMatching(array $report, string $needle): array
    {
        return array_values(array_filter(
            $report['sql'],
            static fn(string $sql) => str_contains($sql, $needle)
        ));
    }

    // ------------------------------------------------------- a settled sale is left alone

    public function testASaleAlreadyInvoicedIsNotSettledAgain(): void
    {
        $report = $this->settle(['order' => ['status' => 3, 'fakturanr' => '500']]);

        self::assertSame([], $report['calls'], 'a settled sale must not reach the ledger again');
        self::assertSame('500', (string)$this->order($report)['fakturanr'], 'the invoice number was reallocated');
        self::assertSame(3, (int)$this->order($report)['status']);
        self::assertSame([], $report['tables']['pos_betalinger'], 'a second payment row was written');
    }

    public function testASaleAlreadyInvoicedAsksTheDatabaseNothing(): void
    {
        $report = $this->settle(['order' => ['status' => 3]]);

        self::assertSame([], $report['sql'], 'status 3 short-circuits before the first query');
    }

    // ------------------------------------------------------------- totals and VAT per line

    public function testAPlainLineIsTotalledExclusiveOfVatWithTheVatBeside(): void
    {
        $report = $this->settle([]);

        self::assertAmount(200.0, $report['result']['sum']);
        self::assertAmount(50.0, $report['result']['moms']);
        self::assertSame(200.0, (float)$this->order($report)['sum']);
        self::assertSame(50.0, (float)$this->order($report)['moms']);
    }

    public function testAPercentDiscountComesOffTheLineBeforeVat(): void
    {
        // 50, not 100, a unit: at 100 a 10-krone discount and a 10% one come to the same
        // amount and the two branches cannot be told apart.
        $report = $this->settle(['lines' => [$this->line(['pris' => 50, 'rabat' => 10,
            'rabatart' => 'percent'])]]);

        self::assertAmount(90.0, $report['result']['sum'], '2 x 50 less 10%');
        self::assertAmount(22.5, $report['result']['moms']);
    }

    public function testAnAmountDiscountComesOffTheUnitPriceNotTheLineTotal(): void
    {
        $report = $this->settle(['lines' => [$this->line(['pris' => 50, 'rabat' => 10,
            'rabatart' => 'amount'])]]);

        self::assertAmount(
            80.0,
            $report['result']['sum'],
            '(50 - 10) x 2 = 80, where the same figure read as a percentage would give 90'
        );
        self::assertAmount(20.0, $report['result']['moms']);
    }

    public function testAVatFreeLineAddsNothingToTheVat(): void
    {
        $report = $this->settle(['lines' => [$this->line(['momsfri' => 'on'])]]);

        self::assertAmount(200.0, $report['result']['sum']);
        self::assertAmount(0.0, $report['result']['moms']);
    }

    public function testEachLineIsVatedAtItsOwnRateNotTheOrdersRate(): void
    {
        $report = $this->settle([
            'order' => ['momssats' => 25],
            'lines' => [
                $this->line(['id' => 10, 'pris' => 100, 'antal' => 1, 'momssats' => 25]),
                $this->line(['id' => 11, 'pris' => 100, 'antal' => 1, 'momssats' => 10]),
            ],
        ]);

        self::assertAmount(200.0, $report['result']['sum']);
        self::assertAmount(35.0, $report['result']['moms'], '25 on one line, 10 on the other');
    }

    // ------------------------------------------------------------------ line numbering

    public function testLinesAreNumberedFromOneInTheOrderTheSettleReadsThem(): void
    {
        $report = $this->settle([
            'lines' => [
                $this->line(['id' => 10, 'pris' => 10]),
                $this->line(['id' => 11, 'pris' => 20]),
                $this->line(['id' => 12, 'pris' => 30]),
            ],
        ]);

        // "order by rabatgruppe, id desc": the newest line is numbered first.
        $byId = [];
        foreach ($report['tables']['ordrelinjer'] as $row) {
            $byId[(int)$row['id']] = (int)$row['posnr'];
        }
        ksort($byId);
        self::assertSame([10 => 3, 11 => 2, 12 => 1], $byId);
    }

    public function testTheLinesAreReadNewestFirst(): void
    {
        $report = $this->settle([]);

        self::assertNotEmpty(
            $this->queriesMatching($report, 'order by rabatgruppe, id desc'),
            'the read order decides posnr, so it is part of the behaviour'
        );
    }

    // ---------------------------------------------------------------- the invoice number

    public function testTheInvoiceNumberIsAllocatedForPosAndStoredOnTheSale(): void
    {
        $report = $this->settle(['invoice_number' => 4711]);

        self::assertContains(
            ['get_next_invoice_number', 'PO', '1'],
            $report['calls'],
            'the POS series has to be asked for, not MAX(fakturanr)+1'
        );
        self::assertSame('4711', (string)$this->order($report)['fakturanr']);
    }

    public function testTheSaleIsMovedToStatusTwoWithTheTillAndTheOperator(): void
    {
        $report = $this->settle(['vars' => ['kasse' => 3, 'brugernavn' => 'kassemedarbejder']]);
        $order = $this->order($report);

        self::assertSame(2, (int)$order['status']);
        self::assertSame('3', (string)$order['felt_5'], 'felt_5 carries the cash register');
        self::assertSame('kassemedarbejder', $order['ref']);
        self::assertSame(date('Y-m-d'), $order['fakturadate']);
    }

    // -------------------------------------------------------------------- the payment row

    public function testThePaymentIsWrittenAsItsOwnRow(): void
    {
        $report = $this->settle(['vars' => ['betaling' => 'Kontant', 'modtaget' => 250]]);

        self::assertCount(1, $report['tables']['pos_betalinger']);
        $payment = $report['tables']['pos_betalinger'][0];
        self::assertSame('Kontant', $payment['betalingstype']);
        self::assertSame(250.0, (float)$payment['amount']);
    }

    public function testTheReservedPaymentRowIsFilledInRatherThanDuplicated(): void
    {
        // exit.php parks a row with betalingstype '!' when the payment starts; the settle has
        // to claim that row, or the sale ends up paid twice.
        $report = $this->settle([
            'betalinger' => [['id' => 77, 'ordre_id' => 1, 'betalingstype' => '!', 'amount' => 0,
                'valuta' => 'DKK', 'valutakurs' => 100]],
        ]);

        self::assertCount(1, $report['tables']['pos_betalinger'], 'the reserved row was not reused');
        self::assertSame(77, (int)$report['tables']['pos_betalinger'][0]['id']);
        self::assertSame('Kontant', $report['tables']['pos_betalinger'][0]['betalingstype']);
    }

    public function testACashPaymentNamedInEnglishIsStoredInDanish(): void
    {
        $report = $this->settle(['vars' => ['betaling' => 'Cash']]);

        self::assertSame('Kontant', $report['tables']['pos_betalinger'][0]['betalingstype']);
    }

    /** @return array<string, array{string, string}> */
    public static function paymentEventTypes(): array
    {
        return [
            'cash is its own event'        => ['Kontant', '12001'],
            'Dankort is its own event'     => ['Dankort', '12002'],
            'a card type ending in -kort'  => ['Betalingskort', '12003'],
            'a card type containing card'  => ['Mastercard', '12003'],
            'MobilePay is not a card'      => ['MobilePay', '12999'],
        ];
    }

    #[DataProvider('paymentEventTypes')]
    public function testThePaymentTypeDecidesTheEventType(string $betaling, string $expected): void
    {
        $report = $this->settle(['vars' => ['betaling' => $betaling]]);

        $types = array_map(static fn(array $row) => $row['ev_type'], $report['tables']['pos_events']);
        self::assertSame([$expected], $types);
    }

    public function testAPaymentTypeCalledOnlyCardIsNotRecognisedAsACard(): void
    {
        // strpos() returns 0 for a match at the start and the result is tested for truth, so a
        // type literally named "card" or "kort" falls through to the catch-all 12999 while
        // "Mastercard" does not. Pinned as it stands, not fixed: S12 must not change it by accident.
        $report = $this->settle(['vars' => ['betaling' => 'card']]);

        self::assertSame('12999', $report['tables']['pos_events'][0]['ev_type']);
    }

    public function testTheEventCarriesTheTillAndTheOperator(): void
    {
        $report = $this->settle(['vars' => ['kasse' => 2, 'bruger_id' => 42]]);
        $event = $report['tables']['pos_events'][0];

        self::assertSame('2', $event['cash_register_id']);
        self::assertSame('42', $event['employee_id']);
        self::assertSame('1', $event['order_id']);
    }

    // -------------------------------------------------------------- reaching the ledger

    public function testDeliveryIsPostedBeforeTheSaleIs(): void
    {
        $report = $this->settle([]);

        self::assertSame(
            ['transaktion', 'get_next_invoice_number', 'levering', 'bogfor'],
            $this->calledFunctions($report)
        );
        self::assertSame('OK', $report['result']['svar']);
    }

    public function testEverythingIsWrittenInsideATransactionThatTheSettleOnlyOpens(): void
    {
        $report = $this->settle([]);

        self::assertSame(
            ['transaktion', 'begin'],
            $report['calls'][0],
            'the first thing after reading the lines is to open a transaction'
        );
        $commits = array_filter(
            $report['calls'],
            static fn(array $call) => $call === ['transaktion', 'commit']
        );
        self::assertSame([], $commits, 'the commit belongs to afslut(), not to the settle');
    }

    public function testADeliveryThatFailsStopsBeforeTheSaleIsPosted(): void
    {
        $report = $this->settle(['levering' => 'Varen findes ikke']);

        self::assertNotContains(
            'bogfor',
            $this->calledFunctions($report),
            'a failed delivery must not be followed by a posting'
        );
        self::assertSame('Varen findes ikke', $report['result']['returned']);
    }

    public function testAPostingThatFailsIsReportedBack(): void
    {
        $report = $this->settle(['bogfor' => 'Perioden er lukket']);

        self::assertSame('Perioden er lukket', $report['result']['returned']);
    }

    public function testAnOnAccountPaymentIsPostedAsOneAndSkipsDeliveryEntirely(): void
    {
        $report = $this->settle([
            'vars' => ['konto_id' => 8, 'betalingsbet' => 'Netto 8 dage', 'indbetaling' => 100],
            'openpost' => [['konto_id' => 8, 'amount' => 250]],
        ]);
        $called = $this->calledFunctions($report);

        self::assertContains('bogfor_indbetaling', $called);
        self::assertNotContains('levering', $called);
        self::assertNotContains('bogfor', $called);
    }

    public function testAnOnAccountPaymentIsTheAmountPaidAndCarriesNoVat(): void
    {
        $report = $this->settle([
            'vars' => ['konto_id' => 8, 'betalingsbet' => 'Netto 8 dage', 'indbetaling' => 100],
            'openpost' => [['konto_id' => 8, 'amount' => 250]],
        ]);

        self::assertAmount(100.0, $report['result']['sum'], 'the sale total is replaced by the amount paid');
        self::assertAmount(0.0, $report['result']['moms']);
    }

    // ------------------------------------- the three variables the file never assigns

    public function testTheFinalCashRoundingRunsWithoutAnAccountAndFallsBackToTheGlobalOne(): void
    {
        // settlePOS.php spells the terminal's difference account $difkto where it sets it
        // (lines 44-46) and $diffkto where it rounds the cash (lines 317-324). $diffkto is
        // never assigned anywhere, so pos_afrund() is handed null and looks up the
        // installation-wide OreDif account instead of this till's own.
        $report = $this->settle(['vars' => ['modtaget' => 250.37]]);

        self::assertNotEmpty(
            $this->queriesMatching($report, "art='OreDif'"),
            'the rounding asked for the global account, so it was given no account'
        );
        self::assertContains('Undefined variable $diffkto in settlePOS.php:317', $report['warnings']);
        self::assertContains('Undefined variable $diffkto in settlePOS.php:320', $report['warnings']);
    }

    public function testTheTillsOwnDifferenceAccountIsNotWhatRoundsTheCash(): void
    {
        // With no global account configured the rounding does nothing at all - even though
        // this till has one in grupper box9. That is the visible cost of the typo above.
        $report = $this->settle([
            'vars' => ['modtaget' => 250.37],
            'grupper' => [['art' => 'OreDif', 'box2' => '']],
        ]);

        self::assertAmount(
            250.37,
            $report['result']['modtaget'],
            'the till has a difference account, but the cash was not rounded by it'
        );
    }

    public function testWithAGlobalAccountTheCashIsRoundedToTheNearestFiftyOere(): void
    {
        $report = $this->settle(['vars' => ['modtaget' => 250.37]]);

        self::assertAmount(250.5, $report['result']['modtaget']);
        self::assertSame(250.5, (float)$this->order($report)['betalt']);
    }

    public function testTheReceiptIdIsNeverSetSoEveryPaymentRowStoresZero(): void
    {
        // afslut() does not assign $receipt_id either, and settlePOS casts it to int.
        $report = $this->settle([]);

        self::assertContains('Undefined variable $receipt_id in settlePOS.php:333', $report['warnings']);
        self::assertSame(0, (int)$report['tables']['pos_betalinger'][0]['receipt_id']);
    }

    // ----------------------------------------------------------------- oere rounding

    public function testATotalThatAgreesWithItsLinesIsLeftAlone(): void
    {
        $report = $this->settle([]);

        self::assertAmount(250.0, $report['result']['incl_moms']);
        self::assertSame(
            100.0,
            (float)$report['tables']['ordrelinjer'][0]['pris'],
            'no line price is nudged when the totals already agree'
        );
    }

    public function testLinePricesAreNudgedWhenTheVatRoundingLeavesTheTotalsDisagreeing(): void
    {
        // Each line's VAT is rounded for the screen, the sale stores sum and VAT apart, and the
        // two disagree by fractions of an oere. settlePOS spreads the difference back over the
        // line prices in thousandths rather than letting the receipt total drift.
        $report = $this->settle([
            'lines' => [
                $this->line(['id' => 10, 'pris' => 10.01, 'antal' => 1]),
                $this->line(['id' => 11, 'pris' => 10.01, 'antal' => 1]),
            ],
            'post' => ['sum' => 25.03],
        ]);

        $prices = array_map(static fn(array $row) => (float)$row['pris'], $report['tables']['ordrelinjer']);
        self::assertNotSame([10.01, 10.01], $prices, 'the correction did not reach the lines');
        self::assertAmount(
            round($report['result']['sum'] + $report['result']['moms'], 2),
            $report['result']['incl_moms'],
            'after the correction the two totals have to agree'
        );
    }
}
