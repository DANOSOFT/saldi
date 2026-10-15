<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/runtime/SaldiRuntimeUnitTest.test.php --- ai/mcp-assistant --- 2026.10.10
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
//
// Unit coverage for data-only config, identity, links, translations and CSRF.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Cover F1 integration behavior and regression cases.
// 20261010 CDX/LH Cover the grant revocation error while its translation is pending.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/SaldiRuntimeTestEnv.php';
require_once __DIR__ . '/../../../includes/saldi_assist_context_token.php';
require_once __DIR__ . '/../../../includes/automation/inboxCsrf.php';

final class SaldiRuntimeUnitTest extends TestCase
{
    public function testConfigParsesTextWithoutExecutingIt(): void
    {
        $secret = bin2hex(random_bytes(12));
        $text = '$db_type = "postgresql"; $sqhost = "localhost:5544"; $squser = "reader"; $sqpass = "' . $secret . '"; $sqdb = "master"; $sqdb = "ignored"; throw new Exception();';
        $cfg = saldi_runtime_parse_config($text);
        self::assertSame('localhost', $cfg['host']);
        self::assertSame(5544, $cfg['port']);
        self::assertSame('master', $cfg['master_db']);
        self::assertSame($secret, $cfg['password']);
        $this->expectException(SaldiRuntimeError::class);
        saldi_runtime_parse_config('$sqhost="x";');
    }

    public function testConfigUsesCompleteEnvironmentAndReset(): void
    {
        $env = ['TYPE' => 'postgresql', 'HOST' => 'host', 'USER' => 'reader', 'PASSWORD' => bin2hex(random_bytes(16)), 'MASTER' => 'master', 'PORT' => '5545'];
        $old = [];
        try {
            foreach ($env as $k => $v) {
                $key = 'SALDI_RUNTIME_DB_' . $k;
                $old[$key] = getenv($key);
                putenv($key . '=' . $v);
            }
            saldi_runtime_reset();
            $cfg = saldi_runtime_config();
            self::assertSame('host', $cfg['host']);
            self::assertSame(5545, $cfg['port']);
            self::assertSame($env['PASSWORD'], $cfg['password']);
            self::assertSame('5.0.0', $cfg['version']);
            putenv('SALDI_RUNTIME_DB_HOST=changed');
            self::assertSame('host', saldi_runtime_config()['host']);
            saldi_runtime_reset();
            self::assertSame('changed', saldi_runtime_config()['host']);
        } finally {
            foreach ($old as $key => $value) {
                putenv($value === false ? $key : $key . '=' . $value);
            }
            saldi_runtime_reset();
        }
    }

    public function testInstallValidationAndRawIdentityMatchClaims(): void
    {
        $ctx = new SaldiTenantContext(saldi_runtime_install_id(), 5, 'test', 'postgresql', 7, "O'Brien", '1', 1, 1, 'user:7');
        $claims = saldi_assist_context_claims(['db' => 'test', 'brugernavn' => "O'Brien"], [], bin2hex(random_bytes(16)));
        self::assertSame($ctx->tenantHash(), $claims['tenant_hash']);
        self::assertSame($ctx->userHash(), $claims['user_hash']);
        self::assertSame($ctx->installId, $claims['install']);
        self::assertTrue($ctx->isHuman());
        self::assertNull($ctx->onBehalfOf());
        $old = getenv('SALDI_INSTALL_ID');
        try {
            putenv('SALDI_INSTALL_ID=Bad.id');
            $this->expectException(SaldiRuntimeError::class);
            saldi_runtime_install_id();
        } finally {
            putenv($old === false ? 'SALDI_INSTALL_ID' : 'SALDI_INSTALL_ID=' . $old);
        }
    }

    public function testScreenAllowlistRejectsActionsAndExternalPaths(): void
    {
        foreach (SaldiScreens::SCREENS as $type => [$path, $param]) {
            $link = SaldiScreens::link($type, 12);
            self::assertTrue(SaldiScreens::isAllowed($link));
            self::assertSame($param === null ? $path : $path . '?' . $param . '=12', $link);
            foreach (['&delete=1', '#x', '?action=post', '%26id=1'] as $suffix) {
                self::assertFalse(SaldiScreens::isAllowed($link . $suffix));
            }
        }
        foreach (['https://example.org/finans/kladdeliste.php', '../finans/kladdeliste.php', '/finans/kladdeliste.php', 'debitor/ordre.php?id=-1', 'debitor/ordre.php?id=12345678901', 'finans/kassekladde.php?id=1'] as $bad) {
            self::assertFalse(SaldiScreens::isAllowed($bad));
        }
        $this->expectException(InvalidArgumentException::class);
        SaldiScreens::link('unknown', 1);
    }

    public function testErrorCatalogueTranslationsAndCsrf(): void
    {
        foreach (SaldiRuntimeError::CODES as $code => [$class, $status]) {
            $e = new SaldiRuntimeError($code, 'Fallback');
            self::assertSame($class, $e->errorClass());
            self::assertSame($status, $e->httpStatus());
            self::assertNotSame($code === 'GRANT_REVOKED' ? '' : 'Fallback', $e->messageDa());
        }
        self::assertSame('Date', SaldiText::get(null, 5741, 2, 'fallback'));
        self::assertSame('Dato', SaldiText::get(null, 5741, 99, 'fallback'));
        self::assertSame('fallback', SaldiText::get(null, 99999999, 1, 'fallback'));
        $_SESSION = [];
        self::assertFalse(saldi_inbox_csrf_check(''));
        self::assertFalse(saldi_inbox_csrf_check(null));
        $token = saldi_inbox_csrf_token();
        self::assertSame($token, saldi_inbox_csrf_token());
        self::assertTrue(saldi_inbox_csrf_check($token));
        self::assertFalse(saldi_inbox_csrf_check(bin2hex(random_bytes(32))));
    }

    public function testMalformedTokenIdsAreRejectedBeforeDatabaseQueries(): void
    {
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        foreach (['0.1', '1.0', '01.1', '9999999999.1', '1.9999999999'] as $ids) {
            self::assertNull(SaldiGrantService::parse('smcp1.local.' . $ids . '.' . $secret));
        }
    }

    public function testCanonicalJsonAndBadDatabaseName(): void
    {
        self::assertSame(saldi_runtime_json(['b' => ['z' => 1, 'a' => 2], 'a' => 0]), saldi_runtime_json(['a' => 0, 'b' => ['a' => 2, 'z' => 1]]));
        $left = (object)['z' => [(object)['z' => 1, 'a' => new stdClass()]], 'a' => (object)['1' => 'one', '0' => 'zero']];
        $right = (object)['a' => (object)['0' => 'zero', '1' => 'one'], 'z' => [(object)['a' => new stdClass(), 'z' => 1]]];
        self::assertSame(saldi_runtime_json($left), saldi_runtime_json($right));
        self::assertSame('{"a":{"0":"zero","1":"one"},"z":[{"a":{},"z":1}]}', saldi_runtime_json($left));
        self::assertSame('[]', saldi_runtime_json([]));
        $this->expectException(InvalidArgumentException::class);
        SaldiDb::connect([], 'bad;database');
    }
}
