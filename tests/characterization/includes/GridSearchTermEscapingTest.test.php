<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20260919 CDX/MJ Pins the escaping of the request-derived values grid.php writes into HTML
//                  attributes. The search term is the worst of them: it arrives as
//                  $_GET['search'][$id][field] AND is persisted to datatables.search_setup, so an
//                  unescaped value is both reflected and stored.
//
//                  render_table_headers() is extracted from the source rather than reached by
//                  including grid.php, which would run the whole grid at include time.
final class GridSearchTermEscapingTest extends TestCase
{
    /** @return callable render_table_headers() lifted out of includes/grid.php */
    private static function headerRenderer(): callable
    {
        static $fn = null;
        if ($fn !== null) {
            return $fn;
        }
        $src = file_get_contents(__DIR__ . '/../../../includes/grid.php');
        $start = strpos($src, 'function render_table_headers(');
        self::assertNotFalse($start, 'render_table_headers() not found');

        // Cut the body off before the toolbar block, which calls the real findtekst() and would
        // reach the database. Everything under test - the header row and the search row - is above
        // it. The braces left open by truncating are counted and closed, so a restructured source
        // fails to parse and the test fails loudly rather than testing something else.
        $stop = strpos($src, '$txt1 = findtekst(', $start);
        self::assertNotFalse($stop, 'toolbar block not found; extraction anchor moved');
        $body = substr($src, $start, $stop - $start);
        $open = substr_count($body, '{') - substr_count($body, '}');
        self::assertGreaterThan(0, $open, 'expected the truncated body to leave braces open');
        $body .= str_repeat('}', $open);

        $fn = eval('return ' . preg_replace('/^function render_table_headers\s*\(/', 'function (', $body, 1) . ';');
        self::assertIsCallable($fn);
        return $fn;
    }

    private function headerHtml(string $searchTerm): string
    {
        $columns = ['hvem' => [
            'field' => 'hvem', 'searchable' => true, 'align' => 'left', 'width' => '1',
            'headerName' => 'Udført af', 'type' => 'text', 'sortable' => false,
            'description' => '', 'renderSearch' => null,
        ]];
        ob_start();
        try {
            (self::headerRenderer())($columns, ['hvem' => $searchTerm], 100, 'ordreliste');
        } finally {
            $html = ob_get_clean();
        }
        return (string)$html;
    }

    /**
     * The payload that was live before this fix: it closed value='' and added its own attributes.
     */
    public function testQuoteBreakingPayloadCannotEscapeTheAttribute(): void
    {
        $html = $this->headerHtml("' onfocus=alert(document.domain) autofocus x='");

        self::assertStringContainsString('&#039;', $html, 'single quotes should be entity-encoded');
        self::assertStringNotContainsString("value='' onfocus=", $html, 'the value attribute must not be closed early');
        // The text may still appear, but only as inert content inside the value attribute.
        self::assertSame(
            1,
            preg_match_all("/value='[^']*'/", $html),
            'exactly one value attribute should be emitted'
        );
    }

    #[DataProvider('payloads')]
    public function testPayloadsAreNeutralised(string $payload, string $mustNotContain): void
    {
        self::assertStringNotContainsString($mustNotContain, $this->headerHtml($payload));
    }

    public static function payloads(): array
    {
        return [
            'single quote break' => ["' autofocus onfocus=alert(1) x='", "value='' autofocus"],
            'tag injection'      => ['<script>alert(1)</script>', '<script>'],
            'double quote'       => ['" onmouseover="alert(1)', '" onmouseover="'],
            'ampersand'          => ['a&b', 'value=\'a&b\''],
        ];
    }

    /** Ordinary terms must still round-trip so the search box keeps showing what was typed. */
    #[DataProvider('benignTerms')]
    public function testBenignTermsSurvive(string $term, string $expectedInValue): void
    {
        self::assertStringContainsString("value='$expectedInValue'", $this->headerHtml($term));
    }

    public static function benignTerms(): array
    {
        return [
            'plain'           => ['Anna', 'Anna'],
            'danish letters'  => ['Søren Ø', 'Søren Ø'],
            'empty'           => ['', ''],
            'spaces'          => ['a b', 'a b'],
        ];
    }
}
