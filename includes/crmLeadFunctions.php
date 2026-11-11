<?php
// --- includes/crmLeadFunctions.php --- ver 5.1.1 --- 2026.11.11 ---
// Copyright (c) 2026 Danosoft ApS
// 20261110 CDX/ASR Keep prospects outside the debtor register until their first quotation or order.
// 20261111 CDX/ASR Enforce legacy CRM and sales permissions without the separate roles module.

require_once __DIR__ . '/crmLeadGuard.php';

/** Interpret the supported legacy rights: 1 writes, 2 only reads, every other value denies. */
function crm_legacy_access(string $rights, int $position, bool $write = false): bool
{
	$bit = substr($rights, $position, 1);
	return $write ? $bit === '1' : in_array($bit, array('1', '2'), true);
}

/** Use a loaded role provider when present; otherwise enforce the master's legacy module right. */
function crm_has_access(string $key, int $position, bool $write = false): bool
{
	global $bruger_id, $rettigheder, $revisor;
	if (!(int) $bruger_id || ($write && ((int) $bruger_id < 0 || !empty($revisor)))) {
		return false;
	}
	return function_exists('perm_can') ? perm_can($key, $write ? 'write' : 'read')
		: crm_legacy_access((string) $rettigheder, $position, $write);
}

/** Whether the authenticated user may read CRM customer data. */
function crm_can_read(): bool
{
	return crm_has_access('debitor.konti', 6);
}

/** Whether the authenticated user may change CRM customers and activities. */
function crm_can_write(): bool
{
	return crm_has_access('debitor.konti', 6, true);
}

/** Whether the authenticated user may create sales documents. */
function crm_can_order(): bool
{
	return crm_has_access('debitor.ordre', 5, true);
}

/** Read access also permits a user's own calendar subscription and notification read state. */
function crm_action_reads(string $action): bool
{
	return in_array($action, array('search', 'place', 'customer', 'data', 'busy', 'ical', 'notifications_seen'), true);
}

/**
 * Active debtor groups for the current fiscal year; the id is the group row, not its account code.
 *
 * @return array<int, array{id: int, n: string}>
 */
function crm_debtor_groups(): array
{
	global $regnaar;
	$active = crm_col_exists('grupper', 'inaktiv') ? " and coalesce(inaktiv, FALSE) = FALSE" : '';
	$q = db_select("select id, kodenr, beskrivelse from grupper where art = 'DG' and fiscal_year = " . (int) $regnaar
		. " and kodenr > 0$active order by kodenr", __FILE__ . ' linje ' . __LINE__);
	$out = array();
	while ($r = db_fetch_array($q)) {
		$out[] = array('id' => (int) $r['id'], 'n' => $r['kodenr'] . ': ' . crm_out($r['beskrivelse']));
	}
	return $out;
}

/**
 * Serialize lead saves/conversions in the caller's transaction, with locks released on commit/rollback.
 * The existing debtor-save advisory lock also protects its number allocation on PostgreSQL.
 *
 * @return bool Whether the tenant's own company row exists and is locked.
 */
function crm_lock_leads(): bool
{
	if (!crm_lock_customer_number(true)) {
		return false;
	}
	// A transactional row lock also serializes MySQL/MariaDB conversions without implicit commits from LOCK TABLES.
	$q = db_select("select id from adresser where art = 'S' order by id for update", __FILE__ . ' linje ' . __LINE__);
	return (bool) db_fetch_array($q);
}

/**
 * Find an existing customer with the same registration number, ignoring presentation spaces/hyphens.
 *
 * @return bool Whether another lead or debtor already has this nonblank number.
 */
function crm_lead_duplicate(string $cvr, int $except): bool
{
	$cvr = strtoupper(str_replace(array(' ', '-'), '', trim($cvr)));
	if ($cvr === '') {
		return false;
	}
	$value = crm_in($cvr);
	return (bool) db_fetch_array(db_select("select id from adresser where art in ('D', 'L') and id <> $except "
		. "and upper(replace(replace(trim(cvrnr), ' ', ''), '-', '')) = '$value' limit 1", __FILE__ . ' linje ' . __LINE__));
}

/**
 * Create or edit a CRM-only customer. The caller owns the transaction; no debtor number is allocated here.
 *
 * @param array<string, mixed> $in Customer fields from the CRM form.
 * @param int $actor Employee responsible for the creation history.
 * @return array{id?: int, error?: string}
 */
function crm_save_lead(array $in, int $actor): array
{
	if (!crm_can_write()) {
		return array('error' => 'noaccess');
	}
	$id = (int) ($in['id'] ?? 0);
	if ($id < 0) {
		return array('error' => 'customer');
	}
	$fields = array('n' => array('firmanavn', 90), 'tlf' => array('tlf', 60), 'mail' => array('email', 60),
		'addr1' => array('addr1', 60), 'addr2' => array('addr2', 60), 'zip' => array('postnr', 10),
		'by' => array('bynavn', 60), 'land' => array('land', 60), 'cvr' => array('cvrnr', 20));
	$values = array();
	foreach ($fields as $key => $spec) {
		if (isset($in[$key]) && !is_string($in[$key])) {
			return array('error' => 'fields');
		}
		$values[$key] = trim((string) ($in[$key] ?? ''));
		if (mb_strlen($values[$key], 'UTF-8') > $spec[1] || strpos($values[$key], "\0") !== false) {
			return array('error' => 'lead_length');
		}
	}
	if ($values['n'] === '') {
		return array('error' => 'lead_name');
	}
	if ($values['mail'] !== '' && !filter_var($values['mail'], FILTER_VALIDATE_EMAIL)) {
		return array('error' => 'lead_email');
	}
	$owner = (int) ($in['own'] ?? 0);
	$status = (string) ($in['st'] ?? '');
	if (!crm_lock_leads()) {
		return array('error' => 'customer');
	}
	$oldOwner = null;
	$oldStatus = null;
	if ($id > 0) {
		$r = db_fetch_array(db_select("select art, lukket, kontoansvarlig, status from adresser where id = $id for update", __FILE__ . ' linje ' . __LINE__));
		if (!$r || $r['art'] !== 'L' || $r['lukket'] === 'on') {
			return array('error' => 'lead_changed');
		}
		$oldOwner = (int) $r['kontoansvarlig'];
		$oldStatus = trim((string) $r['status']);
	}
	if ($owner < 0 || ($owner > 0 && $owner !== $oldOwner && !in_array($owner, array_column(crm_employees(), 'id'), true))) {
		return array('error' => 'fields');
	}
	if ($status !== '' && $status !== $oldStatus && !in_array($status, array_column(crm_statuses(), 'id'), true)) {
		return array('error' => 'fields');
	}
	if (crm_lead_duplicate($values['cvr'], $id)) {
		return array('error' => 'lead_duplicate');
	}
	$columns = $sqlValues = $set = array();
	foreach ($fields as $key => $spec) {
		$value = "'" . crm_in($values[$key]) . "'";
		$columns[] = $spec[0];
		$sqlValues[] = $value;
		$set[] = $spec[0] . ' = ' . $value;
	}
	$ownerSql = $owner ?: 'NULL';
	$statusSql = "'" . crm_in($status) . "'";
	if ($id > 0) {
		db_modify('update adresser set ' . implode(', ', $set) . ", kontoansvarlig = $ownerSql, status = $statusSql where id = $id and art = 'L'", __FILE__ . ' linje ' . __LINE__);
	} else {
		$today = date('Y-m-d');
		db_modify('insert into adresser (' . implode(', ', $columns) . ', art, kontonr, kontoansvarlig, status, oprettet, lukket) values ('
			. implode(', ', $sqlValues) . ", 'L', NULL, $ownerSql, $statusSql, '$today', '')", __FILE__ . ' linje ' . __LINE__);
		$r = db_fetch_array(db_select(crm_is_mysql() ? 'select last_insert_id() as id' : "select currval(pg_get_serial_sequence('adresser', 'id')) as id", __FILE__ . ' linje ' . __LINE__));
		$id = (int) $r['id'];
		crm_insert_entry($id, 'status', $actor, 0, crm_t('Lead oprettet'), null);
	}
	return array('id' => $id);
}

/**
 * Prepare a lead for its first quotation/order by promoting the same address row to a debtor.
 * A retry returns the existing debtor number. History, contacts and cases retain their original customer id.
 * The caller must commit/rollback; this function never starts a nested transaction or creates a document.
 *
 * @param array<string, mixed> $in cid, group (DG row id), kind (quote/order), optional deal id.
 * @param int $actor Employee recorded on the conversion history.
 * @return array{id?: int, nr?: string, error?: string}
 */
function crm_prepare_document(array $in, int $actor): array
{
	global $regnaar;
	if (!crm_can_write() || !crm_can_order()) {
		return array('error' => 'noaccess');
	}
	$id = (int) ($in['cid'] ?? 0);
	$kind = (string) ($in['kind'] ?? '');
	if ($id <= 0 || !in_array($kind, array('quote', 'order'), true)) {
		return array('error' => 'fields');
	}
	if ($kind === 'quote' && db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '3' and box4 = 'on'", __FILE__ . ' linje ' . __LINE__))) {
		return array('error' => 'quick');
	}
	if (!crm_lock_leads()) {
		return array('error' => 'customer');
	}
	$r = db_fetch_array(db_select("select * from adresser where id = $id and art in ('D', 'L') for update", __FILE__ . ' linje ' . __LINE__));
	if (!$r || $r['lukket'] === 'on') {
		return array('error' => 'customer');
	}
	$deal = (int) ($in['deal'] ?? 0);
	if ($deal > 0) {
		$d = db_fetch_array(db_select("select konto_id, state, ordre_id from crm_deals where id = $deal for update", __FILE__ . ' linje ' . __LINE__));
		if (!$d || (int) $d['konto_id'] !== $id || $d['state'] !== 'open') {
			return array('error' => 'customer');
		}
		if (!empty($d['ordre_id'])) {
			return array('error' => 'linked');
		}
	}
	if ($r['art'] === 'D') {
		return array('id' => $id, 'nr' => (string) $r['kontonr']);
	}
	$groupId = (int) ($in['group'] ?? 0);
	$active = crm_col_exists('grupper', 'inaktiv') ? ' and coalesce(inaktiv, FALSE) = FALSE' : '';
	$group = db_fetch_array(db_select("select kodenr from grupper where id = $groupId and art = 'DG' and fiscal_year = "
		. (int) $regnaar . " and kodenr > 0$active", __FILE__ . ' linje ' . __LINE__));
	if (!$group) {
		return array('error' => 'lead_group');
	}
	if (trim((string) $r['firmanavn']) === '') {
		return array('error' => 'lead_name');
	}
	if (crm_lead_duplicate(crm_out($r['cvrnr']), $id)) {
		return array('error' => 'lead_duplicate');
	}
	$number = (int) get_next_number('adresser', 'D');
	if ($number <= 0 || db_fetch_array(db_select("select id from adresser where kontonr = '$number'", __FILE__ . ' linje ' . __LINE__))) {
		return array('error' => 'lead_changed');
	}
	// Follow the debtor card's normal payment defaults, using the most common existing pair (fallback Netto + 8).
	$terms = db_fetch_array(db_select("select betalingsbet, betalingsdage, count(*) as uses from adresser where art = 'D' "
		. "and betalingsbet in ('Netto', 'Lb. md.', 'Kontant', 'Forud', 'Efterkrav') and betalingsdage >= 0 "
		. 'group by betalingsbet, betalingsdage order by count(*) desc, betalingsbet, betalingsdage limit 1', __FILE__ . ' linje ' . __LINE__));
	$payment = $terms ? (string) $terms['betalingsbet'] : 'Netto';
	$days = $terms ? (int) $terms['betalingsdage'] : 8;
	if (in_array($payment, array('Kontant', 'Forud', 'Efterkrav'), true)) {
		$days = 0;
	}
	$groupNo = (int) $group['kodenr'];
	$payment = db_escape_string($payment);
	$type = trim((string) $r['kontotype']) !== '' ? db_escape_string($r['kontotype']) : 'erhverv';
	db_modify("update adresser set art = 'D', kontonr = '$number', gruppe = $groupNo, betalingsbet = '$payment', "
		. "betalingsdage = $days, kontotype = '$type' where id = $id and art = 'L'", __FILE__ . ' linje ' . __LINE__);
	crm_insert_entry($id, 'status', $actor, 0, crm_t('Oprettet som debitor %s', (string) $number), null);
	return array('id' => $id, 'nr' => (string) $number);
}
