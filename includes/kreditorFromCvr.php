<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/kreditorFromCvr.php --- ver 5.0.0 --- 2026-10-03 ---
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
// 20261003 CL/SZ SD-721 Created: kreditor from the CVR register (spec Task 7).
//                kreditorCvrResolve() decides what the pool offers for a document whose supplier is not a kreditor:
//                a match by CVR number, an automatic creation (the user's "Opret kreditor automatisk"), "Opret kreditor" with the CVR data, or the dialog.
//                kreditorCvrCreate() inserts through the kreditor card's kreditorInsert(); bank details read from the invoice are flagged unconfirmed.
//                Creation, "Fortryd" and "Bekræft" are written to audit_log.
// 20261004 CL/SZ SD-721 A CVR number that only a closed kreditor has is not created again on its own: status 'closed' offers "Genåbn" (kreditorCvrReopen()) or "Opret ny".
//                "Opret ny" creates the second kreditor only when the request says so (allowClosed).

require_once __DIR__ . '/cvrLookup.php';
require_once __DIR__ . '/kreditorCreate.php';
require_once __DIR__ . '/auditLog.php';
require_once __DIR__ . '/docsIncludes/poolVendorMatcher.php';

if (!function_exists('kreditorCvrReady')) {
	/**
	 * Whether adresser has the SD-721 columns. includes/betweenUpdates.php adds them at login.
	 *
	 * @return bool
	 */
	function kreditorCvrReady() {
		global $db_type;
		static $ready = null;
		if ($ready !== null) return $ready;
		$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'adresser' AND column_name = 'bank_unconfirmed'";
		$qtxt .= ($db_type == 'mysql' || $db_type == 'mysqli') ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
		$ready = (bool)db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		return $ready;
	}
}

if (!function_exists('kreditorCvrDefaults')) {
	/**
	 * The company's defaults for a kreditor created from the CVR register.
	 *
	 * @return array{gruppe: int|null, betalingsbet: string, betalingsdage: int} gruppe is null until the first creation has asked for it.
	 */
	function kreditorCvrDefaults() {
		$gruppe = get_settings_value('cvr_kreditor_gruppe', 'kreditor', '');
		return array(
			'gruppe' => $gruppe === '' || $gruppe === null ? null : (int)$gruppe,
			'betalingsbet' => (string)get_settings_value('cvr_kreditor_betalingsbet', 'kreditor', 'Netto'),
			'betalingsdage' => (int)get_settings_value('cvr_kreditor_betalingsdage', 'kreditor', 8),
		);
	}
}

if (!function_exists('kreditorCvrSaveDefaults')) {
	/**
	 * Stores the company's defaults.
	 *
	 * @param int $gruppe Kreditor group (grupper art 'KG', kodenr).
	 * @param string $betalingsbet Netto, Lb. md., Kontant, Forud or Efterkrav.
	 * @param int $betalingsdage
	 * @return void
	 */
	function kreditorCvrSaveDefaults($gruppe, $betalingsbet, $betalingsdage) {
		update_settings_value('cvr_kreditor_gruppe', 'kreditor', (string)(int)$gruppe, 'Kreditorgruppe for kreditor oprettet fra CVR-registeret');
		update_settings_value('cvr_kreditor_betalingsbet', 'kreditor', $betalingsbet, 'Betalingsbetingelse for kreditor oprettet fra CVR-registeret');
		update_settings_value('cvr_kreditor_betalingsdage', 'kreditor', (string)(int)$betalingsdage, 'Betalingsdage for kreditor oprettet fra CVR-registeret');
	}
}

if (!function_exists('kreditorCvrTerms')) {
	/**
	 * The payment terms the kreditor card offers.
	 *
	 * @return string[]
	 */
	function kreditorCvrTerms() {
		return array('Netto', 'Lb. md.', 'Kontant', 'Forud', 'Efterkrav');
	}
}

if (!function_exists('kreditorCvrGroups')) {
	/**
	 * The kreditor groups of the fiscal year.
	 *
	 * @param int $regnaar
	 * @return array<int, array{kodenr: int, beskrivelse: string}>
	 */
	function kreditorCvrGroups($regnaar) {
		$groups = array();
		$q = db_select("select kodenr, beskrivelse from grupper where art = 'KG' and fiscal_year = '" . (int)$regnaar . "' order by kodenr", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$groups[] = array('kodenr' => (int)$r['kodenr'], 'beskrivelse' => (string)$r['beskrivelse']);
		}
		return $groups;
	}
}

if (!function_exists('kreditorCvrUserAuto')) {
	/**
	 * The user's "Opret kreditor automatisk" from the journal's gear box (grupper art 'KASKL', box3, option kred_auto). Off by default.
	 *
	 * @param int|null $userId
	 * @return bool
	 */
	function kreditorCvrUserAuto($userId) {
		if ($userId === null || (int)$userId === 0) return false;
		$r = db_fetch_array(db_select("select box3 from grupper where art = 'KASKL' and kode = '1' and kodenr = '" . (int)$userId . "'", __FILE__ . " linje " . __LINE__));
		if (!$r) return false;
		return in_array('kred_auto', array_map('trim', explode(',', (string)$r['box3'])), true);
	}
}

if (!function_exists('kreditorCvrFindByCvr')) {
	/**
	 * The open kreditor with this CVR number, compared after normalising ("DK 12 34 56 78" equals "12345678").
	 *
	 * @param string $cvr Normalised CVR number.
	 * @param bool $closed true: a closed kreditor (lukket) with the number instead.
	 * @return array{id: int, kontonr: string, firmanavn: string}|null The first by kontonr when several have it.
	 */
	function kreditorCvrFindByCvr($cvr, $closed = false) {
		if ($cvr === null || $cvr === '') return null;
		$digits = preg_replace('/\D/', '', $cvr);
		$like = db_escape_string('%' . substr($digits, -4) . '%');
		$state = $closed ? "lukket = 'on'" : "(lukket IS NULL or lukket != 'on')";
		$q = db_select("select id, kontonr, firmanavn, cvrnr from adresser where art = 'K' and $state and cvrnr like '$like' order by kontonr", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (normalizePoolVendorCvr($r['cvrnr']) === $cvr) {
				return array('id' => (int)$r['id'], 'kontonr' => trim((string)$r['kontonr']), 'firmanavn' => (string)$r['firmanavn']);
			}
		}
		return null;
	}
}

if (!function_exists('kreditorCvrPoolFile')) {
	/**
	 * The vendor read from a pool document.
	 *
	 * @param string $filename pool_files.filename.
	 * @return array{id: int, name: string, cvr: string|null, bank: string|null, match: string}|null null when the document isn't in the pool.
	 */
	function kreditorCvrPoolFile($filename) {
		if (!poolVendorColumnsExist()) return null;
		$r = db_fetch_array(db_select("select id, vendor_name, vendor_cvr, vendor_iban, vendor_match from pool_files where filename = '" . db_escape_string((string)$filename) . "'", __FILE__ . " linje " . __LINE__));
		if (!$r) return null;
		return array(
			'id' => (int)$r['id'],
			'name' => trim((string)$r['vendor_name']),
			'cvr' => normalizePoolVendorCvr($r['vendor_cvr']),
			'bank' => trim((string)$r['vendor_iban']) !== '' ? trim((string)$r['vendor_iban']) : null,
			'match' => trim((string)$r['vendor_match']),
		);
	}
}

if (!function_exists('kreditorCvrBankFields')) {
	/**
	 * Bank details as pool_files.vendor_iban stores them, in adresser's columns. A Danish IBAN also gives reg.nr. and kontonr.; a foreign IBAN is also Konto.
	 *
	 * @param string|null $stored An IBAN, or "REG-KONTO".
	 * @return array{iban: string, bank_reg: string, bank_konto: string}|null
	 */
	function kreditorCvrBankFields($stored) {
		$parts = poolVendorRowFromIban($stored);
		if ($parts['iban'] !== null) {
			$iban = normalizePoolVendorIban($parts['iban']);
			if ($iban === null) return null;
			// A Danish IBAN gives reg.nr. and kontonr.; a foreign one goes in Konto, as the kreditor card asks for it
			$reg = '';
			$konto = $iban;
			if (substr($iban, 0, 2) === 'DK' && strlen($iban) === 18) {
				$reg = substr($iban, 4, 4);
				$konto = substr($iban, 8, 10);
			}
			return array('iban' => $iban, 'bank_reg' => $reg, 'bank_konto' => $konto);
		}
		if ($parts['bank_reg'] !== null) {
			return array('iban' => '', 'bank_reg' => $parts['bank_reg'], 'bank_konto' => $parts['bank_konto']);
		}
		return null;
	}
}

if (!function_exists('kreditorCvrIsDanish')) {
	/**
	 * Whether a normalised CVR/VAT number is a syntactically valid Danish CVR number (8 digits; the CVR register has no other).
	 *
	 * @param string|null $cvr
	 * @return bool
	 */
	function kreditorCvrIsDanish($cvr) {
		return $cvr !== null && preg_match('/^\d{8}$/', $cvr) === 1;
	}
}

if (!function_exists('kreditorCvrCreate')) {
	/**
	 * Creates a kreditor from CVR data (or what the user typed in the dialog).
	 * A kreditor with the same CVR number is a match, never a second kreditor.
	 *
	 * @param array<string, string> $company cvrnr, firmanavn, addr1, addr2, postnr, bynavn, tlf, email (unescaped).
	 * @param array{gruppe: int, betalingsbet: string, betalingsdage: int, bank?: string|null, auto?: bool, userId?: int|null, poolFileId?: int|null, mode?: string, allowClosed?: bool} $options
	 *   bank: the document's bank details (pool_files.vendor_iban), stored unconfirmed. auto: created without asking. mode: 'auto', 'click' or 'dialog' for the audit entry.
	 *   allowClosed: the user chose "Opret ny" although a closed kreditor has the CVR number.
	 * @return array{status: string, kreditor: array{id: int, kontonr: string, firmanavn: string}|null, error?: string}
	 *   status 'created', 'match' (the CVR number exists), 'closed' (only a closed kreditor has it; kreditor is that one), or 'error'.
	 */
	function kreditorCvrCreate(array $company, array $options) {
		$cvr = normalizePoolVendorCvr($company['cvrnr'] ?? '');
		if ($cvr !== null) {
			$existing = kreditorCvrFindByCvr($cvr);
			if ($existing) {
				kreditorCvrLinkPoolFile($options['poolFileId'] ?? null, $existing['id']);
				return array('status' => 'match', 'kreditor' => $existing);
			}
			// A closed kreditor has the number: the user chooses between "Genåbn" and "Opret ny" first
			$closed = empty($options['allowClosed']) ? kreditorCvrFindByCvr($cvr, true) : null;
			if ($closed) return array('status' => 'closed', 'kreditor' => $closed);
		}
		$firmanavn = trim((string)($company['firmanavn'] ?? ''));
		if ($firmanavn === '') return array('status' => 'error', 'kreditor' => null, 'error' => 'name');

		$fields = array();
		foreach (array('firmanavn', 'addr1', 'addr2', 'postnr', 'bynavn', 'tlf', 'email') as $key) {
			$fields[$key] = db_escape_string(trim((string)($company[$key] ?? '')));
		}
		if ($fields['postnr'] !== '' && $fields['bynavn'] === '' && function_exists('bynavn')) $fields['bynavn'] = db_escape_string((string)bynavn($company['postnr']));
		$fields['cvrnr'] = db_escape_string($cvr !== null ? $cvr : trim((string)($company['cvrnr'] ?? '')));
		$fields['gruppe'] = (int)$options['gruppe'];
		$fields['betalingsbet'] = db_escape_string(in_array($options['betalingsbet'], kreditorCvrTerms(), true) ? $options['betalingsbet'] : 'Netto');
		$fields['betalingsdage'] = in_array($fields['betalingsbet'], array('Kontant', 'Forud', 'Efterkrav'), true) ? 0 : (int)$options['betalingsdage'];
		$fields['kreditmax'] = 0;
		$fields['lukket'] = '';
		$bank = kreditorCvrBankFields($options['bank'] ?? null);
		if ($bank) {
			$fields['bank_reg'] = db_escape_string($bank['bank_reg']);
			$fields['bank_konto'] = db_escape_string($bank['bank_konto']);
		}

		// The number is taken just before the insert; kreditorInsert() refuses a number taken in between, then the next one is tried
		$id = null;
		for ($try = 0; $try < 3 && !$id; $try++) {
			$fields['kontonr'] = kreditorNextKontonr(0);
			$id = kreditorInsert($fields);
		}
		if (!$id) return array('status' => 'error', 'kreditor' => null, 'error' => 'insert');

		$auto = !empty($options['auto']);
		$userId = isset($options['userId']) && $options['userId'] !== null ? (int)$options['userId'] : null;
		$set = array();
		if ($bank && $bank['iban'] !== '') $set[] = "iban = '" . db_escape_string($bank['iban']) . "'";
		if (kreditorCvrReady()) {
			if ($bank) $set[] = "bank_unconfirmed = CURRENT_TIMESTAMP";
			if ($auto) $set[] = "auto_created = CURRENT_TIMESTAMP, auto_created_by = " . ($userId === null ? 'NULL' : $userId);
		}
		if ($set) db_modify("update adresser set " . implode(', ', $set) . " where id = $id", __FILE__ . " linje " . __LINE__);
		kreditorCvrLinkPoolFile($options['poolFileId'] ?? null, $id);

		$kreditor = array('id' => $id, 'kontonr' => (string)$fields['kontonr'], 'firmanavn' => $firmanavn);
		audit_log_write($auto ? 'kreditor.auto_created' : 'kreditor.created', 'kreditor', $id, array(
			'before' => null,
			'after' => array(
				'kontonr' => $kreditor['kontonr'],
				'firmanavn' => $firmanavn,
				'cvrnr' => $cvr,
				'gruppe' => (int)$options['gruppe'],
				'bank' => $bank ? array('iban' => $bank['iban'], 'bank_reg' => $bank['bank_reg'], 'bank_konto' => $bank['bank_konto'], 'unconfirmed' => true) : null,
			),
			'mode' => isset($options['mode']) ? (string)$options['mode'] : ($auto ? 'auto' : 'click'),
			'pool_file_id' => isset($options['poolFileId']) ? $options['poolFileId'] : null,
		), 'ui');
		return array('status' => 'created', 'kreditor' => $kreditor);
	}
}

if (!function_exists('kreditorCvrLinkPoolFile')) {
	/**
	 * The pool document now matches the kreditor, as a CVR match would (pool_files.vendor_*).
	 *
	 * @param int|null $poolFileId
	 * @param int $kreditorId
	 * @return void
	 */
	function kreditorCvrLinkPoolFile($poolFileId, $kreditorId) {
		if (!$poolFileId || !poolVendorColumnsExist()) return;
		db_modify("update pool_files set vendor_konto_id = " . (int)$kreditorId . ", vendor_match = 'cvr', vendor_score = 1 where id = " . (int)$poolFileId, __FILE__ . " linje " . __LINE__);
	}
}

if (!function_exists('kreditorCvrReferenced')) {
	/**
	 * Whether a saved journal line, a posted entry or an order refers to the kreditor.
	 *
	 * @param int $id adresser.id
	 * @param string $kontonr
	 * @return bool
	 */
	function kreditorCvrReferenced($id, $kontonr) {
		$nr = db_escape_string(trim((string)$kontonr));
		$checks = array(
			"select id from kassekladde where (d_type = 'K' and debet = '$nr') or (k_type = 'K' and kredit = '$nr') limit 1",
			"select id from openpost where konto_id = " . (int)$id . " limit 1",
			"select id from ordrer where konto_id = " . (int)$id . " limit 1",
		);
		foreach ($checks as $qtxt) {
			if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) return true;
		}
		return false;
	}
}

if (!function_exists('kreditorCvrUndo')) {
	/**
	 * "Fortryd": deletes a kreditor created automatically, as long as nothing refers to it.
	 *
	 * @param int $id adresser.id
	 * @return array{ok: bool, error?: string} error 'not_auto' or 'in_use'.
	 */
	function kreditorCvrUndo($id) {
		$id = (int)$id;
		if (!kreditorCvrReady()) return array('ok' => false, 'error' => 'not_auto');
		$row = db_fetch_array(db_select("select id, kontonr, firmanavn, cvrnr, auto_created, auto_created_by from adresser where id = $id and art = 'K'", __FILE__ . " linje " . __LINE__));
		if (!$row || $row['auto_created'] === null || $row['auto_created'] === '') return array('ok' => false, 'error' => 'not_auto');
		if (kreditorCvrReferenced($id, $row['kontonr'])) return array('ok' => false, 'error' => 'in_use');
		db_modify("delete from adresser where id = $id and auto_created is not null", __FILE__ . " linje " . __LINE__);
		if (poolVendorColumnsExist()) {
			db_modify("update pool_files set vendor_konto_id = NULL, vendor_match = 'none', vendor_score = 0 where vendor_konto_id = $id", __FILE__ . " linje " . __LINE__);
		}
		audit_log_write('kreditor.auto_create_undone', 'kreditor', $id, array(
			'before' => array('kontonr' => trim((string)$row['kontonr']), 'firmanavn' => (string)$row['firmanavn'], 'cvrnr' => (string)$row['cvrnr'],
				'auto_created' => (string)$row['auto_created'], 'auto_created_by' => $row['auto_created_by'] === null ? null : (int)$row['auto_created_by']),
			'after' => null,
		), 'ui');
		return array('ok' => true);
	}
}

if (!function_exists('kreditorCvrReopen')) {
	/**
	 * "Genåbn": a closed kreditor is opened again, and the pool document is matched to it.
	 *
	 * @param int $id adresser.id
	 * @param int|null $poolFileId
	 * @return array{ok: bool, kreditor?: array{id: int, kontonr: string, firmanavn: string}}
	 */
	function kreditorCvrReopen($id, $poolFileId = null) {
		$id = (int)$id;
		$row = db_fetch_array(db_select("select id, kontonr, firmanavn, cvrnr from adresser where id = $id and art = 'K' and lukket = 'on'", __FILE__ . " linje " . __LINE__));
		if (!$row) return array('ok' => false);
		db_modify("update adresser set lukket = '' where id = $id and lukket = 'on'", __FILE__ . " linje " . __LINE__);
		kreditorCvrLinkPoolFile($poolFileId, $id);
		$kreditor = array('id' => $id, 'kontonr' => trim((string)$row['kontonr']), 'firmanavn' => (string)$row['firmanavn']);
		audit_log_write('kreditor.reopened', 'kreditor', $id, array(
			'before' => array('kontonr' => $kreditor['kontonr'], 'firmanavn' => $kreditor['firmanavn'], 'cvrnr' => (string)$row['cvrnr'], 'lukket' => 'on'),
			'after' => array('lukket' => ''),
			'pool_file_id' => $poolFileId,
		), 'ui');
		return array('ok' => true, 'kreditor' => $kreditor);
	}
}

if (!function_exists('kreditorCvrConfirmBank')) {
	/**
	 * "Bekræft": the kreditor's bank details may be used for payments.
	 *
	 * @param int $id adresser.id
	 * @return bool true when they were unconfirmed and are confirmed now.
	 */
	function kreditorCvrConfirmBank($id) {
		$id = (int)$id;
		if (!kreditorCvrReady()) return false;
		$row = db_fetch_array(db_select("select bank_reg, bank_konto, iban, bank_unconfirmed from adresser where id = $id and art = 'K'", __FILE__ . " linje " . __LINE__));
		if (!$row || $row['bank_unconfirmed'] === null || $row['bank_unconfirmed'] === '') return false;
		db_modify("update adresser set bank_unconfirmed = NULL where id = $id", __FILE__ . " linje " . __LINE__);
		audit_log_write('kreditor.bank_confirmed', 'kreditor', $id, array(
			'before' => array('bank_unconfirmed' => (string)$row['bank_unconfirmed']),
			'after' => array('bank_unconfirmed' => null, 'bank_reg' => trim((string)$row['bank_reg']), 'bank_konto' => trim((string)$row['bank_konto']), 'iban' => trim((string)$row['iban'])),
		), 'ui');
		return true;
	}
}

if (!function_exists('kreditorCvrResolve')) {
	/**
	 * What the pool offers for a document's supplier when the vendor match found no kreditor.
	 *
	 * @param string $filename pool_files.filename.
	 * @param int|null $userId The user; their "Opret kreditor automatisk" decides between creating and offering.
	 * @return array{status: string, kreditor?: array, company?: array, captured?: array, reason?: string}
	 *   status: 'match' (a kreditor has the CVR number), 'created' (created automatically), 'suggest' ("Ukendt leverandør: Firma A/S (CVR …) — Opret kreditor"),
	 *   'closed' (only a closed kreditor has the CVR number: "Genåbn" or "Opret ny"; company when the CVR register answered),
	 *   'unknown' ("Ukendt leverandør — Opret kreditor", reason: 'no_cvr', 'lookup_failed', 'dissolved', 'not_found'), or 'none' (nothing to offer).
	 */
	function kreditorCvrResolve($filename, $userId) {
		$file = kreditorCvrPoolFile($filename);
		if (!$file || $file['match'] !== 'none') return array('status' => 'none');
		$captured = array('name' => $file['name'], 'cvr' => $file['cvr'], 'bank' => $file['bank'] !== null);

		if ($file['cvr'] !== null) {
			$existing = kreditorCvrFindByCvr($file['cvr']);
			if ($existing) {
				kreditorCvrLinkPoolFile($file['id'], $existing['id']);
				return array('status' => 'match', 'kreditor' => $existing);
			}
			// Never a second kreditor on its own: "Genåbn", or "Opret ny" with the CVR data for the dialog
			$closed = kreditorCvrFindByCvr($file['cvr'], true);
			if ($closed) {
				$answer = array('status' => 'closed', 'kreditor' => $closed, 'captured' => $captured);
				$lookup = kreditorCvrIsDanish($file['cvr']) ? cvrLookupCompany($file['cvr'], 5) : null;
				if ($lookup && $lookup['ok']) $answer['company'] = $lookup['company'];
				return $answer;
			}
		}
		if (!kreditorCvrIsDanish($file['cvr'])) return array('status' => 'unknown', 'reason' => 'no_cvr', 'captured' => $captured);

		$lookup = cvrLookupCompany($file['cvr'], 5);
		if (!$lookup['ok']) {
			return array('status' => 'unknown', 'reason' => $lookup['error'] === 'NOT_FOUND' ? 'not_found' : 'lookup_failed', 'captured' => $captured);
		}
		if ($lookup['dissolved']) return array('status' => 'unknown', 'reason' => 'dissolved', 'captured' => $captured, 'company' => $lookup['company']);

		$defaults = kreditorCvrDefaults();
		if (kreditorCvrUserAuto($userId) && $defaults['gruppe'] !== null) {
			$created = kreditorCvrCreate($lookup['company'], array(
				'gruppe' => $defaults['gruppe'],
				'betalingsbet' => $defaults['betalingsbet'],
				'betalingsdage' => $defaults['betalingsdage'],
				'bank' => $file['bank'],
				'auto' => true,
				'userId' => $userId,
				'poolFileId' => $file['id'],
				'mode' => 'auto',
			));
			if ($created['status'] === 'created' || $created['status'] === 'match') {
				return array('status' => $created['status'], 'kreditor' => $created['kreditor']);
			}
		}
		return array('status' => 'suggest', 'company' => $lookup['company'], 'captured' => $captured, 'needsGroup' => $defaults['gruppe'] === null);
	}
}
if (!function_exists('kreditorCvrClientScript')) {
	/**
	 * The script tags for javascript/kreditorFromCvr.js with its config and texts, for a page one folder below the root (finans/, includes/).
	 *
	 * @param int $sprog_id
	 * @param string $version Cache-busting value for the script URL.
	 * @return string
	 */
	function kreditorCvrClientScript($sprog_id, $version = '1') {
		$config = array(
			'url' => '../kreditor/kreditorFromCvr.php',
			'proxy' => '../sager/cvrLookupProxy.php',
			'cvrScript' => '../javascript/cvrapiopslag.js',
			'texts' => array(
				'kreditor' => findtekst('1169|Kreditor', $sprog_id),
				'unknown' => findtekst('5352|Ukendt leverandør', $sprog_id),
				'create' => findtekst('5353|Opret kreditor', $sprog_id),
				'autoCreated' => findtekst('5354|oprettet automatisk fra CVR-registeret', $sprog_id),
				'created' => findtekst('5355|oprettet', $sprog_id),
				'undo' => findtekst('921|Fortryd', $sprog_id),
				'undone' => findtekst('5356|Kreditoren er slettet igen', $sprog_id),
				'undoInUse' => findtekst('5357|Kan ikke fortrydes: kreditoren er taget i brug', $sprog_id),
				'exists' => findtekst('5358|Kreditoren findes allerede', $sprog_id),
				'lookupFailed' => findtekst('5365|CVR-registeret kunne ikke svare', $sprog_id),
				'dissolved' => findtekst('5366|Virksomheden er ophørt', $sprog_id),
				'notFound' => findtekst('5367|CVR-nummeret findes ikke i CVR-registeret', $sprog_id),
				'noCvr' => findtekst('5370|Intet CVR-nummer på bilaget', $sprog_id),
				'nameMissing' => findtekst('5368|Firmanavn skal udfyldes', $sprog_id),
				'failed' => findtekst('5369|Kreditoren kunne ikke oprettes', $sprog_id),
				'saveDefault' => findtekst('5360|Gem som standard', $sprog_id),
				'cvr' => findtekst('376|CVR-nr.', $sprog_id),
				'firmanavn' => findtekst('360|Firmanavn', $sprog_id),
				'adresse' => findtekst('361|Adresse', $sprog_id),
				'postnr' => findtekst('144|Postnr', $sprog_id),
				'by' => findtekst('146|By', $sprog_id),
				'tlf' => findtekst('377|Telefon', $sprog_id),
				'email' => findtekst('402|E-mail', $sprog_id),
				'gruppe' => findtekst('1183|Kreditorgruppe', $sprog_id),
				'betalingsbet' => findtekst('368|Betalingsbetingelse', $sprog_id),
				'dage' => findtekst('5025|dage', $sprog_id),
				'cancel' => findtekst('5044|Annullér', $sprog_id),
				'closed' => findtekst('5401|er lukket', $sprog_id),
				'reopen' => findtekst('5402|Genåbn', $sprog_id),
				'createNew' => findtekst('5403|Opret ny', $sprog_id),
				'reopened' => findtekst('5404|genåbnet', $sprog_id),
			),
		);
		return "<script>window.saldiKreditorCvr = " . json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ";</script>\n"
			. "<script src=\"../javascript/kreditorFromCvr.js?v=" . htmlspecialchars($version, ENT_QUOTES) . "\"></script>\n";
	}
}
?>
