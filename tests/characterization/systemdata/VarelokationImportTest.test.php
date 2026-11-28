<?php
// tests/characterization/systemdata/VarelokationImportTest.test.php
//
// Runs the SST-875 script tests/test_varelokation_import.php in a child process, so CI runs it.
// The script pins the statements of overfoer_data() in systemdata/importer_varelokationer.php that keep the file's
// line break out of a location, and evaluates them. When 561d47e2 rewrote the split into $felter and began skipping
// blank lines with trim(), 3 of its 15 checks failed on statements it no longer found, unseen, because nothing ran
// the script. It reads the page's source only and needs no database.
//
// History:
// 20261105 CL/LH Created.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class VarelokationImportTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../test_varelokation_import.php';

    /** @var array{exit:int, stdout:string, stderr:string}|null */
    private static ?array $run = null;

    public static function setUpBeforeClass(): void
    {
        self::$run = CharacterizationEnv::runChild(self::SCRIPT);
    }

    public function testTheScriptFinishesWithoutAnError(): void
    {
        $run = self::$run;
        self::assertNotNull($run);
        self::assertSame(0, $run['exit'], $run['stdout'] . $run['stderr']);
    }

    public function testEveryCheckPassed(): void
    {
        $stdout = (string)(self::$run['stdout'] ?? '');
        $stderr = (string)(self::$run['stderr'] ?? '');
        self::assertSame("\nall 16 checks passed\n", $stdout, $stdout . $stderr);
    }
}
