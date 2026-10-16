<?php
// ---- tools/onboarding_reminder.php --- lap 5.0.0 --- 2026.10.06 ---
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
// 20261006 Sawaneh Onboarding §4: one reminder e-mail when a started welcome guide has not been touched for 7 days,
//                  to the user who started it. Never more than one. Run daily from cron, like notification_digest.php:
//                  0 8 * * * php /var/www/html/<install>/tools/onboarding_reminder.php [--db=<database>] [--dry-run]

if (php_sapi_name() !== 'cli') { header('HTTP/1.1 403 Forbidden'); print 'CLI only'; exit; }
chdir(__DIR__);
if (!isset($_SERVER['REQUEST_URI'])) $_SERVER['REQUEST_URI'] = '/install/tools/onboarding_reminder.php';
if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = 'cli';

$options = getopt('', array('db:', 'dry-run'));
$onlyDb = isset($options['db']) ? (string) $options['db'] : '';
$dryRun = isset($options['dry-run']);

include(__DIR__ . '/../includes/db_query.php');
include(__DIR__ . '/../includes/connect.php');
include(__DIR__ . '/../includes/std_func.php');
include_once(__DIR__ . '/../includes/userFunctions.php');
include_once(__DIR__ . '/../includes/onboarding.php');

$companies = array();
$q = db_select("select id, regnskab, db, lukket from regnskab order by id", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$name = trim((string) $r['db']);
	if ($name === '' || $name === $sqdb || trim((string) $r['lukket']) !== '' || ($onlyDb !== '' && $name !== $onlyDb)) {
		continue;
	}
	$companies[] = array('id' => (int) $r['id'], 'db' => $name);
}

$totals = array('sent' => 0, 'noemail' => 0, 'mailfailed' => 0);
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
	if (!tbl_exists('settings') || onb_get('onboarding_state') !== 'started' || onb_get('onboarding_reminded') !== '') {
		continue;
	}
	$last = max((int) onb_get('onboarding_started_at'), (int) onb_get('onboarding_touched'));
	if ($last <= 0 || time() - $last < 7 * 86400) {
		continue;
	}
	$u = db_fetch_array(db_select("select * from brugere where id = " . (int) onb_get('onboarding_user'), __FILE__ . " linje " . __LINE__));
	$to = $u ? trim((string) $u['email']) : '';
	if ($to === '') {
		$totals['noemail']++;
		continue;
	}
	$sprog = max(1, (int) ifset($u, 'language_id', 1));
	$url = onb_get('onboarding_url');
	$body = '<p>' . user_mail_h(sprintf(findtekst('6822|Du er nået %s af %s trin i opsætningen af %s. Fortsæt, hvor du slap – det tager kun et par minutter.', $sprog), onb_done_count(), count(onb_steps_def()), user_company_name())) . '</p>'
		. '<p>' . user_mail_h(findtekst('6823|Log ind og vælg Fortsæt på kortet "Kom godt i gang" på din oversigt.', $sprog)) . '</p>'
		. ($url !== '' ? '<p><a href="' . user_mail_h($url) . '">' . user_mail_h($url) . '</a></p>' : '');
	if ($dryRun) {
		print "{$company['db']}: would remind $to\n";
		$totals['sent']++;
		continue;
	}
	if (user_send_mail($to, findtekst('6821|Din opsætning af Saldi venter', $sprog), $body)) {
		onb_set('onboarding_reminded', (string) time());
		$totals['sent']++;
	} else {
		$totals['mailfailed']++;
		fwrite(STDERR, "{$company['db']}: mail to $to failed\n");
	}
}

printf("%s%d companies, %d reminded, %d without e-mail, %d failed\n", $dryRun ? '[dry run] ' : '', count($companies), $totals['sent'], $totals['noemail'], $totals['mailfailed']);
exit($totals['mailfailed'] > 0 ? 1 : 0);
