<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/ui/AssistPagesV2RenderTest.test.php --- ai/mcp-assistant --- 2026.10.07
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
// The V1 Stille markup of the Saldi Assist pages (settings, report, bank file, Revisorpakke): the bank
// line picker grouped by confidence (only high preselected, never more than 200, nothing pickable outside
// Høj/Middel), the settings switches, choice pills and status strip, the report strip and the Revisorpakke
// strip, CSV buttons and table foot. Rendering only; no database.
//
// 20261007 CL/LH Created.
// 20261007 CL/LH Review regressions: medium has no bulk toggle, bank failures by reason and field, submit
//                enabled without script, singular counter, Danish tool labels and folded tool list, partial
//                Revisorpakke marked (strip and CSV) and no fallback, one save pattern and caption level.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
foreach (['SALDI_ASSISTENT_LIBRARY', 'SALDI_ASSIST_REPORT_LIBRARY', 'SALDI_BANKFIL_LIBRARY', 'SALDI_REVISORPAKKE_LIBRARY'] as $library) {
    if (!defined($library)) {
        define($library, true);
    }
}
require_once __DIR__ . '/../../../systemdata/assistent.php';
require_once __DIR__ . '/../../../systemdata/assistent_rapport.php';
require_once __DIR__ . '/../../../finans/assist_bankfil.php';
require_once __DIR__ . '/../../../systemdata/revisorpakke.php';
require_once __DIR__ . '/../../../includes/tools/SaldiToolRegistry.php';

final class AssistPagesV2RenderTest extends SaldiRuntimeDbTestCase
{
    protected function setUp(): void
    {
        // Rendering needs no database.
    }

    private function line(int $id, string $status, ?string $confidence, bool $journal = false): array
    {
        return ['line_id' => $id, 'date' => '2026-10-05', 'description' => 'Linje ' . $id, 'amount' => '100.00',
            'currency' => 'DKK', 'status' => $status, 'journal_duplicate' => $journal,
            'match' => $confidence === null ? null : ['party' => null, 'account' => ['account' => '7200', 'name' => 'Gebyrer'],
                'invoice' => null, 'confidence' => $confidence]];
    }

    /** @return array<string,string> group key => that group's tbody markup */
    private function tbodies(string $html): array
    {
        preg_match_all('#<tbody data-sap-group="([a-z]+)">(.*?)</tbody>#s', $html, $m, PREG_SET_ORDER);
        return array_column(array_map(static fn(array $g): array => [$g[1], $g[2]], $m), 1, 0);
    }

    public function testBankLinesAreGroupedByConfidenceInAFixedOrder(): void
    {
        $rows = [
            $this->line(1, 'new', null), $this->line(2, 'new', 'medium'), $this->line(3, 'duplicate', null, true),
            $this->line(4, 'new', 'high'), $this->line(5, 'ignored', null), $this->line(6, 'proposed', 'high'),
            $this->line(7, 'duplicate', null),
        ];
        $html = saldi_bankfil_preview_html($rows, true, null, 1);
        $groups = $this->tbodies($html);
        self::assertSame(['high', 'medium', 'none', 'journal', 'other'], array_keys($groups));
        self::assertStringContainsString('value="4"', $groups['high']);
        self::assertStringContainsString('value="2"', $groups['medium']);
        self::assertStringContainsString('Høj sikkerhed', $groups['high']);
        self::assertStringContainsString('Middel sikkerhed', $groups['medium']);
        self::assertStringContainsString('Ingen sikker modpost', $groups['none']);
        self::assertStringContainsString('Allerede i en kassekladde', $groups['journal']);
        // Only Høj and Middel lines can be picked; only Høj starts selected.
        foreach (['none', 'journal', 'other'] as $key) {
            self::assertStringNotContainsString('type="checkbox"', $groups[$key]);
            self::assertStringNotContainsString('data-sap-toggle', $groups[$key]);
        }
        self::assertSame(1, substr_count($groups['high'], ' checked'));
        self::assertSame(0, substr_count($groups['medium'], ' checked'));
        self::assertStringContainsString('>Fravælg alle</button>', $groups['high']);
        // Middel lines are opt-in one by one: no bulk toggle there.
        self::assertStringNotContainsString('data-sap-toggle', $groups['medium']);
        // Other lines say why, without the old trailing colon.
        self::assertStringContainsString('<span class="sai-tag">Mulig dublet</span>', $groups['other']);
        self::assertStringContainsString('<span class="sai-tag">Dato uden for regnskabsåret</span>', $groups['other']);
        self::assertStringNotContainsString('Mulig dublet:', $html);
    }

    public function testBankPickerNeverPreselectsMoreThanTwoHundredAndNothingWhenProposingIsBlocked(): void
    {
        $rows = [];
        for ($i = 1; $i <= 230; $i++) {
            $rows[] = $this->line($i, 'new', 'high');
        }
        $html = saldi_bankfil_preview_html($rows, true, null, 1);
        self::assertSame(200, substr_count($html, ' checked'));
        self::assertSame(230, substr_count($html, 'type="checkbox"'));
        self::assertStringContainsString('>Vælg alle</button>', $html);
        $blocked = saldi_bankfil_preview_html($rows, false, null, 1);
        self::assertStringNotContainsString('type="checkbox"', $blocked);
        self::assertStringNotContainsString('data-sap-toggle', $blocked);
    }

    public function testBankPickerEmptyStateAndGroupKeys(): void
    {
        self::assertStringContainsString('Filen indeholdt ingen linjer.', saldi_bankfil_preview_html([], true, null, 1));
        self::assertSame('journal', saldi_bankfil_group($this->line(1, 'new', 'high', true)));
        self::assertSame('other', saldi_bankfil_group($this->line(1, 'proposed', 'high')));
        self::assertSame('none', saldi_bankfil_group($this->line(1, 'new', null)));
        self::assertSame('medium', saldi_bankfil_group($this->line(1, 'new', 'medium')));
    }

    public function testSettingsSwitchesAndChoicePillsPostTheSameFields(): void
    {
        $switches = ['assistant/tools_enabled' => true, 'automation/routine.low_stock' => true];
        $values = ['vat_period' => 'half', 'interrupt_budget' => '3', 'till_difference_threshold' => '50', 'lead_days' => '14'];
        $html = saldi_assistent_admin_html($switches, $values, '<input type="hidden" name="csrf" value="c">', null, 1);
        self::assertStringContainsString('name="op" value="switches"', $html);
        self::assertSame(count(SALDI_ASSIST_SWITCHES), substr_count($html, 'class="sap-toggle" role="switch"'));
        self::assertStringContainsString('name="on[]" value="assistant/tools_enabled" checked', $html);
        self::assertStringContainsString('name="on[]" value="assistant/external_agents" aria-describedby="sai-switch-1-help">', $html);
        self::assertStringContainsString('<input type="radio" name="vat_period" value="half" checked>', $html);
        self::assertSame(3, substr_count($html, 'name="vat_period"'));
        self::assertStringContainsString('name="lead_days" min="1" max="120"', $html);
        self::assertStringContainsString('1 af 10', $html);
        self::assertStringContainsString('aria-describedby="sai-switch-7-help"', $html);
        self::assertStringContainsString('data-sap-dirty', $html);
    }

    public function testSettingsStripShowsStateWithoutGreenAndLinksToSections(): void
    {
        $html = saldi_assistent_strip_html(['tools' => true, 'external' => false, 'grants' => 2, 'facts' => 5, 'rules' => null,
            'routines_on' => 7, 'routines_total' => 10, 'admin' => true], null, 1);
        self::assertStringContainsString('<span class="sai-ov__value">Til</span><a class="sai-ov__label" href="#sai-switches">Saldi Assist</a>', $html);
        self::assertStringContainsString('sai-ov--off"><span class="sai-ov__value">Fra</span>', $html);
        self::assertStringContainsString('href="#sai-access">Aktive adgange', $html);
        self::assertStringContainsString('7 af 10', $html);
        self::assertStringNotContainsString('done', $html);
        self::assertStringNotContainsString('#sai-rules', $html);
        $user = saldi_assistent_strip_html(['tools' => true, 'external' => true, 'grants' => 0, 'facts' => 0, 'rules' => 3,
            'routines_on' => 7, 'routines_total' => 10, 'admin' => false], null, 1);
        self::assertStringNotContainsString('#sai-switches', $user);
        self::assertStringNotContainsString('af 10', $user);
        self::assertStringContainsString('href="#sai-rules"', $user);
    }

    public function testDigestFormIsASwitch(): void
    {
        $form = saldi_assistent_digest_html(['on' => true, 'email' => '', 'default_email' => ''], '', null, 1);
        self::assertStringContainsString('class="sap-toggle" role="switch" id="sai-digest-on" name="digest" value="daily" checked', $form);
    }

    public function testReportStripSumsTheSectionsAndLabelsTheEstimate(): void
    {
        $report = ['kinds' => [['kind' => 'a', 'proposed' => 8, 'applied' => 2], ['kind' => 'b', 'proposed' => 2, 'applied' => 1]],
            'suggestions' => [['used' => 4], ['used' => 1]], 'tools' => [['calls' => 1200], ['calls' => 34]],
            'rules' => ['active_count' => 3, 'uses' => 9], 'estimated_time_saved' => ['minutes' => 12.5]];
        $html = saldi_assist_report_strip_html($report, null, 1);
        self::assertStringContainsString('<span class="sai-ov__value">10</span><a class="sai-ov__label" href="#sai-r-kinds">Forslag</a>', $html);
        self::assertStringContainsString('Godkendt andel 30 %', $html);
        self::assertStringContainsString('<span class="sai-ov__value">5</span>', $html);
        self::assertStringContainsString('<span class="sai-ov__value">1.234</span>', $html);
        self::assertStringContainsString('13<small>min.</small></span><a class="sai-ov__label" href="#sai-r-estimate">Anslået sparet tid</a>', $html);
        $empty = saldi_assist_report_strip_html(['kinds' => [], 'suggestions' => [], 'tools' => [], 'rules' => ['active_count' => 0], 'estimated_time_saved' => ['minutes' => 0]], null, 1);
        self::assertStringNotContainsString('Godkendt andel', $empty);
        self::assertStringContainsString('0<small>min.</small>', $empty);
    }

    public function testRevisorpakkeStripCsvButtonsAndTotalInTheFoot(): void
    {
        $pack = ['data' => [
            'fiscal_year' => ['id' => 5, 'start' => '2026-01-01', 'end' => '2026-12-31'], 'currency' => 'DKK',
            'trial_balance' => ['rows' => [['account' => '1000', 'name' => 'Salg', 'opening' => '0.00', 'debit' => '0.00', 'credit' => '100.31', 'closing' => '-100.31', 'currency' => 'DKK']],
                'totals' => ['opening' => '0.00', 'debit' => '0.00', 'credit' => '100.31', 'closing' => '-100.31', 'currency' => 'DKK']],
            'open_items' => [], 'vat' => [], 'unposted_journals' => [], 'periods_closed' => [], 'notes' => ['Note <b>'],
        ], 'possibly_incomplete' => false];
        $html = saldi_revisorpakke_html($pack, null, 1);
        self::assertStringContainsString('<span class="sai-ov__value">1</span><a class="sai-ov__label" href="#pack-trial_balance">Saldobalance</a>', $html);
        self::assertStringContainsString('<span class="sai-ov__value">0</span><a class="sai-ov__label" href="#pack-open_items">', $html);
        self::assertSame(3, substr_count($html, 'class="sap-btn sap-btn--secondary sap-btn--sm sap-noprint" href="revisorpakke.php?fiscal_year=5&amp;csv='));
        self::assertMatchesRegularExpression('#<tfoot><tr><td>I alt</td><td></td><td class="sai-num">0,00</td>.*?<td class="sai-num">-100,31</td><td>DKK</td></tr></tfoot>#', $html);
        self::assertStringContainsString('<th scope="col" class="sai-num">Kredit</th>', $html);
        self::assertStringContainsString('<th scope="col">Navn</th>', $html);
        self::assertSame(4, substr_count($html, 'Ingen data i perioden.'));
        self::assertStringContainsString('Note &lt;b&gt;', $html);
        self::assertStringNotContainsString('Resultatet er afkortet.', $html);
    }

    public function testEveryPageLoadsTheSharedStylesheetAndTheV2Scope(): void
    {
        foreach (['systemdata/assistent.php', 'systemdata/assistent_rapport.php', 'finans/assist_bankfil.php', 'systemdata/revisorpakke.php'] as $page) {
            $source = (string)file_get_contents(__DIR__ . '/../../../' . $page);
            self::assertStringContainsString('<link rel="stylesheet" href="../css/saldi_assist_pages.css', $source, $page);
            self::assertStringContainsString('<body class="sai-body sai-v2"', $source, $page);
            self::assertStringContainsString('// 20261007 CL/LH', $source, $page);
        }
        $css = (string)file_get_contents(__DIR__ . '/../../../css/saldi_assist_pages.css');
        // Every rule is scoped to the v2 pages, so the classic pages and the shell modal are untouched.
        foreach (preg_split('/}\s*/', preg_replace('#/\*.*?\*/#s', '', $css)) as $block) {
            $selector = trim((string)strstr($block, '{', true));
            if ($selector === '' || str_starts_with($selector, '@')) {
                continue;
            }
            foreach (explode(',', $selector) as $part) {
                self::assertMatchesRegularExpression('/(^|\s|\.js\s)\.sai-v2|^\.sai-v2/', trim($part), $part);
            }
        }
    }

    public function testBankFailuresAreSplitByReasonAndTiedToTheirField(): void
    {
        $fail = static fn(string $message, array $detail = [], string $op = 'import'): array => saldi_bankfil_failure(new SaldiRuntimeError('VALIDATION_ERROR', $message, $detail), $op, null, 1);
        self::assertSame(['text' => 'Vælg en åben bankkonto i det aktive regnskabsår.', 'field' => 'bank_account'],
            $fail('Bank account must be an open balance account in the active fiscal year', ['fields' => ['bank_account']]));
        self::assertSame('statement', $fail('Invalid upload')['field']);
        self::assertStringContainsString('højst 2 MB', $fail('Invalid camt.053 XML')['text']);
        self::assertSame('Filen indeholdt ingen linjer.', $fail('No statement rows')['text']);
        self::assertStringContainsString('5000', $fail('Statement exceeds 5000 rows')['text']);
        self::assertSame(['text' => 'Specifik', 'field' => 'statement'], $fail('x', ['warning_da' => 'Specifik']));
        self::assertSame(['text' => 'Vælg mindst én linje.', 'field' => ''], $fail('Expected 1 to 200 lines', ['reason' => 'no_selection'], 'propose'));
        self::assertSame('Højst 200 linjer ad gangen.', $fail('Expected 1 to 200 lines', ['reason' => 'too_many_lines'], 'propose')['text']);
        self::assertStringContainsString('Læs filen igen', $fail('Bank line has no safe counter account', ['reason' => 'no_match'], 'propose')['text']);
        self::assertStringContainsString('Siden er udløbet', $fail('Invalid statement state', [], 'propose')['text']);
        // Nothing on step 1 talks about "valgte linjer" unless the reason is unknown.
        self::assertStringNotContainsString('valgte linjer', $fail('Invalid upload')['text']);
    }

    public function testBankSubmitWorksWithoutScriptAndTheCounterIsSingularForOne(): void
    {
        $source = (string)file_get_contents(__DIR__ . '/../../../finans/assist_bankfil.php');
        // Rendered enabled; only the script disables it for an empty selection (medium-only batches without JS).
        self::assertStringContainsString('<button class="sac-btn" type="submit" data-sap-submit>', $source);
        self::assertDoesNotMatchRegularExpression('/data-sap-submit<\?=/', $source);
        self::assertStringContainsString("if (submit) { submit.disabled = n === 0 || n > 200; }", $source);
        self::assertStringContainsString("data-text-one=\"<?= \$t(6376, '{n} linje valgt') ?>\"", $source);
        self::assertStringContainsString("count.getAttribute(n === 1 ? 'data-text-one' : 'data-text')", $source);
        self::assertSame('1 linje valgt', saldi_ui_text(null, 1, 6376, '{n} linje valgt', ['n' => 1]));
        self::assertStringNotContainsString('&#9251;', $source);
    }

    public function testReportShowsToolsByDanishLabelBusiestFirstAndFoldsTheRest(): void
    {
        foreach (SaldiToolRegistry::default()->all() as $tool) {
            self::assertArrayHasKey($tool->name, SALDI_ASSIST_REPORT_TOOLS, $tool->name);
        }
        $labels = array_map(static fn(array $l): string => ($l[2] ? 'F:' : '') . $l[1], SALDI_ASSIST_REPORT_TOOLS);
        self::assertSame(count($labels), count(array_unique($labels)), 'two tools share a label');
        $tools = [];
        foreach (array_slice(array_keys(SALDI_ASSIST_REPORT_TOOLS), 0, 12) as $i => $name) {
            $tools[] = ['tool' => $name, 'calls' => $i + 1, 'errors' => 0, 'error_share' => 0.0, 'p50_duration_ms' => null, 'p90_duration_ms' => null];
        }
        $tools[] = ['tool' => 'check_journal', 'calls' => 99, 'errors' => 0, 'error_share' => 0.0, 'p50_duration_ms' => null, 'p90_duration_ms' => null];
        $tools[] = ['tool' => 'propose_sales_order', 'calls' => 50, 'errors' => 0, 'error_share' => 0.0, 'p50_duration_ms' => null, 'p90_duration_ms' => null];
        $html = saldi_assist_report_html(['kinds' => [], 'suggestions' => [['source' => '', 'shown' => 1, 'used' => 0, 'dismissed' => 0]], 'duplicates_warned' => 0,
            'tools' => $tools, 'routines' => [], 'rules' => ['active_count' => 0, 'uses' => 0], 'estimated_time_saved' => ['minutes' => 0, 'assumptions' => []]], null, 1);
        self::assertMatchesRegularExpression('#<tbody><tr><th scope="row">Tjek kladden</th>.*?<tr><th scope="row">Forslag: Tilbud</th>#s', $html);
        self::assertStringNotContainsString('>check_journal<', $html);
        self::assertStringNotContainsString('>get_company_context<', $html);
        self::assertSame(1, substr_count($html, '<tbody class="sap-more" data-sap-more>'));
        preg_match('#<tbody class="sap-more" data-sap-more>(.*?)</tbody>#s', $html, $more);
        self::assertSame(4, substr_count($more[1], '<tr>'));
        self::assertStringContainsString('Vis alle værktøjer (14)', $html);
        self::assertStringContainsString('<th scope="row">Ukendt</th>', $html);
        self::assertSame('unknown_tool&lt;x&gt;', saldi_assist_report_tool_label('unknown_tool<x>', null, 1));
        self::assertStringContainsString('Kassekladder, der ikke er bogført', SALDI_ASSIST_REPORT_ROUTINES['stale_open_journals'][1]);
    }

    public function testPartialRevisorpakkeIsMarkedInTheStripAndCsvAndThePageNeverFallsBack(): void
    {
        $pack = ['fiscal_year' => ['id' => 5, 'start' => '2026-01-01', 'end' => '2026-12-31'], 'currency' => 'DKK',
            'trial_balance' => ['rows' => [['account' => '1000', 'name' => 'Salg', 'opening' => '0.00', 'debit' => '0.00', 'credit' => '1.00', 'closing' => '-1.00', 'currency' => 'DKK']],
                'totals' => ['opening' => '0.00', 'debit' => '0.00', 'credit' => '1.00', 'closing' => '-1.00', 'currency' => 'DKK']],
            'open_items' => [['party_account' => '1', 'party_name' => 'A', 'invoice' => '7', 'date' => '2026-01-02', 'due_date' => null, 'amount' => '1.00', 'currency' => 'DKK']],
            'vat' => [], 'unposted_journals' => [], 'periods_closed' => [], 'notes' => []];
        $partial = saldi_revisorpakke_html(['data' => $pack, 'possibly_incomplete' => true], null, 1);
        self::assertStringContainsString('<span class="sai-ov__value">1+</span><a class="sai-ov__label" href="#pack-trial_balance">', $partial);
        self::assertStringContainsString('<span class="sai-ov__value">1+</span><a class="sai-ov__label" href="#pack-open_items">', $partial);
        self::assertStringContainsString('<span class="sai-ov__value">0</span><a class="sai-ov__label" href="#pack-unposted_journals">', $partial);
        self::assertStringContainsString('Resultatet er afkortet.', $partial);
        $complete = saldi_revisorpakke_html(['data' => $pack, 'possibly_incomplete' => false], null, 1);
        self::assertStringNotContainsString('1+', $complete);
        $cut = saldi_revisorpakke_csv($pack, 'open_items', null, 1, true);
        self::assertStringStartsWith("\xEF\xBB\xBF\"Delvis: filen indeholder ikke alle rækker.", $cut);
        self::assertStringStartsWith("\xEF\xBB\xBFKonto;", saldi_revisorpakke_csv($pack, 'open_items', null, 1));
        $source = (string)file_get_contents(__DIR__ . '/../../../systemdata/revisorpakke.php');
        self::assertStringContainsString('$result = SaldiYearEndPack::build($ctx, $tdb, (int)$year, true);', $source);
        self::assertStringNotContainsString('(int)$year, false)', $source);
        self::assertStringContainsString("\$lang, \$result['possibly_incomplete']);", $source);
        // One Udskriv style on the report and the Revisorpakke.
        $button = 'class="sap-btn sap-btn--secondary sap-btn--sm sai-js-only" data-sap-print>';
        self::assertStringContainsString($button, $source);
        self::assertStringContainsString($button, (string)file_get_contents(__DIR__ . '/../../../systemdata/assistent_rapport.php'));
    }

    public function testSettingsUseOneSavePatternOneCaptionLevelAndFullSentences(): void
    {
        $digest = saldi_assistent_digest_html(['on' => false, 'email' => 'a@b.dk', 'default_email' => ''], '', null, 1);
        $admin = saldi_assistent_admin_html([], ['vat_period' => 'quarter', 'interrupt_budget' => '3', 'till_difference_threshold' => '50', 'lead_days' => '14'], '', null, 1);
        foreach ([$digest, $admin] as $form) {
            self::assertStringContainsString('data-sap-dirty', $form);
            self::assertMatchesRegularExpression('#<div class="sap-bar">.*<span class="sap-bar__end"><button type="submit" class="sac-btn">Gem</button></span></div></form>$#s', $form);
            self::assertSame(1, substr_count($form, '>Gem</button>'));
        }
        // The address is kept while the mail is off (dimmed, not disabled).
        self::assertStringContainsString('value="a@b.dk"', $digest);
        self::assertStringNotContainsString('disabled', $digest);
        self::assertStringContainsString('Kassekladder, der ikke er bogført', $admin);
        $source = (string)file_get_contents(__DIR__ . '/../../../systemdata/assistent.php');
        self::assertStringNotContainsString('<h3 class="sap-cap">', $source);
        self::assertStringContainsString("6379, 'Se forslag til nye regler i Opgaver.'", $source);
        self::assertStringContainsString("6380, 'Her ser du, hvad Saldi Assist husker", $source);
        self::assertStringContainsString("array_column(\$allGrants, 'id') != array_column(\$myGrants, 'id')", $source);
        $css = (string)file_get_contents(__DIR__ . '/../../../css/saldi_assist_pages.css');
        preg_match('/\.sai-v2 \.sap-sub \{(.*?)\}/s', $css, $sub);
        self::assertStringNotContainsString('uppercase', $sub[1]);
        preg_match('/\.sai-v2 \.sap-choice \{(.*?)\}/s', $css, $choice);
        self::assertStringNotContainsString('#e9e9e6', $choice[1], 'form choices must not reuse the navigation segment track');
        preg_match('/\.sai-v2 \.sap-page \{(.*?)\}/s', $css, $page);
        self::assertStringContainsString('max-width: none', $page[1]);
    }
}
