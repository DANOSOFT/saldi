<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- cron/automation_worker.php --- ai/mcp-assistant --- 2026.10.01
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
// CLI-only tenant worker; never runs schema migrations.
//
// 20261001 CDX/LH Created.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit;
}
chdir(dirname(__DIR__));
if (getenv('SALDI_AUTOMATION_DISABLED') === '1') {
	echo "Automation disabled\n";
	exit(0);
}
require_once __DIR__ . '/../includes/automation/SaldiJobRunner.php';

/** Run the worker CLI. @return int Process exit status. */
function saldi_automation_worker(array $argv): int
{
	$once = false;
	$dry = false;
	$tenant = null;
	$limit = 50;
	foreach (array_slice($argv, 1) as $arg) {
		if ($arg === '--help') {
			echo "Usage: php cron/automation_worker.php [--once] [--tenant=<db>] [--limit=1..500] [--dry-run] [--help]\n";
			return 0;
		} elseif ($arg === '--once') {
			$once = true;
		} elseif ($arg === '--dry-run') {
			$dry = true;
		} elseif (preg_match('/^--tenant=([A-Za-z0-9_-]{1,63})$/D', $arg, $m)) {
			$tenant = $m[1];
		} elseif (preg_match('/^--limit=([0-9]+)$/D', $arg, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 500) {
			$limit = (int)$m[1];
		} else {
			fwrite(STDERR, "Invalid worker option\n");
			return 1;
		}
	}
	$logged = [];
	$registry = SaldiJobRegistry::default();
	$registry->bindPermissions();
	do {
		$failed = false;
		try {
			$cfg = saldi_runtime_config();
			$rows = saldi_runtime_master()->all("SELECT id, db FROM regnskab WHERE id <> 1 AND COALESCE(lukket, '') <> 'on'" . ($tenant === null ? '' : ' AND db = ?') . ' ORDER BY id', $tenant === null ? [] : [$tenant]);
		} catch (Throwable $e) {
			fwrite(STDERR, "Worker master unavailable\n");
			return 1;
		}
		foreach ($rows as $row) {
			$db = null;
			try {
				$entry = saldi_runtime_tenant((int)$row['id']);
				$db = $entry['db'];
				if (!$db->hasTable('automation_jobs') || !$db->hasTable('inbox_items')) {
					continue;
				}
				$runner = new SaldiJobRunner($db, $registry);
				if (!$runner->hasWork() || $dry) {
					continue;
				}
				(new SaldiInboxService($db, SaldiKindRegistry::default()))->expireDue();
				$runner->enqueue();
				$year = (int)$db->value("SELECT kodenr FROM grupper WHERE art = 'RA' AND box5 = 'on' AND COALESCE(box10, '') <> 'on' ORDER BY box2 DESC, box1 DESC, id DESC LIMIT 1");
				$currency = (string)($db->value("SELECT var_value FROM settings WHERE var_name = 'baseCurrency' AND COALESCE(user_id, 0) = 0 AND COALESCE(pos_id, 0) = 0 ORDER BY id DESC LIMIT 1") ?? 'DKK');
				$ctx = SaldiTenantContext::forSystem($cfg['install_id'], (int)$row['id'], $db->name(), $db->dbType(), 'system:worker', $year, $currency);
				for ($i = 0; $i < $limit && ($job = $runner->claim()) !== null; $i++) {
					$start = hrtime(true);
					$result = $runner->run($ctx, $job);
					$failed = $failed || $result['status'] === 'failed';
					echo implode("\t", [$db->name(), $job['job_type'], $job['id'], $result['status'], (int)((hrtime(true) - $start) / 1000000)]) . "\n";
				}
			} catch (Throwable $e) {
				$failed = true;
				if ($once || ($logged[$row['db']] ?? 0) <= time() - 3600) {
					error_log('Automation tenant unavailable: ' . $row['db']);
					$logged[$row['db']] = time();
				}
			} finally {
				if ($db !== null) {
					$db->close();
				}
			}
		}
		if ($once) {
			return $failed ? 1 : 0;
		}
		sleep(60);
	} while (true);
}
exit(saldi_automation_worker($argv));
