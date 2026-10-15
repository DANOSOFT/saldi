<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/kinds/SaldiPurchaseInvoiceStageKind.php --- ai/mcp-assistant --- 2026.10.05
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
// Stage supplier invoice fields for the human receiving/posting flow, with exact guarded undo.
//
// 20261004 CDX/LH Created.
// 20261004 CDX/LH Implement validated invoice staging, snapshots and exact undo.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.
// 20261005 CDX/LH Format receipt quantities on approval cards in Danish notation.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiMasterDraftKind.php';
require_once __DIR__ . '/../../tools/SaldiToolRuntime.php';

final class SaldiPurchaseInvoiceStageKind extends SaldiMasterDraftKind
{
	public function name(): string { return 'purchase_order.invoice_stage'; }
	public function actionClass(): string { return 'reversible_draft'; }
	public function proposePermission(): string { return 'purchases.draft'; }
	public function decidePermission(array $item): string { return 'purchases.draft'; }

	/** @return array{header:?array,lines:list<array>,batches:list<array>} Exact source state, including zero-quantity receipts. */
	private function state(SaldiDb $db, int $id): array
	{
		$lock = $db->inTransaction() ? ' FOR UPDATE' : '';
		$state = [
			'header' => $db->one('SELECT * FROM ordrer WHERE id = ?' . $lock, [$id]),
			'lines' => $db->all('SELECT * FROM ordrelinjer WHERE ordre_id = ? ORDER BY id' . $lock, [$id]),
			'batches' => $db->all('SELECT * FROM batch_kob WHERE linje_id IN (SELECT id FROM ordrelinjer WHERE ordre_id = ?) ORDER BY id' . $lock, [$id]),
		];
		if ($state['header'] !== null) {
			$state['header'] = array_intersect_key($state['header'], array_flip(['id','art','status','ordredate','fakturanr','levdate']));
		}
		$state['lines'] = array_map(static fn(array $row): array => array_intersect_key($row, array_flip(['id','ordre_id','antal','leveres','pris'])), $state['lines']);
		$state['batches'] = array_map(static fn(array $row): array => array_intersect_key($row, array_flip(['id','linje_id','antal'])), $state['batches']);
		return $state;
	}

	/** Check both the order's Unix seconds and the journal-style microtime representation. */
	private function checkLock(SaldiTenantContext $ctx, array $header): void
	{
		$owner = trim((string)ifset($header, 'hvem', ''));
		$parts = preg_split('/\s+/', trim((string)ifset($header, 'tidspkt', '')));
		$seconds = (int)end($parts);
		if ($owner !== '' && $owner !== $ctx->username && time() - $seconds < 3600) {
			throw new SaldiRuntimeError('CONFLICT', 'Purchase order is in use', ['reason' => 'journal_in_use', 'owner' => $owner]);
		}
	}

	/** @return array{payload:array,state:array} Rechecked invoice changes and the original rows. */
	private function prepared(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		$this->keys($input, ['order_id', 'invoice_number', 'invoice_date', 'lines']);
		$id = ifset($input, 'order_id');
		if (!is_int($id) || $id <= 0) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid order id');
		}
		$number = $this->string(ifset($input, 'invoice_number'), 1, 30, 'invoice_number');
		$date = $this->date(ifset($input, 'invoice_date'), 'invoice_date');
		$source = ifset($input, 'lines');
		if (!is_array($source) || !array_is_list($source) || count($source) < 1 || count($source) > 100) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected 1 to 100 invoice lines');
		}
		$state = $this->state($db, $id);
		$header = $state['header'];
		if ($header === null) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Purchase order not found');
		}
		if ($header['art'] !== 'KO' || !in_array((int)$header['status'], [1, 2], true)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Purchase order must be open');
		}
		$this->checkLock($ctx, $db->one('SELECT hvem, tidspkt FROM ordrer WHERE id = ?' . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$id]) ?? []);
		$ra = $db->one("SELECT * FROM grupper WHERE art = 'RA' AND kodenr = ? AND box5 = 'on' AND COALESCE(box10, '') <> 'on'", [$ctx->fiscalYear]);
		if (!$ra) {
			throw new SaldiRuntimeError('CLOSED_PERIOD', 'Active fiscal year unavailable');
		}
		$year = SaldiToolRuntime::year($ra);
		if ($date < $year['start'] || $date > $year['end']) {
			throw new SaldiRuntimeError('CLOSED_PERIOD', 'Invoice date outside fiscal year');
		}
		if ($header['ordredate'] === null || $date < $header['ordredate']) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invoice date precedes order date', ['fields' => ['invoice_date']]);
		}
		$byId = array_column($state['lines'], null, 'id');
		$seen = [];
		$lines = [];
		foreach ($source as $line) {
			if (!is_array($line)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid invoice line');
			}
			$this->keys($line, ['line_id', 'receive_quantity', 'unit_price']);
			$lineId = ifset($line, 'line_id');
			if (!is_int($lineId) || !isset($byId[$lineId]) || isset($seen[$lineId])) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Line must belong to this order and occur once');
			}
			$seen[$lineId] = true;
			$n = ['line_id' => $lineId];
			$original = $byId[$lineId];
			if (array_key_exists('receive_quantity', $line)) {
				$n['receive_quantity'] = $this->decimal($line['receive_quantity'], 'receive_quantity');
				$remaining = $db->value('SELECT CASE WHEN CAST(? AS DECIMAL(30,3)) <= COALESCE(antal, 0) - (SELECT COALESCE(SUM(antal), 0) FROM batch_kob WHERE linje_id = ?) THEN 1 ELSE 0 END FROM ordrelinjer WHERE id = ?', [$n['receive_quantity'], $lineId, $lineId]);
				if ((int)$remaining !== 1) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Receive quantity exceeds remaining quantity', ['fields' => ['receive_quantity']]);
				}
			}
			if (array_key_exists('unit_price', $line)) {
				$n['unit_price'] = $this->decimal($line['unit_price'], 'unit_price');
				$changed = (int)$db->value('SELECT CASE WHEN CAST(? AS DECIMAL(30,3)) = CAST(? AS DECIMAL(30,3)) THEN 0 ELSE 1 END', [$n['unit_price'], $original['pris']]);
				if ($changed && array_filter($state['batches'], static fn(array $b): bool => (int)$b['linje_id'] === $lineId)) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Received line price cannot change', ['fields' => ['unit_price']]);
				}
			}
			$lines[] = $n;
		}
		return ['payload' => ['order_id' => $id, 'invoice_number' => $number, 'invoice_date' => $date, 'lines' => $lines], 'state' => $state];
	}

	/** @return array{payload:array,title:string,subject_type:?string,subject_id:?string,proposed_rev:?string,dedupe_key:?string,visibility:string,assignee_permission:?string,owner_user_id:?int} */
	public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
	{
		$p = $this->prepared($ctx, $db, $input);
		$n = $this->normalized($ctx, $p['payload'], $this->text($ctx, $db, 6101) . ': ' . $p['payload']['invoice_number'], null, 'permission', 'purchases.draft');
		$n['subject_type'] = 'order';
		$n['subject_id'] = (string)$p['payload']['order_id'];
		$n['proposed_rev'] = $this->hash($p['state']);
		return $n;
	}

	public function currentRevision(SaldiTenantContext $ctx, SaldiDb $db, array $item): ?string
	{
		return $this->hash($this->state($db, (int)$item['payload']['order_id']));
	}

	/** @return array{order_id:int,before:array{header:array,lines:list<array>},after_sha256:string,screen:string} */
	public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		return $db->transaction(function () use ($ctx, $db, $item): array {
			$this->freezeReferences($db, ['ordrer', 'ordrelinjer', 'batch_kob']);
			$p = $this->prepared($ctx, $db, $item['payload']);
			if (isset($item['proposed_rev']) && !hash_equals($item['proposed_rev'], $this->hash($p['state']))) {
				throw new SaldiRuntimeError('STALE_CONTEXT', 'Purchase order changed');
			}
			$id = $p['payload']['order_id'];
			$before = ['header' => array_intersect_key($p['state']['header'], array_flip(['fakturanr', 'levdate'])), 'lines' => []];
			$byId = array_column($p['state']['lines'], null, 'id');
			$db->exec('UPDATE ordrer SET fakturanr = ?, levdate = ? WHERE id = ?', [$p['payload']['invoice_number'], $p['payload']['invoice_date'], $id]);
			foreach ($p['payload']['lines'] as $line) {
				$before['lines'][] = array_intersect_key($byId[$line['line_id']], array_flip(['id', 'leveres', 'pris']));
				$values = [];
				$params = [];
				foreach (['receive_quantity' => 'leveres', 'unit_price' => 'pris'] as $field => $column) {
					if (isset($line[$field])) {
						$values[] = $column . ' = ?';
						$params[] = $line[$field];
					}
				}
				if ($values) {
					$db->exec('UPDATE ordrelinjer SET ' . implode(', ', $values) . ' WHERE id = ? AND ordre_id = ?', array_merge($params, [$line['line_id'], $id]));
				}
			}
			return ['order_id' => $id, 'before' => $before, 'after_sha256' => $this->hash($this->state($db, $id)), 'screen' => SaldiScreens::link('purchase_order', $id)];
		});
	}

	public function canUndo(SaldiTenantContext $ctx, SaldiDb $db, array $item): bool
	{
		$r = ifset($item, 'result');
		if (ifset($item, 'status') !== 'applied' || !is_array($r) || !isset($r['order_id'], $r['before'], $r['after_sha256'])) {
			return false;
		}
		$state = $this->state($db, (int)$r['order_id']);
		return $state['header'] !== null && hash_equals($r['after_sha256'], $this->hash($state));
	}

	/** @return array{order_id:int,restored:bool} Restore nullable values exactly, after a locked digest check. */
	public function undo(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		return $db->transaction(function () use ($ctx, $db, $item): array {
			$this->freezeReferences($db, ['ordrer', 'ordrelinjer', 'batch_kob']);
			if (!$this->canUndo($ctx, $db, $item)) {
				throw new SaldiRuntimeError('CONFLICT', 'Purchase order changed', ['reason' => 'undo_changed']);
			}
			$r = $item['result'];
			$header = $db->one('SELECT * FROM ordrer WHERE id = ? FOR UPDATE', [$r['order_id']]);
			$this->checkLock($ctx, $header);
			$db->exec('UPDATE ordrer SET fakturanr = ?, levdate = ? WHERE id = ?', [$r['before']['header']['fakturanr'], $r['before']['header']['levdate'], $r['order_id']]);
			foreach ($r['before']['lines'] as $line) {
				$db->exec('UPDATE ordrelinjer SET leveres = ?, pris = ? WHERE id = ? AND ordre_id = ?', [$line['leveres'], $line['pris'], $line['id'], $r['order_id']]);
			}
			return ['order_id' => (int)$r['order_id'], 'restored' => true];
		});
	}

	/** @return array{columns:list<string>,rows:list<array>,fields:list<array>} Prices before and after; stale cards remain readable. */
	protected function change(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$rows = [];
		$before = array_column($item['result']['before']['lines'] ?? [], null, 'id');
		foreach ($item['payload']['lines'] as $line) {
			$stored = $db->one('SELECT varenr, beskrivelse, leveres, pris FROM ordrelinjer WHERE id = ? AND ordre_id = ?', [$line['line_id'], $item['payload']['order_id']]) ?? [];
			$original = $before[$line['line_id']] ?? $stored;
			$price = (string)(ifset($original, 'pris') ?? '0');
			$rows[] = [(string)ifset($stored, 'varenr', (string)$line['line_id']), SaldiToolRuntime::quantityDa($line['receive_quantity'] ?? (string)(ifset($original, 'leveres') ?? '0')), $this->money($price), $this->money($line['unit_price'] ?? $price)];
		}
		$currency = SaldiToolRuntime::currency($ctx, $db->value('SELECT valuta FROM ordrer WHERE id = ?', [$item['payload']['order_id']]));
		return ['columns' => [$this->text($ctx, $db, 6220, 'Linje'), $this->text($ctx, $db, 6221, 'Antal at modtage'), $this->text($ctx, $db, 6222, 'Pris før'), $this->text($ctx, $db, 6223, 'Pris nu')], 'rows' => $rows, 'fields' => [['label' => $this->text($ctx, $db, 6224, 'Fakturanummer'), 'value' => $item['payload']['invoice_number']], ['label' => $this->text($ctx, $db, 6225, 'Fakturadato'), 'value' => $item['payload']['invoice_date']], ['label' => $this->text($ctx, $db, 776, 'Valuta'), 'value' => $currency]]];
	}
}
