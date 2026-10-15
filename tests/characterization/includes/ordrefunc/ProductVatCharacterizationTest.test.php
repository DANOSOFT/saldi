<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/includes/ordrefunc/ProductVatCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Pin late VAT fallback after inclusive-price conversion for missing SM rates.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../../restapi/fixtures/t2/ProductVatFixture.php';

final class ProductVatCharacterizationTest extends TestCase
{
    private static $tenant;
    private static int $year;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant();
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        self::$year = (int)CharacterizationEnv::one(self::$tenant, "SELECT max(kodenr) AS year FROM grupper WHERE art='RA'")['year'];
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenant) {
            pg_close(self::$tenant);
            CharacterizationEnv::teardownTenant();
        }
    }

    public function testLegacyVatRulesAndMissingAccountPricing(): void
    {
        foreach (ProductVatFixture::scenarios() as $scenario) {
            $fixture = ProductVatFixture::create(self::$tenant, [CharacterizationEnv::class, 'rows'], self::$year, $scenario);
            $child = CharacterizationEnv::runChild(__DIR__ . '/../../support/run_product_vat.php', [json_encode($fixture), CharacterizationEnv::testDb()]);
            $lines = array_values(array_filter(explode("\n", trim($child['stdout']))));
            $out = json_decode(end($lines), true);
            $this->assertIsArray($out, $scenario . ': ' . $child['stdout'] . $child['stderr']);
            if ($scenario === 'missing_account') {
                $this->assertNull($out['line'], 'Legacy SM with no VAT account attempts an empty numeric vat_account and inserts no line');
                $this->assertStringContainsString('Manglende konto for salgsmoms', $out['output']);
                continue;
            }
            $this->assertNotNull($out['line'], $scenario . ': ' . $child['stderr']);
            $this->assertSame($fixture['expected_rate'], (float)$out['line']['momssats'], $scenario);
            $this->assertSame($fixture['expected_exempt'], $out['line']['momsfri'], $scenario);
            $this->assertSame($fixture['expected_reverse'], $out['line']['omvbet'], $scenario);
            $this->assertSame(100.0, (float)$out['line']['pris'], $scenario);
            $pricingRate = in_array($scenario, ['missing_account', 'no_code'], true) ? 25 : $fixture['expected_rate'];
            $this->assertSame(100.0 + $pricingRate, (float)$out['line']['vat_price'], $scenario);
            if (in_array($scenario, ['missing_account', 'no_code'], true)) {
                $this->assertStringContainsString('Manglende konto for salgsmoms', $out['output']);
            }
        }
    }

    public function testMissingSmRateKeepsLegacyInclusivePricing(): void
    {
        foreach ([false, true] as $department) {
            foreach (['', '0', '0.0', null] as $rate) {
                $fixture = ProductVatFixture::missingRate(self::$tenant, [CharacterizationEnv::class, 'rows'], self::$year, $rate, $department);
                $child = CharacterizationEnv::runChild(__DIR__ . '/../../support/run_product_vat.php', [json_encode($fixture), CharacterizationEnv::testDb()]);
                $lines = array_values(array_filter(explode("\n", trim($child['stdout']))));
                $out = json_decode(end($lines), true);
                $label = ($department ? 'department' : 'account') . ' rate=' . var_export($rate, true);
                $this->assertIsArray($out, $label . ': ' . $child['stdout'] . $child['stderr']);
                if ($rate === null) {
                    $this->assertNull($out['line'], $label . ': legacy missing-SM numeric insert failure');
                    $this->assertStringContainsString('Manglende konto for salgsmoms', $out['output']);
                    continue;
                }
                $this->assertNotNull($out['line'], $label . ': ' . $child['stderr']);
                $this->assertSame($fixture['expected_rate'], (float)$out['line']['momssats'], $label);
                $this->assertSame(125.0, (float)$out['line']['pris'], $label);
                $this->assertSame($rate === '0.0' ? 125.0 : 156.25, (float)$out['line']['vat_price'], $label);
                $this->assertSame('7901', $out['line']['vat_account'], $label);
            }
        }
    }
}
