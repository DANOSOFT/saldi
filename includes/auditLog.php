<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/auditLog.php --- ver 5.0.0 --- 2026-10-03 ---
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
// 20261003 CL/SZ SD-724 Created: audit_log_write(), one entry in the shared audit_log table (roles stage 2, Requirements_roles_stage2_EN.md §3 and §7).
//                The table is created by includes/betweenUpdates.php.
//                The roles stage 2 developer may reuse or replace this function; the signature stays the same.
// 20261003 CL/SZ SD-721 kreditor.created added to the list of handling values.
//
// handling values, prefixed by domain. Roles stage 2 (§7.1): login.*, user.*, role.*, session.*, permission.*, integration.*.
// Document pool and kreditor flow (Requirements_document_pool_supplier_invoice_flow_EN.md):
//   document.archived, document.restored, document.purged                       objekt_type 'dokument'
//   kreditor.auto_created, kreditor.auto_create_undone, kreditor.bank_confirmed  objekt_type 'kreditor'
//   kreditor.created (from the CVR register with one click or the dialog, SD-721)  objekt_type 'kreditor'
//   suggestion.rejected, extraction.error_reported                              objekt_type 'forslag'
// Entries are never deleted (five-year retention, as the kontrolspor).

if (!function_exists('audit_log_details_json')) {
	/**
	 * Turns the details of an audit entry into the JSON text stored in audit_log.detaljer.
	 *
	 * An array is encoded as JSON, normally {"before": ..., "after": ...}. Values under a key that names a
	 * password, API key, secret, token or 2FA code are replaced by "***" at any depth, so a caller that
	 * passes a whole row by mistake still never stores them. A string is stored as it is (free text).
	 *
	 * @param array<string, mixed>|string|null $detaljer
	 * @return string|null JSON or free text; null when there are no details.
	 */
	function audit_log_details_json($detaljer) {
		if ($detaljer === null || $detaljer === '' || $detaljer === []) {
			return null;
		}
		if (!is_array($detaljer)) {
			return (string)$detaljer;
		}
		$json = json_encode(audit_log_mask_secrets($detaljer), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		return $json === false ? null : $json;
	}
}

if (!function_exists('audit_log_mask_secrets')) {
	/**
	 * Replaces the values of secret keys (password, kode, tmp_kode, api_key, secret, token, 2fa code) with "***".
	 *
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	function audit_log_mask_secrets(array $values) {
		foreach ($values as $key => $value) {
			if (is_string($key) && preg_match('/^(password|passwd|kodeord|kode|tmp_kode|api_?key|.*_api_?key|secret|.*_secret|token|.*_token|2fa_code|twofa_code)$/i', $key)) {
				$values[$key] = '***';
			} elseif (is_array($value)) {
				$values[$key] = audit_log_mask_secrets($value);
			}
		}
		return $values;
	}
}

if (!function_exists('audit_log_write')) {
	/**
	 * Writes one entry to audit_log. bruger_id, brugernavn and ip are taken from the session.
	 *
	 * bruger_id is the logged-in user's id, -1 in a revisor (auditor) session, and null without a session
	 * (system actions such as cron jobs). brugernavn is copied so the entry survives the user's deletion.
	 *
	 * @param string $handling    What happened, prefixed by domain, e.g. 'document.archived' (see the list at the top of this file).
	 * @param string|null $objekt_type 'dokument', 'kreditor', 'forslag', 'bruger', 'rolle', 'integration', 'session', 'side'.
	 * @param string|int|null $objekt_id Id of the object, e.g. a pool_files id or a kreditor's adresser id.
	 * @param array<string, mixed>|string|null $detaljer {before, after} as an array, or free text. Never passwords or API keys.
	 * @param string $kilde       'ui', 'onboarding', 'migrering', 'api' or 'system'.
	 * @return bool true when the entry was written.
	 */
	function audit_log_write($handling, $objekt_type = null, $objekt_id = null, $detaljer = null, $kilde = 'ui') {
		global $bruger_id, $brugernavn;

		$handling = trim((string)$handling);
		if ($handling === '') {
			return false;
		}

		$text = function ($value, $length) {
			if ($value === null || $value === '') {
				return 'NULL';
			}
			return "'" . db_escape_string(mb_substr((string)$value, 0, $length)) . "'";
		};

		$userId = null;
		$userNameSql = 'NULL';
		if (isset($bruger_id) && is_numeric($bruger_id) && (int)$bruger_id > 0) {
			$userId = (int)$bruger_id;
			$r = db_fetch_array(db_select("select brugernavn from brugere where id = $userId", __FILE__ . " linje " . __LINE__));
			if ($r) {
				$userNameSql = $text($r['brugernavn'], 80);
			}
		} elseif (isset($bruger_id) && (int)$bruger_id === -1) {
			// Revisor session: the user only exists in the master database.
			// online.php has already escaped $brugernavn for this connection, so it goes into the SQL as it is.
			$userId = -1;
			if (!empty($brugernavn) && strlen($brugernavn) <= 80) {
				$userNameSql = "'" . $brugernavn . "'";
			}
		}
		$ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
		$details = audit_log_details_json($detaljer);

		$qtxt = "insert into audit_log (bruger_id, brugernavn, handling, objekt_type, objekt_id, detaljer, ip, kilde) values (";
		$qtxt .= ($userId === null ? 'NULL' : $userId) . ", ";
		$qtxt .= $userNameSql . ", ";
		$qtxt .= $text($handling, 60) . ", ";
		$qtxt .= $text($objekt_type, 30) . ", ";
		$qtxt .= $text($objekt_id, 60) . ", ";
		$qtxt .= ($details === null ? 'NULL' : "'" . db_escape_string($details) . "'") . ", ";
		$qtxt .= $text($ip, 45) . ", ";
		$qtxt .= $text($kilde, 30) . ")";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		return true;
	}
}
