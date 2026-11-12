<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- ver 5.1.1 --- 2026.11.12
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261027 NTR New Release - Moved everything into opdat_5.0.php
// 20261028 CL/LH MB-72: One-time backfill of openpost.udlign_date for settled groups that have none.
// 20261028 CDX/NTR Apply the tekst_key schema migration to both tenant and master databases.
// 20261019 CL/LH MB-73: default 0 for ordrelinjer.leveret.
//                One-time backfill of NULL leveret on sales, credit note and POS lines from their batch_salg delivery records.
// 20261027 CL/LH MB-73 (review): the leveret backfill runs in id windows under a try-lock with a time budget per
//                login (includes/opdat_func/backfillOrderLineDelivered.php) instead of one UPDATE at the first login.
//                The SET DEFAULT check also matches MariaDB, which reports a missing default as the text 'NULL'.
// 20261102 CL/ASR Self-service signup (signup/, doc/signup.md): its master-database tables and the trial columns on regnskab, moved here from the flow's own first-use setup.
//                 Serialized with an advisory lock; a settings flag (system/signup_schema) marks it done.
// 20261110 CDX/NTR Release 5.1.1: move pending migrations into opdat_5.1.php and retain the bounded backfill continuation.
// 20261109 CL/MMR SD-815 (draft) Tenant table einvoice_sendlog: one row per e-document sent through Semantic, with the XML that was sent and Semantic's document id.
// 20261109 CL/MMR SD-815 (draft) einvoice_sendlog is created under an advisory lock: two logins at the same moment made one fail on PostgreSQL.
// 20261112 CL/MMR SD-815 Tenant table einvoice_incoming (each incoming e-document handled once) and the receiver_* columns on einvoice_sendlog (the receiver's answer to a sent invoice).
// 20261112 CL/MMR SD-815 (draft) On MySQL einvoice_sendlog is utf8mb4: in a latin1 database a Semantic text outside latin1 (e.g. 'Ł') made the write fail in strict mode.

/**
 * Injected by includes/connect.php via the entry page that includes this file:
 * @var string $db_type
 */

// ===== PROTECTED: when a version is cut (see doc/ai/convention_database_changes.md), COPY this =====
// ===== segment into the opdat_<major>.<minor>.php file - do not move it. The moved statements =====
// ===== call these helpers, and this file keeps needing them for the next release. =====
include_once(__DIR__ . '/opdat_func/deleteStaleTekst.php');
// ===== END PROTECTED =====

// guardrails-allow migrations until 2026-12-31 by NTR: 5.1.1 version cut retains the resumable backfill below
// Released MB-73 work can span several logins; keep this continuation outside the version gate.
// It returns immediately once the completion flag is set. Do not move it into a future release gate.
require_once __DIR__ . '/opdat_func/backfillOrderLineDelivered.php';
backfillOrderLineDelivered($db_type);

// 20261109 CL/MMR SD-815 (draft) einvoice_sendlog: one row per e-document sent through Semantic
// (includes/einvoice/semanticSendLog.php). It keeps the XML that was sent, packed, with its SHA-256 - Saldi's own
// archive of what it sent (bogføringsloven, 5 years) - and Semantic's document id, which the status webhook and the
// poll match on (never the invoice number: the invoice and credit note series share numbers). db_name is the database
// the row was written in. Tenant database only. The table check is one query per login. When the table is missing, the
// CREATE runs under an advisory lock and the check is repeated inside it: on PostgreSQL, CREATE TABLE IF NOT EXISTS is
// not safe for two logins at the same moment (the second fails on pg_type/pg_class), as the signup block above notes.
// MySQL has no CREATE INDEX IF NOT EXISTS, so its indexes are part of the CREATE TABLE. On MySQL the table is utf8mb4 whatever
// the database's default: Semantic's texts are UTF-8 and a latin1 column refuses other characters in strict mode.
$sendlogMysql = in_array($db_type, ['mysql', 'mysqli'], true);
$sendlogExists = "SELECT table_name FROM information_schema.tables WHERE table_name = 'einvoice_sendlog'";
$sendlogExists .= $sendlogMysql ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
if (!db_fetch_array(db_select($sendlogExists, __FILE__ . " linje " . __LINE__))) {
	// A PostgreSQL advisory lock belongs to the database; MySQL's lock names are server-wide, so the name holds the database.
	$sendlogLockName = "CONCAT('saldi:einvoice_sendlog:', MD5(DATABASE()))";
	$sendlogLock = $sendlogMysql ? "GET_LOCK($sendlogLockName, 30)" : "pg_advisory_lock(hashtext('saldi:einvoice_sendlog'))";
	$sendlogUnlock = $sendlogMysql ? "RELEASE_LOCK($sendlogLockName)" : "pg_advisory_unlock(hashtext('saldi:einvoice_sendlog'))";
	$sendlogLocked = db_fetch_array(db_select("SELECT $sendlogLock AS acquired", __FILE__ . " linje " . __LINE__));
	// MySQL returns 0 when the lock was not granted in time; the next login tries again.
	if (!$sendlogMysql || (int) ($sendlogLocked['acquired'] ?? 0) === 1) {
		try {
			// Another login may have created it while this one waited for the lock.
			if (!db_fetch_array(db_select($sendlogExists, __FILE__ . " linje " . __LINE__))) {
				$sendlogColumns = "ordre_id INTEGER, art VARCHAR(2), fakturanr VARCHAR(20), provider VARCHAR(20), network SMALLINT, ";
				$sendlogColumns.= "sender_endpoint_id VARCHAR(64), semantic_doc_id VARCHAR(40), ubl_doc_id VARCHAR(64), status VARCHAR(30), ";
				$sendlogColumns.= "status_text TEXT, xml " . ($sendlogMysql ? "LONGTEXT" : "TEXT") . ", xml_sha256 CHAR(64), db_name VARCHAR(64), ";
				$sendlogColumns.= "created TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, updated TIMESTAMP NULL, created_by VARCHAR(80), PRIMARY KEY (id)";
				if ($sendlogMysql) {
					$qtxt = "CREATE TABLE IF NOT EXISTS einvoice_sendlog (id INT AUTO_INCREMENT NOT NULL, $sendlogColumns, ";
					$qtxt.= "INDEX einvoice_sendlog_order_idx (ordre_id, art), INDEX einvoice_sendlog_semantic_idx (semantic_doc_id)) DEFAULT CHARSET=utf8mb4";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				} else {
					db_modify("CREATE TABLE IF NOT EXISTS einvoice_sendlog (id SERIAL NOT NULL, $sendlogColumns)", __FILE__ . " linje " . __LINE__);
					db_modify("CREATE INDEX IF NOT EXISTS einvoice_sendlog_order_idx ON einvoice_sendlog (ordre_id, art)", __FILE__ . " linje " . __LINE__);
					db_modify("CREATE INDEX IF NOT EXISTS einvoice_sendlog_semantic_idx ON einvoice_sendlog (semantic_doc_id)", __FILE__ . " linje " . __LINE__);
				}
			}
		} finally {
			db_select("SELECT $sendlogUnlock", __FILE__ . " linje " . __LINE__);
		}
	}
}

// 20261112 CL/MMR SD-815 Incoming e-documents through Semantic (includes/einvoice/semanticIncoming.php). einvoice_incoming
// holds one row per incoming document Semantic delivered (unique semantic_incoming_id), so the webhook and the poll never
// put the same invoice in the Bilagspulje twice. einvoice_sendlog gets the receiver's answer to a sent invoice (Peppol
// ApplicationResponse): receiver_status (UNCL4343), receiver_status_text, receiver_status_date, receiver_response_id.
// Runs after the einvoice_sendlog block above, which creates the table these columns go on. Same advisory-lock pattern,
// and MySQL gets its unique key inside the CREATE TABLE.
$incomingMysql = in_array($db_type, ['mysql', 'mysqli'], true);
$incomingSchema = $incomingMysql ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
$incomingTable = "SELECT table_name FROM information_schema.tables WHERE table_name = 'einvoice_incoming'$incomingSchema";
$incomingReceiver = "SELECT column_name FROM information_schema.columns WHERE table_name = 'einvoice_sendlog' AND column_name = 'receiver_response_id'$incomingSchema";
$incomingSendlog = "SELECT table_name FROM information_schema.tables WHERE table_name = 'einvoice_sendlog'$incomingSchema";
if (db_fetch_array(db_select($incomingSendlog, __FILE__ . " linje " . __LINE__))
	&& (!db_fetch_array(db_select($incomingTable, __FILE__ . " linje " . __LINE__)) || !db_fetch_array(db_select($incomingReceiver, __FILE__ . " linje " . __LINE__)))) {
	$incomingLockName = "CONCAT('saldi:einvoice_incoming:', MD5(DATABASE()))";
	$incomingLock = $incomingMysql ? "GET_LOCK($incomingLockName, 30)" : "pg_advisory_lock(hashtext('saldi:einvoice_incoming'))";
	$incomingUnlock = $incomingMysql ? "RELEASE_LOCK($incomingLockName)" : "pg_advisory_unlock(hashtext('saldi:einvoice_incoming'))";
	$incomingLocked = db_fetch_array(db_select("SELECT $incomingLock AS acquired", __FILE__ . " linje " . __LINE__));
	if (!$incomingMysql || (int) ($incomingLocked['acquired'] ?? 0) === 1) {
		try {
			if (!db_fetch_array(db_select($incomingReceiver, __FILE__ . " linje " . __LINE__))) {
				$qtxt = "ALTER TABLE einvoice_sendlog ADD COLUMN receiver_status VARCHAR(10) NULL, ADD COLUMN receiver_status_text TEXT NULL, ";
				$qtxt.= "ADD COLUMN receiver_status_date TIMESTAMP NULL, ADD COLUMN receiver_response_id VARCHAR(40) NULL";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
			if (!db_fetch_array(db_select($incomingTable, __FILE__ . " linje " . __LINE__))) {
				$incomingColumns = "semantic_incoming_id VARCHAR(40) NOT NULL, ubl_doc_id VARCHAR(64), document_type VARCHAR(30), document_number VARCHAR(100), ";
				$incomingColumns.= "sender_endpoint VARCHAR(100), sender_name VARCHAR(255), status VARCHAR(30), status_text TEXT, pool_filename VARCHAR(255), ";
				$incomingColumns.= "sendlog_id INTEGER, claim_token VARCHAR(64), claimed_at TIMESTAMP NULL, attempts INTEGER NOT NULL DEFAULT 0, received TIMESTAMP NULL, ";
				$incomingColumns.= "xml " . ($incomingMysql ? "LONGTEXT" : "TEXT") . ", db_name VARCHAR(64), created TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP, ";
				$incomingColumns.= "updated TIMESTAMP NULL, PRIMARY KEY (id)";
				if ($incomingMysql) {
					$qtxt = "CREATE TABLE IF NOT EXISTS einvoice_incoming (id INT AUTO_INCREMENT NOT NULL, $incomingColumns, ";
					$qtxt.= "UNIQUE KEY einvoice_incoming_semantic_id_key (semantic_incoming_id))";
				} else {
					$qtxt = "CREATE TABLE IF NOT EXISTS einvoice_incoming (id SERIAL NOT NULL, $incomingColumns, ";
					$qtxt.= "CONSTRAINT einvoice_incoming_semantic_id_key UNIQUE (semantic_incoming_id))";
				}
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		} finally {
			db_select("SELECT $incomingUnlock", __FILE__ . " linje " . __LINE__);
		}
	}
}

?>
