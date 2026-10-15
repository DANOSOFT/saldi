<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/lager/OptallingQueueGuardTest.test.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Characterise the form snapshot separately from the queue's count-date timestamp.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../support/StockFixture.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../restapi/services/legacy/LegacyEngine.php';
require_once __DIR__ . '/../../../includes/mobileUpdates.php';

/**
 * Execute the page's actual queue action without rendering its unrelated report.
 * @param array{vare_id:int,variant_id:int,optalt:string,beholdning:int,lager:int,tidspkt:string,sprog_id:int,snapshot_time?:string} $input
 * @return string Recount HTML, or an empty string when the action queues or deduplicates the count.
 */
function optalling_test_queue(array $input): string
{
    $page = file_get_contents(__DIR__ . '/../../../lager/optalling.php');
    $start = strpos($page, "if ($" . "vare_id && ($" . "optalt ||");
    $end = strpos($page, 'print "<tr><td align=', $start);
    if ($start === false || $end === false) {
        throw new LogicException('Queue action boundary missing');
    }
    extract($GLOBALS, EXTR_SKIP);
    extract($input, EXTR_OVERWRITE);
    $previousPost = $_POST;
    $_POST = ['tidspkt'=>ifset($input, 'snapshot_time', $input['tidspkt'])];
    ob_start();
    try {
        eval(str_replace('__DIR__', var_export(realpath(__DIR__ . '/../../../lager'), true), substr($page, $start, $end - $start)));
        return ob_get_contents();
    } finally {
        ob_end_clean();
        $_POST = $previousPost;
    }
}

final class OptallingQueueGuardTest extends TestCase
{
    private static EngineContext $context;

    public static function setUpBeforeClass(): void
    {
        if (($reason = CharacterizationEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        $_SERVER['REQUEST_URI'] = '/saldi/lager/optalling.php';
        StockFixture::start();
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        $id = (int)CharacterizationEnv::one($master, 'SELECT id FROM regnskab WHERE db=$1', [CharacterizationEnv::testDb()])['id'];
        pg_close($master);
        self::$context = new EngineContext(StockFixture::$tenant, CharacterizationEnv::testDb(), $id, 'chartest', 1, '2026-10-06');
    }

    public static function tearDownAfterClass(): void
    {
        StockFixture::stop();
    }

    public function testZeroCountDuplicateAndOtherWarehouseKeepTheLegacyQueueRows(): void
    {
        StockFixture::item(989100, 10);
        $input = ['vare_id'=>989100,'variant_id'=>0,'optalt'=>'0','beholdning'=>10,'lager'=>2,'tidspkt'=>'20260101120000','sprog_id'=>0];
        LegacyEngine::run(self::$context, static function () use ($input): void {
            self::assertSame('', optalling_test_queue($input));
            self::assertSame('', optalling_test_queue($input));
            $input['lager'] = 1;
            $input['optalt'] = '3,25';
            self::assertSame('', optalling_test_queue($input));
        });
        self::assertEquals([[989100,0,0,10,'f','20260101120000',2],[989100,0,3.25,10,'f','20260101120000',1]],
            array_map('array_values', StockFixture::sql('SELECT vare_id,variant_id,optalt,beholdning,bogfort,tidspkt,lager FROM regulering WHERE vare_id=989100 ORDER BY id')));
        self::assertEquals(10, StockFixture::one('SELECT beholdning FROM varer WHERE id=989100')['beholdning']);
        self::assertSame([], StockFixture::sql('SELECT id FROM stocklog WHERE item_id=989100'));
    }
    public function testMigratedTenantWithoutAppCountsKeepsQueueBehavior(): void
    {
        LegacyEngine::run(self::$context, static function (): void {
            mobileTenantUpdates(StockFixture::$tenant);
        });
        StockFixture::item(989101, 10);
        $input = ['vare_id'=>989101,'variant_id'=>0,'optalt'=>'7','beholdning'=>10,'lager'=>2,'tidspkt'=>'20260101235959','snapshot_time'=>'20260101120000','sprog_id'=>0];
        LegacyEngine::run(self::$context, static function () use ($input): void {
            self::assertSame('', optalling_test_queue($input));
            self::assertSame('', optalling_test_queue($input));
        });
        self::assertEquals([[989101,7,10,2,'f','20260101235959']], array_map('array_values', StockFixture::sql('SELECT vare_id,optalt,beholdning,lager,bogfort,tidspkt FROM regulering WHERE vare_id=989101')));
    }

    public function testOlderAppCountDoesNotBlockANewerWebSnapshotOrAnotherWarehouse(): void
    {
        StockFixture::item(989102, 10);
        StockFixture::sql("INSERT INTO mobile_stock_counts(client_id,session_id,subject,lager,reason_code,submitted_at,line_count,adjusted_count,value_estimate)
            VALUES($1,$2,'fixture/account/1',2,'optaelling','2026-01-01T10:00:00Z',1,0,0)",
            ['b1000000-0000-4000-8000-000000000001','b1000000-0000-4000-8000-000000000002']);
        $count = (int)StockFixture::one("SELECT id FROM mobile_stock_counts WHERE client_id='b1000000-0000-4000-8000-000000000001'")['id'];
        StockFixture::sql("INSERT INTO mobile_stock_count_lines(count_id,line_ref,vare_id,lager,counted_qty,before_qty,after_qty,delta,kostpris,outcome)
            VALUES($1,'b1000000-0000-4000-8000-000000000003',989102,2,10,10,10,0,4,'unchanged')", [$count]);
        $input = ['vare_id'=>989102,'variant_id'=>0,'optalt'=>'7','beholdning'=>10,'lager'=>2,'tidspkt'=>'20260101120000','sprog_id'=>0];
        LegacyEngine::run(self::$context, static function () use ($input): void {
            // Copenhagen noon is 11:00 UTC, after the app count even on a UTC database.
            self::assertSame('', optalling_test_queue($input));
            $input['tidspkt'] = '20260101100000';
            $text = optalling_test_queue($input);
            self::assertStringContainsString('Følgende varer har ændret antal under optællingen og skal optælles igen.', $text);
            // The page replaces tidspkt with the count date's end of day before this action.
            $input['tidspkt'] = '20260101235959';
            $input['snapshot_time'] = '20260101100000';
            self::assertStringContainsString('Følgende varer har ændret antal under optællingen og skal optælles igen.', optalling_test_queue($input));
            $input['lager'] = 1;
            self::assertSame('', optalling_test_queue($input));
        });
        self::assertCount(2, StockFixture::sql('SELECT id FROM regulering WHERE vare_id=989102'));
    }

}
