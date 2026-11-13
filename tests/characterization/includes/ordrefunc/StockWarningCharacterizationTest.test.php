<?php
// 20261113 CDX/NTR SD-599 Pin stock-warning quantities, thresholds and approval log rows before extraction.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../support/CharacterizationEnv.php';

final class StockWarningCharacterizationTest extends TestCase
{
    private static $tenant;
    private static int $itemId;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant(static::class);
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO grupper (art, kodenr, box8) VALUES ('VG', 991, 'on'), ('VG', 992, 'off')");
        $item = CharacterizationEnv::one(
            self::$tenant,
            "INSERT INTO varer (varenr, beskrivelse, gruppe, beholdning, min_lager)
             VALUES ('SD599-STOCK', 'Æble og øl', 991, 99, 3) RETURNING id"
        );
        self::$itemId = (int)$item['id'];
        CharacterizationEnv::rows(
            self::$tenant,
            'INSERT INTO lagerstatus (vare_id, lager, beholdning) VALUES ($1, 1, 2), ($1, 2, 3)',
            [self::$itemId]
        );
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO settings (var_name, var_grp, var_value) VALUES ('min_beholdning', 'productOptions', '4')");
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenant) {
            pg_close(self::$tenant);
            self::$tenant = null;
            CharacterizationEnv::teardownTenant();
        }
    }

    private function runWarning(string $action, float $quantity = 0, string $entry = 'legacy'): array
    {
        $result = CharacterizationEnv::runChild(
            __DIR__ . '/../../support/run_stock_warning.php',
            [CharacterizationEnv::testDb(), self::$itemId, $action, $quantity, $entry]
        );
        self::assertSame(0, $result['exit'], $result['stderr']);
        $out = json_decode(trim($result['stdout']), true);
        self::assertIsArray($out, $result['stdout'] . $result['stderr']);
        return $out;
    }

    public function test_quantity_uses_all_warehouses_and_strict_minimum_comparison(): void
    {
        $atMinimum = $this->runWarning('check', 2);
        self::assertFalse($atMinimum['out_of_stock']);
        self::assertSame(5, $atMinimum['beholdning']);
        self::assertSame(3, $atMinimum['projected_beholdning']);
        $below = $this->runWarning('check', 2.005);
        self::assertTrue($below['out_of_stock']);
        self::assertEqualsWithDelta(2.995, $below['projected_beholdning'], 0.0001);
        self::assertSame('Æble og øl', $below['beskrivelse']);
    }

    public function test_default_minimum_and_non_stock_managed_group_keep_their_behavior(): void
    {
        CharacterizationEnv::rows(self::$tenant, 'UPDATE varer SET min_lager = 0 WHERE id = $1', [self::$itemId]);
        try {
            $out = $this->runWarning('check', 2);
            self::assertTrue($out['out_of_stock']);
            self::assertSame(4, $out['min_lager']);
            CharacterizationEnv::rows(self::$tenant, 'UPDATE varer SET gruppe = 992 WHERE id = $1', [self::$itemId]);
            self::assertFalse($this->runWarning('check', 1250.5)['out_of_stock']);
        } finally {
            CharacterizationEnv::rows(self::$tenant, 'UPDATE varer SET min_lager = 3, gruppe = 991 WHERE id = $1', [self::$itemId]);
        }
    }

    public function test_approval_preserves_trimmed_unicode_note_and_rejects_blank_note(): void
    {
        $out = $this->runWarning('log');
        self::assertTrue($out['accepted']);
        self::assertFalse($out['blank']);
        $rows = CharacterizationEnv::rows(self::$tenant, 'SELECT * FROM order_stock_warning_log WHERE ordre_id = 599');
        self::assertCount(1, $rows);
        self::assertSame("Kunden siger 'ja' til æøå", $rows[0]['note']);
        self::assertSame('chartest', $rows[0]['employee_name']);
        self::assertNull($rows[0]['linje_id']);
        self::assertSame('SD599-STOCK', $rows[0]['varenr']);
        self::assertEqualsWithDelta(5, (float)$rows[0]['beholdning'], 0.0001);
        self::assertEqualsWithDelta(3, (float)$rows[0]['min_lager'], 0.0001);
    }

    public function test_standalone_module_checks_stock_without_loading_the_order_engine(): void
    {
        $out = $this->runWarning('check', 3, 'module');
        self::assertFalse($out['core_loaded']);
        self::assertTrue($out['out_of_stock']);
        self::assertSame(5, $out['beholdning']);
    }
}
