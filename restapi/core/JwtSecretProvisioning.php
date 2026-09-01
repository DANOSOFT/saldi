<?php
// restapi/core/JwtSecretProvisioning.php
//
// Loads (and, since SD-634, lazily creates) the install-specific JWT signing
// secret used by restapi/core/BaseEndpoint.php's bootstrap. Split out of
// BaseEndpoint.php so it can be unit-tested without triggering that file's
// top-level try/catch/exit bootstrap - see tests/restapi/JwtSecretProvisioningTest.php.
//
// History:
// 20260814 CL/SZ SD-634: created (extracted _jwtLoadSecret() out of
//                BaseEndpoint.php) and added _jwtProvisionSecret() so an
//                upgraded install self-heals instead of staying permanently
//                dead - index/install.php:351 was the only code path that
//                created restapi/.ht_jwt_secret.bin, and it only runs on a
//                fresh install; the file is git-ignored (.gitignore's .ht*
//                rule), so a git pull/deploy never ships it.
// 20260814 CL/SZ SD-634 (CodeRabbit): publish via a same-directory 0600 temp
//                file + link(), not a direct fopen('xb') on the final path -
//                that exposed the path before fwrite() completed (a
//                concurrent _jwtLoadSecret() could read a partial file and
//                500) and left the mode to umask (0644 under a common 0022
//                umask, letting any local user read the secret and forge
//                tokens for every tenant).

if (!function_exists('_jwtProvisionSecret')) {
    /**
     * Best-effort, race-safe creation of the JWT secret file if it does not
     * already exist (SD-634).
     *
     * Written to a same-directory temp file first (0600, so the mode never
     * depends on umask), then published with link() rather than rename():
     * link() atomically fails with EEXIST if $path already exists, so a
     * concurrent request racing to provision the same file - or one
     * install.php just wrote - can never be overwritten, unlike rename()
     * which would silently replace it. Only a complete, correctly-permissioned
     * file is ever visible at $path; a reader never sees a partial write.
     */
    function _jwtProvisionSecret(string $path): void
    {
        $dir = dirname($path);
        $tmp = $dir . '/.ht_jwt_secret_' . bin2hex(random_bytes(8)) . '.tmp';

        $fp = @fopen($tmp, 'xb');
        if ($fp === false) {
            return; // directory not writable - is_readable() on $path below sorts out the real failure
        }
        $written = @fwrite($fp, random_bytes(32));
        fclose($fp);
        if ($written !== 32 || !@chmod($tmp, 0600)) {
            @unlink($tmp);
            return;
        }

        @link($tmp, $path); // no-op if $path already exists (lost the race) - EEXIST is not an error here
        @unlink($tmp); // always scratch, whether or not the link() above succeeded
    }
}

if (!function_exists('_jwtLoadSecret')) {
    /**
     * @param ?string $path Overrides JWT::secretPath() - for tests only, so
     *                      they never touch the real install's secret file.
     */
    function _jwtLoadSecret(?string $path = null): string
    {
        $path = $path ?? JWT::secretPath();
        if (!file_exists($path)) {
            _jwtProvisionSecret($path); // SD-634: self-heal on upgraded installs
        }
        if (!is_readable($path)) {
            throw new \RuntimeException(
                'JWT secret file not found or unreadable: ' . $path . '. The web server user must be '
                . 'able to create and read files in ' . dirname($path) . ' - fix permissions there, or '
                . 'create it manually (owner-only, and only if it does not already exist) with: '
                . 'test -f \'' . $path . '\' || (umask 077 && php -r "file_put_contents(\'' . $path . '\', random_bytes(32));")'
            );
        }
        $secret = file_get_contents($path);
        if ($secret === false || strlen($secret) < 32) {
            throw new \RuntimeException('JWT secret file must contain at least 32 bytes (256 bits): ' . $path);
        }
        return $secret;
    }
}
