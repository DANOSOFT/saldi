<?php
// 20261029 CDX/NTR Share temporary files between CLI jobs and the web server.

/**
 * Apply explicit permissions to a local shared file or directory, ignoring umask.
 * Only use for shared runtime data: files become 0666 and directories 0777,
 * allowing www-data access even when the creator cannot chgrp to www-data.
 * Symlinks are rejected so their targets cannot have permissions widened.
 *
 * @param string $path Existing local path.
 * @return bool Whether the permissions are ready; failures retain PHP's warning.
 */
function sharedFilePermissions(string $path): bool
{
    clearstatcache(true, $path);
    if (is_link($path)) {
        trigger_error("Refusing to change shared-file permissions on symlink: $path", E_USER_WARNING);
        return false;
    }
    $permissions = fileperms($path);
    if ($permissions === false) {
        return false;
    }
    $mode = is_dir($path) ? 0777 : 0666;
    if (($permissions & 07777) === $mode) {
        return true;
    }
    return chmod($path, $mode);
}

/**
 * Open a shared temporary file for writing and repair existing permissions first.
 * Parent directories must already exist and allow www-data to traverse them.
 * No ownership change, shell commands or process-wide umask changes are needed.
 *
 * @param string $path Local shared runtime file, never a secret/configuration file.
 * @param string $mode Writable fopen mode (for example 'a' or 'w').
 * @return resource|false Writable stream, or false on an open/permission failure.
 */
function sharedFileOpen(string $path, string $mode = 'a')
{
    clearstatcache(true, $path);
    if ((file_exists($path) || is_link($path)) && !sharedFilePermissions($path)) {
        return false;
    }
    $stream = fopen($path, $mode);
    if ($stream === false) {
        return false;
    }
    if (!sharedFilePermissions($path)) {
        fclose($stream);
        return false;
    }
    return $stream;
}

/**
 * Write or append text to a shared temporary file.
 *
 * @param string $path Local shared runtime file.
 * @param string $contents Text to write.
 * @param bool $append Append rather than replace existing text.
 * @return int|false Bytes written, or false on an open/write/permission failure.
 */
function sharedFilePutContents(string $path, string $contents, bool $append = false)
{
    $stream = sharedFileOpen($path, $append ? 'a' : 'w');
    if ($stream === false) {
        return false;
    }
    try {
        return fwrite($stream, $contents);
    } finally {
        fclose($stream);
    }
}

/**
 * Create or repair a shared temporary directory so www-data can traverse/write it.
 *
 * @param string $path Directory whose parent already exists.
 * @return bool Whether the directory exists with shared permissions.
 */
function sharedDirectory(string $path): bool
{
    if (!is_dir($path) && !mkdir($path, 0777) && !is_dir($path)) {
        return false;
    }
    return sharedFilePermissions($path);
}
