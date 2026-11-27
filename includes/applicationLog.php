<?php
// --- includes/applicationLog.php --- 2026-11-27 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261127 CDX/NTR Route application logs to private storage without changing source-tree permissions.
// 20261127 CDX/NTR Reject web-tree aliases and create missing log directories without changing existing modes.
// 20261127 CDX/NTR Resolve logs inside this installation's temp/database folder and check location only.

require_once __DIR__ . '/sharedFile.php';

/**
 * Resolve the loaded installation's database log folder without relying on the request URL or working directory.
 *
 * @param string $tenant Database name; defaults to the active database, then the master database.
 * @return string Absolute database directory, or the temp root when no valid database is known (not writable as a log destination).
 */
function applicationLogDirectory(string $tenant = ''): string
{
    $root = dirname(__DIR__) . '/temp';
    if ($tenant === '') {
        $tenant = (string)($GLOBALS['db'] ?? '');
        if ($tenant === '') {
            $tenant = (string)($GLOBALS['sqdb'] ?? '');
        }
    }
    if ($tenant === '' || $tenant === '.' || $tenant === '..' || basename($tenant) !== $tenant || str_contains($tenant, '\\') || str_contains($tenant, "\0")) {
        return $root;
    }
    $directory = $root . '/' . $tenant;
    applicationLogCreateDirectory($directory);
    return $directory;
}

/**
 * Check location only: a regular log file directly inside this installation's temp/database folder.
 *
 * @param string $path Absolute log path.
 * @return bool Whether the path stays in its database folder, without traversal or symlink redirection.
 */
function applicationLogPathAllowed(string $path): bool
{
    $root = dirname(__DIR__) . '/temp';
    if (!str_starts_with($path, $root . '/') || str_contains($path, "\0") || str_contains($path, '\\')) {
        return false;
    }
    $parts = explode('/', substr($path, strlen($root) + 1));
    if (count($parts) !== 2 || in_array('', $parts, true) || in_array('.', $parts, true) || in_array('..', $parts, true)) {
        return false;
    }
    $canonicalRoot = realpath($root);
    if ($canonicalRoot === false || realpath(dirname($path)) !== $canonicalRoot . '/' . $parts[0] || is_link($path)) {
        return false;
    }
    return !file_exists($path) || is_file($path);
}

/**
 * Create only a missing database directory; never change permissions on an existing directory.
 *
 * @param string $directory Database directory directly below this installation's temp.
 * @return bool Whether that directory exists.
 */
function applicationLogCreateDirectory(string $directory): bool
{
    $root = dirname(__DIR__) . '/temp';
    if (dirname($directory) !== $root || !is_dir($root)) {
        return false;
    }
    if (is_dir($directory)) {
        return true;
    }
    set_error_handler(static function ($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    try {
        $created = mkdir($directory, 02770);
    } catch (ErrorException $error) {
        clearstatcache(true, $directory);
        if (!is_dir($directory)) {
            sharedFileWarning('Could not create application log database directory: ' . $directory);
        }
        return is_dir($directory);
    } finally {
        restore_error_handler();
    }
    $group = sharedFileGroup();
    return $created && $group !== false && sharedFileApplyPermissions($directory, $group);
}

/**
 * Open a location-checked log without checking ancestor permissions or changing existing file modes.
 *
 * @param string $path Absolute log path in this installation's temp/database folder.
 * @param string $mode Append or overwrite mode.
 * @return resource|false Opened stream, or false for an invalid/unwritable destination.
 */
function applicationLogOpen(string $path, string $mode = 'a')
{
    if (!applicationLogPathAllowed($path)) {
        return false;
    }
    if (!file_exists($path)) {
        $created = sharedFilePlainOpen($path, 'x');
        if ($created !== false) {
            $group = sharedFileGroup();
            $ready = $group !== false && sharedFileApplyPermissions($path, $group);
            fclose($created);
            if (!$ready) {
                return false;
            }
        }
    }
    return applicationLogPathAllowed($path) ? sharedFilePlainOpen($path, $mode) : false;
}

/**
 * Append database log lines with the installation/database location policy.
 * db_query.php loads this module before defining its shared line writer.
 *
 * @see db_log_append()
 *
 * @param string $path Absolute database log path.
 * @param string|string[] $lines Log contents.
 * @param string $mode Append or overwrite mode.
 * @return bool Whether all lines were written.
 */
function applicationLogAppend(string $path, $lines, string $mode = 'a'): bool
{
    return db_log_append($path, $lines, $mode, __NAMESPACE__ . '\\applicationLogOpen');
}

/**
 * Write a database-scoped log; no logs are written directly in temp or outside this installation.
 *
 * @param string $filename Log filename without directory components.
 * @param string $contents Log entry.
 * @param bool $append Append instead of replacing contents.
 * @param string $tenant Database name, or the active/master database when omitted.
 * @return int|false Bytes written, or false when the location is invalid or unavailable.
 */
function applicationLogPutContents(string $filename, string $contents, bool $append = true, string $tenant = '')
{
    $path = applicationLogDirectory($tenant) . '/' . $filename;
    $stream = applicationLogOpen($path, $append ? 'a' : 'w');
    if ($stream === false) {
        return false;
    }
    try {
        $written = 0;
        while ($written < strlen($contents)) {
            $count = fwrite($stream, substr($contents, $written));
            if ($count === false || $count === 0) {
                return false;
            }
            $written += $count;
        }
        return $written;
    } finally {
        fclose($stream);
    }
}
