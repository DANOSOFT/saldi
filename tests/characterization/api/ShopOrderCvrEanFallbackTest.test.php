<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// 20261028 CL/MMR SST-905 Created. shop_order_cvr_ean_from_card() in api/rest_api.php uses the customer card's CVR/EAN when the shop sends none.
//                 A value of only non-breaking spaces (&nbsp; from the shop) did not count as blank, because trim() keeps U+00A0, so the card's CVR was skipped.
//                 The function is lifted from the source (including the file connects to a database) and run with a stubbed card row.
#[RunTestsInSeparateProcesses]
final class ShopOrderCvrEanFallbackTest extends TestCase
{
    private const API = __DIR__ . '/../../../api/rest_api.php';
    private const NBSP = "\u{00A0}";

    private static function boot(?array $card): void
    {
        $GLOBALS['__card'] = $card;
        eval('
            function db_select($q, $where = "") { return $q; }
            function db_fetch_array($q) { return $GLOBALS["__card"] ?? false; }
        ');
        $src = (string)file_get_contents(self::API);
        $a = strpos($src, 'function shop_order_cvr_ean_from_card(');
        self::assertNotFalse($a, 'shop_order_cvr_ean_from_card() is gone from api/rest_api.php');
        $b = strpos($src, "\n}\n", $a);
        eval(substr($src, $a, $b - $a + 2));
    }

    /** @return array<string, array{string, string, array{0: string, 1: string}}> */
    public static function shopValues(): array
    {
        $nbsp = self::NBSP;
        return [
            'shop CVR and EAN empty'           => ['', '', ['53383211', '5798000125223']],
            'shop CVR only a non-breaking space' => [$nbsp, '', ['53383211', '5798000125223']],
            'shop EAN only non-breaking spaces' => ['', $nbsp . ' ' . $nbsp, ['53383211', '5798000125223']],
            'shop CVR with a trailing nbsp'    => ['12345678' . $nbsp, '', ['12345678', '5798000125223']],
            'shop values win'                  => ['12345678', '5790000000005', ['12345678', '5790000000005']],
        ];
    }

    #[DataProvider('shopValues')]
    public function testTheCardFillsWhatTheShopLeftBlank(string $cvr, string $ean, array $expected): void
    {
        self::boot(['cvrnr' => '53383211' . self::NBSP, 'ean' => ' 5798000125223']);
        self::assertSame($expected, shop_order_cvr_ean_from_card(86029, $cvr, $ean));
    }

    public function testWithoutACustomerTheShopValuesAreOnlyTrimmed(): void
    {
        self::boot(null);
        self::assertSame(['', ''], shop_order_cvr_ean_from_card(0, self::NBSP, ' '));
    }
}
