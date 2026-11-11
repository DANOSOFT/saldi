<?php
// 20261111 CDX/NTR Limit source invariants to tracked PHP files instead of runtime directories.

final class SourceFiles
{
    /** @return list<string> Absolute paths, excluding dependencies, tests and the protected update backup. */
    public static function php(): array
    {
        $root = dirname(__DIR__, 4);
        $process = proc_open(['git', 'ls-files', '-z', '--', '*.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $root);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not enumerate tracked PHP source');
        }
        $paths = explode("\0", stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('git ls-files failed while enumerating PHP source');
        }
        $files = [];
        foreach ($paths as $path) {
            if ($path !== '' && !preg_match('#(^|/)(tests|vendor|node_modules)/#', $path) && $path !== 'includes/betweenUpdates2.php') {
                $files[] = $root . '/' . $path;
            }
        }
        return $files;
    }
}
