<?php
// 20261105 CDX/NTR Cover doc-pool and settlement approval invalidation and partial posting after a normalising save.
// 20261105 CDX/NTR Document fixture setup, data providers, and approval regression expectations.
// 20261122 CDX/ASR Cover stale approvals, both save orders, voucher zero and unflagged database failures.

namespace JournalApprovalFixture;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JournalApprovalTest extends TestCase {
    private PDO $db;
    private string $dsn;

    /**
     * Create fresh temporary tables and load real helpers without running page request handlers.
     *
     * The default SQLite connection needs no app bootstrap; SALDI_CHAR_DSN selects an isolated
     * PostgreSQL connection to exercise the same SQL with production column types.
     */
    protected function setUp(): void {
        $dsn = getenv('SALDI_CHAR_DSN') ?: 'sqlite::memory:';
        if ($this->name() === 'testConcurrentSaveCannotBeApprovedFromOldSnapshot' && !str_starts_with($dsn, 'pgsql:')) {
            require_once __DIR__ . '/../support/database/TestDatabase.php';
            $reason = \TestDatabase::unavailableReason();
            if ($reason !== null) self::markTestSkipped($reason);
            $database = \TestDatabase::sqlFixture(self::class);
            $dsn = 'pgsql:host=' . (getenv('SALDI_CHAR_PGHOST') ?: 'postgres') . ';dbname=' . $database->name();
        }
        $this->dsn = $dsn;
        if (str_starts_with($dsn, 'sqlite:') && !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite or SALDI_CHAR_DSN is required for isolated approval fixtures.');
        }
        $this->db = new PDO($dsn, getenv('SALDI_CHAR_PGUSER') ?: null, getenv('SALDI_CHAR_PGPASS') ?: null);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $GLOBALS['journalApprovalDb'] = $this->db;
        $GLOBALS['db_modify_fejl'] = false;
        $GLOBALS['journalApprovalFailMove'] = false;
        $GLOBALS['journalApprovalFailWrite'] = '';
        $GLOBALS['kk_committed'] = 0;
        require_once __DIR__ . '/support/journal_approval_database.php';
        if (!function_exists(__NAMESPACE__ . '\\kk_post_approved')) {
            $root = dirname(__DIR__, 3);
            $source = file_get_contents($root . '/finans/kassekladde_includes/journalApproval.php');
            eval('namespace ' . __NAMESPACE__ . '; use RuntimeException; use Throwable; ' . substr($source, 5));
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
                debet NUMERIC(15,0), kredit NUMERIC(15,0), d_type TEXT, k_type TEXT, amount NUMERIC, faktura TEXT, valuta INTEGER,
                momsfri TEXT, afd INTEGER, medarb INTEGER, projekt TEXT, debetvat TEXT, kreditvat TEXT, valutakurs NUMERIC, ansat INTEGER, betal_id TEXT);
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

    /**
     * Roll back an unfinished fixture transaction and release request/database state between cases.
     */
    protected function tearDown(): void {
        if (isset($this->db) && $this->db->inTransaction()) $this->db->rollBack();
        $_POST = array();
        unset($GLOBALS['journalApprovalDb']);
    }

    /**
     * Read one fixture line, including approval metadata, for before/after comparisons.
     *
     * @param int $id Fixture line id.
     * @return array<string, mixed> Stored posting values and approval metadata.
     */
    private function row(int $id = 10): array {
        return $this->db->query('SELECT * FROM kassekladde WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Posting-field edits that must invalidate approval without touching the other journal line.
     *
     * @return array<string, array{
     *     0: array<string, string>, Submitted document-pool form fields.
     *     1: string, Column whose saved value is asserted.
     *     2: string, Expected saved value as text.
     * }>
     */
    public static function edits(): array {
        return [
            'amount' => [['sum' => '999,00'], 'amount', '999'],
            'zero amount' => [['sum' => '0'], 'amount', '0'],
            'case-only text' => [['beskrivelse' => 'bank'], 'beskrivelse', 'bank'],
            'text with trailing space' => [['beskrivelse' => 'Bank '], 'beskrivelse', 'Bank '],
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

    /**
     * Exercise the shared Save/Attach helper and require all approval metadata to clear on an edit.
     *
     * @param array<string, string> $post Submitted field changes.
     * @param string $column Column checked after saving.
     * @param string $expected Expected saved value.
     */
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

    /**
     * Repeat an unchanged full-field save to verify neither shared entry path revokes approval.
     */
    public function testUnchangedSaveAndAttachKeepApproval(): void {
        $_POST = ['bilagsnr' => '1', 'dato' => '01-11-2026', 'beskrivelse' => 'Bank', 'debet' => '1610',
            'kredit' => '5800', 'sum' => '80,00', 'afd' => '0', 'medarb' => '0', 'projekt' => '0',
            'momsfri' => '', 'debetvat' => 'K1', 'kreditvat' => ''];
        $before = $this->row();
        insertDocUpdateKassekladdeLine(10);
        insertDocUpdateKassekladdeLine(10);
        self::assertSame($before, $this->row());
    }

    /**
     * Treat absent optional values and their normalised zero/empty form values as the same approval.
     */
    public function testUnchangedSaveWithAbsentOptionalFieldsKeepsApproval(): void {
        $this->db->exec('UPDATE kassekladde SET afd=NULL, projekt=NULL, momsfri=NULL WHERE id=10');
        $_POST = ['afd' => '0', 'projekt' => '0', 'momsfri' => ''];
        insertDocUpdateKassekladdeLine(10);
        self::assertTrue(kk_godkendt_bool($this->row()['godkendt']));
    }

    /**
     * Exercise invoice assignment with an existing counterparty and with a blank account side.
     *
     * @return array<int, array{0: bool}> Whether the counterparty already exists on the line.
     */
    public static function settlements(): array { return [[true], [false]]; }

    /**
     * Assign a verified open invoice and require approval metadata to clear in the same transaction.
     *
     * @param bool $existing Whether settlement must retain or fill the counterparty.
     */
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

    /**
     * Reject a stale snapshot before writing, preserving the complete line and closing the transaction.
     */
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

    /**
     * Save-time normalisations that can revoke approval before the partial-post move.
     *
     * @return array<int, array{0: string}> Posting column changed by the simulated save.
     */
    public static function normalisations(): array { return [['debetvat'], ['bilag']]; }

    /**
     * Simulate a save revoking one approval and ensure no otherwise-approved subset is moved.
     *
     * @param string $column Posting field normalised by the save.
     */
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

    /**
     * Verify the normal path moves both approved vouchers and commits the partial-post audit entry.
     */
    public function testUnchangedApprovedLinesMoveAndAuditTogether(): void {
        $result = kk_post_approved(1, 'bookkeeper', 1, kk_approved_line_ids(1));
        self::assertSame('', $result['error']);
        self::assertSame(2, $result['moved']);
        self::assertSame(2, $result['bilag']);
        self::assertSame($result['new_kladde_id'], (int)$this->row()['kladde_id']);
        self::assertSame('journal.partial_post', $this->db->query('SELECT action FROM approval_audit')->fetchColumn());
    }

    /**
     * Keep an entire mixed voucher in the source while moving the independently approved voucher.
     */
    public function testMixedVoucherStaysWhileApprovedVoucherMoves(): void {
        $this->db->exec("INSERT INTO kassekladde (id,kladde_id,bilag,godkendt) VALUES (12,1,1,FALSE)");
        $result = kk_post_approved(1, 'bookkeeper', 1, kk_approved_line_ids(1));
        self::assertSame(['1'], $result['mixed']);
        self::assertSame(1, $result['moved']);
        self::assertSame(1, (int)$this->row()['kladde_id']);
    }

    /** A mixed voucher zero stays intact while an independent approved voucher moves. */
    public function testMixedZeroVoucherStaysTogether(): void {
        $this->db->exec('UPDATE kassekladde SET bilag=0; UPDATE kassekladde SET godkendt=FALSE WHERE id=11');
        $this->db->exec("INSERT INTO kassekladde (id,kladde_id,bilag,godkendt) VALUES (12,1,3,TRUE)");
        $result = kk_post_approved(1, 'bookkeeper', 1);
        self::assertSame(1, $result['moved']);
        self::assertSame(['0'], $result['mixed']);
        self::assertSame(1, (int)$this->row(10)['kladde_id']);
        self::assertSame(1, (int)$this->row(11)['kladde_id']);
    }

    /** All approved lines numbered zero move as one voucher, including zero-plus-N rows. */
    public function testFullyApprovedZeroVoucherMovesAsOneGroup(): void {
        $this->db->exec('UPDATE kassekladde SET bilag=0');
        $result = kk_post_approved(1, 'bookkeeper', 1);
        self::assertSame(2, $result['moved']);
        self::assertSame(1, $result['bilag']);
    }

    /** @return array<string, array{0: string}> Failed write prefixes that must roll back the move. */
    public static function failedWrites(): array {
        return ['new journal' => ['insert into kladdeliste'], 'move' => ['update kassekladde set kladde_id']];
    }

    /** A returned error must roll back even when db_modify_fejl remains false. */
    #[DataProvider('failedWrites')]
    public function testUnflaggedWriteFailureRollsBack(string $prefix): void {
        $GLOBALS['journalApprovalFailWrite'] = $prefix;
        $result = kk_post_approved(1, 'bookkeeper', 1);
        self::assertSame('db', $result['error']);
        self::assertFalse($GLOBALS['db_modify_fejl']);
        self::assertSame(2, (int)$this->db->query('SELECT count(*) FROM kladdeliste')->fetchColumn());
        self::assertSame(1, (int)$this->row()['kladde_id']);
        self::assertSame(0, (int)$this->db->query('SELECT count(*) FROM approval_audit')->fetchColumn());
        self::assertFalse($this->db->inTransaction());
    }

    /** A changed second line rejects the whole batch, without approving the first line. */
    public function testStaleBatchApprovalWritesNothing(): void {
        $snapshots = [10 => kk_approval_snapshot($this->row(10)), 11 => kk_approval_snapshot($this->row(11))];
        $this->db->exec('UPDATE kassekladde SET godkendt=FALSE; UPDATE kassekladde SET amount=999 WHERE id=11');
        $result = kk_set_approval(1, [10, 11], true, $snapshots, 'reviewer');
        self::assertSame('changed', $result['error']);
        self::assertFalse(kk_godkendt_bool($this->row(10)['godkendt']));
        self::assertFalse(kk_godkendt_bool($this->row(11)['godkendt']));
        self::assertSame(0, (int)$this->db->query('SELECT count(*) FROM approval_audit')->fetchColumn());
    }

    /** Missing fingerprints cannot approve; unapproval is always allowed on an open journal. */
    public function testApprovalNeedsSnapshotButUnapprovalDoesNot(): void {
        self::assertSame('changed', kk_set_approval(1, [10], true, [], 'reviewer')['error']);
        self::assertTrue(kk_set_approval(1, [10], false, [], 'reviewer')['ok']);
        self::assertFalse(kk_godkendt_bool($this->row()['godkendt']));
        self::assertNull($this->row()['godkendt_af']);
    }

    /** Approval changes leave the fingerprint valid for later toggles on unchanged content. */
    public function testTogglesKeepSnapshotAndIgnoreForeignIds(): void {
        $snapshot = kk_approval_snapshot($this->row());
        $this->db->exec("INSERT INTO kassekladde (id,kladde_id,bilag,godkendt) VALUES (12,2,1,FALSE)");
        self::assertTrue(kk_set_approval(1, [10], false, [], 'reviewer')['ok']);
        $result = kk_set_approval(1, [10, 12], true, [10 => $snapshot], 'another');
        self::assertSame([10], $result['ids']);
        self::assertSame($snapshot, kk_approval_snapshot($this->row()));
        self::assertSame('another', $this->row()['godkendt_af']);
        self::assertFalse(kk_godkendt_bool($this->row(12)['godkendt']));
        self::assertSame('not_open', kk_set_approval(2, [12], false, [], 'reviewer')['error']);
    }

    /** Failed approval writes do not produce successful responses or audit entries. */
    public function testFailedApprovalRollsBack(): void {
        $GLOBALS['journalApprovalFailWrite'] = 'update kassekladde set godkendt';
        self::assertSame('db', kk_set_approval(1, [10], false, [], 'reviewer')['error']);
        self::assertTrue(kk_godkendt_bool($this->row()['godkendt']));
        self::assertSame(0, (int)$this->db->query('SELECT count(*) FROM approval_audit')->fetchColumn());
    }

    /** Execute the real opdater read-and-update block inside its normal transaction boundary. */
    private function journalSaveAmount(string $newAmount): void {
        $source = file_get_contents(dirname(__DIR__, 3) . '/finans/kassekladde.php');
        $start = strpos($source, "\t\t\t\t\t\$kkGodkReset = '';");
        $end = strpos($source, "\n\t\t\t\t} elseif (", $start);
        $r = ['id' => 10];
        $bilag = 1; $transdate = '2026-11-01'; $beskrivelse = 'Bank';
        $d_type = $k_type = 'F'; $debet = 1610; $kredit = 5800; $faktura = '';
        $amount = $newAmount; $debetvat = 'K1'; $kreditvat = $momsfri = '';
        $afd = $projekt = $ansat_id = $valutakode = 0; $forfaldsdate = null; $betal_id = '';
        $this->db->beginTransaction();
        eval('namespace ' . __NAMESPACE__ . '; ' . substr($source, $start, $end - $start));
        $this->db->commit();
    }

    /** Approval before a changed save is cleared; approval after that save rejects the old view. */
    public function testBothSaveApprovalOrdersAndUnchangedSave(): void {
        $snapshot = kk_approval_snapshot($this->row());
        $this->journalSaveAmount('80');
        self::assertTrue(kk_godkendt_bool($this->row()['godkendt']));
        $this->journalSaveAmount('99');
        self::assertFalse(kk_godkendt_bool($this->row()['godkendt']));
        self::assertSame('changed', kk_set_approval(1, [10], true, [10 => $snapshot], 'reviewer')['error']);
        self::assertTrue(kk_set_approval(1, [10], true, [10 => kk_approval_snapshot($this->row())], 'reviewer')['ok']);
        $this->journalSaveAmount('100');
        self::assertFalse(kk_godkendt_bool($this->row()['godkendt']));
    }

    /** A second connection must compare the content after a concurrent save releases its row lock. */
    public function testConcurrentSaveCannotBeApprovedFromOldSnapshot(): void {
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            self::markTestSkipped('PostgreSQL is required to exercise real row locks.');
        }
        $schema = 'approval_' . bin2hex(random_bytes(8));
        $this->db->exec('CREATE SCHEMA ' . $schema);
        $worker = null;
        try {
            foreach (['kladdeliste', 'kassekladde', 'approval_audit'] as $table) {
                $this->db->exec("CREATE TABLE $schema.$table (LIKE pg_temp.$table INCLUDING ALL)");
                $this->db->exec("INSERT INTO $schema.$table SELECT * FROM pg_temp.$table");
            }
            $this->db->exec("SET search_path TO $schema, pg_temp");
            $this->db->exec('UPDATE kassekladde SET godkendt=FALSE');
            $snapshot = kk_approval_snapshot($this->row());
            $this->db->beginTransaction();
            $this->db->query('SELECT * FROM kassekladde WHERE id=10 FOR UPDATE')->fetchAll();
            $worker = proc_open([PHP_BINARY, __DIR__ . '/support/journal_approval_worker.php', $schema, $snapshot],
                [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(getenv(), ['SALDI_CHAR_DSN' => $this->dsn]));
            self::assertIsResource($worker);
            $waiting = false;
            $deadline = microtime(true) + 5;
            do {
                $this->db->query('SELECT pg_stat_clear_snapshot()')->fetchAll();
                $waiting = (bool)$this->db->query("SELECT count(*) FROM pg_stat_activity WHERE application_name=" . $this->db->quote($schema) . " AND wait_event_type='Lock'")->fetchColumn();
                if (!$waiting) usleep(10000);
            } while (!$waiting && microtime(true) < $deadline && proc_get_status($worker)['running']);
            self::assertTrue($waiting, 'The approval must wait for the in-flight save.');
            $this->db->exec('UPDATE kassekladde SET amount=99 WHERE id=10');
            $this->db->commit();
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0, proc_close($worker), $err);
            $worker = null;
            self::assertSame('changed', json_decode($out, true)['error'], $out);
            self::assertFalse(kk_godkendt_bool($this->row()['godkendt']));
        } finally {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if (is_resource($worker)) { proc_terminate($worker); proc_close($worker); }
            $this->db->exec('SET search_path TO public');
            $this->db->exec('DROP SCHEMA ' . $schema . ' CASCADE');
        }
    }

    /**
     * Inject a failed move after journal creation and require both the new journal and audit to roll back.
     */
    public function testFailedMoveRollsBackNewJournal(): void {
        $GLOBALS['journalApprovalFailMove'] = true;
        $result = kk_post_approved(1, 'bookkeeper', 1);
        self::assertSame('db', $result['error']);
        self::assertSame(2, (int)$this->db->query('SELECT count(*) FROM kladdeliste')->fetchColumn());
        self::assertSame(1, (int)$this->row()['kladde_id']);
        self::assertSame(0, (int)$this->db->query('SELECT count(*) FROM approval_audit')->fetchColumn());
    }

    /**
     * Refuse closed journals and empty approval sets without leaking the transaction to later work.
     */
    public function testClosedAndEmptyJournalsLeaveNoTransactionOpen(): void {
        self::assertSame('not_open', kk_post_approved(2, 'bookkeeper', 1)['error']);
        self::assertFalse($this->db->inTransaction());
        $this->db->exec('UPDATE kassekladde SET godkendt=FALSE');
        self::assertSame('none', kk_post_approved(1, 'bookkeeper', 1)['error']);
        self::assertFalse($this->db->inTransaction());
    }
}
