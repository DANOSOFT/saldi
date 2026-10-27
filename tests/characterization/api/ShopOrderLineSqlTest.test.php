<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261027 CL/MMR SST-904 Created. insert_shop_orderline() in api/rest_api.php decoded its finished SQL with chk4utf8(), so an apostrophe (O&#039;Brien) in a comment line or an item number broke the query.
//                 It now decodes each shop value with shop_input_text() (SST-884, which covers insert_shop_order(); see ShopOrderHtmlEntityTest), escapes the text identifiers and casts the integer ids.
//                 shop_input_text() is lifted from api/rest_api.php (including the file connects to a database); the function's SQL is asserted on the source.
final class ShopOrderLineSqlTest extends TestCase
{
    private const API = __DIR__ . '/../../../api/rest_api.php';

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('shop_input_text')) {
            eval(self::functionSource('shop_input_text'));
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
            'null becomes empty'        => [null, ''],
            'number becomes a string'   => [9870, '9870'],
        ];
    }

    #[DataProvider('values')]
    public function testAShopValueIsDecodedBeforeItIsEscaped(mixed $in, mixed $expected): void
    {
        self::assertSame($expected, shop_input_text($in));
    }

    /** The decoded value is escaped like any other quote, so the SQL stays intact. */
    public function testADecodedQuoteIsEscapedInTheSql(): void
    {
        $escaped = str_replace("'", "''", shop_input_text('O&#039;Brien'));
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
        self::assertStringContainsString('$shopVarenrSql = db_escape_string((string)shop_input_text($shop_varenr));', $line,
            'the item number lookups no longer escape the decoded value');
        self::assertStringContainsString('$shopVariantInt = (int)$shop_variant;', $line, 'shop_varer.shop_variant (integer) is no longer cast');
        self::assertStringContainsString('$shopVareIdInt = (int)$shop_vare_id;', $line, 'shop_varer.shop_id (integer) is no longer cast');

        // Which shop ids reach the integer shop_varer lookup: digits only, leading zeros included (Codex round 2)
        $guard = [];
        preg_match('/\$shopVareIdIsId = (.+?);/', $line, $guard);
        self::assertNotEmpty($guard, 'the shop item id guard is gone');
        $isId = eval('return static function ($shop_vare_id) { return ' . $guard[1] . '; };');
        foreach (['5312' => true, '05312' => true, ' 5312 ' => true, '0' => true, "x'1" => false, '5312abc' => false, '-5' => false, '' => false, '1.5' => false] as $in => $ok) {
            self::assertSame($ok, (bool)$isId((string)$in), "shop item id '$in'");
        }
        foreach (['shop_varenr', 'shop_vare_id', 'shop_variant', 'stregkode', 'saldi_variant'] as $raw) {
            self::assertStringNotContainsString("'\$$raw'", $line, "insert_shop_orderline() puts \$$raw into SQL unescaped");
        }
        self::assertStringContainsString('include(__DIR__ . "/../includes/ordrefunc.php");', $line,
            'insert_shop_orderline() includes ordrefunc.php relative to the working directory again');
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
