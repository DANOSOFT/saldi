<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// 20261029 CL/MMR SST-911 Created. save_webshop_invoice_order_date() (systemdata/diverseIncludes/webshopInvoiceOrderDate.php)
//                 saves "Fakturér webshopordrer med ordredato" when Ordrerelaterede valg is posted.
//                 An unticked checkbox is not posted, so the hidden webshopInvoiceOrderDateShown field decides
//                 whether the form had the checkbox at all. update_settings_value() is stubbed.
#[RunTestsInSeparateProcesses]
final class WebshopInvoiceOrderDateSaveTest extends TestCase
{
    private const FILE = __DIR__ . '/../../../systemdata/diverseIncludes/webshopInvoiceOrderDate.php';

    private static function boot(): void
    {
        $GLOBALS['__saved'] = [];
        eval('
            function update_settings_value($name, $grp, $value, $description, $user = null, $posid = null, $global = false) {
                $GLOBALS["__saved"][] = [$name, $grp, $value];
            }
        ');
        require_once __DIR__ . '/../../../includes/std_func.php'; // ifset(); its update_settings_value() is guarded by function_exists
        require self::FILE;
    }

    public function testTickedCheckboxSavesOn(): void
    {
        self::boot();
        $post = ['webshopInvoiceOrderDateShown' => '1', 'webshopInvoiceOrderDate' => 'on', 'submit' => 'Gem/opdatér'];
        self::assertSame('on', save_webshop_invoice_order_date('POST', 'ordre_valg', $post));
        self::assertSame([['webshopInvoiceOrderDate', 'ordre', 'on']], $GLOBALS['__saved']);
    }

    public function testUntickedCheckboxSavesOff(): void
    {
        self::boot();
        $post = ['webshopInvoiceOrderDateShown' => '1', 'submit' => 'Gem/opdatér'];
        self::assertSame('off', save_webshop_invoice_order_date('POST', 'ordre_valg', $post));
        self::assertSame([['webshopInvoiceOrderDate', 'ordre', 'off']], $GLOBALS['__saved']);
    }

    public function testAnyOtherValueSavesOff(): void
    {
        self::boot();
        $post = ['webshopInvoiceOrderDateShown' => '1', 'webshopInvoiceOrderDate' => "on' OR '1'='1"];
        self::assertSame('off', save_webshop_invoice_order_date('POST', 'ordre_valg', $post));
        self::assertSame([['webshopInvoiceOrderDate', 'ordre', 'off']], $GLOBALS['__saved']);
    }

    public function testFormWithoutTheCheckboxSavesNothing(): void
    {
        self::boot();
        self::assertNull(save_webshop_invoice_order_date('POST', 'ordre_valg', ['submit' => 'Gem/opdatér']));
        self::assertSame([], $GLOBALS['__saved']);
    }

    public function testOtherSektionOrGetSavesNothing(): void
    {
        self::boot();
        $post = ['webshopInvoiceOrderDateShown' => '1', 'webshopInvoiceOrderDate' => 'on'];
        self::assertNull(save_webshop_invoice_order_date('POST', 'kontoindstillinger', $post));
        self::assertNull(save_webshop_invoice_order_date('GET', 'ordre_valg', $post));
        self::assertSame([], $GLOBALS['__saved']);
    }

    public function testDiverseCallsTheHandlerBeforeAnyOutput(): void
    {
        $src = (string)file_get_contents(__DIR__ . '/../../../systemdata/diverse.php');
        $call = strpos($src, 'save_webshop_invoice_order_date(');
        self::assertNotFalse($call, 'diverse.php no longer calls save_webshop_invoice_order_date()');
        $firstPrint = strpos($src, "\nif (\$menu == 'T') {");
        self::assertNotFalse($firstPrint, 'the first output block of diverse.php moved');
        self::assertLessThan($firstPrint, $call, 'the setting must be saved before diverse.php prints anything');
    }
}
