<?php
// 20260916 CDX/LH Prove unsafe tenant names cannot reach bootstrap and child failures survive.
// 20261111 CDX/NTR Database ownership is covered through TestDatabase; retain child diagnostics coverage.
// 20261123 CDX/NTR Port the reviewed test fixture repairs to hml/shopify.
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/CharacterizationEnv.php';
final class CharacterizationEnvSafetyTest extends TestCase
{
    public function testLegacyChildRejectsUnownedDatabaseNamesBeforeConnecting(): void
    {
        foreach (['', CharacterizationEnv::masterDb(), 'saldi_chartest', 'bad/name'] as $name) {
            try {
                CharacterizationEnv::assertOwnedTenant($name);
                self::fail('Unowned database accepted');
            } catch (RuntimeException $error) {
                self::assertSame('Legacy test runner requires a generated test tenant', $error->getMessage());
            }
        }
    }

    public function testChildExitCodeAndDiagnosticsArePreserved(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'saldi-child-');
        try {
            file_put_contents($script, '<?php fwrite(STDERR, "expected child failure"); exit(7);');
            $result = CharacterizationEnv::runChild($script);
            self::assertSame(7, $result['exit']);
            self::assertSame('expected child failure', $result['stderr']);
        } finally {
            unlink($script);
        }
    }
}
