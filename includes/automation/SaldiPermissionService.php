<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiPermissionService.php --- ai/mcp-assistant --- 2026.10.08
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
// Action permissions over module digits and trusted routine lookup.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Add companion shared foundation contracts.
// 20261004 CDX/LH Add companion part 2 foundation contracts.
// 20261008 CL/LH Add the mobile Assist identity and delegation contract.

declare(strict_types=1);

require_once __DIR__ . '/../permissions/SaldiRightsTable.php';

final class SaldiPermissionService
{
	private static ?Closure $routineLookup = null;
	/** Worker injection hook: fn(string $name): ?SaldiRoutine (or permission string).
	 * Unknown routines fail closed. Built-in rules work before registry injection.
	 */
	public static function setRoutineLookup(?callable $lookup): void
	{
		self::$routineLookup = $lookup === null ? null : Closure::fromCallable($lookup);
	}
	/** @return array{permission:string,title_text_id:int} */
	public static function routine(string $name): array
	{
		$builtins = ['overdue_debtors' => ['debtors.read', 5720], 'stale_open_journals' => ['journal.read', 5721], 'low_stock' => ['products.read', 5722], 'vat_missing_documents' => ['journal.read', 5940], 'supplier_bank_change' => ['creditors.read', 5941], 'expiring_batches' => ['products.read', 5942], 'till_difference' => ['ledger.read', 5943], 'posting_rule_candidates' => ['journal.draft', 5944], 'reorder_suggestions' => ['products.read', 6170], 'master_data_hygiene' => ['company.read', 6171]];
		if (self::$routineLookup !== null) {
			$r = (self::$routineLookup)($name);
			if (is_object($r) && method_exists($r, 'permission')) {
				return ['permission' => $r->permission(), 'title_text_id' => $r->titleTextId()];
			}
			if (is_string($r) && $r !== '') {
				return ['permission' => $r, 'title_text_id' => $builtins[$name][1] ?? 5705];
			}
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Unknown routine');
		}
		if (!isset($builtins[$name])) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Unknown routine');
		}
		return ['permission' => $builtins[$name][0], 'title_text_id' => $builtins[$name][1]];
	}
	/** @return list<string> Sorted action permissions. */
	public static function userPermissions(SaldiDb $tenant, string $rights): array
	{
		$baseline = SaldiRightsTable::digitAllows($rights, SaldiRightsTable::MODULES['assistant'], 'read', false);
		$out = $baseline ? SaldiRightsTable::BASELINE : [];
		foreach (SaldiRightsTable::PERMISSIONS as $name => [$positions, $action]) {
			if (SaldiRightsTable::digitAllows($rights, $positions, $action, false)) {
				$out[] = $name;
			}
		}
		sort($out);
		return $out;
	}
	/** Live user permissions intersected with principal capabilities. @return list<string> */
	public static function effective(SaldiTenantContext $ctx, SaldiDb $tenant): array
	{
		if ($ctx->isHuman()) {
			return self::userPermissions($tenant, $ctx->rights);
		}
		if ($ctx->isAgent()) {
			if (!in_array('read', $ctx->scopes, true)) {
				return [];
			}
			return array_values(array_filter(self::userPermissions($tenant, $ctx->rights), static fn(string $p): bool => str_ends_with($p, '.read') || (in_array('draft', $ctx->scopes, true) && in_array($p, ['journal.draft', 'memory.propose', 'followup.draft', 'debtors.draft', 'creditors.draft', 'purchases.draft', 'sales.draft', 'support.draft'], true))));
		}
		if ($ctx->isRule()) {
			return ['company.read', self::routine(substr($ctx->principal, 5))['permission']];
		}
		return $ctx->principal === 'system:worker' ? ['company.read'] : [];
	}
	/** Whether the principal has this action permission. */
	public static function allows(SaldiTenantContext $ctx, SaldiDb $tenant, string $permission): bool
	{
		return in_array($permission, self::effective($ctx, $tenant), true);
	}
	/** Throw PERMISSION_DENIED when this action is not allowed. */
	public static function require(SaldiTenantContext $ctx, SaldiDb $tenant, string $permission): void
	{
		if (!self::allows($ctx, $tenant, $permission)) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Permission denied');
		}
	}
	/** Apply the tenant revenue restriction to humans and their agents. */
	public static function revenueHidden(SaldiTenantContext $ctx, SaldiDb $tenant): bool
	{
		return ($ctx->isHuman() || $ctx->isAgent()) && ($ctx->rights[1] ?? '') !== '1' && SaldiSettings::get($tenant, 'finans', 'hideRevenue') === 'on';
	}
}
