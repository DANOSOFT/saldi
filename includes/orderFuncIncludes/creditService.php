<?php
// --- includes/orderFuncIncludes/creditService.php ---
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
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// Partial credit notes (SD-746): an invoice can get several credit notes, by quantity or as a dekort
// (an amount off a line, the customer keeps the item), and together they never credit more than it invoiced.
// Every credit line points at its invoice line through kred_linje_id; drafts count too, so two open
// drafts cannot add up to more than the invoice.
//
// History:
// 20261118 CL/SS SD-833 Created.
// 20261118 CL/SS SD-833 Codex review: negative invoice lines count in their own direction, and a capped price never rounds up.
//                A dekort is always held to the amount left.
// 20261120 CL/SS SD-833 review: a line whose quantity points the wrong way is held to nothing. The lines tell whether they are delivered.
// 20261121 CL/SS SD-833 review: an amount discount counts in kroner, as momsupdat() counts it.
//                What is left counts only the lines a credit note copies, and a held line delivers no more than it credits.

include_once(__DIR__ . '/stockNeutralLine.php'); # order_line_has_credit_columns()

function credit_line_net(array $line): float
{
	if (trim((string)($line['rabatart'] ?? '')) == 'amount') {
		$pris = (float)$line['pris'] - (float)$line['rabat'];
	} else {
		$pris = (float)$line['pris'] - (float)$line['pris'] * (float)$line['rabat'] / 100;
	}
	if ($line['procent'] !== null && $line['procent'] !== '') {
		$pris *= (float)$line['procent'] / 100;
	}
	return (float)$line['antal'] * $pris;
}

/** The procenttillæg item's number when the invoice carries a procenttillæg, else ''. */
function credit_percent_item(int $origId): string
{
	$r = db_fetch_array(db_select("select procenttillag from ordrer where id = '$origId'", __FILE__ . " linje " . __LINE__));
	if (!$r || !(float)$r['procenttillag']) {
		return '';
	}
	$r = db_fetch_array(db_select("select box13 from grupper where art = 'DIV' and kodenr = '3'", __FILE__ . " linje " . __LINE__));
	$parts = explode(chr(9), (string)($r['box13'] ?? ''));
	return trim($parts[1] ?? '');
}

/**
 * What is left to credit on each line of an invoice, keyed by the invoice line's id.
 * Quantities count in the direction of the line's quantity and amounts in the direction of its net amount, so a
 * negative line (a return or a discount on the invoice) is credited the other way round, and only once.
 * Only the lines the Kredit copy in debitor/ordre.php takes count: an item, posnr > 0, not a følgevare, not the procenttillæg.
 * With $lock it locks the invoice row, so call it inside the transaction that writes the credit lines.
 * $exceptCreditId leaves out one credit note's own lines, for checking that note while it is edited.
 */
function credit_remaining(int $origId, int $exceptCreditId = 0, bool $lock = true): array
{
	if ($lock) {
		db_select("select id from ordrer where id = '$origId' for update", __FILE__ . " linje " . __LINE__);
	}
	$remaining = array();
	$qtxt = "select id, antal, pris, rabat, rabatart, procent from ordrelinjer where ordre_id = '$origId' and antal != 0";
	$qtxt .= " and posnr > 0 and vare_id > 0 and coalesce(folgevare, 0) >= 0";
	if ($percentItem = credit_percent_item($origId)) {
		$qtxt .= " and varenr != '" . db_escape_string($percentItem) . "'";
	}
	$q = db_select("$qtxt order by posnr, id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$net = credit_line_net($r);
		$remaining[(int)$r['id']] = array(
			'qty_sign' => (float)$r['antal'] < 0 ? -1 : 1,
			'amount_sign' => $net < 0 ? -1 : 1,
			'antal' => abs((float)$r['antal']),
			'amount' => round(abs($net), 3),
			'credited_antal' => 0.0,
			'credited_amount' => 0.0,
		);
	}
	if ($remaining) {
		$kind = order_line_has_credit_columns() ? ', ol.kredit_art' : '';
		$qtxt = "select ol.kred_linje_id, ol.antal, ol.pris, ol.rabat, ol.rabatart, ol.procent$kind from ordrelinjer ol, ordrer o";
		$qtxt .= " where o.id = ol.ordre_id and o.art = 'DK' and o.id != '$exceptCreditId'";
		$qtxt .= " and ol.kred_linje_id in (" . implode(',', array_keys($remaining)) . ")";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$line = &$remaining[(int)$r['kred_linje_id']];
			if (trim((string)($r['kredit_art'] ?? '')) != 'dekort') {
				$line['credited_antal'] -= (float)$r['antal'] * $line['qty_sign'];
			}
			$line['credited_amount'] -= credit_line_net($r) * $line['amount_sign'];
			unset($line);
		}
	}
	foreach ($remaining as $id => $line) {
		$remaining[$id]['credited_antal'] = round($line['credited_antal'], 3);
		$remaining[$id]['credited_amount'] = round($line['credited_amount'], 3);
		$remaining[$id]['remaining_antal'] = round($line['antal'] - $line['credited_antal'], 3);
		$remaining[$id]['remaining_amount'] = round($line['amount'] - $line['credited_amount'], 3);
	}
	return $remaining;
}

/** True when a new credit note should take the line: a quantity is left, and so is its amount unless it had none. */
function credit_line_left(array $line): bool
{
	return $line['remaining_antal'] > 0.0005 && ($line['amount'] <= 0.005 || $line['remaining_amount'] > 0.005);
}

/** A credit note's lines that point at an invoice line, keyed by their own id. delivered_rows > 0 once a line is delivered. */
function credit_note_lines(int $creditId): array
{
	$kind = order_line_has_credit_columns() ? ', kredit_art, retur_lager' : '';
	$lines = array();
	$qtxt = "select id, kred_linje_id, vare_id, varenr, beskrivelse, antal, leveres, pris, rabat, rabatart, procent$kind,";
	$qtxt .= " (select count(*) from batch_salg b where b.linje_id = ordrelinjer.id) as delivered_rows from ordrelinjer";
	$qtxt .= " where ordre_id = '$creditId' and kred_linje_id > 0 order by posnr, id";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$lines[(int)$r['id']] = $r;
	}
	return $lines;
}

/**
 * A dekort credits $dekortPct of the invoice line's amount as one line, and the customer keeps the item.
 * Without a dekort the line credits a quantity at the invoice line's price, back on stock when $restock.
 */
function credit_apply_line_choice(int $lineId, float $dekortPct, bool $restock): void
{
	if (!order_line_has_credit_columns()) {
		return;
	}
	$qtxt = "select c.kredit_art, o.antal, o.pris, o.rabat, o.rabatart, o.procent from ordrelinjer c, ordrelinjer o";
	$qtxt .= " where c.id = '$lineId' and o.id = c.kred_linje_id";
	if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		return;
	}
	$dekortPct = max(0.0, min(100.0, $dekortPct));
	if ($dekortPct > 0) {
		$amount = round(credit_line_net($r) * $dekortPct / 100, 2);
		$qtxt = "update ordrelinjer set antal = -1, pris = '$amount', rabat = 0, procent = 100, kredit_art = 'dekort', retur_lager = 'of'";
	} else {
		$retur = $restock ? 'on' : 'of';
		$qtxt = "update ordrelinjer set kredit_art = 'antal', retur_lager = '$retur'";
		if (trim((string)$r['kredit_art']) == 'dekort') {
			$procent = ($r['procent'] === null || $r['procent'] === '') ? 'NULL' : (float)$r['procent'];
			$qtxt .= ", antal = -1, pris = '" . (float)$r['pris'] . "', rabat = '" . (float)$r['rabat'] . "', procent = $procent";
			$qtxt .= ", rabatart = '" . db_escape_string((string)$r['rabatart']) . "'";
		}
	}
	db_modify("$qtxt where id = '$lineId'", __FILE__ . " linje " . __LINE__);
}

/**
 * Lowers a draft credit note's lines to what is left on the invoice after its other credit notes:
 * first the quantity, then the price, so a unit already part credited by a dekort is credited at what is left of it.
 * A quantity that points the wrong way (it would charge, not credit) is set to 0.
 * What the line is set to deliver (leveres) is held within its quantity, so delivery moves no more stock than it credits.
 * The price is cut toward zero, so the stored amount never exceeds what is left.
 * Returns the item numbers of the lines it changed. Locks the invoice row.
 */
function credit_hold_to_remaining(int $creditId): array
{
	$r = db_fetch_array(db_select("select kred_ord_id, art, status from ordrer where id = '$creditId'", __FILE__ . " linje " . __LINE__));
	if (!$r || $r['art'] != 'DK' || (int)$r['status'] >= 3 || !(int)$r['kred_ord_id']) {
		return array();
	}
	$left = credit_remaining((int)$r['kred_ord_id'], $creditId);
	$changed = array();
	foreach (credit_note_lines($creditId) as $id => $line) {
		$origId = (int)$line['kred_linje_id'];
		if (!isset($left[$origId])) {
			continue;
		}
		$orig = &$left[$origId];
		$dekort = trim((string)($line['kredit_art'] ?? '')) == 'dekort';
		$qty = -(float)$line['antal'] * $orig['qty_sign'];
		if (!$dekort && $qty <= 0.0005) {
			if ($qty < -0.0005 || abs((float)$line['leveres']) > 0.0005) {
				db_modify("update ordrelinjer set antal = 0, leveres = 0 where id = '$id'", __FILE__ . " linje " . __LINE__);
			}
			if ($qty < -0.0005) {
				$changed[] = $line['varenr'];
			}
			unset($orig);
			continue;
		}
		$set = array();
		if (!$dekort) {
			$maxQty = max(0.0, $orig['remaining_antal']);
			if ($qty > $maxQty + 0.0005) {
				$qty = $maxQty;
				$line['antal'] = -$qty * $orig['qty_sign'];
				$set[] = "antal = '" . $line['antal'] . "'";
			}
			$orig['remaining_antal'] -= $qty;
		}
		$amount = -credit_line_net($line) * $orig['amount_sign'];
		$maxAmount = max(0.0, $orig['remaining_amount']);
		if ($amount > $maxAmount + 0.0005) {
			$pris = 0.0;
			if ((float)$line['antal'] != 0) {
				$pris = -$maxAmount * $orig['amount_sign'] / (float)$line['antal'];
				$pris = ($pris < 0 ? ceil($pris * 1000 - 1e-9) : floor($pris * 1000 + 1e-9)) / 1000;
			}
			$line['pris'] = $pris;
			$line['rabat'] = 0;
			$line['procent'] = 100;
			$set[] = "pris = '$pris', rabat = 0, procent = 100";
			$amount = -credit_line_net($line) * $orig['amount_sign'];
		}
		$orig['remaining_amount'] -= $amount;
		unset($orig);
		if ($set) {
			$changed[] = $line['varenr'];
		}
		$antal = (float)$line['antal'];
		$leveres = (float)$line['leveres'];
		$heldLeveres = max(min($leveres, max(0.0, $antal)), min(0.0, $antal));
		if (abs($heldLeveres - $leveres) > 0.0005) {
			$set[] = "leveres = '$heldLeveres'";
		}
		if ($set) {
			db_modify("update ordrelinjer set " . implode(', ', $set) . " where id = '$id'", __FILE__ . " linje " . __LINE__);
		}
	}
	return $changed;
}

/** The open (unposted) credit note draft of an invoice, 0 when there is none. */
function credit_open_draft(int $origId): int
{
	$r = db_fetch_array(db_select("select id from ordrer where kred_ord_id = '$origId' and art = 'DK' and status < 3 order by id limit 1", __FILE__ . " linje " . __LINE__));
	return $r ? (int)$r['id'] : 0;
}

/** True while some line of the invoice that the customer paid for still has something to credit. */
function credit_left_on_invoice(int $origId): bool
{
	foreach (credit_remaining($origId, 0, false) as $line) {
		if ($line['amount_sign'] > 0 && credit_line_left($line)) {
			return true;
		}
	}
	return false;
}
