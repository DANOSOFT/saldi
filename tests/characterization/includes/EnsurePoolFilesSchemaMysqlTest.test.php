<?php
// 20261118 CL/MMR SD-815 Created (Codex round 12). includes/opdat_func/ensurePoolFilesSchema.php on real MySQL/MariaDB with PHP 8's default mysqli reporting (exceptions).
//                 A pending read that times out behind another session's table lock ends the run without an uncaught exception, flag or held lock; the next run fills the row.
//                 Needs the mysqli extension and SALDI_CHAR_MYSQL_SOCKET (a disposable server, user root without password unless SALDI_CHAR_MYSQL_USER/PASS); skipped otherwise.
// 20261118 CL/MMR SD-815 Codex round 14: when the unique-index repair cannot get the table (another session's open transaction has read pool_files), no duplicate row is deleted; the retry keeps the newest row.

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

    protected function tearDown(): void
    {
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
}
