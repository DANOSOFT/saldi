<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiAssistReport.php --- ai/mcp-assistant --- 2026.10.03
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
// Aggregate tenant audit events and distinguish estimated savings from measured activity.
//
// 20261002 CDX/LH Created.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';

final class SaldiAssistReport
{
	/**
	 * Date-only bounds include the end day; timestamp bounds are UTC and end-exclusive.
	 * @return array{from:string,to:string,kinds:list<array>,suggestions:list<array>,duplicates_warned:int,tools:list<array>,routines:list<array>,rules:array{active_count:int,uses:int},estimated_time_saved:array{minutes:float,measured:bool,assumptions:array<string,float>}}
	 */
	public static function build(SaldiDb $db, string $from, string $to): array
	{
		$start = self::bound($from, false);
		$end = self::bound($to, true);
		if ($start >= $end) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid report period');
		}
		$bounds = [$start, $end];
		$kinds = [];
		$counts = ['proposed' => 0, 'applied' => 0, 'rejected' => 0, 'expired' => 0, 'cancelled' => 0, 'undone' => 0];
		foreach ($db->all('SELECT kind, event, COUNT(DISTINCT inbox_item_id) AS count FROM decision_log WHERE created_at >= ? AND created_at < ? AND kind IS NOT NULL GROUP BY kind, event ORDER BY kind, event', $bounds) as $row) {
			if (!array_key_exists($row['event'], $counts)) {
				continue;
			}
			$kinds[$row['kind']] ??= ['kind' => $row['kind']] + $counts;
			$kinds[$row['kind']][$row['event']] = (int)$row['count'];
		}
		$latencies = [];
		$decisions = $db->all("SELECT d.kind, d.created_at, p.proposed_at FROM decision_log d JOIN (SELECT inbox_item_id, MIN(created_at) AS proposed_at FROM decision_log WHERE event = 'proposed' GROUP BY inbox_item_id) p ON p.inbox_item_id = d.inbox_item_id WHERE d.created_at >= ? AND d.created_at < ? AND d.event IN ('applied', 'rejected', 'expired', 'cancelled') ORDER BY d.kind, d.id", $bounds);
		foreach ($decisions as $row) {
			$seconds = strtotime($row['created_at'] . ' UTC') - strtotime($row['proposed_at'] . ' UTC');
			if ($seconds >= 0) {
				$latencies[$row['kind']][] = $seconds / 60;
			}
		}
		foreach ($kinds as $name => &$kind) {
			$denominator = $kind['applied'] + $kind['rejected'] + $kind['expired'];
			$kind['accept_share'] = $denominator ? $kind['applied'] / $denominator : null;
			$kind['median_minutes'] = self::percentile($latencies[$name] ?? [], 0.5);
		}
		unset($kind);
		$suggestions = [];
		$duplicates = 0;
		$used = 0;
		$ruleUses = 0;
		foreach ($db->all('SELECT source, event, COUNT(*) AS count FROM assist_events WHERE created_at >= ? AND created_at < ? GROUP BY source, event ORDER BY source, event', $bounds) as $row) {
			$count = (int)$row['count'];
			if ($row['event'] === 'duplicate_warned') {
				$duplicates += $count;
			}
			$field = ['suggestion_shown' => 'shown', 'suggestion_used' => 'used', 'suggestion_dismissed' => 'dismissed'][$row['event']] ?? null;
			if ($field === null) {
				continue;
			}
			$key = $row['source'] ?? '';
			$suggestions[$key] ??= ['source' => $row['source'], 'shown' => 0, 'used' => 0, 'dismissed' => 0];
			$suggestions[$key][$field] = $count;
			if ($field === 'used') {
				$used += $count;
				$ruleUses += $key === 'rule' ? $count : 0;
			}
		}
		$tools = [];
		foreach ($db->all('SELECT tool, status, duration_ms FROM tool_calls WHERE created_at >= ? AND created_at < ? ORDER BY tool, id', $bounds) as $row) {
			$name = $row['tool'];
			$tools[$name] ??= ['tool' => $name, 'calls' => 0, 'errors' => 0, 'durations' => []];
			$tools[$name]['calls']++;
			$tools[$name]['errors'] += in_array($row['status'], ['error', 'denied', 'rate_limited'], true) ? 1 : 0;
			if ($row['duration_ms'] !== null && (int)$row['duration_ms'] >= 0) {
				$tools[$name]['durations'][] = (int)$row['duration_ms'];
			}
		}
		foreach ($tools as &$tool) {
			$tool['error_share'] = $tool['errors'] / $tool['calls'];
			$tool['p50_duration_ms'] = self::percentile($tool['durations'], 0.5);
			$tool['p90_duration_ms'] = self::percentile($tool['durations'], 0.9);
			unset($tool['durations']);
		}
		unset($tool);
		$routines = [];
		foreach ($db->all("SELECT DISTINCT d.inbox_item_id, d.kind, d.event, CASE WHEN d.kind = 'rule.posting' THEN d.actor ELSE '' END AS actor, i.payload FROM decision_log d LEFT JOIN inbox_items i ON i.id = d.inbox_item_id WHERE d.created_at >= ? AND d.created_at < ? AND d.kind IN ('routine.finding', 'rule.posting') AND d.event IN ('proposed', 'applied') ORDER BY d.inbox_item_id", $bounds) as $row) {
			$payload = $row['payload'] ? json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR) : [];
			$name = $row['kind'] === 'routine.finding' ? ($payload['routine'] ?? null) : (str_starts_with($row['actor'], 'rule:') ? substr($row['actor'], 5) : null);
			if (!is_string($name) || $name === '') {
				continue;
			}
			$routines[$name] ??= ['routine' => $name, 'created' => 0, 'acknowledged' => 0, 'rule_proposals' => 0];
			if ($row['kind'] === 'rule.posting') {
				$routines[$name]['rule_proposals'] += $row['event'] === 'proposed' ? 1 : 0;
			} else {
				$routines[$name][$row['event'] === 'proposed' ? 'created' : 'acknowledged']++;
			}
		}
		ksort($routines);
		$assumptions = ['minutes_per_suggestion' => self::minutes($db, 'minutes_per_suggestion', 0.5), 'minutes_per_draft' => self::minutes($db, 'minutes_per_draft', 2.0), 'minutes_per_other_draft' => 1.0];
		$journalDrafts = $kinds['journal.draft_entry']['applied'] ?? 0;
		$otherDrafts = 0;
		foreach (['party.create', 'purchase_order.draft', 'reminder.draft'] as $name) {
			$otherDrafts += $kinds[$name]['applied'] ?? 0;
		}
		return ['from' => $from, 'to' => $to, 'kinds' => array_values($kinds), 'suggestions' => array_values($suggestions), 'duplicates_warned' => $duplicates,
			'tools' => array_values($tools), 'routines' => array_values($routines),
			'rules' => ['active_count' => (int)$db->value("SELECT COUNT(*) FROM posting_rules WHERE status = 'active'"), 'uses' => $ruleUses],
			'estimated_time_saved' => ['minutes' => $used * $assumptions['minutes_per_suggestion'] + $journalDrafts * $assumptions['minutes_per_draft'] + $otherDrafts,
				'measured' => false, 'assumptions' => $assumptions]];
	}
	/** Validate a date or UTC timestamp without relying on the PHP process timezone. */
	private static function bound(string $value, bool $end): string
	{
		$dateOnly = strlen($value) === 10;
		$format = $dateOnly ? '!Y-m-d' : '!Y-m-d H:i:s';
		$normalized = $dateOnly ? $value : str_replace(['T', 'Z'], [' ', ''], $value);
		$date = DateTimeImmutable::createFromFormat($format, $normalized, new DateTimeZone('UTC'));
		if (!$date || $date->format($dateOnly ? 'Y-m-d' : 'Y-m-d H:i:s') !== $normalized) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid report date');
		}
		if ($dateOnly && $end) {
			$date = $date->modify('+1 day');
		}
		return $date->format('Y-m-d H:i:s');
	}
	/** Linearly interpolated percentiles, including an exact median for even populations. */
	private static function percentile(array $values, float $fraction): ?float
	{
		if (!$values) {
			return null;
		}
		sort($values, SORT_NUMERIC);
		$position = (count($values) - 1) * $fraction;
		$lower = (int)floor($position);
		$upper = (int)ceil($position);
		return (float)($values[$lower] + ($values[$upper] - $values[$lower]) * ($position - $lower));
	}
	/** Read non-negative finite assumptions; malformed settings retain the documented defaults. */
	private static function minutes(SaldiDb $db, string $name, float $default): float
	{
		$value = SaldiSettings::get($db, 'assistant', $name, (string)$default);
		return is_numeric($value) && is_finite((float)$value) && (float)$value >= 0 ? (float)$value : $default;
	}
}
