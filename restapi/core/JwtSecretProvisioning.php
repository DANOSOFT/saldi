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

if (!function_exists('_jwtProvisionSecret')) {
    /**
     * Best-effort, race-safe creation of the JWT secret file if it does not
     * already exist (SD-634).
     *
     * fopen(..., 'xb') opens with O_CREAT|O_EXCL: it atomically fails if the
     * file already exists, so two requests racing to provision the same file
     * for the first time can never overwrite one another's secret (or one
     * install.php wrote moments earlier) - the loser just returns here and
     * falls through to _jwtLoadSecret()'s read of the file the winner wrote.
     */
    function _jwtProvisionSecret(string $path): void
    {
        $fp = @fopen($path, 'xb');
        if ($fp === false) {
            return; // already exists, or the directory isn't writable - is_readable() below sorts out which
        }
        $written = @fwrite($fp, random_bytes(32));
        fclose($fp);
        if ($written !== 32) {
            @unlink($path); // don't leave a short/corrupt secret file behind for the next request to trip over
        }
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
                . 'create it manually with: php -r "file_put_contents(\'' . $path . '\', random_bytes(32));"'
            );
        }
        $secret = file_get_contents($path);
        if ($secret === false || strlen($secret) < 32) {
            throw new \RuntimeException('JWT secret file must contain at least 32 bytes (256 bits): ' . $path);
        }
        return $secret;
    }
}
