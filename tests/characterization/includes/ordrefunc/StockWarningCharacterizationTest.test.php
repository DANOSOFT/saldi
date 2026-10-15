<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/includes/ordrefunc/StockWarningCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.05
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

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../../../restapi/services/legacy/LegacyEngine.php';
require_once __DIR__ . '/../../../../includes/db_query.php';

final class StockWarningCharacterizationTest extends TestCase
{
    private static $tenant;
    private static EngineContext $context;
    private static $requestUri;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        self::$requestUri = ifset($_SERVER, 'REQUEST_URI');
        $_SERVER['REQUEST_URI'] = '/saldi/debitor/ordre.php';
        CharacterizationEnv::bootstrapTenant();
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        $id = (int)CharacterizationEnv::one($master, 'SELECT id FROM regnskab WHERE db=$1', [CharacterizationEnv::testDb()])['id'];
        pg_close($master);
        self::$context = new EngineContext(self::$tenant, CharacterizationEnv::testDb(), $id, 'chartest', 1, '2026-03-15');
        CharacterizationEnv::rows(self::$tenant, "DELETE FROM settings WHERE var_name='min_beholdning' AND var_grp='productOptions'");
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO settings(var_name,var_grp,var_value) VALUES('min_beholdning','productOptions',' 5 ')");
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO grupper(art,kodenr,box8,fiscal_year) VALUES('VG','98761','on',$1),('VG','98762','off',$1)", [self::$context->regnaar]);
        LegacyEngine::run(self::$context, static function (): void {
            LegacyEngine::load('orders');
        });
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenant) {
            pg_close(self::$tenant);
            CharacterizationEnv::teardownTenant();
            if (self::$requestUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = self::$requestUri;
            }
        }
    }

    /** Pin complete public results, warehouse precedence, minimum fallback and projected sales. */
    public function testExistingWarningRules(): void
    {
        $cases = [
            [10, 3, 0, null, true, false, 10, 3, 10],
            [10, 3, 8, null, true, true, 10, 3, 2],
            [10, 3, 7, null, true, false, 10, 3, 3],
            [10, 3, 'invalid', null, true, false, 10, 3, 10],
            [0, 0, 0, null, true, true, 0, 5, 0],
            [6, -1, 2, null, true, true, 6, 5, 4],
            [0, 3, 0, [4, 5], true, false, 9, 3, 9],
            [999, 3, 0, [1, 1], true, true, 2, 3, 2],
            [999, 3, 0, [0, 0], true, true, 0, 3, 0],
            [999, 3, 0, [null], true, false, 999, 3, 999],
            [-2, 3, -6, null, true, false, -2, 3, 4],
            [0, 3, 1, null, false, false, 0, 3, -1],
        ];
        foreach ($cases as $index => [$stock, $min, $sale, $warehouses, $tracked, $warn, $expectedStock, $expectedMin, $projected]) {
            $item = CharacterizationEnv::one(self::$tenant,
                'INSERT INTO varer(varenr,beskrivelse,beholdning,min_lager,gruppe) VALUES($1,$2,$3,$4,$5) RETURNING id',
                ['SW-'.$index, 'Warning fixture', $stock, $min, $tracked ? 98761 : 98762]);
            if ($warehouses !== null) {
                foreach ($warehouses as $warehouse => $qty) {
                    CharacterizationEnv::rows(self::$tenant, 'INSERT INTO lagerstatus(vare_id,lager,beholdning) VALUES($1,$2,$3)', [$item['id'], $warehouse + 1, $qty]);
                }
            }
            $result = LegacyEngine::run(self::$context, static fn(): array => check_stock_warning($item['id'], $sale));
            $this->assertSame(['out_of_stock'=>$warn, 'beholdning'=>(float)$expectedStock, 'min_lager'=>(float)$expectedMin,
                'beskrivelse'=>'Warning fixture', 'varenr'=>'SW-'.$index, 'projected_beholdning'=>(float)$projected], $result, 'Case '.$index);
        }
    }

    public function testInvalidOrMissingItemKeepsEmptyResult(): void
    {
        foreach ([null, 0, '', 'not numeric', 999999999] as $id) {
            $this->assertSame(['out_of_stock'=>false, 'beholdning'=>0, 'min_lager'=>0, 'beskrivelse'=>'', 'varenr'=>'', 'projected_beholdning'=>0],
                LegacyEngine::run(self::$context, static fn(): array => check_stock_warning($id)));
        }
    }
    public function testAbsentInvalidAndZeroStandardPreserveLegacyNumericTypes(): void
    {
        $item = CharacterizationEnv::one(self::$tenant,
            "INSERT INTO varer(varenr,beskrivelse,beholdning,min_lager,gruppe) VALUES('SW-STANDARD','Standard fixture',0,0,98761) RETURNING id");
        foreach ([null, 'invalid', '0', '-5'] as $setting) {
            CharacterizationEnv::rows(self::$tenant,"DELETE FROM settings WHERE var_name='min_beholdning' AND var_grp='productOptions'");
            if ($setting !== null) {
                CharacterizationEnv::rows(self::$tenant,"INSERT INTO settings(var_name,var_grp,var_value) VALUES('min_beholdning','productOptions',$1)",[$setting]);
            }
            $child = CharacterizationEnv::runChild(__DIR__ . '/../../support/run_stock_warning.php', [$item['id']]);
            self::assertSame(0,$child['exit'],$child['stdout'].$child['stderr']);
            $result = json_decode(trim($child['stdout']),true,512,JSON_THROW_ON_ERROR);
            self::assertSame($setting === null || $setting === 'invalid' ? 0 : (float)$setting,$result['min_lager']);
            self::assertFalse($result['out_of_stock']);
        }
    }

}
