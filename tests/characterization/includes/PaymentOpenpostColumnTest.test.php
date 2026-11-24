<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * betalinger.openpost_id, the open post a payment on a payment list pays (includes/opdat_func/paymentOpenpostColumn.php,
 * called at the end of includes/betweenUpdates.php).
 *
 * Afregn kommission and Fra liste link a seller's Afr open post to its payment through that column; before it they put
 * the open post's refnr (the report number every seller of the settlement shares) in ordre_id. The migration adds the
 * column to a ledger once, leaves a ledger created with it (includes/opretfunc.php) and a database without betalinger
 * (the master database) alone, and runs at every login, so a second run adds nothing and raises no error.
 *
 * Runs on a real Postgres with search_path set to pg_temp, so current_schema() is this session's temporary schema and
 * every table is a TEMP table: nothing in the shared SQL fixture database (TestDatabase::sqlFixture()) is read or
 * changed. A query that fails fails the test.
 *
 * History:
 * 20261105 CL/LH Created.
 * 20261124 CL/ASR The tests get their database from TestDatabase (sqlFixture), so CI runs them without SALDI_CHAR_DSN.
 */
#[RunTestsInSeparateProcesses]
final class PaymentOpenpostColumnTest extends TestCase
{
    private const OPRETFUNC = __DIR__ . '/../../../includes/opretfunc.php';

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
        $GLOBALS['__poc_conn'] = $conn;
        eval('
            function db_select($q, $where = "", $global = false) {
                $r = @pg_query($GLOBALS["__poc_conn"], $q);
                if ($r === false) {
                    throw new \RuntimeException(pg_last_error($GLOBALS["__poc_conn"]) . " in: " . $q);
                }
                return $r;
            }
            function db_modify($q, $where = "", $global = false) { return db_select($q, $where); }
            function db_fetch_array($r) { return pg_fetch_array($r); }
        ');
        require_once dirname(__DIR__, 3) . '/includes/opdat_func/paymentOpenpostColumn.php';

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
     * @return array<string, string> betalinger's columns in the temporary schema, name => data type.
     */
    private static function columns(): array
    {
        $q = pg_query(self::$conn, "SELECT column_name, data_type FROM information_schema.columns
            WHERE table_name = 'betalinger' AND table_schema = current_schema() ORDER BY ordinal_position");
        $columns = [];
        while ($r = pg_fetch_assoc($q)) {
            $columns[$r['column_name']] = $r['data_type'];
        }
        return $columns;
    }

    public function test_a_ledger_from_before_gets_the_column_once(): void
    {
        self::boot();
        // betalinger as includes/opretfunc.php created it before 20261105, with a payment on a list.
        self::exec("CREATE TABLE betalinger (id SERIAL NOT NULL, bet_type text, fra_kto text, egen_ref text, til_kto text, modt_navn text,
            belob text, betalingsdato text, valuta text, kort_ref text, kvittering text, ordre_id integer, bilag_id integer, liste_id integer,
            PRIMARY KEY (id))");
        self::exec("INSERT INTO betalinger (egen_ref, belob, ordre_id, bilag_id, liste_id) VALUES ('Afr: 1000 01112026 - 08112026', '105,00', 0, 0, 1)");

        self::assertSame('added', addPaymentOpenpostColumn('postgresql'));
        self::assertSame('integer', self::columns()['openpost_id'] ?? null, 'betalinger has openpost_id');
        self::assertSame([['egen_ref' => 'Afr: 1000 01112026 - 08112026', 'openpost_id' => null]],
            pg_fetch_all(pg_query(self::$conn, "SELECT egen_ref, openpost_id FROM betalinger")), 'the payment is kept, with no open post');

        self::assertSame('present', addPaymentOpenpostColumn('postgresql'), 'the next login adds nothing');
        self::assertCount(15, self::columns());
    }

    public function test_a_new_ledger_is_created_with_the_column(): void
    {
        self::boot();
        $source = (string) file_get_contents(self::OPRETFUNC);
        self::assertSame(1, preg_match('/"(CREATE TABLE betalinger \(\$id_column,[^"]*)"/', $source, $m), 'includes/opretfunc.php creates betalinger');
        self::exec(str_replace('$id_column', 'id SERIAL NOT NULL', $m[1]));

        self::assertSame('integer', self::columns()['openpost_id'] ?? null, 'a new ledger has openpost_id');
        self::assertSame('present', addPaymentOpenpostColumn('postgresql'));
    }

    public function test_a_database_without_betalinger_is_left_alone(): void
    {
        self::boot();

        self::assertSame('no table', addPaymentOpenpostColumn('postgresql'));
        self::assertSame([], self::columns());
    }
}
