<?php
// ---- includes/restorePending.php --- ver 5.1.1 --- 2026.11.13 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261104 CL/ASR Created: the clean-up of the two-step restore's work folders (admin/restore.php), shared with admin/backup.php and index/logud.php.
//                 An upload that is never confirmed holds a full copy of the ledger, so it is removed when its 30 minutes have passed on the next visit to any of the three pages, and at once when its user logs out.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
if (!defined('RESTORE_PENDING_TTL')) define('RESTORE_PENDING_TTL', 1800); // a pending upload must be confirmed within 30 minutes

if (!function_exists('restore_pending_folder_ok')) {
	/**
	 * Whether $dir is a work folder of the two-step restore: directly in the system temp folder, not a link,
	 * named saldi_restore_<db>_<32 hex> (pending) or, with $running, saldi_restoring_<db>_<32 hex> (a restore that runs).
	 */
	function restore_pending_folder_ok($dir, $running = false) {
		$tmp = realpath(sys_get_temp_dir());
		$dir = (string) $dir;
		if (!$tmp || $dir === '' || is_link($dir) || !is_dir($dir)) return false;
		$real = realpath($dir);
		$pattern = $running ? '/^saldi_restor(e|ing)_[A-Za-z0-9_]+_[0-9a-f]{32}$/' : '/^saldi_restore_[A-Za-z0-9_]+_[0-9a-f]{32}$/';
		return $real && $real === $dir && dirname($real) === $tmp && preg_match($pattern, basename($real));
	}
}

if (!function_exists('restore_pending_sweep')) {
	/**
	 * Removes pending work folders older than 30 minutes, and folders of a restore that never ended after a day.
	 */
	function restore_pending_sweep() {
		$tmp = realpath(sys_get_temp_dir());
		if (!$tmp) return;
		foreach ((array) glob($tmp . '/saldi_restor*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
			$dir = (string) $dir;
			if (!restore_pending_folder_ok($dir, true)) continue;
			$limit = (strpos(basename($dir), 'saldi_restoring_') === 0) ? 86400 : RESTORE_PENDING_TTL;
			$mtime = (int) @filemtime($dir);
			if ($mtime > 0 && $mtime < time() - $limit) system("rm -rf " . escapeshellarg($dir));
		}
	}
}

if (!function_exists('restore_pending_drop_session')) {
	/**
	 * At logout: removes the pending uploads of this session (every ledger) and their session entries.
	 */
	function restore_pending_drop_session() {
		if (empty($_SESSION['restore_pending']) || !is_array($_SESSION['restore_pending'])) return;
		foreach ($_SESSION['restore_pending'] as $entry) {
			$dir = is_array($entry) && isset($entry['dir']) ? (string) $entry['dir'] : '';
			if ($dir !== '' && restore_pending_folder_ok($dir)) system("rm -rf " . escapeshellarg($dir));
		}
		unset($_SESSION['restore_pending']);
	}
}
