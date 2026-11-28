<?php
// tests/characterization/support/run_bogfor_page.php
//
// Child-process runner for the kassekladde posting engine (SD-601).
//
// finans/bogfor.php is a legacy PAGE SCRIPT: it starts a session, includes
// connect/online/std_func at top level, prints HTML, and exit()s on several
// branches. It cannot be included inside the PHPUnit process. This runner is
// executed as a separate `php` child by KassekladdePostingCharacterizationTest
// and drives the page exactly the way a browser POST does: it fabricates the
// session (seeded in the `online` table by CharacterizationEnv), sets
// $_GET/$_POST the way the kassekladde form does, and includes the page. The
// page's own dispatch then runs transaktion('begin'); bogfor(...);
// transaktion('commit') - the same code path production takes.
//
// argv: 1 = mode ('simuler' posts to the simulering table, 'bogfor' posts
//            for real to transaktioner; 'preview-simuler' and 'preview-bogfor'
//            open the page as Kassekladde's Simulér and Bogfør buttons do,
//            without a POST: the preview, which posts nothing)
//       2 = kladde_id (kladdeliste.id, fixture created by the test)
//       3 = session id (must match the seeded online row)
//       4 = optional session save path (default: the system temp dir). PHP's
//           files handler locks the session file until the request ends, so
//           two children with the same id run one after the other; separate
//           paths let them run at the same time, as two logins do. "" for
//           the default.
//       5 = optional: file the page's HTML is written to (an alert the page
//           shows), also when a branch ends the request with exit().
//
// Prints CHARTEST_PAGE_DONE on stdout if the include ran to completion.
// (Some page branches exit() before that - the test asserts on DB state,
// not on this marker.)
//
// History:
// 20260723 CL/LH SD-601: created.
// 20261117 CL/ASR Optional 4th argument: the session save path, for concurrent postings of one journal.
// 20261119 CL/ASR Optional 5th argument: the page's HTML is written to that file, also when the page ends with exit(); an empty 4th argument is the default session save path. Modes preview-simuler and preview-bogfor open the preview without a POST.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_bogfor_page.php <simuler|bogfor|preview-simuler|preview-bogfor> <kladde_id> <session_id> [<session_save_path> [<html file>]]\n");
    exit(2);
}
$mode = $argv[1];
$kladdeId = (int)$argv[2];
$sessionId = $argv[3];
$htmlFile = $argv[5] ?? '';
$preview = str_starts_with($mode, 'preview-');
$funktion = $preview ? substr($mode, strlen('preview-')) : $mode;
if ($funktion !== 'simuler' && $funktion !== 'bogfor') {
    fwrite(STDERR, "mode must be simuler, bogfor, preview-simuler or preview-bogfor\n");
    exit(2);
}

error_reporting(E_ERROR | E_PARSE); // the legacy page is warning-noisy; keep child output usable

$_SERVER['REQUEST_URI'] = '/saldi/finans/bogfor.php';
$_SERVER['PHP_SELF'] = '/saldi/finans/bogfor.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

$_GET['kladde_id'] = (string)$kladdeId;
$_GET['funktion'] = $funktion;
if (!$preview) {
    $_POST['kladde_id'] = (string)$kladdeId;
    $_POST[$mode] = 'on';
    $_POST['kladdenote'] = 'chartest';
}

ini_set('session.save_path', ($argv[4] ?? '') !== '' ? $argv[4] : sys_get_temp_dir());
session_id($sessionId); // the page's @session_start() picks this id up -> online row matches

chdir(dirname(__DIR__, 3) . '/finans'); // the page uses ../includes/... relative includes

if ($htmlFile !== '') {
    // A branch that ends the request with exit() leaves the page's output in the buffer: it is written from here, and
    // PHP flushes the buffer to stdout after the shutdown functions, as without the file.
    register_shutdown_function(static function () use ($htmlFile): void {
        if (ob_get_level() > 0) {
            file_put_contents($htmlFile, (string)ob_get_contents());
        }
    });
}
ob_start();
include 'bogfor.php';
$pageOutput = ob_get_clean();
if ($htmlFile !== '') {
    file_put_contents($htmlFile, $pageOutput);
}
if (getenv('SALDI_CHAR_DEBUG')) {
    fwrite(STDERR, $pageOutput . "\n");
}

fwrite(STDOUT, "CHARTEST_PAGE_DONE\n");
