<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/runtime/bootstrap.php --- ai/mcp-assistant --- 2026.10.10
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
// Data-only bootstrap and trusted schema entrypoints; token authentication never migrates.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Canonicalize nested JSON objects while preserving empty objects.
// 20261002 CL/LH Shared request-origin helper (X-Forwarded-Proto aware) for every same-origin check.
// 20261008 CL/LH Add the mobile Assist identity and delegation contract.
// 20261010 CDX/LH Report revoked mobile grants with their public error code.

declare(strict_types=1);

require_once __DIR__ . '/../../restapi/core/Rights.php';
require_once __DIR__ . '/../../restapi/core/Subject.php';
require_once __DIR__ . '/SaldiDb.php';
require_once __DIR__ . '/SaldiRuntimeError.php';
require_once __DIR__ . '/SaldiTenantContext.php';
require_once __DIR__ . '/SaldiText.php';
require_once __DIR__ . '/SaldiScreens.php';
require_once __DIR__ . '/../planUpdatesRunner.php';
require_once __DIR__ . '/../planUpdatesSteps.php';
require_once __DIR__ . '/../automation/SaldiSettings.php';
require_once __DIR__ . '/../automation/SaldiPermissionService.php';
require_once __DIR__ . '/../automation/SaldiDecisionLog.php';
require_once __DIR__ . '/../automation/SaldiGrantService.php';

/** Canonical JSON for digests, revisions and audit hashes. */
function saldi_runtime_json(mixed $value): string
{
	$sort = static function (mixed $v) use (&$sort): mixed {
		$object = $v instanceof stdClass;
		if ($object || is_array($v)) {
			$a = (array)$v;
			if ($object || !array_is_list($a)) {
				ksort($a);
			}
			foreach ($a as $key => $item) {
				$a[$key] = $sort($item);
			}
			return $object ? (object)$a : $a;
		}
		return $v;
	};
	return json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
/** Origin (scheme://host[:port]) of a URL without path, query or user info; default ports dropped. */
function saldi_runtime_origin(string $url): ?string
{
	$p = parse_url($url);
	if (!$p || !in_array($p['scheme'] ?? '', ['http', 'https'], true) || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
		return null;
	}
	$port = $p['port'] ?? ($p['scheme'] === 'https' ? 443 : 80);
	return strtolower($p['scheme'] . '://' . $p['host']) . ($port === ($p['scheme'] === 'https' ? 443 : 80) ? '' : ':' . $port);
}
/**
 * This request's own origin, for same-origin checks and for URLs shown to the user. One rule for every
 * endpoint: HTTPS when the server says so or when a TLS-terminating proxy sends X-Forwarded-Proto: https.
 * Trusting the header is safe here because it only ever compares against the browser's Origin, which a
 * cross-site page cannot set, and it can only upgrade the scheme.
 */
function saldi_runtime_request_origin(array $server): ?string
{
	$https = !empty($server['HTTPS']) && strtolower((string)$server['HTTPS']) !== 'off';
	$forwarded = strtolower(trim(explode(',', (string)($server['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
	return saldi_runtime_origin(($https || $forwarded === 'https' ? 'https' : 'http') . '://' . (string)($server['HTTP_HOST'] ?? ''));
}
/** Parse literal assignments only; never execute a legacy connection file.
 * @return array{db_type:string,host:string,port:int,user:string,password:string,master_db:string}
 */
function saldi_runtime_parse_config(string $text): array
{
	preg_match_all('/\$(db_type|sqhost|squser|sqpass|sqdb)\s*=\s*(["\'])(.*?)\2\s*;/s', $text, $matches, PREG_SET_ORDER);
	$values = [];
	foreach ($matches as $m) {
		$values[$m[1]] ??= $m[3];
	}
	foreach (['db_type', 'sqhost', 'squser', 'sqpass', 'sqdb'] as $key) {
		if (!array_key_exists($key, $values)) {
			throw new SaldiRuntimeError('UNAVAILABLE', 'Runtime database configuration unavailable');
		}
	}
	$host = $values['sqhost'];
	$port = in_array($values['db_type'], ['mysql', 'mysqli'], true) ? 3306 : 5432;
	if (preg_match('/^(.+):([0-9]+)$/D', $host, $m)) {
		$host = $m[1];
		$port = (int)$m[2];
	}
	return ['db_type' => $values['db_type'], 'host' => $host, 'port' => $port, 'user' => $values['squser'], 'password' => $values['sqpass'], 'master_db' => $values['sqdb']];
}
/** @return array{db_type:string,host:string,port:int,user:string,password:string,master_db:string,install_id:string,version:string} */
function saldi_runtime_config(): array
{
	if (isset($GLOBALS['saldi_runtime_cache']['config'])) {
		return $GLOBALS['saldi_runtime_cache']['config'];
	}
	$env = [];
	foreach (['db_type' => 'TYPE', 'host' => 'HOST', 'user' => 'USER', 'password' => 'PASSWORD', 'master_db' => 'MASTER'] as $k => $suffix) {
		$env[$k] = getenv('SALDI_RUNTIME_DB_' . $suffix);
	}
	if (!in_array(false, $env, true)) {
		$cfg = $env;
		$cfg['port'] = (int)(getenv('SALDI_RUNTIME_DB_PORT') ?: (in_array($cfg['db_type'], ['mysql', 'mysqli'], true) ? 3306 : 5432));
	} else {
		$file = __DIR__ . '/../connect.php';
		if (!is_readable($file)) {
			throw new SaldiRuntimeError('UNAVAILABLE', 'Runtime database configuration unavailable');
		}
		$cfg = saldi_runtime_parse_config(file_get_contents($file));
	}
	$cfg['db_type'] = match ($cfg['db_type']) {
		'postgres', 'postgresql', 'pgsql' => 'postgresql', 'mysql', 'mysqli' => 'mysql', default => throw new SaldiRuntimeError('UNAVAILABLE', 'Unsupported database type')
	};
	$cfg['install_id'] = saldi_runtime_install_id();
	preg_match('/\$version\s*=\s*(["\'])(.*?)\1\s*;/', file_get_contents(__DIR__ . '/../version.php'), $m);
	$cfg['version'] = $m[2] ?? '';
	return $GLOBALS['saldi_runtime_cache']['config'] = $cfg;
}
/** Install identity is also used by the legacy signed context-token endpoint. */
function saldi_runtime_install_id(): string
{
	$id = getenv('SALDI_INSTALL_ID');
	$id = $id === false ? 'local' : $id;
	if (!preg_match('/^[a-z0-9_-]{1,32}$/D', $id)) {
		throw new SaldiRuntimeError('UNAVAILABLE', 'Invalid runtime install identity');
	}
	return $id;
}
/** Clear request caches and release owned connections (tests). */
function saldi_runtime_reset(): void
{
	foreach ($GLOBALS['saldi_runtime_cache']['tenants'] ?? [] as $entry) {
		$entry['db']->close();
	}
	if (isset($GLOBALS['saldi_runtime_cache']['master'])) {
		$GLOBALS['saldi_runtime_cache']['master']->close();
	}
	$GLOBALS['saldi_runtime_cache'] = [];
}
/** Cached independent master connection; does not touch the legacy connection. */
function saldi_runtime_master(): SaldiDb
{
	$cfg = saldi_runtime_config();
	try {
		return $GLOBALS['saldi_runtime_cache']['master'] ??= SaldiDb::connect($cfg, $cfg['master_db']);
	} catch (PDOException $e) {
		error_log('Runtime master connection failed');
		throw new SaldiRuntimeError('UNAVAILABLE', 'Database unavailable', [], $e);
	}
}
/** @return array{db:SaldiDb,regnskab:array} */
function saldi_runtime_tenant(int $regnskabId): array
{
	$r = saldi_runtime_master()->one('SELECT id, regnskab, db, lukket, lukkes FROM regnskab WHERE id = ?', [$regnskabId]);
	$today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
	if (!$r || $regnskabId <= 1 || $r['lukket'] === 'on' || (!empty($r['lukkes']) && $r['lukkes'] <= $today)) {
		throw new SaldiRuntimeError('UNAUTHENTICATED', 'Unauthorized');
	}
	if (PHP_SAPI !== 'cli' && isset($GLOBALS['saldi_runtime_cache']['tenants'][$regnskabId])) {
		return ['db' => $GLOBALS['saldi_runtime_cache']['tenants'][$regnskabId]['db'], 'regnskab' => $r];
	}
	try {
		$entry = ['db' => SaldiDb::connect(saldi_runtime_config(), $r['db']), 'regnskab' => $r];
	} catch (Throwable $e) {
		throw new SaldiRuntimeError('UNAVAILABLE', 'Database unavailable', [], $e);
	}
	if (PHP_SAPI !== 'cli') {
		$GLOBALS['saldi_runtime_cache']['tenants'][$regnskabId] = $entry;
	}
	return $entry;
}
/** @return array{db:SaldiDb,regnskab:array} */
function saldi_runtime_tenant_by_db(string $db): array
{
	$id = saldi_runtime_master()->value('SELECT id FROM regnskab WHERE db = ?', [$db]);
	return saldi_runtime_tenant((int)$id);
}
/** Trusted entrypoints only. MCP and worker code must never call this. */
function saldi_runtime_ensure_schema(SaldiDb $tenant): void
{
	$raw = saldi_plan_update_steps();
	$names = array_keys($raw);
	$sql = "SELECT var_value FROM settings WHERE var_name = 'plan_updates_last_step' AND var_grp = 'system' ORDER BY id DESC LIMIT 1";
	try {
		if ($tenant->value($sql) === end($names)) {
			return;
		}
		$tenant->transaction(static function () use ($tenant, $raw): void {
			$tenant->lock('saldi_plan_updates', 10);
			$exec = static fn(string $sql) => $tenant->exec($sql);
			$select = static fn(string $sql) => $tenant->one($sql);
			$steps = [];
			foreach ($raw as $name => $step) {
				$steps[$name] = static fn(string $type) => $step($type, $exec, $select);
			}
			$result = plan_updates_run($steps, $tenant->dbType(), $select, $exec);
			if ($result['failed'] !== null) {
				throw new RuntimeException('Schema step failed: ' . $result['failed']);
			}
		});
	} catch (Throwable $e) {
		error_log('Runtime schema unavailable: ' . $e->getMessage());
		throw new SaldiRuntimeError('UNAVAILABLE', 'Runtime schema unavailable', [], $e);
	}
}
/** Reload the user and active fiscal year, never trust grant snapshots. */
function saldi_runtime_context_for_user(SaldiDb $tenant, array $regnskab, int $userId, string $principal, ?int $grantId = null, array $scopes = []): SaldiTenantContext
{
	if ($userId <= 0 || ($principal !== 'user:' . $userId && !preg_match('/^agent:(saldi-assist|external:[1-9][0-9]*)$/D', $principal))) {
		throw new SaldiRuntimeError('PERMISSION_DENIED', 'User login required');
	}
	$user = $tenant->one('SELECT * FROM brugere WHERE id = ?', [$userId]);
	if (!$user) {
		throw new SaldiRuntimeError('PERMISSION_DENIED', 'User login required');
	}
	$year = $tenant->value("SELECT kodenr FROM grupper WHERE art = 'RA' AND box5 = 'on' AND COALESCE(box10, '') <> 'on' AND kodenr = ?", [(int)$user['regnskabsaar']]);
	if ($year === null) {
		$year = $tenant->value("SELECT kodenr FROM grupper WHERE art = 'RA' AND box5 = 'on' AND COALESCE(box10, '') <> 'on' ORDER BY box2 DESC, box1 DESC, id DESC LIMIT 1");
	}
	$lang = (int)($user['language_id'] ?? 1);
	$cfg = saldi_runtime_config();
	$currency = $tenant->value("SELECT var_value FROM settings WHERE var_name = 'baseCurrency' AND COALESCE(user_id, 0) = 0 AND COALESCE(pos_id, 0) = 0 ORDER BY id DESC LIMIT 1") ?: 'DKK';
	return new SaldiTenantContext($cfg['install_id'], (int)$regnskab['id'], $tenant->name(), $tenant->dbType(), $userId, $user['brugernavn'], (string)$user['rettigheder'], (int)$year, in_array($lang, [1, 2, 3], true) ? $lang : 1, $principal, $grantId, $scopes, $currency);
}
/** Reload an enabled registry user and enforce the same membership rules as mobile auth. */
function saldi_runtime_context_for_revisor(int $registryId, string $tenantDb, string $principal = '', ?int $grantId = null, array $scopes = [], ?int $tenantId = null): SaldiTenantContext
{
	if ($registryId <= 0 || ($principal !== '' && $principal !== 'revisor:' . $registryId && $principal !== 'agent:saldi-assist')) {
		throw new SaldiRuntimeError('PERMISSION_DENIED', 'Revisor login required');
	}
	$user = saldi_runtime_master()->one('SELECT * FROM brugere WHERE id = ?', [$registryId]);
	if (!$user || (isset($user['status']) && !in_array($user['status'], [true, 1, '1', 't', 'true', 'on'], true))) {
		throw new SaldiRuntimeError('PERMISSION_DENIED', 'Revisor login required');
	}
	$entry = $tenantId === null ? saldi_runtime_tenant_by_db($tenantDb) : saldi_runtime_tenant($tenantId);
	$db = $entry['db'];
	try {
		if ($db->name() !== $tenantDb) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Tenant access denied');
		}
		$rights = Rights::registry((string)$user['rettigheder']);
		if (!$rights['admin'] && !in_array((int)$entry['regnskab']['id'], $rights['tenant_ids'], true)) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Tenant access denied');
		}
		$year = $db->value("SELECT kodenr FROM grupper WHERE art = 'RA' AND box5 = 'on' AND COALESCE(box10, '') <> 'on' ORDER BY box2 DESC, box1 DESC, id DESC LIMIT 1");
		$currency = $db->value("SELECT var_value FROM settings WHERE var_name = 'baseCurrency' AND COALESCE(user_id, 0) = 0 AND COALESCE(pos_id, 0) = 0 ORDER BY id DESC LIMIT 1") ?: 'DKK';
		$lang = (int)($user['language_id'] ?? 1);
		return new SaldiTenantContext(saldi_runtime_install_id(), (int)$entry['regnskab']['id'], $tenantDb, $db->dbType(), -$registryId, 'revisor:' . $registryId, str_repeat('1', SaldiRightsTable::LENGTH), (int)$year, in_array($lang, [1, 2, 3], true) ? $lang : 1, $principal ?: 'revisor:' . $registryId, $grantId, $scopes, $currency);
	} finally {
		if (PHP_SAPI === 'cli') {
			$db->close();
		}
	}
}
/** Resolve a JWT tenant identity after BaseEndpoint's session check. @return array{ctx:SaldiTenantContext,db:SaldiDb} */
function saldi_runtime_context_for_mobile(array $payload): array
{
	$entry = saldi_runtime_tenant((int)($payload['tenant_id'] ?? 0));
	try {
		$id = (int)($payload['user_id'] ?? 0);
		$ctx = ($payload['principal'] ?? '') === 'registry'
			? saldi_runtime_context_for_revisor($id, $entry['db']->name(), '', null, [], (int)$entry['regnskab']['id'])
			: saldi_runtime_context_for_user($entry['db'], $entry['regnskab'], $id, 'user:' . $id);
		return ['ctx' => $ctx, 'db' => $entry['db']];
	} catch (Throwable $e) {
		$entry['db']->close();
		throw $e;
	}
}
/** Verify the newest scoped rotation and represented actor after resolving the live tenant.
 * @return array{family_id:string,subject:string,principal:string,tenant_id:int|string,expires_at:string,revoked_at:string|null,unexpired:bool|int|string}
 */
function saldi_runtime_mobile_family(SaldiDb $master, string $familyId, int $tenantId, int $userId): array
{
	$row = $master->one('SELECT s.*, s.expires_at > CURRENT_TIMESTAMP AS unexpired FROM refresh_sessions s WHERE s.family_id = ? AND s.tenant_id = ? ORDER BY s.id DESC LIMIT 1', [$familyId, $tenantId]);
	$identity = null;
	try {
		$identity = $row ? Subject::parse($row['subject']) : null;
	} catch (InvalidArgumentException $e) {
		$identity = null;
	}
	$principal = $userId < 0 ? 'registry' : 'account';
	if (!$row || $row['revoked_at'] !== null || !in_array($row['unexpired'], [true, 1, '1', 't', 'true', 'on'], true)
		|| !$identity || $identity['principal'] !== $principal || $identity['user_id'] !== abs($userId) || $row['principal'] !== $principal) {
		throw new SaldiRuntimeError('GRANT_REVOKED', 'Mobile grant revoked', ['reason' => 'GRANT_REVOKED']);
	}
	return $row;
}
/** For pages that have already authenticated through online.php.
 * @return array{ctx:SaldiTenantContext,db:SaldiDb}
 */
function saldi_runtime_context_for_session(): array
{
	$id = (int)($GLOBALS['bruger_id'] ?? 0);
	if ($id <= 0) {
		throw new SaldiRuntimeError('PERMISSION_DENIED', 'User login required');
	}
	$entry = saldi_runtime_tenant((int)($GLOBALS['db_id'] ?? 0));
	$ctx = saldi_runtime_context_for_user($entry['db'], $entry['regnskab'], $id, 'user:' . $id);
	$lang = (int)($GLOBALS['sprog_id'] ?? $ctx->languageId);
	$ctx = new SaldiTenantContext($ctx->installId, $ctx->regnskabId, $ctx->db, $ctx->dbType, $ctx->userId, $ctx->username, $ctx->rights, $ctx->fiscalYear, in_array($lang, [1, 2, 3], true) ? $lang : 1, $ctx->principal, null, [], $ctx->baseCurrency);
	return ['ctx' => $ctx, 'db' => $entry['db']];
}
/** Authenticate opaque grants without migrations or writes before verification.
 * @return array{ctx:SaldiTenantContext,db:SaldiDb,grant:array}
 */
function saldi_runtime_context_from_token(string $token): array
{
	$parts = SaldiGrantService::parse($token);
	$deny = static function (string $reason): never {
		error_log('MCP authentication: ' . $reason);
		throw new SaldiRuntimeError('UNAUTHENTICATED', 'Unauthorized');
	};
	if (!$parts || $parts['install'] !== saldi_runtime_config()['install_id']) {
		$deny('invalid token or install');
	}
	if (getenv('SALDI_MCP_DISABLED') === '1') {
		throw new SaldiRuntimeError('DISABLED', 'MCP disabled');
	}
	$entry = saldi_runtime_tenant($parts['regnskab_id']);
	$db = $entry['db'];
	try {
		if (!$db->hasTable('agent_grants')) {
			$deny('grants schema absent');
		}
		$grant = $db->one('SELECT * FROM agent_grants WHERE id = ?', [$parts['grant_id']]);
		$now = gmdate('Y-m-d H:i:s');
		if (!$grant || !hash_equals($grant['token_hash'], hash('sha256', $parts['secret']))) {
			$deny('invalid grant');
		}
		if ($grant['revoked_at'] !== null || $grant['expires_at'] <= $now) {
			if ($grant['kind'] === 'mobile') {
				throw new SaldiRuntimeError('GRANT_REVOKED', 'Mobile grant revoked', ['reason' => 'GRANT_REVOKED']);
			}
			$deny('invalid grant');
		}
		$revisor = $grant['kind'] === 'mobile' && (int)$grant['user_id'] < 0;
		$user = $revisor ? ['brugernavn' => 'revisor:' . (-(int)$grant['user_id'])] : $db->one('SELECT brugernavn FROM brugere WHERE id = ?', [(int)$grant['user_id']]);
		if (!$user || !hash_equals($grant['user_hash'], substr(hash('sha256', 'saldi-user:' . $db->name() . ':' . $user['brugernavn']), 0, 32))) {
			$deny('user changed');
		}
		if ($grant['kind'] === 'session') {
			$revisorType = $db->isPostgres() ? 'TEXT' : 'CHAR';
			$online = saldi_runtime_master()->one("SELECT brugernavn FROM online WHERE md5(session_id) = ? AND db = ? AND COALESCE(CAST(revisor AS $revisorType), '') NOT IN ('1','t','true','on') ORDER BY logtime DESC LIMIT 1", [$grant['session_ref'], $db->name()]);
			if (!$online || $online['brugernavn'] !== $user['brugernavn']) {
				$deny('session ended');
			}
		} elseif ($grant['kind'] === 'mobile') {
			saldi_runtime_mobile_family(saldi_runtime_master(), (string)($grant['family_id'] ?? ''), (int)$entry['regnskab']['id'], (int)$grant['user_id']);
		} elseif ($grant['kind'] !== 'personal') {
			$deny('unknown grant kind');
		}
		if (SaldiSettings::get($db, 'assistant', 'tools_enabled') !== 'on' || ($grant['kind'] === 'personal' && SaldiSettings::get($db, 'assistant', 'external_agents') !== 'on')) {
			throw new SaldiRuntimeError('DISABLED', 'Tools disabled');
		}
		$ctx = $revisor
			? saldi_runtime_context_for_revisor(-(int)$grant['user_id'], $db->name(), $grant['principal'], (int)$grant['id'], explode(' ', $grant['scopes']), (int)$entry['regnskab']['id'])
			: saldi_runtime_context_for_user($db, $entry['regnskab'], (int)$grant['user_id'], $grant['principal'], (int)$grant['id'], explode(' ', $grant['scopes']));
		if ($db->exec('UPDATE agent_grants SET use_count = use_count + 1, last_used_at = ? WHERE id = ? AND revoked_at IS NULL AND expires_at > ? AND (max_uses IS NULL OR use_count < max_uses)', [$now, $grant['id'], $now]) !== 1) {
			$deny('grant exhausted');
		}
		$grant['use_count'] = (int)$grant['use_count'] + 1;
		$grant['last_used_at'] = $now;
		unset($grant['token_hash']);
		return ['ctx' => $ctx, 'db' => $db, 'grant' => $grant];
	} catch (Throwable $e) {
		$db->close();
		if ($e instanceof SaldiRuntimeError) {
			throw $e;
		}
		throw new SaldiRuntimeError('UNAVAILABLE', 'Database unavailable', [], $e);
	}
}
