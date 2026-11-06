<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Indstillinger » Baggrunde counts the customers and suppliers that use a background (testlab round 1, 2026-11-05).
 *
 * The delete check of a background (settings_rows_usage(), usage 'background') counted customers with
 * "select count(*) from adresser where sprog = ...". adresser has no sprog column, so on a book with background
 * rows the page ran a failing query per row: an error box over the page, an error mail, and a customer count that
 * was always 0. Customers and suppliers take their form language from their group (DG/KG box4, as debitor/ordre.php
 * does for a new order); orders name it themselves (ordrer.sprog).
 *
 * Pins: the queries run on a real Postgres against tables shaped like the SALDI schema (TEMP tables, so nothing in
 * the database behind SALDI_CHAR_DSN is read or changed). A query that fails fails the test. A group counts in any
 * fiscal year but a customer only once, D looks in DG and K in KG, and the group's language matches without regard
 * to case, as the old syssetup.php check (sprogtjek) accepted it.
 *
 * History:
 * 20261105 CL/LH Created.
 */
#[RunTestsInSeparateProcesses]
final class BackgroundUsageTest extends TestCase
{
    /** @var \PgSql\Connection|null */
    private static $conn = null;

    private static function boot(): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('the pgsql extension is required to run the usage SQL.');
        }
        $dsn = (string) getenv('SALDI_CHAR_DSN');
        if ($dsn === '') {
            self::markTestSkipped('set SALDI_CHAR_DSN, SALDI_CHAR_PGUSER and SALDI_CHAR_PGPASS to run the usage SQL.');
        }
        $host = preg_match('/host=([^;]+)/', $dsn, $m) ? $m[1] : 'localhost';
        $name = preg_match('/dbname=([^;]+)/', $dsn, $m) ? $m[1] : '';
        $conn = pg_connect(sprintf(
            "host=%s dbname=%s user=%s password=%s connect_timeout=5",
            $host,
            $name,
            (string) getenv('SALDI_CHAR_PGUSER'),
            (string) getenv('SALDI_CHAR_PGPASS')
        ));
        if ($conn === false) {
            self::markTestSkipped('could not connect to the database SALDI_CHAR_DSN names.');
        }
        self::$conn = $conn;
        $GLOBALS['__bg_conn'] = $conn;
        eval('
            function db_escape_string($s) { return pg_escape_string($GLOBALS["__bg_conn"], (string) $s); }
            function db_select($q, $where = "") {
                $r = @pg_query($GLOBALS["__bg_conn"], $q);
                if ($r === false) {
                    throw new \RuntimeException(pg_last_error($GLOBALS["__bg_conn"]) . " in: " . $q);
                }
                return $r;
            }
            function db_fetch_array($r) { return pg_fetch_assoc($r); }
            function db_modify($q, $where = "") { throw new \RuntimeException("a usage count wrote to the database: " . $q); }
            function findtekst($key, $sprog_id = 1) { return "%s #" . explode("|", (string) $key, 2)[0]; }
        ');
        require_once dirname(__DIR__, 3) . '/includes/settings/components.php';
        require_once dirname(__DIR__, 3) . '/includes/settings/rows.php';

        // The columns these counts read, typed as includes/opretfunc.php creates them; adresser has no sprog.
        // TEMP tables only and no DROP: pg_temp comes first in the search path.
        self::exec("CREATE TEMP TABLE adresser (id integer, firmanavn varchar(90), art varchar(2), gruppe integer)");
        self::exec("CREATE TEMP TABLE grupper (id integer, beskrivelse text, kodenr integer, art text, box1 text, box4 text, fiscal_year integer)");
        self::exec("CREATE TEMP TABLE ordrer (id integer, konto_id integer, art varchar(2), sprog text)");
        self::exec("INSERT INTO grupper VALUES
            (1, 'Formular og varesprog', 1, 'VSPR', 'Engelsk', NULL, NULL),
            (2, 'Formular og varesprog', 2, 'VSPR', 'Tysk', NULL, NULL),
            (3, 'Udland', 1, 'DG', 'S1', 'Engelsk', 1),
            (4, 'Udland', 1, 'DG', 'S1', 'Engelsk', 2),
            (5, 'Indland', 2, 'DG', 'S1', 'Dansk', 2),
            (6, 'Indland', 1, 'KG', 'K1', 'Dansk', 2),
            (7, 'Udland', 2, 'KG', 'K1', 'engelsk', 2)");
        // Two customers in DG 1 and a supplier in KG 2 use Engelsk. The customer in DG 2 and the supplier in KG 1
        // would match only if D/K looked in the other kind's group.
        self::exec("INSERT INTO adresser VALUES
            (1, 'Kunde A', 'D', 1), (2, 'Kunde B', 'D', 1), (3, 'Kunde C', 'D', 2),
            (4, 'Leverandør A', 'K', 1), (5, 'Leverandør B', 'K', 2), (6, 'Eget firma', 'S', NULL)");
        self::exec("INSERT INTO ordrer VALUES (1, 1, 'DO', 'Engelsk'), (2, 3, 'DO', 'Dansk'), (3, 3, 'DO', 'Dansk')");
    }

    private static function exec(string $sql): void
    {
        if (@pg_query(self::$conn, $sql) === false) {
            throw new \RuntimeException(pg_last_error(self::$conn) . " in: " . $sql);
        }
    }

    /**
     * @return array{count: int, text: string}
     */
    private static function usage(string $background): array
    {
        $t = settings_rows_tables(getSettingsSections()['documents.backgrounds'])['backgrounds'];
        $id = (int) pg_fetch_result(pg_query(self::$conn, "select id from grupper where art = 'VSPR' and box1 = '"
            . pg_escape_string(self::$conn, $background) . "'"), 0, 0);
        return settings_rows_usage($t, array('id' => $id, 'cells' => array('box1' => $background, 'template' => ''), 'raw' => array()));
    }

    public function test_a_background_counts_the_customers_and_suppliers_whose_group_uses_it_and_its_orders(): void
    {
        self::boot();

        self::assertSame(array('count' => 4, 'text' => '3 #6682 · 1 #6422'), self::usage('Engelsk'));
    }

    public function test_an_unused_background_has_no_usage(): void
    {
        self::boot();

        self::assertSame(array('count' => 0, 'text' => ''), self::usage('Tysk'));
    }
}
