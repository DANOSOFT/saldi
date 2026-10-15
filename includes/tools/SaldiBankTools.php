<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiBankTools.php --- ai/mcp-assistant --- 2026.10.10
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
// Statement staging, deterministic match previews and approval-only journal proposals.
//
// 20261004 CDX/LH Created.
// 20261004 CDX/LH Implement bank import, matching and atomic journal proposal reservations.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.
// 20261007 CL/LH List and propose apply the batch claim over every row in statement order, before any filter.
// 20261010 CDX/LH Separate upload imports from the model content limit.

declare(strict_types=1);

require_once __DIR__ . '/../automation/SaldiBankMatcher.php';
require_once __DIR__ . '/../automation/SaldiInboxService.php';
require_once __DIR__ . '/SaldiDraftTools.php';

final class SaldiBankTools
{
	/** @return array{data:array,citations:list<array>,possibly_incomplete:bool,result_count:int} */
	public static function import(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		return self::importStatement($ctx, $db, $args, true);
	}
	/** Import uploaded content without the model argument cap. @return array{data:array,citations:list<array>,possibly_incomplete:bool,result_count:int} */
	public static function importUpload(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		return self::importStatement($ctx, $db, $args, false);
	}
	/** @return array{data:array,citations:list<array>,possibly_incomplete:bool,result_count:int} */
	private static function importStatement(SaldiTenantContext $ctx, SaldiDb $db, array $args, bool $modelContent): array
	{
		SaldiPermissionService::require($ctx, $db, 'journal.draft');
		if (!is_string($args['content'] ?? null) || ($modelContent && mb_strlen($args['content']) > 200000)
			|| !is_string($args['bank_account'] ?? null) || (isset($args['filename']) && (!is_string($args['filename']) || mb_strlen($args['filename']) > 255))) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid bank statement input');
		}
		$data = SaldiBankImport::stage($ctx, $db, $args['bank_account'], SaldiBankStatementParser::parse($args['content'], $args['filename'] ?? null));
		return SaldiToolRuntime::result($data, self::citations($data['lines']));
	}
	private static function batch(mixed $batch): string
	{
		if (!is_string($batch) || !preg_match('/^[a-f0-9]{32}$/D', $batch)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid bank batch id');
		}
		return $batch;
	}
	/** @return list<array> Raw staging records in statement order. */
	private static function rows(SaldiDb $db, string $batch, bool $lock = false): array
	{
		$rows = $db->all('SELECT * FROM bank_import_lines WHERE batch_id = ? ORDER BY line_no, id' . ($lock ? ' FOR UPDATE' : ''), [$batch]);
		if (!$rows) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Bank batch not found');
		}
		return $rows;
	}
	/** @return array{line_id:int,date:string,description:string,amount:string,currency:string,status:string} */
	private static function line(array $row): array
	{
		return ['line_id' => (int)$row['id'], 'date' => $row['transdate'], 'description' => $row['description'], 'amount' => SaldiToolRuntime::money($row['amount']), 'currency' => $row['currency'], 'status' => $row['status']];
	}
	/** @return list<array{id:string,label:string,screen:string}> */
	private static function citations(array $rows): array
	{
		return array_map(static fn(array $row): array => SaldiToolRuntime::citation('bank_line', $row['line_id'], $row['description'], 'bank_import'), array_slice($rows, 0, 10));
	}
	/** @return array{data:array,citations:list<array>,possibly_incomplete:bool,result_count:int} */
	public static function listLines(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'journal.read');
		$status = $args['status'] ?? null;
		if ($status !== null && !in_array($status, ['new', 'proposed', 'duplicate', 'ignored'], true)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid bank line status');
		}
		$matcher = new SaldiBankMatcher($ctx, $db);
		$cacheKey = SaldiBankImport::matchCacheKey($ctx, $db);
		$raw = self::rows($db, self::batch($args['batch_id'] ?? null));
		$entries = [];
		foreach ($raw as $row) {
			$cached = $row['proposal'] !== null ? json_decode($row['proposal'], true, 512, JSON_THROW_ON_ERROR) : null;
			$match = ($cached['cache_key'] ?? null) === $cacheKey ? $cached['match'] : ($row['status'] === 'proposed' && $cached !== null ? $cached : $matcher->match($row));
			$entries[] = ['status' => $row['status'], 'match' => $match];
		}
		// Claim over the whole batch first, so a status filter never changes which line holds an invoice.
		$matches = SaldiBankMatcher::claimBatch($entries);
		$rows = [];
		foreach ($raw as $i => $row) {
			if ($status === null || $row['status'] === $status) {
				$rows[] = self::line($row) + ['match' => $matches[$i]];
			}
		}
		return SaldiToolRuntime::result(['rows' => $rows, 'total' => count($rows)], self::citations($rows));
	}
	/** @return array{data:array,citations:list<array>,possibly_incomplete:bool,result_count:int} */
	public static function propose(SaldiTenantContext $ctx, SaldiDb $db, array $args): array
	{
		SaldiPermissionService::require($ctx, $db, 'journal.draft');
		SaldiPermissionService::require($ctx, $db, 'journal.read');
		$batch = self::batch($args['batch_id'] ?? null);
		$request = $args['request_id'] ?? null;
		if (!is_string($request) || strlen($request) < 1 || strlen($request) > 64
			|| !is_string($args['explanation'] ?? null) || mb_strlen(trim($args['explanation'])) < 1 || mb_strlen($args['explanation']) > 500) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected request_id and explanation');
		}
		$selection = $args['line_ids'] ?? null;
		if ($selection !== null && (!is_array($selection) || !array_is_list($selection) || count($selection) < 1 || count($selection) > 200
			|| count(array_unique($selection, SORT_REGULAR)) !== count($selection))) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected 1 to 200 distinct line ids');
		}
		foreach ($selection ?? [] as $id) {
			if (!is_int($id) || $id < 1) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid bank line id');
			}
		}
		if ($selection !== null) {
			sort($selection);
		}
		$bankRequest = ['batch_id' => $batch, 'line_ids' => $selection, 'kladde_id' => $args['kladde_id'] ?? null];
		return $db->transaction(function () use ($ctx, $db, $args, $batch, $selection, $request, $bankRequest): array {
			$db->lock('saldi_bank_propose_' . $batch);
			$requestKey = ($ctx->grantId ? 'g' . $ctx->grantId : 'u' . $ctx->userId) . ':' . $request;
			$existing = $db->one('SELECT id, kind, payload FROM inbox_items WHERE request_key = ?', [$requestKey]);
			if ($existing !== null) {
				// Preserve inbox visibility and replay semantics even after staging retention or undo.
				$item = (new SaldiInboxService($db, SaldiKindRegistry::default()))->get($ctx, (int)$existing['id']);
				if ($existing['kind'] !== 'journal.draft_entry' || saldi_runtime_json($item['payload']['bank_request'] ?? null) !== saldi_runtime_json($bankRequest)) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'request_id already used for a different proposal', ['reason' => 'request_id_reused', 'fields' => ['$.request_id']]);
				}
				return SaldiDraftTools::propose('journal.draft_entry', $ctx, $db, $item['payload'] + array_intersect_key($args, array_flip(['request_id', 'explanation', 'citations', 'tainted'])));
			}
			$raw = self::rows($db, $batch, true);
			$matcher = new SaldiBankMatcher($ctx, $db);
			// Fresh matches for open lines, the reserved match for proposed ones, claimed over the whole batch.
			$matches = SaldiBankMatcher::claimBatch(array_map(static fn(array $row): array => ['status' => $row['status'], 'match' => match ($row['status']) {
				'new' => $matcher->match($row),
				'proposed' => $row['proposal'] !== null ? json_decode($row['proposal'], true, 512, JSON_THROW_ON_ERROR) : null,
				default => null,
			}], $raw));
			$chosen = [];
			$lines = [];
			foreach ($raw as $i => $row) {
				$explicit = $selection !== null && in_array((int)$row['id'], $selection, true);
				if ($selection !== null && !$explicit) {
					continue;
				}
				if ($row['status'] !== 'new') {
					if ($explicit) {
						throw new SaldiRuntimeError('CONFLICT', 'Bank line already reserved or ignored', ['reason' => 'not_open']);
					}
					continue;
				}
				$match = $matches[$i];
				if ($match === null || (!$explicit && $match['confidence'] !== 'high')) {
					if ($explicit) {
						throw new SaldiRuntimeError('VALIDATION_ERROR', 'Bank line has no safe counter account', ['reason' => 'no_match', 'line_id' => (int)$row['id']]);
					}
					continue;
				}
				$bank = ['type' => 'F', 'account' => $row['bank_account']];
				$counter = $match['party'] !== null ? ['type' => $match['party']['ledger'] === 'customer' ? 'D' : 'K', 'account' => $match['party']['account']] : ['type' => 'F', 'account' => $match['account']['account']];
				if (!empty($match['vat_code']) && empty($match['vat_free']) && $match['account'] !== null
					&& $match['vat_code'] !== (string)$db->value('SELECT moms FROM kontoplan WHERE kontonr = ? AND regnskabsaar = ?', [$match['account']['account'], $ctx->fiscalYear])) {
					$counter['vat_code'] = $match['vat_code'];
				}
				$incoming = SaldiBankStatementParser::milli($row['amount']) > 0;
				$lines[] = ['bank_line_id' => (int)$row['id'], 'date' => $row['transdate'], 'description' => mb_substr(trim($row['description']), 0, 100) ?: SaldiText::get($db, 6183, $ctx->languageId, 'Bankpostering'), 'amount' => SaldiToolRuntime::money(ltrim($row['amount'], '-')), 'debit' => $incoming ? $bank : $counter, 'credit' => $incoming ? $counter : $bank, 'vat_free' => $match['vat_free'] ?? false]
					+ ($match['party'] !== null && $match['invoice'] !== null ? ['invoice' => $match['invoice']] : []);
				$chosen[] = ['row' => $row, 'match' => $match];
			}
			if ($selection !== null && count($lines) !== count($selection)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Selected lines do not belong to this batch');
			}
			if (!$lines || count($lines) > 200) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected 1 to 200 matched bank lines; select a smaller set', ['reason' => !$lines ? 'no_match' : 'too_many_lines']);
			}
			$input = ['bank_batch_id' => $batch, 'bank_account' => $raw[0]['bank_account'], 'bank_request' => $bankRequest, 'date' => $lines[0]['date'], 'lines' => $lines, 'note' => SaldiText::get($db, 6120, $ctx->languageId, 'Bankfil til kassekladde')]
				+ array_intersect_key($args, array_flip(['kladde_id', 'request_id', 'explanation', 'citations', 'tainted']));
			if (!isset($input['citations'])) {
				$input['citations'] = array_map(static fn(array $c): string => 'saldi:bank_line:' . $c['row']['id'], array_slice($chosen, 0, 10));
			}
			$result = SaldiDraftTools::propose('journal.draft_entry', $ctx, $db, $input);
			foreach ($chosen as $choice) {
				if ($db->exec("UPDATE bank_import_lines SET status = 'proposed', inbox_item_id = ?, proposal = ? WHERE id = ? AND status = 'new' AND inbox_item_id IS NULL", [$result['data']['id'], saldi_runtime_json($choice['match']), $choice['row']['id']]) !== 1) {
					throw new SaldiRuntimeError('CONFLICT', 'Bank line changed', ['reason' => 'not_open']);
				}
			}
			return $result;
		});
	}
}
