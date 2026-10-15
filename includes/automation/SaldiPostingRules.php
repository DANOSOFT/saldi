<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiPostingRules.php --- ai/mcp-assistant --- 2026.10.02
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
// Approved posting rules, shared account validation and audited human retirement.
// Match locks serialize replacement, retirement and undo without journal writes.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';
require_once __DIR__ . '/SaldiDecisionLog.php';

final class SaldiPostingRules
{
	/** Load lookup helpers at execution, never during runtime/class discovery. */
	public static function helpers(): void
	{
		require_once __DIR__ . '/../std_func.php';
	}
	/** @return list<array> Active rules, newest first. */
	public static function active(SaldiDb $db): array
	{
		return $db->all("SELECT * FROM posting_rules WHERE status = 'active' ORDER BY id DESC");
	}
	/** @return array|null */
	public static function get(SaldiDb $db, int $id): ?array
	{
		return $db->one('SELECT * FROM posting_rules WHERE id = ?', [$id]);
	}
	public static function dedupe(string $type, string $value): string
	{
		return 'rule:' . $type . ':' . sha1($value);
	}
	public static function requireHuman(SaldiTenantContext $ctx, SaldiDb $db): void
	{
		self::helpers();
		if (!$ctx->isHuman()) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Human decision required');
		}
		SaldiPermissionService::require($ctx, $db, 'journal.draft');
	}
	public static function number(mixed $value, string $field): string
	{
		if (!is_string($value) || !preg_match('/^[0-9]{1,15}$/D', $value)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid account number', ['fields' => [$field]]);
		}
		return $value;
	}
	/** @return array Open finance account in the caller's fiscal year. */
	public static function account(SaldiTenantContext $ctx, SaldiDb $db, mixed $number): array
	{
		$number = self::number($number, 'account');
		$row = $db->one("SELECT kontonr, beskrivelse, moms, kontotype FROM kontoplan WHERE kontonr = ? AND regnskabsaar = ? AND kontotype IN ('D','S') AND COALESCE(lukket, '') <> 'on'", [$number, $ctx->fiscalYear]);
		if (!$row) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Open finance account required', ['fields' => ['account']]);
		}
		if ($row['kontotype'] === 'D' && SaldiPermissionService::revenueHidden($ctx, $db)) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Revenue is hidden');
		}
		return $row;
	}
	/** @return array Open supplier/customer, never bank data. */
	public static function party(SaldiDb $db, string $type, mixed $number): array
	{
		$number = self::number($number, 'party.account');
		if (!in_array($type, ['supplier', 'customer'], true)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid party ledger', ['fields' => ['party.ledger']]);
		}
		$row = $db->one("SELECT kontonr, firmanavn FROM adresser WHERE kontonr = ? AND art = ? AND COALESCE(lukket, '') <> 'on'", [$number, $type === 'supplier' ? 'K' : 'D']);
		if (!$row) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Open party required', ['fields' => ['party.account']]);
		}
		return $row;
	}
	public static function vat(SaldiTenantContext $ctx, SaldiDb $db, mixed $value): string
	{
		if ($value === '' || $value === 'none') {
			return $value;
		}
		if (!is_string($value) || !preg_match('/^([A-Z])([0-9]{1,7})$/D', $value, $parts)
			|| $db->value('SELECT id FROM grupper WHERE art = ? AND kodenr = ? AND fiscal_year = ? LIMIT 1', [$parts[1] . 'M', (int)$parts[2], $ctx->fiscalYear]) === null) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid VAT code', ['fields' => ['vat_code']]);
		}
		return $value;
	}
	public static function label(array $rule, SaldiDb $db, int $languageId = 1): string
	{
		$type = $rule['match_type'];
		$value = (string)$rule['match_value'];
		if ($type === 'text') {
			return sprintf(SaldiText::get($db, 5933, $languageId, 'Teksten indeholder »%s«'), $value);
		}
		$name = $db->value('SELECT firmanavn FROM adresser WHERE kontonr = ? AND art = ? LIMIT 1', [$value, $type === 'supplier' ? 'K' : 'D']);
		return sprintf(SaldiText::get($db, $type === 'supplier' ? 5931 : 5932, $languageId, $type === 'supplier' ? 'Leverandør %s' : 'Kunde %s'), $value . ($name ? ' ' . $name : ''));
	}
	/** @return int New rule id; only called from an approved inbox item. */
	public static function insert(SaldiDb $db, SaldiTenantContext $ctx, array $item): int
	{
		self::requireHuman($ctx, $db);
		$payload = $item['payload'];
		return $db->insert('posting_rules', [
			'match_type' => $payload['match']['type'], 'match_value' => $payload['match']['value'],
			'account' => $payload['account'], 'vat_code' => $payload['vat_code'], 'description' => $payload['description'],
			'note' => mb_substr((string)(ifset($item, ['evidence', 'explanation'], '')), 0, 255),
			'inbox_item_id' => $item['id'], 'created_by' => $item['created_by'], 'approved_by' => $ctx->userId,
			'created_at' => gmdate('Y-m-d H:i:s'), 'status' => 'active',
		]);
	}
	/** Idempotent human retirement; optional inbox id records replacement custody. */
	public static function retire(SaldiDb $db, SaldiTenantContext $ctx, int $id, string $reason, ?int $inboxItemId = null): void
	{
		self::requireHuman($ctx, $db);
		if (mb_strlen($reason) > 255) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Retirement reason too long', ['fields' => ['reason']]);
		}
		$db->transaction(static function () use ($db, $ctx, $id, $reason, $inboxItemId): void {
			$row = self::get($db, $id);
			if (!$row) {
				throw new SaldiRuntimeError('NOT_FOUND', 'Rule not found');
			}
			$db->lock(self::dedupe($row['match_type'], $row['match_value']));
			$row = $db->one('SELECT * FROM posting_rules WHERE id = ? FOR UPDATE', [$id]);
			if ($row['status'] !== 'active') {
				return;
			}
			$db->exec("UPDATE posting_rules SET status = 'retired', retired_at = ?, retired_by = ?, retire_reason = ? WHERE id = ?", [gmdate('Y-m-d H:i:s'), $ctx->userId, $reason, $id]);
			SaldiDecisionLog::append($db, [
				'event' => 'rule_retired', 'actor' => $ctx->principal, 'approved_by' => $ctx->userId,
				'subject_type' => 'rule', 'subject_id' => (string)$id, 'kind' => 'rule.retire',
				'inbox_item_id' => $inboxItemId, 'idempotency_key' => 'rule:' . $id . ':retire' . ($inboxItemId === null ? '' : ':' . $inboxItemId . ':' . substr(hash('sha256', $reason), 0, 16)),
				'detail' => ['reason' => $reason],
			]);
		});
	}
}
