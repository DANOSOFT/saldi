<?php
// --- finans/kassekladde_includes/journalAmount.php --- 20261125 CL/MMR MB-86 ---
//
// The amount a cash journal line is saved with, and whether a saved line was changed at all.
//
// The Beløb field shows two decimals, but the save stored what usdecimal() made of the typed text, which keeps
// three: 0,005 was stored as 0.005 and shown as 0,01. Every Gem posts every line back, and opdater() wrote every
// line again from its field, so the next save of any line turned the hidden 0.005 into 0.010 (0,001 into 0.000,
// 12,345 into 12.350 on Simulér). An amount the user never touched changed.
//
// Two rules fix that (Lui 2026-11-20):
//   - a typed amount is rounded to whole øre before it is stored, half away from zero like øre rounding
//     (0,005 -> 0,01, -0,005 -> -0,01, 12,345 -> 12,35), so the stored amount is the one the field shows
//   - a line whose values are already the stored ones is not written again, so saving one line never rewrites
//     another. An amount stored with three decimals before MB-86 counts as unchanged while the field still shows
//     it rounded, and it is also kept when another value of its line is changed (or a line is written only because
//     the page filled in something it had left empty, such as the account's VAT code). It changes only when the
//     user types an amount the field did not show, and that amount is rounded like any other.
//
// usdecimal() itself is not changed: it is used all over Saldi, also where three decimals are wanted
// (quantities, prices). Only the journal's save path rounds to øre.

/**
 * Rounds a number to whole øre, half away from zero.
 *
 * The digits are rounded as text: 12.345 is 12.34499... as a float, so a float round() depends on the PHP
 * version's pre-rounding to give 12.35.
 *
 * @param string|int|float|null $number A number with "." as the decimal separator, e.g. a database value.
 * @return float The number with at most two decimals; 0.0 when $number is not a number.
 */
function journalAmountRound($number) {
	$tal = trim((string)$number);
	if (preg_match('/^([+-]?)(\d{0,15})(?:\.(\d*))?$/', $tal, $m) && ($m[2] !== '' || (isset($m[3]) && $m[3] !== ''))) {
		$decimaler = str_pad(isset($m[3]) ? $m[3] : '', 3, '0');
		$oere = (int)$m[2] * 100 + (int)substr($decimaler, 0, 2);
		if ($decimaler[2] >= '5') $oere++;
		return (float)($m[1] === '-' ? -$oere : $oere) / 100;
	}
	// An exponent or more than 15 integer digits: no amount the column can hold, so a float round() is good enough
	return is_numeric($tal) ? round((float)$tal, 2) : 0.0;
}

/**
 * The amount a line is saved with: the text of its Beløb field, read the way usdecimal() reads it ("." groups
 * thousands, "," is the decimal separator), rounded to whole øre.
 *
 * @param string|null $input The field's text, as staged in tmpkassekl.
 * @return float
 */
function journalAmountFromInput($input) {
	return journalAmountRound(str_replace(array('.', ','), array('', '.'), trim((string)$input)));
}

/**
 * The amount to write for a line that is saved: the stored amount while its field still shows it, otherwise the
 * new one. So an amount stored with three decimals before MB-86 only changes when the user types another amount.
 *
 * @param string|int|float|null $storedAmount The line's amount in the database, or null for a new line.
 * @param float                 $amount       The amount from the field, already rounded (journalAmountFromInput()).
 * @return string|int|float The stored amount, or $amount.
 */
function journalAmountKept($storedAmount, $amount) {
	if ($storedAmount === null || !is_numeric(trim((string)$storedAmount))) return $amount;
	return abs(journalAmountRound($storedAmount) - journalAmountRound($amount)) < 0.005 ? $storedAmount : $amount;
}

/**
 * True when writing $new over the stored line would change nothing, so opdater() can leave the line alone.
 *
 * Each value is compared the way its column holds it: NULL counts as empty text, or as 0 in a number column,
 * numbers compare as numbers, and text and dates compare exactly. The amount compares with what the Beløb field
 * showed for the stored amount (rounded to whole øre), so a line stored with 0.005 before MB-86 stays unchanged
 * while its field still says 0,01.
 *
 * @param array<string, mixed>|null $stored The kassekladde row from the database, or null when it was not found.
 * @param array<string, mixed>      $new    Column => the value opdater() would write.
 * @return bool
 */
function journalLineUnchanged($stored, array $new) {
	if (!is_array($stored)) return false;
	$numeric = array('bilag', 'debet', 'kredit', 'afd', 'ansat', 'valuta');
	foreach ($new as $column => $value) {
		if (!array_key_exists($column, $stored)) return false;
		$was = $stored[$column] === null ? '' : (string)$stored[$column];
		$now = $value === null ? '' : (string)$value;
		if ($column === 'amount') {
			if (journalAmountKept($stored[$column], $value) !== $stored[$column]) return false;
		} elseif (in_array($column, $numeric, true)) {
			$was = trim($was) === '' ? '0' : trim($was);
			$now = trim($now) === '' ? '0' : trim($now);
			if (is_numeric($was) && is_numeric($now) ? (float)$was != (float)$now : $was !== $now) return false;
		} elseif ($was !== $now) {
			return false;
		}
	}
	return true;
}
