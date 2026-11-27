<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Indstillinger » Finans » Valuta saves an exchange rate with or without posting the adjustment, and only for the
 * currency being edited (testlab round 1, 2026-11-05).
 *
 * tests/integration/currencyRateSave.php pinned this on systemdata/valutakort.php: SST-769's "Gem kurs uden bogføring"
 * writes the rate and no postings, balances are recalculated only after an adjustment is posted, and a rate id of one
 * currency cannot be written under another (#598). a1a83086 moved currencies and rates to the rates table of
 * settingsSection.php?s=finance.currencies and left valutakort.php a redirect, so the script found no controller to
 * run ("Currency POST controller boundaries not found") and its 9 cases covered nothing. CI never ran it. The script is
 * gone; its cases are here, run against the code that saves the rates now.
 *
 * Pins, as settingsSection.php saves the rates (settings_rows_save(): a dry run, the confirm box when the rate would
 * post amounts, then the write with the button pressed, confirmed=1 "Bogfør og gem" or confirmed=skip "Gem kurs uden
 * bogføring"), the same 9 cases:
 * - Gem kurs uden bogføring writes a new or a changed rate and nothing else: no postings, no open-item adjustment, no
 *   account rate, no recalculation.
 * - A currency's first rate has nothing to adjust, so it is saved without a confirmation and posts nothing.
 * - Bogfør og gem posts the adjustment against the currency's difference account and recalculates once.
 * - A rate dated before a posting in the currency is refused, also when the request skips the dry run; a currency
 *   without a difference account cannot post one, and that account must be a profit and loss account of the chart (the
 *   difference account the old page took with every rate now belongs to the currency).
 * - The rows are those of the currency in the address (cur), so a rate id of another currency, or one posted without
 *   a currency, is not written, and a posting save next to it books for this currency only.
 * - A rate that is written gets its audit row in the change history; a save that writes nothing audits nothing.
 * - Slet (settings_rows_delete()) refuses a rate that a posting stands behind, as a change of it is refused: a rate
 *   whose adjustment is posted and the rate it adjusted from stay, and so do the postings and the account's rate. The
 *   adjustment is on the currency's account with valuta -1, so a count of the postings in the currency found none and
 *   the rate was deleted (testlab round 2, currency-rates-revaluation DEVY-2). The account is the currency's in any
 *   fiscal year, so an adjustment posted on an account of another year than the open one keeps its rate too. A rate
 *   nothing is posted behind is deleted.
 * The queries run on a real Postgres against TEMP tables shaped like includes/opretfunc.php and
 * includes/betweenUpdates.php create them, and saldi_schema_filter() scopes the save's information_schema lookups to
 * the session's temp schema, so nothing in the database behind SALDI_CHAR_DSN is read or changed, also when it is a
 * SALDI regnskab. genberegn() is a stub that counts the recalculations; texts come from importfiler/tekster.csv. A
 * query that fails fails the test.
 *
 * History:
 * 20261105 CL/LH Created from the 9 cases of tests/integration/currencyRateSave.php.
 * 20261105 CL/LH Slet of a rate whose adjustment is posted is refused, whichever fiscal year is open; a rate with nothing posted behind it is deleted.
 */
#[RunTestsInSeparateProcesses]
final class CurrencyRateSaveTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** The lines the confirm box shows for a USD rate of 750 after 700. */
    private const USD_CONFIRM = ['Konto 68103: -100,00 kr. (modkonto 8040)', '1 åbne poster justeres hos kunder og leverandører'];

    /** The audit row of a new rate dated 14-09-2026. */
    private const RATE_CREATED = 'setting.row_created rates indstilling finance.currencies.rates#2026-09-14 ui';

    /** @var \PgSql\Connection|null */
    private static $conn = null;

    private static function boot(): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('the pgsql extension is required to run the save SQL.');
        }
        $dsn = (string) getenv('SALDI_CHAR_DSN');
        if ($dsn === '') {
            self::markTestSkipped('set SALDI_CHAR_DSN, SALDI_CHAR_PGUSER and SALDI_CHAR_PGPASS to run the save SQL.');
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
        $GLOBALS['__crs_conn'] = $conn;
        $GLOBALS['__crs_texts'] = self::ROOT . '/importfiler/tekster.csv';
        $GLOBALS['__crs_recalculated'] = [];
        $GLOBALS['__crs_transactions'] = [];
        // The save looks up columns in information_schema (SettingsService::auditColumns(), columnIsNumeric()). Pointed
        // at the session's temp schema, those lookups describe the tables below, also when the database is a SALDI
        // regnskab whose own audit_log has columns this one would lack.
        eval('
            function saldi_schema_filter() { return " and table_schema = (select nspname from pg_namespace where oid = pg_my_temp_schema())"; }
            function db_escape_string($s) { return pg_escape_string($GLOBALS["__crs_conn"], (string) $s); }
            function db_cast($expr, $type) { return "cast($expr as $type)"; }
            function db_select($q, $where = "") {
                $r = @pg_query($GLOBALS["__crs_conn"], $q);
                if ($r === false) {
                    throw new \RuntimeException(pg_last_error($GLOBALS["__crs_conn"]) . " in: " . $q);
                }
                return $r;
            }
            function db_fetch_array($r) { return pg_fetch_assoc($r); }
            function db_modify($q, $where = "") { db_select($q, $where); }
            function transaktion($what) {
                $GLOBALS["__crs_transactions"][] = $what;
                db_select($what);
            }
            function genberegn($year, $updateUsage = true) { $GLOBALS["__crs_recalculated"][] = (int) $year; }
            function findtekst($key, $sprog_id = 1) {
                static $texts = null;
                if ($texts === null) {
                    $texts = [];
                    foreach (file($GLOBALS["__crs_texts"], FILE_IGNORE_NEW_LINES) as $line) {
                        $f = explode("\t", $line);
                        if (isset($f[1]) && ctype_digit($f[0])) {
                            $texts[$f[0]] = $f[1];
                        }
                    }
                }
                $parts = explode("|", (string) $key, 2);
                return $texts[$parts[0]] ?? ($parts[1] ?? $parts[0]);
            }
        ');
        require_once self::ROOT . '/includes/settings/components.php';
        require_once self::ROOT . '/includes/settings/rows.php';
        require_once self::ROOT . '/includes/settings/rowHooks.php';
        $GLOBALS['regnaar'] = 1;
        $GLOBALS['sprog_id'] = 1;
        $GLOBALS['bruger_id'] = 1;
        $GLOBALS['brugernavn'] = 'test';

        // The columns the save reads and writes, typed as includes/opretfunc.php creates them, and audit_log as
        // includes/betweenUpdates.php leaves it. TEMP tables only, and no DROP: pg_temp comes first in the search path,
        // also for the audit rows the save writes.
        self::exec("CREATE TEMP TABLE grupper (id integer, beskrivelse text, kode text, kodenr integer, art text, box1 text, box2 text, box3 text, box4 text, fiscal_year integer)");
        self::exec("CREATE TEMP TABLE kontoplan (id serial, kontonr numeric(15,0), beskrivelse text, kontotype varchar(1), saldo numeric(15,3), regnskabsaar integer, valuta integer, valutakurs numeric(15,4))");
        self::exec("CREATE TEMP TABLE transaktioner (id serial, kontonr numeric(15,0), bilag numeric(15,0), transdate date, logtime time, beskrivelse text, debet numeric(15,3), kredit numeric(15,3),
            faktura text, kladde_id integer, projekt text, ansat numeric(15,0), logdate date, afd integer, ordre_id integer, valuta text, valutakurs numeric(15,3), moms numeric(15,3))");
        self::exec("CREATE TEMP TABLE adresser (id integer, kontonr varchar(30), art varchar(2), gruppe integer)");
        self::exec("CREATE TEMP TABLE openpost (id serial, konto_id integer, konto_nr text, amount numeric(15,3), refnr integer, beskrivelse text, udlignet varchar(2), transdate date,
            kladde_id integer, udlign_id integer, udlign_date date, valuta text, valutakurs numeric(15,3))");
        self::exec("CREATE TEMP TABLE kassekladde (id serial, transdate date, valuta integer)");
        self::exec("CREATE TEMP TABLE ordrer (id serial, art varchar(2), valuta text)");
        self::exec("CREATE TEMP TABLE valuta (id serial PRIMARY KEY, gruppe integer, valdate date, kurs numeric(15,3))");
        self::exec("CREATE TEMP TABLE audit_log (id serial, tidspunkt timestamp NOT NULL DEFAULT now(), bruger_id integer, brugernavn varchar(80), handling varchar(60) NOT NULL,
            objekt_type varchar(30), objekt_id varchar(60), detaljer text, ip varchar(45), kilde varchar(30), setting_key varchar(120), section varchar(80), old_value text, new_value text)");
        self::exec("SELECT setval(pg_get_serial_sequence('valuta', 'id'), 100)");

        // Four currencies with Kursdifferencer (8040) as difference account, SEK without one. USD's account is the
        // collective account of a supplier group with an open item, and USD has a posting from before every rate.
        self::exec("INSERT INTO grupper (id, beskrivelse, kodenr, art, box1, box2, box3, box4) VALUES
            (101, 'Euro', 1, 'VK', 'EUR', '745,00', '8040', '0'),
            (102, 'Amerikanske dollar', 2, 'VK', 'USD', '700,00', '8040', '0'),
            (103, 'Norske kroner', 3, 'VK', 'NOK', '', '8040', '0'),
            (104, 'Svenske kroner', 4, 'VK', 'SEK', '65,00', '', '0'),
            (105, 'Leverandører USD', 2, 'KG', '', '68103', '', '')");
        self::exec("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, saldo, regnskabsaar, valuta) VALUES
            (8040, 'Kursdifferencer', 'D', 0, 1, 0), (58101, 'Bank EUR', 'S', 745, 1, 1), (68103, 'Leverandører USD', 'S', -1400, 1, 2),
            (58104, 'Bank NOK', 'S', 500, 1, 3), (58105, 'Bank SEK', 'S', 650, 1, 4)");
        self::exec("INSERT INTO adresser VALUES (50, '9001', 'K', 2)");
        self::exec("INSERT INTO openpost (konto_id, konto_nr, amount, udlignet, transdate, valuta, valutakurs) VALUES (50, '9001', -1400, '0', '2025-12-15', 'USD', 700)");
        self::exec("INSERT INTO transaktioner (kontonr, transdate, kredit, valuta, valutakurs) VALUES (68103, '2025-12-15', 1400, '2', 700)");
        self::exec("INSERT INTO valuta VALUES (11, 1, '2026-01-01', 745), (21, 2, '2026-01-01', 700), (31, 4, '2026-01-01', 65)");
    }

    private static function exec(string $sql): void
    {
        if (@pg_query(self::$conn, $sql) === false) {
            throw new \RuntimeException(pg_last_error(self::$conn) . ' in: ' . $sql);
        }
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private static function rows(string $sql): array
    {
        $r = @pg_query(self::$conn, $sql);
        if ($r === false) {
            throw new \RuntimeException(pg_last_error(self::$conn) . ' in: ' . $sql);
        }
        return pg_fetch_all($r) ?: [];
    }

    /**
     * The tables of the Valuta section, the rates of $currency in the address, as settingsSection.php builds them.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function tables(int $currency): array
    {
        $tables = settings_rows_tables(getSettingsSections()['finance.currencies']);
        $tables['rates']['filter']['options'] = settings_rows_filter_options('currencies');
        $tables['rates']['filter']['value'] = $currency;
        return $tables;
    }

    /**
     * A save of the Valuta section as settingsSection.php makes it: the rows are checked first (dry run); amounts the
     * save would post are shown for confirmation unless a confirm button was pressed; otherwise the rows are written.
     *
     * @param array<string, array<string, string>> $rows row id => posted cells
     * @return array{errors: array<string, int>, confirm: array<int, string>, written: bool}
     */
    private static function save(int $currency, array $rows, string $confirmed = '', string $tableId = 'rates'): array
    {
        $_POST = ['cur' => (string) $currency, 'r' => [$tableId => $rows]] + ($confirmed !== '' ? ['confirmed' => $confirmed] : []);
        $tables = self::tables($currency);
        $dry = settings_rows_save('finance.currencies', $tables, $_POST, null, true);
        if ($dry['errors'] || ($dry['confirm'] && $confirmed === '')) {
            return ['errors' => $dry['errors'], 'confirm' => $dry['confirm'], 'written' => false];
        }
        $saved = settings_rows_save('finance.currencies', $tables, $_POST, null, false);
        return ['errors' => $saved['errors'], 'confirm' => $dry['confirm'], 'written' => true];
    }

    /**
     * @return array<int, string> "date kurs" of a currency's rates, newest first
     */
    private static function rates(int $currency): array
    {
        return array_map(fn (array $r) => $r['valdate'] . ' ' . $r['kurs'], self::rows("SELECT valdate, kurs FROM valuta WHERE gruppe = $currency ORDER BY valdate DESC, id DESC"));
    }

    /**
     * Postings, open items, account rates and recalculations: everything a rate save may post besides the rate.
     *
     * @return array{postings: array<int, string>, openpost: int, account_rates: array<int, string>, recalculated: array<int, int>}
     */
    private static function posted(): array
    {
        return [
            'postings' => array_map(fn (array $r) => $r['kontonr'] . ' ' . $r['transdate'] . ' D' . $r['debet'] . ' K' . $r['kredit'] . ' ' . $r['valuta'],
                self::rows("SELECT kontonr, transdate, round(coalesce(debet, 0), 2) AS debet, round(coalesce(kredit, 0), 2) AS kredit, valuta FROM transaktioner ORDER BY id")),
            'openpost' => (int) self::rows("SELECT count(*) AS n FROM openpost")[0]['n'],
            'account_rates' => array_map(fn (array $r) => $r['kontonr'] . ' ' . $r['valutakurs'], self::rows("SELECT kontonr, valutakurs FROM kontoplan WHERE valutakurs IS NOT NULL ORDER BY kontonr")),
            'recalculated' => $GLOBALS['__crs_recalculated'],
        ];
    }

    /**
     * @return array<int, string> "handling setting_key objekt_type objekt_id kilde" of the audit rows, oldest first
     */
    private static function audited(): array
    {
        return array_map(fn (array $r) => implode(' ', $r), self::rows("SELECT handling, setting_key, objekt_type, objekt_id, kilde FROM audit_log ORDER BY id"));
    }

    /** @return array{postings: array<int, string>, openpost: int, account_rates: array<int, string>, recalculated: array<int, int>} */
    private static function nothingPosted(): array
    {
        return ['postings' => ['68103 2025-12-15 D0.00 K1400.00 2'], 'openpost' => 1, 'account_rates' => [], 'recalculated' => []];
    }

    public function test_gem_kurs_uden_bogforing_saves_a_new_rate_and_posts_nothing(): void
    {
        self::boot();
        $rate = ['n1' => ['valdate' => '14-09-2026', 'kurs' => '750,00']];

        self::assertSame(['errors' => [], 'confirm' => self::USD_CONFIRM, 'written' => false], self::save(2, $rate), 'the rate would post, so the save asks first');
        self::assertSame(['errors' => [], 'confirm' => self::USD_CONFIRM, 'written' => true], self::save(2, $rate, 'skip'));

        self::assertSame(['2026-09-14 750.000', '2026-01-01 700.000'], self::rates(2));
        self::assertSame(self::nothingPosted(), self::posted());
        self::assertSame(['begin', 'commit'], $GLOBALS['__crs_transactions']);
        self::assertSame([self::RATE_CREATED], self::audited());
    }

    public function test_gem_kurs_uden_bogforing_changes_an_existing_rate_and_posts_nothing(): void
    {
        self::boot();
        $rate = ['21' => ['valdate' => '14-09-2026', 'kurs' => '750,00']];

        self::assertSame(['errors' => [], 'confirm' => self::USD_CONFIRM, 'written' => false], self::save(2, $rate));
        self::assertSame(['errors' => [], 'confirm' => self::USD_CONFIRM, 'written' => true], self::save(2, $rate, 'skip'));

        self::assertSame([['id' => '21', 'valdate' => '2026-09-14', 'kurs' => '750.000']], self::rows("SELECT id, valdate, kurs FROM valuta WHERE gruppe = 2"));
        self::assertSame(self::nothingPosted(), self::posted());
        // One row per changed cell, the rate named by its date before the change.
        self::assertSame([
            'setting.row_updated rates.valdate indstilling finance.currencies.rates#2026-01-01 ui',
            'setting.row_updated rates.kurs indstilling finance.currencies.rates#2026-01-01 ui',
        ], self::audited());
    }

    public function test_the_first_rate_of_a_currency_is_saved_without_a_confirmation_and_posts_nothing(): void
    {
        self::boot();

        self::assertSame(['errors' => [], 'confirm' => [], 'written' => true], self::save(3, ['n1' => ['valdate' => '14-09-2026', 'kurs' => '95,00']]));

        self::assertSame(['2026-09-14 95.000'], self::rates(3));
        self::assertSame('95,00', self::rows("SELECT box2 FROM grupper WHERE art = 'VK' AND kodenr = 3")[0]['box2'], 'the currency row shows its newest rate');
        self::assertSame(self::nothingPosted(), self::posted());
        self::assertSame([self::RATE_CREATED], self::audited());
    }

    public function test_bogfor_og_gem_posts_the_adjustment_and_recalculates_once(): void
    {
        self::boot();
        $rate = ['n1' => ['valdate' => '14-09-2026', 'kurs' => '750,00']];

        self::assertSame(['errors' => [], 'confirm' => self::USD_CONFIRM, 'written' => false], self::save(2, $rate));
        self::assertSame(['errors' => [], 'confirm' => self::USD_CONFIRM, 'written' => true], self::save(2, $rate, '1'));

        self::assertSame(['2026-09-14 750.000', '2026-01-01 700.000'], self::rates(2));
        self::assertSame([
            'postings' => ['68103 2025-12-15 D0.00 K1400.00 2', '8040 2026-09-14 D100.00 K0.00 -1', '68103 2026-09-14 D0.00 K100.00 -1'],
            'openpost' => 2,
            'account_rates' => ['68103 750.0000'],
            'recalculated' => [1],
        ], self::posted());
        self::assertSame(['begin', 'commit'], $GLOBALS['__crs_transactions']);
        self::assertSame([self::RATE_CREATED], self::audited());
    }

    public function test_a_rate_dated_before_a_posting_in_the_currency_is_refused_and_nothing_is_recalculated(): void
    {
        self::boot();
        self::exec("INSERT INTO transaktioner (kontonr, transdate, debet, valuta, valutakurs) VALUES (68103, '2026-10-01', 700, '2', 700)");
        $rate = ['n1' => ['valdate' => '14-09-2026', 'kurs' => '750,00']];

        self::assertSame(['errors' => ['rates/n1/valdate' => 6595], 'confirm' => [], 'written' => false], self::save(2, $rate, '1'));
        // A request that goes straight to the write is checked the same way.
        $_POST['confirmed'] = '1';
        self::assertSame(['rates/n1/valdate' => 6595], settings_rows_save('finance.currencies', self::tables(2), $_POST, null, false)['errors']);

        self::assertSame(['2026-01-01 700.000'], self::rates(2));
        self::assertSame(['postings' => ['68103 2025-12-15 D0.00 K1400.00 2', '68103 2026-10-01 D700.00 K0.00 2'], 'openpost' => 1, 'account_rates' => [], 'recalculated' => []], self::posted());
        self::assertSame([], $GLOBALS['__crs_transactions']);
        self::assertSame([], self::audited());
    }

    public function test_an_adjustment_needs_the_currencys_difference_account_and_that_is_a_profit_and_loss_account(): void
    {
        self::boot();

        self::assertSame(['errors' => ['rates/n1/kurs' => 6596], 'confirm' => [], 'written' => false], self::save(4, ['n1' => ['valdate' => '14-09-2026', 'kurs' => '70,00']], '1'));
        self::assertSame(['2026-01-01 65.000'], self::rates(4));
        self::assertSame(self::nothingPosted(), self::posted());

        // The old page took the difference account with every rate and refused one that was not a profit and loss
        // account of the year. It is now the currency's own, checked the same way where it is set.
        self::assertSame(['currencies/102/box3' => 6594], self::save(2, ['102' => ['box3' => '58101']], '', 'currencies')['errors']);
        self::assertSame(['currencies/102/box3' => 5734], self::save(2, ['102' => ['box3' => '9999']], '', 'currencies')['errors']);
        self::assertSame([], $GLOBALS['__crs_transactions']);
        self::assertSame([], self::audited());
    }

    public function test_a_rate_id_of_another_currency_is_not_written(): void
    {
        self::boot();

        // EUR's rate 11 posted as one of USD's rates.
        self::assertSame(['errors' => [], 'confirm' => [], 'written' => true], self::save(2, ['11' => ['valdate' => '14-09-2026', 'kurs' => '999,00']], 'skip'));

        self::assertSame(['2026-01-01 745.000'], self::rates(1));
        self::assertSame(['2026-01-01 700.000'], self::rates(2));
        self::assertSame(self::nothingPosted(), self::posted());
        self::assertSame([], $GLOBALS['__crs_transactions']);
        self::assertSame([], self::audited());
    }

    public function test_a_rate_id_without_a_currency_is_not_written(): void
    {
        self::boot();

        // settingsSection.php sets the filter to a currency of the list, or 0 when cur names none and there is none:
        // then no row is the request's own.
        self::assertSame(['errors' => [], 'confirm' => [], 'written' => true], self::save(0, ['21' => ['valdate' => '14-09-2026', 'kurs' => '999,00']], 'skip'));

        self::assertSame(['2026-01-01 700.000'], self::rates(2));
        self::assertSame(self::nothingPosted(), self::posted());
        self::assertSame([], $GLOBALS['__crs_transactions']);
        self::assertSame([], self::audited());
    }

    public function test_bogfor_og_gem_next_to_a_rate_id_of_another_currency_books_for_this_currency_only(): void
    {
        self::boot();
        $rows = ['11' => ['valdate' => '14-09-2026', 'kurs' => '999,00'], 'n1' => ['valdate' => '14-09-2026', 'kurs' => '750,00']];

        self::assertSame(['errors' => [], 'confirm' => self::USD_CONFIRM, 'written' => false], self::save(2, $rows));
        self::assertSame(['errors' => [], 'confirm' => self::USD_CONFIRM, 'written' => true], self::save(2, $rows, '1'));

        self::assertSame(['2026-01-01 745.000'], self::rates(1));
        self::assertSame(['2026-09-14 750.000', '2026-01-01 700.000'], self::rates(2));
        self::assertSame([
            'postings' => ['68103 2025-12-15 D0.00 K1400.00 2', '8040 2026-09-14 D100.00 K0.00 -1', '68103 2026-09-14 D0.00 K100.00 -1'],
            'openpost' => 2,
            'account_rates' => ['68103 750.0000'],
            'recalculated' => [1],
        ], self::posted());
        self::assertSame([self::RATE_CREATED], self::audited());
    }

    /**
     * The id of a currency's rate of a date.
     */
    private static function rateId(int $currency, string $date): int
    {
        return (int) self::rows("SELECT id FROM valuta WHERE gruppe = $currency AND valdate = '$date'")[0]['id'];
    }

    public function test_slet_refuses_a_rate_whose_adjustment_is_posted_and_the_postings_stay(): void
    {
        self::boot();
        self::save(2, ['n1' => ['valdate' => '14-09-2026', 'kurs' => '750,00']], '1');
        $posted = self::posted();
        $GLOBALS['__crs_transactions'] = [];

        // The adjustment is on 68103, USD's account, with valuta -1. The new rate and the rate it adjusted from both
        // stand behind it.
        $rates = self::tables(2)['rates'];
        foreach (['2026-09-14', '2026-01-01'] as $date) {
            self::assertSame(['err', "$date kan ikke slettes – Bruges af 1 posteringer."], settings_rows_delete('finance.currencies', 'rates', $rates, self::rateId(2, $date), null));
        }
        // A rate dated before the adjustment is refused for the same posting.
        self::assertSame(['rates/n1/valdate' => 6595], self::save(2, ['n1' => ['valdate' => '01-09-2026', 'kurs' => '760,00']], '1')['errors']);

        self::assertSame(['2026-09-14 750.000', '2026-01-01 700.000'], self::rates(2));
        self::assertSame($posted, self::posted(), 'the adjustment, the open-item adjustment and 68103 at 750 stay');
        self::assertSame([], $GLOBALS['__crs_transactions']);
        self::assertSame([self::RATE_CREATED], self::audited());
    }

    public function test_slet_refuses_a_rate_whose_adjustment_is_posted_in_another_fiscal_year_than_the_open_one(): void
    {
        self::boot();
        // Fiscal year 2 brings 58106, a EUR account year 1 does not have. Bogfør og gem of EUR's 07-07-2027 rate with
        // year 2 open posts the adjustment on it, with valuta -1.
        self::exec("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, saldo, regnskabsaar, valuta) VALUES
            (8040, 'Kursdifferencer', 'D', 0, 2, 0), (58106, 'Bank EUR', 'S', 7450, 2, 1)");
        $GLOBALS['regnaar'] = 2;
        $rate = ['n1' => ['valdate' => '07-07-2027', 'kurs' => '747,13']];
        self::assertSame(['errors' => [], 'confirm' => ['Konto 58106: 21,30 kr. (modkonto 8040)'], 'written' => true], self::save(1, $rate, '1'));
        $posted = self::posted();
        self::assertSame([
            'postings' => ['68103 2025-12-15 D0.00 K1400.00 2', '8040 2027-07-07 D0.00 K21.30 -1', '58106 2027-07-07 D21.30 K0.00 -1'],
            'openpost' => 1,
            'account_rates' => ['58106 747.1300'],
            'recalculated' => [2],
        ], $posted);
        $GLOBALS['__crs_transactions'] = [];

        // With year 1 open (the review's case: the count looked at year 1's EUR accounts only, found none of the
        // adjustment, and Slet deleted the rate) and with year 2 open, both rates stand behind the adjustment, and a
        // rate dated before it is refused for the same posting.
        foreach ([1, 2] as $year) {
            $GLOBALS['regnaar'] = $year;
            $rates = self::tables(1)['rates'];
            foreach (['2027-07-07', '2026-01-01'] as $date) {
                self::assertSame(['err', "$date kan ikke slettes – Bruges af 1 posteringer."], settings_rows_delete('finance.currencies', 'rates', $rates, self::rateId(1, $date), null), "year $year open");
            }
            self::assertSame(['rates/n1/valdate' => 6595], self::save(1, ['n1' => ['valdate' => '01-07-2027', 'kurs' => '760,00']], '1')['errors'], "year $year open");
        }

        self::assertSame(['2027-07-07 747.130', '2026-01-01 745.000'], self::rates(1));
        self::assertSame($posted, self::posted(), 'the adjustment and 58106 at 747,13 stay');
        self::assertSame([], $GLOBALS['__crs_transactions']);
        self::assertSame(['setting.row_created rates indstilling finance.currencies.rates#2027-07-07 ui'], self::audited());
    }

    public function test_slet_deletes_a_rate_with_nothing_posted_behind_it(): void
    {
        self::boot();
        self::save(2, ['n1' => ['valdate' => '14-09-2026', 'kurs' => '750,00']], 'skip');
        $GLOBALS['__crs_transactions'] = [];

        self::assertSame(['ok', 'Slettet: 2026-09-14'], settings_rows_delete('finance.currencies', 'rates', self::tables(2)['rates'], self::rateId(2, '2026-09-14'), null));

        self::assertSame(['2026-01-01 700.000'], self::rates(2));
        self::assertSame('700,00', self::rows("SELECT box2 FROM grupper WHERE art = 'VK' AND kodenr = 2")[0]['box2'], 'the currency row shows its newest rate again');
        self::assertSame(self::nothingPosted(), self::posted());
        self::assertSame(['begin', 'commit'], $GLOBALS['__crs_transactions']);
        self::assertSame([self::RATE_CREATED, 'setting.row_deleted rates indstilling finance.currencies.rates#2026-09-14 ui'], self::audited());
    }
}
