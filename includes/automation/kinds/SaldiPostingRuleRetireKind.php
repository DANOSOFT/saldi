<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/kinds/SaldiPostingRuleRetireKind.php --- ai/mcp-assistant --- 2026.10.02
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
// Human-approved, revision-bound posting rule retirement; never changes bookings.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Implement posting rule retirement validation, approval and card.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiInboxKind.php';
require_once __DIR__ . '/../SaldiPostingRules.php';

final class SaldiPostingRuleRetireKind extends SaldiBaseInboxKind
{
	public function name(): string
	{
		return 'rule.retire';
	}
	public function actionClass(): string
	{
		return 'persistent';
	}
	public function proposePermission(): string
	{
		return 'journal.draft';
	}
	public function decidePermission(array $item): string
	{
		return 'journal.draft';
	}
	/** @return array{payload:array,title:string,subject_type:?string,subject_id:?string,proposed_rev:?string,dedupe_key:?string,visibility:string,assignee_permission:?string,owner_user_id:?int} */
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		SaldiPostingRules::helpers();
		SaldiPermissionService::require($ctx, $db, 'journal.draft');
		$id = ifset($input, 'rule_id');
		if (!is_int($id) || $id < 1) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid rule id', ['fields' => ['rule_id']]);
		}
		$rule = SaldiPostingRules::get($db, $id);
		if (!$rule || $rule['status'] !== 'active') {
			throw new SaldiRuntimeError('NOT_FOUND', 'Active rule not found');
		}
		SaldiPostingRules::account($ctx, $db, $rule['account']);
		$normal = $this->normalized($ctx, ['rule_id' => $id, 'reason' => $this->string(ifset($input, 'reason', ''), 0, 255, 'reason')], mb_substr($this->text($ctx, $db, 5901, 'Stop bogføringsregel') . ': ' . SaldiPostingRules::label($rule, $db, $ctx->languageId), 0, 120), 'rule:' . $id . ':retire', 'permission', 'journal.draft');
		$normal['subject_type'] = 'rule';
		$normal['subject_id'] = (string)$id;
		$normal['proposed_rev'] = hash('sha256', saldi_runtime_json($rule));
		return $normal;
	}
	/** @return array{rule_id:int} */
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		SaldiPostingRules::requireHuman($ctx, $db);
		return $db->transaction(function () use ($ctx, $db, $item): array {
			$rule = SaldiPostingRules::get($db, $item['payload']['rule_id']);
			if (!$rule) {
				throw new SaldiRuntimeError('NOT_FOUND', 'Rule not found');
			}
			$db->lock(SaldiPostingRules::dedupe($rule['match_type'], $rule['match_value']));
			$normal = $this->validate($ctx, $db, $item['payload']);
			if (isset($item['proposed_rev']) && $normal['proposed_rev'] !== $item['proposed_rev']) {
				throw new SaldiRuntimeError('STALE_CONTEXT', 'Rule changed');
			}
			$id = $item['payload']['rule_id'];
			SaldiPostingRules::retire($db, $ctx, $id, $item['payload']['reason'] ?: 'retired', (int)$item['id']);
			return ['rule_id' => $id];
		});
	}
	public function currentRevision(SaldiTenantContext $ctx, SaldiDb $db, array $item): ?string
	{
		return hash('sha256', saldi_runtime_json(SaldiPostingRules::get($db, $item['payload']['rule_id'])));
	}
	/** @return array{columns:array,rows:array,fields:list<array{label:string,value:string}>} */
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$rule = SaldiPostingRules::get($db, $item['payload']['rule_id']);
		return ['columns' => [], 'rows' => [], 'fields' => [
			['label' => $this->text($ctx, $db, 5900, 'Bogføringsregel'), 'value' => $rule ? SaldiPostingRules::label($rule, $db, $ctx->languageId) : (string)$item['payload']['rule_id']],
			['label' => $this->text($ctx, $db, 5749, 'Begrundelse'), 'value' => $item['payload']['reason']],
		]];
	}
}
