<?php
// ---- includes/settings/rows.php --- lap 5.0.0 --- 2026.10.05 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20261005 Sawaneh G6.2: 'transient' columns (posted, given to the hooks, never stored - the template of a new background)
//                  and 'create_only' columns (editable on a new row, read-only afterwards).
// 20261006 Sawaneh §8.13 empty-state seeding: table option 'standard' ('grupper' = the groups file a new ledger is made
//                  from, or fixed rows); settings_rows_standard() gives the rows the editor inserts as new, unsaved rows.
// 20261005 Sawaneh 4d fiscal years and currencies: date cells, read-only columns and locked rows, a parent filter
//                  (rates of one currency), automatic numbering, 'forbid', per-row actions, hooks before a row is
//                  written and on delete, and a confirmation step for saves that post amounts (spec G2.3, V9).
// 20261005 Sawaneh 4c VAT and groups: column range, 'requires', 'locked_if_used', 'empty_value', table 'defaults', VAT/group usage;
//                  unchanged cells are not validated again, decimals compare as numbers.
// 20261005 Sawaneh Settings redesign phase 4c (spec §7.3 / §8.2): the row editor behind a section of 'kind' rows.
//                  A table is a list of rows in grupper (one art) or in a table of its own; every cell is a column
//                  definition. Reading, usage counts, validation, saving with one audit row per changed cell,
//                  delete with usage check, the inaktiv flag and copying a fiscal year live here; the page is drawn
//                  by rowsView.php and behaves through javascript/settingsRows.js.
// 20261008 Sawaneh Table option 'hide_empty'; discount group usage counts only the matrix's 'NR' rows (settings decision 20).
// 20261008 Sawaneh Table option 'confirm_skip' (button text, explanation) for the confirm box. Warehouse usage follows
//                  master's warehouseDeletion.php (history, order lines, departments; duplicates may go).

/**
 * The tables of a rows section with their defaults filled in.
 *
 * @return array<string, array<string, mixed>>
 */
function settings_rows_tables(array $section): array
{
	$out = array();
	foreach ($section['tables'] as $tableId => $t) {
		$t += array('sub' => $tableId, 'fiscal' => false, 'usage' => null, 'inactive' => false, 'exclude' => '', 'help' => null,
			'propagate' => array(), 'kode' => null, 'on_save' => null, 'row_name' => null, 'defaults' => array(),
			'no_add' => false, 'filter' => null, 'auto_code' => false, 'row_actions' => array(), 'confirm' => null, 'row_check' => null,
			'before_row' => null, 'on_delete' => null, 'order' => null, 'row_locked' => null, 'create' => null, 'standard' => null, 'hide_empty' => false, 'confirm_skip' => null);
		$st = $t['storage'];
		$t['kind'] = $st[0] === 'grupper' ? 'grupper' : 'table';
		$t['art'] = $t['kind'] === 'grupper' ? (string) $st[1] : '';
		$t['dbtable'] = $t['kind'] === 'grupper' ? 'grupper' : (string) $st[1];
		$t['code_col'] = isset($t['code_col']) ? (string) $t['code_col'] : (isset($t['columns']['kodenr']) ? 'kodenr' : key($t['columns']));
		foreach ($t['columns'] as $col => $def) {
			$t['columns'][$col] = $def + array('type' => 'text', 'required' => false, 'unique' => (isset($def['type']) && $def['type'] === 'code'), 'width' => '', 'numeric' => true, 'help' => null, 'options' => null, 'options_from' => null, 'derive' => null,
				'range' => null, 'requires' => null, 'locked_if_used' => false, 'empty_value' => null, 'readonly' => false, 'forbid' => null,
				'kontotype' => null, 'true_value' => 'on', 'false_value' => '', 'transient' => false, 'create_only' => false);
		}
		$out[$tableId] = $t;
	}
	return $out;
}

/**
 * The fiscal years a per-year table can be edited for, newest first.
 *
 * @return array<int, int>
 */
/**
 * The Danish standard set for an empty table (§8.13 "Opret dansk standardsæt"): for a grupper table the rows of its
 * art from the groups file a new ledger is made from (importfiler/egne_grupper.txt, else grupper.txt, as in
 * admin/opret.php); otherwise the table's own 'standard' rows. Only the table's columns are returned; the rows go into
 * the editor as new rows the user edits and saves.
 *
 * @return array<int, array<string, string>>
 */
function settings_rows_standard(array $t): array
{
	if (is_array($t['standard'])) {
		$rows = $t['standard'];
	} elseif ($t['standard'] === 'grupper' && $t['kind'] === 'grupper') {
		$dir = __DIR__ . '/../../importfiler/';
		$file = is_file($dir . 'egne_grupper.txt') ? $dir . 'egne_grupper.txt' : $dir . 'grupper.txt';
		$keys = array('beskrivelse', 'kode', 'kodenr', 'art', 'box1', 'box2', 'box3', 'box4', 'box5', 'box6', 'box7', 'box8', 'box9', 'box10', 'box11', 'box12', 'box13', 'box14');
		$rows = array();
		foreach (is_file($file) ? file($file, FILE_IGNORE_NEW_LINES) : array() as $line) {
			if (trim($line) === '' || $line[0] === '#') {
				continue;
			}
			$vals = array_map('trim', str_getcsv(trim($line), ',', "'"));
			$row = array_combine($keys, array_pad(array_slice($vals, 0, count($keys)), count($keys), ''));
			if ($row['art'] === $t['art']) {
				$rows[] = $row;
			}
		}
	} else {
		return array();
	}
	$out = array();
	foreach ($rows as $row) {
		$r = array();
		foreach (array_keys($t['columns']) as $col) {
			if (isset($row[$col])) {
				$r[$col] = (string) $row[$col];
			}
		}
		if ($r) {
			$out[] = $r;
		}
	}
	return $out;
}

function settings_rows_years(): array
{
	static $years = null;
	if ($years === null) {
		$years = array();
		$q = db_select("select distinct regnskabsaar from kontoplan where regnskabsaar is not null order by regnskabsaar desc", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$years[] = (int) $r['regnskabsaar'];
		}
		global $regnaar;
		if (!$years) {
			$years[] = (int) $regnaar;
		}
	}
	return $years;
}

function settings_rows_where(array $t, ?int $year): string
{
	$w = $t['kind'] === 'grupper' ? "art = '" . db_escape_string($t['art']) . "'" : '1 = 1';
	if ($t['fiscal']) {
		$w .= " and fiscal_year = " . (int) $year;
	}
	if ($t['exclude'] !== '') {
		$w .= " and (" . $t['exclude'] . ")";
	}
	if (is_array($t['filter'])) {
		$w .= " and " . $t['filter']['column'] . " = " . (isset($t['filter']['value']) ? (int) $t['filter']['value'] : 0);
	}
	return $w;
}

/**
 * The rows of a table: id => array(id, cells (column => stored string), inactive, usage, usage_text).
 *
 * @return array<int, array<string, mixed>>
 */
function settings_rows_load(array $t, ?int $year): array
{
	$rows = array();
	$order = $t['order'] !== null ? (string) $t['order'] : (($t['code_col'] === 'kodenr') ? "length(cast(kodenr as text)), cast(kodenr as text), id" : 'id');
	$q = db_select("select * from " . $t['dbtable'] . " where " . settings_rows_where($t, $year) . " order by " . $order, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$row = array('id' => (int) $r['id'], 'cells' => array(), 'raw' => $r, 'inactive' => isset($r['inaktiv']) && ($r['inaktiv'] === 't' || $r['inaktiv'] === true || $r['inaktiv'] === '1'));
		foreach ($t['columns'] as $col => $def) {
			$row['cells'][$col] = ($def['type'] === 'derived' || $def['transient']) ? '' : (isset($r[$col]) ? (string) $r[$col] : '');
		}
		$usage = settings_rows_usage($t, $row);
		$row['usage'] = $usage['count'];
		$row['usage_text'] = $usage['text'];
		$row['locked'] = $t['row_locked'] !== null && function_exists('settings_rows_hook_locked') && settings_rows_hook_locked((string) $t['row_locked'], $row);
		$rows[$row['id']] = $row;
	}
	return $rows;
}

/**
 * Where a row is referenced, as a count and a short text ("14 ordrer · 3 ansatte"), for the delete dialog (spec §8.2).
 *
 * @return array{count: int, text: string}
 */
function settings_rows_usage(array $t, array $row): array
{
	$parts = array();
	$total = 0;
	$code = isset($row['cells'][$t['code_col']]) ? trim((string) $row['cells'][$t['code_col']]) : '';
	$esc = db_escape_string($code);
	$isInt = ($code !== '' && ctype_digit($code));
	$add = function (string $sql, int $textId) use (&$parts, &$total) {
		$r = db_fetch_array(db_select($sql, __FILE__ . " linje " . __LINE__));
		$n = $r ? (int) $r['n'] : 0;
		if ($n > 0) {
			$parts[] = sprintf(st_txt($textId), number_format($n, 0, ',', '.'));
			$total += $n;
		}
	};
	if ($t['usage'] === 'fiscal_year' && function_exists('settings_fiscal_year_usage')) {
		foreach (settings_fiscal_year_usage($row) as $u) {
			if ($u[0] > 0) {
				$parts[] = $u[1];
				$total += $u[0];
			}
		}
	}
	if ($code !== '') {
		switch ($t['usage']) {
			case 'department':
				if ($isInt) {
					$add("select count(*) as n from ordrer where afd = $code", 6422);
					$add("select count(*) as n from transaktioner where afd = $code", 6423);
					$add("select count(*) as n from ansatte where afd = $code", 6424);
				}
				$add("select count(*) as n from grupper where art = 'LG' and box1 = '$esc'", 6428);
				$r = db_fetch_array(db_select("select box3 from grupper where art = 'POS' and kodenr = '1' order by id desc limit 1", __FILE__ . " linje " . __LINE__));
				if ($r && trim((string) $r['box3']) !== '') {
					$n = 0;
					foreach (explode("\t", (string) $r['box3']) as $till) {
						if (trim($till) === $code) {
							$n++;
						}
					}
					if ($n > 0) {
						$parts[] = sprintf(st_txt(6425), $n);
						$total += $n;
					}
				}
				break;
			case 'warehouse':
				// As master's warehouseDeletion.php: a duplicate definition of the number may go; otherwise any stock
				// history, orders, order lines, stock or a department using it keeps the warehouse.
				if ($isInt && !db_fetch_array(db_select("select id from grupper where art = 'LG' and kodenr = '$code' and id <> " . (int) $row['id'] . " limit 1", __FILE__ . " linje " . __LINE__))) {
					$add("select count(*) as n from lagerstatus where lager = $code and beholdning <> 0", 6426);
					$add("select count(*) as n from ordrer where lager = $code", 6422);
					$add("select count(*) as n from ordrelinjer where lager = $code", 6911);
					$add("select (select count(*) from batch_kob where lager = $code) + (select count(*) from batch_salg where lager = $code) + (select count(*) from regulering where lager = $code) as n", 6909);
					$add("select count(*) as n from grupper where art = 'AFD' and box1 = '$code'", 6910);
				}
				break;
			case 'project':
				$add("select count(*) as n from ordrer where projekt = '$esc'", 6422);
				$add("select count(*) as n from transaktioner where projekt = '$esc'", 6423);
				break;
			case 'unit':
				$add("select count(*) as n from varer where enhed = '$esc' or enhed2 = '$esc'", 6427);
				break;
			case 'vat':
				// A VAT code is referenced as kode + number ("S1") by accounts (any year) and by groups of the same year.
				$ref = db_escape_string((string) $t['kode'] . $code);
				$y = isset($GLOBALS['settings_rows_year']) ? (int) $GLOBALS['settings_rows_year'] : 0;
				$add("select count(*) as n from kontoplan where moms = '$ref'", 6463);
				$add("select count(*) as n from grupper where art in ('DG', 'KG') and (box1 = '$ref' or (art = 'KG' and box6 = '$ref'))" . ($y ? " and fiscal_year = $y" : ''), 6464);
				break;
			case 'debtor_group':
				$add("select count(*) as n from adresser where art = 'D' and cast(gruppe as text) = '$esc'", 6465);
				break;
			case 'creditor_group':
				$add("select count(*) as n from adresser where art = 'K' and cast(gruppe as text) = '$esc'", 6466);
				break;
			case 'item_group':
				$add("select count(*) as n from varer where cast(gruppe as text) = '$esc'", 6427);
				break;
			case 'background':
				// G6.2: customers, suppliers and orders name their background (form language) by its name.
				$add("select count(*) as n from adresser where sprog = '$esc'", 6682);
				$add("select count(*) as n from ordrer where sprog = '$esc'", 6422);
				break;
			case 'currency':
				$k = isset($row['raw']['kodenr']) ? (int) $row['raw']['kodenr'] : 0;
				$add("select count(*) as n from kontoplan where valuta = $k", 6601);
				$add("select count(*) as n from transaktioner where cast(valuta as text) = '$k'", 6423);
				$add("select count(*) as n from kassekladde where valuta = $k", 6566);
				$add("select count(*) as n from ordrer where valuta = '$esc'", 6422);
				$add("select count(*) as n from openpost where valuta = '$esc'", 6568);
				$add("select count(*) as n from valuta where gruppe = $k", 6602);
				break;
			case 'currency_rate':
				$g = isset($row['raw']['gruppe']) ? (int) $row['raw']['gruppe'] : 0;
				$add("select count(*) as n from transaktioner where cast(valuta as text) = '$g' and transdate >= '$esc'", 6423);
				break;
			case 'discount_debtor_group':
				if ($isInt) {
					$add("select count(*) as n from adresser where art = 'D' and rabatgruppe = $code", 6465);
					$add("select count(*) as n from rabat where debitorart = 'NR' and vareart = 'NR' and cast(debitor as text) = '$code'", 6877);
				}
				break;
			case 'discount_item_group':
				if ($isInt) {
					$add("select count(*) as n from varer where dvrg = $code", 6427);
					$add("select count(*) as n from rabat where debitorart = 'NR' and vareart = 'NR' and cast(vare as text) = '$code'", 6877);
				}
				break;
			case 'price_group':
				if (function_exists('settings_group_item_count')) {
					$n = settings_group_item_count($row['raw']);
					if ($n > 0) {
						$parts[] = sprintf(st_txt(6427), number_format($n, 0, ',', '.'));
						$total += $n;
					}
				}
				break;
			case 'variant_type':
			case 'variant_value':
				if (function_exists('settings_variant_item_count')) {
					$n = settings_variant_item_count($t['usage'] === 'variant_value' ? array((int) $row['id']) : settings_variant_value_ids((int) $row['id']));
					if ($n > 0) {
						$parts[] = sprintf(st_txt(6841), number_format($n, 0, ',', '.'));
						$total += $n;
					}
				}
				break;
		}
	}
	return array('count' => $total, 'text' => implode(' · ', $parts));
}

/**
 * A read-only cell computed from other rows.
 */
function settings_rows_derived(string $name, array $row): string
{
	if (function_exists('settings_rows_derived_extra') && ($v = settings_rows_derived_extra($name, $row)) !== null) {
		return $v;
	}
	if ($name === 'department_warehouse') {
		$code = db_escape_string(trim((string) $row['cells']['kodenr']));
		if ($code === '') {
			return '';
		}
		$out = array();
		$q = db_select("select kodenr, beskrivelse from grupper where art = 'LG' and box1 = '$code' order by length(cast(kodenr as text)), cast(kodenr as text)", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$out[] = trim((string) $r['kodenr']) . ' ' . trim((string) $r['beskrivelse']);
		}
		return implode(', ', $out);
	}
	return '';
}

/**
 * The stored string shown in a cell.
 */
function settings_rows_form_value(array $def, string $raw): string
{
	if ($def['type'] === 'decimal') {
		return $raw === '' ? '' : str_replace('.', ',', rtrim(rtrim(number_format((float) $raw, 4, '.', ''), '0'), '.'));
	}
	if ($def['type'] === 'bool') {
		return (trim($raw) !== '' && trim($raw) !== (string) $def['false_value']) ? '1' : '';
	}
	if ($def['type'] === 'date' && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
		return $m[3] . '-' . $m[2] . '-' . $m[1];
	}
	return $raw;
}

/**
 * A posted cell as the string to store, or an error text id.
 *
 * @return array{raw: string, error: int|null}
 */
function settings_rows_to_raw(array $def, string $value, ?int $year): array
{
	$value = trim($value);
	$error = null;
	$raw = $value;
	switch ($def['type']) {
		case 'bool':
			$raw = ($value === '1' || $value === 'on') ? (string) $def['true_value'] : (string) $def['false_value'];
			break;
		case 'date':
			if ($value !== '') {
				$y = $mo = $d = 0;
				if (preg_match('/^(\d{1,2})[-.\/](\d{1,2})[-.\/](\d{2}|\d{4})$/', $value, $m)) {
					$y = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
					$mo = (int) $m[2];
					$d = (int) $m[1];
				} elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
					$y = (int) $m[1];
					$mo = (int) $m[2];
					$d = (int) $m[3];
				}
				if ($y && checkdate($mo, $d, $y)) {
					$raw = sprintf('%04d-%02d-%02d', $y, $mo, $d);
				} else {
					$error = 6592;
				}
			}
			break;
		case 'code':
			if ($value !== '' && $def['numeric'] && !preg_match('/^[0-9]+$/', $value)) {
				$error = 6431;
			} elseif ($value !== '' && is_array($def['range']) && ((int) $value < $def['range'][0] || (int) $value > $def['range'][1])) {
				$error = isset($def['range'][2]) ? (int) $def['range'][2] : 6431;
			}
			break;
		case 'decimal':
			if ($value !== '') {
				$n = str_replace(array(' ', '.'), '', $value);
				$n = str_replace(',', '.', $n);
				if (!is_numeric($n)) {
					$error = 5732;
				} else {
					$raw = (string) (float) $n;
				}
			}
			break;
		case 'account':
			if ($value !== '') {
				if (!preg_match('/^[0-9]+$/', $value)) {
					$error = 5734;
				} else {
					global $regnaar;
					$y = $year !== null ? $year : (int) $regnaar;
					$r = db_fetch_array(db_select("select kontotype from kontoplan where kontonr = '" . db_escape_string($value) . "' and regnskabsaar = $y", __FILE__ . " linje " . __LINE__));
					if (!$r) {
						$error = 5734;
					} elseif ($def['kontotype'] !== null && trim((string) $r['kontotype']) !== (string) $def['kontotype']) {
						$error = 6594;
					}
				}
			}
			break;
		case 'select':
			$options = st_options($def);
			if ($value !== '' && !array_key_exists($value, $options)) {
				$error = 5719;
			}
			break;
	}
	if ($raw === '' && $def['empty_value'] !== null) {
		$raw = (string) $def['empty_value'];
	}
	if ($raw !== '' && is_array($def['forbid']) && function_exists('settings_rows_forbidden') && settings_rows_forbidden((string) $def['forbid'][0], $raw)) {
		$error = (int) $def['forbid'][1];
	}
	if ($error === null && $raw === '' && $def['required']) {
		$error = 6419;
	}
	return array('raw' => $raw, 'error' => $error);
}

/**
 * Whether a posted cell equals the stored value (decimals compared as numbers: "25" and "25.00" are the same).
 */
function settings_rows_same(array $def, string $stored, string $raw): bool
{
	if ($def['type'] === 'decimal' && is_numeric($stored) && is_numeric($raw)) {
		return abs((float) $stored - (float) $raw) < 0.000001;
	}
	if ($def['type'] === 'bool') {
		$on = function ($v) use ($def) {
			return trim((string) $v) !== '' && trim((string) $v) !== (string) $def['false_value'];
		};
		return $on($stored) === $on($raw);
	}
	if ($def['type'] === 'date') {
		return substr(trim($stored), 0, 10) === $raw;
	}
	return trim($stored) === $raw;
}

/**
 * Validate the posted rows of every table and, unless $dryRun, write the changes: new rows inserted, changed cells
 * updated, each with its audit row (spec §8.12). Nothing is written while any cell is wrong.
 *
 * @param array<string, array<string, mixed>> $tables
 * A dry run also returns 'confirm': lines describing amounts a table's 'confirm' hook would post (spec G2.3, V9).
 *
 * @return array{errors: array<string, int>, posted: array<string, array<string, array<string, string>>>, flash: array<int, array<int, string>>, confirm: array<int, string>}
 */
function settings_rows_save(string $sectionId, array $tables, array $post, ?int $year, bool $dryRun): array
{
	$errors = array();
	$flash = array();
	$posted = (isset($post['r']) && is_array($post['r'])) ? $post['r'] : array();
	$plan = array();
	foreach ($tables as $tableId => $t) {
		if (empty($posted[$tableId]) || !is_array($posted[$tableId])) {
			continue;
		}
		$existing = settings_rows_load($t, $year);
		$codeCol = $t['code_col'];
		$codes = array();
		foreach ($existing as $id => $row) {
			$codes[mb_strtolower(trim((string) $row['cells'][$codeCol]))] = $id;
		}
		$seen = array();
		foreach ($posted[$tableId] as $rowId => $cells) {
			if (!is_array($cells)) {
				continue;
			}
			$rowId = (string) $rowId;
			$isNew = (strpos($rowId, 'n') === 0);
			if ((!$isNew && !isset($existing[(int) $rowId])) || ($isNew && $t['no_add']) || (!$isNew && $existing[(int) $rowId]['locked'])) {
				continue;
			}
			$current = $isNew ? null : $existing[(int) $rowId];
			$clean = array();
			$rowErrors = array();
			$empty = true;
			foreach ($t['columns'] as $col => $def) {
				if ($def['type'] === 'derived') {
					continue;
				}
					if (!$isNew && (!isset($cells[$col]) || $def['readonly'] || $def['create_only'] || $def['transient'])) {
					// Not posted (a disabled control): the stored value stays.
					$clean[$col] = (string) $current['cells'][$col];
					continue;
				}
				$v = isset($cells[$col]) ? (string) $cells[$col] : '';
				$conv = settings_rows_to_raw($def, $v, $year);
				$clean[$col] = $conv['raw'];
				if ($conv['raw'] !== '' && $def['type'] !== 'bool') {
					$empty = false;
				}
				$unchanged = !$isNew && settings_rows_same($def, (string) $current['cells'][$col], $conv['raw']);
				if ($unchanged) {
					// A value already stored is not judged again (old data may predate today's rules).
					$clean[$col] = (string) $current['cells'][$col];
				} elseif ($conv['error'] !== null) {
					$rowErrors[$col] = $conv['error'];
				} elseif (!$isNew && $def['locked_if_used'] && $current['usage'] > 0) {
					$rowErrors[$col] = 6467;
				}
			}
			foreach ($t['columns'] as $col => $def) {
				// "requires": when this cell is filled in, another one must be too (batch control needs stock-managed).
				if (is_array($def['requires']) && !isset($rowErrors[$col]) && isset($clean[$col]) && $clean[$col] !== ''
					&& (!isset($clean[$def['requires'][0]]) || $clean[$def['requires'][0]] === '')) {
					$rowErrors[$col] = (int) $def['requires'][1];
				}
			}
			if ($isNew && $empty) {
				continue;
			}
			// The code must be unique in the table (and in this year), and cannot change once the row is referenced.
			$code = mb_strtolower(trim((string) $clean[$codeCol]));
			if ($code !== '') {
				$owner = isset($codes[$code]) ? $codes[$code] : null;
				if ((($owner !== null && (string) $owner !== $rowId) || isset($seen[$code])) && !isset($rowErrors[$codeCol])) {
					$rowErrors[$codeCol] = isset($t['columns'][$codeCol]['unique_text']) ? (int) $t['columns'][$codeCol]['unique_text'] : 6418;
				}
				$seen[$code] = true;
			}
			if (!$isNew && $current['usage'] > 0 && trim((string) $current['cells'][$codeCol]) !== trim((string) $clean[$codeCol])) {
				$rowErrors[$codeCol] = 6420;
			}
			if (!$rowErrors && $t['row_check'] !== null && function_exists('settings_rows_row_check')) {
				$dirty = $isNew;
				foreach ($clean as $col => $raw) {
					if (!$isNew && !settings_rows_same($t['columns'][$col], (string) $current['cells'][$col], $raw)) {
						$dirty = true;
					}
				}
				if ($dirty) {
					$rowErrors = settings_rows_row_check((string) $t['row_check'], $t, $clean, $current);
				}
			}
			foreach ($rowErrors as $col => $textId) {
				$errors[$tableId . '/' . $rowId . '/' . $col] = $textId;
			}
			if ($rowErrors) {
				continue;
			}
			if ($isNew) {
				$plan[] = array('insert', $tableId, $clean);
			} else {
				$changed = array();
				foreach ($clean as $col => $raw) {
					if (!settings_rows_same($t['columns'][$col], (string) $current['cells'][$col], $raw)) {
						$changed[$col] = $raw;
					}
				}
				if ($changed) {
					$plan[] = array('update', $tableId, $current, $changed);
				}
			}
		}
	}
	$confirm = array();
	if (!$errors && $dryRun) {
		foreach ($plan as $step) {
			$t = $tables[$step[1]];
			if ($t['confirm'] !== null && function_exists('settings_rows_confirm_lines')) {
				$confirm = array_merge($confirm, settings_rows_confirm_lines((string) $t['confirm'], $t, $step));
			}
		}
	}
	if ($errors || $dryRun || !$plan) {
		return array('errors' => $errors, 'posted' => $posted, 'flash' => $flash, 'confirm' => $confirm);
	}
	global $regnaar;
	transaktion('begin');
	foreach ($plan as $step) {
		$t = $tables[$step[1]];
		$objekt = $sectionId . '.' . $step[1];
		if ($t['before_row'] !== null && function_exists('settings_rows_before_row')) {
			settings_rows_before_row((string) $t['before_row'], $t, $step);
		}
		if ($step[0] === 'insert') {
			if ($t['auto_code'] && $t['kind'] === 'grupper') {
				// The next number counted as a number, not as text ("10" after "9"; currencies, audit V8).
				$r = db_fetch_array(db_select("select max(cast(kodenr as integer)) as m from grupper where art = '" . db_escape_string($t['art']) . "' and cast(kodenr as text) ~ '^[0-9]+$'", __FILE__ . " linje " . __LINE__));
				$step[2] = array('kodenr' => (string) ((int) ($r ? $r['m'] : 0) + 1)) + $step[2];
			}
			if (is_array($t['filter'])) {
				$step[2] = array($t['filter']['column'] => (string) (int) $t['filter']['value']) + $step[2];
			}
			$cols = array();
			$vals = array();
				foreach ($t['defaults'] + $step[2] as $col => $raw) {
					if (isset($t['columns'][$col]) && $t['columns'][$col]['transient']) {
						continue;
					}
				$cols[] = $col;
				$vals[] = "'" . db_escape_string((string) (isset($step[2][$col]) && $step[2][$col] !== '' ? $step[2][$col] : $raw)) . "'";
			}
			if ($t['kind'] === 'grupper') {
				$cols[] = 'art';
				$vals[] = "'" . db_escape_string($t['art']) . "'";
				if ($t['kode'] !== null) {
					$cols[] = 'kode';
					$vals[] = "'" . db_escape_string((string) $t['kode']) . "'";
				}
				if ($t['fiscal']) {
					$cols[] = 'fiscal_year';
					$vals[] = (int) ($year !== null ? $year : $regnaar);
				}
			}
			db_modify("insert into " . $t['dbtable'] . " (" . implode(', ', $cols) . ") values (" . implode(', ', $vals) . ")", __FILE__ . " linje " . __LINE__);
			$code = (string) $step[2][$t['code_col']];
			SettingsService::auditRow($sectionId, $step[1], $objekt . '#' . $code, '', json_encode($step[2], JSON_UNESCAPED_UNICODE), 'setting.row_created');
		} else {
			$current = $step[2];
			$sets = array();
			foreach ($step[3] as $col => $raw) {
				$sets[] = $col . " = '" . db_escape_string($raw) . "'";
			}
			$where = "id = " . (int) $current['id'] . ($t['kind'] === 'grupper' ? " and art = '" . db_escape_string($t['art']) . "'" : '');
			db_modify("update " . $t['dbtable'] . " set " . implode(', ', $sets) . " where $where", __FILE__ . " linje " . __LINE__);
			$code = (string) $current['cells'][$t['code_col']];
			foreach ($step[3] as $col => $raw) {
				if (in_array($col, $t['propagate'], true) && $t['kind'] === 'grupper') {
					// Year-agnostic classifications live on every year's copy of the row (risk review R8).
					db_modify("update grupper set $col = '" . db_escape_string($raw) . "' where art = '" . db_escape_string($t['art']) . "' and kodenr = '" . db_escape_string($code) . "'", __FILE__ . " linje " . __LINE__);
				}
				SettingsService::auditRow($sectionId, $step[1] . '.' . $col, $objekt . '#' . $code, (string) $current['cells'][$col], $raw, 'setting.row_updated');
			}
		}
		if ($t['on_save'] !== null) {
			settings_rows_after_save((string) $t['on_save']);
		}
	}
	transaktion('commit');
	return array('errors' => array(), 'posted' => $posted, 'flash' => $flash, 'confirm' => array());
}

/**
 * Follow-ups once a table is saved ('on_save' on the table).
 */
function settings_rows_after_save(string $hook): void
{
	if (function_exists('settings_rows_after_save_extra') && settings_rows_after_save_extra($hook)) {
		return;
	}
	if ($hook === 'warehouses_to_departments') {
		// A department's default warehouse (AFD box1, read by the order pages) follows the warehouses flagged with
		// it here; a department without a flagged warehouse keeps whatever it had.
		$q = db_select("select box1 as afd, min(cast(kodenr as text)) as lager from grupper where art = 'LG' and coalesce(box1, '') <> '' group by box1", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			db_modify("update grupper set box1 = '" . db_escape_string((string) $r['lager']) . "' where art = 'AFD' and cast(kodenr as text) = '" . db_escape_string((string) $r['afd']) . "'", __FILE__ . " linje " . __LINE__);
		}
		// A default warehouse that no longer exists is cleared.
		db_modify("update grupper set box1 = '' where art = 'AFD' and coalesce(box1, '') <> '' and box1 not in (select cast(kodenr as text) from grupper where art = 'LG')", __FILE__ . " linje " . __LINE__);
	}
}

/**
 * Delete a row that nothing references (spec §8.2). Returns a flash entry.
 *
 * @return array{0: string, 1: string}
 */
function settings_rows_delete(string $sectionId, string $tableId, array $t, int $id, ?int $year): array
{
	$rows = settings_rows_load($t, $year);
	if (!isset($rows[$id])) {
		return array('err', st_txt(5719));
	}
	$row = $rows[$id];
	$code = trim((string) $row['cells'][$t['code_col']]);
	if ($row['usage'] > 0) {
		return array('err', sprintf(st_txt(6414), $code) . ' – ' . sprintf(st_txt(6415), $row['usage_text']));
	}
	$where = "id = $id" . ($t['kind'] === 'grupper' ? " and art = '" . db_escape_string($t['art']) . "'" : '');
	transaktion('begin');
	if ($t['on_delete'] !== null && function_exists('settings_rows_on_delete')) {
		settings_rows_on_delete((string) $t['on_delete'], $t, $row);
	}
	db_modify("delete from " . $t['dbtable'] . " where $where", __FILE__ . " linje " . __LINE__);
	SettingsService::auditRow($sectionId, $tableId, $sectionId . '.' . $tableId . '#' . $code, json_encode($row['cells'], JSON_UNESCAPED_UNICODE), '', 'setting.row_deleted');
	if ($t['on_save'] !== null) {
		settings_rows_after_save((string) $t['on_save']);
	}
	transaktion('commit');
	return array('ok', sprintf(st_txt(6430), $code !== '' ? $code : (string) $id));
}

/**
 * Mark a row inactive (soft delete) or active again. Returns a flash entry.
 *
 * @return array{0: string, 1: string}
 */
function settings_rows_set_inactive(string $sectionId, string $tableId, array $t, int $id, bool $inactive, ?int $year): array
{
	$rows = settings_rows_load($t, $year);
	if (!isset($rows[$id]) || !$t['inactive'] || $t['kind'] !== 'grupper') {
		return array('err', st_txt(5719));
	}
	$code = trim((string) $rows[$id]['cells'][$t['code_col']]);
	db_modify("update grupper set inaktiv = " . ($inactive ? 'true' : 'false') . " where id = $id and art = '" . db_escape_string($t['art']) . "'", __FILE__ . " linje " . __LINE__);
	SettingsService::auditRow($sectionId, $tableId . '.inaktiv', $sectionId . '.' . $tableId . '#' . $code, $inactive ? '' : 'on', $inactive ? 'on' : '', $inactive ? 'setting.row_deactivated' : 'setting.row_updated');
	return array('ok', sprintf(st_txt($inactive ? 6435 : 6437), $code));
}

/**
 * Copy a per-year table from another fiscal year into $year (only when $year has no rows yet).
 */
function settings_rows_copy_year(string $sectionId, string $tableId, array $t, int $fromYear, int $year): array
{
	if (!$t['fiscal'] || $t['kind'] !== 'grupper' || settings_rows_load($t, $year)) {
		return array('err', st_txt(5719));
	}
	$cols = array_keys(array_filter($t['columns'], function ($def) {
		return $def['type'] !== 'derived' && !$def['transient'];
	}));
	$list = implode(', ', $cols);
	db_modify("insert into grupper ($list, art, kode, fiscal_year) select $list, art, kode, $year from grupper where " . settings_rows_where($t, $fromYear), __FILE__ . " linje " . __LINE__);
	SettingsService::auditRow($sectionId, $tableId, $sectionId . '.' . $tableId, (string) $fromYear, (string) $year, 'setting.row_created');
	return array('ok', sprintf(st_txt(6440), $fromYear));
}
