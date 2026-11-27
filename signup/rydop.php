<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- signup/rydop.php --- ver 5.1.0 --- 2026-11-02 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// Cleanup job for the signup flow (plan.md, section 2): deletes signups that were never activated
// after cleanup_days (default 14), rate-limit events older than a week and CVR lookups older
// than a month. Activated rows are kept: they link the account to its signup, and their token hash
// stays so the welcome mail's link can still say "already activated - go to login".
//
// Command line only, e.g. nightly from cron:
//   15 3 * * * cd <install>/signup && php rydop.php >> ../temp/signup-rydop.log 2>&1
//
// 20261014 CL/PS Created.
// 20261102 CL/ASR Header: activated rows keep their token hash.

if (php_sapi_name() !== 'cli') {
	http_response_code(404);
	exit;
}
chdir(__DIR__);
include(__DIR__ . "/../includes/signupIncludes/signupBootstrap.php");

/** @var string $db_type from includes/connect.php */
$days = max(1, (int)signup_setting('cleanup_days', '14'));
$mysql = ($db_type == 'mysql' || $db_type == 'mysqli');
$older = function ($col, $n, $unit) use ($mysql) {
	return $mysql ? "$col < DATE_SUB(NOW(), INTERVAL $n $unit)" : "$col < now() - interval '$n $unit'";
};

$count = function ($qtxt) {
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, true));
	return (int)ifset($r, 'n', 0);
};
$pending = $count("SELECT count(*) AS n FROM signup_pending WHERE status IN ('pending','failed') AND " . $older('created', $days, 'DAY'));
db_modify("DELETE FROM signup_pending WHERE status IN ('pending','failed') AND " . $older('created', $days, 'DAY'), __FILE__ . " linje " . __LINE__, true);
$events = $count("SELECT count(*) AS n FROM signup_events WHERE " . $older('ts', 7, 'DAY'));
db_modify("DELETE FROM signup_events WHERE " . $older('ts', 7, 'DAY'), __FILE__ . " linje " . __LINE__, true);
$cvr = $count("SELECT count(*) AS n FROM signup_cvr_cache WHERE " . $older('fetched', 30, 'DAY'));
db_modify("DELETE FROM signup_cvr_cache WHERE " . $older('fetched', 30, 'DAY'), __FILE__ . " linje " . __LINE__, true);
echo date('Y-m-d H:i:s') . " signup cleanup: $pending pending signups older than $days days, $events events, $cvr cached CVR lookups removed\n";
