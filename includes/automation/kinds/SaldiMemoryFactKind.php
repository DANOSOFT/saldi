<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/kinds/SaldiMemoryFactKind.php --- ai/mcp-assistant --- 2026.10.02
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
// Approval-gated facts reject secrets and recheck memory caps.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Use a bounded fact preview in memory proposal titles.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiInboxKind.php';
require_once __DIR__ . '/../SaldiMemoryStore.php';
final class SaldiMemoryFactKind extends SaldiBaseInboxKind
{
	public function name(): string
	{
		return 'memory.fact';
	}
	public function actionClass(): string
	{
		return 'persistent';
	}
	public function proposePermission(): string
	{
		return 'memory.propose';
	}
	public function decidePermission(array $item): string
	{
		return $item['payload']['scope'] === 'company' ? 'settings.admin' : 'memory.read';
	}
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		$scope = $input['scope'] ?? '';
		if (!in_array($scope, ['company', 'user'], true)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid memory scope');
		}
		$raw = $this->string($input['fact'] ?? null, 3, 300, 'fact');
		$fact = $this->string(preg_replace('/[\x00-\x1f\x7f]/u', '', $raw), 3, 300, 'fact');
		if (preg_match('/(?:password|kodeord|adgangskode|api[_ -]?key|token|cpr)\s*(?:[:=]|[0-9])|\b(?=[A-Za-z0-9_-]{16,}\b)(?=[A-Za-z0-9_-]*[A-Za-z])(?=[A-Za-z0-9_-]*[0-9])[A-Za-z0-9_-]+/iu', $fact)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Secret-like memory is not allowed');
		}
		$reason = $this->string($input['reason'] ?? '', 0, 255, 'reason');
		SaldiMemoryStore::checkCap($db, $scope, $scope === 'user' ? $ctx->userId : null, $fact);
		return $this->normalized($ctx, ['scope' => $scope, 'fact' => $fact, 'reason' => $reason], $this->text($ctx, $db, 5752, 'Husk') . ': ' . mb_substr($fact, 0, 80), $scope . ':' . ($scope === 'user' ? $ctx->userId : 0) . ':' . sha1(mb_strtolower($fact)), $scope === 'user' ? 'owner' : 'permission', $scope === 'company' ? 'settings.admin' : null);
	}
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		return ['memory_id' => SaldiMemoryStore::insert($db, $ctx, $item)];
	}
	public function canUndo(SaldiTenantContext $ctx, SaldiDb $db, array $item): bool
	{
		return $db->value('SELECT id FROM assistant_memory WHERE id = ? AND retracted_at IS NULL', [$item['result']['memory_id'] ?? 0]) !== null;
	}
	public function undo(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		SaldiMemoryStore::retract($db, $ctx, (int)$item['result']['memory_id'], 'undo');
		return ['retracted' => true];
	}
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		return ['columns' => [], 'rows' => [], 'fields' => [['label' => $this->text($ctx, $db, 5747), 'value' => $item['payload']['fact']], ['label' => $this->text($ctx, $db, 5748), 'value' => $this->text($ctx, $db, $item['payload']['scope'] === 'user' ? 5728 : 5729)]]];
	}
}
