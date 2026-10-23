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
// 20261004 CL/SZ SD-721 kreditor.reopened added.
// 20261004 CL/SZ SD-724 audit_log_write() is now the roles stage 2 developer's version (audit_log_for_SD-724.md §3), so both branches define the same function.
//                It takes strings only; callers pass detaljer as JSON text, built with audit_log_details_json(), which keeps masking secrets.
//                No table yet (before the login migration ran): nothing is written. bruger_id 0 when there is no user.
//
// handling values, prefixed by domain. Roles stage 2 (§7.1): login.*, user.*, role.*, session.*, permission.*, integration.*.
// Document pool and kreditor flow (Requirements_document_pool_supplier_invoice_flow_EN.md):
//   document.archived, document.restored, document.purged                       objekt_type 'dokument'
//   kreditor.auto_created, kreditor.auto_create_undone, kreditor.bank_confirmed  objekt_type 'kreditor'
//   kreditor.created (from the CVR register with one click or the dialog, SD-721)  objekt_type 'kreditor'
//   kreditor.reopened ("Genåbn": a closed kreditor with the document's CVR number, SD-721)  objekt_type 'kreditor'
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
	 * @return string JSON or free text, for audit_log_write()'s detaljer; '' when there are no details.
	 */
	function audit_log_details_json($detaljer) {
		if ($detaljer === null || $detaljer === '' || $detaljer === []) {
			return '';
		}
		if (!is_array($detaljer)) {
			return (string)$detaljer;
		}
		$json = json_encode(audit_log_mask_secrets($detaljer), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		return $json === false ? '' : $json;
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
	 * Writes one entry to audit_log, as the roles stage 2 branch's audit_log_write() does (same signature, guarded the same way).
	 *
	 * bruger_id and brugernavn come from the session globals online.php sets (bruger_id -1 in a revisor session, 0 without a user);
	 * ip from REMOTE_ADDR. Writes nothing when the table doesn't exist yet. Never throws.
	 *
	 * @param string $handling   What happened, prefixed by domain, e.g. 'document.archived' (see the list at the top of this file). Max 60 characters.
	 * @param string $objektType 'dokument', 'kreditor', 'forslag', 'bruger', 'rolle', 'integration', 'session', 'side'.
	 * @param string $objektId   Id of the object as a string, e.g. a pool_files id or a kreditor's adresser id.
	 * @param string $detaljer   JSON {before, after} (audit_log_details_json()) or free text. Never passwords or API keys. Not truncated.
	 * @param string $kilde      'ui' when a user clicked something; 'api' or 'cron' for automatic actions.
	 * @return void
	 */
	function audit_log_write(string $handling, string $objektType = '', string $objektId = '', string $detaljer = '', string $kilde = 'ui'): void
	{
		global $bruger_id, $brugernavn;
		if (!function_exists('tbl_exists') || !tbl_exists('audit_log')) {
			return;
		}
		$id   = isset($bruger_id) ? (int) $bruger_id : 0;
		$navn = db_escape_string(isset($brugernavn) ? (string) $brugernavn : '');
		$ip   = db_escape_string(isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : '');
		$qtxt = "insert into audit_log (bruger_id, brugernavn, handling, objekt_type, objekt_id, detaljer, ip, kilde) values ("
			. "$id, '$navn', '" . db_escape_string(substr($handling, 0, 60)) . "', '" . db_escape_string(substr($objektType, 0, 30)) . "', '"
			. db_escape_string(substr($objektId, 0, 60)) . "', '" . db_escape_string($detaljer) . "', '$ip', '" . db_escape_string(substr($kilde, 0, 30)) . "')";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
}
