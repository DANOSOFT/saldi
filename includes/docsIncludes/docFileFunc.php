<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/docFileFunc.php --- ver 5.1.1 --- 2026-11-10 ---
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
// 20261003 CL/SZ SD-723 Created: helpers for docFile.php, which serves a tenant's documents only to a logged-in user of that tenant instead of by their direct file path.
// 20261005 CL/SZ SD-723 docFileMissingBox(): the message shown instead of a document whose file is not on the server.
// 20261110 CDX/ASR Map document folders to their owning module after normalizing path segments.
// 20261110 CDX/ASR Bind XML previews to their original document and the logged-in tenant session.

/**
 * Determines the owning module from a path below the tenant's document root.
 * Pool files are shared by the three invoice modules; previews need their registered original.
 * Call again with the resolved canonical path before serving a file, so symlinks
 * cannot turn a permitted customer path into a finance document.
 *
 * @param string $kind doc or temp.
 * @param string $relative Path below the tenant root, without the tenant name.
 * @return string|null Permission key/OR-list, or null for an unknown location.
 */
function docFilePermissionKey(string $kind, string $relative): ?string
{
    if ($kind !== 'doc' || strpos($relative, "\0") !== false || str_starts_with($relative, '/')) {
        return null;
    }
    $segments = [];
    foreach (explode('/', str_replace('\\', '/', $relative)) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            if (!$segments) {
                return null;
            }
            array_pop($segments);
        } else {
            $segments[] = $segment;
        }
    }
    $folder = $segments[0] ?? '';
    if ($folder === 'finance' || $folder === 'bilag') {
        return 'finans.kassekladde';
    }
    if ($folder === 'debitor') {
        return ($segments[1] ?? '') === 'orders' ? 'debitor.ordre' : 'debitor.konti';
    }
    if ($folder === 'creditor' || $folder === 'kreditor') {
        return ($segments[1] ?? '') === 'orders' ? 'kreditor.ordre' : 'kreditor.konti';
    }
    if ($folder === 'pulje') {
        return 'finans.kassekladde|debitor.ordre|kreditor.ordre';
    }
    return null;
}

/**
 * Resolves the document root used by the legacy viewers independently of the caller's directory.
 *
 * @param string $folder Configured document folder, absolute or relative to includes/.
 * @param string $tenant Tenant database name from the authenticated session.
 * @return string Absolute tenant root (which need not exist yet).
 */
function docFileTenantRoot(string $folder, string $tenant): string
{
    return (str_starts_with($folder, '/') ? rtrim($folder, '/') : dirname(__DIR__) . '/' . rtrim($folder, '/')) . '/' . $tenant;
}

/**
 * Resolves an original document and its actual owning module before reading its contents.
 *
 * @param string $tenantRoot Trusted document-root/tenant path.
 * @param string $requested Absolute or legacy includes-relative document path.
 * @return array{path: string, relative: string, key: string}|null Valid canonical document.
 */
function docFileReadTarget(string $tenantRoot, string $requested): ?array
{
    require_once __DIR__ . '/documentAccess.php';
    $path = docAccessCanonicalPath($tenantRoot, $requested);
    if ($path === null) {
        return null;
    }
    $relative = substr($path, strlen((string)realpath($tenantRoot)) + 1);
    $key = docFilePermissionKey('doc', $relative);
    return $key === null ? null : ['path' => $path, 'relative' => $relative, 'key' => $key];
}

/**
 * Registers a rendered XML preview only after its original has passed the viewer's access gate.
 * Both content hashes bind the cached rendering to this original and reject later file replacement.
 *
 * @param string $tenant Authenticated tenant name.
 * @param string $tenantRoot Trusted document-root/tenant path.
 * @param string $source Original XML path.
 * @param string $preview Existing generated HTML path.
 * @return bool Whether the preview was registered in the current session.
 */
function docFileRegisterPreview(string $tenant, string $tenantRoot, string $source, string $preview): bool
{
    $target = docFileReadTarget($tenantRoot, $source);
    $name = basename($preview);
    if ($target === null || strtolower(pathinfo($target['path'], PATHINFO_EXTENSION)) !== 'xml'
        || !preg_match('/^xml_(view|preview)_[0-9a-f]{32}\.html$/D', $name) || !is_file($preview) || is_link($preview)) {
        return false;
    }
    $sourceHash = hash_file('sha256', $target['path']);
    $previewHash = hash_file('sha256', $preview);
    if ($sourceHash === false || $previewHash === false) {
        return false;
    }
    $_SESSION['doc_file_previews'][$tenant][$name] = [
        'source' => $target['relative'], 'sourceHash' => $sourceHash, 'previewHash' => $previewHash,
    ];
    while (count($_SESSION['doc_file_previews'][$tenant]) > 128) {
        array_shift($_SESSION['doc_file_previews'][$tenant]);
    }
    return true;
}

/**
 * Revalidates a session's preview against the current original; callers must enforce its returned key.
 * Unregistered predictable cache names, another tenant, moved files and changed contents fail closed.
 *
 * @param string $tenant Authenticated tenant name.
 * @param string $tenantRoot Trusted document-root/tenant path.
 * @param string $tempRoot Trusted tenant temp folder.
 * @param string $name Requested preview basename.
 * @return array{path: string, key: string}|null Preview and the original's current permission key.
 */
function docFilePreviewTarget(string $tenant, string $tenantRoot, string $tempRoot, string $name): ?array
{
    if (!preg_match('/^xml_(view|preview)_[0-9a-f]{32}\.html$/D', $name)) {
        return null;
    }
    $entry = $_SESSION['doc_file_previews'][$tenant][$name] ?? null;
    if (!is_array($entry) || !isset($entry['source'], $entry['sourceHash'], $entry['previewHash'])) {
        return null;
    }
    $source = docFileReadTarget($tenantRoot, $tenantRoot . '/' . $entry['source']);
    $preview = docFileResolve($tempRoot, $name, ['html']);
    if ($source === null || $source['relative'] !== $entry['source'] || $preview === null
        || is_link(rtrim($tempRoot, '/') . '/' . $name)
        || !hash_equals($entry['sourceHash'], (string)hash_file('sha256', $source['path']))
        || !hash_equals($entry['previewHash'], (string)hash_file('sha256', $preview))) {
        return null;
    }
    return ['path' => $preview, 'key' => $source['key']];
}


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

if (!function_exists('docFileMissingBox')) {
	/**
	 * The message shown in place of a document whose file is not on the server.
	 *
	 * The file can be missing (deleted, moved, or a copy of the data without its documents), or the
	 * session can have moved to another company in another tab, so the file is looked for in that company's folder.
	 *
	 * @param string $fileName Name of the missing file, shown to the user; '' leaves it out.
	 * @param int    $sprog_id Language id for findtekst().
	 * @return string HTML of the message.
	 */
	function docFileMissingBox($fileName, $sprog_id) {
		$html = "<div style='height:auto;max-width:520px;margin:40px auto;padding:16px 20px;border:1px solid #f0c36d;border-radius:6px;";
		$html.= "background-color:#fff8e1;color:#5c4400;font-family:Helvetica,Arial,sans-serif;font-size:14px;line-height:1.5;text-align:left'>";
		$html.= "<b>" . htmlspecialchars(findtekst('5405|Bilaget findes ikke på serveren', $sprog_id), ENT_QUOTES) . "</b>";
		if ($fileName !== '') $html.= "<br>" . htmlspecialchars($fileName, ENT_QUOTES);
		$html.= "<p style='margin:8px 0 0'>" . htmlspecialchars(findtekst('5406|Filen kan være slettet eller flyttet. Har du åbnet et andet regnskab i en anden fane, så genindlæs siden.', $sprog_id), ENT_QUOTES) . "</p>";
		$html.= "</div>";
		return $html;
	}
}
?>
