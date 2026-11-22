<?php
// tests/characterization/support/run_ret_genfakt_page.php
//
// Child-process runner for «Ret abonnementsordrer» (debitor/ret_genfakt.php),
// with the form posted the way the browser posts it.
//
// The page starts a session and includes connect/online/std_func at top
// level, so it runs as a child process with a fabricated session that
// CharacterizationEnv seeded in `online`. The form fields are parsed with
// parse_str(), the same way PHP builds $_POST, so `ordreliste[]=7` arrives
// as an array exactly as it would from a browser.
//
// argv: 1 = form fields as a query string
//       2 = session id (must match the seeded online row)
//
// Prints one JSON line on stdout when the page finishes. A fatal error (the
// white screen) exits 255, which CharacterizationTestCase::runChild() fails on.
//   alert       - the text of the page's onLoad alert() as the browser would show it, or null.
//                 A message that is not a valid JavaScript string (the old quoting broke on an
//                 apostrophe) comes back as {"broken": <the raw argument>}.
//   red_genfakt - the names of the Genfakt. fields drawn with a red border
//
// History:
// 20261122 CL/SZ SST-879 Created for RetGenfaktSyncTest.
// 20261122 CL/SZ SST-927 Also reports the alert text and the Genfakt. fields marked in red, for RetGenfaktNextInvoiceTest.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_ret_genfakt_page.php <form fields> <session_id>\n");
    exit(2);
}
$chartestFields = $argv[1];
$chartestSessionId = $argv[2];

error_reporting(E_ALL);
set_error_handler(static function (): bool {
    return true; // the page's and the legacy includes' notices stay off stdout
});

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/saldi/debitor/ret_genfakt.php';
$_SERVER['PHP_SELF'] = '/saldi/debitor/ret_genfakt.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
parse_str($chartestFields, $_POST);

ini_set('session.save_path', sys_get_temp_dir());
session_id($chartestSessionId);

chdir(dirname(__DIR__, 3) . '/debitor'); // the page uses ../includes/... relative includes

ob_start();
include 'ret_genfakt.php';
$chartestOutput = ob_get_clean();
if (getenv('SALDI_CHAR_DEBUG')) {
    fwrite(STDERR, $chartestOutput . "\n");
}
$chartestAlert = null;
if (preg_match('/onLoad="javascript:alert\((.*?)\)">/s', $chartestOutput, $chartestMatch)) {
    $chartestArgument = html_entity_decode($chartestMatch[1], ENT_QUOTES, 'UTF-8');
    $chartestDecoded = json_decode($chartestArgument);
    if (is_string($chartestDecoded)) {
        $chartestAlert = $chartestDecoded;
    } elseif (preg_match("/^'((?:[^'\\\\\n]|\\\\.)*)'$/s", $chartestArgument, $chartestQuoted)) {
        $chartestAlert = stripcslashes($chartestQuoted[1]); // a valid single-quoted JavaScript string
    } else {
        $chartestAlert = ['broken' => $chartestArgument];
    }
}
preg_match_all('/style="[^"]*border:2px solid #FF0000[^"]*" name="(genfakt_\d+)"/', $chartestOutput, $chartestRed);
fwrite(STDOUT, json_encode(['done' => true, 'alert' => $chartestAlert, 'red_genfakt' => $chartestRed[1]]) . "\n");
