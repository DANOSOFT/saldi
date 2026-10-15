<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiAssistReportTest.test.php --- ai/mcp-assistant --- 2026.10.03
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
// Deterministic report periods, audit aggregates, percentiles and honest estimates.
//
// 20261002 CDX/LH Created.
// 20261003 CDX/LH Add regression coverage for the confirmed Assist review findings.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiReachTestCase.php';
require_once __DIR__ . '/../../../includes/automation/SaldiAssistReport.php';

final class SaldiAssistReportTest extends SaldiReachTestCase
{
    private function decision(
        string $kind,
        string $event,
        string $time,
        int $itemId,
        string $actor = 'user:910001'
    ): void {
        $this->env->db->insert('decision_log', ['created_at' => $time, 'kind' => $kind,
            'event' => $event, 'inbox_item_id' => $itemId, 'actor' => $actor]);
    }

    private function event(string $event, ?string $source, string $time = '2026-10-01 12:00:00'): void
    {
        $this->env->db->insert('assist_events', ['created_at' => $time, 'event' => $event,
            'source' => $source, 'user_id' => 910001]);
    }

    public function testEmptyReportAndInvalidPeriodsAreExplicit(): void
    {
        $report = SaldiAssistReport::build($this->env->db, '2026-10-01', '2026-10-02');
        foreach (['kinds', 'suggestions', 'tools', 'routines'] as $key) {
            self::assertSame([], $report[$key]);
        }
        self::assertSame(['active_count' => 0, 'uses' => 0], $report['rules']);
        self::assertSame(0.0, $report['estimated_time_saved']['minutes']);
        self::assertFalse($report['estimated_time_saved']['measured']);
        self::assertSame(['minutes_per_suggestion' => 0.5, 'minutes_per_draft' => 2.0,
            'minutes_per_other_draft' => 1.0], $report['estimated_time_saved']['assumptions']);
        foreach (
            [['2026-02-30', '2026-10-02'], ['2026-10-03', '2026-10-01'],
            ['yesterday', '2026-10-02'], ['2026-10-01 25:00:00', '2026-10-02'],
            ['2026-10-01T00:00:00Z', '2026-10-01T00:00:00Z']] as [$from, $to]
        ) {
            $this->error('VALIDATION_ERROR', fn() => SaldiAssistReport::build($this->env->db, $from, $to));
        }
    }

    public function testDecisionCountsInclusiveEndDateSharesAndMedian(): void
    {
        foreach (
            [['applied', '00:30:00'], ['rejected', '01:30:00'],
            ['expired', '02:30:00'], ['cancelled', '03:30:00']] as $index => [$event, $clock]
        ) {
            $this->decision('journal.draft_entry', 'proposed', '2026-09-30 23:30:00', 1000 + $index);
            $this->decision('journal.draft_entry', $event, '2026-10-01 ' . $clock, 1000 + $index);
        }
        $this->decision('journal.draft_entry', 'undone', '2026-10-02 12:00:00', 1000);
        $this->decision('journal.draft_entry', 'proposed', '2026-10-02 23:59:59', 1004);
        $this->decision('journal.draft_entry', 'applied', '2026-10-03 00:00:00', 1004);
        $this->decision('journal.draft_entry', 'apply_failed', '2026-10-02 12:00:00', 1004);
        $report = SaldiAssistReport::build($this->env->db, '2026-10-01', '2026-10-02');
        self::assertCount(1, $report['kinds']);
        self::assertSame(['kind' => 'journal.draft_entry', 'proposed' => 1, 'applied' => 1,
            'rejected' => 1, 'expired' => 1, 'cancelled' => 1, 'undone' => 1,
            'accept_share' => 1 / 3, 'median_minutes' => 150.0], $report['kinds'][0]);
        $instant = SaldiAssistReport::build($this->env->db, '2026-10-02T00:00:00Z', '2026-10-03T00:00:00Z');
        self::assertSame(0, $instant['kinds'][0]['applied']);
        self::assertNull($instant['kinds'][0]['accept_share']);
    }

    public function testSuggestionSourcesToolsRulesAndEstimatesAreNotMeasuredSavings(): void
    {
        foreach (['suggestion_shown', 'suggestion_shown', 'suggestion_used', 'suggestion_dismissed'] as $event) {
            $this->event($event, 'rule');
        }
        $this->event('suggestion_used', 'history');
        $this->event('duplicate_warned', null);
        $this->event('digest_sent', null);
        $this->event('suggestion_used', 'rule', '2026-10-03 00:00:00');
        foreach ([100, 200, 300, 400, null] as $index => $duration) {
            $this->env->db->insert('tool_calls', ['created_at' => '2026-10-01 12:00:00',
                'principal' => 'user:910001', 'tool' => 'get_period_status',
                'args_hash' => hash('sha256', (string)$index), 'status' => $index === 0 ? 'error' : 'ok',
                'duration_ms' => $duration]);
        }
        $this->env->db->insert('posting_rules', ['status' => 'active', 'match_type' => 'text',
            'match_value' => 'fixture', 'account' => '1000', 'created_by' => 'user:910001',
            'created_at' => '2026-10-01 00:00:00']);
        $this->env->db->insert('posting_rules', ['status' => 'retired', 'match_type' => 'text',
            'match_value' => 'retired', 'account' => '1000', 'created_by' => 'user:910001',
            'created_at' => '2026-10-01 00:00:00']);
        foreach (
            ['journal.draft_entry', 'party.create', 'purchase_order.draft', 'reminder.draft',
            'routine.finding', 'support.report', 'rule.posting', 'memory.fact'] as $index => $kind
        ) {
            $this->decision($kind, 'applied', '2026-10-01 12:00:00', 2000 + $index);
        }
        $this->setting('assistant', 'minutes_per_suggestion', '1.25');
        $this->setting('assistant', 'minutes_per_draft', '3');
        $report = SaldiAssistReport::build($this->env->db, '2026-10-01', '2026-10-02');
        $sources = array_column($report['suggestions'], null, 'source');
        self::assertSame(['source' => 'rule', 'shown' => 2, 'used' => 1, 'dismissed' => 1], $sources['rule']);
        self::assertSame(1, $sources['history']['used']);
        self::assertSame(1, $report['duplicates_warned']);
        self::assertSame(['active_count' => 1, 'uses' => 1], $report['rules']);
        self::assertSame(['tool' => 'get_period_status', 'calls' => 5, 'errors' => 1,
            'error_share' => 0.2, 'p50_duration_ms' => 250.0, 'p90_duration_ms' => 370.0], $report['tools'][0]);
        self::assertSame(8.5, $report['estimated_time_saved']['minutes']);
        self::assertFalse($report['estimated_time_saved']['measured']);
        self::assertSame(1.25, $report['estimated_time_saved']['assumptions']['minutes_per_suggestion']);
        $this->setting('assistant', 'minutes_per_suggestion', '-10');
        self::assertSame(0.5, SaldiAssistReport::build(
            $this->env->db,
            '2026-10-01',
            '2026-10-02'
        )['estimated_time_saved']['assumptions']['minutes_per_suggestion']);
    }

    public function testRoutineFindingsAcknowledgementsAndCandidateProposalsAreSeparate(): void
    {
        $item = $this->inbox()->create($this->env->ctx(), 'followup.task', ['title' => 'Report fixture']);
        $this->env->db->exec('DELETE FROM decision_log');
        $this->env->db->exec('UPDATE inbox_items SET kind = ?, payload = ? WHERE id = ?', [
            'routine.finding', saldi_runtime_json(['routine' => 'low_stock']), $item['id'],
        ]);
        $this->decision('routine.finding', 'proposed', '2026-10-01 12:00:00', $item['id'], 'rule:low_stock');
        $this->decision('routine.finding', 'proposed', '2026-10-01 13:00:00', $item['id'], 'system:worker');
        $this->decision('routine.finding', 'revised', '2026-10-01 14:00:00', $item['id'], 'rule:low_stock');
        $this->decision('routine.finding', 'applied', '2026-10-02 12:00:00', $item['id']);
        $this->decision('rule.posting', 'proposed', '2026-10-01 12:00:00', 3001, 'rule:posting_rule_candidates');
        $this->decision('rule.posting', 'proposed', '2026-10-01 12:00:00', 3002);
        $report = SaldiAssistReport::build($this->env->db, '2026-10-01', '2026-10-02');
        self::assertSame(1, array_column($report['kinds'], null, 'kind')['routine.finding']['proposed']);
        self::assertSame([
            ['routine' => 'low_stock', 'created' => 1, 'acknowledged' => 1, 'rule_proposals' => 0],
            ['routine' => 'posting_rule_candidates', 'created' => 0, 'acknowledged' => 0, 'rule_proposals' => 1],
        ], $report['routines']);
    }
}
