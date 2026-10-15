<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/finans/KassekladdeVatCodesTest.test.php --- ver 5.0.0 --- 2026.10.05
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

require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../includes/std_func.php';

final class KassekladdeVatCodesTest extends TestCase
{
    private static $tenant;
    private static array $savedGlobals;
    private static array $savedServer;

    public static function setUpBeforeClass(): void
    {
        if (($reason = CharacterizationEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        self::$savedServer = $_SERVER;
        $_SERVER['REQUEST_URI'] = '/tests/kassekladde-vat';
        CharacterizationEnv::bootstrapTenant();
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        foreach (['connection', 'db_type'] as $key) {
            self::$savedGlobals[$key] = [array_key_exists($key, $GLOBALS), ifset($GLOBALS, $key)];
        }
        $GLOBALS['connection'] = self::$tenant;
        $GLOBALS['db_type'] = 'postgresql';
        $library = __DIR__ . '/../../../finans/kassekladde_includes/vatCodes.php';
        if (is_file($library)) {
            require_once $library;
        } else {
            // Evaluate just the existing helper definitions, never the rendering page.
            $page = file_get_contents(__DIR__ . '/../../../finans/kassekladde.php');
            $start = strpos($page, 'function scalar_input_text(');
            $end = strpos($page, 'function ensure_cash_vat_columns()', $start);
            if ($start === false || $end === false) {
                throw new RuntimeException('VAT characterization source boundaries missing');
            }
            eval(substr($page, $start, $end - $start));
        }
        CharacterizationEnv::rows(self::$tenant, 'BEGIN');
        CharacterizationEnv::rows(self::$tenant, "DELETE FROM grupper WHERE substring(art,2,1)='M'");
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO grupper(kode,kodenr,beskrivelse,art) VALUES
            ('K',1,'Purchase','KM'),('S',1,'Sale','SM'),('E',2,'EU','EM'),('Y',3,'Import','YM'),('X',4,'Excluded','XM')");
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO kontoplan(kontonr,regnskabsaar,moms,genvej) VALUES (987001,987,'K1','P')");
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO kassekladde(id,debet,d_type) VALUES (987001,987001,'F')");
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenant) {
            CharacterizationEnv::rows(self::$tenant, 'ROLLBACK');
            pg_close(self::$tenant);
            foreach (self::$savedGlobals as $key => [$exists, $value]) {
                if ($exists) {
                    $GLOBALS[$key] = $value;
                } else {
                    unset($GLOBALS[$key]);
                }
            }
            CharacterizationEnv::teardownTenant();
            $_SERVER = self::$savedServer;
        }
    }

    private function codes(): array
    {
        if (function_exists('kassekladde_vat_codes')) {
            return kassekladde_vat_codes();
        }
        $page = file_get_contents(__DIR__ . '/../../../finans/kassekladde.php');
        $start = strpos($page, '$vat_codes = array();');
        $end = strpos($page, '/**', $start);
        eval(substr($page, $start, $end - $start));
        return $vat_codes;
    }

    public function test_code_list_account_default_shortcut_and_invalid_input(): void
    {
        $codes = $this->codes();
        $this->assertSame(['E2'=>'EU','K1'=>'Purchase','S1'=>'Sale','Y3'=>'Import'], $codes);
        $this->assertSame('K1', lookup_account_vat_code('987001','F',987,$codes));
        $this->assertSame('K1', lookup_account_vat_code('p','F',987,$codes));
        $this->assertSame('', lookup_account_vat_code('987001','K',987,$codes));
        $this->assertSame('', normalize_vat_code('unknown', $codes));
        $this->assertSame('', scalar_input_text(['K1']));
        $this->assertSame(null, get_saved_vat_code([], 'debetvat'));
    }

    public function test_exemption_override_and_blank_fallback_match_the_web(): void
    {
        $codes = $this->codes();
        $this->assertSame('', resolve_post_vat_code(987001,'debetvat','987001','F','','on',false,987,$codes));
        $this->assertSame('S1', resolve_post_vat_code(987001,'debetvat','987001','F','S1','',false,987,$codes));
        $this->assertSame('K1', resolve_post_vat_code(987001,'debetvat','987001','F','unknown','',false,987,$codes));
        $this->assertSame('K1', resolve_lookup_vat_code(null,'987001','F','987001','F','','',987,$codes));
        $this->assertSame('', resolve_lookup_vat_code('','987001','F','987001','F','K1','on',987,$codes));
        $this->assertSame('Y3', resolve_lookup_vat_code('Y3','987001','F','987001','F','K1','',987,$codes));
    }
}
