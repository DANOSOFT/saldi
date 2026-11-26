<?php
// 20261125 CDX/NTR Exercise imported catalogue text through the real column callbacks and grid highlighting (PR #1220).
// 20261125 CDX/NTR Load the common grid text preparation used by the extracted row renderer.
// 20261126 CDX/NTR Cover LATIN9 search text in the actual automatic-search script.
// 20261126 CDX/NTR Decode both browser search fields as Windows-1252 on legacy pages while preserving database-encoded fallbacks.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/std_func.php';
require_once dirname(__DIR__, 3) . '/includes/grid/text.php';
require_once dirname(__DIR__, 3) . '/includes/orderFuncIncludes/externalPriceList.php';
require_once __DIR__ . '/support/external_price_list.php';

final class ProductLookupRenderingTest extends TestCase
{
    public function testImportedTextIsEscapedBeforeTrustedHighlighting(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/debitor/productLookup.php');
        $start = strpos($source, '// Column configuration');
        $code = substr($source, $start, strpos($source, '// Salgspris column', $start) - $start);
        $href = 'ordre.php';
        $id = 11;
        $fokus = 'beskrivelse';
        $bordnr = '';
        $afd_lager = 2;
        eval($code);
        $grid = file_get_contents(dirname(__DIR__, 3) . '/includes/grid.php');
        preg_match('/function render_table_row\([^\n]*\) \{.*?^\}/ms', substr($grid, strrpos($grid, 'function render_table_row(')), $match);
        if (!function_exists('ProductLookupRenderingFixture\render_table_row')) {
            eval('namespace ProductLookupRenderingFixture;' . $match[0]);
        }
        $payload = '<img src=x onerror=alert(1)> Pump & fittings';
        $row = ['id' => 7, 'varenr' => $payload, 'beskrivelse' => $payload, 'enhed' => $payload, 'varenr_alias' => '', 'stregkode' => '', 'vv_stregkode' => '', 'vv_variant_text' => '<b>variant</b>'];
        foreach ($columns as $column) {
            $column += ['type' => 'text', 'align' => 'left'];
            ob_start();
            try {
                \ProductLookupRenderingFixture\render_table_row([$column], $row, [$column['field'] => 'Pump']);
                $html = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            self::assertStringNotContainsString('<img', $html, $column['field']);
            self::assertStringContainsString('&lt;img', $html, $column['field']);
            self::assertStringContainsString('&amp; fittings', $html, $column['field']);
            self::assertStringContainsString('<span style="background-color:#FF0">Pump</span>', $html, $column['field']);
        }
    }

    public function testAutomaticDescriptionSearchKeepsWarehouseAndFindsTheExternalItem(): void
    {
        $fields = self::submitGrid('beskrivelse', 'pump');
        self::assertSame('2', $fields['lager']);
        self::assertSame('pump', $fields['search[productLookup11][beskrivelse]']);
        self::assertSame('', $fields['search[productLookup11][varenr]']);
        self::assertSame(['A100'], array_column(self::externalMatches($fields, 'beskrivelse', ''), 'varenr'));
        self::assertSame(['A100'], array_column(self::externalMatches([], 'beskrivelse', 'pump'), 'varenr'));
    }

    public function testAutomaticItemNumberSearchStillUsesTheNumberField(): void
    {
        $fields = self::submitGrid('varenr', 'A100');
        self::assertSame('2', $fields['lager']);
        self::assertSame('A100', $fields['search[productLookup11][varenr]']);
        self::assertSame('', $fields['search[productLookup11][beskrivelse]']);
        self::assertSame(['A100'], array_column(self::externalMatches($fields, 'varenr', ''), 'varenr'));
    }

    public function testAutomaticSearchPreservesQuotesNewlinesAndScriptEndAsText(): void
    {
        $find = "pump '</script>\n& fittings";
        self::assertSame($find, self::submitGrid('beskrivelse', $find)['search[productLookup11][beskrivelse]']);
    }

    public function testAutomaticSearchNormalizesLatin9BeforeJsonEncoding(): void
    {
        $previous = $GLOBALS['db_encode'] ?? null;
        $GLOBALS['db_encode'] = 'LATIN9';
        try {
            $find = mb_convert_encoding('Pære €', 'ISO-8859-15', 'UTF-8');
            self::assertSame('Pære €', self::submitGrid('beskrivelse', $find)['search[productLookup11][beskrivelse]']);
        } finally {
            $GLOBALS['db_encode'] = $previous;
        }
    }

    public function testLegacyBrowserSearchAndDatabaseFallbackUseTheirOwnEncodings(): void
    {
        $csv = "varenr;beskrivelse;kostpris;salgspris\nP€ŠŒŽŸ;Pære €ŠŒŽŸ;1;2\n";
        $savedEncoding = $GLOBALS['db_encode'] ?? null;
        try {
            foreach (['varenr' => 'P€ŠŒŽŸ', 'beskrivelse' => 'Pære €ŠŒŽŸ'] as $field => $text) {
                $fields = ["search[productLookup11][$field]" => mb_convert_encoding($text, 'Windows-1252', 'UTF-8')];
                $rows = self::externalMatches($fields, $field, '', $csv, 'LATIN9');
                self::assertSame(['P€ŠŒŽŸ'], array_column($rows, 'varenr'), $field);
                $rows = self::externalMatches([], $field, mb_convert_encoding($text, 'ISO-8859-15', 'UTF-8'), $csv, 'LATIN9');
                self::assertSame(['P€ŠŒŽŸ'], array_column($rows, 'varenr'), $field . ' fallback');
            }
        } finally {
            $GLOBALS['db_encode'] = $savedEncoding;
        }
    }

    public function testOrderRedirectsPreserveOriginalLegacySearchText(): void
    {
        $order = file_get_contents(dirname(__DIR__, 3) . '/debitor/ordre.php');
        preg_match_all('/\$find_param = [^\n]+;\n\s*\$find_encoding = [^\n]+;.*?(?=\s*header\("Location: \$url"\);)/s', $order, $blocks);
        self::assertCount(3, $blocks[0]);
        $lookup = file_get_contents(dirname(__DIR__, 3) . '/debitor/productLookup.php');
        $start = strpos($lookup, '$find = if_isset');
        $decode = substr($lookup, $start, strpos($lookup, '$bordnr =', $start) - $start);
        $decode = str_replace('__DIR__', var_export(dirname(__DIR__, 3) . '/debitor', true), $decode);
        $savedGet = $_GET;
        $savedPost = $_POST;
        $savedEncoding = $GLOBALS['db_encode'] ?? null;
        $text = "Pære € O'Hara";
        $browserText = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        $id = 11;
        $art = 'DO';
        $sort = $ref = $vis_kost = $bordnr = '';
        $afd_lager = 2;
        $varenr0_original = $varenr[0] = $beskrivelse[0] = str_replace("'", "''", $text);
        try {
            foreach ($blocks[0] as $block) {
                $_POST = ['varenr0' => $browserText, 'beskrivelse0' => $browserText];
                $GLOBALS['db_encode'] = 'LATIN9';
                eval($block);
                parse_str(parse_url($url, PHP_URL_QUERY), $_GET);
                self::assertSame('browser', $_GET['find_encoding']);
                self::assertSame($browserText, $_GET['find']);
                eval($decode);
                self::assertSame($text, self::submitGrid($_GET['fokus'], $find)['search[productLookup11][' . $_GET['fokus'] . ']']);
                $csv = "varenr;beskrivelse;kostpris;salgspris\n$text;$text;1;2\n";
                $rows = self::externalMatches([], $_GET['fokus'], $find, $csv, 'LATIN9');
                self::assertSame([$text], array_column($rows, 'varenr'));
            }
        } finally {
            $_GET = $savedGet;
            $_POST = $savedPost;
            $GLOBALS['db_encode'] = $savedEncoding;
        }
    }

    /** @return array<string, string> Fields submitted by the actual page JavaScript. */
    private static function submitGrid(string $fokus, string $find): array
    {
        $id = 11;
        $art = 'DO';
        $bordnr = '';
        $afd_lager = 2;
        $href = 'ordre.php';
        $source = file_get_contents(dirname(__DIR__, 3) . '/debitor/productLookup.php');
        ob_start();
        try {
            eval(substr($source, strpos($source, '// Add JavaScript for ESC')));
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $html, $scripts);
        $process = proc_open(['node', __DIR__ . '/support/product_lookup_grid.cjs'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fwrite($pipes[0], json_encode(['script' => implode("\n", $scripts[1])]));
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error);
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<int, array{varenr: string}> External matches from the page's real request-to-search mapping. */
    private static function externalMatches(array $fields, string $fokus, string $find, ?string $csv = null, string $encoding = 'UTF8'): array
    {
        \ExternalPriceListFixture\boot();
        \ExternalPriceListFixture\State::$csv = $csv ?? "varenr;beskrivelse;kostpris;salgspris\nA100;Hydraulic pump;1;2\n";
        $GLOBALS['db_encode'] = $encoding;
        $source = file_get_contents(dirname(__DIR__, 3) . '/debitor/productLookup.php');
        $start = strpos($source, '    $gridSearch =');
        $code = substr($source, $start, strpos($source, '    if ($priceList &&', $start) - $start);
        $id = 11;
        $savedGet = $_GET;
        parse_str(http_build_query($fields), $_GET);
        try {
            eval($code);
        } finally {
            $_GET = $savedGet;
        }
        return \ExternalPriceListFixture\externalPriceListSearch(['url' => 'https://example.invalid/prices.csv', 'delimiter' => ';', 'encoding' => 'utf-8'], $searchItemNo, $searchText);
    }
}
