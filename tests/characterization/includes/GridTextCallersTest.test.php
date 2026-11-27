<?php
// 20261125 CDX/NTR Cover order-list and journal callbacks with prepared display text and untouched row data.
// 20261126 CDX/NTR Cover numeric columns converted to dropdowns before highlighted values reach their renderer.
// 20261126 CDX/NTR Cover journal owners, statement links and description truncation with prepared legacy text.

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
            'journal owner' => ['finans/kladdeliste.php', '"field" => "oprettet_af"'],
        ];
    }

    #[DataProvider('textColumns')]
    public function testTextIsEscapedExactlyOnce(string $path, string $anchor): void
    {
        $raw = 'A &amp; B <img src=x onerror=alert(1)>';
        $html = self::productionCallback($path, $anchor)(grid_text_html($raw, 'B'), ['id' => 1], ['align' => 'left']);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('<span style="background-color:#FF0">B</span>', $html);
        self::assertSame($raw, html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function testLegacyJournalOwnerIsEscapedOnce(): void
    {
        $raw = mb_convert_encoding('Søren & "Co" \\ <b>', 'ISO-8859-15', 'UTF-8');
        $html = self::productionCallback('finans/kladdeliste.php', '"field" => "oprettet_af"')(
            grid_text_html($raw, '', 'LATIN9'),
            ['id' => 1],
            ['align' => 'left']
        );
        self::assertSame('Søren & "Co" \\ <b>', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function testStatementLinksUseRawValuesEvenWhenHighlighted(): void
    {
        foreach (['bilag' => 'kontospec.php?bilag=', 'kladde_id' => 'kassekladde.php?kladde_id=', 'kontonr' => 'kontospec.php?kontonr='] as $field => $prefix) {
            $raw = "A&B'1";
            $html = self::productionCallback('finans/kontospec.php', "'field' => '$field'")(
                grid_text_html($raw, '1'),
                [$field => $raw],
                []
            );
            self::assertStringContainsString($prefix . urlencode($raw), $html);
            self::assertStringContainsString('<span style="background-color:#FF0">1</span>', $html);
        }
    }

    public function testStatementTruncatesPlainTextBeforeEscapingAndKeepsSafeTooltip(): void
    {
        $oldEncoding = $GLOBALS['db_encode'] ?? null;
        $GLOBALS['db_encode'] = 'LATIN9';
        $rawUtf8 = str_repeat('ø', 99) . "&'suffix <img src=x>";
        $raw = mb_convert_encoding($rawUtf8, 'ISO-8859-15', 'UTF-8');
        $row = ['beskrivelse' => $raw];
        try {
            $getter = self::productionCallback('finans/kontospec.php', "'field' => 'beskrivelse'", 'valueGetter');
            $prepared = grid_text_html($getter($raw, $row, []), '', 'LATIN9');
            $html = self::productionCallback('finans/kontospec.php', "'field' => 'beskrivelse'")($prepared, $row, []);
        } finally {
            $GLOBALS['db_encode'] = $oldEncoding;
        }
        $dom = new DOMDocument();
        $dom->loadHTML('<html><head><meta charset="UTF-8"></head><body><table><tr>' . $html . '</tr></table></body></html>');
        $cell = $dom->getElementsByTagName('td')->item(0);
        self::assertSame(str_repeat('ø', 99) . '&...', $cell->textContent);
        self::assertSame($rawUtf8, $cell->getAttribute('title'));
        self::assertCount(0, $dom->getElementsByTagName('img'));
    }

    public function testStatementAllowsNullDescriptionsAndEscapesRawOrderTooltips(): void
    {
        $getter = self::productionCallback('finans/kontospec.php', "'field' => 'beskrivelse'", 'valueGetter');
        $renderer = self::productionCallback('finans/kontospec.php', "'field' => 'beskrivelse'");
        self::assertSame("<td title=''></td>", $renderer($getter(null, [], []), ['beskrivelse' => null], []));
        $html = self::productionCallback('finans/kontospec.php', "'field' => 'faktura'")('123', ['ordrenr' => "O' & <b>", 'ordre_id' => 0], []);
        self::assertStringContainsString("title='Ordrenr: O&#039; &amp; &lt;b&gt;'", $html);
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
    private static function productionCallback(string $path, string $anchor, string $kind = 'render'): Closure
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/' . $path);
        $start = strpos($source, $anchor);
        self::assertNotFalse($start, $anchor);
        preg_match('/["\x27]' . preg_quote($kind, '/') . '["\x27]\s*(?:=>|\]\s*=)\s*(function\b)/', $source, $match, PREG_OFFSET_CAPTURE, $start);
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
        $bilag = '1';
        return eval('namespace GridTextCallersFixture; return ' . $body . ';');
    }
}
