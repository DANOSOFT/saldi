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
//     another. An amount stored with three decimals before MB-86 is kept while its field still holds the text it
//     was drawn with, also when another value of its line is changed (or a line is written only because the page
//     filled in something it had left empty, such as the account's VAT code). Any other text in the field is a new
//     amount, rounded like any other: 0.005 drawn as 0,01 and changed to 0,006 is saved as 0.010.
//
// 20261125 CL/MMR MB-86 (Codex round 1) Whether the field still shows the stored amount is decided on the text left in it, not on the rounded amount.
//                 0,006 rounds to the 0,01 the field showed, so that edit was lost.
// 20261125 CL/MMR MB-86 (Codex round 2) An amount that rounds past what kassekladde.amount (numeric(15,3)) holds is refused like any invalid amount.
//                 The debit/credit swap of a negative amount is decided on the typed sign, so -0,004 is saved as 0.00 on swapped accounts as before.
// 20261125 CL/MMR MB-86 (Codex round 3) A stored amount left as it was drawn is not range checked, so 999999999999.995 (shown 1.000.000.000.000,00) no longer blocks the save.
//                 journalAmountDrawn() is the one test for "left as drawn", used by the range check and by journalAmountKept().
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
 * True when the Beløb text can be saved: Danish format (dk_amount_is_valid()), and rounded to whole øre it fits
 * kassekladde.amount, numeric(15,3). 999.999.999.999,995 rounds to 1.000.000.000.000,00, which the column cannot
 * hold, so it is refused like "1234.56" instead of failing in the database.
 *
 * A stored amount left as it was drawn (journalAmountDrawn()) is valid whatever it rounds to: opdater() keeps the
 * stored amount, so nothing is written for it, and the user is not stopped by a value they did not type.
 *
 * @param string|null           $input        The field's text.
 * @param string|int|float|null $storedAmount The line's amount in the database, or null for a new line or when the
 *                                            text is not the line's own (the "+=" copy is a new line).
 * @return bool
 */
function journalAmountIsValid($input, $storedAmount = null) {
	if (journalAmountDrawn($storedAmount, $input)) return true;
	return dk_amount_is_valid($input) && abs(journalAmountFromInput($input)) <= 999999999999.99;
}

/**
 * True when the typed amount is below zero before it is rounded. opdater() saves a negative amount as a positive
 * one on swapped accounts, and -0,004 must still swap although it is saved as 0.00.
 *
 * @param string|null $input The field's text, read like journalAmountFromInput() reads it.
 * @return bool
 */
function journalAmountIsNegative($input) {
	$tal = str_replace(array('.', ','), array('', '.'), trim((string)$input));
	if (preg_match('/^-\d*(?:\.\d*)?$/', $tal)) return (bool)preg_match('/[1-9]/', $tal);
	return is_numeric($tal) && (float)$tal < 0;
}

/**
 * The text the Beløb field is drawn with for a stored amount, the same as dkdecimal($storedAmount, 2).
 *
 * @param string|int|float|null $storedAmount The line's amount in the database.
 * @return string E.g. "0,01" for 0.005 and "1.234,57" for 1234.565.
 */
function journalAmountShown($storedAmount) {
	return number_format(journalAmountRound($storedAmount), 2, ',', '.');
}

/**
 * True when the field still holds exactly the text it was drawn with for the stored amount, so the user did not
 * touch it. A negative stored amount never counts: opdater() saves a negative amount as a positive one on swapped
 * accounts, so it cannot be kept as it is.
 *
 * @param string|int|float|null $storedAmount The line's amount in the database, or null for a new line.
 * @param string|null           $typed        The field's text as posted, before rounding.
 * @return bool
 */
function journalAmountDrawn($storedAmount, $typed) {
	if ($storedAmount === null || !is_numeric(trim((string)$storedAmount)) || (float)$storedAmount < 0) return false;
	return trim((string)$typed) === journalAmountShown($storedAmount);
}

/**
 * The amount to write for a line that is saved: the stored amount while its field still holds the text it was drawn
 * with (journalAmountDrawn()), otherwise the new one. So an amount stored with three decimals before MB-86 is only
 * replaced when the user changes the text in the field.
 *
 * @param string|int|float|null $storedAmount The line's amount in the database, or null for a new line.
 * @param string|null           $typed        The field's text as posted (tmpkassekl.amount), before rounding.
 * @param float                 $amount       That text rounded to whole øre (journalAmountFromInput()), sign removed.
 * @return string|int|float The stored amount, or $amount.
 */
function journalAmountKept($storedAmount, $typed, $amount) {
	return journalAmountDrawn($storedAmount, $typed) ? $storedAmount : $amount;
}

/**
 * True when writing $new over the stored line would change nothing, so opdater() can leave the line alone.
 *
 * Each value is compared the way its column holds it: NULL counts as empty text, or as 0 in a number column,
 * numbers compare as numbers, and text and dates compare exactly. The amount compares with the stored amount as a
 * number to the column's three decimals (0.010 and 0.01 are equal, 0.005 and 0.010 are not); opdater() passes the
 * stored amount itself when journalAmountKept() keeps it.
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
			if (!is_numeric(trim($was)) || !is_numeric(trim($now)) || abs((float)$was - (float)$now) >= 0.0005) return false;
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
