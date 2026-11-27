<?php
// --- includes/applicationLog.php --- 2026-11-27 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261127 CDX/NTR Route application logs to private storage without changing source-tree permissions.
// 20261127 CDX/NTR Reject web-tree aliases and create missing log directories without changing existing modes.

require_once __DIR__ . '/sharedFile.php';

/**
 * Locate private logs, creating only missing directories under trusted writable parents.
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
    if (applicationLogOutsideWebTree($root)) {
        applicationLogCreateDirectory($root);
    }
    if ($tenant === '') {
        return $root;
    }
    $directory = $root . '/tenant-' . rawurlencode($tenant);
    if (!is_dir($directory) && applicationLogTrustedPath($root . '/.log-directory-check')) {
        applicationLogCreateDirectory($directory);
    }
    return $directory;
}

/**
 * Resolve existing symlinks and reject storage in the installation or a known document root.
 *
 * @param string $path Absolute log-directory path, possibly not created yet.
 * @return bool Whether its canonical destination is outside public application storage.
 */
function applicationLogOutsideWebTree(string $path): bool
{
    $suffix = '';
    $ancestor = $path;
    while (($resolved = realpath($ancestor)) === false && dirname($ancestor) !== $ancestor) {
        $suffix = '/' . basename($ancestor) . $suffix;
        $ancestor = dirname($ancestor);
    }
    if ($resolved === false || in_array('..', explode('/', $suffix), true)) {
        return false;
    }
    $canonical = rtrim($resolved, '/') . $suffix;
    foreach (array(dirname(__DIR__), $_SERVER['DOCUMENT_ROOT'] ?? '') as $publicRoot) {
        if ($publicRoot === '' || ($publicRoot = realpath($publicRoot)) === false) {
            continue;
        }
        $publicRoot = rtrim($publicRoot, '/');
        if ($canonical === $publicRoot || str_starts_with($canonical, $publicRoot . '/')) {
            sharedFileWarning('Application logs must be outside the web tree; configure SALDI_LOG_DIR');
            return false;
        }
    }
    return true;
}

/**
 * Create a single new log directory; existing directories, including concurrent creations, keep their modes.
 *
 * @param string $directory Missing directory under a trusted, writable existing parent.
 * @return bool Whether the directory exists after this attempt.
 */
function applicationLogCreateDirectory(string $directory): bool
{
    if (is_dir($directory)) {
        return true;
    }
    if (!is_dir(dirname($directory)) || !is_writable(dirname($directory)) || !sharedFileTrustedParent($directory)) {
        return false;
    }
    set_error_handler(static function ($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    try {
        $created = mkdir($directory, 02770);
    } catch (ErrorException $error) {
        if (!is_dir($directory)) {
            sharedFileWarning('Could not create private application log directory: ' . $directory);
        }
        return is_dir($directory);
    } finally {
        restore_error_handler();
    }
    if ($created) {
        return sharedFileApplyPermissions($directory, sharedFileGroup());
    }
    return false;
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
        || !applicationLogOutsideWebTree($root)
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
