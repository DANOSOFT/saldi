<?php
// 20261029 CDX/NTR Provide an administrator-only migration for existing shared runtime files.
// 20261102 CL/NTR Accept a symlinked runtime directory by migrating its resolved path.
// 20261103 CDX/NTR Restrict migration to this installation's temp and validate argument ancestors before resolving.
//                  Continue past refused entries and report an incomplete migration at the end.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../includes/sharedFile.php';

/**
 * Migrate an offline runtime tree, locking each directory before examining entries.
 * Directory ownership moves to root so former owners cannot reopen it to others.
 * Symlinks and special files are refused and are never followed.
 *
 * @param string $path Existing runtime file or directory below trusted ancestors.
 * @return bool Whether the complete tree has group-only shared permissions.
 */
function migrateSharedRuntime(string $path): bool
{
    $path = rtrim($path, '/') ?: '/';
    if (!sharedFileTrustedParent($path)) {
        return false;
    }
    clearstatcache(true, $path);
    if (is_link($path)) {
        fwrite(STDERR, "Refusing runtime symlink: $path\n");
        return false;
    }
    if (is_dir($path)) {
        if (!chown($path, 0) || !sharedFilePermissions($path)) {
            return false;
        }
        $entries = scandir($path);
        if ($entries === false) {
            return false;
        }
        $complete = true;
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..' && !migrateSharedRuntime($path . '/' . $entry)) {
                $complete = false;
            }
        }
        return $complete;
    }
    return sharedFilePermissions($path);
}

if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) {
    fwrite(STDERR, "Run this migration as an administrator via CLI; see doc/runtime-permissions.md\n");
    exit(1);
}
if ($argc !== 2 || !is_dir($argv[1])) {
    fwrite(STDERR, "Usage: sudo php tools/migrate_shared_runtime.php /absolute/path/to/temp\n");
    exit(1);
}
// Check the original holding chains before resolving; only trusted accounts may replace a symlink.
$installationTemp = __DIR__ . '/../temp';
if (!sharedFileTrustedParent(rtrim($argv[1], '/') ?: '/') || !sharedFileTrustedParent($installationTemp)) {
    fwrite(STDERR, "Refusing untrusted runtime path: $argv[1]\n");
    exit(1);
}
$runtime = realpath($argv[1]);
$expected = realpath($installationTemp);
if ($runtime === false || $runtime === '/' || $runtime !== $expected) {
    fwrite(STDERR, "Refusing runtime path outside this installation's temp directory: $argv[1]\n");
    exit(1);
}
// A symlinked temp is migrated at its target; entries inside are still never followed.
if (!migrateSharedRuntime($runtime)) {
    exit(1);
}
fwrite(STDOUT, "Runtime migration complete. Restart CLI jobs and web workers with the shared group.\n");
