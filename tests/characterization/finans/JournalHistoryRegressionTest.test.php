<?php
// 20260907 CDX/LH Cover typed historical suggestions and posted duplicate identity/currency.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class JournalHistoryRegressionTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is required for the isolated journal fixtures.');
        }
        require_once __DIR__ . '/support/journal_history_database.php';
        require_once dirname(__DIR__, 3) . '/finans/kassekladde_includes/journalHistory.php';
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $GLOBALS['journalHistoryTestDb'] = $this->db;
        $this->db->exec('CREATE TABLE kassekladde (id INTEGER, bilag INTEGER, kladde_id INTEGER,
            transdate TEXT, d_type TEXT, debet TEXT, k_type TEXT, kredit TEXT, amount NUMERIC,
            faktura TEXT, beskrivelse TEXT)');
        $this->db->exec('CREATE TABLE transaktioner (id INTEGER, bilag INTEGER, kladde_id INTEGER,
            transdate TEXT, kontonr INTEGER, debet NUMERIC, kredit NUMERIC, faktura TEXT)');
        $this->db->exec('CREATE TABLE adresser (id INTEGER, kontonr TEXT, art TEXT)');
        $this->db->exec('CREATE TABLE openpost (konto_id INTEGER, kladde_id INTEGER, refnr INTEGER,
            transdate TEXT, faktnr TEXT, amount NUMERIC, valutakurs NUMERIC)');
        $this->db->exec("INSERT INTO adresser VALUES (1,'3000','K'),(2,'3001','K'),(3,'3000','D')");
    }

    public static function accountTypes(): array
    {
        return [['K', 'K'], ['D', 'D'], ['F', 'F'], ['', 'F'], [null, 'F']];
    }

    #[DataProvider('accountTypes')]
    public function testSuggestionsPreserveCounterAccountType(?string $storedType, string $expectedType): void
    {
        $insert = $this->db->prepare("INSERT INTO kassekladde VALUES
            (1,10,80,'2025-09-07','F','5800',?,'3000',125,'INV','Supplier payment'),
            (2,11,80,'2025-09-08',?,'3000','F','5800',125,'INV','Customer receipt')");
        $insert->execute([$storedType, $storedType]);
        foreach (['D', 'K'] as $side) {
            $suggestions = sidste_5_forslag('5800', 'F', $side, 90, 'UTF-8', 1);
            self::assertCount(1, $suggestions['rows']);
            self::assertSame('3000', $suggestions['rows'][0]['kontonr']);
            self::assertSame($expectedType, $suggestions['rows'][0]['art']);
        }
    }

    public function testSuggestionsExcludeCurrentJournalAndRetainOnlyFiveRecentRows(): void
    {
        for ($id = 1; $id <= 8; $id++) {
            $this->db->exec("INSERT INTO kassekladde VALUES
                ($id,$id,80,'2025-09-07','F','5800','F','4000',125,'INV','Historical line')");
        }
        $this->db->exec("INSERT INTO kassekladde VALUES
            (9,9,90,'2025-09-09','F','5800','K','3000',125,'INV','Current line')");
        $suggestions = sidste_5_forslag('5800', 'F', 'K', 90, 'UTF-8', 1);
        self::assertSame([8, 7, 6, 5, 4], array_column($suggestions['rows'], 'bilag'));
    }

    public function testDraftDuplicateStillUsesTheEnteredAmount(): void
    {
        $this->db->exec("INSERT INTO kassekladde VALUES
            (1,10,80,'2025-09-07','F','5800','K','3000',100,'INV','EUR payment')");
        self::assertSame('10,80,kladde', $this->duplicate('F', '5800', 'K', '3000', 100, 750));
    }

    public static function ledgerCurrencies(): array
    {
        return [[100, 100], [100, 750], [12.34, 92.55]];
    }

    #[DataProvider('ledgerCurrencies')]
    public function testPostedDuplicateUsesTheConvertedBaseAmount(float $entered, float $base): void
    {
        $this->ledger(5800, 4000, $base);
        self::assertSame('20,0,bogfort', $this->duplicate('F', '5800', 'F', '4000', $entered, $base));
        self::assertSame('0,0,0', $this->duplicate('F', '5800', 'F', '4000', $entered, $base + 1));
    }

    public static function counterpartySides(): array
    {
        return [['K', false, 1, 125, 100], ['D', true, 3, 125, 100], ['K', false, 1, 100, 750]];
    }

    #[DataProvider('counterpartySides')]
    public function testPostedDuplicateRequiresTheActualCounterparty(
        string $type, bool $debitSide, int $addressId, float $entered, float $rate
    ): void {
        $base = $entered * $rate / 100;
        $this->ledger($debitSide ? 5600 : 5800, $debitSide ? 5800 : 8600, $base);
        $signed = $debitSide ? $entered : -$entered;
        $this->db->exec("INSERT INTO openpost VALUES ($addressId,80,20,'2025-09-07','INV',$signed,$rate)");
        $match = $debitSide
            ? $this->duplicate($type, '3000', 'F', '5800', $entered, $base)
            : $this->duplicate('F', '5800', $type, '3000', $entered, $base);
        self::assertSame('20,0,bogfort', $match);
        $other = $debitSide
            ? $this->duplicate($type, '3001', 'F', '5800', $entered, $base)
            : $this->duplicate('F', '5800', $type, '3001', $entered, $base);
        self::assertSame('0,0,0', $other);
        $this->db->exec('DELETE FROM openpost');
        self::assertSame('0,0,0', $debitSide
            ? $this->duplicate($type, '3000', 'F', '5800', $entered, $base)
            : $this->duplicate('F', '5800', $type, '3000', $entered, $base));
    }

    public function testSupplierEvidenceCannotMatchACustomerWithTheSameNumber(): void
    {
        $this->ledger(5800, 8600, 125);
        $this->db->exec("INSERT INTO openpost VALUES (1,80,20,'2025-09-07','INV',-125,100)");
        self::assertSame('0,0,0', $this->duplicate('F', '5800', 'D', '3000', 125, 125));
    }

    public function testOtherVoucherOrAmountIsNotCounterpartyEvidence(): void
    {
        $this->ledger(5800, 8600, 125);
        $this->db->exec("INSERT INTO openpost VALUES
            (1,81,20,'2025-09-07','INV',-125,100),
            (1,80,21,'2025-09-07','INV',-125,100),
            (1,80,20,'2025-09-07','INV',-100,100)");
        self::assertSame('0,0,0', $this->duplicate('F', '5800', 'K', '3000', 125, 125));
    }

    public function testLedgerPairRequiresTheSameInvoiceOnBothSides(): void
    {
        $this->ledger(5800, 4000, 125);
        $this->db->exec("UPDATE transaktioner SET faktura='OTHER' WHERE id=2");
        self::assertSame('0,0,0', $this->duplicate('F', '5800', 'F', '4000', 125, 125));
    }

    public function testFiscalYearBoundariesExcludeOldLedgerRows(): void
    {
        $this->ledger(5800, 4000, 125);
        self::assertSame('0,0,0', find_dublet(99, '2025-09-07', 'F', '5800', 'F', '4000',
            125, 'INV', 125, '2026-01-01', '2026-12-31'));
    }

    private function ledger(int $debit, int $credit, float $amount): void
    {
        $insert = $this->db->prepare("INSERT INTO transaktioner VALUES
            (1,20,80,'2025-09-07',?,?,0,'INV'),(2,20,80,'2025-09-07',?,0,?,'INV')");
        $insert->execute([$debit, $amount, $credit, $amount]);
    }

    private function duplicate(string $dType, string $debit, string $kType, string $credit, float $amount, float $base): string
    {
        return find_dublet(99, '2025-09-07', $dType, $debit, $kType, $credit, $amount, 'INV',
            $base, '2025-01-01', '2025-12-31');
    }
}
