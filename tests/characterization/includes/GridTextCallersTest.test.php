<?php
// 20261125 CDX/NTR Cover order-list and journal callbacks with prepared display text and untouched row data.
// 20261126 CDX/NTR Cover numeric columns converted to dropdowns before highlighted values reach their renderer.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/includes/grid/text.php';
require_once __DIR__ . '/support/grid_text_callers.php';

final class GridTextCallersTest extends TestCase
{
    /** @return array<string, array{string, string}> Existing text callbacks that must consume prepared HTML once. */
    public static function textColumns(): array
    {
        return [
            'customer country' => ['debitor/ordreliste.php', '"land" => array('],
            'customer extra text' => ['debitor/ordreliste.php', '// Default text fields'],
            'supplier extra text' => ['kreditor/ordreliste.php', '// Default text fields'],
            'supplier account id' => ['kreditor/ordreliste.php', "elseif (\$field_name == 'konto_id')"],
        ];
    }

    #[DataProvider('textColumns')]
    public function testTextIsEscapedExactlyOnce(string $path, string $anchor): void
    {
        $raw = 'A &amp; B <img src=x onerror=alert(1)>';
        $html = self::productionCallback($path, $anchor)(grid_text_html($raw, 'B'), [], ['align' => 'left']);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('<span style="background-color:#FF0">B</span>', $html);
        self::assertSame($raw, html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function testStatusComparisonsUseRawDatabaseStrings(): void
    {
        foreach (['debitor/ordreliste.php', 'kreditor/ordreliste.php'] as $path) {
            $callback = self::productionCallback($path, '// Boolean/status fields');
            foreach (['0' => '✗', '1' => '✓'] as $status => $expected) {
                $status = (string) $status;
                $html = $callback(grid_text_html($status, $status), ['betalt' => $status], ['field' => 'betalt', 'align' => 'left']);
                self::assertStringContainsString($expected, $html, $path);
            }
        }
    }

    public function testSupplierNumericDropdownRemainsVisibleWhenHighlighted(): void
    {
        $renderer = self::productionCallback('kreditor/ordreliste.php', '// Numeric fields');
        $prepared = grid_text_html('1', '1');
        $html = $renderer($prepared, ['status' => '1'], ['type' => 'dropdown', 'field' => 'status', 'align' => 'right', 'decimalPrecision' => 0]);
        self::assertStringContainsString($prepared, $html);
    }

    public function testJournalUsesRawAccountsForLookupAndPreparedTextForDisplay(): void
    {
        foreach ([['debet', 'd_type', '// Debet column'], ['kredit', 'k_type', '// Kredit column']] as [$field, $typeField, $anchor]) {
            \GridTextCallersFixture\State::$queries = [];
            \GridTextCallersFixture\State::$linkedAccount = null;
            $raw = "O'Hara & Co";
            $callback = self::productionCallback('finans/kassekladde.php', $anchor);
            $previousYear = $GLOBALS['regnaar'] ?? null;
            $GLOBALS['regnaar'] = 2026;
            try {
                $html = $callback(grid_text_html($raw, 'Hara'), [$field => $raw, $typeField => 'D'], []);
            } finally {
                $GLOBALS['regnaar'] = $previousYear;
            }
            self::assertSame($raw, \GridTextCallersFixture\State::$linkedAccount);
            self::assertCount(1, \GridTextCallersFixture\State::$queries);
            self::assertStringContainsString("kontonr='O''Hara & Co'", \GridTextCallersFixture\State::$queries[0]);
            self::assertStringContainsString('<span style="background-color:#FF0">Hara</span>', $html);
            self::assertSame($raw, html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            self::assertStringContainsString('title=\'Company&#039;s &amp; &lt;name&gt;\'', $html);
        }
    }

    /** @return Closure Actual render callback isolated from the page's request handling. */
    private static function productionCallback(string $path, string $anchor): Closure
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/' . $path);
        $start = strpos($source, $anchor);
        self::assertNotFalse($start, $anchor);
        preg_match('/["\x27]render["\x27]\s*(?:=>|\]\s*=)\s*(function\b)/', $source, $match, PREG_OFFSET_CAPTURE, $start);
        self::assertNotEmpty($match);
        $tokens = token_get_all('<?php ' . substr($source, $match[1][1]));
        $body = '';
        $depth = 0;
        $started = false;
        foreach (array_slice($tokens, 1) as $token) {
            $body .= is_array($token) ? $token[1] : $token;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
                $started = true;
            }
            if ($token === '}' && --$depth === 0 && $started) {
                break;
            }
        }
        $regnaar = 2026;
        $kladde_id = 12;
        return eval('namespace GridTextCallersFixture; return ' . $body . ';');
    }
}
