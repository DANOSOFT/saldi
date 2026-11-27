<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/saleDeliveryFields.php --- ver 5.1.1 ---- 2026-11-26 ----
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
// 20261124 CL/LH K33 The wished date is read as YYYY-MM-DD whatever the connection's DateStyle, the background save sends the session's CSRF token, and pos_move_sale_delivery() takes a moved sale's delivery to the table's sale.
// 20261126 CDX/NTR Save cleared dates, validate complete past dates and report whether a background request actually updated an open POS sale.

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
	// to_char: the date input takes only YYYY-MM-DD, and a DMY DateStyle would blank it, so the next submit cleared the date.
	$qtxt = "select o.status, o.art, o.leveringsvalg_id, o.leveringsmaade, to_char(o.oensket_levdato, 'YYYY-MM-DD') as oensket_levdato, v.navn, v.type";
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
 * An empty choice, and any choice of type taget med, is "taget med": both fields are cleared, since
 * the store-order export treats a sale without a choice as taken along. A choice has to be active -
 * or be the one the sale already has, which the field posts back with every submit even after it
 * was deactivated. The date is optional for afhentning and levering (a delivery without a date goes
 * to Shopify with "Leveringsdato aftales med kunden", K36), but when given it has to be a real date,
 * today or later. A refused choice changes nothing on the sale; with a refused date the choice is
 * still stored and the sale keeps the date it had.
 *
 * The choice row is read with a share lock in the same transaction as the order update, so
 * pos_save_delivery_choices() cannot rewrite or delete it between the check and the attach.
 *
 * @param int    $orderId
 * @param string $valg    The posted pos_leveringsvalg id, '' for taget med.
 * @param string $dato    The posted wished date, '' for none.
 * @return string|false|null The refusal to show the cashier, false when no open POS sale was updated, or NULL on success.
 */
function pos_save_sale_delivery($orderId, $valg, $dato) {
	global $sprog_id;
	$sale = pos_sale_delivery($orderId);
	if (!$sale || $sale['art'] !== 'PO' || $sale['status'] >= 3) {
		return false;
	}
	$orderId = (int) $orderId;
	$valg = trim((string) $valg);
	$valgId = pos_delivery_choice_id($valg);
	if ($valg !== '' && $valgId < 1) {
		return findtekst('pos.delivery.error.choice|Leveringsmåden findes ikke eller er ikke længere aktiv.', $sprog_id);
	}
	$dato = trim((string) $dato);
	$date = null;
	if ($dato !== '') {
		$date = pos_delivery_parse_date($dato);
		if ($date === null) {
			$error = findtekst('pos.delivery.error.date|Ønsket leveringsdato skal være en gyldig dato (dd-mm-åååå).', $sprog_id);
		} elseif ($date < date('Y-m-d')) {
			$error = findtekst('pos.delivery.error.past|Ønsket leveringsdato kan ikke ligge før i dag.', $sprog_id);
		}
	}
	$refusal = null;
	$applied = true;
	$ok = pos_delivery_try(function () use ($orderId, $valgId, $sale, $date, &$error, &$refusal, &$applied, $sprog_id) {
		$choice = null;
		if ($valgId) {
			$rows = shopify_db_rows('SELECT id, navn, type, aktiv FROM pos_leveringsvalg WHERE id = $1 FOR SHARE', array($valgId));
			$choice = $rows ? $rows[0] : null;
			if (!$choice || ($choice['aktiv'] !== 't' && $valgId !== $sale['valg_id'])) {
				$refusal = findtekst('pos.delivery.error.choice|Leveringsmåden findes ikke eller er ikke længere aktiv.', $sprog_id);
				return;
			}
		}
		if (!$choice || $choice['type'] === 'taget_med') {
			// Taken along never keeps a date, so a date typed before switching back is not an error.
			$applied = (bool) shopify_db_rows("UPDATE ordrer SET leveringsvalg_id = NULL, leveringsmaade = NULL, oensket_levdato = NULL WHERE id = $1 AND art = 'PO' AND status < 3 RETURNING id", array($orderId));
			return;
		}
		if (isset($error)) {
			// The pick stands; only the date is refused, and the sale keeps the date it had.
			$applied = (bool) shopify_db_rows(
				"UPDATE ordrer SET leveringsvalg_id = $2, leveringsmaade = $3 WHERE id = $1 AND art = 'PO' AND status < 3 RETURNING id",
				array($orderId, $valgId, $choice['navn'])
			);
			$refusal = $error;
			return;
		}
		$applied = (bool) shopify_db_rows(
			"UPDATE ordrer SET leveringsvalg_id = $2, leveringsmaade = $3, oensket_levdato = $4 WHERE id = $1 AND art = 'PO' AND status < 3 RETURNING id",
			array($orderId, $valgId, $choice['navn'], $date)
		);
	});
	if (!$ok) {
		return findtekst('pos.delivery.error.save|Leveringsmåden kunne ikke gemmes. Prøv igen.', $sprog_id);
	}
	return $applied ? $refusal : false;
}

/**
 * Validates a delivery endpoint payload and reports whether it saved an open POS sale.
 *
 * @param array $post Posted delivery fields; permission and CSRF checks belong to the endpoint.
 * @return array{ok: bool, error?: string} A refusal has a cashier-facing error; an inapplicable request does not.
 */
function pos_sale_delivery_response($post) {
	$id = ifset($post, 'id', '');
	$choice = ifset($post, 'leveringsvalg_id');
	$date = ifset($post, 'oensket_levdato', '');
	if (!is_scalar($id) || !is_scalar($choice) || !is_scalar($date) || !ctype_digit((string) $id)
		|| strlen((string) $id) > 10 || (int) $id < 1 || (int) $id > 2147483647) {
		return array('ok' => false);
	}
	$error = pos_save_sale_delivery((int) $id, (string) $choice, (string) $date);
	if (is_string($error)) {
		return array('ok' => false, 'error' => $error);
	}
	return array('ok' => $error === null);
}

/**
 * A posted choice id as an integer, or 0 when it is empty, not a number or outside the integer
 * column's range.
 *
 * @param string $valg
 * @return int
 */
function pos_delivery_choice_id($valg) {
	$valg = trim((string) $valg);
	if ($valg === '' || !ctype_digit($valg) || strlen($valg) > 10 || (int) $valg > 2147483647) {
		return 0;
	}
	return (int) $valg;
}

/**
 * Whether a posted choice attaches a delivery to the sale: an active choice of type afhentning or
 * levering. A taget med choice, the empty choice and anything that is not an active choice attach
 * nothing, so they never create a sale.
 *
 * @param string $valg The posted pos_leveringsvalg id.
 * @return bool
 */
function pos_delivery_choice_attaches($valg) {
	$valgId = pos_delivery_choice_id($valg);
	if (!pos_delivery_supported() || !$valgId) {
		return false;
	}
	try {
		$rows = shopify_db_rows("SELECT 1 FROM pos_leveringsvalg WHERE id = $1 AND aktiv AND type <> 'taget_med'", array($valgId));
	} catch (Throwable $e) {
		error_log('K33 delivery choice lookup failed: ' . $e->getMessage());
		return false;
	}
	return (bool) $rows;
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
 * A sale moved onto a table that already has an open sale: its lines join that sale, so its
 * delivery choice and wished date go along, unless the table's sale has a choice of its own.
 * Called before the emptied sale is reset.
 *
 * @param int $fromId The sale that is moved and then emptied.
 * @param int $toId   The table's sale that receives the lines.
 * @return void
 */
function pos_move_sale_delivery($fromId, $toId) {
	$fromId = (int) $fromId;
	$toId = (int) $toId;
	if ($fromId < 1 || $toId < 1 || $fromId === $toId || !pos_delivery_supported()) {
		return;
	}
	$qtxt = "update ordrer set leveringsvalg_id = s.leveringsvalg_id, leveringsmaade = s.leveringsmaade, oensket_levdato = s.oensket_levdato";
	$qtxt.= " from ordrer s where ordrer.id = '$toId' and s.id = '$fromId'";
	$qtxt.= " and ordrer.leveringsvalg_id is null and s.leveringsvalg_id is not null";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

/**
 * The delivery row in the sale header (debitor/func/pos_ordre_itemscan.php).
 *
 * An open sale gets the list of active choices and the wished date. Neither submits the sale form:
 * a change is saved at once in the background (saveDelivery.php), so a product button that leaves
 * the page by a link cannot lose it, and picking a delivery never confirms an item that is waiting
 * for its quantity. The fields are also posted with the sale form's next submit. The date field is
 * hidden and disabled while a taget med choice is selected. A posted sale shows its choice and date as text.
 * Nothing is printed until a delivery choice has been set up, unless the sale already has one.
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
	$utf8Page = (isset($GLOBALS['charset']) && $GLOBALS['charset'] === 'UTF-8');
	$label = pos_delivery_html(findtekst('pos.delivery.label|Levering', $sprog_id));
	$dateLabel = pos_delivery_html(findtekst('pos.delivery.date|Ønsket leveringsdato', $sprog_id));
	if ((int) $status >= 3) {
		if ($sale && $sale['valg_id'] && $sale['type'] !== 'taget_med') {
			print "<tr><td colspan='6'>$label: " . pos_delivery_html($sale['navn'], $utf8Page);
			if ($sale['dato']) {
				print " &nbsp; $dateLabel: " . dkdato($sale['dato']);
			}
			print "</td></tr>\n";
		}
		return;
	}
	$current = $sale ? $sale['valg_id'] : null;
	// A sale that already has a choice keeps its field after the last choice was deactivated, so
	// the cashier can still change its date or set it back to taget med.
	if (!$current && !pos_delivery_enabled()) {
		return;
	}
	$choices = pos_delivery_choices(true);
	$currentListed = false;
	foreach ($choices as $choice) {
		$currentListed = $currentListed || $choice['id'] === $current;
	}
	// A choice deactivated after it was picked stays on the sale until the cashier changes it.
	if ($current && !$currentListed) {
		array_unshift($choices, array('id' => $current, 'navn' => $sale['navn'], 'type' => $sale['type']));
	}
	$type = $sale ? $sale['type'] : 'taget_med';
	$types = pos_delivery_types($sprog_id);
	print "<tr><td colspan='6'><span class='pos-levering'>$label: ";
	print "<select class='inputbox' name='leveringsvalg_id' onchange='posLeveringGem(this)'>";
	// No stored choice is "taget med". Without a taget med choice set up, an empty option stands for
	// it; otherwise the first taget med choice is shown selected. Either way pos_save_sale_delivery()
	// stores a taget med pick as no choice, so nothing is attached until the cashier picks a delivery.
	$hasTakenAlong = false;
	foreach ($choices as $choice) {
		$hasTakenAlong = $hasTakenAlong || $choice['type'] === 'taget_med';
	}
	if (!$hasTakenAlong) {
		$selected = $current ? '' : ' selected';
		print "<option value='' data-type='taget_med'$selected>" . pos_delivery_html($types['taget_med'], $utf8Page) . "</option>";
	}
	$selectedOne = (bool) $current || !$hasTakenAlong;
	foreach ($choices as $choice) {
		$isSelected = $current ? $choice['id'] === $current : (!$selectedOne && $choice['type'] === 'taget_med');
		$selectedOne = $selectedOne || $isSelected;
		$selected = $isSelected ? ' selected' : '';
		// Every taget med choice posts the empty value: taget med is stored as no choice, and a row
		// changed to another type in the settings while this page is open cannot attach a delivery.
		$value = ($choice['type'] === 'taget_med') ? '' : (int) $choice['id'];
		print "<option value='$value' data-type='" . pos_delivery_html($choice['type']) . "'$selected>";
		print pos_delivery_html($choice['navn'], $utf8Page) . "</option>";
	}
	print "</select></span>";
	$value = ($sale && $sale['dato']) ? $sale['dato'] : '';
	// No min attribute: a sale reopened after its wished date has passed must still submit. The
	// server refuses a past date. The field is disabled while hidden, so it is not posted then.
	$hidden = ($type === 'taget_med') ? " style='display:none'" : '';
	$disabled = ($type === 'taget_med') ? ' disabled' : '';
	print "<span id='pos-levdato' class='pos-levdato'$hidden> &nbsp; $dateLabel: ";
	print "<input class='inputbox' type='date' name='oensket_levdato' value='$value'$disabled onchange='posLeveringGem(this)'></span>";
	print pos_sale_delivery_script((int) $orderId);
	print "</td></tr>\n";
}

/**
 * The script behind the delivery fields: shows or hides the date for the picked type, and saves
 * the change in the background through saveDelivery.php, one request after the other so the last
 * edit wins. An incomplete native date is not sent; an intentionally empty date clears the wish. A refusal is
 * shown to the cashier. A sale that has no row yet is saved with the sale form's next submit.
 *
 * @param int $orderId
 * @return string
 */
function pos_sale_delivery_script($orderId) {
	$orderId = (int) $orderId;
	// saveDelivery.php refuses a request without the session's token (perm_require_post_csrf()).
	if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	$token = preg_replace('/[^0-9a-f]/', '', $_SESSION['csrf_token']);
	return "<script>\n"
		. "function posLeveringGem(el) {\n"
		. "  var f = el.form, o = f.elements['leveringsvalg_id'], d = f.elements['oensket_levdato'];\n"
		. "  var taget = o.options[o.selectedIndex].getAttribute('data-type') === 'taget_med';\n"
		. "  var w = document.getElementById('pos-levdato');\n"
		. "  if (w) { w.style.display = taget ? 'none' : ''; }\n"
		. "  if (d) { d.disabled = taget; }\n"
		. "  if (!$orderId) { return; }\n"
		. "  if (el === d && d.validity.badInput) { return; }\n"
		. "  var b = new FormData();\n"
		. "  b.append('id', '$orderId');\n"
		. "  b.append('leveringsvalg_id', o.value);\n"
		. "  b.append('oensket_levdato', (d && !d.disabled) ? d.value : '');\n"
		. "  window.posLeveringKoe = (window.posLeveringKoe || Promise.resolve()).then(function () {\n"
		. "    return fetch('pos_ordre_includes/saveDelivery.php', { method: 'POST', body: b, credentials: 'same-origin', headers: { 'X-CSRF-Token': '$token' } })\n"
		. "      .then(function (r) { return r.json(); })\n"
		. "      .then(function (j) { if (j && j.error) { alert(j.error); } })\n"
		. "      .catch(function () {});\n"
		. "  });\n"
		. "}\n"
		. "</script>\n";
}

