<?php
// --- includes/crmNotifications.php --- ver 5.1.1 --- 2026.11.11 ---
// LICENSE
// This program is free software under the GNU General Public License, version 2 or later.
// It may not be used in competition with Saldi.dk ApS without prior written permission.
// This program is distributed without any warranty; see the GNU General Public License.
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261111 CDX/ASR Store CRM assignment alerts and read state independently of the global notification center.

/** Whether the login/install migration has created the dedicated CRM notification table. */
function crm_notifications_ready(): bool
{
	return function_exists('tbl_exists') && tbl_exists('crm_notifications');
}

/**
 * Atomically create or refresh one recipient's event, retaining read state on ordinary refresh.
 * Moving a meeting/follow-up explicitly resets its read state through crm_notification_unread().
 * Values arrive as UTF-8 and are stored in the tenant's encoding.
 */
function crm_notification_upsert(string $key, int $user, string $title, string $body, string $url, ?string $expires): void
{
	if ($user <= 0 || !crm_notifications_ready()) {
		return;
	}
	$key = db_escape_string(substr($key, 0, 160));
	$title = db_escape_string(crm_to_db($title));
	$body = db_escape_string(crm_to_db($body));
	$url = db_escape_string((string) preg_replace('#^/debitor/#', '', $url));
	$expires = $expires === null ? 'NULL' : "'" . db_escape_string($expires) . "'";
	$sql = "insert into crm_notifications (event_key, bruger_id, title, body, url, created, expires, seen) "
		. "values ('$key', $user, '$title', '$body', '$url', CURRENT_DATE, $expires, FALSE)";
	$sql .= crm_is_mysql()
		? ' on duplicate key update title = VALUES(title), body = VALUES(body), url = VALUES(url), expires = VALUES(expires)'
		: ' on conflict (event_key, bruger_id) do update set title = EXCLUDED.title, body = EXCLUDED.body, url = EXCLUDED.url, expires = EXCLUDED.expires';
	db_modify($sql, __FILE__ . ' linje ' . __LINE__);
}

/** Reopen a moved event for its recipient without changing anyone else's read state. */
function crm_notification_unread(string $key, int $user): void
{
	if ($user > 0 && crm_notifications_ready()) {
		$key = db_escape_string($key);
		db_modify("update crm_notifications set seen = FALSE where event_key = '$key' and bruger_id = $user", __FILE__ . ' linje ' . __LINE__);
	}
}

/**
 * Return only the authenticated user's recent, unexpired notices and total unread count.
 *
 * @return array{notifications: list<array{id: int, title: string, body: string, url: string, created: string, seen: bool}>, notificationUnread: int}
 */
function crm_notifications_dataset(): array
{
	global $bruger_id;
	$out = array('notifications' => array(), 'notificationUnread' => 0);
	$user = (int) $bruger_id;
	if ($user <= 0 || !crm_can_read() || !crm_notifications_ready()) {
		return $out;
	}
	$where = "bruger_id = $user and (expires is null or expires >= CURRENT_DATE)";
	$count = db_fetch_array(db_select("select count(*) as n from crm_notifications where $where and seen = FALSE", __FILE__ . ' linje ' . __LINE__));
	$out['notificationUnread'] = (int) ($count['n'] ?? 0);
	$q = db_select("select id, title, body, url, created, seen from crm_notifications where $where order by seen, created desc, id desc limit 50", __FILE__ . ' linje ' . __LINE__);
	while ($row = db_fetch_array($q)) {
		$out['notifications'][] = array(
			'id' => (int) $row['id'], 'title' => crm_out($row['title']), 'body' => crm_out($row['body']),
			'url' => (string) preg_replace('#^/debitor/#', '', (string) $row['url']),
			'created' => (string) $row['created'], 'seen' => in_array($row['seen'], array(true, 1, '1', 't'), true),
		);
	}
	return $out;
}

/**
 * Mark personal notices read. A submitted recipient is never accepted.
 * NULL ids means all currently unexpired notices; an empty list means none.
 *
 * @param list<int>|null $ids Optional notice ids, still restricted to the current user.
 * @return void
 */
function crm_notifications_seen(?array $ids = null): void
{
	global $bruger_id;
	$user = (int) $bruger_id;
	if ($user <= 0 || !crm_can_read() || !crm_notifications_ready()) {
		return;
	}
	$where = "bruger_id = $user and (expires is null or expires >= CURRENT_DATE)";
	if ($ids !== null) {
		$ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
		if (!$ids) {
			return;
		}
		$where .= ' and id in (' . implode(',', $ids) . ')';
	}
	db_modify("update crm_notifications set seen = TRUE where $where", __FILE__ . ' linje ' . __LINE__);
}
