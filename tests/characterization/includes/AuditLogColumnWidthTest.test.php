<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * audit_log() (includes/permissions.php) inside the caller's PostgreSQL transaction.
 *
 * The activity log writes its entry on the request's own connection, also in the middle of a posting or an import
 * (includes/dataAudit.php through db_modify()). A value wider than its column (brugernavn VARCHAR(80), handling
 * VARCHAR(60), objekt_type VARCHAR(30), objekt_id VARCHAR(60), kilde VARCHAR(30)) made the insert fail, and a failed
 * statement aborts the whole transaction. Every value is now cut to its column's width in characters, counted as they are
 * stored: text that is not UTF-8 is converted first, as db_escape_string() converts it.
 *
 * Runs on a real Postgres with search_path set to pg_temp: audit_log is a TEMP table of this session, and nothing in the
 * shared SQL fixture database (TestDatabase::sqlFixture()) is read or changed.
 *
 * History:
 * 20261124 CL/ASR Created.
 */
#[RunTestsInSeparateProcesses]
final class AuditLogColumnWidthTest extends TestCase
{
    /** @var \PgSql\Connection|null */
    private static $conn = null;

    protected function setUp(): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('the pgsql extension is required to write the activity log to PostgreSQL.');
        }
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        self::$conn = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        $GLOBALS['__alw_conn'] = self::$conn;
        $GLOBALS['db_type'] = 'postgresql';
        // The database layer of includes/db_query.php on this connection; a failed statement fails the test.
        eval('
            function db_select($q, $where = "", $global = false) {
                $r = @pg_query($GLOBALS["__alw_conn"], $q);
                if ($r === false) {
                    throw new \RuntimeException(pg_last_error($GLOBALS["__alw_conn"]) . " in: " . $q);
                }
                return $r;
            }
            function db_modify($q, $where = "", $global = false) { return db_select($q, $where); }
            function db_fetch_array($r) { return pg_fetch_array($r); }
            function db_escape_string($s) {
                // As includes/db_query.php: text that is not UTF-8 is read as Windows-1252 before it is escaped.
                $s = str_replace("\0", "", (string) $s);
                if (!mb_check_encoding($s, "UTF-8")) $s = mb_convert_encoding($s, "UTF-8", "Windows-1252");
                return pg_escape_string($GLOBALS["__alw_conn"], $s);
            }
            function tbl_exists($table) {
                return (bool) pg_fetch_array(db_select("SELECT 1 FROM information_schema.tables WHERE table_name = \'" . $table . "\'"));
            }
        ');
        require_once dirname(__DIR__, 3) . '/includes/permissions.php';

        self::exec("SET search_path TO pg_temp");
        // As includes/betweenUpdates.php creates it on PostgreSQL.
        self::exec("CREATE TEMP TABLE audit_log (id serial PRIMARY KEY, tidspunkt timestamp DEFAULT now(), bruger_id integer,
            brugernavn varchar(80), handling varchar(60) NOT NULL, objekt_type varchar(30), objekt_id varchar(60), detaljer text,
            ip varchar(45), kilde varchar(30))");
        self::assertStringStartsWith('pg_temp', (string) pg_fetch_result(pg_query(self::$conn, "SELECT current_schema()"), 0, 0));
    }

    private static function exec(string $sql): void
    {
        if (@pg_query(self::$conn, $sql) === false) {
            throw new \RuntimeException(pg_last_error(self::$conn) . ' in: ' . $sql);
        }
    }

    public function testValuesWiderThanTheirColumnsAreCutAndTheTransactionGoesOn(): void
    {
        $GLOBALS['bruger_id'] = 7;
        $GLOBALS['brugernavn_raw'] = str_repeat('Ø', 85);
        self::exec('BEGIN');
        audit_log('data.' . str_repeat('æ', 70), 'Navn: Fjordlys Design A/S', str_repeat('Leverandør ', 4), str_repeat('500 · Fjordlys ', 6), 'lager/' . str_repeat('å', 40));
        self::assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status(self::$conn), 'the entry aborted the caller\'s transaction');
        self::exec('COMMIT');

        $row = pg_fetch_assoc(pg_query(self::$conn, "SELECT brugernavn, handling, objekt_type, objekt_id, kilde, detaljer FROM audit_log"));
        self::assertSame(str_repeat('Ø', 80), $row['brugernavn']);
        self::assertSame(60, mb_strlen($row['handling']));
        self::assertSame(30, mb_strlen($row['objekt_type']));
        self::assertSame(60, mb_strlen($row['objekt_id']));
        self::assertSame(30, mb_strlen($row['kilde']));
        self::assertTrue(mb_check_encoding($row['objekt_id'], 'UTF-8') && str_starts_with($row['objekt_id'], '500 · Fjordlys'), 'cut between characters, not inside one');
        self::assertSame('Navn: Fjordlys Design A/S', $row['detaljer']);
    }

    public function testTextThatIsNotUtf8IsCutAsTheCharactersItBecomes(): void
    {
        // ISO-8859-15 bytes (æ is 0xE6): db_escape_string() turns each into a character of its own.
        $GLOBALS['bruger_id'] = 8;
        $GLOBALS['brugernavn_raw'] = str_repeat("\xe6", 79) . "\xe6more";
        self::exec('BEGIN');
        audit_log('data.' . str_repeat("\xe6", 61), 'detaljer', str_repeat("\xe6", 31), str_repeat("\xe6", 61), str_repeat("\xe6", 31));
        self::assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status(self::$conn), 'the entry aborted the caller\'s transaction');
        self::exec('COMMIT');

        $row = pg_fetch_assoc(pg_query(self::$conn, "SELECT brugernavn, handling, objekt_type, objekt_id, kilde FROM audit_log"));
        self::assertSame(str_repeat('æ', 80), $row['brugernavn']);
        self::assertSame('data.' . str_repeat('æ', 55), $row['handling']);
        self::assertSame(str_repeat('æ', 30), $row['objekt_type']);
        self::assertSame(str_repeat('æ', 60), $row['objekt_id']);
        self::assertSame(str_repeat('æ', 30), $row['kilde']);
    }

    public function testShortValuesAreWrittenAsGiven(): void
    {
        $GLOBALS['bruger_id'] = 3;
        $GLOBALS['brugernavn_raw'] = "O'Brien";
        audit_log('data.updated', 'Navn: Før → Efter', 'Leverandør', '400 · Efter', 'lager/varekort.php');
        $row = pg_fetch_assoc(pg_query(self::$conn, "SELECT bruger_id, brugernavn, handling, objekt_type, objekt_id, kilde FROM audit_log"));
        self::assertSame(['3', "O'Brien", 'data.updated', 'Leverandør', '400 · Efter', 'lager/varekort.php'], array_values($row));
    }
}
