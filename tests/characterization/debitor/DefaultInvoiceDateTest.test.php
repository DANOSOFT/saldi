<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// 20261029 CL/MMR SST-911 Created. default_invoice_date() in debitor/orderIncludes/defaultInvoiceDate.php gives the
//                 date an invoice gets when Fakturér is pressed with an empty Fakt. dato.
//                 Since SST-802 every webshop order got its order date, which MEDshop did not want.
//                 The order date is now only used when the regnskab has ticked it under Ordrerelaterede valg.
//                 The database and the setting are stubbed, each test in its own process.
#[RunTestsInSeparateProcesses]
final class DefaultInvoiceDateTest extends TestCase
{
    private const FILE = __DIR__ . '/../../../debitor/orderIncludes/defaultInvoiceDate.php';

    /**
     * Loads default_invoice_date() with a stubbed setting and shop_ordrer lookup.
     *
     * @param string|null $setting   settings ordre/webshopInvoiceOrderDate, null when the row is missing.
     * @param array|null  $shopOrder The row the shop_ordrer query returns, null for an order made in Saldi.
     */
    private static function boot(?string $setting, ?array $shopOrder): void
    {
        $GLOBALS['__setting'] = $setting;
        $GLOBALS['__shopOrder'] = $shopOrder;
        $GLOBALS['__queries'] = [];
        eval('
            function get_settings_value($name, $grp, $default) {
                if ($name !== "webshopInvoiceOrderDate" || $grp !== "ordre") return $default;
                return $GLOBALS["__setting"] ?? $default;
            }
            function db_select($q, $where = "") { $GLOBALS["__queries"][] = $q; return $q; }
            function db_fetch_array($q) { return $GLOBALS["__shopOrder"] ?? false; }
        ');
        require self::FILE;
    }

    public function testWebshopOrderGetsTodayWhenTheSettingIsMissing(): void
    {
        self::boot(null, ['ordredate' => '2026-10-25']);
        self::assertSame(date('Y-m-d'), default_invoice_date(168427));
        self::assertSame([], $GLOBALS['__queries'], 'no shop_ordrer lookup is needed when the setting is off');
    }

    public function testWebshopOrderGetsTodayWhenTheSettingIsOff(): void
    {
        self::boot('off', ['ordredate' => '2026-10-25']);
        self::assertSame(date('Y-m-d'), default_invoice_date(168427));
        self::assertSame(date('Y-m-d'), default_invoice_date(168427, '2026-10-24'));
    }

    public function testWebshopOrderGetsItsOrderDateWhenTheSettingIsOn(): void
    {
        self::boot('on', ['ordredate' => '2026-10-25']);
        self::assertSame('2026-10-25', default_invoice_date(168427));
    }

    public function testOrderDateSubmittedWithFakturerWinsWhenTheSettingIsOn(): void
    {
        self::boot('on', ['ordredate' => '2026-10-25']);
        self::assertSame('2026-10-24', default_invoice_date(168427, '2026-10-24'));
    }

    public function testOrderMadeInSaldiGetsTodayWhenTheSettingIsOn(): void
    {
        self::boot('on', null);
        self::assertSame(date('Y-m-d'), default_invoice_date(168428));
    }

    public function testNewOrderWithoutIdGetsToday(): void
    {
        self::boot('on', ['ordredate' => '2026-10-25']);
        self::assertSame(date('Y-m-d'), default_invoice_date(0));
    }
}
