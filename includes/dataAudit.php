<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- includes/dataAudit.php --- ver 5.1.1 --- 2026.11.25 ---
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
// 20261114 CL/ASR Created: changes to master data go in the activity log (audit_log), whichever page makes them (owner, 14 Nov 2026).
//                 db_modify() (includes/db_query.php) calls data_audit_before() just before an insert, update or delete runs and data_audit_after() when it has run.
//                 Watched tables: varer (Vare), adresser (Kunde, Leverandør, Eget firma) and kontoplan (Konto); see data_audit_tables().
//                 An update is logged with the fields that changed (before → after), read from the rows before and after, so no SQL is parsed for values.
//                 Stock and cost price are logged only from the pages where a person sets them (varekort, lagerregulering, optælling), not from sales, goods received or recalculations.
//                 Computed columns (saldo, modtime) are never logged.
//                 More than DATA_AUDIT_MAX entries in one request (an import, a new fiscal year) end in one summary line instead.
//                 Never in the way: every step is guarded, and a failure only leaves the change unlogged.
// 20261114 CL/ASR Invoicing and crediting are logged (owner, 14 Nov 2026: who credited a large order must be traceable; Vor ref on the document is the seller and can be changed).
//                 Table ordrer, sales and purchases (art DO, DK, KO, KK; not the POS receipts, art PO): an order whose status goes from under 3 to 3 or more is logged as Faktureret or Krediteret, with number, account, amount and the invoice credited.
//                 A deleted order is logged too; other changes to orders (every save) are not.
// 20261124 CL/ASR Integration: the look at the rows before a write reads LIKE as master's db_modify() now runs it (db_normalize_like_operators), so quoted text with ' like ' in it is found as written.
// 20261124 CL/ASR The activity log's reads run under a savepoint inside the caller's transaction, so a read that fails no longer aborts it.
//                 An insert is logged by name only when it added exactly one row (pg_affected_rows()), drew its id from the sequence and has no ON CONFLICT, nextval() or setval(); others are counted.
//                 Its own entries are written under a savepoint with db_modify() in webservice mode (data_audit_log()), so one that cannot be written never ends the request or the caller's transaction.
//                 What such a write prints goes to the server's error log, not into the page.
// 20261125 CL/ASR A WHERE (or RETURNING) after a line break is found, so such an update is logged with its change instead of only counted.
//                 A value that is not UTF-8 is read as Windows-1252, as db_escape_string() reads it (€ is 0x80 there; ISO-8859-1 made it a control character).
//                 A count made inside a transaction is written at the end of the request only when that transaction committed (txid_status()).
//                 Its WHERE is found in the tokens db_normalize_like_operators() reads (comments, strings, E'' strings, quoted names, dollar strings, whole words), and each count's status is asked of any open connection, so one closed before the end of the request loses no line.
//                 A statement whose look at the rows cannot run is counted instead of left out.

if (!defined('DATA_AUDIT_MAX')) define('DATA_AUDIT_MAX', 100);

if (!function_exists('data_audit_tables')):

/**
 * The watched tables: the object's name, the columns that name it, the columns never logged, the readable field names.
 *
 * @return array<string, array<string, mixed>>
 */
function data_audit_tables(): array
{
	return array(
		'varer' => array(
			'type'   => 'Vare',
			'name'   => array('varenr', 'beskrivelse'),
			'skip'   => array('modtime'),
			'manual' => array('beholdning', 'kostpris'),   // only from the pages in data_audit_manual_pages()
			'labels' => array(
				'varenr' => 'Varenr.', 'stregkode' => 'Stregkode', 'beskrivelse' => 'Beskrivelse', 'enhed' => 'Enhed', 'enhed2' => 'Enhed 2',
				'gruppe' => 'Varegruppe', 'salgspris' => 'Salgspris', 'kostpris' => 'Kostpris', 'beholdning' => 'Beholdning',
				'min_lager' => 'Min. lager', 'max_lager' => 'Maks. lager', 'lukket' => 'Udgået', 'notes' => 'Bemærkning', 'notesinternal' => 'Intern note',
				'location' => 'Lokation', 'retail_price' => 'Vejl. udsalgspris', 'special_price' => 'Tilbudspris', 'kategori' => 'Kategori',
				'publiceret' => 'Publiceret', 'serienr' => 'Serienr.', 'samlevare' => 'Samlevare', 'prisgruppe' => 'Prisgruppe', 'rabatgruppe' => 'Rabatgruppe',
				'trademark' => 'Varemærke', 'colli' => 'Kolli', 'netweight' => 'Nettovægt', 'grossweight' => 'Bruttovægt', 'varenr_alias' => 'Alias-varenr.',
				'special_from_date' => 'Tilbud fra', 'special_to_date' => 'Tilbud til', 'has_due_date' => 'Udløbsdato', 'default_shelf_life_days' => 'Holdbarhed (dage)', 'vat_price' => 'Pris inkl. moms', 'provision' => 'Provision',
			),
		),
		'adresser' => array(
			'type'   => 'Konto',   // Kunde, Leverandør or Eget firma from the row's art (data_audit_object())
			'name'   => array('kontonr', 'firmanavn'),
			'skip'   => array('modtime', 'saldo', 'invoiced', 'long', 'lat'),
			'manual' => array(),
			'labels' => array(
				'kontonr' => 'Kontonr.', 'firmanavn' => 'Navn', 'fornavn' => 'Fornavn', 'efternavn' => 'Efternavn', 'addr1' => 'Adresse', 'addr2' => 'Adresse 2',
				'postnr' => 'Postnr.', 'bynavn' => 'By', 'land' => 'Land', 'kontakt' => 'Kontakt', 'tlf' => 'Tlf.', 'mobile' => 'Mobil', 'email' => 'E-mail',
				'web' => 'Hjemmeside', 'cvrnr' => 'CVR-nr.', 'ean' => 'EAN', 'institution' => 'Institution', 'bank_navn' => 'Bank', 'bank_reg' => 'Reg.nr.',
				'bank_konto' => 'Bankkonto', 'iban' => 'IBAN', 'swift' => 'SWIFT', 'kreditmax' => 'Kreditmaks', 'betalingsbet' => 'Betalingsbetingelse',
				'betalingsdage' => 'Betalingsdage', 'gruppe' => 'Gruppe', 'rabat' => 'Rabat', 'rabatgruppe' => 'Rabatgruppe', 'kontoansvarlig' => 'Kontoansvarlig',
				'lukket' => 'Lukket', 'status' => 'Status', 'notes' => 'Bemærkning', 'mailfakt' => 'Faktura pr. mail', 'udskriv_til' => 'Udskriv til',
				'momskonto' => 'Momskonto', 'kategori' => 'Kategori', 'kontotype' => 'Kontotype', 'art' => 'Art', 'pbs' => 'Betalingsservice', 'pbs_nr' => 'PBS-nr.',
			),
		),
		'ordrer' => array(
			'type'   => 'Ordre',   // Faktura, Kreditnota, Købsfaktura or Købskreditnota from art (data_audit_object())
			'name'   => array('fakturanr', 'firmanavn'),
			'skip'   => array(),
			'manual' => array(),
			'events' => true,      // only invoicing, crediting and deleting (data_audit_order_events()), not every save
			'arts'   => array('DO', 'DK', 'KO', 'KK'),
			'labels' => array(),
		),
		'kontoplan' => array(
			'type'   => 'Konto i kontoplanen',
			'name'   => array('kontonr', 'beskrivelse'),
			'skip'   => array('modtime', 'saldo'),
			'manual' => array(),
			'labels' => array(
				'kontonr' => 'Kontonr.', 'beskrivelse' => 'Navn', 'kontotype' => 'Kontotype', 'moms' => 'Moms', 'fra_kto' => 'Fra konto', 'til_kto' => 'Til konto',
				'lukket' => 'Lukket', 'primo' => 'Primo', 'genvej' => 'Genvej', 'modkonto' => 'Modkonto', 'valuta' => 'Valuta', 'overfor_til' => 'Overføres til',
				'anvendelse' => 'Anvendelse', 'regnskabsaar' => 'Regnskabsår',
			),
		),
	);
}

/** The pages where a person sets stock and cost price by hand; elsewhere (sales, goods received, recalculations) they are left out. */
function data_audit_manual_pages(): array
{
	return array('lager/varekort.php', 'lager/lagerregulering.php', 'lager/optalling.php');
}

/** The page that made the change, as lager/varekort.php. */
function data_audit_page(): string
{
	$s = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
	if ($s === '') return PHP_SAPI === 'cli' ? 'cli' : '';
	$parts = explode('/', trim($s, '/'));
	return implode('/', array_slice($parts, -2));
}

/**
 * A read of our own on the request's connection: never the error box or the error mail of db_select(), only rows or null.
 * Inside the caller's transaction the read runs under a savepoint of its own, so a read that fails is rolled back to it and
 * the caller's transaction goes on; a transaction that has already failed, or a connection that is busy, is not read from.
 */
function data_audit_rows($conn, string $sql): ?array
{
	if (!$conn) return null;
	$status = pg_transaction_status($conn);
	if ($status !== PGSQL_TRANSACTION_IDLE && $status !== PGSQL_TRANSACTION_INTRANS) return null;
	$inTransaction = ($status === PGSQL_TRANSACTION_INTRANS);
	if ($inTransaction && !@pg_query($conn, 'savepoint data_audit')) return null;
	$res = @pg_query($conn, $sql);
	if (!$res) {
		if ($inTransaction) {
			@pg_query($conn, 'rollback to savepoint data_audit');
			@pg_query($conn, 'release savepoint data_audit');
		}
		return null;
	}
	$rows = pg_fetch_all($res);
	if ($inTransaction) @pg_query($conn, 'release savepoint data_audit');
	return is_array($rows) ? $rows : array();
}

/**
 * Where a clause begins in a statement ($word as ' where '; not the where of a sub-select), or -1. The statement is
 * read in the tokens includes/db_query.php db_normalize_like_operators() reads it in, which db_modify() runs every query
 * through: -- and nested block comments, strings ('' inside, E'' and backslashes as $standardConformingStrings says,
 * a string continued on the next line), quoted identifiers, dollar-quoted strings, and whole words (a $ inside a word
 * is part of it). The word counts outside brackets with whitespace on both sides; the offset is the whitespace before
 * it, and strlen($word) after the offset is the clause. Unterminated quoting reads to the end: -1.
 */
function data_audit_find(string $sql, string $word, bool $standardConformingStrings = true): int
{
	$core = strtolower(trim($word));
	$length = strlen($sql);
	$depth = 0;
	for ($offset = 0; $offset < $length;) {
		$start = $offset;
		$char = $sql[$offset];
		$pair = substr($sql, $offset, 2);
		if ($pair === '--') {
			$offset += strcspn($sql, "\r\n", $offset);
		} elseif ($pair === '/*') {
			$offset += 2;
			$nest = 1;
			while ($offset < $length && $nest > 0) {
				$pair = substr($sql, $offset, 2);
				if ($pair === '/*' || $pair === '*/') {
					$nest += $pair === '/*' ? 1 : -1;
					$offset += 2;
				} else {
					$offset++;
				}
			}
			if ($nest > 0) return -1;
		} elseif ($char === "'" || $char === '"' || $pair === "E'" || $pair === "e'") {
			$escapePrefix = $pair === "E'" || $pair === "e'";
			$quote = $escapePrefix ? "'" : $char;
			$escaped = $escapePrefix || ($quote === "'" && !$standardConformingStrings);
			$offset += $escapePrefix ? 2 : 1;
			$closed = false;
			while ($offset < $length) {
				if ($escaped && $sql[$offset] === '\\') {
					$offset = min($length, $offset + 2);
				} elseif ($sql[$offset] === $quote) {
					$offset++;
					if ($offset < $length && $sql[$offset] === $quote) {
						$offset++;
					} elseif ($quote === "'" && preg_match("/\\G(?:[ \\t\\f\\v]|--[^\\r\\n]*)*[\\r\\n](?:[ \\t\\f\\v\\r\\n]|--[^\\r\\n]*[\\r\\n])*'/", $sql, $continuation, 0, $offset)) {
						$offset += strlen($continuation[0]);   // the string goes on after a line break
					} else {
						$closed = true;
						break;
					}
				} else {
					$offset++;
				}
			}
			if (!$closed) return -1;
		} elseif ($char === '$' && preg_match('/\G\$(?:[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)?\$/', $sql, $match, 0, $offset)) {
			$end = strpos($sql, $match[0], $offset + strlen($match[0]));
			if ($end === false) return -1;
			$offset = $end + strlen($match[0]);
		} elseif (preg_match('/\G[a-zA-Z_\x80-\xff][a-zA-Z0-9_$\x80-\xff]*/', $sql, $match, 0, $offset)) {
			$wl = strlen($match[0]);
			if ($depth === 0 && strtolower($match[0]) === $core && $start > 0 && ctype_space($sql[$start - 1])
				&& $start + $wl < $length && ctype_space($sql[$start + $wl])) {
				return $start - 1;
			}
			$offset += $wl;
		} else {
			if ($char === '(') $depth++;
			elseif ($char === ')') $depth = max(0, $depth - 1);
			$offset++;
		}
	}
	return -1;
}

/**
 * Before an insert, update or delete: what the watched rows look like now. Null when the statement is not on a watched table.
 *
 * @return array<string, mixed>|null
 */
function data_audit_before(string $qtext, bool $global, $conn): ?array
{
	global $db_type;
	static $busy = false;
	try {
		if ($busy || $global || ($db_type !== 'postgresql' && $db_type !== 'postgres' && $db_type !== '' && $db_type !== null)) return null;
		if (!function_exists('audit_log') || !function_exists('audit_ready')) return null;
		// As db_modify() runs it on PostgreSQL: master's normalizer turns LIKE into ILIKE outside quoted text, comments and
		// identifiers, so a value such as 'A like B' is looked up as written.
		$q = ltrim($qtext);
		$q = function_exists('db_normalize_like_operators') && $conn
			? db_normalize_like_operators($q, pg_parameter_status($conn, 'standard_conforming_strings') !== 'off')
			: str_replace(' like ', ' ilike ', $q);
		if (!preg_match('/^(update|insert\s+into|delete\s+from)\s+([a-z_][a-z0-9_]*)\b/i', $q, $m)) return null;
		$op = strtolower(substr($m[1], 0, 6));
		$table = strtolower($m[2]);
		$tables = data_audit_tables();
		if (!isset($tables[$table]) || !audit_ready()) return null;
		$ctx = array('op' => $op, 'table' => $table, 'conn' => $conn, 'rows' => array(), 'bulk' => false);
		if ($op === 'insert') {
			// The new row is found afterwards by the id the statement drew from the table's sequence (currval()). An insert that
			// names the id itself or has no column list may draw none, with ON CONFLICT an id can be drawn for a row that is
			// skipped or updated instead, and a statement that calls nextval() or setval() itself can move the sequence past the
			// new row, so these are counted instead (also when the words are only in a value: never a wrong name).
			if (!preg_match('/^insert\s+into\s+[a-z_][a-z0-9_]*\s*\(([^)]*)\)/i', $q, $c) || preg_match('/(^|,)\s*"?id"?\s*(,|$)/i', $c[1])
				|| preg_match('/\bon\s+conflict\b|\b(nextval|setval)\s*\(/i', $q)) {
				$ctx['bulk'] = true;
			}
			return $ctx;
		}
		$standard = !$conn || pg_parameter_status($conn, 'standard_conforming_strings') !== 'off';
		$w = data_audit_find($q, ' where ', $standard);
		if ($w < 0) { $ctx['bulk'] = true; return $ctx; }   // a whole table: counted, not listed
		$where = substr($q, $w + 7);
		$r = data_audit_find($where, ' returning ', $standard);
		if ($r >= 0) $where = substr($where, 0, $r);
		$where = rtrim($where, " ;\n\r\t");
		$busy = true;
		$rows = data_audit_rows($conn, "select * from $table where $where\nlimit " . (DATA_AUDIT_MAX + 1));   // a -- comment ends at the line
		$busy = false;
		if ($rows === null) {   // the look could not run (its WHERE is not one alone, or not read right): counted, not lost
			$ctx['bulk'] = true;
			return $ctx;
		}
		if (count($rows) > DATA_AUDIT_MAX) $ctx['bulk'] = true;
		$ctx['rows'] = $rows;
		return $ctx;
	} catch (\Throwable $e) {
		$busy = false;
		return null;
	}
}

/** After the statement has run: the audit entries for what changed. */
function data_audit_after(array $ctx, $result = null): void
{
	static $busy = false;
	if ($busy) return;
	$busy = true;
	try {
		$table = $ctx['table']; $def = data_audit_tables()[$table]; $conn = $ctx['conn'];
		if ($ctx['bulk'] && !empty($def['events']) && $ctx['op'] !== 'delete') return;   // a mass status change of orders (POS day end, updates): not an invoicing by a person
		if ($ctx['bulk']) {
			$n = ($result !== null && $result !== false) ? (int) @pg_affected_rows($result) : count($ctx['rows']);
			if ($n > 0) data_audit_count($table, 'data.' . ($ctx['op'] === 'insert' ? 'created' : ($ctx['op'] === 'delete' ? 'deleted' : 'updated')), $n, $conn);
			return;
		}
		if (!empty($def['events'])) { data_audit_order_events($ctx, $def); return; }
		if ($ctx['op'] === 'insert') {
			// currval() names the last row the statement added, so only an insert of exactly one row is logged by name.
			$n = ($result !== null && $result !== false) ? (int) @pg_affected_rows($result) : 1;
			if ($n !== 1) {
				if ($n > 1) data_audit_count($table, 'data.created', $n, $conn);
				return;
			}
			$rows = data_audit_rows($conn, "select * from $table where id = currval(pg_get_serial_sequence('$table', 'id'))");
			if ($rows) data_audit_entry($table, 'data.created', $rows[0], data_audit_snapshot($rows[0], $def));
			return;
		}
		if ($ctx['op'] === 'delete') {
			foreach ($ctx['rows'] as $row) data_audit_entry($table, 'data.deleted', $row, data_audit_snapshot($row, $def));
			return;
		}
		// update: the same rows after, by id, and the fields that differ
		$ids = array();
		foreach ($ctx['rows'] as $row) if (isset($row['id']) && is_numeric($row['id'])) $ids[] = (int) $row['id'];
		if (!$ids) return;
		$after = array();
		foreach ((array) data_audit_rows($conn, "select * from $table where id in (" . implode(',', $ids) . ")") as $row) $after[(int) $row['id']] = $row;
		$manualOk = in_array(data_audit_page(), data_audit_manual_pages(), true);
		foreach ($ctx['rows'] as $row) {
			$id = (int) $row['id'];
			if (!isset($after[$id])) continue;
			$changes = array();
			foreach ($after[$id] as $col => $new) {
				if (in_array($col, $def['skip'], true) || $col === 'id') continue;
				if (!$manualOk && in_array($col, $def['manual'], true)) continue;
				// the item card stamps today's date in the offer period on every save; it only matters with an offer price
				if ($table === 'varer' && strpos($col, 'special_') === 0 && $col !== 'special_price' && (float) ($after[$id]['special_price'] ?? 0) == 0) continue;
				$old = array_key_exists($col, $row) ? $row[$col] : null;
				if (data_audit_same($old, $new)) continue;
				$changes[] = data_audit_label($def, $col) . ': ' . data_audit_value($old) . ' → ' . data_audit_value($new);
			}
			if ($changes) data_audit_entry($table, 'data.updated', $after[$id], implode(' · ', $changes));
		}
	} catch (\Throwable $e) {
		// an unlogged change, never a failed page
	} finally {
		$busy = false;
	}
}

function data_audit_same($a, $b): bool
{
	if ($a === $b) return true;
	$a = trim((string) $a); $b = trim((string) $b);
	if ($a === $b) return true;
	return is_numeric($a) && is_numeric($b) && (float) $a == (float) $b;   // 5 and 5.000
}

function data_audit_value($v): string
{
	$s = trim((string) $v);
	if ($s === '') return '(tom)';
	if ($s === 't' || $s === 'on') return 'Ja';    // PostgreSQL's booleans and Saldi's ticked boxes
	if ($s === 'f' || $s === 'off') return 'Nej';
	if (!mb_check_encoding($s, 'UTF-8')) $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');   // as db_escape_string() reads it
	if (is_numeric($s) && strpos($s, '.') !== false) $s = str_replace('.', ',', rtrim(rtrim($s, '0'), '.'));   // 5.000 as 5, 17.50 as 17,5
	return mb_strlen($s) > 60 ? mb_substr($s, 0, 57) . '…' : $s;
}

function data_audit_label(array $def, string $col): string
{
	return isset($def['labels'][$col]) ? $def['labels'][$col] : $col;
}

/** Invoicing, crediting and deleting of sales and purchase documents; every other change to an order is left out. */
function data_audit_order_events(array $ctx, array $def): void
{
	$conn = $ctx['conn'];
	$arts = $def['arts'];
	if ($ctx['op'] === 'insert') return;
	if ($ctx['op'] === 'delete') {
		foreach ($ctx['rows'] as $row) {
			if (!in_array(strtoupper(trim((string) $row['art'])), $arts, true)) continue;
			data_audit_entry('ordrer', 'data.deleted', $row, data_audit_order_text($row, $conn, false));
		}
		return;
	}
	$ids = array();
	foreach ($ctx['rows'] as $row) {
		if (in_array(strtoupper(trim((string) $row['art'])), $arts, true) && (int) $row['status'] < 3 && isset($row['id'])) $ids[] = (int) $row['id'];
	}
	if (!$ids) return;
	foreach ((array) data_audit_rows($conn, "select * from ordrer where id in (" . implode(',', $ids) . ")") as $row) {
		if ((int) $row['status'] < 3) continue;
		$credit = in_array(strtoupper(trim((string) $row['art'])), array('DK', 'KK'), true);
		data_audit_entry('ordrer', $credit ? 'data.credited' : 'data.invoiced', $row, data_audit_order_text($row, $conn, true));
	}
}

/** "Kunde 1000 Testkunde ApS · 1.250,00 DKK inkl. moms · Fakturadato 14-11-2026 · krediterer faktura 5". */
function data_audit_order_text(array $row, $conn, bool $invoiced): string
{
	$art = strtoupper(trim((string) $row['art']));
	$out = array();
	$who = trim(trim((string) ($row['kontonr'] ?? '')) . ' ' . trim((string) ($row['firmanavn'] ?? '')));
	if ($who !== '') $out[] = (in_array($art, array('KO', 'KK'), true) ? 'Leverandør ' : 'Kunde ') . data_audit_value($who);
	$total = (float) ($row['sum'] ?? 0) + (float) ($row['moms'] ?? 0);
	$out[] = number_format($total, 2, ',', '.') . ' ' . (trim((string) ($row['valuta'] ?? '')) ?: 'DKK') . ' inkl. moms';
	if ($invoiced && trim((string) ($row['fakturadate'] ?? '')) !== '') $out[] = 'Fakturadato ' . date('d-m-Y', strtotime((string) $row['fakturadate']));
	if (!$invoiced) {
		$st = (int) ($row['status'] ?? 0);
		$out[] = 'Status: ' . ($st >= 3 ? 'faktureret' : ($st == 2 ? 'leveret' : ($st == 1 ? 'ordre' : 'tilbud')));
		if (trim((string) ($row['ordrenr'] ?? '')) !== '') $out[] = 'Ordrenr. ' . data_audit_value($row['ordrenr']);
	}
	$kred = (int) ($row['kred_ord_id'] ?? 0);
	if ($kred > 0) {
		$orig = data_audit_rows($conn, "select fakturanr from ordrer where id = $kred");
		if ($orig && trim((string) $orig[0]['fakturanr']) !== '') $out[] = 'krediterer faktura ' . data_audit_value($orig[0]['fakturanr']);
	}
	return implode(' · ', $out);
}

/** A created or deleted row's filled, named fields. */
function data_audit_snapshot(array $row, array $def): string
{
	$out = array();
	foreach ($def['labels'] as $col => $label) {
		if (!array_key_exists($col, $row) || in_array($col, $def['name'], true)) continue;
		$v = trim((string) $row[$col]);
		if ($v === '' || $v === '0' || $v === 'f' || $v === 'off' || (is_numeric($v) && (float) $v == 0)) continue;
		$out[] = $label . ': ' . data_audit_value($v);
		if (count($out) >= 8) break;
	}
	return implode(' · ', $out);
}

/** The object as the activity log shows it: its kind and its number and name, as they read at the time. */
function data_audit_object(string $table, array $row): array
{
	$def = data_audit_tables()[$table];
	$type = $def['type'];
	if ($table === 'adresser') {
		$art = strtoupper(trim((string) ($row['art'] ?? '')));
		$type = ($art === 'D') ? 'Kunde' : (($art === 'K') ? 'Leverandør' : (($art === 'S') ? 'Eget firma' : 'Adresse'));
	}
	if ($table === 'ordrer') {
		$art = strtoupper(trim((string) ($row['art'] ?? '')));
		$doc = array('DO' => 'Faktura', 'DK' => 'Kreditnota', 'KO' => 'Købsfaktura', 'KK' => 'Købskreditnota');
		$type = ((int) ($row['status'] ?? 0) >= 3 && isset($doc[$art])) ? $doc[$art] : (($art === 'DK' || $art === 'KK') ? 'Kreditnota' : 'Ordre');
		$nr = trim((string) ($row['fakturanr'] ?? '')) !== '' ? trim((string) $row['fakturanr']) : trim((string) ($row['ordrenr'] ?? ''));
		return array($type, $nr);
	}
	$parts = array();
	foreach ($def['name'] as $col) {
		$v = trim((string) ($row[$col] ?? ''));
		if ($v !== '') $parts[] = data_audit_value($v);
	}
	return array($type, implode(' · ', $parts));
}

/**
 * audit_log() for an entry the activity log makes by itself, on the request's connection (db_modify()'s). Inside the
 * caller's transaction it runs under a savepoint, and db_modify() runs in its webservice mode, which returns instead of
 * showing the error box and ending the request; an entry that cannot be written is left out, the caller's transaction goes
 * on, and the caller's own failure flag ($db_modify_fejl, which the posting pages check) is left as it was. What the write
 * prints is kept out of the page and goes to the server's error log.
 */
function data_audit_log(string $handling, string $details, string $type, string $id): void
{
	$conn = $GLOBALS['connection'] ?? null;
	$isPg = $conn instanceof \PgSql\Connection;
	$status = $isPg ? pg_transaction_status($conn) : null;
	if ($isPg && $status !== PGSQL_TRANSACTION_IDLE && $status !== PGSQL_TRANSACTION_INTRANS) return;
	$inTransaction = $isPg && $status === PGSQL_TRANSACTION_INTRANS;
	if ($inTransaction && !@pg_query($conn, 'savepoint data_audit_log')) return;
	$had = array();
	foreach (array('webservice', 'db_modify_fejl') as $g) $had[$g] = array_key_exists($g, $GLOBALS) ? array($GLOBALS[$g]) : null;
	$GLOBALS['webservice'] = true;
	ob_start();   // what a failed entry prints (a warning, the error text on a develop server, an alert) stays out of the page
	try {
		audit_log($handling, $details, $type, $id, mb_substr(data_audit_page(), 0, 30));
	} catch (\Throwable $e) {
		error_log('dataAudit: ' . $handling . ' not logged: ' . $e->getMessage());
	} finally {
		$printed = ob_get_clean();
		if ($printed !== '' && $printed !== false) error_log('dataAudit: ' . $handling . ': ' . mb_substr(strip_tags($printed), 0, 500));
		foreach ($had as $g => $v) {
			if ($v === null) unset($GLOBALS[$g]); else $GLOBALS[$g] = $v[0];
		}
	}
	if ($inTransaction) {
		if (pg_transaction_status($conn) === PGSQL_TRANSACTION_INERROR) @pg_query($conn, 'rollback to savepoint data_audit_log');
		@pg_query($conn, 'release savepoint data_audit_log');
	}
}

function data_audit_entry(string $table, string $handling, array $row, string $details): void
{
	static $n = 0;
	if (++$n > DATA_AUDIT_MAX) { data_audit_count($table, $handling, 1); return; }
	list($type, $id) = data_audit_object($table, $row);
	data_audit_log($handling, $details, $type, mb_substr($id, 0, 60));
}

/**
 * Many changes in one request (an import, a new fiscal year): counted, and one line at the end. A count made inside a
 * transaction is kept with the transaction's id, and the line counts it only when that transaction committed: after a
 * rollback, or a transaction still open when the request ends (rolled back as the connection closes), nothing changed.
 * $conn is the statement's connection (db_modify()'s); without it the request's.
 */
function data_audit_count(string $table, string $handling, int $count, $conn = null): void
{
	static $counts = null, $statusConns = array();
	if ($conn === null) $conn = isset($GLOBALS['connection']) ? $GLOBALS['connection'] : null;
	if ($counts === null) {
		$counts = array();
		register_shutdown_function(function () use (&$counts, &$statusConns) {
			$total = array(); $status = array();
			foreach ($counts as $key => $c) {
				try {
					list($t, $hnd, $xid) = explode('|', $key);
					if ($xid !== '') {
						if (!array_key_exists($xid, $status)) $status[$xid] = data_audit_xid_status($xid, $statusConns);
						if ($status[$xid] === 'aborted' || $status[$xid] === 'in progress') continue;
					}
					$total[$t . '|' . $hnd] = (isset($total[$t . '|' . $hnd]) ? $total[$t . '|' . $hnd] : 0) + $c;
				} catch (\Throwable $e) {
				}
			}
			foreach ($total as $key => $c) {
				try {
					list($t, $hnd) = explode('|', $key);
					$def = data_audit_tables()[$t];
					$what = ($hnd === 'data.created') ? 'oprettet' : (($hnd === 'data.deleted') ? 'slettet' : (($hnd === 'data.invoiced') ? 'faktureret' : (($hnd === 'data.credited') ? 'krediteret' : 'ændret')));
					$noun = ($t === 'varer') ? 'varer' : (($t === 'kontoplan') ? 'konti i kontoplanen' : (($t === 'ordrer') ? 'ordrer' : 'kunder/leverandører'));
					data_audit_log('data.bulk', $c . ' ' . $noun . ' ' . $what . ' i én handling', $def['type'], '');
				} catch (\Throwable $e) {
				}
			}
		});
	}
	$xid = '';
	if ($conn instanceof \PgSql\Connection && pg_transaction_status($conn) === PGSQL_TRANSACTION_INTRANS) {
		$r = data_audit_rows($conn, 'select txid_current_if_assigned() as x');
		if ($r && ctype_digit((string) $r[0]['x'])) {
			$xid = (string) $r[0]['x'];
			$statusConns[spl_object_id($conn)] = $conn;
		}
	}
	$key = $table . '|' . $handling . '|' . $xid;
	$counts[$key] = (isset($counts[$key]) ? $counts[$key] : 0) + $count;
}

/**
 * What became of transaction $xid: 'committed', 'aborted', 'in progress', or '' when no connection can tell (then the
 * count is written, as before txid_status() was asked). Transaction ids are the database server's, so any open
 * connection to it answers: the request's first, then those the counts were made on. One that is closed is passed over.
 *
 * @param array<int, mixed> $conns
 */
function data_audit_xid_status(string $xid, array $conns): string
{
	$candidates = array_merge(isset($GLOBALS['connection']) ? array($GLOBALS['connection']) : array(), array_values($conns));
	foreach ($candidates as $cn) {
		try {
			if (!($cn instanceof \PgSql\Connection) || pg_connection_status($cn) !== PGSQL_CONNECTION_OK) continue;
			$r = data_audit_rows($cn, "select txid_status($xid) as s");
			if ($r) return (string) $r[0]['s'];
		} catch (\Throwable $e) {   // a connection closed with pg_close() (db_close()) throws
		}
	}
	return '';
}

endif;
