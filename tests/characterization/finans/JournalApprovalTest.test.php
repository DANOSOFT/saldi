<?php
// 20261105 CDX/NTR Cover doc-pool and settlement approval invalidation and partial posting after a normalising save.

namespace JournalApprovalFixture;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JournalApprovalTest extends TestCase {
    private PDO $db;

    protected function setUp(): void {
        $dsn = getenv('SALDI_CHAR_DSN') ?: 'sqlite::memory:';
        if (str_starts_with($dsn, 'sqlite:') && !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite or SALDI_CHAR_DSN is required for isolated approval fixtures.');
        }
        $this->db = new PDO($dsn, getenv('SALDI_CHAR_PGUSER') ?: null, getenv('SALDI_CHAR_PGPASS') ?: null);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $GLOBALS['journalApprovalDb'] = $this->db;
        $GLOBALS['db_modify_fejl'] = false;
        $GLOBALS['journalApprovalFailMove'] = false;
        require_once __DIR__ . '/support/journal_approval_database.php';
        if (!function_exists(__NAMESPACE__ . '\\kk_post_approved')) {
            $root = dirname(__DIR__, 3);
            // The page include performs request I/O. Load only its real shared Save/Attach helper.
            $source = file_get_contents($root . '/includes/docsIncludes/insertDoc.php');
            $start = strpos($source, "if (!function_exists('insertDocUpdateKassekladdeLine')) {");
            $end = strpos($source, '// Handle updateOnly action', $start);
            eval('namespace ' . __NAMESPACE__ . '; ' . substr($source, $start, $end - $start));
            $source = file_get_contents($root . '/finans/kassekladde_includes/postApproved.php');
            $source = preg_replace('/^include_once\(.*auditLog.php.*\);$/m', '', $source);
            eval('namespace ' . __NAMESPACE__ . '; use RuntimeException; use Throwable; ' . substr($source, 5));
            $source = file_get_contents($root . '/finans/kassekladde_includes/autoSettlement.php');
            eval('namespace ' . __NAMESPACE__ . '; use RuntimeException; use Throwable; ' . substr($source, 5));
        }
        $this->db->exec("CREATE TEMP TABLE kladdeliste (id INTEGER PRIMARY KEY, bogfort TEXT, kladdenote TEXT, kladdedate DATE, oprettet_af TEXT);
            CREATE TEMP TABLE kassekladde (id INTEGER PRIMARY KEY, kladde_id INTEGER, bilag INTEGER, godkendt BOOLEAN,
                godkendt_af TEXT, godkendt_tid TIMESTAMP, transdate DATE, forfaldsdate DATE, beskrivelse TEXT,
                debet INTEGER, kredit INTEGER, d_type TEXT, k_type TEXT, amount NUMERIC, faktura TEXT, valuta INTEGER,
                momsfri TEXT, afd INTEGER, medarb INTEGER, projekt INTEGER, debetvat TEXT, kreditvat TEXT, valutakurs NUMERIC);
            CREATE TEMP TABLE approval_audit (action TEXT, details TEXT);
            CREATE TEMP TABLE adresser (id INTEGER, kontonr TEXT, art TEXT);
            CREATE TEMP TABLE openpost (id INTEGER, konto_id INTEGER, konto_nr TEXT, faktnr TEXT, udlignet TEXT);
            INSERT INTO kladdeliste (id,bogfort,kladdenote) VALUES (1,'-','Bank'),(2,'B','Posted');
            INSERT INTO kassekladde (id,kladde_id,bilag,godkendt,godkendt_af,godkendt_tid,transdate,beskrivelse,debet,kredit,d_type,k_type,amount,faktura,valuta,momsfri,afd,medarb,projekt,debetvat,kreditvat)
                VALUES (10,1,1,TRUE,'reviewer','2026-11-01 10:00:00','2026-11-01','Bank',1610,5800,'F','F',80,'',0,'',0,0,0,'K1',''),
                       (11,1,2,TRUE,'reviewer','2026-11-01 10:00:00','2026-11-01','Bank',1610,5800,'F','F',80,'',0,'',0,0,0,'K1','');");
        $_POST = array();
        $GLOBALS['regnaar'] = 2026;
    }

    protected function tearDown(): void {
        if (isset($this->db) && $this->db->inTransaction()) $this->db->rollBack();
        $_POST = array();
        unset($GLOBALS['journalApprovalDb']);
    }

    private function row(int $id = 10): array {
        return $this->db->query('SELECT * FROM kassekladde WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
    }

    public static function edits(): array {
        return [
            'amount' => [['sum' => '999,00'], 'amount', '999'],
            'zero amount' => [['sum' => '0'], 'amount', '0'],
            'text with quote' => [['beskrivelse' => "Supplier's invoice"], 'beskrivelse', "Supplier's invoice"],
            'date' => [['dato' => '02-11-2026'], 'transdate', '2026-11-02'],
            'voucher number' => [['bilagsnr' => '9'], 'bilag', '9'],
            'debit account and type' => [['debet' => 'K3000'], 'd_type', 'K'],
            'credit account and type' => [['kredit' => 'D3000'], 'k_type', 'D'],
            'clear account' => [['debet' => ''], 'debet', '0'],
            'invoice' => [['fakturanr' => 'INV-2'], 'faktura', 'INV-2'],
            'currency' => [['valuta' => '2'], 'valuta', '2'],
            'department' => [['afd' => '2'], 'afd', '2'],
            'employee' => [['medarb' => '2'], 'medarb', '2'],
            'project' => [['projekt' => '2'], 'projekt', '2'],
            'VAT exemption' => [['momsfri' => 'on'], 'momsfri', 'on'],
            'VAT' => [['debetvat' => 'K2'], 'debetvat', 'K2'],
            'due date' => [['forfald' => '30-11-2026'], 'forfaldsdate', '2026-11-30'],
        ];
    }

    #[DataProvider('edits')]
    public function testDocPoolEditClearsOnlyTheChangedLinesApproval(array $post, string $column, string $expected): void {
        $_POST = $post;
        insertDocUpdateKassekladdeLine(10);
        $row = $this->row();
        self::assertSame($expected, (string)$row[$column]);
        self::assertFalse(kk_godkendt_bool($row['godkendt']));
        self::assertNull($row['godkendt_af']);
        self::assertNull($row['godkendt_tid']);
        self::assertTrue(kk_godkendt_bool($this->row(11)['godkendt']));
    }

    public function testUnchangedSaveAndAttachKeepApproval(): void {
        $_POST = ['bilagsnr' => '1', 'dato' => '01-11-2026', 'beskrivelse' => 'Bank', 'debet' => '1610',
            'kredit' => '5800', 'sum' => '80,00', 'afd' => '0', 'medarb' => '0', 'projekt' => '0',
            'momsfri' => '', 'debetvat' => 'K1', 'kreditvat' => ''];
        $before = $this->row();
        insertDocUpdateKassekladdeLine(10);
        insertDocUpdateKassekladdeLine(10);
        self::assertSame($before, $this->row());
    }

    public function testUnchangedSaveWithAbsentOptionalFieldsKeepsApproval(): void {
        $this->db->exec('UPDATE kassekladde SET afd=NULL, projekt=NULL, momsfri=NULL WHERE id=10');
        $_POST = ['afd' => '0', 'projekt' => '0', 'momsfri' => ''];
        insertDocUpdateKassekladdeLine(10);
        self::assertTrue(kk_godkendt_bool($this->row()['godkendt']));
    }

    public static function settlements(): array { return [[true], [false]]; }

    #[DataProvider('settlements')]
    public function testSettlementClearsApprovalForExistingAndNewCounterparty(bool $existing): void {
        $this->db->exec("UPDATE kassekladde SET debet=" . ($existing ? '3000' : '0') . ", d_type='" . ($existing ? 'K' : 'F') . "' WHERE id=10;
            INSERT INTO adresser VALUES (20,'3000','K'); INSERT INTO openpost VALUES (30,20,'3000','INV-1','0')");
        $snapshot = autoSettlementSnapshot($this->row());
        saveAutoSettlement(1,10,30,20,$snapshot);
        self::assertSame('INV-1', $this->row()['faktura']);
        self::assertSame(3000, (int)$this->row()['debet']);
        self::assertFalse(kk_godkendt_bool($this->row()['godkendt']));
        self::assertNull($this->row()['godkendt_af']);
        self::assertNull($this->row()['godkendt_tid']);
    }

    public function testRejectedSettlementPreservesApprovalAndRollsBack(): void {
        $before = $this->row();
        try {
            saveAutoSettlement(1, 10, 30, 20, 'stale snapshot');
            self::fail('Expected a stale snapshot rejection.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('changed', $error->getMessage());
        }
        self::assertSame($before, $this->row());
        self::assertFalse($this->db->inTransaction());
    }

    public static function normalisations(): array { return [['debetvat'], ['bilag']]; }

    #[DataProvider('normalisations')]
    public function testLostApprovalStopsEveryMoveBeforeJournalCreation(string $column): void {
        $expected = kk_approved_line_ids(1);
        $this->db->exec("UPDATE kassekladde SET $column='3', godkendt=FALSE, godkendt_af=NULL, godkendt_tid=NULL WHERE id=11");
        $result = kk_post_approved(1, 'bookkeeper', 1, $expected);
        self::assertSame('approval_changed', $result['error']);
        self::assertSame(0, $result['moved']);
        self::assertSame(2, (int)$this->db->query('SELECT count(*) FROM kladdeliste')->fetchColumn());
        self::assertSame(2, (int)$this->db->query('SELECT count(*) FROM kassekladde WHERE kladde_id=1')->fetchColumn());
        self::assertFalse($this->db->inTransaction());
    }

    public function testUnchangedApprovedLinesMoveAndAuditTogether(): void {
        $result = kk_post_approved(1, 'bookkeeper', 1, kk_approved_line_ids(1));
        self::assertSame('', $result['error']);
        self::assertSame(2, $result['moved']);
        self::assertSame(2, $result['bilag']);
        self::assertSame($result['new_kladde_id'], (int)$this->row()['kladde_id']);
        self::assertSame('journal.partial_post', $this->db->query('SELECT action FROM approval_audit')->fetchColumn());
    }

    public function testMixedVoucherStaysWhileApprovedVoucherMoves(): void {
        $this->db->exec("INSERT INTO kassekladde (id,kladde_id,bilag,godkendt) VALUES (12,1,1,FALSE)");
        $result = kk_post_approved(1, 'bookkeeper', 1, kk_approved_line_ids(1));
        self::assertSame(['1'], $result['mixed']);
        self::assertSame(1, $result['moved']);
        self::assertSame(1, (int)$this->row()['kladde_id']);
    }

    public function testZeroVoucherLinesAreIndependentGroups(): void {
        $this->db->exec('UPDATE kassekladde SET bilag=0; UPDATE kassekladde SET godkendt=FALSE WHERE id=11');
        $result = kk_post_approved(1, 'bookkeeper', 1);
        self::assertSame(1, $result['moved']);
        self::assertSame([], $result['mixed']);
    }

    public function testFailedMoveRollsBackNewJournal(): void {
        $GLOBALS['journalApprovalFailMove'] = true;
        $result = kk_post_approved(1, 'bookkeeper', 1);
        self::assertSame('db', $result['error']);
        self::assertSame(2, (int)$this->db->query('SELECT count(*) FROM kladdeliste')->fetchColumn());
        self::assertSame(1, (int)$this->row()['kladde_id']);
        self::assertSame(0, (int)$this->db->query('SELECT count(*) FROM approval_audit')->fetchColumn());
    }

    public function testClosedAndEmptyJournalsLeaveNoTransactionOpen(): void {
        self::assertSame('not_open', kk_post_approved(2, 'bookkeeper', 1)['error']);
        self::assertFalse($this->db->inTransaction());
        $this->db->exec('UPDATE kassekladde SET godkendt=FALSE');
        self::assertSame('none', kk_post_approved(1, 'bookkeeper', 1)['error']);
        self::assertFalse($this->db->inTransaction());
    }
}
