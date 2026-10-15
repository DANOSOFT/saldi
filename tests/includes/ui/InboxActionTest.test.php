<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/ui/InboxActionTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// inbox/action.php controller (saldi_inbox_action_handle) against a disposable
// plan_tpl clone: request guards (method, origin, revisor/admin session, CSRF,
// body), tenant binding, preview card, digest-bound approval, reject, undo, count.
//
// 20261002 CL/LH Created.
// 20261002 CDX/LH Cover F1 integration behavior and regression cases.
// 20261002 CL/LH Proxy-aware same-origin check; inbox page view state and paging past 100 items.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
if (!defined('SALDI_INBOX_ACTION_LIBRARY')) {
    define('SALDI_INBOX_ACTION_LIBRARY', true);
}
require_once __DIR__ . '/../../../inbox/action.php';

final class InboxActionTest extends SaldiRuntimeDbTestCase
{
    private const CSRF = 'test-csrf-0123456789abcdef';

    private int $sessionCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = ['inbox_csrf' => self::CSRF];
        $this->sessionCalls = 0;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    private function server(array $override = []): array
    {
        return array_merge([
            'REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'saldi.test', 'HTTP_ORIGIN' => 'http://saldi.test',
            'HTTP_X_SALDI_CSRF' => self::CSRF,
        ], $override);
    }

    /** @return array{status:int, body:array} */
    private function call(array $body, array $server = [], int $brugerId = 910001): array
    {
        return saldi_inbox_action_handle($this->server($server), json_encode($body, JSON_THROW_ON_ERROR), $brugerId, function (): array {
            $this->sessionCalls++;
            return ['ctx' => $this->env->ctx(), 'db' => $this->env->db];
        });
    }

    private function hash(): string
    {
        return $this->env->ctx()->tenantHash();
    }

    private function followup(string $title = 'Ring til <b>Hansen</b> ApS'): array
    {
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist', 23, ['read', 'draft']);
        return $this->inbox()->create($agent, 'followup.task', ['title' => $title], ['evidence' => ['explanation' => '<img src=x onerror=alert(1)>']]);
    }

    private function journalItem(): array
    {
        $db = $this->env->db;
        $db->exec('DELETE FROM kassekladde');
        $db->exec('DELETE FROM tmpkassekl');
        $db->exec('DELETE FROM kladdeliste');
        foreach ([991001 => 'D', 991002 => 'S'] as $number => $type) {
            $db->insert('kontoplan', ['id' => $number, 'kontonr' => $number, 'regnskabsaar' => 4, 'kontotype' => $type, 'beskrivelse' => 'UI account', 'lukket' => '']);
        }
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist', 23, ['read', 'draft']);
        return $this->inbox()->create($agent, 'journal.draft_entry', ['date' => '2025-06-15', 'lines' => [
            ['description' => 'Adobe', 'debit' => ['type' => 'F', 'account' => '991001'], 'credit' => ['type' => 'F', 'account' => '991002'], 'amount' => '1000'],
            ['description' => 'Moms', 'debit' => ['type' => 'F', 'account' => '991001'], 'credit' => ['type' => 'F', 'account' => '991002'], 'amount' => '250'],
        ]]);
    }

    private function assertError(array $response, int $status, string $code): void
    {
        self::assertSame($status, $response['status'], json_encode($response['body'], JSON_UNESCAPED_UNICODE));
        self::assertFalse($response['body']['ok']);
        self::assertSame($code, $response['body']['error']['code']);
        self::assertNotSame('', $response['body']['error']['message_da']);
    }

    public function testCsrfMissingOrWrongIsRefusedBeforeAnyDatabaseWork(): void
    {
        $item = $this->followup();
        $body = ['op' => 'approve', 'item_id' => $item['id'], 'digest' => $item['digest'], 'tenant_hash' => $this->hash()];
        $server = $this->server();
        unset($server['HTTP_X_SALDI_CSRF']);
        $this->assertError(saldi_inbox_action_handle($server, json_encode($body), 910001, fn() => throw new LogicException('opened')), 403, 'PERMISSION_DENIED');
        $this->assertError($this->call($body, ['HTTP_X_SALDI_CSRF' => '']), 403, 'PERMISSION_DENIED');
        $wrong = $this->call($body, ['HTTP_X_SALDI_CSRF' => 'wrong']);
        $this->assertError($wrong, 403, 'PERMISSION_DENIED');
        self::assertSame('csrf', $wrong['body']['error']['detail']['reason']);
        $_SESSION = [];
        $this->assertError($this->call($body), 403, 'PERMISSION_DENIED');
        self::assertSame(0, $this->sessionCalls);
        self::assertSame('open', $this->inbox()->get($this->env->ctx(), $item['id'])['status']);
    }

    public function testMethodOriginAndRevisorSessionsAreRefused(): void
    {
        $body = ['op' => 'count', 'tenant_hash' => $this->hash()];
        $this->assertError($this->call($body, ['REQUEST_METHOD' => 'GET']), 405, 'VALIDATION_ERROR');
        $this->assertError($this->call($body, ['HTTP_ORIGIN' => 'http://evil.test']), 403, 'PERMISSION_DENIED');
        $this->assertError($this->call($body, ['HTTP_ORIGIN' => '']), 403, 'PERMISSION_DENIED');
        self::assertTrue($this->call($body, ['HTTP_ORIGIN' => '', 'HTTP_REFERER' => 'http://saldi.test/saldi/index/main.php'])['body']['ok']);
        self::assertTrue($this->call($body, ['HTTP_ORIGIN' => 'https://saldi.test', 'HTTP_X_FORWARDED_PROTO' => 'https'])['body']['ok']);
        $this->assertError($this->call($body, ['HTTP_ORIGIN' => 'https://saldi.test']), 403, 'PERMISSION_DENIED');
        $this->assertError($this->call($body, ['HTTP_ORIGIN' => '', 'HTTP_REFERER' => 'http://saldi.test@evil.test/']), 403, 'PERMISSION_DENIED');
        $denied = $this->call($body, [], 0);
        $this->assertError($denied, 403, 'PERMISSION_DENIED');
        self::assertSame('Opgaver kræver et brugerlogin.', $denied['body']['error']['message_da']);
        $this->assertError($this->call($body, [], -1), 403, 'PERMISSION_DENIED');
        self::assertSame(2, $this->sessionCalls);
    }

    public function testViewStateIsWhitelistedAndKeepsTheOffset(): void
    {
        $kinds = SaldiKindRegistry::default()->names();
        self::assertSame(['tab' => 'open', 'kind' => '', 'item' => 0, 'limit' => 25, 'offset' => 0], saldi_inbox_view_state([], $kinds));
        $state = saldi_inbox_view_state(['tab' => 'done', 'kind' => 'followup.task', 'limit' => '160', 'offset' => '200'], $kinds);
        self::assertSame(['tab' => 'done', 'kind' => 'followup.task', 'item' => 0, 'limit' => 100, 'offset' => 200], $state);
        self::assertSame(0, saldi_inbox_view_state(['offset' => '-5'], $kinds)['offset']);
        self::assertSame(0, saldi_inbox_view_state(['offset' => ['1']], $kinds)['offset']);
        self::assertSame('tab=done&kind=followup.task&limit=100&offset=200', saldi_inbox_view_query($state));
        self::assertSame('', saldi_inbox_view_query(saldi_inbox_view_state([], $kinds)));
        self::assertSame(['limit' => 50, 'offset' => 0], saldi_inbox_next_page(['limit' => 25, 'offset' => 0]));
        self::assertSame(['limit' => 100, 'offset' => 300], saldi_inbox_next_page(['limit' => 100, 'offset' => 200]));
    }

    public function testInboxPagesReachItemsPastTheNewestHundred(): void
    {
        $ctx = $this->env->ctx();
        $inbox = $this->inbox();
        $ids = [];
        for ($i = 1; $i <= 130; $i++) {
            $item = $inbox->create($ctx, 'followup.task', ['title' => 'Paged task ' . $i]);
            $ids[] = $item['id'];
            if ($i % 2 === 0) {
                $inbox->cancel($ctx, $item['id']);
            } elseif ($i % 3 === 0) {
                $inbox->decide($ctx, $item['id'], 'reject', $item['digest'], $ctx->tenantHash());
            }
        }
        $kinds = SaldiKindRegistry::default()->names();
        // Open: 130 - 65 cancelled - 22 rejected = 43; the 'Afvist' tab spans rejected + cancelled = 87.
        $expected = array_values(array_filter(array_reverse($ids), fn(int $id): bool => in_array($inbox->get($ctx, $id)['status'], ['rejected', 'cancelled'], true)));
        self::assertCount(87, $expected);
        $state = saldi_inbox_view_state(['tab' => 'rejected'], $kinds);
        $seen = [];
        do {
            $page = saldi_inbox_page_items($inbox, $ctx, $state);
            $seen = $state['offset'] === 0 ? array_column($page['items'], 'id') : array_merge($seen, array_column($page['items'], 'id'));
            if (!$page['has_more']) {
                break;
            }
            $state = array_merge($state, saldi_inbox_next_page($state));
        } while (true);
        self::assertSame($expected, $seen);
        $open = saldi_inbox_page_items($inbox, $ctx, ['tab' => 'open', 'kind' => '', 'limit' => 100, 'offset' => 0]);
        self::assertCount(43, $open['items']);
        self::assertFalse($open['has_more']);
        // One status past the newest hundred: the second 100-page holds the 30 oldest items.
        $db = $this->env->db;
        $db->exec("UPDATE inbox_items SET status = 'expired', open_key = NULL");
        $first = saldi_inbox_page_items($inbox, $ctx, ['tab' => 'expired', 'kind' => '', 'limit' => 100, 'offset' => 0]);
        self::assertCount(100, $first['items']);
        self::assertTrue($first['has_more']);
        $second = saldi_inbox_page_items($inbox, $ctx, ['tab' => 'expired', 'kind' => '', 'limit' => 100, 'offset' => 100]);
        self::assertSame(array_slice(array_reverse($ids), 100), array_column($second['items'], 'id'));
        self::assertFalse($second['has_more']);
        $exact = saldi_inbox_page_items($inbox, $ctx, ['tab' => 'expired', 'kind' => '', 'limit' => 100, 'offset' => 30]);
        self::assertCount(100, $exact['items']);
        self::assertFalse($exact['has_more']);
    }

    public function testMalformedBodiesAreValidationErrors(): void
    {
        $item = $this->followup();
        foreach ([
            ['op' => 'delete', 'item_id' => $item['id'], 'tenant_hash' => $this->hash()],
            ['op' => 'preview', 'item_id' => '1', 'tenant_hash' => $this->hash()],
            ['op' => 'preview', 'item_id' => $item['id'], 'tenant_hash' => 'short'],
            ['op' => 'approve', 'item_id' => $item['id'], 'tenant_hash' => $this->hash()],
            ['op' => 'reject', 'item_id' => $item['id'], 'digest' => $item['digest'], 'tenant_hash' => $this->hash(), 'note' => str_repeat('x', 256)],
        ] as $body) {
            $this->assertError($this->call($body), 400, 'VALIDATION_ERROR');
        }
        $this->assertError(saldi_inbox_action_handle($this->server(), 'not json', 910001, fn() => throw new LogicException('opened')), 400, 'VALIDATION_ERROR');
        self::assertSame(0, $this->sessionCalls);
    }

    public function testTenantHashMismatchIsConflictForEveryOperation(): void
    {
        $item = $this->followup();
        $other = substr(hash('sha256', 'saldi-tenant:some_other_company'), 0, 32);
        foreach (['preview', 'approve', 'reject', 'undo', 'cancel', 'count'] as $op) {
            $response = $this->call(['op' => $op, 'item_id' => $item['id'], 'digest' => $item['digest'], 'tenant_hash' => $other]);
            $this->assertError($response, 409, 'CONFLICT');
            self::assertSame('tenant_changed', $response['body']['error']['detail']['reason']);
            self::assertSame('Du har skiftet regnskab i et andet vindue. Genindlæs siden.', $response['body']['error']['message_da']);
        }
        self::assertSame('open', $this->inbox()->get($this->env->ctx(), $item['id'])['status']);
    }

    public function testPreviewReturnsTheItemCardAndEscapedModalMarkup(): void
    {
        $item = $this->followup();
        $response = $this->call(['op' => 'preview', 'item_id' => $item['id'], 'tenant_hash' => $this->hash()]);
        self::assertSame(200, $response['status']);
        self::assertTrue($response['body']['ok']);
        self::assertSame($item['id'], $response['body']['item']['id']);
        self::assertSame($item['digest'], $response['body']['item']['digest']);
        self::assertSame($this->hash(), $response['body']['item']['tenant_hash']);
        self::assertSame('Ring til <b>Hansen</b> ApS', $response['body']['item']['card']['title']);
        $html = $response['body']['html'];
        self::assertStringContainsString('Ring til &lt;b&gt;Hansen&lt;/b&gt; ApS', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('<form', $html);
        self::assertSame('open', $this->inbox()->get($this->env->ctx(), $item['id'])['status']);
        $missing = $this->call(['op' => 'preview', 'item_id' => 999999, 'tenant_hash' => $this->hash()]);
        $this->assertError($missing, 404, 'NOT_FOUND');
    }

    public function testApproveWithTheReviewedDigestAppliesAndReportsTheReceipt(): void
    {
        $item = $this->journalItem();
        $wrong = $this->call(['op' => 'approve', 'item_id' => $item['id'], 'digest' => str_repeat('0', 64), 'tenant_hash' => $this->hash()]);
        $this->assertError($wrong, 409, 'CONFLICT');
        self::assertSame('digest_mismatch', $wrong['body']['error']['detail']['reason']);
        self::assertSame('open', $this->inbox()->get($this->env->ctx(), $item['id'])['status']);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde'));

        $applied = $this->call(['op' => 'approve', 'item_id' => $item['id'], 'digest' => $item['digest'], 'tenant_hash' => $this->hash()]);
        self::assertSame(200, $applied['status'], json_encode($applied['body'], JSON_UNESCAPED_UNICODE));
        self::assertSame('applied', $applied['body']['item']['status']);
        $kladde = $applied['body']['item']['result']['kladde_id'];
        self::assertSame('2 linjer lagt i kassekladde ' . $kladde . '. Intet er bogført.', $applied['body']['receipt']['text']);
        self::assertSame('finans/kassekladde.php?kladde_id=' . $kladde, $applied['body']['receipt']['screen']);
        self::assertSame(2, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde WHERE kladde_id = ?', [$kladde]));
        self::assertStringContainsString('sac--done', $applied['body']['html']);
        self::assertSame(910001, (int)$this->env->db->value("SELECT approved_by FROM decision_log WHERE inbox_item_id = ? AND event = 'applied'", [$item['id']]));

        $again = $this->call(['op' => 'approve', 'item_id' => $item['id'], 'digest' => $item['digest'], 'tenant_hash' => $this->hash()]);
        $this->assertError($again, 409, 'CONFLICT');
        self::assertSame('Forslaget er allerede behandlet eller ændret, siden du åbnede det.', $again['body']['error']['message_da']);

        $undo = $this->call(['op' => 'undo', 'item_id' => $item['id'], 'tenant_hash' => $this->hash()]);
        self::assertSame('undone', $undo['body']['item']['status']);
        self::assertSame('Linjerne er fjernet fra kassekladden igen.', $undo['body']['receipt']['text']);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde'));
    }

    public function testRejectWithNoteAndOpenCount(): void
    {
        $first = $this->followup('Første');
        $this->followup('Anden');
        self::assertSame(['ok' => true, 'count' => 2], $this->call(['op' => 'count', 'tenant_hash' => $this->hash()])['body']);
        $rejected = $this->call(['op' => 'reject', 'item_id' => $first['id'], 'digest' => $first['digest'], 'tenant_hash' => $this->hash(), 'note' => '  Klaret pr. telefon  ']);
        self::assertSame('rejected', $rejected['body']['item']['status']);
        self::assertSame('Klaret pr. telefon', $rejected['body']['item']['decision_note']);
        self::assertSame("O'Brien", $rejected['body']['item']['decided_by_name']);
        self::assertStringContainsString('Behandlet af O&#039;Brien', $rejected['body']['html']);
        self::assertStringContainsString('Bemærkning: Klaret pr. telefon', $rejected['body']['html']);
        self::assertSame('Klaret pr. telefon', $this->env->db->value('SELECT decision_note FROM inbox_items WHERE id = ?', [$first['id']]));
        self::assertStringContainsString('Intet er ændret.', $rejected['body']['html']);
        self::assertStringNotContainsString('sac--done', $rejected['body']['html']);
        self::assertSame(1, $this->call(['op' => 'count', 'tenant_hash' => $this->hash()])['body']['count']);
    }

    public function testCancelReturnsCardAndReceiptAndIsTerminal(): void
    {
        $item = $this->followup();
        $body = ['op' => 'cancel', 'item_id' => $item['id'], 'tenant_hash' => $this->hash()];
        $cancelled = $this->call($body);
        self::assertSame(200, $cancelled['status']);
        self::assertSame('cancelled', $cancelled['body']['item']['status']);
        self::assertStringContainsString('Trukket tilbage', $cancelled['body']['html']);
        self::assertArrayHasKey('receipt', $cancelled['body']);
        $again = $this->call($body);
        $this->assertError($again, 409, 'CONFLICT');
        self::assertSame('not_open', $again['body']['error']['detail']['reason']);
    }

    public function testAnotherUserCannotSeeOrDecideAPrivateItem(): void
    {
        $item = $this->followup();
        $response = saldi_inbox_action_handle($this->server(), json_encode(['op' => 'approve', 'item_id' => $item['id'], 'digest' => $item['digest'], 'tenant_hash' => $this->hash()]), 910002, fn(): array => ['ctx' => $this->env->ctx(910002), 'db' => $this->env->db]);
        $this->assertError($response, 404, 'NOT_FOUND');
        self::assertSame('open', $this->inbox()->get($this->env->ctx(), $item['id'])['status']);
    }

    public function testAgentContextIsNeverADecider(): void
    {
        $item = $this->followup();
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist', 23, ['read', 'draft']);
        $response = saldi_inbox_action_handle($this->server(), json_encode(['op' => 'approve', 'item_id' => $item['id'], 'digest' => $item['digest'], 'tenant_hash' => $this->hash()]), 910001, fn(): array => ['ctx' => $agent, 'db' => $this->env->db]);
        $this->assertError($response, 403, 'PERMISSION_DENIED');
    }

    public function testDatabaseFailuresBecomeUnavailableWithoutDetails(): void
    {
        $response = saldi_inbox_action_handle($this->server(), json_encode(['op' => 'count', 'tenant_hash' => $this->hash()]), 910001, fn() => throw new PDOException('password authentication failed for user "secret"'));
        $this->assertError($response, 503, 'UNAVAILABLE');
        self::assertStringNotContainsString('secret', json_encode($response['body']));
    }
}
