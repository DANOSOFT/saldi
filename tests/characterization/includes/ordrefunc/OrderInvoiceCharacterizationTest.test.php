<?php
// tests/characterization/includes/ordrefunc/OrderInvoiceCharacterizationTest.test.php
//
// Characterization tests for the order -> invoice conversion (SD-601).
//
// Pins the CURRENT behavior of includes/ordrefunc.php bogfor($ordre_id,'on')
// as production drives it (remoteBooking/api.php:296-337): deliver the lines,
// convert to invoice, assign fakturanr, set status 3. Also pins the guard
// against invoicing twice.
//
// Fixture orders are created the way remoteBooking/api.php CreateOrder does
// it (direct INSERT INTO ordrer + opret_ordrelinje) via the child runner's
// bootstrap; here we insert the rows over pg and let the runner drive the
// ordrefunc code.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20260723 CL/LH SD-601: created.
// 20260916 CDX/LH Isolate disposable test tenants and retain child-process failure diagnostics.
// 20261019 CL/LH MB-73: fast invoicing now records leveret; assert it instead of noting it is not stamped.
// 20261027 CL/LH MB-73 (review): fast invoicing a line still NULL from before the migration keeps its earlier delivery.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../support/CharacterizationEnv.php';

final class OrderInvoiceCharacterizationTest extends TestCase
{
    /** @var resource|\PgSql\Connection */
    private static $tenant;
    private static int $regnaar;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant();
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        $ra = CharacterizationEnv::one(self::$tenant, "SELECT max(kodenr) AS ra FROM grupper WHERE art = 'RA'");
        self::$regnaar = (int)$ra['ra'];
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant) {
            pg_close(self::$tenant);
            self::$tenant = null;
            CharacterizationEnv::teardownTenant();
        }
    }

    /** Create debtor + item + order with one line: qty 2 x 125.00, 25% VAT. */
    private function createOrderFixture(): array
    {
        $t = self::$tenant;

        $dg = CharacterizationEnv::one($t, "SELECT kodenr FROM grupper WHERE art = 'DG' ORDER BY kodenr LIMIT 1");
        $debtor = CharacterizationEnv::one(
            $t,
            "INSERT INTO adresser (kontonr, firmanavn, addr1, postnr, bynavn, email, art, gruppe, betalingsbet, betalingsdage)
             VALUES (91001, 'Chartest Kunde', 'Testvej 1', '8000', 'Aarhus', 'chartest@example.invalid', 'D', $1, 'Netto', 8)
             RETURNING id",
            [$dg ? (int)$dg['kodenr'] : 1]
        );
        $debtorId = (int)$debtor['id'];

        $vg = CharacterizationEnv::one($t, "SELECT kodenr FROM grupper WHERE art = 'VG' AND fiscal_year = $1 ORDER BY kodenr LIMIT 1", [self::$regnaar]);
        $item = CharacterizationEnv::one(
            $t,
            "INSERT INTO varer (varenr, beskrivelse, enhed, salgspris, kostpris, gruppe)
             VALUES ('CHAR1', 'Chartest vare', 'stk', 125.00, 75.00, $1)
             RETURNING id",
            [$vg ? (int)$vg['kodenr'] : 1]
        );
        $itemId = (int)$item['id'];

        $sum = 250.00;      // 2 x 125
        $moms = 62.50;      // 25%
        $order = CharacterizationEnv::one(
            $t,
            "INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsbet, betalingsdage, kontonr, art, valuta,
                                 ordredate, levdate, ordrenr, sum, moms, momssats, status, konto_id, udskriv_til)
             VALUES ('Chartest Kunde', 'Testvej 1', '8000', 'Aarhus', 'chartest@example.invalid', 'Netto', 8, 91001, 'DO', 'DKK',
                     CURRENT_DATE, CURRENT_DATE,
                     (SELECT COALESCE(max(ordrenr), 0) + 1 FROM ordrer), $1, $2, 25, 1, $3, 'email')
             RETURNING id, ordrenr",
            [$sum, $moms, $debtorId]
        );
        $orderId = (int)$order['id'];

        CharacterizationEnv::rows(
            $t,
            "INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, beskrivelse, enhed, posnr, antal, pris, rabat, momssats, procent)
             VALUES ($1, $2, 'CHAR1', 'Chartest vare', 'stk', 1, 2, 125.00, 0, 25, 100)",
            [$orderId, $itemId]
        );

        return [$orderId, $debtorId, $sum, $moms];
    }

    private function runInvoice(string $scenario, int $orderId): array
    {
        $res = CharacterizationEnv::runChild(
            __DIR__ . '/../../support/run_order_invoice.php',
            [$scenario, $orderId, CharacterizationEnv::testDb()]
        );
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $json = $lines !== [] ? json_decode(end($lines), true) : null;
        $this->assertIsArray($json, "runner must emit JSON (stdout: {$res['stdout']} stderr: {$res['stderr']})");
        return $json;
    }

    public function test_invoicing_an_open_order_assigns_invoice_number_and_invoiced_status(): void
    {
        [$orderId, , $sum, $moms] = $this->createOrderFixture();

        $out = $this->runInvoice('invoice', $orderId);

        $this->assertSame('OK', $out['levering'], 'levering reports OK');
        $this->assertSame('OK', $out['bogfor'], 'bogfor reports OK');
        // Observed: the delivered+invoiced order ends at status 4 (the
        // re-invoice guard at includes/ordrefunc.php:1231 only requires >2).
        $this->assertSame('4', (string)$out['status'], 'order status becomes 4 (delivered + invoiced)');
        $this->assertGreaterThan(0, (int)$out['fakturanr'], 'an invoice number is assigned');
        $this->assertEqualsWithDelta($sum, (float)$out['sum'], 0.005, 'order sum unchanged by invoicing');
        $this->assertEqualsWithDelta($moms, (float)$out['moms'], 0.005, 'order VAT unchanged by invoicing');

        $lines = CharacterizationEnv::rows(
            self::$tenant,
            'SELECT antal, pris, leveres, leveret FROM ordrelinjer WHERE ordre_id = $1 AND vare_id > 0',
            [$orderId]
        );
        $this->assertNotEmpty($lines);
        foreach ($lines as $line) {
            // Observed: levering() consumes the pending quantity (leveres was
            // set to antal before the call, and is 0 after) and records it in
            // leveret, even though the fixture inserts the line without one (MB-73).
            $this->assertEqualsWithDelta(0.0, (float)$line['leveres'], 0.001, 'pending delivery quantity consumed');
            $this->assertNotNull($line['leveret'], 'delivered quantity recorded (MB-73)');
            $this->assertEqualsWithDelta(2.0, (float)$line['leveret'], 0.001, 'delivered quantity equals the invoiced quantity');
            $this->assertEqualsWithDelta(2.0, (float)$line['antal'], 0.001, 'ordered quantity unchanged');
        }
    }

    public function test_invoice_numbers_are_sequential_across_orders(): void
    {
        [$firstId] = $this->createOrderFixture();
        $first = $this->runInvoice('invoice', $firstId);
        [$secondId] = $this->createOrderFixture();
        $second = $this->runInvoice('invoice', $secondId);

        $this->assertSame('OK', $second['bogfor']);
        $this->assertSame(
            (int)$first['fakturanr'] + 1,
            (int)$second['fakturanr'],
            'invoice numbers are assigned sequentially'
        );
    }

    public function test_invoicing_twice_is_refused_and_keeps_the_original_invoice_number(): void
    {
        [$orderId] = $this->createOrderFixture();
        $first = $this->runInvoice('invoice', $orderId);
        $this->assertSame('OK', $first['bogfor']);
        $fakturanr = (int)$first['fakturanr'];
        $this->assertGreaterThan(0, $fakturanr);

        $second = $this->runInvoice('reinvoice', $orderId);

        $this->assertStringContainsString(
            'invoice allready created',
            (string)$second['bogfor'],
            'second invoicing attempt is refused (current wording pinned)'
        );
        $this->assertSame($fakturanr, (int)$second['fakturanr'], 'invoice number is unchanged');
        $this->assertSame('4', (string)$second['status'], 'status stays 4');
    }

    /**
     * MB-73 review: a line from before ordrelinjer.leveret got DEFAULT 0 is still NULL until the login backfill
     * (includes/opdat_func/backfillOrderLineDelivered.php) reaches its id window, and API deliveries never log in.
     * Here 1 of 3 was delivered earlier (batch_salg) and fast invoicing delivers the other 2 first. leveret must be 3,
     * as when the backfill runs first, and the backfill, which only fills NULL lines, must leave it at 3.
     */
    public function test_fast_invoicing_a_line_still_null_from_before_the_migration_keeps_its_earlier_delivery(): void
    {
        [$orderId] = $this->createOrderFixture();
        $t = self::$tenant;
        CharacterizationEnv::rows($t, 'UPDATE ordrer SET sum = 375, moms = 93.75 WHERE id = $1', [$orderId]);
        $line = CharacterizationEnv::one(
            $t,
            'UPDATE ordrelinjer SET antal = 3, leveret = NULL WHERE ordre_id = $1 AND vare_id > 0 RETURNING id, vare_id',
            [$orderId]
        );
        $lineId = (int)$line['id'];
        CharacterizationEnv::rows(
            $t,
            "INSERT INTO batch_salg (batch_kob_id, vare_id, variant_id, linje_id, salgsdate, ordre_id, antal, pris, lev_nr, lager)
             VALUES (0, $1, 0, $2, CURRENT_DATE, $3, 1, 125.00, 1, 1)",
            [(int)$line['vare_id'], $lineId, $orderId]
        );
        // The backfill has not run on this tenant yet
        CharacterizationEnv::rows($t, "DELETE FROM settings WHERE var_grp = 'system' AND var_name LIKE 'ordrelinjer_leveret%'");

        $out = $this->runInvoice('invoice', $orderId);
        $this->assertSame('OK', $out['levering'], 'levering reports OK');
        $this->assertSame('OK', $out['bogfor'], 'bogfor reports OK');

        $state = function () use ($t, $lineId): array {
            return CharacterizationEnv::one(
                $t,
                'SELECT l.leveres, l.leveret, (SELECT sum(b.antal) FROM batch_salg b
                  WHERE b.linje_id = l.id AND b.ordre_id = l.ordre_id AND b.vare_id = l.vare_id) AS delivered
                 FROM ordrelinjer l WHERE l.id = $1',
                [$lineId]
            );
        };
        $after = $state();
        $this->assertEqualsWithDelta(3.0, (float)$after['delivered'], 0.001, 'fast invoicing delivered the remaining 2');
        $this->assertEqualsWithDelta(0.0, (float)$after['leveres'], 0.001, 'pending delivery quantity consumed');
        $this->assertNotNull($after['leveret']);
        $this->assertEqualsWithDelta(3.0, (float)$after['leveret'], 0.001, 'leveret counts the earlier delivery too');

        // Then a login runs the backfill to the end
        $res = CharacterizationEnv::runChild(
            __DIR__ . '/../support/run_leveret_backfill.php',
            [CharacterizationEnv::testDb(), 'function', 60, 20000]
        );
        $lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $backfill = $lines !== [] ? json_decode(end($lines), true) : null;
        $this->assertSame(['status' => 'done'], $backfill, "backfill runner: {$res['stdout']} {$res['stderr']}");
        $this->assertEqualsWithDelta(3.0, (float)$state()['leveret'], 0.001, 'the backfill leaves the delivered line at 3');
    }
}
