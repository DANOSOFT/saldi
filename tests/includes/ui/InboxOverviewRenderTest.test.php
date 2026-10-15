<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/ui/InboxOverviewRenderTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// The "Dagens overblik" card on Opgaver (inbox/index.php saldi_inbox_overview_html, SPEC-companion
// C5.2) without a database: only the sections the overview carries, rows with nothing in them left
// out (the VAT deadline always shown), allowlisted screen links, Danish dates and money, the calm
// line when nothing needs attention, escaping, and never the green "applied" tone.
//
// 20261002 CL/LH Created.

declare(strict_types=1);

if (!defined('SALDI_INBOX_ACTION_LIBRARY')) {
    define('SALDI_INBOX_ACTION_LIBRARY', true);
}
if (!defined('SALDI_INBOX_PAGE_LIBRARY')) {
    define('SALDI_INBOX_PAGE_LIBRARY', true);
}
require_once __DIR__ . '/../../../inbox/index.php';

final class InboxOverviewRenderTest extends PHPUnit\Framework\TestCase
{
    private function full(array $override = []): array
    {
        return array_replace([
            'date' => '2026-10-02',
            'open_items' => ['count' => 4, 'interrupts' => [['item_id' => 9, 'title' => '<script>x</script>', 'screen' => 'inbox/index.php?item=9']],
                'by_kind' => [['kind' => 'journal.draft_entry', 'label' => 'Kladdelinjer', 'count' => 2], ['kind' => 'routine.finding', 'label' => 'Rutinefund', 'count' => 2]]],
            'overdue_debtors' => ['count' => 7, 'amount' => '48210.00', 'currency' => 'DKK', 'converted' => true],
            'open_journals' => ['count' => 3, 'oldest_date' => '2026-08-12'],
            'vat' => ['period' => ['from' => '2026-07-01', 'to' => '2026-09-30'], 'deadline' => '2026-12-01', 'days_left' => 60, 'missing_documents' => 14, 'note' => 'Tjek altid skat.dk.'],
            'pool_documents' => ['count' => 3],
            'low_stock' => ['count' => 5],
            'nothing_to_do' => false,
        ], $override);
    }

    public function testEverySectionRendersWithItsScreenLink(): void
    {
        $html = saldi_inbox_overview_html($this->full(), null, 1);
        self::assertStringContainsString('Dagens overblik', $html);
        self::assertStringContainsString('02-10-2026', $html);
        self::assertStringContainsString('2 Kladdelinjer &#183; 2 Rutinefund', $html);
        self::assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('href="index.php?item=9"', $html);
        self::assertStringContainsString('data-saldi-screen="debitor/rapport.php?rapportart=openpost"', $html);
        self::assertStringContainsString('48.210,00 DKK', $html);
        self::assertStringContainsString('data-saldi-screen="finans/kladdeliste.php"', $html);
        self::assertStringContainsString('ældste 12-08-2026', $html);
        self::assertStringContainsString('data-saldi-screen="finans/moms_periode.php"', $html);
        self::assertStringContainsString('01-12-2026', $html);
        self::assertStringContainsString('60 dage', $html);
        self::assertStringContainsString('Bilag mangler før momsfristen: 14', $html);
        self::assertStringContainsString('Tjek altid skat.dk.', $html);
        self::assertStringContainsString('data-saldi-screen="finans/pulje_review.php"', $html);
        self::assertStringContainsString('Varer under minimum', $html);
        self::assertStringNotContainsString('Intet kræver din opmærksomhed', $html);
        self::assertStringNotContainsString('sai-ov--attention', $html);
        self::assertStringNotContainsString('done', $html);
        // Every link the card makes is either the page itself or an allowlisted screen.
        preg_match_all('/data-saldi-screen="([^"]+)"/', $html, $screens);
        foreach ($screens[1] as $screen) {
            self::assertTrue(SaldiScreens::isAllowed(html_entity_decode($screen)), $screen);
        }
    }

    public function testEmptyRowsAndMissingSectionsAreLeftOut(): void
    {
        $html = saldi_inbox_overview_html([
            'date' => '2026-10-02',
            'open_items' => ['count' => 0, 'interrupts' => [], 'by_kind' => []],
            'overdue_debtors' => ['count' => 0, 'amount' => '0.00', 'currency' => 'DKK', 'converted' => true],
            'open_journals' => ['count' => 0, 'oldest_date' => null],
            'vat' => ['period' => ['from' => '2026-07-01', 'to' => '2026-09-30'], 'deadline' => '2026-12-01', 'days_left' => 60, 'missing_documents' => 0, 'note' => 'n'],
            'nothing_to_do' => true,
        ], null, 1);
        self::assertStringContainsString('Intet kræver din opmærksomhed i dag.', $html);
        self::assertStringContainsString('Momsfrist', $html);
        self::assertStringNotContainsString('Forfaldne debitorposter', $html);
        self::assertStringNotContainsString('Kassekladder, der ikke er bogført', $html);
        self::assertStringNotContainsString('Bilag mangler', $html);
        self::assertStringNotContainsString('Bilag i puljen', $html);
        self::assertStringNotContainsString('Varer under minimum', $html);
    }

    public function testVatDeadlineCloseWithMissingDocumentsIsAmberAndUnconvertedTotalsSaySo(): void
    {
        $html = saldi_inbox_overview_html($this->full([
            'vat' => ['period' => ['from' => '2026-07-01', 'to' => '2026-09-30'], 'deadline' => '2026-10-05', 'days_left' => 3, 'missing_documents' => 2, 'note' => 'n'],
            'overdue_debtors' => ['count' => 1, 'amount' => '10.00', 'currency' => 'DKK', 'converted' => false],
        ]), null, 1);
        self::assertStringContainsString('sai-ov--attention', $html);
        self::assertStringContainsString('Poster uden valutakurs er ikke regnet med.', $html);
    }

    public function testOnlyTheSectionsTheViewerMayReadAppear(): void
    {
        $html = saldi_inbox_overview_html(['date' => '2026-10-02', 'pool_documents' => ['count' => 2], 'nothing_to_do' => false], null, 1);
        self::assertStringContainsString('Bilag i puljen', $html);
        self::assertStringNotContainsString('Åbne opgaver', $html);
        self::assertStringNotContainsString('Momsfrist', $html);
    }
}
