<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/signupIncludes/signupFunc.php';

if (!function_exists('ifset')) {
    /** Minimal stand-in for includes/std_func.php's ifset() so the pure functions can run without the app. */
    function ifset($arrayOrVar, $key = null, $default = null)
    {
        if (is_array($arrayOrVar) && $key !== null) {
            return array_key_exists($key, $arrayOrVar) && $arrayOrVar[$key] !== null ? $arrayOrVar[$key] : $default;
        }
        return $arrayOrVar ?? $default;
    }
}

/**
 * Measurement (doc/signup.md, "Measurement"): when account_activated may go to Google Analytics, and what it carries.
 * The consent comes from the website's saldi_consent cookie, the ids from GA4's own cookies on .saldi.dk.
 */
final class signupTracking extends TestCase
{
    private const NOW = 1795429492; // 2026-11-23

    private static function ms(int $secondsAgo): string
    {
        return (string)((self::NOW - $secondsAgo) * 1000 + 784);
    }

    public function testTheConsentCookieIsRead(): void
    {
        self::assertSame(['statistik' => true, 'marketing' => true], signup_consent_cookie('1.' . self::ms(60) . '.1.1', self::NOW));
        self::assertSame(['statistik' => true, 'marketing' => false], signup_consent_cookie('1.' . self::ms(60) . '.1.0', self::NOW));
        self::assertSame(['statistik' => false, 'marketing' => false], signup_consent_cookie('1.' . self::ms(60) . '.0.0', self::NOW));
    }

    public function testAChoiceCountsForTwelveMonths(): void
    {
        self::assertNotNull(signup_consent_cookie('1.' . self::ms(364 * 86400) . '.1.1', self::NOW));
        self::assertNull(signup_consent_cookie('1.' . self::ms(366 * 86400) . '.1.1', self::NOW), 'expired, as on the website');
        self::assertNull(signup_consent_cookie('1.' . self::ms(-2 * 86400) . '.1.1', self::NOW), 'from the future');
    }

    /** @return array<string, array{string}> */
    public static function badCookies(): array
    {
        return [
            'empty' => [''],
            'other version' => ['2.1795429492784.1.1'],
            'not 0/1' => ['1.1795429492784.2.1'],
            'missing part' => ['1.1795429492784.1'],
            'extra part' => ['1.1795429492784.1.1.1'],
            'json, as in localStorage' => ['{"v":1,"t":1795429492784,"statistik":true,"marketing":true}'],
            'trailing text' => ["1.1795429492784.1.1\n"],
        ];
    }

    #[DataProvider('badCookies')]
    public function testAnythingElseIsNoChoice(string $raw): void
    {
        self::assertNull(signup_consent_cookie($raw, self::NOW));
    }

    public function testGaIdsComeFromBothCookieFormats(): void
    {
        $gs1 = ['_ga' => 'GA1.1.1325931386.1795429237', '_ga_XC59W34F3B' => 'GS1.1.1795429237.3.1.1795429300.0.0.0'];
        self::assertSame(['client_id' => '1325931386.1795429237', 'session_id' => '1795429237'], signup_ga_ids($gs1, 'G-XC59W34F3B'));
        $gs2 = ['_ga' => 'GA1.2.1325931386.1795429237', '_ga_XC59W34F3B' => 'GS2.1.s1795429237$o3$g1$t1795429300$j0$l0$h0'];
        self::assertSame(['client_id' => '1325931386.1795429237', 'session_id' => '1795429237'], signup_ga_ids($gs2, 'G-XC59W34F3B'));
    }

    public function testMissingOrOddGaCookiesGiveEmptyIds(): void
    {
        self::assertSame(['client_id' => '', 'session_id' => ''], signup_ga_ids([], 'G-XC59W34F3B'));
        self::assertSame(['client_id' => '', 'session_id' => ''], signup_ga_ids(['_ga' => 'GA1.1.abc.def', '_ga_XC59W34F3B' => 'GS9.1.1795429237'], 'G-XC59W34F3B'));
        $other = ['_ga' => 'GA1.1.1.2', '_ga_OTHER' => 'GS1.1.1795429237.3.1.1795429300.0.0.0'];
        self::assertSame(['client_id' => '1.2', 'session_id' => ''], signup_ga_ids($other, 'G-XC59W34F3B'), "another stream's session is not ours");
    }

    public function testThePayloadCarriesOnlyThePackage(): void
    {
        $p = signup_ga_activation_payload(['statistik' => true, 'marketing' => true], ['client_id' => '1.2', 'session_id' => '1795429237'], 'professionel');
        self::assertSame([
            'client_id' => '1.2',
            'consent' => ['ad_user_data' => 'GRANTED', 'ad_personalization' => 'GRANTED'],
            'events' => [['name' => 'account_activated', 'params' => ['pakke' => 'professionel', 'engagement_time_msec' => 1, 'session_id' => '1795429237']]],
        ], $p);
    }

    public function testWithoutMarketingConsentGoogleAdsMayNotUseIt(): void
    {
        $p = signup_ga_activation_payload(['statistik' => true, 'marketing' => false], ['client_id' => '1.2', 'session_id' => ''], 'finans');
        self::assertSame(['ad_user_data' => 'DENIED', 'ad_personalization' => 'DENIED'], $p['consent']);
        self::assertArrayNotHasKey('session_id', $p['events'][0]['params'], 'no session cookie, no session id');
    }

    public function testNothingIsSentWithoutStatisticsConsentOrGaCookie(): void
    {
        $ids = ['client_id' => '1.2', 'session_id' => '3'];
        self::assertNull(signup_ga_activation_payload(null, $ids, 'business'), 'no choice on the website');
        self::assertNull(signup_ga_activation_payload(['statistik' => false, 'marketing' => true], $ids, 'business'), 'marketing alone is not statistics');
        self::assertNull(signup_ga_activation_payload(['statistik' => true, 'marketing' => true], ['client_id' => '', 'session_id' => ''], 'business'), 'other browser');
    }

    public function testTrackingIsOffUntilConfigured(): void
    {
        // no settings table here and no SALDI_SIGNUP_GA_ID / _GA_API_SECRET in the environment
        $cookies = ['saldi_consent' => '1.' . (time() * 1000) . '.1.1', '_ga' => 'GA1.1.1.2'];
        self::assertFalse(signup_track_activation(['pakke' => 'business'], $cookies));
    }
}
