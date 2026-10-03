<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/docFileFunc.php --- ver 5.0.0 --- 2026-10-02 ---
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
// 20261002 CL/SZ Doc pool task 1: Created: helpers for docFile.php, which serves a tenant's documents only to a
//                  logged-in user of that tenant instead of by their direct file path.

if (!function_exists('docFileUrl')) {
	/**
	 * Turns a document path as the viewers build it into a docFile.php URL.
	 *
	 * Two kinds of path are recognised: a file under the tenant's document folder
	 * ("$docFolder/$db/pulje/x.pdf", "$docFolder/$db/finance/30/141/x.pdf") and an XML preview
	 * rendered into the tenant's temp folder ("../temp/$db/xml_view_<md5>.html"). Any other path is
	 * returned unchanged, so a caller never ends up without a document to show. The relative part goes
	 * last in the URL, so the URL still ends in the file's extension for code that checks it.
	 *
	 * @param string $path      Path as built by the viewer, relative to the includes folder.
	 * @param string $docFolder Document root as the viewer resolved it, e.g. '../bilag'.
	 * @param string $db        Tenant database name, from the session.
	 * @return string URL of docFile.php relative to includes/ or finans/, or $path when not recognised.
	 */
	function docFileUrl($path, $docFolder, $db) {
		$original = $path;
		$path = str_replace('//', '/', (string)$path);
		$endpoint = '../includes/docsIncludes/docFile.php';
		$docPrefix = rtrim((string)$docFolder, '/') . '/' . $db . '/';
		if ($db && strpos($path, $docPrefix) === 0) {
			return $endpoint . '?k=doc&f=' . rawurlencode(substr($path, strlen($docPrefix)));
		}
		$tempPrefix = '../temp/' . $db . '/';
		if ($db && strpos($path, $tempPrefix) === 0) {
			return $endpoint . '?k=temp&f=' . rawurlencode(substr($path, strlen($tempPrefix)));
		}
		return $original;
	}
}

if (!function_exists('docFileResolve')) {
	/**
	 * Resolves a requested file inside one root folder, refusing anything that leaves it.
	 *
	 * The file must exist, be a regular file, lie inside $root after symlinks and "../" are resolved,
	 * and have one of the allowed extensions.
	 *
	 * @param string   $root       Folder the file must be inside, e.g. the tenant's document folder.
	 * @param string   $relative   Requested path relative to $root.
	 * @param string[] $extensions Allowed lower-case extensions without the dot.
	 * @return string|null Absolute path of the file, or null when it is missing or not allowed.
	 */
	function docFileResolve($root, $relative, array $extensions) {
		$relative = (string)$relative;
		if ($relative === '' || strpos($relative, "\0") !== false) return null;
		$rootReal = realpath($root);
		if ($rootReal === false || !is_dir($rootReal)) return null;
		$fileReal = realpath($rootReal . '/' . $relative);
		if ($fileReal === false || !is_file($fileReal)) return null;
		if (strpos($fileReal, $rootReal . DIRECTORY_SEPARATOR) !== 0) return null;
		$ext = strtolower(pathinfo($fileReal, PATHINFO_EXTENSION));
		if (!in_array($ext, $extensions, true)) return null;
		return $fileReal;
	}
}

if (!function_exists('docFileContentType')) {
	/**
	 * Content type to serve a document with, by extension.
	 *
	 * @param string $ext Lower-case extension without the dot.
	 * @return string MIME type; application/octet-stream for anything not listed.
	 */
	function docFileContentType($ext) {
		$types = array(
			'pdf'  => 'application/pdf',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'xml'  => 'text/xml; charset=utf-8',
			'html' => 'text/html; charset=utf-8',
		);
		return isset($types[$ext]) ? $types[$ext] : 'application/octet-stream';
	}
}
?>
