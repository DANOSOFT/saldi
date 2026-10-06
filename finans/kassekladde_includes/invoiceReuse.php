<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/invoiceReuse.php --- ver 5.0.0 --- 2026-10-03 ---
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
// 20261003 CL/SZ SD-715 Created: warns when a kreditor's invoice number was used before, whatever the date or amount (find_dublet() only catches a line identical in every field).
//                Looks in open journals, posted kreditor entries (openpost) and the document pool.
//                Only the invoice side counts (Kredit = K, negative openpost): a payment carries the invoice's number on purpose.
//                Lines of the same voucher (journal + bilag) don't count either, since one invoice is often split over several lines.
// 20261006 CL/SZ SD-715 (CodeRabbit) Dropped the single-check "limit 20": the exclusions (same voucher, the line itself) run in PHP after
//                the query, so a real earlier use could be past the first 20 rows and never checked (e.g. an invoice split over 20+ lines of the same voucher).
// 20261003 CL/SZ SD-717 Archived pool documents don't count as an earlier use.

if (!function_exists('invoice_reuse_number')) {
	/**
	 * Invoice number as the check compares it: trimmed and lower-case. Values without a letter or digit
	 * ("-", used as a placeholder) and "0" count as no number, so they never warn.
	 *
	 * @param mixed $faktura Invoice number as typed or stored.
	 * @return string Normalised number, or '' when there is none.
	 */
	function invoice_reuse_number($faktura) {
		$faktura = is_scalar($faktura) ? trim((string)$faktura, ' ') : '';
		if ($faktura === '0' || !preg_match('/[[:alnum:]]/u', $faktura)) return '';
		return function_exists('mb_strtolower') ? mb_strtolower($faktura, 'UTF-8') : strtolower($faktura);
	}
}

if (!function_exists('invoice_reuse_pool_ready')) {
	/**
	 * Whether pool_files has the columns the check reads. The pool adds vendor_konto_id the first time it is opened
	 * in a version that has it, so a company that hasn't opened the pool since has the table without it.
	 *
	 * @return bool
	 */
	function invoice_reuse_pool_ready() {
		global $db, $db_type;
		static $ready = null;
		if ($ready !== null) return $ready;
		$qtxt = "select column_name from information_schema.columns where table_name = 'pool_files' ";
		$qtxt.= "and column_name in ('vendor_konto_id', 'invoice_number')";
		if ($db_type == 'mysql' || $db_type == 'mysqli') $qtxt .= " and table_schema = '" . db_escape_string($db) . "'";
		$found = 0;
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while (db_fetch_array($q)) $found++;
		return $ready = ($found == 2);
	}
}

if (!function_exists('invoice_reuse_find')) {
	/**
	 * Earlier uses of kreditor + invoice number, for any number of lines at once: one query per source.
	 *
	 * @param array<string|int, array{kontonr: string|int, faktura: string, line_id?: int, kladde_id?: int,
	 *        bilag?: string|int, pool_file?: string}> $checks Lines to check, keyed by the caller's own key.
	 * @return array<string|int, array<int, array{source: string, kladde_id: int, bilag: string, transdate: string,
	 *         filename: string}>> Per key, at most one hit per source (kladde, bogfort, pool), in that order.
	 */
	function invoice_reuse_find(array $checks) {
		$wanted = array();
		$kontonrs = $numbers = array();
		foreach ($checks as $key => $check) {
			$kontonr = is_scalar($check['kontonr'] ?? null) ? trim((string)$check['kontonr']) : '';
			$number = invoice_reuse_number($check['faktura'] ?? '');
			if (!ctype_digit($kontonr) || $number === '') continue;
			$wanted[$key] = array(
				'kontonr'   => $kontonr,
				'number'    => $number,
				'line_id'   => (int)($check['line_id'] ?? 0),
				'kladde_id' => (int)($check['kladde_id'] ?? 0),
				'bilag'     => trim((string)($check['bilag'] ?? '')),
				'pool_file' => (string)($check['pool_file'] ?? ''),
			);
			$kontonrs[$kontonr] = "'" . db_escape_string($kontonr) . "'";
			$numbers[$number] = "'" . db_escape_string($number) . "'";
		}
		$hits = array();
		if (!$wanted) return $hits;
		$kontonrList = implode(',', $kontonrs);
		$kontonrIntList = implode(',', array_map('intval', array_keys($kontonrs)));
		$numberList = implode(',', $numbers);
		$limit = '';

		// The same voucher (journal + bilag) and the line itself are never "used before"
		$sameVoucher = function($want, $kladdeId, $bilag) {
			return $want['kladde_id'] && (int)$kladdeId == $want['kladde_id'] && trim((string)$bilag) === $want['bilag'];
		};
		$add = function($key, $source, $row) use (&$hits) {
			foreach ($hits[$key] ?? array() as $hit) {
				if ($hit['source'] == $source) return;
			}
			$hits[$key][] = array(
				'source'    => $source,
				'kladde_id' => (int)($row['kladde_id'] ?? 0),
				'bilag'     => trim((string)($row['bilag'] ?? '')),
				'transdate' => (string)($row['transdate'] ?? ''),
				'filename'  => (string)($row['filename'] ?? ''),
			);
		};

		// 1. Lines in open journals
		$qtxt = "select k.id, k.kladde_id, k.bilag, k.kredit, lower(trim(k.faktura)) as fnr from kassekladde k, kladdeliste l ";
		$qtxt.= "where l.id = k.kladde_id and l.bogfort = '-' and k.k_type = 'K' and k.kredit in ($kontonrIntList) ";
		$qtxt.= "and lower(trim(k.faktura)) in ($numberList) order by k.kladde_id, k.id$limit";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			foreach ($wanted as $key => $want) {
				if ((string)(int)$r['kredit'] !== $want['kontonr'] || $r['fnr'] !== $want['number']) continue;
				if ((int)$r['id'] == $want['line_id'] || $sameVoucher($want, $r['kladde_id'], $r['bilag'])) continue;
				$add($key, 'kladde', $r);
			}
		}

		// 2. Posted kreditor invoices (negative amounts; payments are positive). No fiscal-year limit on purpose.
		$qtxt = "select a.kontonr, lower(trim(o.faktnr)) as fnr, o.refnr as bilag, o.transdate, o.kladde_id ";
		$qtxt.= "from openpost o, adresser a where a.id = o.konto_id and a.art = 'K' and a.kontonr in ($kontonrList) ";
		$qtxt.= "and o.amount < 0 and lower(trim(o.faktnr)) in ($numberList) order by o.transdate desc, o.id desc$limit";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			foreach ($wanted as $key => $want) {
				if (trim((string)$r['kontonr']) !== $want['kontonr'] || $r['fnr'] !== $want['number']) continue;
				if ($sameVoucher($want, $r['kladde_id'], $r['bilag'])) continue;
				$add($key, 'bogfort', $r);
			}
		}

		// 3. Other documents in the pool, by the kreditor they were matched to; not archived ones (SD-717)
		if (invoice_reuse_pool_ready()) {
			require_once __DIR__ . '/../../includes/docsIncludes/poolArchive.php';
			$qtxt = "select a.kontonr, lower(trim(p.invoice_number)) as fnr, p.filename from pool_files p, adresser a ";
			$qtxt.= "where a.id = p.vendor_konto_id and a.art = 'K' and a.kontonr in ($kontonrList) ";
			$qtxt.= "and lower(trim(p.invoice_number)) in ($numberList) and " . poolArchiveActiveSql('p.') . " order by p.id$limit";
			$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				foreach ($wanted as $key => $want) {
					if (trim((string)$r['kontonr']) !== $want['kontonr'] || $r['fnr'] !== $want['number']) continue;
					if ($want['pool_file'] !== '' && $r['filename'] === $want['pool_file']) continue;
					$add($key, 'pool', $r);
				}
			}
		}
		return $hits;
	}
}

if (!function_exists('invoice_reuse_html')) {
	/**
	 * The warning text and where the number was used, with a link where there is somewhere to go.
	 *
	 * @param array  $hits         Hits for one line, from invoice_reuse_find().
	 * @param string $faktura      The invoice number as the user typed it.
	 * @param string $poolLinkBase documents.php URL (with source parameters) the pool file name is appended to, or ''.
	 * @return string HTML (escaped), '' without hits.
	 */
	function invoice_reuse_html(array $hits, $faktura, $poolLinkBase = '') {
		global $sprog_id;
		if (!$hits) return '';
		$html = htmlspecialchars(str_replace('{nr}', trim((string)$faktura),
			findtekst('5286|Fakturanr. {nr} er allerede brugt på denne kreditor', $sprog_id)), ENT_QUOTES);
		foreach ($hits as $hit) {
			$href = '';
			if ($hit['source'] == 'kladde') {
				$text = str_replace(array('{kladde}', '{bilag}'), array($hit['kladde_id'], $hit['bilag']),
					findtekst('5287|Kladde {kladde}, bilag {bilag}', $sprog_id));
				$href = '../finans/kassekladde.php?kladde_id=' . $hit['kladde_id'];
			} elseif ($hit['source'] == 'bogfort') {
				$text = str_replace(array('{bilag}', '{dato}'), array($hit['bilag'], dkdato($hit['transdate'])),
					findtekst('5288|Bogført som bilag {bilag} den {dato}', $sprog_id));
				if ($hit['kladde_id']) $href = '../finans/kassekladde.php?kladde_id=' . $hit['kladde_id'];
			} else {
				$text = str_replace('{fil}', $hit['filename'], findtekst('5289|Andet bilag i puljen: {fil}', $sprog_id));
				if ($poolLinkBase !== '') $href = $poolLinkBase . '&openPool=1&poolFile=' . rawurlencode($hit['filename']);
			}
			$text = htmlspecialchars($text, ENT_QUOTES);
			$html .= "<br>" . ($href !== ''
				? "<a href='" . htmlspecialchars($href, ENT_QUOTES) . "' target='_blank' rel='noopener'>$text</a>"
				: $text);
		}
		return $html;
	}
}
