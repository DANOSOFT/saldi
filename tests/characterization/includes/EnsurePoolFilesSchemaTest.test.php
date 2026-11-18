<?php
// 20261118 CL/MMR SD-815 Created. pool_files of a regnskab created at 5.1 (includes/opretfunc.php before this fix) gets norm_amount, the other later columns and a unique filename at login (includes/opdat_func/ensurePoolFilesSchema.php).
//                 So the pool writers' INSERT ... ON CONFLICT (filename) works, and a new regnskab gets that schema at once. Real PostgreSQL, private schema.
// 20261118 CL/MMR SD-815 Codex round 7: a partial unique index on filename does not count, and an interrupted norm_amount backfill is finished by the next run.
// 20261118 CL/MMR SD-815 Codex round 8: a deferrable filename constraint is replaced, and a failed or impossible norm_amount update never marks the backfill done.
//                 The test's db_modify() acts like the production one for an update file: a failed statement is logged, rolled back to a savepoint and reported as accepted.
// 20261118 CL/MMR SD-815 Codex round 9: two connections, a pool save committing while the backfill update waits for its row lock is kept (amount and norm_amount together, or the text alone, filled on the next run).
// 20261118 CL/MMR SD-815 Codex round 10: the test's db_select()/db_modify() rewrite ' like ' to ' ilike ' as the production PostgreSQL branch does, and amount texts with ' like ', quotes and ';' are filled.
// 20261118 CL/MMR SD-815 Codex round 11: the test's db_select() returns false on an error, as the production one does; a pending-row read cancelled by a real lock timeout leaves no flag, and the next run fills the row.
// 20261118 CL/MMR SD-815 Codex round 13: CREATE UNIQUE INDEX timing out behind another session's lock rolls the whole filename repair back (both rows kept, nothing reported, no flag); the retry completes it.
//                 $GLOBALS['connection'] is the test connection, as in production, and transaction statements pass straight through the test's db_modify().
// 20261118 CL/MMR SD-815 Codex round 14: a pending read that times out inside a caller's transaction leaves that transaction usable and no advisory lock; transaction statements are not counted as writes.
// 20261118 CL/MMR SD-815 Codex round 20: an ALTER TABLE that times out (db_modify() only logs it) is not reported as an added column, and the run stops without a flag.
// 20261118 CL/MMR SD-815 Codex round 21: a rejected backfill-flag INSERT is not reported, in autocommit and inside a caller's transaction (which can still commit its own row); the retry writes one flag.
// 20261118 CL/MMR SD-815 Codex round 22: a unique index on left(filename, 7) is replaced by the full one, so two different names sharing the prefix both register.
// 20261118 CL/MMR SD-815 Codex round 23: a unique index on lower(subject) WHERE filename IS NOT NULL is not touched and still enforces its rule, with and without a repair.
// 20261118 CL/MMR SD-815 Codex round 24: a unique index on the constant lower('filename') is kept and still allows one row; an odd column name ("scratch(") does not stop the left(filename, 7) repair.
// 20261118 CL/MMR SD-815 Codex round 25: unique indexes on reverse(filename) and on a CASE rule over filename are kept (and the rule still holds); the log names them for review.
// 20261118 CL/MMR SD-815 Codex round 26: with a tenant's own substr() earlier in search_path, the index on it is kept and the real pg_catalog.substr prefix index is replaced.
// 20261118 CL/MMR SD-815 Codex round 27: a lookup index on left(filename, 255) of a varchar(255) filename covers every name and is kept, with and without a repair.
// 20261118 CL/MMR SD-815 Codex round 28: left(filename, 255) on a domain over varchar(255) is kept, and a left(filename, 0) one-row rule is kept and still holds.
// 20261118 CL/MMR SD-815 Codex round 29: PostgreSQL never drops an expression index (tests that expected left(filename, 7) to be replaced now expect it kept and named for review); lookups on CHECK-limited text and domains are kept.
// 20261118 CL/MMR SD-815 Codex round 30: a unique filename key with INCLUDE (id) counts, so nothing is added and the backfill runs; with both usual names taken the new index gets pool_files_filename_uidx2.
// 20261118 CL/MMR SD-815 Codex round 31: with the four usual names taken (three indexes and a table with a row) the new index is pool_files_filename_uidx4; a stopped run is logged with its reason.
// 20261118 CL/MMR SD-815 Codex round 37: an ON DELETE CASCADE reference from another table and duplicate filenames: nothing deleted anywhere, no flag, the constraint named in the log; without duplicates the repair runs.
// 20261118 CL/MMR SD-815 Codex round 42: a DELETE trigger or a DELETE rule on pool_files and duplicate filenames: nothing deleted anywhere, no flag, named in the log.
// 20261118 CL/MMR SD-815 Codex round 43: a table inheriting from pool_files (older duplicate and a DELETE trigger there) keeps every row anywhere, no unique index, no flag, named in the log; a unique index on pool_files alone does not count while a table inherits from it.
// 20261118 CL/MMR SD-815 Codex round 44: in autocommit and in a caller's transaction, a temporary pool_files shadowing an indexed one changes nothing and writes no flag (the next run without it fills the permanent row), and a partly migrated table with an inheriting one keeps both tables' columns as they were.
// 20261118 CL/MMR SD-815 Codex round 45: a partly migrated UNLOGGED pool_files with duplicates in current_schema() is repaired and backfilled like a logged one and stays unlogged (autocommit and a caller's transaction).

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EnsurePoolFilesSchemaTest extends TestCase
{
    private ?\PgSql\Connection $connection = null;
    private ?\PgSql\Connection $other = null;
    private string $database = '';
    private string $committedSchema = '';
    private string $log = '';

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
        $this->log = (string) tempnam(sys_get_temp_dir(), 'pool_schema_pg_log_');
        ini_set('error_log', $this->log); // error_log() from the helper, not to stderr
        $this->database = TestDatabase::sqlFixture(static::class)->name();
        $this->connection = TestDatabase::connect($this->database);
        $GLOBALS['ensurePoolFilesConnection'] = $this->connection;
        $GLOBALS['connection'] = $this->connection; // ensurePoolFilesInTransaction() reads it, as db_query_connection() does
        $GLOBALS['ensurePoolFilesWrites'] = [];
        $GLOBALS['ensurePoolFilesErrors'] = [];
        if (!function_exists('db_select')) {
            function db_select($sql, $location = '') {
                // As includes/db_query.php on PostgreSQL: ' like ' becomes ' ilike ', and an error is logged and returns false.
                $sql = str_replace(' like ', ' ilike ', $sql);
                $connection = $GLOBALS['ensurePoolFilesConnection'];
                $inTransaction = pg_transaction_status($connection) === PGSQL_TRANSACTION_INTRANS && empty($GLOBALS['ensurePoolFilesRaw']);
                if ($inTransaction) {
                    pg_query($connection, 'SAVEPOINT db_select');
                }
                $result = @pg_query($connection, $sql);
                if ($result === false) {
                    $GLOBALS['ensurePoolFilesErrors'][] = pg_last_error($connection);
                    if ($inTransaction) {
                        pg_query($connection, 'ROLLBACK TO SAVEPOINT db_select');
                    }
                }
                if ($inTransaction) {
                    pg_query($connection, 'RELEASE SAVEPOINT db_select');
                }
                return $result;
            }
            function db_fetch_array($result) {
                return $result === false ? false : pg_fetch_assoc($result);
            }
            function db_modify($sql, $location = '') {
                $sql = str_replace(' like ', ' ilike ', $sql); // as includes/db_query.php does on PostgreSQL
                $connection = $GLOBALS['ensurePoolFilesConnection'];
                if (preg_match('/^(BEGIN|COMMIT|ROLLBACK|SAVEPOINT|RELEASE)\b/', $sql)) {
                    $GLOBALS['ensurePoolFilesTransactionStatements'][] = $sql;
                    if (@pg_query($connection, $sql) === false) {
                        $GLOBALS['ensurePoolFilesErrors'][] = pg_last_error($connection);
                    }
                    return '0' . chr(9) . 'query accepted';
                }
                $GLOBALS['ensurePoolFilesWrites'][] = $sql;
                $inTransaction = pg_transaction_status($connection) === PGSQL_TRANSACTION_INTRANS && empty($GLOBALS['ensurePoolFilesRaw']);
                if ($inTransaction) {
                    pg_query($connection, 'SAVEPOINT db_modify');
                }
                $whileBlocked = $GLOBALS['ensurePoolFilesWhileBlocked'] ?? null;
                if ($whileBlocked !== null && str_starts_with($sql, 'UPDATE pool_files SET norm_amount')) {
                    // Sent without waiting; the test lets its other connection commit while this one waits for the row lock.
                    unset($GLOBALS['ensurePoolFilesWhileBlocked']);
                    pg_send_query($connection, $sql);
                    $whileBlocked(pg_get_pid($connection));
                    $result = pg_get_result($connection);
                    $ok = pg_result_status($result) === PGSQL_COMMAND_OK;
                    $GLOBALS['ensurePoolFilesBlockedRows'] = $ok ? pg_affected_rows($result) : -1;
                } else {
                    $ok = @pg_query($connection, $sql) !== false;
                }
                if (!$ok) {
                    $GLOBALS['ensurePoolFilesErrors'][] = pg_last_error($connection);
                    if ($inTransaction) {
                        pg_query($connection, 'ROLLBACK TO SAVEPOINT db_modify');
                    }
                }
                if ($inTransaction) {
                    pg_query($connection, 'RELEASE SAVEPOINT db_modify');
                }
                return '0' . chr(9) . 'query accepted';
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
        $this->sql('CREATE TABLE settings (id serial PRIMARY KEY, var_name text, var_grp text, var_value text, var_description text, user_id integer, group_id integer, pos_id integer)');
    }

    private function backfillFlags(): int
    {
        return (int) pg_fetch_result($this->sql("SELECT count(*) FROM settings WHERE var_grp = 'system' AND var_name = 'pool_files_norm_amount_backfilled'"), 0, 0);
    }

    protected function tearDown(): void
    {
        if ($this->other !== null) {
            @pg_query($this->other, 'ROLLBACK');
            pg_close($this->other);
        }
        if ($this->connection !== null) {
            @pg_query($this->connection, 'ROLLBACK');
            if ($this->committedSchema !== '') {
                pg_query($this->connection, "DROP SCHEMA {$this->committedSchema} CASCADE");
            }
            pg_close($this->connection);
        }
        unset($GLOBALS['ensurePoolFilesWhileBlocked'], $GLOBALS['ensurePoolFilesBlockedRows']);
        if ($this->log !== '' && is_file($this->log)) {
            unlink($this->log);
        }
        unset($GLOBALS['ensurePoolFilesConnection'], $GLOBALS['ensurePoolFilesWrites'], $GLOBALS['ensurePoolFilesErrors'], $GLOBALS['connection'], $GLOBALS['ensurePoolFilesRaw']);
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
        self::assertContains('norm_amount_backfill', $done);
        self::assertSame(1, $this->backfillFlags());
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
        self::assertSame(['norm_amount_index', 'norm_amount_backfill'], ensurePoolFilesSchema('postgresql'));
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

        self::assertSame(['norm_amount_backfill'], ensurePoolFilesSchema('postgresql'), 'a new regnskab has the schema; only the (empty) backfill is recorded once');
        $GLOBALS['ensurePoolFilesWrites'] = [];
        self::assertSame([], ensurePoolFilesSchema('postgresql'));
        self::assertSame([], $GLOBALS['ensurePoolFilesWrites']);
        $this->poolInsert('first.pdf', '125,00', str_repeat('b', 64));
        $this->poolInsert('first.pdf', '125,00', str_repeat('b', 64));
        self::assertSame('1', pg_fetch_result($this->sql('SELECT count(*) FROM pool_files'), 0, 0));
        self::assertSame('f', pg_fetch_result($this->sql('SELECT manually_edited FROM pool_files'), 0, 0));
    }

    public function testAPartialUniqueIndexOnFilenameDoesNotCount(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql("CREATE UNIQUE INDEX pool_files_filename_uidx ON pool_files (filename) WHERE filename LIKE '%.xml'");
        $failed = @pg_query($this->connection, "SAVEPOINT partial; INSERT INTO pool_files (filename) VALUES ('x.xml') ON CONFLICT (filename) DO NOTHING");
        self::assertFalse($failed, 'a partial index cannot serve ON CONFLICT (filename)');
        $this->sql('ROLLBACK TO SAVEPOINT partial');

        $done = ensurePoolFilesSchema('postgresql');

        self::assertContains('unique_filename', $done);
        $this->poolInsert('semantic-invoice.xml', '125,00', str_repeat('c', 64));
        $this->poolInsert('semantic-invoice.xml', '125,00', str_repeat('c', 64));
        self::assertSame('1', pg_fetch_result($this->sql("SELECT count(*) FROM pool_files WHERE filename = 'semantic-invoice.xml'"), 0, 0));
        self::assertSame(['pool_files_filename_full_uidx', 'pool_files_filename_uidx'],
            pg_fetch_all_columns($this->sql("SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'pool_files' AND indexdef ILIKE '%UNIQUE%(filename)%' ORDER BY 1")));
        self::assertSame([], ensurePoolFilesSchema('postgresql'));
    }

    public function testAnInterruptedBackfillIsFinishedByTheNextRun(): void
    {
        // The state after a run that added the column and the indexes but stopped before the backfill finished.
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('ALTER TABLE pool_files ADD COLUMN norm_amount numeric(15,3), ADD COLUMN manually_edited boolean NOT NULL DEFAULT false,
            ADD COLUMN source_sha256 char(64), ADD COLUMN vendor_name text, ADD COLUMN vendor_cvr varchar(20), ADD COLUMN vendor_iban varchar(40),
            ADD COLUMN vendor_konto_id integer, ADD COLUMN vendor_score numeric(4,3), ADD COLUMN vendor_match varchar(10)');
        $this->sql('CREATE UNIQUE INDEX pool_files_filename_uidx ON pool_files (filename)');
        $this->sql('CREATE INDEX idx_pool_files_norm_amount ON pool_files (norm_amount)');
        $this->sql("INSERT INTO pool_files (filename, amount, norm_amount) VALUES ('old.pdf', '99,00', NULL), ('done.pdf', '5,00', 5), ('text.pdf', 'ukendt', NULL)");
        self::assertSame(0, $this->backfillFlags());

        self::assertSame(['norm_amount_backfill'], ensurePoolFilesSchema('postgresql'));

        self::assertSame([['filename' => 'done.pdf', 'norm_amount' => '5.000'], ['filename' => 'old.pdf', 'norm_amount' => '99.000'], ['filename' => 'text.pdf', 'norm_amount' => null]],
            pg_fetch_all($this->sql('SELECT filename, norm_amount FROM pool_files ORDER BY filename')));
        self::assertSame(1, $this->backfillFlags());
        $GLOBALS['ensurePoolFilesWrites'] = [];
        self::assertSame([], ensurePoolFilesSchema('postgresql'), 'the next run makes no writes');
        self::assertSame([], $GLOBALS['ensurePoolFilesWrites']);
    }

    public function testARegnskabThatRanTheOldBackfillIsNotScannedAgain(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('pool_files_norm_amount_backfilled', 'system', 'yes', 'opdat_5.0.php')");
        $done = ensurePoolFilesSchema('postgresql');
        self::assertNotContains('norm_amount_backfill', $done);
        self::assertSame(1, $this->backfillFlags());
    }

    /** @return array<int, array{conname: string}> */
    private function deferrableConstraints(): array
    {
        return pg_fetch_all($this->sql("SELECT conname FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid JOIN pg_namespace n ON n.oid = t.relnamespace
            WHERE t.relname = 'pool_files' AND n.nspname = current_schema() AND c.condeferrable"));
    }

    public function testADeferrableFilenameConstraintIsReplacedByAnImmediateIndex(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('ALTER TABLE pool_files ADD CONSTRAINT pool_files_filename_key UNIQUE (filename) DEFERRABLE INITIALLY IMMEDIATE');
        $failed = @pg_query($this->connection, "SAVEPOINT before_deferrable; INSERT INTO pool_files (filename) VALUES ('x.xml') ON CONFLICT (filename) DO NOTHING");
        self::assertFalse($failed, 'ON CONFLICT cannot use a deferrable constraint');
        $this->sql('ROLLBACK TO SAVEPOINT before_deferrable');

        self::assertContains('unique_filename', ensurePoolFilesSchema('postgresql'));

        self::assertSame([], $this->deferrableConstraints());
        $this->poolInsert('semantic-invoice.xml', '125,00', str_repeat('d', 64));
        $this->poolInsert('semantic-invoice.xml', '125,00', str_repeat('d', 64));
        self::assertSame('1', pg_fetch_result($this->sql("SELECT count(*) FROM pool_files WHERE filename = 'semantic-invoice.xml'"), 0, 0));
        self::assertSame([], ensurePoolFilesSchema('postgresql'));
        self::assertSame([], $GLOBALS['ensurePoolFilesErrors']);
    }

    public function testADeferrableConstraintNextToAnImmediateIndexIsDropped(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('ALTER TABLE pool_files ADD CONSTRAINT pool_files_filename_key UNIQUE (filename) DEFERRABLE INITIALLY IMMEDIATE');
        $this->sql('CREATE UNIQUE INDEX pool_files_filename_uidx ON pool_files (filename)');

        self::assertContains('unique_filename', ensurePoolFilesSchema('postgresql'));

        self::assertSame([], $this->deferrableConstraints());
        self::assertSame(['pool_files_filename_uidx'],
            pg_fetch_all_columns($this->sql("SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'pool_files' AND indexdef ILIKE '%UNIQUE%(filename)%'")));
        $this->poolInsert('semantic-invoice.xml', '125,00', str_repeat('e', 64));
        $this->poolInsert('semantic-invoice.xml', '125,00', str_repeat('e', 64));
        self::assertSame('1', pg_fetch_result($this->sql("SELECT count(*) FROM pool_files WHERE filename = 'semantic-invoice.xml'"), 0, 0));
        self::assertSame([], $GLOBALS['ensurePoolFilesErrors']);
    }

    public function testAFailedUpdateLeavesTheBackfillOpenUntilALaterRunFillsTheRow(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('old.pdf', '99,00')");
        self::assertNotSame([], ensurePoolFilesSchema('postgresql'));
        // The schema is complete now; make the next backfill write fail, as a production write error would.
        $this->sql("DELETE FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'");
        $this->sql('UPDATE pool_files SET norm_amount = NULL');
        $this->sql("CREATE FUNCTION pool_files_fail() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''forced pool_files update failure''; END'");
        $this->sql('CREATE TRIGGER pool_files_fail BEFORE UPDATE ON pool_files FOR EACH ROW EXECUTE FUNCTION pool_files_fail()');

        self::assertSame([], ensurePoolFilesSchema('postgresql'), 'nothing is reported done');
        self::assertNotSame([], $GLOBALS['ensurePoolFilesErrors'], 'the update really failed');
        self::assertSame(0, $this->backfillFlags(), 'no completion flag after a failed write');
        self::assertNull(pg_fetch_result($this->sql("SELECT norm_amount FROM pool_files WHERE filename = 'old.pdf'"), 0, 0));

        $this->sql('DROP TRIGGER pool_files_fail ON pool_files');
        $GLOBALS['ensurePoolFilesErrors'] = [];
        self::assertSame(['norm_amount_backfill'], ensurePoolFilesSchema('postgresql'));
        self::assertSame('99.000', pg_fetch_result($this->sql("SELECT norm_amount FROM pool_files WHERE filename = 'old.pdf'"), 0, 0));
        self::assertSame(1, $this->backfillFlags());
        $GLOBALS['ensurePoolFilesWrites'] = [];
        self::assertSame([], ensurePoolFilesSchema('postgresql'));
        self::assertSame([], $GLOBALS['ensurePoolFilesWrites']);
        self::assertSame([], $GLOBALS['ensurePoolFilesErrors']);
    }

    public function testAnAmountNumericCannotHoldStaysEmptyAndDoesNotBlockTheFlag(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('huge.pdf', '1000000000000'), ('max.pdf', '999.999.999.999,99'), ('ok.pdf', '12,50')");

        ensurePoolFilesSchema('postgresql');

        self::assertSame([], $GLOBALS['ensurePoolFilesErrors'], 'no overflow is attempted');
        self::assertSame([['filename' => 'huge.pdf', 'norm_amount' => null], ['filename' => 'max.pdf', 'norm_amount' => '999999999999.990'], ['filename' => 'ok.pdf', 'norm_amount' => '12.500']],
            pg_fetch_all($this->sql('SELECT filename, norm_amount FROM pool_files ORDER BY filename')));
        self::assertSame(1, $this->backfillFlags());
        $GLOBALS['ensurePoolFilesWrites'] = [];
        self::assertSame([], ensurePoolFilesSchema('postgresql'));
        self::assertSame([], $GLOBALS['ensurePoolFilesWrites']);
    }

    /**
     * A committed private schema (a second connection cannot see the per-test transaction), with the old 5.1 pool_files
     * already given its columns and indexes, one row '99,00' without norm_amount and no backfill flag. Dropped in tearDown.
     */
    private function committedPoolWithPendingRow(): void
    {
        $this->sql('ROLLBACK');
        $this->committedSchema = 'pool_lock_' . bin2hex(random_bytes(8));
        $this->sql("CREATE SCHEMA {$this->committedSchema}");
        $this->sql("SET search_path TO {$this->committedSchema}");
        $this->sql('CREATE TABLE settings (id serial PRIMARY KEY, var_name text, var_grp text, var_value text, var_description text, user_id integer, group_id integer, pos_id integer)');
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('old.pdf', '99,00')");
        ensurePoolFilesSchema('postgresql');
        $this->sql("DELETE FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'");
        $this->sql('UPDATE pool_files SET norm_amount = NULL');
        $this->other = TestDatabase::connect($this->database);
        pg_query($this->other, "SET search_path TO {$this->committedSchema}");
    }

    /** The other connection takes the row lock with $save and commits it once the backfill waits for that lock. */
    private function saveWhileTheBackfillWaits(string $save): void
    {
        $other = $this->other;
        self::assertNotFalse(pg_query($other, 'BEGIN'));
        self::assertNotFalse(pg_query($other, $save));
        $GLOBALS['ensurePoolFilesWhileBlocked'] = static function (int $pid) use ($other): void {
            $deadline = microtime(true) + 20;
            do {
                $waiting = pg_fetch_result(pg_query($other, "SELECT count(*) FROM pg_locks WHERE pid = $pid AND NOT granted"), 0, 0);
                if ((int) $waiting > 0) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            if ((int) $waiting === 0) {
                throw new RuntimeException('the backfill update never waited for the row lock');
            }
            pg_query($other, 'COMMIT');
        };
    }

    /** @return array{amount: string, norm_amount: string|null} */
    private function oldRow(): array
    {
        return pg_fetch_assoc($this->sql("SELECT amount, norm_amount FROM pool_files WHERE filename = 'old.pdf'"));
    }

    public function testAPoolSaveCommittedWhileTheBackfillWaitsIsKept(): void
    {
        $this->committedPoolWithPendingRow();
        $this->saveWhileTheBackfillWaits("UPDATE pool_files SET amount = '125,00', norm_amount = 125.000 WHERE filename = 'old.pdf'");

        self::assertSame(['norm_amount_backfill'], ensurePoolFilesSchema('postgresql'));

        self::assertSame(0, $GLOBALS['ensurePoolFilesBlockedRows'], 'the waiting update re-checked the row and wrote nothing');
        self::assertSame(['amount' => '125,00', 'norm_amount' => '125.000'], $this->oldRow(), 'the save is kept');
        self::assertSame(1, $this->backfillFlags(), 'nothing is left to fill');
        self::assertSame([], $GLOBALS['ensurePoolFilesErrors']);
    }

    public function testANewAmountTextSavedWhileTheBackfillWaitsIsFilledByTheNextRun(): void
    {
        $this->committedPoolWithPendingRow();
        $this->saveWhileTheBackfillWaits("UPDATE pool_files SET amount = '125,00' WHERE filename = 'old.pdf'");

        self::assertSame([], ensurePoolFilesSchema('postgresql'), 'the row changed under it: no flag yet');

        self::assertSame(0, $GLOBALS['ensurePoolFilesBlockedRows']);
        self::assertSame(['amount' => '125,00', 'norm_amount' => null], $this->oldRow(), 'no stale 99.000');
        self::assertSame(0, $this->backfillFlags());

        self::assertSame(['norm_amount_backfill'], ensurePoolFilesSchema('postgresql'));
        self::assertSame(['amount' => '125,00', 'norm_amount' => '125.000'], $this->oldRow());
        self::assertSame(1, $this->backfillFlags());
        self::assertSame([], $GLOBALS['ensurePoolFilesErrors']);
    }

    public function testAmountTextsTheQueryHelperWouldAlterAreFilled(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $texts = ['like.pdf' => 'amount like 99,00', 'quote.pdf' => "it's 5,00", 'semicolon.pdf' => '7,00; kr', 'backslash.pdf' => '8,00 \\'];
        foreach ($texts as $file => $amount) {
            $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('$file', '" . pg_escape_string($this->connection, $amount) . "')");
        }
        $expected = [];
        foreach ($texts as $file => $amount) {
            $expected[$file] = ensurePoolFilesBackfillValue($amount);
        }
        self::assertSame('99.000', $expected['like.pdf']);

        self::assertContains('norm_amount_backfill', ensurePoolFilesSchema('postgresql'));

        foreach ($expected as $file => $value) {
            $stored = pg_fetch_result($this->sql("SELECT norm_amount FROM pool_files WHERE filename = '$file'"), 0, 0);
            self::assertSame($value, $stored, $file);
        }
        self::assertSame(1, $this->backfillFlags());
        foreach ($GLOBALS['ensurePoolFilesWrites'] as $sql) {
            foreach ($texts as $amount) {
                self::assertStringNotContainsString($amount, $sql, 'the amount text is never put into the SQL');
            }
        }
        self::assertSame([], $GLOBALS['ensurePoolFilesErrors']);
        $GLOBALS['ensurePoolFilesWrites'] = [];
        self::assertSame([], ensurePoolFilesSchema('postgresql'));
        self::assertSame([], $GLOBALS['ensurePoolFilesWrites']);
    }

    public function testAPendingReadCancelledByALockTimeoutLeavesNoFlag(): void
    {
        $this->committedPoolWithPendingRow();
        // The other session holds pool_files; this one gives up after 200 ms, so the pending-row reads really fail.
        self::assertNotFalse(pg_query($this->other, 'BEGIN'));
        self::assertNotFalse(pg_query($this->other, 'LOCK TABLE pool_files IN ACCESS EXCLUSIVE MODE'));
        $this->sql("SET lock_timeout = '200ms'");

        self::assertSame([], ensurePoolFilesSchema('postgresql'));

        self::assertNotSame([], $GLOBALS['ensurePoolFilesErrors'], 'the read really failed');
        self::assertStringContainsString('lock timeout', implode(' ', $GLOBALS['ensurePoolFilesErrors']));
        self::assertSame(0, $this->backfillFlags(), 'a failed read is not «nothing left»');
        self::assertStringContainsString('stopped, the next login tries again', (string) file_get_contents($this->log), 'the stop is logged with its reason');
        self::assertSame('0', pg_fetch_result($this->sql("SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND pid = pg_backend_pid()"), 0, 0), 'the advisory lock was released');

        pg_query($this->other, 'ROLLBACK');
        $this->sql('RESET lock_timeout');
        $GLOBALS['ensurePoolFilesErrors'] = [];
        self::assertSame(['norm_amount_backfill'], ensurePoolFilesSchema('postgresql'));
        self::assertSame(['amount' => '99,00', 'norm_amount' => '99.000'], $this->oldRow());
        self::assertSame(1, $this->backfillFlags());
        self::assertSame([], $GLOBALS['ensurePoolFilesErrors']);
    }

    public function testAnIndexCreationThatTimesOutRollsTheFilenameRepairBack(): void
    {
        // A committed schema (the other session must see it), every later column present, two rows with one filename.
        $this->sql('ROLLBACK');
        $this->committedSchema = 'pool_uidx_' . bin2hex(random_bytes(8));
        $this->sql("CREATE SCHEMA {$this->committedSchema}");
        $this->sql("SET search_path TO {$this->committedSchema}");
        $this->sql('CREATE TABLE settings (id serial PRIMARY KEY, var_name text, var_grp text, var_value text, var_description text, user_id integer, group_id integer, pos_id integer)');
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('ALTER TABLE pool_files ADD COLUMN norm_amount numeric(15,3), ADD COLUMN manually_edited boolean NOT NULL DEFAULT false,
            ADD COLUMN source_sha256 char(64), ADD COLUMN vendor_name text, ADD COLUMN vendor_cvr varchar(20), ADD COLUMN vendor_iban varchar(40),
            ADD COLUMN vendor_konto_id integer, ADD COLUMN vendor_score numeric(4,3), ADD COLUMN vendor_match varchar(10)');
        $this->sql('CREATE INDEX idx_pool_files_norm_amount ON pool_files (norm_amount)');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00'), ('a.pdf', '2,00')");
        // DELETE may run (ROW EXCLUSIVE does not conflict with it), CREATE INDEX (SHARE) may not.
        $this->other = TestDatabase::connect($this->database);
        pg_query($this->other, "SET search_path TO {$this->committedSchema}");
        self::assertNotFalse(pg_query($this->other, 'BEGIN'));
        self::assertNotFalse(pg_query($this->other, 'LOCK TABLE pool_files IN ROW EXCLUSIVE MODE'));
        $this->sql("SET lock_timeout = '200ms'");
        // No per-statement savepoints: like production, a failed statement aborts the repair's transaction.
        $GLOBALS['ensurePoolFilesRaw'] = true;

        $done = ensurePoolFilesSchema('postgresql');

        self::assertNotContains('unique_filename', $done, 'nothing reported');
        self::assertStringContainsString('lock timeout', implode(' ', $GLOBALS['ensurePoolFilesErrors']), 'the CREATE INDEX really failed');
        self::assertSame(['1,00', '2,00'], pg_fetch_all_columns($this->sql("SELECT amount FROM pool_files WHERE filename = 'a.pdf' ORDER BY id")), 'the delete was rolled back');
        self::assertSame(0, $this->backfillFlags(), 'the run stopped');
        self::assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status($this->connection), 'no transaction left open');
        self::assertSame('0', pg_fetch_result($this->sql("SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND pid = pg_backend_pid()"), 0, 0));

        pg_query($this->other, 'ROLLBACK');
        $this->sql('RESET lock_timeout');
        $GLOBALS['ensurePoolFilesErrors'] = [];
        $done = ensurePoolFilesSchema('postgresql');
        self::assertContains('unique_filename', $done);
        self::assertSame(['2,00'], pg_fetch_all_columns($this->sql("SELECT amount FROM pool_files WHERE filename = 'a.pdf'")), 'the newest row is kept');
        $this->poolInsert('a.pdf', '3,00', str_repeat('f', 64));
        $this->poolInsert('semantic-invoice.xml', '125,00', str_repeat('f', 64));
        $this->poolInsert('semantic-invoice.xml', '125,00', str_repeat('f', 64));
        self::assertSame('1', pg_fetch_result($this->sql("SELECT count(*) FROM pool_files WHERE filename = 'semantic-invoice.xml'"), 0, 0));
        self::assertSame([], $GLOBALS['ensurePoolFilesErrors']);
        self::assertSame([], ensurePoolFilesSchema('postgresql'));
    }

    public function testATimedOutReadInsideACallersTransactionLeavesItUsable(): void
    {
        $this->committedPoolWithPendingRow();
        $this->other = $this->other ?? TestDatabase::connect($this->database);
        self::assertNotFalse(pg_query($this->other, 'BEGIN'));
        self::assertNotFalse(pg_query($this->other, 'LOCK TABLE pool_files IN ACCESS EXCLUSIVE MODE'));
        $this->sql("SET lock_timeout = '200ms'");
        $GLOBALS['ensurePoolFilesRaw'] = true; // like production: no per-statement savepoints
        $this->sql('BEGIN'); // the caller's transaction
        $this->sql("INSERT INTO settings (var_name, var_grp, var_value) VALUES ('caller', 'test', 'kept')");

        self::assertSame([], ensurePoolFilesSchema('postgresql'));

        self::assertStringContainsString('lock timeout', implode(' ', $GLOBALS['ensurePoolFilesErrors']), 'the read really failed');
        self::assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->connection), 'the caller\'s transaction is usable, not aborted');
        self::assertSame('kept', pg_fetch_result($this->sql("SELECT var_value FROM settings WHERE var_name = 'caller'"), 0, 0), 'the caller\'s own work is intact');
        self::assertSame(0, $this->backfillFlags());
        $this->sql('ROLLBACK');
        self::assertSame('0', pg_fetch_result($this->sql("SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND pid = pg_backend_pid()"), 0, 0), 'no advisory lock after the caller ends');

        pg_query($this->other, 'ROLLBACK');
        $this->sql('RESET lock_timeout');
        $GLOBALS['ensurePoolFilesErrors'] = [];
        $this->sql('BEGIN');
        self::assertSame(['norm_amount_backfill'], ensurePoolFilesSchema('postgresql'));
        $this->sql('COMMIT');
        self::assertSame(['amount' => '99,00', 'norm_amount' => '99.000'], $this->oldRow());
        self::assertSame(1, $this->backfillFlags());
        self::assertSame('0', pg_fetch_result($this->sql("SELECT count(*) FROM pg_locks WHERE locktype = 'advisory' AND pid = pg_backend_pid()"), 0, 0));
        self::assertSame([], $GLOBALS['ensurePoolFilesErrors']);
    }

    public function testAColumnThatCouldNotBeAddedIsNotReported(): void
    {
        $this->sql('ROLLBACK');
        $this->committedSchema = 'pool_cols_' . bin2hex(random_bytes(8));
        $this->sql("CREATE SCHEMA {$this->committedSchema}");
        $this->sql("SET search_path TO {$this->committedSchema}");
        $this->sql('CREATE TABLE settings (id serial PRIMARY KEY, var_name text, var_grp text, var_value text, var_description text, user_id integer, group_id integer, pos_id integer)');
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('old.pdf', '99,00')");
        // A reader's open transaction: ALTER TABLE (ACCESS EXCLUSIVE) must wait for it.
        $this->other = TestDatabase::connect($this->database);
        pg_query($this->other, "SET search_path TO {$this->committedSchema}");
        pg_query($this->other, 'BEGIN');
        pg_query($this->other, 'SELECT count(*) FROM pool_files');
        $this->sql("SET lock_timeout = '200ms'");
        $GLOBALS['ensurePoolFilesRaw'] = true; // like production

        $done = ensurePoolFilesSchema('postgresql');

        self::assertSame([], $done, 'no column is reported that is not there');
        self::assertStringContainsString('lock timeout', implode(' ', $GLOBALS['ensurePoolFilesErrors']));
        self::assertSame(0, $this->backfillFlags());
        self::assertSame(0, pg_num_rows($this->sql("SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'pool_files' AND column_name = 'norm_amount'")));

        pg_query($this->other, 'ROLLBACK');
        $this->sql('RESET lock_timeout');
        $GLOBALS['ensurePoolFilesErrors'] = [];
        $done = ensurePoolFilesSchema('postgresql');
        self::assertContains('norm_amount', $done);
        self::assertContains('norm_amount_backfill', $done);
        self::assertSame(['amount' => '99,00', 'norm_amount' => '99.000'], $this->oldRow());
    }

    /** A trigger that rejects only the backfill flag's INSERT into settings. */
    private function rejectTheFlagInsert(): void
    {
        $this->sql("CREATE FUNCTION reject_pool_flag() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN IF NEW.var_name = ''pool_files_norm_amount_backfilled'' THEN RAISE EXCEPTION ''forced flag failure''; END IF; RETURN NEW; END'");
        $this->sql('CREATE TRIGGER reject_pool_flag BEFORE INSERT ON settings FOR EACH ROW EXECUTE FUNCTION reject_pool_flag()');
    }

    public function testARejectedFlagInsertIsNotReportedInAutocommit(): void
    {
        $this->committedPoolWithPendingRow();
        $this->rejectTheFlagInsert();
        $GLOBALS['ensurePoolFilesRaw'] = true; // like production

        self::assertNotContains('norm_amount_backfill', ensurePoolFilesSchema('postgresql'));
        self::assertStringContainsString('forced flag failure', implode(' ', $GLOBALS['ensurePoolFilesErrors']));
        self::assertSame(0, $this->backfillFlags());
        self::assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status($this->connection));

        $this->sql('DROP TRIGGER reject_pool_flag ON settings');
        $GLOBALS['ensurePoolFilesErrors'] = [];
        self::assertSame(['norm_amount_backfill'], ensurePoolFilesSchema('postgresql'));
        self::assertSame(1, $this->backfillFlags());
        self::assertSame(['amount' => '99,00', 'norm_amount' => '99.000'], $this->oldRow());
    }

    public function testARejectedFlagInsertInsideACallersTransactionLeavesItCommittable(): void
    {
        $this->committedPoolWithPendingRow();
        $this->rejectTheFlagInsert();
        $GLOBALS['ensurePoolFilesRaw'] = true; // like production
        $this->sql('BEGIN'); // the caller's transaction
        $this->sql("INSERT INTO settings (var_name, var_grp, var_value) VALUES ('caller', 'test', 'kept')");

        self::assertSame([], ensurePoolFilesSchema('postgresql'));

        self::assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->connection), 'not aborted');
        $commit = pg_query($this->connection, 'COMMIT');
        self::assertSame('COMMIT', pg_result_status($commit, PGSQL_STATUS_STRING), 'the caller commits, not a silent rollback');
        self::assertSame('kept', pg_fetch_result($this->sql("SELECT var_value FROM settings WHERE var_name = 'caller'"), 0, 0));
        self::assertSame(0, $this->backfillFlags());
        self::assertNull($this->oldRow()['norm_amount'], 'the backfill was rolled back with the savepoint');

        $this->sql('DROP TRIGGER reject_pool_flag ON settings');
        $GLOBALS['ensurePoolFilesErrors'] = [];
        self::assertSame(['norm_amount_backfill'], ensurePoolFilesSchema('postgresql'));
        self::assertSame(1, $this->backfillFlags());
        self::assertSame('99.000', $this->oldRow()['norm_amount']);
    }

    public function testAUniqueIndexOnPartOfTheFilenameIsKeptAndNamedForReview(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('CREATE UNIQUE INDEX existing_filename ON pool_files (left(filename, 7))');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('invoice-a.pdf', '1,00')");

        self::assertContains('unique_filename', ensurePoolFilesSchema('postgresql'), 'the full index is added');

        // PostgreSQL never drops an expression index: whether it is an obsolete prefix is left to a person.
        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'existing_filename'")), 'kept');
        self::assertStringContainsString('existing_filename', (string) file_get_contents($this->log), 'named in the log for review');
        $this->poolInsert('invoice-a.pdf', '1,00', str_repeat('a', 64));
        self::assertSame('1', pg_fetch_result($this->sql("SELECT count(*) FROM pool_files WHERE filename = 'invoice-a.pdf'"), 0, 0), 'the full index serves ON CONFLICT');
        self::assertSame([], ensurePoolFilesSchema('postgresql'));
    }

    /** A row the subject rule refuses: a new filename with an existing subject in other case. */
    private function subjectRuleRefuses(string $savepoint): bool
    {
        $result = @pg_query($this->connection, "SAVEPOINT $savepoint; INSERT INTO pool_files (filename, subject) VALUES ('other-$savepoint.pdf', 'FAKTURA 1')");
        if ($result === false) {
            $this->sql("ROLLBACK TO SAVEPOINT $savepoint");
            return true;
        }
        return false;
    }

    public function testAnUnrelatedUniqueIndexWithFilenameInItsPredicateIsKept(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('CREATE UNIQUE INDEX pool_files_filename_uidx ON pool_files (filename)');
        $this->sql('CREATE UNIQUE INDEX pool_files_subject_key ON pool_files (lower(subject)) WHERE filename IS NOT NULL');
        $this->sql("INSERT INTO pool_files (filename, subject, amount) VALUES ('a.pdf', 'Faktura 1', '1,00')");
        self::assertTrue($this->subjectRuleRefuses('before'));

        self::assertNotContains('unique_filename', ensurePoolFilesSchema('postgresql'), 'nothing to repair: the full index is there');

        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'pool_files_subject_key'")), 'kept');
        self::assertTrue($this->subjectRuleRefuses('after'), 'its rule still holds');
    }

    public function testAnUnrelatedUniqueIndexIsKeptWhenTheRepairRuns(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('CREATE UNIQUE INDEX pool_files_subject_key ON pool_files (lower(subject)) WHERE filename IS NOT NULL');
        $this->sql("INSERT INTO pool_files (filename, subject, amount) VALUES ('a.pdf', 'Faktura 1', '1,00')");

        self::assertContains('unique_filename', ensurePoolFilesSchema('postgresql'));

        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'pool_files_subject_key'")), 'kept');
        self::assertTrue($this->subjectRuleRefuses('after_repair'), 'its rule still holds');
        $this->poolInsert('b.pdf', '2,00', str_repeat('c', 64));
        $this->poolInsert('b.pdf', '2,00', str_repeat('c', 64));
        self::assertSame('1', pg_fetch_result($this->sql("SELECT count(*) FROM pool_files WHERE filename = 'b.pdf'"), 0, 0));
    }

    public function testAUniqueIndexOnALiteralThatSaysFilenameIsKept(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('CREATE UNIQUE INDEX pool_files_filename_uidx ON pool_files (filename)');
        $this->sql("CREATE UNIQUE INDEX pool_files_singleton_key ON pool_files (lower('filename'::text))");
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00')");

        self::assertNotContains('unique_filename', ensurePoolFilesSchema('postgresql'));

        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'pool_files_singleton_key'")), 'kept');
        $refused = @pg_query($this->connection, "SAVEPOINT singleton; INSERT INTO pool_files (filename) VALUES ('b.pdf')");
        self::assertFalse($refused, 'its rule (one row) still holds');
        $this->sql('ROLLBACK TO SAVEPOINT singleton');
    }

    public function testAnOddColumnNameDoesNotStopTheRepair(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('ALTER TABLE pool_files ADD COLUMN "scratch(" text');
        $this->sql('CREATE UNIQUE INDEX existing_filename ON pool_files (left(filename, 7))');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('invoice-a.pdf', '1,00')");

        self::assertContains('unique_filename', ensurePoolFilesSchema('postgresql'));

        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'existing_filename'")), 'kept');
        self::assertSame([], $GLOBALS['ensurePoolFilesErrors'], 'no PostgreSQL error');
    }

    public function testALookupIndexOnReverseFilenameIsKept(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('CREATE UNIQUE INDEX pool_files_filename_uidx ON pool_files (filename)');
        $this->sql('CREATE UNIQUE INDEX pool_files_reverse_key ON pool_files (reverse(filename))');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('invoice-a.pdf', '1,00'), ('invoice-b.pdf', '1,00')");

        self::assertNotContains('unique_filename', ensurePoolFilesSchema('postgresql'));

        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'pool_files_reverse_key'")), 'the lookup index is kept');
    }

    public function testAUniquenessRuleOverFilenameIsKeptWhenTheRepairRuns(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql("CREATE UNIQUE INDEX pool_files_one_invoice ON pool_files ((CASE WHEN filename LIKE 'invoice-%' THEN 1 ELSE NULL END))");
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('invoice-a.pdf', '1,00')");

        self::assertContains('unique_filename', ensurePoolFilesSchema('postgresql'), 'the full index is added');

        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'pool_files_one_invoice'")), 'the rule is kept');
        $refused = @pg_query($this->connection, "SAVEPOINT rule; INSERT INTO pool_files (filename) VALUES ('invoice-b.pdf')");
        self::assertFalse($refused, 'and still refuses a second invoice');
        $this->sql('ROLLBACK TO SAVEPOINT rule');
        self::assertStringContainsString('pool_files_one_invoice', (string) file_get_contents($this->log), 'named in the log for review');
    }

    public function testIndexesOnATenantSubstrAndTheBuiltInAreBothKept(): void
    {
        $schema = (string) pg_fetch_result($this->sql('SELECT current_schema()'), 0, 0);
        $this->sql("SET LOCAL search_path TO $schema, pg_catalog");
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql("CREATE FUNCTION substr(text, integer, integer) RETURNS text IMMUTABLE LANGUAGE sql AS 'SELECT $1'");
        $this->sql('CREATE UNIQUE INDEX pool_files_tenant_key ON pool_files (substr(filename, 1, 7))');
        $this->sql('CREATE UNIQUE INDEX pool_files_prefix_key ON pool_files (pg_catalog.substr(filename, 1, 7))');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('invoice-a.pdf', '1,00')");

        self::assertContains('unique_filename', ensurePoolFilesSchema('postgresql'));

        $indexes = pg_fetch_all_columns($this->sql("SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'pool_files' ORDER BY 1"));
        self::assertContains('pool_files_tenant_key', $indexes);
        self::assertContains('pool_files_prefix_key', $indexes);
        self::assertStringContainsString('pool_files_prefix_key', (string) file_get_contents($this->log), 'the built-in prefix is named for review');
        self::assertSame([], ensurePoolFilesSchema('postgresql'));
    }

    public function testAFullWidthLeftIndexIsKept(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES); // filename varchar(255), as in production
        $this->sql('CREATE UNIQUE INDEX pool_files_filename_uidx ON pool_files (filename)');
        $this->sql('CREATE UNIQUE INDEX pool_files_left_lookup ON pool_files (left(filename, 255))');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('invoice-a.pdf', '1,00'), ('invoice-b.pdf', '1,00')");

        self::assertNotContains('unique_filename', ensurePoolFilesSchema('postgresql'));

        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'pool_files_left_lookup'")), 'kept');
    }

    public function testExpressionIndexesAreKeptWhenTheRepairRuns(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('CREATE UNIQUE INDEX pool_files_left_lookup ON pool_files (left(filename, 255))');
        $this->sql('CREATE UNIQUE INDEX pool_files_short_prefix ON pool_files (left(filename, 7))');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('invoice-a.pdf', '1,00')");

        self::assertContains('unique_filename', ensurePoolFilesSchema('postgresql'));

        $indexes = pg_fetch_all_columns($this->sql("SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'pool_files'"));
        self::assertContains('pool_files_left_lookup', $indexes);
        self::assertContains('pool_files_short_prefix', $indexes);
    }

    public function testAFullWidthLeftIndexOnADomainColumnIsKept(): void
    {
        $this->sql('CREATE DOMAIN pool_filename AS varchar(255)');
        $this->sql(str_replace('filename varchar(255)', 'filename pool_filename', self::OLD_OPRET_POOL_FILES));
        $this->sql('CREATE UNIQUE INDEX pool_files_filename_uidx ON pool_files (filename)');
        $this->sql('CREATE UNIQUE INDEX pool_files_left_lookup ON pool_files (left(filename, 255))');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('invoice-a.pdf', '1,00'), ('invoice-b.pdf', '1,00')");

        ensurePoolFilesSchema('postgresql');

        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'pool_files_left_lookup'")), 'kept');
    }

    public function testAZeroLengthLeftRuleIsKept(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('CREATE UNIQUE INDEX pool_files_filename_uidx ON pool_files (filename)');
        $this->sql('CREATE UNIQUE INDEX pool_files_one_row ON pool_files (left(filename, 0))');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('a.pdf', '1,00')");

        ensurePoolFilesSchema('postgresql');

        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'pool_files_one_row'")), 'kept');
        $refused = @pg_query($this->connection, "SAVEPOINT one_row; INSERT INTO pool_files (filename) VALUES ('b.pdf')");
        self::assertFalse($refused, 'its rule still holds');
        $this->sql('ROLLBACK TO SAVEPOINT one_row');
    }

    public function testALookupOnACheckLimitedTextColumnIsKept(): void
    {
        $this->sql(str_replace('filename varchar(255)', 'filename text CHECK (char_length(filename) <= 255)', self::OLD_OPRET_POOL_FILES));
        $this->sql('CREATE UNIQUE INDEX pool_files_filename_uidx ON pool_files (filename)');
        $this->sql('CREATE UNIQUE INDEX pool_files_left_lookup ON pool_files (left(filename, 255))');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('invoice-a.pdf', '1,00'), ('invoice-b.pdf', '1,00')");

        ensurePoolFilesSchema('postgresql');

        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'pool_files_left_lookup'")), 'kept');
    }

    public function testALookupOnACheckLimitedDomainIsKept(): void
    {
        $this->sql('CREATE DOMAIN pool_filename_text AS text CHECK (char_length(VALUE) <= 255)');
        $this->sql(str_replace('filename varchar(255)', 'filename pool_filename_text', self::OLD_OPRET_POOL_FILES));
        $this->sql('CREATE UNIQUE INDEX pool_files_left_lookup ON pool_files (left(filename, 255))');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('invoice-a.pdf', '1,00')");

        self::assertContains('unique_filename', ensurePoolFilesSchema('postgresql'));

        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'pool_files_left_lookup'")), 'kept');
        $this->poolInsert('invoice-b.pdf', '1,00', str_repeat('f', 64));
        self::assertSame(['invoice-a.pdf', 'invoice-b.pdf'], pg_fetch_all_columns($this->sql('SELECT filename FROM pool_files ORDER BY filename')));
    }

    public function testAUniqueFilenameKeyWithAnIncludeColumnCounts(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('CREATE UNIQUE INDEX pool_files_filename_uidx ON pool_files (filename) INCLUDE (id)');
        $this->sql('CREATE INDEX pool_files_filename_full_uidx ON pool_files (subject)'); // the fallback name, taken by something else
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('old.pdf', '99,00')");

        $done = ensurePoolFilesSchema('postgresql');

        self::assertNotContains('unique_filename', $done, 'the covering key is the filename key');
        self::assertContains('norm_amount_backfill', $done);
        self::assertSame('99.000', pg_fetch_result($this->sql("SELECT norm_amount FROM pool_files WHERE filename = 'old.pdf'"), 0, 0));
        self::assertSame(1, $this->backfillFlags());
        self::assertSame(['pool_files_filename_full_uidx', 'pool_files_filename_uidx'], pg_fetch_all_columns($this->sql(
            "SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'pool_files' AND indexname LIKE 'pool\\_files\\_filename%' ORDER BY 1")), 'both kept, nothing added');
        $this->poolInsert('old.pdf', '99,00', str_repeat('a', 64));
        self::assertSame('1', pg_fetch_result($this->sql("SELECT count(*) FROM pool_files WHERE filename = 'old.pdf'"), 0, 0), 'ON CONFLICT (filename) uses the covering key');
        self::assertSame([], ensurePoolFilesSchema('postgresql'));
    }

    public function testBothUsualIndexNamesTakenGetsTheNextFreeName(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('CREATE INDEX pool_files_filename_uidx ON pool_files (subject)');
        $this->sql('CREATE INDEX pool_files_filename_full_uidx ON pool_files (account)');
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('old.pdf', '99,00')");

        $done = ensurePoolFilesSchema('postgresql');

        self::assertContains('unique_filename', $done);
        self::assertContains('norm_amount_backfill', $done);
        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'pool_files_filename_uidx2' AND indexdef ILIKE '%UNIQUE%(filename)%'")));
        self::assertSame(2, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname IN ('pool_files_filename_uidx', 'pool_files_filename_full_uidx')")), 'the others kept');
        self::assertSame('99.000', pg_fetch_result($this->sql("SELECT norm_amount FROM pool_files WHERE filename = 'old.pdf'"), 0, 0));
    }

    public function testFourTakenNamesStillLeaveAFreeOne(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('CREATE INDEX pool_files_filename_uidx ON pool_files (subject)');
        $this->sql('CREATE INDEX pool_files_filename_full_uidx ON pool_files (account)');
        $this->sql('CREATE INDEX pool_files_filename_uidx2 ON pool_files (file_date)');
        $this->sql('CREATE TABLE pool_files_filename_uidx3 (id integer PRIMARY KEY, note text)');
        $this->sql("INSERT INTO pool_files_filename_uidx3 VALUES (1, 'sentinel')");
        $this->sql("INSERT INTO pool_files (filename, amount) VALUES ('old.pdf', '99,00')");

        $done = ensurePoolFilesSchema('postgresql');

        self::assertContains('unique_filename', $done);
        self::assertContains('norm_amount_backfill', $done);
        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = 'pool_files_filename_uidx4' AND indexdef ILIKE '%UNIQUE%(filename)%'")));
        self::assertSame(3, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname IN ('pool_files_filename_uidx', 'pool_files_filename_full_uidx', 'pool_files_filename_uidx2')")), 'the other indexes kept');
        self::assertSame('sentinel', pg_fetch_result($this->sql('SELECT note FROM pool_files_filename_uidx3 WHERE id = 1'), 0, 0), 'the table and its row kept');
        $this->poolInsert('old.pdf', '99,00', str_repeat('a', 64));
        self::assertSame('1', pg_fetch_result($this->sql("SELECT count(*) FROM pool_files WHERE filename = 'old.pdf'"), 0, 0), 'ON CONFLICT (filename) works');
        self::assertSame('99.000', pg_fetch_result($this->sql("SELECT norm_amount FROM pool_files WHERE filename = 'old.pdf'"), 0, 0));
        self::assertSame(1, $this->backfillFlags());
    }

    public function testDuplicatesReferencedByACascadingForeignKeyAreNotDeleted(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('CREATE TABLE pool_linked_document (id integer PRIMARY KEY, pool_id integer REFERENCES pool_files (id) ON DELETE CASCADE)');
        $this->sql("INSERT INTO pool_files (id, filename, amount) VALUES (1, 'a.pdf', '1,00'), (2, 'a.pdf', '99,00')");
        $this->sql('INSERT INTO pool_linked_document VALUES (10, 1), (11, 2)');

        $done = ensurePoolFilesSchema('postgresql');

        self::assertNotContains('unique_filename', $done);
        self::assertSame('2', pg_fetch_result($this->sql("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 0, 0), 'both duplicates kept');
        self::assertSame(['10', '11'], pg_fetch_all_columns($this->sql('SELECT id FROM pool_linked_document ORDER BY id')), 'no linked row deleted');
        self::assertSame(0, $this->backfillFlags());
        self::assertStringContainsString('pool_linked_document', (string) file_get_contents($this->log), 'the reference is named');
    }

    public function testAReferencedTableWithoutDuplicatesIsRepaired(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('CREATE TABLE pool_linked_document (id integer PRIMARY KEY, pool_id integer REFERENCES pool_files (id) ON DELETE CASCADE)');
        $this->sql("INSERT INTO pool_files (id, filename, amount) VALUES (1, 'a.pdf', '1,00'), (2, 'b.pdf', '99,00')");
        $this->sql('INSERT INTO pool_linked_document VALUES (10, 1), (11, 2)');

        $done = ensurePoolFilesSchema('postgresql');

        self::assertContains('unique_filename', $done);
        self::assertContains('norm_amount_backfill', $done);
        self::assertSame(['10', '11'], pg_fetch_all_columns($this->sql('SELECT id FROM pool_linked_document ORDER BY id')));
    }

    private function linkedDocumentsWithoutAForeignKey(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('CREATE TABLE pool_linked_document (id integer PRIMARY KEY, pool_id integer)');
        $this->sql("INSERT INTO pool_files (id, filename, amount) VALUES (1, 'a.pdf', '1,00'), (2, 'a.pdf', '99,00')");
        $this->sql('INSERT INTO pool_linked_document VALUES (10, 1), (11, 2)');
    }

    private function assertNothingDeleted(string $named): void
    {
        self::assertSame('2', pg_fetch_result($this->sql("SELECT count(*) FROM pool_files WHERE filename = 'a.pdf'"), 0, 0), 'both duplicates kept');
        self::assertSame(['10', '11'], pg_fetch_all_columns($this->sql('SELECT id FROM pool_linked_document ORDER BY id')), 'no linked row deleted');
        self::assertSame(0, $this->backfillFlags());
        self::assertStringContainsString($named, (string) file_get_contents($this->log));
    }

    public function testADeleteTriggerStopsTheCollapse(): void
    {
        $this->linkedDocumentsWithoutAForeignKey();
        $this->sql("CREATE FUNCTION pool_files_unlink() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN DELETE FROM pool_linked_document WHERE pool_id = OLD.id; RETURN OLD; END'");
        $this->sql('CREATE TRIGGER pool_files_unlink AFTER DELETE ON pool_files FOR EACH ROW EXECUTE FUNCTION pool_files_unlink()');

        self::assertNotContains('unique_filename', ensurePoolFilesSchema('postgresql'));

        $this->assertNothingDeleted('trigger pool_files_unlink');
    }

    public function testADeleteRuleStopsTheCollapse(): void
    {
        $this->linkedDocumentsWithoutAForeignKey();
        $this->sql('CREATE RULE pool_files_unlink_rule AS ON DELETE TO pool_files DO ALSO DELETE FROM pool_linked_document WHERE pool_id = OLD.id');

        self::assertNotContains('unique_filename', ensurePoolFilesSchema('postgresql'));

        $this->assertNothingDeleted('rule pool_files_unlink_rule');
    }

    public function testWithoutATriggerTheCollapseRuns(): void
    {
        $this->linkedDocumentsWithoutAForeignKey();

        self::assertContains('unique_filename', ensurePoolFilesSchema('postgresql'));
        self::assertSame('99,00', pg_fetch_result($this->sql("SELECT amount FROM pool_files WHERE filename = 'a.pdf'"), 0, 0));
        self::assertSame(['10', '11'], pg_fetch_all_columns($this->sql('SELECT id FROM pool_linked_document ORDER BY id')), 'nothing else touched');
    }

    private function anInheritingTableWithADeleteTrigger(bool $autocommit): void
    {
        if ($autocommit) {
            $this->autocommitSchema();
        }
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('CREATE TABLE pool_linked_document (id integer PRIMARY KEY, pool_id integer)');
        $this->sql('CREATE TABLE pool_files_archive () INHERITS (pool_files)');
        $this->sql("INSERT INTO pool_files_archive (id, filename, amount) VALUES (1, 'a.pdf', '1,00')");
        $this->sql("INSERT INTO pool_files (id, filename, amount) VALUES (2, 'a.pdf', '99,00')");
        $this->sql('INSERT INTO pool_linked_document VALUES (10, 1), (11, 2)');
        $this->sql("CREATE FUNCTION pool_files_archive_unlink() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN DELETE FROM pool_linked_document WHERE pool_id = OLD.id; RETURN OLD; END'");
        $this->sql('CREATE TRIGGER pool_files_archive_unlink AFTER DELETE ON pool_files_archive FOR EACH ROW EXECUTE FUNCTION pool_files_archive_unlink()');
        $before = $this->physicalColumns();
        self::assertCount(11, $before['pool_files'], 'a partly migrated table');

        self::assertSame([], ensurePoolFilesSchema('postgresql'), 'nothing done');

        self::assertSame($before, $this->physicalColumns(), 'no column added to pool_files or pool_files_archive');

        self::assertSame(['1', '2'], pg_fetch_all_columns($this->sql('SELECT id FROM pool_files ORDER BY id')), 'both duplicates kept');
        self::assertSame(['10', '11'], pg_fetch_all_columns($this->sql('SELECT id FROM pool_linked_document ORDER BY id')), 'no linked row deleted');
        self::assertSame(0, $this->backfillFlags());
        self::assertSame(0, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'pool_files' AND indexdef ILIKE '%UNIQUE%(filename)%'")), 'no unique index that would only cover the parent');
        self::assertStringContainsString('pool_files_archive', (string) file_get_contents($this->log), 'the inheriting table is named');
        self::assertSame($autocommit ? PGSQL_TRANSACTION_IDLE : PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->connection));
    }

    public function testAnInheritingTableWithADeleteTriggerChangesNothingInAutocommit(): void
    {
        $this->anInheritingTableWithADeleteTrigger(true);
    }

    public function testAnInheritingTableWithADeleteTriggerChangesNothingInACallersTransaction(): void
    {
        $this->anInheritingTableWithADeleteTrigger(false);
    }

    public function testAnInheritingTableIsNotCoveredByAUniqueIndexOnPoolFiles(): void
    {
        $this->sql(self::OLD_OPRET_POOL_FILES);
        $this->sql('CREATE UNIQUE INDEX pool_files_filename_uidx ON pool_files (filename)');
        $this->sql('CREATE TABLE pool_files_archive () INHERITS (pool_files)');
        $this->sql("INSERT INTO pool_files_archive (id, filename, amount) VALUES (1, 'a.pdf', '1,00')");
        $this->sql("INSERT INTO pool_files (id, filename, amount) VALUES (2, 'a.pdf', '99,00')");

        self::assertFalse(ensurePoolFilesSchemaState('postgresql')['uniqueFilename'], 'the index on pool_files does not make the filenames it reads unique');
        self::assertSame(['pool_files_archive'], ensurePoolFilesSchemaState('postgresql')['inheritingTables']);
        self::assertNotContains('unique_filename', ensurePoolFilesSchema('postgresql'));

        self::assertSame(['1', '2'], pg_fetch_all_columns($this->sql('SELECT id FROM pool_files ORDER BY id')), 'nothing deleted');
        self::assertSame(0, $this->backfillFlags());
        self::assertStringContainsString('tables inherit from pool_files (pool_files_archive)', (string) file_get_contents($this->log));
    }

    /** @return array<string, list<string>> The columns of pool_files and pool_files_archive as the catalogue has them. */
    private function physicalColumns(): array
    {
        $columns = [];
        foreach (['pool_files', 'pool_files_archive'] as $table) {
            $columns[$table] = pg_fetch_all_columns($this->sql("SELECT attname FROM pg_attribute WHERE attrelid = '$table'::regclass AND attnum > 0 AND NOT attisdropped ORDER BY attnum"));
        }
        return $columns;
    }

    private function aTemporaryPoolFiles(bool $autocommit): void
    {
        if ($autocommit) {
            $this->autocommitSchema();
        }
        // Every column and both indexes; one row still waits for its norm_amount, and no flag.
        $this->sql(self::OLD_OPRET_POOL_FILES);
        self::assertContains('norm_amount_backfill', ensurePoolFilesSchema('postgresql'));
        $this->sql("DELETE FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled'");
        $this->sql("INSERT INTO pool_files (id, filename, amount) VALUES (1, 'a.pdf', '125,00')");
        $this->sql('CREATE TEMPORARY TABLE pool_files (LIKE pool_files INCLUDING ALL)');
        $this->sql("INSERT INTO pg_temp.pool_files (id, filename, amount) VALUES (7, 'shadow.pdf', '125,00')");
        self::assertTrue(ensurePoolFilesSchemaState('postgresql')['uniqueFilename'] && ensurePoolFilesSchemaState('postgresql')['normAmountIndex'], 'only the backfill is missing');

        self::assertSame([], ensurePoolFilesSchema('postgresql'), 'nothing done');

        self::assertSame(0, $this->backfillFlags(), 'no flag for the permanent table');
        self::assertNull(pg_fetch_result($this->sql("SELECT norm_amount FROM {$this->schema()}.pool_files WHERE id = 1"), 0, 0));
        self::assertNull(pg_fetch_result($this->sql('SELECT norm_amount FROM pg_temp.pool_files WHERE id = 7'), 0, 0), 'the temporary table untouched');
        self::assertStringContainsString('a temporary pool_files of this session shadows the table in current_schema()', (string) file_get_contents($this->log));

        $this->sql('DROP TABLE pg_temp.pool_files');
        self::assertSame(['norm_amount_backfill'], ensurePoolFilesSchema('postgresql'));
        self::assertSame('125.000', pg_fetch_result($this->sql('SELECT norm_amount FROM pool_files WHERE id = 1'), 0, 0));
        self::assertSame(1, $this->backfillFlags());
        self::assertSame($autocommit ? PGSQL_TRANSACTION_IDLE : PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->connection));
    }

    public function testATemporaryPoolFilesDoesNotStandInForTheRegnskabsTableInAutocommit(): void
    {
        $this->aTemporaryPoolFiles(true);
    }

    public function testATemporaryPoolFilesDoesNotStandInForTheRegnskabsTableInACallersTransaction(): void
    {
        $this->aTemporaryPoolFiles(false);
    }

    /** Leaves setUp()'s transaction: a committed schema of its own (dropped by tearDown()), as at a login. */
    private function autocommitSchema(): void
    {
        $this->sql('ROLLBACK');
        $this->committedSchema = 'pool_auto_' . bin2hex(random_bytes(8));
        $this->sql("CREATE SCHEMA {$this->committedSchema}");
        $this->sql("SET search_path TO {$this->committedSchema}");
        $this->sql('CREATE TABLE settings (id serial PRIMARY KEY, var_name text, var_grp text, var_value text, var_description text, user_id integer, group_id integer, pos_id integer)');
    }

    private function schema(): string
    {
        return pg_fetch_result($this->sql('SELECT quote_ident(current_schema())'), 0, 0);
    }

    private function anUnloggedPoolFiles(bool $autocommit): void
    {
        if ($autocommit) {
            $this->autocommitSchema();
        }
        $this->sql(str_replace('CREATE TABLE pool_files', 'CREATE UNLOGGED TABLE pool_files', self::OLD_OPRET_POOL_FILES));
        $this->sql("INSERT INTO pool_files (id, filename, amount) VALUES (1, 'a.pdf', '1,00'), (2, 'a.pdf', '99,00')");

        $done = ensurePoolFilesSchema('postgresql');

        foreach (['norm_amount', 'unique_filename', 'norm_amount_index', 'norm_amount_backfill'] as $step) {
            self::assertContains($step, $done);
        }
        foreach (array_keys(ensurePoolFilesSchemaColumns()) as $column) {
            self::assertContains($column, $this->columns(), "$column added");
        }
        self::assertSame([['id' => '2', 'norm_amount' => '99.000']], pg_fetch_all($this->sql('SELECT id, norm_amount FROM pool_files')), 'the newest row kept and filled');
        self::assertSame(1, pg_num_rows($this->sql("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'pool_files' AND indexdef ILIKE '%UNIQUE%(filename)%'")));
        self::assertSame(1, $this->backfillFlags());
        self::assertSame('u', pg_fetch_result($this->sql("SELECT relpersistence FROM pg_class WHERE oid = 'pool_files'::regclass"), 0, 0), 'still unlogged');
        self::assertSame($autocommit ? PGSQL_TRANSACTION_IDLE : PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->connection));
        self::assertSame([], ensurePoolFilesSchema('postgresql'), 'the next login has nothing to do');
    }

    public function testAnUnloggedPoolFilesIsRepairedInAutocommit(): void
    {
        $this->anUnloggedPoolFiles(true);
    }

    public function testAnUnloggedPoolFilesIsRepairedInACallersTransaction(): void
    {
        $this->anUnloggedPoolFiles(false);
    }
}

