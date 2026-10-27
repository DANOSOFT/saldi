<?php
// tests/characterization/includes/OrderLineDeliveredBackfillTest.test.php
//
// MB-73: the login-time migration in includes/betweenUpdates.php sets DEFAULT 0 on ordrelinjer.leveret
// and backfills NULL leveret on delivered sales, credit note and POS lines from batch_salg
// (includes/opdat_func/backfillOrderLineDelivered.php).
//
// Pins:
//   - the backfilled value is the batch_salg sum for the line, order and item, as debitor/ordre.php
//     shows it; undelivered, text, purchase and already-set lines are left alone;
//   - a login that finds the backfill lock taken skips the backfill instead of running it alongside;
//   - the backfill stops after its time budget and the next login carries on from the stored window.
//
// Each run is a child process (support/run_leveret_backfill.php), i.e. its own database session, the
// way two logins are. Runs against a throwaway database SALDI_CHAR_TEST_DB . '_mb73' (default
// saldi_chartest_mb73) created through SALDI_CHAR_MASTER_DB; skips without pgsql or SALDI_CHAR_PGPASS.
//
// History:
// 20261027 CL/LH MB-73 (review): created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class OrderLineDeliveredBackfillTest extends TestCase
{
    /** @var resource|\PgSql\Connection|null */
    private static $master = null;
    /** @var resource|\PgSql\Connection|null */
    private $db = null;

    /** Expected leveret per fixture line after the backfill; null = left NULL. */
    private const EXPECTED = [
        'do_single'        => '2.000',  // one batch_salg row
        'do_split'         => '3.000',  // two batch_salg rows, summed
        'do_text'          => null,     // text line, no item
        'do_undelivered'   => null,     // no batch_salg row
        'dk_credit'        => '-2.000', // credit note line
        'po_receipt'       => '1.000',  // POS line
        'po_already_set'   => '5.000',  // not NULL before, untouched
        'ko_purchase'      => null,     // purchase line, excluded even with a batch_salg row
        'do_other_item'    => null,     // batch_salg for another item does not count
        'do_other_order'   => null,     // batch_salg for another order does not count
        'do_null_quantity' => '0.000',  // delivery record without a quantity
    ];

    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('pgsql extension not loaded');
        }
        if (CharacterizationEnv::pgPass() === '') {
            self::markTestSkipped('SALDI_CHAR_PGPASS is required for database characterization tests');
        }
        $master = @pg_connect(sprintf(
            "host='%s' dbname='%s' user='%s' password='%s' connect_timeout=3",
            addslashes(CharacterizationEnv::pgHost()),
            addslashes(CharacterizationEnv::masterDb()),
            addslashes(CharacterizationEnv::pgUser()),
            addslashes(CharacterizationEnv::pgPass())
        ), PGSQL_CONNECT_FORCE_NEW);
        if ($master === false) {
            self::markTestSkipped('postgres not reachable at host "' . CharacterizationEnv::pgHost() . '"');
        }
        self::$master = $master;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$master) {
            self::dropDatabase();
            pg_close(self::$master);
            self::$master = null;
        }
    }

    protected function setUp(): void
    {
        self::dropDatabase();
        CharacterizationEnv::rows(self::$master, 'CREATE DATABASE ' . pg_escape_identifier(self::$master, self::dbName()));
        $this->db = CharacterizationEnv::connect(self::dbName());
        $this->createFixture();
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            pg_close($this->db);
            $this->db = null;
        }
    }

    private static function dbName(): string
    {
        $name = CharacterizationEnv::testDb() . '_mb73';
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new RuntimeException('unsafe database name');
        }
        return $name;
    }

    private static function dropDatabase(): void
    {
        CharacterizationEnv::rows(self::$master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = $1 AND pid <> pg_backend_pid()', [self::dbName()]);
        CharacterizationEnv::rows(self::$master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier(self::$master, self::dbName()));
    }

    /** The columns the migration reads, as admin/opret.php creates them before this change (no default). */
    private function createFixture(): void
    {
        CharacterizationEnv::rows($this->db, 'CREATE TABLE settings (id serial PRIMARY KEY, var_name text, var_grp text, var_value text, var_description text)');
        CharacterizationEnv::rows($this->db, 'CREATE TABLE ordrer (id serial PRIMARY KEY, art varchar(2))');
        CharacterizationEnv::rows($this->db, 'CREATE TABLE ordrelinjer (id serial PRIMARY KEY, ordre_id integer, vare_id integer, beskrivelse text, antal numeric(15,3), leveret numeric(15,3))');
        CharacterizationEnv::rows($this->db, 'CREATE TABLE batch_salg (id serial PRIMARY KEY, linje_id integer, ordre_id integer, vare_id integer, antal numeric(15,3))');
        CharacterizationEnv::rows($this->db, 'CREATE INDEX batch_salg_linje_id_idx ON batch_salg (linje_id)');

        $order = function (string $art): int {
            return (int)CharacterizationEnv::one($this->db, 'INSERT INTO ordrer (art) VALUES ($1) RETURNING id', [$art])['id'];
        };
        $line = function (int $orderId, ?int $itemId, string $name, string $antal, ?string $leveret = null): int {
            return (int)CharacterizationEnv::one(
                $this->db,
                'INSERT INTO ordrelinjer (ordre_id, vare_id, beskrivelse, antal, leveret) VALUES ($1, $2, $3, $4, $5) RETURNING id',
                [$orderId, $itemId, $name, $antal, $leveret]
            )['id'];
        };
        $delivery = function (int $lineId, int $orderId, int $itemId, ?string $antal): void {
            CharacterizationEnv::rows($this->db, 'INSERT INTO batch_salg (linje_id, ordre_id, vare_id, antal) VALUES ($1, $2, $3, $4)', [$lineId, $orderId, $itemId, $antal]);
        };

        $do = $order('DO');
        $delivery($line($do, 10, 'do_single', '2'), $do, 10, '2');
        $split = $line($do, 11, 'do_split', '3');
        $delivery($split, $do, 11, '1');
        $delivery($split, $do, 11, '2');
        $line($do, null, 'do_text', '0');
        $line($do, 12, 'do_undelivered', '1');
        $dk = $order('DK');
        $delivery($line($dk, 10, 'dk_credit', '-2'), $dk, 10, '-2');
        $po = $order('PO');
        $delivery($line($po, 13, 'po_receipt', '1'), $po, 13, '1');
        $delivery($line($po, 13, 'po_already_set', '5', '5'), $po, 13, '4');
        $ko = $order('KO');
        $delivery($line($ko, 14, 'ko_purchase', '4'), $ko, 14, '4');
        $do2 = $order('DO');
        $delivery($line($do2, 15, 'do_other_item', '2'), $do2, 16, '2');
        $delivery($line($do2, 15, 'do_other_order', '1'), $do, 15, '1');
        $delivery($line($do2, 17, 'do_null_quantity', '1'), $do2, 17, null);
    }

    /** @return array<string, string|null> leveret by fixture line name */
    private function leveret(): array
    {
        $out = [];
        foreach (CharacterizationEnv::rows($this->db, 'SELECT beskrivelse, leveret FROM ordrelinjer ORDER BY id') as $row) {
            $out[$row['beskrivelse']] = $row['leveret'];
        }
        return $out;
    }

    /** @return array<string, string> var_name => var_value for the MB-73 settings rows */
    private function settings(): array
    {
        $out = [];
        $rows = CharacterizationEnv::rows($this->db, "SELECT var_name, var_value FROM settings WHERE var_grp = 'system' AND var_name LIKE 'ordrelinjer_leveret%' ORDER BY id");
        foreach ($rows as $row) {
            $this->assertArrayNotHasKey($row['var_name'], $out, 'settings row ' . $row['var_name'] . ' written twice');
            $out[$row['var_name']] = $row['var_value'];
        }
        return $out;
    }

    /** One login: a child process with its own database session. */
    private function login(string $mode, array $args = []): array
    {
        $res = CharacterizationEnv::runChild(__DIR__ . '/support/run_leveret_backfill.php', array_merge([self::dbName(), $mode], $args));
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        $this->assertIsArray($json, "runner must emit JSON (stdout: {$res['stdout']} stderr: {$res['stderr']})");
        $this->assertArrayNotHasKey('error', $json, (string)($json['error'] ?? ''));
        return $json;
    }

    public function testLoginSetsTheDefaultAndBackfillsTheOrderPageSum(): void
    {
        $this->login('segment');

        $this->assertSame(self::EXPECTED, $this->leveret());
        $this->assertSame(['ordrelinjer_leveret_backfilled' => 'yes'], $this->settings(), 'flag written once, cursor removed');
        $default = CharacterizationEnv::one($this->db, "SELECT column_default FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'ordrelinjer' AND column_name = 'leveret'");
        $this->assertSame('0', $default['column_default']);
        $newLine = CharacterizationEnv::one($this->db, "INSERT INTO ordrelinjer (ordre_id, vare_id, antal) VALUES (1, 10, 1) RETURNING leveret");
        $this->assertSame('0.000', $newLine['leveret'], 'a line inserted without leveret starts at 0');

        // A second login changes nothing: no row is rewritten (xmin unchanged) and no settings row is added
        $before = CharacterizationEnv::rows($this->db, 'SELECT id, xmin::text AS xmin FROM ordrelinjer ORDER BY id');
        $this->login('segment');
        $this->assertSame($before, CharacterizationEnv::rows($this->db, 'SELECT id, xmin::text AS xmin FROM ordrelinjer ORDER BY id'));
        $this->assertSame(['ordrelinjer_leveret_backfilled' => 'yes'], $this->settings());
    }

    public function testLoginThatFindsTheBackfillRunningSkipsIt(): void
    {
        // This session plays the login that is running the backfill
        CharacterizationEnv::rows($this->db, "SELECT pg_advisory_lock(hashtext('ordrelinjer_leveret_backfill'))");

        $this->login('segment');

        $untouched = array_fill_keys(array_keys(self::EXPECTED), null);
        $untouched['po_already_set'] = '5.000';
        $this->assertSame($untouched, $this->leveret(), 'nothing backfilled while the other login holds the lock');
        $this->assertSame([], $this->settings(), 'no flag while the other login holds the lock');

        CharacterizationEnv::rows($this->db, "SELECT pg_advisory_unlock(hashtext('ordrelinjer_leveret_backfill'))");
        $this->login('segment');
        $this->assertSame(self::EXPECTED, $this->leveret());
        $this->assertSame(['ordrelinjer_leveret_backfilled' => 'yes'], $this->settings());
    }

    public function testBudgetStopsAfterOneWindowAndTheNextLoginCarriesOn(): void
    {
        // Budget 0: every login does one window of 3 ids (11 lines -> 4 logins)
        $this->assertSame('continued', $this->login('function', ['0', '3'])['status']);
        $this->assertSame(['ordrelinjer_leveret_backfill_id' => '3'], $this->settings());
        $progress = $this->leveret();
        $this->assertSame('2.000', $progress['do_single']);
        $this->assertSame('3.000', $progress['do_split']);
        $this->assertNull($progress['dk_credit'], 'ids above the first window wait for the next login');

        $this->assertSame('continued', $this->login('function', ['0', '3'])['status']);
        $this->assertSame(['ordrelinjer_leveret_backfill_id' => '6'], $this->settings());
        $this->assertSame('continued', $this->login('function', ['0', '3'])['status']);
        $this->assertSame('done', $this->login('function', ['0', '3'])['status']);

        $this->assertSame(self::EXPECTED, $this->leveret());
        $this->assertSame(['ordrelinjer_leveret_backfilled' => 'yes'], $this->settings(), 'flag written, cursor removed');
        $this->assertSame('done', $this->login('function', ['0', '3'])['status']);
    }
}
