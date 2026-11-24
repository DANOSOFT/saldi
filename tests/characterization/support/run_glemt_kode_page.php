<?php
// tests/characterization/support/run_glemt_kode_page.php
//
// Child-process runner for the password recovery page, index/glemt_kode.php
// (login > "Glemt adgangskode?").
//
// The page needs no session: it includes connect/db_query/std_func at top
// level, looks the regnskab up in the master database and sends its mail with
// PHPMailer. The body is parsed with parse_str(), the way PHP builds $_POST,
// so "regnskab=R&navn=U&send=Send" is the browser's Send.
//
// argv: 1 = form-urlencoded body to POST, e.g. "regnskab=chartest&navn=u&send=Send"
//
// Prints one JSON line on stdout:
//   html - what the page printed
//
// History:
// 20261105 CL/LH Created for PasswordRecoveryMailTest.

if ($argc < 2) {
    fwrite(STDERR, "usage: php run_glemt_kode_page.php <body>\n");
    exit(2);
}
$chartestBody = $argv[1];

error_reporting(E_ALL);
set_error_handler(static function (): bool {
    return true; // keep the legacy page's warnings off stdout
});

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/saldi/index/glemt_kode.php';
$_SERVER['PHP_SELF'] = '/saldi/index/glemt_kode.php';
$_SERVER['SCRIPT_NAME'] = '/saldi/index/glemt_kode.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__, 3) . '/index/glemt_kode.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_GET = [];
parse_str($chartestBody, $_POST);
$_REQUEST = $_POST;

chdir(dirname(__DIR__, 3) . '/index'); // the page uses ../includes/... relative includes

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
    fwrite(STDOUT, "\n" . json_encode(['html' => $html], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
};
// Registered from a shutdown function, so it runs after any the page's includes register.
register_shutdown_function(static function () use ($chartestReport): void {
    register_shutdown_function($chartestReport);
});
include __DIR__ . '/../../../index/glemt_kode.php';
