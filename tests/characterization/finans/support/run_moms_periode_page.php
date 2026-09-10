<?php
// tests/characterization/finans/support/run_moms_periode_page.php
//
// Child-process runner for finans/moms_periode.php (SD-646). The page is a
// full session-driven page (session_start + includes/online.php), not a bare
// function, so it needs the same fabricated session + seeded `online` row as
// tests/restapi's page-driven checks - see MomsPeriodeLukEnv::bootstrapTenant().
//
// argv: 1 = session id (must match the seeded online row)
//       2 = optional GET query string (e.g. "vis_aar=2026")
//
// Prints the page's raw HTML output. The test asserts on stdout content
// (the friendly absent-schema message, or its absence) and on the exit code
// (a PHP fatal error terminates the child with a non-zero exit).
//
// History:
// 20260815 CL/SZ SD-646: created.

if ($argc < 2) {
    fwrite(STDERR, "usage: php run_moms_periode_page.php <session_id> [query_string]\n");
    exit(2);
}
$sessionId = $argv[1];
$queryString = $argv[2] ?? '';

error_reporting(E_ERROR | E_PARSE); // the legacy page is warning-noisy; keep child output usable

$_SERVER['REQUEST_URI'] = '/saldi/finans/moms_periode.php' . ($queryString !== '' ? "?$queryString" : '');
$_SERVER['PHP_SELF'] = '/saldi/finans/moms_periode.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'sd646test';
parse_str($queryString, $_GET);

ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId); // the page's own @session_start() picks this id up -> online row matches

chdir(dirname(__DIR__, 4) . '/finans'); // the page uses ../includes/... relative includes

include 'moms_periode.php';
