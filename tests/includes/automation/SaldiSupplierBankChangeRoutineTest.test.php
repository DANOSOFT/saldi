<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiSupplierBankChangeRoutineTest.test.php --- ai/mcp-assistant --- 2026.10.03
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
// Isolated Watch v2 regression coverage using synthetic fixtures only.
//
// 20261002 CDX/LH Created.
// 20261003 CDX/LH Add regression coverage for the confirmed Assist review findings.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiWatchDbTestCase.php';
require_once __DIR__ . '/../../../includes/automation/routines/SaldiSupplierBankChangeRoutine.php';
require_once __DIR__ . '/../../../includes/automation/SaldiJobRegistry.php';

final class SaldiSupplierBankChangeRoutineTest extends SaldiWatchDbTestCase
{
    public function testBaselineChangeMergeAndUnchangedPayloadNeverContainBankNumbers(): void
    {
        $db = $this->env->db;
        $routine = new SaldiSupplierBankChangeRoutine();
        $ctx = $this->rule($routine->name());
        self::assertNull($routine->run($ctx, $db, '2026-10-02'));
        $this->supplier();
        $this->supplier(70002);
        $this->supplier(70003, ['lukket' => 'on']);
        self::assertNull($routine->run($ctx, $db, '2026-10-02'));
        self::assertSame(2, (int)$db->value('SELECT COUNT(*) FROM automation_state'));
        $db->exec('UPDATE adresser SET bank_konto = ? WHERE id = ?', ['bank-fixture', 70001]);
        $payload = $routine->run($ctx, $db, '2026-10-02');
        self::assertSame('interrupt', $payload['attention']);
        self::assertSame(1, $payload['count']);
        $item = $this->inbox()->create($ctx, 'routine.finding', $payload);
        self::assertSame($item['payload'], $routine->run($ctx, $db, '2026-10-03'));
        $db->exec('UPDATE adresser SET iban = ? WHERE id = ?', ['iban-fixture', 70002]);
        $merged = $routine->run($ctx, $db, '2026-10-03');
        self::assertSame(2, $merged['count']);
        self::assertCount(2, $merged['rows']);
        self::assertSame($payload['rows'][0], $merged['rows'][0]);
        $stored = json_encode($db->all('SELECT * FROM automation_state'), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('bank-fixture', $stored);
        self::assertStringNotContainsString('iban-fixture', $stored);
        self::assertStringNotContainsString('bank_konto', json_encode($merged, JSON_THROW_ON_ERROR));
        self::assertSame('hourly', $routine->schedule());
    }

    public function testAcknowledgedFindingDoesNotReappearWithoutAnotherChange(): void
    {
        $routine = new SaldiSupplierBankChangeRoutine();
        $ctx = $this->rule($routine->name());
        $this->supplier();
        $routine->run($ctx, $this->env->db, '2026-10-02');
        $this->env->db->exec('UPDATE adresser SET bank_fi = ?', ['fixture-new']);
        $payload = $routine->run($ctx, $this->env->db, '2026-10-02');
        $item = $this->inbox()->create($ctx, 'routine.finding', $payload);
        $this->approve($item);
        self::assertNull($routine->run($ctx, $this->env->db, '2026-10-03'));
    }

    public function testMoreThanTwentySuppliersAccumulateAnExactCount(): void
    {
        $routine = new SaldiSupplierBankChangeRoutine();
        $ctx = $this->rule($routine->name());
        foreach (range(70001, 70025) as $id) {
            $this->supplier($id);
        }
        $routine->run($ctx, $this->env->db, '2026-10-02');
        $this->env->db->exec('UPDATE adresser SET bank_reg = ?', ['fixture-changed']);
        $payload = $routine->run($ctx, $this->env->db, '2026-10-02');
        self::assertSame(25, $payload['count']);
        self::assertCount(20, $payload['rows']);
        $item = $this->inbox()->create($ctx, 'routine.finding', $payload);
        $this->env->db->exec('UPDATE adresser SET swift = ? WHERE id = ?', ['new-fixture', 70025]);
        $next = $routine->run($ctx, $this->env->db, '2026-10-03');
        self::assertSame(25, $next['count']);
        self::assertCount(20, $next['rows']);
    }
    public function testInPlaceFindingRevisionLogsRevisedInsteadOfAnotherProposal(): void
    {
        $db = $this->env->db;
        $this->setting('automation', 'routine.supplier_bank_change', 'on');
        $this->supplier();
        $this->supplier(70002);
        $registry = SaldiJobRegistry::default();
        $handler = $registry->get('routine.supplier_bank_change');
        $run = fn(): array => $db->transaction(fn(): array => $handler($this->env->ctx(), $db, ['payload' => saldi_runtime_json(['scheduled_date' => '2026-10-03'])]));
        $run();
        $db->exec('UPDATE adresser SET bank_konto = ? WHERE id = 70001', ['bank-fixture-1']);
        $itemId = $run()['result']['item_id'];
        $db->exec('UPDATE adresser SET bank_konto = ? WHERE id = 70002', ['bank-fixture-2']);
        self::assertSame($itemId, $run()['result']['item_id']);
        self::assertSame(['proposed', 'revised'], array_column($db->all('SELECT event FROM decision_log WHERE inbox_item_id = ? ORDER BY id', [$itemId]), 'event'));
    }

}
