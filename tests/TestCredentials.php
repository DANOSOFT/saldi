<?php
// tests/TestCredentials.php
//
// Passwords for accounts the test suites seed themselves. Tracked in git on
// purpose: it contains no secret, because every password is generated at
// random once per PHP process (memoised per key) and only ever lives in the
// throwaway data a suite creates and tears down again. A run that dies
// half-way therefore leaves behind a password nobody knows.
//
// To log in by hand while a suite keeps its data (e.g. SALDI_REST_KEEP_TENANT=1
// for tests/restapi), set SALDI_TEST_PASSWORD_<KEY> in the environment - e.g.
// SALDI_TEST_PASSWORD_RESTAPI - and that value is used instead. Never write
// such a value into a tracked file (doc/ai/convention_no_hardcoded_secrets.md).
//
// Real third-party credentials (API keys of external services) do not belong
// here; read those from environment variables and skip when unset.
//
// History:
// 20260904 CL/NTR created.

final class TestCredentials
{
    /** @var array<string, string> Generated passwords, one per key, for this process. */
    private static array $passwords = [];

    /**
     * Password for the account a suite seeds under $key (e.g. 'restapi').
     * Random per process unless SALDI_TEST_PASSWORD_<KEY> overrides it.
     */
    public static function password(string $key): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            throw new InvalidArgumentException("credential key must be lower-case [a-z0-9_]: $key");
        }
        if (!isset(self::$passwords[$key])) {
            $override = getenv('SALDI_TEST_PASSWORD_' . strtoupper($key));
            self::$passwords[$key] = is_string($override) && $override !== '' ? $override : bin2hex(random_bytes(16));
        }
        return self::$passwords[$key];
    }
}
