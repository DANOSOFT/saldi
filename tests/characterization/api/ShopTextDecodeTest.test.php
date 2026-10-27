<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261027 CL/MMR SST-904 Created. A shop name like O&#039;Brien broke the customer/order insert: api/rest_api.php escaped the values and then decoded the finished SQL with chk4utf8(), which turned the entity into an unescaped quote.
//                 restApiShopText() is lifted from api/rest_api.php (including the file connects to a database) and run here; the order of decoding and SQL building is asserted on the source.
final class ShopTextDecodeTest extends TestCase
{
    private const API = __DIR__ . '/../../../api/rest_api.php';

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('restApiShopText')) {
            eval(self::functionSource('restApiShopText'));
        }
    }

    /** @return array<string, array{mixed, mixed}> */
    public static function values(): array
    {
        return [
            'numeric apostrophe'        => ['O&#039;Brien ApS', "O'Brien ApS"],
            'short numeric apostrophe'  => ['O&#39;Hara', "O'Hara"],
            'named entities'            => ['Kaffe &amp; te &quot;økologisk&quot;', 'Kaffe & te "økologisk"'],
            'UTF-8 Danish kept'         => ['Ærøvej 1', 'Ærøvej 1'],
            'ISO-8859-1 converted'      => ["\xC6r\xF8 Kaffe", 'Ærø Kaffe'],
            'UTF-8 outside the old list kept' => ['Pris 5 €', 'Pris 5 €'],
            'plain ASCII unchanged'     => ['Hansen ApS', 'Hansen ApS'],
            'spaces are not trimmed'    => [' Vej 2 ', ' Vej 2 '],
            'empty'                     => ['', ''],
            'null stays null'           => [null, null],
            'number becomes a string'   => [9870, '9870'],
        ];
    }

    #[DataProvider('values')]
    public function testAShopValueIsDecodedAsItIsStored(mixed $in, mixed $expected): void
    {
        self::assertSame($expected, restApiShopText($in));
    }

    /** The decoded value is escaped like any other quote, so the SQL stays intact. */
    public function testADecodedQuoteIsEscapedInTheSql(): void
    {
        $escaped = str_replace("'", "''", restApiShopText('O&#039;Brien'));
        self::assertSame("insert into adresser(firmanavn) values ('O''Brien')", "insert into adresser(firmanavn) values ('$escaped')");
    }

    /**
     * insert_shop_order() decodes its text values before the first SQL is built and never decodes
     * finished SQL; insert_shop_orderline() escapes the decoded item number in its lookup.
     */
    public function testNoFinishedSqlIsDecoded(): void
    {
        $code = self::code();
        $order = self::body($code, 'insert_shop_order', 'insert_shop_orderline');
        $line = self::body($code, 'insert_shop_orderline', 'fakturer_ordre');

        foreach (['insert_shop_order' => $order, 'insert_shop_orderline' => $line] as $fn => $body) {
            self::assertDoesNotMatchRegularExpression('/\$qtxt\s*=\s*chk4utf8\s*\(\s*\$qtxt\s*\)/', $body,
                "$fn decodes finished SQL again, so a decoded quote breaks it");
        }
        $decode = strpos($order, '$firmanavn = restApiShopText($firmanavn);');
        $firstSql = strpos($order, '$qtxt');
        self::assertNotFalse($decode, 'insert_shop_order() no longer decodes firmanavn');
        self::assertLessThan($firstSql, $decode, 'insert_shop_order() builds SQL before the values are decoded');
        foreach (['addr1', 'kontakt', 'lev_firmanavn', 'notes', 'tlf', 'email'] as $field) {
            self::assertStringContainsString("\$$field = restApiShopText(\$$field);", $order, "$field is not decoded");
        }
        self::assertStringContainsString('db_escape_string(restApiShopText($shop_varenr))', $line,
            'the item number lookup no longer escapes the decoded value');
    }

    /** The source without comments, so commented-out code does not count. */
    private static function code(): string
    {
        $out = '';
        foreach (token_get_all((string)file_get_contents(self::API)) as $t) {
            if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($t) ? $t[1] : $t;
        }
        return $out;
    }

    private static function body(string $code, string $fn, string $next): string
    {
        $a = strpos($code, "function $fn(");
        $b = strpos($code, "function $next(", (int)$a + 1);
        self::assertNotFalse($a, "$fn() is gone");
        self::assertNotFalse($b, "$next() is gone");
        return substr($code, $a, $b - $a);
    }

    private static function functionSource(string $fn): string
    {
        $code = self::code();
        $a = strpos($code, "function $fn(");
        self::assertNotFalse($a, "$fn() is gone from api/rest_api.php");
        $depth = 0;
        for ($i = strpos($code, '{', $a); $i < strlen($code); $i++) {
            if ($code[$i] === '{') $depth++;
            if ($code[$i] === '}' && --$depth === 0) {
                return substr($code, $a, $i - $a + 1);
            }
        }
        self::fail("$fn() has no end");
    }
}
