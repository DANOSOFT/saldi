<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Indstillinger » Virksomhed » Regnskabsår » Opret opens the new year balanced (testlab round 1, year-end-rollover,
 * 2026-11-05).
 *
 * settings_fiscal_year_create() inserts the year, copies the groups and the chart of accounts and makes the year the
 * user's active one in a single click, and only then shows the year card. The copy opened each balance account with its
 * own saldo: Resultat landed on no account and a balance account whose Overfør til points elsewhere was not merged, so
 * the active, posting-enabled Y+1 opened out of balance by Resultat until somebody pressed Gem/opdatér on the card (or
 * posted into Y). In the lab, a customer book's new year opened with Ny Primo off from Saldo by exactly the year's
 * result, missing from the result account.
 *
 * Pins: the new year opens with the carry-forward the card's Gem writes (balance accounts onto their Overfør til or
 * themselves, the result of the year onto the result account's Overfør til, operating and sum accounts at 0), so its
 * opening balance sums to what Y's did; and when the result has no account to land on nothing partial is written, so
 * the copy keeps last year's balances for the card to settle. The queries run on a real Postgres against TEMP tables
 * shaped like includes/opretfunc.php creates them, so nothing in the database behind SALDI_CHAR_DSN is read or changed.
 *
 * History:
 * 20261105 CL/LH Created.
 */
#[RunTestsInSeparateProcesses]
final class FiscalYearCreateOpeningBalanceTest extends TestCase
{
    /** @var \PgSql\Connection|null */
    private static $conn = null;

    private static function boot(): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('the pgsql extension is required to create the year.');
        }
        $dsn = (string) getenv('SALDI_CHAR_DSN');
        if ($dsn === '') {
            self::markTestSkipped('set SALDI_CHAR_DSN, SALDI_CHAR_PGUSER and SALDI_CHAR_PGPASS to create the year.');
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
        $GLOBALS['__fy_conn'] = $conn;
        $GLOBALS['__fy_genberegn'] = [];
        $GLOBALS['__fy_audit'] = [];
        // genberegn() is a recorder: the fixture's saldo already is primo plus the year's postings, as genberegn() leaves
        // it, and the real one switches to the master database to count postings.
        eval('
            function db_escape_string($s) { return pg_escape_string($GLOBALS["__fy_conn"], (string) $s); }
            function db_cast($expr, $type) { return "cast($expr as $type)"; }
            function db_select($q, $where = "") {
                $r = @pg_query($GLOBALS["__fy_conn"], $q);
                if ($r === false) {
                    throw new \RuntimeException(pg_last_error($GLOBALS["__fy_conn"]) . " in: " . $q);
                }
                return $r;
            }
            function db_modify($q, $where = "", $noErr = false) { return db_select($q, $where); }
            function db_fetch_array($r) { return pg_fetch_assoc($r); }
            function transaktion($t) { db_select($t); }
            function saldi_schema_filter() { return " and table_schema = (select nspname from pg_namespace where oid = pg_my_temp_schema())"; }
            function afrund($v, $d) { return round((float) $v, (int) $d); }
            function genberegn($year, $updateUsage = true) { $GLOBALS["__fy_genberegn"][] = (int) $year; }
            final class SettingsService {
                public static function auditRow(string $section, string $key, string $objektId, string $old, string $new, string $handling): void
                {
                    $GLOBALS["__fy_audit"][] = [$objektId, $handling];
                }
            }
        ');
        require_once dirname(__DIR__, 3) . '/includes/settings/rowHooks.php';

        // TEMP tables only, and no DROP: pg_temp comes first in the search path.
        self::exec("CREATE TEMP TABLE grupper (id serial, beskrivelse text, kode text, kodenr integer, art text, box1 text, box2 text, box3 text, box4 text, box5 text,
            box6 text, box7 text, box8 text, box9 text, box10 text, box11 text, box12 text, box13 text, box14 text, fiscal_year integer, PRIMARY KEY (id))");
        self::exec("CREATE TEMP TABLE kontoplan (id serial, kontonr numeric(15,0), beskrivelse text, kontotype varchar(1), moms text, fra_kto numeric(15,0),
            til_kto numeric(15,0), lukket varchar(2), primo numeric(15,3), saldo numeric(15,3), regnskabsaar integer, genvej varchar(2), overfor_til numeric(15,0),
            anvendelse text, modkonto numeric(15,0), valuta integer, valutakurs numeric(15,4), system_account boolean, account_group integer, map_to numeric(15,0),
            PRIMARY KEY (id))");
        self::exec("CREATE TEMP TABLE transaktioner (id serial, kontonr numeric(15,0), bilag numeric(15,0), transdate date, debet numeric(15,3), kredit numeric(15,3),
            PRIMARY KEY (id))");

        // Y = 17, calendar 2026, open for posting; one year-dependent group.
        self::exec("INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5, box10, fiscal_year) VALUES
            ('2026', '', 17, 'RA', '01', '2026', '12', '2026', 'on', '', NULL),
            ('Debitorer', 'D', 1, 'DG', '56100', '', '', '', '', '', 17)");
        // Opening of Y: 50905 30.000, 58200 20.000, 60900 -50.000 (balanced). Postings in 2026: a sale 10.000 (56100 / 1000),
        // a purchase 4.000 (1310 / 58200), an asset addition 1.907,87 on 50910, whose Overfør til is 50905 (50910 / 58200).
        // Resultat R = -10.000 + 4.000 = -6.000 goes to 60900, the Overfør til of the result account 20000. The posting on
        // 2025-12-31 is outside Y and counts for nothing.
        self::exec("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, primo, saldo, regnskabsaar, overfor_til, fra_kto, lukket) VALUES
            (1000, 'Salg', 'D', 0, -10000, 17, 0, 0, ''),
            (1310, 'Vareforbrug', 'D', 0, 4000, 17, 0, 0, ''),
            (1999, 'Driftsresultat', 'Z', 0, -6000, 17, 0, 1000, ''),
            (20000, 'Resultat', 'X', 0, 0, 17, 60900, 0, ''),
            (50905, 'Indretning kostpris primo', 'S', 30000, 30000, 17, 50905, 0, ''),
            (50910, 'Tilgang i året', 'S', 0, 1907.87, 17, 50905, 0, ''),
            (56100, 'Debitorer', 'S', 0, 10000, 17, 0, 0, ''),
            (58200, 'Bank', 'S', 20000, 14092.13, 17, 58200, 0, ''),
            (60900, 'Overført fra tidligere år', 'S', -50000, -50000, 17, 60900, 0, '')");
        self::exec("INSERT INTO transaktioner (kontonr, bilag, transdate, debet, kredit) VALUES
            (56100, 1, '2026-03-01', 10000, 0), (1000, 1, '2026-03-01', 0, 10000),
            (1310, 2, '2026-05-01', 4000, 0), (58200, 2, '2026-05-01', 0, 4000),
            (50910, 3, '2026-06-30', 1907.87, 0), (58200, 3, '2026-06-30', 0, 1907.87),
            (58200, 4, '2025-12-31', 999, 0), (1000, 4, '2025-12-31', 0, 999)");
    }

    private static function exec(string $sql): void
    {
        if (@pg_query(self::$conn, $sql) === false) {
            throw new \RuntimeException(pg_last_error(self::$conn) . ' in: ' . $sql);
        }
    }

    /**
     * @return array<string, string> kontonr => primo of the year, two decimals
     */
    private static function primo(int $year): array
    {
        $out = [];
        $q = db_select("select kontonr, primo from kontoplan where regnskabsaar = $year order by kontonr");
        while ($r = db_fetch_array($q)) {
            $out[(string) $r['kontonr']] = number_format((float) $r['primo'], 2, '.', '');
        }
        return $out;
    }

    public function test_a_new_year_opens_with_the_carry_forward_the_year_cards_gem_writes(): void
    {
        self::boot();

        $res = settings_fiscal_year_create(1, 2027, 12, 2027, '', true);

        self::assertNull($res['error']);
        self::assertSame(18, $res['kodenr']);
        self::assertSame([
            '1000' => '0.00',
            '1310' => '0.00',
            '1999' => '0.00',
            '20000' => '0.00',
            '50905' => '31907.87', // its own 30.000 plus 50910's 1.907,87 (Overfør til 50905)
            '50910' => '0.00',
            '56100' => '10000.00',
            '58200' => '14092.13',
            '60900' => '-56000.00', // -50.000 plus Resultat -6.000
        ], self::primo(18));
        $sum = db_fetch_array(db_select("select sum(primo) as s from kontoplan where regnskabsaar = 18 and kontotype = 'S'"));
        self::assertSame('0.00', number_format((float) $sum['s'], 2, '.', ''), 'Y+1 opens balanced');
        self::assertSame([17, 18], $GLOBALS['__fy_genberegn'], 'Y is recomputed before the copy, Y+1 after its opening is written');

        // Y is untouched, the year exists open for posting with its groups, and the card's Gem would write the same.
        self::assertSame(['1000' => '0.00', '1310' => '0.00', '1999' => '0.00', '20000' => '0.00', '50905' => '30000.00', '50910' => '0.00',
            '56100' => '0.00', '58200' => '20000.00', '60900' => '-50000.00'], self::primo(17));
        $ra = db_fetch_array(db_select("select beskrivelse, box1, box2, box3, box4, box5 from grupper where art = 'RA' and kodenr = 18"));
        self::assertSame(['beskrivelse' => '2027', 'box1' => '01', 'box2' => '2027', 'box3' => '12', 'box4' => '2027', 'box5' => 'on'], $ra);
        $dg = db_fetch_array(db_select("select count(*) as n from grupper where art = 'DG' and fiscal_year = 18"));
        self::assertSame('1', (string) $dg['n']);
        $before = self::primo(18);
        self::assertSame([18], updateFollowingOpeningBalances(17));
        self::assertSame($before, self::primo(18));
        self::assertSame([['company.fiscal_years.years#18', 'setting.row_created']], $GLOBALS['__fy_audit']);
    }

    public function test_without_an_account_for_the_result_the_new_year_keeps_last_years_balances_for_the_card(): void
    {
        self::boot();
        self::exec("UPDATE kontoplan SET overfor_til = 0 WHERE kontonr = 20000");

        $res = settings_fiscal_year_create(1, 2027, 12, 2027, 'Uden resultatkonto', true);

        self::assertNull($res['error']);
        // Nothing partial: every balance account keeps its own saldo, as before the carry-forward, until the card is saved.
        self::assertSame([
            '1000' => '0.00',
            '1310' => '0.00',
            '1999' => '0.00',
            '20000' => '0.00',
            '50905' => '30000.00',
            '50910' => '1907.87',
            '56100' => '10000.00',
            '58200' => '14092.13',
            '60900' => '-50000.00',
        ], self::primo(18));
    }
}
