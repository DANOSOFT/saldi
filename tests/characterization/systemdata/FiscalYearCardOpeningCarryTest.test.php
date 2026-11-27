<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Gem/opdatér on the year card (systemdata/regnskabskort.php) of a chart without a Sideskift (X) account keeps last
 * year's result in the opening (owner's decision 19 Nov 2026, "only the change goes forward").
 *
 * The standard chart (importfiler/kontoplan.txt) has no X account, so the card has no Resultat row. Its Gem set the
 * year's opening to 0 and added the rows it posts, so it took last year's result off 6940 Overført resultat: a year
 * Indstillinger had just opened balanced was out of balance by last year's result after one press on Gem, and a late
 * posting into last year that had reached the opening lost its result again.
 *
 * Pins: on such a chart the Gem saves the Overfør til of every row (both years, as the rows do) and then writes the
 * opening with updateFollowingOpeningBalances() in delta mode: the balance accounts by that Overfør til, and 6940 moved by
 * the change only, so the result stays and a result moved in by hand is not doubled. Only where that carry stops before
 * the year (a boundary, here no Resultat account in the year's chart) do the rows write the opening, as before. The old
 * card's creation path (POST without an id, the chart copied from last year) places the result as Indstillinger's Opret
 * does, so the new year opens balanced. A chart with an X account is saved from its Resultat row as before. The page runs
 * in a child process against stub includes, the real includes/std_func.php and includes/updateFollowingOpeningBalances.php,
 * and TEMP tables on the Postgres behind SALDI_CHAR_DSN, so nothing in that database is read or changed.
 *
 * Where the carry stops at the year because its chart has no Resultat account with a status account after it, the rows
 * write the opening without the result as before, and the card shows a warning saying so (p1-yearend-F1, owner's decision
 * 19 Nov 2026). The save takes the opening-balance carry's lock before its transaction (openingCarryLockBeforeTransaction(),
 * p1-pos-close-R1). The warning says what the rows wrote: what they left out with its amount, or that nothing of the
 * result is missing when it was already moved onto 6940 by hand within last year (by closing the drift accounts, or by
 * D 6935 Årets resultat / K 6940, which the warning counts as the carry's place rule does), and the opening's sum. It also
 * comes where the carry stops because a status account of last year has no account in the year, which drops a result
 * already placed in the opening (review round 2). The save keeps the lock until the card has recalculated the year
 * (genberegn()) after its commit, so a POS day close waiting for it does not take its snapshot in between and fail on a row
 * the recalculation rewrites (p1-pos-close-N1).
 *
 * History:
 * 20261119 CL/ASR Created (harness from the review of the earlier year-end attempt).
 * 20261119 CL/ASR The warning when the rows write the opening without the result (p1-yearend-F1).
 *                 The stub connection is also $connection, on which the carry's lock runs.
 * 20261119 CL/ASR The warning names what the rows left out and the opening's sum, says nothing is missing when the result is already on 6940, and comes at a 'status' stop too (review round 2).
 * 20261119 CL/ASR A result closed by D 6935 / K 6940 within 2026, wholly or in part, is not reported as left out, at a 'status' stop or a 'result' stop (review round 6).
 * 20261120 CL/ASR Gem holds the carry's lock while the card recalculates the year, and lets go of it before the card is drawn (p1-pos-close-N1).
 */
final class FiscalYearCardOpeningCarryTest extends TestCase
{
    private string $fixture;

    /** The status accounts of the fixture's chart, in account number order. */
    private const STATUS = ['5570', '5580', '6190', '6480', '6910', '6940', '6960', '7680'];

    protected function setUp(): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('the pgsql extension is required to save the card.');
        }
        if ((string) getenv('SALDI_CHAR_DSN') === '') {
            self::markTestSkipped('set SALDI_CHAR_DSN, SALDI_CHAR_PGUSER and SALDI_CHAR_PGPASS to save the card.');
        }
        $this->fixture = sys_get_temp_dir() . '/saldi-yearcard-' . bin2hex(random_bytes(8));
        mkdir($this->fixture . '/systemdata', 0700, true);
        mkdir($this->fixture . '/includes/oldDesign', 0700, true);
        $stdFunc = var_export(dirname(__DIR__, 3) . '/includes/std_func.php', true);
        // The page includes these by bare relative paths, so they resolve from the child's working directory. connect.php
        // opens the connection, creates the TEMP tables and loads fixture.sql; on exit it prints every year's primo and
        // Overfør til.
        $stubs = [
            'includes/connect.php' => '<?php
                if (!function_exists("db_select")) {
                    $GLOBALS["__card_conn"] = pg_connect((string) getenv("SALDI_CARD_PG"));
                    $GLOBALS["connection"] = $GLOBALS["__card_conn"];
                    function db_escape_string($s) { return pg_escape_string($GLOBALS["__card_conn"], (string) $s); }
                    function db_select($q, $where = "") {
                        $r = @pg_query($GLOBALS["__card_conn"], $q);
                        if ($r === false) {
                            throw new \RuntimeException(pg_last_error($GLOBALS["__card_conn"]) . " in: " . $q);
                        }
                        return $r;
                    }
                    function db_modify($q, $where = "", $noErr = false) { return db_select($q, $where); }
                    function db_fetch_array($r) { return pg_fetch_assoc($r); }
                    function transaktion($t) { db_select($t); }
                    function findtekst($textId, $languageID) { return substr((string) $textId, strpos((string) $textId, "|") + 1); }
                    foreach (explode(";\n", (string) file_get_contents(__DIR__ . "/../fixture.sql")) as $sql) {
                        if (trim($sql) !== "") {
                            db_select($sql);
                        }
                    }
                    register_shutdown_function(static function () {
                        $out = ["primo" => [], "overfor" => []];
                        $q = db_select("select regnskabsaar, kontonr, primo, overfor_til from kontoplan order by regnskabsaar, kontonr");
                        while ($r = db_fetch_array($q)) {
                            $out["primo"][$r["regnskabsaar"]][$r["kontonr"]] = number_format((float) $r["primo"], 2, ".", "");
                            $out["overfor"][$r["regnskabsaar"]][$r["kontonr"]] = (string) (int) $r["overfor_til"];
                        }
                        echo "\n##STATE##" . json_encode($out);
                    });
                }',
            'includes/online.php' => '<?php $sprog_id = 1; $menu = "S"; $bruger_id = 1; $brugernavn = "test"; $revisor = 0;',
            'includes/std_func.php' => '<?php require_once ' . $stdFunc . ';',
            'includes/genberegn.php' => '<?php function genberegn($year, $updateUsage = true) {}',
            'includes/topline_settings.php' => '<?php $buttonStyle = "";',
            'includes/oldDesign/footer.php' => '<?php echo "[footer]";',
            'systemdata/top.php' => '<?php echo "[top]";',
        ];
        foreach ($stubs as $path => $code) {
            file_put_contents($this->fixture . '/' . $path, $code);
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->fixture)) {
            return;
        }
        foreach (array_merge(glob($this->fixture . '/sess_*'), glob($this->fixture . '/fixture.sql'), glob($this->fixture . '/includes/oldDesign/*.php'), glob($this->fixture . '/includes/*.php'), glob($this->fixture . '/systemdata/*.php')) as $file) {
            unlink($file);
        }
        rmdir($this->fixture . '/includes/oldDesign');
        rmdir($this->fixture . '/includes');
        rmdir($this->fixture . '/systemdata');
        rmdir($this->fixture);
    }

    /**
     * A ledger on a chart shaped like the standard chart: 2026 (kodenr 17, grupper id 1) and, unless $opening2027 is null,
     * 2027 (kodenr 18, grupper id 2), both open. 2026 opens balanced with 5570 30.000, 6480 20.000, 6910 -10.000 and 6940
     * -40.000 and has a sale of 12.500 (6190 / 1010 10.000 / 7680 2.500), a purchase of 4.000 (1610 / 6480) and an asset
     * addition of 1.907,87 on 5580 (5580 / 6480): the result is -6.000. Its saldo is primo plus those postings (the old
     * card's creation path copies it): 6480 20.000 - 4.000 - 1.907,87 = 14.092,13.
     *
     * @param array<string, string>|null $opening2027 2027's status openings by account number, or null for no 2027 yet.
     * @param string $overfor5580 5580's Overfør til in both years.
     * @param string $resultAccounts More chart rows for both years (kontonr, beskrivelse, kontotype, overfor_til, fra_kto).
     * @param string $morePostings More transaktioner rows (kontonr, bilag, transdate, debet, kredit), or ''.
     * @param string $only2026 More chart rows for 2026 only (kontonr, beskrivelse, kontotype, overfor_til, fra_kto), or ''.
     * @return array<int, string>
     */
    private static function ledger(?array $opening2027, string $overfor5580, string $resultAccounts, string $morePostings, string $only2026 = ''): array
    {
        $sql = [
            "CREATE TEMP TABLE grupper (id serial, beskrivelse text, kode text, kodenr integer, art text, box1 text, box2 text, box3 text, box4 text, box5 text,
                box6 text, box7 text, box8 text, box9 text, box10 text, box11 text, box12 text, box13 text, box14 text, fiscal_year integer, PRIMARY KEY (id))",
            "CREATE TEMP TABLE kontoplan (id serial, kontonr numeric(15,0), beskrivelse text, kontotype varchar(1), moms text, fra_kto numeric(15,0),
                til_kto numeric(15,0), lukket varchar(2), primo numeric(15,3), saldo numeric(15,3), regnskabsaar integer, genvej varchar(2), overfor_til numeric(15,0),
                valuta integer, PRIMARY KEY (id))",
            "CREATE TEMP TABLE transaktioner (id serial, kontonr numeric(15,0), bilag numeric(15,0), transdate date, debet numeric(15,3), kredit numeric(15,3),
                PRIMARY KEY (id))",
            "CREATE TEMP TABLE online (session_id text, regnskabsaar integer)",
            "CREATE TEMP TABLE brugere (id integer, regnskabsaar integer)",
            "INSERT INTO brugere (id, regnskabsaar) VALUES (1, 17)",
            "INSERT INTO grupper (id, beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5, box9, box10, fiscal_year) VALUES
                (1, '2026', '', 17, 'RA', '01', '2026', '12', '2026', 'on', '', '', NULL),
                (3, 'Debitorer', 'D', 1, 'DG', '6190', '', '', '', '', '', '', 17)",
            "SELECT setval('grupper_id_seq', 100)",
        ];
        $years = [17 => ['30000', '0', '0', '20000', '-10000', '-40000', '0', '0']];
        $saldo = ['30000', '1907.87', '12500', '14092.13', '-10000', '-40000', '0', '-2500'];
        if ($opening2027 !== null) {
            $sql[] = "INSERT INTO grupper (id, beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5, box9, box10, fiscal_year) VALUES
                (2, '2027', '', 18, 'RA', '01', '2027', '12', '2027', 'on', '', '', NULL),
                (4, 'Debitorer', 'D', 1, 'DG', '6190', '', '', '', '', '', '', 18)";
            $years[18] = array_values($opening2027);
        }
        foreach ($years as $year => $p) {
            $s = $year === 17 ? $saldo : array_fill(0, 8, '0');
            $sql[] = "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, primo, saldo, regnskabsaar, overfor_til, fra_kto, til_kto, lukket, genvej, valuta) VALUES
                (1010, 'Salg af varer og ydelser', 'D', '', 0, " . ($year === 17 ? '-10000' : '0') . ", $year, 0, 0, 0, '', '', 0),
                (1610, 'Varekøb', 'D', '', 0, " . ($year === 17 ? '4000' : '0') . ", $year, 0, 0, 0, '', '', 0),
                (4999, 'Årets resultat', 'Z', '', 0, 0, $year, 0, 1010, 0, '', '', 0),
                (5570, 'Inventar, bogført værdi primo', 'S', '', $p[0], $s[0], $year, 5570, 0, 0, '', '', 0),
                (5580, 'Inventar, årets tilgange', 'S', '', $p[1], $s[1], $year, $overfor5580, 0, 0, '', '', 0),
                (6190, 'Tilgodehavender fra salg og tjenesteydelser', 'S', '', $p[2], $s[2], $year, 6190, 0, 0, '', '', 0),
                (6480, 'Bankkonto', 'S', '', $p[3], $s[3], $year, 6480, 0, 0, '', '', 0),
                (6910, 'Øvrige reserver', 'S', '', $p[4], $s[4], $year, 6910, 0, 0, '', '', 0),
                (6931, 'Overført resultat', 'H', '', 0, 0, $year, 0, 0, 0, '', '', 0),
                (6940, 'Overført resultat', 'S', '', $p[5], $s[5], $year, 6940, 0, 0, '', '', 0),
                (6960, 'Foreslået udbytte', 'S', '', $p[6], $s[6], $year, 6960, 0, 0, '', '', 0),
                (7680, 'Salgsmoms', 'S', '', $p[7], $s[7], $year, 7680, 0, 0, '', '', 0)";
            foreach (array_filter([$resultAccounts, $year === 17 ? $only2026 : '']) as $accounts) {
                $sql[] = "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, overfor_til, fra_kto, primo, saldo, regnskabsaar, til_kto, lukket, genvej, valuta)
                    SELECT kontonr, beskrivelse, kontotype, '', overfor_til, fra_kto, 0, 0, $year, 0, '', '', 0
                    FROM (VALUES $accounts) AS a (kontonr, beskrivelse, kontotype, overfor_til, fra_kto)";
            }
        }
        $sql[] = "INSERT INTO transaktioner (kontonr, bilag, transdate, debet, kredit) VALUES
            (6190, 1, '2026-03-01', 12500, 0), (1010, 1, '2026-03-01', 0, 10000), (7680, 1, '2026-03-01', 0, 2500),
            (1610, 2, '2026-05-01', 4000, 0), (6480, 2, '2026-05-01', 0, 4000),
            (5580, 3, '2026-06-30', 1907.87, 0), (6480, 3, '2026-06-30', 0, 1907.87)" . ($morePostings !== '' ? ", $morePostings" : '');
        return $sql;
    }

    /** The standard chart's Resultat account, which shows the sum 4999 in the balance sheet. */
    private const RESULTAT = "(6935, 'Årets resultat', 'R', 0, 4999)";

    /**
     * The 2027 card's rows for the 2026 status accounts: number, 2026 closing (primo plus postings) and the Overfør til the
     * card shows first. The status closings sum to 6.000 (minus the 2026 result), as the card's Saldo column does.
     *
     * @return array<int, array{0: int, 1: string, 2: int}>
     */
    private static function rows2027(string $overfor5580, string $closing6190 = '12500', string $closing7680 = '-2500'): array
    {
        return [[5570, '30000', 5570], [5580, '1907.87', (int) $overfor5580], [6190, $closing6190, 6190], [6480, '14092.13', 6480], [6910, '-10000', 6910],
            [6940, '-40000', 6940], [6960, '0', 6960], [7680, $closing7680, 7680]];
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string, 2: string, 3: string, 4: array<int, array{0: int, 1: string, 2: int}>, 5: array<string, string>, 6: string, 7: bool, 8: string|null, 9?: string}>
     */
    public static function existingYears(): array
    {
        // 2027 as Indstillinger opened it (place mode) while 5580's Overfør til was itself: 5580 kept its own 1.907,87, and
        // 6940 took minus the other openings, 30.000 + 1.907,87 + 12.500 + 14.092,13 - 10.000 - 2.500 = 46.000.
        $placed = ['5570' => '30000', '5580' => '1907.87', '6190' => '12500', '6480' => '14092.13', '6910' => '-10000', '6940' => '-46000', '6960' => '0', '7680' => '-2500'];
        // The rows write, as before: each 2026 closing onto its Overfør til, 5580's 1.907,87 onto 5570, 6940 its own -40.000.
        $rowsWrite = ['5570' => '31907.87', '5580' => '0.00', '6190' => '12500.00', '6480' => '14092.13', '6910' => '-10000.00', '6940' => '-40000.00', '6960' => '0.00', '7680' => '-2500.00'];
        return [
            // The bookkeeper chooses 5570 for 5580 on the card. The carry moves 1.907,87 from 5580 to 5570, a change of 0, so
            // 6940 keeps -46.000 with the result. The rows alone (before) wrote 6940's own -40.000 and dropped the result.
            'Gem keeps the result and saves the Overfør til' => [
                $placed, '5580', self::RESULTAT, '', self::rows2027('5570'),
                array_replace($rowsWrite, ['6940' => '-46000.00']), // 31.907,87 + 12.500 + 14.092,13 - 10.000 - 46.000 - 2.500 = 0
                '<td align=right>6.000,00</td><td></td><td align=right>0,00</td>', true, null,
            ],
            // 2027 opened without the 2026 result (own saldi, sum 6.000) and repaired by hand in 2027 (6935 debit / 6940
            // credit 6.000). A late sale into 2026 (6190 125 / 1010 100 / 7680 25) then reached it through finans/bogfor.php's
            // carry: 6190 +125, 7680 -25, 6940 -40.000 - 100. Gem keeps that: no change, so 6940 stays -40.100 and the opening
            // sums to 6.000, which the hand repair balances. The rows alone wrote -40.000 and dropped the late sale's result.
            'Gem keeps a late posting\'s result on a year repaired by hand' => [
                ['5570' => '31907.87', '5580' => '0', '6190' => '12625', '6480' => '14092.13', '6910' => '-10000', '6940' => '-40100', '6960' => '0', '7680' => '-2525'],
                '5570', self::RESULTAT,
                "(6935, 6, '2027-01-02', 6000, 0), (6940, 6, '2027-01-02', 0, 6000), (6190, 9, '2026-12-31', 125, 0), (1010, 9, '2026-12-31', 0, 100), (7680, 9, '2026-12-31', 0, 25)",
                self::rows2027('5570', '12625', '-2525'),
                array_replace($rowsWrite, ['6190' => '12625.00', '6940' => '-40100.00', '7680' => '-2525.00']),
                // Saldo: 30.000 + 1.907,87 + 12.625 + 14.092,13 - 10.000 - 40.000 - 2.525 = 6.100; Ny primo 6.000.
                '<td align=right>6.100,00</td><td></td><td align=right>6.000,00</td>', true, null,
            ],
            // A boundary: the chart has no Resultat account (2027 opened as above), so the result has no account in 2027
            // and the carry writes nothing. The rows write the opening as before (the result dropped, 6940 -40.000, and
            // the opening off by 6.000), and the Overfør til is saved. The card now says so (owner's decision 19 Nov 2026);
            // before, it saved without a word. It names the result left out (the drift accounts 1010 -10.000 and 1610
            // 4.000) and the opening's sum.
            'no Resultat account in 2027: the rows write as before, with a warning' => [
                $placed, '5580', '', '', self::rows2027('5570'), $rowsWrite,
                '<td align=right>6.000,00</td><td></td><td align=right>6.000,00</td>', false,
                'Åbningsbalancen for regnskabsår 2027 er gemt fra saldiene på kortet uden resultatet for regnskabsår 2026 (-6.000,00). '
                . 'Primo i alt er 6.000,00 og ikke 0,00. '
                . 'Kontoplanen for 2027 har ingen konto af typen Resultat med en statuskonto efter sig (som 6935 Årets resultat og 6940 '
                . 'Overført resultat i standardkontoplanen), som resultatet kan føres til.',
            ],
            // The same chart, but the bookkeeper closed the drift accounts into 6940 within 2026 (1010 debit 10.000, 1610
            // credit 4.000, 6940 credit 6.000 on 31-12-2026). The rows carry 6940's -46.000 with the result in it, and the
            // opening sums to 0. Before, the warning still said the opening was saved without the result, so a bookkeeper
            // who posted it again would have doubled it. Now it says the drift accounts are at 0 and nothing is missing.
            'no Resultat account, the result closed into 6940 within 2026: the warning says nothing is missing' => [
                $placed, '5580', '', "(1010, 8, '2026-12-31', 10000, 0), (1610, 8, '2026-12-31', 0, 4000), (6940, 8, '2026-12-31', 0, 6000)",
                [[5570, '30000', 5570], [5580, '1907.87', 5570], [6190, '12500', 6190], [6480, '14092.13', 6480], [6910, '-10000', 6910],
                    [6940, '-46000', 6940], [6960, '0', 6960], [7680, '-2500', 7680]],
                array_replace($rowsWrite, ['6940' => '-46000.00']), // 31.907,87 + 12.500 + 14.092,13 - 10.000 - 46.000 - 2.500 = 0
                '<td align=right>0,00</td><td></td><td align=right>0,00</td>', false,
                'Åbningsbalancen for regnskabsår 2027 er gemt fra saldiene på kortet. '
                . 'Driftskontiene for regnskabsår 2026 står i 0,00, så der mangler intet resultat. Primo i alt er 0,00. '
                . 'Kontoplanen for 2027 har ingen konto af typen Resultat med en statuskonto efter sig (som 6935 Årets resultat og 6940 '
                . 'Overført resultat i standardkontoplanen), som resultatet kan føres til.',
            ],
            // A 'status' boundary: 2027 was placed (6940 -46.000) before 5990 Deposita was added to 2026's chart only, and
            // 300 was paid from the bank onto it in 2026. On the card 5990 has no Overfør til (it is not in 2027's chart),
            // so the carry stops at 2027 and the rows write the opening: without 5990's 300 and without the result already
            // placed in 6940 (-46.000 back to -40.000), 5.700 off. Before, the card saved that without a word.
            'a 2026 status account without an account in 2027: the rows write, with a warning naming what they left out' => [
                $placed, '5580', self::RESULTAT, "(5990, 7, '2026-08-01', 300, 0), (6480, 7, '2026-08-01', 0, 300)",
                [[5570, '30000', 5570], [5580, '1907.87', 5570], [5990, '300', 0], [6190, '12500', 6190], [6480, '13792.13', 6480],
                    [6910, '-10000', 6910], [6940, '-40000', 6940], [6960, '0', 6960], [7680, '-2500', 7680]],
                array_replace($rowsWrite, ['6480' => '13792.13']), // 31.907,87 + 12.500 + 13.792,13 - 10.000 - 40.000 - 2.500 = 5.700
                '<td align=right>6.000,00</td><td></td><td align=right>5.700,00</td>', false,
                'Åbningsbalancen for regnskabsår 2027 er gemt fra saldiene på kortet uden resultatet for regnskabsår 2026 (-6.000,00) '
                . 'og saldoen på konto 5990 (300,00). Primo i alt er 5.700,00 og ikke 0,00. '
                . 'Primo føres kun med resultatet, når hver statuskonto i regnskabsår 2026 har en konto at blive ført til i 2027, og konto '
                . '5990 har ingen: dens Overfør til er tom eller findes ikke i kontoplanen for 2027, og det gør kontoen selv heller ikke.',
                "(5990, 'Deposita', 'S', 0, 0)",
            ],
            // The same 'status' boundary, but the bookkeeper closed the 2026 result by hand on 31-12-2026 the usual way,
            // D 6935 Årets resultat (Resultat) / K 6940 6.000, so 6940's closing -46.000 holds it and the drift accounts
            // still show -6.000. The rows write 6940 -46.000 and leave out only 5990's 300 (sum -300). Before, the
            // warning counted the drift accounts only and said the result -6.000 was left out too, which a bookkeeper
            // who posted it again would have doubled (6940 -52.000). Now it names 5990 only and says the result is there.
            'a status stop where the result was closed via 6935 within 2026: the warning names only the balance left out' => [
                $placed, '5580', self::RESULTAT,
                "(5990, 7, '2026-08-01', 300, 0), (6480, 7, '2026-08-01', 0, 300), (6935, 8, '2026-12-31', 6000, 0), (6940, 8, '2026-12-31', 0, 6000)",
                [[5570, '30000', 5570], [5580, '1907.87', 5570], [5990, '300', 0], [6190, '12500', 6190], [6480, '13792.13', 6480],
                    [6910, '-10000', 6910], [6940, '-46000', 6940], [6960, '0', 6960], [7680, '-2500', 7680]],
                array_replace($rowsWrite, ['6480' => '13792.13', '6940' => '-46000.00']), // 31.907,87 + 12.500 + 13.792,13 - 10.000 - 46.000 - 2.500 = -300
                '<td align=right>0,00</td><td></td><td align=right>-300,00</td>', false,
                'Åbningsbalancen for regnskabsår 2027 er gemt fra saldiene på kortet uden saldoen på konto 5990 (300,00). '
                . 'Resultatet for regnskabsår 2026 (-6.000,00) er allerede ført til statuskontiene med posteringer på konto 6935, så der mangler intet resultat. '
                . 'Primo i alt er -300,00 og ikke 0,00. '
                . 'Primo føres kun med resultatet, når hver statuskonto i regnskabsår 2026 har en konto at blive ført til i 2027, og konto '
                . '5990 har ingen: dens Overfør til er tom eller findes ikke i kontoplanen for 2027, og det gør kontoen selv heller ikke.',
                "(5990, 'Deposita', 'S', 0, 0)",
            ],
            // A 'result' boundary: 2026's chart has 6935 Årets resultat, 2027's has no Resultat account. The bookkeeper
            // closed the 2026 result by hand (D 6935 / K 6940 6.000 on 31-12-2026), so the rows write 6940 -46.000 and the
            // opening sums to 0. Before, the warning said the opening was saved without the result -6.000.
            'a result stop where the result was closed via 6935 within 2026: the warning says nothing is missing' => [
                $placed, '5580', '', "(6935, 8, '2026-12-31', 6000, 0), (6940, 8, '2026-12-31', 0, 6000)",
                [[5570, '30000', 5570], [5580, '1907.87', 5570], [6190, '12500', 6190], [6480, '14092.13', 6480], [6910, '-10000', 6910],
                    [6940, '-46000', 6940], [6960, '0', 6960], [7680, '-2500', 7680]],
                array_replace($rowsWrite, ['6940' => '-46000.00']), // 31.907,87 + 12.500 + 14.092,13 - 10.000 - 46.000 - 2.500 = 0
                '<td align=right>0,00</td><td></td><td align=right>0,00</td>', false,
                'Åbningsbalancen for regnskabsår 2027 er gemt fra saldiene på kortet. '
                . 'Resultatet for regnskabsår 2026 (-6.000,00) er allerede ført til statuskontiene med posteringer på konto 6935, så der mangler intet resultat. '
                . 'Primo i alt er 0,00. '
                . 'Kontoplanen for 2027 har ingen konto af typen Resultat med en statuskonto efter sig (som 6935 Årets resultat og 6940 '
                . 'Overført resultat i standardkontoplanen), som resultatet kan føres til.',
                self::RESULTAT,
            ],
            // The same, but only 4.000 of the result -6.000 was moved (D 6935 / K 6940 4.000): the rows write 6940 -44.000
            // and the opening sums to 2.000. The warning names the -2.000 that is not on the status accounts.
            'a result stop where part of the result was moved via 6935: the warning names the rest' => [
                $placed, '5580', '', "(6935, 8, '2026-12-31', 4000, 0), (6940, 8, '2026-12-31', 0, 4000)",
                [[5570, '30000', 5570], [5580, '1907.87', 5570], [6190, '12500', 6190], [6480, '14092.13', 6480], [6910, '-10000', 6910],
                    [6940, '-44000', 6940], [6960, '0', 6960], [7680, '-2500', 7680]],
                array_replace($rowsWrite, ['6940' => '-44000.00']), // 31.907,87 + 12.500 + 14.092,13 - 10.000 - 44.000 - 2.500 = 2.000
                '<td align=right>2.000,00</td><td></td><td align=right>2.000,00</td>', false,
                'Åbningsbalancen for regnskabsår 2027 er gemt fra saldiene på kortet uden den del af resultatet for regnskabsår 2026, '
                . 'der ikke allerede er ført til statuskontiene med posteringer på konto 6935 (-2.000,00). '
                . 'Primo i alt er 2.000,00 og ikke 0,00. '
                . 'Kontoplanen for 2027 har ingen konto af typen Resultat med en statuskonto efter sig (som 6935 Årets resultat og 6940 '
                . 'Overført resultat i standardkontoplanen), som resultatet kan føres til.',
                self::RESULTAT,
            ],
        ];
    }

    /**
     * @param array<string, string> $opening2027
     * @param array<int, array{0: int, 1: string, 2: int}> $rows
     * @param array<string, string> $expected
     */
    #[DataProvider('existingYears')]
    public function test_gem_on_an_existing_year_without_a_sideskift_account(array $opening2027, string $overfor5580, string $resultAccounts, string $morePostings, array $rows, array $expected, string $sumRow, bool $carried, ?string $alert, string $only2026 = ''): void
    {
        file_put_contents($this->fixture . '/fixture.sql', implode(";\n", self::ledger($opening2027, $overfor5580, $resultAccounts, $morePostings, $only2026)));

        [$stdout, $warnings] = $this->runCard(self::cardPost('2', 18, $rows));

        self::assertStringContainsString('[footer]', $stdout, 'the card was drawn after the save');
        self::assertSame($alert === null ? [] : [$alert], self::alerts($stdout), 'the card\'s message after the save');
        $state = self::state($stdout);
        self::assertSame($expected, array_intersect_key($state['primo']['18'], array_flip(self::STATUS)));
        self::assertSame(['30000.00', '0.00', '20000.00', '-40000.00'], [$state['primo']['17']['5570'], $state['primo']['17']['5580'], $state['primo']['17']['6480'], $state['primo']['17']['6940']], '2026 is untouched');
        self::assertSame(['5570', '5570'], [$state['overfor']['17']['5580'], $state['overfor']['18']['5580']], 'the Overfør til chosen on the card is saved in both years');
        self::assertStringContainsString($sumRow, preg_replace("/\n/", '', $stdout), 'Saldo and Ny primo sums');
        // The page raises its legacy warnings (fields the POST lacks, the rows' row 0 on a chart without a Sideskift
        // account), but the carry path through the Gem raises none of its own.
        [$from, $to] = $carried ? self::gemBlockLines() : [0, 0];
        foreach ($warnings as $warning) {
            self::assertStringNotContainsString('updateFollowingOpeningBalances.php', $warning);
            if ($carried && preg_match('/regnskabskort\.php:(\d+) /', $warning, $m)) {
                self::assertFalse((int) $m[1] >= $from && (int) $m[1] <= $to, 'the carry path raises no warning: ' . $warning);
            }
        }
    }

    /**
     * @return array{0: int, 1: int} The first and last line of regnskabskort.php's opening save, from the mode flag to the
     *   place carry after the chart copy.
     */
    private static function gemBlockLines(): array
    {
        $lines = file(dirname(__DIR__, 3) . '/systemdata/regnskabskort.php');
        $from = $to = 0;
        foreach ($lines as $i => $line) {
            if (!$from && str_contains($line, '$chartCopied = false;')) {
                $from = $i + 1;
            }
            if (str_contains($line, 'updateFollowingOpeningBalances($kodenr - 1, OPENING_CARRY_PLACE);')) {
                $to = $i + 1;
            }
        }
        self::assertGreaterThan($from, $to, 'the Gem block is found');
        return [$from, $to];
    }

    public function test_gem_on_a_sideskift_chart_is_saved_from_its_resultat_row_as_before(): void
    {
        // 4998 Sideskift with Overfør til 6940 in both years; 2027 opened with 6940 at 0 to show what the Gem writes.
        $opening = ['5570' => '0', '5580' => '0', '6190' => '0', '6480' => '0', '6910' => '0', '6940' => '0', '6960' => '0', '7680' => '0'];
        file_put_contents($this->fixture . '/fixture.sql', implode(";\n", self::ledger($opening, '5570', "(4998, 'Sideskift', 'X', 6940, 0), " . self::RESULTAT, '')));

        [$stdout] = $this->runCard(self::cardPost('2', 18, array_merge([[4998, '-6000', 6940]], self::rows2027('5570'))));

        self::assertStringContainsString('[footer]', $stdout, 'the card was drawn after the save');
        self::assertSame([], self::alerts($stdout), 'the Sideskift path is unchanged: no warning');
        $state = self::state($stdout);
        // The rows: 5570 30.000 + 1.907,87, and 6940 its own -40.000 plus the Resultat row's -6.000.
        self::assertSame(['5570' => '31907.87', '5580' => '0.00', '6190' => '12500.00', '6480' => '14092.13', '6910' => '-10000.00', '6940' => '-46000.00', '6960' => '0.00', '7680' => '-2500.00'],
            array_intersect_key($state['primo']['18'], array_flip(self::STATUS)));
        self::assertStringContainsString("<td>Resultat</td><input type='hidden' name='kontonr[0]' value='4998'>", preg_replace("/\n/", '', $stdout));
    }

    public function test_gem_holds_the_carrys_lock_until_the_card_has_recalculated_the_year(): void
    {
        // p1-pos-close-N1: Gem let go of the opening-balance carry's lock right after its commit, before the card recalculates
        // the year (genberegn(), which rewrites every kontoplan row of the year). A POS day close waiting for the lock took
        // its snapshot in between and failed on a row the recalculation then rewrote. Here genberegn() reports whether the
        // request holds the lock, and whether its database session does, when the card recalculates; the footer, drawn
        // after it, whether the session still does.
        $opening = ['5570' => '0', '5580' => '0', '6190' => '0', '6480' => '0', '6910' => '0', '6940' => '0', '6960' => '0', '7680' => '0'];
        file_put_contents($this->fixture . '/fixture.sql', implode(";\n", self::ledger($opening, '5570', "(4998, 'Sideskift', 'X', 6940, 0), " . self::RESULTAT, '')));
        $advisory = '(int) pg_fetch_row(pg_query($GLOBALS["__card_conn"], "select count(*) from pg_locks where locktype = \'advisory\' and granted and pid = pg_backend_pid()"))[0]';
        file_put_contents($this->fixture . '/includes/genberegn.php', '<?php function genberegn($year, $updateUsage = true) {
            echo "\n##GENBEREGN##" . json_encode(["year" => (int) $year, "usage" => $updateUsage, "held" => function_exists("openingCarryLockState") && openingCarryLockState()["held"], "advisory" => ' . $advisory . ']) . "\n";
        }');
        file_put_contents($this->fixture . '/includes/oldDesign/footer.php', '<?php echo "[footer]##FOOTER##" . json_encode(["advisory" => ' . $advisory . ']) . "\n";');

        [$stdout] = $this->runCard(self::cardPost('2', 18, array_merge([[4998, '-6000', 6940]], self::rows2027('5570'))));

        self::assertStringContainsString('[footer]', $stdout, 'the card was drawn after the save');
        preg_match_all('/##GENBEREGN##(\{.*?\})\n/', $stdout, $m);
        $recalculations = array_values(array_filter(array_map(static fn(string $json): array => json_decode($json, true), $m[1]), static fn(array $r): bool => $r['usage']));
        self::assertSame([['year' => 18, 'usage' => true, 'held' => true, 'advisory' => 1]], $recalculations, 'the card recalculates 2027 while the save still holds the carry\'s lock');
        self::assertSame(1, preg_match('/##FOOTER##(\{.*?\})\n/', $stdout, $footer));
        self::assertSame(['advisory' => 0], json_decode($footer[1], true), 'and lets go of it before the card is drawn');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function overforTilOnCreation(): array
    {
        return [
            // The copy takes 5580's Overfør til 5570 from 2026.
            'the stored Overfør til' => ['5570'],
            // 5580 is stored onto itself, and the create form posts 5570: the second pass saves it and places again.
            'an Overfør til chosen on the create form' => ['5580'],
        ];
    }

    /**
     * The old card's creation path: a POST without an id for 2027 (kodenr 18), which has no year row or chart yet. The
     * page inserts the year, copies 2026's chart with each status account's own saldo, and saves twice. Before, the second
     * pass wrote the rows and dropped the result (6940 -40.000, 2027 off by 6.000).
     */
    #[DataProvider('overforTilOnCreation')]
    public function test_the_old_cards_creation_path_places_the_result(string $overfor5580): void
    {
        file_put_contents($this->fixture . '/fixture.sql', implode(";\n", self::ledger(null, $overfor5580, self::RESULTAT, '')));

        [$stdout, $warnings] = $this->runCard(self::cardPost('0', 18, self::rows2027('5570')));

        self::assertStringContainsString('[footer]', $stdout, 'the card of the new year was drawn after the save');
        self::assertSame([], self::alerts($stdout), 'the result is placed: no warning');
        $state = self::state($stdout);
        self::assertSame([
            '5570' => '31907.87', // 30.000 plus 5580's 1.907,87
            '5580' => '0.00',
            '6190' => '12500.00',
            '6480' => '14092.13',
            '6910' => '-10000.00',
            // minus the other openings: 31.907,87 + 12.500 + 14.092,13 - 10.000 - 2.500 = 46.000, its own -40.000 plus the result -6.000
            '6940' => '-46000.00',
            '6960' => '0.00',
            '7680' => '-2500.00',
        ], array_intersect_key($state['primo']['18'], array_flip(self::STATUS)));
        self::assertSame('0.00', number_format(array_sum(array_map('floatval', array_intersect_key($state['primo']['18'], array_flip(self::STATUS)))), 2, '.', ''), '2027 opens balanced');
        self::assertSame(['5570', '5570'], [$state['overfor']['17']['5580'], $state['overfor']['18']['5580']]);
        self::assertStringContainsString('<td align=right>6.000,00</td><td></td><td align=right>0,00</td>', preg_replace("/\n/", '', $stdout), 'Saldo and Ny primo sums');
        foreach ($warnings as $warning) {
            self::assertStringNotContainsString('updateFollowingOpeningBalances.php', $warning);
        }
    }

    /**
     * The texts of the alerts the page printed with json_encode(), as the save's warning is printed.
     *
     * @return list<string>
     */
    private static function alerts(string $stdout): array
    {
        preg_match_all('/<script>alert\((".*?")\);<\/script>/', $stdout, $m);
        return array_map(static fn(string $json): string => (string) json_decode($json), $m[1]);
    }

    /**
     * @return array{primo: array<string, array<string, string>>, overfor: array<string, array<string, string>>}
     */
    private static function state(string $stdout): array
    {
        $state = json_decode(substr($stdout, (int) strrpos($stdout, '##STATE##') + 9), true);
        self::assertIsArray($state, $stdout);
        return $state;
    }

    /**
     * The POST the card's Gem/opdatér sends for the year with grupper id $id (0 to create it) and kodenr $kodenr (a
     * calendar year from 2009 + $kodenr, so 18 is 2027): one row per status account of the year before, plus row 0 for
     * the Resultat row of a Sideskift chart (its account 4998).
     *
     * @param array<int, array{0: int, 1: string, 2: int}> $rows Account number, closing balance and the Overfør til shown.
     * @return array<string, mixed>
     */
    private static function cardPost(string $id, int $kodenr, array $rows): array
    {
        $year = (string) (2009 + $kodenr);
        $sideskift = $rows[0][0] === 4998;
        $post = ['id' => $id, 'beskrivelse' => $year, 'kodenr' => (string) $kodenr, 'kode' => '', 'startmd' => '01', 'startaar' => $year, 'slutmd' => '12',
            'slutaar' => $year, 'aaben' => 'on', 'kontoantal' => (string) (count($rows) - ($sideskift ? 1 : 0)),
            'kontonr' => [], 'saldo' => [], 'overfor_til' => []];
        $first = $sideskift ? 0 : 1;
        foreach ($rows as $i => $row) {
            $post['kontonr'][$first + $i] = (string) $row[0];
            $post['saldo'][$first + $i] = $row[1];
            $post['overfor_til'][$first + $i] = (string) $row[2];
        }
        return $post;
    }

    /**
     * Run regnskabskort.php with the POST its Gem/opdatér sends, then let it draw the card.
     *
     * @param array<string, mixed> $post
     * @return array{0: string, 1: array<int, string>} The page output and the warnings it raised.
     */
    private function runCard(array $post): array
    {
        $dsn = (string) getenv('SALDI_CHAR_DSN');
        $conn = sprintf(
            'host=%s dbname=%s user=%s password=%s connect_timeout=5',
            preg_match('/host=([^;]+)/', $dsn, $m) ? $m[1] : 'localhost',
            preg_match('/dbname=([^;]+)/', $dsn, $m) ? $m[1] : '',
            (string) getenv('SALDI_CHAR_PGUSER'),
            (string) getenv('SALDI_CHAR_PGPASS')
        );
        // The page's own legacy warnings (row 0 of a chart without a Sideskift account, ...) are collected, not fatal.
        $script = 'set_error_handler(static function ($severity, $message, $file, $line) {'
            . 'fwrite(STDERR, "WARN $file:$line $message\n"); return true; });'
            . '$_SERVER["REQUEST_METHOD"] = "POST";'
            . '$_GET = [];'
            . '$_POST = ' . var_export($post, true) . ';'
            . 'require ' . var_export(dirname(__DIR__, 3) . '/systemdata/regnskabskort.php', true) . ';';
        $process = proc_open(
            [PHP_BINARY, '-d', 'session.save_path=' . $this->fixture, '-d', 'include_path=.', '-d', 'display_errors=stderr', '-r', $script],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->fixture . '/systemdata',
            array_merge(getenv(), ['SALDI_CARD_PG' => $conn])
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr . $stdout);
        $warnings = preg_split('/\n(?=WARN )/', trim($stderr), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($warnings as $warning) {
            self::assertStringStartsWith('WARN ', $warning, $stderr);
        }
        return [$stdout, $warnings];
    }
}
