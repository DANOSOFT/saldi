<?php
// tests/characterization/support/run_reminder_automation_page.php
//
// Child-process runner for the automatic reminder page, systemdata/reminderAutomation.php.
//
// Works as support/run_settings_section_page.php: the page starts a session and includes
// connect/online at top level, so it runs as a child process with the fabricated session that
// CharacterizationEnv seeded in `online`. The body is parsed with parse_str(), as PHP builds
// $_POST. The session (and with it the CSRF token and the page's flash message) lives in the
// system temp directory under the seeded session id, so the GET after a POST sees the message.
//
// The page ends with exit() after its redirect, so the result is printed from a shutdown function.
//
// argv: 1 = form-urlencoded body to POST, or "" for a GET
//       2 = session id (must match the seeded online row)
//
// Prints one JSON line on stdout:
//   status - the HTTP status the page set (303 after a POST)
//   html   - what the page printed
//
// History:
// 20261126 CL/ASR Created for ReminderAutomationPageTest (Stripe port, #1147).

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_reminder_automation_page.php <body or \"\"> <session_id>\n");
    exit(2);
}
$chartestBody = $argv[1];
$chartestSessionId = $argv[2];

error_reporting(E_ALL);
set_error_handler(static function (): bool {
    return true; // keep the legacy includes' warnings off stdout
});

$_SERVER['REQUEST_METHOD'] = $chartestBody === '' ? 'GET' : 'POST';
$_SERVER['REQUEST_URI'] = '/saldi/systemdata/reminderAutomation.php';
$_SERVER['PHP_SELF'] = '/saldi/systemdata/reminderAutomation.php';
$_SERVER['SCRIPT_NAME'] = '/saldi/systemdata/reminderAutomation.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . '/systemdata/reminderAutomation.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_GET = [];
$_POST = [];
if ($chartestBody !== '') {
    parse_str($chartestBody, $_POST);
}
$_REQUEST = $_GET + $_POST;

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId);

chdir(dirname(__DIR__, 3) . '/systemdata'); // the page uses ../includes/... relative includes

ob_start();
$chartestLevel = ob_get_level();
$chartestReport = static function () use ($chartestLevel): void {
    while (ob_get_level() > $chartestLevel) {
        ob_end_flush();
    }
    $html = (string) ob_get_clean();
    if (getenv('SALDI_CHAR_DEBUG')) {
        fwrite(STDERR, $html . "\n");
    }
    fwrite(STDOUT, "\n" . json_encode(['status' => http_response_code(), 'html' => $html], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
};
// Registered from a shutdown function, so it runs after the ones the page registers (includes/errorReport.php).
register_shutdown_function(static function () use ($chartestReport): void {
    register_shutdown_function($chartestReport);
});
include __DIR__ . '/../../../systemdata/reminderAutomation.php';
