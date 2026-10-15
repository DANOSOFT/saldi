<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/permissions/SaldiRightsParityTest.test.php --- ver 5.0.0 --- 2026.10.08
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
// 20261008 CL/LH Created for the mobile Assist contract.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../../../includes/std_func.php';
require_once __DIR__ . '/../../../includes/runtime/bootstrap.php';
require_once __DIR__ . '/../../../includes/automation/SaldiKindRegistry.php';

final class SaldiRightsParityTest extends TestCase
{
	private function db(): SaldiDb
	{
		return (new ReflectionClass(SaldiDb::class))->newInstanceWithoutConstructor();
	}
	public function testEveryModuleActionAndDigitUsesOneTable(): void
	{
		$rightsPositions = (new ReflectionClass(Rights::class))->getConstant('POSITIONS');
		self::assertSame(array_diff_key(SaldiRightsTable::MODULES, ['assistant' => true]), $rightsPositions);
		$matrix = [str_repeat('0', 16), str_repeat('1', 16), str_repeat('2', 16)];
		foreach (range(0, 15) as $position) {
			foreach (range(0, 9) as $digit) {
				$rights = str_repeat('0', 16);
				$rights[$position] = (string)$digit;
				$matrix[] = $rights;
			}
		}
		foreach ($matrix as $rights) {
			foreach (SaldiRightsTable::MODULES as $module => $positions) {
				foreach (['read', 'write'] as $action) {
					$want = false;
					foreach ($positions as $position) {
						$want = $want || ($action === 'read' ? in_array($rights[$position], ['1', '2'], true) : $rights[$position] === '1');
					}
					self::assertSame($want, Rights::allows($rights, $module, $action), $module . '/' . $action . '/' . $rights);
				}
			}
			$permissions = SaldiPermissionService::userPermissions($this->db(), $rights);
			foreach (SaldiRightsTable::PERMISSIONS as $permission => [$positions, $action]) {
				self::assertSame(SaldiRightsTable::digitAllows($rights, $positions, $action, false), in_array($permission, $permissions, true), $permission . '/' . $rights);
			}
			foreach ($this->kindItems() as [$kind, $item]) {
				$permission = $kind->decidePermission($item);
				if (in_array($permission, $permissions, true)) {
					self::assertTrue(Rights::allows($rights, SaldiKindRegistry::module($kind->name()), 'read'));
					self::assertTrue(Rights::allows($rights, SaldiKindRegistry::module($kind->name()), str_ends_with($permission, '.read') ? 'read' : 'write'));
				}
			}
		}
	}
	/** Representative payloads include both parties, private/company memory, and all builtin routines. @return iterable<array> */
	private function kindItems(): iterable
	{
		foreach (SaldiKindRegistry::default()->names() as $name) {
			$kind = SaldiKindRegistry::default()->get($name);
			$payloads = [['scope' => 'user', 'target_scope' => 'user', 'ledger' => 'customer', 'party_type' => 'debtor', 'routine' => 'overdue_debtors']];
			if (in_array($name, ['party.create', 'settlement.draft', 'memory.fact', 'memory.retract'], true)) {
				$payloads[] = ['scope' => 'company', 'target_scope' => 'company', 'ledger' => 'supplier', 'party_type' => 'creditor'];
			}
			if ($name === 'routine.finding') {
				$payloads = array_map(static fn(string $routine): array => ['routine' => $routine], self::routines());
			}
			foreach ($payloads as $payload) {
				yield [$kind, ['payload' => $payload]];
			}
		}
	}
	private static function routines(): array
	{
		return ['overdue_debtors', 'stale_open_journals', 'low_stock', 'vat_missing_documents', 'supplier_bank_change', 'expiring_batches', 'till_difference', 'posting_rule_candidates', 'reorder_suggestions', 'master_data_hygiene'];
	}
	public function testAllKindAndRoutinePermissionsAreRegistered(): void
	{
		$known = array_merge(array_keys(SaldiRightsTable::PERMISSIONS), SaldiRightsTable::BASELINE);
		foreach ($this->kindItems() as [$kind, $item]) {
			self::assertContains($kind->proposePermission(), $known);
			self::assertContains($kind->decidePermission($item), $known);
		}
		foreach (self::routines() as $routine) {
			self::assertContains(SaldiPermissionService::routine($routine)['permission'], $known);
		}
	}
	public function testEmptyIsUnrestrictedIsTheNamedLegacyDivergence(): void
	{
		foreach (['', '  '] as $rights) {
			self::assertSame([], SaldiPermissionService::userPermissions($this->db(), $rights));
			foreach (SaldiRightsTable::MODULES as $module => $positions) {
				foreach (['read', 'write'] as $action) {
					self::assertSame($module !== 'assistant', Rights::allows($rights, $module, $action));
					self::assertFalse(SaldiRightsTable::digitAllows($rights, $positions, $action, false));
				}
			}
		}
		self::assertTrue(Rights::allows('', 'assistant', 'read', null, true));
	}
}
