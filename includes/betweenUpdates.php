<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- ver 5.0.0 --- 2026.09.29
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
// 20260717 Live-import reconciliation: most of production's pending betweenUpdates.php
// content was already relocated into includes/opdat_4.3.php (see commit 74634e46); only the
// genuinely new statements below (not present in opdat_4.3.php) were pulled in from production.
// 20260717 CL/NTR Guard the API-key insert/update blocks so an existing but
//                  incomplete .ht_keys.txt can't silently write an empty var_value.
// 20260724 Sawaneh  MobilePay webhook reconciliation: add connect/read timeouts and
//                  fail-soft logging to the api.vipps.no calls, and gate the whole
//                  block behind a one-shot marker so it no longer runs (or makes any
//                  outbound HTTP) on every login.
// 20260728 CL/SZ Moved the Bilagsmatch pool_files.norm_amount/pg_trgm setup here from
//                  includes/opdat_4.3.php's opdat_to('4.3.0', ...) gate: that gate had
//                  already run on tenants (including the reviewer's test DB) before this
//                  code was added to it, so opdat_to() skipped the whole closure and
//                  norm_amount was never created there. fetchbilagsmatch.php then queried
//                  a nonexistent column, pg_query() failed, and the endpoint silently
//                  returned zero rows regardless of any actual match. All statements below
//                  are idempotent (existence/flag-checked), matching this file's pattern.
// 20260812 Sawaneh  Review: the callback url is read from settings ('mobilepay'/
//                  'webhook_base_url') instead of $_SERVER['SERVER_NAME'], so a crafted
//                  Host header can no longer redirect the payment callback; a webhook
//                  deletion counts only on 2xx and a failed one keeps the db
//                  unreconciled; and a 2xx list payload without a webhooks array is
//                  treated as a failure rather than as an empty list.
// 20260812 Sawaneh  The reconciliation itself moved to includes/stdFunc/mobilepayWebhookSync.php
//                  so it can be exercised against a stub endpoint; this file keeps the
//                  settings reads, the secret write and the one-shot marker.
// 20260908 CL/Sawaneh SST-763: pbs_ordrer attempt columns (oprettet, bruger_id, gensendt_fra,
//                     resultat*) and a unique (liste_id, ordre_id) index so one invoice can
//                     be resent in a later batch but never twice in the same batch.
// 20260914 CDX/LH Port ssl3 created_by columns for purchase and sales batches.
// 20260716 CL/LH Added unique Stripe paid-invoice import key.
// 20260918 CDX/PHR Add a separate performed_by field for the selected order employee.
// 20260921 CDX/LH Make performed_by creation safe for concurrent tenant updates.
// 20260921 Sawaneh  Review: an empty webhook_base_url is no longer seeded from webhook_reconciled_url -
//                  older markers came from SERVER_NAME, so that made an untrusted host canonical.
// 20260922 CL/LAH Leverandørforslag fra AI-scan: pool_files.vendor_name/vendor_cvr/vendor_iban/
//                  vendor_konto_id/vendor_match/vendor_score (kravspec Bilagsflow AI-3), Postgres
//                  and MySQL. Also added to both CREATE TABLE IF NOT EXISTS fallbacks in docPool.php.
// 20260924 Sawaneh SST-757: Give brugere rows with no regnskabsaar the newest open fiscal year.
//                  Sager -> Ansatte created them without one, which broke every fiscal_year query for those users.
// 20260928 CL/LH Widen int ordrer.shop_status to varchar(20) before creating the Stripe
//                  paid-invoice index; the string predicate blocked login on int-typed tenants.
// 20260928 CL/SZ SST-818: Indexes for the lookups finans/kassekladde.php runs for every line of an open journal (kassekladde by kladde_id and by account, documents by source line, adresser by kontonr).
// 20260929 CDX/PHR Initialize the tenant HTML layout version without changing existing forms.
// 20260930 CL/NTR The repeated tekster clean-ups now call deleteStaleTekst() (includes/opdat_func/),
//                  and the texts reworded on the translation branch are cleaned up too.

/**
 * Injected by includes/connect.php via the entry page that includes this file:
 * @var string $db_type
 */

// ===== PROTECTED: when a version is cut (see doc/ai/convention_database_changes.md), COPY this =====
// ===== segment into the opdat_<major>.<minor>.php file - do not move it. The moved statements =====
// ===== call these helpers, and this file keeps needing them for the next release. =====
include_once(__DIR__ . '/opdat_func/deleteStaleTekst.php');
// ===== END PROTECTED =====

$performedByMysql = in_array($db_type, ['mysql', 'mysqli'], true);
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrer' AND column_name='performed_by'";
$qtxt .= $performedByMysql ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	if ($performedByMysql) {
		// MySQL has no ADD COLUMN IF NOT EXISTS. Serialize this migration per tenant.
		$performedByLock = "CONCAT('saldi:performed_by:', MD5(DATABASE()))";
		$lockResult = db_fetch_array(db_select("SELECT GET_LOCK($performedByLock, 30) AS acquired", __FILE__ . " linje " . __LINE__));
		if ((int) ($lockResult['acquired'] ?? 0) !== 1) {
			throw new RuntimeException('Could not acquire the performed_by migration lock.');
		}
		try {
			// Another login may have added the column while this connection waited.
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				db_modify("ALTER TABLE ordrer ADD COLUMN performed_by TEXT", __FILE__ . " linje " . __LINE__);
			}
		} finally {
			db_select("SELECT RELEASE_LOCK($performedByLock)", __FILE__ . " linje " . __LINE__);
		}
	} else {
		db_modify("ALTER TABLE ordrer ADD COLUMN IF NOT EXISTS performed_by TEXT", __FILE__ . " linje " . __LINE__);
	}
}

// Bilagsmatch scoring engine: pool_files.amount is a free-form string ("1.234,56",
// "1,234.56", etc). Add a real NUMERIC column so matching can join on it directly
// instead of re-parsing the string with a regex on every query.
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='pool_files' and column_name='norm_amount'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER TABLE pool_files ADD COLUMN norm_amount NUMERIC(15,3)", __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT indexname FROM pg_indexes WHERE tablename = 'pool_files' AND indexname = 'idx_pool_files_norm_amount'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("CREATE INDEX idx_pool_files_norm_amount ON pool_files(norm_amount)", __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT 1 FROM information_schema.columns WHERE table_name='batch_kob' AND column_name='created_by' LIMIT 1";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER TABLE batch_kob ADD COLUMN created_by TEXT", __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT 1 FROM information_schema.columns WHERE table_name='batch_salg' AND column_name='created_by' LIMIT 1";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER TABLE batch_salg ADD COLUMN created_by TEXT", __FILE__ . " linje " . __LINE__);
}

// One-time backfill of norm_amount for rows written before this column existed.
$already_backfilled = db_fetch_array(db_select(
	"SELECT var_value FROM settings WHERE var_name = 'pool_files_norm_amount_backfilled' AND var_grp = 'system'",
	__FILE__ . " linje " . __LINE__
));
if (!$already_backfilled) {
	include_once(__DIR__ . "/docsIncludes/poolAmountNormalizer.php");
	$q_backfill = db_select("SELECT id, amount FROM pool_files WHERE norm_amount IS NULL AND amount IS NOT NULL AND amount != ''", __FILE__ . " linje " . __LINE__);
	while ($r_backfill = db_fetch_array($q_backfill)) {
		$normalized = normalizePoolAmount($r_backfill['amount']);
		if ($normalized !== null) {
			db_modify(
				"UPDATE pool_files SET norm_amount = " . db_escape_string((string) $normalized) . " WHERE id = " . (int) $r_backfill['id'],
				__FILE__ . " linje " . __LINE__
			);
		}
	}
	db_modify(
		"INSERT INTO settings (var_name, var_grp, var_value, var_description)
		VALUES ('pool_files_norm_amount_backfilled', 'system', 'yes', 'One-time backfill of pool_files.norm_amount from the legacy amount text column')",
		__FILE__ . " linje " . __LINE__
	);
}

// Ongoing catch-up (unlike the one-time backfill above, this has no settings flag - it
// stays cheap and self-limiting because the WHERE clause only ever matches rows still
// missing norm_amount). Several other pool_files write paths (docPool.php's pulje-folder
// sync and rename/edit flow, restapi/models/attachment/AttachmentModel.php's REST upload)
// wrote `amount` without ever computing `norm_amount`, so rows written through them before
// those call sites were fixed are still stuck at norm_amount = NULL - the same reason
// Bilagsmatch's amount_score always scored 0 for them.
include_once(__DIR__ . "/docsIncludes/poolAmountNormalizer.php");
$q_norm_catchup = db_select("SELECT id, amount FROM pool_files WHERE norm_amount IS NULL AND amount IS NOT NULL AND amount != ''", __FILE__ . " linje " . __LINE__);
while ($r_norm_catchup = db_fetch_array($q_norm_catchup)) {
	$normalized_catchup = normalizePoolAmount($r_norm_catchup['amount']);
	if ($normalized_catchup !== null) {
		db_modify(
			"UPDATE pool_files SET norm_amount = " . db_escape_string((string) $normalized_catchup) . " WHERE id = " . (int) $r_norm_catchup['id'],
			__FILE__ . " linje " . __LINE__
		);
	}
}

// Same reasoning as the norm_amount catch-up above, for currency: extractInvoiceHandler.php
// and the REST attachment upload path stored whatever currency string the AI extraction API
// returned verbatim (e.g. "kr" instead of "DKK") before normalizePoolCurrency() existed.
// fetchbilagsmatch.php's currency hard gate is a plain UPPER(TRIM(...)) string match, so an
// unrecognized alias silently excluded that file from every candidate regardless of score.
// Self-limiting the same way: once a row's currency is already normalized, this is a no-op -
// so scanning every non-empty row (rather than pre-filtering to "doesn't already look like a
// 3-letter code") is what's correct here. That prefilter used to exclude DKR/NKR/SKR: they're
// 3 uppercase letters too, so they matched "looks like a code already" and were skipped even
// though normalizePoolCurrency() maps them to DKK/NOK/SEK.
$q_currency_catchup = db_select("SELECT id, currency FROM pool_files WHERE currency IS NOT NULL AND currency != ''", __FILE__ . " linje " . __LINE__);
while ($r_currency_catchup = db_fetch_array($q_currency_catchup)) {
	$normalized_currency = normalizePoolCurrency($r_currency_catchup['currency']);
	if ($normalized_currency !== null && $normalized_currency !== $r_currency_catchup['currency']) {
		db_modify(
			"UPDATE pool_files SET currency = '" . db_escape_string($normalized_currency) . "' WHERE id = " . (int) $r_currency_catchup['id'],
			__FILE__ . " linje " . __LINE__
		);
	}
}

// Bilagsmatch text-similarity scoring uses pg_trgm when available; on tenants where
// CREATE EXTENSION isn't permitted (managed hosting without superuser), fetchbilagsmatch.php
// falls back to ILIKE/position() matching instead - this must never block the migration.
// Attempted only once (flag below) so a tenant that lacks the privilege doesn't retry
// (and re-email the error) on every future migration run.
$trgm_attempted = db_fetch_array(db_select(
	"SELECT var_value FROM settings WHERE var_name = 'pg_trgm_extension_attempted' AND var_grp = 'system'",
	__FILE__ . " linje " . __LINE__
));
if (!$trgm_attempted) {
	$qtxt = "SELECT 1 FROM pg_extension WHERE extname = 'pg_trgm'";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		db_modify("CREATE EXTENSION IF NOT EXISTS pg_trgm", __FILE__ . " linje " . __LINE__);
	}
	db_modify(
		"INSERT INTO settings (var_name, var_grp, var_value, var_description)
		VALUES ('pg_trgm_extension_attempted', 'system', 'yes', 'Whether pg_trgm CREATE EXTENSION has been attempted for Bilagsmatch text scoring')",
		__FILE__ . " linje " . __LINE__
	);
}

// The Bilagsmatch tekst_id block was renumbered from 5032-5047 to 5040-5055 (see
// finans/kassekladde_includes/bilagsmatch.php, "Renumber Bilagsmatch tekst_id block to
// avoid collision with upstream") because upstream/master independently claimed 5032-5039
// for unrelated GS1/POS strings. Tenants that had already exercised the feature under the
// OLD numbering got those 16 rows inserted into `tekster` via findtekst()'s self-healing
// insert - so the overlapping ids 5040-5047 still hold the OLD strings (Type/preview
// tooltip/lookup tooltip/Bilagsmatch title/Dato/Bilag/Tekst/Beløb) while the current code
// asks those same ids for the NEW strings (Konto/Modkonto/Valuta/Præcision/Annullér/summary
// template/"0 fundet"/empty-message). findtekst() always prefers an existing DB row over
// tekster.csv, so the Bilagsmatch popup silently showed the wrong label for all 8 ids -
// e.g. "Voucher no." (old 5045) instead of the live "0 matches selected · 0 found" summary
// (new 5045). Clear only rows still holding the exact old value (same pattern as the
// Stillingsliste fix below) so the next findtekst() call re-reads the correct text from
// tekster.csv (now at 5048-5055) and re-inserts it.
$bilagsmatch_stale_tekster = [
	[5040, 1, 'Type'], [5040, 2, 'Type'], [5040, 3, 'Type'],
	[5041, 1, 'Klik for at forhåndsvise/åbne dokumentet'], [5041, 2, 'Click to preview/open the document'], [5041, 3, 'Klikk for å forhåndsvise/åpne dokumentet'],
	[5042, 1, 'Slå konto op'], [5042, 2, 'Look up account'], [5042, 3, 'Slå opp konto'],
	[5043, 1, 'Bilagsmatch'], [5043, 2, 'Voucher match'], [5043, 3, 'Bilagsmatch'],
	[5044, 1, 'Dato'], [5044, 2, 'Date'], [5044, 3, 'Dato'],
	[5045, 1, 'Bilag'], [5045, 2, 'Voucher no.'], [5045, 3, 'Bilag'],
	[5046, 1, 'Tekst'], [5046, 2, 'Text'], [5046, 3, 'Tekst'],
	[5047, 1, 'Beløb'], [5047, 2, 'Amount'], [5047, 3, 'Beløp'],
];
foreach ($bilagsmatch_stale_tekster as $stale) {
	deleteStaleTekst($stale[0], $stale[2], $stale[1]);
}

deleteStaleTekst(38, 'Stillingsliste', 1);

# 20260715 CL/SZ - lager/rapport.php's "Bestilt" (Ordered) column query lost its ordrer.levdate
# range filter (see lager/rapport.php ~line 653) so open orders are found by status/leveret alone.
# Neither was ever indexed, so that query now scans far more rows than the old (incorrect)
# date-bounded version did. These target the actual filter conditions it uses.
db_modify("CREATE INDEX IF NOT EXISTS ordrer_status_idx ON ordrer (status)",__FILE__ . " linje " . __LINE__);
db_modify("CREATE INDEX IF NOT EXISTS ordrelinjer_open_ordre_id_idx ON ordrelinjer (ordre_id) WHERE leveret < antal",__FILE__ . " linje " . __LINE__);

# 20260715 CL/SZ - the ordrer.levdate range filter on the Bestilt query above was restored, so
# index that too now that it's back in active use.
db_modify("CREATE INDEX IF NOT EXISTS ordrer_levdate_idx ON ordrer (levdate)",__FILE__ . " linje " . __LINE__);

# 20260715 CL/SZ - lager/rapport.php's detailed Koeb/Salg loop calls find_kostpris()/
# find_varemomssats() (includes/ordrefunc.php / includes/std_func.php) once per order line -
# also used by debitor/ordre.php, kreditor/ordre.php(M) and includes/rapport.php. These hit
# batch_salg.linje_id, grupper (art,kodenr) and kontoplan (kontonr,regnskabsaar) with no
# supporting index (grupper/kontoplan only had their primary key), forcing a full table scan
# on every single order line processed - the main cost of a large report, not the Bestilt query.
db_modify("CREATE INDEX IF NOT EXISTS batch_salg_linje_id_idx ON batch_salg (linje_id)",__FILE__ . " linje " . __LINE__);
db_modify("CREATE INDEX IF NOT EXISTS grupper_art_kodenr_idx ON grupper (art, kodenr)",__FILE__ . " linje " . __LINE__);
db_modify("CREATE INDEX IF NOT EXISTS kontoplan_kontonr_regnskabsaar_idx ON kontoplan (kontonr, regnskabsaar)",__FILE__ . " linje " . __LINE__);

# 20260715 CL/SZ - lager/rapport.php's per-item loop looks up kostpriser (vare_id, transdate)
# once per item whenever the report's end date isn't today (~line 893). kostpriser only had its
# primary key, so every item forced a full table scan of kostpriser to find its latest price -
# on a large item report this is the same "no index on the hot per-row lookup" issue as above.
db_modify("CREATE INDEX IF NOT EXISTS kostpriser_vare_id_transdate_idx ON kostpriser (vare_id, transdate)",__FILE__ . " linje " . __LINE__);

// 20260928 CL/SZ SST-818: finans/kassekladde.php looks up kassekladde, documents and adresser once per journal line;
// none of these columns were indexed, so every lookup was a full table scan and a journal of a few hundred lines
// took several seconds to open. MySQL has no CREATE INDEX IF NOT EXISTS and needs a prefix length on a text column.
$kkIndexMysql = in_array($db_type, ['mysql', 'mysqli'], true);
$kkIndexes = array(
	'kassekladde_kladde_id_idx'        => array('kassekladde', 'kladde_id', 'kladde_id'),
	'kassekladde_debet_transdate_idx'  => array('kassekladde', 'debet, transdate, id', 'debet, transdate, id'),
	'kassekladde_kredit_transdate_idx' => array('kassekladde', 'kredit, transdate, id', 'kredit, transdate, id'),
	'documents_source_source_id_idx'   => array('documents', 'source, source_id', 'source, source_id'),
	'adresser_kontonr_art_idx'         => array('adresser', 'kontonr, art', 'kontonr(30), art'),
);
foreach ($kkIndexes as $kkIndexName => list($kkIndexTable, $kkIndexColumns, $kkIndexColumnsMysql)) {
	if ($kkIndexMysql) {
		$qtxt = "SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '$kkIndexTable' AND index_name = '$kkIndexName'";
		if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			db_modify("CREATE INDEX $kkIndexName ON $kkIndexTable ($kkIndexColumnsMysql)", __FILE__ . " linje " . __LINE__);
		}
	} else {
		db_modify("CREATE INDEX IF NOT EXISTS $kkIndexName ON $kkIndexTable ($kkIndexColumns)", __FILE__ . " linje " . __LINE__);
	}
}

# 20260924 CL/NTR Two concurrent logins can both pass the pg_indexes existence check before
#                  either has committed the CREATE UNIQUE INDEX, and the losing statement then
#                  fails with unique_violation (23505) on pg_class_relname_nsp_index, not
#                  duplicate_table (42P07) - so a WHEN duplicate_table handler would miss it.
#                  A session-level advisory lock around the check+create serializes this
#                  betweenUpdates.php run against itself without catching every unique_violation
#                  (duplicate ordrer.kundeordnr values must still fail).
db_select("SELECT pg_advisory_lock(hashtext('ordrer_stripe_paid_invoice_uidx'))", __FILE__ . " linje " . __LINE__);
$qtxt = "SELECT indexname FROM pg_indexes WHERE tablename = 'ordrer' AND indexname = 'ordrer_stripe_paid_invoice_uidx'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	# 20260928 CL/LH shop_status is still int on tenants created by admin/opret.php or upgraded
	#                through opdat_4.0.php - only api/rest_api.php widened it, on the first shop
	#                order. There the 'stripe_paid_bridge' literal below fails the integer cast and
	#                db_modify() alerts + exits, blocking login. Widen it first (same statement as
	#                rest_api.php; existing numeric values keep their digits as text).
	$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'ordrer' AND column_name = 'shop_status'";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if ($r && $r['data_type'] == 'integer') {
		db_modify("ALTER TABLE ordrer ALTER COLUMN shop_status TYPE varchar(20)", __FILE__ . " linje " . __LINE__);
	}
	$qtxt = "CREATE UNIQUE INDEX ordrer_stripe_paid_invoice_uidx ON ordrer (kundeordnr) WHERE art = 'DO' AND shop_status = 'stripe_paid_bridge'";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
db_select("SELECT pg_advisory_unlock(hashtext('ordrer_stripe_paid_invoice_uidx'))", __FILE__ . " linje " . __LINE__);

#####

include ("../includes/connect.php");
$qtxt = "SELECT id, var_value FROM settings WHERE var_name = 'apikey'  AND var_grp = 'app_api'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
if (!$r['id'] && file_exists("../../.ht_keys.txt")) {
	include ("../../.ht_keys.txt");
	if (!empty($aiApiKey)) {
		$qtxt = "insert into settings (var_name, var_grp, var_value, var_description) values ";
		$qtxt.= "('apikey', 'app_api', '$aiApiKey', 'apikey for the mobile app and voucher ai')";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
} elseif ($r['id'] && !$r['var_value'] && file_exists("../../.ht_keys.txt")) {
	include ("../../.ht_keys.txt");
	if (!empty($aiApiKey)) {
		$qtxt = "update settings set var_value = '$aiApiKey' where id = '$r[id]'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
}
$qtxt = "SELECT id, var_value FROM settings WHERE var_name = 'apiKey'  AND var_grp = 'easyUBL'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
if (!$r['id'] && file_exists("../../.ht_keys.txt")) {
	include ("../../.ht_keys.txt");
	if (!empty($easyUBLApiKey)) {
		$qtxt = "insert into settings (var_name, var_grp, var_value, var_description) values ";
		$qtxt.= "('apiKey', 'easyUBL', '$easyUBLApiKey', 'apikey for the easyUBL')";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
} elseif ($r['id'] && file_exists("../../.ht_keys.txt")) {
	include ("../../.ht_keys.txt");
	if (!empty($easyUBLApiKey)) {
		$qtxt = "update settings set var_value = '$easyUBLApiKey' where id = '$r[id]'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
}

include ("../includes/online.php");

$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'settings' and  column_name = 'digital_status'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE settings ADD digital_status varchar(25)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'variant_varer' and  column_name = 'variant_text'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE variant_varer ADD variant_text varchar(25)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

// easyUBL
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='timereg_sessions'";
if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE timereg_sessions (
		id SERIAL PRIMARY KEY NOT NULL,
		user_id integer NOT NULL,
		status varchar(15) NOT NULL,
		planned_start timestamp,
		planned_stop timestamp,
		actual_start timestamp NOT NULL,
		actual_stop timestamp,
		length integer,
		comment_start varchar(400),
		comment_stop varchar(400),
		godkendt boolean,
		loen numeric
		)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='timereg_breaks'";
if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE timereg_breaks (
		id SERIAL PRIMARY KEY NOT NULL,
		session_id integer NOT NULL,
		t_start timestamp NOT NULL,
		t_stop timestamp,
		length integer)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

// MobilePay: ensure webhook is registered for the current server
$q = db_select("SELECT var_value FROM settings WHERE var_grp = 'mobilepay' AND var_name = 'client_id'", __FILE__ . " linje " . __LINE__);
$mp_client_id = db_fetch_array($q)['var_value'] ?? null;
if ($mp_client_id) {
	$q = db_select("SELECT var_value FROM settings WHERE var_grp = 'mobilepay' AND var_name = 'client_secret'", __FILE__ . " linje " . __LINE__);
	$mp_client_secret = db_fetch_array($q)['var_value'];
	$q = db_select("SELECT var_value FROM settings WHERE var_grp = 'mobilepay' AND var_name = 'subscriptionKey'", __FILE__ . " linje " . __LINE__);
	$mp_subscription = db_fetch_array($q)['var_value'];
	$q = db_select("SELECT var_value FROM settings WHERE var_grp = 'mobilepay' AND var_name = 'MSN'", __FILE__ . " linje " . __LINE__);
	$mp_msn = db_fetch_array($q)['var_value'];

	// One-shot gate: reconciliation only talks to Vipps once per webhook URL. Once the
	// URL for this server/db is confirmed, the stored marker matches $expected_url and
	// the whole block (and all outbound HTTP) is skipped on subsequent logins. A changed
	// base url/db, or a first-time setup, changes/clears the marker and re-triggers it.
	$q = db_select("SELECT var_value FROM settings WHERE var_grp = 'mobilepay' AND var_name = 'webhook_reconciled_url'", __FILE__ . " linje " . __LINE__);
	$mp_reconciled_url = db_fetch_array($q)['var_value'] ?? null;

	// The callback url must NOT come from $_SERVER['SERVER_NAME']: with Apache's default
	// UseCanonicalName Off that follows the request's Host header, so a crafted Host on a
	// login request could make this code delete the real webhook and register the payment
	// callback at an attacker's address. It is read from settings instead.
	$q = db_select("SELECT var_value FROM settings WHERE var_grp = 'mobilepay' AND var_name = 'webhook_base_url'", __FILE__ . " linje " . __LINE__);
	$mp_base_row = db_fetch_array($q);
	$mp_webhook_base = trim((string)($mp_base_row['var_value'] ?? ''));

	// An existing webhook_reconciled_url is NOT adopted as the base url: markers written before
	// 20260812 were built from $_SERVER['SERVER_NAME'], so adopting one would make a host taken
	// from request metadata canonical. Its host is only named in the log as a hint for whoever
	// fills in the setting.
	$mp_base_parts = $mp_webhook_base !== '' ? parse_url($mp_webhook_base) : false;
	if (!$mp_base_parts || empty($mp_base_parts['host']) || strtolower($mp_base_parts['scheme'] ?? '') !== 'https') {
		// Without a configured base url there is nothing safe to reconcile against, so no
		// webhook is deleted or registered. Vipps keeps delivering to whatever is already
		// registered; only reconciliation waits.
		$mp_hint = $mp_reconciled_url ? (string)parse_url($mp_reconciled_url, PHP_URL_HOST) : '';
		error_log("betweenUpdates.php: MobilePay webhook reconciliation skipped - set settings var_grp 'mobilepay', var_name 'webhook_base_url' to the canonical https base url for this installation" . ($mp_hint !== '' ? " (last reconciled host, unverified: " . preg_replace('/[^A-Za-z0-9.:-]/', '', $mp_hint) . ")" : ''));
		$expected_url = null;
	} else {
		$expected_url = rtrim($mp_webhook_base, '/') . '/pos/debitor/payments/mobilepay/webhook_recive.php?db=' . $db;
	}

	if ($expected_url !== null && $mp_reconciled_url !== $expected_url) {
		include_once(__DIR__ . '/stdFunc/mobilepayWebhookSync.php');
		$mp_result = mobilepay_webhook_sync(array(
			'expectedUrl'     => $expected_url,
			'db'              => $db,
			'clientId'        => $mp_client_id,
			'clientSecret'    => $mp_client_secret,
			'subscriptionKey' => $mp_subscription,
			'msn'             => $mp_msn,
		));
		foreach ($mp_result['errors'] as $mp_error) {
			error_log("betweenUpdates.php: MobilePay webhook reconciliation - $mp_error");
		}

		if ($mp_result['secret']) {
			db_modify("DELETE FROM settings WHERE var_grp = 'mobilepay' AND var_name = 'webhook_secret'", __FILE__ . " linje " . __LINE__);
			$new_secret = db_escape_string($mp_result['secret']);
			db_modify("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('webhook_secret', 'mobilepay', '$new_secret', 'The secret that is generated for the webhook')", __FILE__ . " linje " . __LINE__);
		}

		// Persist the marker only after a confirmed reconciliation, so a transient Vipps
		// outage - or a stale webhook that could not be deleted - leaves it unchanged and
		// the next login retries rather than assuming success.
		if ($mp_result['reconciled']) {
			$new_reconciled_url = db_escape_string($expected_url);
			if ($mp_reconciled_url === null) {
				db_modify("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('webhook_reconciled_url', 'mobilepay', '$new_reconciled_url', 'Last webhook URL reconciled with Vipps - one-shot gate for betweenUpdates.php')", __FILE__ . " linje " . __LINE__);
			} else {
				db_modify("UPDATE settings SET var_value = '$new_reconciled_url' WHERE var_grp = 'mobilepay' AND var_name = 'webhook_reconciled_url'", __FILE__ . " linje " . __LINE__);
			}
		}
	}
}

// R5 moms periodelaasning — opret/reparer tabel, funktion og trigger ved login.
// SD-646: moms_periode_luk_ensure_schema() (includes/std_func.php) checker og
// reparerer hvert af de tre objekter uafhaengigt (tabel/funktion/trigger), saa
// en delvis installation - fx tabellen oprettet men funktion/trigger fejlede
// stille - selvhelbreder ved dette login i stedet for kun at blive opdaget naar
// selve triggeren mangler.
moms_periode_luk_ensure_schema();

// Add note column to moms_periode_luk if not present (idempotent guard).
$qtxt = "SELECT 1 FROM information_schema.columns WHERE table_name='moms_periode_luk' AND column_name='note' LIMIT 1";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    db_modify("ALTER TABLE moms_periode_luk ADD COLUMN note TEXT", __FILE__ . " linje " . __LINE__);
}

// 20260812 CL/SZ - Bilagsmatch's pinned-preview attachment icon tooltip (tekst_id 5071) was
// reworded from "Click to open the document" to "Click to see attachment" now that the
// hover preview is gone and this icon is the only way to view an attachment. findtekst()
// always prefers an existing DB row over tekster.csv (same issue as the ids-5040-5047 block
// above), so clear only rows still holding the exact old text - the next findtekst() call
// re-reads the new text from tekster.csv and re-inserts it.
$bilagsmatch_stale_tooltip_5071 = [
	[5071, 1, 'Klik for at åbne dokumentet'], [5071, 2, 'Click to open the document'], [5071, 3, 'Klikk for å åpne dokumentet'],
];
foreach ($bilagsmatch_stale_tooltip_5071 as $stale) {
	deleteStaleTekst($stale[0], $stale[2], $stale[1]);
}

// 20260807 CL/LH Stripe subscriptions: four tables + indexes for the native Stripe
// integration (doc/stripe/INTERFACE_CONTRACT.md). Placed HERE and in admin/opret.php,
// deliberately NOT in opdat_4.3.php - its opdat_to('4.3.0') gate has already run on
// existing tenants, so anything added there is silently skipped (see the 20260728 note
// at the top of this file). All statements are idempotent. Indexes exist only here:
// the partial unique index is PostgreSQL-only syntax.
// NB: column is billing_interval, not "interval" - INTERVAL is a reserved word in
// PostgreSQL/MySQL. No stripe code may ever read or write ordrer.nextfakt.
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='stripe_catalog'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE stripe_catalog (
		id SERIAL PRIMARY KEY,
		varenr text,
		stripe_price_id varchar(255),
		stripe_product_id varchar(255),
		unit_ore integer,
		billing_interval varchar(10) NOT NULL DEFAULT 'month',
		interval_count integer NOT NULL DEFAULT 1,
		currency varchar(3) NOT NULL DEFAULT 'DKK',
		active boolean NOT NULL DEFAULT true,
		created_at timestamp DEFAULT CURRENT_TIMESTAMP)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='stripe_events'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE stripe_events (
		id SERIAL PRIMARY KEY,
		event_id varchar(255) NOT NULL,
		event_type varchar(100),
		payload text,
		status varchar(30) NOT NULL DEFAULT 'received',
		saldi_order_id integer,
		invoice_number varchar(30),
		error text,
		received_at timestamp DEFAULT CURRENT_TIMESTAMP,
		processed_at timestamp)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='stripe_customers'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE stripe_customers (
		id SERIAL PRIMARY KEY,
		stripe_customer_id varchar(255) NOT NULL,
		stripe_subscription_id varchar(255),
		konto_id integer,
		kontonr varchar(30),
		order_id integer,
		status varchar(30) NOT NULL DEFAULT 'active',
		created_at timestamp DEFAULT CURRENT_TIMESTAMP,
		updated_at timestamp)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='stripe_import_failures'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE stripe_import_failures (
		id SERIAL PRIMARY KEY,
		event_id varchar(255),
		stripe_invoice_id varchar(255),
		reason varchar(50),
		http_code integer,
		message text,
		payload_json text,
		created_at timestamp DEFAULT CURRENT_TIMESTAMP,
		resolved_at timestamp)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
// Indexes: replay-safety (unique event), one active mapping per varenr (partial
// unique - PostgreSQL only), and the webhook's two customer lookups.
$qtxt = "SELECT indexname FROM pg_indexes WHERE tablename = 'stripe_events' AND indexname = 'stripe_events_event_id_uidx'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("CREATE UNIQUE INDEX stripe_events_event_id_uidx ON stripe_events (event_id)", __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT indexname FROM pg_indexes WHERE tablename = 'stripe_catalog' AND indexname = 'stripe_catalog_varenr_active_uidx'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("CREATE UNIQUE INDEX stripe_catalog_varenr_active_uidx ON stripe_catalog (varenr) WHERE active", __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT indexname FROM pg_indexes WHERE tablename = 'stripe_customers' AND indexname = 'idx_stripe_customers_customer_id'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("CREATE INDEX idx_stripe_customers_customer_id ON stripe_customers (stripe_customer_id)", __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT indexname FROM pg_indexes WHERE tablename = 'stripe_customers' AND indexname = 'idx_stripe_customers_konto_id'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("CREATE INDEX idx_stripe_customers_konto_id ON stripe_customers (konto_id)", __FILE__ . " linje " . __LINE__);
}
// 20260819 CL/LH Per-debtor opt-out for kortbetaling ("Ingen kortbetaling" on the
// debitorkort): overrides templates and catalog - the link helper renders '' and
// subscribe.php parks. Does NOT touch already-running subscriptions.
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='adresser' AND column_name='stripe_fravalg'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER TABLE adresser ADD stripe_fravalg varchar(2)", __FILE__ . " linje " . __LINE__);
}


$qtxt = "SELECT data_type FROM information_schema.columns WHERE table_name = 'ansatte' and column_name = 'mobile'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	# IF NOT EXISTS because betweenUpdates.php runs at login: two concurrent logins can both
	# get past the check above, and one of the two ALTER statements would then fail.
	$qtxt = "ALTER TABLE ansatte ADD COLUMN IF NOT EXISTS mobile text";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}


// 20260827 NTR Tekst 351: ' - not changed' suffix added in all three languages. Delete rows still
// holding the old text so findtekst() re-seeds them from tekster.csv. Guarded on the old values
// because betweenUpdates.php runs at every login and customer-edited texts must not be wiped.
$gamle_351 = array('Kontonummer findes allerede', 'not changed', 'Kontonummer eksisterer allerede');
deleteStaleTekst(351, $gamle_351);

$cvr_gamle_tekster = array(
	'Auto-opslag','Auto lookup','Auto-oppslag',
	'CVR-opslaget kunne ikke gennemføres. Udfyld felterne manuelt.',
	'The VAT lookup could not be completed. Please fill in the fields manually.',
	'Oppslaget kunne ikke gjennomføres. Fyll ut feltene manuelt.',
	'Kvoten for CVR-opslag er opbrugt.','The quota for VAT lookups has been used up.','Kvoten for oppslag er brukt opp.',
	'CVR-nummeret blev ikke fundet.','The VAT number was not found.','Organisasjonsnummeret ble ikke funnet.',
	'CVR-nummeret er ikke gyldigt.','The VAT number is not valid.','Organisasjonsnummeret er ikke gyldig.',
	'Søger...','Searching...','Søker...'
);
deleteStaleTekst([5040, 5046], $cvr_gamle_tekster);
deleteStaleTekst([5040, 5046], ['Tast CVR-nr. efterfulgt', 'Enter the VAT no. followed', 'Tast inn org.nr. etterfulgt'], null, true);

// 20260908 CL/Sawaneh SST-763: one pbs_ordrer row per PBS attempt. New columns record who/when,
// the Nets result registered by the user and which earlier attempt a resend replaces.
// brugernavn is stored as text too: revisor/superuser logins have bruger_id = -1 (no brugere row).
$pbs_ordrer_kolonner = array(
	'oprettet' => 'timestamp', 'bruger_id' => 'integer', 'brugernavn' => 'text', 'gensendt_fra' => 'integer',
	'resultat' => 'varchar(16)', 'resultat_ref' => 'text', 'resultat_dato' => 'date', 'resultat_bruger_id' => 'integer'
);
$pbs_mysql = ($db_type == 'mysql' || $db_type == 'mysqli');
foreach ($pbs_ordrer_kolonner as $pbs_kolonne => $pbs_type) {
	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'pbs_ordrer' AND column_name = '$pbs_kolonne'";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		# IF NOT EXISTS (Postgres/MariaDB, not MySQL) because betweenUpdates.php runs at login and two
		# concurrent logins can both pass the check above.
		$pbs_if_not_exists = $pbs_mysql ? '' : 'IF NOT EXISTS ';
		db_modify("ALTER TABLE pbs_ordrer ADD COLUMN $pbs_if_not_exists$pbs_kolonne $pbs_type", __FILE__ . " linje " . __LINE__);
	}
}
// Legacy pbsfakt() blocked any second row per invoice, so duplicates within a batch should not
// exist; remove any (keep the oldest) before the unique index so the statement cannot fail.
if ($pbs_mysql) {
	$qtxt = "SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pbs_ordrer' AND index_name = 'pbs_ordrer_liste_ordre_uidx'";
	$pbs_dedupe = "DELETE a FROM pbs_ordrer a JOIN pbs_ordrer b ON a.liste_id = b.liste_id AND a.ordre_id = b.ordre_id AND a.id > b.id";
	$pbs_index = "CREATE UNIQUE INDEX pbs_ordrer_liste_ordre_uidx ON pbs_ordrer (liste_id, ordre_id)";
} else {
	$qtxt = "SELECT indexname FROM pg_indexes WHERE tablename = 'pbs_ordrer' AND indexname = 'pbs_ordrer_liste_ordre_uidx'";
	$pbs_dedupe = "DELETE FROM pbs_ordrer a USING pbs_ordrer b WHERE a.liste_id = b.liste_id AND a.ordre_id = b.ordre_id AND a.id > b.id";
	$pbs_index = "CREATE UNIQUE INDEX IF NOT EXISTS pbs_ordrer_liste_ordre_uidx ON pbs_ordrer (liste_id, ordre_id)";
}
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify($pbs_dedupe, __FILE__ . " linje " . __LINE__);
	db_modify($pbs_index, __FILE__ . " linje " . __LINE__);
}

// 20260910 CL/SZ SST-776 follow-up (root cause identified during the SST-740 trace on
// 20260908, but PR #584 only shipped the FileReservation/session-tenant half - this closes
// the other half): pool_files never had a uniqueness constraint on filename in the
// schema paths that actually provision it (admin/opret.php, includes/opdat_4.1.php,
// includes/opdat_4.2.php all create it without one - only docPool.php's own from-scratch
// CREATE TABLE IF NOT EXISTS included one, which never runs against a tenant that already
// has the table). Combined with docPool.php's pulje-folder sync only ever inserting a row
// when the filename string isn't already known - never checking whether the tracked row
// still corresponds to a file actually on disk - a row orphaned by any pulje-file removal
// other than the guarded attach flow in includes/docsIncludes/insertDoc.php (a manual
// delete, a failed move, etc.) could sit forever and later get silently re-attached to an
// unrelated upload that happened to reuse the same generated filename (recurring vendor +
// date names, e.g. NETS/META, collide easily). The customer then sees one document's real
// PDF paired with a different document's vendor/amount/date/invoice number. Same
// dedupe-then-constrain pattern as pbs_ordrer_liste_ordre_uidx above: existing duplicate
// filenames are collapsed to the highest id (most recently inserted row) before the index
// is added, so this cannot fail on data that predates the fix. The docPool.php sync itself
// now also deletes any row whose file is no longer in the pulje folder, which is what
// actually prevents new orphans going forward - this index is the backstop against a race
// between two requests doing that same reconciliation concurrently.
if ($db_type == 'mysql' || $db_type == 'mysqli') {
	// Group by index_name and require exactly one column in the index - seq_in_index = 1
	// alone would also match a composite index like UNIQUE(filename, account), which
	// still permits duplicate filenames and must not be treated as covering this.
	$qtxt = "SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'pool_files' AND non_unique = 0 GROUP BY index_name HAVING COUNT(*) = 1 AND MAX(column_name) = 'filename'";
	$pool_files_dedupe = "DELETE a FROM pool_files a JOIN pool_files b ON a.filename = b.filename AND a.id < b.id";
	$pool_files_index = "CREATE UNIQUE INDEX pool_files_filename_uidx ON pool_files (filename)";
} else {
	// Checked against pg_index/pg_attribute directly (not a fixed index name, and not
	// information_schema.table_constraints) because a tenant whose pool_files table
	// didn't exist yet when docPool.php's own CREATE TABLE IF NOT EXISTS bootstrap first
	// ran (its schema already includes an unnamed UNIQUE(filename) table constraint,
	// which Postgres auto-names pool_files_filename_key) already has this covered under
	// a different name - confirmed live against a real customer dump (saldi_821_verify,
	// used for MB-39) that has exactly that constraint with zero duplicate filenames.
	// Checking any single-column unique index on filename, regardless of name or
	// whether it backs a formal constraint, avoids creating a second redundant index
	// on tenants provisioned that way.
	$qtxt = "
		SELECT indexrelid::regclass AS idxname
		FROM pg_index i
		JOIN pg_class t ON t.oid = i.indrelid
		WHERE t.relname = 'pool_files'
			AND i.indisunique
			AND i.indnatts = 1
			AND i.indkey[0] = (SELECT attnum FROM pg_attribute WHERE attrelid = t.oid AND attname = 'filename')
	";
	$pool_files_dedupe = "DELETE FROM pool_files a USING pool_files b WHERE a.filename = b.filename AND a.id < b.id";
	$pool_files_index = "CREATE UNIQUE INDEX IF NOT EXISTS pool_files_filename_uidx ON pool_files (filename)";
}
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify($pool_files_dedupe, __FILE__ . " linje " . __LINE__);
	db_modify($pool_files_index, __FILE__ . " linje " . __LINE__);
}

// 20260922 CL/LAH Leverandørforslag fra AI-scan (kravspec Bilagsflow AI-3): the vendor read on a
// scanned invoice and its match against kreditorer (adresser art='K'), written by
// includes/docsIncludes/extractInvoiceHandler.php and read back by includes/_docPoolData.php.
// Guarded per column so the block is idempotent on both Postgres and MySQL; betweenUpdates.php
// runs at every login.
$poolVendorMysql = in_array($db_type, ['mysql', 'mysqli'], true);
// vendor_match last on purpose: _docPoolData.php and the REST AttachmentModel probe for that
// column before selecting/inserting the others, so once it exists the rest are guaranteed.
$poolVendorColumns = array(
	'vendor_name' => 'text',
	'vendor_cvr' => 'varchar(20)',
	'vendor_iban' => 'varchar(40)',
	'vendor_konto_id' => 'integer',
	'vendor_score' => 'numeric(4,3)',
	'vendor_match' => 'varchar(10)',
);
$poolVendorMissing = array();
foreach ($poolVendorColumns as $poolVendorColumn => $poolVendorType) {
	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'pool_files' AND column_name = '$poolVendorColumn'";
	$qtxt .= $poolVendorMysql ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$poolVendorMissing[$poolVendorColumn] = $qtxt;
	}
}
if ($poolVendorMissing) {
	if ($poolVendorMysql) {
		// MySQL has no ADD COLUMN IF NOT EXISTS and two concurrent logins can both pass the
		// check above; serialize per tenant and recheck under the lock (same as performed_by).
		$poolVendorLock = "CONCAT('saldi:pool_files_vendor:', MD5(DATABASE()))";
		$poolVendorLockResult = db_fetch_array(db_select("SELECT GET_LOCK($poolVendorLock, 30) AS acquired", __FILE__ . " linje " . __LINE__));
		if ((int) ($poolVendorLockResult['acquired'] ?? 0) !== 1) {
			throw new RuntimeException('Could not acquire the pool_files vendor migration lock.');
		}
		try {
			foreach ($poolVendorMissing as $poolVendorColumn => $poolVendorProbe) {
				if (!db_fetch_array(db_select($poolVendorProbe, __FILE__ . " linje " . __LINE__))) {
					db_modify("ALTER TABLE pool_files ADD COLUMN $poolVendorColumn " . $poolVendorColumns[$poolVendorColumn], __FILE__ . " linje " . __LINE__);
				}
			}
		} finally {
			db_select("SELECT RELEASE_LOCK($poolVendorLock)", __FILE__ . " linje " . __LINE__);
		}
	} else {
		foreach ($poolVendorMissing as $poolVendorColumn => $poolVendorProbe) {
			db_modify("ALTER TABLE pool_files ADD COLUMN IF NOT EXISTS $poolVendorColumn " . $poolVendorColumns[$poolVendorColumn], __FILE__ . " linje " . __LINE__);
		}
	}
}

// 20260923 CL/NTR Tekst 242 (Ryk alle hover on the debtor openpost report) was an unclosed
// <big>/<UL>/<LI> fragment - overly bureaucratic-looking for a one-line explanation. Delete rows
// still holding the old text so findtekst() re-seeds them from tekster.csv with plain text.
// Guarded on the old values because betweenUpdates.php runs at every login and customer-edited
// texts must not be wiped.
$gamle_242 = array(
	'<big>Denne funktion gør følgende:<UL><LI>udligner alle konti',
	'<big>This feature does the following: <UL> <LI> settles all accounts',
	'<big> Denne funksjonen gjør følgende: <UL> <LI> gjør opp alle kontoer'
);
deleteStaleTekst(242, $gamle_242);

// 20260930 CL/NTR Texts reworded in importfiler/tekster.csv on the translation branch (vareliste,
// indkøb, ordrestatus and serienumre pages). findtekst() prefers an existing DB row over the csv, so
// delete the rows still holding the old text; the next findtekst() call re-seeds the new text.
// Entries are [tekst_id, sprog_id, old text]. Rows that only gained a text (empty before) need no entry.
$tekster_reworded_20260930 = [
	[373, 3, 'Løp. md.'],
	[429, 2, 'Category'],
	[429, 3, 'Kategori'],
	[544, 2, 'Invoice Display'],
	[545, 2, 'Offer Display'],
	[545, 3, 'Tilbyr utsikt'],
	[546, 2, 'Order Display'],
	[546, 3, 'Bestill skjerm'],
	[954, 3, 'Kjøpsforslag'],
	[967, 3, 'Gjenstandsnavn'],
	[988, 2, 'Supplier'],
	[988, 3, 'Leverandør'],
	[1208, 2, 'Start mnth.'],
	[1208, 3, 'Start md.'],
	[1210, 2, 'End mnth.'],
	[1210, 3, 'Slutt md.'],
	[2640, 2, ' Click here to add a new product'],
	[2640, 3, 'Klikk her for å opprette et nytt produkt'],
	[2641, 2, 'Your product list is displayed here. Click a item number to open it.'],
	[2648, 3, 'Her ser du hvor mye systemet anbefaler at du bestiller på nytt. Dette beregnes ut fra lagerbeholdning, ordrer og andre faktorer.'],
	[2652, 3, 'Hvis varen skal bestilles i bestemte mengder, kan du sette opp systemet til å bestille i for eksempel partier på f.eks.'],
	[2656, 3, 'Her kan du se hvor produktet ble kjøpt, hvilken leverandør det ble kjøpt fra, og ordrenummeret'],
	[2657, 3, 'Her kan du finne informasjon om hvor produktet ble solgt, hvem kjøperen var, og ordrenummeret'],
];
foreach ($tekster_reworded_20260930 as $reworded) {
	deleteStaleTekst($reworded[0], $reworded[2], $reworded[1]);
}

// 20260924 Sawaneh SST-757: Users created via Sager -> Ansatte were inserted without regnskabsaar. Checked with a
// select first so logins with nothing to repair do not write, and skipped on tenants with no open fiscal year.
if (db_fetch_array(db_select("select id from brugere where regnskabsaar is null limit 1", __FILE__ . " linje " . __LINE__))) {
	$newestFiscalYear = newest_active_fiscal_year();
	if ($newestFiscalYear) {
		db_modify("update brugere set regnskabsaar = '$newestFiscalYear' where regnskabsaar is null", __FILE__ . " linje " . __LINE__);
	}
}
// Preserve HTML users before the renderer changes; explicit choices survive later updates.
require_once __DIR__ . '/formFuncIncludes/htmlLayoutVersion.php';
initializeFormHtmlLayoutVersion($db_type);

?>
