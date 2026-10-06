<?php
// 20260916 CDX/LH Prove unsafe tenant names cannot reach bootstrap and child failures survive.
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/CharacterizationEnv.php';
final class CharacterizationEnvSafetyTest extends TestCase
{
    public function testProtectedDatabasesAreRejectedBeforeConnecting(): void
    {
        $previous = getenv('SALDI_CHAR_TEST_DB');
        try {
            foreach ([CharacterizationEnv::masterDb(), CharacterizationEnv::templateDb(), 'bad/name'] as $name) {
                putenv('SALDI_CHAR_TEST_DB=' . $name);
                try {
                    CharacterizationEnv::assertSafeDatabases();
                    self::fail('Unsafe database accepted');
                } catch (RuntimeException $expected) {
                    self::assertNotSame('', $expected->getMessage());
                }
            }
        } finally {
            putenv($previous === false ? 'SALDI_CHAR_TEST_DB' : 'SALDI_CHAR_TEST_DB=' . $previous);
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
