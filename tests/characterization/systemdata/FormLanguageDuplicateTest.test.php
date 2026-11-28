<?php
// tests/characterization/systemdata/FormLanguageDuplicateTest.test.php
//
// Runs the SST-745 script tests/test_sst745_form_language_duplicate.php in a child process, so CI runs it.
// The script lifts the sprog_id block out of systemdata/formularkort.php and evals it with stubs for the
// database layer only. When 03531dcd made the block ask perm_can() before it inserts a missing language,
// the first new language died on an undefined function and 6 of its 8 checks never ran, unseen, because
// nothing ran the script. It needs no database of its own: the script works on in-memory sqlite.
//
// History:
// 20261105 CL/LH Created.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class FormLanguageDuplicateTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../test_sst745_form_language_duplicate.php';

    /** @var array<int, string> */
    private const EXPECTED = [
        'three capitalisations of Engelsk leave a single VSPR row',
        'every capitalisation resolves to the existing kodenr 1',
        'a new language is still inserted',
        'the new language gets the next kodenr',
        'three distinct languages produce three rows',
        'pre-existing duplicate rows are preserved, not deleted',
        'a save against existing duplicates resolves to the lowest kodenr',
        'Dansk resolves to sprog_id 0 without creating a row',
        'a read-only user inserts no row for a new language',
        'for a read-only user a new language resolves to sprog_id 0',
        'for a read-only user an existing language still resolves to its kodenr',
    ];

    /** @var array{exit:int, stdout:string, stderr:string}|null */
    private static ?array $run = null;

    public static function setUpBeforeClass(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is required for the in-memory grupper table.');
        }
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
        foreach (self::EXPECTED as $label) {
            self::assertStringContainsString('PASS: ' . $label . "\n", $stdout, "missing check: $label\n$stdout$stderr");
        }
        self::assertSame(count(self::EXPECTED), substr_count($stdout, 'PASS: '), 'unexpected number of checks');
    }
}
