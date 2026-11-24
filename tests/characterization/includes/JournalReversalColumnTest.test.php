<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * kassekladde.ompost_id, the posted line that a line written by Tilbagefør reverses (addJournalReversalColumn() at the
 * end of includes/betweenUpdates.php; includes/opretfunc.php for a new ledger).
 *
 * Posting converts a reversal at the rate its original was posted at through that column
 * (journalLineInBaseCurrency() in finans/bogfor_includes/postingRules.php). The migration adds the column to a ledger
 * once and keeps its lines, leaves a ledger created with it and a database without kassekladde (the master database)
 * alone, and runs at every login, so a second run adds nothing and raises no error.
 *
 * The function is read from includes/betweenUpdates.php, which runs every migration of the file when included.
 * Runs on a real Postgres, in the owned SQL fixture database (TestDatabase::sqlFixture()), with search_path set to
 * pg_temp, so current_schema() is this session's temporary schema and every table is a TEMP table: nothing else in that
 * database is read or changed. A query that fails fails the test.
 *
 * History:
 * 20261117 CL/ASR Created.
 * 20261124 CL/ASR The tests get their database from TestDatabase (sqlFixture), so CI runs them without SALDI_CHAR_DSN.
 */
#[RunTestsInSeparateProcesses]
final class JournalReversalColumnTest extends TestCase
{
    private const INCLUDES = __DIR__ . '/../../../includes';

    /** @var \PgSql\Connection|null */
    private static $conn = null;

    private static function boot(): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('the pgsql extension is required to run the migration.');
        }
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $conn = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        self::$conn = $conn;
        $GLOBALS['__jrc_conn'] = $conn;
        eval('
            function db_select($q, $where = "", $global = false) {
                $r = @pg_query($GLOBALS["__jrc_conn"], $q);
                if ($r === false) {
                    throw new \RuntimeException(pg_last_error($GLOBALS["__jrc_conn"]) . " in: " . $q);
                }
                return $r;
            }
            function db_modify($q, $where = "", $global = false) { return db_select($q, $where); }
            function db_fetch_array($r) { return pg_fetch_array($r); }
        ');
        require_once self::INCLUDES . '/opdat_func/databaseDialect.php';

        $source = (string) file_get_contents(self::INCLUDES . '/betweenUpdates.php');
        $start = strpos($source, "if (!function_exists('addJournalReversalColumn')) {");
        $end = strpos($source, "\naddJournalReversalColumn(\$db_type);");
        self::assertNotFalse($start, 'includes/betweenUpdates.php defines addJournalReversalColumn()');
        self::assertNotFalse($end, 'includes/betweenUpdates.php calls addJournalReversalColumn($db_type)');
        self::assertGreaterThan($start, $end);
        eval(substr($source, $start, $end - $start));

        // Every table this session creates is a TEMP table, and current_schema() names the temporary schema once it exists.
        self::exec("SET search_path TO pg_temp");
        self::exec("CREATE TEMP TABLE openpost (id serial, PRIMARY KEY (id))");
        self::assertStringStartsWith('pg_temp', (string) pg_fetch_result(pg_query($conn, "SELECT current_schema()"), 0, 0));
    }

    private static function exec(string $sql): void
    {
        if (@pg_query(self::$conn, $sql) === false) {
            throw new \RuntimeException(pg_last_error(self::$conn) . ' in: ' . $sql);
        }
    }

    /**
     * @return array<string, string> kassekladde's columns in the temporary schema, name => data type.
     */
    private static function columns(): array
    {
        $q = pg_query(self::$conn, "SELECT column_name, data_type FROM information_schema.columns
            WHERE table_name = 'kassekladde' AND table_schema = current_schema() ORDER BY ordinal_position");
        $columns = [];
        while ($r = pg_fetch_assoc($q)) {
            $columns[$r['column_name']] = $r['data_type'];
        }
        return $columns;
    }

    /** kassekladde as includes/opretfunc.php creates it, with numeric for $decimal_type. */
    private static function createdTable(): string
    {
        $source = (string) file_get_contents(self::INCLUDES . '/opretfunc.php');
        self::assertSame(1, preg_match('/\$qtxt = "CREATE TABLE kassekladde ";\s*((?:\$qtxt\.= "[^"]*";\s*|\/\/[^\n]*\n\s*)+)/', $source, $m),
            'includes/opretfunc.php creates kassekladde');
        preg_match_all('/^\s*\$qtxt\.= "([^"]*)";/m', $m[1], $parts); // the commented-out lines are not part of it
        return 'CREATE TABLE kassekladde ' . str_replace(['$id_column', '$decimal_type'], ['id SERIAL NOT NULL', 'numeric'], implode('', $parts[1]));
    }

    public function test_a_ledger_from_before_gets_the_column_once(): void
    {
        self::boot();
        // kassekladde as includes/opretfunc.php created it before 20261117, with a posted line.
        self::exec(str_replace(',ompost_id integer', '', self::createdTable()));
        self::assertArrayNotHasKey('ompost_id', self::columns());
        self::exec("INSERT INTO kassekladde (bilag, kladde_id, amount, valuta, valutakurs) VALUES (7, 1, 100, 1, 746)");
        $before = count(self::columns());

        self::assertSame('added', addJournalReversalColumn('postgresql'));
        self::assertSame('integer', self::columns()['ompost_id'] ?? null, 'kassekladde has ompost_id');
        self::assertSame([['bilag' => '7', 'amount' => '100.000', 'ompost_id' => null]],
            pg_fetch_all(pg_query(self::$conn, "SELECT bilag, amount, ompost_id FROM kassekladde")), 'the line is kept and reverses nothing');

        self::assertSame('present', addJournalReversalColumn('postgresql'), 'the next login adds nothing');
        self::assertCount($before + 1, self::columns());
    }

    public function test_a_new_ledger_is_created_with_the_column(): void
    {
        self::boot();
        self::exec(self::createdTable());

        self::assertSame('integer', self::columns()['ompost_id'] ?? null, 'a new ledger has ompost_id');
        self::assertSame('present', addJournalReversalColumn('postgresql'));
    }

    public function test_a_database_without_kassekladde_is_left_alone(): void
    {
        self::boot();

        self::assertSame('no table', addJournalReversalColumn('postgresql'));
        self::assertSame([], self::columns());
    }
}
