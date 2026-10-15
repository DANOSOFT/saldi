<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/kinds/SaldiSettlementKind.php --- ai/mcp-assistant --- 2026.10.04
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
// Exact posted-item settlements with pinned source rows and guarded restoration.
//
// 20261004 CDX/LH Created.
// 20261004 CDX/LH Implement exact settlement approval and undo.
// 20261008 CL/LH Allocate udlign_id through the shared helper (openpost_udlign_seq on PostgreSQL).

declare(strict_types=1);

require_once __DIR__ . '/../SaldiInboxKind.php';
require_once __DIR__ . '/../../tools/SaldiToolRuntime.php';
require_once __DIR__ . '/../../std_func.php';

final class SaldiSettlementKind extends SaldiBaseInboxKind
{
	public function name(): string
	{
		return 'settlement.draft';
	}
	public function actionClass(): string
	{
		return 'persistent';
	}
	public function proposePermission(): string
	{
		return 'debtors.draft';
	}
	public function decidePermission(array $item): string
	{
		return ifset($item, ['payload', 'party_type']) === 'creditor' ? 'creditors.draft' : 'debtors.draft';
	}
	/** Parse stored numeric(15,3) without floats or losing sub-cent differences. */
	public static function milli(string $amount): int
	{
		if (!preg_match('/^(-?)([0-9]{1,12})(?:\.([0-9]{1,3}))?$/D', $amount, $parts)) {
			throw new SaldiRuntimeError('UNAVAILABLE', 'Invalid stored amount');
		}
		$value = (int)$parts[2] * 1000 + (int)str_pad(ifset($parts, 3, ''), 3, '0');
		return $parts[1] === '-' ? -$value : $value;
	}
	/** Format integer thousandths as an exact decimal string. */
	public static function decimal(int $milli): string
	{
		return ($milli < 0 ? '-' : '') . intdiv(abs($milli), 1000) . '.' . str_pad((string)(abs($milli) % 1000), 3, '0', STR_PAD_LEFT);
	}
	/** Half-away-from-zero integer øre, the settlement and display boundary. */
	public static function cents(string $amount): int
	{
		$milli = self::milli($amount);
		return ($milli < 0 ? -1 : 1) * intdiv(abs($milli) + 5, 10);
	}
	/** @return list<array<string,mixed>> Sorted source records, optionally locked by apply/undo. */
	private function rows(SaldiDb $db, array $ids, bool $lock = false): array
	{
		return $db->all('SELECT * FROM openpost WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id' . ($lock ? ' FOR UPDATE' : ''), $ids);
	}
	/** @return array{party:array,rows:list<array>,revision:string} Checked source set; no writes. */
	private function source(SaldiTenantContext $ctx, SaldiDb $db, array $input, bool $lock = false): array
	{
		$type = ifset($input, 'party_type');
		$account = ifset($input, 'party_account');
		$ids = ifset($input, 'item_ids');
		if (!in_array($type, ['debtor', 'creditor'], true) || !is_string($account) || !preg_match('/^[0-9]{1,15}$/D', $account)
			|| !is_array($ids) || !array_is_list($ids) || count($ids) < 2 || count($ids) > 10
			|| count(array_unique($ids, SORT_REGULAR)) !== count($ids)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid settlement selection');
		}
		foreach ($ids as $id) {
			if (!is_int($id) || $id < 1) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid open item id');
			}
		}
		SaldiPermissionService::require($ctx, $db, $this->decidePermission(['payload' => $input]));
		$party = $db->one("SELECT id, kontonr, art, firmanavn, lukket FROM adresser WHERE kontonr = ? AND art = ?" . ($lock ? ' FOR UPDATE' : ''), [$account, $type === 'creditor' ? 'K' : 'D']);
		$rows = $this->rows($db, $ids, $lock);
		$revision = hash('sha256', saldi_runtime_json([$party, $rows]));
		if (isset($input['source_revision']) && (!is_string($input['source_revision']) || !hash_equals($input['source_revision'], $revision))) {
			throw new SaldiRuntimeError('CONFLICT', 'Selected open items changed', ['reason' => 'not_open']);
		}
		if (!$party || $party['lukket'] === 'on' || count($rows) !== count($ids)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Party or selected items unavailable');
		}
		$year = $db->one("SELECT kodenr, box1, box2, box3, box4, box5 FROM grupper WHERE art = 'RA' AND kodenr = ?", [$ctx->fiscalYear]);
		if (!$year || $year['box5'] !== 'on') {
			throw new SaldiRuntimeError('CLOSED_PERIOD', 'Active fiscal year unavailable');
		}
		$end = SaldiToolRuntime::year($year)['end'];
		$currency = SaldiToolRuntime::currency($ctx, $rows[0]['valuta']);
		$total = $cents = 0;
		foreach ($rows as $row) {
			if ((int)$row['konto_id'] !== (int)$party['id'] || $row['udlignet'] === '1') {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Items must be unsettled and belong to this party');
			}
			if (SaldiToolRuntime::currency($ctx, $row['valuta']) !== $currency) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Currencies must match', ['reason' => 'currency_mismatch']);
			}
			if (!is_string($row['transdate']) || SaldiToolRuntime::date($row['transdate']) > $end) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Item date is after the active fiscal year');
			}
			$total += self::milli((string)$row['amount']);
			$cents += self::cents((string)$row['amount']);
		}
		if ($total !== 0 || $cents !== 0) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Use manual settlement for differences', ['reason' => 'not_balanced', 'difference' => SaldiToolRuntime::money(self::decimal($total)), 'difference_exact' => self::decimal($total), 'currency' => $currency]);
		}
		return ['party' => $party, 'rows' => $rows, 'revision' => $revision];
	}
	/** @return array{payload:array,title:string,subject_type:?string,subject_id:?string,proposed_rev:?string,dedupe_key:?string,visibility:string,assignee_permission:?string,owner_user_id:?int} */
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		$source = $this->source($ctx, $db, $input);
		$rows = $source['rows'];
		$payload = ['party_type' => $input['party_type'], 'party_account' => $input['party_account'],
			'item_ids' => array_map(static fn(array $row): int => (int)$row['id'], $rows),
			'source_revision' => $source['revision'], 'currency' => SaldiToolRuntime::currency($ctx, $rows[0]['valuta']),
			'rows' => array_map(static fn(array $row): array => ['invoice' => (string)$row['faktnr'], 'date' => $row['transdate'], 'amount' => SaldiToolRuntime::money($row['amount']), 'currency' => SaldiToolRuntime::currency($ctx, $row['valuta'])], $rows)];
		$permission = $this->decidePermission(['payload' => $payload]);
		$n = $this->normalized($ctx, $payload, $this->text($ctx, $db, 6100, 'Udligning') . ': ' . SaldiToolRuntime::text($source['party']['firmanavn']), null, 'permission', $permission);
		$n['subject_type'] = 'party';
		$n['subject_id'] = (string)$source['party']['id'];
		return $n;
	}
	/** @return array{udlign_id:int,item_ids:list<int>,before:list<array{id:int,udlignet:?string,udlign_id:?int,udlign_date:?string}>,rows_sha256:string} */
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		if (!$ctx->isHuman()) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Human decision required');
		}
		$db->lock('saldi_udlign_id');
		if ($db->isPostgres()) {
			$db->exec('LOCK TABLE openpost IN EXCLUSIVE MODE');
		}
		$source = $this->source($ctx, $db, $item['payload'], true);
		$rows = $source['rows'];
		require_once __DIR__ . '/../../openpost/udlignId.php';
		$id = udlign_next_id_db($db);
		$before = array_map(static fn(array $row): array => ['id' => (int)$row['id'], 'udlignet' => $row['udlignet'], 'udlign_id' => $row['udlign_id'] === null ? null : (int)$row['udlign_id'], 'udlign_date' => $row['udlign_date']], $rows);
		$ids = array_column($before, 'id');
		$count = $db->exec("UPDATE openpost SET udlignet = '1', udlign_id = ?, udlign_date = ? WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ") AND COALESCE(udlignet, '') <> '1'", array_merge([$id, max(array_column($rows, 'transdate'))], $ids));
		if ($count !== count($ids)) {
			throw new SaldiRuntimeError('CONFLICT', 'Selected items changed', ['reason' => 'not_open']);
		}
		return ['udlign_id' => $id, 'item_ids' => $ids, 'before' => $before, 'rows_sha256' => hash('sha256', saldi_runtime_json($this->rows($db, $ids)))];
	}
	/** Refuse undo after any manual unsettlement or source-record edit. */
	public function canUndo(SaldiTenantContext $ctx, SaldiDb $db, array $item): bool
	{
		$result = ifset($item, 'result');
		if (ifset($item, 'status') !== 'applied' || !is_array($result) || empty($result['item_ids']) || !isset($result['rows_sha256'], $result['udlign_id'])) {
			return false;
		}
		$rows = $this->rows($db, $result['item_ids']);
		foreach ($rows as $row) {
			if ($row['udlignet'] !== '1' || (int)$row['udlign_id'] !== $result['udlign_id']) {
				return false;
			}
		}
		return count($rows) === count($result['item_ids']) && hash_equals($result['rows_sha256'], hash('sha256', saldi_runtime_json($rows)));
	}
	/** @return array{udlign_id:int,item_ids:list<int>,restored:int} */
	public function undo(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		if (!$ctx->isHuman()) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Human decision required');
		}
		SaldiPermissionService::require($ctx, $db, $this->decidePermission($item));
		$db->lock('saldi_udlign_id');
		$this->rows($db, $item['result']['item_ids'], true);
		if (!$this->canUndo($ctx, $db, $item)) {
			throw new SaldiRuntimeError('CONFLICT', 'Settlement changed', ['reason' => 'undo_changed']);
		}
		foreach ($item['result']['before'] as $before) {
			$db->exec('UPDATE openpost SET udlignet = ?, udlign_id = ?, udlign_date = ? WHERE id = ?', [$before['udlignet'], $before['udlign_id'], $before['udlign_date'], $before['id']]);
		}
		return ['udlign_id' => $item['result']['udlign_id'], 'item_ids' => $item['result']['item_ids'], 'restored' => count($item['result']['before'])];
	}
	/** @return array{columns:list<string>,rows:list<list<string>>,fields:list<array{label:string,value:string}>} */
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		return ['columns' => [$this->text($ctx, $db, 6203, 'Faktura'), $this->text($ctx, $db, 5741, 'Dato'), $this->text($ctx, $db, 5746, 'Beløb')],
			'rows' => array_map(static fn(array $row): array => [$row['invoice'], $row['date'], $row['amount'] . ' ' . ifset($row, 'currency', ifset($item, ['payload', 'currency'], $ctx->baseCurrency))], ifset($item, ['payload', 'rows'], [])),
			'fields' => [['label' => $this->text($ctx, $db, 2373, 'I alt'), 'value' => '0.00 ' . ifset($item, ['payload', 'currency'], $ctx->baseCurrency)]]];
	}
}
