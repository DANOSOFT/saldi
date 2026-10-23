<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- includes/notifications.php --- lap 5.0.0 --- 2026.10.10 ---
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
// 20260927 Sawaneh Notification center (topbar spec §3): company-local notifications with
//                  per-user read state; v1 sources = Saldi news, missing/expiring fiscal year,
//                  batch expiry, bank-integration connection. Types can be switched off per user.
// 20260930 Sawaneh Daily e-mail summary of unread notifications (Adam: summary only, no mail per notification),
//                  sent by tools/notification_digest.php once a day for users who switched it on.
// 20261001 Sawaneh notif_ready() remembers its answer per company database (the digest script visits several).
// 20261002 Sawaneh notif_ready() checks the table's shape: an older unused table has the same name in most companies.
// 20261010 Sawaneh The Oversigt role keys dashboard.block.news / dashboard.block.expiry (front-page addendum 4) hide the
//                  news and batch-expiry items in the bell, as those banners now live there (topbar spec §3).
// 20261019 Sawaneh Review 2 (PR #803): the batch-expiry limit date is computed in PHP (no Postgres interval literal).
// 20261021 ASR Type 'crm': the CRM's daily follow-up summary (crm_notif_refresh() in includes/crmFunctions.php); the
//              CRM's event notifications (follow-up/deal assigned to you, deal won/lost) are written by crmFunctions.php.

/**
 * @return array<string, string> type -> label text id
 */
function notif_types(): array
{
	return array(
		'news'       => '5640|Nyheder',
		'warning'    => '5641|Advarsler',
		'suggestion' => '5642|Forslag',
		'system'     => '5643|System',
		'crm'        => '7512|CRM',
	);
}

function notif_ready(): bool
{
	// Per database: the daily summary script walks through every company in one process.
	static $ready = array();
	$key = isset($GLOBALS['db']) ? (string) $GLOBALS['db'] : '';
	if (!isset($ready[$key])) {
		// The 'type' column tells the notification center's table from the older unused one of the same name.
		$ready[$key] = function_exists('tbl_exists') && tbl_exists('notification_read')
			&& (bool) db_fetch_array(db_select("select column_name from information_schema.columns where table_name = 'notifications' and column_name = 'type'" . saldi_schema_filter() . "", __FILE__ . " linje " . __LINE__));
	}
	return $ready[$key];
}

/**
 * Types the user has switched off under Personal settings -> Notifications.
 *
 * @return array<int, string>
 */
function notif_disabled_types(int $brugerId): array
{
	$off = array();
	foreach (notif_types() as $type => $label) {
		if ((string) get_settings_value('type_' . $type, 'notifications', '1', $brugerId) === '0') {
			$off[] = $type;
		}
	}
	return $off;
}

/**
 * Items the user's role hides. Resolved for the logged-in user only; the digest script sees everything.
 */
function notif_role_filter(int $brugerId): string
{
	global $bruger_id;
	if (!function_exists('perm_can') || (int) $bruger_id !== $brugerId) {
		return '';
	}
	$sql = '';
	if (!perm_can('dashboard.block.news')) {
		$sql .= " and n.source_key not like 'news:%'";
	}
	if (!perm_can('dashboard.block.expiry')) {
		$sql .= " and n.source_key != 'batch:expiry'";
	}
	return $sql;
}

/**
 * Insert or refresh a generated notification. $sourceKey identifies the event so a poll
 * never creates duplicates; $userId null = broadcast to every user in the company.
 */
function notif_upsert(string $sourceKey, ?int $userId, string $type, string $title, string $body, string $link, ?string $expires): void
{
	$keySql = db_escape_string(substr($sourceKey, 0, 80));
	$userSql = ($userId === null) ? 'is null' : '= ' . (int) $userId;
	$r = db_fetch_array(db_select("select id from notifications where source_key = '$keySql' and user_id $userSql", __FILE__ . " linje " . __LINE__));
	$titleSql = db_escape_string($title);
	$bodySql = db_escape_string($body);
	$linkSql = db_escape_string($link);
	$expSql = $expires === null ? 'null' : "'" . db_escape_string($expires) . "'";
	if ($r) {
		db_modify("update notifications set title = '$titleSql', body = '$bodySql', link = '$linkSql', expires = $expSql where id = " . (int) $r['id'], __FILE__ . " linje " . __LINE__);
		return;
	}
	$userIns = ($userId === null) ? 'null' : (string) (int) $userId;
	db_modify("insert into notifications (user_id, type, title, body, link, expires, source_key) values ($userIns, '" . db_escape_string($type) . "', '$titleSql', '$bodySql', '$linkSql', $expSql, '$keySql')", __FILE__ . " linje " . __LINE__);
}

/**
 * Bring the v1 sources up to date for this user. Cheap enough to run on every poll,
 * but index/notifications.php throttles it per session anyway.
 */
function notif_refresh(int $brugerId, string $rettigheder, int $sprogId): void
{
	if (!notif_ready()) {
		return;
	}
	notif_source_news($sprogId);
	notif_source_fiscal_year($sprogId);
	// bruger_id is -1 for auditor/master-admin sessions: they get per-user items too.
	if ($brugerId !== 0) {
		notif_source_batches($brugerId, $rettigheder, $sprogId);
		notif_source_bank($brugerId, $sprogId);
		// CRM: today's follow-ups for the user's employee (debitor/crm.php).
		include_once(__DIR__ . '/crmFunctions.php');
		if ($brugerId > 0 && function_exists('crm_notif_refresh')) {
			crm_notif_refresh($brugerId, $rettigheder, $sprogId);
		}
	}
	// Prune what has expired (spec 3.4).
	db_modify("delete from notification_read where notification_id in (select id from notifications where expires is not null and expires < current_date)", __FILE__ . " linje " . __LINE__);
	db_modify("delete from notifications where expires is not null and expires < current_date", __FILE__ . " linje " . __LINE__);
}

/**
 * News from Saldi: the existing `settings nyhed` text, now read per user instead of the
 * company-wide dismissal the old dashboard banner used.
 */
function notif_source_news(int $sprogId): void
{
	$text = trim((string) get_settings_value('nyhed', 'dashboard', ''));
	$key = 'news:' . md5($text);
	// A new text replaces the previous news item.
	db_modify("delete from notification_read where notification_id in (select id from notifications where source_key like 'news:%' and source_key != '" . db_escape_string($key) . "')", __FILE__ . " linje " . __LINE__);
	db_modify("delete from notifications where source_key like 'news:%' and source_key != '" . db_escape_string($key) . "'", __FILE__ . " linje " . __LINE__);
	if ($text === '') {
		return;
	}
	notif_upsert($key, null, 'news', findtekst('5626|Nyt i Saldi', $sprogId), strip_tags($text), '', null);
}

/**
 * No open fiscal year covering today, or the current one ends within 45 days and the
 * next does not exist yet (spec 3.3).
 */
function notif_source_fiscal_year(int $sprogId): void
{
	$today = date('Y-m-d');
	$years = array();
	$q = db_select("select kodenr, beskrivelse, box1, box2, box3, box4, box5 from grupper where art = 'RA' order by box2, box1", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$start = sprintf('%04d-%02d-01', (int) $r['box2'], (int) $r['box1']);
		$end = date('Y-m-t', mktime(0, 0, 0, (int) $r['box3'], 1, (int) $r['box4']));
		$years[] = array('start' => $start, 'end' => $end, 'open' => ($r['box5'] === 'on'), 'label' => (string) $r['beskrivelse']);
	}
	$current = null;
	foreach ($years as $y) {
		if ($y['start'] <= $today && $y['end'] >= $today) {
			$current = $y;
		}
	}
	$link = '/systemdata/regnskabsaar.php';
	if ($current === null || !$current['open']) {
		notif_upsert('fiscal:missing', null, 'warning', findtekst('5627|Intet aktivt regnskabsår', $sprogId), findtekst('5646|Aktivér et regnskabsår under Indstillinger » Virksomhed » Regnskabsår', $sprogId), $link, date('Y-m-d', strtotime('+7 days')));
		return;
	}
	$daysLeft = (int) floor((strtotime($current['end']) - strtotime($today)) / 86400);
	if ($daysLeft > 45) {
		return;
	}
	$dayAfter = date('Y-m-d', strtotime($current['end'] . ' +1 day'));
	foreach ($years as $y) {
		if ($y['start'] <= $dayAfter && $y['end'] >= $dayAfter) {
			return;
		}
	}
	notif_upsert('fiscal:next:' . $current['end'], null, 'warning', findtekst('5629|Regnskabsåret slutter snart', $sprogId), sprintf(findtekst('5628|Opret det næste regnskabsår, før det nuværende slutter den %s', $sprogId), date('d-m-Y', strtotime($current['end']))), $link, $dayAfter);
}

/**
 * Batch expiry, parameterised per user like the old dashboard banner.
 */
function notif_source_batches(int $brugerId, string $rettigheder, int $sprogId): void
{
	if (substr($rettigheder, 9, 1) < '1' || !tbl_exists('batch_kob')) {
		return;
	}
	$days = function_exists('get_due_date_warning_days') ? get_due_date_warning_days($brugerId) : 30;
	$limitDate = date('Y-m-d', strtotime('+' . (int) $days . ' days'));
	$r = db_fetch_array(db_select("select count(*) as antal, sum(case when due_date < current_date then 1 else 0 end) as udloebet from batch_kob where due_date is not null and rest > 0 and due_date <= '$limitDate'", __FILE__ . " linje " . __LINE__));
	$antal = $r ? (int) $r['antal'] : 0;
	if ($antal <= 0) {
		db_modify("delete from notifications where source_key = 'batch:expiry' and user_id = $brugerId", __FILE__ . " linje " . __LINE__);
		return;
	}
	$body = sprintf(findtekst('5631|%s batch(er) udløber inden for %s dage – %s er allerede udløbet', $sprogId), $antal, $days, (int) $r['udloebet']);
	notif_upsert('batch:expiry', $brugerId, 'warning', findtekst('5630|Varer med udløb', $sprogId), $body, '/lager/udlobsrapport.php', date('Y-m-d', strtotime('+1 day')));
}

/**
 * Bank integration: the connection state lives in the session (OAuth login expiry),
 * so this is per user and only meaningful where the integration is enabled.
 */
function notif_source_bank(int $brugerId, int $sprogId): void
{
	$enabledFile = __DIR__ . '/../bank_integration/includes/enabled.php';
	if (!file_exists($enabledFile)) {
		return;
	}
	include_once($enabledFile);
	if (!function_exists('bankIntegrationEnabled') || !bankIntegrationEnabled()) {
		return;
	}
	$oauth = isset($_SESSION['OAuth']) ? $_SESSION['OAuth'] : null;
	$expired = ($oauth === null || $oauth === false);
	if (!$expired && isset($oauth['login']['expires'])) {
		$expired = (strtotime((string) $oauth['login']['expires']) <= time());
	}
	if (!$expired) {
		db_modify("delete from notifications where source_key = 'bank:expired' and user_id = $brugerId", __FILE__ . " linje " . __LINE__);
		return;
	}
	notif_upsert('bank:expired', $brugerId, 'system', findtekst('5632|Bankintegration: forbindelsen er udløbet', $sprogId), findtekst('5633|Forny forbindelsen til banken, før nye posteringer kan hentes', $sprogId), '/finans/kladdeliste.php', date('Y-m-d', strtotime('+1 day')));
}

/**
 * The latest notifications for the user with their read state.
 *
 * @return array<int, array{id: int, type: string, title: string, body: string, link: string, created: string, unread: bool}>
 */
function notif_list(int $brugerId, int $limit = 30): array
{
	$out = array();
	if (!notif_ready()) {
		return $out;
	}
	$off = notif_disabled_types($brugerId);
	$typeFilter = ($off ? " and n.type not in ('" . implode("','", array_map('db_escape_string', $off)) . "')" : '') . notif_role_filter($brugerId);
	$qtxt = "select n.id, n.type, n.title, n.body, n.link, n.created, r.read_at from notifications n "
		. "left join notification_read r on r.notification_id = n.id and r.user_id = $brugerId "
		. "where (n.user_id is null or n.user_id = $brugerId) and (n.expires is null or n.expires >= current_date)$typeFilter "
		. "order by n.created desc, n.id desc limit " . (int) $limit;
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[] = array(
			'id'      => (int) $r['id'],
			'type'    => (string) $r['type'],
			'title'   => (string) $r['title'],
			'body'    => (string) $r['body'],
			'link'    => (string) $r['link'],
			'created' => (string) $r['created'],
			'unread'  => empty($r['read_at']),
		);
	}
	return $out;
}

function notif_mark_read(int $brugerId, ?int $notificationId): void
{
	if (!notif_ready()) {
		return;
	}
	$ids = array();
	if ($notificationId !== null) {
		$ids[] = $notificationId;
	} else {
		foreach (notif_list($brugerId, 200) as $n) {
			if ($n['unread']) {
				$ids[] = $n['id'];
			}
		}
	}
	foreach ($ids as $id) {
		$id = (int) $id;
		$r = db_fetch_array(db_select("select 1 from notification_read where notification_id = $id and user_id = $brugerId", __FILE__ . " linje " . __LINE__));
		if (!$r) {
			db_modify("insert into notification_read (notification_id, user_id, read_at) values ($id, $brugerId, now())", __FILE__ . " linje " . __LINE__);
		}
	}
}

/**
 * "for 12 min. siden" style relative time for the panel.
 */
function notif_relative(string $created, int $sprogId): string
{
	$ts = strtotime($created);
	if (!$ts) {
		return '';
	}
	$diff = max(0, time() - $ts);
	if ($diff < 120) {
		return findtekst('5645|lige nu', $sprogId);
	}
	if ($diff < 3600) {
		return sprintf(findtekst('5635|for %s min. siden', $sprogId), (int) floor($diff / 60));
	}
	if ($diff < 86400) {
		return sprintf(findtekst('5636|for %s timer siden', $sprogId), (int) floor($diff / 3600));
	}
	if ($diff < 172800) {
		return findtekst('5637|i går', $sprogId);
	}
	return sprintf(findtekst('5638|for %s dage siden', $sprogId), (int) floor($diff / 86400));
}

/**
 * Unread notifications for the daily summary: newer than the last summary, of the types the
 * user has on.
 *
 * @return array<int, array{title: string, body: string, link: string, created: string}>
 */
function notif_digest_items(int $brugerId, string $since): array
{
	$off = notif_disabled_types($brugerId);
	$typeFilter = $off ? " and n.type not in ('" . implode("','", array_map('db_escape_string', $off)) . "')" : '';
	$sinceFilter = $since !== '' ? " and n.created > '" . db_escape_string($since) . "'" : '';
	$qtxt = "select n.title, n.body, n.link, n.created from notifications n "
		. "left join notification_read r on r.notification_id = n.id and r.user_id = $brugerId "
		. "where r.read_at is null and (n.user_id is null or n.user_id = $brugerId) and (n.expires is null or n.expires >= current_date)$typeFilter$sinceFilter "
		. "order by n.created desc limit 50";
	$out = array();
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[] = array('title' => (string) $r['title'], 'body' => (string) $r['body'], 'link' => (string) $r['link'], 'created' => (string) $r['created']);
	}
	return $out;
}

/**
 * Send one user's daily summary through the company's mail setup. The caller refreshes the
 * sources first (notif_refresh), as the bell does.
 *
 * @param array<string, mixed> $user row from brugere
 * @return string 'sent', 'none' (nothing new), 'noemail' or 'mailfailed'
 */
function notif_send_digest(array $user, int $sprogId): string
{
	$id = (int) $user['id'];
	$to = trim((string) $user['email']);
	if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
		return 'noemail';
	}
	$since = (string) get_settings_value('digest_last', 'notifications', '', $id);
	$items = notif_digest_items($id, $since);
	if (!$items) {
		return 'none';
	}
	$h = 'user_mail_h';
	$company = user_company_name();
	$body = '<p>' . $h(sprintf(findtekst('5964|Du har %s ulæste notifikationer i %s:', $sprogId), count($items), $company)) . '</p><ul>';
	foreach ($items as $item) {
		$body .= '<li><b>' . $h($item['title']) . '</b>' . ($item['body'] !== '' ? '<br>' . $h($item['body']) : '') . '</li>';
	}
	$body .= '</ul><p>' . $h(findtekst('5965|Log ind i Saldi og åbn klokken for at se dem.', $sprogId)) . '</p>';
	$body .= '<p style="color:#888;font-size:12px">' . $h(findtekst('5966|Du får denne mail, fordi du har slået den daglige opsummering til under Personlige indstillinger → Notifikationer.', $sprogId)) . '</p>';
	if (!user_send_mail($to, sprintf(findtekst('5967|Daglig opsummering fra %s', $sprogId), $company), $body)) {
		return 'mailfailed';
	}
	update_settings_value('digest_last', 'notifications', date('Y-m-d H:i:s'), 'Time of the last daily summary', $id);
	return 'sent';
}
