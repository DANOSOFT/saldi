<?php
// 20261118 CL/MMR SD-815 Created. pool_files of a regnskab created at 5.1 (includes/opretfunc.php before this fix) gets norm_amount, the other later columns and a unique filename at login (includes/opdat_func/ensurePoolFilesSchema.php).
//                 So the pool writers' INSERT ... ON CONFLICT (filename) works, and a new regnskab gets that schema at once. Real PostgreSQL, private schema.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EnsurePoolFilesSchemaTest extends TestCase
{
    private ?\PgSql\Connection $connection = null;

    /** The pool_files statement includes/opretfunc.php ran for a new regnskab before this fix. */
    private const OLD_OPRET_POOL_FILES = "CREATE TABLE pool_files (id SERIAL NOT NULL, filename varchar(255), subject text, account varchar(50), "
        . "amount varchar(50), file_date varchar(50), updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP, "
        . "invoice_number varchar(100), description text, currency varchar(10), content_sha256 char(64), PRIMARY KEY (id))";

    protected function setUp(): void
    {
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $this->connection = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        $GLOBALS['ensurePoolFilesConnection'] = $this->connection;
        $GLOBALS['ensurePoolFilesWrites'] = [];
        if (!function_exists('db_select')) {
            function db_select($sql, $location = '') {
                $result = pg_query($GLOBALS['ensurePoolFilesConnection'], $sql);
                if ($result === false) {
                    throw new RuntimeException(pg_last_error($GLOBALS['ensurePoolFilesConnection']));
                }
                return $result;
            }
            function db_fetch_array($result) {
                return $result === false ? false : pg_fetch_assoc($result);
            }
            function db_modify($sql, $location = '') {
                $GLOBALS['ensurePoolFilesWrites'][] = $sql;
                return db_select($sql, $location);
            }
            function db_escape_string($value) {
                return pg_escape_string($GLOBALS['ensurePoolFilesConnection'], (string) $value);
            }
        }
        require_once __DIR__ . '/../../../includes/opdat_func/ensurePoolFilesSchema.php';
        $this->sql('BEGIN');
        $schema = 'pool_schema_' . bin2hex(random_bytes(8));
        $this->sql("CREATE SCHEMA $schema");
        $this->sql("SET LOCAL search_path TO $schema");
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            pg_query($this->connection, 'ROLLBACK');
            pg_close($this->connection);
        }
        unset($GLOBALS['ensurePoolFilesConnection'], $GLOBALS['ensurePoolFilesWrites']);
    }

    private function sql(string $sql): \PgSql\Result
    {
        $result = pg_query($this->connection, $sql);
        self::assertNotFalse($result, pg_last_error($this->connection));
        return $result;
    }

    /** The pool writers' insert (includes/docsIncludes/poolUpload.php, poolUploadRegisterFile()). */
    private function poolInsert(string $filename, string $amount, string $hash): void
    {
        $this->sql("INSERT INTO pool_files (filename, subject, account, amount, file_date, invoice_number, description, currency, content_sha256, source_sha256, norm_amount) "
            . "VALUES ('$filename', 'Leverandør', '', '$amount', '2026-11-18', 'F1', '', 'DKK', '$hash', NULL, 125.000) ON CONFLICT (filename) DO NOTHING");
    }

    /** @return list<string> */
    private function columns(): array
    {
        return pg_fetch_all_columns($this->sql("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'pool_files' ORDER BY column_name"));
    }

    public function testARegnskabCreatedAt51GetsWhatThePoolWritersNeed(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1.234,50'), ('a.pdf', '99,00'), ('b.pdf', ''), ('c.pdf', '10')");
        $failed = @pg_query($this->connection, "SAVEPOINT before_fix; INSERT INTO pool_files (filename, norm_amount) VALUES ('x.pdf', 1) ON CONFLICT (filename) DO NOTHING");
        self::assertFalse($failed, 'the old schema refuses the pool insert');
        $this->sql('ROLLBACK TO SAVEPOINT before_fix');

        $done = ensurePoolFilesSchema('postgresql');

        self::assertContains('norm_amount', $done);
        self::assertContains('unique_filename', $done);
        self::assertContains('norm_amount_index', $done);
        foreach (array_keys(ensurePoolFilesSchemaColumns()) as $column) {
            self::assertContains($column, $this->columns(), "$column added");
        }
        // Duplicate filenames collapse to the newest row; norm_amount is filled from the amount text.
        self::assertSame([['filename' => 'a.pdf', 'amount' => '99,00', 'norm_amount' => '99.000', 'manually_edited' => 'f'],
            ['filename' => 'b.pdf', 'amount' => '', 'norm_amount' => null, 'manually_edited' => 'f'],
            ['filename' => 'c.pdf', 'amount' => '10', 'norm_amount' => '10.000', 'manually_edited' => 'f']],
            pg_fetch_all($this->sql('SELECT filename, amount, norm_amount, manually_edited FROM pool_files ORDER BY filename')));

        // The same document delivered twice gives one row.
        $this->poolInsert('semantic-invoice.xml', '125,00', str_repeat('a', 64));
        $this->poolInsert('semantic-invoice.xml', '125,00', str_repeat('a', 64));
        self::assertSame('1', pg_fetch_result($this->sql("SELECT count(*) FROM pool_files WHERE filename = 'semantic-invoice.xml'"), 0, 0));

        // A second login changes nothing and writes nothing.
        $GLOBALS['ensurePoolFilesWrites'] = [];
        self::assertSame([], ensurePoolFilesSchema('postgresql'));
        self::assertSame([], $GLOBALS['ensurePoolFilesWrites']);
    }

    public function testAnExistingUniqueFilenameConstraintIsKeptAndNotDuplicated(): void
    {
        // A table made by poolUploadEnsureSchema() has an unnamed UNIQUE (pool_files_filename_key) and every column.
        $this->sql("CREATE TABLE pool_files (id serial PRIMARY KEY, filename varchar(255) NOT NULL UNIQUE, subject text, account varchar(50),
            amount varchar(50), norm_amount numeric(15,3), file_date varchar(50), invoice_number varchar(100), description text,
            manually_edited boolean NOT NULL DEFAULT false, currency varchar(10), updated timestamp DEFAULT CURRENT_TIMESTAMP,
            vendor_name text, vendor_cvr varchar(20), vendor_iban varchar(40), vendor_konto_id integer, vendor_match varchar(10),
            vendor_score numeric(4,3), content_sha256 char(64), source_sha256 char(64))");
        self::assertSame(['norm_amount_index'], ensurePoolFilesSchema('postgresql'));
        self::assertSame([], ensurePoolFilesSchema('postgresql'));
        $unique = pg_fetch_all_columns($this->sql("SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'pool_files' AND indexdef ILIKE '%UNIQUE%(filename)%' ORDER BY 1"));
        self::assertSame(['pool_files_filename_key'], $unique, 'no second unique index on filename');
    }

    public function testNoPoolFilesTableNoWork(): void
    {
        self::assertSame([], ensurePoolFilesSchema('postgresql'));
        self::assertSame([], $GLOBALS['ensurePoolFilesWrites']);
    }

    public function testANewRegnskabGetsTheSchemaAtOnce(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../../includes/opretfunc.php');
        $start = strpos($source, '$qtxt = "CREATE TABLE pool_files (');
        $end = strpos($source, 'CREATE INDEX idx_pool_files_norm_amount', (int) $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $end = strpos($source, "\n", $end);
        $id_column = 'id SERIAL NOT NULL';
        eval(substr($source, $start, $end - $start));

        $GLOBALS['ensurePoolFilesWrites'] = [];
        self::assertSame([], ensurePoolFilesSchema('postgresql'), 'a new regnskab already has everything');
        $this->poolInsert('first.pdf', '125,00', str_repeat('b', 64));
        $this->poolInsert('first.pdf', '125,00', str_repeat('b', 64));
        self::assertSame('1', pg_fetch_result($this->sql('SELECT count(*) FROM pool_files'), 0, 0));
        self::assertSame('f', pg_fetch_result($this->sql('SELECT manually_edited FROM pool_files'), 0, 0));
    }
}
