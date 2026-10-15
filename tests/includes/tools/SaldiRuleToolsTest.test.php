<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/tools/SaldiRuleToolsTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Deterministic candidates, tool contracts and permission regression tests.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiPostingTestCase.php';
require_once __DIR__ . '/../../../includes/tools/SaldiToolRegistry.php';
require_once __DIR__ . '/../../../includes/mcp/SaldiMcpServer.php';

final class SaldiRuleToolsTest extends SaldiPostingTestCase
{
    private function call(string $name, array $input, ?SaldiTenantContext $context = null): array
    {
        $context ??= $this->env->ctx();
        $server = new SaldiMcpServer(null, fn(): array => ['ctx' => $context, 'db' => $this->env->db], []);
        $response = $server->handle([
            'method' => 'POST', 'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer smcp1.local.10.99.' . bin2hex(random_bytes(32)),
            ],
            'body' => json_encode([
                'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
                'params' => ['name' => $name, 'arguments' => (object)$input],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertSame(200, $response['status']);
        return $response['body']['result']['structuredContent'];
    }

    public function testRulePagingStatusUsageAndCitationAreSchemaCompatible(): void
    {
        $first = $this->rule();
        $second = $this->rule(['match' => ['type' => 'text', 'value' => 'kontorartikler'], 'account' => '2900']);
        $this->env->db->insert('assist_events', [
            'event' => 'suggestion_used', 'subject' => 'rule:' . $second['result']['rule_id'],
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->env->db->insert('assist_events', [
            'event' => 'suggestion_used', 'subject' => 'rule:' . $second['result']['rule_id'],
            'created_at' => gmdate('Y-m-d H:i:s', time() - 91 * 86400),
        ]);
        $result = SaldiRuleTools::listRules($this->env->ctx(), $this->env->db, ['limit' => 1]);
        self::assertSame(2, $result['data']['total']);
        self::assertTrue($result['possibly_incomplete']);
        self::assertSame(1, $result['result_count']);
        self::assertSame(1, $result['data']['rows'][0]['uses_90d']);
        self::assertSame('Software', SaldiRuleTools::listRules(
            $this->env->ctx(),
            $this->env->db,
            ['limit' => 1, 'offset' => 1]
        )['data']['rows'][0]['account_name']);
        self::assertSame('saldi:rule:' . $second['result']['rule_id'], $result['citations'][0]['id']);
        self::assertStringEndsWith('Z', $result['data']['rows'][0]['created_at']);
        SaldiPostingRules::retire($this->env->db, $this->env->ctx(), $first['result']['rule_id'], 'stop');
        self::assertCount(1, SaldiRuleTools::listRules($this->env->ctx(), $this->env->db, [])['data']['rows']);
        $retired = SaldiRuleTools::listRules($this->env->ctx(), $this->env->db, ['status' => 'retired']);
        $all = SaldiRuleTools::listRules($this->env->ctx(), $this->env->db, ['status' => 'all']);
        self::assertCount(1, $retired['data']['rows']);
        self::assertCount(2, $all['data']['rows']);
        $envelope = $this->call('list_posting_rules', ['status' => 'all']);
        self::assertTrue($envelope['ok']);
        SaldiSchemaValidator::validate(
            $envelope,
            SaldiToolRegistry::default()->get('list_posting_rules')->outputSchema()
        );
    }

    public function testSuggestionToolReturnsTypedEvidenceAndBothSchemasValidate(): void
    {
        $this->repeat(4);
        $this->rule(['account' => '2900']);
        $input = [
            'description' => 'Adobe abonnement', 'amount' => '1250.00',
            'party' => ['ledger' => 'supplier', 'account' => '1005'],
        ];
        $result = SaldiRuleTools::suggest($this->env->ctx(), $this->env->db, $input);
        self::assertCount(2, $result['data']['suggestions']);
        self::assertCount(4, $result['data']['duplicates']);
        self::assertSame(6, $result['result_count']);
        self::assertStringStartsWith('saldi:rule:', $result['citations'][0]['id']);
        self::assertStringStartsWith('saldi:journal_line:', $result['citations'][1]['id']);
        $envelope = $this->call('suggest_posting', $input);
        self::assertTrue($envelope['ok']);
        SaldiSchemaValidator::validate($envelope, SaldiToolRegistry::default()->get('suggest_posting')->outputSchema());
        $denied = $this->call('suggest_posting', $input, $this->env->ctx(910001, str_repeat('0', 16)));
        self::assertFalse($denied['ok']);
        SaldiSchemaValidator::validate($denied, SaldiToolRegistry::default()->get('suggest_posting')->outputSchema());
    }

    public function testRuleListPermissionRevenueFilteringAndBadPages(): void
    {
        $this->rule();
        $this->rule(['match' => ['type' => 'text', 'value' => 'inventar'], 'account' => '5600']);
        $this->setting('finans', 'hideRevenue', 'on');
        $hidden = SaldiRuleTools::listRules($this->restrictedContext(), $this->env->db, []);
        self::assertSame(1, $hidden['data']['total']);
        self::assertSame('5600', $hidden['data']['rows'][0]['account']);
        $this->error('PERMISSION_DENIED', fn() => SaldiRuleTools::listRules(
            $this->env->ctx(910001, str_repeat('0', 16)),
            $this->env->db,
            []
        ));
        foreach ([['status' => 'deleted'], ['limit' => 101], ['limit' => '1'], ['offset' => -1]] as $input) {
            $this->error('VALIDATION_ERROR', fn() => SaldiRuleTools::listRules(
                $this->env->ctx(),
                $this->env->db,
                $input
            ));
        }
    }

    public function testRuleProposalAndRetirementCardsValidateAgainstMcpSchemas(): void
    {
        $proposal = $this->call('propose_posting_rule', [
            'match' => ['type' => 'supplier', 'value' => '1005'], 'account' => '2800',
            'vat_code' => 'K1', 'request_id' => 'rule-card', 'explanation' => 'Requested by the bookkeeper.',
        ]);
        self::assertTrue($proposal['ok']);
        self::assertSame(['Når', 'Konto', 'Moms', 'Tekst på linjen'], $proposal['data']['card']['columns']);
        self::assertSame('2800 Software', $proposal['data']['card']['rows'][0][1]);
        SaldiSchemaValidator::validate(
            $proposal,
            SaldiToolRegistry::default()->get('propose_posting_rule')->outputSchema()
        );
        $applied = $this->approve($proposal['data']);
        $retirement = $this->call('propose_rule_retirement', [
            'rule_id' => $applied['result']['rule_id'], 'reason' => str_repeat('x', 255),
            'request_id' => 'retire-card', 'explanation' => 'No longer needed.',
        ]);
        self::assertTrue($retirement['ok']);
        SaldiSchemaValidator::validate(
            $retirement,
            SaldiToolRegistry::default()->get('propose_rule_retirement')->outputSchema()
        );
        self::assertSame('applied', $this->approve($retirement['data'])['status']);
    }
}
