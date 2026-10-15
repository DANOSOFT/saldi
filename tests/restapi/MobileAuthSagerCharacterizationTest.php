<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileAuthSagerCharacterizationTest.php --- ver 5.0.0 --- 2026.10.02
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.
// 20261001 CDX/LH Characterize password-reset/login interleavings on both production Sager handlers.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../restapi/core/MobileAuthRevocation.php';
require_once __DIR__ . '/../../restapi/core/MobileAuthChallenges.php';

/** Characterize the actual Sager handlers without executing their page rendering. */
final class MobileAuthSagerCharacterizationTest extends TestCase
{
    private static $tenant;
    private static $master;
    private static int $tenantId;
    private static array $user;
    private static ?string $originalUri;
    private static ?string $originalIp;

    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        self::$originalIp = ifset($_SERVER, 'REMOTE_ADDR');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        self::$originalUri = ifset($_SERVER, 'REQUEST_URI');
        $_SERVER['REQUEST_URI'] = '/saldi/sager/ansatte.php';
        RestApiEnv::bootstrapTenant();
        global $sqhost, $squser, $sqpass, $sqdb, $db_type, $db_encode, $connection;
        require_once __DIR__ . '/../../includes/db_query.php';
        include __DIR__ . '/../../includes/connect.php';
        self::$tenant = RestApiEnv::connect(RestApiEnv::testDb());
        self::$master = RestApiEnv::connect(RestApiEnv::masterDb());
        self::$tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        mobileTenantUpdates(self::$tenant);
        self::$user = RestApiEnv::rows(self::$tenant, 'SELECT * FROM brugere WHERE brugernavn=$1', [RestApiEnv::user()])[0];
        new MobileAuth();
        JWT::setSecret(_jwtLoadSecret());
        JWT::setClaimEnricher(null);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$master) {
            foreach (['refresh_sessions', 'auth_challenges', 'auth_versions'] as $table) {
                RestApiEnv::rows(self::$master, "DELETE FROM $table WHERE tenant_id=$1", [self::$tenantId]);
            }
            pg_close(self::$tenant);
            pg_close(self::$master);
        }
        RestApiEnv::teardownTenant();
        if (self::$originalIp === null) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = self::$originalIp;
        }
        if (self::$originalUri === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = self::$originalUri;
        }
    }

    /**
     * Extract the trusted production function, substituting only its DB write boundary.
     * @return Closure
     */
    private function handler(string $file, string $name, Closure $write): Closure
    {
        $tokens = token_get_all(file_get_contents(__DIR__ . '/../../' . $file));
        for ($i = 0; $i < count($tokens); $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }
            $j = $i + 1;
            while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if (!is_array($tokens[$j]) || $tokens[$j][1] !== $name) {
                continue;
            }
            $source = '';
            $depth = 0;
            $opened = false;
            do {
                $token = $tokens[$i++];
                $source .= is_array($token) ? $token[1] : $token;
                if ($token === '{') {
                    $opened = true;
                    $depth++;
                } elseif ($token === '}') {
                    $depth--;
                }
            } while (!$opened || $depth > 0);
            $source = str_replace('function ' . $name, 'function', $source);
            $source = preg_replace('/\)\s*:\s*(void|bool)\s*\{/', ') use ($write): $1 {', $source, 1);
            $source = str_replace('db_modify(', '$write(', $source);
            return eval('return ' . $source . ';');
        }
        throw new RuntimeException('Sager handler missing');
    }

    public function test_login_interleaved_at_employee_password_write_cannot_survive_reset(): void
    {
        $this->interleavedReset(false);
    }

    public function test_login_interleaved_at_customer_credential_write_cannot_survive_reset(): void
    {
        $this->interleavedReset(true);
    }

    /** @return void Run login's epoch/password reads just before the reset's tenant write. */
    private function interleavedReset(bool $customer): void
    {
        global $connection;
        $connection = self::$tenant;
        $password = TestCredentials::password('sager_before_reset');
        RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET kode=$1 WHERE id=$2', [md5($password), self::$user['id']]);
        $employee = (int)RestApiEnv::rows(self::$tenant, "INSERT INTO ansatte(navn,konto_id) VALUES ('Auth contact',42) RETURNING id")[0]['id'];
        RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET ansat_id=$1 WHERE id=$2', [$employee, self::$user['id']]);
        $auth = new MobileAuth();
        $subject = Subject::build('account', (int)self::$user['id']);
        $pair = $auth->create(self::$user, 'account', self::$tenantId, 'sager-before');
        $challengeService = new class($auth) extends MobileAuthChallenges {
            protected function deliver(string $channel, string $target, string $code, int $tenantId): void {}
        };
        $user = self::$user;
        $user['email'] = 'sager@example.invalid';
        $user['tlf'] = '';
        $challenge = $challengeService->create($user, self::$tenantId, 'sager-login', $auth->version($subject, self::$tenantId));
        $version = null;
        $observedUser = null;
        $write = function (string $sql, string $location) use ($auth, $subject, $password, &$version, &$observedUser): void {
            // Deterministic concurrent-login schedule: pause reset at its write, verify old
            // credentials, finish reset, then let that login attempt issue its session.
            $version = $auth->version($subject, self::$tenantId);
            $observedUser = RestApiEnv::rows(self::$tenant, 'SELECT * FROM brugere WHERE id=$1', [self::$user['id']])[0];
            $this->assertSame(md5($password), $observedUser['kode']);
            db_modify($sql, $location);
        };
        $replacement = TestCredentials::password('sager_after_reset');
        try {
            if ($customer) {
                $handler = $this->handler('sager/kunder.php', 'sagerSaveCustomerLogin', $write);
                $this->assertTrue($handler($employee, 42, 'changed_contact', $replacement));
            } else {
                ($this->handler('sager/ansatte.php', 'sagerSaveEmployeePassword', $write))((int)self::$user['id'], md5($replacement));
            }
            $this->assertSame(md5($replacement), RestApiEnv::rows(self::$tenant, 'SELECT kode FROM brugere WHERE id=$1', [self::$user['id']])[0]['kode']);
            $this->assertSame($version + 1, $auth->version($subject, self::$tenantId));
            try {
                $auth->create($observedUser, 'account', self::$tenantId, 'sager-login', $version);
                $this->fail('Old password verification survived reset');
            } catch (MobileAuthError $e) {
                $this->assertSame('session_revoked', $e->error);
            }
            try {
                MobileAuth::checkSession(JWT::decode($pair['access_token']));
                $this->fail('Existing session survived reset');
            } catch (MobileAuthError $e) {
                $this->assertSame('session_revoked', $e->error);
            }
            $this->assertNotNull(RestApiEnv::rows(self::$master, 'SELECT consumed_at FROM auth_challenges WHERE id=$1', [$challenge['challenge_id']])[0]['consumed_at']);
            $newUser = RestApiEnv::rows(self::$tenant, 'SELECT * FROM brugere WHERE id=$1', [self::$user['id']])[0];
            $fresh = $auth->create($newUser, 'account', self::$tenantId, 'sager-new', $auth->version($subject, self::$tenantId));
            $this->assertNotEmpty(MobileAuth::checkSession(JWT::decode($fresh['access_token'])));
        } finally {
            RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET ansat_id=NULL,kode=$1,brugernavn=$2 WHERE id=$3', [md5(RestApiEnv::password()), RestApiEnv::user(), self::$user['id']]);
            RestApiEnv::rows(self::$tenant, 'DELETE FROM ansatte WHERE id=$1', [$employee]);
        }
    }
}
