<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/crmFunctions.php --- lap 5.0.0 --- 2026.10.20 ---
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
// 20261020 ASR CRM redesign (prototype_crm.html v1.1): schema, the data behind debitor/crm.php and the actions
//              of debitor/crmApi.php.
//
// Data model (existing unless marked new):
//   historik     konto_id (customer), ansat_id (our employee = owner), kontakt_id (customer's contact person, ansatte),
//                notat, notedate, kontaktet (date of the entry), kontaktes (follow-up date), completed, dokument,
//                type (new: note | opkald | mail | moede | status | deal)
//   adresser     status (id from grupper art='DebInfo' box3/box4), kontoansvarlig (owner), kontaktet/kontaktes
//                (kept in step: latest entry / earliest open follow-up, so the old lists still show them)
//   grupper SKN  status groups: box1 name, box2 status ids (tab separated), kodenr order,
//                box3 status a customer gets when won on the board, box4 when lost (new use, optional)
//   crm_deals    new table: deals per customer for the pipeline (phase 2)
//
// Open follow-up: kontaktes set and completed false. Rows written before this page have completed NULL; such a row
// only counts as open while no later entry exists for the customer (the old model kept one "next contact" per
// customer, so a newer note replaced it). Without this every old note would show up as overdue.

if (!function_exists('crm_ensure_schema')) {

function crm_is_mysql(): bool
{
	global $db_type;
	return in_array($db_type, array('mysql', 'mysqli'), true);
}

function crm_col_exists(string $table, string $col): bool
{
	$schema = crm_is_mysql() ? " and table_schema = DATABASE()" : " and table_schema = current_schema()";
	return (bool) db_fetch_array(db_select("select column_name from information_schema.columns where table_name = '$table' and column_name = '$col'$schema", __FILE__ . " linje " . __LINE__));
}

/**
 * Idempotent; called from includes/betweenUpdates.php at login and from debitor/crm.php (first use without a new login).
 */
function crm_ensure_schema(): void
{
	if (!crm_col_exists('historik', 'completed')) {
		db_modify("ALTER TABLE historik ADD COLUMN completed boolean", __FILE__ . " linje " . __LINE__);
	}
	if (!crm_col_exists('historik', 'type')) {
		db_modify("ALTER TABLE historik ADD COLUMN type varchar(10)", __FILE__ . " linje " . __LINE__);
	}
	if (!crm_col_exists('crm_deals', 'id')) {
		if (crm_is_mysql()) {
			db_modify("CREATE TABLE crm_deals (
				id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
				konto_id INT NOT NULL,
				title VARCHAR(120) NOT NULL,
				amount DECIMAL(15,2) DEFAULT 0,
				stage VARCHAR(10),
				expected_close DATE NULL,
				ansat_id INT NULL,
				state VARCHAR(10) NOT NULL DEFAULT 'open',
				stage_since DATE NULL,
				created DATE NULL,
				closed DATE NULL,
				lost_reason TEXT,
				INDEX crm_deals_konto_idx (konto_id)
			)", __FILE__ . " linje " . __LINE__);
		} else {
			db_modify("CREATE TABLE crm_deals (
				id SERIAL PRIMARY KEY,
				konto_id integer NOT NULL,
				title varchar(120) NOT NULL,
				amount numeric(15,2) DEFAULT 0,
				stage varchar(10),
				expected_close date,
				ansat_id integer,
				state varchar(10) NOT NULL DEFAULT 'open',
				stage_since date,
				created date,
				closed date,
				lost_reason text
			)", __FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX crm_deals_konto_idx ON crm_deals (konto_id)", __FILE__ . " linje " . __LINE__);
		}
	}
}

// ------------------------------------------------------------------ encoding (the JSON is always UTF-8)

function crm_out($s): string
{
	global $db_encode;
	$s = (string) $s;
	return ($db_encode === 'UTF8' || $s === '') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-15');
}

function crm_in($s): string
{
	global $db_encode;
	$s = trim((string) $s);
	if ($db_encode !== 'UTF8') {
		$s = mb_convert_encoding($s, 'ISO-8859-15', 'UTF-8');
	}
	return db_escape_string($s);
}

function crm_date($v): ?string
{
	$v = trim((string) $v);
	return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

function crm_bool($v): bool
{
	return in_array($v, array('t', 'true', true, 1, '1'), true);
}

function crm_ids(array $ids): string
{
	$ids = array_values(array_unique(array_map('intval', $ids)));
	return $ids ? implode(',', $ids) : '0';
}

// ------------------------------------------------------------------ reference data

function crm_statuses(): array
{
	$out = array();
	$r = db_fetch_array(db_select("select box3, box4 from grupper where art = 'DebInfo'", __FILE__ . " linje " . __LINE__));
	if ($r) {
		$ids = explode(chr(9), (string) $r['box3']);
		$names = explode(chr(9), (string) $r['box4']);
		foreach ($ids as $i => $id) {
			$id = trim($id);
			if ($id !== '') {
				$out[] = array('id' => $id, 'n' => crm_out(isset($names[$i]) ? trim($names[$i]) : $id));
			}
		}
	}
	return $out;
}

function crm_groups(): array
{
	$out = array();
	$q = db_select("select id, box1, box2, box3, box4 from grupper where art = 'SKN' order by kodenr, id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[] = array(
			'id'   => (int) $r['id'],
			'n'    => crm_out($r['box1']),
			's'    => array_values(array_filter(array_map('trim', explode(chr(9), (string) $r['box2'])), 'strlen')),
			'won'  => trim((string) $r['box3']),
			'lost' => trim((string) $r['box4']),
		);
	}
	return $out;
}

function crm_own_id(): int
{
	$r = db_fetch_array(db_select("select id from adresser where art = 'S'", __FILE__ . " linje " . __LINE__));
	return $r ? (int) $r['id'] : 0;
}

function crm_employees(): array
{
	$out = array();
	$own = crm_own_id();
	$q = db_select("select id, navn, initialer from ansatte where konto_id = $own and (lukket is null or lukket != 'on') order by navn", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[] = array('id' => (int) $r['id'], 'n' => crm_out($r['navn']), 'i' => crm_out($r['initialer']));
	}
	return $out;
}

// ------------------------------------------------------------------ customers

/**
 * @param string $where extra condition on adresser a
 * @return array<int, array<string, mixed>> keyed by id
 */
function crm_customers(string $where): array
{
	$out = array();
	$qtxt = "select a.id, a.kontonr, a.firmanavn, a.bynavn, a.tlf, a.email, a.status, a.kontoansvarlig, a.oprettet "
		. "from adresser a where a.art = 'D' and $where order by a.firmanavn";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[(int) $r['id']] = array(
			'id'    => (int) $r['id'],
			'nr'    => crm_out($r['kontonr']),
			'n'     => crm_out($r['firmanavn']),
			'by'    => crm_out($r['bynavn']),
			'tlf'   => crm_out($r['tlf']),
			'mail'  => crm_out($r['email']),
			'st'    => trim((string) $r['status']),
			'own'   => (int) $r['kontoansvarlig'],
			'since' => (string) $r['oprettet'],
			'rev'   => 0, 'inv' => 0, 'late' => 0,
			'stSince' => (string) $r['oprettet'],
			'ppl'   => array(),
		);
	}
	if (!$out) {
		return $out;
	}
	$ids = crm_ids(array_keys($out));
	$q = db_select("select id, konto_id, navn, tlf, mobil, email from ansatte where konto_id in ($ids) and (lukket is null or lukket != 'on') order by posnr, navn", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[(int) $r['konto_id']]['ppl'][] = array('id' => (int) $r['id'], 'n' => crm_out($r['navn']), 'tlf' => crm_out($r['mobil'] ?: $r['tlf']), 'mail' => crm_out($r['email']));
	}
	$year = date('Y') . '-01-01';
	$q = db_select("select konto_id, sum(sum) as s from ordrer where art in ('DO', 'DK') and status >= 3 and fakturadate >= '$year' and konto_id in ($ids) group by konto_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[(int) $r['konto_id']]['rev'] = round((float) $r['s']);
	}
	$today = date('Y-m-d');
	$q = db_select("select konto_id, count(*) as n, sum(case when forfaldsdate < '$today' then 1 else 0 end) as late from openpost where konto_id in ($ids) and (udlignet is null or udlignet != '1') and amount > 0 group by konto_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[(int) $r['konto_id']]['inv'] = (int) $r['n'];
		$out[(int) $r['konto_id']]['late'] = (int) $r['late'];
	}
	$q = db_select("select konto_id, max(kontaktet) as d from historik where type = 'status' and konto_id in ($ids) group by konto_id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['d']) {
			$out[(int) $r['konto_id']]['stSince'] = (string) $r['d'];
		}
	}
	return $out;
}

/** The customers the CRM shows: with a status, an entry, a deal or a next-contact date. */
function crm_customer_scope(): string
{
	return "(a.lukket is null or a.lukket != 'on') and ((a.status is not null and a.status != '') or a.kontaktes is not null "
		. "or exists (select 1 from historik h where h.konto_id = a.id) or exists (select 1 from crm_deals d where d.konto_id = a.id))";
}

function crm_search(string $term): array
{
	$t = crm_in($term);
	$where = "(a.lukket is null or a.lukket != 'on')";
	if ($t !== '') {
		$where .= " and (a.firmanavn like '%$t%' or a.kontonr like '$t%' or a.id in (select konto_id from ansatte where navn like '%$t%'))";
	}
	$all = crm_customers($where);
	return array_slice(array_values($all), 0, 10);
}

// ------------------------------------------------------------------ entries and deals

function crm_history(array $custIds): array
{
	$out = array();
	if (!$custIds) {
		return $out;
	}
	$ids = crm_ids($custIds);
	$from = date('Y-m-d', strtotime('-2 years'));
	$q = db_select("select id, konto_id, kontakt_id, ansat_id, notat, kontaktet, kontaktes, completed, type, dokument from historik "
		. "where konto_id in ($ids) and (kontaktet >= '$from' or (kontaktes is not null and completed is null) or completed = FALSE) "
		. "order by konto_id, kontaktet, id", __FILE__ . " linje " . __LINE__);
	$latest = array(); // last real entry per customer, for the legacy rule
	$rows = array();
	while ($r = db_fetch_array($q)) {
		$rows[] = $r;
		$type = (string) $r['type'];
		if ($type !== 'status' && $type !== 'deal') {
			$latest[(int) $r['konto_id']] = (int) $r['id'];
		}
	}
	foreach ($rows as $r) {
		$type = trim((string) $r['type']);
		$due = $r['kontaktes'] ? (string) $r['kontaktes'] : null;
		if ($r['completed'] === null || $r['completed'] === '') {
			$done = $due !== null && isset($latest[(int) $r['konto_id']]) && $latest[(int) $r['konto_id']] !== (int) $r['id'];
		} else {
			$done = crm_bool($r['completed']);
		}
		$out[] = array(
			'id'   => (int) $r['id'],
			'cid'  => (int) $r['konto_id'],
			'type' => $type !== '' ? $type : 'note',
			'by'   => (int) $r['ansat_id'],
			'to'   => (int) $r['kontakt_id'],
			'date' => (string) $r['kontaktet'],
			'note' => crm_out($r['notat']),
			'due'  => $due,
			'done' => $done,
			'file' => crm_out($r['dokument']),
		);
	}
	return $out;
}

function crm_deals(): array
{
	$out = array();
	$from = date('Y-m-d', strtotime('-1 year'));
	$q = db_select("select * from crm_deals where state = 'open' or closed >= '$from' order by id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$out[] = array(
			'id' => (int) $r['id'], 'cid' => (int) $r['konto_id'], 't' => crm_out($r['title']), 'v' => round((float) $r['amount']),
			'st' => trim((string) $r['stage']), 'state' => (string) $r['state'], 'close' => (string) $r['expected_close'],
			'own' => (int) $r['ansat_id'], 'since' => (string) ($r['stage_since'] ?: $r['created']), 'closed' => (string) $r['closed'],
			'reason' => crm_out($r['lost_reason']),
		);
	}
	return $out;
}

/** Everything the page needs. */
function crm_dataset(): array
{
	global $ansat_id;
	$customers = crm_customers(crm_customer_scope());
	$deals = crm_deals();
	// customers referenced by deals but outside the scope (closed accounts) are still needed for the cards
	$missing = array_diff(array_map(function ($d) { return $d['cid']; }, $deals), array_keys($customers));
	if ($missing) {
		$customers += crm_customers("a.id in (" . crm_ids($missing) . ")");
	}
	$jobs = (bool) db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box7 = 'on'", __FILE__ . " linje " . __LINE__));
	return array(
		'today'     => date('Y-m-d'),
		'me'        => (int) $ansat_id,
		'canWrite'  => function_exists('perm_can') ? perm_can('debitor.konti', 'write') : true,
		'jobs'      => $jobs,
		'emps'      => crm_employees(),
		'statuses'  => crm_statuses(),
		'groups'    => crm_groups(),
		'customers' => array_values($customers),
		'hist'      => crm_history(array_keys($customers)),
		'deals'     => $deals,
	);
}

// ------------------------------------------------------------------ actions (debitor/crmApi.php)

/** adresser.kontaktet / kontaktes follow the entries, so the old customer lists stay right. */
function crm_sync_customer(int $cid): void
{
	$r = db_fetch_array(db_select("select max(kontaktet) as d from historik where konto_id = $cid and (type is null or type not in ('status', 'deal'))", __FILE__ . " linje " . __LINE__));
	$last = $r && $r['d'] ? "'" . $r['d'] . "'" : 'NULL';
	$r = db_fetch_array(db_select("select min(kontaktes) as d from historik where konto_id = $cid and kontaktes is not null and completed = FALSE", __FILE__ . " linje " . __LINE__));
	$next = $r && $r['d'] ? "'" . $r['d'] . "'" : 'NULL';
	db_modify("update adresser set kontaktet = $last, kontaktes = $next where id = $cid", __FILE__ . " linje " . __LINE__);
}

function crm_insert_entry(int $cid, string $type, int $by, int $to, string $note, ?string $due, bool $done = false, string $date = ''): int
{
	$date = crm_date($date) ?: date('Y-m-d');
	$type = in_array($type, array('note', 'opkald', 'mail', 'moede', 'status', 'deal'), true) ? $type : 'note';
	$dueSql = $due ? "'$due'" : 'NULL';
	$doneSql = $due ? ($done ? 'TRUE' : 'FALSE') : 'NULL';
	db_modify("insert into historik (konto_id, kontakt_id, ansat_id, notat, notedate, kontaktet, kontaktes, completed, type) values "
		. "($cid, $to, $by, '" . crm_in($note) . "', '" . date('Y-m-d') . "', '$date', $dueSql, $doneSql, '$type')", __FILE__ . " linje " . __LINE__);
	$r = db_fetch_array(db_select("select max(id) as id from historik where konto_id = $cid", __FILE__ . " linje " . __LINE__));
	return (int) $r['id'];
}

function crm_status_name(string $id): string
{
	foreach (crm_statuses() as $s) {
		if ($s['id'] === $id) {
			return $s['n'];
		}
	}
	return $id;
}

/**
 * @return array<string, mixed> result for the client (ids for undo)
 */
function crm_action(string $action, array $in): array
{
	global $ansat_id;
	$me = (int) $ansat_id;
	switch ($action) {
		case 'add_entry':
			$cid = (int) $in['cid'];
			$due = crm_date($in['due'] ?? '');
			$closed = array();
			foreach ((array) ($in['close'] ?? array()) as $hid) {
				$hid = (int) $hid;
				db_modify("update historik set completed = TRUE where id = $hid and konto_id = $cid", __FILE__ . " linje " . __LINE__);
				$closed[] = $hid;
			}
			$owner = $due && !empty($in['owner']) ? (int) $in['owner'] : $me;
			$id = crm_insert_entry($cid, (string) ($in['type'] ?? 'note'), $owner, (int) ($in['to'] ?? 0), (string) ($in['note'] ?? ''), $due);
			crm_sync_customer($cid);
			return array('id' => $id, 'closed' => $closed);

		case 'update_entry':
			$hid = (int) $in['id'];
			$type = in_array($in['type'] ?? '', array('note', 'opkald', 'mail', 'moede'), true) ? $in['type'] : 'note';
			db_modify("update historik set notat = '" . crm_in($in['note'] ?? '') . "', type = '$type', kontakt_id = " . (int) ($in['to'] ?? 0) . " where id = $hid", __FILE__ . " linje " . __LINE__);
			return array('id' => $hid);

		case 'toggle_done':
			$hid = (int) $in['id'];
			$done = !empty($in['done']);
			db_modify("update historik set completed = " . ($done ? 'TRUE' : 'FALSE') . " where id = $hid", __FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select konto_id from historik where id = $hid", __FILE__ . " linje " . __LINE__));
			if ($r) crm_sync_customer((int) $r['konto_id']);
			return array('id' => $hid);

		case 'set_due':
			$hid = (int) $in['id'];
			$due = crm_date($in['due'] ?? '');
			if (!$due) return array('error' => 'date');
			db_modify("update historik set kontaktes = '$due', completed = FALSE where id = $hid", __FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select konto_id from historik where id = $hid", __FILE__ . " linje " . __LINE__));
			if ($r) crm_sync_customer((int) $r['konto_id']);
			return array('id' => $hid);

		case 'delete_entry':
			$hid = (int) $in['id'];
			$r = db_fetch_array(db_select("select * from historik where id = $hid", __FILE__ . " linje " . __LINE__));
			if (!$r) return array('error' => 'gone');
			db_modify("delete from historik where id = $hid", __FILE__ . " linje " . __LINE__);
			crm_sync_customer((int) $r['konto_id']);
			return array('row' => array('cid' => (int) $r['konto_id'], 'type' => (string) $r['type'], 'by' => (int) $r['ansat_id'], 'to' => (int) $r['kontakt_id'],
				'note' => crm_out($r['notat']), 'due' => $r['kontaktes'], 'done' => crm_bool($r['completed']), 'date' => (string) $r['kontaktet']));

		case 'restore_entry':
			$row = (array) $in['row'];
			$cid = (int) $row['cid'];
			$id = crm_insert_entry($cid, (string) $row['type'], (int) $row['by'], (int) $row['to'], (string) $row['note'], crm_date($row['due'] ?? ''), !empty($row['done']), (string) $row['date']);
			crm_sync_customer($cid);
			return array('id' => $id);

		case 'set_status':
			$cid = (int) $in['cid'];
			$to = preg_replace('/[^0-9A-Za-z_-]/', '', (string) $in['status']);
			$r = db_fetch_array(db_select("select status from adresser where id = $cid and art = 'D'", __FILE__ . " linje " . __LINE__));
			if (!$r) return array('error' => 'customer');
			$from = trim((string) $r['status']);
			db_modify("update adresser set status = '$to' where id = $cid", __FILE__ . " linje " . __LINE__);
			if (!empty($in['remove'])) {
				// undo: the logged move goes away with it
				db_modify("delete from historik where id = " . (int) $in['remove'] . " and konto_id = $cid and type = 'status'", __FILE__ . " linje " . __LINE__);
				return array('from' => $from);
			}
			$note = ($from !== '' ? crm_status_name($from) : 'Ingen status') . ' → ' . ($to !== '' ? crm_status_name($to) : 'Ingen status');
			if (!empty($in['how'])) $note .= ' (' . trim((string) $in['how']) . ')';
			$id = crm_insert_entry($cid, 'status', $me, 0, $note, null);
			return array('id' => $id, 'from' => $from);

		case 'save_deal':
			$id = (int) ($in['id'] ?? 0);
			$cid = (int) $in['cid'];
			$title = crm_in($in['t'] ?? '');
			if ($title === '' || !$cid) return array('error' => 'fields');
			$amount = round((float) str_replace(array('.', ','), array('', '.'), (string) ($in['v'] ?? '0')), 2);
			$stage = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($in['st'] ?? ''));
			$close = crm_date($in['close'] ?? '');
			$closeSql = $close ? "'$close'" : 'NULL';
			$own = (int) ($in['own'] ?? $me);
			$today = date('Y-m-d');
			if ($id) {
				db_modify("update crm_deals set konto_id = $cid, title = '$title', amount = $amount, expected_close = $closeSql, ansat_id = $own where id = $id", __FILE__ . " linje " . __LINE__);
			} else {
				db_modify("insert into crm_deals (konto_id, title, amount, stage, expected_close, ansat_id, state, stage_since, created) values "
					. "($cid, '$title', $amount, '$stage', $closeSql, $own, 'open', '$today', '$today')", __FILE__ . " linje " . __LINE__);
				$r = db_fetch_array(db_select("select max(id) as id from crm_deals where konto_id = $cid", __FILE__ . " linje " . __LINE__));
				$id = (int) $r['id'];
				crm_insert_entry($cid, 'deal', $me, 0, 'Handel oprettet: »' . trim((string) $in['t']) . '«', null);
			}
			return array('id' => $id);

		case 'move_deal':
			$id = (int) $in['id'];
			$d = db_fetch_array(db_select("select * from crm_deals where id = $id", __FILE__ . " linje " . __LINE__));
			if (!$d) return array('error' => 'deal');
			$before = array('stage' => $d['stage'], 'state' => $d['state'], 'stage_since' => $d['stage_since'], 'closed' => $d['closed'], 'lost_reason' => crm_out($d['lost_reason']));
			$today = date('Y-m-d');
			if (!empty($in['state'])) {
				$state = in_array($in['state'], array('won', 'lost', 'open'), true) ? $in['state'] : 'open';
				$reason = crm_in($in['reason'] ?? '');
				db_modify("update crm_deals set state = '$state', closed = " . ($state === 'open' ? 'NULL' : "'$today'") . ", lost_reason = '$reason' where id = $id", __FILE__ . " linje " . __LINE__);
				$label = $state === 'won' ? 'vundet' : ($state === 'lost' ? 'tabt' . (trim((string) ($in['reason'] ?? '')) !== '' ? ' – ' . trim((string) $in['reason']) : '') : 'genåbnet');
			} else {
				$stage = preg_replace('/[^0-9A-Za-z_-]/', '', (string) $in['st']);
				db_modify("update crm_deals set stage = '$stage', stage_since = '$today' where id = $id", __FILE__ . " linje " . __LINE__);
				$label = crm_status_name((string) $d['stage']) . ' → ' . crm_status_name($stage);
			}
			$hid = crm_insert_entry((int) $d['konto_id'], 'deal', $me, 0, 'Handel »' . crm_out($d['title']) . '«: ' . $label, null);
			return array('id' => $id, 'hid' => $hid, 'before' => $before);

		case 'restore_deal':
			$id = (int) $in['id'];
			$b = (array) $in['before'];
			$stage = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($b['stage'] ?? ''));
			$state = in_array($b['state'] ?? '', array('won', 'lost', 'open'), true) ? $b['state'] : 'open';
			$since = crm_date($b['stage_since'] ?? '');
			$closed = crm_date($b['closed'] ?? '');
			db_modify("update crm_deals set stage = '$stage', state = '$state', stage_since = " . ($since ? "'$since'" : 'NULL') . ", closed = " . ($closed ? "'$closed'" : 'NULL') . ", lost_reason = '" . crm_in($b['lost_reason'] ?? '') . "' where id = $id", __FILE__ . " linje " . __LINE__);
			if (!empty($in['remove'])) {
				db_modify("delete from historik where id = " . (int) $in['remove'] . " and type = 'deal'", __FILE__ . " linje " . __LINE__);
			}
			return array('id' => $id);

		case 'delete_deal':
			$id = (int) $in['id'];
			db_modify("delete from crm_deals where id = $id", __FILE__ . " linje " . __LINE__);
			return array('id' => $id);

		case 'save_groups':
			$keep = array();
			$pos = 1;
			foreach ((array) $in['groups'] as $g) {
				$g = (array) $g;
				$name = crm_in($g['n'] ?? '');
				$s = implode(chr(9), array_map(function ($x) { return preg_replace('/[^0-9A-Za-z_-]/', '', (string) $x); }, (array) ($g['s'] ?? array())));
				$won = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($g['won'] ?? ''));
				$lost = preg_replace('/[^0-9A-Za-z_-]/', '', (string) ($g['lost'] ?? ''));
				$gid = (int) ($g['id'] ?? 0);
				if ($gid > 0) {
					db_modify("update grupper set box1 = '$name', box2 = '$s', box3 = '$won', box4 = '$lost', kodenr = '$pos' where id = $gid and art = 'SKN'", __FILE__ . " linje " . __LINE__);
				} else {
					db_modify("insert into grupper (art, beskrivelse, box1, box2, box3, box4, kodenr) values ('SKN', 'Status knapper (CRM)', '$name', '$s', '$won', '$lost', '$pos')", __FILE__ . " linje " . __LINE__);
					$r = db_fetch_array(db_select("select max(id) as id from grupper where art = 'SKN'", __FILE__ . " linje " . __LINE__));
					$gid = (int) $r['id'];
				}
				$keep[] = $gid;
				$pos++;
			}
			db_modify("delete from grupper where art = 'SKN' and id not in (" . crm_ids($keep) . ")", __FILE__ . " linje " . __LINE__);
			update_settings_value("antalStatusKnapper", "crmvisning", count($keep), "Number of status buttons");
			return array('n' => count($keep));
	}
	return array('error' => 'action');
}

} // function_exists
