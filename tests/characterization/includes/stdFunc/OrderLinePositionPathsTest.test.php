<?php
// tests/characterization/includes/stdFunc/OrderLinePositionPathsTest.test.php
//
// Every page whose form saves the positions of an order's lines (it reads a
// posted Pos field and writes ordrelinjer.posnr) uses the shared positions
// helper, includes/stdFunc/orderLinePositions.php, or is listed below with the
// reason it needs none.
//
// Such a form writes the positions it showed, so one drawn before a colleague's
// save gave its new line a position a line already had (testlab
// concurrent-posting DEVY-1). The fix first reached debitor/ordre.php and
// kreditor/ordre.php and missed two other editors of order lines, finans/ordre.php
// and debitor/ret_genfakt.php (review, twice). A new editor now fails here until
// it uses the helper or says why it needs none. OrderLinePositionsRaceTest
// drives the pages themselves.
//
// Reads the source only; runs everywhere.
//
// History:
// 20261105 CL/LH Created.

use PHPUnit\Framework\TestCase;

final class OrderLinePositionPathsTest extends TestCase
{
    /** What a page that saves an order's line positions from its form calls. */
    private const HELPER_CALLS = ['order_lines_drawn', 'order_lines_added_since', 'order_line_new_position', 'order_lines_keep_added_apart'];

    /** Files that save line positions from a form without the helper, and why that is safe. */
    private const NOT_USED = [
        'kreditor/scanAttachments/frontpage/frontTable.php' => 'unreachable: only the disabled getScan/XXscanAttachments.php includes frontpageInclude.php, '
            . 'and its insert has no comma between posnr and ordre_id, so it never adds a line',
    ];

    /**
     * @return array<string, list<string>> repo-relative path of each file whose code (not its comments) reads a posted Pos
     *         field and writes ordrelinjer.posnr => the helper calls it lacks
     */
    private static function positionSavers(): array
    {
        $root = dirname(__DIR__, 4);
        $files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static fn(SplFileInfo $f): bool => !$f->isDir() || !in_array($f->getFilename(), ['.git', 'vendor', 'node_modules', 'tests'], true)
        ));
        $found = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string)file_get_contents($file->getPathname());
            if (!preg_match('/ordrelinjer/i', $source) || !str_contains($source, 'posn')) {
                continue;
            }
            $tokens = array_values(array_filter(token_get_all($source),
                static fn($token): bool => !is_array($token) || !in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML, T_WHITESPACE], true)));
            $readsPos = $writesPos = false;
            $calls = [];
            foreach ($tokens as $i => $token) {
                if (!is_array($token)) {
                    continue;
                }
                if (in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                    $text = $token[0] === T_CONSTANT_ENCAPSED_STRING ? substr($token[1], 1, -1) : $token[1];
                    // A line's Pos field: $_POST['posn...'], or a name built as 'posn' . $x or 'posnr_' . $x.
                    $previous = $tokens[$i - 1] ?? null;
                    $postKey = $previous === '[' && is_array($tokens[$i - 2] ?? null) && $tokens[$i - 2][1] === '$_POST';
                    $prefix = in_array($text, ['posn', 'posnr_'], true) && ($tokens[$i + 1] ?? null) === '.';
                    $readsPos = $readsPos || ($postKey && str_starts_with($text, 'posn')) || $prefix;
                    $writesPos = $writesPos || preg_match('/update\s+ordrelinjer\s+set\s+posnr|insert\s+into\s+ordrelinjer\s*\([^)]*\bposnr\b/i', $text) === 1;
                } elseif ($token[0] === T_STRING && in_array($token[1], self::HELPER_CALLS, true)) {
                    $calls[$token[1]] = true;
                }
            }
            if ($readsPos && $writesPos) {
                $found[substr($file->getPathname(), strlen($root) + 1)] = array_values(array_diff(self::HELPER_CALLS, array_keys($calls)));
            }
        }
        ksort($found);
        return $found;
    }

    public function test_every_form_that_saves_line_positions_uses_the_positions_helper(): void
    {
        $missing = [];
        foreach (self::positionSavers() as $path => $lacks) {
            if ($lacks !== [] && !isset(self::NOT_USED[$path])) {
                $missing[$path] = $lacks;
            }
        }

        $this->assertSame([], $missing,
            'These files save the positions of an order\'s lines from a form without includes/stdFunc/orderLinePositions.php. '
            . 'Print order_lines_drawn() in the form before the lines, and in the save lock the order row first in the transaction, '
            . 'then call order_lines_added_since(), order_line_new_position() and order_lines_keep_added_apart() as debitor/ordre.php does, '
            . 'or add the file to NOT_USED with the reason a stale form cannot give two lines one position.');
    }

    public function test_the_order_line_editors_are_among_the_files_found(): void
    {
        $savers = self::positionSavers();

        foreach (['debitor/ordre.php', 'debitor/ret_genfakt.php', 'finans/ordre.php', 'kreditor/ordre.php'] as $path) {
            $this->assertArrayHasKey($path, $savers, "$path saves line positions from its form, so the check must find it");
            $this->assertSame([], $savers[$path], "$path uses the positions helper");
        }
    }

    public function test_every_listed_exception_still_saves_line_positions(): void
    {
        $savers = self::positionSavers();

        foreach (array_keys(self::NOT_USED) as $path) {
            $this->assertArrayHasKey($path, $savers, "$path no longer saves line positions from a form; remove it from NOT_USED");
            $this->assertNotSame([], $savers[$path], "$path uses the positions helper now; remove it from NOT_USED");
        }
    }
}
