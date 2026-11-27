<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tools/stripe_arm_ledgers.php --- 2026.11.27
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261126 CL/ASR Created (review round 1, S2): the one-off deploy step for the server whose ledgers collect with Stripe.
//                 Since I15 a ledger other than the pinned one is armed only when its Stripe settings were saved in it on this server (armed_for).
//                 A ledger saved in itself before that update (target_db = the ledger, no armed_for) is unarmed until it is saved again: no payment on the payment page, the classic PDF instead of the link mail, no charge job and no hold on classic reminders.
//                 This tool lists the enabled ledgers with their arming state and, with --stamp, stores armed_for for the ones saved before the update, as a save on this server does.
//                 It never stamps a ledger whose settings were copied from another ledger or saved on another server.
//                 Run it right after the deploy, before the next charge job, and only on the server whose ledgers really collect: on any other server it would arm a copy.
// 20261127 ASR With $allowedLedgers in includes/connect.php only the listed ledgers are visited (includes/ledgerAllowList.php).
// 20261127 ASR With $allowedLedgers in includes/connect.php the ledger named for one run must be listed, the master database too (saldi_ledger_guard()); the master stays connected for the registry.
//
//   php tools/stripe_arm_ledgers.php [--db=<ledger db>] [--stamp]
//   Without --stamp it writes nothing. Each enabled ledger prints one line: "<ledger>: stripe_arm state=<state> action=<action>".
//   state: pinned | armed | saved_before_arming (stamped with --stamp) | other_server | not_saved_here (settings saved in another ledger, or never saved).

if (php_sapi_name() !== 'cli') { header('HTTP/1.1 403 Forbidden'); print 'CLI only'; exit; }
date_default_timezone_set('Europe/Copenhagen');
chdir(__DIR__);
if (!isset($_SERVER['REQUEST_URI'])) $_SERVER['REQUEST_URI'] = '/cli/tools/stripe_arm_ledgers.php';
if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = 'cli';
$options = getopt('', array('db::', 'stamp'));
$stamp = isset($options['stamp']);
$only = isset($options['db']) && $options['db'] !== false ? (string)$options['db'] : '';
if (isset($options['db']) && !preg_match('/^[a-z0-9_]{1,40}$/', $only)) { fwrite(STDERR, "--db must be a ledger db\n"); exit(1); }

include(__DIR__ . '/../includes/db_query.php');
include(__DIR__ . '/../includes/connect.php');
include(__DIR__ . '/../includes/std_func.php');
include_once(__DIR__ . '/../includes/stripeIncludes/stripeSettings.php');

if ($only !== '') saldi_ledger_guard($only);
$webservice = true;
$bruger_id = 0; $brugernavn = 'stripe_arm_ledgers';
$ledgers = array();
$q = db_select("select db from regnskab where coalesce(lukket, '') <> 'on' and db is not null and db <> ''"
	. ($only !== '' ? " and db = '" . db_escape_string($only) . "'" : '') . " order by id", __FILE__ . " linje " . __LINE__, true);
while ($r = db_fetch_array($q)) {
	if (preg_match('/^[a-z0-9_]{1,40}$/', (string)$r['db']) && saldi_ledger_allowed((string)$r['db'])) $ledgers[] = (string)$r['db'];
}
if ($only !== '' && !$ledgers) { fwrite(STDERR, "unknown or closed ledger $only\n"); exit(1); }

/**
 * The Stripe settings stripe_ledger_armed() reads, straight from the ledger's settings table.
 *
 * @return array<string, string> enabled, target_db and armed_for, where saved.
 */
function stripe_arm_ledgers_settings() {
	$set = array();
	$q = db_select("select var_name, var_value from settings where var_grp = 'stripe' and var_name in ('enabled', 'target_db', 'armed_for')", __FILE__ . " linje " . __LINE__);
	while ($q && $r = db_fetch_array($q)) $set[$r['var_name']] = (string)$r['var_value'];
	return $set;
}

$pinned = stripeConfigDb();
$failed = 0;
foreach ($ledgers as $ledger) {
	$connection = db_connect($sqhost, $squser, $sqpass, $ledger);
	if (!$connection) { fwrite(STDERR, "$ledger: no connection\n"); $failed++; continue; }
	$db = $ledger;
	$set = stripe_arm_ledgers_settings();
	if (($set['enabled'] ?? '') === 'on') {
		$targetDb = $set['target_db'] ?? '';
		$armedFor = $set['armed_for'] ?? '';
		$action = 'none';
		if ($ledger === $pinned) {
			$state = 'pinned';
		} elseif (stripe_ledger_set_up_here($ledger, $targetDb, $armedFor)) {
			$state = 'armed';
		} elseif ($targetDb !== $ledger) {
			$state = 'not_saved_here';
		} elseif ($armedFor !== '') {
			$state = 'other_server';
		} else {
			$state = 'saved_before_arming';
			if ($stamp) {
				update_settings_value('armed_for', 'stripe', db_escape_string(stripe_arming_key($ledger)), 'Serveren disse Stripe-indstillinger er gemt paa (sha256)');
				$check = stripe_arm_ledgers_settings();
				$action = stripe_ledger_set_up_here($ledger, $check['target_db'] ?? '', $check['armed_for'] ?? '') ? 'stamped' : 'failed';
				if ($action === 'failed') $failed++;
			} else {
				$action = 'run_with_stamp';
			}
		}
		print date('c') . " $ledger: stripe_arm state=$state action=$action\n";
	}
	db_close($connection);
}
exit($failed ? 1 : 0);
