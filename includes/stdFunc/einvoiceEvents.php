<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/einvoiceEvents.php --- ver 5.1.0 --- 2026-10-30 ---
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
// 20261030 CL/MJ SST-882 Created: the history of e-invoice statuses and messages per invoice (table einvoice_events).
//                The EasyUBL webhook and a failed send write to it, and the order page reads the latest rejection from it.

include_once(__DIR__ . '/einvoiceErrors.php');

if (!function_exists('einvoice_events_columns')) {
	/**
	 * The columns of einvoice_events, for ensureTableAndColumns() in includes/betweenUpdates.php.
	 *
	 * One row per status EasyUBL reports and per failed send, so the history of every attempt is
	 * kept. The table is only created by the update; nothing else may create it.
	 *
	 * @return array<string, string> Column name => SQL type.
	 */
	function einvoice_events_columns() {
		return array(
			'id' => 'serial PRIMARY KEY',
			'ordre_id' => 'integer',
			'fakturanr' => 'varchar(30)',
			'source' => 'varchar(10)',
			'status' => 'varchar(25)',
			'message' => 'text',
			'class' => 'varchar(30)',
			'created' => 'timestamp',
		);
	}
}

if (!function_exists('einvoice_events_ready')) {
	/**
	 * Whether einvoice_events exists in the connected tenant. The webhook can reach a tenant that
	 * nobody has logged in to since the update, and it must not create the table itself.
	 *
	 * @return bool
	 */
	function einvoice_events_ready() {
		static $ready = array();
		$db = (string) ($GLOBALS['db'] ?? '');
		if (!array_key_exists($db, $ready)) {
			$dbSql = db_escape_string($db);
			$qtxt = "SELECT table_name FROM information_schema.tables WHERE (table_schema = '$dbSql' OR table_catalog = '$dbSql') AND table_name = 'einvoice_events'";
			$ready[$db] = (bool) db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		}
		return $ready[$db];
	}
}

if (!function_exists('einvoice_log_event')) {
	/**
	 * Stores one e-invoice status or failure for an invoice, with its message classified.
	 *
	 * @param int    $ordreId   ordrer.id.
	 * @param string $fakturanr ordrer.fakturanr.
	 * @param string $source    'webhook' (EasyUBL reported a status) or 'send' (the send itself failed).
	 * @param string $status    Status word, as stored in ordrer.digital_status (Error, Rejected, SendFailed, ...).
	 * @param string $message   The text EasyUBL or Semantic gave, '' when none.
	 * @param string $cvr       The CVR on the invoice, for einvoice_classify_message().
	 * @return bool True when the row was written; false when the table does not exist yet.
	 */
	function einvoice_log_event($ordreId, $fakturanr, $source, $status, $message, $cvr = '') {
		if (!einvoice_events_ready()) {
			return false;
		}
		$classified = einvoice_classify_message($message, $cvr);
		$v = array_map('db_escape_string', array(
			mb_substr((string) $fakturanr, 0, 30),
			mb_substr((string) $source, 0, 10),
			mb_substr((string) $status, 0, 25),
			mb_substr($classified['text'], 0, 4000),
			$classified['class'],
		));
		$qtxt = "INSERT INTO einvoice_events (ordre_id, fakturanr, source, status, message, class, created) ";
		$qtxt .= "VALUES (" . (int) $ordreId . ", '$v[0]', '$v[1]', '$v[2]', '$v[3]', '$v[4]', now())";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		return true;
	}
}

if (!function_exists('einvoice_record_webhook')) {
	/**
	 * Stores a status the EasyUBL webhook reported, on the invoice it names.
	 *
	 * EasyUBL identifies the document by its invoice number (externalIdentifier). If more than one
	 * debtor document carries that number, the event goes on the newest one.
	 *
	 * @param string $fakturanr externalIdentifier from the webhook.
	 * @param string $status    Status word just stored in ordrer.digital_status.
	 * @param string $message   base64EncodedMessage, decoded ('' when none).
	 * @return bool True when a row was written.
	 */
	function einvoice_record_webhook($fakturanr, $status, $message) {
		$fakturanr = trim((string) $fakturanr);
		if ($fakturanr === '' || !einvoice_events_ready()) {
			return false;
		}
		$qtxt = "SELECT id, cvrnr FROM ordrer WHERE fakturanr = '" . db_escape_string($fakturanr) . "' AND art IN ('DO', 'DK') ORDER BY id DESC LIMIT 1";
		$order = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if (!$order) {
			return false;
		}
		return einvoice_log_event((int) $order['id'], $fakturanr, 'webhook', $status, $message, (string) $order['cvrnr']);
	}
}

if (!function_exists('einvoice_event_is_rejection')) {
	/**
	 * Whether an event should be shown as a rejection on the order page.
	 *
	 * A delivered status (Sent and later) ends it, and so does a temporary validation problem that
	 * EasyUBL retries by itself, or a status without any text to show.
	 *
	 * @param array{status: string, message: string, class: string}|null $event The newest event of the invoice.
	 * @return bool
	 */
	function einvoice_event_is_rejection($event) {
		if (!is_array($event)) {
			return false;
		}
		if (in_array((string) ($event['status'] ?? ''), array('Sent', 'Received', 'Confirmed', 'Approved'), true)) {
			return false;
		}
		return !in_array((string) ($event['class'] ?? ''), array('', 'none', 'transient_validation'), true);
	}
}

if (!function_exists('einvoice_latest_rejection')) {
	/**
	 * The newest event of an invoice, when it is a rejection to show (einvoice_event_is_rejection()).
	 *
	 * @param int $ordreId ordrer.id.
	 * @return array{status: string, message: string, class: string, created: string}|null
	 */
	function einvoice_latest_rejection($ordreId) {
		if (!einvoice_events_ready()) {
			return null;
		}
		$qtxt = "SELECT status, message, class, created FROM einvoice_events WHERE ordre_id = " . (int) $ordreId . " ORDER BY id DESC LIMIT 1";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if (!einvoice_event_is_rejection($r ?: null)) {
			return null;
		}
		return array('status' => (string) $r['status'], 'message' => (string) $r['message'], 'class' => (string) $r['class'], 'created' => (string) $r['created']);
	}
}
