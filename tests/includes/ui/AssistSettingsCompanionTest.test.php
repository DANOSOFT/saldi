<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/ui/AssistSettingsCompanionTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Saldi Assist settings page companion sections (systemdata/assistent.php, SPEC-companion C8.3) against
// a disposable plan_tpl clone: "Stop regel" through the page controller (journal.draft only, decision
// log), the per-user daily overview mail (own preference only, a bad address saves nothing), the admin
// values (VAT period, interrupt budget, till threshold, journal suggestions) checked before anything is
// saved, and the rules table / digest form markup.
//
// 20261002 CL/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
if (!defined('SALDI_ASSISTENT_LIBRARY')) {
    define('SALDI_ASSISTENT_LIBRARY', true);
}
require_once __DIR__ . '/../../../systemdata/assistent.php';

final class AssistSettingsCompanionTest extends SaldiRuntimeDbTestCase
{
    private const CSRF = 'test-csrf-settings-0123456789';
    /** Journal read, no journal write, no settings.admin. */
    private const READER = '1021111111111111';

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = ['inbox_csrf' => self::CSRF];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    private function post(array $post, ?SaldiTenantContext $ctx = null): array
    {
        return saldi_assistent_post($post + ['csrf' => self::CSRF, 'thisDb' => 'plan_x'], 'plan_x', ['ctx' => $ctx ?? $this->env->ctx(), 'db' => $this->env->db], 1);
    }

    private function rule(string $value = 'adobe'): int
    {
        return $this->env->db->insert('posting_rules', ['status' => 'active', 'match_type' => 'text', 'match_value' => $value, 'account' => '2750', 'vat_code' => '',
            'description' => 'Software', 'note' => 'Set 4 gange', 'created_by' => 'user:910001', 'approved_by' => 910001, 'created_at' => gmdate('Y-m-d H:i:s')]);
    }

    private function adminPost(array $override = []): array
    {
        return $override + ['op' => 'switches', 'on' => ['assistant/journal_suggestions'], 'vat_period' => 'month', 'interrupt_budget' => '5', 'till_difference_threshold' => '120'];
    }

    public function testStopRuleRetiresForJournalWritersOnly(): void
    {
        $id = $this->rule();
        $denied = $this->post(['op' => 'retire_rule', 'rule_id' => (string)$id], $this->env->ctx(910001, self::READER));
        self::assertSame('warn', $denied['flash']['tone']);
        self::assertSame('active', $this->env->db->value('SELECT status FROM posting_rules WHERE id = ?', [$id]));

        $done = $this->post(['op' => 'retire_rule', 'rule_id' => (string)$id]);
        self::assertSame(['tone' => 'neutral', 'text' => 'Reglen er stoppet.'], $done['flash']);
        $row = $this->env->db->one('SELECT status, retired_by, retire_reason FROM posting_rules WHERE id = ?', [$id]);
        self::assertSame(['status' => 'retired', 'retired_by' => 910001, 'retire_reason' => 'retired'], $row);
        self::assertSame(1, (int)$this->env->db->value("SELECT COUNT(*) FROM decision_log WHERE event = 'rule_retired' AND subject_id = ?", [(string)$id]));
        // Again: idempotent, still one log row.
        self::assertSame('neutral', $this->post(['op' => 'retire_rule', 'rule_id' => (string)$id])['flash']['tone']);
        self::assertSame(1, (int)$this->env->db->value("SELECT COUNT(*) FROM decision_log WHERE event = 'rule_retired' AND subject_id = ?", [(string)$id]));
        self::assertSame('warn', $this->post(['op' => 'retire_rule', 'rule_id' => '0'])['flash']['tone']);
    }

    public function testCsrfAndCompanyBindingComeFirst(): void
    {
        $id = $this->rule();
        self::assertSame('warn', saldi_assistent_post(['op' => 'retire_rule', 'rule_id' => (string)$id, 'csrf' => 'wrong', 'thisDb' => 'plan_x'], 'plan_x', ['ctx' => $this->env->ctx(), 'db' => $this->env->db], 1)['flash']['tone']);
        self::assertSame('warn', saldi_assistent_post(['op' => 'retire_rule', 'rule_id' => (string)$id, 'csrf' => self::CSRF, 'thisDb' => 'other'], 'plan_x', ['ctx' => $this->env->ctx(), 'db' => $this->env->db], 1)['flash']['tone']);
        self::assertSame('active', $this->env->db->value('SELECT status FROM posting_rules WHERE id = ?', [$id]));
    }

    public function testDigestPreferenceIsPerUserAndABadAddressSavesNothing(): void
    {
        $reader = $this->env->ctx(910002, self::READER);
        self::assertSame('neutral', $this->post(['op' => 'digest', 'digest' => 'daily', 'digest_email' => ' bog@firma.dk '], $reader)['flash']['tone']);
        self::assertSame('daily', SaldiSettings::getUser($this->env->db, $reader, 'assistant', 'digest'));
        self::assertSame('bog@firma.dk', SaldiSettings::getUser($this->env->db, $reader, 'assistant', 'digest_email'));
        self::assertSame('off', SaldiSettings::getUser($this->env->db, $this->env->ctx(), 'assistant', 'digest'));

        self::assertSame('warn', $this->post(['op' => 'digest', 'digest_email' => 'ikke en adresse'], $reader)['flash']['tone']);
        self::assertSame('daily', SaldiSettings::getUser($this->env->db, $reader, 'assistant', 'digest'));
        self::assertSame('bog@firma.dk', SaldiSettings::getUser($this->env->db, $reader, 'assistant', 'digest_email'));

        $this->post(['op' => 'digest', 'digest_email' => ''], $reader);
        self::assertSame('off', SaldiSettings::getUser($this->env->db, $reader, 'assistant', 'digest'));
        self::assertSame('', SaldiSettings::getUser($this->env->db, $reader, 'assistant', 'digest_email'));
    }

    public function testAdminValuesAreCheckedBeforeAnythingIsSaved(): void
    {
        $db = $this->env->db;
        self::assertSame('neutral', $this->post($this->adminPost())['flash']['tone']);
        self::assertSame('on', SaldiSettings::get($db, 'assistant', 'journal_suggestions'));
        self::assertSame('month', SaldiSettings::get($db, 'assistant', 'vat_period', 'quarter'));
        self::assertSame('5', SaldiSettings::get($db, 'assistant', 'interrupt_budget', '3'));
        self::assertSame('120', SaldiSettings::get($db, 'assistant', 'till_difference_threshold', '50'));
        self::assertSame('off', SaldiSettings::get($db, 'automation', 'routine.low_stock'));

        foreach ([['interrupt_budget' => '11'], ['interrupt_budget' => '-1'], ['till_difference_threshold' => '50,5'], ['vat_period' => 'year'], ['till_difference_threshold' => '1000001']] as $bad) {
            $result = $this->post($this->adminPost($bad + ['on' => ['automation/routine.low_stock']]));
            self::assertSame('warn', $result['flash']['tone'], json_encode($bad));
            self::assertSame('off', SaldiSettings::get($db, 'automation', 'routine.low_stock'), json_encode($bad));
            self::assertSame('on', SaldiSettings::get($db, 'assistant', 'journal_suggestions'));
        }
        // Non-admins cannot change tenant settings.
        self::assertSame('warn', $this->post($this->adminPost(['vat_period' => 'half']), $this->env->ctx(910002, self::READER))['flash']['tone']);
        self::assertSame('month', SaldiSettings::get($db, 'assistant', 'vat_period', 'quarter'));
    }

    public function testRulesTableAndDigestFormMarkup(): void
    {
        $rules = [[
            'rule_id' => 7, 'match' => ['type' => 'text', 'value' => 'adobe', 'label' => 'Teksten indeholder »<adobe>«'], 'account' => '2750', 'account_name' => 'Software',
            'vat_code' => 'none', 'description' => '', 'note' => '<b>4 gange</b>', 'created_at' => '2026-10-01T10:00:00Z', 'approved_by_name' => 'x', 'uses_90d' => 12,
        ]];
        $html = saldi_assistent_rules_html($rules, true, '<input type="hidden" name="csrf" value="c">', null, 1);
        self::assertStringContainsString('Teksten indeholder »&lt;adobe&gt;«', $html);
        self::assertStringContainsString('&lt;b&gt;4 gange&lt;/b&gt;', $html);
        self::assertStringContainsString('2750 Software', $html);
        self::assertStringContainsString('Momsfri', $html);
        self::assertStringContainsString('01.10.2026', $html);
        self::assertStringContainsString('<td class="sai-num">12</td>', $html);
        self::assertStringContainsString('name="op" value="retire_rule"', $html);
        self::assertStringContainsString('name="rule_id" value="7"', $html);
        self::assertStringContainsString('data-confirm="Stop reglen?', $html);
        self::assertStringNotContainsString('retire_rule', saldi_assistent_rules_html($rules, false, '', null, 1));
        self::assertStringContainsString('Kontoens momskode', saldi_assistent_rules_html([array_merge($rules[0], ['vat_code' => ''])], false, '', null, 1));
        self::assertStringContainsString('Ingen aktive regler.', saldi_assistent_rules_html([], true, '', null, 1));

        $form = saldi_assistent_digest_html(['on' => true, 'email' => '"x@y.dk', 'default_email' => 'me@firma.dk'], '', null, 1);
        self::assertStringContainsString('name="digest" value="daily" checked', $form);
        self::assertStringContainsString('value="&quot;x@y.dk"', $form);
        self::assertStringContainsString('placeholder="me@firma.dk"', $form);
        self::assertStringNotContainsString(' checked', saldi_assistent_digest_html(['on' => false, 'email' => '', 'default_email' => ''], '', null, 1));
    }

    public function testRulesComeFromTheRuleToolForTheViewersFiscalYear(): void
    {
        $db = $this->env->db;
        $ctx = $this->env->ctx();
        $account = (string)$db->value("SELECT kontonr FROM kontoplan WHERE regnskabsaar = ? AND kontotype = 'D' AND COALESCE(lukket, '') <> 'on' ORDER BY kontonr LIMIT 1", [$ctx->fiscalYear]);
        self::assertNotSame('', $account);
        $id = $db->insert('posting_rules', ['status' => 'active', 'match_type' => 'text', 'match_value' => 'husleje', 'account' => $account, 'vat_code' => '',
            'description' => null, 'note' => null, 'created_by' => 'user:910001', 'approved_by' => 910001, 'created_at' => gmdate('Y-m-d H:i:s')]);
        $rows = SaldiRuleTools::listRules($ctx, $db, ['status' => 'active', 'limit' => 100])['data']['rows'];
        self::assertSame([$id], array_column($rows, 'rule_id'));
        $html = saldi_assistent_rules_html($rows, true, '', $db, 1);
        self::assertStringContainsString('husleje', $html);
        self::assertStringContainsString('&#8211;', $html);
    }
}
