<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/mcp/SaldiMcpHttpTest.test.php --- ai/mcp-assistant --- 2026.10.04
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
// Real Apache MCP and session-token requests through php -r stream contexts.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Cover companion foundation contracts.
// 20261004 CDX/LH Add companion part 2 foundation contracts.

declare(strict_types=1);

require_once __DIR__ . '/../tools/SaldiToolDbTestCase.php';

final class SaldiMcpHttpTest extends SaldiToolDbTestCase
{
    /** Exercise Apache from a separate php -r process, keeping bearer tokens on stdin. @return array */
    private function http(string $path, string $method, array $headers = [], string $body = ''): array
    {
        $code = <<<'PHP'
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$context = stream_context_create(['http' => [
    'method' => $input['method'],
    'header' => implode("\r\n", $input['headers']),
    'content' => $input['body'],
    'ignore_errors' => true,
    'follow_location' => 0,
    'timeout' => 10,
]]);
$content = file_get_contents('http://localhost/saldi/' . $input['path'], false, $context);
preg_match('/\s([0-9]{3})\s/', $http_response_header[0], $m);
echo json_encode(['status' => (int)$m[1], 'headers' => $http_response_header, 'body' => $content], JSON_THROW_ON_ERROR);
PHP;
        $process = proc_open(
            [PHP_BINARY,
                '-r',
                $code],
            [0 => ['pipe',
                    'r'],
                1 => ['pipe',
                    'w'],
                2 => ['pipe',
                    'w']],
            $pipes
        );
        fwrite(
            $pipes[0],
            json_encode(
                ['path' => $path,
                    'method' => $method,
                    'headers' => $headers,
                    'body' => $body],
                JSON_THROW_ON_ERROR
            )
        );
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);
        self::assertSame('', $errors);
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testActualApacheEndpointWithRealSessionGrant(): void
    {
        $this->env->enable();
        $ctx = $this->env->ctx();
        $session = $this->env->session($ctx);
        $issued = (new SaldiGrantService($this->env->master, $this->env->db, $ctx))->issueSession(md5($session));
        $body = '{"jsonrpc":"2.0","id":1,"method":"tools/list"}';
        self::assertSame(401, $this->http('mcp/', 'POST', ['Content-Type: application/json'], $body)['status']);
        self::assertSame(405, $this->http('mcp/', 'GET')['status']);
        $headers = ['Content-Type: application/json',
            'Authorization: Bearer ' . $issued['token'],
            'MCP-Protocol-Version: 2025-06-18'];
        self::assertSame(
            403,
            $this->http(
                'mcp/',
                'POST',
                array_merge(
                    $headers,
                    ['Origin: https://foreign.example.test']
                ),
                $body
            )['status']
        );
        $response = $this->http('mcp/', 'POST', $headers, $body);
        self::assertSame(200, $response['status']);
        $rpc = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(54, $rpc['result']['tools']);
        self::assertContains('get_company_context', array_column($rpc['result']['tools'], 'name'));
        self::assertStringNotContainsString('Access-Control-Allow-Origin', implode("\n", $response['headers']));
        self::assertSame(
            1,
            (int)$this->env->db->value(
                'SELECT use_count FROM agent_grants WHERE id = ?',
                [$issued['grant']['id']]
            )
        );
        $call = $this->http(
            'mcp/',
            'POST',
            $headers,
            '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"get_company' .
            '_context","arguments":{}}}'
        );
        $envelope = json_decode($call['body'], true, 512, JSON_THROW_ON_ERROR)['result']['structuredContent'];
        self::assertTrue($envelope['ok']);
        self::assertSame($ctx->tenantHash(), $envelope['data']['tenant_hash']);
        self::assertSame(1, (int)$this->env->db->value("SELECT COUNT(*) FROM tool_calls WHERE status='ok'"));
        self::assertSame(403, $this->http('mcp/hidden.php', 'GET')['status']);
    }

    public function testActualTokenEndpointWithSessionCookie(): void
    {
        $this->env->enable();
        $ctx = $this->env->ctx();
        $session = $this->env->session($ctx);
        $r = $this->http(
            'includes/saldi_assist_mcp_token.php',
            'POST',
            ['Origin: http://localhost',
            'Cookie: PHPSESSID=' . $session]
        );
        self::assertSame(200, $r['status']);
        $body = json_decode($r['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($body['enabled']);
        self::assertSame($ctx->userHash(), $body['user_hash']);
        self::assertNotNull(SaldiGrantService::parse($body['token']));
        self::assertSame(
            403,
            $this->http(
                'includes/saldi_assist_mcp_token.php',
                'POST',
                ['Origin: https://foreign.example.test',
                'Cookie: PHPSESSID=' . $session]
            )['status']
        );
        self::assertSame(
            401,
            $this->http(
                'includes/saldi_assist_mcp_token.php',
                'POST',
                ['Origin: http://localhost']
            )['status']
        );
    }
}
