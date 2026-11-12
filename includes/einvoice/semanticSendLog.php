<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/semanticSendLog.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261109 CL/MMR SD-815 Created (draft). The send log over the tenant table einvoice_sendlog: one row per e-document sent through Semantic.
//                 It keeps the XML that was sent (Saldi's own archive, bogføringsloven 5 years) and the Semantic document id the webhook and the poll match on.
//                 Texts are written so PostgreSQL's ' like ' to ' ilike ' rewrite in db_modify() cannot change them.
// 20261112 CL/MMR SD-815 The receiver's answer to a sent document (Peppol ApplicationResponse) is kept in four receiver_* columns: einvoice_sendlog_receiver() reads them for the order page.
//                 einvoice_sendlog_find_by_fakturanr() lists the rows an answer may belong to.
// 20261112 CL/MMR SD-815 MySQL: characters outside the Basic Multilingual Plane (emoji) become U+FFFD, since the connection is utf8 (3 bytes) and strict mode refuses them.
//
/*
 * Table einvoice_sendlog (created by includes/betweenUpdates.php, and by includes/opretfunc.php for a new regnskab):
 *   id serial, ordre_id int, art varchar(2) ('DO' invoice, 'DK' credit note), fakturanr varchar(20),
 *   provider varchar(20) ('semantic'), network smallint (3 Peppol, 4 Nemhandel), sender_endpoint_id varchar(64),
 *   semantic_doc_id varchar(40) (the int64 outgoing document id), ubl_doc_id varchar(64), status varchar(30),
 *   status_text text, xml text, xml_sha256 char(64), db_name varchar(64), created timestamp, updated timestamp,
 *   created_by varchar(80).
 *   The receiver's answer (SD-815 task 20, added by includes/betweenUpdates.php after the table itself):
 *   receiver_status varchar(10) (UNCL4343: AB, IP, UQ, CA, RE, AP, PD), receiver_status_text text (the reason),
 *   receiver_status_date timestamp (when the receiver answered), receiver_response_id varchar(40) (Semantic's incoming
 *   document id of the answer). They are not in einvoice_sendlog_select_columns(), so a database that has the table but
 *   not yet these columns can still be read; einvoice_sendlog_receiver() reads them.
 *
 * xml holds the document packed by einvoice_sendlog_pack_xml() ("gz:" + base64 of gzip, or "b64:" + base64),
 * not the raw XML: db_modify() rewrites ' like ' to ' ilike ' in every PostgreSQL statement, which would change an
 * item text such as "looks like new", and a tenant database may not be UTF-8. xml_sha256 is the SHA-256 of the
 * exact UTF-8 bytes that were sent, so the archive can be checked.
 *
 * A row is matched to Semantic's notifications by semantic_doc_id, never by fakturanr: the invoice and credit
 * note series can share numbers.
 */

if (!function_exists('einvoice_sendlog_columns')) {
	/**
	 * The columns callers may write, with their type and size. Everything else (id, created, updated) is set by SQL.
	 *
	 * @return array<string, array{type: string, max: int}> type 'int', 'string' (cut to max characters) or 'text'.
	 */
	function einvoice_sendlog_columns() {
		return array(
			'ordre_id' => array('type' => 'int', 'max' => 0),
			'art' => array('type' => 'string', 'max' => 2),
			'fakturanr' => array('type' => 'string', 'max' => 20),
			'provider' => array('type' => 'string', 'max' => 20),
			'network' => array('type' => 'int', 'max' => 0),
			'sender_endpoint_id' => array('type' => 'string', 'max' => 64),
			'semantic_doc_id' => array('type' => 'string', 'max' => 40),
			'ubl_doc_id' => array('type' => 'string', 'max' => 64),
			'status' => array('type' => 'string', 'max' => 30),
			'status_text' => array('type' => 'string', 'max' => 2000),
			'xml' => array('type' => 'text', 'max' => 0),
			'xml_sha256' => array('type' => 'string', 'max' => 64),
			'db_name' => array('type' => 'string', 'max' => 64),
			'created_by' => array('type' => 'string', 'max' => 80),
			'receiver_status' => array('type' => 'string', 'max' => 10),
			'receiver_status_text' => array('type' => 'string', 'max' => 2000),
			'receiver_status_date' => array('type' => 'string', 'max' => 19),
			'receiver_response_id' => array('type' => 'string', 'max' => 40),
		);
	}
}

if (!function_exists('einvoice_sendlog_open_statuses')) {
	/**
	 * Statuses that may still change at Semantic, so the poll asks for them (semantic_status_poll()).
	 * SD-815 OPEN: (Q7) which OutgoingDocumentStatus values are final? 'Sent' is treated as final for polling here;
	 *              later receipts arrive by webhook only.
	 *
	 * @return list<string>
	 */
	function einvoice_sendlog_open_statuses() {
		return array('Submitting', 'Unknown', 'NoStatus', 'Pending', 'PendingValidating', 'PendingValid', 'Sending', 'Parked');
	}
}

if (!function_exists('einvoice_sendlog_failed_statuses')) {
	/**
	 * Statuses meaning the document certainly did not reach the network, so sending again needs no confirmation.
	 * 'Unknown' and 'Submitting' are not here: the document may have been created at Semantic.
	 *
	 * @return list<string>
	 */
	function einvoice_sendlog_failed_statuses() {
		return array('ValidationFailed', 'SendFailed', 'Error');
	}
}

if (!function_exists('einvoice_sendlog_is_mysql')) {
	/**
	 * @param string|null $dbType $db_type from includes/connect.php.
	 * @return bool
	 */
	function einvoice_sendlog_is_mysql($dbType) {
		return in_array(strtolower((string)$dbType), array('mysql', 'mysqli'), true);
	}
}

if (!function_exists('einvoice_sendlog_pack_xml')) {
	/**
	 * Pack the sent XML for the xml column: "gz:" + base64(gzip) when zlib is there, else "b64:" + base64.
	 *
	 * @param string $xml The exact UTF-8 document that was sent.
	 * @return string
	 */
	function einvoice_sendlog_pack_xml($xml) {
		$xml = (string)$xml;
		if (function_exists('gzencode')) {
			$gz = gzencode($xml, 9);
			if ($gz !== false) {
				return 'gz:' . base64_encode($gz);
			}
		}
		return 'b64:' . base64_encode($xml);
	}
}

if (!function_exists('einvoice_sendlog_unpack_xml')) {
	/**
	 * The XML from a packed xml column value.
	 *
	 * @param string|null $stored
	 * @return string|null Null when empty or unreadable.
	 */
	function einvoice_sendlog_unpack_xml($stored) {
		$stored = (string)$stored;
		if (strncmp($stored, 'gz:', 3) === 0 && function_exists('gzdecode')) {
			$gz = base64_decode(substr($stored, 3), true);
			// Only data with the gzip magic bytes reaches gzdecode(); a damaged archive row still shows its warning.
			$xml = ($gz !== false && strncmp($gz, "\x1f\x8b", 2) === 0) ? gzdecode($gz) : false;
			return ($xml === false) ? null : $xml;
		}
		if (strncmp($stored, 'b64:', 4) === 0) {
			$xml = base64_decode(substr($stored, 4), true);
			return ($xml === false) ? null : $xml;
		}
		return null;
	}
}

if (!function_exists('einvoice_sendlog_sql_value')) {
	/**
	 * One value as SQL: ints cast, strings cut to the column size and escaped, null as NULL.
	 *
	 * On PostgreSQL db_modify() and db_select() turn every ' like ' in the statement into ' ilike ', also inside a
	 * string, so an error text such as "looks like" would be stored changed. There the literal is split at ' like '
	 * and joined again with ||, which the replacement does not see.
	 * On MySQL a character outside the Basic Multilingual Plane becomes U+FFFD (the connection is utf8, 3 bytes).
	 *
	 * @param mixed                          $value
	 * @param array{type: string, max: int}  $column From einvoice_sendlog_columns().
	 * @param callable(string): string        $escape db_escape_string() or a test double.
	 * @param string|null                    $dbType $db_type; anything but mysql/mysqli counts as PostgreSQL.
	 * @return string
	 */
	function einvoice_sendlog_sql_value($value, array $column, callable $escape, $dbType = null) {
		if ($value === null) {
			return 'NULL';
		}
		if ($column['type'] === 'int') {
			return (string)(int)$value;
		}
		$value = (string)$value;
		if ($column['type'] === 'string' && $column['max'] > 0) {
			if (function_exists('mb_substr')) {
				if (!mb_check_encoding($value, 'UTF-8')) {
					$value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
				}
				$value = mb_substr($value, 0, $column['max'], 'UTF-8');
			} else {
				$value = substr($value, 0, $column['max']);
			}
		}
		if (einvoice_sendlog_is_mysql($dbType) && preg_match('//u', $value)) {
			// db_connect() sets NAMES 'utf8' (3 bytes per character), so MySQL in strict mode refuses a 4-byte character.
			$value = (string)preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\u{FFFD}", $value);
		}
		$literal = "'" . call_user_func($escape, $value) . "'";
		if (!einvoice_sendlog_is_mysql($dbType) && strpos($literal, ' like ') !== false) {
			$literal = '(' . str_replace(' like ', " ' || 'like ", $literal) . ')';
		}
		return $literal;
	}
}

if (!function_exists('einvoice_sendlog_insert_sql')) {
	/**
	 * The INSERT for one row. Keys that are not columns are ignored; created and updated are set to now.
	 *
	 * @param array<string, mixed>     $row
	 * @param callable(string): string $escape
	 * @param string|null              $dbType $db_type, see einvoice_sendlog_sql_value().
	 * @return string
	 */
	function einvoice_sendlog_insert_sql(array $row, callable $escape, $dbType = null) {
		$columns = einvoice_sendlog_columns();
		$names = array();
		$values = array();
		foreach ($columns as $name => $column) {
			if (array_key_exists($name, $row)) {
				$names[] = $name;
				$values[] = einvoice_sendlog_sql_value($row[$name], $column, $escape, $dbType);
			}
		}
		$names[] = 'created';
		$values[] = 'CURRENT_TIMESTAMP';
		$names[] = 'updated';
		$values[] = 'CURRENT_TIMESTAMP';
		return 'INSERT INTO einvoice_sendlog (' . implode(', ', $names) . ') VALUES (' . implode(', ', $values) . ')';
	}
}

if (!function_exists('einvoice_sendlog_update_sql')) {
	/**
	 * The UPDATE of one row's fields; updated is set to now. '' when no known field is given.
	 *
	 * @param int                      $id
	 * @param array<string, mixed>     $fields
	 * @param callable(string): string $escape
	 * @param string|null              $dbType $db_type, see einvoice_sendlog_sql_value().
	 * @return string
	 */
	function einvoice_sendlog_update_sql($id, array $fields, callable $escape, $dbType = null) {
		$columns = einvoice_sendlog_columns();
		$sets = array();
		foreach ($fields as $name => $value) {
			if (isset($columns[$name])) {
				$sets[] = $name . ' = ' . einvoice_sendlog_sql_value($value, $columns[$name], $escape, $dbType);
			}
		}
		if (!$sets || (int)$id <= 0) {
			return '';
		}
		$sets[] = 'updated = CURRENT_TIMESTAMP';
		return 'UPDATE einvoice_sendlog SET ' . implode(', ', $sets) . ' WHERE id = ' . (int)$id;
	}
}

if (!function_exists('einvoice_sendlog_select_columns')) {
	/**
	 * Every column but xml, which only einvoice_sendlog_xml() reads.
	 *
	 * @return string
	 */
	function einvoice_sendlog_select_columns() {
		return 'id, ordre_id, art, fakturanr, provider, network, sender_endpoint_id, semantic_doc_id, ubl_doc_id, '
			. 'status, status_text, xml_sha256, db_name, created, updated, created_by';
	}
}

if (!function_exists('einvoice_sendlog_row')) {
	/**
	 * A fetched row with only its named keys.
	 *
	 * @param array<int|string, mixed>|false|null $row
	 * @return array{id: int, ordre_id: int, art: string, fakturanr: string, provider: string, network: int, sender_endpoint_id: string, semantic_doc_id: string, ubl_doc_id: string, status: string, status_text: string, xml_sha256: string, db_name: string, created: string, updated: string, created_by: string}|null
	 */
	function einvoice_sendlog_row($row) {
		if (!is_array($row) || !isset($row['id'])) {
			return null;
		}
		$out = array();
		foreach ($row as $key => $value) {
			if (is_string($key)) {
				$out[$key] = ($value === null) ? '' : $value;
			}
		}
		$out['id'] = (int)$out['id'];
		$out['ordre_id'] = (int)($out['ordre_id'] ?? 0);
		$out['network'] = (int)($out['network'] ?? 0);
		foreach (array('art', 'fakturanr', 'provider', 'sender_endpoint_id', 'semantic_doc_id', 'ubl_doc_id', 'status', 'status_text', 'xml_sha256', 'db_name', 'created', 'updated', 'created_by') as $key) {
			$out[$key] = trim((string)($out[$key] ?? ''));
		}
		return $out;
	}
}

if (!function_exists('einvoice_sendlog_insert')) {
	/**
	 * Insert one send-log row.
	 *
	 * @param array<string, mixed> $row Column => value, see einvoice_sendlog_columns(). Pack xml with einvoice_sendlog_pack_xml().
	 * @return int The new id, 0 when it could not be read.
	 */
	function einvoice_sendlog_insert(array $row) {
		global $db_type;
		db_modify(einvoice_sendlog_insert_sql($row, 'db_escape_string', $db_type), __FILE__ . " linje " . __LINE__);
		// The id of the insert just made, read from this connection (like kk_last_insert_id() in finans/kassekladde.php).
		if (einvoice_sendlog_is_mysql($db_type)) {
			return (int)mysqli_insert_id(db_query_connection(false));
		}
		$r = db_fetch_array(db_select("SELECT currval(pg_get_serial_sequence('einvoice_sendlog', 'id')) AS id", __FILE__ . " linje " . __LINE__));
		return isset($r['id']) ? (int)$r['id'] : 0;
	}
}

if (!function_exists('einvoice_sendlog_update')) {
	/**
	 * Update fields of one row (unknown fields are ignored).
	 *
	 * @param int                  $id
	 * @param array<string, mixed> $fields
	 * @return void
	 */
	function einvoice_sendlog_update($id, array $fields) {
		global $db_type;
		$qtxt = einvoice_sendlog_update_sql((int)$id, $fields, 'db_escape_string', $db_type);
		if ($qtxt !== '') {
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	}
}

if (!function_exists('einvoice_sendlog_get')) {
	/**
	 * One row by id, without the XML.
	 *
	 * @param int $id
	 * @return array<string, mixed>|null See einvoice_sendlog_row().
	 */
	function einvoice_sendlog_get($id) {
		if ((int)$id <= 0) {
			return null;
		}
		$qtxt = 'SELECT ' . einvoice_sendlog_select_columns() . ' FROM einvoice_sendlog WHERE id = ' . (int)$id;
		return einvoice_sendlog_row(db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)));
	}
}

if (!function_exists('einvoice_sendlog_find_by_semantic_id')) {
	/**
	 * The row for a Semantic outgoing document id (the newest, should there be more), without the XML.
	 *
	 * @param string $semanticDocId Digits only; anything else finds nothing.
	 * @return array<string, mixed>|null See einvoice_sendlog_row().
	 */
	function einvoice_sendlog_find_by_semantic_id($semanticDocId) {
		$semanticDocId = trim((string)$semanticDocId);
		if (!preg_match('/^[0-9]{1,40}$/', $semanticDocId)) {
			return null;
		}
		$qtxt = 'SELECT ' . einvoice_sendlog_select_columns() . ' FROM einvoice_sendlog';
		$qtxt .= " WHERE semantic_doc_id = '" . db_escape_string($semanticDocId) . "' AND provider = 'semantic'";
		$qtxt .= ' ORDER BY id DESC LIMIT 1';
		return einvoice_sendlog_row(db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)));
	}
}

if (!function_exists('einvoice_sendlog_find_by_ubl_id')) {
	/**
	 * The row for a Semantic UBL document id (OutgoingDocument.ublDocumentId), without the XML.
	 *
	 * @param string $ublDocId A uuid; anything else finds nothing.
	 * @return array<string, mixed>|null See einvoice_sendlog_row().
	 */
	function einvoice_sendlog_find_by_ubl_id($ublDocId) {
		$ublDocId = strtolower(trim((string)$ublDocId));
		if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $ublDocId)) {
			return null;
		}
		$qtxt = 'SELECT ' . einvoice_sendlog_select_columns() . ' FROM einvoice_sendlog';
		$qtxt .= " WHERE LOWER(ubl_doc_id) = '" . db_escape_string($ublDocId) . "' AND provider = 'semantic'";
		$qtxt .= ' ORDER BY id DESC LIMIT 1';
		return einvoice_sendlog_row(db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)));
	}
}

if (!function_exists('einvoice_sendlog_latest_for_order')) {
	/**
	 * The newest row for an order and document kind, without the XML.
	 *
	 * @param int    $orderId ordrer.id
	 * @param string $art     'DO' or 'DK'
	 * @return array<string, mixed>|null See einvoice_sendlog_row().
	 */
	function einvoice_sendlog_latest_for_order($orderId, $art) {
		$orderId = (int)$orderId;
		$art = strtoupper(trim((string)$art));
		if ($orderId <= 0 || !preg_match('/^[A-Z]{2}$/', $art)) {
			return null;
		}
		$qtxt = 'SELECT ' . einvoice_sendlog_select_columns() . ' FROM einvoice_sendlog';
		$qtxt .= " WHERE ordre_id = $orderId AND art = '" . db_escape_string($art) . "'";
		$qtxt .= ' ORDER BY id DESC LIMIT 1';
		return einvoice_sendlog_row(db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__)));
	}
}

if (!function_exists('einvoice_sendlog_find_open_sql')) {
	/**
	 * The query behind einvoice_sendlog_find_open(): Semantic rows with a document id whose status is open, created
	 * at or after $cutoff, oldest first.
	 *
	 * @param int                      $limit  1..500
	 * @param string                   $cutoff 'Y-m-d H:i:s'; rows created before it are no longer asked about.
	 * @param callable(string): string $escape
	 * @return string
	 */
	function einvoice_sendlog_find_open_sql($limit, $cutoff, callable $escape) {
		$limit = max(1, min(500, (int)$limit));
		$statuses = array();
		foreach (einvoice_sendlog_open_statuses() as $status) {
			$statuses[] = "'" . call_user_func($escape, $status) . "'";
		}
		$qtxt = 'SELECT ' . einvoice_sendlog_select_columns() . ' FROM einvoice_sendlog';
		$qtxt .= " WHERE provider = 'semantic' AND semantic_doc_id IS NOT NULL AND semantic_doc_id <> ''";
		$qtxt .= ' AND status IN (' . implode(', ', $statuses) . ')';
		$qtxt .= " AND created >= '" . call_user_func($escape, (string)$cutoff) . "'";
		$qtxt .= " ORDER BY id ASC LIMIT $limit";
		return $qtxt;
	}
}

if (!function_exists('einvoice_sendlog_find_open')) {
	/**
	 * Rows whose status may still change at Semantic (einvoice_sendlog_open_statuses()), created in the last 30 days,
	 * oldest first, without the XML.
	 *
	 * @param int $limit 1..500
	 * @return list<array<string, mixed>> See einvoice_sendlog_row().
	 */
	function einvoice_sendlog_find_open($limit) {
		$cutoff = date('Y-m-d H:i:s', time() - 30 * 86400);
		$query = db_select(einvoice_sendlog_find_open_sql((int)$limit, $cutoff, 'db_escape_string'), __FILE__ . " linje " . __LINE__);
		$rows = array();
		while ($query && ($r = db_fetch_array($query))) {
			$row = einvoice_sendlog_row($r);
			if ($row !== null) {
				$rows[] = $row;
			}
		}
		return $rows;
	}
}

if (!function_exists('einvoice_sendlog_xml')) {
	/**
	 * The XML a row archived, unpacked.
	 *
	 * @param int $id
	 * @return string|null
	 */
	function einvoice_sendlog_xml($id) {
		if ((int)$id <= 0) {
			return null;
		}
		$r = db_fetch_array(db_select('SELECT xml FROM einvoice_sendlog WHERE id = ' . (int)$id, __FILE__ . " linje " . __LINE__));
		return is_array($r) ? einvoice_sendlog_unpack_xml($r['xml'] ?? '') : null;
	}
}

if (!function_exists('einvoice_sendlog_find_by_fakturanr')) {
	/**
	 * The Semantic rows sent with this invoice or credit note number that may have reached the receiver (not refused
	 * before sending), newest first, without the XML. Invoice and credit note series can share numbers, so the caller
	 * must tell them apart (semantic_incoming_match_response()).
	 *
	 * @param string $fakturanr Exactly as stored; a number longer than the column finds nothing rather than a cut one.
	 * @param int    $limit     1..50
	 * @return list<array<string, mixed>> See einvoice_sendlog_row().
	 */
	function einvoice_sendlog_find_by_fakturanr($fakturanr, $limit = 20) {
		$fakturanr = trim((string)$fakturanr);
		$columns = einvoice_sendlog_columns();
		$length = function_exists('mb_strlen') ? mb_strlen($fakturanr, 'UTF-8') : strlen($fakturanr);
		if ($fakturanr === '' || $length > $columns['fakturanr']['max']) {
			return array();
		}
		$failed = array();
		foreach (array('ValidationFailed', 'SendFailed') as $status) {
			$failed[] = "'" . db_escape_string($status) . "'";
		}
		$qtxt = 'SELECT ' . einvoice_sendlog_select_columns() . ' FROM einvoice_sendlog';
		$qtxt .= " WHERE provider = 'semantic' AND fakturanr = '" . db_escape_string($fakturanr) . "'";
		$qtxt .= ' AND (status IS NULL OR status NOT IN (' . implode(', ', $failed) . '))';
		$qtxt .= ' ORDER BY id DESC LIMIT ' . max(1, min(50, (int)$limit));
		$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		$rows = array();
		while ($query && ($r = db_fetch_array($query))) {
			$row = einvoice_sendlog_row($r);
			if ($row !== null) {
				$rows[] = $row;
			}
		}
		return $rows;
	}
}

if (!function_exists('einvoice_sendlog_receiver')) {
	/**
	 * The receiver's answer stored on one row (Peppol ApplicationResponse, semanticIncoming.php). For the order page:
	 * semantic_incoming_receiver_display() turns it into e.g. "Afvist af modtager: <grund>".
	 *
	 * @param int $id
	 * @return array{
	 *   status: string,      UNCL4343 code (AB, IP, UQ, CA, RE, AP, PD), '' when the receiver has not answered.
	 *   text: string,        The receiver's reason, '' when none was given.
	 *   date: string,        When the receiver answered, 'Y-m-d H:i:s', '' when unknown.
	 *   responseId: string,  Semantic's incoming document id of the answer.
	 * }|null Null when the row does not exist or the receiver columns are not there yet.
	 */
	function einvoice_sendlog_receiver($id) {
		global $db_type;
		static $columnsExist = array();
		if ((int)$id <= 0) {
			return null;
		}
		$key = (string)($GLOBALS['db'] ?? '');
		if (!isset($columnsExist[$key])) {
			$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'einvoice_sendlog' AND column_name = 'receiver_status'";
			$qtxt .= einvoice_sendlog_is_mysql($db_type) ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
			$columnsExist[$key] = (bool)db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		}
		if (!$columnsExist[$key]) {
			return null;
		}
		$qtxt = 'SELECT receiver_status, receiver_status_text, receiver_status_date, receiver_response_id FROM einvoice_sendlog WHERE id = ' . (int)$id;
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if (!is_array($r)) {
			return null;
		}
		$date = trim((string)($r['receiver_status_date'] ?? ''));
		return array(
			'status' => trim((string)($r['receiver_status'] ?? '')),
			'text' => trim((string)($r['receiver_status_text'] ?? '')),
			'date' => ($date === '') ? '' : substr($date, 0, 19),
			'responseId' => trim((string)($r['receiver_response_id'] ?? '')),
		);
	}
}
