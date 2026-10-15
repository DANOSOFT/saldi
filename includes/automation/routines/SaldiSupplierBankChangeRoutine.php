<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/routines/SaldiSupplierBankChangeRoutine.php --- ai/mcp-assistant --- 2026.10.02
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
// Hash-only supplier bank baselines and accumulated, number-free open findings.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Implement atomic baselines and stable merged finding payloads.

declare(strict_types=1);

require_once __DIR__ . '/../SaldiRoutine.php';
require_once __DIR__ . '/../../tools/SaldiToolRuntime.php';

final class SaldiSupplierBankChangeRoutine extends SaldiBaseRoutine
{
	public function name(): string
	{
		return 'supplier_bank_change';
	}
	public function permission(): string
	{
		return 'creditors.read';
	}
	public function titleTextId(): int
	{
		return 5941;
	}
	public function schedule(): string
	{
		return 'hourly';
	}
	/** @return array{routine:string,scheduled_date:string,count:int,total?:array{amount:string,currency:string,converted:bool},rows:list<array>}|null */
	public function run(SaldiTenantContext $ctx, SaldiDb $db, string $scheduledDate): ?array
	{
		$this->cutoff($scheduledDate, 0);
		return $db->transaction(function () use ($db, $scheduledDate): ?array {
			$db->lock('saldi_routine:' . $this->name());
			$old = $db->value("SELECT payload FROM inbox_items WHERE open_key = ? AND status = 'open'", ['routine.finding:' . $this->name()]);
			$payload = $old === null ? null : json_decode($old, true, 512, JSON_THROW_ON_ERROR);
			$pending = $payload === null ? [] : json_decode((string)($db->value('SELECT state_value FROM automation_state WHERE state_key = ?', ['supplier_bank_finding']) ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
			foreach ($payload['rows'] ?? [] as $row) {
				$pending[$row['screen']] = $row;
			}
			$changed = false;
			foreach ($db->all("SELECT id, kontonr, firmanavn, bank_navn, bank_reg, bank_konto, bank_fi, swift, iban FROM adresser WHERE art = 'K' AND COALESCE(lukket, '') <> 'on' ORDER BY id") as $supplier) {
				$key = 'supplier_bank:' . $supplier['id'];
				$hash = hash('sha256', implode('|', array_map(static fn(string $field): string => (string)($supplier[$field] ?? ''), ['bank_navn', 'bank_reg', 'bank_konto', 'bank_fi', 'swift', 'iban'])));
				$previous = $db->value('SELECT state_value FROM automation_state WHERE state_key = ?', [$key]);
				if ($previous === $hash) {
					continue;
				}
				self::state($db, $key, $hash);
				if ($previous !== null) {
					$screen = SaldiScreens::link('creditor', (int)$supplier['id']);
					$pending[$screen] = ['label' => SaldiToolRuntime::text($supplier['firmanavn'] . ' (' . $supplier['kontonr'] . '): bankoplysninger ændret'), 'screen' => $screen];
					$changed = true;
				}
			}
			if (!$changed) {
				return $payload;
			}
			self::state($db, 'supplier_bank_finding', saldi_runtime_json($pending));
			return ['routine' => $this->name(), 'scheduled_date' => $scheduledDate, 'count' => count($pending), 'rows' => array_slice(array_values($pending), 0, 20), 'attention' => 'interrupt'];
		});
	}
	/** Persist only hashes or number-free finding rows under the routine transaction lock. */
	private static function state(SaldiDb $db, string $key, string $value): void
	{
		$now = gmdate('Y-m-d H:i:s');
		if ($db->value('SELECT 1 FROM automation_state WHERE state_key = ?', [$key]) === null) {
			$db->exec('INSERT INTO automation_state (state_key, state_value, updated_at) VALUES (?, ?, ?)', [$key, $value, $now]);
		} else {
			$db->exec('UPDATE automation_state SET state_value = ?, updated_at = ? WHERE state_key = ?', [$value, $now, $key]);
		}
	}
}
