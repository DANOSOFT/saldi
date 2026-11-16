<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/bankImportColumns.php --- ver 5.1.1 --- 2026-11-16 ---
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
// 20261116 CL/MMR SST-962 Review (CodeRabbit): the choices are saved for the number of columns of the preview they were made on, counted from the file (bank_import_columns_for_post()).
//                 «Vis» with a new splitter saves nothing and shows the choices saved for the new number of columns.
//                 The save runs under a lock, so two first saves at once cannot leave two rows, and the newest row is the one read.

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
			$name = trim((string)ifset($feltnavn, $y, ''));
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

if (!function_exists('bank_import_columns_where')) {
	/**
	 * The settings rows of one user's choices for one number of columns. A NULL user_id counts as 0, as in
	 * update_settings_value() and the settings unique index.
	 *
	 * @param int|string $userId    brugere.id.
	 * @param int|string $feltantal Index of the last column (number of columns - 1).
	 * @return string SQL condition without "where".
	 */
	function bank_import_columns_where($userId, $feltantal) {
		return "var_grp = 'bankimport' and var_name = '" . bank_import_columns_setting_name($feltantal) . "' and coalesce(user_id, 0) = " . (int)$userId;
	}
}

if (!function_exists('bank_import_columns_lock_name')) {
	/**
	 * The name of the lock a save of one user's choices for one number of columns runs under. The database name is
	 * part of it, because a MySQL lock is shared by the whole server (a PostgreSQL advisory lock is per database).
	 *
	 * @param int|string $userId    brugere.id.
	 * @param int|string $feltantal Index of the last column (number of columns - 1).
	 * @return string 43 characters, within MySQL's 64.
	 */
	function bank_import_columns_lock_name($userId, $feltantal) {
		global $db;
		return 'bankimport_' . md5((string)$db . '|' . (int)$userId . '|' . bank_import_columns_setting_name($feltantal));
	}
}

if (!function_exists('bank_import_saved_columns')) {
	/**
	 * The choices a user saved for files with this number of columns.
	 *
	 * @param int|string $userId    brugere.id.
	 * @param int|string $feltantal Index of the last column (number of columns - 1).
	 * @return array<int, string>|null Index 0..$feltantal => name or '', or null when nothing is saved for the layout.
	 *         Should the layout have more than one row, the newest (highest id) is read, as the settings duplicate merge does.
	 */
	function bank_import_saved_columns($userId, $feltantal) {
		if ((int)$userId <= 0 || (int)$feltantal < 0) {
			return null;
		}
		$qtxt = "select var_value from settings where " . bank_import_columns_where($userId, $feltantal) . " order by id desc limit 1";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$value = $r ? (string)$r['var_value'] : '';
		if ($value === '') {
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
	 * The look-up and the insert run under a lock per user and number of columns (a PostgreSQL advisory lock, or
	 * GET_LOCK() on MySQL), so two first saves at once cannot both insert. Rows left over from before are merged:
	 * the newest gets the value and the others are deleted. The settings API itself is not changed. Call it outside
	 * a transaction, so the row is committed before the lock is released.
	 *
	 * @param int|string $userId    brugere.id.
	 * @param array      $feltnavn  Column index => chosen name, as posted.
	 * @param int|string $feltantal Index of the last column (number of columns - 1).
	 * @return bool True when the choices were stored.
	 */
	function bank_import_save_columns($userId, $feltnavn, $feltantal) {
		global $db_type;

		if ((int)$userId <= 0 || (int)$feltantal < 0) {
			return false;
		}
		$columns = bank_import_clean_columns($feltnavn, $feltantal);
		if (!array_filter($columns, 'strlen')) {
			return false;
		}
		$mysql = ($db_type == 'mysql' || $db_type == 'mysqli');
		$lock = bank_import_columns_lock_name($userId, $feltantal);
		$where = bank_import_columns_where($userId, $feltantal);
		$value = db_escape_string(implode(',', $columns));
		if ($mysql) {
			db_select("select get_lock('$lock', 10)", __FILE__ . " linje " . __LINE__);
		} else {
			db_select("select pg_advisory_lock(hashtext('$lock'))", __FILE__ . " linje " . __LINE__);
		}
		$ids = array();
		$q = db_select("select id from settings where $where order by id desc", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$ids[] = (int)$r['id'];
		}
		if ($ids) {
			$keep = array_shift($ids);
			db_modify("update settings set var_value = '$value' where id = $keep", __FILE__ . " linje " . __LINE__);
			if ($ids) {
				db_modify("delete from settings where id in (" . implode(',', $ids) . ")", __FILE__ . " linje " . __LINE__);
			}
		} else {
			$name = bank_import_columns_setting_name($feltantal);
			$qtxt = "insert into settings (var_name, var_grp, var_value, var_description, user_id) values ";
			$qtxt .= "('$name', 'bankimport', '$value', 'Bank import column choices for a file with this number of columns', " . (int)$userId . ")";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		if ($mysql) {
			db_select("select release_lock('$lock')", __FILE__ . " linje " . __LINE__);
		} else {
			db_select("select pg_advisory_unlock(hashtext('$lock'))", __FILE__ . " linje " . __LINE__);
		}
		return true;
	}
}

if (!function_exists('bank_import_file_last_column')) {
	/**
	 * Index of the last column in an uploaded bank file (number of columns - 1), found the way vis_data() in
	 * finans/bankimport.php finds it: the separator that occurs most often in the first 11 lines (comma, semicolon
	 * or tab), then the line with the most fields, with double quotes around a field honoured.
	 *
	 * With a splitter chosen on the page («Komma», «Semikolon» or «Tabulator») that separator is used instead, as
	 * vis_data() does after «Vis».
	 *
	 * @param string $filnavn  Path of the uploaded file.
	 * @param string $charset  The tenant's charset, as for bank_import_read_lines().
	 * @param string $splitter The splitter chosen on the page, or '' to find it in the file.
	 * @return int The last column index, or -1 when the file cannot be read, has no separator or the splitter is unknown.
	 */
	function bank_import_file_last_column($filnavn, $charset, $splitter = '') {
		if (!is_readable((string)$filnavn)) {
			return -1;
		}
		$lines = bank_import_read_lines((string)$filnavn, (string)$charset);
		if (!$lines) {
			return -1;
		}
		if ((string)$splitter !== '') {
			$sep = ifset(array('Komma' => ',', 'Semikolon' => ';', 'Tabulator' => "\t"), (string)$splitter, null);
			return $sep === null ? -1 : bank_import_lines_last_column($lines, $sep);
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
		return bank_import_lines_last_column($lines, $sep);
	}
}

if (!function_exists('bank_import_lines_last_column')) {
	/**
	 * Index of the last column over the lines of a bank file: the line with the most fields, with double quotes
	 * around a field honoured, as vis_data() splits them.
	 *
	 * @param string[] $lines The file's lines, as from bank_import_read_lines().
	 * @param string   $sep   The separator: ',', ';' or a tab.
	 * @return int The last column index, or -1 when every line is empty.
	 */
	function bank_import_lines_last_column($lines, $sep) {
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

if (!function_exists('bank_import_columns_for_post')) {
	/**
	 * The column choices to show after «Vis» or «Hent», with the save that belongs to them. Runs before the page prints.
	 *
	 * The posted choices were made on the preview the page showed, made with $previewSplitter. They are saved for
	 * that preview's number of columns, counted from the file and not taken from the post. «Vis» with another splitter
	 * asks for a new preview, so nothing is saved: the choices saved for the new number of columns are shown, or the
	 * posted ones padded to it when none are saved. «Hent» imports the preview that was shown (flyt_data() reads the
	 * file vis_data() wrote), so its choices are saved for that preview whatever the splitter field says.
	 *
	 * @param int|string  $userId          brugere.id.
	 * @param string      $filnavn         Path of the uploaded file.
	 * @param string      $charset         The tenant's charset.
	 * @param array       $feltnavn        Column index => chosen name, as posted.
	 * @param string|null $splitter        The splitter field as posted.
	 * @param string|null $previewSplitter The splitter the shown preview was made with, or null when the form has no
	 *                                     such field (a page opened before it existed); the posted splitter is then used.
	 *                                     '' means the preview had no splitter: «Vis» with a splitter is then a new one, and
	 *                                     nothing is saved. A splitter that is not a string (a forged post) changes nothing.
	 * @param bool        $show            True for «Vis», false for «Hent».
	 * @return array The choices to pass on to vis_data() or flyt_data().
	 */
	function bank_import_columns_for_post($userId, $filnavn, $charset, $feltnavn, $splitter, $previewSplitter, $show) {
		if (!is_string($splitter) || ($previewSplitter !== null && !is_string($previewSplitter))) {
			return $feltnavn;
		}
		$shown = $previewSplitter === null ? $splitter : $previewSplitter;
		if ($show && $splitter !== $shown) {
			$last = bank_import_file_last_column($filnavn, $charset, $splitter);
			if ($last < 0) {
				return $feltnavn;
			}
			$saved = bank_import_saved_columns($userId, $last);
			return $saved !== null ? $saved : bank_import_clean_columns($feltnavn, $last);
		}
		$last = $shown === '' ? -1 : bank_import_file_last_column($filnavn, $charset, $shown);
		if ($last >= 0) {
			bank_import_save_columns($userId, $feltnavn, $last);
		}
		return $feltnavn;
	}
}
