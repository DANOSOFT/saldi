<?php
// --- includes/crmLeadGuard.php --- ver 5.1.1 --- 2026-11-11 ---
// LICENSE
// This program is free software under the GNU General Public License, version 2 or later.
// It may not be used in competition with Saldi.dk ApS without prior written permission.
// This program is distributed without any warranty; see the GNU General Public License.
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261110 CDX/ASR Keep CRM leads out of the debtor card and order entry until CRM converts them.
// 20261111 CDX/ASR Include the lead guards independently on master 5.1.1.

/**
 * Stop a generic debtor request that targets a CRM lead before its business writes.
 *
 * GET requests return to the lead in CRM; writes are rejected without converting the lead.
 * Entry pages buffer their authentication output until this check has completed.
 *
 * @param array<mixed> $candidateIds Account ids from every applicable request source.
 * @param string $requestMethod HTTP method of the current request.
 * @param int $languageId Current user's language, supplied by online.php.
 * @param array{inframe?: mixed, popup?: mixed} $context Existing shell/popup navigation context.
 * @param string $charset Encoding of translated page output, supplied by online.php.
 * @return void
 */
function crm_guard_lead_accounts(array $candidateIds, string $requestMethod, int $languageId, array $context = array(), string $charset = 'UTF-8'): void
{
	$ids = array();
	foreach ($candidateIds as $candidate) {
		if (is_scalar($candidate) && (int) $candidate > 0) {
			$ids[(int) $candidate] = (int) $candidate;
		}
	}
	if (!$ids) {
		return;
	}
	$row = db_fetch_array(db_select("select id from adresser where art = 'L' and id in (" . implode(',', $ids) . ") order by id limit 1", __FILE__ . ' line ' . __LINE__));
	if (!$row) {
		return;
	}
	$params = array('tab' => 'cust', 'drawer' => (int) $row['id']);
	foreach (array('inframe', 'popup') as $key) {
		if (!empty($context[$key])) {
			$params[$key] = '1';
		}
	}
	$url = '../debitor/crm.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
	$href = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
	if (in_array(strtoupper($requestMethod), array('GET', 'HEAD'), true)) {
		if (!headers_sent()) {
			header('Location: ' . $url, true, 302);
		} else {
			echo '<meta http-equiv="refresh" content="0;url=' . $href . '"><a href="' . $href . '">CRM</a>';
		}
		exit;
	}
	if (!headers_sent()) {
		http_response_code(409);
		header('Content-Type: text/html; charset=' . $charset);
	}
	$message = findtekst('crm.lead.guard|Denne kunde er et lead. Opret tilbud eller ordre fra CRM for at gøre kunden til debitor.', $languageId);
	echo '<p>' . htmlspecialchars($message, ENT_QUOTES, $charset) . '</p><p><a href="' . $href . '">CRM</a></p>';
	exit;
}

/**
 * Serialize customer-number allocation with the legacy debtor saver (lock key 5130513).
 * PostgreSQL can scope the lock to the caller's transaction; standalone cards hold it until the request ends.
 * MySQL/MariaDB named locks are tenant-specific and released on the same connection at request shutdown.
 *
 * @param bool $transaction Whether the caller owns a transaction.
 * @return bool Whether allocation may proceed.
 */
function crm_lock_customer_number(bool $transaction = false): bool
{
	global $db_type, $db, $connection;
	if ($db_type !== 'mysql' && $db_type !== 'mysqli') {
		$fn = $transaction ? 'pg_advisory_xact_lock' : 'pg_advisory_lock';
		return (bool) db_select("select $fn(5130513)", __FILE__ . ' linje ' . __LINE__);
	}
	static $held = array();
	$key = 'saldi_account_' . sha1((string) $db);
	if (isset($held[$key])) {
		return true;
	}
	$r = db_fetch_array(db_select("select GET_LOCK('$key', 10) as acquired", __FILE__ . ' linje ' . __LINE__));
	if (!$r || (int) $r['acquired'] !== 1) {
		return false;
	}
	$held[$key] = true;
	$lockedConnection = $connection;
	register_shutdown_function(static function () use ($lockedConnection, $key): void {
		try {
			mysqli_query($lockedConnection, "select RELEASE_LOCK('$key')");
		} catch (Throwable $e) {
			// A closed connection has already released its named locks.
		}
	});
	return true;
}
