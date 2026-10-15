<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiBankImport.php --- ai/mcp-assistant --- 2026.10.05
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
// Bank staging, duplicate detection and atomic release of rejected or undone proposals.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.
// 20261007 CL/LH Cached matches are stored unclaimed (claims are applied per batch); bump the cache key.

declare(strict_types=1);

require_once __DIR__ . '/SaldiBankStatementParser.php';
require_once __DIR__ . '/SaldiPermissionService.php';
require_once __DIR__ . '/../tools/SaldiToolRuntime.php';

final class SaldiBankImport
{
	/** Require an open balance account in the active year, including at approval. */
	public static function bankAccount(SaldiTenantContext $ctx, SaldiDb $db, mixed $account): string
	{
		if (!is_string($account) || !preg_match('/^[0-9]{1,15}$/D', $account)
			|| !$db->one("SELECT kontonr FROM kontoplan WHERE kontonr = ? AND regnskabsaar = ? AND kontotype = 'S' AND COALESCE(lukket, '') <> 'on'", [$account, $ctx->fiscalYear])) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Bank account must be an open balance account in the active fiscal year', ['fields' => ['bank_account']]);
		}
		return ltrim($account, '0') ?: '0';
	}
	/** @return array{id:int,start:string,end:string} Active, unlocked fiscal year bounds. */
	public static function year(SaldiTenantContext $ctx, SaldiDb $db): array
	{
		$row = $db->one("SELECT kodenr, box1, box2, box3, box4 FROM grupper WHERE art = 'RA' AND kodenr = ? AND box5 = 'on' AND COALESCE(box10, '') <> 'on'", [$ctx->fiscalYear]);
		if (!$row) {
			throw new SaldiRuntimeError('CLOSED_PERIOD', 'Fiscal year closed');
		}
		return SaldiToolRuntime::year($row);
	}
	/** Canonical text for content hashes; retain invoice digits and Danish letters. */
	public static function normalise(string $text): string
	{
		return trim(preg_replace('/\s+/u', ' ', mb_strtolower($text, 'UTF-8')));
	}
	/** Existing signed bank movement within two days, in the base currency. */
	public static function journalDuplicate(SaldiDb $db, string $account, array $row, array $claimed = []): ?int
	{
		$date = new DateTimeImmutable($row['date']);
		$exclude = $claimed ? ' AND id NOT IN (' . implode(',', array_fill(0, count($claimed), '?')) . ')' : '';
		$id = $db->value("SELECT id FROM kassekladde WHERE transdate BETWEEN ? AND ? AND COALESCE(valuta, 0) = 0 AND ((d_type = 'F' AND debet = ?) OR (k_type = 'F' AND kredit = ?)) AND (CASE WHEN d_type = 'F' AND debet = ? THEN amount ELSE 0 END - CASE WHEN k_type = 'F' AND kredit = ? THEN amount ELSE 0 END) = CAST(? AS DECIMAL(15,3)) " . $exclude . " ORDER BY id LIMIT 1", array_merge([$date->modify('-2 days')->format('Y-m-d'), $date->modify('+2 days')->format('Y-m-d'), $account, $account, $account, $account, $row['amount']], $claimed));
		return $id === null ? null : (int)$id;
	}
	/**
	 * Persist staging only; uploads never create a journal or book entries.
	 * @return array{batch_id:string,lines:list<array{line_id:int,date:string,description:string,amount:string,currency:string,status:string}>,duplicates:int,warnings:list<string>}
	 */
	public static function stage(SaldiTenantContext $ctx, SaldiDb $db, string $bankAccount, array $parsed): array
	{
		SaldiPermissionService::require($ctx, $db, 'journal.draft');
		$bankAccount = self::bankAccount($ctx, $db, $bankAccount);
		$year = self::year($ctx, $db);
		$parsed = self::selectStatement($ctx, $db, $bankAccount, $parsed);
		if (!is_array($parsed['rows'] ?? null) || !array_is_list($parsed['rows']) || count($parsed['rows']) > 5000) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected at most 5000 statement rows');
		}
		return $db->transaction(function () use ($ctx, $db, $bankAccount, $parsed, $year): array {
			$db->lock('saldi_bank_stage');
			$db->exec('DELETE FROM bank_import_lines WHERE created_at < ?', [gmdate('Y-m-d H:i:s', time() - 30 * 86400)]);
			$batch = bin2hex(random_bytes(16));
			$result = ['batch_id' => $batch, 'lines' => [], 'duplicates' => 0, 'warnings' => $parsed['warnings'] ?? []];
			$claimed = [];
			$claimedHashes = [];
			$matcher = null;
			foreach ($parsed['rows'] as $i => $row) {
				if (!is_array($row) || !is_string($row['date'] ?? null) || self::validDate($row['date']) === false
					|| !is_string($row['amount'] ?? null) || !preg_match('/^-?[0-9]{1,12}(?:\.[0-9]{1,3})?$/D', $row['amount'])
					|| !is_string($row['description'] ?? null)
					|| !is_string($row['currency'] ?? null) || !preg_match('/^[A-Z]{3}$/D', $row['currency'])
					|| (isset($row['reference']) && (!is_string($row['reference'])))) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid statement row', ['fields' => ['rows[' . $i . ']']]);
				}
				foreach (['description' => 255, 'reference' => 100] as $field => $limit) {
					if (isset($row[$field]) && mb_strlen($row[$field]) > $limit) {
						$row[$field] = mb_substr($row[$field], 0, $limit);
						$result['warnings'][] = sprintf(SaldiText::get($db, 6306, $ctx->languageId, 'En tekst på linje %s er afkortet til %s tegn.'), (string)($i + 1), (string)$limit);
					}
				}
				$amount = SaldiBankStatementParser::amount($row['amount']);
				$hash = hash('sha256', saldi_runtime_json([$bankAccount, $row['date'], (string)SaldiBankStatementParser::milli($amount), self::normalise($row['description']), self::normalise($row['reference'] ?? '')]));
				$status = 'new';
				$proposal = null;
				if ($row['date'] < $year['start'] || $row['date'] > $year['end']) {
					$status = 'ignored';
					$result['warnings'][] = sprintf(SaldiText::get($db, 6182, $ctx->languageId, 'Linje %s ligger uden for det aktive regnskabsår.'), (string)($i + 1));
				} elseif (($prior = $db->one("SELECT id, inbox_item_id FROM bank_import_lines WHERE line_hash = ? AND status = 'proposed'" . ($claimedHashes ? ' AND id NOT IN (' . implode(',', array_fill(0, count($claimedHashes), '?')) . ')' : '') . ' ORDER BY id LIMIT 1', array_merge([$hash], $claimedHashes))) !== null) {
					$status = 'duplicate';
					$claimedHashes[] = (int)$prior['id'];
					// A reserved hash and its applied journal row represent one movement.
					$item = $db->one("SELECT payload, result FROM inbox_items WHERE id = ? AND status = 'applied'", [$prior['inbox_item_id']]);
					if ($item !== null) {
						$payload = json_decode($item['payload'], true, 512, JSON_THROW_ON_ERROR);
						$receipt = json_decode($item['result'], true, 512, JSON_THROW_ON_ERROR);
						foreach ($payload['lines'] as $index => $line) {
							if (($line['bank_line_id'] ?? null) === (int)$prior['id'] && isset($receipt['line_ids'][$index])) {
								$claimed[] = (int)$receipt['line_ids'][$index];
								$proposal = saldi_runtime_json(['line_id' => (int)$receipt['line_ids'][$index]]);
							}
						}
					}
				} elseif ($row['currency'] === $ctx->baseCurrency && ($duplicate = self::journalDuplicate($db, $bankAccount, $row, $claimed)) !== null) {
					$status = 'duplicate';
					$proposal = saldi_runtime_json(['line_id' => $duplicate]);
					$claimed[] = $duplicate;
				}
				// Supersede abandoned previews, while preserving every reserved proposal.
				$db->exec("UPDATE bank_import_lines SET status = 'ignored', proposal = ? WHERE line_hash = ? AND batch_id <> ? AND status = 'new' AND inbox_item_id IS NULL", [saldi_runtime_json(['superseded_by' => $batch]), $hash, $batch]);
				if ($status === 'new' && SaldiPermissionService::allows($ctx, $db, 'journal.read')) {
					require_once __DIR__ . '/SaldiBankMatcher.php';
					$matcher ??= new SaldiBankMatcher($ctx, $db);
					$proposal = saldi_runtime_json(['cache_key' => self::matchCacheKey($ctx, $db), 'match' => $matcher->match($row + ['bank_account' => $bankAccount])]);
				}
				$balance = isset($row['balance']) && is_string($row['balance']) ? SaldiBankStatementParser::amount($row['balance']) : null;
				if (isset($row['balance']) && $balance === null) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid statement balance');
				}
				$id = $db->insert('bank_import_lines', ['batch_id' => $batch, 'line_no' => $i + 1, 'bank_account' => $bankAccount, 'transdate' => $row['date'], 'description' => $row['description'], 'amount' => $amount, 'currency' => $row['currency'], 'balance' => $balance, 'reference' => $row['reference'] ?? null, 'line_hash' => $hash, 'status' => $status, 'proposal' => $proposal, 'inbox_item_id' => null, 'created_by' => $ctx->userId, 'created_at' => gmdate('Y-m-d H:i:s')]);
				$result['lines'][] = ['line_id' => $id, 'date' => $row['date'], 'description' => $row['description'], 'amount' => SaldiToolRuntime::money($amount), 'currency' => $row['currency'], 'status' => $status];
				$result['duplicates'] += $status === 'duplicate' ? 1 : 0;
			}
			return $result;
		});
	}
	/** Context-bound previews never reveal matches computed with broader permissions. */
	public static function matchCacheKey(SaldiTenantContext $ctx, SaldiDb $db): string
	{
		return hash('sha256', saldi_runtime_json(['unclaimed-v2', $ctx->rights, $ctx->scopes, $ctx->fiscalYear, SaldiPermissionService::revenueHidden($ctx, $db)]));
	}
	/** Select by an explicitly associated IBAN/BBAN; never assume the company's bank is this ledger. */
	private static function selectStatement(SaldiTenantContext $ctx, SaldiDb $db, string $account, array $parsed): array
	{
		$accounts = $parsed['statement_accounts'] ?? [];
		if (!$accounts) {
			return $parsed;
		}
		$identifier = SaldiSettings::get($db, 'assistant', 'bank_iban.' . $account, '');
		if ($db->hasColumn('kontoplan', 'iban')) {
			$identifier = (string)$db->value('SELECT iban FROM kontoplan WHERE kontonr = ? AND regnskabsaar = ?', [$account, $ctx->fiscalYear]) ?: $identifier;
		}
		$normalise = static fn(string $value): string => strtoupper(preg_replace('/\s+/', '', $value));
		if ($identifier !== '') {
			$parsed['rows'] = array_values(array_filter($parsed['rows'], static fn(array $row): bool => $normalise($row['statement_account'] ?? '') === $normalise($identifier)));
			if ($parsed['rows']) {
				return $parsed;
			}
		} elseif (count($accounts) === 1 && ($accounts[0] !== '' || ($parsed['statement_count'] ?? 1) === 1)) {
			return $parsed;
		}
		$accountList = implode(', ', array_map(static fn(string $value): string => $value ?: '(ukendt konto)', $accounts));
		$warning = sprintf(SaldiText::get($db, 6307, 1, 'Bankkontoen kan ikke knyttes sikkert til filen. Konti i filen: %s. Vælg en fil med én konto eller angiv kontoens IBAN.'), $accountList);
		throw new SaldiRuntimeError('VALIDATION_ERROR', 'Statement account selection required', ['warning_da' => $warning, 'warning_id' => 6307, 'warning_args' => [$accountList]]);
	}
	private static function validDate(string $date): bool
	{
		return SaldiBankStatementParser::date($date) === $date;
	}
	/** Release only lines reserved by this item; invoked in the decision transaction. */
	public static function release(SaldiDb $db, int $itemId): void
	{
		$db->exec("UPDATE bank_import_lines SET status = 'new', inbox_item_id = NULL, proposal = NULL WHERE inbox_item_id = ? AND status = 'proposed'", [$itemId]);
	}
	/** Terminal inbox paths bypass undo; this narrow hook also covers agent withdrawal. */
	public static function inboxStatusChanged(SaldiDb $db, array $item, string $status): void
	{
		if (($item['kind'] ?? '') === 'journal.draft_entry' && in_array($status, ['rejected', 'expired', 'cancelled', 'undone'], true)) {
			$payload = is_array($item['payload']) ? $item['payload'] : json_decode($item['payload'], true, 512, JSON_THROW_ON_ERROR);
			if (isset($payload['bank_batch_id'])) {
				self::release($db, (int)$item['id']);
			}
		}
	}
}
