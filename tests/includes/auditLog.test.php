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

    public function testSignatureIsTheRolesBranchOne(): void
    {
        // The roles stage 2 branch defines the same function behind the same guard; whichever loads first is used
        $f = new ReflectionFunction('audit_log_write');
        $this->assertSame('void', (string)$f->getReturnType());
        $params = array_map(function ($p) { return $p->getName() . ':' . $p->getType() . ($p->isOptional() ? '=' . var_export($p->getDefaultValue(), true) : ''); }, $f->getParameters());
        $this->assertSame(array('handling:string', "objektType:string=''", "objektId:string=''", "detaljer:string=''", "kilde:string='ui'"), $params);
    }

    public function testNoDetailsGivesEmptyText(): void
    {
        // audit_log_write() takes detaljer as a string, so "no details" is ''
        $this->assertSame('', audit_log_details_json(null));
        $this->assertSame('', audit_log_details_json(''));
        $this->assertSame('', audit_log_details_json([]));
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
