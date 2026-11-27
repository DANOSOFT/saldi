<?php
// tests/characterization/support/run_lagerstatus_page.php
//
// Child-process runner for lager/lagerstatus.php, the Lagerstatus report.
//
// lagerstatus.php is a legacy page script: it starts a session, includes
// connect/online/std_func at top level and prints as it goes, so it cannot be
// included inside the PHPUnit process. This runner fills $_GET from the query
// string, and $_POST from the post string when there is one, the way the
// browser does for the report's OK form (a POST) and for its CSV, page and
// Print links (GETs). It includes the page with the fabricated session
// CharacterizationEnv seeded in the `online` table. The page's HTML goes to
// stdout. The CSV it writes lands in temp/<tenant db>/lagerstatus.csv, as in
// production.
//
// argv: 1 = session id (must match the seeded online row)
//       2 = query string, e.g. "dato=30-11-2025&dateType=fakturadate&csv=1"
//       3 = post string (optional), e.g. "dato=30-11-2025&dateType=fakturadate"
//
// History:
// 20261105 CL/LH Created for LagerstatusDateTypeTest.
// 20261115 CL/ASR Integration: the page is included by __DIR__, not by a path the chdir() resolves (include-paths guardrail).

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_lagerstatus_page.php <session_id> <query string> [post string]\n");
    exit(2);
}
$sessionId = $argv[1];
$post = $argv[3] ?? null;

error_reporting(E_ERROR | E_PARSE); // the legacy page is warning-noisy; keep child output usable

$_SERVER['REQUEST_METHOD'] = $post === null ? 'GET' : 'POST';
$_SERVER['REQUEST_URI'] = '/saldi/lager/lagerstatus.php' . ($argv[2] === '' ? '' : '?' . $argv[2]);
$_SERVER['PHP_SELF'] = '/saldi/lager/lagerstatus.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';

parse_str($argv[2], $_GET);
$_POST = array();
if ($post !== null) {
    parse_str($post, $_POST);
}
$_REQUEST = array_merge($_GET, $_POST);

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId); // the page's @session_start() picks this id up -> online row matches

chdir(dirname(__DIR__, 3) . '/lager'); // the page uses ../includes/... and ../temp/... relative paths

include __DIR__ . '/../../../lager/lagerstatus.php';
