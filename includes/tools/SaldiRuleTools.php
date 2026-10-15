<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiRuleTools.php --- ai/mcp-assistant --- 2026.10.02
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
// Permission-checked posting rule catalogue and read-only posting suggestions.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Implement rule paging, citations and deterministic suggestions.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';
require_once __DIR__ . '/SaldiToolRuntime.php';
require_once __DIR__ . '/../automation/SaldiPostingSuggester.php';

final class SaldiRuleTools
{
	/** @return array{data:array,citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int} */
	public static function listRules(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPostingRules::helpers();
		SaldiPermissionService::require($ctx, $db, 'rules.read');
		$status = ifset($args, 'status', 'active');
		if (!in_array($status, ['active', 'retired', 'all'], true)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid rule status', ['fields' => ['status']]);
		}
		$limit = ifset($args, 'limit', 25);
		$offset = ifset($args, 'offset', 0);
		if (!is_int($limit) || $limit < 1 || $limit > 100 || !is_int($offset) || $offset < 0 || $offset > 999999) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid page');
		}
		$sql = "SELECT r.*, a.beskrivelse AS account_name, u.brugernavn AS approved_by_name, COALESCE(e.uses, 0) AS uses_90d FROM posting_rules r JOIN kontoplan a ON a.kontonr = CAST(r.account AS DECIMAL(15,0)) AND a.regnskabsaar = ? LEFT JOIN brugere u ON u.id = r.approved_by LEFT JOIN (SELECT subject, COUNT(*) AS uses FROM assist_events WHERE event = 'suggestion_used' AND created_at >= ? GROUP BY subject) e ON e.subject = "
			. ($db->isPostgres() ? "'rule:' || CAST(r.id AS TEXT)" : "CONCAT('rule:', r.id)") . ' WHERE 1 = 1';
		$params = [$ctx->fiscalYear, gmdate('Y-m-d H:i:s', time() - 90 * 86400)];
		if ($status !== 'all') {
			$sql .= ' AND r.status = ?';
			$params[] = $status;
		}
		if (SaldiPermissionService::revenueHidden($ctx, $db)) {
			$sql .= " AND a.kontotype <> 'D'";
		}
		$page = SaldiToolRuntime::page($db, $sql, 'r.id DESC', $params, ['limit' => $limit, 'offset' => $offset]);
		$rows = [];
		$citations = [];
		foreach ($page['rows'] as $rule) {
			$label = SaldiPostingRules::label($rule, $db, $ctx->languageId);
			$rows[] = [
				'rule_id' => (int)$rule['id'], 'match' => ['type' => $rule['match_type'], 'value' => $rule['match_value'], 'label' => SaldiToolRuntime::text($label)],
				'account' => $rule['account'], 'account_name' => SaldiToolRuntime::text($rule['account_name']), 'vat_code' => $rule['vat_code'],
				'description' => $rule['description'], 'note' => $rule['note'], 'created_at' => str_replace(' ', 'T', $rule['created_at']) . 'Z',
				'approved_by_name' => $rule['approved_by_name'], 'uses_90d' => (int)$rule['uses_90d'],
			];
			$citations[] = SaldiToolRuntime::citation('rule', (int)$rule['id'], $label);
		}
		return SaldiToolRuntime::result(['rows' => $rows, 'total' => $page['total']], $citations, $page['more']);
	}
	/** @return array{data:array,citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int} */
	public static function suggest(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		$data = (new SaldiPostingSuggester($ctx, $db))->suggest($args);
		$citations = [];
		foreach ($data['suggestions'] as $suggestion) {
			if ($suggestion['rule_id'] !== null) {
				$citations['rule:' . $suggestion['rule_id']] = SaldiToolRuntime::citation('rule', $suggestion['rule_id'], $suggestion['description']);
			}
			foreach ($suggestion['basis']['line_ids'] as $id) {
				$citations['line:' . $id] = SaldiToolRuntime::citation('journal_line', $id, $suggestion['description']);
			}
		}
		foreach ($data['duplicates'] as $duplicate) {
			$citations['line:' . $duplicate['line_id']] = SaldiToolRuntime::citation('journal_line', $duplicate['line_id'], $duplicate['description']) + ['screen' => $duplicate['screen']];
		}
		return SaldiToolRuntime::result($data, array_values($citations), false, count($data['suggestions']) + count($data['duplicates']));
	}
}
