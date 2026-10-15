<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/mcp/SaldiMcpTokenTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Session token origin, raw-user binding, trusted migration and issuance budgets.
//
// 20261001 CDX/LH Created.
// 20261002 CL/LH Behind a TLS-terminating proxy (X-Forwarded-Proto) the https origin is accepted.

declare(strict_types=1);

require_once __DIR__ . '/../tools/SaldiToolDbTestCase.php';
require_once __DIR__ . '/../../../includes/saldi_assist_mcp_token.php';

final class SaldiMcpTokenTest extends SaldiToolDbTestCase
{
    public function testSessionBudgetSurvivesCompanySwitches(): void
    {
        $this->env->enable();
        $session = $this->env->session($this->env->ctx());
        $issuancesFromOtherCompany = array_fill(0, 60, time());
        self::assertSame(429, saldi_mcp_session_token($this->server(), $session, $issuancesFromOtherCompany)['status']);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM agent_grants'));
        $issuancesFromOtherCompany = array_fill(0, 60, time() - 3601);
        self::assertSame(200, saldi_mcp_session_token($this->server(), $session, $issuancesFromOtherCompany)['status']);
        self::assertCount(1, $issuancesFromOtherCompany);
    }
    private function server(array $overrides = []): array
    {
        return array_replace(
            ['REQUEST_METHOD' => 'POST',
                'HTTP_HOST' => 'localhost',
                'HTTP_ORIGIN' => 'http://localhost'],
            $overrides
        );
    }

    public function testOriginSessionNoUserAndDisabledResponses(): void
    {
        self::assertSame(405, saldi_mcp_session_token($this->server(['REQUEST_METHOD' => 'GET']), '')['status']);
        self::assertSame(
            403,
            saldi_mcp_session_token(
                $this->server(['HTTP_ORIGIN' => 'https://foreign.example.test']),
                ''
            )['status']
        );
        self::assertSame(403, saldi_mcp_session_token($this->server(['HTTP_ORIGIN' => 'null']), '')['status']);
        self::assertSame(401, saldi_mcp_session_token($this->server(), bin2hex(random_bytes(16)))['status']);
        $session = $this->env->session($this->env->ctx());
        $disabled = saldi_mcp_session_token($this->server(), $session);
        self::assertSame(200, $disabled['status']);
        self::assertSame(['enabled' => false], $disabled['body']);
        self::assertSame('no-store', $disabled['headers']['Cache-Control']);
        $revisor = $this->env->session($this->env->ctx(), true);
        $denied = saldi_mcp_session_token($this->server(), $revisor);
        self::assertSame(403, $denied['status']);
        self::assertSame(['error' => 'no_user'], $denied['body']);
        $referer = $this->server(['HTTP_REFERER' => 'http://localhost/saldi/index/main.php']);
        unset($referer['HTTP_ORIGIN']);
        self::assertSame(200, saldi_mcp_session_token($referer, $session)['status']);
        $referer['HTTP_REFERER'] = 'http://localhost@foreign.example.test/saldi/';
        self::assertSame(403, saldi_mcp_session_token($referer, $session)['status']);
        // TLS ends at a proxy that does not set HTTPS=on: the browser still sends its https origin.
        $proxied = $this->server(['HTTP_ORIGIN' => 'https://localhost', 'HTTP_X_FORWARDED_PROTO' => 'https']);
        self::assertSame(200, saldi_mcp_session_token($proxied, $session)['status']);
        self::assertSame(403, saldi_mcp_session_token($this->server(['HTTP_ORIGIN' => 'https://localhost']), $session)['status']);
    }

    public function testIssuanceRawUsernameBindingAndHourlyLimit(): void
    {
        $this->env->enable();
        $ctx = $this->env->ctx();
        $session = $this->env->session($ctx);
        $r = saldi_mcp_session_token($this->server(), $session);
        self::assertSame(200, $r['status']);
        self::assertTrue($r['body']['enabled']);
        self::assertSame($ctx->tenantHash(), $r['body']['tenant_hash']);
        self::assertSame($ctx->userHash(), $r['body']['user_hash']);
        self::assertSame($ctx->installId, $r['body']['install_id']);
        self::assertMatchesRegularExpression('/Z$/', $r['body']['expires_at']);
        $auth = saldi_runtime_context_from_token($r['body']['token']);
        self::assertSame("O'Brien", $auth['ctx']->username);
        self::assertSame('agent:saldi-assist', $auth['ctx']->principal);
        self::assertSame(['read', 'draft'], $auth['ctx']->scopes);
        $auth['db']->close();
        for ($i = 0; $i < 59; $i++) {
            $this->env->db->insert(
                'agent_grants',
                ['principal' => 'agent:saldi-assist',
                    'label' => 'Issuance fixture',
                    'user_id' => $ctx->userId,
                    'user_hash' => $ctx->userHash(),
                    'kind' => 'session',
                    'scopes' => 'read draft',
                    'token_hash' => hash(
                        'sha256',
                        random_bytes(32)
                    ),
                    'session_ref' => md5($session),
                    'created_at' => gmdate('Y-m-d H:i:s'),
                    'expires_at' => gmdate(
                        'Y-m-d H:i:s',
                        time() + 3600
                    )]
            );
        }
        self::assertSame(429, saldi_mcp_session_token($this->server(), $session)['status']);
        $this->env->db->exec('UPDATE agent_grants SET created_at = ?', [gmdate('Y-m-d H:i:s', time() - 3601)]);
        self::assertSame(200, saldi_mcp_session_token($this->server(), $session)['status']);
        self::assertSame(5, (int)$this->env->db->value('SELECT COUNT(*) FROM agent_grants WHERE revoked_at IS NULL'));
    }

    public function testTrustedEndpointMigratesMissingTablesBeforeDisabledCheck(): void
    {
        $session = $this->env->session($this->env->ctx());
        foreach (
            ['agent_grants',
                'inbox_items',
                'decision_log',
                'tool_calls',
                'assistant_memory',
                'automation_jobs'] as $table
        ) {
            $this->env->db->exec('DROP TABLE ' . $table);
        }
        $this->env->db->exec("DELETE FROM settings WHERE var_name='plan_updates_last_step'");
        $r = saldi_mcp_session_token($this->server(), $session);
        self::assertSame(['enabled' => false], $r['body']);
        self::assertTrue($this->env->db->hasTable('agent_grants'));
        self::assertTrue($this->env->db->hasTable('inbox_items'));
    }
}
