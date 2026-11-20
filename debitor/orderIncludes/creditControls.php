<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/orderIncludes/creditControls.php --- ver 5.1.0 --- 2026-11-20 ---
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
// Copyright (c) 2026-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261118 CL/SS SD-833: created. The dekort and restock choices on a credit note draft, under its lines.
// 20261120 CL/SS SD-833 review: once a line is delivered its choices are locked, as delivery has already moved the stock or not.

include_once(__DIR__ . '/../../includes/orderFuncIncludes/creditService.php');

/**
 * Saves the choices posted from the block and keeps the draft within what is left on the invoice.
 * Runs inside the order save's transaction, after the lines are saved. Returns an alert text, or ''.
 */
function credit_controls_save(int $creditId, array $post, $sprog_id): string
{
	if (isset($post['credit_line']) && is_array($post['credit_line'])) {
		$lines = credit_note_lines($creditId);
		foreach (array_keys($post['credit_line']) as $lineId) {
			$lineId = (int)$lineId;
			if (!isset($lines[$lineId]) || (int)$lines[$lineId]['delivered_rows'] > 0) {
				continue;
			}
			$pct = (float)usdecimal((string)($post['credit_dekort'][$lineId] ?? ''));
			credit_apply_line_choice($lineId, $pct, isset($post['credit_restock'][$lineId]));
		}
	}
	$changed = credit_hold_to_remaining($creditId);
	if (!$changed) {
		return '';
	}
	return findtekst('8067|Reguleret til det, der er tilbage at kreditere', $sprog_id) . ': ' . implode(', ', $changed);
}

/** The block under a credit note draft's lines: per line a dekort percentage, restock, and what is left. */
function credit_controls_render(int $creditId, $sprog_id, $charset): void
{
	if (!order_line_has_credit_columns()) {
		return;
	}
	$r = db_fetch_array(db_select("select kred_ord_id from ordrer where id = '$creditId'", __FILE__ . " linje " . __LINE__));
	$origId = (int)($r['kred_ord_id'] ?? 0);
	$lines = credit_note_lines($creditId);
	if (!$origId || !$lines) {
		return;
	}
	$left = credit_remaining($origId, $creditId, false);
	$h = function ($txt) use ($charset) {
		return htmlspecialchars((string)$txt, ENT_QUOTES, $charset);
	};
	$dekortTitle = $h(findtekst('8065|Procent af linjens fakturerede beløb. Kunden beholder varen, og lageret ændres ikke.', $sprog_id));
	$restockTitle = $h(findtekst('8066|Varen lægges på lager igen på det lager, den blev trukket fra.', $sprog_id));
	print "<tr><td colspan='12'><table class='dataTable' cellspacing='0' cellpadding='2' width='100%'><tbody>\n";
	print "<tr><td colspan='5'><b>" . $h(findtekst('8061|Kreditering', $sprog_id)) . "</b></td></tr>\n";
	print "<tr><td>" . $h(findtekst('917|Varenr.', $sprog_id)) . "</td><td></td>";
	print "<td align='right' title='$dekortTitle'>" . $h(findtekst('8062|Dekort %', $sprog_id)) . "</td>";
	print "<td align='center' title='$restockTitle'>" . $h(findtekst('8063|Læg på lager', $sprog_id)) . "</td>";
	print "<td align='right'>" . $h(findtekst('8064|Tilbage at kreditere', $sprog_id)) . "</td></tr>\n";
	foreach ($lines as $id => $line) {
		$orig = $left[(int)$line['kred_linje_id']] ?? null;
		$dekort = trim((string)($line['kredit_art'] ?? '')) == 'dekort';
		$pct = '';
		if ($dekort && $orig && $orig['amount'] > 0) {
			$pct = dkdecimal(-credit_line_net($line) * $orig['amount_sign'] * 100 / $orig['amount'], 2);
		}
		$checked = (!$dekort && trim((string)($line['retur_lager'] ?? '')) != 'of') ? 'checked' : '';
		$delivered = (int)$line['delivered_rows'] > 0;
		$disabled = ($dekort || $delivered) ? 'disabled' : '';
		print "<tr><td>" . $h($line['varenr']) . ($delivered ? '' : "<input type='hidden' name='credit_line[$id]' value='1'>") . "</td>";
		print "<td>" . $h($line['beskrivelse']) . "</td>";
		print "<td align='right' title='$dekortTitle'><input class='inputbox' type='text' style='text-align:right;width:50px' name='credit_dekort[$id]' value='$pct'" . ($delivered ? ' disabled' : '') . "></td>";
		print "<td align='center' title='$restockTitle'><input type='checkbox' name='credit_restock[$id]' $checked $disabled></td>";
		print "<td align='right'>";
		if ($orig) {
			print dkdecimal($orig['remaining_antal'], 2) . " / " . dkdecimal($orig['remaining_amount'], 2);
		}
		print "</td></tr>\n";
	}
	print "</tbody></table></td></tr>\n";
}
