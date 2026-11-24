<?php
// tests/characterization/support/run_page.php
//
// Generic child-process runner for one legacy page script.
//
// Legacy pages start a session, include connect/online/std_func at top level,
// print HTML and exit() on several branches, so they cannot be included in the
// PHPUnit process. Modelled on run_bogfor_page.php, this runner is executed as
// a separate `php` child (CharacterizationEnv::runChild()) and drives the page
// the way a browser request does: it fabricates the session (seeded in the
// `online` table by CharacterizationEnv), fills $_GET from the query string
// with parse_str() (so `id[]=7` arrives as an array, as from a browser), fills
// $_POST for a POST request, changes to the page's folder for its
// ../includes/... relative includes and includes the page.
//
// argv: 1 = page, relative to the repository root (e.g. systemdata/laas_lager.php)
//       2 = session id (must match the seeded online row)
//       3 = query string, e.g. "regnaar=1&print=0" ('' for none)
//       4 = optional JSON object with the POST fields; when given, the request is a POST
//           (values arrive as strings, as from a browser)
//
// Prints one JSON line on stdout, also when the page exit()s:
//   returned - true when the include ran to the end, false when the page exit()ed
//   output   - everything the page printed
//   warnings - PHP warnings and notices raised in the page file itself
//              (the legacy includes' own noise is ignored)
// A fatal error ends the child with a non-zero exit status and the message on stderr.
// The test asserts on this output and on database state.
//
// History:
// 20261114 CL/ASR Created for LaasLagerRetiredTest.

if ($argc < 4 || $argc > 5) {
    fwrite(STDERR, "usage: php run_page.php <page> <session_id> <query> [post-json]\n");
    exit(2);
}

/**
 * State of the run, kept in a class so the page's own top-level variables cannot overwrite it.
 */
final class ChartestPageRun
{
    /** @var string Real path of the page file. */
    public static string $file = '';
    /** @var int Output-buffer level before the runner's own buffer. */
    public static int $bufferLevel = 0;
    /** @var bool True once the include has returned. */
    public static bool $returned = false;
    /** @var list<string> Warnings and notices raised in the page file. */
    public static array $warnings = [];

    /**
     * Collect what the page printed and write the JSON result line. Runs at shutdown, so also after exit().
     *
     * @return void
     */
    public static function report(): void
    {
        $output = '';
        while (ob_get_level() > self::$bufferLevel) {
            $output = (string)ob_get_clean() . $output;
        }
        $json = json_encode(
            ['returned' => self::$returned, 'output' => $output, 'warnings' => self::$warnings],
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        fwrite(STDOUT, $json . "\n");
    }
}

$chartestPage = $argv[1];
$chartestSessionId = $argv[2];
$chartestQuery = $argv[3];
$chartestRoot = dirname(__DIR__, 3);
if (!preg_match('#^[A-Za-z0-9_]+(/[A-Za-z0-9_]+)*\.php$#', $chartestPage) || !is_file($chartestRoot . '/' . $chartestPage)) {
    fwrite(STDERR, "page must be an existing .php file given relative to the repository root\n");
    exit(2);
}
$chartestPost = null;
if ($argc === 5) {
    $chartestPost = json_decode($argv[4], true);
    if (!is_array($chartestPost)) {
        fwrite(STDERR, "post-json must be a JSON object\n");
        exit(2);
    }
    // A browser sends every field as a string.
    array_walk_recursive($chartestPost, static function (&$value): void {
        $value = is_bool($value) ? ($value ? '1' : '') : (string)$value;
    });
}
ChartestPageRun::$file = (string)realpath($chartestRoot . '/' . $chartestPage);

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
    // @-suppressed calls (the page's @session_start()) arrive with error_reporting() masked.
    if ((error_reporting() & $errno) && realpath($errfile) === ChartestPageRun::$file) {
        ChartestPageRun::$warnings[] = "line $errline: $errstr";
    }
    return true; // keep the legacy includes' warnings off stdout
});

$_SERVER['REQUEST_METHOD'] = $chartestPost === null ? 'GET' : 'POST';
$_SERVER['REQUEST_URI'] = '/saldi/' . $chartestPage . ($chartestQuery !== '' ? '?' . $chartestQuery : '');
$_SERVER['QUERY_STRING'] = $chartestQuery;
$_SERVER['SCRIPT_NAME'] = '/saldi/' . $chartestPage;
$_SERVER['PHP_SELF'] = '/saldi/' . $chartestPage;
$_SERVER['SCRIPT_FILENAME'] = ChartestPageRun::$file;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestQuery, $_GET);
$_POST = $chartestPost ?? [];
$_REQUEST = array_merge($_GET, $_POST);

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId); // the page's @session_start() picks this id up -> online row matches

chdir(dirname(ChartestPageRun::$file)); // the page uses ../includes/... relative includes

// Registered again from within shutdown, so the report runs after any shutdown function the page registers.
register_shutdown_function(static function (): void {
    register_shutdown_function([ChartestPageRun::class, 'report']);
});
ChartestPageRun::$bufferLevel = ob_get_level();
ob_start();
include ChartestPageRun::$file;
ChartestPageRun::$returned = true;
