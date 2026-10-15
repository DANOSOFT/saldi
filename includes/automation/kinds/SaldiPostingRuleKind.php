<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/kinds/SaldiPostingRuleKind.php --- ai/mcp-assistant --- 2026.10.02
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
// Approval-gated posting rules with serialized replacement and custody-safe undo.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Implement posting rule validation, approval, cards and undo.
// 20261005 CL/LH A party rule may name the supplier/customer when exactly one open party has that name.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiInboxKind.php';
require_once __DIR__ . '/../SaldiPostingRules.php';
require_once __DIR__ . '/../SaldiPostingSuggester.php';

final class SaldiPostingRuleKind extends SaldiBaseInboxKind
{
	public function name(): string
	{
		return 'rule.posting';
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
		$match = ifset($input, 'match');
		if (!is_array($match) || !in_array(ifset($match, 'type', ''), ['supplier', 'customer', 'text'], true)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid rule match', ['fields' => ['match']]);
		}
		$type = $match['type'];
		if ($type === 'text') {
			$value = SaldiPostingSuggester::normalise($this->string(ifset($match, 'value'), 3, 100, 'match.value'));
			if (mb_strlen($value) < 3 || mb_strlen($value) > 60 || !preg_match('/\p{L}/u', $value)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid text match', ['fields' => ['match.value']]);
			}
		} else {
			$raw = ifset($match, 'value');
			// "Hjertegarn" instead of its account number: accept a name that matches exactly one
			// open party of that ledger (case-insensitive), else say how to find the number.
			if (is_string($raw) && !preg_match('/^[0-9]{1,15}$/D', trim($raw)) && trim($raw) !== '') {
				$found = $db->all("SELECT kontonr FROM adresser WHERE art = ? AND COALESCE(lukket, '') <> 'on' AND LOWER(TRIM(firmanavn)) = LOWER(TRIM(?)) LIMIT 2", [$type === 'supplier' ? 'K' : 'D', $raw]);
				if (count($found) !== 1) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'match.value must be the ' . $type . ' account number; ' . (count($found) ? 'several parties have that name' : 'no open ' . $type . ' has that exact name') . ' (use search_parties)', ['fields' => ['match.value'], 'reason' => count($found) ? 'ambiguous_party' : 'unknown_party']);
				}
				$raw = (string)$found[0]['kontonr'];
			}
			$value = SaldiPostingRules::number($raw, 'match.value');
			SaldiPostingRules::party($db, $type, $value);
		}
		$account = SaldiPostingRules::account($ctx, $db, ifset($input, 'account'));
		$existing = $db->one("SELECT * FROM posting_rules WHERE status = 'active' AND match_type = ? AND match_value = ? ORDER BY id DESC LIMIT 1", [$type, $value]);
		$payload = [
			'match' => ['type' => $type, 'value' => $value], 'account' => $input['account'],
			'vat_code' => SaldiPostingRules::vat($ctx, $db, ifset($input, 'vat_code', '')),
			'description' => $this->string(ifset($input, 'description', ''), 0, 100, 'description'),
			'replaces_rule_id' => $existing ? (int)$existing['id'] : null,
		];
		$label = SaldiPostingRules::label(['match_type' => $type, 'match_value' => $value], $db, $ctx->languageId);
		$normal = $this->normalized($ctx, $payload, mb_substr('Regel: ' . $label . ' -> ' . $input['account'] . ' ' . $account['beskrivelse'], 0, 120), SaldiPostingRules::dedupe($type, $value), 'permission', 'journal.draft');
		$normal['subject_type'] = 'rule';
		$normal['subject_id'] = $existing ? (string)$existing['id'] : null;
		$normal['proposed_rev'] = $this->matchRevision($existing);
		return $normal;
	}
	private function matchRevision(?array $rule): string
	{
		return hash('sha256', saldi_runtime_json($rule));
	}
	public function currentRevision(SaldiTenantContext $ctx, SaldiDb $db, array $item): ?string
	{
		$match = $item['payload']['match'];
		return $this->matchRevision($db->one("SELECT * FROM posting_rules WHERE status = 'active' AND match_type = ? AND match_value = ? ORDER BY id DESC LIMIT 1", [$match['type'], $match['value']]));
	}
	/** @return array{rule_id:int,replaced_rule_id:?int} */
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		SaldiPostingRules::requireHuman($ctx, $db);
		return $db->transaction(function () use ($ctx, $db, $item): array {
			$payload = $item['payload'];
			$db->lock(SaldiPostingRules::dedupe($payload['match']['type'], $payload['match']['value']));
			$normal = $this->validate($ctx, $db, $payload);
			if ($normal['payload']['replaces_rule_id'] !== $payload['replaces_rule_id']
				|| (isset($item['proposed_rev']) && $normal['proposed_rev'] !== $item['proposed_rev'])) {
				throw new SaldiRuntimeError('STALE_CONTEXT', 'Rule changed');
			}
			$replaced = $payload['replaces_rule_id'];
			if ($replaced !== null) {
				SaldiPostingRules::retire($db, $ctx, $replaced, 'replaced', (int)$item['id']);
			}
			$id = SaldiPostingRules::insert($db, $ctx, $item);
			return ['rule_id' => $id, 'replaced_rule_id' => $replaced];
		});
	}
	public function canUndo(SaldiTenantContext $ctx, SaldiDb $db, array $item): bool
	{
		SaldiPostingRules::helpers();
		$rule = SaldiPostingRules::get($db, (int)(ifset($item, ['result', 'rule_id'], 0)));
		return $rule !== null && $rule['status'] === 'active' && (int)$rule['inbox_item_id'] === (int)$item['id'];
	}
	/** @return array{rule_id:int,reactivated_rule_id:?int} */
	public function undo(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		SaldiPostingRules::requireHuman($ctx, $db);
		return $db->transaction(function () use ($ctx, $db, $item): array {
			$payload = $item['payload'];
			$db->lock(SaldiPostingRules::dedupe($payload['match']['type'], $payload['match']['value']));
			if (!$this->canUndo($ctx, $db, $item)) {
				throw new SaldiRuntimeError('CONFLICT', 'Rule is no longer active', ['reason' => 'undo_changed']);
			}
			$id = (int)$item['result']['rule_id'];
			SaldiPostingRules::retire($db, $ctx, $id, 'undo', (int)$item['id']);
			$replaced = ifset($item, ['result', 'replaced_rule_id']);
			$reactivated = null;
			if ($replaced !== null) {
				$old = $db->one('SELECT * FROM posting_rules WHERE id = ? FOR UPDATE', [$replaced]);
				$event = $db->one("SELECT inbox_item_id, detail FROM decision_log WHERE subject_type = 'rule' AND subject_id = ? AND event = 'rule_retired' ORDER BY id DESC LIMIT 1", [(string)$replaced]);
				if ($old && $old['status'] === 'retired' && $old['retire_reason'] === 'replaced'
					&& $event && (int)$event['inbox_item_id'] === (int)$item['id']) {
					$db->exec("UPDATE posting_rules SET status = 'active', retired_at = NULL, retired_by = NULL, retire_reason = NULL WHERE id = ?", [$replaced]);
					$reactivated = (int)$replaced;
				}
			}
			return ['rule_id' => $id, 'reactivated_rule_id' => $reactivated];
		});
	}
	/** @return array{columns:list<string>,rows:list<list<string>>,fields:list<array{label:string,value:string}>} */
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$payload = $item['payload'];
		$columns = [];
		foreach ([[5920, 'Når'], [5921, 'Konto'], [5922, 'Moms'], [5923, 'Tekst på linjen']] as [$id, $fallback]) {
			$columns[] = $this->text($ctx, $db, $id, $fallback);
		}
		$account = $db->value('SELECT beskrivelse FROM kontoplan WHERE kontonr = ? AND regnskabsaar = ?', [$payload['account'], $ctx->fiscalYear]);
		$vat = $payload['vat_code'];
		return ['columns' => $columns, 'rows' => [[
			SaldiPostingRules::label(['match_type' => $payload['match']['type'], 'match_value' => $payload['match']['value']], $db, $ctx->languageId),
			$payload['account'] . ' ' . $account,
			$vat === '' ? $this->text($ctx, $db, 5934, 'Kontoens momskode') : ($vat === 'none' ? $this->text($ctx, $db, 5935, 'Momsfri') : $vat),
			$payload['description'],
		]], 'fields' => $payload['replaces_rule_id'] === null ? [] : [['label' => $this->text($ctx, $db, 5900, 'Bogføringsregel'), 'value' => sprintf($this->text($ctx, $db, 5936, 'Erstatter regel %s'), $payload['replaces_rule_id'])]]];
	}
}
