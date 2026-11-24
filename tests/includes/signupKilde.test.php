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
 * Where a signup came from (doc/signup.md, "Kilde"): what signup_kilde() keeps of the website's utm_*, Google Ads
 * click ids, referrer and landing page, and the line signup_kilde_text() writes in the new-customer mail.
 */
final class signupKilde extends TestCase
{
    public function testAGoogleAdsVisitIsKeptAsSent(): void
    {
        $in = [
            'name' => 'Mette Hansen', 'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'Regnskab DK – vinter',
            'utm_term' => 'regnskabsprogram', 'utm_content' => 'annonce-a', 'gclid' => 'EAIaIQobChMI8_x-Yz3a.B~',
            'referrer' => 'www.Google.com', 'landing' => '/priser/',
        ];
        $kilde = signup_kilde($in);
        self::assertSame([
            'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'Regnskab DK – vinter', 'utm_term' => 'regnskabsprogram',
            'utm_content' => 'annonce-a', 'gclid' => 'EAIaIQobChMI8_x-Yz3a.B~', 'gbraid' => '', 'wbraid' => '',
            'referrer' => 'www.google.com', 'landing' => '/priser/',
        ], $kilde);
        self::assertSame(array_keys(signup_kilde_columns()), array_keys($kilde), 'one value per column, nothing else');
    }

    public function testNothingSentIsNothingKept(): void
    {
        self::assertSame(array_fill_keys(array_keys(signup_kilde_columns()), ''), signup_kilde(['name' => 'Mette']));
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function refusedValues(): array
    {
        return [
            'a click id with a space' => ['gclid', 'abc def', ''],
            'a click id with markup' => ['wbraid', '<script>', ''],
            'a click id that is too long' => ['gclid', str_repeat('a', 201), ''],
            'a click id at the limit' => ['gbraid', str_repeat('a', 200), str_repeat('a', 200)],
            'a referrer with a path' => ['referrer', 'chatgpt.com/c/123', ''],
            'a referrer with a scheme' => ['referrer', 'https://chatgpt.com', ''],
            'a referrer without a dot' => ['referrer', 'localhost', ''],
            'a landing page with a query' => ['landing', '/prov/?gclid=abc', ''],
            'a landing page that is a full address' => ['landing', 'https://saldi.dk/prov/', ''],
            'a landing page with markup' => ['landing', '/"><img>', ''],
            'markup in a campaign name' => ['utm_campaign', 'vinter<script>"x"', 'vinterscriptx'],
            'line breaks in a campaign name' => ['utm_source', "news\r\nletter", 'news letter'],
            'a campaign name that is too long' => ['utm_campaign', str_repeat('k', 151), ''],
            'not a string' => ['utm_source', ['google'], ''],
        ];
    }

    #[DataProvider('refusedValues')]
    public function testValuesOfTheWrongFormAreDropped(string $column, mixed $value, string $expected): void
    {
        self::assertSame($expected, signup_kilde([$column => $value])[$column]);
    }

    public function testTheMailLineNamesTheCampaign(): void
    {
        self::assertSame('google / cpc / regnskab-dk', signup_kilde_text(['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'regnskab-dk']));
        self::assertSame('linkedin', signup_kilde_text(['utm_source' => 'linkedin', 'referrer' => 'www.linkedin.com']));
    }

    public function testTheMailLineFallsBackToTheReferrer(): void
    {
        self::assertSame('chatgpt.com', signup_kilde_text(['utm_source' => '', 'referrer' => 'chatgpt.com']));
    }

    public function testTheMailLineSaysWhenAGoogleAdsClickCameAlong(): void
    {
        self::assertSame('google / cpc, Google Ads-klik', signup_kilde_text(['utm_source' => 'google', 'utm_medium' => 'cpc', 'gclid' => 'EAIa']));
        self::assertSame('Google Ads-klik', signup_kilde_text(['gbraid' => '0AAAAA']));
    }

    public function testTheMailLineIsEmptyWithoutASource(): void
    {
        self::assertSame('', signup_kilde_text(['source' => 'prov']));
    }
}
