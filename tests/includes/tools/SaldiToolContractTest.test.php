<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/tools/SaldiToolContractTest.test.php --- ai/mcp-assistant --- 2026.10.04
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
// Canonical contract drift and exported capability allowlists.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Cover companion foundation contracts.
// 20261004 CDX/LH Add companion part 2 foundation contracts.
// 20261009 CL/LH GRANT_REVOKED is the twelfth runtime error code.

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/tools/SaldiToolContract.php';

final class SaldiToolContractTest extends PHPUnit\Framework\TestCase
{
    public function testCanonicalExportHasNoDrift(): void
    {
        self::assertSame(
            file_get_contents(__DIR__ . '/../../../doc/contracts/tool-registry.v1.json'),
            SaldiToolContract::json()
        );
        $contract = json_decode(SaldiToolContract::json(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(54, $contract['tools']);
        self::assertCount(15, $contract['kinds']);
        self::assertCount(12, $contract['error_codes']);
        self::assertCount(8, $contract['forbidden_capabilities']);
        self::assertSame(['2025-06-18', '2025-03-26'], $contract['protocol_versions']);
        self::assertStringEndsWith("\n", SaldiToolContract::json());
        self::assertStringStartsWith("{\n  \"error_codes\":", SaldiToolContract::json());
        foreach ($contract['screens'] as $screen) {
            self::assertTrue(SaldiScreens::isAllowed(SaldiScreens::link(
                $screen['type'],
                $screen['param'] === null ? null : 12
            )));
        }
    }
}
