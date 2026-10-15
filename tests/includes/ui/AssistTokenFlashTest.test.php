<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/ui/AssistTokenFlashTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// The one-time personal-key flash (systemdata/assistent.php) is shown only to the issuing company and
// user; anywhere else it is consumed and only the grant id and issuing company come back.
//
// 20261002 CL/LH Created.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/automation/assistTokenFlash.php';

final class AssistTokenFlashTest extends TestCase
{
    private const TOKEN = ['token' => 'smcp1.local.10.7.not-a-real-secret', 'grant_id' => 7];

    public function testIssuingCompanyAndUserSeeTheKeyOnce(): void
    {
        $session = [];
        saldi_assist_token_flash_put($session, self::TOKEN, 'tenant_a', 5, 'Firma A ApS');
        $taken = saldi_assist_token_flash_take($session, 'tenant_a', 5);
        self::assertSame(['token' => self::TOKEN['token'], 'grant_id' => 7, 'company' => 'Firma A ApS'], $taken['token']);
        self::assertNull($taken['foreign']);
        self::assertArrayNotHasKey('assistent_new_token', $session);
        self::assertSame(['token' => null, 'foreign' => null], saldi_assist_token_flash_take($session, 'tenant_a', 5));
    }

    public function testAnotherCompanyOrUserNeverSeesTheKey(): void
    {
        foreach ([['tenant_b', 5], ['tenant_a', 6], ['tenant_a', 0]] as [$db, $user]) {
            $session = [];
            saldi_assist_token_flash_put($session, self::TOKEN, 'tenant_a', 5, 'Firma A ApS');
            $taken = saldi_assist_token_flash_take($session, $db, $user);
            self::assertNull($taken['token']);
            self::assertSame(['grant_id' => 7, 'company' => 'Firma A ApS'], $taken['foreign']);
            self::assertStringNotContainsString(self::TOKEN['token'], json_encode($taken));
            self::assertArrayNotHasKey('assistent_new_token', $session);
        }
        // A flash from before the binding existed (no db/user) is dropped, never shown.
        $session = ['assistent_new_token' => self::TOKEN];
        $legacy = saldi_assist_token_flash_take($session, 'tenant_a', 5);
        self::assertNull($legacy['token']);
        self::assertSame(7, $legacy['foreign']['grant_id']);
    }
}
