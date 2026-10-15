<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/mcp/SaldiMcpServer.php --- ai/mcp-assistant --- 2026.10.10
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
// Stateless JSON MCP transport, authorization, reserved-row budgets and audit.
//
// 20261001 CDX/LH Created.
// 20261002 CL/LH Draft receipts follow the returned item's status and say when it was deduplicated.
// 20261010 CDX/LH Return revoked mobile grant authentication errors over MCP.

declare(strict_types=1);

require_once __DIR__ . '/../tools/SaldiToolRegistry.php';
require_once __DIR__ . '/../assist/RecordAuth.php';

final class SaldiMcpServer
{
	public const PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26'];
	public const INSTRUCTIONS = 'Reads are live and permission-checked. Drafts become inbox items that a person approves in Saldi. Nothing is posted, paid, sent, filed, locked or deleted through this server.';
	private SaldiToolRegistry $registry;
	private Closure $authenticate;
	private array $allowedOrigins;
	private bool $ownsConnection;
	public function __construct(?SaldiToolRegistry $registry = null, ?callable $authenticate = null, ?array $allowedOrigins = null, private string $version = '')
	{
		$this->ownsConnection = $authenticate === null;
		$this->registry = $registry ?? SaldiToolRegistry::default();
		$this->authenticate = Closure::fromCallable($authenticate ?? 'saldi_runtime_context_from_token');
		$this->allowedOrigins = $allowedOrigins ?? array_values(array_filter(preg_split('/[\s,]+/', getenv('SALDI_MCP_ALLOWED_ORIGINS') ?: '')));
	}
	/** Stateless HTTP request/response contract. No PHP session or transport output.
	 * @param array{method:string,headers?:array,body:string,server?:array} $request
	 * @return array{status:int,headers:array,body:array|null}
	 */
	public function handle(array $request): array
	{
		$headers = [];
		foreach ($request['headers'] ?? [] as $name => $value) {
			$headers[strtolower($name)] = $value;
		}
		if (($request['method'] ?? '') !== 'POST') {
			return $this->rpcError(null, -32600, 'Method not allowed', 405, null, ['Allow' => 'POST']);
		}
		if (isset($headers['origin']) && !in_array($headers['origin'], $this->allowedOrigins, true)) {
			return $this->rpcError(null, -32600, 'Forbidden origin', 403);
		}
		if (strlen($request['body']) > 256 * 1024) {
			return $this->rpcError(null, -32600, 'Request too large', 413);
		}
		if (strtolower(trim(explode(';', $headers['content-type'] ?? '')[0])) !== 'application/json') {
			return $this->rpcError(null, -32600, 'Unsupported media type', 415);
		}
		$authorization = saldi_assist_authorization($request['server'] ?? [], $request['headers'] ?? []);
		try {
			if (!preg_match('/^Bearer (smcp1\.[A-Za-z0-9._-]+)$/D', $authorization, $m)) {
				throw new SaldiRuntimeError('UNAUTHENTICATED', 'Unauthorized');
			}
			$auth = ($this->authenticate)($m[1]);
			$ctx = $auth['ctx'];
			$db = $auth['db'];
		} catch (Throwable $e) {
			if ($e instanceof SaldiRuntimeError && $e->errorCode === 'DISABLED') {
				return $this->rpcError(null, -32000, 'Disabled', getenv('SALDI_MCP_DISABLED') === '1' ? 503 : 403, ['code' => 'DISABLED']);
			}
			if ($e instanceof SaldiRuntimeError && ($e->errorCode === 'GRANT_REVOKED'
				|| ($e->errorCode === 'UNAUTHENTICATED' && ($e->detail['reason'] ?? '') === 'GRANT_REVOKED'))) {
				return $this->rpcError(null, -32001, 'Unauthorized', 401, ['code' => 'GRANT_REVOKED'], ['WWW-Authenticate' => 'Bearer error="invalid_token"']);
			}
			if ($e instanceof SaldiRuntimeError && $e->errorCode === 'UNAUTHENTICATED') {
				return $this->rpcError(null, -32001, 'Unauthorized', 401, null, ['WWW-Authenticate' => 'Bearer error="invalid_token"']);
			}
			return $this->rpcError(null, -32000, 'Unavailable', 503, ['code' => 'UNAVAILABLE']);
		}
		try {
			try {
				$message = json_decode($request['body'], false, 64, JSON_THROW_ON_ERROR);
			} catch (JsonException $e) {
				return $this->rpcError(null, -32700, 'Parse error');
			}
			if (!$message instanceof stdClass || ($message->jsonrpc ?? null) !== '2.0' || !is_string($message->method ?? null) || (property_exists($message, 'id') && !is_int($message->id) && !is_string($message->id) && $message->id !== null)) {
				// Batches are deliberately rejected for both advertised versions.
				return $this->rpcError(null, -32600, 'Invalid Request');
			}
			$id = $message->id ?? null;
			$method = $message->method;
			if ($method !== 'initialize' && isset($headers['mcp-protocol-version']) && !in_array($headers['mcp-protocol-version'], self::PROTOCOL_VERSIONS, true)) {
				return $this->rpcError($id, -32600, 'Unsupported protocol version', 400);
			}
			if (property_exists($message, 'params') && !$message->params instanceof stdClass) {
				return $this->rpcError($id, -32602, 'Invalid params');
			}
			if (!property_exists($message, 'id')) {
				return $this->response(202, null);
			}
			$p = (array)($message->params ?? new stdClass());
			switch ($method) {
				case 'initialize':
					if (!is_string($p['protocolVersion'] ?? null)) {
						return $this->rpcError($id, -32602, 'Invalid params');
					}
					$result = ['protocolVersion' => in_array($p['protocolVersion'], self::PROTOCOL_VERSIONS, true) ? $p['protocolVersion'] : self::PROTOCOL_VERSIONS[0], 'capabilities' => ['tools' => ['listChanged' => false]], 'serverInfo' => ['name' => 'saldi', 'title' => 'Saldi', 'version' => $this->version ?: saldi_runtime_config()['version']], 'instructions' => self::INSTRUCTIONS];
					break;
				case 'ping':
					$result = new stdClass();
					break;
				case 'tools/list':
					$result = ['tools' => array_map(static fn(SaldiToolDefinition $tool): array => $tool->discovery(), $this->registry->visible($ctx, $db))];
					break;
				case 'tools/call':
					if (!is_string($p['name'] ?? null) || mb_strlen($p['name']) > 64 || (array_key_exists('arguments', $p) && !$p['arguments'] instanceof stdClass)) {
						return $this->rpcError($id, -32602, 'Invalid params');
					}
					$result = $this->call($ctx, $db, $p['name'], $p['arguments'] ?? new stdClass());
					break;
				default:
					return $this->rpcError($id, -32601, 'Method not found');
			}
			return $this->response(200, ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
		} catch (Throwable $e) {
			error_log('MCP request unavailable');
			return $this->rpcError($message->id ?? null, -32000, 'Unavailable', 503, ['code' => 'UNAVAILABLE']);
		} finally {
			if ($this->ownsConnection && PHP_SAPI === 'cli') {
				$db->close();
			}
		}
	}
	/** Reserved audit rows count toward both budgets, serialized per grant. @return array{0:int,1:bool} */
	private function reserve(SaldiTenantContext $ctx, SaldiDb $db, string $name, stdClass $args): array
	{
		return $db->transaction(static function () use ($ctx, $db, $name, $args): array {
			$db->lock('saldi_tool_rate:' . $ctx->grantId);
			$id = $db->insert('tool_calls', ['created_at' => gmdate('Y-m-d H:i:s'), 'grant_id' => $ctx->grantId, 'principal' => $ctx->principal, 'user_id' => $ctx->userId, 'tool' => $name, 'args_hash' => hash('sha256', SaldiToolRuntime::canonical($args)), 'status' => 'reserved']);
			$minute = (int)$db->value('SELECT COUNT(*) FROM tool_calls WHERE grant_id = ? AND created_at >= ?', [$ctx->grantId, gmdate('Y-m-d H:i:s', time() - 60)]);
			$day = (int)$db->value('SELECT COUNT(*) FROM tool_calls WHERE grant_id = ? AND created_at >= ?', [$ctx->grantId, gmdate('Y-m-d H:i:s', time() - 86400)]);
			$limited = $minute > 60 || $day > 1000;
			if ($limited) {
				$db->exec("UPDATE tool_calls SET status = 'rate_limited', error_code = 'RATE_LIMITED', duration_ms = 0, result_count = 0 WHERE id = ?", [$id]);
			}
			return [$id, $limited];
		});
	}
	/** Build one tool envelope, with a metadata-only audit record. @return array */
	private function call(SaldiTenantContext $ctx, SaldiDb $db, string $name, stdClass $arguments): array
	{
		$start = hrtime(true);
		$args = json_decode(json_encode($arguments, JSON_THROW_ON_ERROR), true, 64, JSON_THROW_ON_ERROR);
		[$auditId, $limited] = $this->reserve($ctx, $db, $name, $arguments);
		$status = 'ok';
		$code = null;
		$count = 0;
		try {
			if ($limited) {
				throw new SaldiRuntimeError('RATE_LIMITED', 'Tool call limit reached');
			}
			$tool = $this->registry->get($name);
			if ($tool === null || !in_array($tool, $this->registry->visible($ctx, $db), true)) {
				throw new SaldiRuntimeError('PERMISSION_DENIED', 'Permission denied');
			}
			SaldiSchemaValidator::validate($arguments, $tool->inputSchema);
			$r = ($tool->handler)($ctx, $db, $args);
			$receipt = $tool->effect === 'draft' ? self::draftReceipt($r['data']) : ['state' => 'done', 'label_da' => 'Data hentet fra Saldi.', 'label_en' => 'Live data read from Saldi.'];
			$envelope = ['ok' => true, 'committed' => false, 'data' => $r['data'], 'receipt' => $receipt, 'citations' => $r['citations'], 'possibly_incomplete' => $r['possibly_incomplete']];
			$envelope = self::bounded($envelope, true);
			try {
				SaldiSchemaValidator::validate($envelope, $tool->outputSchema());
			} catch (SaldiRuntimeError $e) {
				error_log('MCP tool output schema mismatch: ' . $name);
				throw new SaldiRuntimeError('UNAVAILABLE', 'Tool unavailable');
			}
			$count = isset($envelope['data']['rows']) ? count($envelope['data']['rows']) : (isset($envelope['data']['lines']) ? count($envelope['data']['lines']) : $r['result_count']);
		} catch (Throwable $e) {
			if (!$e instanceof SaldiRuntimeError) {
				error_log('MCP tool unavailable: ' . $name);
				$e = new SaldiRuntimeError('UNAVAILABLE', 'Tool unavailable');
			}
			$code = $e->errorCode;
			$status = $code === 'RATE_LIMITED' ? 'rate_limited' : ($code === 'PERMISSION_DENIED' ? 'denied' : 'error');
			$envelope = ['ok' => false, 'committed' => false, 'error' => ['code' => $code, 'class' => $e->errorClass(), 'message_en' => $e->getMessage(), 'message_da' => $e->messageDa($db, $ctx->languageId), 'detail' => $e->detail ?: new stdClass()]];
		}
		$db->exec('UPDATE tool_calls SET status = ?, error_code = ?, duration_ms = ?, result_count = ? WHERE id = ?', [$status, $code, (int)round((hrtime(true) - $start) / 1000000), $count, $auditId]);
		return self::toolResult($envelope);
	}
	/**
	 * Receipt for a draft tool, from the returned item's real status: a deduplicated item may already be
	 * decided, so only an open item is 'awaiting_approval'.
	 *
	 * @return array{state:string,label_da:string,label_en:string,item_id:int,status:string,deduplicated:bool}
	 */
	public static function draftReceipt(array $item): array
	{
		$status = (string)($item['status'] ?? 'open');
		$deduplicated = (bool)($item['deduplicated'] ?? false);
		if ($status === 'open') {
			$receipt = ['state' => 'awaiting_approval', 'label_da' => $deduplicated ? 'Forslaget ligger allerede i Opgaver til godkendelse. Intet er bogført.' : 'Forslag lagt i Opgaver til godkendelse. Intet er bogført.', 'label_en' => $deduplicated ? 'This proposal already awaits human approval in Saldi. Nothing is posted.' : 'Proposal awaits human approval in Saldi. Nothing is posted.'];
		} elseif ($status === 'applied') {
			$receipt = ['state' => 'applied', 'label_da' => 'Forslaget er allerede godkendt.', 'label_en' => 'This proposal was already approved.'];
		} else {
			$da = ['rejected' => 'afvist', 'cancelled' => 'trukket tilbage', 'expired' => 'udløbet', 'undone' => 'fortrudt'][$status] ?? $status;
			$receipt = ['state' => 'closed', 'label_da' => 'Forslaget er allerede lukket (' . $da . '). Intet er ændret.', 'label_en' => 'This proposal is already closed (' . $status . '). Nothing was changed.'];
		}
		return $receipt + ['item_id' => (int)$item['id'], 'status' => $status, 'deduplicated' => $deduplicated];
	}
	private static function toolResult(array $envelope): array
	{
		return ['content' => [['type' => 'text', 'text' => json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]], 'structuredContent' => $envelope, 'isError' => !$envelope['ok']];
	}
	/** Bound text and trim result rows together with their citations. @return array */
	public static function bounded(array $envelope, bool $transport = false): array
	{
		$trimmed = false;
		$trim = static function (mixed $v) use (&$trim, &$trimmed): mixed {
			if (is_string($v) && mb_strlen($v) > 500) {
				$trimmed = true;
				return mb_substr($v, 0, 500);
			}
			if (is_array($v)) {
				foreach ($v as $k => $item) {
					$v[$k] = $trim($item);
				}
			}
			return $v;
		};
		$envelope = $trim($envelope);
		$envelope['possibly_incomplete'] = $envelope['possibly_incomplete'] || $trimmed;
		while (strlen(json_encode($transport ? self::toolResult($envelope) : $envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) > 200 * 1024) {
			$key = isset($envelope['data']['rows']) ? 'rows' : (isset($envelope['data']['lines']) ? 'lines' : null);
			if ($key === null || !$envelope['data'][$key]) {
				throw new SaldiRuntimeError('UNAVAILABLE', 'Result exceeds the response limit');
			}
			array_pop($envelope['data'][$key]);
			// Never cite a record removed from the response due to the size cap.
			$envelope['citations'] = [];
			$envelope['possibly_incomplete'] = true;
		}
		return $envelope;
	}
	private function response(int $status, ?array $body, array $headers = []): array
	{
		return ['status' => $status, 'headers' => ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store'] + $headers, 'body' => $body];
	}
	private function rpcError(mixed $id, int $code, string $message, int $status = 200, ?array $data = null, array $headers = []): array
	{
		return $this->response($status, ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message] + ($data === null ? [] : ['data' => $data])], $headers);
	}
}
