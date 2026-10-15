<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiSettlementTest.test.php --- ai/mcp-assistant --- 2026.10.04
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
// Exact posted settlements, approval custody, allocation contention and guarded undo.
//
// 20261004 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiPostingTestCase.php';
require_once __DIR__ . '/../../../includes/tools/SaldiToolRegistry.php';
require_once __DIR__ . '/../../../includes/tools/SaldiSettlementTools.php';

final class SaldiSettlementTest extends SaldiPostingTestCase
{
    private int $postId = 950000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env->db->exec('DELETE FROM openpost');
        $this->env->db->exec('DELETE FROM transaktioner');
        $this->env->db->exec(
            "UPDATE grupper SET box1 = '01', box2 = '2026', box3 = '12', box4 = '2026', box5 = 'on'"
            . " WHERE art = 'RA' AND kodenr = 4"
        );
    }

    private function post(string $amount, array $overrides = []): int
    {
        return $this->env->db->insert('openpost', $overrides + [
            'id' => ++$this->postId, 'konto_id' => 2005, 'konto_nr' => '2005',
            'amount' => $amount, 'faktnr' => 'INV-' . $this->postId,
            'transdate' => '2026-06-01', 'udlignet' => '', 'valuta' => 'DKK',
        ]);
    }

    private function input(array $ids, string $type = 'debtor', string $account = '2005'): array
    {
        return ['party_type' => $type, 'party_account' => $account, 'item_ids' => $ids];
    }

    private function proposal(array $ids): array
    {
        return $this->inbox()->create($this->env->ctx(), 'settlement.draft', $this->input($ids));
    }

    private function settlements(array $args = []): array
    {
        return SaldiSettlementTools::suggest($this->env->ctx(), $this->env->db, $args + ['party_type' => 'debtor']);
    }

    public function testExactPairsAndInvoiceNumberMatches(): void
    {
        $a = $this->post('100.000', ['faktnr' => 'MATCH']);
        $b = $this->post('-100.000', ['faktnr' => 'MATCH']);
        $c = $this->post('20.005');
        $d = $this->post('-20.005');
        $result = $this->settlements();
        self::assertCount(2, $result['data']['groups']);
        self::assertSame('invoice_number', $result['data']['groups'][0]['reason']);
        self::assertSame([$a, $b], $result['data']['groups'][0]['item_ids']);
        self::assertSame([$c, $d], $result['data']['groups'][1]['item_ids']);
        self::assertSame(['20.01', '-20.01'], $result['data']['groups'][1]['amounts']);
        SaldiSchemaValidator::validate(
            $result['data'],
            SaldiToolRegistry::default()->get('suggest_settlements')->dataSchema
        );
        self::assertSame('saldi:party:2005', $result['citations'][0]['id']);
        self::assertSame(4, (int)$this->env->db->value(
            "SELECT COUNT(*) FROM openpost WHERE COALESCE(udlignet, '') <> '1'"
        ));
    }

    public function testPaymentCoversTwoOrThreeInvoicesExactly(): void
    {
        $ids = [$this->post('-60.000'), $this->post('10.000'), $this->post('20.000'), $this->post('30.000')];
        $two = [$this->post('-125.000'), $this->post('50.000'), $this->post('75.000')];
        $groups = $this->settlements()['data']['groups'];
        self::assertContains($ids, array_column($groups, 'item_ids'));
        self::assertContains($two, array_column($groups, 'item_ids'));
        self::assertSame('exact_subset', $groups[0]['reason']);
        self::assertSame('0.00', $groups[0]['total']);
    }

    public function testPartyCurrencyDateAndAlreadySettledItemsNeverMixed(): void
    {
        $a = $this->post('100.000');
        $this->post('-100.000', ['valuta' => 'EUR']);
        $this->post('-100.000', ['konto_id' => 2006, 'konto_nr' => '2006']);
        $this->post('-100.000', ['udlignet' => '1']);
        $this->post('-100.000', ['transdate' => '2027-01-01']);
        self::assertSame([], $this->settlements()['data']['groups']);
        $b = $this->post('-100.000', ['transdate' => '2025-01-01']);
        self::assertSame([$a, $b], $this->settlements()['data']['groups'][0]['item_ids']);
        self::assertSame([], $this->settlements(['party_account' => '2006'])['data']['groups']);
    }

    public function testOreAndSubCentDifferencesAreRejected(): void
    {
        $kind = new SaldiSettlementKind();
        foreach (['-99.990', '-99.999'] as $amount) {
            $this->env->db->exec('DELETE FROM openpost');
            $ids = [$this->post('100.000'), $this->post($amount)];
            self::assertSame([], $this->settlements()['data']['groups']);
            $error = $this->error(
                'VALIDATION_ERROR',
                fn() => $kind->validate($this->env->ctx(), $this->env->db, $this->input($ids))
            );
            self::assertSame('not_balanced', $error->detail['reason']);
            self::assertNotSame('0.000', $error->detail['difference_exact']);
        }
    }

    public function testValidationRejectsDuplicateMissingMixedAndFutureSelections(): void
    {
        $a = $this->post('100.000');
        $b = $this->post('-100.000', ['valuta' => 'EUR']);
        $kind = new SaldiSettlementKind();
        $validate = fn($ids) => $kind->validate($this->env->ctx(), $this->env->db, $this->input($ids));
        $error = $this->error('VALIDATION_ERROR', fn() => $validate([$a, $b]));
        self::assertSame('currency_mismatch', $error->detail['reason']);
        foreach ([[$a, $a], [$a, 999999], [$a], [$a, (string)$b]] as $ids) {
            $this->error('VALIDATION_ERROR', fn() => $validate($ids));
        }
        $this->env->db->exec("UPDATE openpost SET valuta = 'DKK', konto_id = 2006 WHERE id = ?", [$b]);
        $this->error('VALIDATION_ERROR', fn() => $validate([$a, $b]));
        $this->env->db->exec("UPDATE openpost SET konto_id = 2005, transdate = '2027-01-01' WHERE id = ?", [$b]);
        $this->error('VALIDATION_ERROR', fn() => $validate([$a, $b]));
    }

    public function testApprovalReceiptAndUndoRestoreEveryBeforeValueExactly(): void
    {
        $a = $this->post('150.000', [
            'udlignet' => null, 'udlign_id' => null, 'udlign_date' => null, 'transdate' => '2025-12-31',
        ]);
        $b = $this->post('-150.000', [
            'udlignet' => '0', 'udlign_id' => 99, 'udlign_date' => '2025-01-01', 'transdate' => '2026-06-15',
        ]);
        $before = $this->env->db->all('SELECT * FROM openpost ORDER BY id');
        $item = $this->proposal([$b, $a]);
        self::assertSame('permission', $this->env->db->value(
            'SELECT visibility FROM inbox_items WHERE id = ?',
            [$item['id']]
        ));
        self::assertSame('debtors.draft', $this->env->db->value(
            'SELECT assignee_permission FROM inbox_items WHERE id = ?',
            [$item['id']]
        ));
        $applied = $this->approve($item);
        self::assertSame(100, $applied['result']['udlign_id']);
        self::assertSame([$a, $b], $applied['result']['item_ids']);
        self::assertTrue($applied['can_undo']);
        $rows = $this->env->db->all('SELECT * FROM openpost ORDER BY id');
        self::assertSame(['1', '1'], array_column($rows, 'udlignet'));
        self::assertSame(['2026-06-15', '2026-06-15'], array_column($rows, 'udlign_date'));
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM transaktioner'));
        $card = (new SaldiSettlementKind())->render($this->env->ctx(), $this->env->db, $applied);
        self::assertSame(['Faktura', 'Dato', 'Beløb'], $card['columns']);
        self::assertSame(['label' => 'I alt', 'value' => '0.00 DKK'], $card['fields'][0]);
        self::assertStringContainsString('Intet bogføres', $card['next_step']);
        $undone = $this->inbox()->undo($this->env->ctx(), $item['id'], $this->env->ctx()->tenantHash());
        self::assertSame('undone', $undone['status']);
        self::assertSame($before, $this->env->db->all('SELECT * FROM openpost ORDER BY id'));
    }

    public function testManualUnsettleOrEditBlocksUndo(): void
    {
        $a = $this->post('100.000');
        $b = $this->post('-100.000');
        $item = $this->approve($this->proposal([$a, $b]));
        $kind = new SaldiSettlementKind();
        $this->env->db->exec("UPDATE openpost SET udlignet = '' WHERE id = ?", [$a]);
        self::assertFalse($kind->canUndo($this->env->ctx(), $this->env->db, $item));
        $this->error(
            'CONFLICT',
            fn() => $this->inbox()->undo($this->env->ctx(), $item['id'], $this->env->ctx()->tenantHash())
        );
        $this->env->db->exec("UPDATE openpost SET udlignet = '1', amount = '100.010' WHERE id = ?", [$a]);
        self::assertFalse($kind->canUndo($this->env->ctx(), $this->env->db, $item));
    }

    public function testChangeBySecondConnectionAfterProposalReturnsConflict(): void
    {
        $a = $this->post('100.000');
        $b = $this->post('-100.000');
        $item = $this->proposal([$a, $b]);
        $other = SaldiDb::connect(saldi_runtime_config(), $this->env->name);
        try {
            $other->exec("UPDATE openpost SET beskrivelse = 'Manual edit' WHERE id = ?", [$a]);
        } finally {
            $other->close();
        }
        self::assertSame('not_open', $this->error('CONFLICT', fn() => $this->approve($item))->detail['reason']);
        self::assertSame(['', ''], array_column(
            $this->env->db->all('SELECT udlignet FROM openpost ORDER BY id'),
            'udlignet'
        ));
    }

    public function testAllocationAndRowConflictWhileAnotherTransactionHoldsLock(): void
    {
        $a = $this->post('100.000');
        $b = $this->post('-100.000');
        $c = $this->post('200.000');
        $d = $this->post('-200.000');
        $first = $this->proposal([$a, $b]);
        $second = $this->proposal([$c, $d]);
        $worker = null;
        $pipes = [];
        $this->env->db->transaction(function () use ($first, $second, $c, &$worker, &$pipes): void {
            $this->env->db->lock('saldi_udlign_id');
            $worker = proc_open(
                [PHP_BINARY, __DIR__ . '/../support/SaldiSettlementWorker.php',
                    $this->env->name, (string)$this->env->regnskab['id']],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
                $pipes
            );
            self::assertIsResource($worker);
            fwrite($pipes[0], json_encode($second, JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            self::assertSame("ready\n", fgets($pipes[1]));
            $result = (new SaldiSettlementKind())->apply($this->env->ctx(), $this->env->db, $first);
            self::assertSame(1, $result['udlign_id']);
            $this->env->db->exec("UPDATE openpost SET amount = '200.010' WHERE id = ?", [$c]);
        });
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($worker), $errors);
        self::assertSame('CONFLICT', json_decode($output, true, 512, JSON_THROW_ON_ERROR)['error']);
        self::assertSame(2, (int)$this->env->db->value("SELECT COUNT(*) FROM openpost WHERE udlignet = '1'"));
        $this->env->db->exec("UPDATE openpost SET amount = '200.000' WHERE id = ?", [$c]);
        $result = $this->env->db->transaction(
            fn() => (new SaldiSettlementKind())->apply($this->env->ctx(), $this->env->db, $second)
        );
        self::assertSame(2, $result['udlign_id']);
    }

    public function testCreditorPermissionAndVisibilityAndAgentsCannotApply(): void
    {
        $a = $this->post('-100.000', ['konto_id' => 1005, 'konto_nr' => '1005']);
        $b = $this->post('100.000', ['konto_id' => 1005, 'konto_nr' => '1005']);
        $rights = str_repeat('0', 16);
        $rights[8] = '1';
        $ctx = $this->env->ctx(910001, $rights);
        $item = $this->inbox()->create($ctx, 'settlement.draft', $this->input([$a, $b], 'creditor', '1005'));
        self::assertSame('creditors.draft', $this->env->db->value(
            'SELECT assignee_permission FROM inbox_items WHERE id = ?',
            [$item['id']]
        ));
        self::assertCount(1, SaldiSettlementTools::suggest(
            $ctx,
            $this->env->db,
            ['party_type' => 'creditor']
        )['data']['groups']);
        $this->error(
            'PERMISSION_DENIED',
            fn() => SaldiSettlementTools::suggest($ctx, $this->env->db, ['party_type' => 'debtor'])
        );
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist', 12, ['read', 'draft']);
        $this->error('PERMISSION_DENIED', fn() => (new SaldiSettlementKind())->apply($agent, $this->env->db, $item));
        self::assertSame('applied', $this->approve($item, $ctx)['status']);
    }

    public function testDraftToolReturnsSchemaValidApprovalCard(): void
    {
        $ids = [$this->post('100.000'), $this->post('-100.000')];
        $result = SaldiDraftTools::propose(
            'settlement.draft',
            $this->env->ctx(),
            $this->env->db,
            $this->input($ids) + ['request_id' => 'b7-settlement-tool', 'explanation' => 'Præcist par']
        );
        SaldiSchemaValidator::validate(
            $result['data'],
            SaldiToolRegistry::default()->get('propose_settlement')->dataSchema
        );
        self::assertSame('open', $result['data']['status']);
        self::assertSame('0.00 DKK', $result['data']['card']['fields'][0]['value']);
        self::assertSame('DKK', $result['data']['payload']['rows'][0]['currency']);
        self::assertSame('100.00 DKK', $result['data']['card']['rows'][0][2]);
        self::assertSame(['', ''], array_column(
            $this->env->db->all('SELECT udlignet FROM openpost ORDER BY id'),
            'udlignet'
        ));
    }

    public function testCandidateCapAndOutputLimitAreReported(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->post('100.000');
        }
        $this->post('-100.000');
        $result = $this->settlements();
        self::assertTrue($result['possibly_incomplete']);
        self::assertSame([], $result['data']['groups']);
        $this->env->db->exec('DELETE FROM openpost');
        $this->post('10.000');
        $this->post('-10.000');
        $this->post('20.000');
        $this->post('-20.000');
        $result = $this->settlements(['limit' => 1]);
        self::assertCount(1, $result['data']['groups']);
        self::assertTrue($result['possibly_incomplete']);
    }
}
