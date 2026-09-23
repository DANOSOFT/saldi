<?php
// --- includes/docsIncludes/poolVendorMatcher.php ---
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260922 CL/LAH Leverandørforslag fra AI-scan (kravspec "Bilagsflow"): normalisering af
//                  CVR/IBAN/navn og match af den scannede leverandør mod kreditorer i adresser
//                  (art = 'K'), i rækkefølgen cvr -> bank -> navn. Rene funktioner uden DB-kald
//                  øverst (dækket af tests/characterization/includes/docsIncludes/
//                  PoolVendorMatcherCharacterizationTest.test.php); de få DB-adaptere nederst.
//                  Navnelighed er en PHP-udgave af pg_trgm's similarity(), så resultatet er
//                  ens på Postgres og MySQL og kan testes uden database.

if (!function_exists('normalizePoolVendorCvr')) {
	/**
	 * Normalize a CVR/VAT number as read on an invoice.
	 *
	 * "DK 12 34 56 78", "DK12345678", "CVR: 12345678" and "12 34 56 78" all become "12345678".
	 * Foreign VAT numbers keep their country prefix but lose spaces, dots and dashes
	 * ("SE 5566-7788 9901" -> "SE556677889901").
	 *
	 * @param string|null $raw
	 * @return string|null Null when $raw is empty or does not look like a CVR/VAT number.
	 */
	function normalizePoolVendorCvr($raw) {
		if ($raw === null) return null;
		$value = strtoupper(trim((string) $raw));
		if ($value === '') return null;

		// A leading label ("CVR:", "CVR-nr.:", "VAT no:", "SE-nr.") is only a label when a
		// separator follows it - "SE556677889901" is a Swedish VAT number, not "SE" + digits.
		// Try the label-stripped form first ("SE-nr. 12345678" would otherwise pass as a
		// foreign VAT number), then the raw form ("SE 5566-7788 9901").
		$stripped = preg_replace('/^(CVR|VAT|MOMS|ORG|TAX|SE)([\s.\-]*(NR|NO|NUMMER|NUMBER))?\.?\s*[:\s]\s*/', '', $value, 1);
		foreach (array($stripped, $value) as $candidate) {
			$candidate = preg_replace('/[\s.\-\/:]/', '', $candidate);
			if ($candidate === '') continue;
			if (preg_match('/^(DK)?(\d{8})$/', $candidate, $m)) return $m[2];
			if (preg_match('/^[A-Z]{2}[A-Z0-9]{2,13}$/', $candidate) && preg_match('/\d.*\d/', $candidate)) return $candidate;
		}
		return null;
	}
}

if (!function_exists('normalizePoolVendorIban')) {
	/**
	 * Normalize an IBAN: uppercase, no spaces. Returns null unless it has IBAN shape
	 * (two letters, two check digits, 11-30 alphanumerics).
	 *
	 * @param string|null $raw
	 * @return string|null
	 */
	function normalizePoolVendorIban($raw) {
		if ($raw === null) return null;
		$value = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $raw));
		if ($value === '') return null;
		if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $value)) return $value;
		return null;
	}
}

if (!function_exists('normalizePoolVendorBankAccount')) {
	/**
	 * Normalize a Danish reg.nr. + kontonr. pair to "REG-KONTO" ("0892-1001348").
	 * Reg keeps its leading zeros (they are significant), konto drops them so
	 * "0001001348" and "1001348" compare equal.
	 *
	 * @param string|null $reg
	 * @param string|null $konto
	 * @return string|null Null unless both parts contain digits.
	 */
	function normalizePoolVendorBankAccount($reg, $konto) {
		$reg = preg_replace('/\D/', '', (string) $reg);
		$konto = ltrim(preg_replace('/\D/', '', (string) $konto), '0');
		if ($reg === '' || $konto === '') return null;
		return str_pad($reg, 4, '0', STR_PAD_LEFT) . '-' . $konto;
	}
}

if (!function_exists('poolVendorBankKeys')) {
	/**
	 * Every key a party's bank details can be matched on. A Danish IBAN
	 * (DKkk RRRR KKKKKKKKKK) also yields its reg/konto key, so an invoice that only
	 * prints the IBAN still matches a kreditor that only has reg.nr./kontonr. filled in.
	 *
	 * @param string|null $iban
	 * @param string|null $reg
	 * @param string|null $konto
	 * @return string[] Keys of the form "IBAN:<iban>" and "ACCT:<reg>-<konto>", possibly empty.
	 */
	function poolVendorBankKeys($iban, $reg, $konto) {
		$keys = array();
		$iban = normalizePoolVendorIban($iban);
		if ($iban !== null) {
			$keys[] = 'IBAN:' . $iban;
			if (substr($iban, 0, 2) === 'DK' && strlen($iban) === 18) {
				$acct = normalizePoolVendorBankAccount(substr($iban, 4, 4), substr($iban, 8, 10));
				if ($acct !== null) $keys[] = 'ACCT:' . $acct;
			}
		}
		$acct = normalizePoolVendorBankAccount($reg, $konto);
		if ($acct !== null && !in_array('ACCT:' . $acct, $keys, true)) $keys[] = 'ACCT:' . $acct;
		return $keys;
	}
}

if (!function_exists('normalizePoolVendorName')) {
	/**
	 * Normalize a company name for comparison: lowercase, legal form (ApS, A/S, I/S, AB,
	 * GmbH, Ltd, ...) removed, e-mail addresses and URLs removed, punctuation collapsed
	 * to single spaces.
	 *
	 * @param string|null $raw
	 * @return string '' when nothing is left.
	 */
	function normalizePoolVendorName($raw) {
		if ($raw === null) return '';
		$value = mb_strtolower(trim((string) $raw), 'UTF-8');
		if ($value === '') return '';
		$value = preg_replace('/\S*@\S*|https?:\/\/\S+|www\.\S+/u', ' ', $value);
		$value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);
		$legalForms = array(
			'aps', 'a s', 'i s', 'k s', 'p s', 'ivs', 'as', 'ab', 'hb', 'kb', 'oy', 'oyj', 'gmbh', 'ag', 'kg', 'ug', 'ltd',
			'limited', 'plc', 'inc', 'llc', 'corp', 'co', 'bv', 'b v', 'nv', 'sa', 'sarl', 'srl', 'sas', 'spa', 'sl',
			'lukket', 'ltda', 'pty',
		);
		$value = ' ' . preg_replace('/\s+/u', ' ', $value) . ' ';
		foreach ($legalForms as $form) {
			$value = str_replace(' ' . $form . ' ', ' ', $value);
		}
		return trim(preg_replace('/\s+/u', ' ', $value));
	}
}

if (!function_exists('poolVendorTrigrams')) {
	/**
	 * Trigram set of a normalized name, built the way pg_trgm does it: each word is
	 * padded with two spaces in front and one behind before slicing.
	 *
	 * @param string $normalizedName
	 * @return array<string,true>
	 */
	function poolVendorTrigrams($normalizedName) {
		$set = array();
		foreach (preg_split('/\s+/u', trim($normalizedName), -1, PREG_SPLIT_NO_EMPTY) as $word) {
			$padded = '  ' . $word . ' ';
			$len = mb_strlen($padded, 'UTF-8');
			for ($i = 0; $i + 3 <= $len; $i++) {
				$set[mb_substr($padded, $i, 3, 'UTF-8')] = true;
			}
		}
		return $set;
	}
}

if (!function_exists('poolVendorNameSimilarity')) {
	/**
	 * pg_trgm-style similarity of two trigram sets: shared / union, 0.000-1.000.
	 *
	 * @param array<string,true> $a
	 * @param array<string,true> $b
	 * @return float
	 */
	function poolVendorNameSimilarity(array $a, array $b) {
		if (!$a || !$b) return 0.0;
		$shared = count(array_intersect_key($a, $b));
		$union = count($a) + count($b) - $shared;
		return $union > 0 ? round($shared / $union, 3) : 0.0;
	}
}

if (!function_exists('poolVendorBuildIndex')) {
	/**
	 * Build the lookup structures poolVendorMatch() needs from the tenant's kreditorer.
	 * Built once per request and reused for every file.
	 *
	 * @param array<int,array{id:int|string,kontonr:string|null,firmanavn:string|null,cvrnr?:string|null,iban?:string|null,bank_reg?:string|null,bank_konto?:string|null}> $kreditorer
	 * @return array{
	 *   byId: array<int,array{kontoId:int,kontonr:string,firmanavn:string}>,  Contract fields per kreditor.
	 *   byCvr: array<string,int[]>,      Normalized CVR -> kreditor ids.
	 *   byBank: array<string,int[]>,     Bank key (see poolVendorBankKeys) -> kreditor ids.
	 *   names: array<int,string>,        Normalized firmanavn per kreditor (empty names omitted).
	 *   trigrams: array<int,array<string,true>>,  Trigram set per kreditor.
	 *   tokens: array<string,int[]>      Name word (>= 3 chars) -> kreditor ids, for the cheap scan.
	 * }
	 */
	function poolVendorBuildIndex(array $kreditorer) {
		$index = array('byId' => array(), 'byCvr' => array(), 'byBank' => array(), 'names' => array(), 'trigrams' => array(), 'tokens' => array());
		foreach ($kreditorer as $row) {
			$id = (int) ($row['id'] ?? 0);
			if ($id <= 0) continue;
			$index['byId'][$id] = array(
				'kontoId' => $id,
				'kontonr' => (string) ($row['kontonr'] ?? ''),
				'firmanavn' => (string) ($row['firmanavn'] ?? ''),
			);
			$cvr = normalizePoolVendorCvr($row['cvrnr'] ?? null);
			if ($cvr !== null) $index['byCvr'][$cvr][] = $id;
			foreach (poolVendorBankKeys($row['iban'] ?? null, $row['bank_reg'] ?? null, $row['bank_konto'] ?? null) as $key) {
				$index['byBank'][$key][] = $id;
			}
			$name = normalizePoolVendorName($row['firmanavn'] ?? null);
			if ($name === '') continue;
			$index['names'][$id] = $name;
			$index['trigrams'][$id] = poolVendorTrigrams($name);
			foreach (array_unique(preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY)) as $token) {
				if (mb_strlen($token, 'UTF-8') >= 3) $index['tokens'][$token][] = $id;
			}
		}
		return $index;
	}
}

if (!function_exists('poolVendorMatch')) {
	/**
	 * Match the vendor read on an invoice against the kreditor index. First unambiguous
	 * hit wins, in the order cvr (1.000) -> bank (0.950) -> name (trigram similarity).
	 *
	 * @param array{name?:string|null,cvr?:string|null,iban?:string|null,bank_reg?:string|null,bank_konto?:string|null} $vendor
	 *   Raw values as read on the invoice; normalized here.
	 * @param array $index From poolVendorBuildIndex().
	 * @param array{ownCvr?:string|null,nameThreshold?:float,nameScan?:string} $options
	 *   ownCvr: the tenant's own CVR - an invoice CVR equal to it is the buyer's and is dropped.
	 *   nameThreshold: minimum similarity for a name hit (default 0.45).
	 *   nameScan: 'full' compares every kreditor (single-file paths); 'tokens' only those
	 *   sharing a word of >= 3 letters (bulk re-match when the pool opens, see AI-6).
	 * @return array{
	 *   name: string|null,       Vendor name as read on the invoice.
	 *   cvr: string|null,        Normalized CVR/VAT number, or null.
	 *   iban: string|null,       Normalized IBAN, else "reg-konto", or null.
	 *   kontoId: int|null,       adresser.id of the matched kreditor.
	 *   kontonr: string|null,    adresser.kontonr of the matched kreditor.
	 *   firmanavn: string|null,  The kreditor's name in Saldi.
	 *   match: string,           cvr | bank | name | ambiguous | none
	 *   score: float,            0.000-1.000; 0.0 for none/ambiguous.
	 *   candidates: array<int,array{kontoId:int,kontonr:string,firmanavn:string}>  Only for ambiguous (max 5).
	 * }
	 */
	function poolVendorMatch(array $vendor, array $index, array $options = array()) {
		$name = isset($vendor['name']) ? trim((string) $vendor['name']) : '';
		$cvr = normalizePoolVendorCvr($vendor['cvr'] ?? null);
		$ownCvr = normalizePoolVendorCvr($options['ownCvr'] ?? null);
		if ($cvr !== null && $ownCvr !== null && $cvr === $ownCvr) $cvr = null;
		$iban = normalizePoolVendorIban($vendor['iban'] ?? null);
		$bankKeys = poolVendorBankKeys($vendor['iban'] ?? null, $vendor['bank_reg'] ?? null, $vendor['bank_konto'] ?? null);
		if ($iban === null) {
			$acct = normalizePoolVendorBankAccount($vendor['bank_reg'] ?? null, $vendor['bank_konto'] ?? null);
			$iban = $acct;
		}
		$threshold = isset($options['nameThreshold']) ? (float) $options['nameThreshold'] : 0.45;
		$scan = ($options['nameScan'] ?? 'full') === 'tokens' ? 'tokens' : 'full';

		$result = array(
			'name' => $name !== '' ? $name : null,
			'cvr' => $cvr,
			'iban' => $iban,
			'kontoId' => null,
			'kontonr' => null,
			'firmanavn' => null,
			'match' => 'none',
			'score' => 0.0,
			'candidates' => array(),
		);

		$normalizedName = normalizePoolVendorName($name);
		$nameTrigrams = $normalizedName !== '' ? poolVendorTrigrams($normalizedName) : array();

		// Orders candidate ids by name similarity so an ambiguous list shows the likeliest first.
		$rankByName = function (array $ids) use ($index, $nameTrigrams) {
			$scored = array();
			foreach (array_values(array_unique($ids)) as $pos => $id) {
				$sim = isset($index['trigrams'][$id]) ? poolVendorNameSimilarity($nameTrigrams, $index['trigrams'][$id]) : 0.0;
				$scored[] = array($id, $sim, $pos);
			}
			usort($scored, function ($a, $b) {
				if ($a[1] != $b[1]) return $a[1] < $b[1] ? 1 : -1;
				return $a[2] <=> $b[2];
			});
			return array_map(function ($s) { return $s[0]; }, $scored);
		};
		$finish = function ($ids, $matchType, $score) use (&$result, $index, $rankByName) {
			$ids = array_values(array_unique(array_filter($ids, function ($id) use ($index) { return isset($index['byId'][$id]); })));
			if (!$ids) return false;
			if (count($ids) === 1) {
				$k = $index['byId'][$ids[0]];
				$result['kontoId'] = $k['kontoId'];
				$result['kontonr'] = $k['kontonr'];
				$result['firmanavn'] = $k['firmanavn'];
				$result['match'] = $matchType;
				$result['score'] = round((float) $score, 3);
				return true;
			}
			$result['match'] = 'ambiguous';
			$result['score'] = 0.0;
			foreach (array_slice($rankByName($ids), 0, 5) as $id) {
				$result['candidates'][] = $index['byId'][$id];
			}
			return true;
		};

		// 1. CVR
		if ($cvr !== null && !empty($index['byCvr'][$cvr]) && $finish($index['byCvr'][$cvr], 'cvr', 1.0)) return $result;

		// 2. Bank (IBAN or reg.nr. + kontonr.)
		$bankIds = array();
		foreach ($bankKeys as $key) {
			if (!empty($index['byBank'][$key])) $bankIds = array_merge($bankIds, $index['byBank'][$key]);
		}
		if ($bankIds && $finish($bankIds, 'bank', 0.95)) return $result;

		// 3. Name similarity
		if ($normalizedName === '' || empty($index['trigrams'])) return $result;
		if ($scan === 'tokens') {
			$candidateIds = array();
			foreach (array_unique(preg_split('/\s+/u', $normalizedName, -1, PREG_SPLIT_NO_EMPTY)) as $token) {
				if (!empty($index['tokens'][$token])) $candidateIds = array_merge($candidateIds, $index['tokens'][$token]);
			}
			$candidateIds = array_unique($candidateIds);
		} else {
			$candidateIds = array_keys($index['trigrams']);
		}
		$best = 0.0;
		$bestIds = array();
		foreach ($candidateIds as $id) {
			$sim = poolVendorNameSimilarity($nameTrigrams, $index['trigrams'][$id]);
			if ($sim < $threshold) continue;
			if ($sim > $best + 0.0005) {
				$best = $sim;
				$bestIds = array($id);
			} elseif (abs($sim - $best) <= 0.0005) {
				$bestIds[] = $id;
			}
		}
		if ($bestIds) $finish($bestIds, 'name', $best);
		return $result;
	}
}

if (!function_exists('poolVendorFromRow')) {
	/**
	 * Rebuild the docData vendor contract for a pool_files row. kontonr/firmanavn and
	 * the ambiguous candidate list are not stored - they are read off the live index so
	 * a renamed kreditor shows its current name.
	 *
	 * @param array $row A pool_files row (vendor_* columns may be null).
	 * @param array $index From poolVendorBuildIndex().
	 * @return array|null Same shape as poolVendorMatch(); null when the file was never
	 *   scanned with vendor support (vendor_match empty).
	 */
	function poolVendorFromRow(array $row, array $index) {
		$match = trim((string) ($row['vendor_match'] ?? ''));
		if ($match === '') return null;
		$kontoId = isset($row['vendor_konto_id']) && $row['vendor_konto_id'] !== null && $row['vendor_konto_id'] !== '' ? (int) $row['vendor_konto_id'] : null;
		$vendor = array(
			'name' => isset($row['vendor_name']) && $row['vendor_name'] !== '' ? (string) $row['vendor_name'] : null,
			'cvr' => isset($row['vendor_cvr']) && $row['vendor_cvr'] !== '' ? (string) $row['vendor_cvr'] : null,
			'iban' => isset($row['vendor_iban']) && $row['vendor_iban'] !== '' ? (string) $row['vendor_iban'] : null,
			'kontoId' => null,
			'kontonr' => null,
			'firmanavn' => null,
			'match' => $match,
			'score' => round((float) ($row['vendor_score'] ?? 0), 3),
			'candidates' => array(),
		);
		if ($kontoId !== null && isset($index['byId'][$kontoId])) {
			$vendor['kontoId'] = $kontoId;
			$vendor['kontonr'] = $index['byId'][$kontoId]['kontonr'];
			$vendor['firmanavn'] = $index['byId'][$kontoId]['firmanavn'];
		} elseif ($kontoId !== null) {
			// Kreditor deleted after the match: the stale id is ignored (edge case table, section 7).
			$vendor['match'] = 'none';
			$vendor['score'] = 0.0;
		}
		if ($vendor['match'] === 'ambiguous') {
			$fresh = poolVendorMatch(array('name' => $vendor['name'], 'cvr' => $vendor['cvr'], 'iban' => $vendor['iban']), $index, array('nameScan' => 'tokens'));
			$vendor['candidates'] = $fresh['candidates'];
		}
		return $vendor;
	}
}

if (!function_exists('poolVendorNeedsRematch')) {
	/**
	 * Whether a pool_files row should be matched again when the pool opens (AI-6): it
	 * carries vendor identity but no valid kreditor, or points at a kreditor that no
	 * longer exists.
	 *
	 * @param array $row
	 * @param array $index
	 * @return bool
	 */
	function poolVendorNeedsRematch(array $row, array $index) {
		if (trim((string) ($row['vendor_match'] ?? '')) === '') return false;
		$hasIdentity = trim((string) ($row['vendor_cvr'] ?? '')) !== ''
			|| trim((string) ($row['vendor_iban'] ?? '')) !== ''
			|| trim((string) ($row['vendor_name'] ?? '')) !== '';
		if (!$hasIdentity) return false;
		$kontoId = isset($row['vendor_konto_id']) && $row['vendor_konto_id'] !== null && $row['vendor_konto_id'] !== '' ? (int) $row['vendor_konto_id'] : null;
		if ($kontoId === null) return true;
		return !isset($index['byId'][$kontoId]);
	}
}

if (!function_exists('poolVendorRowFromIban')) {
	/**
	 * Split a stored vendor_iban value back into the parts poolVendorMatch() accepts:
	 * an IBAN stays an IBAN, "REG-KONTO" becomes bank_reg/bank_konto.
	 *
	 * @param string|null $stored
	 * @return array{iban:string|null,bank_reg:string|null,bank_konto:string|null}
	 */
	function poolVendorRowFromIban($stored) {
		$stored = trim((string) $stored);
		if ($stored === '') return array('iban' => null, 'bank_reg' => null, 'bank_konto' => null);
		if (preg_match('/^(\d{4})-(\d+)$/', $stored, $m)) return array('iban' => null, 'bank_reg' => $m[1], 'bank_konto' => $m[2]);
		return array('iban' => $stored, 'bank_reg' => null, 'bank_konto' => null);
	}
}

// ---------------------------------------------------------------------------
// Database adapters. Everything below needs db_select()/db_fetch_array()/db_escape_string()
// from includes/db_query.php on an open tenant connection.
// ---------------------------------------------------------------------------

if (!function_exists('poolVendorColumnsExist')) {
	/**
	 * Whether pool_files carries the vendor_* columns on the current tenant. The columns are
	 * added by includes/betweenUpdates.php at login; a request that arrives before that
	 * (branch switched under a live session, MySQL tenant mid-migration) must degrade to the
	 * pre-vendor behaviour instead of failing the whole save with a db error. vendor_match is
	 * the last column the migration adds, so its presence implies the others.
	 *
	 * @return bool
	 */
	function poolVendorColumnsExist() {
		global $db_type;
		$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'pool_files' AND column_name = 'vendor_match'"
			. (in_array($db_type, array('mysql', 'mysqli'), true) ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()");
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		return (bool) ($q ? db_fetch_array($q) : false);
	}
}

if (!function_exists('poolVendorLoadIndex')) {
	/**
	 * Load the tenant's open kreditorer (adresser.art = 'K', not lukket) into a match index.
	 * One query. A kreditor closed after a match therefore reads as "deleted" in
	 * poolVendorFromRow() and is matched again.
	 *
	 * @return array See poolVendorBuildIndex().
	 */
	function poolVendorLoadIndex() {
		$rows = array();
		$qtxt = "SELECT id, kontonr, firmanavn, cvrnr, iban, bank_reg, bank_konto FROM adresser WHERE art = 'K' AND (lukket IS NULL OR lukket != 'on')";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($q && ($r = db_fetch_array($q))) {
			$rows[] = $r;
		}
		return poolVendorBuildIndex($rows);
	}
}

if (!function_exists('poolVendorLoadOwnCvr')) {
	/**
	 * The tenant's own CVR from its stamkort (adresser.art = 'S'), normalized, or null.
	 *
	 * @return string|null
	 */
	function poolVendorLoadOwnCvr() {
		$q = db_select("SELECT cvrnr FROM adresser WHERE art = 'S' ORDER BY id LIMIT 1", __FILE__ . " linje " . __LINE__);
		$r = $q ? db_fetch_array($q) : null;
		return $r ? normalizePoolVendorCvr($r['cvrnr'] ?? null) : null;
	}
}

if (!function_exists('poolVendorColumnValues')) {
	/**
	 * The pool_files vendor_* column values for a match result, ready for SQL.
	 *
	 * @param array $match From poolVendorMatch().
	 * @return array<string,string> Column name -> SQL literal (quoted/escaped or NULL).
	 */
	function poolVendorColumnValues(array $match) {
		$text = function ($v, $maxLen) {
			if ($v === null || $v === '') return 'NULL';
			return "'" . db_escape_string(mb_substr((string) $v, 0, $maxLen, 'UTF-8')) . "'";
		};
		return array(
			'vendor_name' => $text($match['name'] ?? null, 255),
			'vendor_cvr' => $text($match['cvr'] ?? null, 20),
			'vendor_iban' => $text($match['iban'] ?? null, 40),
			'vendor_konto_id' => isset($match['kontoId']) && $match['kontoId'] !== null ? (string) (int) $match['kontoId'] : 'NULL',
			'vendor_match' => $text($match['match'] ?? 'none', 10),
			'vendor_score' => number_format((float) ($match['score'] ?? 0), 3, '.', ''),
		);
	}
}

if (!function_exists('poolVendorUpdateSql')) {
	/**
	 * "col = value, col = value" fragment for an UPDATE pool_files statement.
	 *
	 * @param array $match From poolVendorMatch().
	 * @return string
	 */
	function poolVendorUpdateSql(array $match) {
		$parts = array();
		foreach (poolVendorColumnValues($match) as $col => $value) {
			$parts[] = "$col = $value";
		}
		return implode(', ', $parts);
	}
}
