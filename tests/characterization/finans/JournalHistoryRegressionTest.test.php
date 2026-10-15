<?php
// 20260907 CDX/LH Cover typed historical suggestions and posted duplicate identity/currency.
// 20261001 CDX/LH Pin creditor history across posted and open journals before query extraction.
// 20261001 CDX/LH Cover VAT override and exemption evidence on both counter-account sides.

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
        $this->db->exec('ALTER TABLE kassekladde ADD COLUMN debetvat TEXT');
        $this->db->exec('ALTER TABLE kassekladde ADD COLUMN kreditvat TEXT');
        $this->db->exec('ALTER TABLE kassekladde ADD COLUMN momsfri TEXT');
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
        $insert = $this->db->prepare("INSERT INTO kassekladde (id,bilag,kladde_id,transdate,d_type,debet,k_type,kredit,amount,faktura,beskrivelse) VALUES
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
            $this->db->exec("INSERT INTO kassekladde (id,bilag,kladde_id,transdate,d_type,debet,k_type,kredit,amount,faktura,beskrivelse) VALUES
                ($id,$id,80,'2025-09-07','F','5800','F','4000',125,'INV','Historical line')");
        }
        $this->db->exec("INSERT INTO kassekladde (id,bilag,kladde_id,transdate,d_type,debet,k_type,kredit,amount,faktura,beskrivelse) VALUES
            (9,9,90,'2025-09-09','F','5800','K','3000',125,'INV','Current line')");
        $suggestions = sidste_5_forslag('5800', 'F', 'K', 90, 'UTF-8', 1);
        self::assertSame([8, 7, 6, 5, 4], array_column($suggestions['rows'], 'bilag'));
    }

    public function testDraftDuplicateStillUsesTheEnteredAmount(): void
    {
        $this->db->exec("INSERT INTO kassekladde (id,bilag,kladde_id,transdate,d_type,debet,k_type,kredit,amount,faktura,beskrivelse) VALUES
            (1,10,80,'2025-09-07','F','5800','K','3000',100,'INV','EUR payment')");
        self::assertSame('10,80,kladde', $this->duplicate('F', '5800', 'K', '3000', 100, 750));
    }

    public function testCreditorHistoryKeepsVoucherDateTextAccountAndOrderAcrossJournalStates(): void
    {
        $this->db->exec('CREATE TABLE kladdeliste (id INTEGER, bogfort TEXT)');
        $this->db->exec("INSERT INTO kladdeliste VALUES (80,'V'),(81,'-'),(90,'-')");
        $this->db->exec("INSERT INTO kassekladde (id,bilag,kladde_id,transdate,d_type,debet,k_type,kredit,amount,faktura,beskrivelse) VALUES
            (1,101,80,'2026-09-01','F','2210','K','3000',125,'A','Posted purchase'),
            (2,102,81,'2026-09-02','F','2750','K','3000',250,'B','Open purchase'),
            (3,103,80,'2026-09-02','F','2220','K','3000',375,'C','Latest posted purchase'),
            (4,104,90,'2026-09-03','F','2230','K','3000',500,'D','Excluded current journal'),
            (5,105,81,'2026-09-04','F','0','K','3000',625,'E','Empty counter account')");
        $this->db->exec("UPDATE kassekladde SET debetvat='K1' WHERE id=3");
        $result = sidste_5_forslag('3000', 'K', 'D', 90, 'UTF-8', 1);
        self::assertSame('Sidste 5 posteringer for kreditor: 3000', $result['heading']);
        self::assertSame([
            ['bilag' => 103, 'dato' => '02-09-2026', 'tekst' => 'Latest posted purchase', 'kontonr' => '2220', 'art' => 'F'],
            ['bilag' => 102, 'dato' => '02-09-2026', 'tekst' => 'Open purchase', 'kontonr' => '2750', 'art' => 'F'],
            ['bilag' => 101, 'dato' => '01-09-2026', 'tekst' => 'Posted purchase', 'kontonr' => '2210', 'art' => 'F'],
        ], $result['rows']);
        $posted = journal_counter_rows('3000', 'K', 'D', 90, true, 0, '2026-09-01');
        self::assertSame([103, 101], array_column($posted, 'bilag'));
        self::assertSame('K1', $posted[0]['vat']);
        self::assertSame('Latest posted purchase', $posted[0]['beskrivelse']);
        self::assertCount(1, journal_counter_rows('3000', 'K', 'D', 90, true, 1, '2026-09-02'));
    }

    public function testCounterRowsCarryMissingOverridesAndExemptionsOnBothSides(): void
    {
        $this->db->exec("CREATE TABLE kladdeliste (id INTEGER, bogfort TEXT)");
        $this->db->exec("INSERT INTO kladdeliste VALUES (80,'V')");
        foreach (['D', 'K'] as $side) {
            $debit = $side === 'D' ? '2210' : '3000';
            $credit = $side === 'D' ? '3000' : '2210';
            $debitType = $side === 'D' ? 'F' : 'K';
            $creditType = $side === 'D' ? 'K' : 'F';
            $vatField = $side === 'D' ? 'debetvat' : 'kreditvat';
            $insert = $this->db->prepare("INSERT INTO kassekladde
                (id,bilag,kladde_id,transdate,d_type,debet,k_type,kredit,beskrivelse,$vatField,momsfri)
                VALUES (?,101,80,'2026-09-01',?,?,?,?, 'VAT evidence',?,?)");
            foreach ([[null, null], ['', ''], ['K1', 'on']] as $id => [$override, $exemption]) {
                $insert->execute([$id + 1, $debitType, $debit, $creditType, $credit, $override, $exemption]);
            }
            $rows = journal_counter_rows('3000', 'K', $side, 0, true, 0, null);
            self::assertSame(['K1', '', null], array_column($rows, 'vat'));
            self::assertSame(['on', '', null], array_column($rows, 'momsfri'));
            $this->db->exec('DELETE FROM kassekladde');
        }
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
