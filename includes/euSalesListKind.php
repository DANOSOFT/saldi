<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/euSalesListKind.php --- ver 5.1.1 --- 2026-11-13 ---
//                           LICENSE
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261113 CL/ASR Created: whether a sale to a customer in another EU country is goods or services.
//                 Finans > Listeangivelse (the EU sales list) and box B of Momsrubrikker both decide it here, so they agree.

require_once __DIR__ . '/vatReportRange.php';

if (!function_exists('eu_sales_account_number')) {
	/**
	 * An account number from a settings box, trimmed; '' when the box is empty or holds only zeros.
	 *
	 * Some ledgers left an unused box as 0 or 00, and there is no account 0.
	 *
	 * @param mixed $account The box value as read from grupper.
	 *
	 * @return string The account number, or '' for none.
	 */
	function eu_sales_account_number($account): string
	{
		$account = trim((string) $account);
		return ltrim($account, '0') === '' ? '' : $account;
	}
}

if (!function_exists('eu_sales_accounts')) {
	/**
	 * The EU accounts of the VAT report settings (Indstillinger > Moms, grupper art='MR') for a fiscal year.
	 *
	 * They are read from the row vat_report_row() picks, as the VAT report does. In the standard setup box3 and
	 * box4 are the box A purchase accounts (1630 and 1710; 2800 and 2700 in the old chart) and box5 and box6 the
	 * box B sales accounts (1050 and 1150; 1220 and 1200).
	 *
	 * @param int $regnaar Fiscal year (grupper art='RA' kodenr) the accounts are wanted for.
	 *
	 * @return array{
	 *   goods: string,            Box B goods sales account (box5), '' when not set.
	 *   services: string,         Box B services sales account (box6), '' when not set.
	 *   legacy_services: string,  Box A services purchase account (box4), '' when not set. The EU sales list
	 *                             used to read this box as its services account, and some ledgers point it at one.
	 * }
	 */
	function eu_sales_accounts(int $regnaar): array
	{
		$rows = array();
		$qtxt = "select box4, box5, box6, fiscal_year from grupper where art = 'MR' order by id";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$rows[] = $r;
		}
		$row = vat_report_row($rows, $regnaar);
		return array(
			'goods' => eu_sales_account_number($row['box5'] ?? ''),
			'services' => eu_sales_account_number($row['box6'] ?? ''),
			'legacy_services' => eu_sales_account_number($row['box4'] ?? ''),
		);
	}
}

if (!function_exists('eu_sales_kind')) {
	/**
	 * Whether an invoice line to a customer in another EU country is a sale of goods or of services.
	 *
	 * The item group's EU sales account (VG box12, the account the line was posted to) decides first: the box B
	 * services account means services and the box B goods account goods, so the list follows the balances the
	 * bookkeeper reads rubric B from. That holds only when the two box B accounts differ. Otherwise the item group's
	 * own Varer/ydelser choice (grupper art='VG' box5) decides when it is set. The box A services account still means
	 * services, as the EU sales list has always read it, unless it is also the box B goods account. Anything else is goods.
	 *
	 * @param mixed $groupType      The item group's Varer/ydelser choice: 'varer', 'ydelser', or anything else for not set.
	 * @param mixed $euSalesAccount The item group's EU sales account.
	 * @param array{goods: string, services: string, legacy_services: string} $accounts From eu_sales_accounts().
	 *
	 * @return string 'goods' or 'services'.
	 */
	function eu_sales_kind($groupType, $euSalesAccount, array $accounts): string
	{
		$groupType = trim((string) $groupType);
		$account = eu_sales_account_number($euSalesAccount);
		if ($account !== '' && $accounts['goods'] !== $accounts['services']) {
			if ($account === $accounts['services']) {
				return 'services';
			}
			if ($account === $accounts['goods']) {
				return 'goods';
			}
		}
		if ($groupType === 'ydelser') {
			return 'services';
		}
		if ($groupType === 'varer') {
			return 'goods';
		}
		if ($account !== '' && $account === $accounts['legacy_services'] && $account !== $accounts['goods']) {
			return 'services';
		}
		return 'goods';
	}
}

if (!function_exists('eu_sales_list_kind')) {
	/**
	 * The column of the EU sales list an invoice line to a customer in another EU country counts in.
	 *
	 * A line whose item group has no EU sales account was posted to the ordinary sales account, so it is left out
	 * once the VAT report settings name an EU services account (box6, or box4 as the list read it before). Without
	 * one the line counts, as it always has. Otherwise eu_sales_kind() decides.
	 *
	 * @param mixed $groupType      The item group's Varer/ydelser choice (grupper art='VG' box5).
	 * @param mixed $euSalesAccount The item group's EU sales account (VG box12).
	 * @param array{goods: string, services: string, legacy_services: string} $accounts From eu_sales_accounts().
	 *
	 * @return string 'goods', 'services', or '' when the line is not on the list.
	 */
	function eu_sales_list_kind($groupType, $euSalesAccount, array $accounts): string
	{
		if (eu_sales_account_number($euSalesAccount) === '' && ($accounts['services'] !== '' || $accounts['legacy_services'] !== '')) {
			return '';
		}
		return eu_sales_kind($groupType, $euSalesAccount, $accounts);
	}
}
