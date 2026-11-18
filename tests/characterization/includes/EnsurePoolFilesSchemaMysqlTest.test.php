<?php
// 20261118 CL/MMR SD-815 Created (Codex round 12). includes/opdat_func/ensurePoolFilesSchema.php on real MySQL/MariaDB with PHP 8's default mysqli reporting (exceptions).
//                 A pending read that times out behind another session's table lock ends the run without an uncaught exception, flag or held lock; the next run fills the row.
//                 Needs the mysqli extension and SALDI_CHAR_MYSQL_SOCKET (a disposable server, user root without password unless SALDI_CHAR_MYSQL_USER/PASS); skipped otherwise.
// 20261118 CL/MMR SD-815 Codex round 14: when the unique-index repair cannot get the table (another session's open transaction has read pool_files), no duplicate row is deleted; the retry keeps the newest row.
// 20261118 CL/MMR SD-815 Codex round 15: inside a caller's transaction nothing runs (the caller can still roll back its own work), and an existing non-unique index named pool_files_filename_uidx does not stop the repair.
// 20261118 CL/MMR SD-815 Codex round 16: an old ROW_FORMAT=COMPACT utf8mb4 pool_files that cannot hold the index keeps both rows and logs why; after ROW_FORMAT=DYNAMIC the retry completes.
// 20261118 CL/MMR SD-815 Codex round 17: a permanent table called pool_files_uidx_probe, with a row, survives both a deferred and a successful repair (the probe is a temporary table).
// 20261118 CL/MMR SD-815 Codex round 18: a database user with only SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX and LOCK TABLES (no CREATE TEMPORARY TABLES) gets the repair.
//                 A user without CREATE keeps both rows and the log names the missing privilege, not the row format.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EnsurePoolFilesSchemaMysqlTest extends TestCase
{
    private ?\mysqli $connection = null;
    private ?\mysqli $other = null;
    private string $database = '';
    private string $log = '';

    private function connect(): \mysqli
    {
        $socket = (string) getenv('SALDI_CHAR_MYSQL_SOCKET');
        $user = getenv('SALDI_CHAR_MYSQL_USER') !== false ? (string) getenv('SALDI_CHAR_MYSQL_USER') : 'root';
        $pass = getenv('SALDI_CHAR_MYSQL_PASS') !== false ? (string) getenv('SALDI_CHAR_MYSQL_PASS') : '';
        return mysqli_connect('localhost', $user, $pass, null, 0, $socket);
    }

    protected function setUp(): void
    {
        if (!extension_loaded('mysqli') || (string) getenv('SALDI_CHAR_MYSQL_SOCKET') === '') {
            self::markTestSkipped('needs the mysqli extension and SALDI_CHAR_MYSQL_SOCKET');
        }
        // PHP 8.1+'s default: errors are thrown as mysqli_sql_exception, as on the servers.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->log = (string) tempnam(sys_get_temp_dir(), 'pool_schema_log_');
        ini_set('error_log', $this->log);
        $this->connection = $this->connect();
        $this->database = 'pool_schema_' . bin2hex(random_bytes(6));
        mysqli_query($this->connection, "CREATE DATABASE {$this->database} CHARACTER SET utf8mb4");
        mysqli_select_db($this->connection, $this->database);
        $GLOBALS['ensurePoolFilesMysql'] = $this->connection;
        if (!function_exists('db_select')) {
            // As includes/db_query.php's mysqli branch: mysqli_query() straight through, so a failure throws.
            function db_select($sql, $location = '') {
                return mysqli_query($GLOBALS['ensurePoolFilesMysql'], $sql);
            }
            function db_fetch_array($result) {
                return $result === false || $result === true ? false : (mysqli_fetch_assoc($result) ?? false);
            }
            function db_modify($sql, $location = '') {
                mysqli_query($GLOBALS['ensurePoolFilesMysql'], $sql);
                return '0' . chr(9) . 'query accepted';
            }
            function db_escape_string($value) {
                return mysqli_real_escape_string($GLOBALS['ensurePoolFilesMysql'], (string) $value);
            }
        }
        require_once __DIR__ . '/../../../includes/opdat_func/ensurePoolFilesSchema.php';
        $this->sql('CREATE TABLE settings (id INT AUTO_INCREMENT NOT NULL, var_name text, var_grp text, var_value text, var_description text, user_id integer, group_id integer, pos_id integer, PRIMARY KEY (id))');
        // includes/opretfunc.php's pool_files for a new regnskab before SD-815, MySQL flavour.
        $this->sql('CREATE TABLE pool_files (id INT AUTO_INCREMENT NOT NULL, filename varchar(255), subject text, account varchar(50), amount varchar(50), file_date varchar(50), updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP, invoice_number varchar(100), description text, currency varchar(10), content_sha256 char(64), PRIMARY KEY (id))');
    }

    /** @var list<string> */
    private array $users = [];

    /** A database user of this test with exactly $grants on the test database; the helper then runs as that user. */
    private function runAs(string $grants): \mysqli
    {
        $user = 'pool_mig_' . bin2hex(random_bytes(4));
        $pass = bin2hex(random_bytes(12));
        $this->sql("CREATE USER '$user'@'localhost' IDENTIFIED BY '$pass'");
        $this->users[] = $user;
        $this->sql("GRANT $grants ON {$this->database}.* TO '$user'@'localhost'");
        $limited = mysqli_connect('localhost', $user, $pass, $this->database, 0, (string) getenv('SALDI_CHAR_MYSQL_SOCKET'));
        $GLOBALS['ensurePoolFilesMysql'] = $limited;
        return $limited;
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            foreach ($this->users as $user) {
                mysqli_query($this->connection, "DROP USER IF EXISTS '$user'@'localhost'");
            }
        }
        if ($this->other !== null) {
            try {
                mysqli_query($this->other, 'UNLOCK TABLES');
            } catch (\mysqli_sql_exception $e) {
            }
            mysqli_close($this->other);
        }
        if ($this->connection !== null) {
            mysqli_query($this->connection, "DROP DATABASE IF EXISTS {$this->database}");
            mysqli_close($this->connection);
        }
        unset($GLOBALS['ensurePoolFilesMysql']);
        if ($this->log !== '' && is_file($this->log)) {
            unlink($this->log);
        }
    }

    private function sql(string $sql): \mysqli_result|bool
    {
        return mysqli_query($this->connection, $sql);
    }

    /** A permanent table with the name the round-16 probe used, holding a sentinel row. */
    private function permanentTableWithTheProbeName(): void
    {
        $this->sql('CREATE TABLE pool_files_uidx_probe (id INT PRIMARY KEY, note varchar(20))');
        $this->sql("INSERT INTO pool_files_uidx_probe VALUES (1, 'sentinel')");
    }

    private function assertPermanentTableKept(): void
    {
        self::assertSame('sentinel', $this->one('SELECT note FROM pool_files_uidx_probe WHERE id = 1'), 'a permanent table is never dropped');
    }

    private function one(string $sql): ?string
    {
        $row = mysqli_fetch_row($this->sql($sql));
        return $row === null ? null : ($row[0] === null ? null : (string) $row[0]);
    }

    public function testTheSchemaIsFixedAndATimedOutReadEndsTheRunQuietly(): void
    {
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '2,00'), ('old.pdf', '99,00')");

        $done = ensurePoolFilesSchema('mysqli');

        self::assertContains('norm_amount', $done);
        self::assertContains('unique_filename', $done);
        self::assertContains('norm_amount_backfill', $done);
        self::assertSame('1', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'duplicates collapsed');
        self::assertSame('99.000', $this->one("SELECT norm_amount FROM pool_files WHERE filename = 'old.pdf'"));
        $this->sql("INSERT INTO pool_files (filename, amount, norm_amount) VALUES ('x.xml', '1', 1) ON DUPLICATE KEY UPDATE id = id");
        $this->sql("INSERT INTO pool_files (filename, amount, norm_amount) VALUES ('x.xml', '1', 1) ON DUPLICATE KEY UPDATE id = id");
        self::assertSame('1', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'x.xml'"));
        self::assertSame([], ensurePoolFilesSchema('mysqli'));

        // Back to «backfill not done», and another session holds pool_files.
        $this->sql("DELETE FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'");
        $this->sql("UPDATE pool_files SET norm_amount = NULL WHERE filename = 'old.pdf'");
        $this->other = $this->connect();
        mysqli_select_db($this->other, $this->database);
        mysqli_query($this->other, 'LOCK TABLES pool_files WRITE');
        $this->sql('SET SESSION lock_wait_timeout = 1');

        $result = ensurePoolFilesSchema('mysqli'); // must not throw

        self::assertSame([], $result);
        self::assertStringContainsString('Lock wait timeout', (string) file_get_contents($this->log), 'the failed read is logged');
        self::assertSame('0', $this->one("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"), 'no flag after a failed read');
        self::assertNull($this->one("SELECT IS_USED_LOCK(CONCAT('saldi:pool_files_schema:', MD5(DATABASE())))"), 'the named lock was released');

        mysqli_query($this->other, 'UNLOCK TABLES');
        $this->sql('SET SESSION lock_wait_timeout = DEFAULT');
        self::assertSame(['norm_amount_backfill'], ensurePoolFilesSchema('mysqli'));
        self::assertSame('99.000', $this->one("SELECT norm_amount FROM pool_files WHERE filename = 'old.pdf'"));
        self::assertSame('1', $this->one("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"));
        self::assertNull($this->one("SELECT IS_USED_LOCK(CONCAT('saldi:pool_files_schema:', MD5(DATABASE())))"));
        self::assertSame([], ensurePoolFilesSchema('mysqli'));
    }

    public function testABlockedIndexRepairDeletesNothing(): void
    {
        // Every later column present, only the unique index and the backfill missing; two rows with one filename.
        foreach (['norm_amount numeric(15,3)', 'manually_edited boolean NOT NULL DEFAULT false', 'source_sha256 char(64)', 'vendor_name text', 'vendor_cvr varchar(20)',
            'vendor_iban varchar(40)', 'vendor_konto_id integer', 'vendor_score numeric(4,3)', 'vendor_match varchar(10)'] as $column) {
            $this->sql("ALTER TABLE pool_files ADD COLUMN $column");
        }
        $this->sql('CREATE INDEX idx_pool_files_norm_amount ON pool_files (norm_amount)');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '2,00')");
        // Another session's open transaction has read pool_files: it holds a metadata lock until it ends.
        $this->other = $this->connect();
        mysqli_select_db($this->other, $this->database);
        mysqli_query($this->other, 'START TRANSACTION');
        mysqli_query($this->other, 'SELECT count(*) FROM pool_files');
        $this->sql('SET SESSION lock_wait_timeout = 1');

        $done = ensurePoolFilesSchema('mysqli'); // must not throw

        self::assertNotContains('unique_filename', $done);
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'nothing deleted');
        self::assertStringContainsString('Lock wait timeout', (string) file_get_contents($this->log));
        self::assertSame('0', $this->one("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"));
        self::assertNull($this->one("SELECT IS_USED_LOCK(CONCAT('saldi:pool_files_schema:', MD5(DATABASE())))"));
        $this->sql('SELECT 1 FROM settings'); // no LOCK TABLES left on this session (it would refuse an unlocked table)

        mysqli_query($this->other, 'COMMIT');
        $this->sql('SET SESSION lock_wait_timeout = DEFAULT');
        $done = ensurePoolFilesSchema('mysqli');
        self::assertContains('unique_filename', $done);
        self::assertSame('2,00', $this->one("SELECT amount FROM pool_files WHERE filename = 'a.pdf'"), 'the newest row is kept');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '3,00') ON DUPLICATE KEY UPDATE id = id");
        self::assertSame('1', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"));
        self::assertSame([], ensurePoolFilesSchema('mysqli'));
    }

    public function testNothingRunsInsideACallersTransaction(): void
    {
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '2,00')");
        $this->sql('START TRANSACTION');
        $this->sql("INSERT INTO settings (var_name, var_grp, var_value) VALUES ('caller', 'test', 'uncommitted')");

        self::assertSame([], ensurePoolFilesSchema('mysqli'));

        self::assertSame('1', $this->one('SELECT @@in_transaction'), 'the caller is still in its transaction');
        self::assertStringContainsString('inside a transaction', (string) file_get_contents($this->log));
        $this->sql('ROLLBACK');
        $this->other = $this->connect();
        mysqli_select_db($this->other, $this->database);
        self::assertSame('0', (string) mysqli_fetch_row(mysqli_query($this->other, "SELECT count(*) FROM settings WHERE var_name = 'caller'"))[0], 'the caller could roll its own work back');
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'nothing touched');

        $done = ensurePoolFilesSchema('mysqli'); // the next login, outside a transaction
        self::assertContains('unique_filename', $done);
        self::assertSame('2,00', $this->one("SELECT amount FROM pool_files WHERE filename = 'a.pdf'"));
    }

    public function testAnExistingNonUniqueIndexWithTheUsualNameIsNoObstacle(): void
    {
        $this->permanentTableWithTheProbeName();
        $this->sql('CREATE INDEX pool_files_filename_uidx ON pool_files (filename)');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '2,00')");

        $done = ensurePoolFilesSchema('mysqli');

        self::assertContains('unique_filename', $done);
        self::assertSame('2,00', $this->one("SELECT amount FROM pool_files WHERE filename = 'a.pdf'"), 'the newest row is kept');
        self::assertSame('0', $this->one("SELECT non_unique FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files' AND index_name = 'pool_files_filename_full_uidx'"), 'the new unique index');
        $this->assertPermanentTableKept();
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '3,00') ON DUPLICATE KEY UPDATE id = id");
        self::assertSame('1', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"));
        self::assertSame([], ensurePoolFilesSchema('mysqli'));
    }

    public function testATableThatCannotHoldTheIndexKeepsItsRows(): void
    {
        $this->sql('DROP TABLE pool_files');
        $this->sql('CREATE TABLE pool_files (id INT AUTO_INCREMENT NOT NULL, filename varchar(255), subject text, account varchar(50), amount varchar(50),
            norm_amount numeric(15,3), file_date varchar(50), updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP, invoice_number varchar(100), description text,
            manually_edited boolean NOT NULL DEFAULT false, currency varchar(10), content_sha256 char(64), source_sha256 char(64), vendor_name text,
            vendor_cvr varchar(20), vendor_iban varchar(40), vendor_konto_id integer, vendor_score numeric(4,3), vendor_match varchar(10),
            PRIMARY KEY (id), INDEX idx_pool_files_norm_amount (norm_amount)) ENGINE=InnoDB ROW_FORMAT=COMPACT DEFAULT CHARSET=utf8mb4');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '99,00')");
        $this->permanentTableWithTheProbeName();

        $done = ensurePoolFilesSchema('mysqli'); // must not throw

        self::assertNotContains('unique_filename', $done);
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'nothing deleted');
        self::assertStringContainsString('cannot hold a unique index on the whole filename', (string) file_get_contents($this->log));
        $this->assertPermanentTableKept();
        self::assertSame('0', $this->one("SELECT count(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'pool\\_files\\_uidx\\_probe\\_%'"), 'no trial copy left as a table');
        self::assertStringContainsString('ROW_FORMAT=DYNAMIC', (string) file_get_contents($this->log), 'the log names the row format');
        self::assertSame('0', $this->one("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"));
        self::assertNull($this->one("SELECT IS_USED_LOCK(CONCAT('saldi:pool_files_schema:', MD5(DATABASE())))"));
        self::assertNotContains('unique_filename', ensurePoolFilesSchema('mysqli'), 'a retry on the same table defers again');
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"));

        $this->sql('ALTER TABLE pool_files ROW_FORMAT=DYNAMIC'); // the prerequisite the log names
        $done = ensurePoolFilesSchema('mysqli');
        self::assertContains('unique_filename', $done);
        $this->assertPermanentTableKept();
        self::assertContains('norm_amount_backfill', $done);
        self::assertSame('99.000', $this->one("SELECT norm_amount FROM pool_files WHERE filename = 'a.pdf'"), 'the newest row is kept and filled');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '3,00') ON DUPLICATE KEY UPDATE id = id");
        self::assertSame('1', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"));
        self::assertSame([], ensurePoolFilesSchema('mysqli'));
    }

    public function testTheGrantsThatWereEnoughBeforeAreStillEnough(): void
    {
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '99,00')");
        $limited = $this->runAs('SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, LOCK TABLES');

        $done = ensurePoolFilesSchema('mysqli');

        self::assertContains('unique_filename', $done);
        self::assertContains('norm_amount_backfill', $done);
        self::assertSame('99.000', $this->one("SELECT norm_amount FROM pool_files WHERE filename = 'a.pdf'"));
        self::assertSame('1', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"));
        self::assertSame('0', $this->one("SELECT count(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'pool\\_files\\_uidx\\_probe\\_%'"), 'the trial copy is dropped');
        mysqli_close($limited);
    }

    public function testAMissingPrivilegeIsLoggedAsSuchAndDeletesNothing(): void
    {
        // Every later column already there, so the first statement that needs CREATE is the trial copy.
        foreach (['norm_amount numeric(15,3)', 'manually_edited boolean NOT NULL DEFAULT false', 'source_sha256 char(64)', 'vendor_name text', 'vendor_cvr varchar(20)',
            'vendor_iban varchar(40)', 'vendor_konto_id integer', 'vendor_score numeric(4,3)', 'vendor_match varchar(10)'] as $column) {
            $this->sql("ALTER TABLE pool_files ADD COLUMN $column");
        }
        $this->sql('CREATE INDEX idx_pool_files_norm_amount ON pool_files (norm_amount)');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '99,00')");
        $limited = $this->runAs('SELECT, INSERT, UPDATE, DELETE, DROP, ALTER, INDEX, LOCK TABLES'); // no CREATE

        $done = ensurePoolFilesSchema('mysqli'); // must not throw

        self::assertNotContains('unique_filename', $done);
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'nothing deleted');
        $log = (string) file_get_contents($this->log);
        self::assertStringContainsString('lacks a privilege', $log);
        self::assertStringNotContainsString('ROW_FORMAT', $log, 'not mistaken for a row-format problem');
        mysqli_close($limited);
    }
}
