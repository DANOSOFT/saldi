<?php
// tests/characterization/support/run_reminder_page.php
//
// Child-process runner for the reminder popups debitor/rapport.php opens from
// the reminder overview: debitor/afslut_rykker.php (Afslut) and
// debitor/rykkerprint.php (Udskriv, and inkasso.php and rykker.php's Send),
// and for the reminder page debitor/rykker.php and its Inkasso page debitor/inkassoprint.php.
//
// These are legacy page scripts: they start a session, include
// connect/online/std_func at top level, print HTML and exit(), so they run
// here and not in the PHPUnit process. The GET parameters are the ones the
// report puts in the popup URL, with a fabricated session that
// CharacterizationEnv has seeded in the `online` table. The page's HTML goes
// to stdout unbuffered, so an exit() inside the page still delivers
// everything printed before it.
//
// argv: 1 = session id (must match the seeded online row)
//       2 = page: afslut_rykker.php, rykkerprint.php, rykker.php or inkassoprint.php
//       3 = JSON object of GET parameters
//       4 = optional JSON object of POST parameters (rykker.php's form)
//
// History:
// 20261126 ASR Created for review I4: the popups check the report's ledger (thisDb) themselves.
// 20261126 ASR Review CL2-1: also the reminder page (rykker.php), with its form posted when argv 4 is given.
// 20261126 ASR Review LR2-5: also inkassoprint.php.
//              It loads ../phpmailer/class.phpmailer.php, which is not in the repository, unless a class PHPMailer exists.
//              An empty class stands in: the tests give the inkasso company no e-mail address, so the page never uses the mailer.

$pages = array('afslut_rykker.php', 'rykkerprint.php', 'rykker.php', 'inkassoprint.php');
if ($argc < 4 || !in_array($argv[2], $pages, true)) {
    fwrite(STDERR, "usage: php run_reminder_page.php <session_id> <" . implode('|', $pages) . "> <get json> [<post json>]\n");
    exit(2);
}
$sessionId = $argv[1];
$page = $argv[2];
$get = json_decode($argv[3], true);
$post = $argc > 4 ? json_decode($argv[4], true) : array();
if (!is_array($get) || !is_array($post)) {
    fwrite(STDERR, "the GET and POST parameters must be JSON objects\n");
    exit(2);
}

error_reporting(E_ERROR | E_PARSE); // the legacy pages are warning-noisy; keep child output usable

$_SERVER['REQUEST_URI'] = '/saldi/debitor/' . $page;
$_SERVER['PHP_SELF'] = '/saldi/debitor/' . $page;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

$_GET = $get;
$_POST = $post;
$_SERVER['REQUEST_METHOD'] = $post ? 'POST' : 'GET';
$_REQUEST = array_merge($_GET, $_POST);

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId); // the page's @session_start() picks this id up -> online row matches

chdir(dirname(__DIR__, 3) . '/debitor'); // the page uses ../includes/... relative includes

if ($page === 'inkassoprint.php' && !class_exists('PHPMailer', false)) {
    class PHPMailer {}   // see the history line above: never used without the inkasso company's e-mail address
}

include $page;
