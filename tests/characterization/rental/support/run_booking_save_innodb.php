<?php
// tests/characterization/rental/support/run_booking_save_innodb.php
//
// Child-process runner for RentalStandLockTest: one staff save of a booking, rental/rental.php?createBooking or
// ?updateBooking, run as its block stands in that file, against the scripted MySQL/MariaDB database of
// innodb_booking_stub_database.php and with the JSON body rental/booking.js or rental/edit.js sends. The block ends with
// exit() on a refusal, so the result is printed from a shutdown function.
//
// argv: 1 = createBooking or updateBooking
//       2 = JSON: stands, periods and waiting for innodbStubDatabase(), and body, the request body
//
// Prints one JSON line on stdout:
//   status     - the HTTP status the block set (false when it set none)
//   body       - what it printed
//   statements - every statement it sent, in order
//
// History:
// 20261105 CL/LH Created.

if ($argc < 3 || !in_array($argv[1], ['createBooking', 'updateBooking'], true)) {
    fwrite(STDERR, "usage: php run_booking_save_innodb.php <createBooking|updateBooking> <scenario JSON>\n");
    exit(2);
}
$innodbHandler = $argv[1];
$innodbScenario = json_decode($argv[2], true);

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

require __DIR__ . '/innodb_booking_stub_database.php';
require_once dirname(__DIR__, 2) . '/support/ChartestRequestBody.php';
require dirname(__DIR__, 4) . '/includes/stdFunc/rentalPeriodTaken.php';

// The block of rental/rental.php that answers ?<handler>: the code between its braces.
$innodbSource = (string)file_get_contents(dirname(__DIR__, 4) . '/rental/rental.php');
$innodbStart = strpos($innodbSource, 'if(isset($_GET["' . $innodbHandler . '"])){');
if ($innodbStart === false) {
    fwrite(STDERR, "rental/rental.php has no ?$innodbHandler block\n");
    exit(2);
}
$innodbDepth = 0;
$innodbBlock = '';
foreach (array_slice(token_get_all('<?php ' . substr($innodbSource, $innodbStart)), 1) as $innodbToken) {
    $innodbText = is_array($innodbToken) ? $innodbToken[1] : $innodbToken;
    $innodbOpens = $innodbText === '{' || (is_array($innodbToken) && in_array($innodbToken[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true));
    if ($innodbText === '}' && --$innodbDepth === 0) {
        break;
    }
    if ($innodbDepth > 0) {
        $innodbBlock .= $innodbText;
    }
    if ($innodbOpens) {
        $innodbDepth++;
    }
}

innodbStubDatabase($innodbScenario['stands'], $innodbScenario['periods'], $innodbScenario['waiting']);
ChartestRequestBody::register((string)json_encode($innodbScenario['body']));
$_GET = [$innodbHandler => ''];

ob_start();
register_shutdown_function(static function (): void {
    $body = (string)ob_get_clean();
    fwrite(STDOUT, "\n" . json_encode([
        'status' => http_response_code(),
        'body' => $body,
        'statements' => $GLOBALS['innodbStub']['statements'],
    ], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
});
eval($innodbBlock);
