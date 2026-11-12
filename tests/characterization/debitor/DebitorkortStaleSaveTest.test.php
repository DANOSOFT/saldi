<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../support/database/TestDatabase.php';

// 20261112 CL/SS MB-82 Customer card stale save.
//                Two users open the same customer; the one saving from the older form overwrote the other's newer notes without a warning.
//                The real debitorkortIncludes/staleSave.php runs in a child process (support/run_debitorkort_stale_save.php) against an isolated schema.
final class DebitorkortStaleSaveTest extends TestCase
{
    private const RUNNER = __DIR__ . '/../support/run_debitorkort_stale_save.php';

    /** @var array<int, string> */
    private const EXPECTED = [
        "A card saved with nothing changed since it was opened is not stale",
        "A card opened before another user's save is refused",
        "After reopening, the same user can save",
        "Invoicing and modtime (columns the card doesn't save) don't make a card stale",
        "A changed e-mail row makes the card stale",
        "A changed delivery address makes the card stale",
        "An added delivery address makes the card stale",
        "Reordered contacts make the card stale",
        "An edited contact makes the card stale, so «-» can't delete it unseen",
        "A private card gets its first and last name from its name as plain text, also with æøå",
        "The names are filled in once, and only on a private card",
        "Names saved by someone else while the card fills them in are kept",
        "On a LATIN9 connection a customer with æøå is still detected as changed",
        "A post without recordHash, a new customer and a malformed hash are not checked",
    ];

    /** @var array{exit:int, stdout:string, stderr:string}|null */
    private static ?array $run = null;

    /**
     * Runs the child runner once for the class, with the database connection passed through the environment.
     */
    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $dsn = sprintf(
            "host='%s' dbname='%s' user='%s' password='%s' connect_timeout=3",
            addcslashes(CharacterizationEnv::pgHost(), "'\\"),
            addcslashes(TestDatabase::sqlFixture(static::class)->name(), "'\\"),
            addcslashes(CharacterizationEnv::pgUser(), "'\\"),
            addcslashes(CharacterizationEnv::pgPass(), "'\\")
        );
        // Through the environment, not argv, so the password is not visible in the process list.
        putenv('SALDI_STALE_SAVE_DSN=' . $dsn);
        try {
            self::$run = CharacterizationEnv::runChild(self::RUNNER);
        } finally {
            putenv('SALDI_STALE_SAVE_DSN');
        }
    }

    /**
     * The runner exits 0 and reports no failed check.
     */
    public function testTheRunnerFinishesWithoutFailures(): void
    {
        $run = self::$run;
        self::assertNotNull($run);
        self::assertStringNotContainsString('FAIL:', $run['stdout'], $run['stdout'] . $run['stderr']);
        self::assertSame(0, $run['exit'], $run['stdout'] . $run['stderr']);
    }

    /**
     * Every expected check is reported as passed, and no other check.
     */
    public function testEveryCheckPassed(): void
    {
        $stdout = (string)(self::$run['stdout'] ?? '');
        foreach (self::EXPECTED as $label) {
            self::assertStringContainsString('PASS: ' . $label . "\n", $stdout, "missing check: $label");
        }
        self::assertSame(count(self::EXPECTED), substr_count($stdout, 'PASS: '), 'unexpected number of checks');
    }
}
