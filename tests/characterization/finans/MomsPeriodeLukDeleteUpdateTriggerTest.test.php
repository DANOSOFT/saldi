<?php
// tests/characterization/finans/MomsPeriodeLukDeleteUpdateTriggerTest.test.php
//
// Regression tests for P1: check_moms_periode_luk() and tr_check_moms_periode_luk
// previously ran BEFORE INSERT OR UPDATE and only checked NEW.transdate, leaving
// DELETE of rows in closed VAT periods and UPDATE moving rows out of closed VAT
// periods unblocked. Also verifies that existing tenants with the legacy
// INSERT/UPDATE-only function and trigger are detected and upgraded in place.
//
// History:
// 20261127 CL/HUG Created for P1 moms_periode_luk DELETE and UPDATE-out trigger enforcement.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/MomsPeriodeLukEnv.php';
require_once dirname(__DIR__, 3) . '/includes/db_query.php';
require_once dirname(__DIR__, 3) . '/includes/std_func.php';

final class MomsPeriodeLukDeleteUpdateTriggerTest extends TestCase
{
    /** @var resource|\PgSql\Connection */
    private static $tenant;

    public static function setUpBeforeClass(): void
    {
        $reason = MomsPeriodeLukEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        MomsPeriodeLukEnv::bootstrapTenant();
        self::$tenant = MomsPeriodeLukEnv::connect(MomsPeriodeLukEnv::testDb());
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenant) {
            self::pointAtTenant();
            moms_periode_luk_ensure_schema();
            pg_close(self::$tenant);
            self::$tenant = null;
            MomsPeriodeLukEnv::teardownTenant();
        }
    }

    protected function setUp(): void
    {
        self::pointAtTenant();
        if (pg_transaction_status(self::$tenant) !== PGSQL_TRANSACTION_IDLE) {
            pg_query(self::$tenant, 'ROLLBACK');
        }
        pg_query(self::$tenant, 'DROP TRIGGER IF EXISTS tr_check_moms_periode_luk ON transaktioner');
        pg_query(self::$tenant, 'DROP FUNCTION IF EXISTS check_moms_periode_luk()');
        pg_query(self::$tenant, 'DROP TABLE IF EXISTS moms_periode_luk');
        pg_query(self::$tenant, "DELETE FROM transaktioner WHERE beskrivelse LIKE 'P1-TEST%'");
        moms_periode_luk_ensure_schema();
    }

    private static function pointAtTenant(): void
    {
        global $connection, $db, $db_type;
        $connection = self::$tenant;
        $db = MomsPeriodeLukEnv::testDb();
        $db_type = 'postgresql';
        $_SERVER['REQUEST_URI'] = '/x';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    private function insertTransaction(string $transdate, string $desc): int
    {
        $res = pg_query_params(
            self::$tenant,
            "INSERT INTO transaktioner (bilag, transdate, beskrivelse, kontonr, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id)
             VALUES (1, $1, $2, 1000, 100.00, 0.00, 0, 0, CURRENT_DATE, '12:00', '0', 0, 0)
             RETURNING id",
            [$transdate, $desc]
        );
        $this->assertNotFalse($res, 'setup insert must succeed: ' . pg_last_error(self::$tenant));
        $row = pg_fetch_assoc($res);
        return (int)$row['id'];
    }

    private function setPeriodStatus(int $year, int $month, string $status): void
    {
        $res = pg_query_params(
            self::$tenant,
            "INSERT INTO moms_periode_luk (kalender_aar, kalender_maaned, status)
             VALUES ($1, $2, $3)
             ON CONFLICT (kalender_aar, kalender_maaned) DO UPDATE SET status = EXCLUDED.status",
            [$year, $month, $status]
        );
        $this->assertNotFalse($res, 'setting period status failed: ' . pg_last_error(self::$tenant));
    }

    /** @return list<array<string, mixed>> */
    private function fetchTransaktion(int $id): array
    {
        $res = pg_query_params(self::$tenant, 'SELECT id, transdate FROM transaktioner WHERE id = $1', [$id]);
        $this->assertNotFalse($res);
        $rows = pg_fetch_all($res);
        return is_array($rows) ? $rows : [];
    }

    public function test_delete_in_closed_vat_period_is_blocked(): void
    {
        $id = $this->insertTransaction('2026-03-15', 'P1-TEST closed delete');
        $this->setPeriodStatus(2026, 3, 'closed');

        $res = @pg_query_params(self::$tenant, 'DELETE FROM transaktioner WHERE id = $1', [$id]);
        $err = pg_last_error(self::$tenant);

        $this->assertFalse($res, 'DELETE in a closed VAT period must raise');
        $this->assertStringContainsString('Perioden 03-2026 er lukket for bogfoering', $err);

        $still = $this->fetchTransaktion($id);
        $this->assertCount(1, $still, 'blocked DELETE must leave the ledger row intact');
    }

    public function test_update_moving_row_out_of_closed_vat_period_is_blocked(): void
    {
        $id = $this->insertTransaction('2026-03-15', 'P1-TEST closed move out');
        $this->setPeriodStatus(2026, 3, 'closed');
        $this->setPeriodStatus(2026, 4, 'open');

        $res = @pg_query_params(
            self::$tenant,
            "UPDATE transaktioner SET transdate = '2026-04-10' WHERE id = $1",
            [$id]
        );
        $err = pg_last_error(self::$tenant);

        $this->assertFalse($res, 'UPDATE moving a row out of a closed VAT period must raise');
        $this->assertStringContainsString('Perioden 03-2026 er lukket for bogfoering', $err);

        $rows = $this->fetchTransaktion($id);
        $this->assertSame('2026-03-15', $rows[0]['transdate'], 'blocked UPDATE must keep original transdate');
    }

    public function test_update_moving_row_into_closed_vat_period_is_blocked(): void
    {
        $this->setPeriodStatus(2026, 3, 'closed');
        $this->setPeriodStatus(2026, 4, 'open');
        $id = $this->insertTransaction('2026-04-10', 'P1-TEST move in');

        $res = @pg_query_params(
            self::$tenant,
            "UPDATE transaktioner SET transdate = '2026-03-20' WHERE id = $1",
            [$id]
        );
        $err = pg_last_error(self::$tenant);

        $this->assertFalse($res, 'UPDATE moving a row into a closed VAT period must raise');
        $this->assertStringContainsString('Perioden 03-2026 er lukket for bogfoering', $err);
    }

    public function test_insert_in_closed_vat_period_is_blocked(): void
    {
        $this->setPeriodStatus(2026, 3, 'closed');

        $res = @pg_query_params(
            self::$tenant,
            "INSERT INTO transaktioner (bilag, transdate, beskrivelse, kontonr, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id)
             VALUES (1, '2026-03-10', 'P1-TEST closed insert', 1000, 50.00, 0.00, 0, 0, CURRENT_DATE, '12:00', '0', 0, 0)",
            []
        );
        $err = pg_last_error(self::$tenant);

        $this->assertFalse($res, 'INSERT in a closed VAT period must raise');
        $this->assertStringContainsString('Perioden 03-2026 er lukket for bogfoering', $err);
    }

    public function test_delete_and_update_in_open_vat_period_succeed(): void
    {
        $this->setPeriodStatus(2026, 3, 'closed');
        $this->setPeriodStatus(2026, 4, 'open');
        $id = $this->insertTransaction('2026-04-10', 'P1-TEST open row');

        $upd = pg_query_params(
            self::$tenant,
            "UPDATE transaktioner SET transdate = '2026-04-18' WHERE id = $1",
            [$id]
        );
        $this->assertNotFalse($upd, 'UPDATE within an open period must succeed');
        $updated = $this->fetchTransaktion($id);
        $this->assertSame('2026-04-18', $updated[0]['transdate'], 'open-period UPDATE must persist the new date');

        $del = pg_query_params(self::$tenant, 'DELETE FROM transaktioner WHERE id = $1', [$id]);
        $this->assertNotFalse($del, 'DELETE in an open period must succeed');
        $this->assertSame(1, pg_affected_rows($del), 'RETURN OLD must actually delete the row');

        $remaining = $this->fetchTransaktion($id);
        $this->assertSame([], $remaining);
    }

    public function test_fresh_install_includes_note_column_and_legacy_upgrade_preserves_existing_note(): void
    {
        // Fresh install via moms_periode_luk_ensure_schema() must include the note column used by finans/moms_periode.php.
        $res = pg_query(
            self::$tenant,
            "INSERT INTO moms_periode_luk (kalender_aar, kalender_maaned, status, note)
             VALUES (2026, 6, 'closed', 'Locked for Q2 VAT filing')"
        );
        $this->assertNotFalse($res, 'fresh moms_periode_luk table must accept note writes: ' . pg_last_error(self::$tenant));

        // Replace function and trigger with legacy INSERT/UPDATE-only versions and verify upgrade preserves the closed row and note.
        pg_query(self::$tenant, 'DROP TRIGGER IF EXISTS tr_check_moms_periode_luk ON transaktioner');
        $legacyFn = "CREATE OR REPLACE FUNCTION check_moms_periode_luk() RETURNS TRIGGER AS \$\$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM moms_periode_luk
                    WHERE kalender_aar = EXTRACT(YEAR FROM NEW.transdate)
                      AND kalender_maaned = EXTRACT(MONTH FROM NEW.transdate)
                      AND status = 'closed'
                ) THEN
                    RAISE EXCEPTION 'Perioden % er lukket for bogfoering - kontakt bogholder for at genaabne.',
                        TO_CHAR(NEW.transdate, 'MM-YYYY');
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql";
        $this->assertNotFalse(pg_query(self::$tenant, $legacyFn));
        $this->assertNotFalse(pg_query(
            self::$tenant,
            'CREATE TRIGGER tr_check_moms_periode_luk BEFORE INSERT OR UPDATE ON transaktioner FOR EACH ROW EXECUTE FUNCTION check_moms_periode_luk()'
        ));

        $after = moms_periode_luk_ensure_schema();
        $this->assertSame(['table' => true, 'function' => true, 'trigger' => true], $after);

        $kept = pg_fetch_assoc(pg_query(self::$tenant, 'SELECT status, note FROM moms_periode_luk WHERE kalender_aar = 2026 AND kalender_maaned = 6'));
        $this->assertSame('closed', $kept['status']);
        $this->assertSame('Locked for Q2 VAT filing', $kept['note']);
    }

    public function test_replica_only_trigger_is_detected_as_not_ready_and_repaired(): void
    {
        $this->assertNotFalse(pg_query(
            self::$tenant,
            'ALTER TABLE transaktioner ENABLE REPLICA TRIGGER tr_check_moms_periode_luk'
        ));

        $status = moms_periode_luk_schema_status();
        $this->assertFalse($status['trigger'], 'replica-only trigger does not fire on normal writes and must be flagged');
        $this->assertFalse(moms_periode_luk_schema_ready());

        $repaired = moms_periode_luk_ensure_schema();
        $this->assertSame(['table' => true, 'function' => true, 'trigger' => true], $repaired);
        $this->assertTrue(moms_periode_luk_schema_ready());

        // Verify the repaired trigger actively blocks DELETE in a closed month.
        $id = $this->insertTransaction('2026-07-10', 'P1-TEST after replica repair');
        $this->setPeriodStatus(2026, 7, 'closed');
        $this->assertFalse(@pg_query_params(self::$tenant, 'DELETE FROM transaktioner WHERE id = $1', [$id]));
    }

    public function test_tilbagefor_keeps_openpost_transaktioner_and_journal_status_intact_on_closed_period(): void
    {
        $kladdeRes = pg_query(
            self::$tenant,
            "INSERT INTO kladdeliste (kladdedate, bogfort, kladdenote, oprettet_af)
             VALUES ('2026-08-10', '!', 'P1-TEST recovery', 'chartest')
             RETURNING id"
        );
        $this->assertNotFalse($kladdeRes);
        $kladdeId = (int)pg_fetch_assoc($kladdeRes)['id'];

        $txRes = pg_query_params(
            self::$tenant,
            "INSERT INTO transaktioner (bilag, transdate, beskrivelse, kontonr, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id)
             VALUES (1, '2026-08-10', 'P1-TEST recovery tx', 1000, 100.00, 0.00, $1, 0, CURRENT_DATE, '12:00', '0', 0, 0)",
            [$kladdeId]
        );
        $this->assertNotFalse($txRes);
        $opRes = pg_query_params(
            self::$tenant,
            "INSERT INTO openpost (konto_id, konto_nr, faktnr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr)
             VALUES (1, '1000', 'P1', 100.00, 'P1-TEST recovery op', '0', '2026-08-10', $1, 0)",
            [$kladdeId]
        );
        $this->assertNotFalse($opRes);
        $this->setPeriodStatus(2026, 8, 'closed');

        // Run the atomic tilbagefor() sequence in webservice mode so db_modify flags $db_modify_fejl instead of exiting.
        $src = (string)file_get_contents(dirname(__DIR__, 3) . '/finans/kassekladde.php');
        $this->assertMatchesRegularExpression(
            '/function\s+tilbagefor\s*\(\$kladde_id\)\s*\{[\s\S]*?transaktion\(\'begin\'\);[\s\S]*?delete from transaktioner[\s\S]*?delete from openpost[\s\S]*?transaktion\(\'rollback\'\);/i',
            $src,
            'tilbagefor() must wrap recovery in a transaction and delete transaktioner before openpost'
        );

        // Execute the exact tilbagefor() function body against the closed-period journal.
        global $webservice, $db_modify_fejl, $db_transaktion_depth;
        $prevWs = $webservice ?? null;
        $webservice = true;
        $db_modify_fejl = false;
        $db_transaktion_depth = 0;
        try {
            transaktion('begin');
            @db_modify("delete from transaktioner where kladde_id = '$kladdeId'", __FILE__ . ' linje ' . __LINE__);
            @db_modify("delete from openpost where kladde_id = '$kladdeId'", __FILE__ . ' linje ' . __LINE__);
            @db_modify("update kladdeliste set bogfort = '-' where id = '$kladdeId'", __FILE__ . ' linje ' . __LINE__);
            if (!empty($db_modify_fejl)) {
                transaktion('rollback');
            } else {
                transaktion('commit');
            }
        } finally {
            $webservice = $prevWs;
            if (pg_transaction_status(self::$tenant) !== PGSQL_TRANSACTION_IDLE) {
                pg_query(self::$tenant, 'ROLLBACK');
            }
            $db_transaktion_depth = 0;
        }

        $this->assertTrue($db_modify_fejl, 'closed-period trigger refusal must flag $db_modify_fejl');
        $txCnt = (int)pg_fetch_assoc(pg_query_params(self::$tenant, 'SELECT count(*) AS c FROM transaktioner WHERE kladde_id = $1', [$kladdeId]))['c'];
        $opCnt = (int)pg_fetch_assoc(pg_query_params(self::$tenant, 'SELECT count(*) AS c FROM openpost WHERE kladde_id = $1', [$kladdeId]))['c'];
        $status = trim((string)pg_fetch_assoc(pg_query_params(self::$tenant, 'SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId]))['bogfort']);
        $this->assertSame(1, $txCnt, 'transaktioner row must stay intact');
        $this->assertSame(1, $opCnt, 'openpost row must stay intact');
        $this->assertSame('!', $status, 'kladdeliste status must stay !');
    }

    public function test_ensure_schema_detects_and_upgrades_legacy_insert_update_only_install(): void
    {
        // Replace function and trigger with the legacy INSERT/UPDATE-only definitions from origin/master.
        pg_query(self::$tenant, 'DROP TRIGGER IF EXISTS tr_check_moms_periode_luk ON transaktioner');
        $legacyFn = "CREATE OR REPLACE FUNCTION check_moms_periode_luk() RETURNS TRIGGER AS \$\$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM moms_periode_luk
                    WHERE kalender_aar = EXTRACT(YEAR FROM NEW.transdate)
                      AND kalender_maaned = EXTRACT(MONTH FROM NEW.transdate)
                      AND status = 'closed'
                ) THEN
                    RAISE EXCEPTION 'Perioden % er lukket for bogfoering - kontakt bogholder for at genaabne.',
                        TO_CHAR(NEW.transdate, 'MM-YYYY');
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql";
        $this->assertNotFalse(pg_query(self::$tenant, $legacyFn));
        $this->assertNotFalse(pg_query(
            self::$tenant,
            'CREATE TRIGGER tr_check_moms_periode_luk BEFORE INSERT OR UPDATE ON transaktioner FOR EACH ROW EXECUTE FUNCTION check_moms_periode_luk()'
        ));

        $before = moms_periode_luk_schema_status();
        $this->assertTrue($before['table']);
        $this->assertFalse($before['function'], 'legacy function lacking OLD.transdate check must be flagged for upgrade');
        $this->assertFalse($before['trigger'], 'legacy INSERT/UPDATE-only trigger must be flagged for upgrade');
        $this->assertFalse(moms_periode_luk_schema_ready());

        $after = moms_periode_luk_ensure_schema();
        $this->assertSame(['table' => true, 'function' => true, 'trigger' => true], $after);
        $this->assertTrue(moms_periode_luk_schema_ready());

        // Second call is an idempotent no-op.
        $noop = moms_periode_luk_ensure_schema();
        $this->assertSame(['table' => true, 'function' => true, 'trigger' => true], $noop);

        // Upgraded tenant now blocks DELETE in a closed period.
        $id = $this->insertTransaction('2026-05-10', 'P1-TEST upgraded delete');
        $this->setPeriodStatus(2026, 5, 'closed');
        $res = @pg_query_params(self::$tenant, 'DELETE FROM transaktioner WHERE id = $1', [$id]);
        $this->assertFalse($res, 'upgraded tenant must block DELETE in closed period');
    }

    public function test_search_path_with_leading_empty_schema_reuses_public_locks_without_shadowing(): void
    {
        $id = $this->insertTransaction('2026-09-10', 'P1-TEST search_path');
        $this->setPeriodStatus(2026, 9, 'closed');

        $this->assertNotFalse(pg_query(self::$tenant, 'CREATE SCHEMA IF NOT EXISTS hug_leading_role_schema'));
        $this->assertNotFalse(pg_query(self::$tenant, 'SET search_path TO hug_leading_role_schema, public'));
        try {
            $status = moms_periode_luk_schema_status();
            $this->assertSame(['table' => true, 'function' => true, 'trigger' => true], $status);

            $ensured = moms_periode_luk_ensure_schema();
            $this->assertSame(['table' => true, 'function' => true, 'trigger' => true], $ensured);

            $shadow = pg_fetch_assoc(pg_query(
                self::$tenant,
                "SELECT count(*) AS c FROM information_schema.tables WHERE table_schema = 'hug_leading_role_schema' AND table_name = 'moms_periode_luk'"
            ));
            $this->assertSame('0', $shadow['c'], 'must not create a shadowing empty moms_periode_luk table in the leading schema');

            $this->assertFalse(
                @pg_query_params(self::$tenant, 'DELETE FROM transaktioner WHERE id = $1', [$id]),
                'existing public VAT lock must still block DELETE under multi-schema search_path'
            );
        } finally {
            pg_query(self::$tenant, 'RESET search_path');
            pg_query(self::$tenant, 'DROP SCHEMA IF EXISTS hug_leading_role_schema CASCADE');
        }
    }

    public function test_mysql_reports_trigger_unavailable_without_querying_pg_catalog(): void
    {
        global $db_type;
        $prev = $db_type;
        $db_type = 'mysqli';
        try {
            $status = moms_periode_luk_schema_status();
            $this->assertSame(['table' => false, 'function' => false, 'trigger' => false], $status);
            $ensured = moms_periode_luk_ensure_schema();
            $this->assertSame(['table' => false, 'function' => false, 'trigger' => false], $ensured);
        } finally {
            $db_type = $prev;
        }
    }
}
