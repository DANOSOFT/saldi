<?php
// tests/characterization/support/run_rest_api.php
//
// Runs api/rest_api.php for one request in a child process, as an integration's HTTP call would:
// $_GET from the request, REMOTE_ADDR 127.0.0.1, the page's own directory as the working
// directory. The page answers with exit(json_encode(...)), which reaches stdout.
//
// argv: 1 = JSON object of the GET parameters (db, saldiuser, key, action, ...)
//
// History:
// 20261111 CL/SZ MB-154 Created.

if ($argc < 2) {
	fwrite(STDERR, "usage: php run_rest_api.php <get.json>\n");
	exit(2);
}
$get = json_decode($argv[1], true);
if (!is_array($get)) {
	fwrite(STDERR, "the GET parameters must be a JSON object\n");
	exit(2);
}

$_GET = $get;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/saldi/api/rest_api.php';

chdir(dirname(__DIR__, 3) . '/api'); // the page includes ../includes/... and writes ../temp/<db>/
include 'rest_api.php';
