<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiAttention.php --- ai/mcp-assistant --- 2026.10.02
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
// Serialize interrupt allocation per recipient over a Copenhagen calendar day.
// The caller's creation transaction retains the lock until the item is stored.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';

final class SaldiAttention
{
	/** @return array{0:string,1:string} UTC bounds, including DST-short and DST-long days. */
	public static function dayBounds(string $date): array
	{
		$start = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Copenhagen'));
		if (!$start || $start->format('Y-m-d') !== $date) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid attention date');
		}
		return [$start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), $start->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
	}
	/** Grant interrupts only below the tenant's daily, recipient-scoped budget. */
	public static function decide(SaldiDb $db, ?int $ownerUserId, ?string $assigneePermission, string $requested): string
	{
		if ($requested !== 'interrupt') {
			return $requested;
		}
		return $db->transaction(static function () use ($db, $ownerUserId, $assigneePermission): string {
			$recipient = $ownerUserId === null ? 'permission:' . $assigneePermission : 'owner:' . $ownerUserId;
			$db->lock('saldi_attention:' . $recipient);
			$budget = max(0, min(10, (int)SaldiSettings::get($db, 'assistant', 'interrupt_budget', '3')));
			$today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
			[$start, $end] = self::dayBounds($today);
			$where = $ownerUserId === null ? "owner_user_id IS NULL AND visibility = 'permission' AND assignee_permission = ?" : 'owner_user_id = ?';
			$count = (int)$db->value("SELECT COUNT(*) FROM inbox_items WHERE attention = 'interrupt' AND created_at >= ? AND created_at < ? AND " . $where, [$start, $end, $ownerUserId ?? $assigneePermission]);
			return $count < $budget ? 'interrupt' : 'ambient';
		});
	}
}
