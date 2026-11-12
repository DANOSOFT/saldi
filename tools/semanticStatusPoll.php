<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tools/semanticStatusPoll.php --- ver 5.1.1 --- 2026-11-12 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261109 CL/MMR SD-815 Created (draft). Command-line pull fallback for Semantic statuses: asks Semantic about the open send-log rows of one regnskab, or of every regnskab on the server, and applies the answers.
//                 Usage: php tools/semanticStatusPoll.php --db=<database> [--db=...] | --all [--limit=50]. Exit 0 = done, 1 = usage or configuration, 2 = some rows could not be checked.
// 20261112 CL/MMR SD-815 Ready for cron across all regnskaber: a lock so two runs never overlap, a time and row budget, rows not older than --max-age-days, a rotation so every regnskab gets its turn, and output only on errors.
//                 Each tenant connection is closed after use. The incoming-document pull will be called from semantic_poll_incoming() in includes/einvoice/semanticPoll.php.
//                 Exit 0 = done, 1 = usage or configuration, 2 = some rows or regnskaber failed, 3 = another run holds the lock.

/**
 * Injected by ../includes/connect.php, included below:
 * @var string $sqhost
 * @var string $squser
 * @var string $sqpass
 * @var string $sqdb
 * @var string $db_type
 * @var mixed  $connection
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
chdir(__DIR__);
if (!isset($_SERVER['REQUEST_URI'])) {
	// get_relative() in db_query.php counts the slashes: three give '../', the installation root (as for
	// signup/rydop.php), so database error logs go to temp/<db>.
	$_SERVER['REQUEST_URI'] = '/saldi/tools/cli.php';
	$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}
$webservice = true; // db_modify() returns instead of showing an alert and exiting

include_once(__DIR__ . '/../includes/einvoice/semanticPoll.php');

$usage = "usage: php tools/semanticStatusPoll.php --all | --db=<database> [--db=...]\n"
	. "       [--limit=50] [--max-age-days=30] [--budget=240] [--max-rows=500] [--verbose]\n"
	. "exit 0 = done, 1 = usage or configuration, 2 = some rows or regnskaber failed, 3 = another run holds the lock\n";
$arguments = semantic_poll_arguments(array_slice($argv ?? array(), 1));
if ($arguments['help']) {
	fwrite(STDOUT, $usage);
	exit(0);
}
if ($arguments['error'] !== '') {
	fwrite(STDERR, 'semanticStatusPoll: ' . $arguments['error'] . "\n" . $usage);
	exit(1);
}

$tempDir = realpath(__DIR__ . '/../temp');
if ($tempDir === false || !is_dir($tempDir) || !is_writable($tempDir)) {
	fwrite(STDERR, "semanticStatusPoll: the installation's temp directory is missing or not writable\n");
	exit(1);
}
$lock = semantic_poll_lock($tempDir . '/.ht_semanticStatusPoll.lock');
if ($lock['reason'] === 'busy') {
	// Another run is still working; it is not an error, so cron stays quiet.
	if ($arguments['verbose']) {
		fwrite(STDOUT, "semanticStatusPoll: another run holds the lock\n");
	}
	exit(3);
}
if ($lock['reason'] !== 'locked') {
	fwrite(STDERR, "semanticStatusPoll: could not open the lock file in temp\n");
	exit(1);
}
$statePath = $tempDir . '/.ht_semanticStatusPoll.state';

include(__DIR__ . '/../includes/connect.php');
include_once(__DIR__ . '/../includes/std_func.php');
date_default_timezone_set('Europe/Copenhagen');

$client = semantic_client(null, array('connectTimeout' => 5, 'timeout' => 20));
if (!$client instanceof SemanticClient) {
	fwrite(STDERR, "semanticStatusPoll: Semantic is not configured on this server (SALDI_SEMANTIC_* or the key file)\n");
	semantic_poll_unlock($lock['handle']);
	exit(1);
}

// The databases come from the master database's regnskab table, so a name on the command line must be a regnskab here.
$masterConnection = $connection;
$known = array();
$query = db_select("SELECT db FROM regnskab ORDER BY id", __FILE__ . " linje " . __LINE__);
while ($query && ($r = db_fetch_array($query))) {
	$name = trim((string)$r['db']);
	if ($name !== '' && $name !== $sqdb && semantic_webhook_db_name_valid($name)) {
		$known[$name] = true;
	}
}
$exitCode = 0;
if ($arguments['all']) {
	$targets = semantic_poll_rotate(array_keys($known), semantic_poll_state_read($statePath));
} else {
	$targets = array();
	foreach ($arguments['dbs'] as $name) {
		if (isset($known[$name])) {
			$targets[] = $name;
		} else {
			fwrite(STDERR, "semanticStatusPoll: $name is not a regnskab on this server\n");
			$exitCode = 1;
		}
	}
}

$budget = new SemanticPollBudget($arguments['budgetSeconds'], $arguments['maxRows']);
$options = array('limit' => $arguments['limit'], 'maxAgeDays' => $arguments['maxAgeDays']);
$pollTenant = function ($target, SemanticPollBudget $budget) use ($client, $options, $masterConnection, $sqhost, $squser, $sqpass, $sqdb, $db_type) {
	global $connection, $db;
	$result = semantic_poll_empty_result();
	$tenantConnection = db_connect($sqhost, $squser, $sqpass, $target, __FILE__ . " linje " . __LINE__);
	try {
		if (!$tenantConnection || !db_select_database($tenantConnection, $target)) {
			$result['failed'] = 1;
			$result['errors'][] = 'could not open the database';
			return $result;
		}
		$connection = $tenantConnection;
		$db = $target;
		return semantic_poll_tenant($client, $target, $db_type, $options, $budget);
	} finally {
		// One connection per regnskab at a time: a server with many regnskaber must not run out of connections.
		if ($tenantConnection) {
			db_close($tenantConnection);
		}
		$connection = $masterConnection;
		$db = $sqdb;
	}
};
$run = semantic_poll_run($targets, $budget, $pollTenant);
if ($arguments['all'] && $run['lastDone'] !== '') {
	semantic_poll_state_write($statePath, $run['lastDone']);
}
semantic_poll_unlock($lock['handle']);

$report = semantic_poll_report($run, $arguments['verbose']);
foreach ($report['stderr'] as $line) {
	fwrite(STDERR, $line . "\n");
}
foreach ($report['stdout'] as $line) {
	fwrite(STDOUT, $line . "\n");
}
exit(max($exitCode, semantic_poll_exit_code($run)));
