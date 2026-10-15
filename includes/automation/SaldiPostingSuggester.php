<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiPostingSuggester.php --- ai/mcp-assistant --- 2026.10.05
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
// Local, deterministic journal hints: approved party/text rules, scored history
// and duplicate warnings. Reads only; never books, fills or saves journal rows.
//
// 20261002 CDX/LH Created.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.

declare(strict_types=1);

require_once __DIR__ . '/SaldiPostingRules.php';
require_once __DIR__ . '/../tools/SaldiToolRuntime.php';

final class SaldiPostingSuggester
{
	private array $accounts = [];
	private array $parties = [];
	private bool $accountsLoaded = false;
	private ?array $rules = null;
	private ?array $uses = null;
	private const STOPWORDS = ['af', 'og', 'til', 'fra', 'for', 'med', 'den', 'det', 'der', 'som', 'faktura', 'fakt', 'nr', 'betaling', 'bet', 'the', 'and', 'inv'];
	public function __construct(private SaldiTenantContext $ctx, private SaldiDb $db)
	{
		SaldiPostingRules::helpers();
	}
	public static function normalise(string $text): string
	{
		return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\s]+/u', ' ', mb_strtolower($text, 'UTF-8'))));
	}
	/** @return list<string> Unique significant words, in their original order. */
	public static function tokens(string $text): array
	{
		return array_values(array_unique(array_filter(explode(' ', self::normalise($text)), static fn(string $word): bool => mb_strlen($word) >= 3 && !in_array($word, self::STOPWORDS, true))));
	}
	private static function accountNumber(mixed $number): string
	{
		return preg_match('/^([0-9]+)(?:\.0+)?$/D', (string)$number, $parts) ? (ltrim($parts[1], '0') ?: '0') : '';
	}
	/** @return array Normalized, bounded input; invoice is optional for duplicate checks. */
	private function line(array $line): array
	{
		if (!is_string(ifset($line, 'description')) || mb_strlen(trim($line['description'])) < 1 || mb_strlen($line['description']) > 100) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid description', ['fields' => ['description']]);
		}
		if (isset($line['amount']) && (!is_string($line['amount']) || !preg_match('/^-?[0-9]{1,12}(?:\.[0-9]{1,3})?$/D', $line['amount']))) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid amount', ['fields' => ['amount']]);
		}
		foreach (['kladde_id', 'exclude_line_id'] as $field) {
			if (isset($line[$field]) && (!is_int($line[$field]) || $line[$field] < 1)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid line reference', ['fields' => [$field]]);
			}
		}
		if (isset($line['date']) && !is_string($line['date'])) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid date', ['fields' => ['date']]);
		}
		$line['date'] = SaldiToolRuntime::date(ifset($line, 'date') ?? SaldiToolRuntime::today());
		if (isset($line['party'])) {
			$party = $line['party'];
			if (!is_array($party) || !in_array(ifset($party, 'ledger', ''), ['supplier', 'customer'], true)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid party', ['fields' => ['party']]);
			}
			SaldiPostingRules::party($this->db, $party['ledger'], ifset($party, 'account'));
			if (isset($party['side']) && !in_array($party['side'], ['debit', 'credit'], true)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid party side', ['fields' => ['party.side']]);
			}
		}
		if (isset($line['invoice']) && (!is_string($line['invoice']) || mb_strlen($line['invoice']) > 30)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid invoice', ['fields' => ['invoice']]);
		}
		if (isset($line['invoice'])) {
			$line['invoice'] = trim($line['invoice']);
		}
		return $line;
	}
	private function loadAccounts(): void
	{
		if ($this->accountsLoaded) {
			return;
		}
		$this->accountsLoaded = true;
		$hidden = SaldiPermissionService::revenueHidden($this->ctx, $this->db);
		foreach ($this->db->all("SELECT kontonr, beskrivelse, moms, kontotype FROM kontoplan WHERE regnskabsaar = ? AND kontotype IN ('D','S') AND COALESCE(lukket, '') <> 'on'", [$this->ctx->fiscalYear]) as $row) {
			if (!$hidden || $row['kontotype'] !== 'D') {
				$this->accounts[self::accountNumber($row['kontonr'])] = $row;
			}
		}
		foreach ($this->db->all("SELECT kontonr, art, firmanavn FROM adresser WHERE art IN ('D','K') AND COALESCE(lukket, '') <> 'on'") as $row) {
			$this->parties[$row['art'] . ':' . self::accountNumber($row['kontonr'])] = $row;
		}
	}
	/** @return array{ledger:string,account:string,name:string,vat_code:string}|null */
	private function side(string $type, mixed $number, string $vat = ''): ?array
	{
		$number = self::accountNumber($number);
		if ($number === '' || $number === '0') {
			return null;
		}
		$row = $type === 'F' ? (ifset($this->accounts, $number)) : (ifset($this->parties, $type . ':' . $number));
		if (!$row) {
			return null;
		}
		return ['ledger' => SaldiToolRuntime::ledgerWord($type), 'account' => $number, 'name' => SaldiToolRuntime::text($type === 'F' ? $row['beskrivelse'] : $row['firmanavn']), 'vat_code' => $type === 'F' ? ($vat !== '' ? $vat : (string)(ifset($row, 'moms', ''))) : ''];
	}
	/** @return array|null A pattern is dropped entirely if a populated side is unavailable. */
	private function pattern(array $row): ?array
	{
		$debit = $this->side((string)$row['d_type'], $row['debet'], (string)(ifset($row, 'debetvat', '')));
		$credit = $this->side((string)$row['k_type'], $row['kredit'], (string)(ifset($row, 'kreditvat', '')));
		if ((self::accountNumber($row['debet']) !== '0' && !empty($row['debet']) && $debit === null)
			|| (self::accountNumber($row['kredit']) !== '0' && !empty($row['kredit']) && $credit === null)
			|| ((ifset($debit, 'ledger', '')) !== 'account' && (ifset($credit, 'ledger', '')) !== 'account')) {
			return null;
		}
		return ['debit' => $debit, 'credit' => $credit, 'vat_free' => (ifset($row, 'momsfri', '')) === 'on'];
	}
	private function key(array $suggestion): string
	{
		return saldi_runtime_json(array_map(static fn(?array $side): ?array => $side === null ? null : array_intersect_key($side, array_flip(['ledger', 'account', 'vat_code'])), [$suggestion['debit'], $suggestion['credit']])) . ':' . (int)$suggestion['vat_free'];
	}
	/** @return array{0:string,1:list<string>} */
	private function partyFilter(array $line): array
	{
		if (!isset($line['party'])) {
			return ['', []];
		}
		$party = $line['party'];
		$type = $party['ledger'] === 'supplier' ? 'K' : 'D';
		$side = ifset($party, 'side');
		if ($side !== null) {
			return [$side === 'debit' ? ' AND k.d_type = ? AND k.debet = ?' : ' AND k.k_type = ? AND k.kredit = ?', [$type, $party['account']]];
		}
		return [' AND ((k.d_type = ? AND k.debet = ?) OR (k.k_type = ? AND k.kredit = ?))', [$type, $party['account'], $type, $party['account']]];
	}
	/** @return list<array> Ranked, weighted history patterns with evidence. */
	private function history(array $line): array
	{
		$tokens = self::tokens($line['description']);
		$today = SaldiToolRuntime::today();
		$cutoff = (new DateTimeImmutable($today))->modify('-730 days')->format('Y-m-d');
		[$partySql, $partyParams] = $this->partyFilter($line);
		$where = ' FROM kassekladde k JOIN kladdeliste l ON l.id = k.kladde_id WHERE k.transdate >= ? AND k.amount > 0 AND (COALESCE(k.debet, 0) <> 0 OR COALESCE(k.kredit, 0) <> 0) AND k.id <> ?' . $partySql;
		$params = array_merge([$cutoff, ifset($line, 'exclude_line_id', 0)], $partyParams);
		$select = 'SELECT k.id, k.transdate, k.beskrivelse, k.d_type, k.debet, k.k_type, k.kredit, k.momsfri';
		foreach (['debetvat', 'kreditvat'] as $column) {
			$select .= $this->db->hasColumn('kassekladde', $column) ? ', k.' . $column : ", '' AS " . $column;
		}
		// Probe every significant token together: a new suffix must not hide a known payee.
		$probeSql = implode(' OR ', array_fill(0, count($tokens), 'LOWER(k.beskrivelse) LIKE ?'));
		$rows = !$tokens ? [] : $this->db->all($select . $where . ' AND (' . $probeSql . ') ORDER BY k.transdate DESC, k.id DESC LIMIT 400', array_merge($params, array_map(static fn(string $token): string => '%' . $token . '%', $tokens)));
		$fallback = isset($line['party']) && count($rows) < 3;
		if ($fallback) {
			$rows = $this->db->all($select . $where . ' ORDER BY k.transdate DESC, k.id DESC LIMIT 400', $params);
		}
		$recent = (new DateTimeImmutable($today))->modify('-180 days')->format('Y-m-d');
		$patterns = [];
		$total = 0.0;
		foreach ($rows as $row) {
			$rowTokens = self::tokens((string)$row['beskrivelse']);
			$union = count(array_unique(array_merge($tokens, $rowTokens)));
			$score = $union === 0 ? 0.0 : count(array_intersect($tokens, $rowTokens)) / $union;
			if ($rowTokens && count(array_intersect($tokens, $rowTokens)) === count($rowTokens)) {
				$score = max(0.8, $score);
			}
			if ($fallback) {
				$score = max(0.5, $score);
			}
			$pattern = $this->pattern($row);
			if ($score < 0.5 || $pattern === null) {
				continue;
			}
			$key = saldi_runtime_json([$row['d_type'], self::accountNumber($row['debet']), $row['debetvat'], $row['k_type'], self::accountNumber($row['kredit']), $row['kreditvat'], $row['momsfri']]);
			if (!isset($patterns[$key])) {
				$patterns[$key] = $pattern + ['weight' => 0.0, 'basis' => ['count' => 0, 'last_date' => $row['transdate'], 'line_ids' => []], 'descriptions' => []];
			}
			$weight = $score * ($row['transdate'] >= $recent ? 1.0 : 0.6);
			$total += $weight;
			$patterns[$key]['weight'] += $weight;
			$patterns[$key]['basis']['count']++;
			if (count($patterns[$key]['basis']['line_ids']) < 10) {
				$patterns[$key]['basis']['line_ids'][] = (int)$row['id'];
			}
			$description = mb_substr(trim((string)$row['beskrivelse']), 0, 100);
			$patterns[$key]['descriptions'][$description] = (ifset($patterns, [$key, 'descriptions', $description], 0)) + 1;
		}
		usort($patterns, static fn(array $first, array $second): int => $second['weight'] <=> $first['weight'] ?: strcmp(saldi_runtime_json($first['debit']), saldi_runtime_json($second['debit'])));
		foreach ($patterns as &$pattern) {
			$count = $pattern['basis']['count'];
			$pattern['confidence'] = $count >= 3 && $pattern['weight'] / $total >= 0.7 ? 'high' : ($count >= 2 ? 'medium' : 'low');
			arsort($pattern['descriptions']);
			$pattern['description'] = (string)array_key_first($pattern['descriptions']);
			$pattern['source'] = 'history';
			$pattern['rule_id'] = null;
			unset($pattern['descriptions']);
		}
		unset($pattern);
		return $patterns;
	}
	/** @return array|null A text rule only borrows a counter side when it dominates history. */
	private function rule(array $rule, array $line, array $history, array $uses): ?array
	{
		$vat = $rule['vat_code'];
		$finance = $this->side('F', $rule['account'], $vat === 'none' ? '' : $vat);
		if ($finance === null) {
			return null;
		}
		$debit = $finance;
		$credit = null;
		if ($rule['match_type'] !== 'text') {
			$party = $this->side($rule['match_type'] === 'supplier' ? 'K' : 'D', $rule['match_value']);
			if ($party === null) {
				return null;
			}
			$onDebit = (ifset($line, ['party', 'side'], $rule['match_type'] === 'customer' ? 'debit' : 'credit')) === 'debit';
			$debit = $onDebit ? $party : $finance;
			$credit = $onDebit ? $finance : $party;
		} else {
			$counter = [];
			$total = 0;
			foreach ($history as $pattern) {
				foreach (['debit' => 'credit', 'credit' => 'debit'] as $side => $other) {
					if ((ifset($pattern, [$side, 'ledger'], '')) === 'account' && $pattern[$side]['account'] === $finance['account']) {
						$key = saldi_runtime_json([$side, $pattern[$other]]);
						$counter[$key] ??= ['side' => $side, 'other' => $pattern[$other], 'count' => 0];
						$counter[$key]['count'] += $pattern['basis']['count'];
						$total += $pattern['basis']['count'];
					}
				}
			}
			usort($counter, static fn(array $first, array $second): int => $second['count'] <=> $first['count']);
			if ($counter && $counter[0]['count'] / $total >= 0.6) {
				$debit = $counter[0]['side'] === 'debit' ? $finance : $counter[0]['other'];
				$credit = $counter[0]['side'] === 'credit' ? $finance : $counter[0]['other'];
			}
		}
		$usage = ifset($uses, (int)$rule['id'], ['count' => 0, 'last_date' => null]);
		return ['source' => 'rule', 'rule_id' => (int)$rule['id'], 'confidence' => 'high', 'debit' => $debit, 'credit' => $credit, 'vat_free' => $vat === 'none', 'description' => $rule['description'] ?: $line['description'], 'basis' => $usage + ['line_ids' => []]];
	}
	/** @return list<array{line_id:int,kladde_id:int,bilag:int,date:string,amount:string,description:string,posted:bool,screen:string}> */
	private function duplicates(array $line): array
	{
		if (!isset($line['party']) || (!isset($line['amount']) && empty($line['invoice']))) {
			return [];
		}
		[$partySql, $partyParams] = $this->partyFilter($line);
		$date = new DateTimeImmutable($line['date']);
		$params = array_merge([$date->modify('-60 days')->format('Y-m-d'), $date->modify('+60 days')->format('Y-m-d'), ifset($line, 'exclude_line_id', 0)], $partyParams);
		$checks = [];
		if (isset($line['amount'])) {
			$checks[] = 'ROUND(k.amount, 2) = CAST(? AS DECIMAL(15,2))';
			$params[] = SaldiToolRuntime::money($line['amount']);
		}
		if (!empty($line['invoice'])) {
			$checks[] = 'LOWER(k.faktura) = ?';
			$params[] = mb_strtolower(trim($line['invoice']));
		}
		$revenueSql = '';
		if (SaldiPermissionService::revenueHidden($this->ctx, $this->db)) {
			$revenueSql = " AND NOT EXISTS (SELECT 1 FROM kontoplan a WHERE a.regnskabsaar = ? AND a.kontotype = 'D' AND ((k.d_type = 'F' AND a.kontonr = k.debet) OR (k.k_type = 'F' AND a.kontonr = k.kredit)))";
			$params[] = $this->ctx->fiscalYear;
		}
		$rows = $this->db->all('SELECT k.id, k.kladde_id, k.bilag, k.transdate, k.amount, k.beskrivelse, l.bogfort FROM kassekladde k JOIN kladdeliste l ON l.id = k.kladde_id WHERE k.transdate BETWEEN ? AND ? AND k.id <> ?' . $partySql . ' AND (' . implode(' OR ', $checks) . ')' . $revenueSql . ' ORDER BY k.transdate DESC, k.id DESC LIMIT 5', $params);
		return array_map(static fn(array $row): array => ['line_id' => (int)$row['id'], 'kladde_id' => (int)$row['kladde_id'], 'bilag' => (int)$row['bilag'], 'date' => $row['transdate'], 'amount' => SaldiToolRuntime::money($row['amount']), 'description' => mb_substr((string)$row['beskrivelse'], 0, 100), 'posted' => in_array($row['bogfort'], ['V', '1', 'on'], true), 'screen' => SaldiScreens::link('journal', (int)$row['kladde_id'])], $rows);
	}
	/**
	 * @param array{description:string,amount?:string,date?:string,party?:array{ledger:string,account:string,side?:string},kladde_id?:int,exclude_line_id?:int,invoice?:string} $line
	 * @return array{
	 *   suggestions:list<array{
	 *     source:string,rule_id:?int,confidence:string,
	 *     debit:?array{ledger:string,account:string,name:string,vat_code:string},
	 *     credit:?array{ledger:string,account:string,name:string,vat_code:string},
	 *     vat_free:bool,description:string,
	 *     basis:array{count:int,last_date:?string,line_ids:list<int>}
	 *   }>,
	 *   duplicates:list<array{line_id:int,kladde_id:int,bilag:int,date:string,amount:string,description:string,posted:bool,screen:string}>
	 * }
	 */
	public function suggest(array $line): array
	{
		SaldiPermissionService::require($this->ctx, $this->db, 'journal.read');
		SaldiPermissionService::require($this->ctx, $this->db, 'accounts.read');
		$line = $this->line($line);
		$this->loadAccounts();
		$history = $this->history($line);
		if ($this->uses === null) {
			$this->uses = [];
			foreach ($this->db->all("SELECT subject, COUNT(*) AS count, MAX(created_at) AS last_date FROM assist_events WHERE event = 'suggestion_used' AND created_at >= ? AND subject LIKE 'rule:%' GROUP BY subject", [gmdate('Y-m-d H:i:s', time() - 90 * 86400)]) as $row) {
				$this->uses[(int)substr($row['subject'], 5)] = ['count' => (int)$row['count'], 'last_date' => substr($row['last_date'], 0, 10)];
			}
		}
		$rules = array_filter($this->rules ??= SaldiPostingRules::active($this->db), static function (array $rule) use ($line): bool {
			return $rule['match_type'] === 'text'
				? str_contains(' ' . self::normalise($line['description']) . ' ', ' ' . $rule['match_value'] . ' ')
				: isset($line['party']) && $rule['match_type'] === $line['party']['ledger'] && $rule['match_value'] === $line['party']['account'];
		});
		usort($rules, static fn(array $first, array $second): int => ($first['match_type'] === 'text') <=> ($second['match_type'] === 'text') ?: mb_strlen($second['match_value']) <=> mb_strlen($first['match_value']) ?: (int)$second['id'] <=> (int)$first['id']);
		$suggestions = [];
		$seen = [];
		foreach ($rules as $rule) {
			$suggestion = $this->rule($rule, $line, $history, $this->uses);
			if ($suggestion !== null && !isset($seen[$this->key($suggestion)])) {
				$seen[$this->key($suggestion)] = true;
				$suggestions[] = $suggestion;
			}
			if (count($suggestions) === 3) {
				break;
			}
		}
		foreach ($history as $suggestion) {
			if (count($suggestions) === 3) {
				break;
			}
			if (!isset($seen[$this->key($suggestion)])) {
				unset($suggestion['weight']);
				$suggestions[] = $suggestion;
				$seen[$this->key($suggestion)] = true;
			}
		}
		return ['suggestions' => $suggestions, 'duplicates' => $this->duplicates($line)];
	}
}
