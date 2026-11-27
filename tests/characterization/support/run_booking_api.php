<?php
// tests/characterization/support/run_booking_api.php
//
// Child-process runner for createBooking on the public booking endpoints (SST-999).
//
// Runs the real remoteBooking/api.php or mysale/api.php from top to bottom, as one
// POST ?createBooking request. Only two things are replaced:
//   - public_tenant_db(): the endpoints only accept "<master>_<id>" account names, and
//     the generated test database is named t<hex>. The endpoints define the function
//     inside a function_exists() guard, so the one below is used instead.
//   - php://input: the CLI has no request body, so a stream wrapper serves the JSON
//     body for php://input and opens every other php:// stream as usual.
//
// Usage: php run_booking_api.php <remoteBooking|mysale> <tenant_db> <json body>
// Prints a single JSON object on the LAST line of stdout: {"status": <HTTP code>, "body": "<response>"}.
//
// History:
// 20261119 CL/MMR SST-999 Created (review of PR #1097: a period moved by closed days must not overlap another booking).
// 20261120 CL/MMR SST-999 Codex round 10: sets Europe/Copenhagen before the endpoint is included, so the child counts days as the fixture does.

if ($argc < 4 || !in_array($argv[1], array('remoteBooking', 'mysale'), true)) {
    fwrite(STDERR, "usage: php run_booking_api.php <remoteBooking|mysale> <tenant_db> <json body>\n");
    exit(2);
}
$bookingApi = $argv[1];
$tenantDb = $argv[2];

/**
 * Serves the request body for php://input; every other php:// stream is opened by PHP's own wrapper.
 */
final class BookingRequestBody
{
    /** @var string The JSON body the endpoint reads from php://input. */
    public static string $body = '';
    /** @var resource|null Set by PHP for user stream wrappers. */
    public $context;
    private int $position = 0;
    /** @var resource|null The real stream when the path is not php://input. */
    private $real = null;

    /**
     * @param string $path php:// URL being opened.
     * @param string $mode fopen() mode.
     * @param int $options Stream options from PHP.
     * @param string|null $openedPath Not used.
     * @return bool Whether the stream opened.
     */
    public function stream_open($path, $mode, $options, &$openedPath): bool
    {
        if (strtolower($path) === 'php://input') {
            $this->position = 0;
            return true;
        }
        stream_wrapper_restore('php');
        $this->real = fopen($path, $mode);
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', self::class);
        return $this->real !== false;
    }

    /**
     * @param int $count Bytes wanted.
     * @return string|false The next bytes.
     */
    public function stream_read($count)
    {
        if ($this->real) {
            return fread($this->real, $count);
        }
        $chunk = (string)substr(self::$body, $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }

    /**
     * @param string $data Bytes to write.
     * @return int Bytes written.
     */
    public function stream_write($data): int
    {
        return $this->real ? (int)fwrite($this->real, $data) : 0;
    }

    /** @return bool Whether the stream is at its end. */
    public function stream_eof(): bool
    {
        return $this->real ? feof($this->real) : $this->position >= strlen(self::$body);
    }

    /** @return array<int|string, int>|false Stream statistics. */
    public function stream_stat()
    {
        return $this->real ? fstat($this->real) : array();
    }

    /** @return void */
    public function stream_close(): void
    {
        if ($this->real) {
            fclose($this->real);
        }
    }
}

/**
 * The test database, in place of the endpoints' account name check.
 * @param mixed $value The account name from the request (ignored).
 * @return string The generated test database name.
 */
function public_tenant_db($value) {
    return $GLOBALS['tenantDb'];
}

BookingRequestBody::$body = $argv[3];
stream_wrapper_unregister('php');
stream_wrapper_register('php', BookingRequestBody::class);

$_GET = array('createBooking' => '', 'id' => $tenantDb, 'db' => $tenantDb);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = "/saldi/$bookingApi/api.php?createBooking";
$_SERVER['PHP_SELF'] = "/saldi/$bookingApi/api.php";
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

http_response_code(200);
ob_start();
register_shutdown_function(static function (): void {
    $body = '';
    while (ob_get_level() > 0) {
        $body = ob_get_clean() . $body;
    }
    fwrite(STDOUT, "\n" . json_encode(array('status' => http_response_code(), 'body' => trim($body))) . "\n");
});

// The endpoints' dates are local days: includes/settings.php sets Europe/Copenhagen when connect.php loads it, but a
// test connect.php may not, and the CLI default is UTC. Set it here, as RentalBookingOverlapTest does for its fixture.
date_default_timezone_set('Europe/Copenhagen');
chdir(dirname(__DIR__, 3) . "/$bookingApi"); // ../includes/... must resolve
require dirname(__DIR__, 3) . "/$bookingApi/api.php";
