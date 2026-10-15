<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiMemoryStore.php --- ai/mcp-assistant --- 2026.10.01
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
// Bounded, private, retractable approved memory with scope locks.
//
// 20261001 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';
final class SaldiMemoryStore
{
	/** @return array{company:array,user:array,revision:string} */
	public static function active(SaldiDb $db, int $userId): array
	{
		$rows = $db->all("SELECT id, scope, user_id, fact, created_at FROM assistant_memory WHERE retracted_at IS NULL AND (scope = 'company' OR (scope = 'user' AND user_id = ?)) ORDER BY id", [$userId]);
		$result = ['company' => [], 'user' => [], 'revision' => hash('sha256', saldi_runtime_json(array_map(static fn(array $r): array => ['id' => (int)$r['id'], 'fact' => $r['fact']], $rows)))];
		foreach ($rows as $row) {
			$result[$row['scope']][] = ['id' => (int)$row['id'], 'fact' => $row['fact'], 'created_at' => str_replace(' ', 'T', $row['created_at']) . 'Z'];
		}
		return $result;
	}
	/** Cap reads use the same scope lock as inserts, including validation previews. */
	public static function checkCap(SaldiDb $db, string $scope, ?int $userId, string $fact): void
	{
		$db->transaction(static function () use ($db, $scope, $userId, $fact): void {
			$db->lock('saldi_memory:' . $scope . ':' . ($userId ?? 0));
			if (!in_array($scope, ['company', 'user'], true) || ($scope === 'user' && ($userId === null || $userId <= 0))) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid memory scope');
			}
			$used = (int)$db->value('SELECT COALESCE(SUM(char_length(fact)), 0) FROM assistant_memory WHERE scope = ? AND COALESCE(user_id, 0) = ? AND retracted_at IS NULL', [$scope, $userId ?? 0]);
			if (mb_strlen($fact) > 300 || $used + mb_strlen($fact) > ($scope === 'company' ? 2000 : 800)) {
				throw new SaldiRuntimeError('MEMORY_FULL', 'Memory cap reached');
			}
		});
	}
	/** Called only by an approved memory.fact, never by a tool or routine. */
	public static function insert(SaldiDb $db, SaldiTenantContext $ctx, array $item): int
	{
		if (!$ctx->isHuman()) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Human approval required');
		}
		$p = $item['payload'];
		$scope = $p['scope'];
		$userId = $scope === 'user' ? (int)$item['owner_user_id'] : null;
		if ($scope === 'company') {
			SaldiPermissionService::require($ctx, $db, 'settings.admin');
		} elseif ($userId !== $ctx->userId) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Private memory owner required');
		}
		if (!in_array($scope, ['company', 'user'], true)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid memory scope');
		}
		return $db->transaction(static function () use ($db, $ctx, $item, $p, $scope, $userId): int {
			self::checkCap($db, $scope, $userId, $p['fact']);
			return $db->insert('assistant_memory', ['scope' => $scope, 'user_id' => $userId, 'fact' => $p['fact'], 'source_principal' => $item['created_by'], 'source_note' => $p['reason'] ?? '', 'inbox_item_id' => $item['id'], 'approved_by' => $ctx->userId, 'created_at' => gmdate('Y-m-d H:i:s')]);
		});
	}
	/** Owner-only private retraction; company facts require a human administrator. */
	public static function retract(SaldiDb $db, SaldiTenantContext $ctx, int $id, string $reason): void
	{
		if (!$ctx->isHuman()) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Human retraction required');
		}
		if (mb_strlen($reason) > 255) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Retraction reason too long');
		}
		$db->transaction(static function () use ($db, $ctx, $id, $reason): void {
			$row = $db->one('SELECT * FROM assistant_memory WHERE id = ? FOR UPDATE', [$id]);
			if (!$row || ($row['scope'] === 'user' && (int)$row['user_id'] !== $ctx->userId)) {
				throw new SaldiRuntimeError('NOT_FOUND', 'Memory not found');
			}
			if ($row['scope'] === 'company') {
				SaldiPermissionService::require($ctx, $db, 'settings.admin');
			}
			if ($row['retracted_at'] !== null) {
				return;
			}
			$db->lock('saldi_memory:' . $row['scope'] . ':' . ($row['user_id'] ?? 0));
			$db->exec('UPDATE assistant_memory SET retracted_at = ?, retracted_by = ?, retract_reason = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), $ctx->userId, $reason, $id]);
			SaldiDecisionLog::append($db, ['event' => 'memory_retracted', 'actor' => $ctx->principal, 'approved_by' => $ctx->userId, 'subject_type' => 'memory', 'subject_id' => (string)$id, 'idempotency_key' => 'memory:' . $id . ':retracted']);
		});
	}
}
