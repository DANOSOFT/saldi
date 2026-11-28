<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
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
 * the copy keeps last year's balances for the card to settle, and the caller is told why.
 *
 * A chart without a Sideskift (X) account, like the standard chart (owner's decision 19 Nov 2026, "only the change goes
 * forward"): the result goes onto the first status account after the target year's first Resultat (R) account (6940
 * after 6935). A new year places it (6940 opens with minus the other openings, so the year balances and a result moved
 * into 6940 within last year is not counted twice); a later posting into last year moves 6940 only by the change it makes
 * to the other openings, through every following year, so a result moved in by hand in the new year is not doubled and
 * the opening's sum stays as it was. A target year with a Sideskift account that last year lacks gets the change, the
 * years after it the Sideskift carry. Without an R account, or a status account after it, nothing is written and the
 * reason is 'result'; deleted and missing years ('year'), a balance without an account ('status') and closed years work as
 * before. The queries run on a real Postgres against TEMP tables shaped like includes/opretfunc.php creates them, in the
 * empty database TestDatabase::sqlFixture() creates, so no tenant's tables are read or changed.
 *
 * History:
 * 20261105 CL/LH Created.
 * 20261119 CL/ASR A standard-like chart without a Sideskift account: a new year opens balanced with the result on 6940,
 *                 a result moved within the old year is not doubled, a posting into the old year moves 6940 by the
 *                 change only (also on a year opened without the result and repaired by hand, over three years, and into
 *                 a year with a Sideskift account added), no R account or no status account after it stops with the
 *                 reason 'result', and the boundaries (deleted, missing, status) and closed years keep working.
 * 20261119 CL/ASR A new year does not copy last year's warehouse rows (p1-yearend-F2).
 *                 The stubs share their connection as $connection, on which the carry's lock runs.
 * 20261124 CL/ASR The tests get their database from TestDatabase (sqlFixture), so CI runs them without SALDI_CHAR_DSN.
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
        require_once __DIR__ . '/../support/database/TestDatabase.php';
        $reason = TestDatabase::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        // Each test runs in its own process, so it gets an empty database of its own, dropped when the process ends.
        $conn = TestDatabase::connect(TestDatabase::sqlFixture(static::class)->name());
        self::$conn = $conn;
        $GLOBALS['__fy_conn'] = $conn;
        // The carry's lock runs its statements on the tenant connection directly, as includes/db_query.php names it.
        $GLOBALS['connection'] = $conn;
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
        require_once dirname(__DIR__, 3) . '/includes/updateFollowingOpeningBalances.php';

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
        $stop = 'not set';
        self::assertSame([18], updateFollowingOpeningBalances(17, OPENING_CARRY_DELTA, $stop));
        self::assertNull($stop, 'the walk reached the newest year');
        self::assertSame($before, self::primo(18), 'a Sideskift chart is carried the same way in either mode');
        self::assertSame([['company.fiscal_years.years#18', 'setting.row_created']], $GLOBALS['__fy_audit']);
    }

    public function test_a_new_year_does_not_copy_last_years_warehouse_rows(): void
    {
        // p1-yearend-F2: stock is keyed on grupper.kodenr and the warehouse lists read art 'LG' without fiscal_year, so a
        // copied warehouse row only listed the warehouse twice. The year card stopped copying them (SST-829,
        // systemdata/regnskabskort.php); Opret here still did. Before: warehouse 7 had a row for 2026 and one for 2027.
        self::boot();
        self::exec("INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box10, fiscal_year) VALUES
            ('Hovedlager', '', 7, 'LG', '', '', 17), ('Fjernlager', '', 8, 'LG', '', '', 0)");

        $res = settings_fiscal_year_create(1, 2027, 12, 2027, '', true);

        self::assertNull($res['error']);
        $warehouses = [];
        $q = db_select("select kodenr, fiscal_year from grupper where art = 'LG' order by kodenr, fiscal_year");
        while ($r = db_fetch_array($q)) {
            $warehouses[] = $r['kodenr'] . '/' . $r['fiscal_year'];
        }
        self::assertSame(['7/17', '8/0'], $warehouses, 'one row per warehouse, as before the new year');
        $dg = db_fetch_array(db_select("select count(*) as n from grupper where art = 'DG' and fiscal_year = 18"));
        self::assertSame('1', (string) $dg['n'], 'the year\'s other groups are still copied');
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
        // The caller can tell why: the Sideskift account's result has no account in 2027.
        $stop = 'not set';
        self::assertSame([], updateFollowingOpeningBalances(17, OPENING_CARRY_DELTA, $stop));
        self::assertSame(['reason' => 'result', 'sourceYear' => 17, 'targetYear' => 18, 'account' => 0], $stop);
    }

    // ------------------------------------------------------------------ a chart without a Sideskift (X) account

    /**
     * Replaces the fixture's chart with one shaped like the standard chart (importfiler/kontoplan.txt): no Sideskift (X)
     * account, the result shown by the Resultat (R) account 6935 (from the sum 4999) after the heading 6931, then 6940
     * Overført resultat, the result's destination, and 6960 Foreslået udbytte after it. 6910 Øvrige reserver is a status
     * account before the R account, 6960 and 7680 Salgsmoms are status accounts after 6940: none of them may take the result.
     *
     * 2026 (17) opens balanced: 5570 30.000 + 6480 20.000 - 6910 10.000 - 6940 40.000 = 0. Its postings: a sale of 12.500
     * (6190 debit 12.500 / 1010 credit 10.000 / 7680 credit 2.500), a purchase of 4.000 (1610 / 6480) and an asset
     * addition of 1.907,87 on 5580, whose Overfør til is 5570 (5580 / 6480). The result is -10.000 + 4.000 = -6.000 (a
     * profit). saldo is primo plus the year's postings, as genberegn() (a recorder here) leaves it:
     * 6480 20.000 - 4.000 - 1.907,87 = 14.092,13, and the status saldi sum to 6.000, minus the result.
     */
    private static function useStandardChart(): void
    {
        self::exec("DELETE FROM kontoplan");
        self::exec("DELETE FROM transaktioner");
        self::exec("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, primo, saldo, regnskabsaar, overfor_til, fra_kto, lukket) VALUES
            (1010, 'Salg af varer og ydelser', 'D', 0, -10000, 17, 0, 0, ''),
            (1610, 'Varekøb', 'D', 0, 4000, 17, 0, 0, ''),
            (4999, 'Årets resultat', 'Z', 0, -6000, 17, 0, 1010, ''),
            (5000, 'BALANCE', 'H', 0, 0, 17, 0, 0, ''),
            (5570, 'Inventar, bogført værdi primo', 'S', 30000, 30000, 17, 5570, 0, ''),
            (5580, 'Inventar, årets tilgange', 'S', 0, 1907.87, 17, 5570, 0, ''),
            (6190, 'Tilgodehavender fra salg og tjenesteydelser', 'S', 0, 12500, 17, 0, 0, ''),
            (6480, 'Bankkonto', 'S', 20000, 14092.13, 17, 6480, 0, ''),
            (6910, 'Øvrige reserver', 'S', -10000, -10000, 17, 6910, 0, ''),
            (6931, 'Overført resultat', 'H', 0, 0, 17, 0, 0, ''),
            (6935, 'Årets resultat', 'R', 0, -6000, 17, 0, 4999, ''),
            (6940, 'Overført resultat', 'S', -40000, -40000, 17, 6940, 0, ''),
            (6960, 'Foreslået udbytte', 'S', 0, 0, 17, 6960, 0, ''),
            (7680, 'Salgsmoms', 'S', 0, -2500, 17, 7680, 0, '')");
        self::exec("INSERT INTO transaktioner (kontonr, bilag, transdate, debet, kredit) VALUES
            (6190, 1, '2026-03-01', 12500, 0), (1010, 1, '2026-03-01', 0, 10000), (7680, 1, '2026-03-01', 0, 2500),
            (1610, 2, '2026-05-01', 4000, 0), (6480, 2, '2026-05-01', 0, 4000),
            (5580, 3, '2026-06-30', 1907.87, 0), (6480, 3, '2026-06-30', 0, 1907.87)");
    }

    /**
     * @return array<string, string> The status accounts' openings of a year on the standard-like chart, two decimals.
     */
    private static function statusPrimo(int $year): array
    {
        return array_intersect_key(self::primo($year), array_flip(['5570', '5580', '6190', '6480', '6910', '6940', '6960', '7680']));
    }

    private static function statusPrimoSum(int $year): string
    {
        $sum = db_fetch_array(db_select("select sum(primo) as s from kontoplan where regnskabsaar = $year and kontotype = 'S'"));
        return number_format((float) $sum['s'], 2, '.', '');
    }

    /**
     * What a year's balance report is off by: the status openings plus the year's postings on status and drift accounts,
     * 0.00 when balanced. A posting on the Resultat account 6935 shows on no line (its line is the sum 4999 of the drift
     * accounts), so a hand move D 6935 / K 6940 adds only its 6940 side.
     */
    private static function balanceOff(int $year, string $from, string $to): string
    {
        $r = db_fetch_array(db_select("select (select coalesce(sum(primo), 0) from kontoplan where regnskabsaar = $year and kontotype = 'S')
            + (select coalesce(sum(t.debet - t.kredit), 0) from transaktioner t join kontoplan k on k.kontonr = t.kontonr and k.regnskabsaar = $year
               and k.kontotype in ('S', 'D') where t.transdate between '$from' and '$to') as d"));
        return number_format((float) $r['d'], 2, '.', '');
    }

    /**
     * A late sale into 2026 after 2027 exists, as finans/bogfor.php posts it: 6190 debit 125 / 1010 credit 100 / 7680
     * credit 25. Its result is -100.
     */
    private static function postALateSaleInto2026(): void
    {
        self::exec("INSERT INTO transaktioner (kontonr, bilag, transdate, debet, kredit) VALUES
            (6190, 9, '2026-12-31', 125, 0), (1010, 9, '2026-12-31', 0, 100), (7680, 9, '2026-12-31', 0, 25)");
    }

    /**
     * 2027's opening as the code before the carry wrote it: each status account with its own 2026 saldo, the result on no
     * account.
     */
    private static function openAsBeforeTheCarry(): void
    {
        self::exec("UPDATE kontoplan SET primo = s.saldo FROM kontoplan s
            WHERE s.regnskabsaar = 17 AND kontoplan.regnskabsaar = 18 AND s.kontonr = kontoplan.kontonr AND kontoplan.kontotype = 'S'");
    }

    /** 2027 as settings_fiscal_year_create() opens it from the standard-like fixture: the status accounts carried, the result on 6940. */
    private const OPENING_2027 = [
        '5570' => '31907.87', // 30.000 plus 5580's 1.907,87 (Overfør til 5570)
        '5580' => '0.00',
        '6190' => '12500.00',
        '6480' => '14092.13',
        '6910' => '-10000.00', // before the R account: its own balance only
        // minus the other openings: 31.907,87 + 12.500 + 14.092,13 - 10.000 - 2.500 = 46.000, which is its own -40.000 plus the result -6.000
        '6940' => '-46000.00',
        '6960' => '0.00', // after 6940: not the destination
        '7680' => '-2500.00',
    ];

    public function test_without_a_sideskift_account_a_new_year_opens_balanced_with_the_result_on_6940(): void
    {
        self::boot();
        self::useStandardChart();

        $res = settings_fiscal_year_create(1, 2027, 12, 2027, '', true);

        self::assertNull($res['error']);
        self::assertSame(18, $res['kodenr']);
        self::assertSame(self::OPENING_2027, self::statusPrimo(18));
        self::assertSame(['1010' => '0.00', '1610' => '0.00', '4999' => '0.00', '6935' => '0.00'],
            array_intersect_key(self::primo(18), array_flip(['1010', '1610', '4999', '6935'])), 'drift, sum and R accounts open at 0');
        self::assertSame('0.00', self::statusPrimoSum(18), '2027 opens balanced: 31.907,87 + 12.500 + 14.092,13 - 10.000 - 46.000 - 2.500 = 0');
        self::assertSame([17, 18], $GLOBALS['__fy_genberegn']);
        self::assertSame('20000.00', self::primo(17)['6480'], '2026 is untouched');
        // Delta mode once more (as Gem on the card or a posting into 2026 runs it) changes nothing.
        self::assertSame([18], updateFollowingOpeningBalances(17));
        self::assertSame(self::OPENING_2027, self::statusPrimo(18));
        $stop = 'not set';
        self::assertSame([18], updateFollowingOpeningBalances(17, OPENING_CARRY_DELTA, $stop));
        self::assertNull($stop);
        self::assertSame(6940, resultDestinationWithoutSideskift(18), 'the first status account after 6935, not 6910 before it or 6960 after it');
    }

    public function test_a_result_moved_into_6940_within_the_old_year_is_not_doubled_when_the_new_year_is_created(): void
    {
        self::boot();
        self::useStandardChart();
        // The bookkeeper closes the 2026 result into equity in 2026: 6935 debit 6.000 / 6940 credit 6.000, so 6940's 2026
        // saldo is -40.000 - 6.000 = -46.000. The R account's 6.000 debit is neither a status nor a drift posting.
        self::exec("INSERT INTO transaktioner (kontonr, bilag, transdate, debet, kredit) VALUES
            (6935, 5, '2026-12-31', 6000, 0), (6940, 5, '2026-12-31', 0, 6000)");
        self::exec("UPDATE kontoplan SET saldo = -46000 WHERE kontonr = 6940 AND regnskabsaar = 17");

        settings_fiscal_year_create(1, 2027, 12, 2027, '', true);

        // 6940 is still minus the other openings, -46.000: its carried -46.000 plus the result -6.000 plus the R account's
        // +6.000. Adding the result to 6940's carried balance would give -52.000 and open 2027 off by -6.000.
        self::assertSame(self::OPENING_2027, self::statusPrimo(18));
        self::assertSame('0.00', self::statusPrimoSum(18));
    }

    public function test_a_posting_into_the_old_year_moves_only_its_change_onto_6940(): void
    {
        self::boot();
        self::useStandardChart();
        settings_fiscal_year_create(1, 2027, 12, 2027, '', true);
        self::postALateSaleInto2026();

        // finans/bogfor.php's call: the default mode.
        self::assertSame([18], updateFollowingOpeningBalances(17));

        $expected = self::OPENING_2027;
        $expected['6190'] = '12625.00'; // 12.500 + 125
        $expected['7680'] = '-2525.00'; // -2.500 - 25
        $expected['6940'] = '-46100.00'; // -46.000 less the other openings' change, 125 - 25 = 100: the sale's result -100
        self::assertSame($expected, self::statusPrimo(18));
        self::assertSame('0.00', self::statusPrimoSum(18), 'the sum is unchanged');
        self::assertSame('0.00', self::primo(17)['6190'], 'the posted year itself is untouched');
        $stop = 'not set';
        self::assertSame([18], updateFollowingOpeningBalances(17, OPENING_CARRY_DELTA, $stop), 'once more: no change');
        self::assertNull($stop);
        self::assertSame($expected, self::statusPrimo(18));
    }

    public function test_a_year_opened_without_the_result_and_repaired_by_hand_gets_only_the_change(): void
    {
        self::boot();
        self::useStandardChart();
        settings_fiscal_year_create(1, 2027, 12, 2027, '', true);
        // 2027 as the code before opened it: each status account with its own 2026 saldo (5580 not merged into 5570), the
        // result on no account, so the opening sums to 30.000 + 1.907,87 + 12.500 + 14.092,13 - 10.000 - 40.000 - 2.500
        // = 6.000. The bookkeeper repaired the year by hand with a posting dated in it: 6935 debit 6.000 / 6940 credit 6.000.
        self::openAsBeforeTheCarry();
        self::exec("INSERT INTO transaktioner (kontonr, bilag, transdate, debet, kredit) VALUES
            (6935, 6, '2027-01-02', 6000, 0), (6940, 6, '2027-01-02', 0, 6000)");
        self::assertSame('6000.00', self::statusPrimoSum(18), 'precondition');
        self::assertSame('0.00', self::balanceOff(18, '2027-01-01', '2027-12-31'), 'precondition: 6.000 - 6.000, the hand repair balances 2027');

        self::postALateSaleInto2026();
        self::assertSame([18], updateFollowingOpeningBalances(17));

        self::assertSame([
            '5570' => '31907.87', // 30.000 + 1.907,87: 5580 now lands on its Overfør til (+1.907,87)
            '5580' => '0.00', // 1.907,87 - 1.907,87
            '6190' => '12625.00', // 12.500 + 125
            '6480' => '14092.13',
            '6910' => '-10000.00',
            // -40.000 less the change 1.907,87 - 1.907,87 + 125 - 25 = 100; placing the result instead would give -46.100
            // and count the hand repair's 6.000 twice
            '6940' => '-40100.00',
            '6960' => '0.00',
            '7680' => '-2525.00', // -2.500 - 25
        ], self::statusPrimo(18));
        self::assertSame('6000.00', self::statusPrimoSum(18), 'the sum stays as it was');
        self::assertSame('0.00', self::balanceOff(18, '2027-01-01', '2027-12-31'), '2027 stays balanced by its repair');
    }

    public function test_a_posting_into_the_first_of_three_years_moves_6940_by_the_same_amount_in_each_following_year(): void
    {
        self::boot();
        self::useStandardChart();
        settings_fiscal_year_create(1, 2027, 12, 2027, '', true);
        // 2027 has a sale on account of 500 (6190 / 1010): its result is -500.
        self::exec("INSERT INTO transaktioner (kontonr, bilag, transdate, debet, kredit) VALUES
            (6190, 7, '2027-04-01', 500, 0), (1010, 7, '2027-04-01', 0, 500)");
        settings_fiscal_year_create(1, 2028, 12, 2028, '', true);
        $opening2028 = self::OPENING_2027;
        $opening2028['6190'] = '13000.00'; // 12.500 + 500
        $opening2028['6940'] = '-46500.00'; // -46.000 plus the 2027 result -500
        self::assertSame($opening2028, self::statusPrimo(19), 'precondition: 2028 created in place mode');

        self::postALateSaleInto2026();
        self::assertSame([18, 19], updateFollowingOpeningBalances(17));

        $p2027 = self::statusPrimo(18);
        $p2028 = self::statusPrimo(19);
        // 2027: 12.500 + 125, -2.500 - 25, and 6940 -46.000 - 100.
        self::assertSame(['12625.00', '-2525.00', '-46100.00'], [$p2027['6190'], $p2027['7680'], $p2027['6940']]);
        // 2028: 13.000 + 125, -2.500 - 25, and 6940 -46.500 - 100: the same 100 as 2027.
        self::assertSame(['13125.00', '-2525.00', '-46600.00'], [$p2028['6190'], $p2028['7680'], $p2028['6940']]);
        self::assertSame(['0.00', '0.00'], [self::statusPrimoSum(18), self::statusPrimoSum(19)]);
    }

    public function test_a_sideskift_account_added_in_a_later_year_takes_the_change_and_carries_it_on(): void
    {
        self::boot();
        self::useStandardChart();
        settings_fiscal_year_create(1, 2027, 12, 2027, '', true);
        // The bookkeeper adds a Sideskift account to 2027's chart, with Overfør til 6940; the R account 6935 stays.
        self::exec("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, primo, saldo, regnskabsaar, overfor_til, fra_kto, lukket) VALUES
            (4998, 'Sideskift', 'X', 0, 0, 18, 6940, 0, '')");
        self::exec("INSERT INTO transaktioner (kontonr, bilag, transdate, debet, kredit) VALUES
            (6190, 7, '2027-04-01', 500, 0), (1010, 7, '2027-04-01', 0, 500)");
        // 2028 from 2027 goes by the Sideskift account: 6190 12.500 + 500, 6940 -46.000 plus the 2027 result -500.
        settings_fiscal_year_create(1, 2028, 12, 2028, '', true);
        self::assertSame(['13000.00', '-46500.00'], [self::statusPrimo(19)['6190'], self::statusPrimo(19)['6940']], 'precondition');

        self::postALateSaleInto2026();
        self::assertSame([18, 19], updateFollowingOpeningBalances(17));

        $p2027 = self::statusPrimo(18);
        $p2028 = self::statusPrimo(19);
        // 2026 has no Sideskift account: 2027 moves by the change, 12.500 + 125, -2.500 - 25 and 6940 -46.000 - 100.
        self::assertSame(['12625.00', '-2525.00', '-46100.00'], [$p2027['6190'], $p2027['7680'], $p2027['6940']]);
        // 2027 has one: 2028's 6940 is 2027's new -46.100 plus the 2027 result -500, so the late sale counts once.
        self::assertSame(['13125.00', '-2525.00', '-46600.00'], [$p2028['6190'], $p2028['7680'], $p2028['6940']]);
        self::assertSame(['0.00', '0.00'], [self::statusPrimoSum(18), self::statusPrimoSum(19)]);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function resultatAccounts(): array
    {
        return [
            'no Resultat account' => ["DELETE FROM kontoplan WHERE kontonr = 6935", 0],
            'no status account after the Resultat account' => ["UPDATE kontoplan SET kontonr = 9999 WHERE kontonr = 6935", 0],
            // The first R account by number counts: with 6000 before 6935, 6190 is the first status account after it.
            'an earlier Resultat account' => ["INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, primo, saldo, regnskabsaar, overfor_til, fra_kto, lukket)
                VALUES (6000, 'Resultat', 'R', 0, 0, 17, 0, 4999, '')", 6190],
        ];
    }

    #[DataProvider('resultatAccounts')]
    public function test_the_result_goes_to_the_first_status_account_after_the_first_resultat_account_or_nowhere(string $chart, int $destination): void
    {
        self::boot();
        self::useStandardChart();
        self::exec($chart);

        settings_fiscal_year_create(1, 2027, 12, 2027, '', true);

        if ($destination) {
            // 6190 opens with minus the other openings: -(31.907,87 + 14.092,13 - 10.000 - 40.000 - 2.500) = 6.500, its own
            // 12.500 plus the result -6.000; 6940 keeps its own -40.000.
            self::assertSame(['6500.00', '-40000.00', '0.00'], [self::statusPrimo(18)['6190'], self::statusPrimo(18)['6940'], self::statusPrimoSum(18)]);
        } else {
            // Nothing written: the copy keeps each status account's own 2026 saldo (5580's 1.907,87 not merged into 5570),
            // so 2027 is off by the result: 30.000 + 1.907,87 + 12.500 + 14.092,13 - 10.000 - 40.000 - 2.500 = 6.000.
            self::assertSame(['30000.00', '1907.87', '-40000.00'], [self::statusPrimo(18)['5570'], self::statusPrimo(18)['5580'], self::statusPrimo(18)['6940']]);
            self::assertSame('6000.00', self::statusPrimoSum(18));
        }
        $stop = 'not set';
        self::assertSame($destination ? [18] : [], updateFollowingOpeningBalances(17, OPENING_CARRY_DELTA, $stop));
        self::assertSame($destination ? null : ['reason' => 'result', 'sourceYear' => 17, 'targetYear' => 18, 'account' => 0], $stop);
        self::assertSame($destination, resultDestinationWithoutSideskift(18));
    }

    public function test_a_target_year_without_a_resultat_account_is_a_boundary_with_the_reason_result(): void
    {
        self::boot();
        self::useStandardChart();
        settings_fiscal_year_create(1, 2027, 12, 2027, '', true);
        self::assertSame(self::OPENING_2027, self::statusPrimo(18), 'precondition');
        // 2026 keeps its 6935, but 2027's chart no longer has it: the rule reads the target year's chart.
        self::exec("DELETE FROM kontoplan WHERE kontonr = 6935 AND regnskabsaar = 18");
        self::postALateSaleInto2026();

        $stop = 'not set';
        self::assertSame([], updateFollowingOpeningBalances(17, OPENING_CARRY_DELTA, $stop));

        self::assertSame(self::OPENING_2027, self::statusPrimo(18), 'nothing is written: 6190 stays 12.500 without the late 125');
        self::assertSame(['reason' => 'result', 'sourceYear' => 17, 'targetYear' => 18, 'account' => 0], $stop);
        self::assertSame(0, resultDestinationWithoutSideskift(18));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function deletedMarkers(): array
    {
        return ['deleted by older code (box10 on)' => ['on'], 'deleted by deleteFiscalYear.php (a timestamp, chart deleted)' => ['1700000000']];
    }

    #[DataProvider('deletedMarkers')]
    public function test_a_deleted_year_is_a_boundary_with_the_reason_year(string $box10): void
    {
        self::boot();
        self::useStandardChart();
        settings_fiscal_year_create(1, 2027, 12, 2027, '', true);
        settings_fiscal_year_create(1, 2028, 12, 2028, '', true);
        self::assertSame(self::OPENING_2027, self::statusPrimo(18), 'precondition: 2027 created in place mode');
        $opening2028 = self::statusPrimo(19);
        self::exec("UPDATE grupper SET box10 = '$box10' WHERE art = 'RA' AND kodenr = 18");
        if ($box10 !== 'on') {
            self::exec("DELETE FROM kontoplan WHERE regnskabsaar = 18");
        }
        self::postALateSaleInto2026();

        self::assertSame([], updateFollowingOpeningBalances(17), 'into the deleted year');
        self::assertSame([], updateFollowingOpeningBalances(18), 'from the deleted year');
        self::assertSame($opening2028, self::statusPrimo(19), '2028 is untouched');
        if ($box10 === 'on') {
            self::assertSame(self::OPENING_2027, self::statusPrimo(18), '2027 is untouched');
        }
        $stop = 'not set';
        updateFollowingOpeningBalances(17, OPENING_CARRY_DELTA, $stop);
        self::assertSame(['reason' => 'year', 'sourceYear' => 17, 'targetYear' => 18, 'account' => 0], $stop);
        updateFollowingOpeningBalances(18, OPENING_CARRY_DELTA, $stop);
        self::assertSame(['reason' => 'year', 'sourceYear' => 18, 'targetYear' => 19, 'account' => 0], $stop);
    }

    public function test_a_closed_year_a_balance_without_an_account_and_a_missing_year_work_as_before(): void
    {
        self::boot();
        self::useStandardChart();
        // 2027 is closed for posting (box5 empty); it is still carried into, and stays closed.
        settings_fiscal_year_create(1, 2027, 12, 2027, '', false);
        settings_fiscal_year_create(1, 2028, 12, 2028, '', true);
        self::postALateSaleInto2026();
        self::assertSame([18, 19], updateFollowingOpeningBalances(17));
        self::assertSame('', db_fetch_array(db_select("select box5 from grupper where art = 'RA' and kodenr = 18"))['box5'], '2027 stays closed');
        self::assertSame(['-46100.00', '-46100.00'], [self::statusPrimo(18)['6940'], self::statusPrimo(19)['6940']], '6940 -46.000 - 100 in both');
        $stop = 'not set';
        self::assertSame([18, 19], updateFollowingOpeningBalances(17, OPENING_CARRY_DELTA, $stop));
        self::assertNull($stop);
        // The newest year has nothing after it.
        self::assertSame([], updateFollowingOpeningBalances(19, OPENING_CARRY_DELTA, $stop));
        self::assertNull($stop);

        // A 2026 status account without postings that 2027's chart lacks, with no Overfør til: its balance has no account.
        // A customer's payment of 300 into 2026 (6480 / 6190) is then carried nowhere.
        self::exec("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, primo, saldo, regnskabsaar, overfor_til, fra_kto, lukket) VALUES
            (5990, 'Deposita', 'S', 0, 0, 17, 0, 0, '')");
        self::exec("INSERT INTO transaktioner (kontonr, bilag, transdate, debet, kredit) VALUES (6480, 10, '2026-12-31', 300, 0), (6190, 10, '2026-12-31', 0, 300)");
        self::assertSame([], updateFollowingOpeningBalances(17, OPENING_CARRY_DELTA, $stop));
        self::assertSame(['reason' => 'status', 'sourceYear' => 17, 'targetYear' => 18, 'account' => 5990], $stop);
        self::assertSame('12625.00', self::statusPrimo(18)['6190'], 'nothing written: 12.500 + 125, without the payment\'s -300');
        self::exec("DELETE FROM kontoplan WHERE kontonr = 5990");

        // 2027 missing between 2026 and 2028: neither 2027 nor 2028 is written.
        self::exec("DELETE FROM grupper WHERE art = 'RA' AND kodenr = 18");
        self::assertSame([], updateFollowingOpeningBalances(17, OPENING_CARRY_DELTA, $stop));
        self::assertSame(['reason' => 'year', 'sourceYear' => 17, 'targetYear' => 18, 'account' => 0], $stop);
        // Both stay 12.500 + 125 from the late sale (2027 has no postings here): the payment's -300 reaches neither.
        self::assertSame(['12625.00', '12625.00'], [self::statusPrimo(18)['6190'], self::statusPrimo(19)['6190']], 'the payment of 300 is carried nowhere');
    }

    /**
     * @return array<string, array{0: int, 1: array<int, int>, 2: array<string, string>, 3: string|null}>
     */
    public static function sideskiftCharts(): array
    {
        return [
            // 50905 30.000 + 50910's 1.907,87; 60900 -50.000 plus the result -6.000 through the Sideskift account 20000.
            'Sideskift with an Overfør til' => [60900, [18], ['50905' => '31907.87', '50910' => '0.00', '60900' => '-56000.00'], null],
            // Nothing carried: the copy's own saldi, and the Resultat account 50000 does not stand in for the Overfør til.
            'Sideskift without an Overfør til' => [0, [], ['50905' => '30000.00', '50910' => '1907.87', '60900' => '-50000.00'], 'result'],
        ];
    }

    /**
     * @param array<int, int> $updated
     * @param array<string, string> $expected
     */
    #[DataProvider('sideskiftCharts')]
    public function test_a_chart_with_a_sideskift_account_ignores_its_resultat_account(int $overforTil, array $updated, array $expected, ?string $reason): void
    {
        self::boot();
        // A Resultat account 50000 before the status account 50905: with a Sideskift account it must not take the result.
        self::exec("UPDATE kontoplan SET overfor_til = $overforTil WHERE kontonr = 20000");
        self::exec("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, primo, saldo, regnskabsaar, overfor_til, fra_kto, lukket) VALUES
            (50000, 'Årets resultat', 'R', 0, -6000, 17, 0, 1999, '')");

        settings_fiscal_year_create(1, 2027, 12, 2027, '', true);

        $primo = self::primo(18);
        self::assertSame($expected, ['50905' => $primo['50905'], '50910' => $primo['50910'], '60900' => $primo['60900']]);
        self::assertSame($updated, updateFollowingOpeningBalances(17));
        $primo = self::primo(18);
        self::assertSame($expected, ['50905' => $primo['50905'], '50910' => $primo['50910'], '60900' => $primo['60900']]);
        $stop = 'not set';
        self::assertSame($updated, updateFollowingOpeningBalances(17, OPENING_CARRY_DELTA, $stop));
        self::assertSame($reason, $stop === null ? null : $stop['reason']);
    }
}
