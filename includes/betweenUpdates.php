<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- ver 5.1.1 --- 2026.11.10
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
// 20261029 CL/MMR SST-841: rentalperiod.payment_id and payment_url (the Vibrant payment the server created for a booking).
// 20261102 CL/ASR Self-service signup (signup/, doc/signup.md): its master-database tables and the trial columns on regnskab, moved here from the flow's own first-use setup.
//                 Serialized with an advisory lock; a settings flag (system/signup_schema) marks it done.
// 20261104 CL/MMR SST-841 CodeRabbit (PR #969): the rentalperiod table and column lookups only look at the active schema (current_schema() / DATABASE()).
// 20261110 CL/MMR SST-841 CodeRabbit (PR #969): PostgreSQL adds the rentalperiod columns with IF NOT EXISTS, so two first logins at once cannot fail.
//                 MySQL adds them under a per-database GET_LOCK and reads the columns again while holding it.
// 20261110 CDX/NTR Release 5.1.1: move pending migrations into opdat_5.1.php and retain the bounded backfill continuation.

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
// 20261029 CL/MMR SST-841: rentalperiod.payment_id and payment_url hold the Vibrant payment the server created for
// a booking, so only that payment can approve it. Only accounts that use the rental module (the table exists) get
// the columns; the login-free booking pages only check for them (includes/vibrantPayment.php).
// Both lookups are limited to the active schema, as a rentalperiod table in another schema must not decide it.
// Two first logins at the same moment can both find them missing: PostgreSQL adds them with IF NOT EXISTS, MySQL
// (no IF NOT EXISTS) takes a per-database lock and reads the columns again while holding it. A MySQL login that does
// not get the lock in time leaves the columns to the next login.
if (isset($db) && is_string($db) && $db !== '') {
	$rentalMysql = in_array($db_type, ['mysql', 'mysqli'], true);
	$rentalSchema = $rentalMysql ? "table_schema = DATABASE()" : "table_schema = current_schema()";
	$rentalTable = db_fetch_array(db_select(
		"SELECT table_name FROM information_schema.tables WHERE $rentalSchema AND table_name = 'rentalperiod'",
		__FILE__ . " linje " . __LINE__
	));
	if ($rentalTable) {
		$rentalWanted = array('payment_id' => 'varchar(100)', 'payment_url' => 'varchar(500)');
		$rentalColumnsQuery = "SELECT column_name FROM information_schema.columns WHERE $rentalSchema AND table_name = 'rentalperiod'";
		$rentalColumns = array();
		$q = db_select($rentalColumnsQuery, __FILE__ . " linje " . __LINE__);
		while ($q && $r = db_fetch_array($q)) {
			$rentalColumns[] = $r['column_name'];
		}
		$rentalMissing = array_diff(array_keys($rentalWanted), $rentalColumns);
		if ($rentalMissing && !$rentalMysql) {
			foreach ($rentalMissing as $column) {
				db_modify("ALTER TABLE rentalperiod ADD COLUMN IF NOT EXISTS $column $rentalWanted[$column]", __FILE__ . " linje " . __LINE__);
			}
		} elseif ($rentalMissing) {
			$rentalLock = "CONCAT('saldi:rental_payment:', MD5(DATABASE()))";
			$rentalLocked = db_fetch_array(db_select("SELECT GET_LOCK($rentalLock, 30) AS acquired", __FILE__ . " linje " . __LINE__));
			if ((int)($rentalLocked['acquired'] ?? 0) === 1) {
				try {
					// Another login may have added them while this one waited for the lock.
					$rentalColumns = array();
					$q = db_select($rentalColumnsQuery, __FILE__ . " linje " . __LINE__);
					while ($q && $r = db_fetch_array($q)) {
						$rentalColumns[] = $r['column_name'];
					}
					foreach (array_diff(array_keys($rentalWanted), $rentalColumns) as $column) {
						db_modify("ALTER TABLE rentalperiod ADD COLUMN $column $rentalWanted[$column]", __FILE__ . " linje " . __LINE__);
					}
				} finally {
					db_select("SELECT RELEASE_LOCK($rentalLock)", __FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
}

?>
