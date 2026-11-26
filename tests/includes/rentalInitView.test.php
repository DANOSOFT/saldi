<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * MB-111: rental/index.js picks its startup view from the query string. A query string that
 * matches none of the known views (such as ?inframe=1 alone, from the main menu) must still
 * fall back to createCalendar(), or Daglig oversigt opens blank.
 *
 * Saldi's CI PHP container has no node, so this checks the dispatch block's structure in plain
 * PHP; it does not execute the JavaScript.
 */
final class rentalInitView extends TestCase
{
    /** @return array{0: string, 1: string} the outer if's body and the code after its closing brace */
    private function initBlock(): array
    {
        $src = file_get_contents(__DIR__ . '/../../rental/index.js');
        $this->assertIsString($src);
        $at = strpos($src, '// Init the view');
        $this->assertNotFalse($at, 'init view marker missing in rental/index.js');
        $tail = preg_replace(['#/\*.*?\*/#s', '/"[^"\r\n]*"/'], ['', '""'], substr($src, $at));
        $this->assertIsString($tail);
        $head = 'if (queryString !== "") {';
        $open = strpos($tail, $head);
        $this->assertNotFalse($open, 'outer queryString check missing');
        $start = $open + strlen($head);
        $depth = 1;
        for ($i = $start, $n = strlen($tail); $i < $n && $depth > 0; $i++) {
            if ($tail[$i] === '{') {
                $depth++;
            } elseif ($tail[$i] === '}') {
                $depth--;
            }
        }
        $this->assertSame(0, $depth, 'unbalanced braces in the init view block');
        return [substr($tail, $start, $i - 1 - $start), substr($tail, $i)];
    }

    public function testUnmatchedQueryStringFallsBackToCalendar(): void
    {
        [$body] = $this->initBlock();
        $this->assertMatchesRegularExpression(
            '/singleProductOverview\(value\)\s*\}\s*else\s*\{\s*createCalendar\(\)\s*\}\s*$/',
            $body,
            'the query-string dispatch needs a final else that opens the calendar'
        );
    }

    public function testEmptyQueryStringStillOpensCalendar(): void
    {
        [, $rest] = $this->initBlock();
        $this->assertMatchesRegularExpression('/^\s*else\s*\{\s*createCalendar\(\)\s*\}/', $rest);
    }
}
