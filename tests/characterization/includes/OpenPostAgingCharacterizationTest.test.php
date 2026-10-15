<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/includes/OpenPostAgingCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Pin missing, stored, parity and date-specific foreign exchange rates before verification.
// 20261001 CDX/LH Characterize fractional foreign amounts at the web rounding threshold.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../includes/std_func.php';
require_once __DIR__ . '/../../../includes/forfaldsdag.php';
require_once __DIR__ . '/../../../includes/reportFunc/showOpenPosts.php';

final class OpenPostAgingCharacterizationTest extends TestCase
{
    private static $tenant;
    private static array $savedGlobals;
    private static array $savedServer;

    public static function setUpBeforeClass(): void
    {
        if (CharacterizationEnv::unavailableReason() !== null) {
            return;
        }
        CharacterizationEnv::bootstrapTenant();
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        self::$savedServer = $_SERVER;
        $_SERVER['REQUEST_URI'] = '/saldi/debitor/rapport.php';
        self::$savedGlobals = [];
        foreach (['connection', 'db_type', 'baseCurrency'] as $name) {
            self::$savedGlobals[$name] = [array_key_exists($name, $GLOBALS), ifset($GLOBALS, $name)];
        }
        $GLOBALS['connection'] = self::$tenant;
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['baseCurrency'] = 'DKK';
        CharacterizationEnv::rows(self::$tenant, "DELETE FROM valuta");
        CharacterizationEnv::rows(self::$tenant, "DELETE FROM grupper WHERE art='VK'");
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO grupper (art,kodenr,box1) VALUES ('VK',9911,'EUR')");
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO valuta (gruppe,valdate,kurs) VALUES (9911,'2020-01-01',745)");
    }

    protected function setUp(): void
    {
        if (!self::$tenant) {
            $this->markTestSkipped(CharacterizationEnv::unavailableReason() ?: 'Tenant unavailable');
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenant) {
            $_SERVER = self::$savedServer;
            foreach (self::$savedGlobals as $name => [$existed, $value]) {
                if ($existed) {
                    $GLOBALS[$name] = $value;
                } else {
                    unset($GLOBALS[$name]);
                }
            }
            pg_close(self::$tenant);
            self::$tenant = null;
        }
        CharacterizationEnv::teardownTenant();
    }

    /** @return list<array<string,mixed>> */
    private function posts(array $account): array
    {
        $today = new DateTimeImmutable('2026-10-02');
        $posts = [];
        foreach ($account['posts'] as $post) {
            $due = $today->modify(sprintf('%+d days', -(int)$post['days']))->format('Y-m-d');
            $posts[] = ['amount' => $post['amount'], 'faktnr' => $post['invoice'],
                'transdate' => '2026-01-01', 'forfaldsdate' => $due,
                'udlignet' => ifset($post, 'settled', false) ? '1' : '0',
                'udlign_date' => $today->modify('-' . (int)ifset($post, 'settled_days', 1) . ' days')->format('Y-m-d'),
                'valuta' => ifset($post, 'currency', 'DKK'), 'valutakurs' => ifset($post, 'rate', '100')];
        }
        return $posts;
    }

    public function testFixtureAccountsPinWebTotalsAndVisibility(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__ . '/../../restapi/fixtures/t2/open-posts-accounts.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($fixture['accounts'] as $account) {
            $cache = [];
            $aging = openpost_account_aging($this->posts($account), '2026-10-02', '2026-10-02', 'D', $cache);
            self::assertSame($account['expected'], number_format($aging['openY'], 2, '.', ''), $account['name']);
            self::assertEqualsWithDelta((float)$account['expected'], $aging['openKontrol'], 0.001);
            $visible = openpost_account_visible($aging, '2026-10-02', '2026-10-02', '', '');
            self::assertSame(!in_array($account['name'], ['Fully paid', 'Settled after report date'], true), $visible);
        }
    }

    public function testHistoricalSettlementAndDebitCreditModes(): void
    {
        $posts = [['amount' => '500.00', 'transdate' => '2026-01-01', 'forfaldsdate' => null,
            'udlignet' => '1', 'udlign_date' => '2026-09-27', 'valuta' => '', 'valutakurs' => null]];
        $cache = [];
        $aging = openpost_account_aging($posts, '2026-09-20', '2026-10-02', 'D', $cache);
        self::assertSame(500.0, $aging['openY']);
        self::assertSame(500.0, $aging['forfalden_plus90']);
        self::assertTrue(openpost_account_visible($aging, '2026-09-20', '2026-10-02', 'on', ''));
        self::assertFalse(openpost_account_visible($aging, '2026-09-20', '2026-10-02', '', 'on'));
        self::assertSame(0, $aging['openY']);
    }

    public function testLegacyBucketEdgesAndPerRowRounding(): void
    {
        foreach ([0 => null, 1 => 'forfalden', 8 => 'forfalden_plus8', 30 => 'forfalden_plus30',
            60 => 'forfalden_plus60', 90 => 'forfalden_plus90'] as $days => $bucket) {
            $posts = $this->posts(['posts' => [['invoice' => 'edge', 'amount' => '1.005', 'days' => $days]]]);
            $cache = [];
            $aging = openpost_account_aging($posts, '2026-10-02', '2026-10-02', 'D', $cache);
            self::assertSame(1.01, $aging['openY']);
            if ($bucket !== null) {
                self::assertSame(1.01, $aging[$bucket]);
            }
        }
    }

    public function testForeignStoredAndMissingRatesUseWebPrecision(): void
    {
        foreach ([null, '0', '100', '745'] as $rate) {
            foreach (['1.005', '-1.005'] as $amount) {
                $posts = [['amount' => $amount, 'transdate' => '2026-02-01', 'forfaldsdate' => null,
                    'udlignet' => '0', 'udlign_date' => null, 'valuta' => 'EUR', 'valutakurs' => $rate]];
                $cache = [];
                $aging = openpost_account_aging($posts, '2026-10-02', '2026-10-02', 'D', $cache);
                $sign = (float)$amount < 0 ? -1 : 1;
                self::assertEqualsWithDelta($sign * 7.48725, $aging['openY'], 0.000001);
                self::assertSame($sign * 7.49, $aging['openKontrol']);
                self::assertEqualsWithDelta($aging['openY'], $aging['forfalden_plus90'], 0.000001);
            }
        }
    }

    public function testFractionalForeignSaldoUsesAfrundAtTheHalfCentThreshold(): void
    {
        foreach ([1, -1] as $sign) {
            $posts = [['amount' => $sign * 0.151, 'transdate' => '2026-02-01', 'forfaldsdate' => null,
                'udlignet' => '0', 'udlign_date' => null, 'valuta' => 'EUR', 'valutakurs' => '745']];
            $cache = [];
            $aging = openpost_account_aging($posts, '2026-10-02', '2026-10-02', 'D', $cache);
            self::assertEqualsWithDelta($sign * 1.12495, $aging['openY'], 0.0000001);
            self::assertSame($sign * 1.13, afrund($aging['openY'], 2));
            self::assertSame($sign * 1.13, $aging['openKontrol']);
        }
    }

    public function testParityAndTransactionDateSelectTheHistoricalRate(): void
    {
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO grupper (art,kodenr,box1) VALUES ('VK',9912,'USD')");
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO valuta (gruppe,valdate,kurs)
            VALUES (9912,'2026-01-01',100),(9912,'2026-07-01',650),(9912,'2027-01-01',900)");
        foreach (['2026-06-30' => [1.005, 1.01], '2026-07-01' => [6.5325, 6.53]] as $date => [$rest, $control]) {
            $posts = [['amount' => '1.005', 'transdate' => $date, 'forfaldsdate' => $date,
                'udlignet' => '0', 'udlign_date' => null, 'valuta' => 'USD', 'valutakurs' => '100']];
            $cache = [];
            $aging = openpost_account_aging($posts, '2026-10-02', '2026-10-02', 'D', $cache);
            self::assertEqualsWithDelta($rest, $aging['openY'], 0.000001);
            self::assertSame($control, $aging['openKontrol']);
        }
    }

    public function testPostgresDecimalZeroRateDiffersFromTheLegacyMissingRate(): void
    {
        foreach (['0.000000', '0.0'] as $rate) {
            $posts = [['amount' => '1.005', 'transdate' => '2026-02-01', 'forfaldsdate' => null,
                'udlignet' => '0', 'udlign_date' => null, 'valuta' => 'EUR', 'valutakurs' => $rate]];
            $cache = [];
            $aging = openpost_account_aging($posts, '2026-10-02', '2026-10-02', 'D', $cache);
            self::assertSame(0.0, $aging['openY']);
            self::assertSame(1.01, $aging['openKontrol']);
        }
    }
}
