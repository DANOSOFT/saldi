<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/saldi_assist_mcp_token.php --- ai/mcp-assistant --- 2026.10.02
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
// Same-origin, session-bound MCP delegation with hourly issuance limits.
//
// 20261001 CDX/LH Created.
// 20261002 CL/LH Same request-origin rule as Opgaver (honours X-Forwarded-Proto behind a TLS proxy).

declare(strict_types=1);

require_once __DIR__ . '/runtime/bootstrap.php';

/** Same-origin, session-bound grant issuance. No legacy includes or HTML rendering.
 * @return array{status:int,body:array,headers:array}
 */
function saldi_mcp_session_token(array $server, string $sessionId, ?array &$issuances = null): array
{
	$respond = static fn(int $status, array $body, array $headers = []): array => ['status' => $status, 'body' => $body, 'headers' => ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store'] + $headers];
	if (($server['REQUEST_METHOD'] ?? '') !== 'POST') {
		return $respond(405, ['error' => 'method_not_allowed'], ['Allow' => 'POST']);
	}
	$own = saldi_runtime_request_origin($server);
	$source = $server['HTTP_ORIGIN'] ?? $server['HTTP_REFERER'] ?? '';
	if ($own === null || saldi_runtime_origin($source) !== $own || (isset($server['HTTP_ORIGIN']) && !preg_match('~^https?://[^/?#]+$~D', $source))) {
		return $respond(403, ['error' => 'foreign_origin']);
	}
	$tenant = null;
	try {
		$master = saldi_runtime_master();
		$online = $master->one('SELECT brugernavn, db, revisor FROM online WHERE session_id = ? ORDER BY logtime DESC LIMIT 1', [$sessionId]);
		if ($sessionId === '' || !$online || empty($online['brugernavn'])) {
			return $respond(401, ['error' => 'no_session']);
		}
		if (in_array($online['revisor'], [true, 1, '1', 't', 'true', 'on'], true) || $online['db'] === saldi_runtime_config()['master_db']) {
			return $respond(403, ['error' => 'no_user']);
		}
		$entry = saldi_runtime_tenant_by_db($online['db']);
		$tenant = $entry['db'];
		$userId = (int)$tenant->value('SELECT id FROM brugere WHERE brugernavn = ?', [$online['brugernavn']]);
		if ($userId <= 0) {
			return $respond(403, ['error' => 'no_user']);
		}
		$ctx = saldi_runtime_context_for_user($tenant, $entry['regnskab'], $userId, 'user:' . $userId);
		saldi_runtime_ensure_schema($tenant);
		if (SaldiSettings::get($tenant, 'assistant', 'tools_enabled') !== 'on') {
			return $respond(200, ['enabled' => false]);
		}
		$ref = md5($sessionId);
		return $tenant->transaction(static function () use ($tenant, $master, $ctx, $ref, $respond, &$issuances): array {
			$tenant->lock('saldi_token_issuance:' . $ref);
			if ($issuances !== null) {
				// The PHP session lock also bounds issuance across company switches.
				$issuances = array_values(array_filter($issuances, static fn(int $time): bool => $time > time() - 3600));
			}
			$n = (int)$tenant->value("SELECT COUNT(*) FROM agent_grants WHERE kind = 'session' AND session_ref = ? AND created_at >= ?", [$ref, gmdate('Y-m-d H:i:s', time() - 3600)]);
			if ($n >= 60 || ($issuances !== null && count($issuances) >= 60)) {
				return $respond(429, ['error' => 'rate_limited']);
			}
			$issued = (new SaldiGrantService($master, $tenant, $ctx))->issueSession($ref);
			if ($issuances !== null) {
				$issuances[] = time();
			}
			return $respond(200, ['enabled' => true, 'token' => $issued['token'], 'expires_at' => str_replace(' ', 'T', $issued['grant']['expires_at']) . 'Z', 'install_id' => $ctx->installId, 'tenant_hash' => $ctx->tenantHash(), 'user_hash' => $ctx->userHash()]);
		});
	} catch (SaldiRuntimeError $e) {
		return $respond($e->httpStatus(), ['error' => $e->errorCode]);
	} catch (Throwable $e) {
		error_log('MCP session grant unavailable');
		return $respond(503, ['error' => 'UNAVAILABLE']);
	} finally {
		if ($tenant !== null && PHP_SAPI === 'cli') {
			$tenant->close();
		}
	}
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
	session_start();
	$saldiMcpSessionId = session_id();
	$_SESSION['saldi_mcp_issuances'] ??= [];
	$saldiMcpTokenResponse = saldi_mcp_session_token($_SERVER, $saldiMcpSessionId, $_SESSION['saldi_mcp_issuances']);
	session_write_close();
	http_response_code($saldiMcpTokenResponse['status']);
	foreach ($saldiMcpTokenResponse['headers'] as $name => $value) {
		header($name . ': ' . $value);
	}
	echo json_encode($saldiMcpTokenResponse['body'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
