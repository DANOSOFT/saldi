<?php
// 20260922 CL/LAH Kreditor-forslag: pins the kravspec afsnit 6 tiers of poolVendorSuggestion()
//                  (auto / forslag / vælger / intet) in includes/docsIncludes/poolVendorSuggestion.php.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PoolVendorSuggestionCharacterizationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ob_start();
        require_once dirname(__DIR__, 4) . '/includes/docsIncludes/poolVendorSuggestion.php';
        $includeOutput = ob_get_clean();
        self::assertSame('', $includeOutput, 'poolVendorSuggestion.php emitted output at include time');
        self::assertTrue(function_exists('poolVendorSuggestion'));
    }

    // ---------------------------------------------------------------
    // Kravspec afsnit 6: what the bilagsflow does with a match
    // ---------------------------------------------------------------

    public function testCvrAndBankMatchesFillAutomatically(): void
    {
        foreach (['cvr' => 1.0, 'bank' => 0.95] as $match => $score) {
            $s = poolVendorSuggestion(['match' => $match, 'score' => $score, 'kontonr' => '30120', 'firmanavn' => 'Dan Group Alarm']);
            self::assertSame('auto', $s['mode'], $match);
            self::assertSame('K30120', $s['kredit']);
            self::assertSame('Dan Group Alarm', $s['firmanavn']);
        }
    }

    public function testNameMatchIsAutomaticFromEightyPercentAndASuggestionBelow(): void
    {
        self::assertSame('auto', poolVendorSuggestion(['match' => 'name', 'score' => 0.80, 'kontonr' => '1010'])['mode']);
        self::assertSame('auto', poolVendorSuggestion(['match' => 'name', 'score' => 0.833, 'kontonr' => '1010'])['mode']);
        $s = poolVendorSuggestion(['match' => 'name', 'score' => 0.61, 'kontonr' => '1010', 'firmanavn' => 'Papyrus Supplies A/S']);
        self::assertSame('suggest', $s['mode']);
        self::assertSame('K1010', $s['kredit']);
        self::assertSame(0.61, $s['score']);
        self::assertSame('auto', poolVendorSuggestion(['match' => 'name', 'score' => 0.61, 'kontonr' => '1010'], 0.5)['mode'], 'threshold is an option');
    }

    public function testAmbiguousOffersTheCandidatesAsKreditValues(): void
    {
        $s = poolVendorSuggestion(['match' => 'ambiguous', 'score' => 0, 'kontonr' => null, 'candidates' => [
            ['kontoId' => 501, 'kontonr' => '30500', 'firmanavn' => 'Nordisk Kontor ApS'],
            ['kontoId' => 502, 'kontonr' => '30501', 'firmanavn' => 'Nordisk Kontor Odense ApS'],
            ['kontoId' => 503, 'kontonr' => '', 'firmanavn' => 'broken row'],
        ]]);
        self::assertSame('choose', $s['mode']);
        self::assertNull($s['kredit']);
        self::assertSame(['K30500', 'K30501'], array_column($s['candidates'], 'kredit'));
        self::assertSame('none', poolVendorSuggestion(['match' => 'ambiguous', 'candidates' => []])['mode']);
    }

    public function testNoMatchNullOrMissingKontonrDoesNothing(): void
    {
        self::assertSame('none', poolVendorSuggestion(null)['mode']);
        self::assertSame('none', poolVendorSuggestion(['match' => 'none', 'score' => 0, 'kontonr' => null, 'name' => 'Kvickly'])['mode']);
        self::assertSame('none', poolVendorSuggestion(['match' => 'cvr', 'score' => 1, 'kontonr' => ''])['mode'], 'deleted kreditor: no kontonr');
        self::assertSame(['mode', 'kredit', 'kontonr', 'firmanavn', 'score', 'candidates'], array_keys(poolVendorSuggestion(null)));
    }
}
