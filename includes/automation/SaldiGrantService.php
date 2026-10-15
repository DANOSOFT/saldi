<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiGrantService.php --- ai/mcp-assistant --- 2026.10.08
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
// Opaque, bounded delegation with live session verification in bootstrap.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Attach a stable reason to personal-grant conflicts.
// 20261008 CL/LH Add the mobile Assist identity and delegation contract.

declare(strict_types=1);

final class SaldiGrantService
{
	public function __construct(private SaldiDb $master, private SaldiDb $tenant, private SaldiTenantContext $issuer)
	{
	}
	private function human(): void
	{
		if (!$this->issuer->isHuman()) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Human issuer required');
		}
	}
	/** @return array{token:string,grant:array} */
	public function issueSession(string $sessionRef): array
	{
		$this->human();
		if (!preg_match('/^[a-f0-9]{32}$/D', $sessionRef)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid session reference');
		}
		if (SaldiSettings::get($this->tenant, 'assistant', 'tools_enabled') !== 'on') {
			throw new SaldiRuntimeError('DISABLED', 'Tools disabled');
		}
		return $this->tenant->transaction(function () use ($sessionRef): array {
			$this->tenant->lock('saldi_grants:' . $this->issuer->userId);
			$result = $this->issue('session', 'Saldi Assist', ['read', 'draft'], 8 * 3600, $sessionRef, 2000);
			$active = $this->tenant->all("SELECT id FROM agent_grants WHERE user_id = ? AND session_ref = ? AND kind = 'session' AND revoked_at IS NULL AND expires_at > ? ORDER BY created_at DESC, id DESC", [$this->issuer->userId, $sessionRef, gmdate('Y-m-d H:i:s')]);
			foreach (array_slice($active, 5) as $row) {
				$this->revoke((int)$row['id']);
			}
			return $result;
		});
	}
	/** Mobile delegation bound to a live refresh family. @return array{token:string,grant:array} */
	public function issueMobile(string $familyId, string $sessionRef): array
	{
		$this->human();
		if (!preg_match('/^[a-f0-9]{32}$/D', $sessionRef) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $familyId)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid mobile session');
		}
		saldi_runtime_mobile_family($this->master, $familyId, $this->issuer->regnskabId, $this->issuer->userId);
		if (SaldiSettings::get($this->tenant, 'assistant', 'tools_enabled') !== 'on') {
			throw new SaldiRuntimeError('DISABLED', 'Tools disabled');
		}
		return $this->tenant->transaction(function () use ($familyId, $sessionRef): array {
			$this->tenant->lock('saldi_mobile_grants:' . $familyId);
			$result = $this->issue('mobile', 'Saldi Assist (app)', ['read', 'draft'], 8 * 3600, $sessionRef, 2000, $familyId);
			$active = $this->tenant->all("SELECT id FROM agent_grants WHERE family_id = ? AND kind = 'mobile' AND revoked_at IS NULL AND expires_at > ? ORDER BY created_at DESC, id DESC", [$familyId, gmdate('Y-m-d H:i:s')]);
			foreach (array_slice($active, 5) as $row) {
				$this->revoke((int)$row['id']);
			}
			return $result;
		});
	}
	/** Self-only personal delegation. @return array{token:string,grant:array} */
	public function issuePersonal(string $label, array $scopes, int $ttlDays): array
	{
		$this->human();
		if (SaldiSettings::get($this->tenant, 'assistant', 'tools_enabled') !== 'on' || SaldiSettings::get($this->tenant, 'assistant', 'external_agents') !== 'on') {
			throw new SaldiRuntimeError('DISABLED', 'External agents disabled');
		}
		if (!in_array($scopes, [['read'], ['read', 'draft']], true) || !in_array($ttlDays, [30, 90, 365], true) || ($ttlDays === 365 && !SaldiPermissionService::allows($this->issuer, $this->tenant, 'settings.admin')) || mb_strlen(trim($label)) < 1 || mb_strlen($label) > 100) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid personal grant');
		}
		return $this->tenant->transaction(function () use ($label, $scopes, $ttlDays): array {
			$this->tenant->lock('saldi_grants:' . $this->issuer->userId);
			$n = $this->tenant->value("SELECT COUNT(*) FROM agent_grants WHERE user_id = ? AND kind = 'personal' AND revoked_at IS NULL AND expires_at > ?", [$this->issuer->userId, gmdate('Y-m-d H:i:s')]);
			if ((int)$n >= 5) {
				throw new SaldiRuntimeError('CONFLICT', 'Personal grant limit reached', ['reason' => 'not_open']);
			}
			return $this->issue('personal', trim($label), $scopes, $ttlDays * 86400, null, null);
		});
	}
	private function issue(string $kind, string $label, array $scopes, int $ttl, ?string $sessionRef, ?int $maxUses, ?string $familyId = null): array
	{
		$secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
		$row = ['principal' => 'agent:saldi-assist', 'label' => $label, 'user_id' => $this->issuer->userId, 'user_hash' => $this->issuer->userHash(), 'kind' => $kind, 'scopes' => implode(' ', $scopes), 'token_hash' => hash('sha256', $secret), 'session_ref' => $sessionRef, 'created_at' => gmdate('Y-m-d H:i:s'), 'expires_at' => gmdate('Y-m-d H:i:s', time() + $ttl), 'max_uses' => $maxUses];
		if ($familyId !== null) {
			$row['family_id'] = $familyId;
		}
		$id = $this->tenant->insert('agent_grants', $row);
		if ($kind === 'personal') {
			$this->tenant->exec('UPDATE agent_grants SET principal = ? WHERE id = ?', ['agent:external:' . $id, $id]);
		}
		SaldiDecisionLog::append($this->tenant, ['event' => 'grant_issued', 'actor' => $this->issuer->principal, 'grant_id' => $id, 'idempotency_key' => 'grant:' . $id . ':issued']);
		$grant = $this->tenant->one('SELECT * FROM agent_grants WHERE id = ?', [$id]);
		unset($grant['token_hash']);
		return ['token' => 'smcp1.' . $this->issuer->installId . '.' . $this->issuer->regnskabId . '.' . $id . '.' . $secret, 'grant' => $grant];
	}
	/** Revoke a grant for its human owner or an administrator. */
	public function revoke(int $grantId): void
	{
		$this->human();
		$this->tenant->transaction(function () use ($grantId): void {
			$row = $this->tenant->one('SELECT * FROM agent_grants WHERE id = ? FOR UPDATE', [$grantId]);
			if (!$row || ((int)$row['user_id'] !== $this->issuer->userId && !SaldiPermissionService::allows($this->issuer, $this->tenant, 'settings.admin'))) {
				throw new SaldiRuntimeError('NOT_FOUND', 'Grant not found');
			}
			if ($row['revoked_at'] !== null) {
				return;
			}
			$this->tenant->exec('UPDATE agent_grants SET revoked_at = ?, revoked_by = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), $this->issuer->userId, $grantId]);
			SaldiDecisionLog::append($this->tenant, ['event' => 'grant_revoked', 'actor' => $this->issuer->principal, 'grant_id' => $grantId, 'idempotency_key' => 'grant:' . $grantId . ':revoked']);
		});
	}
	/** List the issuer's grants without secret hashes. @return list<array> */
	public function listMine(): array
	{
		$this->human();
		return $this->listing(' WHERE user_id = ?', [$this->issuer->userId]);
	}
	/** Administrator-only listing without secret hashes. @return list<array> */
	public function listAll(): array
	{
		$this->human();
		SaldiPermissionService::require($this->issuer, $this->tenant, 'settings.admin');
		return $this->listing('', []);
	}
	private function listing(string $where, array $params): array
	{
		$rows = $this->tenant->all('SELECT * FROM agent_grants' . $where . ' ORDER BY id DESC', $params);
		foreach ($rows as &$row) {
			unset($row['token_hash']);
		} unset($row);
		return $rows;
	}
	/** @return array{install:string,regnskab_id:int,grant_id:int,secret:string}|null */
	public static function parse(string $token): ?array
	{
		if (!preg_match('/^smcp1\.([a-z0-9_-]{1,32})\.([1-9][0-9]{0,9})\.([1-9][0-9]{0,9})\.([A-Za-z0-9_-]{43})$/D', $token, $m)) {
			return null;
		}
		if ((int)$m[2] > 2147483647 || (int)$m[3] > 2147483647) {
			return null;
		}
		$bytes = base64_decode(strtr($m[4], '-_', '+/') . '=', true);
		if ($bytes === false || strlen($bytes) !== 32 || rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=') !== $m[4]) {
			return null;
		}
		return ['install' => $m[1], 'regnskab_id' => (int)$m[2], 'grant_id' => (int)$m[3], 'secret' => $m[4]];
	}
}
