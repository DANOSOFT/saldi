<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * CHARACTERIZATION suite for genbestilReplayKey()/genbestilIsReplay()/genbestilRememberSubmit()
 * (lager/varerIncludes/genbestilReplay.php), MB-35 follow-up.
 *
 * lager/varer.php's genbestil_ant POST handler calls genbestil() once per submitted item, and
 * genbestil() itself has no idempotency - it always inserts a fresh ordrelinjer row. A double-click
 * on "Opret", or browser Back + resend, replays the identical POST and previously created a
 * duplicate purchase-order line per item, summing quantities. These functions fingerprint the whole
 * POST payload (scoped to tenant/user) so an identical resubmission in the same session is
 * recognised and skipped, while a genuinely different submission (edited quantities, different
 * items) is still processed. Same pattern as finans/kassekladde_includes/saveReplay.php (#538).
 */
final class GenbestilReplayCharacterizationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 4) . '/lager/varerIncludes/genbestilReplay.php';
        self::assertTrue(function_exists('genbestilReplayKey'), 'genbestilReplayKey() not defined after include');
        self::assertTrue(function_exists('genbestilIsReplay'), 'genbestilIsReplay() not defined after include');
        self::assertTrue(function_exists('genbestilRememberSubmit'), 'genbestilRememberSubmit() not defined after include');
    }

    public function testFreshSessionIsNeverATreatedAsReplay(): void
    {
        $post = ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 9];
        $key = genbestilReplayKey($post, 'test_12', 'Oskar D');
        self::assertFalse(genbestilIsReplay([], $key), 'An empty/fresh session must never look like a replay');
    }

    public function testIdenticalResubmissionAfterRememberIsDetectedAsReplay(): void
    {
        $post = ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 9];
        $key = genbestilReplayKey($post, 'test_12', 'Oskar D');

        $session = [];
        self::assertFalse(genbestilIsReplay($session, $key));
        genbestilRememberSubmit($session, $key);

        // Simulate the browser resending the exact same POST (double-click / back + resend).
        $replayedKey = genbestilReplayKey($post, 'test_12', 'Oskar D');
        self::assertSame($key, $replayedKey, 'The same payload must fingerprint identically on replay');
        self::assertTrue(genbestilIsReplay($session, $replayedKey), 'An identical resubmission must be detected as a replay');
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function differingSubmissions(): array
    {
        return [
            'different quantity for the same item' => [
                ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 3],
                'test_12',
                'Oskar D',
            ],
            'different item' => [
                ['genbestil_ant' => 1, 'gb_id_1' => 17, 'gb_antal_1' => 9],
                'test_12',
                'Oskar D',
            ],
            'different tenant' => [
                ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 9],
                'test_53',
                'Oskar D',
            ],
            'different user' => [
                ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 9],
                'test_12',
                'Someone Else',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $differentPost
     */
    #[DataProvider('differingSubmissions')]
    public function testGenuinelyDifferentSubmissionIsNotAReplay(array $differentPost, string $db, string $user): void
    {
        $originalPost = ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 9];
        $originalKey = genbestilReplayKey($originalPost, 'test_12', 'Oskar D');

        $session = [];
        genbestilRememberSubmit($session, $originalKey);

        $differentKey = genbestilReplayKey($differentPost, $db, $user);
        self::assertNotSame($originalKey, $differentKey, 'A genuinely different submission must fingerprint differently');
        self::assertFalse(genbestilIsReplay($session, $differentKey), 'A genuinely different submission must not be treated as a replay');
    }

    public function testOnlyTheMostRecentSubmissionIsRemembered(): void
    {
        $firstPost = ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 9];
        $secondPost = ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 3];
        $firstKey = genbestilReplayKey($firstPost, 'test_12', 'Oskar D');
        $secondKey = genbestilReplayKey($secondPost, 'test_12', 'Oskar D');

        $session = [];
        genbestilRememberSubmit($session, $firstKey);
        genbestilRememberSubmit($session, $secondKey);

        // Once a second, different submission has gone through, a replay of the FIRST one is no
        // longer recognised - only one Indkøbsforslag submission is ever in flight per session.
        self::assertFalse(genbestilIsReplay($session, $firstKey));
        self::assertTrue(genbestilIsReplay($session, $secondKey));
    }
}
