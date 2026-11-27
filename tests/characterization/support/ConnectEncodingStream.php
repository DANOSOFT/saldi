<?php
// tests/characterization/support/ConnectEncodingStream.php
//
// Lets a child runner run a page as on an installation of another charset.
//
// includes/connect.php is written by index/install.php and names the
// installation's charset ($db_encode = "UTF8" or "LATIN9"); every page
// includes it again and again, so a runner cannot set the charset itself.
// This pass-through file:// wrapper opens every file as PHP would, except
// includes/connect.php, which it serves with $db_encode set to the charset
// asked for. The page then connects with that client encoding (db_connect())
// and gets its text in it, as on such an installation; the test stack's
// UTF8 databases convert on the server.
//
// Only for child runners (support/run_*.php): it replaces the file wrapper
// for the rest of the process.
//
// History:
// 20261105 CL/LH Created for KontoindstillingerLedgerTest (a LATIN9 regnskab name).

final class ConnectEncodingStream
{
    /** @var resource|null Stream context supplied by PHP. */
    public $context;

    /** @var resource|null */
    private $handle;

    /** @var resource|null */
    private $dir;

    private static string $connectPath = '';

    private static string $encode = '';

    /** From here on, $connectPath (a real path) is served with $db_encode = "$encode". */
    public static function register(string $connectPath, string $encode): void
    {
        $real = realpath($connectPath);
        if ($real === false || !preg_match('/^[A-Z0-9]+$/', $encode)) {
            throw new RuntimeException("cannot serve $connectPath as $encode");
        }
        self::$connectPath = $real;
        self::$encode = $encode;
        stream_wrapper_unregister('file');
        stream_wrapper_register('file', self::class);
    }

    /** Runs $call on PHP's own file wrapper. */
    private static function native(callable $call): mixed
    {
        stream_wrapper_restore('file');
        try {
            return $call();
        } finally {
            stream_wrapper_unregister('file');
            stream_wrapper_register('file', self::class);
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $context = $this->context;
        $report = ($options & STREAM_REPORT_ERRORS) !== 0;
        if (strpbrk($mode, 'waxc+') === false && realpath($path) === self::$connectPath) {
            $source = (string) self::native(static fn() => file_get_contents($path));
            $source = preg_replace('/^\$db_encode = "[^"]*";$/m', '$db_encode = "' . self::$encode . '";', $source, 1, $count);
            if ($count !== 1) {
                throw new RuntimeException(self::$connectPath . ' names no $db_encode');
            }
            $this->handle = fopen('php://memory', 'r+b');
            fwrite($this->handle, $source);
            rewind($this->handle);
            $openedPath = $path;
            return true;
        }
        $usePath = ($options & STREAM_USE_PATH) !== 0;
        $handle = self::native(static fn() => $report ? fopen($path, $mode, $usePath, $context) : @fopen($path, $mode, $usePath, $context));
        if ($handle === false) {
            return false;
        }
        $this->handle = $handle;
        $openedPath = $path;
        return true;
    }

    public function stream_read(int $count): string|false
    {
        return fread($this->handle, $count);
    }

    public function stream_write(string $data): int
    {
        return (int) fwrite($this->handle, $data);
    }

    public function stream_eof(): bool
    {
        return feof($this->handle);
    }

    public function stream_tell(): int
    {
        return (int) ftell($this->handle);
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        return fseek($this->handle, $offset, $whence) === 0;
    }

    public function stream_flush(): bool
    {
        return fflush($this->handle);
    }

    public function stream_close(): void
    {
        fclose($this->handle);
    }

    public function stream_stat(): array|false
    {
        return fstat($this->handle);
    }

    /** $operation 0 asks whether the stream can be locked at all (file_put_contents() with LOCK_EX). */
    public function stream_lock(int $operation): bool
    {
        return $operation === 0 ? true : flock($this->handle, $operation);
    }

    public function stream_truncate(int $size): bool
    {
        return ftruncate($this->handle, $size);
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        return false;
    }

    /** @return resource|false */
    public function stream_cast(int $castAs)
    {
        return $this->handle ?? false;
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        return self::native(static fn(): bool => match ($option) {
            STREAM_META_TOUCH => touch($path, ...array_values((array) $value)),
            STREAM_META_OWNER, STREAM_META_OWNER_NAME => chown($path, $value),
            STREAM_META_GROUP, STREAM_META_GROUP_NAME => chgrp($path, $value),
            STREAM_META_ACCESS => chmod($path, $value),
            default => false,
        });
    }

    public function url_stat(string $path, int $flags): array|false
    {
        $link = ($flags & STREAM_URL_STAT_LINK) !== 0;
        $quiet = ($flags & STREAM_URL_STAT_QUIET) !== 0;
        return self::native(static function () use ($path, $link, $quiet) {
            if ($quiet) {
                return $link ? @lstat($path) : @stat($path);
            }
            return $link ? lstat($path) : stat($path);
        });
    }

    public function unlink(string $path): bool
    {
        return self::native(static fn(): bool => unlink($path));
    }

    public function rename(string $from, string $to): bool
    {
        return self::native(static fn(): bool => rename($from, $to));
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return self::native(static fn(): bool => mkdir($path, $mode, ($options & STREAM_MKDIR_RECURSIVE) !== 0));
    }

    public function rmdir(string $path, int $options): bool
    {
        return self::native(static fn(): bool => rmdir($path));
    }

    public function dir_opendir(string $path, int $options): bool
    {
        $dir = self::native(static fn() => opendir($path));
        if ($dir === false) {
            return false;
        }
        $this->dir = $dir;
        return true;
    }

    public function dir_readdir(): string|false
    {
        return readdir($this->dir);
    }

    public function dir_rewinddir(): bool
    {
        rewinddir($this->dir);
        return true;
    }

    public function dir_closedir(): bool
    {
        closedir($this->dir);
        return true;
    }
}
