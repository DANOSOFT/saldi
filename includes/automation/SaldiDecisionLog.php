<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiDecisionLog.php --- ai/mcp-assistant --- 2026.10.03
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
// Append-only audit events with atomic idempotency.
//
// 20261001 CDX/LH Created.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.

declare(strict_types=1);

final class SaldiDecisionLog
{
	/** Append only; duplicate idempotency keys return the original event id. */
	public static function append(SaldiDb $db, array $row): int
	{
		$events = ['proposed', 'revised', 'applied', 'rejected', 'expired', 'cancelled', 'apply_failed', 'undone', 'memory_retracted', 'grant_issued', 'grant_revoked', 'rule_retired'];
		if (!in_array($row['event'] ?? null, $events, true)) {
			throw new InvalidArgumentException('Unknown decision event');
		}
		$allowed = ['event', 'inbox_item_id', 'kind', 'subject_type', 'subject_id', 'actor', 'on_behalf_of', 'approved_by', 'grant_id', 'idempotency_key', 'engine', 'model', 'cost_usd', 'detail', 'undo_ref'];
		$row = array_intersect_key($row, array_flip($allowed));
		$row['created_at'] = gmdate('Y-m-d H:i:s');
		if (isset($row['detail']) && !is_string($row['detail'])) {
			$row['detail'] = saldi_runtime_json($row['detail']);
		}
		$id = $db->insertIgnore('decision_log', $row, ['idempotency_key']);
		if ($id !== null) {
			return $id;
		}
		return (int)$db->value('SELECT id FROM decision_log WHERE idempotency_key = ?', [$row['idempotency_key']]);
	}
}
