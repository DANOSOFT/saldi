<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/includes/AlignGroupCharacterizationTest.test.php --- ver 5.0.0 --- 2026-10-03 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Pin zero-difference settlement through the web page.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../finans/support/PaymentFixture.php';
final class AlignGroupCharacterizationTest extends TestCase
{
    private static $connection;
    public static function setUpBeforeClass(): void
    {
        if ($reason=CharacterizationEnv::unavailableReason()) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant();
        PaymentFixture::session();
        self::$connection=CharacterizationEnv::connect(CharacterizationEnv::testDb());
    }
    public static function tearDownAfterClass(): void
    {
        if (self::$connection) {
            pg_close(self::$connection);
            PaymentFixture::dropSession();
            CharacterizationEnv::teardownTenant();
        }
    }
    public function testWebZeroDifferenceSettlesTheWholeSelectedGroup(): void
    {
        $fixture=PaymentFixture::seed(self::$connection);
        PaymentFixture::invoice(self::$connection,$fixture,'201');
        foreach (['-400.00','-600.00'] as $amount) {
            PaymentFixture::query(self::$connection,"INSERT INTO openpost (konto_id,konto_nr,faktnr,amount,udlignet,transdate,valuta,valutakurs,projekt,udlign_id) VALUES ($1,$2,'201',$3,'0','2026-03-15','DKK',100,0,0)",[$fixture['debtor'],$fixture['number'],$amount]);
        }
        $result=CharacterizationEnv::runChild(__DIR__.'/../support/run_payment_align.php',[$fixture['debtor']]);
        self::assertSame(0,$result['exit'],$result['stderr'].$result['stdout']);
        $rows=PaymentFixture::query(self::$connection,'SELECT udlignet,udlign_id,udlign_date FROM openpost WHERE konto_id=$1 ORDER BY id',[$fixture['debtor']]);
        self::assertCount(3,$rows);
        self::assertSame(['1','1','1'],array_column($rows,'udlignet'));
        self::assertCount(1,array_unique(array_column($rows,'udlign_id')));
        self::assertGreaterThan(0,(int)$rows[0]['udlign_id']);
        self::assertSame(['2026-03-15','2026-03-15','2026-03-15'],array_column($rows,'udlign_date'));
    }
}
