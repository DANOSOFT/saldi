<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiDailyOverview.php --- ai/mcp-assistant --- 2026.10.04
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
// Permission-scoped daily facts and shared bilag-level attachment checks.
// All data stays inside Saldi; sections not authorized for the caller are absent.
//
// 20261002 CDX/LH Created.
// 20261004 CDX/LH Add companion part 2 foundation contracts.
// 20261007 CL/LH Open-item counts leave out credit notes for revenue-hidden users.

declare(strict_types=1);

require_once __DIR__ . '/SaldiVatPeriod.php';

final class SaldiDailyOverview
{
	/**
	 * @return array{
	 *   date:string,
	 *   open_items?:array{
	 *     count:int,
	 *     interrupts:list<array{item_id:int,title:string,screen:string}>,
	 *     by_kind:list<array{kind:string,label:string,count:int}>
	 *   },
	 *   overdue_debtors?:array{count:int,amount:string,currency:string,converted:bool},
	 *   open_journals?:array{count:int,oldest_date:string|null},
	 *   vat?:array{period:array{from:string,to:string},deadline:string,days_left:int,missing_documents:int,note:string},
	 *   pool_documents?:array{count:int},
	 *   low_stock?:array{count:int},
	 *   nothing_to_do:bool
	 * } Sections without the caller's permission are omitted, never replaced with misleading zeroes.
	 */
	public static function build(SaldiTenantContext $ctx, SaldiDb $db): array
	{
		$today = SaldiToolRuntime::today();
		$data = ['date' => $today];
		$busy = false;
		if (SaldiPermissionService::allows($ctx, $db, 'inbox.read')) {
			$permissions = SaldiPermissionService::effective($ctx, $db);
			$permissionWhere = $permissions ? "(visibility = 'permission' AND assignee_permission IN (" . implode(',', array_fill(0, count($permissions), '?')) . '))' : '1 = 0';
			// Same visibility as SaldiInboxService::predicate: revenue-hidden users never see credit notes.
			$where = " FROM inbox_items WHERE status = 'open' AND ((owner_user_id = ? AND ? > 0) OR " . $permissionWhere . ')'
				. (SaldiPermissionService::revenueHidden($ctx, $db) ? " AND kind <> 'credit_note.draft'" : '');
			$params = array_merge([$ctx->userId, $ctx->userId], $permissions);
			$interrupts = $db->all('SELECT id, title' . $where . " AND attention = 'interrupt' ORDER BY id DESC LIMIT 20", $params);
			$byKind = $db->all('SELECT kind, COUNT(*) AS count' . $where . ' GROUP BY kind ORDER BY kind', $params);
			$labels = ['journal.draft_entry' => 5701, 'memory.fact' => 5702, 'memory.retract' => 5703, 'followup.task' => 5704, 'routine.finding' => 5705, 'rule.posting' => 5900, 'rule.retire' => 5901, 'party.create' => 5902, 'purchase_order.draft' => 5904, 'reminder.draft' => 5905, 'support.report' => 5906, 'settlement.draft' => 6100, 'purchase_order.invoice_stage' => 6101, 'sales_order.draft' => 6102, 'credit_note.draft' => 6104];
			$data['open_items'] = ['count' => (int)$db->value('SELECT COUNT(*)' . $where, $params), 'interrupts' => array_map(static fn(array $row): array => ['item_id' => (int)$row['id'], 'title' => SaldiToolRuntime::text($row['title']), 'screen' => SaldiScreens::link('inbox_item', (int)$row['id'])], $interrupts), 'by_kind' => array_map(static fn(array $row): array => ['kind' => $row['kind'], 'label' => SaldiText::get($db, $labels[$row['kind']] ?? 5705, $ctx->languageId, $row['kind']) . ($row['kind'] === 'sales_order.draft' ? ' / ' . SaldiText::get($db, 6103, $ctx->languageId, 'Ordre') : ''), 'count' => (int)$row['count']], $byKind)];
			$busy = $interrupts !== [];
		}
		if (SaldiPermissionService::allows($ctx, $db, 'debtors.read')) {
			$conversion = SaldiToolRuntime::openpostBaseSql($ctx->baseCurrency);
			$from = " FROM openpost o JOIN adresser party ON party.id = o.konto_id WHERE party.art = 'D' AND COALESCE(o.udlignet, '') <> '1' AND o.forfaldsdate < ?";
			$summary = $db->one('SELECT COUNT(DISTINCT party.id) AS count, SUM(' . $conversion['amount'] . ') AS amount' . $from . ' AND (' . $conversion['amount'] . ') IS NOT NULL', array_merge($conversion['params'], [$today], $conversion['params']));
			$missing = (int)$db->value('SELECT COUNT(*)' . $from . ' AND (' . $conversion['missing'] . ') = 1', array_merge([$today], $conversion['params']));
			$data['overdue_debtors'] = ['count' => (int)$summary['count'], 'amount' => SaldiToolRuntime::money($summary['amount']), 'currency' => $ctx->baseCurrency, 'converted' => $missing === 0];
			$busy = $busy || $data['overdue_debtors']['count'] > 0;
		}
		if (SaldiPermissionService::allows($ctx, $db, 'journal.read')) {
			$summary = $db->one("SELECT COUNT(*) AS count, MIN(j.kladdedate) AS oldest_date FROM kladdeliste j WHERE COALESCE(j.bogfort, '-') = '-' AND EXISTS (SELECT 1 FROM kassekladde line WHERE line.kladde_id = j.id)");
			$data['open_journals'] = ['count' => (int)$summary['count'], 'oldest_date' => $summary['oldest_date']];
			$period = SaldiVatPeriod::current(SaldiSettings::get($db, 'assistant', 'vat_period', 'quarter'), $today);
			$missing = self::missingDocuments($ctx, $db, $period['from'], $period['to'], true);
			$data['vat'] = ['period' => ['from' => $period['from'], 'to' => $period['to']], 'deadline' => $period['deadline'], 'days_left' => $period['days_left'], 'missing_documents' => $missing['count'], 'note' => SaldiText::get($db, 5968, $ctx->languageId, 'Fristen er beregnet ud fra momsperioden i Assist-indstillinger. Tjek altid skat.dk.')];
			$busy = $busy || ($summary['oldest_date'] !== null && $summary['oldest_date'] < (new DateTimeImmutable($today))->modify('-14 days')->format('Y-m-d')) || ($period['days_left'] <= 14 && $missing['count'] > 0);
		}
		if (SaldiPermissionService::allows($ctx, $db, 'documents.read')) {
			$data['pool_documents'] = ['count' => (int)$db->value('SELECT COUNT(*) FROM pool_files')];
		}
		if (SaldiPermissionService::allows($ctx, $db, 'products.read')) {
			$data['low_stock'] = ['count' => (int)$db->value("SELECT COUNT(*) FROM varer WHERE COALESCE(lukket, '') <> 'on' AND min_lager > 0 AND beholdning < min_lager")];
			$busy = $busy || $data['low_stock']['count'] > 0;
		}
		$data['nothing_to_do'] = !$busy;
		return $data;
	}
	/**
	 * Any attachment on any sibling line covers the whole kladde/bilag, even outside the date range.
	 * @return array{count:int,rows:list<array{journal_id:int,bilag:int,label:string,amount:string,currency:string,date:string,screen:string}>}
	 */
	public static function missingDocuments(SaldiTenantContext $ctx, SaldiDb $db, string $from, string $to, bool $vatOnly): array
	{
		$vatWhere = $vatOnly ? " AND EXISTS (SELECT 1 FROM kontoplan account WHERE account.regnskabsaar = ? AND COALESCE(TRIM(account.moms), '') <> '' AND ((line.d_type = 'F' AND account.kontonr = line.debet) OR (line.k_type = 'F' AND account.kontonr = line.kredit)))" : '';
		$params = [$ctx->baseCurrency, $from, $to];
		if ($vatOnly) {
			$params[] = $ctx->fiscalYear;
		}
		$attachments = $db->hasTable('documents') ? " OR EXISTS (SELECT 1 FROM documents document WHERE document.source = 'kassekladde' AND document.source_id = sibling.id)" : '';
		$amount = "CASE WHEN COALESCE(line.valuta, 0) = 0 OR EXISTS (SELECT 1 FROM grupper currency WHERE currency.art = 'VK' AND currency.kodenr = line.valuta AND currency.box1 = ?) THEN line.amount ELSE line.amount * CAST(COALESCE(NULLIF(line.valutakurs, 0), 100) AS DECIMAL(30,6)) / 100 END";
		$select = "SELECT line.kladde_id, line.bilag, MIN(line.transdate) AS date, MIN(line.beskrivelse) AS description, SUM(" . $amount . ") AS amount FROM kassekladde line WHERE line.transdate >= ? AND line.transdate <= ?" . $vatWhere
			. " AND NOT EXISTS (SELECT 1 FROM kassekladde sibling WHERE sibling.kladde_id = line.kladde_id AND (sibling.bilag = line.bilag OR (sibling.bilag IS NULL AND line.bilag IS NULL)) AND (COALESCE(TRIM(sibling.dokument), '') <> ''" . $attachments . ')) GROUP BY line.kladde_id, line.bilag';
		$count = (int)$db->value('SELECT COUNT(*) FROM (' . $select . ') missing', $params);
		$rows = $db->all($select . ' ORDER BY MIN(line.transdate), line.kladde_id, line.bilag LIMIT 20', $params);
		return ['count' => $count, 'rows' => array_map(static fn(array $row): array => ['journal_id' => (int)$row['kladde_id'], 'bilag' => (int)$row['bilag'], 'label' => SaldiToolRuntime::text('Kassekladde ' . $row['kladde_id'] . ', bilag ' . $row['bilag'] . ': ' . $row['description']), 'amount' => SaldiToolRuntime::money($row['amount']), 'currency' => $ctx->baseCurrency, 'date' => $row['date'], 'screen' => SaldiScreens::link('journal', (int)$row['kladde_id'])], $rows)];
	}
	/** @return list<array{month:string,closed:bool}> Every touched calendar month, absent closure table means open. */
	public static function vatMonths(SaldiDb $db, string $from, string $to): array
	{
		$months = [];
		$cursor = new DateTimeImmutable(substr($from, 0, 7) . '-01');
		while ($cursor->format('Y-m') <= substr($to, 0, 7)) {
			$closed = $db->hasTable('moms_periode_luk') && $db->value("SELECT 1 FROM moms_periode_luk WHERE kalender_aar = ? AND kalender_maaned = ? AND status = 'closed'", [(int)$cursor->format('Y'), (int)$cursor->format('m')]) !== null;
			$months[] = ['month' => $cursor->format('Y-m'), 'closed' => $closed];
			$cursor = $cursor->modify('+1 month');
		}
		return $months;
	}
}
