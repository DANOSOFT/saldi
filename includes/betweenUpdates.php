<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- patch 5.0.0--- 2026.06.15
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260717 Live-import reconciliation: most of production's pending betweenUpdates.php
// content was already relocated into includes/opdat_4.3.php (see commit 74634e46); only the
// genuinely new statements below (not present in opdat_4.3.php) were pulled in from production.
// 20260717 CL/NTR Guard the API-key insert/update blocks so an existing but
//                  incomplete .ht_keys.txt can't silently write an empty var_value.
// 20260728 CL/SZ Moved the Bilagsmatch pool_files.norm_amount/pg_trgm setup here from
//                  includes/opdat_4.3.php's opdat_to('4.3.0', ...) gate: that gate had
//                  already run on tenants (including the reviewer's test DB) before this
//                  code was added to it, so opdat_to() skipped the whole closure and
//                  norm_amount was never created there. fetchbilagsmatch.php then queried
//                  a nonexistent column, pg_query() failed, and the endpoint silently
//                  returned zero rows regardless of any actual match. All statements below
//                  are idempotent (existence/flag-checked), matching this file's pattern.



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
	$qtxt = "select id from tekster where sprog_id = '$stale[1]' and tekst_id = '$stale[0]' and tekst = '" . db_escape_string($stale[2]) . "'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		db_modify("update tekster set tekst = '' where id = '$r[id]'", __FILE__ . " linje " . __LINE__);
	}
}

$qtxt = "Select id from tekster where sprog_id = '1' and tekst_id = '38' and tekst = 'Stillingsliste'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("update tekster set tekst = '' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
}

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

	$expected_url = 'https://' . $_SERVER['SERVER_NAME'] . '/pos/debitor/payments/mobilepay/webhook_recive.php?db=' . $db;

	// Get access token
	$ch = curl_init('https://api.vipps.no/accesstoken/get');
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Content-Type: application/json',
		"Client_id: $mp_client_id",
		"Client_secret: $mp_client_secret",
		"Ocp-Apim-Subscription-Key: $mp_subscription",
		"Merchant-Serial-Number: $mp_msn",
		'Content-Length: 0'
	]);
	$token_resp = json_decode(curl_exec($ch), true);
	curl_close($ch);
	$mp_token = $token_resp['access_token'] ?? null;

	if ($mp_token) {
		$mp_headers = [
			"Authorization: Bearer $mp_token",
			"Ocp-Apim-Subscription-Key: $mp_subscription",
			"Merchant-Serial-Number: $mp_msn",
			'Content-Type: application/json'
		];

		// List registered webhooks
		$ch = curl_init('https://api.vipps.no/webhooks/v1/webhooks');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $mp_headers);
		$webhooks = json_decode(curl_exec($ch), true)['webhooks'] ?? [];
		curl_close($ch);

		$correct_webhook_exists = false;
		foreach ($webhooks as $wh) {
			if ($wh['url'] === $expected_url) {
				$correct_webhook_exists = true;
			} else {
				// Delete webhook pointing to a different URL for this db
				if (strpos($wh['url'], 'webhook_recive.php?db=' . $db) !== false) {
					$ch = curl_init('https://api.vipps.no/webhooks/v1/webhooks/' . $wh['id']);
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_HTTPHEADER, $mp_headers);
					curl_exec($ch);
					curl_close($ch);
				}
			}
		}

		if (!$correct_webhook_exists) {
			// Register webhook with correct URL
			$ch = curl_init('https://api.vipps.no/webhooks/v1/webhooks');
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $mp_headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
				'url' => $expected_url,
				'events' => ['epayments.payment.authorized.v1', 'user.checked-in.v1', 'epayments.payment.cancelled.v1', 'epayments.payment.aborted.v1', 'epayments.payment.expired.v1', 'epayments.payment.terminated.v1']
			]));
			$reg_resp = json_decode(curl_exec($ch), true);
			curl_close($ch);

			if (!empty($reg_resp['secret'])) {
				db_modify("DELETE FROM settings WHERE var_grp = 'mobilepay' AND var_name = 'webhook_secret'", __FILE__ . " linje " . __LINE__);
				$new_secret = db_escape_string($reg_resp['secret']);
				db_modify("INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ('webhook_secret', 'mobilepay', '$new_secret', 'The secret that is generated for the webhook')", __FILE__ . " linje " . __LINE__);
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
	$qtxt = "select id from tekster where sprog_id = '$stale[1]' and tekst_id = '$stale[0]' and tekst = '" . db_escape_string($stale[2]) . "'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		db_modify("update tekster set tekst = '' where id = '$r[id]'", __FILE__ . " linje " . __LINE__);
	}
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
foreach ($gamle_351 as $gammel) {
	db_modify("delete from tekster where tekst_id = '351' and tekst = '$gammel'", __FILE__ . " linje " . __LINE__);
}

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
foreach ($cvr_gamle_tekster as $cvr_tekst) {
	$cvr_tekst = db_escape_string($cvr_tekst);
	db_modify("delete from tekster where tekst_id between '5040' and '5046' and tekst = '$cvr_tekst'", __FILE__ . " linje " . __LINE__);
}
db_modify("delete from tekster where tekst_id between '5040' and '5046' and tekst like 'Tast CVR-nr. efterfulgt%'", __FILE__ . " linje " . __LINE__);
db_modify("delete from tekster where tekst_id between '5040' and '5046' and tekst like 'Enter the VAT no. followed%'", __FILE__ . " linje " . __LINE__);
db_modify("delete from tekster where tekst_id between '5040' and '5046' and tekst like 'Tast inn org.nr. etterfulgt%'", __FILE__ . " linje " . __LINE__);

?>
