<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/ui/SaldiJournalSuggestEndpointTest.test.php --- ai/mcp-assistant --- 2026.10.03
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
// Endpoint security, real legacy online sessions, switches and bounded telemetry.
//
// 20261002 CDX/LH Created.
// 20261003 CDX/LH Add regression coverage for the confirmed Assist review findings.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiPostingTestCase.php';
if (!defined('SALDI_JOURNAL_SUGGEST_LIBRARY')) {
    define('SALDI_JOURNAL_SUGGEST_LIBRARY', true);
}
require_once __DIR__ . '/../../../finans/kassekladde_includes/assistSuggest.php';

final class SaldiJournalSuggestEndpointTest extends SaldiPostingTestCase
{
    private string $csrf;
    private int $sessionCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->csrf = bin2hex(random_bytes(32));
        $_SESSION = ['inbox_csrf' => $this->csrf];
        $this->sessionCalls = 0;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    private function server(array $overrides = []): array
    {
        return array_merge([
            'REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'saldi.test', 'HTTP_ORIGIN' => 'http://saldi.test',
            'HTTP_X_SALDI_CSRF' => $this->csrf, 'CONTENT_TYPE' => 'application/json; charset=utf-8',
        ], $overrides);
    }

    private function call(array $input, array $server = [], int $userId = 910001): array
    {
        return saldi_journal_suggest_handle(
            $this->server($server),
            json_encode($input, JSON_THROW_ON_ERROR),
            $userId,
            function (): array {
                $this->sessionCalls++;
                return ['ctx' => $this->env->ctx(), 'db' => $this->env->db];
            }
        );
    }

    private function assertFailure(array $response, int $status, string $code): void
    {
        self::assertSame($status, $response['status']);
        self::assertFalse($response['body']['ok']);
        self::assertSame($code, $response['body']['error']['code']);
        self::assertNotEmpty($response['body']['error']['message_da']);
    }

    public function testRequestGuardsFailBeforeOpeningSession(): void
    {
        $input = ['op' => 'suggest', 'kladde_id' => 42, 'line' => ['description' => 'Adobe abonnement']];
        $this->assertFailure($this->call($input, ['REQUEST_METHOD' => 'GET']), 405, 'VALIDATION_ERROR');
        $this->assertFailure(
            $this->call($input, ['HTTP_ORIGIN' => 'https://elsewhere.test']),
            403,
            'PERMISSION_DENIED'
        );
        $this->assertFailure($this->call($input, ['HTTP_ORIGIN' => '']), 403, 'PERMISSION_DENIED');
        $this->assertFailure($this->call($input, ['HTTP_X_SALDI_CSRF' => '']), 403, 'PERMISSION_DENIED');
        $this->assertFailure($this->call($input, [], 0), 403, 'PERMISSION_DENIED');
        $this->assertFailure($this->call($input, [], -1), 403, 'PERMISSION_DENIED');
        $this->assertFailure($this->call($input, ['CONTENT_TYPE' => 'text/plain']), 400, 'VALIDATION_ERROR');
        $this->assertFailure($this->call(['op' => 'approve']), 400, 'VALIDATION_ERROR');
        self::assertSame(0, $this->sessionCalls);
        $malformed = saldi_journal_suggest_handle($this->server(), '{', 910001, fn() => self::fail('Session opened'));
        $this->assertFailure($malformed, 400, 'VALIDATION_ERROR');
        $oversized = saldi_journal_suggest_handle(
            $this->server(),
            str_repeat('x', 8193),
            910001,
            fn() => self::fail('Session opened')
        );
        $this->assertFailure($oversized, 400, 'VALIDATION_ERROR');
    }

    public function testSwitchDefaultsOffAndSuggestUsesHumanContextWithoutWritingHistory(): void
    {
        $this->repeat(4);
        $input = [
            'op' => 'suggest', 'kladde_id' => 42, 'line' => [
                'description' => 'Adobe abonnement', 'amount' => '1250.00',
                'party' => ['ledger' => 'supplier', 'account' => '1005'],
            ],
        ];
        self::assertSame(['ok' => true, 'enabled' => false], $this->call($input)['body']);
        $this->setting('assistant', 'journal_suggestions', 'on');
        $response = $this->call($input);
        self::assertSame(200, $response['status']);
        self::assertTrue($response['body']['ok']);
        self::assertSame('2800', $response['body']['suggestions'][0]['debit']['account']);
        self::assertCount(4, $response['body']['duplicates']);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM assist_events'));
        self::assertSame(4, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde'));
        self::assertSame(3, (int)$this->env->db->value('SELECT COUNT(*) FROM kladdeliste'));
        $this->assertFailure($this->call(array_merge($input, ['kladde_id' => 999999])), 404, 'NOT_FOUND');
        $this->assertFailure(
            $this->call(array_merge($input, ['line' => ['description' => '']])),
            400,
            'VALIDATION_ERROR'
        );
    }

    public function testOriginAcceptsProxyAndRefererButRefusesMissingOrigin(): void
    {
        $input = ['op' => 'suggest', 'kladde_id' => 42, 'line' => ['description' => 'Adobe abonnement']];
        self::assertSame(200, $this->call($input, [
            'HTTP_X_FORWARDED_PROTO' => 'https, http', 'HTTP_ORIGIN' => 'https://saldi.test',
        ])['status']);
        self::assertSame(200, $this->call($input, [
            'HTTP_ORIGIN' => '', 'HTTP_REFERER' => 'http://saldi.test/finans/kassekladde.php?kladde_id=42',
        ])['status']);
    }

    public function testEventsAreBoundedToOneHundredTwentyPerSessionAndNeverLogDescription(): void
    {
        $this->setting('assistant', 'journal_suggestions', 'on');
        $this->repeat(4);
        $rule = $this->rule();
        $this->env->db->exec('UPDATE posting_rules SET id = 1 WHERE id = ?', [$rule['result']['rule_id']]);
        $this->call(['op' => 'suggest', 'kladde_id' => 42, 'line' => ['description' => 'Adobe abonnement', 'party' => ['ledger' => 'supplier', 'account' => '1005']]]);
        $events = ['suggestion_shown', 'suggestion_used', 'suggestion_dismissed', 'duplicate_warned', 'ask_from_row'];
        for ($index = 0; $index < 121; $index++) {
            self::assertSame(['ok' => true], $this->call([
                'op' => 'event', 'event' => $events[$index % 5], 'subject' => in_array($events[$index % 5], ['suggestion_shown', 'suggestion_used'], true) ? 'rule:1' : 'kladde:42',
                'source' => 'rule', 'description' => 'Must not be stored',
            ])['body']);
        }
        self::assertSame(120, (int)$this->env->db->value('SELECT COUNT(*) FROM assist_events'));
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM assist_events WHERE detail IS NOT NULL'));
        $_SESSION['journal_assist_events'][0] = time() - 3601;
        self::assertTrue($this->call([
            'op' => 'event', 'event' => 'suggestion_used', 'subject' => 'rule:1', 'source' => 'rule',
        ])['body']['ok']);
        self::assertSame(121, (int)$this->env->db->value('SELECT COUNT(*) FROM assist_events'));
        foreach ([['event' => 'paid'], ['source' => 'agent']] as $override) {
            $this->assertFailure($this->call(array_merge([
                'op' => 'event', 'event' => 'suggestion_used', 'subject' => 'rule:1',
            ], $override)), 400, 'VALIDATION_ERROR');
        }
        $this->setting('assistant', 'journal_suggestions', 'off');
        self::assertSame(['ok' => true, 'enabled' => false], $this->call([
            'op' => 'event', 'event' => 'suggestion_used', 'subject' => 'rule:1',
        ])['body']);
        self::assertSame(121, (int)$this->env->db->value('SELECT COUNT(*) FROM assist_events'));
    }

    public function testLiveOnlineSessionRejectsEndedRevisorAndUnknownUsers(): void
    {
        $sessionId = $this->env->session($this->env->ctx());
        $this->env->master->exec('UPDATE online SET language_id = ? WHERE session_id = ?', [2, $sessionId]);
        $open = saldi_journal_suggest_session($sessionId);
        try {
            self::assertSame($this->env->name, $open['ctx']->db);
            self::assertSame(910001, $open['ctx']->userId);
            self::assertTrue($open['ctx']->isHuman());
            self::assertSame(2, $open['ctx']->languageId);
        } finally {
            $open['db']->close();
        }
        $this->env->master->exec('DELETE FROM online WHERE session_id = ?', [$sessionId]);
        $this->error('UNAUTHENTICATED', fn() => saldi_journal_suggest_session($sessionId));
        $revisor = $this->env->session($this->env->ctx(), true);
        $this->error('PERMISSION_DENIED', fn() => saldi_journal_suggest_session($revisor));
        $unknown = $this->env->session($this->env->ctx());
        $this->env->master->exec(
            'UPDATE online SET brugernavn = ? WHERE session_id = ?',
            ['Unknown fixture user', $unknown]
        );
        $this->error('UNAUTHENTICATED', fn() => saldi_journal_suggest_session($unknown));
        $this->error('UNAUTHENTICATED', fn() => saldi_journal_suggest_session(''));
    }

    public function testSessionAndPermissionsCannotBeSubstitutedByBody(): void
    {
        $this->setting('assistant', 'journal_suggestions', 'on');
        $input = ['op' => 'suggest', 'kladde_id' => 42, 'line' => ['description' => 'Adobe abonnement']];
        $this->assertFailure($this->call($input, [], 910002), 401, 'UNAUTHENTICATED');
        $ended = saldi_journal_suggest_handle($this->server(), json_encode($input), 910001, static function (): array {
            throw new SaldiRuntimeError('UNAUTHENTICATED', 'Session ended');
        });
        $this->assertFailure($ended, 401, 'UNAUTHENTICATED');
        $agent = $this->env->ctx(910001, null, 'agent:saldi-assist', 23, ['read', 'draft']);
        $this->error('PERMISSION_DENIED', fn() => saldi_journal_suggest_perform($agent, $this->env->db, $input));
        $this->error('PERMISSION_DENIED', fn() => saldi_journal_suggest_perform(
            $this->env->ctx(910001, str_repeat('0', 16)),
            $this->env->db,
            $input
        ));
    }
    public function testShownAndUsedRequireRecentSubjectsFromThisSession(): void
    {
        $this->setting('assistant', 'journal_suggestions', 'on');
        $this->repeat(4);
        foreach (['suggestion_shown', 'suggestion_used'] as $event) {
            foreach (['kladde:42', 'rule:999', 'item:42', 'line:42', 'Adobe password', null] as $subject) {
                self::assertSame(['ok' => true], $this->call(['op' => 'event', 'event' => $event, 'subject' => $subject])['body']);
            }
        }
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM assist_events'));
        $this->call(['op' => 'suggest', 'kladde_id' => 42, 'line' => ['description' => 'Adobe abonnement']]);
        foreach (['suggestion_shown', 'suggestion_used'] as $event) {
            $this->call(['op' => 'event', 'event' => $event, 'subject' => 'kladde:42', 'source' => 'history']);
        }
        self::assertSame(2, (int)$this->env->db->value('SELECT COUNT(*) FROM assist_events'));
        $_SESSION['journal_assist_suggestions'][0]['at'] = time() - 1801;
        $this->call(['op' => 'event', 'event' => 'suggestion_used', 'subject' => 'kladde:42', 'source' => 'history']);
        self::assertSame(2, (int)$this->env->db->value('SELECT COUNT(*) FROM assist_events'));
    }

}
