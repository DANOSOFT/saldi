<?php
// 20260916 CDX/LH Prove unsafe tenant names cannot reach bootstrap and child failures survive.
// 20261111 CDX/NTR Database ownership is covered through TestDatabase; retain child diagnostics coverage.
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/CharacterizationEnv.php';
final class CharacterizationEnvSafetyTest extends TestCase
{
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
