<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiSettings.php --- ai/mcp-assistant --- 2026.10.08
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
// Tenant settings and private human preferences with service-level checks.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Add companion shared foundation contracts.
// 20261002 CDX/LH Validate companion settings and isolate per-user digest preferences.
// 20261004 CDX/LH Add companion part 2 foundation contracts.
// 20261004 CDX/LH Validate private bank accounts and tenant lead days for implemented routines.
// 20261008 CL/LH Add the mobile Assist identity and delegation contract.

declare(strict_types=1);

final class SaldiSettings
{
	/** Read the latest global tenant setting, ignoring user/POS overrides. */
	public static function get(SaldiDb $db, string $group, string $name, string $default = 'off'): string
	{
		$v = $db->value('SELECT var_value FROM settings WHERE var_grp = ? AND var_name = ? AND COALESCE(user_id, 0) = 0 AND COALESCE(pos_id, 0) = 0 ORDER BY id DESC LIMIT 1', [$group, $name]);
		return $v === null ? $default : (string)$v;
	}
	/** Only human administrators may change the registered tenant settings. */
	public static function set(SaldiDb $db, SaldiTenantContext $ctx, string $group, string $name, string $value): void
	{
		if (!$ctx->isHuman()) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Human administrator required');
		}
		SaldiPermissionService::require($ctx, $db, 'settings.admin');
		$allowed = ['assistant/tools_enabled', 'assistant/external_agents', 'assistant/journal_suggestions', 'automation/routine.overdue_debtors', 'automation/routine.stale_open_journals', 'automation/routine.low_stock', 'automation/routine.vat_missing_documents', 'automation/routine.supplier_bank_change', 'automation/routine.expiring_batches', 'automation/routine.till_difference', 'automation/routine.posting_rule_candidates', 'automation/routine.reorder_suggestions', 'automation/routine.master_data_hygiene'];
		$key = $group . '/' . $name;
		$valid = in_array($key, $allowed, true) && in_array($value, ['on', 'off'], true);
		if ($key === 'assistant/vat_period') {
			$valid = in_array($value, ['month', 'quarter', 'half'], true);
		} elseif ($key === 'assistant/lead_days') {
			$valid = preg_match('/^[0-9]{1,3}$/D', $value) === 1 && (int)$value >= 1 && (int)$value <= 120;
		} elseif (in_array($key, ['assistant/interrupt_budget', 'assistant/till_difference_threshold'], true)) {
			$max = $name === 'interrupt_budget' ? 10 : 100000;
			$valid = preg_match('/^[0-9]{1,6}$/D', $value) === 1 && (int)$value <= $max;
		} elseif (in_array($key, ['assistant/minutes_per_suggestion', 'assistant/minutes_per_draft'], true)) {
			$valid = preg_match('/^([0-5]?[0-9](\.[0-9]+)?|60(\.0+)?)$/D', $value) === 1;
		}
		if (!$valid) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid tenant setting');
		}
		$db->transaction(static function () use ($db, $group, $name, $value): void {
			$db->lock('saldi_settings:' . $group . ':' . $name);
			$id = $db->value('SELECT id FROM settings WHERE var_grp = ? AND var_name = ? AND COALESCE(user_id, 0) = 0 AND COALESCE(pos_id, 0) = 0 ORDER BY id DESC LIMIT 1', [$group, $name]);
			if ($id !== null) {
				$db->exec('UPDATE settings SET var_value = ?, user_id = NULL WHERE id = ?', [$value, $id]);
			} else {
				$db->insert('settings', ['var_grp' => $group, 'var_name' => $name, 'var_value' => $value, 'var_description' => 'Saldi Assist tenant setting', 'user_id' => null, 'pos_id' => null]);
			}
		});
	}
	/** Read only the current human's private preference, ignoring global/POS values. */
	public static function getUser(SaldiDb $db, SaldiTenantContext $ctx, string $group, string $name, string $default = 'off'): string
	{
		self::requireUser($ctx);
		$v = $db->value('SELECT var_value FROM settings WHERE var_grp = ? AND var_name = ? AND user_id = ? AND COALESCE(pos_id, 0) = 0 ORDER BY id DESC LIMIT 1', [$group, $name, $ctx->userId]);
		return $v === null ? $default : (string)$v;
	}
	/** Save only the current human's private preferences; no administrator right needed. */
	public static function setUser(SaldiDb $db, SaldiTenantContext $ctx, string $group, string $name, string $value): void
	{
		self::requireUser($ctx);
		$valid = $group === 'assistant' && (($name === 'digest' && in_array($value, ['off', 'daily'], true))
			|| ($name === 'improvement_consent' && in_array($value, ['yes', 'no'], true))
			|| ($name === 'digest_email' && ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false)));
		if ($group === 'assistant' && $name === 'bank_account') {
			$valid = preg_match('/^[0-9]{1,15}$/D', $value) === 1
				&& $db->value("SELECT id FROM kontoplan WHERE kontonr = CAST(? AS DECIMAL(15,0)) AND regnskabsaar = ? AND kontotype = 'S' AND COALESCE(lukket, '') <> 'on'", [$value, $ctx->fiscalYear]) !== null;
		}
		if (!$valid) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid user preference');
		}
		$db->transaction(static function () use ($db, $ctx, $group, $name, $value): void {
			$db->lock('saldi_settings:user:' . $ctx->userId . ':' . $group . ':' . $name);
			$id = $db->value('SELECT id FROM settings WHERE var_grp = ? AND var_name = ? AND user_id = ? AND COALESCE(pos_id, 0) = 0 ORDER BY id DESC LIMIT 1', [$group, $name, $ctx->userId]);
			if ($id !== null) {
				$db->exec('UPDATE settings SET var_value = ?, pos_id = NULL WHERE id = ?', [$value, $id]);
			} else {
				$db->insert('settings', ['var_grp' => $group, 'var_name' => $name, 'var_value' => $value, 'var_description' => 'Saldi Assist user preference', 'user_id' => $ctx->userId, 'pos_id' => null]);
			}
		});
	}
	private static function requireUser(SaldiTenantContext $ctx): void
	{
		if (!$ctx->isHuman()) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Human self required');
		}
	}
}
