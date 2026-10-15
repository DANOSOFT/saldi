<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/routines/SaldiPostingRuleCandidatesRoutine.php --- ai/mcp-assistant --- 2026.10.02
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
// Weekly SQL-only proposals from repeated posted party lines. Pending, recently
// rejected and active matches are suppressed; empty runs create no inbox items.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Implement bounded, deduplicated posting rule candidates.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiRoutine.php';
require_once __DIR__ . '/../SaldiProposingRoutine.php';
require_once __DIR__ . '/../SaldiPostingRules.php';

final class SaldiPostingRuleCandidatesRoutine extends SaldiBaseRoutine implements SaldiProposingRoutine
{
	public function name(): string
	{
		return 'posting_rule_candidates';
	}
	public function permission(): string
	{
		return 'journal.draft';
	}
	public function titleTextId(): int
	{
		return 5944;
	}
	public function schedule(): string
	{
		return 'weekly';
	}
	/** @return array{routine:string,scheduled_date:string,count:int,total?:array{amount:string,currency:string,converted:bool},rows:list<array>}|null */
	public function run(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): ?array
	{
		$proposals = $this->proposals($ctx, $db, $scheduledDate);
		return $proposals === [] ? null : ['routine' => $this->name(), 'scheduled_date' => $scheduledDate, 'count' => count($proposals), 'rows' => $proposals];
	}
	/** @return list<array{match:array{type:string,value:string},account:string,vat_code?:string,description?:string,replaces_rule_id?:int,explanation?:string,dedupe_key?:string}> */
	public function proposals(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): array
	{
		SaldiPermissionService::require($ctx, $db, $this->permission());
		$cutoff = $this->cutoff($scheduledDate, 120);
		$rejectedCutoff = $this->cutoff($scheduledDate, 90) . ' 00:00:00';
		$where = " FROM kassekladde k JOIN kladdeliste l ON l.id = k.kladde_id WHERE k.transdate BETWEEN ? AND ? AND k.amount > 0 AND l.bogfort = 'V'";
		$sql = "SELECT party_type, party_account, finance_account, COUNT(*) AS count FROM (SELECT k.d_type AS party_type, k.debet AS party_account, CASE WHEN k.k_type = 'F' THEN k.kredit ELSE NULL END AS finance_account" . $where . " AND k.d_type IN ('D','K') AND k.debet > 0 UNION ALL SELECT k.k_type AS party_type, k.kredit AS party_account, CASE WHEN k.d_type = 'F' THEN k.debet ELSE NULL END AS finance_account" . $where . " AND k.k_type IN ('D','K') AND k.kredit > 0) history GROUP BY party_type, party_account, finance_account";
		$parties = [];
		foreach ($db->all($sql, [$cutoff, $scheduledDate, $cutoff, $scheduledDate]) as $row) {
			$type = $row['party_type'] === 'K' ? 'supplier' : 'customer';
			$value = (string)(int)$row['party_account'];
			$key = SaldiPostingRules::dedupe($type, $value);
			$parties[$key] ??= ['match' => ['type' => $type, 'value' => $value], 'total' => 0, 'count' => 0, 'account' => ''];
			$parties[$key]['total'] += (int)$row['count'];
			if ($row['finance_account'] !== null && (int)$row['finance_account'] > 0 && (int)$row['count'] > $parties[$key]['count']) {
				$parties[$key]['count'] = (int)$row['count'];
				$parties[$key]['account'] = (string)(int)$row['finance_account'];
			}
		}
		$blocked = [];
		foreach (SaldiPostingRules::active($db) as $rule) {
			$blocked[SaldiPostingRules::dedupe($rule['match_type'], $rule['match_value'])] = true;
		}
		foreach ($db->all("SELECT dedupe_key FROM inbox_items WHERE kind = 'rule.posting' AND (status = 'open' OR (status = 'rejected' AND COALESCE(decided_at, created_at) >= ?))", [$rejectedCutoff]) as $item) {
			$blocked[$item['dedupe_key']] = true;
		}
		usort($parties, static fn(array $first, array $second): int => $second['count'] <=> $first['count'] ?: strcmp($first['match']['type'] . ':' . $first['match']['value'], $second['match']['type'] . ':' . $second['match']['value']));
		$proposals = [];
		foreach ($parties as $party) {
			$key = SaldiPostingRules::dedupe($party['match']['type'], $party['match']['value']);
			if (isset($blocked[$key]) || $party['total'] < 4 || $party['count'] * 5 < $party['total'] * 4) {
				continue;
			}
			try {
				$account = SaldiPostingRules::account($ctx, $db, $party['account']);
				SaldiPostingRules::party($db, $party['match']['type'], $party['match']['value']);
			} catch (SaldiRuntimeError $error) {
				if (!in_array($error->errorCode, ['VALIDATION_ERROR', 'PERMISSION_DENIED'], true)) {
					throw $error;
				}
				continue;
			}
			$label = SaldiPostingRules::label(['match_type' => $party['match']['type'], 'match_value' => $party['match']['value']], $db, $ctx->languageId);
			$proposals[] = ['match' => $party['match'], 'account' => $party['account'], 'dedupe_key' => $key, 'explanation' => mb_substr(sprintf('%d af %d linjer med %s er bogført på %s siden %s.', $party['count'], $party['total'], $label, $party['account'] . ' ' . $account['beskrivelse'], $cutoff), 0, 500)];
			if (count($proposals) === 5) {
				break;
			}
		}
		return $proposals;
	}
}
