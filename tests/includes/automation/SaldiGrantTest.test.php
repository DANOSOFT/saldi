<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiGrantTest.test.php --- ai/mcp-assistant --- 2026.10.01
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
// Opaque tokens, live sessions, bounded grants and authentication without migrations.
//
// 20261001 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';

final class SaldiGrantTest extends SaldiRuntimeDbTestCase
{
    private function service(?SaldiTenantContext $ctx = null): SaldiGrantService
    {
        return new SaldiGrantService($this->env->master, $this->env->db, $ctx ?? $this->env->ctx());
    }

    private function verify(string $token): array
    {
        $result = saldi_runtime_context_from_token($token);
        $result['db']->close();
        return $result;
    }

    public function testPersonalIssueParseVerifyLiveRightsAndNoHashDisclosure(): void
    {
        $this->env->enable();
        $issued = $this->service()->issuePersonal('My agent', ['read', 'draft'], 90);
        $p = SaldiGrantService::parse($issued['token']);
        self::assertSame(43, strlen($p['secret']));
        self::assertSame($this->env->regnskab['id'], $p['regnskab_id']);
        self::assertArrayNotHasKey('token_hash', $issued['grant']);
        self::assertArrayNotHasKey('token_hash', $this->service()->listMine()[0]);
        self::assertSame(910001, (int)$issued['grant']['user_id']);
        $this->env->db->exec("UPDATE brugere SET rettigheder = '2' WHERE id = 910001");
        $verified = $this->verify($issued['token']);
        self::assertSame('2', $verified['ctx']->rights);
        self::assertSame(910001, $verified['ctx']->onBehalfOf());
        self::assertSame('agent:external:' . $p['grant_id'], $verified['ctx']->principal);
        self::assertArrayNotHasKey('token_hash', $verified['grant']);
        self::assertSame(1, (int)$this->env->db->value('SELECT use_count FROM agent_grants WHERE id = ?', [$p['grant_id']]));
    }

    public function testMalformedInstallMismatchRevokedExpiredAndAtomicUses(): void
    {
        $this->env->enable();
        self::assertNull(SaldiGrantService::parse('garbage'));
        $issued = $this->service()->issuePersonal('One use', ['read'], 30);
        $id = $issued['grant']['id'];
        $parts = explode('.', $issued['token']);
        $parts[1] = 'another-install';
        $this->error('UNAUTHENTICATED', fn() => $this->verify(implode('.', $parts)));
        self::assertSame(0, (int)$this->env->db->value('SELECT use_count FROM agent_grants WHERE id = ?', [$id]));
        $this->env->db->exec('UPDATE agent_grants SET max_uses = 1 WHERE id = ?', [$id]);
        $this->verify($issued['token']);
        $this->error('UNAUTHENTICATED', fn() => $this->verify($issued['token']));
        self::assertSame(1, (int)$this->env->db->value('SELECT use_count FROM agent_grants WHERE id = ?', [$id]));
        $this->env->db->exec('UPDATE agent_grants SET max_uses = NULL, expires_at = ? WHERE id = ?', ['2000-01-01 00:00:00', $id]);
        $this->error('UNAUTHENTICATED', fn() => $this->verify($issued['token']));
        $this->env->db->exec('UPDATE agent_grants SET expires_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() + 3600), $id]);
        $this->service()->revoke((int)$id);
        $this->error('UNAUTHENTICATED', fn() => $this->verify($issued['token']));
        self::assertSame(1, (int)$this->env->db->value("SELECT COUNT(*) FROM decision_log WHERE event = 'grant_revoked'"));
    }

    public function testSessionNeedsMatchingLiveOnlineRowAndKeepsLatestFive(): void
    {
        $this->env->enable();
        $ctx = $this->env->ctx();
        $session = $this->env->session($ctx);
        $issued = [];
        for ($i = 0; $i < 6; $i++) {
            $issued[] = $this->service()->issueSession(md5($session));
        }
        $this->error('UNAUTHENTICATED', fn() => $this->verify($issued[0]['token']));
        $verified = $this->verify($issued[5]['token']);
        self::assertSame('agent:saldi-assist', $verified['ctx']->principal);
        self::assertSame($ctx->userHash(), $verified['ctx']->userHash());
        self::assertSame(2000, (int)$verified['grant']['max_uses']);
        self::assertSame(5, (int)$this->env->db->value("SELECT COUNT(*) FROM agent_grants WHERE revoked_at IS NULL"));
        $this->env->master->exec('UPDATE online SET brugernavn = ? WHERE session_id = ?', ['someone else', $session]);
        $this->error('UNAUTHENTICATED', fn() => $this->verify($issued[5]['token']));
        $this->env->master->exec('DELETE FROM online WHERE session_id = ?', [$session]);
        $this->error('UNAUTHENTICATED', fn() => $this->verify($issued[5]['token']));
    }

    public function testRevisorSessionIsRejectedWithoutUse(): void
    {
        $this->env->enable();
        $session = $this->env->session($this->env->ctx(), true);
        $issued = $this->service()->issueSession(md5($session));
        $this->error('UNAUTHENTICATED', fn() => $this->verify($issued['token']));
        self::assertSame(0, (int)$this->env->db->value('SELECT use_count FROM agent_grants WHERE id = ?', [$issued['grant']['id']]));
    }

    public function testPersonalScopesTtlSelfOnlyAndMaximumFive(): void
    {
        $this->error('DISABLED', fn() => $this->service()->issuePersonal('off', ['read'], 30));
        $this->env->enable();
        foreach ([[[], 30], [['draft'], 30], [['read', 'admin'], 30], [['read'], 31]] as [$scopes, $ttl]) {
            $this->error('VALIDATION_ERROR', fn() => $this->service()->issuePersonal('invalid', $scopes, $ttl));
        }
        $limited = $this->env->ctx(910001, '2');
        $this->error('VALIDATION_ERROR', fn() => $this->service($limited)->issuePersonal('limited', ['read'], 365));
        $this->error('PERMISSION_DENIED', fn() => $this->service($this->env->ctx(910001, null, 'agent:saldi-assist', 1, ['read', 'draft']))->issuePersonal('agent', ['read'], 30));
        for ($i = 0; $i < 5; $i++) {
            $grant = $this->service()->issuePersonal('grant ' . $i, ['read'], $i === 0 ? 365 : 30);
            self::assertSame(910001, (int)$grant['grant']['user_id']);
        }
        $this->error('CONFLICT', fn() => $this->service()->issuePersonal('sixth', ['read'], 30));
        self::assertCount(5, $this->service()->listAll());
        $this->error('PERMISSION_DENIED', fn() => $this->service($limited)->listAll());
        $this->error('NOT_FOUND', fn() => $this->service($this->env->ctx(910002, '2'))->revoke((int)$grant['grant']['id']));
    }

    public function testMissingSchemaAuthenticationNeverCreatesTables(): void
    {
        $fresh = new SaldiRuntimeTestEnv(false);
        try {
            $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $token = 'smcp1.' . saldi_runtime_install_id() . '.' . $fresh->regnskab['id'] . '.1.' . $secret;
            $this->error('UNAUTHENTICATED', fn() => saldi_runtime_context_from_token($token));
            foreach (['agent_grants', 'inbox_items', 'decision_log', 'tool_calls', 'assistant_memory', 'automation_jobs'] as $table) {
                self::assertFalse($fresh->db->hasTable($table));
            }
        } finally {
            $fresh->close();
        }
    }

    public function testDisabledAndChangedUsernameDoNotConsumeGrant(): void
    {
        $this->env->enable();
        $issued = $this->service()->issuePersonal('Bound', ['read'], 30);
        SaldiSettings::set($this->env->db, $this->env->ctx(), 'assistant', 'tools_enabled', 'off');
        $this->error('DISABLED', fn() => $this->verify($issued['token']));
        SaldiSettings::set($this->env->db, $this->env->ctx(), 'assistant', 'tools_enabled', 'on');
        $this->env->db->exec('UPDATE brugere SET brugernavn = ? WHERE id = 910001', ['renamed']);
        $this->error('UNAUTHENTICATED', fn() => $this->verify($issued['token']));
        self::assertSame(0, (int)$this->env->db->value('SELECT use_count FROM agent_grants WHERE id = ?', [$issued['grant']['id']]));
    }

    public function testConcurrentVerificationHonoursAtomicMaxUses(): void
    {
        $this->env->enable();
        $issued = $this->service()->issuePersonal('Concurrent', ['read'], 30);
        $this->env->db->exec('UPDATE agent_grants SET max_uses = 2 WHERE id = ?', [$issued['grant']['id']]);
        $code = 'require ' . var_export(__DIR__ . '/../../../includes/runtime/bootstrap.php', true) . ';'
            . '$token = stream_get_contents(STDIN); try { $r = saldi_runtime_context_from_token($token); $r["db"]->close(); echo "ok"; } catch (SaldiRuntimeError $e) { echo $e->errorCode; }';
        $processes = [];
        for ($i = 0; $i < 6; $i++) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, '-r', $code], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            fwrite($pipes[0], $issued['token']);
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }
        $outcomes = [];
        foreach ($processes as [$process, $pipes]) {
            $outcomes[] = stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process));
        }
        self::assertSame(2, count(array_filter($outcomes, fn($v) => $v === 'ok')));
        self::assertSame(4, count(array_filter($outcomes, fn($v) => $v === 'UNAUTHENTICATED')));
        self::assertSame(2, (int)$this->env->db->value('SELECT use_count FROM agent_grants WHERE id = ?', [$issued['grant']['id']]));
    }

    public function testKillSwitchExternalGateWrongSecretAndAdminRevoke(): void
    {
        $this->env->enable();
        $issued = $this->service()->issuePersonal('Bound access', ['read'], 30);
        $old = getenv('SALDI_MCP_DISABLED');
        try {
            putenv('SALDI_MCP_DISABLED=1');
            $this->error('DISABLED', fn() => $this->verify($issued['token']));
        } finally {
            putenv($old === false ? 'SALDI_MCP_DISABLED' : 'SALDI_MCP_DISABLED=' . $old);
        }
        SaldiSettings::set($this->env->db, $this->env->ctx(), 'assistant', 'external_agents', 'off');
        $this->error('DISABLED', fn() => $this->verify($issued['token']));
        SaldiSettings::set($this->env->db, $this->env->ctx(), 'assistant', 'external_agents', 'on');
        $parts = explode('.', $issued['token']);
        $parts[4] = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->error('UNAUTHENTICATED', fn() => $this->verify(implode('.', $parts)));
        $this->service($this->env->ctx(910002))->revoke((int)$issued['grant']['id']);
        $this->service($this->env->ctx(910002))->revoke((int)$issued['grant']['id']);
        self::assertSame(1, (int)$this->env->db->value("SELECT COUNT(*) FROM decision_log WHERE event = 'grant_revoked'"));
        self::assertSame(0, (int)$this->env->db->value('SELECT use_count FROM agent_grants WHERE id = ?', [$issued['grant']['id']]));
    }
}
