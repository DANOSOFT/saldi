<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- includes/saldi_help_texts.php --- ver 5.1.1 --- 2026-11-07 ---
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
// 20261107 CL/ASR Created: the help texts (help.* keys in importfiler/tekster_keyed.tsv) for the help panel.
//                 Only keys that the keyed TSV files list are resolved, so a request can never self-heal an arbitrary key into a tenant's tekster table.
// 20261107 CL/ASR Review: the texts the tenant's tekster table already holds are read in one query and put in findtekst()'s session cache, so a first open costs one query instead of two per key.
//                 findtekst_utf8() still resolves (and seeds) the rest.

/**
 * The help.<group>.* and help.common.* keys of the keyed TSV contents, custom file first, each key once, in file order. Pure.
 *
 * @param string $customTsv   contents of importfiler/egne_tekster_keyed.tsv ('' when there is none)
 * @param string $standardTsv contents of importfiler/tekster_keyed.tsv
 * @param string $group       the page's text group, e.g. 'kk'
 * @return array<int, string> the keys
 */
function saldi_help_text_keys(string $customTsv, string $standardTsv, string $group): array
{
	$keys = array();
	foreach (array($customTsv, $standardTsv) as $tsv) {
		foreach (preg_split('/\r\n|\n|\r/', $tsv) as $line) {
			$key = explode("\t", $line, 2)[0];
			if ((strpos($key, 'help.' . $group . '.') === 0 || strpos($key, 'help.common.') === 0) && !isset($keys[$key])) {
				$keys[$key] = true;
			}
		}
	}
	return array_keys($keys);
}

/**
 * key => UTF-8 text in the user's language, through findtekst_utf8() (a tenant's own edit wins, and each text is cached
 * in the session after the first call). [] for a malformed group, for 'common' (shared texts, not a page) and for a group
 * without help.<group>.* keys.
 *
 * Needs findtekst_utf8() (includes/stdFunc/findTxtUtf8.php) and a database connection, except for the [] cases.
 *
 * @param string $group   the page's text group, matching /^[a-z]{2,12}$/
 * @param int    $sprogId the user's language: 1 Danish, 2 English, 3 Norwegian
 * @return array<string, string>
 */
function saldi_help_texts(string $group, int $sprogId): array
{
	if (!preg_match('/^[a-z]{2,12}$/', $group) || $group === 'common') return array();
	if (!function_exists('locateDir')) include_once(__DIR__ . '/stdFunc/locateDir.php');
	// The same folder findtekst() reads the keyed seeds from (findtekst_keyed_seed()); the fallback covers a caller
	// whose working directory is not inside the installation.
	$dir = locateDir('importfiler');
	if ($dir === '') $dir = __DIR__ . '/../importfiler';
	$read = function (string $f): string { return is_file($f) ? (string) file_get_contents($f) : ''; };
	$keys = saldi_help_text_keys($read("$dir/egne_tekster_keyed.tsv"), $read("$dir/tekster_keyed.tsv"), $group);
	$hasGroupKey = false;
	foreach ($keys as $k) {
		if (strpos($k, 'help.' . $group . '.') === 0) {
			$hasGroupKey = true;
			break;
		}
	}
	if (!$hasGroupKey) return array();
	saldi_help_texts_prefetch($keys, $sprogId);
	$out = array();
	foreach ($keys as $k) {
		$out[$k] = html_entity_decode(findtekst_utf8($k, $sprogId), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
	return $out;
}

/**
 * The usable rows of a tekster query, key => text: per key only its first row (the query orders by id, so this is the
 * row findtekst() would read), and only when that text is not '', not '-' and not the key itself (a placeholder that is
 * not translated yet). The same rules as findtekst() (includes/stdFunc/findTxt.php); everything else is left to it. Pure.
 *
 * @param array<int, array<string, mixed>> $rows rows with tekst_key and tekst
 * @return array<string, string>
 */
function saldi_help_text_pick(array $rows): array
{
	$seen = array();
	$out = array();
	foreach ($rows as $r) {
		$key = isset($r['tekst_key']) ? (string) $r['tekst_key'] : '';
		if ($key === '' || isset($seen[$key])) continue;
		$seen[$key] = true;
		$text = isset($r['tekst']) ? $r['tekst'] : null;
		if ($text === null || $text === '' || $text === '-' || $text === $key) continue;
		$out[$key] = (string) $text;
	}
	return $out;
}

/**
 * Puts the help texts that the tenant's tekster table already holds into findtekst()'s session cache, in one query.
 * Without it each uncached findtekst() runs two queries (the information_schema check and the keyed lookup), about
 * 380 for the kassekladde group. Keys that are cached already, missing or only placeholders are left to findtekst(),
 * which also seeds a missing key from the keyed TSV. Does nothing without the tekst_key column or the db functions.
 *
 * @param array<int, string> $keys    help keys from the keyed TSV files (never from the client)
 * @param int                $sprogId the user's language
 */
function saldi_help_texts_prefetch(array $keys, int $sprogId): void
{
	global $db_type;
	if (!function_exists('findtekst_session_var') || !function_exists('db_select') || !function_exists('db_fetch_array') || !function_exists('db_escape_string')) return;
	$missing = array();
	foreach ($keys as $k) {
		if (!isset($_SESSION[findtekst_session_var($k, $sprogId)]) && preg_match('/^[a-z0-9][a-z0-9_.-]{0,189}$/', $k)) $missing[] = "'" . db_escape_string($k) . "'";
	}
	if (!$missing) return;
	$col = "SELECT column_name FROM information_schema.columns WHERE table_name='tekster' AND column_name='tekst_key'"
		. (in_array($db_type, array('mysql', 'mysqli'), true) ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()");
	if (!db_fetch_array(db_select($col, __FILE__ . ' linje ' . __LINE__))) return;
	$lang = ($sprogId < 1 || $sprogId > 3) ? 1 : $sprogId;   // as findtekst()
	$q = db_select("SELECT tekst_key, tekst FROM tekster WHERE sprog_id = '$lang' AND tekst_key IN (" . implode(',', $missing) . ") ORDER BY id",
		__FILE__ . ' linje ' . __LINE__);
	$rows = array();
	while ($q && ($r = db_fetch_array($q))) $rows[] = $r;
	foreach (saldi_help_text_pick($rows) as $k => $text) {
		$_SESSION[findtekst_session_var($k, $sprogId)] = $text;   // the database encoding, as findtekst() caches it
	}
}
