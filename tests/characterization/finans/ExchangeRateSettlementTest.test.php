<?php
// 20261021 CL/MJ MB-79 Pin the exchange difference an automatic settlement leaves behind, and the
// currency condition that stops a DKK payment settling a foreign-currency post.
// 20261027 CL/LH MB-79 The open-post difference row is written the way manual settlement writes it, and
// equalizeMatchingRecords() (includes/genberegn.php), which runs after every posting, keeps to the same
// currency and rate conditions; it is lifted the same way.
// 20261027 CL/LH MB-79 Simuler measures against what the journal's earlier lines would have written to
// openpost, the confirmation page gets the same differences (journalRateDifferences()), a reference given
// twice is measured once, and a difference account missing from the chart of accounts is refused.
// 20261027 CL/LH MB-79 The confirmation page's in-voucher currency rounding goes on the difference account's row.
//
// The functions under test live in finans/bogfor.php, which is a page: including it would run the
// page. They are lifted out of the shipped file and evaluated into this namespace instead, so the
// tests cannot pass against a rule that has moved on. eval() does not inherit the caller's
// namespace, so it is restated.

namespace Saldi\Tests\ExchangeRateSettlement;

use PDO;
use PHPUnit\Framework\TestCase;

final class ExchangeRateSettlementTest extends TestCase
{
    private PDO $db;
    // The lift declares functions, so it happens once per process. Everything it declares - the
    // settlement functions and the db_* stubs they call - is in this namespace, so nothing here
    // can collide with another test's globals and the tests need no process of their own.
    private static bool $lifted = false;
    private static bool $equalizeLifted = false;

    private const EUR = 2;   // grupper art='VK' kodenr
    private const USD = 3;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is required for the isolated settlement fixtures.');
        }
        require_once __DIR__ . '/support/exchange_rate_settlement_database.php';

        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $GLOBALS['exchangeRateSettlementDb'] = $this->db;
        $GLOBALS['exchangeRateSettlementSql'] = [];
        $GLOBALS['exchangeRateSettlementTransactions'] = [];
        $GLOBALS['regnaar'] = 2026;
        $GLOBALS['kladde_id'] = 4001;

        $this->db->exec("CREATE TABLE openpost (id INTEGER PRIMARY KEY AUTOINCREMENT, konto_id INTEGER,
            konto_nr TEXT, faktnr TEXT, amount NUMERIC, refnr INTEGER, beskrivelse TEXT, udlignet TEXT,
            transdate TEXT, kladde_id INTEGER, bilag_id INTEGER, udlign_id INTEGER, udlign_date TEXT,
            valuta TEXT, projekt TEXT, valutakurs NUMERIC, forfaldsdate TEXT, betal_id TEXT)");
        $this->db->exec("CREATE TABLE adresser (id INTEGER PRIMARY KEY, kontonr TEXT, art TEXT, gruppe TEXT)");
        $this->db->exec("CREATE TABLE grupper (id INTEGER PRIMARY KEY AUTOINCREMENT, art TEXT, kodenr TEXT,
            box1 TEXT, box2 TEXT, box3 TEXT)");
        $this->db->exec("CREATE TABLE transaktioner (id INTEGER PRIMARY KEY AUTOINCREMENT, kontonr TEXT,
            bilag TEXT, transdate TEXT, logdate TEXT, logtime TEXT, beskrivelse TEXT, debet NUMERIC,
            kredit NUMERIC, faktura TEXT, kladde_id INTEGER, afd TEXT, ansat TEXT, projekt TEXT,
            valuta TEXT, valutakurs NUMERIC, ordre_id INTEGER, moms NUMERIC)");
        $this->db->exec("CREATE TABLE simulering AS SELECT * FROM transaktioner");
        $this->db->exec("CREATE TABLE kontoplan (id INTEGER PRIMARY KEY AUTOINCREMENT, kontonr TEXT, regnskabsaar INTEGER,
            kontotype TEXT, beskrivelse TEXT)");
        // The difference accounts exist as operating accounts in the fiscal year; 7900 is a heading.
        $this->db->exec("INSERT INTO kontoplan (kontonr,regnskabsaar,kontotype,beskrivelse) VALUES
            ('7960',2026,'D','Valutadifferencer EUR'),('7961',2026,'D','Valutadifferencer USD'),
            ('7900',2026,'H','Finansielle poster'),('7962',2025,'D','Kun sidste aar')");
        $this->db->exec("CREATE TABLE kassekladde (id INTEGER PRIMARY KEY AUTOINCREMENT, kladde_id INTEGER, bilag INTEGER,
            transdate TEXT, beskrivelse TEXT, d_type TEXT, debet INTEGER, k_type TEXT, kredit INTEGER, faktura TEXT,
            amount NUMERIC, valuta INTEGER, projekt TEXT, afd INTEGER, ansat INTEGER, ordre_id INTEGER, momsfri TEXT)");
        $this->db->exec("CREATE TABLE valuta (id INTEGER PRIMARY KEY AUTOINCREMENT, gruppe INTEGER, valdate TEXT, kurs NUMERIC)");
        $this->db->exec("INSERT INTO valuta (gruppe,valdate,kurs) VALUES
            (" . self::EUR . ",'2026-09-01',745),(" . self::EUR . ",'2026-10-01',746)");
        // Creditor 1009 in group 1, whose control account is 6710; debtor 2200 in group 5, control 6010.
        $this->db->exec("INSERT INTO adresser (id,kontonr,art,gruppe) VALUES (29,'1009','K','1'),(30,'2200','D','5')");
        $this->db->exec("INSERT INTO grupper (art,kodenr,box1,box2,box3) VALUES
            ('VK','" . self::EUR . "','EUR','745','7960'),
            ('VK','" . self::USD . "','USD','680','7961'),
            ('KG','1','','6710',''),
            ('DG','5','','6010','')");

        if (!self::$lifted) {
            eval('namespace ' . __NAMESPACE__ . '; ' . self::liftedSource());
            self::$lifted = true;
        }
        if (!self::$equalizeLifted) {
            eval('namespace ' . __NAMESPACE__ . '; ' . self::liftedEqualizeSource());
            self::$equalizeLifted = true;
        }
    }

    /** equalizeMatchingRecords() exactly as includes/genberegn.php ships it. */
    private static function liftedEqualizeSource(): string
    {
        $src = file_get_contents(dirname(__DIR__, 3) . '/includes/genberegn.php');
        $from = strpos($src, 'function equalizeMatchingRecords (');
        self::assertNotFalse($from, 'includes/genberegn.php no longer defines equalizeMatchingRecords(); the lift is stale');
        // The function and the function_exists() guard around it close together on one line.
        $to = strpos($src, "\n}}", $from);
        self::assertNotFalse($to, 'equalizeMatchingRecords() no longer ends with the guard\'s closing brace');
        return substr($src, $from, $to - $from) . "\n}";
    }

    /** The settlement functions exactly as finans/bogfor.php ships them. */
    private static function liftedSource(): string
    {
        $src = file_get_contents(self::bogforPath());
        $from = strpos($src, 'function openpostCurrencyLetters(');
        $to   = strpos($src, 'function valutaopslag(');
        self::assertNotFalse($from, 'finans/bogfor.php no longer defines openpostCurrencyLetters(); the lift is stale');
        self::assertNotFalse($to, 'finans/bogfor.php no longer defines valutaopslag(); the lift has no end anchor');
        self::assertGreaterThan($from, $to, 'valutaopslag() now comes before the settlement functions');
        $lift = substr($src, $from, $to - $from);
        foreach (['openpostAccountQuery', 'openpostInvoiceList', 'openpostMatchQuery',
                  'openpostMatches', 'openpostOverlay', 'openpostOverlayMatch', 'openpostOverlayRecord',
                  'openpostRateDifferenceAmount', 'openpostRateDifference', 'journalLineOpenpostSides',
                  'journalRateDifferences', 'confirmationRoundingOnListedAccount', 'rateDifferenceText',
                  'rateDifferenceOpenpostAmount', 'rateDifferenceAccounts', 'rateDifferenceAccountError',
                  'bookRateDifference', 'openpost'] as $needed) {
            self::assertStringContainsString('function ' . $needed . '(', $lift,
                $needed . '() is no longer between openpostCurrencyLetters() and valutaopslag()');
        }
        // Drop the row of #### that separates the helpers from openpost() in the page.
        return preg_replace('/^#+$/m', '', $lift);
    }

    private static function bogforPath(): string
    {
        return dirname(__DIR__, 3) . '/finans/bogfor.php';
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Posts one journal line through openpost(), the way bogfor() calls it: a positive amount is a
     * debit on the debtor/creditor, a negative one a credit.
     */
    private function post(string $art, string $konto, string $faktura, float $amount,
                          $valutakode, $kurs, string $transdate, int $bilag): void
    {
        openpost($art, $konto, (string)$bilag, $faktura, $amount, 'line', $transdate,
            $bilag + 100, $valutakode, $kurs, '', '', '');
    }

    /** @return array<int,array<string,mixed>> */
    private function openposts(): array
    {
        return $this->db->query("SELECT id,faktnr,amount,udlignet,udlign_id,valuta,valutakurs,beskrivelse
            FROM openpost ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** The DKK value the open-post ledger carries for a settlement group. */
    private function groupDkk(): float
    {
        $total = 0.0;
        foreach ($this->openposts() as $row) {
            $total += round($row['amount'] * $row['valutakurs'] / 100, 2);
        }
        return round($total, 2);
    }

    /** @return array<int,array<string,mixed>> */
    private function ledger(string $table = 'transaktioner'): array
    {
        return $this->db->query("SELECT kontonr,debet,kredit,beskrivelse,valuta,valutakurs
            FROM $table ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** One match row as openpostMatchQuery() returns it. */
    private static function match(float $amount, $kurs): array
    {
        return ['id' => 1, 'transdate' => '2026-09-01', 'amount' => $amount, 'valutakurs' => $kurs];
    }

    private static function journalLine(array $overrides = []): array
    {
        return $overrides + ['bilag' => '2', 'transdate' => '2026-10-01', 'faktura' => 'F-745',
            'afd' => '', 'ansat' => '0', 'projekt' => '', 'ordre_id' => '0', 'amount' => 1000.03];
    }

    // -------------------------------------------- resolving the currency code

    public function testNoCurrencyCodeIsTheInstallationsOwnCurrency(): void
    {
        self::assertSame('DKK', openpostCurrencyLetters(0));
        self::assertSame('DKK', openpostCurrencyLetters(''));
    }

    public function testACurrencyCodeResolvesToTheLettersStoredOnOpenPosts(): void
    {
        self::assertSame('EUR', openpostCurrencyLetters(self::EUR));
        self::assertSame('USD', openpostCurrencyLetters(self::USD));
    }

    public function testACodeWithNoCurrencyGroupResolvesToNothingRatherThanWarning(): void
    {
        self::assertSame('', openpostCurrencyLetters(99));
    }

    // ------------------------------------------------------- the match query

    public function testTheMatchQueryRequiresTheSameCurrency(): void
    {
        $qtxt = openpostMatchQuery(29, '', 'F-745', 'EUR', 1000.03, true);
        self::assertStringContainsString("coalesce(nullif(valuta,''),'DKK')='EUR'", $qtxt);
    }

    public function testAnEmptyOrMissingCurrencyOnAStoredRowIsReadAsDkk(): void
    {
        // Rows written before openpost.valuta existed have it NULL, and includes/ret_valutadiff.php
        // writes '-'. A DKK settlement has to keep working against them.
        $qtxt = openpostMatchQuery(29, '', 'F-745', 'DKK', 1000.03, true);
        self::assertStringContainsString("coalesce(nullif(valuta,''),'DKK')='DKK'", $qtxt);
        $qtxt = openpostMatchQuery(29, '', 'F-745', '', 1000.03, true);
        self::assertStringContainsString("coalesce(nullif(valuta,''),'DKK')='DKK'", $qtxt);
    }

    public function testTheAmountWindowAppliesOnlyToASingleInvoiceReference(): void
    {
        self::assertStringContainsString("amount >= '1000.025'", openpostMatchQuery(29, '', 'F', 'EUR', 1000.03, true));
        self::assertStringNotContainsString('amount >=', openpostMatchQuery(29, '', 'F', 'EUR', 1000.03, false));
    }

    public function testTheMatchQueryCastsTheAccountAndEscapesTheProject(): void
    {
        $qtxt = openpostMatchQuery("29' or '1'='1", "p' or '1'='1", 'F', 'EUR', 1, true);
        self::assertStringContainsString("konto_id='29' and projekt=", $qtxt,
            'the account id has to be a number and nothing else');
        self::assertStringNotContainsString("or '1'='1 ", $qtxt);
        self::assertStringContainsString("projekt='p'' or ''1''=''1'", $qtxt);
    }

    public function testAnInvoiceReferenceIsNotEscapedTwice(): void
    {
        // bogfor() passes $faktura[$y], which it has already run through db_escape_string().
        $qtxt = openpostMatchQuery(29, '', "O''Brien", 'EUR', 1, true);
        self::assertStringContainsString("faktnr='O''Brien'", $qtxt);
    }

    // ----------------------------------------------------- measuring the gap

    public function testTheTicketsPairLeavesTenKronerOnTheControlAccount(): void
    {
        // EUR 1.000,03 invoiced at 745 and paid at 746: the creditor control account keeps 10,00 DKK.
        $diff = openpostRateDifferenceAmount(1000.03, 746, [self::match(-1000.03, 745)], 'EUR');
        self::assertSame(10.00, $diff);
    }

    public function testTheSameRateOnBothSidesIsNoDifference(): void
    {
        self::assertSame(0.0, openpostRateDifferenceAmount(1000.03, 745, [self::match(-1000.03, 745)], 'EUR'));
    }

    public function testADkkSettlementIsNeverARateDifference(): void
    {
        self::assertSame(0.0, openpostRateDifferenceAmount(1000.03, 100, [self::match(-1000.03, 745)], 'DKK'));
        self::assertSame(0.0, openpostRateDifferenceAmount(1000.03, 100, [self::match(-1000.03, 745)], ''));
    }

    public function testNothingIsDerivedFromAStoredRowThatCarriesNoRate(): void
    {
        // openpost.valuta and openpost.valutakurs were added together, so a row missing the rate is
        // read as the settlement it was before MB-79 - not as a rate of nothing, which would turn the
        // whole amount into a difference.
        self::assertSame(0.0, openpostRateDifferenceAmount(1000.03, 745, [self::match(-1000.03, null)], 'EUR'));
        self::assertSame(0.0, openpostRateDifferenceAmount(1000.03, 745, [self::match(-1000.03, 0)], 'EUR'));
        self::assertSame(0.0, openpostRateDifferenceAmount(1000.03, 0, [self::match(-1000.03, 745)], 'EUR'));
    }

    public function testAmountsThatDoNotCancelAreAResidualAndNotARateDifference(): void
    {
        // A part payment leaves a real currency balance; manual settlement handles that, with its
        // own maximum-difference guard.
        self::assertSame(0.0, openpostRateDifferenceAmount(400.00, 746, [self::match(-1000.03, 745)], 'EUR'));
    }

    public function testADifferenceBelowOneOereIsLeftAlone(): void
    {
        self::assertSame(0.0, openpostRateDifferenceAmount(1.00, 745.2, [self::match(-1.00, 745)], 'EUR'));
    }

    public function testAGroupThatCancelsToAFloatingPointResidueBooksNothing(): void
    {
        // Each side is rounded to two decimals before it is summed, so a real difference is always a
        // whole number of oere. What is left over is arithmetic: EUR 2,03 at 700 against 1,01 at 701
        // and 1,02 at 699,01 cancels to 8,9e-16, which is not zero and would be booked as a 0,00 pair.
        $diff = openpostRateDifferenceAmount(2.03, 700,
            [self::match(-1.01, 701), self::match(-1.02, 699.01)], 'EUR');
        self::assertSame(0.0, $diff, 'a residue of the arithmetic is not an exchange difference');
    }

    public function testTheMatchToleranceIsNotMistakenForARateDifference(): void
    {
        // openpost() matches within +/-0,005, so two amounts can settle each other without being
        // equal. At one and the same rate that leaves a DKK difference - 3 oere here - which belongs
        // to the tolerance, not to any movement in the exchange rate.
        self::assertSame(0.0, openpostRateDifferenceAmount(1000.00, 745, [self::match(-1000.004, 745)], 'EUR'));
    }

    public function testSeveralInvoiceReferencesAreMeasuredAsOneGroup(): void
    {
        // A payment of EUR 1.500 at 746 covering two invoices booked at 745 and 744.
        $diff = openpostRateDifferenceAmount(1500.00, 746,
            [self::match(-1000.00, 745), self::match(-500.00, 744)], 'EUR');
        self::assertSame(20.00, $diff);
    }

    public function testASalesInvoicePaidAtAHigherRateIsAGain(): void
    {
        // Sales invoice EUR 1.000,03 debited at 745, paid at 746: more DKK came in than was invoiced,
        // so the difference is negative - which is what makes bookRateDifference() debit the control
        // account and credit the currency account instead of the other way round.
        self::assertSame(-10.00, openpostRateDifferenceAmount(-1000.03, 746, [self::match(1000.03, 745)], 'EUR'));
    }

    public function testASalesInvoicePaidAtALowerRateIsALoss(): void
    {
        // The same invoice at 746, paid at 745: 10,00 DKK less came in than was invoiced.
        self::assertSame(10.00, openpostRateDifferenceAmount(-1000.03, 745, [self::match(1000.03, 746)], 'EUR'));
    }

    // ------------------------------------------- openpost() against the data

    public function testTheTicketsScenarioSettlesAndLeavesTheOpenPostLedgerFlat(): void
    {
        $this->post('K', '1009', 'F-745', -1000.03, self::EUR, 745, '2026-09-01', 1);
        $GLOBALS['kladde_id'] = 4002;
        $this->post('K', '1009', 'F-745', 1000.03, self::EUR, 746, '2026-10-01', 2);

        $rows = $this->openposts();
        self::assertCount(3, $rows, 'the invoice, the payment and the difference that balances them');
        foreach ($rows as $row) {
            self::assertSame('1', $row['udlignet'], 'every row of the settled group is marked settled');
            self::assertSame('1', (string)$row['udlign_id'], 'and belongs to the same settlement');
        }
        $difference = $rows[2];
        // Written the way doAlign.php writes a manual settlement's difference: 0,001 EUR worth -10,00 DKK. A
        // DKK row at rate 100 was converted into EUR by the account statement and left a EUR balance.
        self::assertSame('EUR', $difference['valuta'], 'the difference row is in the settled currency');
        self::assertSame(-0.001, (float)$difference['amount']);
        self::assertSame(1000000.0, (float)$difference['valutakurs']);
        self::assertSame(0.0, round((float)$difference['amount'], 2),
            'so it adds nothing to the account\'s balance in EUR');
        self::assertStringContainsString('Udligning af valutadiff', $difference['beskrivelse']);
        self::assertStringContainsString('EUR 1.000,03', $difference['beskrivelse']);
        self::assertStringContainsString('DKK 10,00', $difference['beskrivelse']);
        self::assertSame(0.0, $this->groupDkk(),
            'the settled group is worth nothing in DKK once the difference is booked on the control account');
    }

    public function testAPairBookedAtTheSameRateIsUnchanged(): void
    {
        $this->post('K', '1009', 'F-800', -1000.03, self::EUR, 745, '2026-09-01', 1);
        $GLOBALS['kladde_id'] = 4002;
        $this->post('K', '1009', 'F-800', 1000.03, self::EUR, 745, '2026-10-01', 2);

        $rows = $this->openposts();
        self::assertCount(2, $rows, 'no difference row where there is no difference');
        self::assertSame('1', $rows[0]['udlignet']);
        self::assertSame(0.0, $this->groupDkk());
    }

    public function testADkkPaymentDoesNotSettleAForeignCurrencyPost(): void
    {
        $this->post('K', '1009', 'F-900', -1000.03, self::EUR, 745, '2026-09-01', 1);
        $GLOBALS['kladde_id'] = 4002;
        $this->post('K', '1009', 'F-900', 1000.03, 0, 100, '2026-10-01', 2);

        $rows = $this->openposts();
        self::assertSame('EUR', $rows[0]['valuta']);
        self::assertSame('0', $rows[0]['udlignet'], 'the EUR invoice is still open');
        self::assertSame('DKK', $rows[1]['valuta']);
        self::assertSame('0', $rows[1]['udlignet'], 'and so is the DKK payment');
    }

    public function testOneForeignCurrencyDoesNotSettleAnother(): void
    {
        $this->post('K', '1009', 'F-901', -1000.03, self::EUR, 745, '2026-09-01', 1);
        $GLOBALS['kladde_id'] = 4002;
        $this->post('K', '1009', 'F-901', 1000.03, self::USD, 680, '2026-10-01', 2);

        foreach ($this->openposts() as $row) {
            self::assertSame('0', $row['udlignet']);
        }
    }

    public function testADkkPostFromBeforeTheCurrencyColumnStillSettles(): void
    {
        $this->db->exec("INSERT INTO openpost (konto_id,konto_nr,faktnr,amount,udlignet,transdate,projekt)
            VALUES (29,'1009','F-902',-500.00,'0','2026-09-01','')");
        $this->post('K', '1009', 'F-902', 500.00, 0, 100, '2026-10-01', 2);

        $rows = $this->openposts();
        self::assertSame('1', $rows[0]['udlignet'],
            'a row with no currency and no rate is still an ordinary DKK open post');
        self::assertCount(2, $rows, 'and settling it books no exchange difference');
    }

    public function testTheDifferenceIsMeasuredOnTheOneAccountRowOpenpostReaches(): void
    {
        // openpost() reuses $q for the match query inside its own loop over the account numbers, so
        // the loop ends after the first row. Measuring more rows than it settles would book a
        // difference twice.
        $this->db->exec("INSERT INTO adresser (id,kontonr,art,gruppe) VALUES (31,'1009','K','1')");
        foreach ([29, 31] as $kontoId) {
            $this->db->exec("INSERT INTO openpost (konto_id,konto_nr,faktnr,amount,udlignet,transdate,projekt,valuta,valutakurs)
                VALUES ($kontoId,'1009','F-903',-1000.03,'0','2026-09-01','','EUR',745)");
        }

        $measured = openpostRateDifference('K', '1009', 'F-903', 1000.03, self::EUR, 746, '');
        self::assertSame(10.00, $measured, 'the difference is counted once, not once per account row');

        $this->post('K', '1009', 'F-903', 1000.03, self::EUR, 746, '2026-10-01', 2);
        $settled = $this->db->query("SELECT COUNT(*) FROM openpost WHERE udlignet='1' AND amount=-1000.03")
            ->fetchColumn();
        self::assertSame('1', (string)$settled, 'and openpost() settles exactly the one it measured');
    }

    public function testAnInvoiceReferenceCarryingAQuoteStillSettles(): void
    {
        // bogfor() hands openpost() the reference already escaped; it is stored unescaped and has to
        // match again on the way back in.
        $this->post('K', '1009', "O''Brien", -1000.03, self::EUR, 745, '2026-09-01', 1);
        self::assertSame("O'Brien", $this->openposts()[0]['faktnr']);
        $GLOBALS['kladde_id'] = 4002;
        $this->post('K', '1009', "O''Brien", 1000.03, self::EUR, 746, '2026-10-01', 2);

        $rows = $this->openposts();
        self::assertSame('1', $rows[0]['udlignet']);
        self::assertSame(0.0, $this->groupDkk());
    }

    // ---------------------------------------------------- booking the ledger

    public function testALossIsPostedToTheCurrencyAccountAgainstTheControlAccount(): void
    {
        bookRateDifference(10.00, '6710', '7960', 'transaktioner', 4002, self::journalLine(), 'EUR');

        $rows = $this->ledger();
        self::assertCount(2, $rows);
        self::assertSame('7960', $rows[0]['kontonr'], 'the exchange loss is an expense on the VK box3 account');
        self::assertSame(10.0, (float)$rows[0]['debet']);
        self::assertSame('6710', $rows[1]['kontonr'], 'and clears what it left on the control account');
        self::assertSame(10.0, (float)$rows[1]['kredit']);
        $balance = $this->db->query("SELECT COALESCE(SUM(debet),0)-COALESCE(SUM(kredit),0) FROM transaktioner")
            ->fetchColumn();
        self::assertSame(0.0, (float)$balance,
            'the pair balances on its own, so the journal still balances after posting');
        self::assertSame('DKK', $rows[0]['valuta']);
        self::assertSame('100', (string)$rows[0]['valutakurs']);
        self::assertStringContainsString('DKK 10,00', $rows[0]['beskrivelse']);
    }

    public function testAGainIsPostedTheOtherWayRound(): void
    {
        bookRateDifference(-10.00, '6010', '7960', 'transaktioner', 4002, self::journalLine(), 'EUR');

        $rows = $this->ledger();
        self::assertSame('6010', $rows[0]['kontonr'], 'the control account is debited');
        self::assertSame(10.0, (float)$rows[0]['debet']);
        self::assertSame('7960', $rows[1]['kontonr'], 'and the gain is credited to the currency account');
        self::assertSame(10.0, (float)$rows[1]['kredit']);
    }

    public function testSimulerGetsTheSameRowInTheSimulationTable(): void
    {
        bookRateDifference(10.00, '6710', '7960', 'simulering', 4002, self::journalLine(), 'EUR');

        self::assertCount(2, $this->ledger('simulering'), 'the difference is visible in Simuler');
        self::assertCount(0, $this->ledger('transaktioner'), 'and a simulation posts nothing for real');
    }

    public function testTheJournalLineIsCarriedOntoBothRows(): void
    {
        bookRateDifference(10.00, '6710', '7960', 'transaktioner',
            4002, self::journalLine(['bilag' => '77', 'transdate' => '2026-10-02', 'faktura' => 'F-9']), 'EUR');

        foreach ($this->db->query("SELECT bilag,transdate,faktura,kladde_id FROM transaktioner")
                     ->fetchAll(PDO::FETCH_ASSOC) as $row) {
            self::assertSame('77', $row['bilag']);
            self::assertSame('2026-10-02', $row['transdate']);
            self::assertSame('F-9', $row['faktura']);
            self::assertSame('4002', (string)$row['kladde_id']);
        }
    }

    public function testADifferenceWithNowhereToGoIsRefusedRatherThanPostedToNothing(): void
    {
        // bookRateDifference() ends the request, so this reads the shipped guard rather than running it.
        $src = self::liftedSource();
        $from = strpos($src, 'function bookRateDifference(');
        $insert = strpos($src, 'insert into $tabel', $from);
        self::assertNotFalse($insert, 'bookRateDifference() no longer inserts into $tabel');
        $guard = substr($src, $from, $insert - $from);
        self::assertMatchesRegularExpression('/\$accountError\s*=\s*rateDifferenceAccountError\(\$diffkonto/', $guard,
            'a missing or unknown currency difference account would be posted to');
        self::assertStringContainsString("transaktion('rollback')", $guard,
            'and the journal would be left half posted');
    }

    public function testADifferenceAccountMustBeSetUpAndExistInTheFiscalYear(): void
    {
        self::assertSame('Manglende konto til valutadiffencer! (bilag: 7)', rateDifferenceAccountError('', 7));
        self::assertSame('Difference konto 999999 eksisterer ikke (bilag: 7)', rateDifferenceAccountError('999999', 7),
            'an account that is not in the chart of accounts had the difference committed to it');
        self::assertNotSame('', rateDifferenceAccountError('7900', 7), 'a heading cannot be posted to');
        self::assertNotSame('', rateDifferenceAccountError('7962', 7), 'nor an account from another fiscal year');
        self::assertSame('', rateDifferenceAccountError('7960', 7));
    }

    public function testTheAccountsOfAGainAndALoss(): void
    {
        self::assertSame(['7960', '6710', 10.0], rateDifferenceAccounts(10.004, '6710', '7960'), 'a loss debits the currency account');
        self::assertSame(['6010', '7960', 10.0], rateDifferenceAccounts(-10.00, '6010', '7960'), 'a gain credits it');
    }

    // ----------------------------------------------- how bogfor() wires it up

    public function testBothOpenItemSidesMeasureAndBookBeforeSettling(): void
    {
        $src = file_get_contents(self::bogforPath());
        $loop = strpos($src, '// Open items are booked on the debtor/creditor number itself');
        self::assertNotFalse($loop, 'the open-item block in bogfor() has moved');
        $end = strpos($src, '$debet[$y]=$line[\'debet\'];', $loop);
        self::assertNotFalse($end, 'the end of the open-item block in bogfor() has moved');
        $block = substr($src, $loop, $end - $loop);

        self::assertStringContainsString('foreach (journalLineOpenpostSides($row,$line) as $side)', $block,
            'the debit and the credit side each measure their own difference');
        self::assertMatchesRegularExpression('/\$kursdiff=openpostRateDifference\([^;]*\$openpostOverlay\);/', $block,
            'against the record of the journal\'s earlier lines while simulating');
        self::assertStringContainsString("bookRateDifference(\$kursdiff, \$side['samlekonto']", $block,
            'each side clears the control account its own posting used');
        self::assertSame(1, preg_match_all('/if \(!\$simuler\) openpost\(/', $block),
            'while openpost() itself still only runs when posting for real');
        self::assertMatchesRegularExpression('/\$openpostOverlay=\(\$simuler\) \? openpostOverlay\(\) : NULL;/', $src,
            'posting for real measures the open-post ledger as the earlier lines left it');
    }

    public function testTheSidesOfAJournalLineThatOpenOrSettleAPost(): void
    {
        $line = ['d_type' => 'D', 'k_type' => 'F', 'debet' => '6010', 'kredit' => '1250'];
        $sides = journalLineOpenpostSides(['debet' => '2200', 'kredit' => '1250', 'amount' => 100], $line);
        self::assertSame(['debet'], array_keys($sides));
        self::assertSame(['art' => 'D', 'konto' => '2200', 'amount' => 100, 'samlekonto' => '6010'], $sides['debet']);

        $line = ['d_type' => 'F', 'k_type' => 'K', 'debet' => '2600', 'kredit' => '6710'];
        $sides = journalLineOpenpostSides(['debet' => '2600', 'kredit' => '1009', 'amount' => 100], $line);
        self::assertSame(['kredit'], array_keys($sides));
        self::assertSame(-100, $sides['kredit']['amount'], 'the credit side is negated, as bogfor() hands it to openpost()');
        self::assertSame('6710', $sides['kredit']['samlekonto']);

        $line = ['d_type' => 'K', 'k_type' => 'D', 'debet' => '6710', 'kredit' => '6010'];
        self::assertSame(['debet', 'kredit'],
            array_keys(journalLineOpenpostSides(['debet' => '1009', 'kredit' => '2200', 'amount' => 5], $line)),
            'a transfer from a debtor to a creditor opens or settles a post on both, debit side first');
        $line = ['d_type' => 'F', 'k_type' => 'F', 'debet' => '58000', 'kredit' => '1250'];
        self::assertSame([], journalLineOpenpostSides(['debet' => '58000', 'kredit' => '1250', 'amount' => 5], $line));
    }

    public function testTheDifferenceIsBookedIntoWhicheverTableTheJournalIsPostingTo(): void
    {
        $src = file_get_contents(self::bogforPath());
        $from = strpos($src, 'function bookRateDifference(');
        self::assertNotFalse($from);
        $body = substr($src, $from, strpos($src, 'function openpost(', $from) - $from);
        self::assertStringContainsString('insert into $tabel', $body,
            'hardcoding transaktioner would keep the difference out of Simuler');
        self::assertStringNotContainsString('insert into transaktioner', $body);
    }

    // ----------------------------------------------- the open-post difference row

    public function testTheDifferenceRowIsWorthTheDifferenceInDkkAndNothingInTheCurrency(): void
    {
        foreach ([10.00, -10.00, 12.34, 0.01, -98765.43] as $dkkdiff) {
            [$rowAmount, $rowKurs] = rateDifferenceOpenpostAmount($dkkdiff);
            self::assertSame(0.001, abs((float)$rowAmount), "the row for $dkkdiff is 0,001 in the currency");
            self::assertSame(-$dkkdiff, round((float)$rowAmount * $rowKurs / 100, 2),
                "and worth -$dkkdiff in DKK, so the settled group nets to zero");
        }
    }

    public function testASalesInvoicePaidAtAHigherRateSettlesWithAPositiveDifferenceRow(): void
    {
        // Debtor 2200: EUR 1.000,03 invoiced at 745 and paid at 746 - a gain of 10,00 DKK.
        $this->post('D', '2200', 'F-746', 1000.03, self::EUR, 745, '2026-09-01', 1);
        $GLOBALS['kladde_id'] = 4002;
        $this->post('D', '2200', 'F-746', -1000.03, self::EUR, 746, '2026-10-01', 2);

        $rows = $this->openposts();
        self::assertCount(3, $rows);
        self::assertSame('EUR', $rows[2]['valuta']);
        self::assertSame(0.001, (float)$rows[2]['amount']);
        self::assertSame(0.0, $this->groupDkk());
    }

    // ----------------------------- equalizeMatchingRecords() after the posting

    private function openRow(string $faktnr, float $amount, ?string $valuta, $kurs): void
    {
        $this->db->exec("INSERT INTO openpost (konto_id,konto_nr,faktnr,amount,udlignet,udlign_id,transdate,projekt,valuta,valutakurs)
            VALUES (29,'1009','$faktnr',$amount,'0',0,'2026-09-01',''," . ($valuta === null ? 'NULL' : "'$valuta'") . ","
            . ($kurs === null ? 'NULL' : $kurs) . ")");
    }

    /** @return array<int,string> udlignet per row, in id order */
    private function settledFlags(): array
    {
        return array_map(fn($row) => (string)$row['udlignet'], $this->openposts());
    }

    public function testEqualizeLeavesADkkPaymentAgainstAForeignCurrencyInvoiceOpen(): void
    {
        // bogfor() calls equalizeMatchingRecords() after every posting. It matched number and figure only,
        // so it settled what openpost() had just refused to: DKK 500 against EUR 500.
        $this->openRow('F-950', 500.00, 'EUR', 745);
        $this->openRow('F-950', -500.00, 'DKK', 100);
        equalizeMatchingRecords();
        self::assertSame(['0', '0'], $this->settledFlags());
    }

    public function testEqualizeLeavesAForeignCurrencyPairAtDifferentRatesOpen(): void
    {
        // Settling here books nothing, so the 10,00 DKK between the rates would stay on the control account.
        $this->openRow('F-951', 1000.03, 'EUR', 745);
        $this->openRow('F-951', -1000.03, 'EUR', 746);
        equalizeMatchingRecords();
        self::assertSame(['0', '0'], $this->settledFlags());
    }

    public function testEqualizeStillSettlesAForeignCurrencyPairAtTheSameRate(): void
    {
        $this->openRow('F-952', 1000.03, 'EUR', 745);
        $this->openRow('F-952', -1000.03, 'EUR', 745);
        equalizeMatchingRecords();
        self::assertSame(['1', '1'], $this->settledFlags());
    }

    public function testEqualizeStillSettlesADkkPairWithOrWithoutTheCurrencyColumns(): void
    {
        $this->openRow('F-953', 500.00, null, null);
        $this->openRow('F-953', -500.00, 'DKK', 100);
        $this->openRow('F-954', 300.00, 'DKK', 100);
        $this->openRow('F-954', -300.00, '', null);
        equalizeMatchingRecords();
        self::assertSame(['1', '1', '1', '1'], $this->settledFlags());
    }

    public function testEqualizeSettlesAForeignCurrencyRowWithNoRateAsBefore(): void
    {
        // The same reading as openpostRateDifferenceAmount(): a row without a rate says nothing about its DKK value.
        $this->openRow('F-955', 1000.03, 'EUR', 745);
        $this->openRow('F-955', -1000.03, 'EUR', null);
        equalizeMatchingRecords();
        self::assertSame(['1', '1'], $this->settledFlags());
    }
    // -------------------------------------- the same lookup for measuring and settling

    public function testTheMatchQuerySkipsTheIdsAlreadyTakenAndHasAFixedOrder(): void
    {
        $qtxt = openpostMatchQuery(29, '', 'F', 'EUR', 100, true, [5, 'j1', '7']);
        self::assertStringContainsString(' and id not in (5,7)', $qtxt,
            'the journal\'s own unwritten posts (j1) are not stored ids');
        self::assertStringEndsWith(' order by id', $qtxt);
        self::assertStringNotContainsString('not in', openpostMatchQuery(29, '', 'F', 'EUR', 100, true));
    }

    public function testTheSameInvoiceReferenceTwiceIsMeasuredOnceTheWayOpenpostSettlesIt(): void
    {
        // A payment of EUR 100 at 746 against invoice F-960 at 745, with the reference typed as 'F-960;F-960'.
        // The measurement found the invoice twice, so the amounts did not cancel and nothing was booked, while
        // openpost() settles it once and wrote the 1,00 DKK difference on the open-post ledger only.
        $this->post('D', '2200', 'F-960', 100.00, self::EUR, 745, '2026-09-15', 1);
        $GLOBALS['kladde_id'] = 4002;

        $measured = openpostRateDifference('D', '2200', 'F-960;F-960', -100.00, self::EUR, 746, '');
        self::assertSame(-1.00, $measured, 'the difference the ledger gets');

        $this->post('D', '2200', 'F-960;F-960', -100.00, self::EUR, 746, '2026-10-05', 2);
        $rows = $this->openposts();
        self::assertCount(3, $rows, 'the invoice, the payment and one difference row');
        self::assertSame(['1', '1', '1'], array_map(fn($row) => (string)$row['udlignet'], $rows));
        self::assertSame(-$measured, round((float)$rows[2]['amount'] * (float)$rows[2]['valutakurs'] / 100, 2),
            'and the open-post ledger gets the same difference');
    }

    // ------------------------------------------- Simuler: the journal's own lines

    public function testSimulerSeesTheInvoiceAnEarlierLineOfTheSameJournalOpens(): void
    {
        // One journal: invoice F-961 EUR 100 at 745, then its payment at 746. Posting writes the invoice to
        // openpost before the payment is measured; Simuler writes nothing, so it measured nothing.
        $overlay = openpostOverlay();
        self::assertSame(0.0, openpostRateDifference('D', '2200', 'F-961', 100.00, self::EUR, 745, '', $overlay));
        self::assertSame(-1.00, openpostRateDifference('D', '2200', 'F-961', -100.00, self::EUR, 746, '', $overlay),
            'the payment settles the invoice the first line would have opened');
        self::assertCount(0, $this->openposts(), 'and the simulation wrote nothing to openpost');

        // Posting the same journal for real books the same difference.
        self::assertSame(0.0, openpostRateDifference('D', '2200', 'F-961', 100.00, self::EUR, 745, ''));
        $this->post('D', '2200', 'F-961', 100.00, self::EUR, 745, '2026-09-15', 1);
        self::assertSame(-1.00, openpostRateDifference('D', '2200', 'F-961', -100.00, self::EUR, 746, ''));
    }

    public function testSimulerMeasuresAnInvoiceOnceWhenTheJournalPaysItTwice(): void
    {
        // Invoice F-962 is posted; one journal holds two EUR 100 payments for it. Posting, the first settles
        // it and the second is left open. Simuler measured the invoice for both and booked two differences.
        $this->post('D', '2200', 'F-962', 100.00, self::EUR, 745, '2026-09-15', 1);
        $GLOBALS['kladde_id'] = 4002;

        $overlay = openpostOverlay();
        $simulated = [
            openpostRateDifference('D', '2200', 'F-962', -100.00, self::EUR, 746, '', $overlay),
            openpostRateDifference('D', '2200', 'F-962', -100.00, self::EUR, 746, '', $overlay),
        ];
        self::assertSame([-1.00, 0.0], $simulated);

        $posted = [];
        foreach ([2, 3] as $bilag) {
            $posted[] = openpostRateDifference('D', '2200', 'F-962', -100.00, self::EUR, 746, '');
            $this->post('D', '2200', 'F-962', -100.00, self::EUR, 746, '2026-10-05', $bilag);
        }
        self::assertSame($posted, $simulated, 'Simuler books what posting books');
    }

    public function testAPostTheJournalOpensIsMatchedByTheSameRulesAsAStoredOne(): void
    {
        $overlay = openpostOverlay();
        openpostRateDifference('D', '2200', 'F-963', 100.00, self::EUR, 745, '', $overlay);
        self::assertSame(0.0, openpostRateDifference('D', '2200', 'F-963', -100.00, self::EUR, 746, 'P1', $overlay),
            'not from another project');
        self::assertSame(0.0, openpostRateDifference('D', '2200', 'F-963', -40.00, self::EUR, 746, '', $overlay),
            'not for another figure');
        self::assertSame(0.0, openpostRateDifference('D', '2200', 'F-964', -100.00, self::EUR, 746, '', $overlay),
            'not under another number');
        self::assertSame(0.0, openpostRateDifference('D', '2200', 'F-963', -100.00, 0, 100, '', $overlay),
            'not from a DKK payment');
        self::assertSame(-1.00, openpostRateDifference('D', '2200', 'F-963', -100.00, self::EUR, 746, '', $overlay));
        self::assertSame(0.0, openpostRateDifference('D', '2200', 'F-963', -100.00, self::EUR, 746, '', $overlay),
            'and only once');
    }

    // ------------------------------------------------ the confirmation page

    private function journalRow(int $bilag, string $date, string $dType, string $debet, string $kType,
                                string $kredit, string $faktura, float $amount, int $valuta): void
    {
        $this->db->exec("INSERT INTO kassekladde (kladde_id,bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,faktura,
            amount,valuta,projekt,afd,ansat,ordre_id,momsfri) VALUES (4100,$bilag,'$date','line','$dType',$debet,'$kType',
            $kredit,'$faktura',$amount,$valuta,'',0,0,0,'')");
    }

    public function testTheConfirmationPageGetsTheDifferencesPostingBooks(): void
    {
        // Debtor 2200: invoice F-965 EUR 100 on 15-09 (745) and its payment on 05-10 (746) in one journal, and
        // creditor 1009: a payment at 746 of the invoice F-966 posted earlier at 745.
        $this->post('K', '1009', 'F-966', -1000.03, self::EUR, 745, '2026-09-01', 1);
        $before = $this->openposts();
        $this->journalRow(10, '2026-09-15', 'D', '2200', 'F', '1250', 'F-965', 100.00, self::EUR);
        $this->journalRow(11, '2026-10-05', 'F', '58000', 'D', '2200', 'F-965', 100.00, self::EUR);
        $this->journalRow(12, '2026-10-05', 'K', '1009', 'F', '58000', 'F-966', 1000.03, self::EUR);
        $this->journalRow(13, '2026-10-05', 'F', '58000', 'F', '1250', '', 50.00, 0);

        $differences = journalRateDifferences(4100);

        self::assertCount(2, $differences);
        self::assertSame(['bilag' => '11', 'art' => 'D', 'konto' => '2200', 'kursdiff' => -1.00, 'diffkonto' => '7960',
            'debetkonto' => '6010', 'kreditkonto' => '7960', 'belob' => 1.0],
            array_map(fn($v) => is_int($v) ? (string)$v : $v, $differences[0]),
            'the gain on the debtor: control account 6010 debited, 7960 credited');
        self::assertSame('6710', $differences[1]['kreditkonto'], 'the loss on the creditor clears its control account');
        self::assertSame('7960', $differences[1]['debetkonto']);
        self::assertSame(10.0, $differences[1]['belob']);
        self::assertSame($before, $this->openposts(), 'and working it out wrote nothing');
    }

    public function testTheConfirmationPageShowsTheCurrencyRoundingOnTheDifferenceAccountsOwnRow(): void
    {
        // A EUR 150,06 payment for two invoices, split over two credit lines in one voucher: 0,01 of in-voucher
        // rounding, and exchange differences that already gave 7960 a row with 1,50 credit.
        $kontoliste  = ['56100', '58000', '7960'];
        $kontodebet  = [1.50, 1119.45, 0.0];
        $kontokredit = [1119.44, 0.0, 1.50];

        self::assertTrue(confirmationRoundingOnListedAccount($kontoliste, $kontodebet, $kontokredit, '7960', 0.01));
        self::assertSame([1.50, 1119.45, 0.0], $kontodebet, 'nothing else moves');
        self::assertEqualsWithDelta(1.51, $kontokredit[2], 0.0001, 'the rounding is credited on the 7960 row');
        self::assertSame(1119.44, $kontokredit[0]);

        self::assertTrue(confirmationRoundingOnListedAccount($kontoliste, $kontodebet, $kontokredit, 7960, -0.02));
        self::assertEqualsWithDelta(0.02, $kontodebet[2], 0.0001, 'a negative rounding is debited there');

        $before = [$kontodebet, $kontokredit];
        self::assertFalse(confirmationRoundingOnListedAccount(['56100', '58000'], $kontodebet, $kontokredit, '7960', 0.01),
            'without a 7960 row the page prints the rounding on a row of its own, as before');
        self::assertFalse(confirmationRoundingOnListedAccount($kontoliste, $kontodebet, $kontokredit, '', 0.01),
            'and with no difference account it is left to the page\'s existing error');
        self::assertSame($before, [$kontodebet, $kontokredit]);
    }
}
