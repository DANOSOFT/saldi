<?php
// tests/includes/support/run_db_connect_allowlist.php
//
// Child process for ledgerAllowList.test.php: db_connect() with $allowedLedgers set, as an installation's
// includes/connect.php sets it. A refused database ends the process with exit code 1 before any connection is tried;
// an allowed one goes on to pg_connect(), here to a port nobody listens on, and returns.
//
// argv: 1 = JSON list for $allowedLedgers, 2 = the master database ($sqdb), 3 = the database to open
//
// History:
// 20261127 ASR Created.

$allowedLedgers = json_decode($argv[1], true);
$sqdb = $argv[2];
$db_type = 'postgresql';
$db_encode = 'UTF8';
error_reporting(E_ERROR | E_PARSE);
require __DIR__ . '/../../../includes/db_query.php';
$connection = db_connect('127.0.0.1 port=9 connect_timeout=1', 'nobody', 'none', $argv[3]);
echo "tried\n";
exit(0);
