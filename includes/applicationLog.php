<?php
// --- includes/applicationLog.php --- 2026-11-27 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261127 CDX/NTR Route application logs to private storage without changing source-tree permissions.

require_once __DIR__ . '/sharedFile.php';

/**
 * Locate private logs. An administrator provisions the root; only new tenant directories are created here.
 *
 * @param string $tenant Database name, or empty for installation-wide logs.
 * @return string Absolute directory; callers must validate it before writing.
 */
function applicationLogDirectory(string $tenant = ''): string
{
    $configured = getenv('SALDI_LOG_DIR');
    $root = rtrim($configured !== false && $configured !== '' ? $configured : '/var/log/saldi/' . basename(dirname(__DIR__)), '/');
    if ($root === '' || $root[0] !== '/' || str_contains($root, "\0")) {
        sharedFileWarning('SALDI_LOG_DIR must be an absolute private directory; follow doc/runtime-permissions.md');
        $root = '/var/log/saldi/' . basename(dirname(__DIR__));
    }
    if ($tenant === '') {
        return $root;
    }
    $directory = $root . '/tenant-' . rawurlencode($tenant);
    if (!is_dir($directory) && applicationLogTrustedPath($root . '/.log-directory-check')) {
        sharedDirectory($directory);
    }
    return $directory;
}

/**
 * Require a provisioned, trusted log directory. There is no fallback through an unsafe parent.
 *
 * @param string $path Log file under the configured root.
 * @return bool Whether a writer may use the path without weakening parent permissions.
 */
function applicationLogTrustedPath(string $path): bool
{
    $root = applicationLogDirectory();
    if (!str_starts_with($path, $root . '/')
        || in_array('..', explode('/', substr($path, strlen($root) + 1)), true)
        || !sharedFileTrustedParent($path)) {
        sharedFileWarning('Private application log directory is unavailable; provision SALDI_LOG_DIR as described in doc/runtime-permissions.md');
        return false;
    }
    return true;
}

/**
 * Write an installation-wide or tenant log outside the application tree.
 *
 * @param string $filename A filename without directory components.
 * @param string $contents Log entry.
 * @param bool $append Append instead of replacing the log.
 * @param string $tenant Database name, or empty for installation-wide logs.
 * @return int|false Bytes written, or false when storage is unavailable or unsafe.
 */
function applicationLogPutContents(string $filename, string $contents, bool $append = true, string $tenant = '')
{
    if ($filename === '' || $filename === '.' || $filename === '..' || basename($filename) !== $filename || str_contains($filename, "\0")) {
        return false;
    }
    $path = applicationLogDirectory($tenant) . '/' . $filename;
    if (!applicationLogTrustedPath($path)) {
        return false;
    }
    return sharedFilePutContents($path, $contents, $append);
}
