<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/auditLog.php';

/**
 * audit_log_details_json() decides what lands in audit_log.detaljer, which is kept for five years
 * and must never contain passwords, API keys or 2FA codes.
 */
final class auditLog extends TestCase
{
    public function testBeforeAfterIsStoredAsJson(): void
    {
        $json = audit_log_details_json(['before' => ['status' => 'aktiv'], 'after' => ['status' => 'arkiveret']]);
        $this->assertSame('{"before":{"status":"aktiv"},"after":{"status":"arkiveret"}}', $json);
    }

    public function testDanishLettersAreNotEscaped(): void
    {
        $this->assertSame('{"after":{"navn":"Havemøbelland ÆØÅ"}}', audit_log_details_json(['after' => ['navn' => 'Havemøbelland ÆØÅ']]));
    }

    public function testFreeTextIsStoredAsItIs(): void
    {
        $this->assertSame('Fejl i udtræk: beløb mangler', audit_log_details_json('Fejl i udtræk: beløb mangler'));
    }

    public function testNoDetailsGivesNull(): void
    {
        $this->assertNull(audit_log_details_json(null));
        $this->assertNull(audit_log_details_json(''));
        $this->assertNull(audit_log_details_json([]));
    }

    public function testSecretsAreMaskedAtAnyDepth(): void
    {
        $json = audit_log_details_json([
            'before' => ['kode' => 'old', 'email' => 'a@example.com'],
            'after' => ['password' => 'new', 'api_key' => 'k1', 'smtp' => ['smtp_api_key' => 'k2', 'client_secret' => 's', 'access_token' => 't']],
        ]);
        $this->assertStringNotContainsString('old', $json);
        $this->assertStringNotContainsString('"new"', $json);
        $this->assertStringNotContainsString('k1', $json);
        $this->assertStringNotContainsString('k2', $json);
        $this->assertStringNotContainsString('"s"', $json);
        $this->assertStringNotContainsString('"t"', $json);
        $this->assertStringContainsString('a@example.com', $json);
    }

    public function testOrdinaryKeysThatContainSecretWordsAreKept(): void
    {
        $json = audit_log_details_json(['after' => ['postkode' => '8000', 'bankkonto' => '1234']]);
        $this->assertSame('{"after":{"postkode":"8000","bankkonto":"1234"}}', $json);
    }
}
