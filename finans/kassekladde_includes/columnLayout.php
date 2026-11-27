<?php
// --- finans/kassekladde_includes/columnLayout.php --- 2026-11-27 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261127 CDX/NTR Share journal column gates and optional cells across all row types.

/**
 * Decide columns from the displayed rows, including staged rows after a refused save.
 *
 * @param array<string, bool> $options Attachment, dimension, currency and payment-ID settings.
 * @param array<int, string|null> $debitTypes Displayed debit account types.
 * @param array<int, string|null> $creditTypes Displayed credit account types.
 * @param array<int, mixed> $bankBalances Imported bank balances.
 * @param bool $controlAccount Whether a control account is selected.
 * @return array{
 *   attachments?: bool, department?: bool, employee?: bool, project?: bool, currency?: bool,
 *   due: bool, payment: bool, ledger: bool, bank: bool, difference: bool
 * } Gates shared by the header and every row.
 */
function kkJournalColumns(array $options, array $debitTypes, array $creditTypes, array $bankBalances, bool $controlAccount): array {
	$due = in_array('D', $debitTypes, true) || in_array('K', $creditTypes, true);
	return array_merge($options, array(
		'due' => $due,
		'payment' => $due && $options['payment'],
		'ledger' => $controlAccount,
		'bank' => $controlAccount && count(array_filter($bankBalances, function ($balance) { return abs((float)$balance) > 0; })) > 0,
		'difference' => $controlAccount
	));
}

/**
 * Reserve the same payment columns on every row; only customer/supplier rows are editable.
 *
 * @param array<string, bool> $columns Journal column gates.
 * @param int $row Form row number.
 * @param bool $editable Whether this row has a customer debit or supplier credit.
 * @param string $due Due date, escaped here for its attribute.
 * @param string $payment Payment ID, escaped here for its attribute.
 * @param string $focus Trusted focus/blur event attributes.
 * @param string $charset Page character encoding.
 * @return string HTML cells, including placeholders for inapplicable rows.
 */
function kkJournalPaymentCells(array $columns, int $row, bool $editable, string $due, string $payment, string $focus, string $charset): string {
	$html = '';
	$fields = array(
		'due' => array('forf', 85, htmlspecialchars($due, ENT_QUOTES, $charset)),
		'payment' => array('b_id', 100, htmlspecialchars($payment, ENT_QUOTES, $charset))
	);
	foreach ($fields as $column => $field) {
		if (!$columns[$column]) {
			continue;
		}
		$attributes = $editable
			? "type='text' name='{$field[0]}$row' value=\"{$field[2]}\" $focus onchange='javascript:docChange = true;'"
			: "readonly='readonly'";
		$html .= "<td><input class='inputbox' style='text-align:left;width:{$field[1]}px;' $attributes></td>\n";
	}
	return $html;
}

/**
 * Keep balance cells present even when their value or the difference is zero.
 *
 * @param array<string, bool> $columns Journal column gates.
 * @param float|null $ledger Running control balance, or null for an empty row.
 * @param float|null $bank This row's bank balance; null means no imported balance.
 * @param string $title Escaped explanation when the control balance was recalculated.
 * @return string HTML cells in the header's ledger, bank, difference order.
 */
function kkJournalBalanceCells(array $columns, ?float $ledger, ?float $bank, string $title = ''): string {
	$html = '';
	if ($columns['ledger']) {
		$style = $title !== '' ? " style='font-weight:bold' title='$title'" : '';
		$html .= "<td align='right'$style>" . ($ledger !== null ? dkdecimal($ledger, 2) : '') . "</td>\n";
	}
	if ($columns['bank']) {
		$html .= "<td></td><td align='right'>" . ($bank !== null ? dkdecimal($bank, 2) : '') . "</td>\n";
	}
	if ($columns['difference']) {
		$difference = ($bank !== null && $ledger !== null) ? afrund($bank, 2) - afrund($ledger, 2) : null;
		$value = $difference ? "<div style='color:red'>(" . dkdecimal($difference) . ")</div>" : '';
		$html .= "<td></td><td align='right'>$value</td>\n";
	}
	return $html;
}
