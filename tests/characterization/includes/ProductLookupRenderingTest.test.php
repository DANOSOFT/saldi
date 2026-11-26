<?php
// 20261125 CDX/NTR Exercise imported catalogue text through the real column callbacks and grid highlighting (PR #1220).
// 20261125 CDX/NTR Load the common grid text preparation used by the extracted row renderer.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/std_func.php';
require_once dirname(__DIR__, 3) . '/includes/grid/text.php';
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
    private static function externalMatches(array $fields, string $fokus, string $find): array
    {
        \ExternalPriceListFixture\boot();
        \ExternalPriceListFixture\State::$csv = "varenr;beskrivelse;kostpris;salgspris\nA100;Hydraulic pump;1;2\n";
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
