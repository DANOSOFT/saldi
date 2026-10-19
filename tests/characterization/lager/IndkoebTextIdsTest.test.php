<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20261016 MJ SST-871 Pins which text ids the Indkoeb list reads, and that none of them is
//                  shared with the PBS resend.
//
//                  MEDSHOP could not place a purchase proposal: the Bestil button said
//                  "Fakturaen er udlignet/betalt og kan ikke gensendes til PBS" while
//                  ordering, and a created order answered "Fakturaen har ikke vaeret med i
//                  nogen PBS-leverance. Den kommer paa leverancen ved bogfoering.!". The
//                  order was in fact created; read as a failure, it was submitted three
//                  times, so purchase order 16839 got item 755099 three times over.
//
//                  Nothing about this is visible from the page: findtekst() returns the
//                  tekster row for the id and only falls back to the text after the pipe
//                  when the tekster table is missing (includes/stdFunc/findTxt.php:35). A
//                  caller pointed at someone else's id therefore prints their string in
//                  silence. These tests read the id out of the page source and compare it
//                  with the row that decides what is shown, which is the only place the
//                  disagreement exists.
//
//                  lager/lister/indkoeb.php is a page script that opens a session and prints
//                  on include, so the source is read rather than included.
final class IndkoebTextIdsTest extends TestCase
{
    private const PAGE = __DIR__ . '/../../../lager/lister/indk' . "\u{00f8}" . 'b.php';
    private const TEKSTER = __DIR__ . '/../../../importfiler/tekster.csv';

    /** The PBS resend owns 3385-3404; these four are the ones the list used to borrow. */
    private const PBS_FILES = array(
        'includes/pbsfunc.php',
        'debitor/pbs_gensend.php',
        'debitor/pbsfile.php',
    );

    /** What each call site means, against the id that now has to hold it. */
    private const EXPECTED = array(
        '4982' => 'Bestiller',
        '4983' => 'Bestillinger oprettet',
        '4984' => 'Fejl ved oprettelse af bestillinger',
        '4985' => 'Ingen v' . "\u{00e6}" . 'rdier angivet',
    );

    private static ?string $src = null;
    /** @var array<string, array{string, string, string}>|null */
    private static ?array $rows = null;

    private static function source(): string
    {
        if (self::$src === null) {
            $src = file_get_contents(self::PAGE);
            self::assertNotFalse($src, 'could not read lager/lister/indk' . "\u{00f8}" . 'b.php');
            self::$src = $src;
        }
        return self::$src;
    }

    /**
     * The rows that actually decide what the user sees.
     *
     * @return array<string, array{string, string, string}> id => [Dansk, English, Norsk]
     */
    private static function rows(): array
    {
        if (self::$rows === null) {
            $raw = file_get_contents(self::TEKSTER);
            self::assertNotFalse($raw, 'could not read importfiler/tekster.csv');
            $rows = array();
            foreach (explode("\n", $raw) as $line) {
                $f = explode("\t", rtrim($line, "\r"));
                if (isset($f[0]) && preg_match('/^[0-9]+$/', $f[0])) {
                    $rows[$f[0]] = array($f[1] ?? '', $f[2] ?? '', $f[3] ?? '');
                }
            }
            self::$rows = $rows;
        }
        return self::$rows;
    }

    /**
     * Every findtekst id in a file, with the text each call site wrote after the pipe.
     *
     * @return array<string, list<string>> id => defaults seen
     */
    private static function callsIn(string $source): array
    {
        $found = array();
        preg_match_all(
            '/findtekst(?:_utf8)?\s*\(\s*([\'"])([0-9]+)\|((?:\\\\.|(?!\1).)*)\1/',
            $source, $m, PREG_SET_ORDER);
        foreach ($m as $hit) {
            $found[$hit[2]][] = $hit[3];
        }
        return $found;
    }

    /** The reported bug: the four call sites read the ids that hold their text. */
    public function testTheFourTextsReadTheIdsThatHoldThem(): void
    {
        $calls = self::callsIn(self::source());
        foreach (self::EXPECTED as $id => $wanted) {
            self::assertArrayHasKey($id, $calls,
                "id $id is no longer read, so \"$wanted\" comes from somewhere else again");
            self::assertContains($wanted, $calls[$id],
                "id $id is read for something other than \"$wanted\"");
        }
    }

    /** The ids it used to read belong to PBS and must be gone from this page. */
    public function testThePbsIdsAreNoLongerReadByTheList(): void
    {
        $calls = self::callsIn(self::source());
        foreach (array('3389', '3390', '3391', '3392') as $pbsId) {
            self::assertArrayNotHasKey($pbsId, $calls,
                "id $pbsId is the PBS resend's, so the list prints a PBS sentence again");
        }
    }

    /**
     * The invariant behind the bug, not just the four instances of it.
     *
     * Sharing an id is how the text table is meant to work - the list and the PBS resend
     * both read 438 for "Dato", and one row serving both is correct. What broke MEDSHOP is
     * a shared id the two sides mean differently: the row can then only satisfy one of
     * them, and the other prints its text. So a shared id has to carry the same text on
     * both sides, whichever ids either page picks up later.
     */
    public function testAnyTextIdSharedWithThePbsResendMeansTheSameThing(): void
    {
        $mine = self::callsIn(self::source());
        $disagreements = array();
        foreach (self::PBS_FILES as $rel) {
            $path = __DIR__ . '/../../../' . $rel;
            if (!file_exists($path)) {
                continue;
            }
            $theirs = self::callsIn((string)file_get_contents($path));
            foreach (array_intersect(array_keys($mine), array_keys($theirs)) as $id) {
                foreach ($mine[$id] as $ours) {
                    foreach ($theirs[$id] as $other) {
                        if (self::sameText($ours, $other)) {
                            continue;
                        }
                        $disagreements[] = "id $id: the list wants \"$ours\", $rel wants \"$other\"";
                    }
                }
            }
        }
        self::assertSame(array(), $disagreements, implode('; ', $disagreements));
    }

    /** Compares two defaults the way a reader would: ignoring case and trailing marks. */
    private static function sameText(string $a, string $b): bool
    {
        $tidy = static function (string $s): string {
            $s = str_replace(array("\\'", '\\"'), array("'", '"'), $s);
            $s = (string)preg_replace('/\s+/u', ' ', $s);
            return mb_strtolower(trim(trim($s), ".:!? "), 'UTF-8');
        };
        return $tidy($a) === $tidy($b);
    }

    /** What the four ids hold is what the call sites say they hold. */
    public function testTheRowsSayWhatTheCallSitesMean(): void
    {
        $rows = self::rows();
        foreach (self::EXPECTED as $id => $wanted) {
            self::assertArrayHasKey($id, $rows, "tekster.csv has no row $id");
            self::assertSame($wanted, $rows[$id][0],
                "row $id does not hold \"$wanted\", so the page shows something else");
        }
    }

    /** No text the list shows may be a PBS text - the symptom the customer saw. */
    public function testNothingTheListShowsMentionsPbs(): void
    {
        $rows = self::rows();
        foreach (array_keys(self::callsIn(self::source())) as $id) {
            if (!isset($rows[$id])) {
                continue;
            }
            foreach ($rows[$id] as $text) {
                self::assertStringNotContainsStringIgnoringCase('PBS', $text,
                    "id $id shows \"$text\", which is PBS wording");
            }
        }
    }

    /**
     * Every id the page reads is complete in all three languages.
     *
     * Blank columns are what made the button read "Tekst nr: 4981" for the customer: on
     * live those rows were missing, and findtekst() prints the id when it resolves to
     * nothing. They are filled here, so this fails if a later change reads an id that is
     * not, and the remaining live gap is a release away rather than a code change.
     */
    public function testEveryIdTheListReadsIsTranslatedInAllThreeLanguages(): void
    {
        $rows = self::rows();
        $incomplete = array();
        foreach (array_keys(self::callsIn(self::source())) as $id) {
            if (!isset($rows[$id])) {
                $incomplete[] = "$id (no row)";
                continue;
            }
            foreach (array(0 => 'Dansk', 1 => 'English', 2 => 'Norsk') as $col => $lang) {
                if (trim($rows[$id][$col]) === '') {
                    $incomplete[] = "$id ($lang)";
                }
            }
        }
        self::assertSame(array(), $incomplete,
            'ids the list reads without a text: ' . implode(', ', $incomplete));
    }

    /** The PBS resend keeps its own texts - this change moved the list, not PBS. */
    public function testThePbsResendKeepsItsOwnTexts(): void
    {
        $rows = self::rows();
        $pbs = array(
            '3389' => 'Fakturaen er udlignet/betalt og kan ikke gensendes til PBS',
            '3391' => 'Ordren er ikke bogf' . "\u{00f8}" . 'rt som faktura',
            '3392' => 'Afvisning fra Nets (advis/fejlkode og dato)',
        );
        foreach ($pbs as $id => $text) {
            self::assertArrayHasKey($id, $rows, "tekster.csv has no row $id");
            self::assertSame($text, $rows[$id][0], "row $id is no longer the PBS text");
        }
        self::assertStringStartsWith(
            'Fakturaen har ikke v' . "\u{00e6}" . 'ret med i nogen PBS-leverance',
            $rows['3390'][0], 'row 3390 is no longer the PBS text');
    }

    /** Each of the four ids is read by this page alone, so it cannot be borrowed back. */
    public function testTheFourIdsAreReadOnlyByThisPage(): void
    {
        $root = realpath(__DIR__ . '/../../../');
        self::assertNotFalse($root);
        $page = realpath(self::PAGE);

        $others = array();
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            if ($file->getExtension() !== 'php' || $file->getRealPath() === $page) {
                continue;
            }
            $path = (string)$file->getRealPath();
            if (strpos($path, DIRECTORY_SEPARATOR . '.git') !== false
                || strpos($path, DIRECTORY_SEPARATOR . 'vendor') !== false
                || strpos($path, DIRECTORY_SEPARATOR . 'tests') !== false) {
                continue;
            }
            $calls = self::callsIn((string)file_get_contents($path));
            foreach (array_keys(self::EXPECTED) as $id) {
                if (isset($calls[$id])) {
                    $others[] = substr($path, strlen($root) + 1) . " reads $id";
                }
            }
        }
        self::assertSame(array(), $others,
            'the four ids are read elsewhere too: ' . implode(', ', $others));
    }
}
