<?php
// --- includes/docsIncludes/poolPaths.php ---
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
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260925 LOE MB-42 The document pool's folder is one of three layouts per installation - ownCloud,
//                  a local bilag folder or a custom documents folder - and the codebase detects it
//                  with the same chain in includes/documents.php, includes/docsIncludes/insertDoc.php
//                  and includes/vis_bilag.php. Code that has to find a tenant's pulje folder from a
//                  place without that variable (the login-time backfill in betweenUpdates.php, for
//                  one) was hardcoding bilag, which silently found nothing on an ownCloud or
//                  custom-documents installation. Resolved absolute off the installation root, so it
//                  does not depend on the caller's working directory either.

if (!function_exists('poolDocFolderName')) {
	/**
	 * Which document folder does this installation use?
	 *
	 * Same order as includes/docsIncludes/insertDoc.php: owncloud, then bilag, then documents, and
	 * bilag as the fallback every other place uses when none of them exists.
	 *
	 * @param string|null $root Installation root; defaults to this file's installation.
	 * @return string 'owncloud', 'bilag' or 'documents'
	 */
	function poolDocFolderName($root = null)
	{
		if ($root === null) {
			// includes/docsIncludes/ -> the installation root one level above includes/
			$root = dirname(__DIR__, 2);
		}
		$root = rtrim($root, '/');
		foreach (array('owncloud', 'bilag', 'documents') as $candidate) {
			if (is_dir($root . '/' . $candidate)) {
				return $candidate;
			}
		}
		return 'bilag';
	}
}

if (!function_exists('poolPuljePath')) {
	/**
	 * Absolute path to one tenant's pulje folder, e.g. /install/owncloud/tenant_db/pulje.
	 *
	 * @param string $db   Tenant database name.
	 * @param string|null $root Installation root; defaults to this file's installation.
	 * @return string Path without a trailing slash.
	 */
	function poolPuljePath($db, $root = null)
	{
		if ($root === null) {
			$root = dirname(__DIR__, 2);
		}
		return rtrim($root, '/') . '/' . poolDocFolderName($root) . '/' . $db . '/pulje';
	}
}
