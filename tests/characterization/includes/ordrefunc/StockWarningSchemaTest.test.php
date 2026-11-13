<?php
// 20261113 CDX/NTR SD-599 A table in a different schema must not block this tenant's warning log setup.

require_once __DIR__ . '/../../support/CharacterizationTestCase.php';

final class StockWarningSchemaTest extends CharacterizationTestCase
{
    public function test_another_schema_does_not_hide_the_missing_tenant_log(): void
    {
        self::rows('CREATE SCHEMA sd599_other');
        self::rows('CREATE TABLE sd599_other.order_stock_warning_log (id integer)');
        self::rows('DROP TABLE IF EXISTS order_stock_warning_log');
        $item = self::$fx->item(['stocked' => false]);
        try {
            $out = $this->runChildJson('run_stock_warning.php', [CharacterizationEnv::testDb(), $item['id'], 'ensure', 0, 'legacy']);
            self::assertTrue($out['ready']);
            self::assertCount(1, self::rows("SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'order_stock_warning_log'"));
        } finally {
            self::rows('DROP SCHEMA sd599_other CASCADE');
        }
    }
}
