<?php
// tests/characterization/includes/NestedTransaktionTest.test.php
//
// Regression tests for D5 / MB-195: transaktion() tracked $db_transaktion_depth
// but always sent raw BEGIN/COMMIT/ROLLBACK, ending outer transactions early on
// PostgreSQL and implicitly committing outer transactions on MySQL/MariaDB.
//
// Covers:
//   1. Outer begin -> inner begin -> insert -> inner commit -> outer rollback: row is gone.
//   2. Inner rollback: inner work is reverted and outer sees $db_modify_fejl = true while
//      remaining inside the outer transaction.
//   3. Three-level nesting and explicit SAVEPOINT pass-through (get_next_invoice_number).
//   4. debitor/bogfor.php nested path: refused posting and outer rollback after inner
//      bogfor() commit both leave zero transaktioner/openpost rows and revert outer writes.
//
// History:
// 20261127 CL/HUG Created for D5 / MB-195 nested transaktion() regression test.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once dirname(__DIR__, 3) . '/includes/db_query.php';

final class NestedTransaktionTest extends CharacterizationTestCase
{
    private static int $invoiceSeq = 95000;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['connection'] = self::$tenant;
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['db'] = CharacterizationEnv::testDb();
        $GLOBALS['brugernavn'] = 'chartest';
        $GLOBALS['db_transaktion_depth'] = 0;
        $GLOBALS['db_modify_fejl'] = false;
        $_SERVER['REQUEST_URI'] = '/saldi/debitor/bogfor.php';

        $tempDir = dirname(__DIR__, 3) . '/temp/' . $GLOBALS['db'];
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        if (pg_transaction_status(self::$tenant) !== PGSQL_TRANSACTION_IDLE) {
            pg_query(self::$tenant, 'ROLLBACK');
        }

        pg_query(self::$tenant, 'CREATE TABLE IF NOT EXISTS hug_nested_tx_probe (tag TEXT NOT NULL)');
        pg_query(self::$tenant, 'TRUNCATE TABLE hug_nested_tx_probe');
    }

    protected function tearDown(): void
    {
        if (pg_transaction_status(self::$tenant) !== PGSQL_TRANSACTION_IDLE) {
            pg_query(self::$tenant, 'ROLLBACK');
        }
        $GLOBALS['db_transaktion_depth'] = 0;
        $GLOBALS['db_modify_fejl'] = false;
        parent::tearDown();
    }

    /** @return list<string> */
    private function probeTags(): array
    {
        $rows = self::rows('SELECT tag FROM hug_nested_tx_probe ORDER BY tag');
        return array_map(static fn(array $r) => (string)$r['tag'], $rows);
    }

    public function test_outer_rollback_reverts_row_inserted_in_committed_inner_transaction(): void
    {
        $this->assertTrue(transaktion('begin'));
        $this->assertSame(1, $GLOBALS['db_transaktion_depth']);

        $this->assertTrue(transaktion('begin'));
        $this->assertSame(2, $GLOBALS['db_transaktion_depth']);

        $res = pg_query(self::$tenant, "INSERT INTO hug_nested_tx_probe (tag) VALUES ('inner-committed')");
        $this->assertNotFalse($res);

        $this->assertTrue(transaktion('commit'));
        $this->assertSame(1, $GLOBALS['db_transaktion_depth']);
        $this->assertSame(
            PGSQL_TRANSACTION_INTRANS,
            pg_transaction_status(self::$tenant),
            'inner commit must release its savepoint without committing the outer transaction'
        );

        $this->assertTrue(transaktion('rollback'));
        $this->assertSame(0, $GLOBALS['db_transaktion_depth']);
        $this->assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status(self::$tenant));

        $this->assertSame([], $this->probeTags(), 'outer rollback must revert rows from a committed inner block');
    }

    public function test_inner_rollback_reverts_inner_work_and_marks_outer_failed(): void
    {
        $this->assertTrue(transaktion('begin'));
        $this->assertFalse($GLOBALS['db_modify_fejl']);
        $this->assertNotFalse(pg_query(self::$tenant, "INSERT INTO hug_nested_tx_probe (tag) VALUES ('outer-row')"));

        $this->assertTrue(transaktion('begin'));
        $this->assertNotFalse(pg_query(self::$tenant, "INSERT INTO hug_nested_tx_probe (tag) VALUES ('inner-row')"));

        $this->assertTrue(transaktion('rollback'));
        $this->assertSame(1, $GLOBALS['db_transaktion_depth']);
        $this->assertTrue(
            $GLOBALS['db_modify_fejl'],
            'inner rollback must set $db_modify_fejl so outer failure checks see it'
        );
        $this->assertSame(
            PGSQL_TRANSACTION_INTRANS,
            pg_transaction_status(self::$tenant),
            'inner rollback must roll back only to its savepoint, leaving outer transaction open'
        );
        $this->assertSame(['outer-row'], $this->probeTags(), 'inner row must be reverted while outer row remains until outer ends');

        // Even if the outer transaction commits, nothing from the rolled-back inner block may persist.
        $this->assertTrue(transaktion('commit'));
        $this->assertSame(0, $GLOBALS['db_transaktion_depth']);
        $this->assertSame(['outer-row'], $this->probeTags());
        $this->assertTrue($GLOBALS['db_modify_fejl'], 'outer caller still sees $db_modify_fejl after inner rollback');
    }

    public function test_deep_nesting_and_explicit_savepoint_passthrough(): void
    {
        $this->assertTrue(transaktion('begin'));
        $this->assertTrue(transaktion('begin'));
        $this->assertTrue(transaktion('begin'));
        $this->assertSame(3, $GLOBALS['db_transaktion_depth']);

        // Explicit savepoint calls (such as get_next_invoice_number) pass through without altering depth.
        $this->assertTrue(transaktion('SAVEPOINT manual_sp'));
        $this->assertSame(3, $GLOBALS['db_transaktion_depth']);
        $this->assertNotFalse(pg_query(self::$tenant, "INSERT INTO hug_nested_tx_probe (tag) VALUES ('manual-reverted')"));
        $this->assertTrue(transaktion('ROLLBACK TO SAVEPOINT manual_sp'));
        $this->assertTrue(transaktion('RELEASE SAVEPOINT manual_sp'));
        $this->assertSame(3, $GLOBALS['db_transaktion_depth']);

        $this->assertNotFalse(pg_query(self::$tenant, "INSERT INTO hug_nested_tx_probe (tag) VALUES ('level-3')"));
        $this->assertTrue(transaktion('rollback'));
        $this->assertSame(2, $GLOBALS['db_transaktion_depth']);
        $this->assertTrue($GLOBALS['db_modify_fejl']);

        $this->assertNotFalse(pg_query(self::$tenant, "INSERT INTO hug_nested_tx_probe (tag) VALUES ('level-2')"));
        $this->assertTrue(transaktion('commit'));
        $this->assertSame(1, $GLOBALS['db_transaktion_depth']);

        $this->assertTrue(transaktion('rollback'));
        $this->assertSame(0, $GLOBALS['db_transaktion_depth']);
        $this->assertSame([], $this->probeTags());
    }

    private function createSalesOrder(bool $makeOutOfBalance): int
    {
        $regnaar = self::$fx->regnaar();
        [$salesAcct, $vatAcct] = self::$fx->vatSalesAccount();

        self::rows("DELETE FROM grupper WHERE art = 'DIV' AND kodenr = '3'");
        self::rows("INSERT INTO grupper (beskrivelse, kode, kodenr, art, box5) VALUES ('D5 straks', '', 3, 'DIV', 'on')");

        $dg = self::one(
            "SELECT kodenr FROM grupper WHERE art = 'DG' AND fiscal_year = $1 ORDER BY kodenr LIMIT 1",
            [$regnaar]
        );
        $this->assertNotNull($dg);

        $kontoNr = 85000 + (++self::$invoiceSeq % 1000);
        $debtor = self::one(
            "INSERT INTO adresser (kontonr, firmanavn, addr1, postnr, bynavn, email, art, gruppe, betalingsbet, betalingsdage)
             VALUES ($1, 'D5 Test Kunde', 'Testvej 1', '8000', 'Aarhus', 'd5@example.invalid', 'D', $2, 'Netto', 8)
             RETURNING id",
            [$kontoNr, (int)$dg['kodenr']]
        );
        $debtorId = (int)$debtor['id'];

        $order = self::one(
            "INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsbet, betalingsdage, kontonr, art, valuta, valutakurs,
                                 ordredate, levdate, fakturadate, fakturanr, ordrenr, sum, moms, momssats, status, konto_id, ref)
             VALUES ('D5 Test Kunde', 'Testvej 1', '8000', 'Aarhus', 'd5@example.invalid', 'Netto', 8, $1, 'DO', 'DKK', 100,
                     CURRENT_DATE, CURRENT_DATE, CURRENT_DATE, $2,
                     (SELECT COALESCE(max(ordrenr), 0) + 1 FROM ordrer), 100.00, 25.00, 25, 2, $3, 'original-ref')
             RETURNING id",
            [$kontoNr, (string)self::$invoiceSeq, $debtorId]
        );
        $orderId = (int)$order['id'];

        // An order line with bogf_konto = 0 triggers forkontrolPosteringsbalance refusal inside bogfor_nu().
        self::rows(
            "INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, beskrivelse, enhed, posnr, antal, pris, rabat, momssats, procent, bogf_konto, vat_account, momsfri)
             VALUES ($1, 0, 'D5ITEM', 'D5 test line', 'stk', 1, 1, 100.00, 0, 25, 100, $2, $3, '')",
            [$orderId, $makeOutOfBalance ? 0 : $salesAcct, $vatAcct]
        );

        return $orderId;
    }

    /** @return array<string, mixed> */
    private function runDebitorBogforPath(string $scenario, int $orderId): array
    {
        $result = CharacterizationEnv::runChild(
            __DIR__ . '/support/run_debitor_bogfor_nested_tx.php',
            [$scenario, $orderId, CharacterizationEnv::testDb()]
        );
        $this->assertSame(0, $result['exit'], "runner failed:\nstdout: {$result['stdout']}\nstderr: {$result['stderr']}");
        $lines = array_values(array_filter(explode("\n", trim($result['stdout']))));
        $decoded = $lines ? json_decode((string)end($lines), true) : null;
        $this->assertIsArray($decoded, "expected JSON output:\nstdout: {$result['stdout']}\nstderr: {$result['stderr']}");
        return $decoded;
    }

    public function test_debitor_bogfor_refused_posting_leaves_no_transaktioner_or_openpost_and_keeps_outer_tx(): void
    {
        $orderId = $this->createSalesOrder(true);
        $out = $this->runDebitorBogforPath('refused', $orderId);

        $this->assertNotSame('OK', $out['svar'], 'out-of-balance order must be refused');
        $this->assertSame(
            PGSQL_TRANSACTION_INTRANS,
            $out['after_bogfor_tx_status'],
            'bogfor() inner rollback must not terminate debitor/bogfor.php outer transaction'
        );
        $this->assertSame(1, $out['after_bogfor_depth']);
        $this->assertTrue($out['after_bogfor_modify_fejl'], 'outer caller must observe $db_modify_fejl after inner rollback');
        $this->assertSame(PGSQL_TRANSACTION_IDLE, $out['final_tx_status']);
        $this->assertSame(0, $out['final_depth']);
        $this->assertSame(0, $out['tx_count'], 'refused posting must leave no transaktioner rows');
        $this->assertSame(0, $out['openpost_count'], 'refused posting must leave no openpost rows');
        $this->assertSame(2, $out['order_status'], 'refused posting must leave order status unchanged');
        $this->assertSame('original-ref', $out['order_ref'], 'outer rollback must also revert pre-bogfor writes');
    }

    public function test_debitor_bogfor_outer_rollback_after_successful_inner_bogfor_leaves_no_rows(): void
    {
        $orderId = $this->createSalesOrder(false);
        $out = $this->runDebitorBogforPath('outer_rollback', $orderId);

        $this->assertSame('OK', $out['svar']);
        $this->assertSame(
            PGSQL_TRANSACTION_INTRANS,
            $out['after_bogfor_tx_status'],
            'bogfor() inner commit must not commit debitor/bogfor.php outer transaction early'
        );
        $this->assertSame(1, $out['after_bogfor_depth']);
        $this->assertGreaterThan(0, $out['after_bogfor_tx_count'], 'bogfor() must have written transaktioner rows inside the open outer transaction');
        $this->assertGreaterThan(0, $out['after_bogfor_openpost_count'], 'bogfor() must have written openpost rows inside the open outer transaction');
        $this->assertSame(PGSQL_TRANSACTION_IDLE, $out['final_tx_status']);
        $this->assertSame(0, $out['final_depth']);
        $this->assertSame(0, $out['tx_count'], 'outer rollback after inner bogfor() commit must revert transaktioner');
        $this->assertSame(0, $out['openpost_count'], 'outer rollback after inner bogfor() commit must revert openpost');
        $this->assertSame(2, $out['order_status']);
        $this->assertSame('original-ref', $out['order_ref']);
    }

    public function test_nested_begin_inside_aborted_postgres_tx_unwinds_without_ending_outer_tx(): void
    {
        $this->assertTrue(transaktion('begin'));
        $this->assertSame(1, $GLOBALS['db_transaktion_depth']);

        // Put the PostgreSQL transaction into INERROR.
        $this->assertFalse(@pg_query(self::$tenant, 'SELECT 1 / 0'));
        $this->assertSame(PGSQL_TRANSACTION_INERROR, pg_transaction_status(self::$tenant));

        // Nested begin fails to create sp_2, flags failure, and advances depth so matching end unwinds cleanly.
        $this->assertFalse(@transaktion('begin'));
        $this->assertSame(2, $GLOBALS['db_transaktion_depth']);
        $this->assertTrue($GLOBALS['db_modify_fejl']);

        $this->assertFalse(transaktion('rollback'));
        $this->assertSame(1, $GLOBALS['db_transaktion_depth']);
        $this->assertSame(PGSQL_TRANSACTION_INERROR, pg_transaction_status(self::$tenant));

        // Outermost rollback still closes the aborted outer transaction cleanly.
        $this->assertTrue(transaktion('rollback'));
        $this->assertSame(0, $GLOBALS['db_transaktion_depth']);
        $this->assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status(self::$tenant));
    }

    public function test_outer_commit_of_aborted_postgres_tx_returns_false_and_flags_failure(): void
    {
        $this->assertTrue(transaktion('begin'));
        $this->assertFalse(@pg_query(self::$tenant, 'SELECT 1 / 0'));
        $this->assertSame(PGSQL_TRANSACTION_INERROR, pg_transaction_status(self::$tenant));

        $this->assertFalse(transaktion('commit'));
        $this->assertSame(0, $GLOBALS['db_transaktion_depth']);
        $this->assertTrue($GLOBALS['db_modify_fejl']);
        $this->assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status(self::$tenant));
    }

    public function test_inner_commit_after_inner_sql_error_unwinds_savepoint_and_keeps_outer_tx(): void
    {
        $this->assertTrue(transaktion('begin'));
        $this->assertNotFalse(pg_query(self::$tenant, "INSERT INTO hug_nested_tx_probe (tag) VALUES ('outer-row')"));

        $this->assertTrue(transaktion('begin'));
        $this->assertNotFalse(pg_query(self::$tenant, "INSERT INTO hug_nested_tx_probe (tag) VALUES ('inner-before-error')"));
        $this->assertFalse(@pg_query(self::$tenant, 'SELECT 1 / 0'));
        $this->assertSame(PGSQL_TRANSACTION_INERROR, pg_transaction_status(self::$tenant));

        // Inner commit sees INERROR, unwinds sp_2 cleanly, flags $db_modify_fejl, and returns false.
        $this->assertFalse(transaktion('commit'));
        $this->assertSame(1, $GLOBALS['db_transaktion_depth']);
        $this->assertTrue($GLOBALS['db_modify_fejl']);
        $this->assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status(self::$tenant));
        $this->assertSame(['outer-row'], $this->probeTags());

        $this->assertTrue(transaktion('rollback'));
        $this->assertSame(0, $GLOBALS['db_transaktion_depth']);
        $this->assertSame([], $this->probeTags());
    }

    public function test_outer_commit_after_inner_commit_persists_both_rows(): void
    {
        $this->assertTrue(transaktion('begin'));
        $this->assertNotFalse(pg_query(self::$tenant, "INSERT INTO hug_nested_tx_probe (tag) VALUES ('outer-kept')"));

        $this->assertTrue(transaktion('begin'));
        $this->assertNotFalse(pg_query(self::$tenant, "INSERT INTO hug_nested_tx_probe (tag) VALUES ('inner-kept')"));
        $this->assertTrue(transaktion('commit'));
        $this->assertSame(1, $GLOBALS['db_transaktion_depth']);
        $this->assertFalse($GLOBALS['db_modify_fejl']);

        $this->assertTrue(transaktion('commit'));
        $this->assertSame(0, $GLOBALS['db_transaktion_depth']);
        $this->assertFalse($GLOBALS['db_modify_fejl']);
        $this->assertSame(['inner-kept', 'outer-kept'], $this->probeTags());
    }
}
