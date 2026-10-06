<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/viewOnlyDocs.php --- ver 5.0.0 --- 2026-10-02 ---
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
// 20261002 CL/SZ SD-701 Created: the documents attached to one source line, for the view-only voucher tab.

if (!function_exists('viewOnlyDocs')) {
	/**
	 * Lists the documents attached to one source line, oldest first, with the path the viewer shows.
	 *
	 * The path is built the same way as in listDocs.php, so a showDoc value from a link on that page
	 * matches one of the returned paths.
	 *
	 * @param string $source    Source type, e.g. 'kassekladde'.
	 * @param int    $sourceId  Id of the source line.
	 * @param string $docFolder Document root, e.g. '../bilag'.
	 * @param string $db        Tenant database name, from the session.
	 * @return array<int, array{
	 *   id: int,        documents.id.
	 *   filename: string, Stored file name.
	 *   path: string,   Path of the file relative to the includes folder.
	 * }>
	 */
	function viewOnlyDocs($source, $sourceId, $docFolder, $db) {
		$docs = array();
		$qtxt = "select id, filename, filepath from documents where source = '" . db_escape_string($source) . "'";
		$qtxt.= " and source_id = '" . intval($sourceId) . "' order by id";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($q && $r = db_fetch_array($q)) {
			$path = rtrim($docFolder, '/') . '/' . $db . '/' . ltrim($r['filepath'], '/') . '/' . $r['filename'];
			$docs[] = array(
				'id'       => (int)$r['id'],
				'filename' => $r['filename'],
				'path'     => str_replace('//', '/', $path),
			);
		}
		return $docs;
	}
}
?>
