<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiReorderSuggestionsRoutineTest.test.php --- ai/mcp-assistant --- 2026.10.04
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
// Weekly reorder findings are bounded, dated and honest when no items need attention.
//
// 20261004 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiPurchaseTestCase.php';

final class SaldiReorderSuggestionsRoutineTest extends SaldiPurchaseTestCase
{
    public function testWeeklyFindingUsesScheduledWindowAndTopTwentyLinks(): void
    {
        $db = $this->env->db;
        $db->exec('DELETE FROM ordrelinjer');
        for ($i = 0; $i < 25; $i++) {
            $this->product(['varenr' => 'EXTRA-' . $i]);
        }
        $this->sale('90.000', [], '2026-06-10');
        $before = $this->snapshot();
        $routine = new SaldiReorderSuggestionsRoutine();
        self::assertSame('weekly', $routine->schedule());
        self::assertSame('products.read', $routine->permission());
        self::assertSame(6170, $routine->titleTextId());
        $finding = $routine->run($this->env->ctx(), $db, '2026-06-10');
        self::assertSame(26, $finding['count']);
        self::assertCount(20, $finding['rows']);
        self::assertSame('reorder_suggestions', $finding['routine']);
        self::assertSame('2026-06-10', $finding['scheduled_date']);
        self::assertStringContainsString('(42.000)', $finding['rows'][0]['label']);
        self::assertSame(SaldiScreens::link('product', $this->product), $finding['rows'][0]['screen']);
        $user = $this->env->ctx();
        $ctx = SaldiTenantContext::forSystem(
            $user->installId,
            $user->regnskabId,
            $user->db,
            $user->dbType,
            'rule:reorder_suggestions',
            $user->fiscalYear,
            $user->baseCurrency
        );
        $item = $this->inbox()->create($ctx, 'routine.finding', $finding);
        self::assertCount(20, $item['card']['evidence']);
        self::assertSame($before, $this->snapshot());
    }

    public function testEmptyRunReturnsNullAndInvalidScheduledDatesFail(): void
    {
        $db = $this->env->db;
        $db->exec('UPDATE varer SET beholdning = 1000');
        $routine = new SaldiReorderSuggestionsRoutine();
        self::assertNull($routine->run($this->env->ctx(), $db, '2026-06-10'));
        $this->error('VALIDATION_ERROR', fn() => $routine->run($this->env->ctx(), $db, '2026-02-30'));
    }
}
