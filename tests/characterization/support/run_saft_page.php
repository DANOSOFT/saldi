<?php
// tests/characterization/support/run_saft_page.php
//
// Child-process runner for finans/saft.php, the page Finans -> Rapporter ->
// SAF-T Financial redirects to (finans/rapport.php sends a Location header to
// saft.php with the period in the query string).
//
// saft.php is a legacy page script: it starts a session, includes
// connect/online/std_func at top level and prints as it goes, so it cannot be
// included inside the PHPUnit process. This runner fills $_GET from the query
// string the way the redirect does and includes the page with the fabricated
// session CharacterizationEnv seeded in the `online` table. The page's HTML
// goes to stdout; its form carries the serialized balances that
// finans/saftCreator.php writes into the XML.
//
// argv: 1 = session id (must match the seeded online row)
//       2 = query string, e.g. "regnaar=1&maaned_fra=1&maaned_til=12&aar_fra=2026&aar_til=2026&dato_fra=1&dato_til=31&rapportart=saft"
//
// History:
// 20261117 CL/ASR Created for SaftOpeningBalanceTest.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_saft_page.php <session_id> <query string>\n");
    exit(2);
}
$sessionId = $argv[1];

error_reporting(E_ERROR | E_PARSE); // the legacy page is warning-noisy; keep child output usable

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/saldi/finans/saft.php?' . $argv[2];
$_SERVER['PHP_SELF'] = '/saldi/finans/saft.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

parse_str($argv[2], $_GET);
$_POST = array();
$_REQUEST = $_GET;

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId); // the page's @session_start() picks this id up -> online row matches

chdir(dirname(__DIR__, 3) . '/finans'); // the page uses ../includes/... and ../importfiler/... relative paths

include __DIR__ . '/../../../finans/saft.php';
