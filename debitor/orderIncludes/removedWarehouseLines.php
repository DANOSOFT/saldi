<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/orderIncludes/removedWarehouseLines.php --- ver 5.1.1 --- 2026-11-16 ---
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
// 20261116 CL/MMR SD-827 Lines that would move stock on a warehouse that no longer exists, so
//                 debitor/levering.php can stop Fakturér, Kreditér and Lever with a message.

/**
 * Order lines that would move stock on a warehouse that is no longer set up.
 *
 * A credit note copies the warehouse from the invoice it credits. When that warehouse has
 * been removed since (grupper art='LG'), levering() would put the stock back on it, where no
 * page shows it. debitor/levering.php uses this list to stop and ask the user to choose an
 * active warehouse in the line's Lager field first.
 *
 * Only lines levering() would move stock for count: posnr > 0, an item number, a varegruppe
 * with stock (VG box8 'on') and a quantity to deliver now. With hurtigfakturering that is
 * antal minus what is already delivered, otherwise the line's leveres, as in levering().
 * A line without a warehouse (0) is left out: levering() posts it to 1, as before.
 *
 * The check only runs when the regnskab has two or more warehouse rows. With fewer, the
 * order page does not show the Lager field, so the user could not correct the line; those
 * lines are left to the posting code.
 *
 * Lines are named by item number, not posnr: the order page numbers its rows itself, and the
 * parts of a set are stored with posnr 100, 101 and so on. A part of a set gets its warehouse
 * from the set line (samlevare 'on'), whose Lager field is the one the user can change, so it
 * is reported as the set's item number. Each item number and warehouse is listed once.
 *
 * @param int|string $ordreId    ordrer.id
 * @param string     $hurtigfakt 'on' when the order is invoiced in one step (hurtigfakturering)
 * @param int|string $regnaar    Active fiscal year, used for the varegruppe like levering()
 * @return array<int, array{varenr: string, lager: int}> One entry per item number and warehouse, in
 *                                                       posnr order; empty when nothing is wrong.
 */
function order_lines_on_removed_warehouse($ordreId, $hurtigfakt, $regnaar) {
	$ordreId = (int)$ordreId;
	$regnaar = (int)$regnaar;
	$lines = array();
	if ($ordreId < 1) {
		return $lines;
	}

	$warehouses = array();
	$warehouseRows = 0;
	$q = db_select("select kodenr from grupper where art = 'LG'", __FILE__ . " linje " . __LINE__); // No fiscal_year @ art = LG
	while ($r = db_fetch_array($q)) {
		$warehouses[(int)$r['kodenr']] = true;
		$warehouseRows++;
	}
	if ($warehouseRows < 2) {
		return $lines;
	}

	$stockGroups = array();
	$setItems = array();
	$seen = array();
	$qtxt = "select ol.id, ol.posnr, ol.varenr, ol.antal, ol.leveres, ol.lager, ol.saet, ol.samlevare, v.gruppe";
	$qtxt .= " from ordrelinjer ol, varer v";
	$qtxt .= " where ol.ordre_id = '$ordreId' and v.id = ol.vare_id and ol.posnr > 0";
	$qtxt .= " order by ol.posnr, ol.id";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$lager = (int)$r['lager'];
		if ($lager < 1 || isset($warehouses[$lager]) || !strlen(trim((string)$r['varenr']))) {
			continue;
		}
		$gruppe = (string)$r['gruppe'];
		if (!isset($stockGroups[$gruppe])) {
			$qtxt = "select box8 from grupper where art = 'VG' and kodenr = '" . db_escape_string($gruppe) . "'";
			$qtxt .= " and fiscal_year = '$regnaar'";
			$g = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			$stockGroups[$gruppe] = ($g && trim((string)$g['box8']) == 'on');
		}
		if (!$stockGroups[$gruppe]) {
			continue;
		}
		if ($hurtigfakt == 'on') {
			$linjeId = (int)$r['id'];
			$d = db_fetch_array(db_select("select sum(antal) as antal from batch_salg where linje_id = '$linjeId'", __FILE__ . " linje " . __LINE__));
			$toDeliver = afrund((float)$r['antal'], 3) - (float)($d['antal'] ?? 0);
		} else {
			$toDeliver = afrund((float)$r['leveres'], 3);
		}
		if (abs($toDeliver) < 0.0005) {
			continue;
		}
		$varenr = trim((string)$r['varenr']);
		$saet = (int)$r['saet'];
		if ($saet > 0 && trim((string)$r['samlevare']) != 'on') {
			if (!isset($setItems[$saet])) {
				$qtxt = "select varenr from ordrelinjer where ordre_id = '$ordreId' and saet = '$saet' and samlevare = 'on' order by id limit 1";
				$h = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				$setItems[$saet] = ($h && strlen(trim((string)$h['varenr']))) ? trim((string)$h['varenr']) : '';
			}
			if ($setItems[$saet] !== '') {
				$varenr = $setItems[$saet];
			}
		}
		if (isset($seen["$varenr|$lager"])) {
			continue;
		}
		$seen["$varenr|$lager"] = true;
		$lines[] = array('varenr' => $varenr, 'lager' => $lager);
	}
	return $lines;
}
