<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/ui/AssistCompanion2SettingsPageRenderTest.test.php --- ai/mcp-assistant --- 2026.10.04
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
// Rendering, access boundaries and posted page actions against disposable tenant fixtures.
//
// 20261004 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
if (!defined('SALDI_ASSISTENT_LIBRARY')) {
    define('SALDI_ASSISTENT_LIBRARY', true);
}
require_once __DIR__ . '/../../../systemdata/assistent.php';

final class AssistCompanion2SettingsPageRenderTest extends SaldiRuntimeDbTestCase
{
    private function form(string $lead): array
    {
        return ['op' => 'switches', 'csrf' => 'settings-page-test', 'thisDb' => 'plan_x',
            'on' => ['automation/routine.reorder_suggestions', 'automation/routine.master_data_hygiene'],
            'vat_period' => 'quarter', 'interrupt_budget' => '3', 'till_difference_threshold' => '50',
            'lead_days' => $lead];
    }

    public function testLeadDaysValidationBoundsAndLegacyFormDefault(): void
    {
        self::assertSame('1', saldi_assistent_admin_values($this->form('1'))['lead_days']);
        self::assertSame('120', saldi_assistent_admin_values($this->form('120'))['lead_days']);
        foreach (['0', '121', '-1', '1.5', '1e2'] as $bad) {
            self::assertNull(saldi_assistent_admin_values($this->form($bad)));
        }
        $old = $this->form('14');
        unset($old['lead_days']);
        self::assertSame('14', saldi_assistent_admin_values($old)['lead_days']);
    }

    public function testRoutineAndLeadSettingsSaveAndInvalidPostSavesNothing(): void
    {
        $_SESSION = ['inbox_csrf' => 'settings-page-test'];
        $open = ['ctx' => $this->env->ctx(), 'db' => $this->env->db];
        $saved = saldi_assistent_post($this->form('25'), 'plan_x', $open, 1);
        self::assertSame('neutral', $saved['flash']['tone']);
        self::assertSame('25', SaldiSettings::get($this->env->db, 'assistant', 'lead_days'));
        foreach (['reorder_suggestions', 'master_data_hygiene'] as $routine) {
            self::assertSame('on', SaldiSettings::get($this->env->db, 'automation', 'routine.' . $routine));
        }
        $bad = $this->form('121');
        $bad['on'] = [];
        self::assertSame('warn', saldi_assistent_post($bad, 'plan_x', $open, 1)['flash']['tone']);
        self::assertSame('25', SaldiSettings::get($this->env->db, 'assistant', 'lead_days'));
        self::assertSame('on', SaldiSettings::get($this->env->db, 'automation', 'routine.reorder_suggestions'));
        foreach ([['csrf' => 'bad'], ['thisDb' => 'other']] as $override) {
            $post = array_replace($this->form('12'), $override);
            self::assertSame('warn', saldi_assistent_post($post, 'plan_x', $open, 1)['flash']['tone']);
        }
        self::assertSame('25', SaldiSettings::get($this->env->db, 'assistant', 'lead_days'));
        $_SESSION = [];
    }

    public function testSettingsMarkupExposesBoundFieldAndRoutineToggles(): void
    {
        $page = file_get_contents(__DIR__ . '/../../../systemdata/assistent.php');
        self::assertStringContainsString('name="lead_days" min="1" max="120"', $page);
        self::assertStringContainsString('href="revisorpakke.php"', $page);
        self::assertContains('routine.reorder_suggestions', array_column(SALDI_ASSIST_SWITCHES, 1));
        self::assertContains('routine.master_data_hygiene', array_column(SALDI_ASSIST_SWITCHES, 1));
    }
}
