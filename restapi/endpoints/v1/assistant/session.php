<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/assistant/session.php --- ver 5.0.0 --- 2026.10.08
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
// 20261008 CL/LH Created for the mobile Assist contract.

declare(strict_types=1);

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../../includes/runtime/bootstrap.php';
require_once __DIR__ . '/../../../../includes/saldi_assist_context_token.php';

/** Shared Assist envelope and live mobile identity for the sequenced server slices. */
abstract class SaldiAssistantEndpoint extends BaseEndpoint
{
	/** @return array{ctx:SaldiTenantContext,db:SaldiDb} */
	protected function assistantContext(): array
	{
		return saldi_runtime_context_for_mobile(JWTAuth::validateToken());
	}
	protected function decodeJsonBody($rawInput)
	{
		try {
			return trim((string)$rawInput) === '' ? null : json_decode($rawInput, false, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid JSON body');
		}
	}
	protected function checkAuthorization()
	{
		if (!JWTAuth::validateToken()) {
			$this->sendError('UNAUTHENTICATED', 'Valid access token required', 401);
		}
		return parent::checkAuthorization();
	}
	protected function enforceDeclaredRight($payload): bool
	{
		if (!parent::enforceDeclaredRight($payload)) {
			$this->sendError('FORBIDDEN', 'Permission denied', 403);
		}
		return true;
	}
	protected function handleError($exception = null)
	{
		if ($exception instanceof SaldiRuntimeError) {
			$code = $exception->errorCode;
			if ($code === 'DISABLED') {
				$code = 'ASSIST_DISABLED';
			} elseif (($exception->detail['reason'] ?? '') === 'GRANT_REVOKED') {
				$code = 'GRANT_REVOKED';
			}
			$this->sendError($code, $exception->getMessage(), $code === 'VALIDATION_ERROR' ? 422 : $exception->httpStatus(), ['details' => $exception->detail]);
		}
		if ($exception instanceof MobileAuthError) {
			$code = $exception->status === 429 ? 'RATE_LIMITED' : 'UNAUTHENTICATED';
			$this->sendError($code, 'Session unavailable', $exception->status);
		}
		if ($exception instanceof BadMethodCallException) {
			$this->sendError('VALIDATION_ERROR', 'Method not allowed', 405);
		}
		parent::handleError($exception);
	}
	protected function sendResponse($success, $data = null, $message = '', $httpCode = 200)
	{
		header('Cache-Control: no-store');
		if (is_array($data) && isset($data['error'])) {
			$data['error'] = match ($data['error']) {
				'session_revoked' => 'GRANT_REVOKED',
				'invalid_token' => 'UNAUTHENTICATED',
				'permission_denied' => 'FORBIDDEN',
				default => $data['error'],
			};
		}
		parent::sendResponse($success, $data, $message, $httpCode);
	}
}

class AssistantSessionEndpoint extends SaldiAssistantEndpoint
{
	/** @return array{module:string,action:string} */
	protected function requiredRight(): ?array
	{
		return ['module' => 'assistant', 'action' => 'read'];
	}
	/** Restapi configuration follows the existing environment-based deployment settings. */
	public static function chatBaseUrl(): string
	{
		return rtrim(trim((string)getenv('SALDI_ASSIST_CHAT_BASE_URL')), '/');
	}
	protected function handlePost($data)
	{
		$session = ifset($data, 'embed_session');
		$mcp = ifset($data, 'mcp');
		if (!is_string($session) || !preg_match('/^[a-f0-9]{32}$/D', $session) || !is_bool($mcp)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid Assist session');
		}
		$url = self::chatBaseUrl();
		$secret = (string)getenv('SALDI_ASSIST_CONTEXT_SECRET');
		$kid = getenv('SALDI_ASSIST_KID') ?: 'k2026a';
		if ($url === '' || saldi_runtime_origin($url) !== $url || $secret === '') {
			$this->sendError('ASSIST_DISABLED', 'Assist is unavailable', 403);
		}
		$payload = JWTAuth::validateToken();
		$family = (string)ifset($payload, 'sid', '');
		(new MobileAuth())->assistRateLimit($family);
		$open = $this->assistantContext();
		$db = $open['db'];
		$ctx = $open['ctx'];
		try {
			saldi_runtime_mobile_family(saldi_runtime_master(), $family, $ctx->regnskabId, $ctx->userId);
			$licences = [];
			foreach (SALDI_ASSIST_DEFAULT_FEATURES as $feature) {
				$licences[$feature] = ['feature_key' => $feature, 'enabled' => true, 'expires_at' => null];
			}
			$master = saldi_runtime_master();
			if ($master->hasTable('license_features')) {
				foreach ($master->all('SELECT feature_key, enabled, expires_at FROM license_features WHERE regnskab_id = ?', [$ctx->regnskabId]) as $row) {
					$licences[$row['feature_key']] = $row;
				}
			}
			$claims = saldi_assist_context_claims(['db' => $ctx->db, 'brugernavn' => $ctx->username, 'rettigheder' => $ctx->rights, 'language_id' => $ctx->languageId], array_values($licences), $session, null, saldi_runtime_config()['version']);
			$grant = null;
			if ($mcp && SaldiSettings::get($db, 'assistant', 'tools_enabled') === 'on' && SaldiPermissionService::allows($ctx, $db, 'inbox.read')) {
				$grant = (new SaldiGrantService($master, $db, $ctx))->issueMobile($family, $session);
			}
			$consent = SaldiSettings::getUser($db, $ctx, 'assistant', 'improvement_consent', '');
			$result = ['chat_base_url' => $url, 'context_token' => saldi_assist_sign_claims($claims, $kid, $secret), 'context_expires_at' => $claims['exp'], 'mcp_token' => $grant['token'] ?? null, 'mcp_expires_at' => $grant === null ? null : strtotime($grant['grant']['expires_at'] . ' UTC'), 'tenant_hash' => $claims['tenant_hash'], 'mode' => $grant === null ? 'help' : 'assist', 'consent' => in_array($consent, ['yes', 'no'], true) ? $consent : null];
		} finally {
			if (PHP_SAPI === 'cli') {
				$db->close();
			}
		}
		$this->sendResponse(true, $result);
	}
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
	(new AssistantSessionEndpoint())->handleRequestMethod();
}
