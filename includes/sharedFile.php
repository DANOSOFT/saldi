<?php
// 20261029 CDX/NTR Share temporary files between CLI jobs and the web server.
// 20261029 CDX/NTR Restrict sharing to a configured group and require trusted parent directories.

/**
 * Resolve the shared runtime group; CLI and web processes must use the same group.
 *
 * @return int|false Group ID, or false after an actionable configuration warning.
 */
function sharedFileGroup()
{
    $name = getenv('SALDI_SHARED_FILE_GROUP') ?: 'www-data';
    $group = function_exists('posix_getgrnam') ? posix_getgrnam($name) : false;
    if ($group === false) {
        trigger_error("Shared runtime group '$name' is unavailable; configure POSIX and follow doc/runtime-permissions.md", E_USER_WARNING);
        return false;
    }
    return $group['gid'];
}

/**
 * Validate the directory chain before any chmod, creation, opening or truncation.
 * Shared-group members and directory owners are trusted deployment accounts.
 * Reject symlinks and directories writable by others or an unrelated group.
 * Sticky ancestors (such as /tmp) are allowed only above an owned directory;
 * the immediate parent must never be writable by others, even with the sticky bit.
 * This trust boundary, rather than a post-open symlink check, prevents replacement.
 *
 * @param string $path Local file/directory path whose parent must already exist.
 * @return bool Whether untrusted accounts are prevented from replacing the path.
 */
function sharedFileTrustedParent(string $path): bool
{
    $gid = sharedFileGroup();
    if ($gid === false || str_contains($path, '://') || str_contains($path, "\0")) {
        return false;
    }
    $absolute = str_starts_with($path, '/') ? $path : getcwd() . '/' . $path;
    $parts = array_values(array_filter(explode('/', dirname($absolute)),
        static fn(string $part): bool => $part !== '' && $part !== '.'));
    $directory = '';
    foreach ($parts as $index => $part) {
        $directory .= '/' . $part;
        clearstatcache(true, $directory);
        $stat = lstat($directory);
        if ($stat === false) {
            return false;
        }
        $mode = $stat['mode'];
        $last = $index === count($parts) - 1;
        $stickyAncestor = !$last && ($mode & 01000) !== 0;
        if (($mode & 0170000) !== 0040000
            || (($mode & 0002) !== 0 && !$stickyAncestor)
            || (($mode & 0020) !== 0 && $stat['gid'] !== $gid && !$stickyAncestor)) {
            trigger_error("Untrusted shared-file parent: $directory; follow doc/runtime-permissions.md", E_USER_WARNING);
            return false;
        }
        if ($stickyAncestor) {
            // A sticky parent protects an entry only when it belongs to a trusted account.
            $child = $directory . '/' . $parts[$index + 1];
            clearstatcache(true, $child);
            $childStat = lstat($child);
            if ($childStat === false || !in_array($childStat['uid'], [0, posix_geteuid()], true)) {
                trigger_error("Untrusted entry below sticky directory: $child", E_USER_WARNING);
                return false;
            }
        }
    }
    return true;
}

/**
 * Share a regular runtime file (0660) or setgid directory (02770) with www-data.
 * SALDI_SHARED_FILE_GROUP can select another dedicated group. Existing files
 * owned by another account must be migrated by their owner or an administrator;
 * the web process never attempts privilege escalation. Already-correct files
 * work across owners without chmod. See doc/runtime-permissions.md for setup.
 *
 * @param string $path Existing local runtime path, never a secret/configuration file.
 * @return bool Whether the group and permissions are ready.
 */
function sharedFilePermissions(string $path): bool
{
    $path = rtrim($path, '/') ?: '/';
    if (!sharedFileTrustedParent($path)) {
        return false;
    }
    clearstatcache(true, $path);
    $stat = lstat($path);
    if ($stat === false) {
        return false;
    }
    $type = $stat['mode'] & 0170000;
    if ($type !== 0100000 && $type !== 0040000) {
        trigger_error("Refusing shared permissions on symlink or special file: $path", E_USER_WARNING);
        return false;
    }
    $gid = sharedFileGroup();
    if ($gid === false) {
        return false;
    }
    $mode = $type === 0040000 ? 02770 : 0660;
    if ($stat['gid'] === $gid && ($stat['mode'] & 07777) === $mode) {
        return true;
    }
    $uid = posix_geteuid();
    if (($uid !== 0 && $stat['uid'] !== $uid)
        || ($stat['gid'] !== $gid && $uid !== 0 && !in_array($gid, array_merge([posix_getegid()], posix_getgroups()), true))) {
        trigger_error("Shared runtime permissions require migration by the owner or administrator: $path; follow doc/runtime-permissions.md", E_USER_WARNING);
        return false;
    }
    if ($stat['gid'] !== $gid && !chgrp($path, $gid)) {
        return false;
    }
    return chmod($path, $mode);
}

/**
 * Open a shared runtime file, repairing owner-managed permissions before opening.
 * The trusted parent must already exist; setgid parents ensure group inheritance.
 * Creation is safe under any umask because the parent excludes untrusted users.
 *
 * @param string $path Local shared runtime file.
 * @param string $mode Writable fopen mode (for example 'a' or 'w').
 * @return resource|false Writable stream, or false on an open/permission failure.
 */
function sharedFileOpen(string $path, string $mode = 'a')
{
    if (!sharedFileTrustedParent($path)) {
        return false;
    }
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
 * Create or repair a shared setgid directory below a trusted, existing parent.
 *
 * @param string $path Directory whose parent already exists.
 * @return bool Whether the directory exists with shared permissions.
 */
function sharedDirectory(string $path): bool
{
    $path = rtrim($path, '/') ?: '/';
    if (!sharedFileTrustedParent($path)) {
        return false;
    }
    if (!is_dir($path) && !mkdir($path, 02770) && !is_dir($path)) {
        return false;
    }
    return sharedFilePermissions($path);
}
