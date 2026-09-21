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
// 20260814 CL/SZ SD-634 (CodeRabbit): added mode/no-leftover-temp-file
//                coverage for the atomic temp-file+link() publish; replaced
//                the 0500-directory permission-denial test (root bypasses
//                directory permission bits, so that test would silently pass
//                for the wrong reason - and leave a child file behind - under
//                a root-run suite) with a not-a-directory target, which even
//                root cannot write through.

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
                chmod($path, 0700); // undo any permission-denial test so we can actually see/remove its contents
                foreach (scandir($path) ?: [] as $child) {
                    if ($child !== '.' && $child !== '..') {
                        @unlink($path . '/' . $child);
                    }
                }
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

    /** A fresh directory to inspect for leftover temp files after provisioning. */
    private function tempSecretDir(): string
    {
        $dir = sys_get_temp_dir() . '/jwt_secret_test_dir_' . bin2hex(random_bytes(8));
        mkdir($dir, 0700);
        $this->cleanupPaths[] = $dir;
        return $dir;
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

    public function test_load_secret_throws_an_actionable_error_when_the_parent_path_is_not_writable(): void
    {
        // A regular file can't have children under ANY privilege level - not
        // even root can fopen() a path through a non-directory - unlike a
        // 0500 directory, whose permission bits a root-run test suite would
        // bypass, silently succeeding for the wrong reason and leaving a
        // child secret file behind for tearDown() to miss.
        $notADir = $this->tempSecretPath();
        file_put_contents($notADir, 'i am a file, not a directory');
        $path = $notADir . '/.ht_jwt_secret.bin';

        try {
            _jwtLoadSecret($path);
            $this->fail('expected a RuntimeException when the parent path is not a directory');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString($path, $e->getMessage(), 'the error must name the exact path, per SD-634 acceptance criterion 3');
        }
    }

    public function test_provisioned_secret_is_owner_only_regardless_of_umask(): void
    {
        $path = $this->tempSecretPath();
        $originalUmask = umask(0022); // the common permissive default - the created file must not inherit it
        try {
            _jwtLoadSecret($path);
        } finally {
            umask($originalUmask);
        }

        $this->assertSame('0600', substr(sprintf('%o', fileperms($path)), -4), 'the secret file must be owner-only regardless of the process umask');
    }

    public function test_provisioning_leaves_no_temp_file_behind_on_success(): void
    {
        $dir = $this->tempSecretDir();
        $path = $dir . '/.ht_jwt_secret.bin';

        _jwtLoadSecret($path);

        $remaining = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));
        $this->assertSame(['.ht_jwt_secret.bin'], $remaining, 'no .tmp scratch file should survive a successful publish');
    }

    public function test_provisioning_leaves_no_temp_file_behind_when_it_loses_the_race(): void
    {
        $dir = $this->tempSecretDir();
        $path = $dir . '/.ht_jwt_secret.bin';
        file_put_contents($path, 'winner-of-the-race-000000000000');

        // Simulates a second, concurrent first-request: it finds the file
        // already there (another process/request won the race) and must not
        // disturb it or leave its own scratch file behind.
        _jwtProvisionSecret($path);

        $this->assertSame('winner-of-the-race-000000000000', file_get_contents($path));
        $remaining = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));
        $this->assertSame(['.ht_jwt_secret.bin'], $remaining, 'the loser of the race must clean up its own temp file');
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
