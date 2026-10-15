<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiInterruptNotifier.php --- ver 5.0.0 --- 2026.10.08
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
// 20261008 CL/LH Created for the mobile Assist contract.

declare(strict_types=1);

final class SaldiInterruptNotifier
{
	/** Stage exactly one interrupt in the creation transaction; delivery cannot outlive a rolled-back item. */
	public static function notify(SaldiDb $db, ?SaldiTenantContext $ctx, array $item): void
	{
		try {
			if (ifset($item, 'attention') !== 'interrupt' || !$db->isPostgres()) {
				return;
			}
			foreach (['notifications', 'notification_recipients', 'push_dispatch', 'mobile_events', 'notification_preferences'] as $table) {
				if (!$db->hasTable($table)) {
					return;
				}
			}
			$db->transaction(static function () use ($db, $ctx, $item): void {
				$language = $ctx === null ? 1 : $ctx->languageId;
				$db->insertIgnore('mobile_events', ['type' => 'inbox_interrupt', 'dedupe_key' => 'assist:interrupt:' . (int)$item['id'],
					'ref_type' => 'inbox_item', 'ref_id' => (int)$item['id'],
					'payload' => saldi_runtime_json(['created_by' => 'assistant',
						'title' => SaldiText::get($db, 6431, $language, 'Saldi Assist har et nyt fund'),
						'message' => SaldiText::get($db, 6432, $language, 'Åbn Opgaver for at se det.')])], ['dedupe_key']);
			});
		} catch (Throwable $error) {
			error_log('Saldi interrupt notification unavailable: ' . get_class($error));
		}
	}
	/** Best-effort immediate delivery after commit; nested creations remain in the cron's atomic outbox. */
	public static function flush(SaldiDb $db, ?SaldiTenantContext $ctx, ?int $itemId = null): void
	{
		$tenant = null;
		try {
			if ($ctx === null || $db->inTransaction() || !$db->hasTable('mobile_events')) {
				return;
			}
			require_once __DIR__ . '/../../restapi/services/MobileEventFanout.php';
			$cfg = saldi_runtime_config();
			$quote = static fn(string $value): string => "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
			$tenant = pg_connect('connect_timeout=3 host=' . $quote($cfg['host']) . ' port=' . (int)ifset($cfg, 'port', 5432)
				. ' dbname=' . $quote($db->name()) . ' user=' . $quote($cfg['user']) . ' password=' . $quote($cfg['password']), PGSQL_CONNECT_FORCE_NEW);
			if (!$tenant) {
				throw new RuntimeException('Notification connection unavailable');
			}
			$store = new MobileSchema($tenant);
			$store->execute("SET statement_timeout = '5000ms'");
			$store->execute("SET lock_timeout = '1000ms'");
			$store->execute('SET jit = off');
			if ($itemId !== null) {
				(new MobileEventFanout($tenant, MobileAuth::connectRegistry(), $ctx->regnskabId))->interruptAfterCommit($itemId);
			}
		} catch (Throwable $error) {
			error_log('Saldi interrupt delivery deferred: ' . get_class($error));
		} finally {
			if ($tenant instanceof \PgSql\Connection) {
				pg_close($tenant);
			}
		}
	}
}
