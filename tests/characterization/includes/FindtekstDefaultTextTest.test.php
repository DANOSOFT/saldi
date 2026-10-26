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

    public function testEmptyCsvLineFallsBackToTheCallsDefaultText(): void
    {
        self::boot('UTF8');
        self::assertSame('Autoudfyld', findtekst('4981|Autoudfyld', 1));
        self::assertSame([], $GLOBALS['__inserts'], 'the default text must not be stored in tekster, or a later csv text is never picked up');
    }

    public function testCsvTextStillWinsAndIsStored(): void
    {
        self::boot('UTF8');
        self::assertSame('Bestiller', findtekst('4982|Something else', 1));
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
        self::assertSame(mb_convert_encoding('Udløbsdato', 'ISO-8859-1', 'UTF-8'), findtekst('4981|Udløbsdato', 1));
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
            self::assertSame('Bestiller', findtekst('4982|Something else', 1));
            self::assertSame('Autoudfyld', findtekst('4981|Autoudfyld', 1));
        } finally {
            chdir($cwd);
        }
    }

    public function testDefaultTextIsCachedForTheSession(): void
    {
        self::boot('UTF8');
        findtekst('4981|Autoudfyld', 1);
        self::assertSame('Autoudfyld', $_SESSION['text_4981_1'] ?? null);
    }
}
