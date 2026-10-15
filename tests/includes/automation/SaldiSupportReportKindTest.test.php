<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiSupportReportKindTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Regression coverage for approved support custody and transport receipts.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Compare support receipt contents independently of key order.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiReachTestCase.php';
require_once __DIR__ . '/../../../includes/tools/SaldiToolRegistry.php';

final class SaldiSupportReportKindTest extends SaldiReachTestCase
{
    public function testOwnerApprovalQueuesOnlyTheNormalizedDescriptionAndMetadata(): void
    {
        $this->company();
        $ctx = $this->env->ctx();
        $input = ['category' => 'question', 'summary' => "  Hjælp med\x00 kladden  ",
            'details' => "En beskrivelse\nuden kontrollinjer.", 'screen' => 'finans/kladdeliste.php'];
        $item = $this->inbox()->create($ctx, 'support.report', $input);
        self::assertSame('owner', $this->env->db->value(
            'SELECT visibility FROM inbox_items WHERE id = ?',
            [$item['id']]
        ));
        self::assertSame($ctx->userId, $item['owner_user_id']);
        self::assertSame('Hjælp med kladden', $item['payload']['summary']);
        self::assertSame('En beskrivelseuden kontrollinjer.', $item['payload']['details']);
        self::assertSame('Send til support', $item['card']['approve_label']);
        self::assertSame('Spørgsmål', $item['card']['fields'][0]['value']);
        self::assertSame(0, (int)$this->env->db->value(
            "SELECT COUNT(*) FROM automation_jobs WHERE job_type = 'support.deliver'"
        ));
        $this->error('NOT_FOUND', fn() => $this->inbox()->get($this->env->ctx(910002), $item['id']));
        $applied = $this->approve($item);
        self::assertTrue($applied['result']['queued']);
        self::assertFalse($applied['can_undo']);
        $job = $this->env->db->one('SELECT * FROM automation_jobs WHERE id = ?', [$applied['result']['job_id']]);
        self::assertSame('support.deliver', $job['job_type']);
        $payload = json_decode($job['payload'], true, 64, JSON_THROW_ON_ERROR);
        foreach ($item['payload'] as $key => $value) {
            self::assertSame($value, $payload[$key]);
        }
        self::assertSame($ctx->tenantHash(), $payload['tenant_hash']);
        self::assertSame($ctx->userHash(), $payload['submitted_by_hash']);
        self::assertSame('Fixture company', $payload['company_name']);
        self::assertSame(saldi_runtime_config()['version'], $payload['saldi_version']);
        self::assertMatchesRegularExpression('/Z$/', $payload['approved_at']);
        self::assertCount(11, $payload);
        self::assertArrayNotHasKey('email', $payload);
        self::assertArrayNotHasKey('username', $payload);
        $this->error('CONFLICT', fn() => $this->approve($item));
        $replayed = $this->env->db->transaction(
            fn(): array => (new SaldiSupportReportKind())->apply($ctx, $this->env->db, $applied)
        );
        self::assertEquals($applied['result'], $replayed);
        self::assertSame(1, (int)$this->env->db->value(
            "SELECT COUNT(*) FROM automation_jobs WHERE job_type = 'support.deliver'"
        ));
    }

    public function testUnsafeSupportInputAndNonOwnerApplicationAreRejected(): void
    {
        $kind = new SaldiSupportReportKind();
        $ctx = $this->env->ctx();
        $valid = ['category' => 'bug', 'summary' => 'The journal screen fails', 'details' => 'Synthetic description'];
        foreach (
            [['category' => 'other'], ['summary' => 'short'], ['summary' => str_repeat('a', 201)],
            ['details' => str_repeat('a', 2001)], ['screen' => 'logud.php'],
            ['details' => 'token: ' . TestCredentials::password('support')]] as $override
        ) {
            $this->error('VALIDATION_ERROR', fn() => $kind->validate(
                $ctx,
                $this->env->db,
                array_replace($valid, $override)
            ));
        }
        self::assertSame('', $kind->validate($ctx, $this->env->db, $valid)['payload']['screen'] ?? '');
        $emptyDetails = $kind->validate($ctx, $this->env->db, array_replace($valid, ['details' => '']));
        self::assertSame('', $emptyDetails['payload']['details']);
        $item = $this->inbox()->create($ctx, 'support.report', $valid);
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist', 23, ['read', 'draft']);
        foreach ([$this->env->ctx(910002), $agent] as $other) {
            $this->error('PERMISSION_DENIED', fn() => $kind->apply($other, $this->env->db, $item));
        }
    }

    public function testReportProblemToolSuccessAndErrorMatchTheContract(): void
    {
        $tool = SaldiToolRegistry::default()->get('report_problem');
        $args = ['category' => 'question', 'summary' => 'Question about a journal',
            'details' => 'Synthetic description', 'explanation' => 'User requested support',
            'request_id' => 'support-tool'];
        $output = ($tool->handler)($this->env->ctx(), $this->env->db, $args);
        unset($output['result_count']);
        SaldiSchemaValidator::validate(['ok' => true, 'committed' => false] + $output, $tool->outputSchema());
        self::assertSame('open', $output['data']['status']);
        $error = $this->error('VALIDATION_ERROR', fn() => ($tool->handler)(
            $this->env->ctx(),
            $this->env->db,
            array_replace($args, ['category' => 'other', 'request_id' => 'support-invalid'])
        ));
        SaldiSchemaValidator::validate(['ok' => false, 'committed' => false,
            'error' => ['code' => $error->errorCode, 'class' => $error->errorClass(),
                'message_en' => $error->getMessage()]], $tool->outputSchema());
    }
}
