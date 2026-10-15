<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/ui/InboxCompanionPageTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Opgaver companion additions (SPEC-companion C5.1, C5.2) against a disposable plan_tpl clone:
// Åbne leaves silent items out and lists interrupt items first with the "Vigtigt" flag, the Stille
// tab lists only silent items, the tab counters, the shell banner ops ('banner', 'banner_seen')
// through the JSON controller, and one overview_viewed event per user and day.
//
// 20261002 CL/LH Created.
// 20261007 CL/LH The tab counters (Afgjort included) leave out credit-note drafts for revenue-hidden users.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
if (!defined('SALDI_INBOX_ACTION_LIBRARY')) {
    define('SALDI_INBOX_ACTION_LIBRARY', true);
}
if (!defined('SALDI_INBOX_PAGE_LIBRARY')) {
    define('SALDI_INBOX_PAGE_LIBRARY', true);
}
require_once __DIR__ . '/../../../inbox/index.php';

final class InboxCompanionPageTest extends SaldiRuntimeDbTestCase
{
    private const CSRF = 'test-csrf-companion-0123456789';

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

    private function task(string $title, string $attention, int $userId = 910001): array
    {
        return $this->inbox()->create($this->env->ctx($userId), 'followup.task', ['title' => $title], ['attention' => $attention]);
    }

    /** @return array{status:int, body:array} */
    private function call(array $body, int $userId = 910001): array
    {
        $server = ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'saldi.test', 'HTTP_ORIGIN' => 'http://saldi.test', 'HTTP_X_SALDI_CSRF' => self::CSRF];
        $body += ['tenant_hash' => $this->env->ctx($userId)->tenantHash()];
        return saldi_inbox_action_handle($server, json_encode($body, JSON_THROW_ON_ERROR), $userId, fn(): array => ['ctx' => $this->env->ctx($userId), 'db' => $this->env->db]);
    }

    private function page(string $tab): array
    {
        $kinds = SaldiKindRegistry::default()->names();
        return saldi_inbox_page_items($this->inbox(), $this->env->ctx(), saldi_inbox_view_state(['tab' => $tab], $kinds), $this->env->db);
    }

    public function testOpenTabHidesSilentItemsAndListsInterruptsFirst(): void
    {
        $ambient = $this->task('Almindelig opgave', 'ambient');
        $silent = $this->task('Stille opgave', 'silent');
        $interrupt = $this->task('Vigtig opgave', 'interrupt');
        $newer = $this->task('Nyere opgave', 'receipt');

        $open = $this->page('open');
        self::assertSame([$interrupt['id'], $newer['id'], $ambient['id']], array_column($open['items'], 'id'));
        self::assertFalse($open['has_more']);
        self::assertSame([$silent['id']], array_column($this->page('silent')['items'], 'id'));
        self::assertSame(['open' => 3, 'silent' => 1], saldi_inbox_tab_counts($this->env->db, $this->env->ctx()));
        // The shell badge (countOpen) still counts every open item.
        self::assertSame(4, $this->inbox()->countOpen($this->env->ctx()));
        // Another user's items stay invisible in both tabs.
        $this->task('Andens opgave', 'silent', 910002);
        self::assertSame(['open' => 3, 'silent' => 1], saldi_inbox_tab_counts($this->env->db, $this->env->ctx()));
    }

    public function testCountersLeaveOutCreditNotesTheViewerCannotSee(): void
    {
        $open = $this->task('Kreditnota åben', 'ambient');
        $decided = $this->task('Kreditnota afgjort', 'ambient');
        $this->inbox()->decide($this->env->ctx(), $decided['id'], 'reject', $decided['digest'], $this->env->ctx()->tenantHash());
        $this->task('Opfølgning', 'ambient');
        $plain = $this->task('Opfølgning afgjort', 'ambient');
        $this->inbox()->decide($this->env->ctx(), $plain['id'], 'reject', $plain['digest'], $this->env->ctx()->tenantHash());
        // Only the kind matters to the predicate; a real credit-note payload needs a posted invoice.
        $this->env->db->exec("UPDATE inbox_items SET kind = 'credit_note.draft' WHERE id IN (" . (int)$open['id'] . ',' . (int)$decided['id'] . ')');
        $rights = str_repeat('1', 16);
        $rights[1] = '0';
        $hidden = $this->env->ctx(910001, $rights);
        self::assertSame(2, saldi_inbox_decided_count($this->env->db, $hidden));
        self::assertSame(['open' => 2, 'silent' => 0], saldi_inbox_tab_counts($this->env->db, $hidden));
        $this->env->db->insert('settings', ['var_name' => 'hideRevenue', 'var_grp' => 'finans', 'var_value' => 'on']);
        try {
            self::assertSame(1, saldi_inbox_decided_count($this->env->db, $hidden));
            self::assertSame(['open' => 1, 'silent' => 0], saldi_inbox_tab_counts($this->env->db, $hidden));
            // The counters match what the service itself lets this user see.
            self::assertSame($this->inbox()->countOpen($hidden), saldi_inbox_tab_counts($this->env->db, $hidden)['open']);
            self::assertCount(1, $this->inbox()->list($hidden, ['status' => SALDI_INBOX_TABS['decided']]));
            $listed = saldi_inbox_page_items($this->inbox(), $hidden, saldi_inbox_view_state([], SaldiKindRegistry::default()->names()), $this->env->db);
            self::assertNotContains($open['id'], array_column($listed['items'], 'id'));
            self::assertCount(1, $listed['items']);
            // A user who may see revenue still counts them.
            self::assertSame(2, saldi_inbox_decided_count($this->env->db, $this->env->ctx(910001, str_repeat('1', 16))));
        } finally {
            $this->env->db->exec("DELETE FROM settings WHERE var_grp = 'finans' AND var_name = 'hideRevenue'");
        }
    }

    public function testOpenTabPagesWithTheInterruptOrdering(): void
    {
        $ids = [];
        for ($i = 1; $i <= 27; $i++) {
            $ids[] = $this->task('Opgave ' . $i, $i === 1 ? 'interrupt' : 'ambient')['id'];
        }
        $kinds = SaldiKindRegistry::default()->names();
        $first = saldi_inbox_page_items($this->inbox(), $this->env->ctx(), saldi_inbox_view_state([], $kinds), $this->env->db);
        self::assertCount(25, $first['items']);
        self::assertTrue($first['has_more']);
        self::assertSame($ids[0], $first['items'][0]['id']);
        $second = saldi_inbox_page_items($this->inbox(), $this->env->ctx(), saldi_inbox_view_state(['limit' => '50'], $kinds), $this->env->db);
        self::assertCount(27, $second['items']);
        self::assertFalse($second['has_more']);
        self::assertSame(array_merge([$ids[0]], array_reverse(array_slice($ids, 1))), array_column($second['items'], 'id'));
    }

    public function testOnlyOpenInterruptItemsCarryTheImportantFlag(): void
    {
        $interrupt = $this->inbox()->get($this->env->ctx(), $this->task('Vigtig <b>opgave</b>', 'interrupt')['id']);
        $ambient = $this->inbox()->get($this->env->ctx(), $this->task('Almindelig opgave', 'ambient')['id']);
        $opts = ['db' => $this->env->db, 'lang' => 1, 'mode' => 'page', 'csrf' => 'c', 'this_db' => 'x', 'form_action' => 'index.php', 'return_query' => '', 'user_id' => 910001];
        $html = saldi_inbox_item_html($interrupt, $opts, $this->env->db, 1);
        self::assertStringContainsString('sai-item--interrupt', $html);
        self::assertStringContainsString('>Vigtigt</span>', $html);
        self::assertStringNotContainsString('<b>opgave</b>', $html);
        self::assertStringNotContainsString('Vigtigt', saldi_inbox_item_html($ambient, $opts, $this->env->db, 1));
        $closed = $interrupt;
        $closed['status'] = 'applied';
        self::assertStringNotContainsString('sai-item--interrupt', saldi_inbox_item_html($closed, $opts, $this->env->db, 1));
    }

    public function testBannerShowsEachUnseenInterruptOnceAndRecordsIt(): void
    {
        $older = $this->task('Første vigtige', 'interrupt');
        $newer = $this->task('Anden vigtige', 'interrupt');
        $this->task('Ikke vigtig', 'ambient');
        $this->task('Andens vigtige', 'interrupt', 910002);

        $first = $this->call(['op' => 'banner']);
        self::assertSame(200, $first['status']);
        self::assertTrue($first['body']['ok']);
        self::assertSame($newer['id'], $first['body']['banner']['item_id']);
        self::assertSame('Anden vigtige', $first['body']['banner']['title']);
        self::assertSame(['important' => 'Vigtigt', 'open' => 'Åbn', 'close' => 'Luk'], $first['body']['banner']['texts']);
        self::assertArrayNotHasKey('html', $first['body']);

        self::assertTrue($this->call(['op' => 'banner_seen', 'item_id' => $newer['id']])['body']['recorded']);
        self::assertFalse($this->call(['op' => 'banner_seen', 'item_id' => $newer['id']])['body']['recorded']);
        self::assertSame(1, (int)$this->env->db->value("SELECT COUNT(*) FROM assist_events WHERE event = 'interrupt_shown' AND user_id = 910001 AND subject = ?", ['item:' . $newer['id']]));

        self::assertSame($older['id'], $this->call(['op' => 'banner'])['body']['banner']['item_id']);
        $this->call(['op' => 'banner_seen', 'item_id' => $older['id']]);
        self::assertNull($this->call(['op' => 'banner'])['body']['banner']);
        // Seen by user 910001 says nothing about user 910002.
        self::assertNotNull($this->call(['op' => 'banner'], 910002)['body']['banner']);
    }

    public function testBannerSeenRefusesItemsThatAreNotVisibleOpenInterrupts(): void
    {
        $ambient = $this->task('Ikke vigtig', 'ambient');
        $foreign = $this->task('Andens vigtige', 'interrupt', 910002);
        self::assertFalse($this->call(['op' => 'banner_seen', 'item_id' => $ambient['id']])['body']['recorded']);
        $hidden = $this->call(['op' => 'banner_seen', 'item_id' => $foreign['id']]);
        self::assertSame(404, $hidden['status']);
        self::assertSame('NOT_FOUND', $hidden['body']['error']['code']);
        $missing = $this->call(['op' => 'banner_seen']);
        self::assertSame(400, $missing['status']);
        $wrongTenant = $this->call(['op' => 'banner', 'tenant_hash' => str_repeat('0', 32)]);
        self::assertSame(409, $wrongTenant['status']);
        self::assertSame('tenant_changed', $wrongTenant['body']['error']['detail']['reason']);
        self::assertSame(0, (int)$this->env->db->value("SELECT COUNT(*) FROM assist_events WHERE event = 'interrupt_shown'"));
    }

    public function testDecidedInterruptsLeaveTheBanner(): void
    {
        $item = $this->task('Vigtig', 'interrupt');
        $this->inbox()->decide($this->env->ctx(), $item['id'], 'reject', $item['digest'], $this->env->ctx()->tenantHash());
        self::assertNull($this->call(['op' => 'banner'])['body']['banner']);
    }

    public function testOverviewViewedIsRecordedOncePerUserAndDay(): void
    {
        $ctx = $this->env->ctx();
        self::assertTrue(saldi_inbox_overview_viewed($this->env->db, $ctx, '2026-10-02'));
        self::assertFalse(saldi_inbox_overview_viewed($this->env->db, $ctx, '2026-10-02'));
        self::assertTrue(saldi_inbox_overview_viewed($this->env->db, $ctx, '2026-10-03'));
        self::assertTrue(saldi_inbox_overview_viewed($this->env->db, $this->env->ctx(910002), '2026-10-02'));
        self::assertSame(3, (int)$this->env->db->value("SELECT COUNT(*) FROM assist_events WHERE event = 'overview_viewed'"));
    }

    public function testOverviewCardRendersForTheRealService(): void
    {
        $this->task('Vigtig <i>sag</i>', 'interrupt');
        $overview = SaldiDailyOverview::build($this->env->ctx(), $this->env->db);
        $html = saldi_inbox_overview_html($overview, $this->env->db, 1);
        self::assertStringContainsString('Dagens overblik', $html);
        self::assertStringContainsString('Åbne opgaver', $html);
        self::assertStringContainsString('Momsfrist', $html);
        self::assertStringContainsString('Vigtig &lt;i&gt;sag&lt;/i&gt;', $html);
        self::assertStringNotContainsString('<i>sag</i>', $html);
    }
}
