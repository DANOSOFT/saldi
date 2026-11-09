<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tools/semanticStatusPoll.php --- ver 5.1.0 --- 2026-11-09 ---
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

/**
 * Injected by ../includes/connect.php, included below:
 * @var string $sqhost
 * @var string $squser
 * @var string $sqpass
 * @var string $sqdb
 * @var string $db_type
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
$webservice = true; // db_select()/db_modify() return instead of showing an alert and exiting

/**
 * Read the command line.
 *
 * @param list<string> $args $argv without the script name.
 * @return array{dbs: list<string>, all: bool, limit: int, help: bool, error: string}
 */
function semanticPollArguments(array $args) {
	$out = array('dbs' => array(), 'all' => false, 'limit' => 50, 'help' => false, 'error' => '');
	foreach ($args as $arg) {
		if ($arg === '--all') {
			$out['all'] = true;
		} elseif ($arg === '--help' || $arg === '-h') {
			$out['help'] = true;
		} elseif (strncmp($arg, '--db=', 5) === 0) {
			$out['dbs'][] = substr($arg, 5);
		} elseif (strncmp($arg, '--limit=', 8) === 0 && ctype_digit(substr($arg, 8))) {
			$out['limit'] = max(1, min(500, (int)substr($arg, 8)));
		} else {
			$out['error'] = "unknown argument: $arg";
		}
	}
	if ($out['error'] === '' && !$out['help'] && !$out['all'] && !$out['dbs']) {
		$out['error'] = 'give --db=<database> or --all';
	}
	return $out;
}

$arguments = semanticPollArguments(array_slice($argv ?? array(), 1));
if ($arguments['help'] || $arguments['error'] !== '') {
	if ($arguments['error'] !== '') {
		fwrite(STDERR, 'semanticStatusPoll: ' . $arguments['error'] . "\n");
	}
	fwrite(STDERR, "usage: php tools/semanticStatusPoll.php --db=<database> [--db=...] | --all [--limit=50]\n");
	exit($arguments['help'] ? 0 : 1);
}

include(__DIR__ . '/../includes/connect.php');
include_once(__DIR__ . '/../includes/std_func.php');
include_once(__DIR__ . '/../includes/einvoice/semanticClient.php');
include_once(__DIR__ . '/../includes/einvoice/semanticSendLog.php');
include_once(__DIR__ . '/../includes/einvoice/semanticStatus.php');
date_default_timezone_set('Europe/Copenhagen');

$client = semantic_client(null, array('connectTimeout' => 5, 'timeout' => 20));
if (!$client instanceof SemanticClient) {
	fwrite(STDERR, "semanticStatusPoll: Semantic is not configured on this server (SALDI_SEMANTIC_* or the key file)\n");
	exit(1);
}

// The databases come from the master database's regnskab table, so a name on the command line must be a regnskab here.
$known = array();
$query = db_select("SELECT db FROM regnskab ORDER BY id", __FILE__ . " linje " . __LINE__);
while ($query && ($r = db_fetch_array($query))) {
	$name = trim((string)$r['db']);
	if ($name !== '' && $name !== $sqdb && semantic_webhook_db_name_valid($name)) {
		$known[$name] = true;
	}
}
$targets = $arguments['all'] ? array_keys($known) : $arguments['dbs'];

$exitCode = 0;
foreach ($targets as $target) {
	if (!isset($known[$target])) {
		fwrite(STDERR, "semanticStatusPoll: $target is not a regnskab on this server\n");
		$exitCode = 1;
		continue;
	}
	$connection = db_connect($sqhost, $squser, $sqpass, $target, __FILE__ . " linje " . __LINE__);
	if (!$connection || !db_select_database($connection, $target)) {
		fwrite(STDERR, "semanticStatusPoll: could not open $target\n");
		$exitCode = 2;
		continue;
	}
	$db = $target;
	$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_name = 'einvoice_sendlog'";
	$qtxt .= einvoice_sendlog_is_mysql($db_type) ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		continue; // never updated since SD-815, so nothing was sent through Semantic
	}
	$result = semantic_status_poll($arguments['limit'], $client, $target);
	if ($result['checked'] > 0 || $result['messages']) {
		print date('Y-m-d H:i:s') . " $target: {$result['checked']} checked, {$result['changed']} changed, {$result['failed']} failed\n";
		foreach ($result['messages'] as $message) {
			print "  $message\n";
		}
	}
	if ($result['failed'] > 0 && $exitCode === 0) {
		$exitCode = 2;
	}
}
exit($exitCode);
