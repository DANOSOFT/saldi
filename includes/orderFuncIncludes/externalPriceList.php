<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/orderFuncIncludes/externalPriceList.php --- ver 5.1.1 --- 2026-11-25 ---
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
//
// The active external price list (Indstillinger → Prislister, grupper art 'PL', box12 'Yes') for the item lookup
// on customer orders: read the CSV file, search it, and create a chosen row as an item.
// 20261125 CL/MMR SST-1051 New file. Brings the external price list back to the customer order item lookup.

/**
 * The active price list, or null when none is in use.
 *
 * @return array{id: int, name: string, url: string, delimiter: string, encoding: string, group: int, groupName: string}|null
 */
function externalPriceListActive(): ?array
{
	$qtxt = "select id, beskrivelse, box2, box8, box10, box11, kodenr from grupper where art = 'PL' and box12 = 'Yes' order by id limit 1";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$r || trim((string) $r['box2']) === '') {
		return null;
	}
	$delimiter = (string) $r['box10'];
	if ($delimiter === '\t' || $delimiter === 'tab') {
		$delimiter = "\t";
	} elseif ($delimiter === '') {
		$delimiter = ';';
	}
	return array(
		'id' => (int) $r['id'],
		'name' => trim((string) $r['beskrivelse']),
		'url' => trim((string) $r['box2']),
		'delimiter' => $delimiter,
		'encoding' => (string) $r['box11'],
		'group' => (int) $r['kodenr'],
		'groupName' => trim((string) $r['box8']),
	);
}

/**
 * Turn a price from the file into a number. Accepts 1234.50, 1234,50, 1.234,50 and 1,234.50.
 */
function externalPriceListNumber(string $value): float
{
	$value = str_replace(array(' ', "\xC2\xA0"), '', trim($value));
	$comma = strrpos($value, ',');
	$dot = strrpos($value, '.');
	if ($comma !== false && ($dot === false || $comma > $dot)) {
		$value = str_replace(array('.', ','), array('', '.'), $value);
	} else {
		$value = str_replace(',', '', $value);
	}
	return is_numeric($value) ? (float) $value : 0.0;
}

/**
 * Read the price file. The column names follow the sample file in Indstillinger → Prislister
 * (varenr, stregkode, varemærke, beskrivelse, kostpris, salgspris, enhed, ...), case-insensitive.
 *
 * @return array<int, array{varenr: string, stregkode: string, trademark: string, beskrivelse: string, kostpris: float, salgspris: float, enhed: string}>|null
 *         null when the file cannot be fetched or has no varenr column
 */
function externalPriceListRows(array $priceList): ?array
{
	static $cache = array();
	if (array_key_exists($priceList['id'], $cache)) {
		return $cache[$priceList['id']];
	}
	$cache[$priceList['id']] = null;
	if (!preg_match('#^https?://#i', $priceList['url'])) {
		return null;
	}
	$context = stream_context_create(array('http' => array('timeout' => 10), 'https' => array('timeout' => 10)));
	$data = @file_get_contents($priceList['url'], false, $context, 0, 20 * 1024 * 1024);
	if ($data === false || trim($data) === '') {
		return null;
	}
	if (strncmp($data, "\xEF\xBB\xBF", 3) === 0) {
		$data = substr($data, 3);
	}
	if ($priceList['encoding'] !== 'utf-8' || !mb_check_encoding($data, 'UTF-8')) {
		$data = mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1');
	}
	$lines = preg_split('/\r\n|\n|\r/', trim($data));
	$names = array(
		'varenr' => 'varenr', 'vare nr' => 'varenr', 'varenummer' => 'varenr',
		'stregkode' => 'stregkode', 'ean' => 'stregkode',
		'varemærke' => 'trademark', 'trademark' => 'trademark',
		'beskrivelse' => 'beskrivelse', 'text' => 'beskrivelse', 'tekst' => 'beskrivelse',
		'kostpris' => 'kostpris', 'salgspris' => 'salgspris', 'enhed' => 'enhed',
	);
	$columns = array();
	foreach (str_getcsv(array_shift($lines), $priceList['delimiter']) as $index => $header) {
		$header = mb_strtolower(trim($header));
		if (isset($names[$header]) && !in_array($names[$header], $columns, true)) {
			$columns[$index] = $names[$header];
		}
	}
	if (!in_array('varenr', $columns, true)) {
		return null;
	}
	$rows = array();
	foreach ($lines as $line) {
		if (trim($line) === '') {
			continue;
		}
		$row = array('varenr' => '', 'stregkode' => '', 'trademark' => '', 'beskrivelse' => '', 'kostpris' => 0.0, 'salgspris' => 0.0, 'enhed' => '');
		foreach (str_getcsv($line, $priceList['delimiter']) as $index => $value) {
			if (!isset($columns[$index])) {
				continue;
			}
			$key = $columns[$index];
			$row[$key] = ($key === 'kostpris' || $key === 'salgspris') ? externalPriceListNumber((string) $value) : trim((string) $value);
		}
		if ($row['varenr'] !== '') {
			$rows[] = $row;
		}
	}
	$cache[$priceList['id']] = $rows;
	return $rows;
}

/**
 * Rows whose item number and description contain the search terms (case-insensitive) and that are not items yet.
 * Empty terms match nothing, so the whole file is never listed. The limit counts only rows that are returned.
 *
 * @return array<int, array{varenr: string, stregkode: string, trademark: string, beskrivelse: string, kostpris: float, salgspris: float, enhed: string}>
 */
function externalPriceListSearch(array $rows, string $itemNo, string $description, int $limit = 50): array
{
	$itemNo = trim($itemNo, " *");
	$description = trim($description, " *");
	if ($itemNo === '' && $description === '') {
		return array();
	}
	$found = array();
	foreach ($rows as $row) {
		if ($itemNo !== '' && mb_stripos($row['varenr'], $itemNo) === false && mb_stripos($row['stregkode'], $itemNo) === false) {
			continue;
		}
		if ($description !== '' && mb_stripos($row['beskrivelse'], $description) === false) {
			continue;
		}
		if (externalPriceListItemId($row['varenr'])) {
			continue;
		}
		$found[] = $row;
		if (count($found) >= $limit) {
			break;
		}
	}
	return $found;
}

/**
 * The item group of the price list: its group number, or the group with the stored name (older settings page).
 *
 * @return int the group number, or 0 when the list has none
 */
function externalPriceListGroup(array $priceList): int
{
	if ($priceList['group']) {
		return $priceList['group'];
	}
	if ($priceList['groupName'] === '') {
		return 0;
	}
	$qtxt = "select kodenr from grupper where art = 'VG' and beskrivelse = '" . db_escape_string($priceList['groupName']) . "' order by fiscal_year desc limit 1";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	return $r ? (int) $r['kodenr'] : 0;
}

/**
 * The id of the item with this item number, or 0.
 */
function externalPriceListItemId(string $itemNo): int
{
	$qtxt = "select id from varer where varenr = '" . db_escape_string($itemNo) . "' order by id limit 1";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	return $r ? (int) $r['id'] : 0;
}

/**
 * Create the price-list row as an item in the price list's item group. An item with the same number is reused.
 *
 * @return int the item id, or 0 when the price list has no item group
 */
function externalPriceListCreateItem(array $row, array $priceList): int
{
	$itemId = externalPriceListItemId($row['varenr']);
	if ($itemId) {
		return $itemId;
	}
	$group = externalPriceListGroup($priceList);
	if (!$group) {
		return 0;
	}
	$qtxt = "insert into varer (varenr, stregkode, trademark, beskrivelse, kostpris, salgspris, enhed, gruppe, lukket) values (";
	$qtxt .= "'" . db_escape_string($row['varenr']) . "', '" . db_escape_string($row['stregkode']) . "', ";
	$qtxt .= "'" . db_escape_string($row['trademark']) . "', '" . db_escape_string($row['beskrivelse']) . "', ";
	$qtxt .= (float) $row['kostpris'] . ", " . (float) $row['salgspris'] . ", '" . db_escape_string($row['enhed']) . "', $group, '0')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	return externalPriceListItemId($row['varenr']);
}
