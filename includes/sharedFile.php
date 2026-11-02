<?php
// 20261029 CDX/NTR Share temporary files between CLI jobs and the web server.
// 20261029 CDX/NTR Restrict sharing to a configured group and require trusted parent directories.
// 20261102 CL/NTR Prefer the balances group when www-data belongs to it.
//                 Fall back to plain writes on unmigrated installs and warn once per message.
//                 Check the opened file against the path before truncating it.
//                 Follow symlinks held by trusted directories; open a symlinked file without chmod.

/**
 * Raise a shared-file warning once per request, so per-query logging cannot flood the error log.
 *
 * @param string $message Actionable warning text.
 * @return void
 */
function sharedFileWarning(string $message): void
{
    static $seen = [];
    if (!isset($seen[$message])) {
        $seen[$message] = true;
        trigger_error($message, E_USER_WARNING);
    }
}

/**
 * Name the shared runtime group: SALDI_SHARED_FILE_GROUP when set, otherwise
 * balances when www-data belongs to it (INSTALLATION.md), otherwise www-data.
 *
 * @return string Group name; CLI and web processes resolve the same name.
 */
function sharedFileGroupName(): string
{
    $configured = getenv('SALDI_SHARED_FILE_GROUP');
    if ($configured) {
        return $configured;
    }
    if (function_exists('posix_getgrnam')) {
        $balances = posix_getgrnam('balances');
        $web = posix_getpwnam('www-data');
        if ($balances !== false
            && (in_array('www-data', $balances['members'], true) || ($web !== false && $web['gid'] === $balances['gid']))) {
            return 'balances';
        }
    }
    return 'www-data';
}

/**
 * Resolve the shared runtime group; CLI and web processes must use the same group.
 *
 * @return int|false Group ID, or false after an actionable configuration warning.
 */
function sharedFileGroup()
{
    $name = sharedFileGroupName();
    $group = function_exists('posix_getgrnam') ? posix_getgrnam($name) : false;
    if ($group === false) {
        sharedFileWarning("Shared runtime group '$name' is unavailable; configure POSIX and follow doc/runtime-permissions.md");
        return false;
    }
    return $group['gid'];
}

/**
 * Validate the directory chain before any chmod, creation, opening or truncation.
 * Shared-group members and directory owners are trusted deployment accounts.
 * Reject directories writable by others or an unrelated group. A symlink is
 * followed only after the directory holding it passed, so no untrusted account
 * can replace it, and its target chain is then validated the same way.
 * Sticky ancestors (such as /tmp) are allowed only above an owned entry;
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
    $components = static fn(string $chain): array => array_values(array_filter(explode('/', $chain),
        static fn(string $part): bool => $part !== '' && $part !== '.'));
    $pending = $components(dirname($absolute));
    $directory = '';
    $links = 0;
    while ($pending) {
        $part = array_shift($pending);
        if ($part === '..') {
            // $directory holds no symlinks, so a lexical step up matches the kernel's.
            $directory = dirname($directory ?: '/') === '/' ? '' : dirname($directory);
            continue;
        }
        $entry = $directory . '/' . $part;
        clearstatcache(true, $entry);
        $stat = @lstat($entry);
        if ($stat === false) {
            return false;
        }
        $mode = $stat['mode'];
        if (($mode & 0170000) === 0120000) {
            // The holding directory already passed, so only trusted accounts can replace this link.
            $target = readlink($entry);
            if ($target === false || ++$links > 40) {
                sharedFileWarning("Unresolvable shared-file parent symlink: $entry");
                return false;
            }
            if (str_starts_with($target, '/')) {
                $directory = '';
            }
            $pending = array_merge($components($target), $pending);
            continue;
        }
        $stickyAncestor = $pending && ($mode & 01002) === 01002;
        if (($mode & 0170000) !== 0040000
            || (($mode & 0002) !== 0 && !$stickyAncestor)
            || (($mode & 0020) !== 0 && $stat['gid'] !== $gid && !$stickyAncestor)) {
            sharedFileWarning("Untrusted shared-file parent: $entry; follow doc/runtime-permissions.md");
            return false;
        }
        if ($stickyAncestor) {
            // A sticky parent protects an entry only when it belongs to a trusted account.
            $child = $entry . '/' . $pending[0];
            clearstatcache(true, $child);
            $childStat = @lstat($child);
            if ($childStat === false || !in_array($childStat['uid'], [0, posix_geteuid()], true)) {
                sharedFileWarning("Untrusted entry below sticky directory: $child");
                return false;
            }
        }
        $directory = $entry;
    }
    return true;
}

/**
 * Share a regular runtime file (0660) or setgid directory (02770) with the group
 * from sharedFileGroupName(). Existing files
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
        sharedFileWarning("Refusing shared permissions on symlink or special file: $path");
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
        sharedFileWarning("Shared runtime permissions require migration by the owner or administrator: $path; follow doc/runtime-permissions.md");
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
 * An unmigrated install (untrusted parent, unknown group) keeps the plain fopen
 * it used before, without changing permissions through that chain; a file the
 * caller cannot repair is still written when the filesystem allows it. A file
 * that is itself a symlink is written through without changing permissions.
 *
 * @param string $path Local shared runtime file.
 * @param string $mode Writable fopen mode (for example 'a' or 'w').
 * @return resource|false Writable stream, or false on an open failure or a special file.
 */
function sharedFileOpen(string $path, string $mode = 'a')
{
    if (!sharedFileTrustedParent($path)) {
        return @fopen($path, $mode);
    }
    clearstatcache(true, $path);
    if (is_link($path)) {
        // A trusted account placed the link; write through it, but never chmod its target.
        return @fopen($path, $mode);
    }
    $existed = file_exists($path);
    if ($existed && !is_file($path)) {
        sharedFileWarning("Refusing shared permissions on special file: $path");
        return false;
    }
    if ($existed) {
        sharedFilePermissions($path);
    }
    // Open without truncating, so a path swapped after the checks is refused before data is lost.
    $truncate = $mode[0] === 'w';
    $stream = @fopen($path, $truncate ? 'c' . substr($mode, 1) : $mode);
    if ($stream === false) {
        return false;
    }
    clearstatcache(true, $path);
    $opened = fstat($stream);
    $linked = lstat($path);
    if ($opened === false || $linked === false || $opened['dev'] !== $linked['dev'] || $opened['ino'] !== $linked['ino']) {
        fclose($stream);
        sharedFileWarning("Shared runtime file changed while opening: $path");
        return false;
    }
    if ($truncate) {
        ftruncate($stream, 0);
    }
    if (!$existed) {
        sharedFilePermissions($path);
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
 * Below an untrusted parent the directory is only created (0770), as before migration;
 * a symlinked directory is used without changing permissions.
 *
 * @param string $path Directory whose parent already exists.
 * @return bool Whether the directory exists with shared permissions.
 */
function sharedDirectory(string $path): bool
{
    $path = rtrim($path, '/') ?: '/';
    if (!sharedFileTrustedParent($path)) {
        return is_dir($path) || @mkdir($path, 0770) || is_dir($path);
    }
    if (is_link($path)) {
        // A symlinked directory is used as it is; its target's permissions are not changed.
        return is_dir($path);
    }
    if (!is_dir($path) && !mkdir($path, 02770) && !is_dir($path)) {
        return false;
    }
    return sharedFilePermissions($path);
}
