<?php
// tests/characterization/includes/Release511UpgradeTest.test.php
//
// 20261110 CDX/NTR Cover the 5.1.1 login upgrade, version stamps, retries and resumable MB-73 work.
// 20261111 CDX/NTR Use common database ownership and inspection retention.
// 20261113 CL/MJ SST-882 betweenUpdates.php gets $db as on a live login, and $db and the regnskab row name the tenant's real database.
//                A migration that looks its table up by $db (ensureTableAndColumns()) then finds it on the second run.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Release511UpgradeTest extends TestCase
{
    private $admin;
    private $tenant;
    private $registry;
    private string $database;
    private ?TestDatabase $ownedDatabase = null;

    protected function setUp(): void
    {
        if (!extension_loaded('pgsql') || CharacterizationEnv::pgPass() === '') {
            self::markTestSkipped('pgsql and SALDI_CHAR_PGPASS are required');
        }
        $this->ownedDatabase = TestDatabase::create(static::class, false);
        $this->database = $this->ownedDatabase->name();
        $this->admin = CharacterizationEnv::connect(CharacterizationEnv::masterDb());

        $this->tenant = CharacterizationEnv::connect($this->database);
        $this->registry = CharacterizationEnv::connect($this->database);
        CharacterizationEnv::rows($this->registry, 'CREATE SCHEMA registry');
        CharacterizationEnv::rows($this->registry, 'SET search_path TO registry');
        foreach ([$this->tenant, $this->registry] as $connection) {
            CharacterizationEnv::rows($connection, 'CREATE TABLE settings (var_name text, var_grp text, var_value text, var_description text)');
            CharacterizationEnv::rows($connection, 'CREATE TABLE tekster (tekst_id integer, sprog_id integer, tekst text)');
        }
        CharacterizationEnv::rows($this->registry, 'CREATE TABLE regnskab (db text, version text)');
        CharacterizationEnv::rows($this->registry, "INSERT INTO regnskab VALUES ('{$this->database}', '5.1.0')");
        CharacterizationEnv::rows($this->tenant, 'CREATE TABLE grupper (art text, box1 text)');
        CharacterizationEnv::rows($this->tenant, "INSERT INTO grupper VALUES ('VE', '5.1.0')");
        CharacterizationEnv::rows($this->tenant, 'CREATE TABLE openpost (konto_id integer, udlign_id integer, udlignet text, udlign_date date, transdate date)');
        CharacterizationEnv::rows($this->tenant, "INSERT INTO openpost VALUES (1, 1, '1', NULL, '2026-10-01'), (1, 1, '1', NULL, '2026-10-02')");
        CharacterizationEnv::rows($this->tenant, 'CREATE TABLE ordrer (id integer, art text)');
        CharacterizationEnv::rows($this->tenant, "INSERT INTO ordrer VALUES (1, 'DO')");
        CharacterizationEnv::rows($this->tenant, 'CREATE TABLE ordrelinjer (id integer, ordre_id integer, vare_id integer, leveret numeric)');
        CharacterizationEnv::rows($this->tenant, 'INSERT INTO ordrelinjer VALUES (20001, 1, 1, NULL)');
        CharacterizationEnv::rows($this->tenant, 'CREATE TABLE batch_salg (linje_id integer, ordre_id integer, vare_id integer, antal numeric)');
        CharacterizationEnv::rows($this->tenant, 'INSERT INTO batch_salg VALUES (20001, 1, 1, 2)');

        $GLOBALS['release511Tenant'] = $this->tenant;
        $GLOBALS['release511Registry'] = $this->registry;
        $GLOBALS['release511Writes'] = [];
        $GLOBALS['release511Failure'] = null;
        $GLOBALS['db'] = $this->database;
        $GLOBALS['sqdb'] = 'release_master';
        $GLOBALS['sqhost'] = '';
        $GLOBALS['squser'] = '';
        $GLOBALS['sqpass'] = '';
        $GLOBALS['db_type'] = 'postgresql';
        require_once __DIR__ . '/support/release511_database.php';
        require_once __DIR__ . '/../../../includes/version.php';
        require_once __DIR__ . '/../../../includes/tjek4opdat.php';
        self::assertSame('5.1.1', $version);
    }

    protected function tearDown(): void
    {
        if ($this->tenant) {
            pg_close($this->tenant);
        }
        if ($this->registry) {
            pg_close($this->registry);
        }
        if ($this->admin && isset($this->database)) {
            $this->ownedDatabase->release();
            pg_close($this->admin);
        }
    }

    public function testLoginUpgradesBothDatabasesAndSecondRunWritesNothing(): void
    {
        tjek4opdat('5.1.0', '5.1.1');
        $this->assertVersions('5.1.1');
        self::assertSame('2026-10-02', CharacterizationEnv::one($this->tenant, 'SELECT MIN(udlign_date) AS date FROM openpost')['date']);
        foreach ([$this->tenant, $this->registry] as $connection) {
            self::assertNotNull(CharacterizationEnv::one($connection, "SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'tekster' AND column_name = 'tekst_key'"));
        }
        self::assertSame('1', CharacterizationEnv::one($this->registry, "SELECT var_value FROM settings WHERE var_name = 'signup_schema'")['var_value']);
        self::assertNotNull(CharacterizationEnv::one($this->registry, "SELECT trial_start, trial_end, pakke, lukkes_kommentar FROM regnskab"));
        $this->continueBackfill();
        self::assertSame('2', CharacterizationEnv::one($this->tenant, 'SELECT leveret FROM ordrelinjer WHERE id = 20001')['leveret']);
        $writes = $GLOBALS['release511Writes'];
        tjek4opdat('5.1.1', '5.1.1');
        opdat_5_1();
        $this->continueBackfill();
        self::assertSame($writes, $GLOBALS['release511Writes']);
        $this->assertVersions('5.1.1');
    }

    public function testCompletedVersionStillResumesAnUnfinishedBackfill(): void
    {
        CharacterizationEnv::rows($this->tenant, "UPDATE grupper SET box1 = '5.1.1'");
        CharacterizationEnv::rows($this->registry, "UPDATE regnskab SET version = '5.1.1'");
        CharacterizationEnv::rows($this->tenant, "INSERT INTO settings (var_name, var_grp, var_value) VALUES ('ordrelinjer_leveret_backfill_id', 'system', '20000')");
        tjek4opdat('5.1.1', '5.1.1');
        $this->continueBackfill();
        self::assertSame('2', CharacterizationEnv::one($this->tenant, 'SELECT leveret FROM ordrelinjer')['leveret']);
        self::assertSame('yes', CharacterizationEnv::one($this->tenant, "SELECT var_value FROM settings WHERE var_name = 'ordrelinjer_leveret_backfilled'")['var_value']);
        self::assertNull(CharacterizationEnv::one($this->tenant, "SELECT var_value FROM settings WHERE var_name = 'ordrelinjer_leveret_backfill_id'"));
    }

    public function testMigrationFailureLeavesBothVersionsUnchangedAndCanRetry(): void
    {
        $GLOBALS['release511Failure'] = 'ALTER TABLE tekster';
        try {
            tjek4opdat('5.1.0', '5.1.1');
            self::fail('The simulated DDL failure must reach the caller.');
        } catch (RuntimeException $error) {
            self::assertSame('Simulated migration failure', $error->getMessage());
        }
        $this->assertVersions('5.1.0');
        $GLOBALS['release511Failure'] = null;
        tjek4opdat('5.1.0', '5.1.1');
        $this->assertVersions('5.1.1');
        self::assertSame('1', CharacterizationEnv::one($this->tenant, "SELECT COUNT(*) AS count FROM settings WHERE var_name = 'openpost_udlign_date_backfilled'")['count']);
    }

    public function testBusyMysqlSignupLockLeavesVersionsUnchanged(): void
    {
        $GLOBALS['db_type'] = 'mysqli';
        $GLOBALS['release511MysqlLockBusy'] = true;
        try {
            tjek4opdat('5.1.0', '5.1.1');
            self::fail('A busy signup lock must leave the upgrade pending.');
        } catch (RuntimeException $error) {
            self::assertSame('Could not acquire the signup schema migration lock.', $error->getMessage());
        }
        $this->assertVersions('5.1.0');
        self::assertSame([], $GLOBALS['release511Writes']);
    }

    public function testFreshInstallIncludesTheReleasedSchema(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../index/install.php');
        $start = strpos($source, '$qtxt = "CREATE TABLE regnskab');
        $end = strpos($source, '$qtxt = "INSERT INTO regnskab', $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        CharacterizationEnv::rows($this->registry, 'DROP TABLE regnskab');
        $GLOBALS['release511Tenant'] = $this->registry;
        eval(substr($source, $start, $end - $start));
        $start = strpos($source, '// 5.1.1 signup schema:');
        $end = strpos($source, '// End of 5.1.1 signup schema.', $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $db_type = 'postgresql';
        eval(substr($source, $start, $end - $start));
        foreach (['trial_start', 'trial_end', 'pakke', 'lukkes_kommentar'] as $column) {
            self::assertNotNull(CharacterizationEnv::one($this->registry, "SELECT column_name FROM information_schema.columns WHERE table_schema = 'registry' AND table_name = 'regnskab' AND column_name = '$column'"));
        }
        self::assertNotNull(CharacterizationEnv::one($this->registry, "SELECT column_name FROM information_schema.columns WHERE table_schema = 'registry' AND table_name = 'signup_pending' AND column_name = 'navn_fra_cvr'"));
        self::assertSame('1', CharacterizationEnv::one($this->registry, "SELECT var_value FROM settings WHERE var_name = 'signup_schema'")['var_value']);

        $GLOBALS['release511Tenant'] = $this->tenant;
        CharacterizationEnv::rows($this->tenant, 'DROP TABLE tekster');
        $source = file_get_contents(__DIR__ . '/../../../includes/opretfunc.php');
        $start = strpos($source, 'db_modify("CREATE TABLE tekster');
        $index = strpos($source, 'db_modify("CREATE INDEX idx_tekster_sprog_key', $start);
        $end = strpos($source, "\n", $index);
        self::assertNotFalse($start);
        self::assertNotFalse($index);
        self::assertNotFalse($end);
        $id_column = 'id serial NOT NULL';
        eval(substr($source, $start, $end - $start));
        self::assertNotNull(CharacterizationEnv::one($this->tenant, "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'tekster' AND column_name = 'tekst_key'"));
        self::assertNotNull(CharacterizationEnv::one($this->tenant, "SELECT indexname FROM pg_indexes WHERE schemaname = 'public' AND indexname = 'idx_tekster_sprog_key'"));
    }

    private function continueBackfill(): void
    {
        $db_type = $GLOBALS['db_type'];
        $db = $GLOBALS['db'];
        include __DIR__ . '/../../../includes/betweenUpdates.php';
    }

    private function assertVersions(string $expected): void
    {
        self::assertSame($expected, CharacterizationEnv::one($this->tenant, "SELECT box1 FROM grupper WHERE art = 'VE'")['box1']);
        self::assertSame($expected, CharacterizationEnv::one($this->registry, "SELECT version FROM regnskab WHERE db = '{$this->database}'")['version']);
    }
}
