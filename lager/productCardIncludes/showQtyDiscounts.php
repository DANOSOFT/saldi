<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/productCardIncludes/showQtyDiscounts.php --- ver 5.1.0 --- 2026-11-09 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20240211 PHR Remover decimal limit from $m_rabat_array and $m_antal_array
// 20261019 CDX/LH MB-78: fix PHP warnings
// 20261027 CL/LH MB-78: Pad a missing decimal part with '' so strlen() gets a string, not null (CodeRabbit).
// 20261109 CDX/NTR Render quantity discounts with explicit parameters and local calculations without changing caller values.
require_once __DIR__ . '/../../includes/std_func.php';

/**
 * Render the item card's quantity discounts without changing the supplied values.
 *
 * @param list<int|float|string> $discountAmounts Discount amounts or percentages before display VAT.
 * @param list<int|float|string> $quantities Quantity thresholds in the same order as the discounts.
 * @param string $discountType 'amount' or 'percent'.
 * @param float $vatPercent VAT percentage to add when displaying amount discounts.
 * @param bool $readOnly Whether the discount group supplies the values.
 * @param bool $hasPercentageOffer Whether a percentage offer replaces quantity discounts.
 * @param int $languageId Language used for the field labels.
 * @return void
 */
function renderQtyDiscounts(
	array $discountAmounts,
	array $quantities,
	string $discountType,
	float $vatPercent,
	bool $readOnly,
	bool $hasPercentageOffer,
	int $languageId
): void {
	print "<tr><td><b>".findtekst(2041, $languageId)."</b></td>";
	if ($hasPercentageOffer) {
		print "<tr><td>".findtekst(2043, $languageId)."</td></tr>";
		return;
	}

	print "<td align=\"right\"><SELECT class=\"inputbox\" NAME=m_type style=\"width: 4em\">";
	if ($discountType == 'amount') {
		print "<option value=\"amount\">kr</option>";
		print "<option value=\"percent\">%</option>";
	} else {
		print "<option value=\"percent\">%</option>";
		print "<option value=\"amount\">kr</option>";
	}
	print "</SELECT> pr</td>";
	print "<td> ".findtekst(2042, $languageId)."</td></tr>";
	for ($index = 0; $index < count($quantities); $index++) {
		$quantity = $quantities[$index];
		if (!$quantity) {
			continue;
		}
		$discountAmount = $discountAmounts[$index] ?? 0;
		if ($vatPercent && $discountType != 'percent') {
			$discountAmount = (float) $discountAmount * (100 + $vatPercent) / 100;
		}
		$discountDecimals = strlen(explode('.', (string) (float) $discountAmount)[1] ?? '');
		$quantityDecimals = strlen(explode('.', (string) (float) $quantity)[1] ?? '');
		$inputType = $readOnly ? 'readonly="readonly"' : 'type="text"';
		if ($discountAmount > 0 || $quantity > 0) {
			print "<tr><td>".findtekst(2044, $languageId)."</td>";
			print "<td><input $inputType size=\"5\" style=\"text-align:right\" name=\"m_rabat_array[$index]\" value=".dkdecimal($discountAmount, $discountDecimals)."></td><td><input $inputType size=\"5\" style=\"text-align:right\" name=\"m_antal_array[$index]\" value=\"".dkdecimal($quantity, $quantityDecimals)."\"></td></tr>";
		}
	}
	if (!$readOnly) {
		$discountAmount = $discountAmounts[$index] ?? '0';
		$quantity = $quantities[$index] ?? '0';
		$discountDecimals = strlen(explode('.', (string) (float) $discountAmount)[1] ?? '');
		$quantityDecimals = strlen(explode('.', (string) (float) $quantity)[1] ?? '');
		print "<tr><td>".findtekst(2044, $languageId)."</td>";
		print "<td><input class='inputbox' type='text' size='5' style='text-align:right' name='m_rabat_array[$index]'";
		print "value='".dkdecimal($discountAmount, $discountDecimals)."'></td>";
		print "<td><input class='inputbox' type='text' size='5' style=text-align:right name='m_antal_array[$index]'";
		print "value='".dkdecimal($quantity, $quantityDecimals)."'></td></tr>";
	}
}
