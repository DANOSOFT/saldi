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
// 20261118 CL/MMR SD-815 Codex round 19: with mysqli reporting off, a table another connection made under the probe's name before the probe's CREATE survives with its row.
//                 $GLOBALS['connection'] is the test's mysqli connection, as in production.
// 20261118 CL/MMR SD-815 Codex round 20: with mysqli reporting off, a repair whose LOCK TABLES times out deletes nothing and writes no flag; after the other session ends the retry completes.
// 20261118 CL/MMR SD-815 Codex round 22: a unique prefix index on filename(7) is replaced by the full one, so two different names sharing the prefix both register; a full index alone is left as it is.
// 20261118 CL/MMR SD-815 Codex round 32: another connection creating an index named pool_files_filename_uidx just before the table lock: kept, the repair uses a free name, in both mysqli reporting modes.
// 20261118 CL/MMR SD-815 Codex round 33: another connection switching pool_files to ROW_FORMAT=COMPACT after the trial and before the lock: both rows kept, nothing reported; after DYNAMIC again the retry completes (both mysqli modes).
// 20261118 CL/MMR SD-815 Codex round 34: with 63 keys, another connection taking the 64th key slot after the trial and before the lock: both rows and its key kept, nothing reported; after a slot is free the retry completes (both mysqli modes).
// 20261118 CL/MMR SD-815 Codex round 35: another connection partitioning pool_files by HASH(id) after the trial and before the lock: both rows kept, nothing reported; the partitioned table defers at the trial; after REMOVE PARTITIONING the retry completes (both mysqli modes).
// 20261118 CL/MMR SD-815 Codex round 36: a table with an outgoing foreign key, and a session with sql_quote_show_create=0, are repaired (FK and setting kept) in both mysqli modes.
// 20261118 CL/MMR SD-815 Codex round 37: an ON DELETE CASCADE reference from another table and duplicate filenames: nothing deleted anywhere, no flag; without duplicates the repair runs (both mysqli modes).
// 20261118 CL/MMR SD-815 Codex round 38: a cascading reference from another database (as root, and as a user with rights on this database only) keeps every row; duplicates without the PROCESS privilege wait; the round-18 grants still repair a table without duplicates.
// 20261118 CL/MMR SD-815 Codex round 39: a parent database named with a '-' (encoded @002d by InnoDB) and a cascading child elsewhere keeps every row; two NULL filenames are no duplicates and are repaired by a parent-only user.
// 20261118 CL/MMR SD-815 Codex round 40: a non-ASCII parent database name with latin1 results (the name comes back as '?') and a cascading child elsewhere keeps every row; the session setting is left as it was.
// 20261118 CL/MMR SD-815 Codex round 41: with sql_mode ANSI_QUOTES (SHOW CREATE TABLE prints "pool_files") the repair completes and the mode is left as it was, in both mysqli modes.
// 20261118 CL/MMR SD-815 Codex round 42: a DELETE trigger on pool_files and duplicates keeps every row (both mysqli modes); a user without the TRIGGER privilege waits with duplicates.
// 20261118 CL/MMR SD-815 Codex round 46: a TEMPORARY pool_files or settings of the session stops the run before any change (both mysqli modes); without it the next run completes.
// 20261118 CL/MMR SD-815 Codex round 47: an updatable settings view takes the flag (a base-table control next to it); a temporary settings holding the flag stops every run on that connection with a log line, and the run after DROP completes (both mysqli modes).
// 20261118 CL/MMR SD-815 Codex round 48: an account with SELECT, INSERT, UPDATE but no SHOW VIEW fills pool_files and writes the flag through the settings view (control with SHOW VIEW); for such an account a temporary settings holding the flag still stops the run and stays untouched (both mysqli modes).
// 20261118 CL/MMR SD-815 tearDown() calls no mysqli function where the extension is missing (the CI container), and every test is on the tools/ci/check-skips.php allowlist.

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
        $GLOBALS['connection'] = $this->connection; // ensurePoolFilesMysqlExec() runs on it, as on db_query_connection()'s
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
        if ((str_starts_with($this->name(), 'testARowFormatChangeBeforeTheLock') || str_starts_with($this->name(), 'testTheLastKeySlotTakenBeforeTheLock')
            || str_starts_with($this->name(), 'testPartitioningBeforeTheLock')) && !function_exists('ensurePoolFilesMysqlExec')) {
            // The production statement runner, unchanged, with one addition for this test: a hook right before LOCK TABLES.
            function ensurePoolFilesMysqlExec(string $sql): ?bool {
                if (str_starts_with($sql, 'LOCK TABLES') && isset($GLOBALS['ensurePoolFilesBeforeLock'])) {
                    $hook = $GLOBALS['ensurePoolFilesBeforeLock'];
                    unset($GLOBALS['ensurePoolFilesBeforeLock']);
                    $hook();
                }
                $GLOBALS['ensurePoolFilesMysqlLastError'] = null;
                $connection = $GLOBALS['connection'] ?? null;
                if (!($connection instanceof \mysqli)) {
                    return null;
                }
                try {
                    $ok = mysqli_query($connection, $sql) !== false;
                    $error = $ok ? '' : mysqli_error($connection);
                    $code = $ok ? 0 : mysqli_errno($connection);
                } catch (\mysqli_sql_exception $e) {
                    $ok = false;
                    $error = $e->getMessage();
                    $code = (int) $e->getCode();
                }
                if (!$ok) {
                    $GLOBALS['ensurePoolFilesMysqlLastError'] = array('code' => $code, 'message' => $error);
                    error_log('ensurePoolFilesSchema: ' . $sql . ' failed: ' . $error);
                }
                return $ok;
            }
        }
        if (str_starts_with($this->name(), 'testAnIndexTakingTheNameBeforeTheLock') && !function_exists('ensurePoolFilesMysqlProbeName')) {
            // Runs right before the table lock (the probe comes first): the other connection takes the usual index name there.
            function ensurePoolFilesMysqlProbeName(): string {
                $rival = $GLOBALS['ensurePoolFilesRivalDdl'] ?? null;
                unset($GLOBALS['ensurePoolFilesRivalDdl']);
                if ($rival !== null) {
                    $rival();
                }
                return 'pool_files_uidx_probe_' . bin2hex(random_bytes(6));
            }
        }
        if ($this->name() === 'testATableAnotherConnectionCreatedUnderTheProbeNameSurvivesWithReportingOff' && !function_exists('ensurePoolFilesMysqlProbeName')) {
            // The probe's name, fixed so the test can make the rival table first (each test runs in its own process).
            function ensurePoolFilesMysqlProbeName(): string {
                return 'pool_files_uidx_probe_rival';
            }
        }
        require_once __DIR__ . '/../../../includes/opdat_func/ensurePoolFilesSchema.php';
        $this->sql('CREATE TABLE settings (id INT AUTO_INCREMENT NOT NULL, var_name text, var_grp text, var_value text, var_description text, user_id integer, group_id integer, pos_id integer, PRIMARY KEY (id))');
        // includes/opretfunc.php's pool_files for a new regnskab before SD-815, MySQL flavour.
        $this->sql('CREATE TABLE pool_files (id INT AUTO_INCREMENT NOT NULL, filename varchar(255), subject text, account varchar(50), amount varchar(50), file_date varchar(50), updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP, invoice_number varchar(100), description text, currency varchar(10), content_sha256 char(64), PRIMARY KEY (id))');
    }

    /** @var list<string> */
    private array $users = [];

    /** @var list<string> */
    private array $otherDatabases = [];

    /** Another database with a table whose rows refer to pool_files here, ON DELETE CASCADE. */
    private function childInAnotherDatabase(): string
    {
        $other = $this->database . '_child';
        $this->sql("CREATE DATABASE $other");
        $this->otherDatabases[] = $other;
        $this->sql("CREATE TABLE $other.pool_linked_document (id INT NOT NULL PRIMARY KEY, pool_id INT NOT NULL,
            CONSTRAINT linked_pool_fk FOREIGN KEY (pool_id) REFERENCES {$this->database}.pool_files (id) ON DELETE CASCADE) ENGINE=InnoDB");
        return $other;
    }

    /** A database user of this test with exactly $grants on the test database; the helper then runs as that user. */
    private function runAs(string $grants, string $globalGrants = ''): \mysqli
    {
        $user = 'pool_mig_' . bin2hex(random_bytes(4));
        $pass = bin2hex(random_bytes(12));
        $this->sql("CREATE USER '$user'@'localhost' IDENTIFIED BY '$pass'");
        $this->users[] = $user;
        $this->sql("GRANT $grants ON {$this->database}.* TO '$user'@'localhost'");
        if ($globalGrants !== '') {
            $this->sql("GRANT $globalGrants ON *.* TO '$user'@'localhost'");
        }
        $limited = mysqli_connect('localhost', $user, $pass, $this->database, 0, (string) getenv('SALDI_CHAR_MYSQL_SOCKET'));
        $GLOBALS['ensurePoolFilesMysql'] = $limited;
        $GLOBALS['connection'] = $limited;
        return $limited;
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            foreach (array_reverse($this->otherDatabases) as $other) { // children before the parents they refer to
                mysqli_query($this->connection, "DROP DATABASE IF EXISTS $other");
            }
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
        unset($GLOBALS['ensurePoolFilesMysql'], $GLOBALS['connection']);
        if (function_exists('mysqli_report')) { // skipped tests run tearDown too, also where there is no mysqli
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        }
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
        // Without duplicates: with duplicates the collapse also needs PROCESS (round 38, see the test below).
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('b.pdf', '99,00')");
        $limited = $this->runAs('SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, LOCK TABLES, TRIGGER');

        $done = ensurePoolFilesSchema('mysqli');

        self::assertContains('unique_filename', $done);
        self::assertContains('norm_amount_backfill', $done);
        self::assertSame('99.000', $this->one("SELECT norm_amount FROM pool_files WHERE filename = 'b.pdf'"));
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

    public function testATableAnotherConnectionCreatedUnderTheProbeNameSurvivesWithReportingOff(): void
    {
        // setUp() fixed the probe's name for this test (ensurePoolFilesMysqlProbeName()); another connection has
        // just made a permanent table of that name, with a row - the state the probe's CREATE meets in the race.
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '99,00')");
        $this->other = $this->connect();
        mysqli_select_db($this->other, $this->database);
        mysqli_query($this->other, 'CREATE TABLE pool_files_uidx_probe_rival (id INT PRIMARY KEY, note varchar(20))');
        mysqli_query($this->other, "INSERT INTO pool_files_uidx_probe_rival VALUES (1, 'sentinel')");
        mysqli_report(MYSQLI_REPORT_OFF); // the mode where a failed CREATE only returns false

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertNotContains('unique_filename', $done, 'deferred');
        self::assertSame('sentinel', $this->one('SELECT note FROM pool_files_uidx_probe_rival WHERE id = 1'), 'the other connection\'s table and row survive');
        self::assertSame('0', $this->one("SELECT count(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files_uidx_probe_rival' AND index_name = 'pool_files_uidx_probe_rival_filename'"), 'no index was added to it');
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'nothing deleted');
        self::assertStringContainsString('already exists', (string) file_get_contents($this->log));
    }

    public function testABlockedTableLockDeletesNothingWithReportingOff(): void
    {
        foreach (['norm_amount numeric(15,3)', 'manually_edited boolean NOT NULL DEFAULT false', 'source_sha256 char(64)', 'vendor_name text', 'vendor_cvr varchar(20)',
            'vendor_iban varchar(40)', 'vendor_konto_id integer', 'vendor_score numeric(4,3)', 'vendor_match varchar(10)'] as $column) {
            $this->sql("ALTER TABLE pool_files ADD COLUMN $column");
        }
        $this->sql('CREATE INDEX idx_pool_files_norm_amount ON pool_files (norm_amount)');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '99,00')");
        $this->other = $this->connect();
        mysqli_select_db($this->other, $this->database);
        mysqli_query($this->other, 'START TRANSACTION');
        mysqli_query($this->other, 'SELECT count(*) FROM pool_files'); // a metadata lock until the transaction ends
        $this->sql('SET SESSION lock_wait_timeout = 1');
        mysqli_report(MYSQLI_REPORT_OFF); // the mode where a failed LOCK TABLES only returns false

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertNotContains('unique_filename', $done);
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'nothing deleted without the table lock');
        self::assertSame('0', $this->one("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"));
        self::assertStringContainsString('could not be locked', (string) file_get_contents($this->log));
        $this->sql('SELECT 1 FROM settings'); // no LOCK TABLES left on this session

        mysqli_query($this->other, 'COMMIT');
        $this->sql('SET SESSION lock_wait_timeout = DEFAULT');
        mysqli_report(MYSQLI_REPORT_OFF);
        $done = ensurePoolFilesSchema('mysqli');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertContains('unique_filename', $done);
        self::assertSame('99,00', $this->one("SELECT amount FROM pool_files WHERE filename = 'a.pdf'"), 'the newest row is kept');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '3,00') ON DUPLICATE KEY UPDATE id = id");
        self::assertSame('1', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"));
    }

    /** Every later column, as a 5.0 table has them. */
    private function allLaterColumns(): void
    {
        foreach (['norm_amount numeric(15,3)', 'manually_edited boolean NOT NULL DEFAULT false', 'source_sha256 char(64)', 'vendor_name text', 'vendor_cvr varchar(20)',
            'vendor_iban varchar(40)', 'vendor_konto_id integer', 'vendor_score numeric(4,3)', 'vendor_match varchar(10)'] as $column) {
            $this->sql("ALTER TABLE pool_files ADD COLUMN $column");
        }
        $this->sql('CREATE INDEX idx_pool_files_norm_amount ON pool_files (norm_amount)');
    }

    /** The pool writer's MySQL insert (poolUploadRegisterFile(): ON DUPLICATE KEY UPDATE id = id). */
    private function poolInsert(string $filename): void
    {
        $this->sql("INSERT INTO pool_files (filename, subject, account, amount, file_date, invoice_number, description, currency, content_sha256, source_sha256, norm_amount)
            VALUES ('$filename', 'x', '', '1,00', '2026-11-18', '', '', 'DKK', '" . str_repeat('a', 64) . "', NULL, 1.000) ON DUPLICATE KEY UPDATE id = id");
    }

    public function testAPrefixUniqueIndexIsReplacedByTheFullOne(): void
    {
        $this->allLaterColumns();
        $this->sql('CREATE UNIQUE INDEX existing_filename ON pool_files (filename(7))');
        $this->poolInsert('invoice-a.pdf');
        $this->poolInsert('invoice-b.pdf');
        self::assertSame('1', $this->one('SELECT count(*) FROM pool_files'), 'the prefix index refuses the second name (the defect)');

        $done = ensurePoolFilesSchema('mysqli');

        self::assertContains('unique_filename', $done);
        self::assertSame('0', $this->one("SELECT count(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files' AND index_name = 'existing_filename'"), 'the prefix index is gone');
        self::assertSame('1', $this->one("SELECT count(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files' AND column_name = 'filename' AND non_unique = 0 AND sub_part IS NULL"), 'one full unique index');
        $this->poolInsert('invoice-b.pdf');
        $this->poolInsert('invoice-b.pdf');
        self::assertSame(['invoice-a.pdf', 'invoice-b.pdf'], array_column(mysqli_fetch_all($this->sql('SELECT filename FROM pool_files ORDER BY filename')), 0), 'both names register, each once');
        self::assertSame([], ensurePoolFilesSchema('mysqli'));
    }

    public function testAFullUniqueIndexAloneIsLeftAsItIs(): void
    {
        $this->allLaterColumns();
        $this->sql('CREATE UNIQUE INDEX existing_filename ON pool_files (filename)');
        $this->poolInsert('invoice-a.pdf');
        $this->poolInsert('invoice-b.pdf');

        $done = ensurePoolFilesSchema('mysqli');

        self::assertNotContains('unique_filename', $done);
        self::assertSame('existing_filename', $this->one("SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files' AND column_name = 'filename' AND non_unique = 0"));
        self::assertSame('2', $this->one('SELECT count(*) FROM pool_files'));
    }

    private function anIndexTakingTheNameBeforeTheLock(int $reportMode): void
    {
        $this->allLaterColumns();
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '99,00')");
        $this->other = $this->connect();
        mysqli_select_db($this->other, $this->database);
        $other = $this->other;
        $GLOBALS['ensurePoolFilesRivalDdl'] = static function () use ($other): void {
            mysqli_query($other, 'CREATE INDEX pool_files_filename_uidx ON pool_files (subject)');
        };
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertSame('1', $this->one("SELECT count(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files' AND index_name = 'pool_files_filename_uidx' AND column_name = 'subject'"), 'the other connection\'s index is kept');
        self::assertContains('unique_filename', $done, 'the repair used a free name');
        self::assertSame('0', $this->one("SELECT non_unique FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files' AND index_name = 'pool_files_filename_full_uidx'"));
        self::assertSame('99,00', $this->one("SELECT amount FROM pool_files WHERE filename = 'a.pdf'"), 'the newest row is kept');
        self::assertSame('99.000', $this->one("SELECT norm_amount FROM pool_files WHERE filename = 'a.pdf'"));
        $this->poolInsert('a.pdf');
        self::assertSame('1', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"));
    }

    public function testAnIndexTakingTheNameBeforeTheLockStrict(): void
    {
        $this->anIndexTakingTheNameBeforeTheLock(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testAnIndexTakingTheNameBeforeTheLockReportingOff(): void
    {
        $this->anIndexTakingTheNameBeforeTheLock(MYSQLI_REPORT_OFF);
    }

    private function aRowFormatChangeBeforeTheLock(int $reportMode): void
    {
        $this->sql('ALTER TABLE pool_files ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4');
        $this->allLaterColumns();
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '99,00')");
        $this->other = $this->connect();
        mysqli_select_db($this->other, $this->database);
        $other = $this->other;
        $GLOBALS['ensurePoolFilesBeforeLock'] = static function () use ($other): void {
            mysqli_query($other, 'ALTER TABLE pool_files ROW_FORMAT=COMPACT');
        };
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertNotContains('unique_filename', $done);
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'nothing deleted');
        self::assertSame('0', $this->one("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"));
        self::assertStringContainsString('changed between the trial and the table lock', (string) file_get_contents($this->log));
        $this->sql('SELECT 1 FROM settings'); // no LOCK TABLES left

        $this->sql('ALTER TABLE pool_files ROW_FORMAT=DYNAMIC');
        mysqli_report($reportMode);
        $done = ensurePoolFilesSchema('mysqli');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertContains('unique_filename', $done);
        self::assertSame('99,00', $this->one("SELECT amount FROM pool_files WHERE filename = 'a.pdf'"), 'the newest row is kept');
        self::assertSame('99.000', $this->one("SELECT norm_amount FROM pool_files WHERE filename = 'a.pdf'"));
    }

    public function testARowFormatChangeBeforeTheLockStrict(): void
    {
        $this->aRowFormatChangeBeforeTheLock(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testARowFormatChangeBeforeTheLockReportingOff(): void
    {
        $this->aRowFormatChangeBeforeTheLock(MYSQLI_REPORT_OFF);
    }

    private function theLastKeySlotTakenBeforeTheLock(int $reportMode): void
    {
        $this->allLaterColumns(); // PRIMARY and idx_pool_files_norm_amount: 2 keys
        for ($i = 1; $i <= 61; $i++) {
            $this->sql("CREATE INDEX extra_key_$i ON pool_files (account)"); // 63 keys; the trial index is the 64th
        }
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '99,00')");
        $this->other = $this->connect();
        mysqli_select_db($this->other, $this->database);
        $other = $this->other;
        $GLOBALS['ensurePoolFilesBeforeLock'] = static function () use ($other): void {
            mysqli_query($other, 'CREATE INDEX rival_key ON pool_files (subject(10))'); // takes the last of 64 slots
        };
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertNotContains('unique_filename', $done);
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'nothing deleted');
        self::assertSame('1', $this->one("SELECT count(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files' AND index_name = 'rival_key'"), 'the other connection\'s key is kept');
        self::assertSame('0', $this->one("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"));
        self::assertStringContainsString('changed between the trial and the table lock', (string) file_get_contents($this->log));

        $this->sql('DROP INDEX extra_key_61 ON pool_files'); // a slot is free again
        mysqli_report($reportMode);
        $done = ensurePoolFilesSchema('mysqli');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertContains('unique_filename', $done);
        self::assertSame('99,00', $this->one("SELECT amount FROM pool_files WHERE filename = 'a.pdf'"), 'the newest row is kept');
        self::assertSame('99.000', $this->one("SELECT norm_amount FROM pool_files WHERE filename = 'a.pdf'"));
    }

    public function testTheLastKeySlotTakenBeforeTheLockStrict(): void
    {
        $this->theLastKeySlotTakenBeforeTheLock(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testTheLastKeySlotTakenBeforeTheLockReportingOff(): void
    {
        $this->theLastKeySlotTakenBeforeTheLock(MYSQLI_REPORT_OFF);
    }

    private function partitioningBeforeTheLock(int $reportMode): void
    {
        $this->sql('ALTER TABLE pool_files ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4');
        $this->allLaterColumns();
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '99,00')");
        $this->other = $this->connect();
        mysqli_select_db($this->other, $this->database);
        $other = $this->other;
        $GLOBALS['ensurePoolFilesBeforeLock'] = static function () use ($other): void {
            mysqli_query($other, 'ALTER TABLE pool_files PARTITION BY HASH(id) PARTITIONS 2');
        };
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertNotContains('unique_filename', $done);
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'nothing deleted');
        self::assertSame('0', $this->one("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"));
        self::assertStringContainsString('changed between the trial and the table lock', (string) file_get_contents($this->log));

        mysqli_report($reportMode);
        self::assertNotContains('unique_filename', ensurePoolFilesSchema('mysqli'), 'a partitioned table cannot hold it: the trial defers');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"));

        $this->sql('ALTER TABLE pool_files REMOVE PARTITIONING');
        mysqli_report($reportMode);
        $done = ensurePoolFilesSchema('mysqli');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertContains('unique_filename', $done);
        self::assertSame('99,00', $this->one("SELECT amount FROM pool_files WHERE filename = 'a.pdf'"), 'the newest row is kept');
        self::assertSame('99.000', $this->one("SELECT norm_amount FROM pool_files WHERE filename = 'a.pdf'"));
    }

    public function testPartitioningBeforeTheLockStrict(): void
    {
        $this->partitioningBeforeTheLock(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testPartitioningBeforeTheLockReportingOff(): void
    {
        $this->partitioningBeforeTheLock(MYSQLI_REPORT_OFF);
    }

    private function aStableTableWithAForeignKeyIsRepaired(int $reportMode): void
    {
        $this->sql('CREATE TABLE pool_parent (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');
        $this->sql('INSERT INTO pool_parent VALUES (1)');
        $this->allLaterColumns();
        $this->sql('ALTER TABLE pool_files ADD COLUMN parent_id INT NULL, ADD CONSTRAINT pool_files_parent_fk FOREIGN KEY (parent_id) REFERENCES pool_parent (id)');
        $this->sql("INSERT INTO pool_files (filename, amount, parent_id) VALUES ('a.pdf', '1,00', 1), ('a.pdf', '99,00', 1)");
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertContains('unique_filename', $done);
        self::assertContains('norm_amount_backfill', $done);
        self::assertSame('99,00', $this->one("SELECT amount FROM pool_files WHERE filename = 'a.pdf'"));
        self::assertSame('99.000', $this->one("SELECT norm_amount FROM pool_files WHERE filename = 'a.pdf'"));
        self::assertSame('1', $this->one("SELECT count(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND constraint_name = 'pool_files_parent_fk'"), 'the foreign key is kept');
    }

    public function testAStableTableWithAForeignKeyIsRepairedStrict(): void
    {
        $this->aStableTableWithAForeignKeyIsRepaired(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testAStableTableWithAForeignKeyIsRepairedReportingOff(): void
    {
        $this->aStableTableWithAForeignKeyIsRepaired(MYSQLI_REPORT_OFF);
    }

    private function unquotedShowCreateIsRepaired(int $reportMode): void
    {
        $this->allLaterColumns();
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '99,00')");
        $this->sql('SET SESSION sql_quote_show_create = 0');
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertContains('unique_filename', $done);
        self::assertSame('99.000', $this->one("SELECT norm_amount FROM pool_files WHERE filename = 'a.pdf'"));
        self::assertSame('0', $this->one('SELECT @@SESSION.sql_quote_show_create'), 'the session setting is left as it was');
    }

    public function testUnquotedShowCreateIsRepairedStrict(): void
    {
        $this->unquotedShowCreateIsRepaired(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testUnquotedShowCreateIsRepairedReportingOff(): void
    {
        $this->unquotedShowCreateIsRepaired(MYSQLI_REPORT_OFF);
    }

    private function aCascadingReference(int $reportMode, bool $duplicates): void
    {
        $this->allLaterColumns();
        $this->sql('CREATE TABLE pool_linked_document (id INT NOT NULL PRIMARY KEY, pool_id INT NOT NULL,
            CONSTRAINT pool_linked_document_fk FOREIGN KEY (pool_id) REFERENCES pool_files (id) ON DELETE CASCADE) ENGINE=InnoDB');
        $second = $duplicates ? 'a.pdf' : 'b.pdf';
        $this->sql("INSERT INTO pool_files (id, filename, amount) VALUES (1, 'a.pdf', '1,00'), (2, '$second', '99,00')");
        $this->sql('INSERT INTO pool_linked_document VALUES (10, 1), (11, 2)');
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertSame('2', $this->one('SELECT count(*) FROM pool_linked_document'), 'no linked row deleted');
        if ($duplicates) {
            self::assertNotContains('unique_filename', $done);
            self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'both duplicates kept');
            self::assertSame('0', $this->one("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"));
            self::assertStringContainsString('pool_linked_document', (string) file_get_contents($this->log), 'the reference is named');
        } else {
            self::assertContains('unique_filename', $done);
            self::assertContains('norm_amount_backfill', $done);
        }
    }

    public function testDuplicatesReferencedByACascadingForeignKeyAreNotDeletedStrict(): void
    {
        $this->aCascadingReference(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT, true);
    }

    public function testDuplicatesReferencedByACascadingForeignKeyAreNotDeletedReportingOff(): void
    {
        $this->aCascadingReference(MYSQLI_REPORT_OFF, true);
    }

    public function testAReferencedTableWithoutDuplicatesIsRepaired(): void
    {
        $this->aCascadingReference(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT, false);
    }

    public function testDuplicatesWithoutTheProcessPrivilegeAreNotDeleted(): void
    {
        $this->allLaterColumns();
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '99,00')");
        $limited = $this->runAs('SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, LOCK TABLES, TRIGGER');

        $done = ensurePoolFilesSchema('mysqli');

        self::assertNotContains('unique_filename', $done);
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'nothing deleted');
        self::assertStringContainsString('PROCESS privilege', (string) file_get_contents($this->log));
        mysqli_close($limited);
    }

    private function aChildInAnotherDatabase(int $reportMode, bool $limitedUser): void
    {
        $this->allLaterColumns();
        $this->sql("INSERT INTO pool_files (id, filename, amount) VALUES (1, 'a.pdf', '1,00'), (2, 'a.pdf', '99,00')");
        $other = $this->childInAnotherDatabase();
        $this->sql("INSERT INTO $other.pool_linked_document VALUES (10, 1), (11, 2)");
        $limited = $limitedUser ? $this->runAs('SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, LOCK TABLES, TRIGGER') : null;
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertNotContains('unique_filename', $done);
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'both parent rows kept');
        self::assertSame('2', $this->one("SELECT count(*) FROM $other.pool_linked_document"), 'both child rows in the other database kept');
        self::assertSame('0', $this->one("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"));
        self::assertStringContainsString($limitedUser ? 'PROCESS privilege' : 'pool_linked_document', (string) file_get_contents($this->log));
        if ($limited !== null) {
            mysqli_close($limited);
        }
    }

    public function testAChildInAnotherDatabaseKeepsEveryRowStrict(): void
    {
        $this->aChildInAnotherDatabase(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT, false);
    }

    public function testAChildInAnotherDatabaseKeepsEveryRowReportingOff(): void
    {
        $this->aChildInAnotherDatabase(MYSQLI_REPORT_OFF, false);
    }

    public function testAChildInAnotherDatabaseKeepsEveryRowForAParentOnlyUser(): void
    {
        $this->aChildInAnotherDatabase(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT, true);
    }

    private function aHyphenatedParentDatabase(int $reportMode): void
    {
        $dashed = 'pool-dash-' . bin2hex(random_bytes(4));
        $this->sql("CREATE DATABASE `$dashed`");
        $this->otherDatabases[] = "`$dashed`";
        mysqli_select_db($this->connection, $dashed);
        $this->sql('CREATE TABLE settings (id INT AUTO_INCREMENT NOT NULL, var_name text, var_grp text, var_value text, var_description text, user_id integer, group_id integer, pos_id integer, PRIMARY KEY (id))');
        $this->sql('CREATE TABLE pool_files (id INT AUTO_INCREMENT NOT NULL, filename varchar(255), subject text, account varchar(50), amount varchar(50), file_date varchar(50), updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP, invoice_number varchar(100), description text, currency varchar(10), content_sha256 char(64), PRIMARY KEY (id)) ENGINE=InnoDB');
        $this->allLaterColumns();
        $this->sql("INSERT INTO pool_files (id, filename, amount) VALUES (1, 'a.pdf', '1,00'), (2, 'a.pdf', '99,00')");
        $child = $this->database . '_dashchild';
        $this->sql("CREATE DATABASE $child");
        $this->otherDatabases[] = $child;
        $this->sql("CREATE TABLE $child.pool_linked_document (id INT NOT NULL PRIMARY KEY, pool_id INT NOT NULL,
            CONSTRAINT dashed_pool_fk FOREIGN KEY (pool_id) REFERENCES `$dashed`.pool_files (id) ON DELETE CASCADE) ENGINE=InnoDB");
        $this->sql("INSERT INTO $child.pool_linked_document VALUES (10, 1), (11, 2)");
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertNotContains('unique_filename', $done);
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'both parent rows kept');
        self::assertSame('2', $this->one("SELECT count(*) FROM $child.pool_linked_document"), 'both child rows kept');
        self::assertStringContainsString('pool_linked_document', (string) file_get_contents($this->log));
    }

    public function testAHyphenatedParentDatabaseKeepsEveryRowStrict(): void
    {
        $this->aHyphenatedParentDatabase(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testAHyphenatedParentDatabaseKeepsEveryRowReportingOff(): void
    {
        $this->aHyphenatedParentDatabase(MYSQLI_REPORT_OFF);
    }

    public function testTwoNullFilenamesAreNoDuplicates(): void
    {
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES (NULL, '10,00'), (NULL, '99,00'), ('a.pdf', '5,00')");
        $limited = $this->runAs('SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, LOCK TABLES, TRIGGER');

        $done = ensurePoolFilesSchema('mysqli');

        self::assertContains('unique_filename', $done);
        self::assertContains('norm_amount_backfill', $done);
        self::assertSame(['10.000', '99.000'], array_column(mysqli_fetch_all($this->sql('SELECT norm_amount FROM pool_files WHERE filename IS NULL ORDER BY norm_amount')), 0), 'both NULL rows kept and filled');
        mysqli_close($limited);
    }

    public function testAnotherHyphenatedDatabasesOwnReferenceDoesNotBlockTheRepair(): void
    {
        // Another tenant-like database, named with a '-', has a pool_files with a referring child of its own.
        $dashed = 'pool-other-' . bin2hex(random_bytes(4));
        $this->sql("CREATE DATABASE `$dashed`");
        $this->otherDatabases[] = "`$dashed`";
        $this->sql("CREATE TABLE `$dashed`.pool_files (id INT NOT NULL PRIMARY KEY, filename varchar(255)) ENGINE=InnoDB");
        $this->sql("CREATE TABLE `$dashed`.pool_linked_document (id INT NOT NULL PRIMARY KEY, pool_id INT NOT NULL,
            FOREIGN KEY (pool_id) REFERENCES `$dashed`.pool_files (id) ON DELETE CASCADE) ENGINE=InnoDB");
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '99,00')");

        $done = ensurePoolFilesSchema('mysqli');

        self::assertContains('unique_filename', $done, 'a reference to another database\'s pool_files is not ours');
        self::assertSame('99.000', $this->one("SELECT norm_amount FROM pool_files WHERE filename = 'a.pdf'"));
    }

    private function aNonAsciiParentDatabaseWithLatin1Results(int $reportMode): void
    {
        $tokyo = 'pool_' . bin2hex(random_bytes(3)) . '_東京';
        mysqli_set_charset($this->connection, 'utf8mb4');
        $this->sql("CREATE DATABASE `$tokyo` CHARACTER SET utf8mb4");
        $this->otherDatabases[] = "`$tokyo`";
        mysqli_select_db($this->connection, $tokyo);
        $this->sql('CREATE TABLE settings (id INT AUTO_INCREMENT NOT NULL, var_name text, var_grp text, var_value text, var_description text, user_id integer, group_id integer, pos_id integer, PRIMARY KEY (id))');
        $this->sql('CREATE TABLE pool_files (id INT AUTO_INCREMENT NOT NULL, filename varchar(255), subject text, account varchar(50), amount varchar(50), file_date varchar(50), updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP, invoice_number varchar(100), description text, currency varchar(10), content_sha256 char(64), PRIMARY KEY (id)) ENGINE=InnoDB');
        $this->allLaterColumns();
        $this->sql("INSERT INTO pool_files (id, filename, amount) VALUES (1, 'a.pdf', '1,00'), (2, 'a.pdf', '99,00')");
        $child = $this->database . '_tokyochild';
        $this->sql("CREATE DATABASE $child");
        $this->otherDatabases[] = $child;
        $this->sql("CREATE TABLE $child.pool_linked_document (id INT NOT NULL PRIMARY KEY, pool_id INT NOT NULL,
            CONSTRAINT tokyo_pool_fk FOREIGN KEY (pool_id) REFERENCES `$tokyo`.pool_files (id) ON DELETE CASCADE) ENGINE=InnoDB");
        $this->sql("INSERT INTO $child.pool_linked_document VALUES (10, 1), (11, 2)");
        $this->sql('SET SESSION character_set_results = latin1'); // the name now comes back as '?'
        self::assertStringContainsString('?', (string) $this->one('SELECT DATABASE()'));
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertSame('latin1', $this->one('SELECT @@SESSION.character_set_results'), 'the session setting is left as it was');
        $this->sql('SET SESSION character_set_results = utf8mb4');
        self::assertNotContains('unique_filename', $done);
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'both parent rows kept');
        self::assertSame('2', $this->one("SELECT count(*) FROM $child.pool_linked_document"), 'both child rows kept');
        self::assertSame('0', $this->one("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"));
    }

    public function testANonAsciiParentDatabaseWithLatin1ResultsKeepsEveryRowStrict(): void
    {
        $this->aNonAsciiParentDatabaseWithLatin1Results(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testANonAsciiParentDatabaseWithLatin1ResultsKeepsEveryRowReportingOff(): void
    {
        $this->aNonAsciiParentDatabaseWithLatin1Results(MYSQLI_REPORT_OFF);
    }

    private function ansiQuotesIsRepaired(int $reportMode): void
    {
        $this->allLaterColumns();
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '99,00')");
        $this->sql("SET SESSION sql_mode = CONCAT(@@SESSION.sql_mode, ',ANSI_QUOTES')");
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertContains('unique_filename', $done);
        self::assertContains('norm_amount_backfill', $done);
        self::assertSame('99.000', $this->one("SELECT norm_amount FROM pool_files WHERE filename = 'a.pdf'"), 'the newest row is kept and filled');
        self::assertStringContainsString('ANSI_QUOTES', (string) $this->one('SELECT @@SESSION.sql_mode'), 'the session mode is left as it was');
    }

    public function testAnsiQuotesIsRepairedStrict(): void
    {
        $this->ansiQuotesIsRepaired(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testAnsiQuotesIsRepairedReportingOff(): void
    {
        $this->ansiQuotesIsRepaired(MYSQLI_REPORT_OFF);
    }

    private function aDeleteTrigger(int $reportMode): void
    {
        $this->allLaterColumns();
        $this->sql('CREATE TABLE pool_linked_document (id INT NOT NULL PRIMARY KEY, pool_id INT) ENGINE=InnoDB');
        $this->sql("INSERT INTO pool_files (id, filename, amount) VALUES (1, 'a.pdf', '1,00'), (2, 'a.pdf', '99,00')");
        $this->sql('INSERT INTO pool_linked_document VALUES (10, 1), (11, 2)');
        $this->sql('CREATE TRIGGER pool_files_unlink AFTER DELETE ON pool_files FOR EACH ROW DELETE FROM pool_linked_document WHERE pool_id = OLD.id');
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertNotContains('unique_filename', $done);
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'both duplicates kept');
        self::assertSame('2', $this->one('SELECT count(*) FROM pool_linked_document'), 'no linked row deleted');
        self::assertSame('0', $this->one("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"));
        self::assertStringContainsString('trigger pool_files_unlink', (string) file_get_contents($this->log));
    }

    public function testADeleteTriggerStopsTheCollapseStrict(): void
    {
        $this->aDeleteTrigger(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testADeleteTriggerStopsTheCollapseReportingOff(): void
    {
        $this->aDeleteTrigger(MYSQLI_REPORT_OFF);
    }

    public function testDuplicatesWithoutTheTriggerPrivilegeAreNotDeleted(): void
    {
        $this->allLaterColumns();
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '99,00')");
        // PROCESS so the reference check could pass; the trigger check is what decides.
        $limited = $this->runAs('SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, LOCK TABLES', 'PROCESS');

        $done = ensurePoolFilesSchema('mysqli');

        self::assertNotContains('unique_filename', $done);
        self::assertSame('2', $this->one("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 'nothing deleted');
        self::assertStringContainsString('TRIGGER privilege', (string) file_get_contents($this->log));
        mysqli_close($limited);
    }

    /** Every column, both indexes and one row still waiting for its norm_amount; no flag. */
    private function onlyTheBackfillMissing(): void
    {
        $this->allLaterColumns();
        $this->sql('CREATE UNIQUE INDEX pool_files_filename_uidx ON pool_files (filename)');
        $this->sql("INSERT INTO pool_files (id, filename, amount) VALUES (1, 'a.pdf', '125,00')");
    }

    /** The regnskab's own row and flag, read on another session (this one sees its temporary table instead). */
    private function permanent(string $sql): ?string
    {
        if ($this->other === null) {
            $this->other = $this->connect();
            mysqli_select_db($this->other, $this->database);
        }
        $row = mysqli_fetch_row(mysqli_query($this->other, $sql));
        return $row === null ? null : ($row[0] === null ? null : (string) $row[0]);
    }

    private function aTemporaryTable(string $table, int $reportMode): void
    {
        $this->onlyTheBackfillMissing();
        // MariaDB refuses CREATE TEMPORARY TABLE t LIKE t; the same definition instead.
        $create = (string) mysqli_fetch_row($this->sql("SHOW CREATE TABLE $table"))[1];
        $this->sql((string) preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create));
        self::assertStringStartsWith('CREATE TEMPORARY TABLE', (string) mysqli_fetch_row($this->sql("SHOW CREATE TABLE $table"))[1]);
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertSame([], $done);
        self::assertNull($this->permanent('SELECT norm_amount FROM pool_files WHERE id = 1'), 'the row is left for the next run');
        self::assertSame('0', $this->permanent("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"));
        self::assertSame('0', $this->one("SELECT count(*) FROM $table"), 'the temporary table untouched');
        self::assertStringContainsString("a temporary $table of this session shadows", (string) file_get_contents($this->log));

        $this->sql("DROP TEMPORARY TABLE $table");
        self::assertSame(['norm_amount_backfill'], ensurePoolFilesSchema('mysqli'));
        self::assertSame('125.000', $this->one('SELECT norm_amount FROM pool_files WHERE id = 1'));
        self::assertSame('1', $this->one("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"));
    }

    public function testATemporaryPoolFilesStopsTheRunStrict(): void
    {
        $this->aTemporaryTable('pool_files', MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testATemporaryPoolFilesStopsTheRunReportingOff(): void
    {
        $this->aTemporaryTable('pool_files', MYSQLI_REPORT_OFF);
    }

    public function testATemporarySettingsStopsTheRunStrict(): void
    {
        $this->aTemporaryTable('settings', MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testATemporarySettingsStopsTheRunReportingOff(): void
    {
        $this->aTemporaryTable('settings', MYSQLI_REPORT_OFF);
    }

    private function aPartlyMigratedPoolWithSettings(bool $asAView, int $reportMode): void
    {
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('b.pdf', '2,50')");
        if ($asAView) {
            $this->sql('RENAME TABLE settings TO settings_storage');
            $this->sql('CREATE VIEW settings AS SELECT * FROM settings_storage');
        }
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertContains('norm_amount', $done);
        self::assertContains('norm_amount_backfill', $done);
        self::assertSame('1.000', $this->one("SELECT norm_amount FROM pool_files WHERE filename = 'a.pdf'"));
        self::assertSame('2.500', $this->one("SELECT norm_amount FROM pool_files WHERE filename = 'b.pdf'"));
        $stored = $asAView ? 'settings_storage' : 'settings';
        self::assertSame('1', $this->one("SELECT count(*) FROM $stored WHERE var_grp = 'system' AND var_name = 'pool_files_norm_amount_backfilled'"));
        self::assertSame([], ensurePoolFilesSchema('mysqli'), 'the next login has nothing to do');
    }

    public function testAnUpdatableSettingsViewTakesTheFlagStrict(): void
    {
        $this->aPartlyMigratedPoolWithSettings(true, MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testAnUpdatableSettingsViewTakesTheFlagReportingOff(): void
    {
        $this->aPartlyMigratedPoolWithSettings(true, MYSQLI_REPORT_OFF);
    }

    public function testASettingsTableTakesTheFlagStrict(): void
    {
        $this->aPartlyMigratedPoolWithSettings(false, MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testASettingsTableTakesTheFlagReportingOff(): void
    {
        $this->aPartlyMigratedPoolWithSettings(false, MYSQLI_REPORT_OFF);
    }

    private function aTemporarySettingsHoldingTheFlag(int $reportMode): void
    {
        $this->onlyTheBackfillMissing();
        $create = (string) mysqli_fetch_row($this->sql('SHOW CREATE TABLE settings'))[1];
        $this->sql((string) preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $create));
        $this->sql("INSERT INTO settings (var_name, var_grp, var_value) VALUES ('pool_files_norm_amount_backfilled', 'system', 'yes')");
        mysqli_report($reportMode);

        $first = ensurePoolFilesSchema('mysqli');
        $second = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertSame([[], []], [$first, $second]);
        self::assertSame(2, substr_count((string) file_get_contents($this->log), 'a temporary settings of this session shadows'), 'each run says why it stopped');
        self::assertNull($this->permanent('SELECT norm_amount FROM pool_files WHERE id = 1'));
        self::assertSame('0', $this->permanent("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"));
        self::assertSame('1', $this->one('SELECT count(*) FROM settings'), 'the temporary settings untouched');

        $this->sql('DROP TEMPORARY TABLE settings');
        self::assertSame(['norm_amount_backfill'], ensurePoolFilesSchema('mysqli'));
        self::assertSame('125.000', $this->one('SELECT norm_amount FROM pool_files WHERE id = 1'));
        self::assertSame('1', $this->one("SELECT count(*) FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'"));
    }

    public function testATemporarySettingsHoldingTheFlagStopsWithAReasonStrict(): void
    {
        $this->aTemporarySettingsHoldingTheFlag(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testATemporarySettingsHoldingTheFlagStopsWithAReasonReportingOff(): void
    {
        $this->aTemporarySettingsHoldingTheFlag(MYSQLI_REPORT_OFF);
    }

    /** Only the backfill missing, settings a view over settings_storage. */
    private function onlyTheBackfillMissingWithASettingsView(): void
    {
        $this->onlyTheBackfillMissing();
        $this->sql('RENAME TABLE settings TO settings_storage');
        $this->sql('CREATE VIEW settings AS SELECT * FROM settings_storage');
    }

    private function aSettingsViewForARestrictedAccount(bool $showView, int $reportMode): void
    {
        $this->onlyTheBackfillMissingWithASettingsView();
        $limited = $this->runAs('SELECT, INSERT, UPDATE' . ($showView ? ', SHOW VIEW' : ''));
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertSame(['norm_amount_backfill'], $done, (string) file_get_contents($this->log));
        self::assertSame('125.000', $this->one('SELECT norm_amount FROM pool_files WHERE id = 1'));
        self::assertSame('1', $this->one("SELECT count(*) FROM settings_storage WHERE var_grp = 'system' AND var_name = 'pool_files_norm_amount_backfilled'"), 'the flag through the view');
        self::assertSame([], ensurePoolFilesSchema('mysqli'), 'the next login has nothing to do');
        mysqli_close($limited);
    }

    public function testASettingsViewWithoutShowViewTakesTheFlagStrict(): void
    {
        $this->aSettingsViewForARestrictedAccount(false, MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testASettingsViewWithoutShowViewTakesTheFlagReportingOff(): void
    {
        $this->aSettingsViewForARestrictedAccount(false, MYSQLI_REPORT_OFF);
    }

    public function testASettingsViewWithShowViewTakesTheFlagStrict(): void
    {
        $this->aSettingsViewForARestrictedAccount(true, MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testASettingsViewWithShowViewTakesTheFlagReportingOff(): void
    {
        $this->aSettingsViewForARestrictedAccount(true, MYSQLI_REPORT_OFF);
    }

    private function aTemporarySettingsOverAViewForARestrictedAccount(int $reportMode): void
    {
        $this->onlyTheBackfillMissingWithASettingsView();
        $create = (string) mysqli_fetch_row($this->sql('SHOW CREATE TABLE settings_storage'))[1];
        $limited = $this->runAs('SELECT, INSERT, UPDATE, CREATE TEMPORARY TABLES');
        mysqli_query($limited, (string) preg_replace('/^CREATE TABLE `settings_storage`/', 'CREATE TEMPORARY TABLE `settings`', $create));
        mysqli_query($limited, "INSERT INTO settings (var_name, var_grp, var_value) VALUES ('pool_files_norm_amount_backfilled', 'system', 'yes')");
        mysqli_report($reportMode);

        $done = ensurePoolFilesSchema('mysqli');

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        self::assertSame([], $done);
        self::assertStringContainsString('a temporary settings of this session shadows', (string) file_get_contents($this->log));
        self::assertNull($this->one('SELECT norm_amount FROM pool_files WHERE id = 1'));
        self::assertSame('0', $this->one("SELECT count(*) FROM settings_storage WHERE var_name = 'pool_files_norm_amount_backfilled'"));
        self::assertSame('1', (string) mysqli_fetch_row(mysqli_query($limited, 'SELECT count(*) FROM settings'))[0], 'the temporary settings untouched');

        mysqli_query($limited, 'DROP TEMPORARY TABLE settings');
        self::assertSame(['norm_amount_backfill'], ensurePoolFilesSchema('mysqli'));
        self::assertSame('125.000', $this->one('SELECT norm_amount FROM pool_files WHERE id = 1'));
        self::assertSame('1', $this->one("SELECT count(*) FROM settings_storage WHERE var_name = 'pool_files_norm_amount_backfilled'"));
        mysqli_close($limited);
    }

    public function testATemporarySettingsOverAViewStopsARestrictedAccountStrict(): void
    {
        $this->aTemporarySettingsOverAViewForARestrictedAccount(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function testATemporarySettingsOverAViewStopsARestrictedAccountReportingOff(): void
    {
        $this->aTemporarySettingsOverAViewForARestrictedAccount(MYSQLI_REPORT_OFF);
    }
}
