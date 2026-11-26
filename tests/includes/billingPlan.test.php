<?php
// 20261126 CL/ASR The operator panel reads the plan off the billing ledger's recurring orders (includes/stdFunc/billingPlan.php): which product numbers are plans, and when the orders name one plan.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class billingPlan extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../includes/stdFunc/billingPlan.php';
    }

    /** @return array<string, array{0: string, 1: string|null}> */
    public static function skus(): array
    {
        return [
            'Finans 12 months' => ['F12', 'finans'],
            'Finans Grundpakke 3 months' => ['FG3', 'finans'],
            'Finans 1 month' => ['FG1', 'finans'],
            'invoicing fee, not Finans' => ['FG', null],
            'fee with an invoice number' => ['FG129', null],
            'Professionel 12 months' => ['PRO12', 'professionel'],
            'Professionel Grundpakke 6 months' => ['PG6', 'professionel'],
            'Business 3 months' => ['BUS3', 'business'],
            'sign-up product' => ['PROFESSIONEL', 'professionel'],
            'lower case and spaces' => [' bus12 ', 'business'],
            'extra postings add-on' => ['EP1000', null],
            'older Mini product' => ['M12', null],
            'interval not sold' => ['F24', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('skus')]
    public function testProductNumbersThatNameAPlan(string $sku, ?string $plan): void
    {
        $this->assertSame($plan, billingPlanForSku($sku));
    }

    public function testSignupSettingsAddProductNumbers(): void
    {
        $this->assertSame('finans', billingPlanForSku('start', ['START' => 'finans']));
    }

    private static function order(string $no, string $next, array $lines): array
    {
        return ['orderNo' => $no, 'nextInvoiceDate' => $next, 'lines' => $lines];
    }

    private static function line(string $sku, string $description, float $quantity = 1, float $price = 100): array
    {
        return ['sku' => $sku, 'description' => $description, 'quantity' => $quantity, 'price' => $price];
    }

    public function testOneOrderWithAPlanAndAddOns(): void
    {
        $r = billingPlanFromOrders([
            self::order('5001', '2026-12-01', [
                self::line('PRO12', 'Professionel Grundpakke 12 mdr.', 1, 2388),
                self::line('EP1000', 'Ekstra 1000 posteringer 12 mdr.', 2, 722),
                self::line('FG', 'Faktureringsgebyr', 1, 12),
            ]),
        ], '2026-11-26');

        $this->assertSame('professionel', $r['plan']);
        $this->assertSame(['orderNo' => '5001', 'nextInvoiceDate' => '2026-12-01'], $r['order']);
        $this->assertSame(['EP1000', 'FG'], array_column($r['other'], 'sku'));
        $this->assertSame(1, $r['active_orders']);
    }

    public function testTheSamePlanOnTwoOrdersIsOnePlan(): void
    {
        $r = billingPlanFromOrders([
            self::order('1', '2026-12-01', [self::line('F12', 'Finans')]),
            self::order('2', '2027-03-01', [self::line('FG3', 'Finans')]),
        ], '2026-11-26');

        $this->assertSame('finans', $r['plan']);
        $this->assertSame(['finans'], $r['plans']);
    }

    public function testTwoDifferentPlansAreNotGuessed(): void
    {
        $r = billingPlanFromOrders([
            self::order('1', '2026-12-01', [self::line('F12', 'Finans')]),
            self::order('2', '2026-12-01', [self::line('BUS12', 'Business')]),
        ], '2026-11-26');

        $this->assertNull($r['plan']);
        $this->assertSame(['finans', 'business'], $r['plans']);

        $newestFirst = billingPlanFromOrders([
            self::order('2', '2026-12-01', [self::line('BUS12', 'Business')]),
            self::order('1', '2026-12-01', [self::line('F12', 'Finans')]),
        ], '2026-11-26');
        $this->assertSame(['finans', 'business'], $newestFirst['plans'], 'the list follows the catalogue, not the order of the orders');
    }

    public function testAnOrderLeftBehindForMoreThanSixtyDaysDoesNotCount(): void
    {
        $r = billingPlanFromOrders([
            self::order('1', '2026-08-01', [self::line('BUS12', 'Business')]),
            self::order('2', '2026-10-01', [self::line('F3', 'Finans')]),
        ], '2026-11-26');

        $this->assertSame('finans', $r['plan']);
        $this->assertSame(1, $r['active_orders']);
    }

    public function testAnOrderWithoutANextInvoiceDateDoesNotCount(): void
    {
        $r = billingPlanFromOrders([self::order('1', '', [self::line('F12', 'Finans')])], '2026-11-26');

        $this->assertNull($r['plan']);
        $this->assertSame(0, $r['active_orders']);
    }

    public function testACreditedPlanLineIsNotAPlan(): void
    {
        $r = billingPlanFromOrders([self::order('1', '2026-12-01', [self::line('F12', 'Finans', -1)])], '2026-11-26');

        $this->assertNull($r['plan']);
    }

    public function testAnOlderProductIsNamedByTheDearestLine(): void
    {
        $r = billingPlanFromOrders([
            self::order('1', '2026-12-01', [
                self::line('FG', 'Faktureringsgebyr', 1, 12),
                self::line('M12', 'Mini regnskab 12 mdr.', 1, 708),
                self::line('LR15', 'Loyalitetsrabat 15%', 1, -178),
            ]),
        ], '2026-11-26');

        $this->assertNull($r['plan']);
        $this->assertSame([], $r['plans']);
        $this->assertSame('Mini regnskab 12 mdr.', $r['main_other']);
    }

    /** @return array<string, array{0: string|null, 1: bool}> */
    public static function nextInvoiceDates(): array
    {
        return [
            'next month' => ['2026-12-26', true],
            'today' => ['2026-11-26', true],
            'exactly 60 days ago' => ['2026-09-27', true],
            '61 days ago' => ['2026-09-26', false],
            'with a time' => ['2026-12-01 00:00:00', true],
            'empty' => ['', false],
            'missing' => [null, false],
            'not a date' => ['snart', false],
        ];
    }

    #[DataProvider('nextInvoiceDates')]
    public function testARecurringOrderRunsUntilSixtyDaysAfterItsNextInvoiceDate(?string $next, bool $running): void
    {
        $this->assertSame($running, billingOrderIsRunning($next, '2026-11-26'));
    }

    public function testNoOrders(): void
    {
        $r = billingPlanFromOrders([], '2026-11-26');

        $this->assertNull($r['plan']);
        $this->assertSame(0, $r['active_orders']);
        $this->assertNull($r['main_other']);
    }
}
