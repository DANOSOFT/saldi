<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- ver 5.0.0 --- 2026.10.27
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
// 20261019 CL/LH MB-73: default 0 for ordrelinjer.leveret.
//                One-time backfill of NULL leveret on sales, credit note and POS lines from their batch_salg delivery records.
// 20261027 CL/LH MB-73 (review): the leveret backfill runs in id windows under a try-lock with a time budget per
//                login (includes/opdat_func/backfillOrderLineDelivered.php) instead of one UPDATE at the first login.
//                The SET DEFAULT check also matches MariaDB, which reports a missing default as the text 'NULL'.

/**
 * Injected by includes/connect.php via the entry page that includes this file:
 * @var string $db_type
 */

// ===== PROTECTED: when a version is cut (see doc/ai/convention_database_changes.md), COPY this =====
// ===== segment into the opdat_<major>.<minor>.php file - do not move it. The moved statements =====
// ===== call these helpers, and this file keeps needing them for the next release. =====
include_once(__DIR__ . '/opdat_func/deleteStaleTekst.php');
// ===== END PROTECTED =====

// 20261019 CL/LH MB-73: ordrelinjer.leveret had no default and opret_ordrelinje() never set it, so
// linjeopdat()'s leveret+n stayed NULL. debitor/ordre.php re-syncs leveret from the delivery records
// whenever the order is shown (the sum of batch_salg.antal for the line, order and item), but fast
// invoicing never shows it, so invoiced and credited lines kept leveret NULL.
// The default is set only while it is still missing, checked in the current tenant's database/schema
// like the column checks now in includes/opdat_5.0.php. SET DEFAULT is a catalog change only; existing
// rows are not rewritten.
$leveretMysql = in_array($db_type, ['mysql', 'mysqli'], true);
// 20261027 CL/LH MB-73 (review): MariaDB reports a missing default as the text 'NULL', not as NULL.
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'ordrelinjer' AND column_name = 'leveret'";
$qtxt .= " AND (column_default IS NULL OR column_default = 'NULL')";
$qtxt .= $leveretMysql ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER TABLE ordrelinjer ALTER COLUMN leveret SET DEFAULT 0", __FILE__ . " linje " . __LINE__);
}
// One-time backfill, flagged in settings like the pool_files backfills in opdat_5.0.php: leveret becomes the same
// batch_salg sum ordre.php shows, for sales, credit note and POS lines that have delivery records. Lines
// without any (not delivered, text lines) are left as they are, as ordre.php leaves them. Purchase lines are
// excluded: their receipts are in batch_kob and kreditor/ordre.php keeps their leveret.
// 20261027 CL/LH MB-73 (review): as one UPDATE this took 4.5 minutes at the first login on a large POS tenant.
// backfillOrderLineDelivered() runs it in windows of ordrelinjer ids under a try-lock and stops after about a
// second; later logins carry on from the stored window, and a login that finds the lock taken skips it.
require_once __DIR__ . '/opdat_func/backfillOrderLineDelivered.php';
backfillOrderLineDelivered($db_type);

?>
