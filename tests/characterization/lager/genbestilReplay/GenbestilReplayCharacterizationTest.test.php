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
 * POST payload plus a per-render form token (scoped to tenant/user) so an identical resubmission of
 * the SAME rendered form is recognised and skipped, while a genuinely different submission (edited
 * quantities, different items, or just a later, separate form load with coincidentally identical
 * content) is still processed. Same pattern as finans/kassekladde_includes/saveReplay.php (#538).
 */
final class GenbestilReplayCharacterizationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 4) . '/lager/varerIncludes/genbestilReplay.php';
        self::assertTrue(function_exists('genbestilFormToken'), 'genbestilFormToken() not defined after include');
        self::assertTrue(function_exists('genbestilReplayKey'), 'genbestilReplayKey() not defined after include');
        self::assertTrue(function_exists('genbestilIsReplay'), 'genbestilIsReplay() not defined after include');
        self::assertTrue(function_exists('genbestilRememberSubmit'), 'genbestilRememberSubmit() not defined after include');
    }

    public function testFormTokenIsUnpredictableAndUnique(): void
    {
        $a = genbestilFormToken();
        $b = genbestilFormToken();
        self::assertNotSame($a, $b, 'Two renders must never share a form token');
        self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/D', $a);
    }

    public function testFreshSessionIsNeverATreatedAsReplay(): void
    {
        $post = ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 9];
        $key = genbestilReplayKey($post, 'token-1', 'test_12', 'Oskar D');
        self::assertFalse(genbestilIsReplay([], $key), 'An empty/fresh session must never look like a replay');
    }

    public function testIdenticalResubmissionAfterRememberIsDetectedAsReplay(): void
    {
        $post = ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 9];
        $key = genbestilReplayKey($post, 'token-1', 'test_12', 'Oskar D');

        $session = [];
        self::assertFalse(genbestilIsReplay($session, $key));
        genbestilRememberSubmit($session, $key);

        // Simulate the browser resending the exact same POST (double-click / back + resend) - the
        // form token is carried in the resent POST unchanged, since it's a hidden field of that
        // same rendered page.
        $replayedKey = genbestilReplayKey($post, 'token-1', 'test_12', 'Oskar D');
        self::assertSame($key, $replayedKey, 'The same payload and form token must fingerprint identically on replay');
        self::assertTrue(genbestilIsReplay($session, $replayedKey), 'An identical resubmission must be detected as a replay');
    }

    public function testSameContentDifferentFormTokenIsNotAReplay(): void
    {
        // Two separate page loads that happen to suggest the exact same item/quantity - a real,
        // deliberate second order, not a double-click of the first one. Each render gets its own
        // token, so the two must not collide even though the rest of the payload is identical.
        $post = ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 9];
        $firstKey = genbestilReplayKey($post, 'token-1', 'test_12', 'Oskar D');

        $session = [];
        genbestilRememberSubmit($session, $firstKey);

        $secondKey = genbestilReplayKey($post, 'token-2', 'test_12', 'Oskar D');
        self::assertNotSame($firstKey, $secondKey, 'Two distinct form tokens must fingerprint differently even with identical content');
        self::assertFalse(genbestilIsReplay($session, $secondKey), 'A later, separate form load must not be treated as a replay of an earlier one');
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string, string}>
     */
    public static function differingSubmissions(): array
    {
        return [
            'different quantity for the same item' => [
                ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 3],
                'token-1',
                'test_12',
                'Oskar D',
            ],
            'different item' => [
                ['genbestil_ant' => 1, 'gb_id_1' => 17, 'gb_antal_1' => 9],
                'token-1',
                'test_12',
                'Oskar D',
            ],
            'different tenant' => [
                ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 9],
                'token-1',
                'test_53',
                'Oskar D',
            ],
            'different user' => [
                ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 9],
                'token-1',
                'test_12',
                'Someone Else',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $differentPost
     */
    #[DataProvider('differingSubmissions')]
    public function testGenuinelyDifferentSubmissionIsNotAReplay(array $differentPost, string $formToken, string $db, string $user): void
    {
        $originalPost = ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 9];
        $originalKey = genbestilReplayKey($originalPost, 'token-1', 'test_12', 'Oskar D');

        $session = [];
        genbestilRememberSubmit($session, $originalKey);

        $differentKey = genbestilReplayKey($differentPost, $formToken, $db, $user);
        self::assertNotSame($originalKey, $differentKey, 'A genuinely different submission must fingerprint differently');
        self::assertFalse(genbestilIsReplay($session, $differentKey), 'A genuinely different submission must not be treated as a replay');
    }

    public function testEarlierSubmissionIsStillRecognisedAfterALaterOneSucceeds(): void
    {
        // A -> B -> A: submit A, then a different B, then A is resent (e.g. browser Back past B).
        // A must still be recognised as a replay - only remembering the single most recent
        // submission would forget A once B goes through (CodeRabbit).
        $postA = ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 9];
        $postB = ['genbestil_ant' => 1, 'gb_id_1' => 16, 'gb_antal_1' => 3];
        $keyA = genbestilReplayKey($postA, 'token-1', 'test_12', 'Oskar D');
        $keyB = genbestilReplayKey($postB, 'token-1', 'test_12', 'Oskar D');

        $session = [];
        genbestilRememberSubmit($session, $keyA);
        genbestilRememberSubmit($session, $keyB);

        self::assertTrue(genbestilIsReplay($session, $keyA), 'An earlier submission must still be recognised as a replay after a later, different one');
        self::assertTrue(genbestilIsReplay($session, $keyB));
    }

    public function testRememberedSubmissionsAreBoundedTo128(): void
    {
        $session = [];
        for ($i = 0; $i < 130; $i++) {
            genbestilRememberSubmit($session, "key-$i");
        }
        self::assertCount(128, $session['gb_completed_submits']);
        self::assertFalse(genbestilIsReplay($session, 'key-0'), 'The oldest entries must be evicted once the bound is exceeded');
        self::assertTrue(genbestilIsReplay($session, 'key-129'));
    }
}
