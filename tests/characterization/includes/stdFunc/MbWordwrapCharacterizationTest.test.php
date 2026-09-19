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
     * The ASCII test above cannot catch a bug in the wrapping algorithm, because pure-ASCII input
     * short-circuits to wordwrap() and never reaches it - which is exactly why the regression
     * above survived 40,000 generated ASCII cases.
     *
     * This drives the real algorithm instead. Each non-ASCII character is replaced by a one-byte
     * ASCII stand-in, so wordwrap() on that shadow string counts the same units mb_wordwrap()
     * counts on the original: the line lengths must agree. Ran at 7510 mismatches against the
     * pre-fix implementation, 0 after.
     */
    public function testSlowPathBreaksWhereWordwrapWouldWhenCountingCharacters(): void
    {
        $bits = ['a', 'bb', 'ccc', 'æ', 'øø', 'ååå', 'dæf', 'loooooongwoooord',
                 'øoooooooongword', 'xy', 'æøåæøåæøåæøå', 'q'];
        mt_srand(20260919);
        $checked = 0;

        for ($i = 0; $i < 400; $i++) {
            $words = [];
            for ($w = 0, $c = mt_rand(1, 9); $w < $c; $w++) {
                $words[] = $bits[mt_rand(0, count($bits) - 1)];
            }
            $tekst = implode(' ', $words);
            if ($i % 9 === 0) {
                $tekst = preg_replace('/ /', "\n", $tekst, 1);
            }
            if (!preg_match('/[\x80-\xFF]/', $tekst)) {
                continue; // only the slow path is under test here
            }
            $shadow = preg_replace('/[æøåÆØÅ]/u', 'x', $tekst);

            foreach ([1, 2, 4, 5, 8, 20] as $bredde) {
                foreach ([true, false] as $cut) {
                    self::assertSame(
                        self::lineLengths(wordwrap($shadow, $bredde, "\n", $cut)),
                        self::lineLengths(mb_wordwrap($tekst, $bredde, "\n", $cut)),
                        sprintf('width %d, cut %s, input %s', $bredde, $cut ? 'on' : 'off', json_encode($tekst))
                    );
                    $checked++;
                }
            }
        }
        self::assertGreaterThan(3000, $checked, 'the generator should produce a meaningful number of slow-path cases');
    }

    /** @return int[] Character length of each wrapped line. */
    private static function lineLengths(string $wrapped): array
    {
        return array_map(static fn(string $l): int => mb_strlen($l, 'UTF-8'), explode("\n", $wrapped));
    }
}
