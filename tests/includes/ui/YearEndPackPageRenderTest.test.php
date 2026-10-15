<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/ui/YearEndPackPageRenderTest.test.php --- ai/mcp-assistant --- 2026.10.04
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
// 20261007 CL/LH The complete pack ignores the tool response limit, so the page never needs a partial fallback.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiInsightDbTestCase.php';
if (!defined('SALDI_REVISORPAKKE_LIBRARY')) {
    define('SALDI_REVISORPAKKE_LIBRARY', true);
}
require_once __DIR__ . '/../../../systemdata/revisorpakke.php';

final class YearEndPackPageRenderTest extends SaldiInsightDbTestCase
{
    private function report(): array
    {
        return SaldiInsightTools::yearEndPack($this->env->ctx(), $this->env->db, []);
    }

    public function testRendersServiceSectionsDatesMoneyAndQuarterlyVat(): void
    {
        $result = $this->report();
        $result['data']['periods_closed'] = [
            ['month' => '2026-02', 'closed' => true], ['month' => '2026-03', 'closed' => false],
        ];
        $html = saldi_revisorpakke_html($result, null, 1);
        $titles = ['Saldobalance', 'Åbne poster', 'Moms', 'Ikke-bogførte kassekladder',
            'Lukkede momsperioder', 'Noter til revisor'];
        foreach ($titles as $title) {
            self::assertStringContainsString('>' . $title . '</h2>', $html);
        }
        self::assertStringContainsString('01-01-2026 – 31-12-2026', $html);
        self::assertStringContainsString('100,31', $html);
        self::assertCount(4, $result['data']['vat']);
        self::assertSame(3, substr_count($html, 'Hent CSV'));
        self::assertStringContainsString('scope="col"', $html);
        self::assertStringContainsString('2026-02</td><td>Lukket', $html);
        self::assertStringNotContainsString('2026-03</td>', $html);
        self::assertStringContainsString('../finans/kassekladde.php?kladde_id=11', $html);
    }

    public function testCsvUsesSameRowsPreservesDecimalsAndBlocksTextFormulas(): void
    {
        $pack = $this->report()['data'];
        $pack['trial_balance']['rows'][0]['name'] = '=HYPERLINK("http://example.test")';
        $pack['trial_balance']['rows'][0]['closing'] = '-1710.00';
        $csv = saldi_revisorpakke_csv($pack, 'trial_balance', null, 1);
        self::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, 0, ';', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);
        self::assertCount(count($pack['trial_balance']['rows']) + 2, $rows);
        self::assertSame("'=HYPERLINK(\"http://example.test\")", $rows[1][1]);
        self::assertSame('-1710.00', $rows[1][5]);
        foreach (['open_items', 'vat'] as $key) {
            self::assertStringStartsWith("\xEF\xBB\xBF", saldi_revisorpakke_csv($pack, $key, null, 1));
        }
        $this->error('VALIDATION_ERROR', fn() => saldi_revisorpakke_csv($pack, '../private', null, 1));
    }

    public function testHtmlEscapesNotesNamesAndExplainsTruncation(): void
    {
        $result = $this->report();
        $result['data']['notes'] = ['<img onerror="x">'];
        $result['data']['trial_balance']['rows'][0]['name'] = '<script>bad</script>';
        $result['possibly_incomplete'] = true;
        $html = saldi_revisorpakke_html($result, null, 1);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;bad', $html);
        self::assertStringContainsString('&lt;img', $html);
        self::assertStringContainsString('Resultatet er afkortet.', $html);
    }

    public function testAdminOnlyAccessIsLocalToReportAndReaderAccessRemainsUnchanged(): void
    {
        $admin = $this->env->ctx(910001, '0100000000000000');
        $reportCtx = saldi_revisorpakke_context($admin, $this->env->db);
        self::assertSame('0100000000000000', $admin->rights);
        self::assertSame('0102000000000000', $reportCtx->rights);
        self::assertSame($admin->principal, $reportCtx->principal);
        self::assertFalse(SaldiPermissionService::allows($reportCtx, $this->env->db, 'journal.draft'));
        $report = SaldiInsightTools::yearEndPack($reportCtx, $this->env->db, []);
        self::assertNotEmpty($report['data']['trial_balance']['rows']);
        $reader = $this->env->ctx(910001, '0002000000000000');
        self::assertSame($reader, saldi_revisorpakke_context($reader, $this->env->db));
        $denied = $this->env->ctx(910001, '0000000000000000');
        $this->error('PERMISSION_DENIED', fn() => saldi_revisorpakke_context($denied, $this->env->db));
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist');
        $this->error('PERMISSION_DENIED', fn() => saldi_revisorpakke_context($agent, $this->env->db));
    }

    public function testRevenueHiddenAppliesToHtmlAndCsv(): void
    {
        SaldiSettings::set($this->env->db, $this->env->ctx(), 'assistant', 'tools_enabled', 'on');
        $this->env->db->insert('settings', ['var_grp' => 'finans', 'var_name' => 'hideRevenue', 'var_value' => 'on']);
        $ctx = $this->env->ctx(910001, '0002000000000000');
        $result = SaldiInsightTools::yearEndPack($ctx, $this->env->db, []);
        self::assertTrue($result['data']['revenue_hidden']);
        self::assertEmpty($result['data']['vat']);
        self::assertStringNotContainsString('Omsætning</td>', saldi_revisorpakke_html($result, null, 1));
        $csv = saldi_revisorpakke_csv($result['data'], 'trial_balance', null, 1);
        self::assertStringNotContainsString('Omsætning', $csv);
    }

    public function testCompletePackLargerThanTheToolResponseLimitIsReturnedWhole(): void
    {
        $db = $this->env->db;
        // About 0.5 MB of trial balance, well over the 198 KB tool response limit.
        $db->exec("INSERT INTO kontoplan (kontonr,regnskabsaar,kontotype,beskrivelse) SELECT 30000+n,4,'S',repeat('Lang kontotekst ',30) FROM generate_series(1,900) n");
        $complete = SaldiYearEndPack::build($this->env->ctx(), $db, 4, true);
        self::assertFalse($complete['possibly_incomplete']);
        self::assertGreaterThan(198 * 1024, strlen(json_encode($complete['data'])));
        self::assertContains('30900', array_column($complete['data']['trial_balance']['rows'], 'account'));
        $csv = saldi_revisorpakke_csv($complete['data'], 'trial_balance', $db, 1, $complete['possibly_incomplete']);
        self::assertStringStartsWith("\xEF\xBB\xBFKonto;", $csv);
        self::assertStringContainsString('30900;', $csv);
    }
}
