<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/mcp/SaldiMcpServerTest.test.php --- ai/mcp-assistant --- 2026.10.10
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
// Protocol negotiation, authentication ordering, audit reservations and transport limits.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Cover companion foundation contracts.
// 20261004 CDX/LH Add companion part 2 foundation contracts.
// 20261010 CDX/LH Cover current and legacy mobile grant revocation errors.

declare(strict_types=1);

require_once __DIR__ . '/../tools/SaldiToolDbTestCase.php';

final class SaldiMcpServerTest extends SaldiToolDbTestCase
{
	public function testRevokedMobileGrantReturnsUnauthorizedWithPublicCode(): void
	{
		foreach ([new SaldiRuntimeError('GRANT_REVOKED', 'Mobile grant revoked'),
			new SaldiRuntimeError('UNAUTHENTICATED', 'Mobile grant revoked', ['reason' => 'GRANT_REVOKED'])] as $error) {
			$server = new SaldiMcpServer(new SaldiToolRegistry(), static function () use ($error): array {
				throw $error;
			});
			$response = $server->handle(['method' => 'POST', 'headers' => [
				'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->opaqueToken(),
			], 'body' => '{"jsonrpc":"2.0","id":1,"method":"initialize"}']);
			self::assertSame(401, $response['status']);
			self::assertSame(-32001, $response['body']['error']['code']);
			self::assertSame('GRANT_REVOKED', $response['body']['error']['data']['code']);
			self::assertSame('Bearer error="invalid_token"', $response['headers']['WWW-Authenticate']);
		}
	}

    public function testWireSizeAndUnexpectedFailureDoNotLeakDiagnostics(): void
    {
        $registry = new SaldiToolRegistry();
        $dataSchema = SaldiToolSchemas::object([
            'rows' => SaldiToolSchemas::list(SaldiToolSchemas::object(['text' => ['type' => 'string']])),
        ]);
        $registry->register(new SaldiToolDefinition(
            'large_read',
            'Large read',
            'Test fixture',
            SaldiToolSchemas::object([]),
            $dataSchema,
            ['company.read'],
            'read',
            'auto',
            static function (SaldiTenantContext $ctx, SaldiDb $db): array {
                SaldiPermissionService::require($ctx, $db, 'company.read');
                return SaldiToolRuntime::result([
                    'rows' => array_fill(0, 1000, ['text' => str_repeat('"\\', 250)]),
                ]);
            }
        ));
        $registry->register(new SaldiToolDefinition(
            'broken_read',
            'Broken read',
            'Test fixture',
            SaldiToolSchemas::object([]),
            $dataSchema,
            ['company.read'],
            'read',
            'auto',
            static function (SaldiTenantContext $ctx, SaldiDb $db): array {
                SaldiPermissionService::require($ctx, $db, 'company.read');
                throw new PDOException('private-db-diagnostic');
            }
        ));
        $server = new SaldiMcpServer($registry, fn(): array => [
            'ctx' => $this->env->ctx(910001, null, 'agent:saldi-assist', 99, ['read']),
            'db' => $this->env->db,
        ]);
        $request = [
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->opaqueToken()],
        ];
        foreach (['large_read', 'broken_read'] as $name) {
            $response = $server->handle($request + ['body' => json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => ['name' => $name, 'arguments' => (object)[]],
            ])]);
            $result = $response['body']['result'];
            self::assertLessThanOrEqual(200 * 1024, strlen(json_encode($result)));
            SaldiSchemaValidator::validate($result['structuredContent'], $registry->get($name)->outputSchema());
            if ($name === 'large_read') {
                self::assertTrue($result['structuredContent']['possibly_incomplete']);
                self::assertLessThan(1000, count($result['structuredContent']['data']['rows']));
            } else {
                self::assertSame('UNAVAILABLE', $result['structuredContent']['error']['code']);
                self::assertStringNotContainsString('private-db-diagnostic', json_encode($response));
                self::assertSame(
                    'error',
                    $this->env->db->value('SELECT status FROM tool_calls ORDER BY id DESC LIMIT 1')
                );
            }
        }
    }

    public function testConcurrentCallsReserveTheLastMinuteSlotOnce(): void
    {
        $this->env->enable();
        $issued = (new SaldiGrantService($this->env->master, $this->env->db, $this->env->ctx()))
            ->issuePersonal('Concurrent budget test', ['read'], 30);
        $grantId = (int)$issued['grant']['id'];
        for ($i = 0; $i < 59; $i++) {
            $this->env->db->insert('tool_calls', [
                'grant_id' => $grantId,
                'principal' => $issued['grant']['principal'],
                'user_id' => 910001,
                'tool' => 'get_memory',
                'args_hash' => hash('sha256', '{}'),
                'status' => 'reserved',
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
        $code = <<<'PHP'
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
require $input['root'] . '/includes/mcp/SaldiMcpServer.php';
$r = (new SaldiMcpServer())->handle([
    'method' => 'POST',
    'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $input['token']],
    'body' => '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"get_memory","arguments":{}}}',
]);
$e = $r['body']['result']['structuredContent'];
echo $e['ok'] ? 'ok' : $e['error']['code'];
PHP;
        $children = [];
        for ($i = 0; $i < 2; $i++) {
            $process = proc_open(
                [PHP_BINARY, '-r', $code],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            fwrite($pipes[0], json_encode([
                'root' => realpath(__DIR__ . '/../../..'),
                'token' => $issued['token'],
            ], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $children[] = [$process, $pipes];
        }
        $outcomes = [];
        foreach ($children as [$process, $pipes]) {
            $outcomes[] = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $errors);
        }
        sort($outcomes);
        self::assertSame(['RATE_LIMITED', 'ok'], $outcomes);
        self::assertSame(1, (int)$this->env->db->value("SELECT COUNT(*) FROM tool_calls WHERE status='ok'"));
        self::assertSame(1, (int)$this->env->db->value("SELECT COUNT(*) FROM tool_calls WHERE status='rate_limited'"));
        self::assertSame(2, (int)$this->env->db->value('SELECT use_count FROM agent_grants WHERE id=?', [$grantId]));
    }

    private function raw(
        string $body,
        array $headers = [],
        string $method = 'POST',
        ?SaldiTenantContext $ctx = null
    ): array {
        $ctx ??= $this->env->ctx(910001, null, 'agent:saldi-assist', 99, ['read', 'draft']);
        $server = new SaldiMcpServer(
            null,
            fn(): array => ['ctx' => $ctx,
                'db' => $this->env->db],
            ['https://allowed.example.test'],
            'test'
        );
        return $server->handle(['method' => $method,
                'headers' => array_replace(
                    ['Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $this->opaqueToken()],
                    $headers
                ),
                'body' => $body]);
    }

    public function testVersionNegotiationAndHeaderRules(): void
    {
        foreach (['2025-06-18', '2025-03-26', 'unsupported'] as $version) {
            $r = $this->rpc('initialize', ['protocolVersion' => $version]);
            self::assertSame(
                in_array(
                    $version,
                    SaldiMcpServer::PROTOCOL_VERSIONS,
                    true
                ) ? $version : '2025-06-18',
                $r['body']['result']['protocolVersion']
            );
            self::assertSame(['tools' => ['listChanged' => false]], $r['body']['result']['capabilities']);
            self::assertSame('saldi', $r['body']['result']['serverInfo']['name']);
            self::assertStringContainsString('Nothing is posted', $r['body']['result']['instructions']);
        }
        self::assertSame(
            200,
            $this->rpc(
                'initialize',
                ['protocolVersion' => '2025-06-18'],
                null,
                ['MCP-Protocol-Version' => 'unsupported']
            )['status']
        );
        self::assertSame(400, $this->rpc('tools/list', [], null, ['MCP-Protocol-Version' => 'unsupported'])['status']);
        self::assertSame(200, $this->rpc('ping')['status']);
        foreach (SaldiMcpServer::PROTOCOL_VERSIONS as $version) {
            self::assertSame(200, $this->rpc('tools/list', [], null, ['MCP-Protocol-Version' => $version])['status']);
        }
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM tool_calls'));
    }

    public function testRpcMalformedBatchUnknownAndNotification(): void
    {
        self::assertSame(-32700, $this->raw('{')['body']['error']['code']);
        foreach (
            ['[]',
                '[{"jsonrpc":"2.0","id":1,"method":"ping"}]',
                'null',
                '{"jsonrpc":"1.0","id":1,"method":"ping"}',
                '{"jsonrpc":"2.0","id":{},"method":"ping"}'] as $body
        ) {
            foreach (SaldiMcpServer::PROTOCOL_VERSIONS as $version) {
                self::assertSame(
                    -32600,
                    $this->raw(
                        $body,
                        ['MCP-Protocol-Version' => $version]
                    )['body']['error']['code']
                );
            }
        }
        self::assertSame(-32601, $this->rpc('unknown')['body']['error']['code']);
        self::assertSame(-32602, $this->rpc('initialize')['body']['error']['code']);
        foreach (['[]', 'null', '42'] as $params) {
            self::assertSame(
                -32602,
                $this->raw('{"jsonrpc":"2.0","id":1,"method":"ping","params":' . $params . '}')['body']['error']['code']
            );
        }
        foreach (['[]', 'null', '42'] as $args) {
            self::assertSame(
                -32602,
                $this->raw('{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"get_memory"' .
                ',"arguments":' . $args . '}}')['body']['error']['code']
            );
        }
        $notification = $this->raw('{"jsonrpc":"2.0","method":"notifications/initialized"}');
        self::assertSame(202, $notification['status']);
        self::assertNull($notification['body']);
        self::assertSame(
            202,
            $this->raw('{"jsonrpc":"2.0","method":"tools/call","params":{"name":"create_followup_ta' .
            'sk"}}')['status']
        );
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM tool_calls'));
    }

    public function testHttpRulesAndEveryRequestRequiresAuthentication(): void
    {
        foreach (['GET', 'DELETE', 'OPTIONS'] as $method) {
            $r = $this->raw('', [], $method);
            self::assertSame(405, $r['status']);
            self::assertSame('POST', $r['headers']['Allow']);
        }
        self::assertSame(403, $this->raw('{}', ['Origin' => 'https://foreign.example.test'])['status']);
        self::assertSame(
            200,
            $this->raw(
                '{"jsonrpc":"2.0","id":1,"method":"ping"}',
                ['Origin' => 'https://allowed.example.test']
            )['status']
        );
        self::assertSame(413, $this->raw(str_repeat('x', 256 * 1024 + 1))['status']);
        self::assertSame(415, $this->raw('{}', ['Content-Type' => 'text/plain'])['status']);
        self::assertSame(
            200,
            $this->raw(
                '{"jsonrpc":"2.0","id":1,"method":"ping"}',
                ['Content-Type' => 'application/json; charset=UTF-8',
                'Accept' => 'text/plain']
            )['status']
        );
        foreach (['initialize', 'ping', 'tools/list', 'notifications/initialized'] as $method) {
            $r = $this->raw(json_encode(['jsonrpc' => '2.0', 'method' => $method]), ['Authorization' => '']);
            self::assertSame(401, $r['status']);
            self::assertSame(-32001, $r['body']['error']['code']);
            self::assertSame('Bearer error="invalid_token"', $r['headers']['WWW-Authenticate']);
            self::assertArrayNotHasKey('Access-Control-Allow-Origin', $r['headers']);
        }
        $request = ['method' => 'POST',
            'headers' => ['Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->opaqueToken()],
            'body' => '{"jsonrpc":"2.0","id":1,"method":"ping"}'];
        $server = new SaldiMcpServer(null, static fn() => throw new SaldiRuntimeError('DISABLED', 'Disabled'), []);
        self::assertSame(403, $server->handle($request)['status']);
        putenv('SALDI_MCP_DISABLED=1');
        try {
            $r = $server->handle($request);
            self::assertSame(503, $r['status']);
            self::assertSame(['code' => 'DISABLED'], $r['body']['error']['data']);
        } finally {
            putenv('SALDI_MCP_DISABLED');
        }
    }

    public function testListPermissionScopesAndUnknownToolIsIndistinguishable(): void
    {
        $readOnly = $this->env->ctx(910001, null, 'agent:saldi-assist', 99, ['read']);
        $r = $this->rpc('tools/list', [], $readOnly);
        self::assertCount(36, $r['body']['result']['tools']);
        foreach ($r['body']['result']['tools'] as $tool) {
            self::assertTrue($tool['annotations']['readOnlyHint']);
            self::assertFalse($tool['annotations']['destructiveHint']);
            self::assertTrue($tool['annotations']['idempotentHint']);
            self::assertFalse($tool['annotations']['openWorldHint']);
        }
        $debtors = $this->only(6);
        $names = array_column($this->rpc('tools/list', [], $debtors)['body']['result']['tools'], 'name');
        self::assertContains('list_open_items', $names);
        self::assertNotContains('get_invoice', $names);
        self::assertNotContains('get_journal', $names);
        $unknown = $this->toolCall('unknown_tool', [], $readOnly);
        $unlisted = $this->toolCall('propose_journal_entry', [], $readOnly);
        self::assertSame(json_encode($unknown), json_encode($unlisted));
        self::assertSame('PERMISSION_DENIED', $unknown['error']['code']);
        self::assertSame(2, (int)$this->env->db->value("SELECT COUNT(*) FROM tool_calls WHERE status='denied'"));
    }

    public function testReservedRowsMinuteAndDailyRateLimitsAndAuditHashes(): void
    {
        $db = $this->env->db;
        $base = ['grant_id' => 99,
            'principal' => 'agent:saldi-assist',
            'user_id' => 910001,
            'tool' => 'get_memory',
            'args_hash' => hash(
                'sha256',
                '{}'
            ),
            'status' => 'reserved'];
        for ($i = 0; $i < 59; $i++) {
            $db->insert('tool_calls', $base + ['created_at' => gmdate('Y-m-d H:i:s')]);
        }
        self::assertTrue($this->toolCall('get_memory')['ok']);
        $r = $this->toolCall('get_memory');
        self::assertSame('RATE_LIMITED', $r['error']['code']);
        self::assertSame('rate_limited', $db->value('SELECT status FROM tool_calls ORDER BY id DESC LIMIT 1'));
        $db->exec('DELETE FROM tool_calls');
        for ($i = 0; $i < 999; $i++) {
            $db->insert('tool_calls', $base + ['created_at' => gmdate('Y-m-d H:i:s', time() - 3600)]);
        }
        self::assertTrue($this->toolCall('get_memory')['ok']);
        self::assertSame('RATE_LIMITED', $this->toolCall('get_memory')['error']['code']);
        $db->exec('DELETE FROM tool_calls');
        $args = ['to' => '2026-07-31', 'account' => '1000', 'from' => '2026-06-01'];
        self::assertTrue($this->toolCall('get_account_balance', $args)['ok']);
        $row = $db->one('SELECT * FROM tool_calls ORDER BY id DESC LIMIT 1');
        self::assertSame(hash('sha256', saldi_runtime_json($args)), $row['args_hash']);
        self::assertSame('ok', $row['status']);
        self::assertSame(1, (int)$row['result_count']);
        self::assertGreaterThanOrEqual(0, (int)$row['duration_ms']);
        $e = $this->toolCall('get_account_balance', ['account' => '1000', 'unexpected' => 'not-stored']);
        self::assertSame('VALIDATION_ERROR', $e['error']['code']);
        self::assertSame('error', $db->value('SELECT status FROM tool_calls ORDER BY id DESC LIMIT 1'));
        self::assertStringNotContainsString('not-stored', json_encode($db->all('SELECT * FROM tool_calls')));
    }

    public function testNoWriteBeforeAuthenticationWithMissingGrantTable(): void
    {
        $this->env->enable();
        $issued = (new SaldiGrantService(
            $this->env->master,
            $this->env->db,
            $this->env->ctx()
        ))->issuePersonal(
            'Missing schema test',
            ['read'],
            30
        );
        $db = $this->env->db;
        $db->exec('DROP TABLE agent_grants');
        $before = [(int)$db->value('SELECT COUNT(*) FROM tool_calls'),
            (int)$db->value('SELECT COUNT(*) FROM decision_log'),
            $db->value("SELECT var_value FROM settings WHERE var_name='plan_updates_last_step'")];
        $server = new SaldiMcpServer();
        $r = $server->handle(['method' => 'POST',
                'headers' => ['Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $issued['token']],
                'body' => '{"jsonrpc":"2.0","id":1,"method":"tools/list"}']);
        self::assertSame(401, $r['status']);
        self::assertFalse($db->hasTable('agent_grants'));
        self::assertSame(
            $before,
            [(int)$db->value('SELECT COUNT(*) FROM tool_calls'),
                (int)$db->value('SELECT COUNT(*) FROM decision_log'),
            $db->value("SELECT var_value FROM settings WHERE var_name='plan_updates_last_step'")]
        );
    }

    public function testResultByteAndStringCaps(): void
    {
        $rows = array_fill(
            0,
            150,
            ['text' => str_repeat(
                'æ',
                600
            ),
                'second' => str_repeat(
                    'ø',
                    500
                ),
                'third' => str_repeat(
                    'å',
                    500
                )]
        );
        $e = SaldiMcpServer::bounded(['ok' => true,
                'committed' => false,
                'data' => ['rows' => $rows,
                    'total' => 150],
                'receipt' => ['state' => 'done'],
                'citations' => [],
                'possibly_incomplete' => false]);
        self::assertLessThanOrEqual(
            200 * 1024,
            strlen(json_encode(
                $e,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ))
        );
        self::assertTrue($e['possibly_incomplete']);
        self::assertLessThan(150, count($e['data']['rows']));
        self::assertSame(500, mb_strlen($e['data']['rows'][0]['text']));
        self::assertSame(150, $e['data']['total']);
    }
}
