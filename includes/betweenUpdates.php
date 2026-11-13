<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- ver 5.1.1 --- 2026.11.13
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
// 20261113 CL/MMR SST-898 Forms say "Mobilnr.: $egen_mobile" instead of "Fax: $egen_fax" (formularFaxToMobile()), and the stored old defaults of texts 378, 50 and 5230 are deleted so the new "Mobilnr." is used.

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

// SST-898: SD-532 made the fax field the mobile field (adresser.mobile). The forms still printed "Fax: <mobile number>",
// and accounts that had texts 378 (Stamdata, kreditor card, jobkort), 50 (form field list) or 5230 (debitor card) stored
// kept an old default. The field is now "Mobilnr." in Danish and Norwegian (Morten 2026-11-13), so the old defaults
// "Telefax"/"Fax"/"Faks", the interim "Mobile", the previous "Mobil" and "Mobilnummer" (5230, SD-525) are deleted and
// findtekst() reads the new default from importfiler/tekster.csv. English keeps "Mobile" / "Mobile number"; only its
// old "Fax" goes. Both steps only touch what is still old, so they are cheap at every login; a text a customer has
// edited (e.g. "Mobiltlf.") is kept.
require_once __DIR__ . '/opdat_func/formularFaxToMobile.php';
formularFaxToMobile();
deleteStaleTekst(378, array('Telefax', 'Mobile', 'Mobil'), 1);
deleteStaleTekst(378, 'Fax', 2);
deleteStaleTekst(378, array('Faks', 'Mobile', 'Mobil'), 3);
deleteStaleTekst(50, array('Fax', 'Mobile', 'Mobil'), 1);
deleteStaleTekst(50, 'Fax', 2);
deleteStaleTekst(50, array('Faks', 'Mobile', 'Mobil'), 3);
deleteStaleTekst(5230, 'Mobilnummer', 1);
deleteStaleTekst(5230, 'Mobilnummer', 3);

?>
