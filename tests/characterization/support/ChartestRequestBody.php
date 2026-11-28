<?php
// tests/characterization/support/ChartestRequestBody.php
//
// The request body a child-process runner hands the script it includes: while
// registered, php://input reads that body, and every other php:// stream is
// PHP's own. Used by run_public_booking_api.php and run_rental_api.php.
//
// History:
// 20261105 CL/LH Created for run_public_booking_api.php and run_rental_api.php (RemoteBookingOverlapTest).

/** The php:// wrapper while a body is given: php://input is that body, every other php:// stream is PHP's own. */
final class ChartestRequestBody
{
    public static string $body = '';

    /** @var resource|null Stream context supplied by PHP. */
    public $context;

    /** @var resource|null */
    private $handle;

    public static function register(string $body): void
    {
        self::$body = $body;
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', self::class);
    }

    /** Runs $call on PHP's own php:// wrapper. */
    private static function native(callable $call): mixed
    {
        stream_wrapper_restore('php');
        try {
            return $call();
        } finally {
            stream_wrapper_unregister('php');
            stream_wrapper_register('php', self::class);
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        if (strcasecmp($path, 'php://input') === 0) {
            $this->handle = self::native(static fn() => fopen('php://memory', 'r+b'));
            fwrite($this->handle, self::$body);
            rewind($this->handle);
            return true;
        }
        $handle = self::native(static fn() => @fopen($path, $mode));
        if ($handle === false) {
            return false;
        }
        $this->handle = $handle;
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

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        return false;
    }
}
