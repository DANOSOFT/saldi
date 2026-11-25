<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//--- includes/salesPostingVat.php ---ver 5.1.1 ----2026-11-25 ---
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
// -----------------------------------------------------------
// 20261125 CL/MMR MB-80 Created: the order-line grouping, the amount rounding and the VAT allocation that bogfor_nu() posts and forkontrolPosteringsbalance() predicts, so the two always agree.
//                 Allocation ported from Lui's #657 (62bdc0d1).

require_once __DIR__ . '/std_func.php';

if (!function_exists('salesPostingAmount')) {
	/**
	 * Converts an amount to the base currency and rounds it the way bogfor_nu() posts it.
	 * A foreign-currency amount is rounded once to 2 decimals (MB-80: 0.01 EUR at 745 is 0.07, where
	 * 3 and then 2 decimals gave 0.08). A base-currency amount is rounded to 3 and then 2 decimals, as before.
	 *
	 * @param float|string $amount Amount in the invoice currency (signed).
	 * @param float|int|string|null $currencyRate ordrer.valutakurs (DKK per 100); null or 0 posts the amount unconverted.
	 * @return float Signed posted amount, 2 decimals.
	 */
	function salesPostingAmount($amount, $currencyRate)
	{
		if ($currencyRate && $currencyRate != 100) {
			return afrund($amount * $currencyRate / 100, 2);
		}
		if ($currencyRate) {
			$amount = $amount * $currencyRate / 100;
		}
		return afrund(afrund($amount, 3), 2);
	}
}

if (!function_exists('salesPostingGroupLines')) {
	/**
	 * Groups order lines into the revenue postings bogfor_nu() makes: one group per revenue account
	 * and VAT account, in the order the lines come in. The amounts are summed exactly as before MB-80
	 * (net to 3 decimals, VAT per line to 3 decimals). Until MB-80 the group was found by revenue account
	 * only, so a line on the same account with another VAT account was dropped.
	 *
	 * @param array<int, array<string, mixed>> $lines ordrelinjer rows.
	 * @return array<int, array{
	 *   bogf_konto: string,   Revenue account.
	 *   vat_account: int,     VAT account (0 when the line has none).
	 *   pris: float,          Net amount in the invoice currency.
	 *   linjemoms: float,     VAT in the invoice currency, sum of the lines' VAT rounded to 3 decimals.
	 *   vatLines: int,        Number of lines with VAT.
	 * }> 1-based, like the arrays bogfor_nu() posts from.
	 */
	function salesPostingGroupLines(array $lines)
	{
		$groups = array();
		$groupNo = array();
		foreach ($lines as $r) {
			if ($r['rabatart'] == 'amount') {
				$linjesum = $r['pris'] * $r['antal'] - ($r['rabat'] * $r['antal']);
			} else {
				$linjesum = $r['pris'] * $r['antal'] - ($r['pris'] * $r['antal'] * $r['rabat'] / 100);
			}
			$net = ($r['procent'] || $r['procent'] != '') ? $linjesum * $r['procent'] / 100 : $linjesum;
			$hasVat = ($r['momssats'] && !$r['momsfri']);
			$vat = $hasVat ? afrund($linjesum / 100 * $r['momssats'], 3) : 0;
			$key = $r['bogf_konto'] . '|' . ($r['vat_account'] * 1);
			if (!isset($groupNo[$key])) {
				$y = count($groups) + 1;
				$groupNo[$key] = $y;
				$groups[$y] = array(
					'bogf_konto' => $r['bogf_konto'],
					'vat_account' => $r['vat_account'] * 1,
					'pris' => ($r['rabatart'] == 'amount') ? $net : afrund($net, 3),
					'linjemoms' => $vat,
					'vatLines' => $hasVat ? 1 : 0,
				);
			} else {
				$y = $groupNo[$key];
				$groups[$y]['pris'] = afrund($groups[$y]['pris'] + $net, 3);
				$groups[$y]['linjemoms'] += $vat;
				$groups[$y]['vatLines'] += $hasVat ? 1 : 0;
			}
		}
		return $groups;
	}
}

if (!function_exists('salesPostingLoadGroups')) {
	/**
	 * Reads the order lines bogfor_nu() posts and groups them per phase and project.
	 * Phase 1 is the ordinary lines (posnr >= 0 with a revenue account, converted to the base currency),
	 * phase 2 the lines with a negative posnr (posted unconverted).
	 *
	 * @param string $lineCondition SQL condition on ordrelinjer.ordre_id, built by the caller from integer ids.
	 * @param array<int, string> $projects Project codes, 1-based as bogfor_nu() builds them.
	 * @param int $projectCount Number of projects (at least 1).
	 * @return array{
	 *   groups: array<int, array<int, array<int, array<string, mixed>>>>,  [phase][project] => salesPostingGroupLines().
	 *   lines: int,  Number of lines read; a foreign-currency invoice may differ by 2 øre per line.
	 * }
	 */
	function salesPostingLoadGroups($lineCondition, array $projects, $projectCount)
	{
		$result = array('groups' => array(1 => array(), 2 => array()), 'lines' => 0);
		for ($t = 1; $t <= 2; $t++) {
			for ($p = 1; $p <= $projectCount; $p++) {
				$project = db_escape_string(isset($projects[$p]) ? $projects[$p] : '');
				$qtxt = "select * from ordrelinjer where $lineCondition and coalesce(projekt,'')='$project' and ";
				$qtxt .= ($t == 1) ? "posnr>='0' and bogf_konto > 0" : "posnr<'0'";
				$qtxt .= " order by bogf_konto,vat_account";
				$lines = array();
				$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$lines[] = $r;
				}
				$result['lines'] += count($lines);
				$result['groups'][$t][$p] = salesPostingGroupLines($lines);
			}
		}
		return $result;
	}
}

if (!function_exists('salesPostingAllocateCents')) {
	/**
	 * Rounds amounts to whole øre while keeping their total. Leftover øre go to the largest
	 * rounding remainder; input order breaks ties.
	 *
	 * @param array<string, float> $amounts Unrounded amounts.
	 * @param float $target Total the rounded amounts must add up to.
	 * @return array<string, float> Rounded amounts, summing exactly to $target.
	 */
	function salesPostingAllocateCents(array $amounts, $target)
	{
		$cents = array();
		foreach ($amounts as $key => $amount) {
			$cents[$key] = (int)round($amount * 100);
		}
		$difference = (int)round($target * 100) - array_sum($cents);
		if (!$cents) {
			return array();
		}
		while ($difference) {
			$direction = $difference > 0 ? 1 : -1;
			$best = null;
			$bestRemainder = null;
			foreach ($amounts as $key => $amount) {
				$remainder = ($amount * 100 - $cents[$key]) * $direction;
				if ($best === null || $remainder > $bestRemainder + 0.0000001) {
					$best = $key;
					$bestRemainder = $remainder;
				}
			}
			$cents[$best] += $direction;
			$difference -= $direction;
		}
		$rounded = array();
		foreach ($cents as $key => $value) {
			$rounded[$key] = $value / 100;
		}
		return $rounded;
	}
}

if (!function_exists('salesPostingVatAllocations')) {
	/**
	 * Splits a foreign-currency invoice's VAT (ordrer.moms) over its phase-1 posting groups in the base
	 * currency. The VAT is first rounded to øre in the invoice currency per VAT account, then converted,
	 * so an invoice with 250.00 EUR VAT at 745 posts exactly 1862.50 and not 249.998 x 7.45 = 1862.49.
	 * The total equals the converted invoice VAT; each VAT account gets its own share before it is
	 * split over the revenue groups that use it.
	 *
	 * @param array<int, array<int, array<string, mixed>>> $groups Phase-1 groups per project (salesPostingLoadGroups()).
	 * @param float|string $invoiceVat ordrer.moms in the invoice currency.
	 * @param float|string $currencyRate ordrer.valutakurs.
	 * @return array<int, array<int, float>>|null Base-currency VAT per [project][group], or null when the lines' VAT
	 *                                            is more than rounding away from the invoice VAT (then bogfor_nu()
	 *                                            posts the lines' VAT as before and the control check decides).
	 */
	function salesPostingVatAllocations(array $groups, $invoiceVat, $currencyRate)
	{
		$raw = $taxRaw = $taxMembers = $result = array();
		$vatLines = 0;
		foreach ($groups as $p => $projectGroups) {
			foreach ($projectGroups as $y => $group) {
				$result[$p][$y] = 0;
				if (!$group['linjemoms']) {
					continue;
				}
				$key = $p . ':' . $y;
				$taxKey = (string)$group['vat_account'];
				$raw[$key] = $group['linjemoms'];
				$taxRaw[$taxKey] = (isset($taxRaw[$taxKey]) ? $taxRaw[$taxKey] : 0) + $group['linjemoms'];
				$taxMembers[$taxKey][$key] = array($p, $y);
				$vatLines += $group['vatLines'];
			}
		}
		$invoiceVat = afrund($invoiceVat, 2);
		if (!$raw) {
			return $invoiceVat ? null : $result;
		}
		if (abs(array_sum($taxRaw) - $invoiceVat) > max(0.01, ($vatLines + 1) * 0.0051) + 0.0000001) {
			return null;
		}
		$sourceTax = salesPostingAllocateCents($taxRaw, $invoiceVat);
		$convertedTax = array();
		foreach ($sourceTax as $taxKey => $amount) {
			$convertedTax[$taxKey] = $amount * $currencyRate / 100;
		}
		$baseTax = salesPostingAllocateCents($convertedTax, salesPostingAmount($invoiceVat, $currencyRate));
		foreach ($taxMembers as $taxKey => $members) {
			$memberBase = array();
			foreach ($members as $key => $position) {
				$memberBase[$key] = $raw[$key] * $currencyRate / 100;
			}
			foreach (salesPostingAllocateCents($memberBase, $baseTax[$taxKey]) as $key => $amount) {
				$result[$members[$key][0]][$members[$key][1]] = $amount;
			}
		}
		return $result;
	}
}
