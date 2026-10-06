<?php
// 20260907 CDX/LH Verify unlock-table authorization and retained sales-order responsibility.
// 20260923 SZ SST-755 (CodeRabbit): cover lock_token matching and refresh_lock_token().
// 20260924 SZ SST-755 (CodeRabbit): cover refresh_lock_token()'s tidspkt binding,
//                  requireTokenForTokenized, legacy-NULL lock_token matching, and the
//                  pre-migration missing-column degrade path.

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
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['unlockRecordLockTokenColumnExists'] = true;
        foreach (['kladdeliste', 'ordrer', 'ordrelinjer'] as $table) {
            $this->db->exec("CREATE TABLE $table (id INTEGER, art TEXT, tidspkt TEXT, hvem TEXT, lock_token TEXT)");
            $this->db->exec("INSERT INTO $table VALUES (1, 'DO', '123', 'salesperson', 'tokA'), (2, 'KO', '456', 'buyer', 'tokB'), (3, 'DK', '789', 'salesperson', 'tokC')");
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

    public function testUnlockClearsLockToken(): void
    {
        unlock_record('kladdeliste', 1, 'salesperson', '123');
        self::assertSame('', $this->db->query('SELECT lock_token FROM kladdeliste WHERE id=1')->fetchColumn());
    }

    // 20260923 SZ SST-755 (CodeRabbit): lock_token distinguishes a second, still-open tab on
    // the same record from the tab that most recently rendered - unlike tidspkt, which only
    // changes on an explicit acquire/save and so is still shared by both tabs at this point.

    public function testStaleLockTokenDoesNotUnlock(): void
    {
        unlock_record('kladdeliste', 1, null, null, 'not-the-current-token');
        self::assertSame(['123', 'salesperson'], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }

    public function testMatchingLockTokenUnlocks(): void
    {
        unlock_record('kladdeliste', 1, null, null, 'tokA');
        self::assertSame(['', ''], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }

    public function testLockTokenAndTidspktBothMustMatchWhenBothGiven(): void
    {
        // A stale tab's cached link only ever carries the lockToken it was rendered with, but
        // this guards the combination too: either mismatching alone must block the release.
        unlock_record('kladdeliste', 1, 'salesperson', '123', 'not-the-current-token');
        self::assertSame(['123', 'salesperson'], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }

    public function testRefreshLockTokenStampsCurrentOwnersRow(): void
    {
        refresh_lock_token('kladdeliste', 1, 'salesperson', 'fresh-token', '123');
        self::assertSame('fresh-token', $this->db->query('SELECT lock_token FROM kladdeliste WHERE id=1')->fetchColumn());
        // The row's tidspkt/hvem are untouched - refresh_lock_token() only ever stamps the token.
        self::assertSame(['123', 'salesperson'], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }

    public function testRefreshLockTokenIgnoresDifferentOwner(): void
    {
        refresh_lock_token('kladdeliste', 1, 'someone-else', 'fresh-token', '123');
        self::assertSame('tokA', $this->db->query('SELECT lock_token FROM kladdeliste WHERE id=1')->fetchColumn());
    }

    public function testRefreshLockTokenRejectsUnsupportedTable(): void
    {
        refresh_lock_token('ordrelinjer', 1, 'salesperson', 'fresh-token', '123');
        self::assertSame('tokA', $this->db->query('SELECT lock_token FROM ordrelinjer WHERE id=1')->fetchColumn());
    }

    // 20260924 SZ SST-755 (CodeRabbit): refresh_lock_token() now also requires the observed
    // tidspkt, so a stale render (one that read the row before a concurrent acquire/save
    // changed tidspkt) can't overwrite the token the replacement lock is relying on.

    public function testRefreshLockTokenRejectsStaleTidspkt(): void
    {
        refresh_lock_token('kladdeliste', 1, 'salesperson', 'fresh-token', 'not-the-current-tidspkt');
        self::assertSame('tokA', $this->db->query('SELECT lock_token FROM kladdeliste WHERE id=1')->fetchColumn());
    }

    public function testRefreshLockTokenRejectsEmptyTidspkt(): void
    {
        refresh_lock_token('kladdeliste', 1, 'salesperson', 'fresh-token', '');
        self::assertSame('tokA', $this->db->query('SELECT lock_token FROM kladdeliste WHERE id=1')->fetchColumn());
    }

    // 20260924 SZ SST-755 (CodeRabbit): a release request with no lockToken at all (an old
    // cached link, from before this feature) must only succeed against a row that itself has
    // no active token - not bypass the check entirely, or it can clear a lock a newer,
    // tokenized render now holds.

    public function testNoTokenReleaseBlockedAgainstTokenizedRowWhenRequired(): void
    {
        unlock_record('kladdeliste', 1, 'salesperson', '123', null, true);
        self::assertSame(['123', 'salesperson'], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }

    public function testNoTokenReleaseAllowedAgainstLegacyRowWhenRequired(): void
    {
        $this->db->exec("UPDATE kladdeliste SET lock_token='' WHERE id=1");
        unlock_record('kladdeliste', 1, 'salesperson', '123', null, true);
        self::assertSame(['', ''], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }

    public function testNoTokenReleaseAllowedAgainstNullLegacyRowWhenRequired(): void
    {
        $this->db->exec("UPDATE kladdeliste SET lock_token=NULL WHERE id=1");
        unlock_record('kladdeliste', 1, 'salesperson', '123', null, true);
        self::assertSame(['', ''], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }

    public function testNoTokenReleaseStillWorksWithoutRequireFlag(): void
    {
        // Unchanged behaviour for any caller that doesn't opt in - e.g. an id-only 2-arg call.
        unlock_record('kladdeliste', 1, 'salesperson', '123', null, false);
        self::assertSame(['', ''], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }

    public function testExplicitEmptyLockTokenMatchesLegacyNullColumn(): void
    {
        // kreditor/ordreliste.php's stale-lock sweep passes $r['lock_token'] ?? '' - a legacy
        // NULL column value must match this, the same as an explicitly empty one.
        $this->db->exec("UPDATE kladdeliste SET lock_token=NULL WHERE id=1");
        unlock_record('kladdeliste', 1, 'salesperson', '123', '');
        self::assertSame(['', ''], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }

    public function testExplicitEmptyLockTokenDoesNotMatchARealToken(): void
    {
        unlock_record('kladdeliste', 1, 'salesperson', '123', '');
        self::assertSame(['123', 'salesperson'], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }

    // 20260924 SZ SST-755 (CodeRabbit): betweenUpdates.php adds lock_token at login/account-open,
    // not on every request, so a session already in flight could reach these functions before
    // the migration runs on this tenant. Both must degrade to pre-token behaviour instead of
    // referencing a column that doesn't exist yet.

    public function testUnlockDegradesWhenLockTokenColumnIsMissing(): void
    {
        $GLOBALS['unlockRecordLockTokenColumnExists'] = false;
        // Actually drop the column, not just flip the mocked existence check - otherwise this
        // would still pass even if the code mistakenly referenced lock_token in its SQL, since
        // the column would still be sitting right there (CodeRabbit).
        $this->db->exec('ALTER TABLE kladdeliste DROP COLUMN lock_token');
        // A lockToken (which would otherwise block this) is ignored entirely pre-migration.
        unlock_record('kladdeliste', 1, 'salesperson', '123', 'not-the-current-token');
        self::assertSame(['', ''], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }

    public function testRefreshLockTokenNoOpsWhenLockTokenColumnIsMissing(): void
    {
        $GLOBALS['unlockRecordLockTokenColumnExists'] = false;
        // Actually drop the column - see testUnlockDegradesWhenLockTokenColumnIsMissing().
        $this->db->exec('ALTER TABLE kladdeliste DROP COLUMN lock_token');
        refresh_lock_token('kladdeliste', 1, 'salesperson', 'fresh-token', '123');
        // No lock_token column left to assert against - the meaningful assertion is that this
        // didn't throw (a real UPDATE ... lock_token would fail against this fixture now), plus
        // confirming the row's other fields are still untouched.
        self::assertSame(['123', 'salesperson'], $this->db->query('SELECT tidspkt,hvem FROM kladdeliste WHERE id=1')->fetch(PDO::FETCH_NUM));
    }
}
