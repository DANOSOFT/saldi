<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * SST-890: findtekst() showed "Tekst nr: 4981" on the Indkøb page because live's tekster.csv has an
 * empty line 4981, although the call carries a default text ('4981|Autoudfyld'). The default text
 * is now used when neither the tekster table nor tekster.csv has the text.
 *
 * The database layer is stubbed (no tekster rows, the table exists) and locateDir() points at a
 * small fixture tekster.csv, so findtekst() runs its real code path without a database.
 *
 * History:
 * 20261026 CL/MMR Created (SST-890).
 * 20261026 CL/MMR Real locateDir.php from a page two levels down (Codex review, SST-890).
 * 20261027 CL/MMR CodeRabbit (PR #878): '|' inside the default, fallbacks cached per default text, default "0".
 * 20261028 CL/MMR Fixture-only default texts are built with call(), not written as findtekst('id|text') literals that guardrails compare with the real tekster.csv.
 * 20261028 CL/MMR A csv translation "0" is found, stored and cached (CodeRabbit, PR #878).
 * 20261029 CL/MMR SST-909: a text from egnetekster.csv is kept, and empty lines in egnetekster.csv/egne_tekster.csv raise no warning.
 * 20261030 CL/MMR CodeRabbit (PR #957): a later egnetekster.csv line without a text for the language, or "-", does not replace an earlier text.
 */
#[RunTestsInSeparateProcesses]
final class FindtekstDefaultTextTest extends TestCase
{
    private static function boot(string $dbEncode, ?array $tableRow = null, bool $stubLocateDir = true): void
    {
        $dir = sys_get_temp_dir() . '/findtekst-sst890-' . getmypid();
        @mkdir($dir . '/importfiler', 0777, true);
        file_put_contents($dir . '/importfiler/tekster.csv', implode("\n", [
            "1\tDansk\tEnglish\tNorsk",
            "4981\t\t\t",
            "4982\tBestiller\tOrdering\tBestiller",
            "4983\t0\t0\t0",
            "",
        ]));
        $GLOBALS['__fixtureDir'] = $dir;
        $GLOBALS['__tableRow'] = $tableRow;
        $GLOBALS['__inserts'] = [];
        $GLOBALS['db'] = 'tenant_db';
        $GLOBALS['sqdb'] = 'master_db';
        $GLOBALS['db_encode'] = $dbEncode;
        $GLOBALS['bruger_id'] = 1;
        $_SESSION = [];
        eval('
            function db_select($q, $where = "") { return $q; }
            function db_fetch_array($q) {
                if (strpos($q, "information_schema") !== false) return ["column_name" => "tekst"];
                if (strpos($q, "from tekster where tekst_id") !== false) return $GLOBALS["__tableRow"] ?: false;
                return false;
            }
            function db_modify($q, $where = "") { $GLOBALS["__inserts"][] = $q; return true; }
            function db_escape_string($s) { return addslashes((string) $s); }
        ');
        if ($stubLocateDir) {
            eval('function locateDir($d) { return $GLOBALS["__fixtureDir"] . "/" . $d; }');
        }
        require dirname(__DIR__, 3) . '/includes/stdFunc/findTxt.php';
    }

    /**
     * The 'id|default' argument for findtekst(). These tests read their own fixture tekster.csv
     * (4981 empty, 4982 "Bestiller", 4983 "0") and pass default texts on purpose that differ from the real
     * importfiler/tekster.csv, so the argument is built here rather than written as a literal.
     */
    private static function call(int $id, string $default): string
    {
        return $id . '|' . $default;
    }

    public function testEmptyCsvLineFallsBackToTheCallsDefaultText(): void
    {
        self::boot('UTF8');
        self::assertSame('Autoudfyld', findtekst('4981|Autoudfyld', 1));
        self::assertSame([], $GLOBALS['__inserts'], 'the default text must not be stored in tekster, or a later csv text is never picked up');
    }

    public function testCsvTextStillWinsAndIsStored(): void
    {
        self::boot('UTF8');
        self::assertSame('Bestiller', findtekst(self::call(4982, 'Something else'), 1));
        self::assertCount(1, $GLOBALS['__inserts']);
        self::assertStringContainsString("insert into tekster", $GLOBALS['__inserts'][0]);
    }

    public function testTableTextStillWins(): void
    {
        self::boot('UTF8', ['id' => 7, 'tekst' => 'Fra tabellen']);
        self::assertSame('Fra tabellen', findtekst('4981|Autoudfyld', 1));
    }

    /** @return array<string, array{string}> */
    public static function callsWithoutDefault(): array
    {
        return ['integer id' => ['4981'], 'empty default' => ['4981|'], 'blank default' => ['4981|   ']];
    }

    #[DataProvider('callsWithoutDefault')]
    public function testWithoutADefaultTheOldPlaceholderStays(string $call): void
    {
        self::boot('UTF8');
        self::assertSame('Tekst nr: 4981', findtekst($call, 1));
    }

    public function testIntegerArgumentWithoutPipeRaisesNoWarning(): void
    {
        self::boot('UTF8');
        set_error_handler(static function (int $no, string $msg): bool { throw new \ErrorException($msg, 0, $no); });
        try {
            self::assertSame('Tekst nr: 4981', findtekst(4981, 1));
        } finally {
            restore_error_handler();
        }
    }

    public function testLatin9TenantGetsTheDefaultTextInIso88591(): void
    {
        self::boot('LATIN9');
        self::assertSame(mb_convert_encoding('Udløbsdato', 'ISO-8859-1', 'UTF-8'), findtekst(self::call(4981, 'Udløbsdato'), 1));
    }

    public function testRealLocateDirIsIncludedWhateverTheWorkingDirectory(): void
    {
        // A page two levels down (like lager/lister/indkøb.php): findTxt.php must include locateDir.php itself.
        self::boot('UTF8', null, false);
        $cwd = (string) getcwd();
        $page = $GLOBALS['__fixtureDir'] . '/lager/lister';
        @mkdir($page, 0777, true);
        chdir($page);
        try {
            self::assertSame('Bestiller', findtekst(self::call(4982, 'Something else'), 1));
            self::assertSame('Autoudfyld', findtekst('4981|Autoudfyld', 1));
        } finally {
            chdir($cwd);
        }
    }

    public function testDefaultTextIsCachedForTheSessionPerDefaultText(): void
    {
        self::boot('UTF8');
        findtekst('4981|Autoudfyld', 1);
        self::assertArrayNotHasKey('text_4981_1', $_SESSION, 'a fallback must not take the text id\'s cache key');
        // Served from the session now: the csv is gone, and nothing else could give "Autoudfyld"
        unlink($GLOBALS['__fixtureDir'] . '/importfiler/tekster.csv');
        self::assertSame('Autoudfyld', findtekst('4981|Autoudfyld', 1));
    }

    public function testDefaultTextKeepsAPipe(): void
    {
        self::boot('UTF8');
        self::assertSame('A | B', findtekst(self::call(4981, 'A | B'), 1));
    }

    public function testEachCallKeepsItsOwnDefaultText(): void
    {
        self::boot('UTF8');
        self::assertSame('Autoudfyld', findtekst('4981|Autoudfyld', 1));
        self::assertSame('Another label', findtekst(self::call(4981, 'Another label'), 1));
        self::assertSame('Tekst nr: 4981', findtekst('4981', 1));
        self::assertSame('Autoudfyld', findtekst('4981|Autoudfyld', 1));
    }

    public function testCachedPlaceholderDoesNotHideALaterDefault(): void
    {
        self::boot('UTF8');
        self::assertSame('Tekst nr: 4981', findtekst('4981', 1));
        self::assertSame('Autoudfyld', findtekst('4981|Autoudfyld', 1));
    }

    /** CodeRabbit (PR #878): a csv translation "0" is a real text: it wins over a default, is stored once and cached under the text id. */
    public function testCsvTranslationZeroIsARealText(): void
    {
        self::boot('UTF8');
        self::assertSame('0', findtekst(self::call(4983, 'Another label'), 1));
        self::assertCount(1, $GLOBALS['__inserts'], 'a csv text "0" must be stored in tekster like any other text');
        self::assertStringContainsString("values ('1','4983','0')", $GLOBALS['__inserts'][0]);
        self::assertSame('0', $_SESSION['text_4983_1'] ?? null, 'a csv text "0" must be cached as the text, not as a fallback');
        self::assertSame('0', findtekst('4983', 1));
        self::assertCount(1, $GLOBALS['__inserts']);
    }

    public function testDefaultTextZeroIsKept(): void
    {
        self::boot('UTF8');
        self::assertSame('0', findtekst(self::call(4981, '0'), 1));
    }

    /**
     * Runs $check from a page one level down, so findtekst()'s "../importfiler/egnetekster.csv" and
     * "../importfiler/egne_tekster.csv" are the fixture's files (written by $files, name => lines).
     */
    private static function fromPage(array $files, callable $check): void
    {
        foreach ($files as $name => $lines) {
            file_put_contents($GLOBALS['__fixtureDir'] . '/importfiler/' . $name, implode("\n", $lines) . "\n");
        }
        $cwd = (string) getcwd();
        $page = $GLOBALS['__fixtureDir'] . '/lager';
        if (!is_dir($page) && !mkdir($page, 0777, true)) {
            self::fail("Could not create the fixture page directory $page");
        }
        chdir($page);
        set_error_handler(static function (int $no, string $msg): bool { throw new \ErrorException($msg, 0, $no); });
        try {
            $check();
        } finally {
            restore_error_handler();
            chdir($cwd);
        }
    }

    public function testEgneteksterTextIsKeptAndStored(): void
    {
        // SST-909: the egnetekster.csv match was thrown away before egne_tekster.csv/tekster.csv were read
        self::boot('UTF8');
        self::fromPage(['egnetekster.csv' => ["4981\tEgen tekst\tOwn text\tEgen tekst"]], static function (): void {
            self::assertSame('Egen tekst', findtekst(self::call(4981, 'Standard'), 1));
        });
        self::assertCount(1, $GLOBALS['__inserts']);
        self::assertStringContainsString("values ('1','4981','Egen tekst')", $GLOBALS['__inserts'][0]);
    }

    public function testEgneteksterTakesPrecedenceOverTeksterCsv(): void
    {
        self::boot('UTF8');
        self::fromPage(['egnetekster.csv' => ["4982\tEgen bestiller\tOwn ordering\tEgen bestiller"]], static function (): void {
            self::assertSame('Egen bestiller', findtekst('4982', 1));
        });
    }

    public function testEmptyLinesInTheOwnTextFilesRaiseNoWarning(): void
    {
        // A line with only the id (trim() strips its tabs) has no language column
        self::boot('UTF8');
        self::fromPage(['egnetekster.csv' => ["4981"], 'egne_tekster.csv' => ["4982"]], static function (): void {
            self::assertSame('Standard', findtekst(self::call(4981, 'Standard'), 1));
            self::assertSame('Bestiller', findtekst('4982', 1));
        });
    }

    public function testAnIdOnlyLineDoesNotReplaceAnEarlierEgneteksterText(): void
    {
        // CodeRabbit (PR #957): the id-only line set the text to '' and tekster.csv's "Bestiller" was used instead
        self::boot('UTF8');
        self::fromPage(['egnetekster.csv' => ["4982\tEgen bestiller\tOwn ordering\tEgen bestiller", "4982"]], static function (): void {
            self::assertSame('Egen bestiller', findtekst('4982', 1));
        });
    }

    public function testALineWithoutTheLanguageColumnDoesNotReplaceAnEarlierText(): void
    {
        // The second line has only a Danish text, so it has no English column (language 2)
        self::boot('UTF8');
        self::fromPage(['egnetekster.csv' => ["4982\tEgen bestiller\tOwn ordering\tEgen bestiller", "4982\tAnden bestiller"]], static function (): void {
            self::assertSame('Own ordering', findtekst('4982', 2));
        });
    }

    public function testADashInEgneteksterFallsBackToTeksterCsv(): void
    {
        // CodeRabbit (PR #957): "-" skipped the fallback files and was then refused as a text, so the placeholder showed
        self::boot('UTF8');
        self::fromPage(['egnetekster.csv' => ["4982\t-\t-\t-"]], static function (): void {
            self::assertSame('Bestiller', findtekst('4982', 1));
        });
        self::assertCount(1, $GLOBALS['__inserts']);
        self::assertStringContainsString("values ('1','4982','Bestiller')", $GLOBALS['__inserts'][0]);
    }

    public function testTheLastEgneteksterTextForAnIdWins(): void
    {
        // Unchanged: of two lines with a text for the same id, the later one is used
        self::boot('UTF8');
        self::fromPage(['egnetekster.csv' => ["4982\tFørste bestiller", "4982\tSidste bestiller"]], static function (): void {
            self::assertSame('Sidste bestiller', findtekst('4982', 1));
        });
    }
}
