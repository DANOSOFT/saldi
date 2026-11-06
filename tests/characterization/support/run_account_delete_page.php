<?php
// tests/characterization/support/run_account_delete_page.php
//
// Child-process runner for the pages that delete a debtor or creditor from its
// card: debitor/debitorkort.php, sager/kunder.php (Slet kunde, and
// debitor/debkort_save.php through its Gem/opdatér path),
// kreditor/kreditorkort.php and includes/kreditorkort.php.
//
// Each page starts a session and includes connect/online/std_func at top
// level, and a delete may end in exit(), so it runs as a child process with a
// fabricated session that CharacterizationEnv seeded in `online`. The query
// string and the form body are parsed with parse_str(), the way PHP builds
// $_GET and $_POST.
//
// argv: 1 = page, relative to the repository root, e.g. "kreditor/kreditorkort.php"
//       2 = query string, e.g. "id=12"
//       3 = form body, e.g. "id=12&submit=Slet"
//       4 = session id (must match the seeded online row)
//
// Prints one JSON line on stdout, also when the page ends in exit():
//   alerts   - the texts of the page's alert() calls, in order
//   warnings - PHP warnings/notices raised in includes/stdFunc/accountHasEntries.php
//              (the pages' own legacy noise is ignored)
//
// History:
// 20261105 CL/LH Created for AccountDeleteKeepsEntriesTest.

if ($argc < 5 || !preg_match('#^[a-z]+/[A-Za-z_]+\.php$#', $argv[1])) {
    fwrite(STDERR, "usage: php run_account_delete_page.php <dir/page.php> <query string> <form body> <session_id>\n");
    exit(2);
}
$chartestPage = $argv[1];
$chartestQuery = $argv[2];
$chartestBody = $argv[3];
$chartestSessionId = $argv[4];

error_reporting(E_ALL);
$chartestWarnings = [];
set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline) use (&$chartestWarnings): bool {
    // @-suppressed calls (the pages' @session_start()) arrive with error_reporting() masked.
    if ((error_reporting() & $errno) && str_ends_with($errfile, '/includes/stdFunc/accountHasEntries.php')) {
        $chartestWarnings[] = "line $errline: $errstr";
    }
    return true; // keep the legacy includes' warnings off stdout
});

register_shutdown_function(static function () use (&$chartestWarnings): void {
    $output = '';
    while (ob_get_level() > 0) {
        $output = ob_get_clean() . $output;
    }
    if (getenv('SALDI_CHAR_DEBUG')) {
        fwrite(STDERR, $output . "\n");
    }
    $alerts = [];
    // alert("...") as jsString() writes it, and the legacy alert('...').
    preg_match_all('/alert\((?:("(?:[^"\\\\]|\\\\.)*")|\'((?:[^\'\\\\]|\\\\.)*)\')\)/', $output, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $alerts[] = isset($match[2]) ? stripslashes($match[2]) : json_decode($match[1]);
    }
    fwrite(STDOUT, json_encode(['alerts' => $alerts, 'warnings' => $chartestWarnings], JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
});

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/saldi/' . $chartestPage . '?' . $chartestQuery;
$_SERVER['PHP_SELF'] = '/saldi/' . $chartestPage;
$_SERVER['SCRIPT_NAME'] = '/saldi/' . $chartestPage;
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestQuery, $_GET);
parse_str($chartestBody, $_POST);
$_REQUEST = $_POST + $_GET;

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId);

chdir(dirname(__DIR__, 3) . '/' . dirname($chartestPage)); // the pages use ../includes/... relative includes

ob_start();
include basename($chartestPage);
