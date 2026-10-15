<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiReadTools.php --- ai/mcp-assistant --- 2026.10.03
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
//
// Live permission-checked reads with exhaustive field allowlists.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Include currency and conversion metadata on money rows.
// 20261002 CL/LH Ledger and account-kind words instead of F/D/K and D/S codes; input property descriptions.
// 20261002 CL/LH list_open_items returns totals over all matching items.
// 20261002 CL/LH Invoice paid from settled open items, legacy due-date rules, shared placeholder-rate conversion, creditor open items sorted largest debt first.
// 20261002 CL/LH search_parties ranks an exact account number, then an exact CVR, then account prefixes first.
// 20261002 CDX/LH Redact private attachment receipts from inbox tool responses.
// 20261003 CL/LH list_open_items rows carry item_id (openpost id) for reminder proposals.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.

declare(strict_types=1);

final class SaldiReadTools
{
	/** Permission-checked company dossier; no banking or private-note columns. @return array */
	public static function company(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'company.read');
		$company = $db->one("SELECT firmanavn, cvrnr FROM adresser WHERE art = 'S' ORDER BY id LIMIT 1") ?? [];
		$years = [];
		$current = null;
		foreach ($db->all("SELECT kodenr, box1, box2, box3, box4, box5, box10 FROM grupper WHERE art = 'RA' ORDER BY kodenr") as $r) {
			$year = SaldiToolRuntime::year($r);
			if ($year['id'] === $ctx->fiscalYear) {
				$current = $year;
			}
			$years[] = $year + ['active' => $r['box5'] === 'on' && $r['box10'] !== 'on'];
		}
		if ($current === null) {
			throw new SaldiRuntimeError('UNAVAILABLE', 'Fiscal year unavailable');
		}
		return SaldiToolRuntime::result(['company_name' => SaldiToolRuntime::text($company['firmanavn'] ?? ''), 'cvr' => SaldiToolRuntime::text($company['cvrnr'] ?? ''), 'base_currency' => $ctx->baseCurrency, 'fiscal_year' => $current, 'fiscal_years' => $years, 'user' => ['name' => SaldiToolRuntime::text($ctx->username), 'language' => $ctx->languageId], 'permissions' => SaldiPermissionService::effective($ctx, $db), 'revenue_hidden' => SaldiPermissionService::revenueHidden($ctx, $db), 'tenant_hash' => $ctx->tenantHash(), 'user_hash' => $ctx->userHash(), 'install_id' => $ctx->installId, 'today' => SaldiToolRuntime::today()]);
	}
	/** Approved company and own-user facts only. @return array */
	public static function memory(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'memory.read');
		$data = SaldiMemoryStore::active($db, $ctx->userId);
		$used = static fn(array $rows): int => array_sum(array_map(static fn(array $r): int => mb_strlen($r['fact']), $rows));
		$data['caps'] = ['company_used' => $used($data['company']), 'company_cap' => 2000, 'user_used' => $used($data['user']), 'user_cap' => 800];
		$cites = array_map(static fn(array $r): array => SaldiToolRuntime::citation('memory', $r['id'], $r['fact']), array_merge($data['company'], $data['user']));
		return SaldiToolRuntime::result($data, $cites, false, count($cites));
	}
	/** Search the current year's accounts, respecting P&L hiding. @return array */
	public static function accounts(SaldiTenantContext $ctx, SaldiDb $db, array $a): array
	{
		SaldiPermissionService::require($ctx, $db, 'accounts.read');
		$hidden = SaldiPermissionService::revenueHidden($ctx, $db);
		$where = 'regnskabsaar = ?';
		$params = [$ctx->fiscalYear];
		$query = trim($a['query'] ?? '');
		if ($query === '') {
			$where .= " AND kontotype IN ('D','S')";
		} else {
			$where .= ' AND (LOWER(COALESCE(beskrivelse, \'\')) LIKE ? ESCAPE \'!\' OR ' . SaldiToolRuntime::cast($db, 'kontonr') . ' LIKE ? ESCAPE \'!\')';
			$params[] = SaldiToolRuntime::like($query);
			$params[] = SaldiToolRuntime::like($query, true);
		}
		if ($hidden) {
			$where .= " AND kontotype <> 'D'";
		}
		if (($a['kind'] ?? 'all') !== 'all') {
			$where .= ' AND kontotype = ?';
			$params[] = $a['kind'] === 'pl' ? 'D' : 'S';
		}
		$page = SaldiToolRuntime::page($db, 'SELECT kontonr, beskrivelse, kontotype, moms, lukket FROM kontoplan WHERE ' . $where, 'kontonr, beskrivelse', $params, $a);
		$rows = [];
		$cites = [];
		foreach ($page['rows'] as $r) {
			$rows[] = ['account' => (string)$r['kontonr'], 'name' => SaldiToolRuntime::text($r['beskrivelse']), 'kind' => SaldiToolRuntime::accountKind($r['kontotype']), 'vat_code' => SaldiToolRuntime::text($r['moms']), 'closed' => $r['lukket'] === 'on'];
			$cites[] = self::accountCitation($ctx, $r);
		}
		return SaldiToolRuntime::result(['rows' => $rows, 'total' => $page['total'], 'revenue_hidden' => $hidden], $cites, $page['more']);
	}
	private static function accountCitation(SaldiTenantContext $ctx, array $r): array
	{
		return SaldiToolRuntime::citation('account', $r['kontonr'] . '@' . $ctx->fiscalYear, $r['kontonr'] . ' ' . ($r['beskrivelse'] ?? ''));
	}
	private static function account(SaldiTenantContext $ctx, SaldiDb $db, string $account): array
	{
		$r = $db->one('SELECT kontonr, beskrivelse, kontotype, primo FROM kontoplan WHERE kontonr = ? AND regnskabsaar = ?', [$account, $ctx->fiscalYear]);
		if (!$r || (SaldiPermissionService::revenueHidden($ctx, $db) && $r['kontotype'] === 'D')) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Account not found');
		}
		return $r;
	}
	/** SQL numeric opening/movement/closing, shared by both balance tools. @return array */
	private static function amounts(SaldiDb $db, array $r, string $from, string $to, string $start): array
	{
		$totals = $db->one('SELECT COALESCE(SUM(CASE WHEN transdate < ? THEN CAST(COALESCE(debet,0) AS DECIMAL(30,6)) - CAST(COALESCE(kredit,0) AS DECIMAL(30,6)) ELSE 0 END),0) AS before_range, COALESCE(SUM(CASE WHEN transdate >= ? THEN CAST(COALESCE(debet,0) AS DECIMAL(30,6)) ELSE 0 END),0) AS debit, COALESCE(SUM(CASE WHEN transdate >= ? THEN CAST(COALESCE(kredit,0) AS DECIMAL(30,6)) ELSE 0 END),0) AS credit FROM transaktioner WHERE kontonr = ? AND transdate >= ? AND transdate <= ?', [$from, $from, $from, $r['kontonr'], $start, $to]);
		$values = $db->one('SELECT CAST(? AS DECIMAL(30,6)) + CAST(? AS DECIMAL(30,6)) AS opening, CAST(? AS DECIMAL(30,6)) - CAST(? AS DECIMAL(30,6)) AS movement, CAST(? AS DECIMAL(30,6)) + CAST(? AS DECIMAL(30,6)) + CAST(? AS DECIMAL(30,6)) - CAST(? AS DECIMAL(30,6)) AS closing', [$r['primo'] ?? '0', $totals['before_range'], $totals['debit'], $totals['credit'], $r['primo'] ?? '0', $totals['before_range'], $totals['debit'], $totals['credit']]);
		$values += ['debit' => $totals['debit'], 'credit' => $totals['credit']];
		return array_map(SaldiToolRuntime::money(...), $values);
	}
	/** One account's mid-year range balances. @return array */
	public static function balance(SaldiTenantContext $ctx, SaldiDb $db, array $a): array
	{
		SaldiPermissionService::require($ctx, $db, 'ledger.read');
		[$from, $to, $start] = SaldiToolRuntime::range($ctx, $db, $a);
		$r = self::account($ctx, $db, $a['account']);
		return SaldiToolRuntime::result(['account' => (string)$r['kontonr'], 'name' => SaldiToolRuntime::text($r['beskrivelse']), 'kind' => SaldiToolRuntime::accountKind($r['kontotype']), 'from' => $from, 'to' => $to, 'currency' => $ctx->baseCurrency, 'revenue_hidden' => SaldiPermissionService::revenueHidden($ctx, $db)] + self::amounts($db, $r, $from, $to, $start), [self::accountCitation($ctx, $r)]);
	}
	/** Paged posted transactions; the ledger stores debit/credit in base currency. @return array */
	public static function ledger(SaldiTenantContext $ctx, SaldiDb $db, array $a): array
	{
		SaldiPermissionService::require($ctx, $db, 'ledger.read');
		[$from, $to] = SaldiToolRuntime::range($ctx, $db, $a);
		$account = self::account($ctx, $db, $a['account']);
		$page = SaldiToolRuntime::page($db, 'SELECT id, transdate, bilag, beskrivelse, debet, kredit, kladde_id FROM transaktioner WHERE kontonr = ? AND transdate >= ? AND transdate <= ?', 'transdate, id', [$a['account'], $from, $to], $a);
		$rows = [];
		$cites = [self::accountCitation($ctx, $account)];
		foreach ($page['rows'] as $r) {
			$jid = (int)$r['kladde_id'] > 0 ? (int)$r['kladde_id'] : null;
			$rows[] = ['date' => (string)$r['transdate'], 'bilag' => (int)$r['bilag'], 'text' => SaldiToolRuntime::text($r['beskrivelse']), 'debit' => SaldiToolRuntime::money($r['debet']), 'credit' => SaldiToolRuntime::money($r['kredit']), 'journal_id' => $jid, 'currency' => $ctx->baseCurrency];
			if ($jid !== null) {
				$cites[] = SaldiToolRuntime::citation('journal', $jid, 'Kassekladde ' . $jid, 'journal');
			}
		}
		return SaldiToolRuntime::result(['rows' => $rows, 'total' => $page['total'], 'currency' => $ctx->baseCurrency, 'revenue_hidden' => SaldiPermissionService::revenueHidden($ctx, $db)], $cites, $page['more']);
	}
	/** Trial balance with the same unrounded opening and closing formulas. @return array */
	public static function trial(SaldiTenantContext $ctx, SaldiDb $db, array $a): array
	{
		SaldiPermissionService::require($ctx, $db, 'ledger.read');
		[$from, $to, $start] = SaldiToolRuntime::range($ctx, $db, $a);
		$hidden = SaldiPermissionService::revenueHidden($ctx, $db);
		// Filter zero accounts in SQL before pagination, using unrounded numeric values.
		$select = "SELECT k.kontonr, k.beskrivelse, k.kontotype, COALESCE(k.primo,0) AS primo FROM kontoplan k WHERE k.regnskabsaar = ? AND k.kontotype IN ('D','S')" . ($hidden ? " AND k.kontotype <> 'D'" : '');
		$params = [$ctx->fiscalYear];
		if (!($a['include_zero'] ?? false)) {
			$select .= ' AND (COALESCE(k.primo,0) + COALESCE((SELECT SUM(COALESCE(t.debet,0) - COALESCE(t.kredit,0)) FROM transaktioner t WHERE t.kontonr = k.kontonr AND t.transdate >= ? AND t.transdate < ?),0) <> 0 OR COALESCE((SELECT SUM(COALESCE(t.debet,0)) FROM transaktioner t WHERE t.kontonr = k.kontonr AND t.transdate >= ? AND t.transdate <= ?),0) <> 0 OR COALESCE((SELECT SUM(COALESCE(t.kredit,0)) FROM transaktioner t WHERE t.kontonr = k.kontonr AND t.transdate >= ? AND t.transdate <= ?),0) <> 0)';
			array_push($params, $start, $from, $from, $to, $from, $to);
		}
		$page = SaldiToolRuntime::page($db, $select, 'k.kontonr', $params, $a);
		$rows = [];
		$cites = [];
		foreach ($page['rows'] as $r) {
			$amounts = self::amounts($db, $r, $from, $to, $start);
			unset($amounts['movement']);
			$rows[] = ['account' => (string)$r['kontonr'], 'name' => SaldiToolRuntime::text($r['beskrivelse']), 'kind' => SaldiToolRuntime::accountKind($r['kontotype']), 'currency' => $ctx->baseCurrency] + $amounts;
			$cites[] = self::accountCitation($ctx, $r);
		}
		return SaldiToolRuntime::result(['rows' => $rows, 'total' => $page['total'], 'currency' => $ctx->baseCurrency, 'revenue_hidden' => $hidden], $cites, $page['more']);
	}
	private static function journalStatus(mixed $status): string
	{
		return match ($status ?? '-') { '-', '!' => 'open', 'S' => 'simulated', default => 'posted' };
	}
	/** Filtered journal headers with line counts. @return array */
	public static function journals(SaldiTenantContext $ctx, SaldiDb $db, array $a): array
	{
		SaldiPermissionService::require($ctx, $db, 'journal.read');
		$where = '1 = 1';
		$params = [];
		$status = $a['status'] ?? 'open';
		if ($status !== 'all') {
			$where .= $status === 'open' ? " AND COALESCE(k.bogfort,'-') IN ('-','!')" : " AND COALESCE(k.bogfort,'-') NOT IN ('-','!','S')";
		}
		if (!empty($a['query'])) {
			$where .= ' AND LOWER(COALESCE(k.kladdenote,\'\')) LIKE ? ESCAPE \'!\'';
			$params[] = SaldiToolRuntime::like($a['query']);
		}
		$page = SaldiToolRuntime::page($db, 'SELECT k.id, k.kladdedate, k.kladdenote, k.bogfort, k.oprettet_af, (SELECT COUNT(*) FROM kassekladde l WHERE l.kladde_id = k.id) AS lines FROM kladdeliste k WHERE ' . $where, 'k.id DESC', $params, $a);
		$rows = [];
		$cites = [];
		foreach ($page['rows'] as $r) {
			$rows[] = ['journal_id' => (int)$r['id'], 'date' => (string)$r['kladdedate'], 'note' => SaldiToolRuntime::text($r['kladdenote']), 'status' => self::journalStatus($r['bogfort']), 'lines' => (int)$r['lines'], 'created_by' => SaldiToolRuntime::text($r['oprettet_af'])];
			$cites[] = SaldiToolRuntime::citation('journal', (int)$r['id'], 'Kassekladde ' . $r['id'], 'journal');
		}
		return SaldiToolRuntime::result(['rows' => $rows, 'total' => $page['total']], $cites, $page['more']);
	}
	private static function side(SaldiTenantContext $ctx, SaldiDb $db, array $r, bool $debit): array
	{
		$type = (string)($r[$debit ? 'd_type' : 'k_type'] ?? '');
		$account = (string)($r[$debit ? 'debet' : 'kredit'] ?? '0');
		$name = '';
		if ($account === '' || $account === '0' || !in_array($type, ['F', 'D', 'K'], true)) {
			return ['ledger' => 'none', 'account' => '', 'name' => ''];
		}
		if ($type === 'F') {
			$name = $db->value('SELECT beskrivelse FROM kontoplan WHERE kontonr = ? AND regnskabsaar = ?', [$account, $ctx->fiscalYear]);
		} elseif (in_array($type, ['D', 'K'], true)) {
			$name = $db->value('SELECT firmanavn FROM adresser WHERE kontonr = ? AND art = ?', [$account, $type]);
		}
		return ['ledger' => SaldiToolRuntime::ledgerWord($type), 'account' => $account, 'name' => SaldiToolRuntime::text($name)];
	}
	/** Journal contents capped at 200 lines; revision includes all lines. @return array */
	public static function journal(SaldiTenantContext $ctx, SaldiDb $db, array $a): array
	{
		SaldiPermissionService::require($ctx, $db, 'journal.read');
		$r = $db->one('SELECT id, kladdedate, kladdenote, bogfort FROM kladdeliste WHERE id = ?', [$a['journal_id']]);
		if (!$r) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Journal not found');
		}
		$raw = $db->all('SELECT * FROM kassekladde WHERE kladde_id = ? ORDER BY pos, id LIMIT 201', [$a['journal_id']]);
		$lines = [];
		$cites = [SaldiToolRuntime::citation('journal', $a['journal_id'], 'Kassekladde ' . $a['journal_id'], 'journal')];
		foreach (array_slice($raw, 0, 200) as $l) {
			$currency = (int)$l['valuta'] === 0 ? $ctx->baseCurrency : $db->value("SELECT box1 FROM grupper WHERE art = 'VK' AND kodenr = ?", [(int)$l['valuta']]);
			if (!$currency) {
				throw new SaldiRuntimeError('UNAVAILABLE', 'Journal currency unavailable');
			}
			$lines[] = ['line_id' => (int)$l['id'], 'bilag' => (int)$l['bilag'], 'date' => (string)$l['transdate'], 'text' => SaldiToolRuntime::text($l['beskrivelse']), 'debit' => self::side($ctx, $db, $l, true), 'credit' => self::side($ctx, $db, $l, false), 'amount' => SaldiToolRuntime::money($l['amount']), 'currency' => $currency];
			$cites[] = SaldiToolRuntime::citation('journal_line', (int)$l['id'], $l['beskrivelse']) + ['screen' => SaldiScreens::link('journal', $a['journal_id'])];
		}
		$kind = SaldiKindRegistry::default()->get('journal.draft_entry');
		return SaldiToolRuntime::result(['journal_id' => (int)$r['id'], 'date' => (string)$r['kladdedate'], 'note' => SaldiToolRuntime::text($r['kladdenote']), 'status' => self::journalStatus($r['bogfort']), 'revision' => $kind->currentRevision($ctx, $db, ['payload' => ['kladde_id' => $a['journal_id']]]), 'lines' => $lines], $cites, count($raw) > 200);
	}
	private static function partyPermission(SaldiTenantContext $ctx, SaldiDb $db, string $type): string
	{
		SaldiPermissionService::require($ctx, $db, $type === 'debtor' ? 'debtors.read' : 'creditors.read');
		return $type === 'debtor' ? 'D' : 'K';
	}
	/** Open balances preserve row currency and compute grouped totals in base currency. @return array */
	public static function openItems(SaldiTenantContext $ctx, SaldiDb $db, array $a): array
	{
		$art = self::partyPermission($ctx, $db, $a['party_type']);
		$today = SaldiToolRuntime::today();
		$where = "p.art = ? AND COALESCE(o.udlignet,'') <> '1'";
		$whereParams = [$art];
		if ($a['overdue_only'] ?? false) {
			$where .= ' AND o.forfaldsdate < ?';
			$whereParams[] = $today;
		}
		if (isset($a['party_account'])) {
			$where .= ' AND p.kontonr = ?';
			$whereParams[] = $a['party_account'];
		}
		$conv = SaldiToolRuntime::openpostBaseSql($ctx->baseCurrency);
		$base = $conv['amount'];
		$missing = $conv['missing'];
		$bind = $conv['params'];
		// Creditor debts are posted negative (finans/bogfor.php), so the largest balance sorts first ascending.
		$dir = $art === 'K' ? 'ASC' : 'DESC';
		$group = $a['group_by_party'] ?? false;
		if ($group) {
			$select = 'SELECT p.id, p.kontonr, p.firmanavn, COALESCE(SUM(' . $base . '),0) AS open_amount, COALESCE(SUM(CASE WHEN o.forfaldsdate < ? THEN ' . $base . ' ELSE 0 END),0) AS overdue_amount, SUM(' . $missing . ') AS rate_unavailable_items, MIN(o.forfaldsdate) AS oldest_due_date, COUNT(*) AS items FROM openpost o JOIN adresser p ON p.id = o.konto_id WHERE ' . $where . ' GROUP BY p.id, p.kontonr, p.firmanavn';
			$params = array_merge($bind, [$today], $bind, $bind, $whereParams);
			$order = 'open_amount ' . $dir . ', p.id';
		} else {
			// Wrapped so the sort can use plain columns: unconvertible rows last, on both databases.
			$select = 'SELECT * FROM (SELECT o.id, p.id AS party_id, p.kontonr, p.firmanavn, o.faktnr, o.transdate, o.forfaldsdate, o.amount, o.valuta, ' . $base . ' AS amount_base, ' . $missing . ' AS rate_unavailable FROM openpost o JOIN adresser p ON p.id = o.konto_id WHERE ' . $where . ') x';
			$params = array_merge($bind, $bind, $whereParams);
			$order = 'x.rate_unavailable, x.amount_base ' . $dir . ', x.id';
		}
		$page = SaldiToolRuntime::page($db, $select, $order, $params, $a);
		$rows = [];
		$cites = [];
		foreach ($page['rows'] as $r) {
			$id = (int)$r[$group ? 'id' : 'party_id'];
			$row = ['party_id' => $id, 'party_account' => (string)$r['kontonr'], 'party_name' => SaldiToolRuntime::text($r['firmanavn']), 'converted' => true];
			if ($group) {
				$unavailable = (int)$r['rate_unavailable_items'];
				$row['converted'] = $unavailable === 0;
				$row += ['open_amount' => SaldiToolRuntime::money($r['open_amount']), 'overdue_amount' => SaldiToolRuntime::money($r['overdue_amount']), 'currency' => $ctx->baseCurrency, 'oldest_due_date' => $r['oldest_due_date'], 'items' => (int)$r['items'], 'rate_unavailable_items' => $unavailable];
			} else {
				$unavailable = (int)$r['rate_unavailable'] === 1;
				$days = $r['forfaldsdate'] && $r['forfaldsdate'] < $today ? (int)(new DateTimeImmutable($r['forfaldsdate']))->diff(new DateTimeImmutable($today))->days : 0;
				$row['converted'] = !$unavailable;
				$row += ['item_id' => (int)$r['id'], 'invoice' => SaldiToolRuntime::text($r['faktnr']), 'date' => (string)$r['transdate'], 'due_date' => $r['forfaldsdate'], 'amount' => SaldiToolRuntime::money($r['amount']), 'currency' => SaldiToolRuntime::currency($ctx, $r['valuta']), 'amount_base' => $unavailable ? null : SaldiToolRuntime::money($r['amount_base']), 'days_overdue' => $days];
				if ($unavailable) {
					$row['rate_unavailable'] = true;
				}
			}
			$rows[] = $row;
			$cites[] = SaldiToolRuntime::citation('party', $id, $r['firmanavn'], $a['party_type']);
		}
		// Totals over every matching item, so an answer can cite a sum instead of adding up a page.
		$sums = $db->one('SELECT COALESCE(SUM(' . $base . '), 0) AS open_amount, COALESCE(SUM(CASE WHEN o.forfaldsdate < ? THEN ' . $base . ' ELSE 0 END), 0) AS overdue_amount, COUNT(DISTINCT p.id) AS parties, COALESCE(SUM(' . $missing . '), 0) AS rate_unavailable_items FROM openpost o JOIN adresser p ON p.id = o.konto_id WHERE ' . $where, array_merge($bind, [$today], $bind, $bind, $whereParams));
		$unavailable = (int)$sums['rate_unavailable_items'];
		$totals = ['open_amount' => SaldiToolRuntime::money($sums['open_amount']), 'overdue_amount' => SaldiToolRuntime::money($sums['overdue_amount']), 'parties' => (int)$sums['parties'], 'currency' => $ctx->baseCurrency, 'rate_unavailable_items' => $unavailable];
		return SaldiToolRuntime::result(['rows' => $rows, 'total' => $page['total'], 'totals' => $totals, 'converted' => $unavailable === 0], $cites, $page['more']);
	}
	/** Party search excludes own-company rows even when their names match. @return array */
	public static function parties(SaldiTenantContext $ctx, SaldiDb $db, array $a): array
	{
		$art = self::partyPermission($ctx, $db, $a['type']);
		$where = 'art = ? AND (LOWER(COALESCE(firmanavn,\'\')) LIKE ? ESCAPE \'!\' OR ' . SaldiToolRuntime::cast($db, 'kontonr') . ' LIKE ? ESCAPE \'!\' OR LOWER(COALESCE(cvrnr,\'\')) LIKE ? ESCAPE \'!\')';
		$pattern = SaldiToolRuntime::like($a['query']);
		$params = [$art, $pattern, $pattern, $pattern];
		if (!($a['include_closed'] ?? false)) {
			$where .= " AND COALESCE(lukket,'') <> 'on'";
		}
		// "debitor 7" means account 7: exact account number first, then exact CVR, then
		// account prefixes, then names (a substring search alone returned 260 rows for "7").
		$q = mb_strtolower(trim((string)$a['query']));
		$prefix = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
		$kontonr = SaldiToolRuntime::cast($db, 'kontonr');
		$rank = "CASE WHEN LOWER($kontonr) = ? THEN 0 WHEN LOWER(COALESCE(cvrnr,'')) = ? THEN 1 WHEN LOWER($kontonr) LIKE ? ESCAPE '!' THEN 2 ELSE 3 END AS match_rank";
		$page = SaldiToolRuntime::page($db, 'SELECT id, kontonr, firmanavn, cvrnr, bynavn, lukket, ' . $rank . ' FROM adresser WHERE ' . $where, 'match_rank, firmanavn, id', array_merge([$q, $q, $prefix], $params), $a);
		$rows = [];
		$cites = [];
		foreach ($page['rows'] as $r) {
			$rows[] = ['party_id' => (int)$r['id'], 'account' => (string)$r['kontonr'], 'name' => SaldiToolRuntime::text($r['firmanavn']), 'cvr' => SaldiToolRuntime::text($r['cvrnr']), 'city' => SaldiToolRuntime::text($r['bynavn']), 'closed' => $r['lukket'] === 'on'];
			$cites[] = SaldiToolRuntime::citation('party', (int)$r['id'], $r['firmanavn'], $a['type']);
		}
		return SaldiToolRuntime::result(['rows' => $rows, 'total' => $page['total']], $cites, $page['more']);
	}
	/** One party with permission-filtered recent documents and converted balances. @return array */
	public static function party(SaldiTenantContext $ctx, SaldiDb $db, array $a): array
	{
		$r = $db->one('SELECT id, art, kontonr, firmanavn, cvrnr, ean, addr1, addr2, postnr, bynavn, land, email, tlf, betalingsdage, kreditmax, lukket FROM adresser WHERE id = ?', [$a['party_id']]);
		if (!$r || !in_array($r['art'], ['D', 'K'], true)) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Party not found');
		}
		$type = $r['art'] === 'D' ? 'debtor' : 'creditor';
		self::partyPermission($ctx, $db, $type);
		$conv = SaldiToolRuntime::openpostBaseSql($ctx->baseCurrency);
		$totals = $db->one('SELECT COALESCE(SUM(' . $conv['amount'] . '),0) AS open_amount, COALESCE(SUM(CASE WHEN o.forfaldsdate < ? THEN ' . $conv['amount'] . ' ELSE 0 END),0) AS overdue_amount, COALESCE(SUM(' . $conv['missing'] . "),0) AS rate_unavailable_items FROM openpost o WHERE o.konto_id = ? AND COALESCE(o.udlignet,'') <> '1'", array_merge($conv['params'], [SaldiToolRuntime::today()], $conv['params'], $conv['params'], [$a['party_id']]));
		$unavailable = (int)$totals['rate_unavailable_items'];
		$documents = [];
		$cites = [SaldiToolRuntime::citation('party', $a['party_id'], $r['firmanavn'], $type)];
		$permission = $type === 'debtor' ? 'sales.read' : 'purchases.read';
		if (SaldiPermissionService::allows($ctx, $db, $permission) && ($type !== 'debtor' || !SaldiPermissionService::revenueHidden($ctx, $db))) {
			// Require again at the branch that actually reads documents.
			SaldiPermissionService::require($ctx, $db, $permission);
			$arts = $type === 'debtor' ? "('DO','DK')" : "('KO','KK')";
			foreach ($db->all('SELECT id, art, ordrenr, COALESCE(fakturadate,ordredate) AS date, CAST(COALESCE(sum,0) AS DECIMAL(30,6)) + CAST(COALESCE(moms,0) AS DECIMAL(30,6)) AS total, valuta FROM ordrer WHERE konto_id = ? AND art IN ' . $arts . ' ORDER BY COALESCE(fakturadate,ordredate) DESC, id DESC LIMIT 5', [$a['party_id']]) as $d) {
				$documents[] = ['order_id' => (int)$d['id'], 'art' => (string)$d['art'], 'number' => SaldiToolRuntime::text($d['ordrenr']), 'date' => (string)$d['date'], 'total' => SaldiToolRuntime::money($d['total']), 'currency' => SaldiToolRuntime::currency($ctx, $d['valuta'])];
				$cites[] = self::invoiceCitation($d);
			}
		}
		return SaldiToolRuntime::result(['party_id' => (int)$r['id'], 'type' => $type, 'account' => (string)$r['kontonr'], 'name' => SaldiToolRuntime::text($r['firmanavn']), 'cvr' => SaldiToolRuntime::text($r['cvrnr']), 'ean' => SaldiToolRuntime::text($r['ean']), 'address' => SaldiToolRuntime::text(trim(($r['addr1'] ?? '') . "\n" . ($r['addr2'] ?? ''))), 'zip' => SaldiToolRuntime::text($r['postnr']), 'city' => SaldiToolRuntime::text($r['bynavn']), 'country' => SaldiToolRuntime::text($r['land']), 'email' => SaldiToolRuntime::text($r['email']), 'phone' => SaldiToolRuntime::text($r['tlf']), 'payment_terms_days' => (int)$r['betalingsdage'], 'credit_limit' => SaldiToolRuntime::money($r['kreditmax']), 'closed' => $r['lukket'] === 'on', 'open_amount' => SaldiToolRuntime::money($totals['open_amount']), 'overdue_amount' => SaldiToolRuntime::money($totals['overdue_amount']), 'currency' => $ctx->baseCurrency, 'recent_documents' => $documents, 'converted' => $unavailable === 0, 'rate_unavailable_items' => $unavailable], $cites);
	}
	private static function invoicePermission(SaldiTenantContext $ctx, SaldiDb $db, string $art): void
	{
		if (!in_array($art, ['DO', 'DK', 'KO', 'KK'], true)) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Invoice not found');
		}
		$sales = in_array($art, ['DO', 'DK'], true);
		SaldiPermissionService::require($ctx, $db, $sales ? 'sales.read' : 'purchases.read');
		if ($sales && SaldiPermissionService::revenueHidden($ctx, $db)) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Permission denied');
		}
	}
	private static function invoiceCitation(array $r): array
	{
		return SaldiToolRuntime::citation('invoice', (int)$r['id'], 'Ordre ' . ($r['ordrenr'] ?? $r['id']), in_array($r['art'], ['DO', 'DK'], true) ? 'sales_order' : 'purchase_order');
	}
	private static function invoiceRow(SaldiTenantContext $ctx, array $r): array
	{
		return ['order_id' => (int)$r['id'], 'art' => (string)$r['art'], 'number' => SaldiToolRuntime::text($r['ordrenr']), 'invoice_number' => SaldiToolRuntime::text($r['fakturanr']), 'date' => (string)($r['fakturadate'] ?: $r['ordredate']), 'party_account' => (string)$r['kontonr'], 'party_name' => SaldiToolRuntime::text($r['firmanavn']), 'net' => SaldiToolRuntime::money($r['sum']), 'vat' => SaldiToolRuntime::money($r['moms']), 'total' => SaldiToolRuntime::money($r['total']), 'currency' => SaldiToolRuntime::currency($ctx, $r['valuta']), 'status' => (int)$r['status'] === 0 ? 'offer' : ((int)$r['status'] < 3 ? 'order' : 'invoiced'), 'paid' => (int)$r['paid'] === 1];
	}
	/**
	 * Invoice columns plus 'paid', computed like debitor/ordreliste.php: an invoice (status >= 3) is paid when
	 * its open item (faktnr = fakturanr, same konto_id, amount = +/- total) is settled (udlignet '1'), the
	 * normal path for invoices on account, or when ordrer.betalt holds a non-zero mark.
	 */
	private static function invoiceSelect(SaldiDb $db): string
	{
		$total = 'CAST(COALESCE(ordrer.sum,0) AS DECIMAL(30,6)) + CAST(COALESCE(ordrer.moms,0) AS DECIMAL(30,6))';
		$settled = 'EXISTS (SELECT 1 FROM openpost op WHERE op.faktnr = ' . SaldiToolRuntime::cast($db, 'ordrer.fakturanr') . " AND op.konto_id = ordrer.konto_id AND op.udlignet = '1' AND (ABS(ROUND(CAST(op.amount AS DECIMAL(30,6)), 2) - ROUND(" . $total . ', 2)) < 0.01 OR ABS(ROUND(CAST(op.amount AS DECIMAL(30,6)), 2) + ROUND(' . $total . ', 2)) < 0.01))';
		// betalt marks: '1', 'on' or a paid amount (POS). '', '-' and any zero such as '0.00' or '-0' are unpaid.
		$marked = "TRIM(TRAILING '.' FROM TRIM(LEADING '0' FROM TRIM(LEADING '-' FROM TRIM(TRAILING '0' FROM TRIM(COALESCE(ordrer.betalt, '')))))) <> ''";
		return 'SELECT id, art, ordrenr, fakturanr, ordredate, fakturadate, kontonr, firmanavn, sum, moms, ' . $total . ' AS total, valuta, status, CASE WHEN status >= 3 AND (' . $settled . ' OR ' . $marked . ') THEN 1 ELSE 0 END AS paid';
	}
	/** Search only the requested, authorized document direction. @return array */
	public static function invoices(SaldiTenantContext $ctx, SaldiDb $db, array $a): array
	{
		$sales = $a['direction'] === 'sales';
		self::invoicePermission($ctx, $db, $sales ? 'DO' : 'KO');
		$where = $sales ? "art IN ('DO','DK')" : "art IN ('KO','KK')";
		$params = [];
		$status = $a['status'] ?? 'all';
		$where .= match ($status) { 'offer' => ' AND status = 0', 'order' => ' AND status IN (1,2)', 'invoiced' => ' AND status >= 3', default => '' };
		if (isset($a['party_account'])) {
			$where .= ' AND kontonr = ?';
			$params[] = $a['party_account'];
		}
		foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
			if (isset($a[$key])) {
				$where .= ' AND COALESCE(fakturadate,ordredate) ' . $op . ' ?';
				$params[] = SaldiToolRuntime::date($a[$key]);
			}
		}
		if (isset($a['from'], $a['to']) && $a['from'] > $a['to']) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid date range');
		}
		if (!empty($a['query'])) {
			$where .= ' AND (LOWER(COALESCE(firmanavn,\'\')) LIKE ? ESCAPE \'!\' OR ' . SaldiToolRuntime::cast($db, 'fakturanr') . ' LIKE ? ESCAPE \'!\' OR ' . SaldiToolRuntime::cast($db, 'ordrenr') . ' LIKE ? ESCAPE \'!\')';
			$p = SaldiToolRuntime::like($a['query']);
			array_push($params, $p, $p, $p);
		}
		$page = SaldiToolRuntime::page($db, self::invoiceSelect($db) . ' FROM ordrer WHERE ' . $where, 'COALESCE(fakturadate,ordredate) DESC, id DESC', $params, $a);
		return SaldiToolRuntime::result(['rows' => array_map(static fn(array $r): array => self::invoiceRow($ctx, $r), $page['rows']), 'total' => $page['total']], array_map(self::invoiceCitation(...), $page['rows']), $page['more']);
	}
	/** Due date: ordrer.due_date, else the posted open item's forfaldsdate, else the legacy forfaldsdag() rule. */
	private static function invoiceDueDate(SaldiDb $db, array $r): ?string
	{
		if (!empty($r['due_date'])) {
			return (string)$r['due_date'];
		}
		if ((string)$r['fakturanr'] !== '') {
			$posted = $db->value('SELECT forfaldsdate FROM openpost WHERE faktnr = ? AND konto_id = ? AND forfaldsdate IS NOT NULL AND (ABS(ROUND(CAST(amount AS DECIMAL(30,6)), 2) - ROUND(CAST(? AS DECIMAL(30,6)), 2)) < 0.01 OR ABS(ROUND(CAST(amount AS DECIMAL(30,6)), 2) + ROUND(CAST(? AS DECIMAL(30,6)), 2)) < 0.01) ORDER BY id LIMIT 1', [(string)$r['fakturanr'], (int)$r['konto_id'], (string)$r['total'], (string)$r['total']]);
			if ($posted !== null) {
				return (string)$posted;
			}
		}
		return empty($r['fakturadate']) ? null : self::termsDueDate((string)$r['fakturadate'], (string)($r['betalingsbet'] ?? ''), (int)($r['betalingsdage'] ?? 0));
	}
	/**
	 * Port of includes/forfaldsdag.php: kontant and efterkrav are due on the invoice date; netto counts the
	 * days from the invoice date; every other term (e.g. 'Lb. md.') counts them from the end of the month.
	 */
	public static function termsDueDate(string $invoiceDate, string $terms, int $days): string
	{
		$terms = strtolower(trim($terms));
		if (in_array($terms, ['kontant', 'efterkrav'], true)) {
			return $invoiceDate;
		}
		[$year, $month, $day] = array_map('intval', explode('-', $invoiceDate));
		$monthEnd = (int)(new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
		if ($terms !== 'netto') {
			$day = $monthEnd;
		}
		$day += max(0, $days);
		while ($day > $monthEnd) {
			$day -= $monthEnd;
			$month++;
			if ($month > 12) {
				$month = 1;
				$year++;
			}
			$monthEnd = (int)(new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
		}
		return sprintf('%04d-%02d-%02d', $year, $month, $day);
	}
	/** Loaded art determines authorization; bank fields and internal notes never leave the DB. @return array */
	public static function invoice(SaldiTenantContext $ctx, SaldiDb $db, array $a): array
	{
		$r = $db->one(self::invoiceSelect($db) . ', due_date, betalingsbet, betalingsdage, konto_id, ref FROM ordrer WHERE id = ?', [$a['order_id']]);
		if (!$r) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Invoice not found');
		}
		self::invoicePermission($ctx, $db, $r['art']);
		$data = self::invoiceRow($ctx, $r);
		$data += ['due_date' => self::invoiceDueDate($db, $r), 'party_id' => (int)$r['konto_id'], 'reference' => SaldiToolRuntime::text($r['ref'])];
		$raw = $db->all('SELECT posnr, varenr, beskrivelse, antal, pris, rabat, momssats, bogf_konto FROM ordrelinjer WHERE ordre_id = ? ORDER BY posnr, id LIMIT 1001', [$a['order_id']]);
		$data['lines'] = array_map(static fn(array $l): array => ['line' => (int)$l['posnr'], 'item_no' => SaldiToolRuntime::text($l['varenr']), 'text' => SaldiToolRuntime::text($l['beskrivelse']), 'quantity' => (string)($l['antal'] ?? '0'), 'unit_price' => SaldiToolRuntime::money($l['pris']), 'currency' => $data['currency'], 'discount_pct' => (string)($l['rabat'] ?? '0'), 'vat_rate' => (string)($l['momssats'] ?? '0'), 'account' => (string)($l['bogf_konto'] ?? '')], array_slice($raw, 0, 1000));
		return SaldiToolRuntime::result($data, [self::invoiceCitation($r)], count($raw) > 1000);
	}
	/** Product search with explicit minimum-stock predicate. @return array */
	public static function products(SaldiTenantContext $ctx, SaldiDb $db, array $a): array
	{
		SaldiPermissionService::require($ctx, $db, 'products.read');
		$where = "COALESCE(lukket,'') <> 'on'";
		$params = [];
		if (!empty($a['query'])) {
			$where .= ' AND (LOWER(COALESCE(varenr,\'\')) LIKE ? ESCAPE \'!\' OR LOWER(COALESCE(beskrivelse,\'\')) LIKE ? ESCAPE \'!\')';
			array_push($params, SaldiToolRuntime::like($a['query']), SaldiToolRuntime::like($a['query']));
		}
		if ($a['low_stock_only'] ?? false) {
			$where .= ' AND min_lager > 0 AND COALESCE(beholdning,0) < min_lager';
		}
		$page = SaldiToolRuntime::page($db, 'SELECT id, varenr, beskrivelse, enhed, salgspris, kostpris, beholdning, min_lager, CASE WHEN min_lager > 0 AND COALESCE(beholdning,0) < min_lager THEN 1 ELSE 0 END AS low_stock FROM varer WHERE ' . $where, 'varenr, id', $params, $a);
		$rows = [];
		$cites = [];
		foreach ($page['rows'] as $r) {
			$rows[] = ['product_id' => (int)$r['id'], 'item_no' => SaldiToolRuntime::text($r['varenr']), 'text' => SaldiToolRuntime::text($r['beskrivelse']), 'unit' => SaldiToolRuntime::text($r['enhed']), 'price' => SaldiToolRuntime::money($r['salgspris']), 'cost' => SaldiToolRuntime::money($r['kostpris']), 'stock' => (string)($r['beholdning'] ?? '0'), 'min_stock' => (string)($r['min_lager'] ?? '0'), 'low_stock' => (int)$r['low_stock'] === 1, 'currency' => $ctx->baseCurrency];
			$cites[] = SaldiToolRuntime::citation('product', (int)$r['id'], $r['varenr'] . ' ' . $r['beskrivelse'], 'product');
		}
		return SaldiToolRuntime::result(['rows' => $rows, 'total' => $page['total']], $cites, $page['more']);
	}
	/** Visible items only; the catalogue omits payload from list responses. @return array */
	public static function inboxList(SaldiTenantContext $ctx, SaldiDb $db, array $a): array
	{
		SaldiPermissionService::require($ctx, $db, 'inbox.read');
		$service = new SaldiInboxService($db, SaldiKindRegistry::default());
		$rows = $service->list($ctx, $a);
		$next = $service->list($ctx, array_replace($a, ['limit' => 1, 'offset' => ($a['offset'] ?? 0) + count($rows)]));
		$cites = [];
		foreach ($rows as &$row) {
			unset($row['payload']);
			$row = SaldiInboxService::publicItem($row);
			$cites[] = SaldiToolRuntime::citation('inbox', $row['id'], $row['title'], 'inbox_item');
		}
		unset($row);
		$data = ['rows' => $rows];
		if (($a['status'] ?? 'open') === 'open' && empty($a['kind'])) {
			$data['total'] = $service->countOpen($ctx);
		}
		return SaldiToolRuntime::result($data, $cites, (bool)$next);
	}
	/** Visible full item for opening its Saldi approval card. @return array */
	public static function inboxItem(SaldiTenantContext $ctx, SaldiDb $db, array $a): array
	{
		SaldiPermissionService::require($ctx, $db, 'inbox.read');
		$item = (new SaldiInboxService($db, SaldiKindRegistry::default()))->get($ctx, $a['item_id']);
		return SaldiToolRuntime::result(SaldiInboxService::publicItem($item), [SaldiToolRuntime::citation('inbox', $item['id'], $item['title'], 'inbox_item')]);
	}
}
