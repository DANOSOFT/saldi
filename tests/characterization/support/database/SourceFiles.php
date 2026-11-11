<?php
// 20261111 CDX/NTR Limit source invariants to tracked PHP files instead of runtime directories.
// 20261111 CDX/NTR Trust only this checkout when Docker uses a different UID; preserve git diagnostics.
// 20261111 CDX/NTR Put wrapped helper-call arguments on separate lines.

final class SourceFiles
{
    /** @return list<string> Absolute paths, excluding dependencies, tests and the protected update backup. */
    public static function php(): array
    {
        $root = dirname(__DIR__, 4);
        $errors = tmpfile();
        $process = proc_open(
            ['git', '-c', 'safe.directory=' . $root, 'ls-files', '-z', '--', '*.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => $errors],
            $pipes,
            $root
        );
        if (!is_resource($process)) {
            fclose($errors);
            throw new RuntimeException('Could not enumerate tracked PHP source');
        }
        $paths = explode("\0", stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        $exit = proc_close($process);
        rewind($errors);
        $diagnostics = stream_get_contents($errors);
        fclose($errors);
        if ($exit !== 0) {
            throw new RuntimeException('git ls-files failed while enumerating PHP source: ' . trim($diagnostics));
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
