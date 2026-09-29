<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20260919 MJ Pins the escaping of the request-derived values grid.php writes into HTML
//                  attributes. The search term is the worst of them: it arrives as
//                  $_GET['search'][$id][field] AND is persisted to datatables.search_setup, so an
//                  unescaped value is both reflected and stored.
//
//                  render_table_headers() is extracted from the source rather than reached by
//                  including grid.php, which would run the whole grid at include time.
final class GridSearchTermEscapingTest extends TestCase
{
    /**
     * Defines the escaping helpers render_table_headers() calls, by lifting them out of grid.php
     * rather than restating them here - a reimplementation could pass while the real one is wrong.
     *
     * @param string $src The full source of includes/grid.php.
     */
    private static function defineHelpers(string $src): void
    {
        foreach (['grid_html', 'grid_align'] as $name) {
            if (function_exists($name)) {
                continue;
            }
            $start = strpos($src, "function $name(");
            self::assertNotFalse($start, "$name() not found in grid.php");
            $open = strpos($src, '{', $start);
            $depth = 0;
            for ($i = $open; $i < strlen($src); $i++) {
                if ($src[$i] === '{') $depth++;
                if ($src[$i] === '}') {
                    $depth--;
                    if ($depth === 0) break;
                }
            }
            self::assertSame(0, $depth, "$name() has unbalanced braces");
            eval(substr($src, $start, $i - $start + 1));
            self::assertTrue(function_exists($name), "$name() did not define");
        }
    }

    /** @return callable render_table_headers() lifted out of includes/grid.php */
    private static function headerRenderer(): callable
    {
        static $fn = null;
        if ($fn !== null) {
            return $fn;
        }
        $src = file_get_contents(__DIR__ . '/../../../includes/grid.php');
        self::defineHelpers($src);
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

    /**
     * Renders the header and search row for a single searchable column with $searchTerm already
     * in the box, which is the state a user is returned to after searching.
     *
     * @param string $searchTerm The term as it would arrive from the request or the saved setup.
     * @param array<string, string> $columnOverrides Column-configuration values to override, for the
     *                              stored-setup sinks: a user's own headerName/description/align are
     *                              persisted in datatables.column_setup and echoed here every time.
     * @return string The emitted HTML.
     */
    private function headerHtml(string $searchTerm, array $columnOverrides = []): string
    {
        $columns = ['hvem' => $columnOverrides + [
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

    /**
     * Each payload must not survive in the one shape that would mean it worked.
     *
     * Asserting on absence rather than on the exact escaped output keeps the test about the
     * vulnerability instead of about htmlspecialchars()' choice of entities.
     *
     * @param string $payload The hostile search term.
     * @param string $mustNotContain The markup that would prove the attribute was broken out of.
     */
    #[DataProvider('payloads')]
    public function testPayloadsAreNeutralised(string $payload, string $mustNotContain): void
    {
        self::assertStringNotContainsString($mustNotContain, $this->headerHtml($payload));
    }

    /**
     * Payload paired with the markup that would indicate it escaped the attribute. The first is
     * the one that was live before this fix; the others cover the neighbouring shapes an attacker
     * would reach for - a tag, the double-quote variant, and a bare ampersand.
     *
     * @return array<string, array{string, string}>
     */
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

    /**
     * Ordinary terms, with what must still appear inside value='...'. Escaping that also mangled
     * normal input would break the search box, so these guard against over-correcting - Danish
     * letters in particular must pass through untouched.
     *
     * @return array<string, array{string, string}>
     */
    public static function benignTerms(): array
    {
        return [
            'plain'           => ['Anna', 'Anna'],
            'danish letters'  => ['Søren Ø', 'Søren Ø'],
            'empty'           => ['', ''],
            'spaces'          => ['a b', 'a b'],
        ];
    }

    // ---------------------------------------------------------------------------------------
    // The stored column configuration is a second, worse sink for the same problem. A user's own
    // header, description and alignment are saved per user in datatables.column_setup by
    // save_column_setup() and echoed again on every later render, so an unescaped value is stored
    // rather than reflected - and this codebase has no CSRF tokens, so the save can be driven on
    // another logged-in user's behalf. Reported by @ZaynSaul on the PR.

    /**
     * A header a user "renamed" to markup must not become markup on anyone's next page view.
     *
     * @param string $payload The hostile stored value.
     * @param string $mustNotContain The markup that would prove it executed.
     */
    #[DataProvider('storedHeaderPayloads')]
    public function testStoredHeaderNameIsNeutralised(string $payload, string $mustNotContain): void
    {
        self::assertStringNotContainsString($mustNotContain, $this->headerHtml('', ['headerName' => $payload]));
    }

    /**
     * The same payloads through the description, which is echoed into its own span.
     *
     * @param string $payload The hostile stored value.
     * @param string $mustNotContain The markup that would prove it executed.
     */
    #[DataProvider('storedHeaderPayloads')]
    public function testStoredDescriptionIsNeutralised(string $payload, string $mustNotContain): void
    {
        self::assertStringNotContainsString($mustNotContain, $this->headerHtml('', ['description' => $payload]));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function storedHeaderPayloads(): array
    {
        return [
            'script tag'     => ['<script>alert(document.cookie)</script>', '<script>'],
            'img onerror'    => ['<img src=x onerror=alert(1)>', '<img '],
            'attribute break'=> ["' onmouseover='alert(1)", "' onmouseover='"],
            'svg onload'     => ['<svg onload=alert(1)>', '<svg '],
        ];
    }

    /**
     * align lands inside style='text-align: ...'. Escaping alone would still let a stored value add
     * further CSS declarations, so it is whitelisted: anything unrecognised must render as left.
     *
     * @param string $stored The stored alignment.
     */
    #[DataProvider('hostileAlignments')]
    public function testStoredAlignIsWhitelisted(string $stored): void
    {
        $html = $this->headerHtml('', ['align' => $stored]);

        // Every text-align this render emits must be one of the three the editor offers.
        preg_match_all('/text-align:\s*([^;\']*)/', $html, $m);
        self::assertNotEmpty($m[1], 'expected at least one text-align declaration');
        foreach ($m[1] as $emitted) {
            self::assertContains(trim($emitted), ['left', 'center', 'right'], "unexpected alignment emitted: $emitted");
        }
    }

    /** @return array<string, array{string}> */
    public static function hostileAlignments(): array
    {
        return [
            'extra declaration' => ['left; background: url(javascript:alert(1))'],
            'attribute break'   => ["left' onmouseover='alert(1)"],
            'expression'        => ['left; width: expression(alert(1))'],
            'unknown value'     => ['justify'],
            'empty'             => [''],
        ];
    }

    /** The three alignments the editor offers must still reach the style attribute unchanged. */
    #[DataProvider('validAlignments')]
    public function testValidAlignmentsSurvive(string $align): void
    {
        self::assertStringContainsString("text-align: $align", $this->headerHtml('', ['align' => $align]));
    }

    /** @return array<string, array{string}> */
    public static function validAlignments(): array
    {
        return ['left' => ['left'], 'center' => ['center'], 'right' => ['right']];
    }

    /** A renamed header with Danish letters must still display as typed. */
    public function testBenignStoredHeaderSurvives(): void
    {
        $html = $this->headerHtml('', ['headerName' => 'Udført af', 'description' => 'Sælgerens initialer']);

        self::assertStringContainsString('Udført af', $html);
        self::assertStringContainsString('Sælgerens initialer', $html);
    }
}
