<?php
// tests/shopify/SchedulerTest.php
//
// shopify_schedule_due() (SD-737), pure: slot keys, flow gating, and the DST changes in
// Copenhagen (2026-03-29 spring forward, 2026-10-25 fall back). No database needed.
//
// History:
// 20261026 Sawaneh SD-737: created.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/shopify/scheduler.php';

final class SchedulerTest extends TestCase
{
    private const SHOPS = [
        ['id' => 1, 'shop_key' => 'hml', 'prices_enabled' => 't', 'orders_enabled' => 't', 'customers_enabled' => 'f', 'stock_publish_enabled' => 't'],
        ['id' => 2, 'shop_key' => 'b2', 'prices_enabled' => 'f', 'orders_enabled' => 'f', 'customers_enabled' => 't', 'stock_publish_enabled' => 'f'],
        ['id' => 3, 'shop_key' => 'off', 'prices_enabled' => 'f', 'orders_enabled' => 'f', 'customers_enabled' => 'f', 'stock_publish_enabled' => 'f'],
    ];

    private static function at(string $local): DateTimeImmutable
    {
        return new DateTimeImmutable($local, new DateTimeZone('Europe/Copenhagen'));
    }

    /** @return array<string, string> "type shop" => dedupe key */
    private static function due(DateTimeImmutable $now): array
    {
        $out = [];
        foreach (shopify_schedule_due($now, self::SHOPS) as $slot) {
            $out[$slot['type'] . ' ' . ($slot['shop_id'] ?? 'tenant')] = $slot['dedupe_key'];
        }
        ksort($out);
        return $out;
    }

    public function testSlotsKeysAndFlowGating(): void
    {
        $due = self::due(self::at('2026-10-26 09:22:10'));
        $this->assertSame([
            'monitor.counterpart 1' => 'hml:2026-10-26T08:15Z',
            'monitor.overview tenant' => 'tenant:2026-10-26',
            'price.window 1' => 'hml:2026-10-26T08:15Z',
            'runtime.purge tenant' => 'tenant:2026-10-26',
            'stock.reconcile 1' => 'hml:2026-10-26',
            'webhook.health 1' => 'hml:2026-10-26T08:00Z',
            'webhook.health 2' => 'b2:2026-10-26T08:00Z',
        ], $due, 'quarter-hour and hourly slots on the UTC grid, daily ones by Copenhagen date, only for enabled flows');
        $slot = shopify_schedule_due(self::at('2026-10-26 09:22:10'), self::SHOPS)[0];
        $this->assertSame(['type', 'shop_id', 'dedupe_key', 'schedule_slot', 'priority', 'payload'], array_keys($slot));
        $this->assertSame(50, $slot['priority']);
    }

    public function testDailySlotsWaitForTheirLocalTime(): void
    {
        $early = self::due(self::at('2026-10-26 01:59:00'));
        $this->assertArrayNotHasKey('stock.reconcile 1', $early);
        $this->assertArrayNotHasKey('monitor.overview tenant', $early);
        $this->assertSame('hml:2026-10-26', self::due(self::at('2026-10-26 02:00:00'))['stock.reconcile 1']);
        $this->assertArrayNotHasKey('runtime.purge tenant', self::due(self::at('2026-10-26 07:14:59')));
        $this->assertSame('tenant:2026-10-26', self::due(self::at('2026-10-26 07:15:00'))['runtime.purge tenant']);
        $this->assertSame('tenant:2026-10-26', self::due(self::at('2026-10-26 23:59:00'))['runtime.purge tenant'], 'a late run still catches the day');
    }

    public function testFallBackRunsTheNightlySlotOnceAndKeepsSubDailyKeysApart(): void
    {
        $first = new DateTimeImmutable('2026-10-25T02:30:00+02:00');
        $second = new DateTimeImmutable('2026-10-25T02:30:00+01:00');
        $this->assertSame('hml:2026-10-25', self::due($first)['stock.reconcile 1']);
        $this->assertSame('hml:2026-10-25', self::due($second)['stock.reconcile 1'], 'the repeated 02:30 is the same daily slot');
        $this->assertSame('hml:2026-10-25T00:30Z', self::due($first)['price.window 1']);
        $this->assertSame('hml:2026-10-25T01:30Z', self::due($second)['price.window 1'], 'the repeated local hour gets its own quarter-hour keys');
    }

    public function testSpringForwardStillRunsTheNightlySlot(): void
    {
        $this->assertArrayNotHasKey('stock.reconcile 1', self::due(new DateTimeImmutable('2026-03-29T01:59:00+01:00')));
        $after = self::due(new DateTimeImmutable('2026-03-29T03:00:00+02:00'));
        $this->assertSame('hml:2026-03-29', $after['stock.reconcile 1'], '02:00 does not exist that night; the slot runs from 03:00');
    }

    public function testNoShopsLeavesOnlyTenantSlots(): void
    {
        $due = [];
        foreach (shopify_schedule_due(self::at('2026-10-26 08:00:00'), []) as $slot) {
            $due[] = $slot['type'];
        }
        $this->assertSame(['monitor.overview', 'runtime.purge'], $due);
    }
}
