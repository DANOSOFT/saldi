<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/kinds/SaldiMemoryRetractKind.php --- ai/mcp-assistant --- 2026.10.02
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
// Visible, active memory retraction proposals.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Use a bounded fact preview in retraction titles.
// 20261002 CL/LH One open retraction per memory fact (dedupe_key memory:<id>); asking twice returns it.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiInboxKind.php';
require_once __DIR__ . '/../SaldiMemoryStore.php';
final class SaldiMemoryRetractKind extends SaldiBaseInboxKind
{
	public function name(): string
	{
		return 'memory.retract';
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
		return $item['payload']['target_scope'] === 'company' ? 'settings.admin' : 'memory.read';
	}
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		$id = $input['memory_id'] ?? null;
		if (!is_int($id) || $id < 1) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid memory id');
		}
		$row = $db->one('SELECT * FROM assistant_memory WHERE id = ? AND retracted_at IS NULL', [$id]);
		if (!$row || ($row['scope'] === 'user' && (int)$row['user_id'] !== $ctx->userId)) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Memory not found');
		}
		$reason = $this->string($input['reason'] ?? '', 0, 255, 'reason');
		$n = $this->normalized($ctx, ['memory_id' => $id, 'reason' => $reason, 'target_scope' => $row['scope'], 'target_user_id' => $row['user_id'] === null ? null : (int)$row['user_id'], 'fact' => $row['fact']], $this->text($ctx, $db, 5753, 'Glem') . ': ' . mb_substr($row['fact'], 0, 80), 'memory:' . $id, $row['scope'] === 'user' ? 'owner' : 'permission', $row['scope'] === 'company' ? 'settings.admin' : null);
		$n['subject_type'] = 'memory';
		$n['subject_id'] = (string)$id;
		return $n;
	}
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		SaldiMemoryStore::retract($db, $ctx, $item['payload']['memory_id'], $item['payload']['reason']);
		return ['memory_id' => $item['payload']['memory_id'], 'retracted' => true];
	}
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		return ['columns' => [], 'rows' => [], 'fields' => [['label' => $this->text($ctx, $db, 5747), 'value' => $item['payload']['fact']], ['label' => $this->text($ctx, $db, 5749), 'value' => $item['payload']['reason']]]];
	}
}
