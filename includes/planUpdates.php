<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/planUpdates.php --- ai/plan --- 2026.10.01
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
// Schema and data steps for the ai/plan integration branch. Included once, near
// the top of includes/betweenUpdates.php (doc/ai-plan/CONVENTIONS.md §6).
// Each step is named, ordered and idempotent. The name of the last applied step
// is stored in settings (var_grp 'system', var_name 'plan_updates_last_step'), so
// a tenant that is up to date costs one select per login.
// Steps are never edited after they merge; a correction is a new step.
//
// 20260925 CL/LH Created with the step runner and no steps.
// 20261001 CDX/LH Share runtime schema steps and serialize login migrations.
// 20261002 CL/LH Run each login step in its own transaction on PostgreSQL.

include_once(__DIR__ . '/planUpdatesRunner.php');
include_once(__DIR__ . '/planUpdatesSteps.php');

/**
 * Injected by includes/connect.php through the login update path:
 * @var string $db_type
 */
$planSelect = static function (string $sql) {
	return db_fetch_array(db_select($sql, __FILE__ . ' linje ' . __LINE__));
};
$planModify = static function (string $sql): void {
	$previous = $GLOBALS['webservice'] ?? null;
	$GLOBALS['webservice'] = true;
	try {
		$result = db_modify($sql, __FILE__ . ' linje ' . __LINE__);
		if (!str_starts_with((string)$result, '0')) {
			throw new RuntimeException('Plan update write failed');
		}
	} finally {
		$GLOBALS['webservice'] = $previous;
	}
};
$planPg = !in_array($db_type, ['mysql', 'mysqli'], true);
$planLocked = false;
try {
	// The runner retains the one-select fast path for up-to-date tenants.
	$planRawSteps = saldi_plan_update_steps();
	$planNames = array_keys($planRawSteps);
	$planWatermark = $planSelect("SELECT var_value FROM settings WHERE var_name = 'plan_updates_last_step' AND var_grp = 'system' ORDER BY id DESC LIMIT 1");
	if (!$planWatermark || $planWatermark['var_value'] !== end($planNames)) {
		$planLock = $planSelect($planPg ? "SELECT pg_try_advisory_lock(hashtext('saldi_plan_updates')) AS acquired" : "SELECT GET_LOCK('saldi_plan_updates', 0) AS acquired");
		$planLocked = in_array($planLock['acquired'] ?? null, [true, 1, '1', 't'], true);
		if ($planLocked) {
			$planSteps = [];
			foreach ($planRawSteps as $name => $step) {
				// PostgreSQL DDL is transactional: a step either lands whole or not at all.
				$planSteps[$name] = static function (string $type) use ($step, $planModify, $planSelect, $planPg): void {
					if (!$planPg) {
						$step($type, $planModify, $planSelect);
						return;
					}
					$planModify('BEGIN');
					try {
						$step($type, $planModify, $planSelect);
						$planModify('COMMIT');
					} catch (Throwable $e) {
						try {
							$planModify('ROLLBACK');
						} catch (Throwable $rollback) {
							error_log('planUpdates: rollback failed');
						}
						throw $e;
					}
				};
			}
			plan_updates_run($planSteps, $db_type, $planSelect, $planModify);
		}
	}
} catch (Throwable $e) {
	error_log('planUpdates: ' . $e->getMessage());
} finally {
	if ($planLocked) {
		$planSelect($planPg ? "SELECT pg_advisory_unlock(hashtext('saldi_plan_updates'))" : "SELECT RELEASE_LOCK('saldi_plan_updates')");
	}
}
