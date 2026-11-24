<?php
//
// strict_types is off on purpose: eval'd code inherits the calling file's setting, and the lifted function runs as on the page.

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * After a restore, the activity log rows the backup lacks go back into the restored ledger (admin/restore.php
 * restore_log_putback(), pre-check on #1199, 2026-11-24).
 *
 * A row whose id the backup already has, for another entry, goes back with a new id from the sequence. The sequence
 * stands at the backup's highest id after the restore, so the first such row drew the id after it. When a later row
 * kept its own id and that was the same one, its insert failed: on the page, db_modify() showed the error box and ended
 * the request before the sequence was set and before the restore was logged as done. That happens when the replaced
 * database's log and the backup's went different ways: another ledger's backup, or one from before a reset.
 *
 * Pins: on a real Postgres (a TEMP audit_log, so nothing in the shared SQL fixture database, TestDatabase::sqlFixture(),
 * is read or changed), the rows that keep their id go back with it, a row whose id is taken gets a new one after the
 * highest, nothing fails, and the sequence ends past every row. So does a second row with an id the first one took back.
 * A row the backup has (same id, time and action) is not put back twice.
 *
 * History:
 * 20261124 CL/ASR Created.
 */
#[RunTestsInSeparateProcesses]
final class RestoreLogPutbackTest extends TestCase
{
    private const PAGE = __DIR__ . '/../../../admin/restore.php';

    /** @var \PgSql\Connection|null */
    private static $conn = null;

    private static function boot(): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('the pgsql extension is required to run the activity log SQL.');
        }
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        self::$conn = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        $GLOBALS['__rlp_conn'] = self::$conn;
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['db'] = 'chartest';
        eval('
            function db_escape_string($s) { return pg_escape_string($GLOBALS["__rlp_conn"], (string) $s); }
            function db_select($q, $where = "") {
                $r = @pg_query($GLOBALS["__rlp_conn"], $q);
                if ($r === false) {
                    throw new \RuntimeException(pg_last_error($GLOBALS["__rlp_conn"]) . " in: " . $q);
                }
                return $r;
            }
            function db_fetch_array($r) { return pg_fetch_assoc($r); }
            // On the page a failed write shows the error box and ends the request; here it fails the test.
            function db_modify($q, $where = "") { db_select($q, $where); return "0"; }
            function ifset($a, $key = null, $default = null) { return (is_array($a) && isset($a[$key])) ? $a[$key] : $default; }
            // This session\'s TEMP schema only, as saldi_schema_filter() names the ledger\'s schema on the page.
            function saldi_schema_filter() { return " and table_schema = (select nspname from pg_namespace where oid = pg_my_temp_schema())"; }
        ');
        eval(self::functionSource('restore_log_putback'));

        // audit_log as includes/permissions.php creates it, in this session's TEMP schema; pg_temp comes first in the search path.
        self::exec("SET search_path TO pg_temp, public");
        self::exec("CREATE TEMP TABLE audit_log (id serial PRIMARY KEY, tidspunkt timestamp NOT NULL DEFAULT now(), bruger_id integer, "
            . "brugernavn varchar(80), handling varchar(60), detaljer text, ip varchar(45))");
    }

    private static function exec(string $sql): void
    {
        if (@pg_query(self::$conn, $sql) === false) {
            throw new \RuntimeException(pg_last_error(self::$conn) . " in: " . $sql);
        }
    }

    /** The restored backup's log: rows 1 to $max with their own ids, and the sequence where pg_dump leaves it. */
    private static function restoredBackup(int $max): void
    {
        for ($id = 1; $id <= $max; $id++) {
            self::exec("INSERT INTO audit_log (id, tidspunkt, brugernavn, handling) VALUES ($id, '2026-11-01 10:00:0$id', 'backup', 'backup.$id')");
        }
        self::exec("SELECT setval(pg_get_serial_sequence('audit_log', 'id'), $max)");
    }

    /** A row of the replaced database's log, as restore_log_snapshot() reads it. */
    private static function kept(int $id, string $time, string $handling): array
    {
        return ['id' => (string) $id, 'tidspunkt' => $time, 'bruger_id' => '0', 'brugernavn' => 'kept', 'handling' => $handling, 'detaljer' => '', 'ip' => ''];
    }

    /** @return array<int, string> handling by id */
    private static function log(): array
    {
        $out = [];
        $q = pg_query(self::$conn, "SELECT id, handling FROM audit_log ORDER BY id");
        while ($r = pg_fetch_assoc($q)) {
            $out[(int) $r['id']] = $r['handling'];
        }
        return $out;
    }

    public function test_a_row_whose_id_is_taken_gets_a_new_id_after_the_rows_that_keep_theirs(): void
    {
        self::boot();
        self::restoredBackup(3);

        // The replaced log went another way: its row 2 is another entry than the backup's, and rows 4 and 5 came later.
        $n = restore_log_putback([
            self::kept(2, '2026-11-20 09:00:00', 'other.2'),
            self::kept(4, '2026-11-20 09:00:01', 'kept.4'),
            self::kept(5, '2026-11-20 09:00:02', 'kept.5'),
        ]);

        self::assertSame(3, $n, 'every row the backup lacks goes back');
        self::assertSame([1 => 'backup.1', 2 => 'backup.2', 3 => 'backup.3', 4 => 'kept.4', 5 => 'kept.5', 6 => 'other.2'], self::log(),
            'rows 4 and 5 keep their ids, and the row whose id 2 is taken gets the next one after them');
        $next = (int) pg_fetch_result(pg_query(self::$conn, "SELECT nextval(pg_get_serial_sequence('audit_log', 'id'))"), 0, 0);
        self::assertSame(7, $next, 'the sequence ends past every row');
    }

    public function test_a_row_whose_id_a_row_just_put_back_took_gets_a_new_id_too(): void
    {
        self::boot();
        self::restoredBackup(3);

        $n = restore_log_putback([
            self::kept(4, '2026-11-20 09:00:00', 'kept.4a'),
            self::kept(4, '2026-11-20 09:00:01', 'kept.4b'),
        ]);

        self::assertSame(2, $n);
        self::assertSame([1 => 'backup.1', 2 => 'backup.2', 3 => 'backup.3', 4 => 'kept.4a', 5 => 'kept.4b'], self::log());
    }

    public function test_a_row_the_backup_has_is_not_put_back_twice(): void
    {
        self::boot();
        self::restoredBackup(3);

        $n = restore_log_putback([
            self::kept(2, '2026-11-01 10:00:02', 'backup.2'),
            self::kept(3, '2026-11-01 10:00:03', 'backup.3'),
            self::kept(4, '2026-11-20 09:00:00', 'kept.4'),
        ]);

        self::assertSame(1, $n);
        self::assertSame([1 => 'backup.1', 2 => 'backup.2', 3 => 'backup.3', 4 => 'kept.4'], self::log());
    }

    /**
     * The source of one function in admin/restore.php, from `function` to its closing brace. restore.php is a page script
     * (session, includes, output), so the function is lifted out of its source rather than loaded with it.
     */
    private static function functionSource(string $name): string
    {
        $source = file_get_contents(self::PAGE);
        self::assertNotFalse($source, 'could not read admin/restore.php');
        $tokens = token_get_all($source);
        foreach ($tokens as $i => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }
            $j = $i + 1;
            while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $name) {
                continue;
            }
            $depth = 0;
            for ($k = $j; $k < count($tokens); $k++) {
                $text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
                if ($text === '{' || (is_array($tokens[$k]) && in_array($tokens[$k][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                    $depth++;
                } elseif ($text === '}') {
                    $depth--;
                    if ($depth === 0) {
                        return implode('', array_map(static fn($t) => is_array($t) ? $t[1] : $t, array_slice($tokens, $i, $k - $i + 1)));
                    }
                }
            }
        }
        self::fail("function $name() is gone from admin/restore.php");
    }
}
