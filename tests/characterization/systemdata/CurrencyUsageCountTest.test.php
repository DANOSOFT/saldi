<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Indstillinger » Regnskab & moms » Valuta reads each large table once, not once per currency and rate (testlab
 * round 1, 2026-11-05).
 *
 * The section shows, for every currency and every rate, where it is used (settings_rows_usage(): the delete check and
 * the cells that lock once used). Each use was a count(*) of its own, and the columns a currency is referenced by have
 * no index (valuta in transaktioner, kassekladde, ordrer and openpost), so every count read the whole table. On
 * test_34 (1M postings, 6 currencies, 1 rate shown) that is 7 reads of transaktioner per page load, and the page took
 * about 15 s to open in the lab; on test_12, whose USD has 261 rates, picking USD adds 261 more.
 *
 * Pins: the counts and texts are those of one count per row (postings and journal lines by the currency's number,
 * orders and open items by its code; a rate counts the currency's postings on or after its date, no other currency's),
 * and loading the tables reads each of the four tables once for all rows; a second load (the save loads the rows
 * again) reads them again and sees what changed. The database still judges each row's condition: here the valuta
 * columns of transaktioner, ordrer and openpost ignore case and spaces, as utf8mb4_general_ci on MySQL and MariaDB
 * ignores case and trailing spaces, so a GBP whose only references are an order in 'gbp', an open item in 'GBP ' and
 * a posting in '4 ' is in use (an exact match in PHP found none, and the currency could be deleted or recoded).
 * The queries run on a real Postgres against TEMP tables shaped like the SALDI schema, so nothing in the database
 * behind SALDI_CHAR_DSN is read or changed. A query that fails fails the test.
 *
 * History:
 * 20261105 CL/LH Created.
 */
#[RunTestsInSeparateProcesses]
final class CurrencyUsageCountTest extends TestCase
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
        $GLOBALS['__cu_conn'] = $conn;
        $GLOBALS['__cu_queries'] = [];
        eval('
            function db_escape_string($s) { return pg_escape_string($GLOBALS["__cu_conn"], (string) $s); }
            function db_cast($expr, $type) { return "cast($expr as $type)"; }
            function db_select($q, $where = "") {
                $GLOBALS["__cu_queries"][] = $q;
                $r = @pg_query($GLOBALS["__cu_conn"], $q);
                if ($r === false) {
                    throw new \RuntimeException(pg_last_error($GLOBALS["__cu_conn"]) . " in: " . $q);
                }
                return $r;
            }
            function db_fetch_array($r) { return pg_fetch_assoc($r); }
            function db_modify($q, $where = "") { throw new \RuntimeException("a usage count wrote to the database: " . $q); }
            function findtekst($key, $sprog_id = 1) { return "%s #" . explode("|", (string) $key, 2)[0]; }
        ');
        require_once dirname(__DIR__, 3) . '/includes/settings/components.php';
        require_once dirname(__DIR__, 3) . '/includes/settings/rows.php';

        // The columns the counts read, typed as includes/opretfunc.php creates them. TEMP tables and a TEMP collation
        // only, and no DROP: pg_temp comes first in the search path. The collation ignores case and spaces, as
        // MySQL's and MariaDB's utf8mb4_general_ci ignores case and trailing spaces (CI runs Postgres only).
        self::exec("CREATE COLLATION pg_temp.saldi_ci (provider = icu, locale = 'und-u-ka-shifted-ks-level2-kv-space', deterministic = false)");
        self::exec("CREATE TEMP TABLE grupper (id integer, beskrivelse text, kodenr integer, art text, box1 text, box2 text, box3 text, box4 text, fiscal_year integer)");
        self::exec("CREATE TEMP TABLE kontoplan (id integer, kontonr integer, valuta integer, regnskabsaar integer)");
        self::exec("CREATE TEMP TABLE transaktioner (id integer, kontonr integer, transdate date, valuta text COLLATE pg_temp.saldi_ci)");
        self::exec("CREATE TEMP TABLE kassekladde (id integer, transdate date, valuta integer)");
        self::exec("CREATE TEMP TABLE ordrer (id integer, art varchar(2), valuta text COLLATE pg_temp.saldi_ci)");
        self::exec("CREATE TEMP TABLE openpost (id integer, konto_id integer, valuta text COLLATE pg_temp.saldi_ci)");
        self::exec("CREATE TEMP TABLE valuta (id integer, gruppe integer, valdate date, kurs numeric(15,3))");
        self::exec("INSERT INTO grupper VALUES
            (11, 'Euro', 1, 'VK', 'EUR', '745', '2050', '1', NULL),
            (12, 'Svenske kroner', 2, 'VK', 'SEK', '65', '2050', '0', NULL),
            (13, 'Norske kroner', 3, 'VK', 'NOK', '', '2050', '0', NULL),
            (14, 'Britiske pund', 4, 'VK', 'GBP', '850', '2050', '0', NULL)");
        self::exec("INSERT INTO kontoplan VALUES (1, 5800, 1, 1), (2, 5810, 1, 1), (3, 5820, 2, 1), (4, 1000, 0, 1), (5, 2000, NULL, 1)");
        // EUR (number 1) postings on five dates, one of them as '1 ', one SEK posting, GBP's only posting as '4 ',
        // base-currency postings with '0', none and -1.
        self::exec("INSERT INTO transaktioner VALUES
            (1, 5800, '2025-12-31', '1'), (2, 5800, '2026-01-01', '1'), (3, 5800, '2026-03-15', '1'), (4, 5810, '2026-06-01', '1'),
            (9, 5810, '2026-06-15', '1 '), (5, 5820, '2026-02-01', '2'), (10, 5830, '2026-02-02', '4 '),
            (6, 1000, '2026-01-10', '0'), (7, 1000, '2026-01-11', NULL), (8, 2000, '2026-01-01', '-1')");
        self::exec("INSERT INTO kassekladde VALUES (1, '2026-01-05', 1), (2, '2026-01-06', 2), (3, '2026-01-07', 2), (4, '2026-01-08', 0)");
        self::exec("INSERT INTO ordrer VALUES (1, 'DO', 'EUR'), (2, 'DO', 'EUR'), (3, 'KO', 'SEK'), (4, 'DO', 'DKK'), (5, 'DO', NULL), (6, 'DO', 'gbp')");
        self::exec("INSERT INTO openpost VALUES (1, 7, 'EUR'), (2, 8, 'DKK'), (3, 9, ''), (4, 10, 'GBP ')");
        // EUR's rates, one of them without a date (counted as nothing, as before), and one SEK rate.
        self::exec("INSERT INTO valuta VALUES
            (21, 1, '2026-01-01', 740), (22, 1, '2026-03-01', 742), (23, 1, '2026-06-01', 745), (24, 1, '2026-07-01', 746),
            (26, 1, NULL, 744), (25, 2, '2026-01-01', 65)");
    }

    private static function exec(string $sql): void
    {
        if (@pg_query(self::$conn, $sql) === false) {
            throw new \RuntimeException(pg_last_error(self::$conn) . ' in: ' . $sql);
        }
    }

    /**
     * @return array<string, mixed> a table of the Valuta section as settingsSection.php builds it
     */
    private static function table(string $tableId, int $currency = 0): array
    {
        $t = settings_rows_tables(getSettingsSections()['finance.currencies'])[$tableId];
        if (is_array($t['filter'])) {
            $t['filter']['value'] = $currency;
        }
        return $t;
    }

    /**
     * @return array<string, array{count: int, text: string}> code => usage, as the page loads the rows
     */
    private static function usageByCode(array $t): array
    {
        $out = [];
        foreach (settings_rows_load($t, null) as $row) {
            $out[(string) $row['cells'][$t['code_col']]] = ['count' => $row['usage'], 'text' => $row['usage_text']];
        }
        return $out;
    }

    /**
     * @return array<string, int> table => number of queries that read it since the last call
     */
    private static function reads(): array
    {
        $reads = ['transaktioner' => 0, 'kassekladde' => 0, 'ordrer' => 0, 'openpost' => 0];
        foreach ($GLOBALS['__cu_queries'] as $q) {
            foreach (array_keys($reads) as $table) {
                if (preg_match('/\bfrom ' . $table . '\b/', $q)) {
                    $reads[$table]++;
                }
            }
        }
        $GLOBALS['__cu_queries'] = [];
        return $reads;
    }

    public function test_the_currencies_count_their_uses_as_the_database_compares_with_one_read_of_each_large_table(): void
    {
        self::boot();

        self::assertSame([
            'EUR' => ['count' => 16, 'text' => '2 #6601 · 5 #6423 · 1 #6566 · 2 #6422 · 1 #6568 · 5 #6602'],
            'GBP' => ['count' => 3, 'text' => '1 #6423 · 1 #6422 · 1 #6568'],
            'NOK' => ['count' => 0, 'text' => ''],
            'SEK' => ['count' => 6, 'text' => '1 #6601 · 1 #6423 · 2 #6566 · 1 #6422 · 1 #6602'],
        ], self::usageByCode(self::table('currencies')));
        self::assertSame(['transaktioner' => 1, 'kassekladde' => 1, 'ordrer' => 1, 'openpost' => 1], self::reads());

        // A save loads the rows again (dry run, then the real run): each load reads the tables once and counts what is
        // there now.
        self::exec("INSERT INTO ordrer VALUES (7, 'KO', 'nok ')");
        self::assertSame(['count' => 1, 'text' => '1 #6422'], self::usageByCode(self::table('currencies'))['NOK']);
        self::assertSame(['transaktioner' => 1, 'kassekladde' => 1, 'ordrer' => 1, 'openpost' => 1], self::reads());
    }

    public function test_a_rate_counts_the_currencys_postings_on_or_after_its_date_with_one_read_for_all_rates(): void
    {
        self::boot();

        self::assertSame([
            '' => ['count' => 0, 'text' => ''],
            '2026-07-01' => ['count' => 0, 'text' => ''],
            '2026-06-01' => ['count' => 2, 'text' => '2 #6423'],
            '2026-03-01' => ['count' => 3, 'text' => '3 #6423'],
            '2026-01-01' => ['count' => 4, 'text' => '4 #6423'],
        ], self::usageByCode(self::table('rates', 1)));
        self::assertSame(['transaktioner' => 1, 'kassekladde' => 0, 'ordrer' => 0, 'openpost' => 0], self::reads());

        self::assertSame(['2026-01-01' => ['count' => 1, 'text' => '1 #6423']], self::usageByCode(self::table('rates', 2)));
    }

    public function test_a_currency_with_more_rates_than_postgres_takes_columns_is_counted_in_a_few_reads(): void
    {
        self::boot();
        // SEK gets 1,700 daily rates from 2021-01-01 (Postgres takes at most 1664 columns in a select) and a second
        // posting on 2023-01-01, next to the one on 2026-02-01.
        self::exec("INSERT INTO valuta SELECT 1000 + i, 2, DATE '2021-01-01' + i, 65 FROM generate_series(0, 1699) AS i");
        self::exec("INSERT INTO transaktioner VALUES (11, 5820, '2023-01-01', '2')");

        $usage = self::usageByCode(self::table('rates', 2));
        self::assertCount(1701, $usage);
        foreach ($usage as $date => $u) {
            $n = ($date <= '2023-01-01' ? 1 : 0) + ($date <= '2026-02-01' ? 1 : 0);
            self::assertSame(['count' => $n, 'text' => $n ? "$n #6423" : ''], $u, $date);
        }
        self::assertSame(['transaktioner' => 2, 'kassekladde' => 0, 'ordrer' => 0, 'openpost' => 0], self::reads());
    }
}
