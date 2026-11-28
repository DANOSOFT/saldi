<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/ordersWaitingForHent.php --- ver 5.1.1 --- 2026-11-20 ---
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261119 CL/ASR Created: orders_waiting_for_hent() tells whether sales or purchase invoices wait at status 3 for Hent ordrer, so Omgående bogføring of that kind is not switched on while they wait. The last setting that is off is not switched on while invoices of the other kind wait either.
// 20261120 CL/ASR It counts every order but a POS order (PO) at status 3, as the cash journal's Hent button and Hent ordrer do, split as Hent ordrer moves them: art K* is purchases, every other art is sales, so a reminder (R1-R3) left at status 3 after a failed posting keeps the last setting off too and is no longer left without Hent; it counted only DO/DK and KO/KK.
// 20261120 CL/ASR finans/kassekladde.php now shows Hent whenever this counts an order of either kind, whatever Omgående bogføring says (owner, 20 Nov 2026), so the doc no longer says the cash journal drops Hent once both settings are on.

if (!function_exists('orders_waiting_for_hent')) {
/**
 * Whether invoices of one kind wait at status 3 (invoiced, not posted) for Hent ordrer in the cash journal.
 *
 * They are the orders the list in finans/hentordrer.php shows: every order but a POS order (art PO) at status 3. The Hent
 * button in finans/kassekladde.php asks this function for both kinds. Hent ordrer moves an art that starts with K as a
 * purchase (Kreditorordrer: KO, KK) and every other art as a sales-side document (DO, DK, and a reminder R1-R3 that a failed
 * reminder posting left at status 3), so that is the split here too. As in the list, with vis_saet (POS/2 box12 of the
 * fiscal year) a sales order (art D*) paid at a till is left out: it waits for the register close (til_dagsafslutning() in
 * finans/hentordrer.php).
 * The cash journal shows Hent whenever an order of either kind waits, whatever Omgående bogføring says (owner, 20 Nov 2026:
 * an order left over after both settings are on, such as one whose journal lines were deleted, keeps Hent). Once a
 * setting is on, it cannot be switched off again, and Hent ordrer is for ledgers where it is off (owner, 19 Nov 2026), so
 * a setting is not switched on while invoices of its kind wait, and the last one that is off not while invoices of the
 * other kind wait either (st_hent_first_error() in includes/settings/components.php, saveOrderOptions()).
 *
 * @param string $kind 'sales' or 'purchases'
 * @return bool
 */
function orders_waiting_for_hent($kind) {
	global $regnaar;
	$sales = ($kind !== 'purchases');
	$tillClose = false;
	if ($sales) {
		$r = db_fetch_array(db_select("select box12 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '" . (int) $regnaar . "'", __FILE__ . " linje " . __LINE__));
		$tillClose = ($r && (bool) trim((string) $r['box12'])); // as til_dagsafslutning() reads it
	}
	$qtxt = "select art, felt_1, felt_2, felt_4, felt_5 from ordrer where status = 3 and art != 'PO' and art " . ($sales ? "not like 'K%'" : "like 'K%'");
	if (!$tillClose) {
		$qtxt .= " limit 1";
	}
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($tillClose && substr((string) $r['art'], 0, 1) === 'D' && $r['felt_1'] && is_numeric($r['felt_2']) && is_numeric($r['felt_4']) && is_numeric($r['felt_5']) && (int) $r['felt_5'] > 0) {
			continue; // paid at a till: the register close takes it
		}
		return true;
	}
	return false;
}
}
