<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/ui/AssistReportRenderTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// The Assist report page body (systemdata/assistent_rapport.php saldi_assist_report_html, SPEC-companion
// C8.4): one table per section, empty sections say so, untrusted names escaped, the 30/90-day window,
// and the time saved shown as an estimate with its assumptions and arithmetic (text 6004), never as a
// measurement. The last test renders a real SaldiAssistReport::build() result from a plan_tpl clone.
//
// 20261002 CL/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
if (!defined('SALDI_ASSIST_REPORT_LIBRARY')) {
    define('SALDI_ASSIST_REPORT_LIBRARY', true);
}
require_once __DIR__ . '/../../../systemdata/assistent_rapport.php';

final class AssistReportRenderTest extends SaldiRuntimeDbTestCase
{
    protected function setUp(): void
    {
        // Rendering needs no database; only the last test opens one.
    }

    private function report(): array
    {
        return [
            'from' => '2026-09-03', 'to' => '2026-10-02',
            'kinds' => [
                ['kind' => 'journal.draft_entry', 'proposed' => 10, 'applied' => 6, 'rejected' => 2, 'expired' => 0, 'cancelled' => 1, 'undone' => 1, 'accept_share' => 0.75, 'median_minutes' => 12.0],
                ['kind' => 'party.create', 'proposed' => 2, 'applied' => 2, 'rejected' => 0, 'expired' => 0, 'cancelled' => 0, 'undone' => 0, 'accept_share' => 1.0, 'median_minutes' => 3.5],
                ['kind' => '<b>odd.kind</b>', 'proposed' => 1, 'applied' => 0, 'rejected' => 0, 'expired' => 0, 'cancelled' => 1, 'undone' => 0, 'accept_share' => null, 'median_minutes' => null],
            ],
            'suggestions' => [
                ['source' => 'history', 'shown' => 40, 'used' => 12, 'dismissed' => 3],
                ['source' => 'rule', 'shown' => 20, 'used' => 18, 'dismissed' => 0],
            ],
            'duplicates_warned' => 4,
            'tools' => [['tool' => 'suggest_posting<i>', 'calls' => 1200, 'errors' => 12, 'error_share' => 0.01, 'p50_duration_ms' => 41.0, 'p90_duration_ms' => 88.4]],
            'routines' => [['routine' => 'posting_rule_candidates', 'created' => 0, 'acknowledged' => 0, 'rule_proposals' => 3]],
            'rules' => ['active_count' => 5, 'uses' => 18],
            'estimated_time_saved' => ['minutes' => 29.0, 'measured' => false, 'assumptions' => ['minutes_per_suggestion' => 0.5, 'minutes_per_draft' => 2.0, 'minutes_per_other_draft' => 1.0]],
        ];
    }

    public function testSectionsTablesAndEscaping(): void
    {
        $html = saldi_assist_report_html($this->report(), null, 1);
        foreach (['Forslag pr. type', 'Forslag i kassekladden', 'Værktøjskald', 'Rutiner', 'Bogføringsregler', 'Anslået sparet tid'] as $heading) {
            self::assertStringContainsString('>' . $heading . '</h2>', $html);
        }
        self::assertStringContainsString('Kladdeforslag', $html);
        self::assertStringContainsString('Ny kunde / Ny leverandør', $html);
        self::assertStringContainsString('75 %', $html);
        self::assertStringContainsString('12 min.', $html);
        self::assertStringContainsString('3,5 min.', $html);
        self::assertStringContainsString('&lt;b&gt;odd.kind&lt;/b&gt;', $html);
        self::assertStringContainsString('suggest_posting&lt;i&gt;', $html);
        self::assertStringNotContainsString('<i>', $html);
        self::assertStringContainsString('1.200', $html);
        self::assertStringContainsString('12 (1 %)', $html);
        self::assertStringContainsString('41 / 88', $html);
        self::assertStringContainsString('Fra regel', $html);
        self::assertStringContainsString('Tidligere posteringer', $html);
        self::assertStringContainsString('Mulig dublet: 4', $html);
        self::assertStringContainsString('Gentagne posteringer, der kan blive til regler', $html);
        self::assertStringNotContainsString('sac--done', $html);
    }

    public function testTheEstimateIsLabelledAndShowsItsArithmetic(): void
    {
        $html = saldi_assist_report_html($this->report(), null, 1);
        $estimate = substr($html, strpos($html, 'id="sai-r-estimate"'));
        self::assertStringContainsString('Anslået ud fra antagelserne herunder, ikke målt.', $estimate);
        self::assertStringContainsString('Minutter pr. stk. (antagelse)', $estimate);
        // 30 used suggestions x 0,5 + 6 applied journal drafts x 2 + 2 other drafts x 1 = 29 minutes.
        self::assertMatchesRegularExpression('#Forslag i kassekladden \(Brugt\)</td><td class="sai-num">30</td><td class="sai-num">0,5</td><td class="sai-num">15</td>#', $estimate);
        self::assertMatchesRegularExpression('#Kladdeforslag \(Godkendt\)</td><td class="sai-num">6</td><td class="sai-num">2</td><td class="sai-num">12</td>#', $estimate);
        self::assertMatchesRegularExpression('#Rykker \(Godkendt\)</td><td class="sai-num">2</td><td class="sai-num">1</td><td class="sai-num">2</td>#', $estimate);
        self::assertStringContainsString('<strong>29 min.</strong>', $estimate);
        self::assertStringNotContainsString(' målt', str_replace('ikke målt', '', $estimate));
    }

    public function testEmptySectionsSaySo(): void
    {
        $html = saldi_assist_report_html(['from' => '2026-09-03', 'to' => '2026-10-02', 'kinds' => [], 'suggestions' => [], 'duplicates_warned' => 0, 'tools' => [], 'routines' => [],
            'rules' => ['active_count' => 0, 'uses' => 0], 'estimated_time_saved' => ['minutes' => 0.0, 'measured' => false, 'assumptions' => []]], null, 1);
        self::assertSame(4, substr_count($html, 'Ingen data i perioden.'));
        self::assertStringNotContainsString('Mulig dublet', $html);
        self::assertStringContainsString('<strong>0 min.</strong>', $html);
    }

    public function testPeriodIsThirtyOrNinetyDaysEndingToday(): void
    {
        self::assertSame(30, saldi_assist_report_days([]));
        self::assertSame(90, saldi_assist_report_days(['dage' => '90']));
        self::assertSame(30, saldi_assist_report_days(['dage' => '365']));
        self::assertSame(30, saldi_assist_report_days(['dage' => ['90']]));
        $today = new DateTimeImmutable('2026-10-02', new DateTimeZone('Europe/Copenhagen'));
        self::assertSame(['2026-09-03', '2026-10-02'], saldi_assist_report_range(30, $today));
        self::assertSame(['2026-07-05', '2026-10-02'], saldi_assist_report_range(90, $today));
    }

    public function testRendersARealReportFromTheService(): void
    {
        parent::setUp();
        $ctx = $this->env->ctx();
        $item = $this->inbox()->create($ctx, 'followup.task', ['title' => 'Rapportopgave']);
        $this->inbox()->decide($ctx, $item['id'], 'reject', $item['digest'], $ctx->tenantHash());
        $this->env->db->insert('assist_events', ['created_at' => gmdate('Y-m-d H:i:s'), 'user_id' => 910001, 'event' => 'suggestion_used', 'subject' => 'rule:1', 'source' => 'rule']);
        [$from, $to] = saldi_assist_report_range(30);
        $html = saldi_assist_report_html(SaldiAssistReport::build($this->env->db, $from, $to), $this->env->db, 1);
        self::assertStringContainsString('Opfølgning', $html);
        self::assertStringContainsString('Fra regel', $html);
        self::assertStringContainsString('<strong>0,5 min.</strong>', $html);
    }
}
