<?php
// 20261103 CDX/NTR Simulate truncation and partial-write failures in isolated shared-file test processes.
// 20261103 CDX/NTR Model overwrite opens so failed-truncation tests detect premature data loss.

/** A local-file wrapper used only in child processes, after application includes are loaded. */
final class SharedFileFaultStream
{
    /** @var resource|null Stream context supplied by PHP. */
    public $context;
    public static bool $truncateSucceeds = true;
    /** @var int[] Byte counts to return for successive low-level writes. */
    public static array $writeCounts = [];
    public static string $contents = '';
    public static int $closed = 0;
    public static int $parentStats = 0;

    /** Accept the fake local file without opening any real filesystem entry. */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        if ($mode[0] === 'w') {
            self::$contents = '';
        }
        return true;
    }

    /**
     * Supply matching inode identity for stat and fstat; all parents are trusted.
     *
     * @return array{mode: int, uid: int, gid: int, dev: int, ino: int, size: int}
     */
    public function url_stat(string $path, int $flags): array
    {
        if ($path !== '/shared-file-fixture/log') {
            self::$parentStats++;
        }
        return [
            'mode' => $path === '/shared-file-fixture/log' ? 0100660 : 0040755,
            'uid' => posix_geteuid(), 'gid' => posix_getegid(), 'dev' => 1, 'ino' => 1, 'size' => 0,
        ];
    }

    /** @return array{mode: int, uid: int, gid: int, dev: int, ino: int, size: int} */
    public function stream_stat(): array
    {
        return $this->url_stat('/shared-file-fixture/log', 0);
    }

    /** Return a controlled failure before any overwrite data can be written. */
    public function stream_truncate(int $size): bool
    {
        return self::$truncateSucceeds;
    }

    /** Write the requested prefix, including a zero-byte write when the fixture requests it. */
    public function stream_write(string $contents): int
    {
        $count = min(array_shift(self::$writeCounts) ?? strlen($contents), strlen($contents));
        self::$contents .= substr($contents, 0, $count);
        return $count;
    }

    /** Allow buffered data to flush without changing the fixture's result. */
    public function stream_flush(): bool
    {
        return true;
    }

    /** Record cleanup on successful and failed writes. */
    public function stream_close(): void
    {
        self::$closed++;
    }
}
