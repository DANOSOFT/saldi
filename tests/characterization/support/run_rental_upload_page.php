<?php
// tests/characterization/support/run_rental_upload_page.php
//
// Child-process runner for the booking importer, rental/upload.php, posted the
// way its form posts a CSV file.
//
// The script starts a session and includes connect/online at top level, so it
// runs as a child process with a fabricated session that CharacterizationEnv
// seeded in `online`. move_uploaded_file() refuses a file PHP did not receive
// as an upload, and the script then reads rental/uploads/<name> anyway: the
// test puts the CSV there before the run and removes it after.
//
// argv: 1 = session id (must match the seeded online row)
//       2 = the CSV's file name in rental/uploads/
//
// Prints one JSON line on stdout:
//   status - the HTTP status the script set
//   body   - what it printed
//
// History:
// 20261105 CL/LH Created for RemoteBookingOverlapTest.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_rental_upload_page.php <session_id> <file name in rental/uploads>\n");
    exit(2);
}
$chartestSessionId = $argv[1];
$chartestFile = $argv[2];

error_reporting(E_ALL);
set_error_handler(static function (): bool {
    return true; // keep the legacy includes' warnings off stdout
});

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/saldi/rental/upload.php';
$_SERVER['PHP_SELF'] = '/saldi/rental/upload.php';
$_SERVER['SCRIPT_NAME'] = '/saldi/rental/upload.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . '/rental/upload.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_GET = [];
$_POST = ['submit' => 'Upload File'];
$_REQUEST = $_POST;
$_FILES = ['fileToUpload' => ['name' => $chartestFile, 'type' => 'text/csv', 'tmp_name' => '', 'error' => UPLOAD_ERR_OK, 'size' => 0]];

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId);

chdir(dirname(__DIR__, 3) . '/rental'); // the script uses ../includes/... and uploads/ relative to its directory

ob_start();
$chartestLevel = ob_get_level();
$chartestReport = static function () use ($chartestLevel): void {
    while (ob_get_level() > $chartestLevel) {
        ob_end_flush();
    }
    $body = (string) ob_get_clean();
    if (getenv('SALDI_CHAR_DEBUG')) {
        fwrite(STDERR, $body . "\n");
    }
    fwrite(STDOUT, "\n" . json_encode(['status' => http_response_code(), 'body' => $body], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
};
// Registered from a shutdown function, so it runs after the ones the script registers (includes/errorReport.php).
register_shutdown_function(static function () use ($chartestReport): void {
    register_shutdown_function($chartestReport);
});
include __DIR__ . '/../../../rental/upload.php';
