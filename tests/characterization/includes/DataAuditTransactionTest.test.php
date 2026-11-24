<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * The activity log's reads (includes/dataAudit.php) inside the caller's PostgreSQL transaction.
 *
 * db_modify() asks data_audit_before() for the watched rows before a write and data_audit_after() for them after it, on the
 * request's own connection. Inside transaktion('begin') a read that fails aborts the whole transaction ("current transaction
 * is aborted"), so every later statement of the posting or import fails. The reads now run under a savepoint, and an insert
 * that names the id itself is counted instead of looked up by currval(), which fails in a session that has drawn no id from
 * the sequence and names an earlier row in one that has; an insert of several rows is counted too, as currval() names only
 * its last.
 *
 * Runs on a real Postgres with search_path set to pg_temp: adresser is a TEMP table of this session, and nothing in the
 * shared SQL fixture database (TestDatabase::sqlFixture()) is read or changed. Each test runs in a process of its own, so
 * each starts with a fresh session.
 *
 * History:
 * 20261124 CL/ASR Created.
 */
#[RunTestsInSeparateProcesses]
final class DataAuditTransactionTest extends TestCase
{
    /** @var \PgSql\Connection|null */
    private static $conn = null;

    protected function setUp(): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('the pgsql extension is required to run the activity log against PostgreSQL.');
        }
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        self::$conn = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['__dat_log'] = [];
        // The activity log itself (includes/permissions.php): ready, and every entry is kept for the assertions.
        eval('
            function audit_ready(): bool { return true; }
            function audit_log(string $handling, string $detaljer = "", string $objektType = "", string $objektId = "", string $kilde = "ui", bool $system = false): void {
                $GLOBALS["__dat_log"][] = [$handling, $objektType, $objektId];
            }
        ');
        require_once dirname(__DIR__, 3) . '/includes/dataAudit.php';

        self::exec("SET search_path TO pg_temp");
        self::exec("CREATE TEMP TABLE adresser (id serial PRIMARY KEY, kontonr text, firmanavn text, art text)");
        self::exec("CREATE TEMP TABLE kategori (id integer, navn text)");
        self::exec("INSERT INTO kategori VALUES (1, 'A')");
        self::assertStringStartsWith('pg_temp', (string) pg_fetch_result(pg_query(self::$conn, "SELECT current_schema()"), 0, 0));
    }

    private static function exec(string $sql): void
    {
        if (@pg_query(self::$conn, $sql) === false) {
            throw new \RuntimeException(pg_last_error(self::$conn) . ' in: ' . $sql);
        }
    }

    /** One write as db_modify() runs it: the look before, the statement, the look after. */
    private static function write(string $sql): void
    {
        $ctx = data_audit_before($sql, false, self::$conn);
        $res = @pg_query(self::$conn, $sql);
        if ($res === false) {
            throw new \RuntimeException(pg_last_error(self::$conn) . ' in: ' . $sql);
        }
        if ($ctx) {
            data_audit_after($ctx, $res);
        }
    }

    private static function transactionStatus(): int
    {
        return pg_transaction_status(self::$conn);
    }

    public function testAnInsertThatNamesTheIdInAFreshSessionLeavesTheTransactionUsable(): void
    {
        self::exec('BEGIN');
        // No id has been drawn from adresser_id_seq in this session, so currval() would fail here.
        self::write("INSERT INTO adresser (id, kontonr, firmanavn, art) VALUES (500, '500', 'Fjordlys Design A/S', 'K')");
        self::assertSame(PGSQL_TRANSACTION_INTRANS, self::transactionStatus(), 'the activity log aborted the caller\'s transaction');
        self::exec("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('501', 'Næste', 'K')");
        self::exec('COMMIT');
        self::assertSame('2', (string) pg_fetch_result(pg_query(self::$conn, "SELECT count(*) FROM adresser"), 0, 0));
    }

    public function testAnInsertThatNamesTheIdIsCountedNotLoggedAsAnEarlierRow(): void
    {
        self::exec('BEGIN');
        self::write("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('100', 'Første', 'D')");
        // currval() now names row 1, which this statement did not create.
        self::write("INSERT INTO adresser (id, kontonr, firmanavn, art) VALUES (900, '900', 'Importeret', 'K')");
        self::exec('COMMIT');
        $created = array_values(array_filter($GLOBALS['__dat_log'], fn ($e) => $e[0] === 'data.created'));
        self::assertSame([['data.created', 'Kunde', '100 · Første']], $created, 'only the row the sequence gave an id is logged by name');
    }

    public function testAnInsertWithItsOwnIdStillLogsTheNewRowByName(): void
    {
        self::exec('BEGIN');
        self::write("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('200', 'Ny kunde', 'D')");
        self::exec('COMMIT');
        self::assertSame([['data.created', 'Kunde', '200 · Ny kunde']], $GLOBALS['__dat_log']);
    }

    public function testAnInsertOfSeveralRowsIsCountedNotLoggedAsItsLastRow(): void
    {
        self::exec('BEGIN');
        self::write("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('210', 'Første (a)', 'D'), ('211', 'Anden, b', 'D')");
        self::exec('COMMIT');
        self::assertSame([], $GLOBALS['__dat_log'], 'currval() names only the last of the rows');
        self::assertSame('2', (string) pg_fetch_result(pg_query(self::$conn, "SELECT count(*) FROM adresser"), 0, 0));
    }

    public function testAnInsertOfSeveralRowsIsWrittenAsOneCountWhenTheRequestEnds(): void
    {
        // The count is written by a shutdown function, so the write runs in a process of its own (support/data_audit_count_runner.php).
        $cmd = [PHP_BINARY, __DIR__ . '/support/data_audit_count_runner.php', TestDatabase::sqlFixture(static::class)->name(),
            "INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('230', 'Første', 'D'),\n ('231', 'Anden', 'K')\nRETURNING id"];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($proc);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($proc), "runner failed: $err");
        $lines = array_map(fn ($l) => json_decode($l, true), array_values(array_filter(explode("\n", $out))));
        self::assertSame([['rows', 2], ['data.bulk', '2 kunder/leverandører oprettet i én handling', 'Konto', '']], $lines);
    }

    public function testAnInsertIsJudgedByTheRowsItAdded(): void
    {
        // Two rows, however the statement is written: counted, not logged as its last row.
        self::write("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('240', 'a' /* ' */, 'D'), /* ) */ ('241', 'b', 'D')");
        self::write("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('242', (SELECT 'x' AS \")\"), 'D'), ('243', 'y', 'D')");
        self::assertSame([], $GLOBALS['__dat_log']);
        // One row: logged by name, also from a select.
        self::write("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('250', 'Én', 'D')");
        self::write("INSERT INTO adresser (kontonr, firmanavn, art) SELECT '260', navn, 'K' FROM kategori WHERE id = 1");
        // No row: nothing, and no look by currval() for a row that is not there.
        self::exec('BEGIN');
        self::write("INSERT INTO adresser (kontonr, firmanavn, art) SELECT '270', navn, 'K' FROM kategori WHERE false");
        self::assertSame(PGSQL_TRANSACTION_INTRANS, self::transactionStatus());
        self::exec('COMMIT');
        self::assertSame([['data.created', 'Kunde', '250 · Én'], ['data.created', 'Leverandør', '260 · A']], $GLOBALS['__dat_log']);
    }

    public function testAnInsertWithOnConflictIsCountedAsCurrvalMayNameAnotherRow(): void
    {
        self::exec("CREATE UNIQUE INDEX adresser_kontonr_idx ON adresser (kontonr)");
        // Row 2 is there already, with an id the sequence did not give.
        self::exec("INSERT INTO adresser (id, kontonr, firmanavn, art) VALUES (2, '300', 'Der i forvejen', 'K')");
        // Ids 1 and 2 are drawn; row 1 is added and the row given id 2 is skipped: one row added, currval() names row 2.
        self::write("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('310', 'Ny', 'D'), ('311', 'Sprunget over', 'D') ON CONFLICT (id) DO NOTHING");
        // An existing row is updated: one row affected, none added, and currval() names the id drawn for nothing.
        self::write("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('310', 'Opdateret', 'D')\nON CONFLICT (kontonr) DO UPDATE SET firmanavn = excluded.firmanavn");
        self::assertSame([], $GLOBALS['__dat_log'], 'an insert with ON CONFLICT is not logged by the name currval() gives');
        self::assertSame(['310 Opdateret', '300 Der i forvejen'], array_map(fn ($r) => $r['kontonr'] . ' ' . $r['firmanavn'],
            pg_fetch_all(pg_query(self::$conn, "SELECT kontonr, firmanavn FROM adresser ORDER BY id"))));
    }

    public function testAnInsertThatCallsNextvalItselfIsCounted(): void
    {
        // Row 3 is there already. The new row gets id 2 from its default, and RETURNING draws 3 after it: currval() names row 3.
        self::exec("INSERT INTO adresser (id, kontonr, firmanavn, art) VALUES (3, '400', 'Der i forvejen', 'K')");
        self::exec("SELECT nextval('adresser_id_seq')");
        self::write("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('410', 'Ny', 'D') RETURNING id, nextval('adresser_id_seq')");
        self::assertSame([], $GLOBALS['__dat_log'], 'an insert that calls nextval() is not logged by the name currval() gives');
        self::assertSame(['410 Ny', '400 Der i forvejen'], array_map(fn ($r) => $r['kontonr'] . ' ' . $r['firmanavn'],
            pg_fetch_all(pg_query(self::$conn, "SELECT kontonr, firmanavn FROM adresser ORDER BY id"))));
    }

    public function testAnInsertWithReturningAndOneRowIsStillLoggedByName(): void
    {
        self::write("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('220', 'Med (parentes), og komma', 'K') RETURNING id, kontonr");
        self::assertSame([['data.created', 'Leverandør', '220 · Med (parentes), og komma']], $GLOBALS['__dat_log']);
    }

    public function testALookBeforeAnUpdateThatCannotRunAloneLeavesTheTransactionUsable(): void
    {
        self::exec("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('300', 'Gammelt navn', 'D')");
        self::exec('BEGIN');
        // The look runs "select * from adresser where <where>" without the FROM list, so kategori is unknown to it.
        self::write("UPDATE adresser SET firmanavn = 'Nyt navn' FROM kategori WHERE adresser.kontonr = '300' AND kategori.id = 1");
        self::assertSame(PGSQL_TRANSACTION_INTRANS, self::transactionStatus(), 'the activity log aborted the caller\'s transaction');
        self::exec('COMMIT');
        self::assertSame('Nyt navn', (string) pg_fetch_result(pg_query(self::$conn, "SELECT firmanavn FROM adresser WHERE kontonr = '300'"), 0, 0));
    }

    public function testAnUpdateInsideATransactionIsStillLoggedWithItsChange(): void
    {
        self::exec("INSERT INTO adresser (kontonr, firmanavn, art) VALUES ('400', 'Før', 'K')");
        self::exec('BEGIN');
        self::write("UPDATE adresser SET firmanavn = 'Efter' WHERE kontonr = '400'");
        self::exec('COMMIT');
        self::assertSame([['data.updated', 'Leverandør', '400 · Efter']], $GLOBALS['__dat_log']);
    }

    public function testATransactionThatHasAlreadyFailedIsNotReadFrom(): void
    {
        self::exec('BEGIN');
        self::assertFalse(@pg_query(self::$conn, 'SELECT * FROM no_such_table'));
        self::assertSame(PGSQL_TRANSACTION_INERROR, self::transactionStatus());
        self::assertNull(data_audit_rows(self::$conn, 'SELECT 1'));
        self::exec('ROLLBACK');
        self::assertSame([['1']], array_map('array_values', data_audit_rows(self::$conn, 'SELECT 1 AS one')));
    }
}
