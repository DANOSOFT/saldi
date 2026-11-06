<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/bankImportColumns.php --- ver 5.1.0 --- 2026-11-06 ---
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261106 CL/MMR SST-962 Created. The column choices of finans/bankimport.php are remembered for every column and per file layout.
//                 Until now only the first 8 columns were kept (grupper KASKL kode 3 box3..box10), and one set was shared by all files.

include_once(__DIR__ . '/bankImportEncoding.php');

if (!function_exists('bank_import_column_names')) {
	/**
	 * The column names a user can choose in bankimport.php. Anything else posted is dropped.
	 *
	 * @return string[]
	 */
	function bank_import_column_names() {
		return array('dato', 'beskrivelse', 'belob', 'saldo', 'kundenr', 'recieverAccount', 'currency');
	}
}

if (!function_exists('bank_import_clean_columns')) {
	/**
	 * The column choices for a file with $feltantal + 1 columns, one entry per column.
	 *
	 * Unknown names become '', missing columns are filled with '' and columns beyond the file are dropped.
	 *
	 * @param array      $feltnavn  Column index => chosen name, as posted or stored.
	 * @param int|string $feltantal Index of the last column (number of columns - 1).
	 * @return array<int, string> Index 0..$feltantal => name or ''.
	 */
	function bank_import_clean_columns($feltnavn, $feltantal) {
		$allowed = bank_import_column_names();
		$columns = array();
		for ($y = 0; $y <= (int)$feltantal; $y++) {
			$name = isset($feltnavn[$y]) ? trim((string)$feltnavn[$y]) : '';
			$columns[$y] = in_array($name, $allowed, true) ? $name : '';
		}
		return $columns;
	}
}

if (!function_exists('bank_import_columns_setting_name')) {
	/**
	 * The settings.var_name the choices for a layout are kept under. A layout is told apart by its number of columns,
	 * so a bank's own file and a card acquirer's file (e.g. 7 and 13 columns) each keep their own choices.
	 *
	 * @param int|string $feltantal Index of the last column (number of columns - 1).
	 * @return string
	 */
	function bank_import_columns_setting_name($feltantal) {
		return 'columns_' . ((int)$feltantal + 1);
	}
}

if (!function_exists('bank_import_saved_columns')) {
	/**
	 * The choices a user saved for files with this number of columns.
	 *
	 * @param int|string $userId    brugere.id.
	 * @param int|string $feltantal Index of the last column (number of columns - 1).
	 * @return array<int, string>|null Index 0..$feltantal => name or '', or null when nothing is saved for the layout.
	 */
	function bank_import_saved_columns($userId, $feltantal) {
		$value = get_settings_value(bank_import_columns_setting_name($feltantal), 'bankimport', null, (int)$userId);
		if ($value === null || $value === '') {
			return null;
		}
		return bank_import_clean_columns(explode(',', $value), $feltantal);
	}
}

if (!function_exists('bank_import_save_columns')) {
	/**
	 * Remembers the user's choices for files with this number of columns.
	 *
	 * Only known names are stored, so the value never holds a quote or a comma of its own.
	 * Nothing is stored when no column is chosen, so an empty post cannot wipe a saved layout.
	 *
	 * @param int|string $userId    brugere.id.
	 * @param array      $feltnavn  Column index => chosen name, as posted.
	 * @param int|string $feltantal Index of the last column (number of columns - 1).
	 * @return bool True when the choices were stored.
	 */
	function bank_import_save_columns($userId, $feltnavn, $feltantal) {
		if ((int)$userId <= 0 || (int)$feltantal < 0) {
			return false;
		}
		$columns = bank_import_clean_columns($feltnavn, $feltantal);
		if (!array_filter($columns, 'strlen')) {
			return false;
		}
		update_settings_value(bank_import_columns_setting_name($feltantal), 'bankimport', implode(',', $columns), 'Bank import column choices for a file with this number of columns', (int)$userId);
		return true;
	}
}

if (!function_exists('bank_import_file_last_column')) {
	/**
	 * Index of the last column in an uploaded bank file (number of columns - 1), found the way vis_data() in
	 * finans/bankimport.php finds it: the separator that occurs most often in the first 11 lines (comma, semicolon
	 * or tab), then the line with the most fields, with double quotes around a field honoured.
	 *
	 * @param string $filnavn Path of the uploaded file.
	 * @param string $charset The tenant's charset, as for bank_import_read_lines().
	 * @return int The last column index, or -1 when the file cannot be read or has no separator.
	 */
	function bank_import_file_last_column($filnavn, $charset) {
		if (!is_readable((string)$filnavn)) {
			return -1;
		}
		$lines = bank_import_read_lines((string)$filnavn, (string)$charset);
		if (!$lines) {
			return -1;
		}
		$counts = array(',' => 0, ';' => 0, "\t" => 0);
		foreach (array_slice($lines, 0, 11) as $line) {
			foreach ($counts as $sep => $n) {
				$counts[$sep] += substr_count($line, $sep);
			}
		}
		arsort($counts);
		$sep = key($counts);
		if ($counts[$sep] == 0 || count(array_keys($counts, $counts[$sep])) > 1) {
			return -1;
		}
		$last = -1;
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}
			$last = max($last, count(str_getcsv($line, $sep, '"', '')) - 1);
		}
		return $last;
	}
}

if (!function_exists('bank_import_columns_for_upload')) {
	/**
	 * The column choices to show for a freshly uploaded file: the ones saved for its number of columns, otherwise the
	 * legacy choices (box3..box10). Either way there is one entry per column of the file, so vis_data() shows every
	 * column and not only as many as were remembered.
	 *
	 * @param int|string $userId  brugere.id.
	 * @param string     $filnavn Path of the uploaded file.
	 * @param string     $charset The tenant's charset.
	 * @param array      $legacy  The choices read from grupper KASKL kode 3 box3..box10.
	 * @return array<int|string, string> The choices to pass to vis_data().
	 */
	function bank_import_columns_for_upload($userId, $filnavn, $charset, $legacy) {
		$last = bank_import_file_last_column($filnavn, $charset);
		if ($last < 0) {
			return $legacy;
		}
		$saved = bank_import_saved_columns($userId, $last);
		return $saved !== null ? $saved : bank_import_clean_columns($legacy, $last);
	}
}
