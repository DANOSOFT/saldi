<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../includes/stdFunc/mbWordwrap.php';

// 20260916 CDX/MJ SST-784 Pins the two properties the print path depends on: mb_wordwrap() is
//                  byte-identical to wordwrap() on ASCII, so no existing non-Danish printout
//                  shifts, and it measures Danish text in characters, which is the defect
//                  reported on Auto-Lauge's order confirmation. Text here is synthetic.
final class MbWordwrapCharacterizationTest extends TestCase
{
    /**
     * The regression that motivated this: wordwrap() counts bytes, so 'æ', 'ø' and 'å' each
     * spend two of the column budget and the line breaks early by exactly that many characters.
     */
    public function testDanishTextFillsTheColumnInsteadOfBreakingEarly(): void
    {
        $tekst = 'Tjek for evt. utætheder på slanger før næste service';
        $bredde = 44;

        $gammel = explode("\n", wordwrap($tekst, $bredde, "\n", true));
        $ny     = explode("\n", mb_wordwrap($tekst, $bredde, "\n", true));

        // The defect, pinned so it cannot quietly come back.
        self::assertSame(38, mb_strlen($gammel[0], 'UTF-8'), 'wordwrap() should still wrap 6 characters early');
        self::assertSame(44, mb_strlen($ny[0], 'UTF-8'), 'mb_wordwrap() should fill the column');
    }

    #[DataProvider('danishLines')]
    public function testNoLineEverExceedsTheWidthInCharacters(string $tekst, int $bredde): void
    {
        foreach (explode("\n", mb_wordwrap($tekst, $bredde, "\n", true)) as $linje) {
            self::assertLessThanOrEqual($bredde, mb_strlen($linje, 'UTF-8'), "overflowed on: $linje");
        }
    }

    public static function danishLines(): array
    {
        $dk = 'Tjek for evt. utætheder på slanger før næste service og syn på køretøjet';
        return [
            'narrow'          => [$dk, 12],
            'reported width'  => [$dk, 44],
            'wide'            => [$dk, 54],
            'single long word'=> ['Bremseskiveudskiftning-forakselsættet-komplet', 20],
            'all non-ascii'   => ['ææææ øøøø åååå ÆØÅ', 8],
        ];
    }

    /**
     * ASCII must route to wordwrap() untouched — every existing non-Danish printout depends on
     * the line breaks not moving.
     */
    #[DataProvider('asciiCases')]
    public function testAsciiIsByteIdenticalToWordwrap(string $tekst, int $bredde, bool $cut): void
    {
        self::assertSame(
            wordwrap($tekst, $bredde, "\n", $cut),
            mb_wordwrap($tekst, $bredde, "\n", $cut)
        );
    }

    public static function asciiCases(): array
    {
        $cases = [];
        foreach ([
            'Service check of brakes and lines before the next inspection',
            'a bb ccc dddd eeeee ffffff',
            "first line\nsecond line that is rather longer than the first",
            'Supercalifragilisticexpialidocious',
            '',
            '   leading and trailing   ',
        ] as $i => $t) {
            foreach ([1, 5, 12, 40] as $w) {
                foreach ([true, false] as $cut) {
                    $cases["case{$i}_w{$w}_" . ($cut ? 'cut' : 'nocut')] = [$t, $w, $cut];
                }
            }
        }
        return $cases;
    }

    /** Existing newlines are hard breaks in wordwrap(); that must not change. */
    public function testExistingNewlinesArePreserved(): void
    {
        self::assertSame("Første\nAndet", mb_wordwrap("Første\nAndet", 40, "\n", true));
    }

    /**
     * 20260919 CDX/MJ Regression from PR #623 review: an over-long word following other text was
     * chopped starting from the space left on the current line, instead of from a blank line the
     * way wordwrap() does. Same column width, different break positions.
     *
     * Note the two are NOT byte-identical here, and should not be - wordwrap() cuts 'løoo' at five
     * BYTES while mb_wordwrap() cuts 'løooo' at five CHARACTERS. What has to match is the shape:
     * 'ab' flushed onto its own line before the chopping starts.
     */
    public function testOverlongWordStartsChoppingFromABlankLine(): void
    {
        self::assertSame(
            ['ab', 'løooo', 'ongwo', 'rd'],
            explode("\n", mb_wordwrap('ab løoooongword', 5, "\n", true))
        );
        // ASCII goes through wordwrap() itself, so this pins the reference behaviour being copied.
        self::assertSame(
            ['ab', 'loooo', 'ngwor', 'd'],
            explode("\n", mb_wordwrap('ab loooongword', 5, "\n", true))
        );
        // The remainder stays on the line, so a following word can still join it.
        self::assertSame(
            ['loooo', 'ngwor', 'd ab'],
            explode("\n", mb_wordwrap('loooongword ab', 5, "\n", true))
        );
    }

    /**
     * 20260919 CDX/MJ Runs of spaces, from CodeRabbit on #623. explode(' ') produced empty tokens
     * for them and a space was dropped per token, which lost leading spaces after a hard break and
     * mis-counted the width.
     */
    #[DataProvider('spaceCases')]
    public function testRunsOfSpacesSurvive(string $tekst, string $expected): void
    {
        self::assertSame($expected, mb_wordwrap($tekst, 40, "\n", true));
    }

    public static function spaceCases(): array
    {
        return [
            'after a hard break' => ["Æ\n  Andet", "Æ\n  Andet"],
            'leading'            => ['  Ø start', '  Ø start'],
            'interior'           => ['æ  cd', 'æ  cd'],
            'run of three'       => ['æ   x', 'æ   x'],
            'trailing'           => ['Æ trailing  ', 'Æ trailing  '],
        ];
    }

    /**
     * This is the test with the actual power, and the one whose absence let three separate parity
     * bugs through review.
     *
     * The ASCII test above proves nothing about the wrapping logic: pure-ASCII input short-circuits
     * to wordwrap() and never reaches it. So this strips that short-circuit out of a copy of the
     * function and runs the real code path on ASCII, where bytes and characters are the same unit
     * and wordwrap() is therefore an exact oracle - output must be byte-identical, not merely the
     * same shape.
     *
     * Scored 69126 mismatches against a hand-written scanner and 0 against the current
     * implementation, which is why the implementation delegates the decisions to wordwrap()
     * instead of reproducing them.
     */
    public function testRealCodePathIsByteIdenticalToWordwrapOnAscii(): void
    {
        $raw = self::withoutAsciiShortcut();

        mt_srand(424242);
        $bits  = ['a', 'bb', 'ccc', 'dddd', 'x', 'loooooongword', 'supercalifragilistic', 'q', 'zz'];
        $glues = [' ', '  ', '   ', '    '];
        $checked = 0;

        for ($i = 0; $i < 300; $i++) {
            $tekst = '';
            for ($w = 0, $c = mt_rand(0, 8); $w < $c; $w++) {
                $tekst .= ($w ? $glues[mt_rand(0, 3)] : '') . $bits[mt_rand(0, count($bits) - 1)];
            }
            if ($i % 4 === 0)  $tekst = $glues[mt_rand(0, 3)] . $tekst;
            if ($i % 5 === 0)  $tekst = $tekst . $glues[mt_rand(0, 3)];
            if ($i % 6 === 0)  $tekst = preg_replace('/ /', "\n", $tekst, 1);
            if ($i % 7 === 0)  $tekst = "\n" . $glues[mt_rand(0, 3)] . $tekst;
            if ($i % 13 === 0) $tekst = $tekst . "\n";

            foreach ([1, 2, 3, 4, 5, 7, 11, 20, 75] as $bredde) {
                foreach ([true, false] as $cut) {
                    self::assertSame(
                        wordwrap($tekst, $bredde, "\n", $cut),
                        $raw($tekst, $bredde, "\n", $cut),
                        sprintf('width %d, cut %s, input %s', $bredde, $cut ? 'on' : 'off', json_encode($tekst))
                    );
                    $checked++;
                }
            }
        }
        self::assertGreaterThan(5000, $checked);
    }

    /**
     * mb_wordwrap() with the pure-ASCII short-circuit removed, so ASCII input reaches the real
     * wrapping code. Built from the shipped source rather than copied, so it cannot drift.
     */
    private static function withoutAsciiShortcut(): callable
    {
        static $fn = null;
        if ($fn !== null) {
            return $fn;
        }
        $src = file_get_contents(__DIR__ . '/../../../../includes/stdFunc/mbWordwrap.php');

        // From the signature to the brace that closes the function: the body ends at `return $ud;`,
        // so the next `}` after it is the function's own. Deterministic, and it fails loudly rather
        // than silently testing the wrong thing if the source is restructured.
        $start = strpos($src, 'function mb_wordwrap(');
        self::assertNotFalse($start, 'mb_wordwrap() signature not found');
        $retur = strpos($src, 'return $ud;', $start);
        self::assertNotFalse($retur, 'end of mb_wordwrap() body not found');
        $slut = strpos($src, '}', $retur);
        self::assertNotFalse($slut);
        $body = substr($src, $start, $slut - $start + 1);

        $lines = array_filter(
            explode("\n", $body),
            static fn(string $l): bool => strpos($l, '\x80-\xFF') === false
        );
        $stripped = implode("\n", $lines);
        self::assertStringNotContainsString('x80', $stripped, 'the ASCII short-circuit should be gone');

        $fn = eval('return ' . preg_replace('/^function mb_wordwrap\s*\(/', 'function (', $stripped, 1) . ';');
        self::assertIsCallable($fn);
        return $fn;
    }
}
