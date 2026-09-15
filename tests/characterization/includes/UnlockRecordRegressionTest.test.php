<?php
// 20260907 CDX/LH Verify unlock-table authorization and retained sales-order responsibility.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class UnlockRecordRegressionTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is required for the isolated unlock fixtures.');
        }
        require_once __DIR__ . '/support/unlock_record_database.php';
        require_once dirname(__DIR__, 3) . '/includes/stdFunc/unlockRecord.php';
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $GLOBALS['unlockRecordTestDb'] = $this->db;
        foreach (['kladdeliste', 'ordrer', 'ordrelinjer'] as $table) {
            $this->db->exec("CREATE TABLE $table (id INTEGER, art TEXT, tidspkt TEXT, hvem TEXT)");
            $this->db->exec("INSERT INTO $table VALUES (1, 'DO', '123', 'salesperson'), (2, 'KO', '456', 'buyer'), (3, 'DK', '789', 'salesperson')");
        }
    }

    public static function rejectedTables(): array
    {
        return [['ordrelinjer'], ['brugere'], ['ORDRER'], ['ordrer;drop'], [['ordrer']], [null], ['']];
    }

    #[DataProvider('rejectedTables')]
    public function testUnsupportedTablesCannotBeUnlocked($table): void
    {
        unlock_record($table, 1);
        self::assertSame(['123', 'salesperson'], $this->db->query('SELECT tidspkt,hvem FROM ordrelinjer WHERE id=1')->fetch(PDO::FETCH_NUM));
        self::assertSame('123', $this->db->query('SELECT tidspkt FROM ordrer WHERE id=1')->fetchColumn());
    }

    public function testJournalUnlockAffectsOnlyTheSelectedRecord(): void
    {
        unlock_record('kladdeliste', 1);
        self::assertSame(['', ''], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
        self::assertSame('456', $this->db->query('SELECT tidspkt FROM kladdeliste WHERE id=2')->fetchColumn());
    }

    public function testOrderUnlockPreservesSalesResponsibility(): void
    {
        foreach ([1, 2, 3] as $id) {
            unlock_record('ordrer', $id);
        }
        self::assertSame([['', 'salesperson'], ['', ''], ['', 'salesperson']], $this->db->query('SELECT tidspkt,hvem FROM ordrer ORDER BY id')->fetchAll(PDO::FETCH_NUM));
    }

    public function testMissingIdDoesNotUnlockRecords(): void
    {
        unlock_record('ordrer', 0);
        self::assertSame(3, (int)$this->db->query("SELECT count(*) FROM ordrer WHERE tidspkt<>''")->fetchColumn());
    }

    // 20260908 SZ SST-755: a stale tab's delayed release must not clobber a lock a newer
    // tab (different tidspkt) or a different user has since acquired.

    public function testStaleTidspktDoesNotUnlock(): void
    {
        unlock_record('kladdeliste', 1, null, 'not-the-current-tidspkt');
        self::assertSame(['123', 'salesperson'], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }

    public function testMatchingTidspktUnlocks(): void
    {
        unlock_record('kladdeliste', 1, null, '123');
        self::assertSame(['', ''], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }

    public function testDifferentOwnerDoesNotUnlock(): void
    {
        unlock_record('kladdeliste', 1, 'someone-else', null);
        self::assertSame(['123', 'salesperson'], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }

    public function testMatchingOwnerUnlocks(): void
    {
        unlock_record('kladdeliste', 1, 'salesperson', null);
        self::assertSame(['', ''], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }

    public function testMatchingOwnerAndTidspktUnlocksOrderWhileRetainingResponsibility(): void
    {
        unlock_record('ordrer', 1, 'salesperson', '123');
        self::assertSame(['', 'salesperson'], $this->db->query('SELECT tidspkt,hvem FROM ordrer WHERE id=1')->fetch(PDO::FETCH_NUM));
    }
}
