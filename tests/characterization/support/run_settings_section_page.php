<?php
// tests/characterization/support/run_settings_section_page.php
//
// Child-process runner for a settings section, systemdata/settingsSection.php.
//
// The page starts a session and includes connect/online at top level, so it
// runs as a child process with a fabricated session that CharacterizationEnv
// seeded in `online`. The query string and the body are parsed with
// parse_str(), the way PHP builds $_GET and $_POST, so f[company.data.phone]
// arrives as an array key as it would from a browser. The session (and with
// it the CSRF token) lives in the system temp directory under the seeded
// session id, so a POST in a second child sees the token the GET drew.
//
// The page ends with exit() after a redirect, so the result is printed from a
// shutdown function.
//
// argv: 1 = query string, e.g. "s=company.data"
//       2 = form-urlencoded body to POST, or "" for a GET
//       3 = session id (must match the seeded online row)
//       4 = optional: the directory of the PHP session (default the system temp
//           directory), so two children can run at the same time: PHP locks a
//           session file for the whole request
//
// Prints one JSON line on stdout:
//   status - the HTTP status the page set (302 after a redirect)
//   html   - what the page printed
//
// History:
// 20261105 CL/LH Created for SettingsSectionThisDbTest.
// 20261119 CL/ASR argv 4, an optional session directory, for two saves at the same time (SettingsCompositeSaveRaceTest).

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_settings_section_page.php <query string> <body or \"\"> <session_id>\n");
    exit(2);
}
$chartestQuery = $argv[1];
$chartestBody = $argv[2];
$chartestSessionId = $argv[3];

error_reporting(E_ALL);
set_error_handler(static function (): bool {
    return true; // keep the legacy includes' warnings off stdout
});

$_SERVER['REQUEST_METHOD'] = $chartestBody === '' ? 'GET' : 'POST';
$_SERVER['REQUEST_URI'] = '/saldi/systemdata/settingsSection.php?' . $chartestQuery;
$_SERVER['PHP_SELF'] = '/saldi/systemdata/settingsSection.php';
$_SERVER['SCRIPT_NAME'] = '/saldi/systemdata/settingsSection.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . '/systemdata/settingsSection.php'; // the page map (includes/stdFunc/pageChrome.php) knows the page by it
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestQuery, $_GET);
$_POST = [];
if ($chartestBody !== '') {
    parse_str($chartestBody, $_POST);
}
$_REQUEST = $_GET + $_POST;

ini_set('session.save_path', ($argv[4] ?? '') !== '' ? $argv[4] : sys_get_temp_dir());
session_id($chartestSessionId);

chdir(dirname(__DIR__, 3) . '/systemdata'); // the page uses ../includes/... relative includes

ob_start();
$chartestLevel = ob_get_level();
$chartestReport = static function () use ($chartestLevel): void {
    // The page's own buffer is still open when it simply ends; closing it runs its output handler.
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
include __DIR__ . '/../../../systemdata/settingsSection.php';
