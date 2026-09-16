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
}
