<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/planUpdatesRunner.php --- ai/plan --- 2026.09.25
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
// Step runner for includes/planUpdates.php (doc/ai-plan/CONVENTIONS.md §6). Kept apart
// from the step list so tests can load it without running any steps.
//
// 20260925 CL/LH Created.

if (!function_exists('plan_updates_run')) {
	/**
	 * Applies every step after the stored watermark, in order, and advances the
	 * watermark after each one. A failing step stops the run without advancing, so
	 * it is retried at the next login; the login itself carries on.
	 *
	 * @param array<string, callable(string): void> $steps  step name => step(dbType)
	 * @param string $dbType  'postgresql', 'mysql' or 'mysqli'
	 * @param callable(string): (array{var_value: string}|false) $selectOne  runs a select, returns the first row
	 * @param callable(string): void $modify  runs a write
	 * @return array{applied: list<string>, failed: string|null}
	 */
	function plan_updates_run(array $steps, string $dbType, callable $selectOne, callable $modify): array
	{
		$result = ['applied' => [], 'failed' => null];
		$names = array_keys($steps);
		if ($names === []) {
			return $result;
		}
		$row = $selectOne("SELECT var_value FROM settings WHERE var_name = 'plan_updates_last_step' AND var_grp = 'system' ORDER BY id DESC LIMIT 1");
		$last = $row ? (string) $row['var_value'] : null;
		if ($last === end($names)) {
			return $result;
		}
		// An unknown watermark (no row yet, or a name not in the list) replays every step; they are idempotent.
		$position = $last === null ? false : array_search($last, $names, true);
		$start = $position === false ? 0 : $position + 1;
		for ($i = $start; $i < count($names); $i++) {
			$name = $names[$i];
			try {
				$steps[$name]($dbType);
			} catch (\Throwable $e) {
				error_log("planUpdates: step $name failed: " . $e->getMessage());
				$result['failed'] = $name;
				return $result;
			}
			if ($row) {
				$modify("UPDATE settings SET var_value = '$name' WHERE var_name = 'plan_updates_last_step' AND var_grp = 'system'");
			} else {
				$modify("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('plan_updates_last_step', 'system', '$name', 'Last applied ai/plan schema step (includes/planUpdates.php)')");
				$row = ['var_value' => $name];
			}
			$result['applied'][] = $name;
		}
		return $result;
	}
}
