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
// 20261125 CL/MMR SST-1051 Search skips rows that are already items before the limit.
//                 The item group comes from the stored group name when it names one group, else the group number.
// 20261125 CL/MMR SST-1051 The file is read as whole CSV records, so a quoted field may span several lines.
// 20261125 CL/MMR SST-1051 CR-only line ends and blank lines before the header are accepted.
// 20261125 CL/MMR SST-1051 A file larger than 20 MB is refused instead of being cut inside a record.
// 20261125 CL/MMR SST-1051 Records are visited one by one instead of kept, so a file with many short lines fits in memory.
// 20261125 CL/MMR SST-1051 The file is downloaded with curl, and a broken-off transfer or HTTP error gives no rows.
// 20261125 CL/MMR SST-1051 Only HTTP 200 is accepted as a whole file.
// 20261125 CL/MMR SST-1051 Rows with a missing or non-numeric price are skipped.
//                 Space-only lines before the header are dropped.
//                 Gzip and deflate responses are decoded.
// 20261125 CL/MMR SST-1051 Prices must be in one of the four documented formats.
//                 A last record with a missing closing quote is refused, also when spaces come before the quote.
// 20261125 CL/MMR SST-1051 Prices are rounded on their digits to 3 decimals and written to the item as exact text.
// 20261125 CDX/NTR Reject CSV headers without both cost and sale prices instead of supplying zero.

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
 * Turn a price from the file into an exact decimal text with 3 decimals, rounded half away from zero like
 * PostgreSQL's numeric(15,3). Accepts 1234.50, 1234,50, 1.234,50 and 1,234.50 (also negative and without decimals);
 * anything else, e.g. «799 kr», «1e309» or «1,2,3.40», is not a price. The rounding is done on the digits, not on a
 * float, so 1.23449999999999999999 stays 1.234.
 *
 * @return string|null e.g. "1234.500", or null when the value is empty, not a price or out of the numeric(15,3) range
 */
function externalPriceListNumber(string $value): ?string
{
	$value = str_replace(array(' ', "\xC2\xA0"), '', trim($value));
	if (preg_match('/^-?\d+(?:[.,]\d+)?$/', $value)) {
		$value = str_replace(',', '.', $value);
	} elseif (preg_match('/^-?\d{1,3}(?:\.\d{3})+(?:,\d+)?$/', $value)) {
		$value = str_replace(array('.', ','), array('', '.'), $value);
	} elseif (preg_match('/^-?\d{1,3}(?:,\d{3})+(?:\.\d+)?$/', $value)) {
		$value = str_replace(',', '', $value);
	} else {
		return null;
	}
	$negative = ($value[0] === '-');
	$parts = explode('.', ltrim($value, '-') . '.');
	$whole = ltrim($parts[0], '0');
	$fraction = str_pad($parts[1], 4, '0');
	// All digits as one integer string in thousandths, then round on the fourth decimal.
	$digits = ($whole === '' ? '0' : $whole) . substr($fraction, 0, 3);
	if ($fraction[3] >= '5') {
		$i = strlen($digits) - 1;
		while ($i >= 0 && $digits[$i] === '9') {
			$digits[$i] = '0';
			$i--;
		}
		$digits = ($i < 0) ? '1' . $digits : substr_replace($digits, (string) ((int) $digits[$i] + 1), $i, 1);
	}
	$digits = str_pad(ltrim($digits, '0'), 4, '0', STR_PAD_LEFT);
	$result = substr($digits, 0, -3) . '.' . substr($digits, -3);
	if (strlen($digits) > 15) {
		return null; // more than 12 digits before the decimal point
	}
	return ($negative && $digits !== '0000') ? '-' . $result : $result;
}

/**
 * True when the raw text of a CSV record ends inside a quoted field, i.e. the closing quote is missing.
 * fgetcsv() silently accepts such a field when it runs to the end of the file.
 */
function externalPriceListQuoteOpen(string $raw, string $delimiter): bool
{
	$inQuote = false;
	$fieldStart = true;
	$length = strlen($raw);
	for ($i = 0; $i < $length; $i++) {
		$char = $raw[$i];
		if ($inQuote) {
			if ($char === '"') {
				if ($i + 1 < $length && $raw[$i + 1] === '"') {
					$i++;
				} else {
					$inQuote = false;
				}
			}
		} elseif ($fieldStart && $char === '"') {
			$inQuote = true;
			$fieldStart = false;
		} elseif ($fieldStart && ($char === ' ' || $char === "\t") && $char !== $delimiter) {
			// fgetcsv() lets spaces and TABs come before the opening quote of a field.
			continue;
		} else {
			$fieldStart = ($char === $delimiter || $char === "\n");
		}
	}
	return $inQuote;
}

/**
 * Download the price file completely or not at all. A transfer that breaks off (curl reports it, also when fewer
 * bytes arrive than the server announced), an HTTP error status and a file over the limit all give null, so a row
 * cut in the middle is never read as a row with missing prices. Any status other than 200 is refused.
 *
 * @return string|null the whole file, or null
 */
function externalPriceListDownload(string $url, int $maxBytes): ?string
{
	if (!function_exists('curl_init')) {
		return null;
	}
	$data = '';
	$tooBig = false;
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 3,
		CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
		CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_ENCODING => '', // accept gzip/deflate and get the decoded file; the size limit counts decoded bytes
		CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use (&$data, &$tooBig, $maxBytes): int {
			if (strlen($data) + strlen($chunk) > $maxBytes) {
				$tooBig = true;
				return 0; // stops the transfer
			}
			$data .= $chunk;
			return strlen($chunk);
		},
	));
	$ok = curl_exec($ch);
	$error = curl_errno($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	curl_close($ch);
	// Only 200 is a whole file: this request asks for no range, so e.g. 206 Partial Content is refused too.
	if ($ok === false || $error || $tooBig || $status !== 200) {
		return null;
	}
	return $data;
}

/**
 * Read the price file record by record and hand each row to $visit; when $visit returns false the reading stops.
 * Rows are not kept, so a file with many short lines does not fill the memory. The column names follow the sample
 * file in Indstillinger → Prislister (varenr, stregkode, varemærke, beskrivelse, kostpris, salgspris, enhed, ...),
 * case-insensitive.
 *
 * @param callable(array{varenr: string, stregkode: string, trademark: string, beskrivelse: string, kostpris: string, salgspris: string, enhed: string}): bool $visit
 * @return bool false when the file cannot be fetched, is larger than 20 MB or lacks varenr, kostpris or salgspris
 */
function externalPriceListEach(array $priceList, callable $visit): bool
{
	if (!preg_match('#^https?://#i', $priceList['url'])) {
		return false;
	}
	$data = externalPriceListDownload($priceList['url'], 20 * 1024 * 1024);
	if ($data === null || trim($data) === '') {
		return false;
	}
	if (strncmp($data, "\xEF\xBB\xBF", 3) === 0) {
		$data = substr($data, 3);
	}
	if ($priceList['encoding'] !== 'utf-8' || !mb_check_encoding($data, 'UTF-8')) {
		$data = mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1');
	}
	// Read whole CSV records, so a quoted field may span several lines. CRLF and CR-only line ends become LF first,
	// and blank or space-only lines before the header are dropped.
	$data = preg_replace('/\A(?:[ \t]*\n)+/', '', str_replace(array("\r\n", "\r"), "\n", $data));
	$stream = fopen('php://temp', 'r+');
	fwrite($stream, $data);
	unset($data);
	rewind($stream);
	$names = array(
		'varenr' => 'varenr', 'vare nr' => 'varenr', 'varenummer' => 'varenr',
		'stregkode' => 'stregkode', 'ean' => 'stregkode',
		'varemærke' => 'trademark', 'trademark' => 'trademark',
		'beskrivelse' => 'beskrivelse', 'text' => 'beskrivelse', 'tekst' => 'beskrivelse',
		'kostpris' => 'kostpris', 'salgspris' => 'salgspris', 'enhed' => 'enhed',
	);
	$columns = array();
	$header = fgetcsv($stream, 0, $priceList['delimiter'], '"', '');
	foreach ((array) $header as $index => $name) {
		$name = mb_strtolower(trim((string) $name));
		if (isset($names[$name]) && !in_array($names[$name], $columns, true)) {
			$columns[$index] = $names[$name];
		}
	}
	if (array_diff(array('varenr', 'kostpris', 'salgspris'), $columns)) {
		fclose($stream);
		return false;
	}
	while (true) {
		$recordStart = ftell($stream);
		$record = fgetcsv($stream, 0, $priceList['delimiter'], '"', '');
		if ($record === false) {
			break;
		}
		// Only a record that runs to the end of the file can have an open quote; such a record is refused.
		if (feof($stream)) {
			fseek($stream, $recordStart);
			$raw = (string) stream_get_contents($stream);
			if (externalPriceListQuoteOpen($raw, $priceList['delimiter'])) {
				break;
			}
		}
		if (trim(implode('', array_map('strval', $record))) === '') {
			continue;
		}
		$row = array('varenr' => '', 'stregkode' => '', 'trademark' => '', 'beskrivelse' => '', 'kostpris' => '0.000', 'salgspris' => '0.000', 'enhed' => '');
		$valid = true;
		foreach ($columns as $index => $key) {
			if ($key === 'kostpris' || $key === 'salgspris') {
				// A price column that is missing in the record, empty or not a number makes the row unusable, so it is
				// never offered or created with price 0. An explicit 0 is a price.
				$number = isset($record[$index]) ? externalPriceListNumber((string) $record[$index]) : null;
				if ($number === null) {
					$valid = false;
					break;
				}
				$row[$key] = $number;
			} else {
				$row[$key] = trim((string) ($record[$index] ?? ''));
			}
		}
		if (!$valid) {
			continue;
		}
		// A line break inside a quoted field becomes a space: item numbers and descriptions are one line in Saldi.
		$row['varenr'] = preg_replace('/\s*\R\s*/u', ' ', $row['varenr']);
		$row['beskrivelse'] = preg_replace('/\s*\R\s*/u', ' ', $row['beskrivelse']);
		if ($row['varenr'] !== '' && $visit($row) === false) {
			break;
		}
	}
	fclose($stream);
	return true;
}

/**
 * Rows whose item number and description contain the search terms (case-insensitive) and that are not items yet.
 * Empty terms match nothing, so the whole file is never listed. The limit counts only rows that are returned.
 *
 * @return array<int, array{varenr: string, stregkode: string, trademark: string, beskrivelse: string, kostpris: string, salgspris: string, enhed: string}>|null
 *         null when the file cannot be used
 */
function externalPriceListSearch(array $priceList, string $itemNo, string $description, int $limit = 50): ?array
{
	$itemNo = trim($itemNo, " *");
	$description = trim($description, " *");
	if ($itemNo === '' && $description === '') {
		return array();
	}
	$found = array();
	$usable = externalPriceListEach($priceList, function (array $row) use ($itemNo, $description, $limit, &$found): bool {
		if ($itemNo !== '' && mb_stripos($row['varenr'], $itemNo) === false && mb_stripos($row['stregkode'], $itemNo) === false) {
			return true;
		}
		if ($description !== '' && mb_stripos($row['beskrivelse'], $description) === false) {
			return true;
		}
		if (externalPriceListItemId($row['varenr'])) {
			return true;
		}
		$found[] = $row;
		return count($found) < $limit;
	});
	return $usable ? $found : null;
}

/**
 * The row with exactly this item number, or null when the file has none or cannot be used.
 *
 * @return array{varenr: string, stregkode: string, trademark: string, beskrivelse: string, kostpris: string, salgspris: string, enhed: string}|null
 */
function externalPriceListFind(array $priceList, string $itemNo): ?array
{
	$match = null;
	externalPriceListEach($priceList, function (array $row) use ($itemNo, &$match): bool {
		if ($row['varenr'] === $itemNo) {
			$match = $row;
			return false;
		}
		return true;
	});
	return $match;
}

/**
 * The item group of the price list in the current fiscal year. The stored name wins when it names exactly one item
 * group, because the older settings page could store a group number that did not match the name the user chose.
 * Otherwise the stored number is used when it is an item group.
 *
 * @return int the group number, or 0 when no item group can be found
 */
function externalPriceListGroup(array $priceList): int
{
	global $regnaar;
	$year = (int) $regnaar;
	if ($priceList['groupName'] !== '') {
		$qtxt = "select distinct kodenr from grupper where art = 'VG' and fiscal_year = $year and beskrivelse = '" . db_escape_string($priceList['groupName']) . "'";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		$byName = array();
		while ($r = db_fetch_array($q)) {
			$byName[] = (int) $r['kodenr'];
		}
		if (count($byName) === 1) {
			return $byName[0];
		}
	}
	if ($priceList['group']) {
		$qtxt = "select kodenr from grupper where art = 'VG' and fiscal_year = $year and kodenr = '" . (int) $priceList['group'] . "' limit 1";
		if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			return $priceList['group'];
		}
	}
	return 0;
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
	// The prices are exact decimal texts from externalPriceListNumber() (digits, an optional minus and a point).
	$qtxt .= "'" . db_escape_string($row['kostpris']) . "', '" . db_escape_string($row['salgspris']) . "', ";
	$qtxt .= "'" . db_escape_string($row['enhed']) . "', $group, '0')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	return externalPriceListItemId($row['varenr']);
}
