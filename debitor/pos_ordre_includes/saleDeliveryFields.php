<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/saleDeliveryFields.php --- ver 5.1.0 ---- 2026-11-20 ----
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261120 CL/LH K33/K34 The delivery choice and the wished delivery date of a POS sale.
//                The cashier picks one of the choices set up under POS-valg and, for afhentning or levering, the date the customer wishes.
//                Both are stored on the order (ordrer.leveringsvalg_id, ordrer.leveringsmaade, ordrer.oensket_levdato), where the store-order export (includes/shopify/services/storeOrders.php, SD-804) reads them.
//                No choice is "taget med".
//                The wished date never goes in levdate, which exitFunc/settlePOS.php overwrites when the sale is posted.
//                Without an active choice, or without the Shopify schema (MySQL), the POS is exactly as before.

include_once(__DIR__ . "/../../systemdata/diverseIncludes/posSaleSettings.php");

/**
 * The delivery choice and wished date stored on a sale.
 *
 * @param int $orderId
 * @return array{valg_id: ?int, navn: string, type: string, dato: ?string, status: int, art: string}|null
 *         NULL when the order does not exist.
 */
function pos_sale_delivery($orderId) {
	$orderId = (int) $orderId;
	if ($orderId < 1 || !pos_delivery_supported()) {
		return null;
	}
	$qtxt = "select o.status, o.art, o.leveringsvalg_id, o.leveringsmaade, o.oensket_levdato, v.navn, v.type";
	$qtxt.= " from ordrer o left join pos_leveringsvalg v on v.id = o.leveringsvalg_id where o.id = '$orderId'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$r) {
		return null;
	}
	$valgId = ($r['leveringsvalg_id'] === null || $r['leveringsvalg_id'] === '') ? null : (int) $r['leveringsvalg_id'];
	return array(
		'valg_id' => $valgId,
		'navn' => (string) ($r['navn'] !== null ? $r['navn'] : $r['leveringsmaade']),
		'type' => $valgId ? (string) $r['type'] : 'taget_med',
		'dato' => $r['oensket_levdato'] ? substr((string) $r['oensket_levdato'], 0, 10) : null,
		'status' => (int) $r['status'],
		'art' => (string) $r['art'],
	);
}

/**
 * A date as the cashier typed it - YYYY-MM-DD from the date field, or dd-mm-yyyy, dd.mm.yyyy or
 * dd/mm/yyyy typed by hand - as YYYY-MM-DD, or NULL when it is not a real date.
 *
 * @param string $typed
 * @return string|null
 */
function pos_delivery_parse_date($typed) {
	$typed = trim((string) $typed);
	if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $typed, $m)) {
		list(, $y, $mo, $d) = $m;
	} elseif (preg_match('/^(\d{1,2})[-.\/](\d{1,2})[-.\/](\d{4})$/', $typed, $m)) {
		list(, $d, $mo, $y) = $m;
	} else {
		return null;
	}
	if (!checkdate((int) $mo, (int) $d, (int) $y)) {
		return null;
	}
	return sprintf('%04d-%02d-%02d', $y, $mo, $d);
}

/**
 * Stores the delivery choice and wished date the cashier set on an open sale.
 *
 * An empty choice is "taget med" and clears both. A choice has to be active - or be the one the
 * sale already has, which the field posts back on every scan even after it was deactivated. A
 * taget med choice never keeps a date. The date is optional for afhentning and levering (a delivery
 * without a date goes to Shopify with "Leveringsdato aftales med kunden", K36), but when given it
 * has to be a real date, today or later. A refused input changes nothing on the sale.
 *
 * @param int    $orderId
 * @param string $valg    The posted pos_leveringsvalg id, '' for taget med.
 * @param string $dato    The posted wished date, '' for none.
 * @return string|null The reason the input was refused, to show the cashier, or NULL.
 */
function pos_save_sale_delivery($orderId, $valg, $dato) {
	global $sprog_id;
	$sale = pos_sale_delivery($orderId);
	if (!$sale || $sale['art'] !== 'PO' || $sale['status'] >= 3) {
		return null;
	}
	$orderId = (int) $orderId;
	$valg = trim((string) $valg);
	if ($valg === '') {
		$qtxt = "update ordrer set leveringsvalg_id = NULL, leveringsmaade = NULL, oensket_levdato = NULL where id = '$orderId'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		return null;
	}
	$valgId = ctype_digit($valg) ? (int) $valg : 0;
	$choice = null;
	if ($valgId > 0) {
		$qtxt = "select id, navn, type, aktiv from pos_leveringsvalg where id = '$valgId'";
		$choice = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	}
	$active = $choice && in_array($choice['aktiv'], array(true, 't', '1', 1), true);
	if (!$choice || (!$active && $valgId !== $sale['valg_id'])) {
		return findtekst('pos.delivery.error.choice|Leveringsmåden findes ikke eller er ikke længere aktiv.', $sprog_id);
	}
	$date = null;
	$dato = trim((string) $dato);
	if ($choice['type'] !== 'taget_med' && $dato !== '') {
		$date = pos_delivery_parse_date($dato);
		if ($date === null) {
			return findtekst('pos.delivery.error.date|Ønsket leveringsdato skal være en gyldig dato (dd-mm-åååå).', $sprog_id);
		}
		if ($date < date('Y-m-d')) {
			return findtekst('pos.delivery.error.past|Ønsket leveringsdato kan ikke ligge før i dag.', $sprog_id);
		}
	}
	$qtxt = "update ordrer set leveringsvalg_id = '$valgId', leveringsmaade = " . pos_delivery_sql($choice['navn']) . ",";
	$qtxt.= " oensket_levdato = " . ($date === null ? 'NULL' : "'$date'") . " where id = '$orderId'";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	return null;
}

/**
 * The SET clause that clears a sale's delivery fields, for the statements that turn an abandoned
 * sale into a new one. Empty where the columns do not exist.
 *
 * @return string
 */
function pos_reset_sale_delivery_sql() {
	return pos_delivery_supported() ? ',leveringsvalg_id=NULL,leveringsmaade=NULL,oensket_levdato=NULL' : '';
}

/**
 * The delivery row in the sale header (debitor/func/pos_ordre_itemscan.php).
 *
 * An open sale gets the list of active choices, which submits the sale form when it changes, and,
 * for afhentning and levering, the wished date. A posted sale shows its choice and date as text.
 * Nothing is printed until a delivery choice has been set up.
 *
 * @param int $orderId 0 while the sale has no row yet.
 * @param int $status
 * @param int $sprog_id
 * @return void
 */
function pos_render_sale_delivery_fields($orderId, $status, $sprog_id) {
	if (!pos_delivery_supported()) {
		return;
	}
	$sale = pos_sale_delivery($orderId);
	$label = pos_delivery_html(findtekst('pos.delivery.label|Levering', $sprog_id));
	$dateLabel = pos_delivery_html(findtekst('pos.delivery.date|Ønsket leveringsdato', $sprog_id));
	if ((int) $status >= 3) {
		if ($sale && $sale['valg_id'] && $sale['type'] !== 'taget_med') {
			print "<tr><td colspan='6'>$label: " . pos_delivery_html($sale['navn']);
			if ($sale['dato']) {
				print " &nbsp; $dateLabel: " . dkdato($sale['dato']);
			}
			print "</td></tr>\n";
		}
		return;
	}
	if (!pos_delivery_enabled()) {
		return;
	}
	$current = $sale ? $sale['valg_id'] : null;
	$choices = pos_delivery_choices(true);
	$hasTakenAlong = false;
	$currentListed = false;
	foreach ($choices as $choice) {
		$hasTakenAlong = $hasTakenAlong || $choice['type'] === 'taget_med';
		$currentListed = $currentListed || $choice['id'] === $current;
	}
	// A choice deactivated after it was picked stays on the sale until the cashier changes it.
	if ($current && !$currentListed) {
		array_unshift($choices, array('id' => $current, 'navn' => $sale['navn'], 'type' => $sale['type']));
	}
	$type = $sale ? $sale['type'] : 'taget_med';
	print "<tr><td colspan='6'><span class='pos-levering'>$label: ";
	print "<select class='inputbox' name='leveringsvalg_id' onchange='this.form.submit()'>";
	if (!$hasTakenAlong) {
		$selected = $current ? '' : ' selected';
		$types = pos_delivery_types($sprog_id);
		print "<option value='' data-type='taget_med'$selected>" . pos_delivery_html($types['taget_med']) . "</option>";
	}
	$selectedOne = (bool) $current || !$hasTakenAlong;
	foreach ($choices as $choice) {
		// With no choice stored, the first taget med choice stands for the default.
		$isSelected = $current ? $choice['id'] === $current : (!$selectedOne && $choice['type'] === 'taget_med');
		$selectedOne = $selectedOne || $isSelected;
		$selected = $isSelected ? ' selected' : '';
		print "<option value='" . (int) $choice['id'] . "' data-type='" . pos_delivery_html($choice['type']) . "'$selected>";
		print pos_delivery_html($choice['navn']) . "</option>";
	}
	print "</select></span>";
	if ($type !== 'taget_med') {
		$value = ($sale && $sale['dato']) ? $sale['dato'] : '';
		$min = date('Y-m-d');
		// Typing a year fires change on every digit; only a date from today on submits the form,
		// and Enter in the field submits it as any other field does.
		print " &nbsp; <span class='pos-levdato'>$dateLabel: ";
		print "<input class='inputbox' type='date' name='oensket_levdato' value='$value' min='$min'";
		print " onchange='if (this.value && this.value >= this.min) this.form.submit()'></span>";
	}
	print "</td></tr>\n";
}
