<?php
// ---- tools/notification_digest.php --- lap 5.0.0 --- 2026.09.30 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20260930 Sawaneh Daily e-mail summary of unread notifications (topbar spec §3.4, Adam: summary only).
//                  Run once a day by cron, e.g.  0 7 * * *  php /var/www/html/saldi/tools/notification_digest.php
//                  Goes through every open company and mails the users who switched the summary on
//                  under Personal settings -> Notifications. Nothing is sent when there is nothing new.
//                  Options: --db=<database> one company only, --dry-run count without sending.
// 20261001 Sawaneh MySQL: select the company database after connecting (as online.php).

if (php_sapi_name() !== 'cli') { header('HTTP/1.1 403 Forbidden'); print 'CLI only'; exit; }
chdir(__DIR__);
// db_query.php's get_relative()/logging read these; a depth-3 URI keeps temp/ at the install root.
if (!isset($_SERVER['REQUEST_URI'])) $_SERVER['REQUEST_URI'] = '/install/tools/notification_digest.php';
if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = 'cli';

$options = getopt('', array('db:', 'dry-run'));
$onlyDb = isset($options['db']) ? (string) $options['db'] : '';
$dryRun = isset($options['dry-run']);

include(__DIR__ . '/../includes/db_query.php');
include(__DIR__ . '/../includes/connect.php');
include(__DIR__ . '/../includes/std_func.php');
include_once(__DIR__ . '/../includes/notifications.php');
include_once(__DIR__ . '/../includes/userFunctions.php');

$companies = array();
$q = db_select("select id, regnskab, db, lukket from regnskab order by id", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$name = trim((string) $r['db']);
	if ($name === '' || $name === $sqdb || trim((string) $r['lukket']) !== '' || ($onlyDb !== '' && $name !== $onlyDb)) {
		continue;
	}
	$companies[] = array('id' => (int) $r['id'], 'db' => $name);
}

$totals = array('sent' => 0, 'none' => 0, 'noemail' => 0, 'mailfailed' => 0);
foreach ($companies as $company) {
	$connection = db_connect($sqhost, $squser, $sqpass, $company['db']);
	if ($connection && ($db_type === 'mysqli' || $db_type === 'mysql') && !mysqli_select_db($connection, $company['db'])) {
		$connection = false;
	}
	if (!$connection) {
		fwrite(STDERR, "{$company['db']}: no connection\n");
		continue;
	}
	$db = $company['db'];
	$db_id = $company['id'];
	if (!notif_ready() || !tbl_exists('settings')) {
		continue;
	}
	$users = array();
	$q = db_select("select u.* from brugere u join settings s on s.user_id = u.id and s.var_grp = 'notifications' and s.var_name = 'digest' and s.var_value = '1'", __FILE__ . " linje " . __LINE__);
	while ($u = db_fetch_array($q)) {
		if (user_row_active($u)) {
			$users[] = $u;
		}
	}
	foreach ($users as $u) {
		$bruger_id = (int) $u['id'];
		$brugernavn = (string) $u['brugernavn'];
		$sprog = max(1, (int) ifset($u, 'language_id', 1));
		notif_refresh($bruger_id, (string) $u['rettigheder'], $sprog);
		if ($dryRun) {
			$result = (trim((string) $u['email']) === '') ? 'noemail' : (notif_digest_items($bruger_id, (string) get_settings_value('digest_last', 'notifications', '', $bruger_id)) ? 'sent' : 'none');
		} else {
			$result = notif_send_digest($u, $sprog);
		}
		$totals[$result]++;
		if ($result === 'mailfailed') {
			fwrite(STDERR, "{$company['db']}: mail to {$u['brugernavn']} failed\n");
		}
	}
}

printf("%s%d companies, %d sent, %d with nothing new, %d without e-mail, %d failed\n", $dryRun ? '[dry run] ' : '', count($companies), $totals['sent'], $totals['none'], $totals['noemail'], $totals['mailfailed']);
exit($totals['mailfailed'] > 0 ? 1 : 0);
