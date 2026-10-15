<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiInboxKind.php --- ai/mcp-assistant --- 2026.10.04
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
// Kind contract plus shared translated ApprovalCard rendering.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Attach a stable reason to unsupported undo conflicts.
// 20261002 CDX/LH Add companion shared foundation contracts.
// 20261004 CDX/LH Add companion part 2 foundation contracts.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';

interface SaldiInboxKind
{
	/** Stable English kind code. */
	public function name(): string;
	/** Custody class: read_only, reversible_draft, persistent, or money. */
	public function actionClass(): string;
	/** Permission required to create a proposal. */
	public function proposePermission(): string;
	/** Permission required to approve, reject or undo the stored item. */
	public function decidePermission(array $item): string;
	/** Normalize and recheck business rules at proposal and approval.
	 *
	 * @return array{
	 *   payload: array, title: string, subject_type: ?string, subject_id: ?string,
	 *   proposed_rev: ?string, dedupe_key: ?string, visibility: string,
	 *   assignee_permission: ?string, owner_user_id: ?int
	 * } */
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array;
	/** Live subject digest; null means the kind has no stale-context check. */
	public function currentRevision(SaldiTenantContext $ctx, SaldiDb $db, array $item): ?string;
	/** Apply the approved change inside the caller's transaction. @return array Receipt. */
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array;
	/** Whether the original applied change remains intact and reversible. */
	public function canUndo(SaldiTenantContext $ctx, SaldiDb $db, array $item): bool;
	/** Reverse exactly this item inside the caller's transaction. @return array Receipt. */
	public function undo(SaldiTenantContext $ctx, SaldiDb $db, array $item): array;
	/** Build the translated, untrusted-data-safe ApprovalCard. @return array Card model. */
	public function render(SaldiTenantContext $ctx, SaldiDb $db, array $item): array;
}

/** Shared safe card rendering and validation primitives for base and companion kinds. */
abstract class SaldiBaseInboxKind implements SaldiInboxKind
{
	public function currentRevision(SaldiTenantContext $ctx, SaldiDb $db, array $item): ?string
	{
		return null;
	}
	public function canUndo(SaldiTenantContext $ctx, SaldiDb $db, array $item): bool
	{
		return false;
	}
	public function undo(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		throw new SaldiRuntimeError('CONFLICT', 'Cannot undo this item', ['reason' => 'undo_changed']);
	}
	protected function text(SaldiTenantContext $ctx, SaldiDb $db, int $id, string $fallback = ''): string
	{
		return SaldiText::get($db, $id, $ctx->languageId, $fallback);
	}
	protected function string(mixed $value, int $min, int $max, string $field): string
	{
		if (!is_string($value) || mb_strlen(trim($value)) < $min || mb_strlen($value) > $max) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid ' . $field, ['fields' => [$field]]);
		}
		return trim($value);
	}
	protected function date(mixed $value, string $field): string
	{
		if (!is_string($value) || !preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid date', ['fields' => [$field]]);
		}
		return $value;
	}
	protected function normalized(SaldiTenantContext $ctx, array $payload, string $title, ?string $dedupe = null, string $visibility = 'owner', ?string $permission = null): array
	{
		return ['payload' => $payload, 'title' => $title, 'subject_type' => null, 'subject_id' => null, 'proposed_rev' => null, 'dedupe_key' => $dedupe, 'visibility' => $visibility, 'assignee_permission' => $permission, 'owner_user_id' => $ctx->userId > 0 ? $ctx->userId : null];
	}
	/** @return array{columns:array,rows:array,fields:array} */
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		return ['columns' => [], 'rows' => [], 'fields' => []];
	}
	public function render(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$ids = ['journal.draft_entry' => 5701, 'memory.fact' => 5702, 'memory.retract' => 5703, 'followup.task' => 5704, 'routine.finding' => 5705, 'rule.posting' => 5900, 'rule.retire' => 5901, 'party.create' => ($item['payload']['ledger'] ?? '') === 'supplier' ? 5903 : 5902, 'purchase_order.draft' => 5904, 'reminder.draft' => 5905, 'support.report' => 5906, 'settlement.draft' => 6100, 'purchase_order.invoice_stage' => 6101, 'sales_order.draft' => ($item['payload']['type'] ?? 'offer') === 'order' ? 6103 : 6102, 'credit_note.draft' => 6104];
		$origin = $item['origin'];
		$originIds = ['agent' => 5710, 'rule' => 5711, 'ai' => 5712, 'system' => 5713, 'user' => 5714];
		$evidence = $item['evidence'] ?? [];
		$links = [];
		foreach ($evidence['citations'] ?? [] as $citation) {
			if (is_array($citation) && is_string($citation['screen'] ?? null) && SaldiScreens::isAllowed($citation['screen'])) {
				$links[] = ['label' => (string)($citation['label'] ?? ''), 'screen' => $citation['screen']];
			}
		}
		$next = match ($this->name()) {
			'journal.draft_entry' => empty($item['payload']['kladde_id']) ? 5706 : 5707, 'memory.fact' => 5708, 'memory.retract' => 5709, 'followup.task' => 5718, 'rule.posting' => 5910, 'rule.retire' => 5911, 'party.create' => 5912, 'purchase_order.draft' => 5913, 'reminder.draft' => 5914, 'support.report' => 5915, 'settlement.draft' => 6110, 'purchase_order.invoice_stage' => 6111, 'sales_order.draft' => ($item['payload']['type'] ?? 'offer') === 'order' ? 6113 : 6112, 'credit_note.draft' => 6114, default => 5719
		};
		return array_merge([
			'item_id' => (int)$item['id'], 'revision' => (int)$item['revision'], 'digest' => $item['digest'], 'tenant_hash' => $ctx->tenantHash(),
			'kind' => $this->name(), 'kind_label' => $this->text($ctx, $db, $ids[$this->name()], $this->name()), 'provenance' => $origin,
			'provenance_label' => $this->text($ctx, $db, $originIds[$origin] ?? 5713, $origin), 'title' => $item['title'], 'summary' => $item['title'],
			'why' => (string)($evidence['explanation'] ?? ''), 'evidence' => $links,
			'notices' => !empty($evidence['tainted']) ? [['kind' => 'tainted', 'text' => $this->text($ctx, $db, 5723)]] : [],
			'next_step' => $this->text($ctx, $db, $next), 'action_class' => $this->actionClass(), 'status' => $item['status'],
			'can_undo' => $item['can_undo'] ?? false, 'approve_label' => $this->text($ctx, $db, match ($this->name()) { 'followup.task' => 5724, 'routine.finding' => 5725, 'support.report' => 5916, 'party.create' => 5917, default => 5726 }),
			'reject_label' => $this->text($ctx, $db, 5727), 'expires_at' => $item['expires_at'], 'screen' => SaldiScreens::link('inbox_item', (int)$item['id']),
		], $this->change($ctx, $db, $item));
	}
}
