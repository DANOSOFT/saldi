<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func/formularFaxToMobile.php --- ver 5.1.1 --- 2026-11-18 ---
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
// 20261111 CL/MMR SST-898 Created. SD-532 turned the fax field into the mobile field (adresser.mobile), but the forms kept "Fax: $egen_fax".
//                 The forms now name the field "Mobilnr." and use $egen_mobile / $adresser_mobile (Morten 2026-11-11: «der er ingen der bruger Fax længere»).
//                 Morten chose the label on 2026-11-13: «Bare "Mobilnr."».
// 20261118 CL/MMR SST-898 (review 4) A login on an account without the formularer or tekster table skips that step instead of failing, and runs it once the table exists.
//                 The old default texts are deleted here (formularFaxToMobileTekster()) and only when one is still stored, so a login with nothing left to change writes nothing.
// 20261118 CL/MMR SST-898 (review 5) The stored texts per id and language are looked up with if_array() (includes/std_func.php, loaded by every caller of betweenUpdates.php).
// 20261118 CL/MMR SST-898 (review 7) A form is only rewritten while it still holds the text and language that were read, so a form saved in between is kept.
// 20261118 CL/MMR SST-898 (review 8) The old and new form texts are sent hex-encoded on PostgreSQL, so db_modify()'s " like " to " ilike " rewrite cannot change them.
// 20261118 CL/MMR SST-898 (review 9) A form text is rewritten in UTF-8 and sent back in the connection's encoding (LATIN9 too); a text that cannot be read or converted exactly is left as it is.
//                 On MySQL/MariaDB the text compare uses the connection's _bin collation and CHAR_LENGTH(), so it is exact also when the column has another character set.
// 20261118 CL/MMR SST-898 (review 10) All texts are sent hex-encoded on MySQL/MariaDB too, so db_escape_string() is not used; the compare converts both sides to utf8mb4_bin.
//                 The old default texts are deleted with the same exact compare instead of deleteStaleTekst().

if (!function_exists('formular_fax_to_mobile_text')) {
	/**
	 * One form text (formularer.beskrivelse) with the fax variables replaced by the mobile ones.
	 *
	 * $egen_fax / $eget_fax become $egen_mobile and $adresser_fax becomes $adresser_mobile; includes/formfunk.php
	 * printed all of them from adresser.mobile already, so the printed number does not change. Where the standard
	 * label "Fax:" (or Telefax/Faks) stands right before the variable, possibly with a dialling code such as
	 * "(+45)" in between, it becomes the mobile label of the form's language: "Mobilnr." (Danish, Norwegian and any
	 * other language), "Mobile" (English) or "Móvil" (Spanish). "Fax.:" becomes "Mobilnr.:", not "Mobilnr..:".
	 * The label counts as the standard one
	 * only when it is the whole label: at the start of the text or right after a "*", ";" or "|" separator, as in the
	 * standard forms ("Fax: $egen_fax", "... * Fax: $egen_fax; ..."). A label of the customer's own, such as
	 * "Mobile Pay.: $egen_fax;" or "Support Fax: $egen_fax", is kept and only the variable changes.
	 *
	 * The text must be UTF-8 (formularFaxToMobile() converts it from the connection's encoding first). A text the
	 * regular expressions cannot read (not valid UTF-8) is returned unchanged, so it is never written.
	 *
	 * @param string $text  The form text, UTF-8.
	 * @param string $sprog The form's language (formularer.sprog), e.g. 'Dansk', 'English', 'Norsk'.
	 * @return string The text with mobile variables and labels; unchanged when it holds no fax variable.
	 */
	function formular_fax_to_mobile_text($text, $sprog) {
		$text = (string)$text;
		$original = $text;
		$lang = strtolower(trim((string)$sprog));
		if (in_array($lang, array('english', 'engelsk'), true)) {
			$label = 'Mobile';
		} elseif ($lang === 'spansk') {
			$label = 'Móvil';
		} else {
			$label = 'Mobilnr.';
		}
		$var = '\$(egen|eget|adresser)_fax(?![A-Za-z0-9_])';
		// the standard label right before the variable: "Fax: ", "Telefax.: ", "Fax: (+45)", as the whole label:
		// at the start of the text or after a separator, so "Support Fax:" keeps its text
		// a dot after the old label ("Fax.:") is dropped when the new label ends with one ("Mobilnr.:")
		$labelled = preg_replace_callback(
			'/(^\s*|[*;|]\s*)(?:Telefax|Fax|FAX|Faks)(\.?)(:\s*(?:\(\+?[0-9]+\)\s*)?)' . $var . '/u',
			function ($m) use ($label) {
				$dot = substr($label, -1) === '.' ? '' : $m[2];
				return $m[1] . $label . $dot . $m[3] . '$' . $m[4] . '_fax';
			},
			$text
		);
		$result = $labelled === null ? null : preg_replace_callback('/' . $var . '/u', function ($m) {
			return '$' . ($m[1] === 'adresser' ? 'adresser' : 'egen') . '_mobile';
		}, $labelled);
		// preg_* return null on a text that is not valid UTF-8
		return $result === null ? $original : $result;
	}
}

if (!function_exists('formular_fax_to_mobile_has_table')) {
	/**
	 * Whether the account's own schema has the table: PostgreSQL current_schema(), MySQL/MariaDB DATABASE(), the same
	 * filter as saldi_schema_filter() in includes/db_query.php. tbl_exists() is not used here: on PostgreSQL it reads
	 * pg_tables in every schema and warns about a missing table, and the update's test runners load only
	 * db_select() and db_fetch_array().
	 *
	 * @param string $table A table name (letters, digits and _ only).
	 * @return bool True when the table exists.
	 */
	function formular_fax_to_mobile_has_table($table) {
		global $db_type;
		$schema = in_array($db_type, array('mysql', 'mysqli'), true) ? 'DATABASE()' : 'current_schema()';
		$table = preg_replace('/[^A-Za-z0-9_]/', '', (string)$table);
		$q = db_select("SELECT 1 AS found FROM information_schema.tables WHERE table_schema = $schema AND table_name = '$table'", __FILE__ . " linje " . __LINE__);
		return (bool)($q && db_fetch_array($q));
	}
}

if (!function_exists('formular_fax_to_mobile_connection')) {
	/**
	 * The encoding in which the database connection sends and expects text. Saldi's db_connect() sets the
	 * PostgreSQL client encoding to UTF8 or LATIN9 from $db_encode; MySQL/MariaDB report the character set of the
	 * results (else of the client; latin1 there is Windows-1252), which formular_fax_to_mobile_literal() also names.
	 *
	 * @return array{encoding: string|null, mysql_charset: string|null} encoding: the mbstring name ('UTF-8',
	 *         'ISO-8859-15', 'ISO-8859-1', 'Windows-1252'), null if not in the list; mysql_charset: MySQL/MariaDB only.
	 */
	function formular_fax_to_mobile_connection() {
		global $db_type;
		$mysql = in_array($db_type, array('mysql', 'mysqli'), true);
		$sql = $mysql ? "SELECT COALESCE(@@character_set_results, @@character_set_client) AS enc" : "SELECT pg_client_encoding() AS enc";
		$r = db_fetch_array(db_select($sql, __FILE__ . " linje " . __LINE__));
		$name = $r ? strtolower((string)$r['enc']) : '';
		$names = array(
			'utf8' => 'UTF-8', 'utf8mb3' => 'UTF-8', 'utf8mb4' => 'UTF-8', 'unicode' => 'UTF-8',
			'latin9' => 'ISO-8859-15', 'iso_8859_15' => 'ISO-8859-15', 'latin1' => $mysql ? 'Windows-1252' : 'ISO-8859-1',
			'win1252' => 'Windows-1252',
		);
		return array(
			'encoding' => ifset($names, $name),
			'mysql_charset' => $mysql && $name !== '' ? preg_replace('/[^a-z0-9_]/', '', $name) : null,
		);
	}
}

if (!function_exists('formular_fax_to_mobile_literal')) {
	/**
	 * A text, in the connection's encoding, as an SQL value that keeps its exact bytes through db_modify() and
	 * db_select(). The text is sent hex-encoded, so no escaping is needed: on PostgreSQL those wrappers turn every
	 * " like " in the statement into " ilike ", also inside a quoted text, and db_escape_string() re-encodes
	 * non-UTF-8 text on MySQL/MariaDB (and calls the removed mysql_real_escape_string() for $db_type 'mysql').
	 * PostgreSQL decodes it with the connection's client encoding (UTF8 or LATIN9), MySQL/MariaDB with the
	 * connection's character set, so the value is the same as a quoted literal would be.
	 *
	 * @param string      $value        The text, in the connection's encoding.
	 * @param string|null $mysqlCharset The MySQL/MariaDB character set (formular_fax_to_mobile_connection()).
	 * @return string The SQL expression.
	 */
	function formular_fax_to_mobile_literal($value, $mysqlCharset = null) {
		global $db_type;
		$hex = bin2hex((string)$value);
		if (in_array($db_type, array('mysql', 'mysqli'), true)) {
			return $mysqlCharset ? "CONVERT(X'$hex' USING $mysqlCharset)" : "X'$hex'";
		}
		return "convert_from(decode('$hex', 'hex'), pg_client_encoding())";
	}
}

if (!function_exists('formular_fax_to_mobile_same')) {
	/**
	 * A WHERE condition that holds while the column still has exactly the value read earlier: IS NULL for a NULL,
	 * otherwise an exact match. MySQL/MariaDB convert both sides to utf8mb4 and compare them with utf8mb4_bin (so
	 * case counts, whatever the column's and the connection's character sets are) plus CHAR_LENGTH(), because
	 * utf8mb4_bin ignores trailing spaces (PAD SPACE); PostgreSQL's = is exact already.
	 *
	 * @param string      $column       A column of formularer (letters, digits and _ only).
	 * @param string|null $value        The value read by the same function, in the connection's encoding.
	 * @param string|null $mysqlCharset The MySQL/MariaDB connection character set (formular_fax_to_mobile_connection()).
	 * @return string The condition, e.g. "sprog = 'Dansk'" or "sprog IS NULL".
	 */
	function formular_fax_to_mobile_same($column, $value, $mysqlCharset = null) {
		global $db_type;
		$column = preg_replace('/[^A-Za-z0-9_]/', '', (string)$column);
		if ($value === null) {
			return "$column IS NULL";
		}
		$literal = formular_fax_to_mobile_literal($value, $mysqlCharset);
		if (in_array($db_type, array('mysql', 'mysqli'), true)) {
			return "(CONVERT($column USING utf8mb4) COLLATE utf8mb4_bin = CONVERT($literal USING utf8mb4) COLLATE utf8mb4_bin"
				. " AND CHAR_LENGTH($column) = CHAR_LENGTH($literal))";
		}
		return "$column = $literal";
	}
}

if (!function_exists('formular_fax_to_mobile_stale_tekster')) {
	/**
	 * The old default texts that formularFaxToMobileTekster() deletes, per text id and language (1 = Danish,
	 * 2 = English, 3 = Norwegian): "Telefax"/"Fax"/"Faks", the interim "Mobile", the previous "Mobil" and
	 * "Mobilnummer" (5230, SD-525). English keeps "Mobile" / "Mobile number"; only its old "Fax" goes.
	 * The new "Mobilnr." is never in the list.
	 *
	 * @return array<int, array{0: int, 1: int, 2: string[]}> [tekst_id, sprog_id, old texts]
	 */
	function formular_fax_to_mobile_stale_tekster() {
		return array(
			array(378, 1, array('Telefax', 'Mobile', 'Mobil')),
			array(378, 2, array('Fax')),
			array(378, 3, array('Faks', 'Mobile', 'Mobil')),
			array(50, 1, array('Fax', 'Mobile', 'Mobil')),
			array(50, 2, array('Fax')),
			array(50, 3, array('Faks', 'Mobile', 'Mobil')),
			array(5230, 1, array('Mobilnummer')),
			array(5230, 3, array('Mobilnummer')),
		);
	}
}

if (!function_exists('formularFaxToMobileTekster')) {
	/**
	 * Deletes the stored old defaults of texts 378, 50 and 5230 (see formular_fax_to_mobile_stale_tekster()), so
	 * findtekst() reads the new default from importfiler/tekster.csv. A text the customer has edited (e.g.
	 * "Mobiltlf.") is kept. Runs from includes/betweenUpdates.php at every login: it reads the few rows of the three
	 * ids and deletes only for a language that still holds an old text, so a login with nothing left to change writes
	 * nothing. The DELETE matches exactly like the form UPDATE (formular_fax_to_mobile_same()), as deleteStaleTekst()
	 * would on PostgreSQL, without db_escape_string(). An account without the tekster table is skipped; a later login
	 * handles it.
	 *
	 * @return int The number of text/language pairs that held an old text.
	 */
	function formularFaxToMobileTekster() {
		if (!formular_fax_to_mobile_has_table('tekster')) {
			return 0;
		}
		$stored = array();
		$q = db_select("SELECT tekst_id, sprog_id, tekst FROM tekster WHERE tekst_id IN (378, 50, 5230)", __FILE__ . " linje " . __LINE__);
		while ($q && ($r = db_fetch_array($q))) {
			$stored[(int)$r['tekst_id'] . '/' . (int)$r['sprog_id']][] = (string)$r['tekst'];
		}
		$found = 0;
		$connection = null;
		foreach (formular_fax_to_mobile_stale_tekster() as $stale) {
			list($tekstId, $sprogId, $oldTexts) = $stale;
			if (!array_intersect($oldTexts, if_array($stored, "$tekstId/$sprogId"))) {
				continue;
			}
			if ($connection === null) {
				$connection = formular_fax_to_mobile_connection();
			}
			$matches = array();
			foreach ($oldTexts as $oldText) {
				$matches[] = formular_fax_to_mobile_same('tekst', $oldText, $connection['mysql_charset']);
			}
			db_modify("DELETE FROM tekster WHERE tekst_id = '" . (int)$tekstId . "' AND sprog_id = '" . (int)$sprogId . "' AND (" . implode(" OR ", $matches) . ")", __FILE__ . " linje " . __LINE__);
			$found++;
		}
		return $found;
	}
}

if (!function_exists('formular_fax_to_mobile_recode')) {
	/**
	 * One stored form text, as read from the connection, rewritten by formular_fax_to_mobile_text() and returned in
	 * the connection's encoding again: the text is converted to UTF-8 for the rewrite and back afterwards. Nothing
	 * is returned when the text does not change, when its encoding is unknown and it is not UTF-8, or when a
	 * conversion is not exact (the result read back as UTF-8 must be the rewritten text), so such a row is never
	 * written.
	 *
	 * @param string|null $text    formularer.beskrivelse as fetched.
	 * @param string|null $sprog   formularer.sprog as fetched.
	 * @param string|null $charset The connection's encoding (formular_fax_to_mobile_connection()); null if unknown.
	 * @return string|null The new text in the connection's encoding, or null when the row is to be left alone.
	 */
	function formular_fax_to_mobile_recode($text, $sprog, $charset) {
		$text = (string)$text;
		$sprog = (string)$sprog;
		if ($charset === null || $charset === 'UTF-8') {
			$utf8 = $text;
			$utf8Sprog = $sprog;
		} else {
			$utf8 = mb_convert_encoding($text, 'UTF-8', $charset);
			$utf8Sprog = mb_convert_encoding($sprog, 'UTF-8', $charset);
			if (!is_string($utf8) || !is_string($utf8Sprog) || mb_convert_encoding($utf8, $charset, 'UTF-8') !== $text) {
				return null;
			}
		}
		if (!mb_check_encoding($utf8, 'UTF-8')) {
			return null;
		}
		$newUtf8 = formular_fax_to_mobile_text($utf8, $utf8Sprog);
		if ($newUtf8 === $utf8) {
			return null;
		}
		$new = ($charset === null || $charset === 'UTF-8') ? $newUtf8 : mb_convert_encoding($newUtf8, $charset, 'UTF-8');
		if (!is_string($new) || $new === '' || (($charset !== null && $charset !== 'UTF-8') && mb_convert_encoding($new, 'UTF-8', $charset) !== $newUtf8)) {
			return null;
		}
		return $new;
	}
}

if (!function_exists('formularFaxToMobile')) {
	/**
	 * Rewrites the account's form texts that still use a fax variable (see formular_fax_to_mobile_text()).
	 * Runs from includes/betweenUpdates.php at every login: it reads only rows that mention "fax", and a
	 * rewritten row no longer holds a fax variable, so a second run (or a concurrent login) changes nothing.
	 * The UPDATE only applies while the row still holds the text and language it was computed from, so a form
	 * saved in the form editor between the read and the write is kept; the next login looks at it again. Both texts go
	 * through formular_fax_to_mobile_literal(), so a form with " like " in it is compared and saved unchanged. The
	 * text is rewritten in UTF-8 and sent back in the connection's encoding (formular_fax_to_mobile_recode()); a text
	 * that cannot be converted both ways without loss is left as it is.
	 * An account without the formularer table is skipped and no flag is set, so a later login rewrites the texts.
	 *
	 * @return int The number of form texts changed: rows that hold the rewritten text after the UPDATE.
	 */
	function formularFaxToMobile() {
		$changed = 0;
		if (!formular_fax_to_mobile_has_table('formularer')) {
			return $changed;
		}
		$connection = null;
		$q = db_select("SELECT id, sprog, beskrivelse FROM formularer WHERE LOWER(beskrivelse) LIKE '%fax%' ORDER BY id", __FILE__ . " linje " . __LINE__);
		while ($q && ($r = db_fetch_array($q))) {
			if ($connection === null) {
				$connection = formular_fax_to_mobile_connection();
			}
			$new = formular_fax_to_mobile_recode($r['beskrivelse'], $r['sprog'], $connection['encoding']);
			if ($new === null) {
				continue;
			}
			$id = (int)$r['id'];
			db_modify("UPDATE formularer SET beskrivelse = " . formular_fax_to_mobile_literal($new, $connection['mysql_charset']) . " WHERE id = '$id' AND "
				. formular_fax_to_mobile_same('beskrivelse', $r['beskrivelse'], $connection['mysql_charset'])
				. " AND " . formular_fax_to_mobile_same('sprog', $r['sprog'], $connection['mysql_charset']),
				__FILE__ . " linje " . __LINE__);
			$check = db_fetch_array(db_select("SELECT beskrivelse FROM formularer WHERE id = '$id'", __FILE__ . " linje " . __LINE__));
			if ($check && (string)$check['beskrivelse'] === $new) {
				$changed++;
			}
		}
		return $changed;
	}
}
