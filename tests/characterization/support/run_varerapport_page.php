<?php
// tests/characterization/support/run_varerapport_page.php
//
// Child-process runner for lager/rapport.php, the page behind Lager ->
// Rapporter (Varerapport).
//
// rapport.php is a legacy page script: it starts a session, includes
// connect/online/std_func at top level and prints as it goes, so it cannot be
// included inside the PHPUnit process. This runner fills $_GET the way the
// report's own links do (its back, CSV and page links are GET requests) and
// includes the page with the fabricated session CharacterizationEnv seeded in
// the `online` table. The page's HTML goes to stdout. The CSV it writes lands
// in temp/<tenant db>/salgsrapport.csv, as in production.
//
// argv: 1 = session id (must match the seeded online row)
//       2 = query string, e.g. "submit=ok&lagertal=on&varegruppe=2"
//
// History:
// 20261105 CL/LH Created for VarerapportGroupTotalsTest.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_varerapport_page.php <session_id> <query string>\n");
    exit(2);
}
$sessionId = $argv[1];

error_reporting(E_ERROR | E_PARSE); // the legacy page is warning-noisy; keep child output usable

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/saldi/lager/rapport.php?' . $argv[2];
$_SERVER['PHP_SELF'] = '/saldi/lager/rapport.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

parse_str($argv[2], $_GET);
$_POST = array();
$_REQUEST = $_GET;

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId); // the page's @session_start() picks this id up -> online row matches

chdir(dirname(__DIR__, 3) . '/lager'); // the page uses ../includes/... and ../temp/... relative paths

include 'rapport.php';
