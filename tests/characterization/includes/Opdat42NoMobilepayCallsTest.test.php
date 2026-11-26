<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261120 CL/MMR MB-85 The 4.2 upgrade steps make no MobilePay/Vipps calls; the 5.1.0 step is the only place that reconciles webhooks.

final class Opdat42NoMobilepayCallsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    private static function source(string $file): string
    {
        return str_replace("\r\n", "\n", (string)file_get_contents(self::ROOT . '/' . $file));
    }

    /** Only the code, so the history lines in the header may still name what was removed. */
    private static function code(string $file): string
    {
        $tokens = token_get_all(self::source($file));
        $code = '';
        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }
        return $code;
    }

    /** @return iterable<string, array{string}> */
    public static function forbiddenInTheFourTwoSteps(): iterable
    {
        yield 'an HTTP call' => ['curl_'];
        yield 'the Vipps API' => ['api.vipps.no'];
        yield 'a webhook' => ['webhook'];
        yield 'the request host' => ['SERVER_NAME'];
        yield 'the MobilePay settings' => ["var_grp = 'mobilepay'"];
    }

    #[DataProvider('forbiddenInTheFourTwoSteps')]
    public function testTheFourTwoStepsDoNotTouchMobilepay(string $needle): void
    {
        self::assertStringNotContainsStringIgnoringCase($needle, self::code('includes/opdat_4.2.php'));
    }

    public function testTheFiveOneStepStillReconcilesAgainstTheConfiguredBaseUrl(): void
    {
        $code = self::code('includes/opdat_5.0.php');
        self::assertStringContainsString("var_name = 'webhook_base_url'", $code);
        self::assertStringContainsString("include_once(__DIR__ . '/stdFunc/mobilepayWebhookSync.php');", $code);
        self::assertSame(1, substr_count($code, 'mobilepay_webhook_sync('));
        self::assertStringNotContainsString('SERVER_NAME', $code);
    }

    public function testTheSyncDeletesOnlyWebhooksForExactlyThisDb(): void
    {
        $code = self::code('includes/stdFunc/mobilepayWebhookSync.php');
        self::assertStringContainsString("\$webhookArgs['db'] !== \$db", $code);
        self::assertStringNotContainsString('strpos(', $code);
    }
}
