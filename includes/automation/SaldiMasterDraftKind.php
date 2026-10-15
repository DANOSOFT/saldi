<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiMasterDraftKind.php --- ai/mcp-assistant --- 2026.10.02
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
// Shared validation, exact decimal arithmetic and guarded order undo for master-data drafts.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/SaldiInboxKind.php';
require_once __DIR__ . '/../std_func.php';

abstract class SaldiMasterDraftKind extends SaldiBaseInboxKind
{
	protected bool $undoCheck = false;

	/** Lock final undo reads on both backends, after the allocation/table locks. */
	protected function undoReadLock(SaldiDb $db): string
	{
		return $this->undoCheck && $db->inTransaction() ? ' FOR UPDATE' : '';
	}
	/** Reject unknown fields, including bank fields, also for direct inbox callers. */
	protected function keys(array $input, array $allowed): void
	{
		$unknown = array_diff(array_keys($input), $allowed);
		if ($unknown) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Unknown fields', ['fields' => array_map(fn($key): string => '$.' . $key, array_values($unknown))]);
		}
	}
	/** Validate and pad a nonnegative decimal without floating-point arithmetic. */
	protected function decimal(mixed $value, string $field, bool $positive = false): string
	{
		if (!is_string($value) || !preg_match('/^[0-9]{1,12}(\.[0-9]{1,3})?$/D', $value) || ($positive && !preg_match('/[1-9]/', $value))) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid decimal ' . $field, ['fields' => ['$.' . $field]]);
		}
		[$whole, $fraction] = array_pad(explode('.', $value), 2, '');
		return (ltrim($whole, '0') ?: '0') . '.' . str_pad($fraction, 3, '0');
	}
	/** SQL numeric arithmetic, rounded once to the stored three decimal places. */
	protected function calculate(SaldiDb $db, string $expression, array $params): string
	{
		return (string)$db->value('SELECT ROUND(' . $expression . ', 3)', $params);
	}
	/** Display money with half-away-from-zero rounding and no floats. */
	protected function money(string $value): string
	{
		$negative = str_starts_with($value, '-');
		[$whole, $fraction] = array_pad(explode('.', ltrim($value, '-')), 2, '');
		$fraction = str_pad($fraction, 3, '0');
		$cents = (int)$whole * 100 + (int)substr($fraction, 0, 2) + ((int)$fraction[2] >= 5 ? 1 : 0);
		return ($negative && $cents ? '-' : '') . intdiv($cents, 100) . '.' . str_pad((string)($cents % 100), 2, '0', STR_PAD_LEFT);
	}
	protected function today(): string
	{
		return (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
	}
	/** Lock order writers before any row locks, including an empty order table. */
	protected function orderLock(SaldiDb $db): void
	{
		$db->lock('saldi_ordrer_number');
		if ($db->isPostgres()) {
			$db->exec('LOCK TABLE ordrer IN EXCLUSIVE MODE');
		}
	}
	/** @return array<string,mixed> Open party in the requested ledger. */
	protected function party(SaldiDb $db, mixed $account, string $art): array
	{
		if (!is_string($account) || !preg_match('/^[0-9]{1,15}$/D', $account)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid party account');
		}
		$row = $db->one("SELECT * FROM adresser WHERE art = ? AND kontonr = ? AND COALESCE(lukket, '') <> 'on'" . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$art, $account]);
		if (!$row) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Party must exist and be open');
		}
		return $row;
	}
	/** @return array<string,mixed> Group settings for the active fiscal year. */
	protected function group(SaldiTenantContext $ctx, SaldiDb $db, string $art, int $number): array
	{
		return $db->one('SELECT * FROM grupper WHERE art = ? AND kodenr = ? AND fiscal_year = ? ORDER BY id LIMIT 1' . ($db->inTransaction() ? ' FOR UPDATE' : ''), [$art, $number, $ctx->fiscalYear]) ?? [];
	}
	/** @return array{0:string,1:string} Currency and rate, with latest valid historic rate. */
	protected function currency(SaldiDb $db, string $currency, string $date): array
	{
		if ($currency === '' || $currency === 'DKK' || $currency === '-') {
			return ['DKK', '100.000'];
		}
		$cast = $db->isPostgres() ? 'INTEGER' : 'SIGNED';
		$rate = $db->value("SELECT v.kurs FROM valuta v JOIN grupper g ON v.gruppe = CAST(g.kodenr AS $cast) WHERE g.art = 'VK' AND g.box1 = ? AND v.valdate <= ? ORDER BY v.valdate DESC, v.id DESC LIMIT 1", [$currency, $date]);
		if ($rate === null || !preg_match('/[1-9]/', (string)$rate)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Currency rate unavailable', ['reason' => 'rate_unavailable']);
		}
		return [$currency, (string)$rate];
	}
	/** @return array{header:?array,lines:list<array>} Every column of the order and its lines. */
	protected function orderRows(SaldiDb $db, int $id): array
	{
		// Availability reads do not upgrade row locks into a table lock. undo() freezes the
		// reference tables first and repeats this digest check before deleting anything.
		$lock = $this->undoReadLock($db);
		return ['header' => $db->one('SELECT * FROM ordrer WHERE id = ?' . $lock, [$id]), 'lines' => $db->all('SELECT * FROM ordrelinjer WHERE ordre_id = ? ORDER BY id' . $lock, [$id])];
	}
	protected function hash(array $rows): string
	{
		return hash('sha256', saldi_runtime_json($rows));
	}
	/** Detect documents, inventory, postings and copied order references before deleting. */
	protected function orderReferenced(SaldiDb $db, int $id): bool
	{
		foreach (['batch_kob', 'batch_salg', 'openpost', 'transaktioner', 'kassekladde'] as $table) {
			$column = $table === 'openpost' ? 'refnr' : 'ordre_id';
			if ($db->hasTable($table) && $db->hasColumn($table, $column) && $db->value('SELECT 1 FROM ' . $table . ' WHERE ' . $column . ' = ? LIMIT 1' . $this->undoReadLock($db), [$id]) !== null) {
				return true;
			}
		}
		if ($db->hasTable('documents') && $db->value("SELECT 1 FROM documents WHERE source_id = ? AND source IN ('kreditor','debitor','purchase_order','sales_order','ordrer','creditorOrder','debitorOrdrer') LIMIT 1" . $this->undoReadLock($db), [$id]) !== null) {
			return true;
		}
		return $db->value('SELECT 1 FROM ordrer WHERE kred_ord_id = ? LIMIT 1' . $this->undoReadLock($db), [$id]) !== null
			|| $db->value('SELECT 1 FROM ordrelinjer WHERE kred_linje_id IN (SELECT id FROM ordrelinjer WHERE ordre_id = ?) LIMIT 1' . $this->undoReadLock($db), [$id]) !== null;
	}
	protected function intactOrder(SaldiDb $db, array $item, int $status, string $art): bool
	{
		if (ifset($item, 'status') !== 'applied' || !is_array(ifset($item, 'result')) || !isset($item['result']['order_id'], $item['result']['rows_sha256'])) {
			return false;
		}
		$id = (int)$item['result']['order_id'];
		$rows = $this->orderRows($db, $id);
		return $rows['header'] !== null && (int)$rows['header']['status'] === $status && $rows['header']['art'] === $art
			&& hash_equals($item['result']['rows_sha256'], $this->hash($rows)) && !$this->orderReferenced($db, $id);
	}
	/** Freeze legacy reference tables so an undo check cannot race a new reference. */
	protected function freezeReferences(SaldiDb $db, array $tables): void
	{
		if ($db->isPostgres()) {
			$existing = array_filter($tables, fn(string $table): bool => $db->hasTable($table));
			$db->exec('LOCK TABLE ' . implode(', ', $existing) . ' IN SHARE ROW EXCLUSIVE MODE');
		}
	}
	/** Reverse the intact order only, under the same allocation lock as apply. @return array{removed_lines:int,removed_order:bool} */
	public function undo(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
	{
		$this->orderLock($db);
		$this->freezeReferences($db, ['ordrelinjer','batch_kob','batch_salg','openpost','transaktioner','kassekladde','documents']);
		$this->undoCheck = true;
		try {
			if (!$this->canUndo($ctx, $db, $item)) {
				throw new SaldiRuntimeError('CONFLICT', 'Order changed or referenced', ['reason' => 'undo_changed']);
			}
			$id = (int)$item['result']['order_id'];
			$lines = $db->exec('DELETE FROM ordrelinjer WHERE ordre_id = ?', [$id]);
			return ['removed_lines' => $lines, 'removed_order' => $db->exec('DELETE FROM ordrer WHERE id = ?', [$id]) === 1];
		} finally {
			$this->undoCheck = false;
		}
	}
}
