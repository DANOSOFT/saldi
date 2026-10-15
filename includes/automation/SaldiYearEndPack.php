<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiYearEndPack.php --- ai/mcp-assistant --- 2026.10.05
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
// Read-only period, year-end, till, inventory and master-data insights.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.
// 20261007 CL/LH The complete pack (Revisorpakke page and CSV) is built without the tool response limit and
//                without row bounds; only the bounded tool result goes through SaldiToolRuntime::result.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';
require_once __DIR__ . '/../tools/SaldiToolRuntime.php';
require_once __DIR__ . '/../tools/SaldiWatchTools.php';

final class SaldiYearEndPack
{
	/**
	 * $complete = false: the bounded tool result (200 rows per section, response limit, possibly_incomplete).
	 * $complete = true: every row for the page and CSV, never trimmed to the tool response limit, so it is
	 * never partial (possibly_incomplete is false) and fails rather than return less.
	 * @return array{data:array,citations:list<array>,possibly_incomplete:bool,result_count:int}
	 */
	public static function build(SaldiTenantContext $ctx, SaldiDb $db, int $yearId, bool $complete = false): array
	{
		SaldiPermissionService::require($ctx, $db, 'ledger.read');
		$row = $db->one("SELECT kodenr, box1, box2, box3, box4 FROM grupper WHERE art='RA' AND kodenr=?", [$yearId]);
		if (!$row) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Fiscal year not found');
		}
		$year = SaldiToolRuntime::year($row);
		$hidden = SaldiPermissionService::revenueHidden($ctx, $db);
		$chart = "SELECT k.kontonr, k.beskrivelse, k.kontotype, CAST(COALESCE(k.primo,0) AS DECIMAL(30,6)) AS opening, COALESCE(SUM(CAST(COALESCE(t.debet,0) AS DECIMAL(30,6))),0) AS debit, COALESCE(SUM(CAST(COALESCE(t.kredit,0) AS DECIMAL(30,6))),0) AS credit FROM kontoplan k LEFT JOIN transaktioner t ON t.kontonr=k.kontonr AND t.transdate>=? AND t.transdate<=? WHERE k.regnskabsaar=? AND k.kontotype IN ('D','S')" . ($hidden ? " AND k.kontotype='S'" : '') . ' GROUP BY k.kontonr, k.beskrivelse, k.kontotype, k.primo';
		$select = 'SELECT c.*, opening+debit-credit AS closing FROM (' . $chart . ') c';
		$params = [$year['start'], $year['end'], $yearId];
		$totals = $db->one('SELECT COALESCE(SUM(opening),0) AS opening, COALESCE(SUM(debit),0) AS debit, COALESCE(SUM(credit),0) AS credit, COALESCE(SUM(closing),0) AS closing FROM (' . $select . ') totals', $params);
		$totals = array_map(SaldiToolRuntime::money(...), $totals) + ['currency' => $ctx->baseCurrency];
		$accounts = $db->all($select . ' ORDER BY kontonr' . ($complete ? '' : ' LIMIT 201'), $params);
		$more = !$complete && count($accounts) > 200;
		$trial = $citations = [];
		foreach (($complete ? $accounts : array_slice($accounts, 0, 200)) as $account) {
			$trial[] = ['account' => (string)$account['kontonr'], 'name' => SaldiToolRuntime::text($account['beskrivelse']), 'kind' => SaldiToolRuntime::accountKind($account['kontotype']), 'currency' => $ctx->baseCurrency] + array_map(SaldiToolRuntime::money(...), array_intersect_key($account, array_flip(['opening', 'debit', 'credit', 'closing'])));
			$citations[] = SaldiToolRuntime::citation('account', $account['kontonr'] . '@' . $yearId, $account['kontonr'] . ' ' . $account['beskrivelse']);
		}
		// Settlement after D remains outstanding at D, even when it is settled today.
		$open = $db->all("SELECT o.id, a.art, o.konto_nr, a.firmanavn, a.id AS party_id, o.faktnr, o.transdate, o.forfaldsdate, o.amount, o.valuta FROM openpost o LEFT JOIN adresser a ON a.id=o.konto_id WHERE o.transdate<=? AND (COALESCE(o.udlignet,'')<>'1' OR o.udlign_date>?) AND a.art IN ('D','K')" . ($hidden ? " AND a.art='K'" : '') . ' ORDER BY a.art, o.konto_nr, o.transdate, o.id' . ($complete ? '' : ' LIMIT 201'), [$year['end'], $year['end']]);
		$more = $more || !$complete && count($open) > 200;
		$items = [];
		foreach (($complete ? $open : array_slice($open, 0, 200)) as $item) {
			$items[] = ['item_id' => (int)$item['id'], 'party_type' => $item['art'] === 'D' ? 'debtor' : 'creditor', 'party_account' => (string)$item['konto_nr'], 'party_name' => SaldiToolRuntime::text($item['firmanavn']), 'invoice' => SaldiToolRuntime::text($item['faktnr']), 'date' => $item['transdate'], 'due_date' => $item['forfaldsdate'], 'amount' => SaldiToolRuntime::money($item['amount']), 'currency' => SaldiToolRuntime::currency($ctx, $item['valuta'])];
			if ($item['party_id']) {
				$citations[] = SaldiToolRuntime::citation('party', (int)$item['party_id'], $item['konto_nr'] . ' ' . $item['firmanavn'], $item['art'] === 'D' ? 'debtor' : 'creditor');
			}
		}
		$vat = [];
		if (!$hidden) {
			$from = new DateTimeImmutable($year['start']);
			for ($quarter = 1; $from->format('Y-m-d') <= $year['end']; $quarter++) {
				$month = (int)$from->format('n');
				$next = $from->modify('first day of this month')->modify('+' . (3 - ($month - 1) % 3) . ' months');
				$summary = SaldiWatchTools::vatSummary($ctx, $db, ['from' => $from->format('Y-m-d'), 'to' => min($year['end'], $next->modify('-1 day')->format('Y-m-d'))]);
				$summary['data']['note'] = SaldiText::get($db, 6270, $ctx->languageId, 'Beregnet ud fra bogførte posteringer. Ikke-bogførte kassekladder er ikke medtaget.');
				$vat[] = ['quarter' => $quarter] + $summary['data'];
				array_push($citations, ...$summary['citations']);
				$from = $next;
			}
		}
		$journals = $db->all("SELECT j.id, j.kladdedate, j.kladdenote, j.oprettet_af, COUNT(l.id) AS lines FROM kladdeliste j LEFT JOIN kassekladde l ON l.kladde_id=j.id WHERE COALESCE(j.bogfort,'-') IN ('-','!') AND (l.transdate>=? AND l.transdate<=? OR (l.id IS NULL AND j.kladdedate>=? AND j.kladdedate<=?)) GROUP BY j.id, j.kladdedate, j.kladdenote, j.oprettet_af ORDER BY j.id" . ($complete ? '' : ' LIMIT 201'), [$year['start'], $year['end'], $year['start'], $year['end']]);
		$more = $more || !$complete && count($journals) > 200;
		$unposted = [];
		foreach (($complete ? $journals : array_slice($journals, 0, 200)) as $journal) {
			$unposted[] = ['journal_id' => (int)$journal['id'], 'date' => $journal['kladdedate'], 'note' => $hidden ? '' : SaldiToolRuntime::text($journal['kladdenote']), 'status' => 'open', 'lines' => (int)$journal['lines'], 'created_by' => SaldiToolRuntime::text($journal['oprettet_af']), 'screen' => SaldiScreens::link('journal', (int)$journal['id'])];
			$citations[] = SaldiToolRuntime::citation('journal', (int)$journal['id'], 'Kassekladde ' . $journal['id'], 'journal');
		}
		$notes = [];
		if ($hidden) {
			$notes[] = SaldiText::get($db, 6268, $ctx->languageId, 'Omsætning er skjult. Drift, kundeposter og moms er udeladt.');
		}
		if ($unposted) {
			$notes[] = SaldiText::get($db, 6269, $ctx->languageId, 'Der er ikke-bogførte kassekladder i regnskabsåret.');
		}
		$data = ['fiscal_year' => $year, 'currency' => $ctx->baseCurrency, 'revenue_hidden' => $hidden, 'trial_balance' => ['rows' => $trial, 'totals' => $totals, 'revenue_hidden' => $hidden], 'open_items' => $items, 'vat' => $vat, 'unposted_journals' => $unposted, 'periods_closed' => SaldiDailyOverview::vatMonths($db, $year['start'], $year['end']), 'notes' => $notes, 'links' => [['label' => SaldiText::get($db, 6160, $ctx->languageId, 'Revisorpakke'), 'screen' => SaldiScreens::link('year_end_pack')]]];
		if ($complete) {
			// The page and CSV are not an MCP response: no envelope limit, and no citations needed.
			return ['data' => $data, 'citations' => [], 'possibly_incomplete' => false, 'result_count' => count($trial) + count($items)];
		}
		return SaldiToolRuntime::result($data, array_values(array_unique($citations, SORT_REGULAR)), $more);
	}
}
