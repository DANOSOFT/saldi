<?php
// tests/restapi/JwtSecretProvisioningTest.php
//
// Unit tests for restapi/core/JwtSecretProvisioning.php (SD-634). Pure - no
// DB, no HTTP, no docker stack - and never touches the real install's
// restapi/.ht_jwt_secret.bin: _jwtLoadSecret() takes an explicit $path
// override for exactly this reason.
//
// History:
// 20260814 CL/SZ SD-634: created.

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/restapi/core/JWT.php';
require_once dirname(__DIR__, 2) . '/restapi/core/JwtSecretProvisioning.php';

final class JwtSecretProvisioningTest extends TestCase
{
    /** @var list<string> */
    private array $cleanupPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanupPaths as $path) {
            if (is_dir($path)) {
                chmod($path, 0700); // undo any permission-denial test so rmdir can actually clean up
                @rmdir($path);
            } elseif (file_exists($path)) {
                @unlink($path);
            }
        }
        $this->cleanupPaths = [];
    }

    private function tempSecretPath(): string
    {
        $path = sys_get_temp_dir() . '/jwt_secret_test_' . bin2hex(random_bytes(8)) . '.bin';
        $this->cleanupPaths[] = $path;
        return $path;
    }

    public function test_load_secret_self_heals_by_creating_a_missing_file(): void
    {
        $path = $this->tempSecretPath();
        $this->assertFileDoesNotExist($path);

        $secret = _jwtLoadSecret($path);

        $this->assertFileExists($path, 'a missing secret file is created on first load, not just failed on');
        $this->assertGreaterThanOrEqual(32, strlen($secret));
        $this->assertSame(file_get_contents($path), $secret);
    }

    public function test_load_secret_never_overwrites_an_existing_file(): void
    {
        $path = $this->tempSecretPath();
        $first = _jwtLoadSecret($path);
        $second = _jwtLoadSecret($path);

        $this->assertSame($first, $second, 'a second load must return the same secret, not regenerate one');
    }

    public function test_provision_is_a_noop_when_the_file_already_exists(): void
    {
        $path = $this->tempSecretPath();
        file_put_contents($path, 'pre-existing-secret-content-000000');

        _jwtProvisionSecret($path);

        $this->assertSame(
            'pre-existing-secret-content-000000',
            file_get_contents($path),
            '_jwtProvisionSecret() must never overwrite a file that is already there - e.g. one install.php just wrote, or a concurrent request racing to provision the same file'
        );
    }

    public function test_load_secret_throws_an_actionable_error_when_the_directory_is_unwritable(): void
    {
        $dir = sys_get_temp_dir() . '/jwt_secret_test_dir_' . bin2hex(random_bytes(8));
        mkdir($dir, 0500); // read+execute only, no write - _jwtProvisionSecret()'s fopen('xb') must fail here
        $this->cleanupPaths[] = $dir;
        $path = $dir . '/.ht_jwt_secret.bin';

        try {
            _jwtLoadSecret($path);
            $this->fail('expected a RuntimeException when the secret directory cannot be written to');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($path, $e->getMessage(), 'the error must name the exact path, per SD-634 acceptance criterion 3');
        }
    }

    public function test_load_secret_rejects_an_existing_file_shorter_than_32_bytes(): void
    {
        $path = $this->tempSecretPath();
        file_put_contents($path, 'too-short');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/32 bytes/');
        _jwtLoadSecret($path);
    }
}
