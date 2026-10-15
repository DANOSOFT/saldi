<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/support/SaldiReachTestCase.php --- ai/mcp-assistant --- 2026.10.02
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
// Isolated environment and tenant configuration helpers for reach service tests.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';

abstract class SaldiReachTestCase extends SaldiRuntimeDbTestCase
{
    private array $savedEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (
            ['SALDI_MAIL_DISABLED', 'SALDI_DIGEST_DISABLED', 'SALDI_PUBLIC_URL',
            'SALDI_SUPPORT_INTAKE_URL', 'SALDI_SUPPORT_INTAKE_SECRET'] as $key
        ) {
            $this->savedEnvironment[$key] = getenv($key);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->savedEnvironment as $key => $value) {
            putenv($value === false ? $key : $key . '=' . $value);
        }
        parent::tearDown();
    }

    protected function company(array $configuration = []): void
    {
        $this->env->db->exec("UPDATE adresser SET email = ?, firmanavn = ?, felt_1 = ?, felt_2 = ?, "
            . "felt_3 = ?, felt_4 = ? WHERE art = 'S'", [
                $configuration['email'] ?? 'sender@example.test',
                $configuration['name'] ?? 'Fixture company',
                $configuration['host'] ?? 'smtp.example.test',
                $configuration['user'] ?? '',
                $configuration['password'] ?? '',
                $configuration['encryption'] ?? '',
            ]);
    }

    protected function setting(string $group, string $name, string $value, ?int $userId = null): void
    {
        $this->env->db->insert('settings', ['var_grp' => $group, 'var_name' => $name,
            'var_value' => $value, 'user_id' => $userId, 'pos_id' => null]);
    }
}
