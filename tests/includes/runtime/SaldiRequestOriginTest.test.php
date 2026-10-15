<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/runtime/SaldiRequestOriginTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// One request-origin rule for every same-origin check (token endpoint, Opgaver, Assist settings),
// and the mcp/.htaccess that passes the Authorization header is tracked, not gitignored.
//
// 20261002 CL/LH Created.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/SaldiRuntimeTestEnv.php';

final class SaldiRequestOriginTest extends TestCase
{
    public function testRequestOriginHonoursHttpsAndForwardedProto(): void
    {
        self::assertSame('http://saldi.test', saldi_runtime_request_origin(['HTTP_HOST' => 'saldi.test']));
        self::assertSame('https://saldi.test', saldi_runtime_request_origin(['HTTP_HOST' => 'saldi.test', 'HTTPS' => 'on']));
        self::assertSame('http://saldi.test', saldi_runtime_request_origin(['HTTP_HOST' => 'saldi.test', 'HTTPS' => 'off']));
        self::assertSame('https://saldi.test', saldi_runtime_request_origin(['HTTP_HOST' => 'Saldi.Test', 'HTTP_X_FORWARDED_PROTO' => 'https']));
        self::assertSame('https://saldi.test', saldi_runtime_request_origin(['HTTP_HOST' => 'saldi.test', 'HTTP_X_FORWARDED_PROTO' => 'HTTPS, http']));
        self::assertSame('http://saldi.test', saldi_runtime_request_origin(['HTTP_HOST' => 'saldi.test', 'HTTP_X_FORWARDED_PROTO' => 'http']));
        self::assertSame('https://saldi.test:8443', saldi_runtime_request_origin(['HTTP_HOST' => 'saldi.test:8443', 'HTTPS' => 'on']));
        self::assertSame('https://saldi.test', saldi_runtime_request_origin(['HTTP_HOST' => 'saldi.test:443', 'HTTPS' => 'on']));
        self::assertNull(saldi_runtime_request_origin([]));
        self::assertNull(saldi_runtime_origin('null'));
        self::assertNull(saldi_runtime_origin('http://user@evil.test/'));
        self::assertSame('https://saldi.test', saldi_runtime_origin('https://saldi.test/saldi/index/main.php?x=1'));
    }

    public function testMcpHtaccessPassesAuthorizationAndIsNotIgnored(): void
    {
        $root = dirname(__DIR__, 3);
        $htaccess = (string)file_get_contents($root . '/mcp/.htaccess');
        self::assertStringContainsString('CGIPassAuth On', $htaccess);
        self::assertStringContainsString('RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]', $htaccess);
        self::assertMatchesRegularExpression('/<FilesMatch "\^\(\?!index\\\\\.php\$\)\.\+">\s*Require all denied/', $htaccess);
        // The negation must follow every rule that ignores .htaccess files (git: last match wins).
        $lines = array_map('trim', file($root . '/.gitignore'));
        $negation = array_search('!mcp/.htaccess', $lines, true);
        self::assertNotFalse($negation);
        foreach ($lines as $i => $line) {
            if (in_array($line, ['.ht*', '.htaccess', '*.htaccess'], true)) {
                self::assertLessThan($negation, $i, 'mcp/.htaccess is ignored again by line ' . ($i + 1));
            }
        }
    }
}
