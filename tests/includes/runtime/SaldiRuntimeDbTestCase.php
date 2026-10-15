<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/runtime/SaldiRuntimeDbTestCase.php --- ai/mcp-assistant --- 2026.10.01
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
// Shared disposable-clone base for runtime and automation tests.
//
// 20261001 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiRuntimeTestEnv.php';

/** Disposable-clone tests fail normally for every error except an unset credential. */
abstract class SaldiRuntimeDbTestCase extends PHPUnit\Framework\TestCase
{
    protected SaldiRuntimeTestEnv $env;

    protected function setUp(): void
    {
        if (getenv('SALDI_CHAR_PGPASS') === false) {
            $this->markTestSkipped('SALDI_CHAR_PGPASS is unset');
        }
        $this->env = new SaldiRuntimeTestEnv();
    }

    protected function tearDown(): void
    {
        if (isset($this->env)) {
            $this->env->close();
        }
        SaldiPermissionService::setRoutineLookup(null);
    }

    protected function error(string $code, callable $fn): SaldiRuntimeError
    {
        try {
            $fn();
        } catch (SaldiRuntimeError $e) {
            self::assertSame($code, $e->errorCode);
            return $e;
        }
        self::fail('Expected runtime error ' . $code);
    }

    protected function inbox(): SaldiInboxService
    {
        return new SaldiInboxService($this->env->db, SaldiKindRegistry::default());
    }

    protected function approve(array $item, ?SaldiTenantContext $ctx = null): array
    {
        $ctx ??= $this->env->ctx();
        return $this->inbox()->decide($ctx, $item['id'], 'approve', $item['digest'], $ctx->tenantHash());
    }
}
