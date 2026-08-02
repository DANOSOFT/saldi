<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/betweenUpdates.php --- patch 5.0.0--- 2026.08.02
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
// 20260801 MJ SD-532 manglede omdoebning af ansatte.fax til ansatte.mobile (kun adresser blev rettet)
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

// 20260801 MJ SD-532 omdoebte ansatte_save.php til at bruge kolonnen 'mobile', men DB-omdoebningen
// daekkede kun adresser-tabellen. Paa aeldre databaser hedder kolonnen stadig 'fax', og INSERT fejler.
// 20260802 MJ SD-532 tilfoejede tjek for at 'mobile' ikke allerede eksisterer (undgaar fejl hvis begge kolonner findes)
$_has_fax    = db_fetch_array(db_select("SELECT column_name FROM information_schema.columns WHERE table_name = 'ansatte' AND column_name = 'fax'", __FILE__ . " linje " . __LINE__));
$_has_mobile = db_fetch_array(db_select("SELECT column_name FROM information_schema.columns WHERE table_name = 'ansatte' AND column_name = 'mobile'", __FILE__ . " linje " . __LINE__));
if ($_has_fax && !$_has_mobile) {
    db_modify("ALTER TABLE ansatte RENAME COLUMN fax TO mobile", __FILE__ . " linje " . __LINE__);
}

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

// R5 moms periodelaasning — opret tabel, funktion og trigger een gang ved login.
// Kontrollen sker paa trigger-eksistens; er triggeren der, springes hele blokken over.
$qtxt = "SELECT 1 FROM pg_trigger WHERE tgname='tr_check_moms_periode_luk' LIMIT 1";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    db_modify("CREATE TABLE IF NOT EXISTS moms_periode_luk (
        id               SERIAL PRIMARY KEY,
        kalender_aar     INTEGER NOT NULL,
        kalender_maaned  INTEGER NOT NULL CHECK (kalender_maaned BETWEEN 1 AND 12),
        status           VARCHAR(6) NOT NULL DEFAULT 'open' CHECK (status IN ('open','closed')),
        lukket_af        VARCHAR(100),
        lukket_dato      TIMESTAMP,
        aabnet_af        VARCHAR(100),
        aabnet_dato      TIMESTAMP,
        UNIQUE (kalender_aar, kalender_maaned)
    )", __FILE__ . " linje " . __LINE__);
    // pg_query() bruges her for at omgaa injecttjek(): PL/pgSQL-kroppen indeholder
    // semikolon uden for enkeltcitater, som injecttjek() ville fejlfortolke som injection.
    $fn  = "CREATE OR REPLACE FUNCTION check_moms_periode_luk() ";
    $fn .= "RETURNS TRIGGER AS \$\$ ";
    $fn .= "BEGIN ";
    $fn .= "    IF EXISTS ( ";
    $fn .= "        SELECT 1 FROM moms_periode_luk ";
    $fn .= "        WHERE kalender_aar    = EXTRACT(YEAR  FROM NEW.transdate) ";
    $fn .= "          AND kalender_maaned = EXTRACT(MONTH FROM NEW.transdate) ";
    $fn .= "          AND status = 'closed' ";
    $fn .= "    ) THEN ";
    $fn .= "        RAISE EXCEPTION 'Perioden % er lukket for bogfoering - kontakt bogholder for at genaabne.', ";
    $fn .= "            TO_CHAR(NEW.transdate, 'MM-YYYY'); ";
    $fn .= "    END IF; ";
    $fn .= "    RETURN NEW; ";
    $fn .= "END; ";
    $fn .= "\$\$ LANGUAGE plpgsql";
    pg_query($connection, $fn);
    pg_query($connection,
        "CREATE TRIGGER tr_check_moms_periode_luk "
        . "BEFORE INSERT OR UPDATE ON transaktioner "
        . "FOR EACH ROW EXECUTE FUNCTION check_moms_periode_luk()");
}

// Add note column to moms_periode_luk if not present (idempotent guard).
$qtxt = "SELECT 1 FROM information_schema.columns WHERE table_name='moms_periode_luk' AND column_name='note' LIMIT 1";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    db_modify("ALTER TABLE moms_periode_luk ADD COLUMN note TEXT", __FILE__ . " linje " . __LINE__);
}

// 20260802 MJ SD-533 delivery_addresses tabel mangler paa aeldre databaser hvis opdat_4.3.php-gaten
// allerede var passeret foer tabellen blev tilfojet. debitor/ordre.php linje 2452 koerer en UPDATE mod
// delivery_addresses for ALLE knaptryk (ogsaa Udskriv), saa en manglende tabel giver "Uforudset haendelse"
// baaede ved tilfoejelse af lagervarer og ved udskrift af eksisterende ordre.
$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'delivery_addresses'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "CREATE TABLE delivery_addresses (id SERIAL NOT NULL, account_id integer NOT NULL,";
	$qtxt.= " is_primary boolean NOT NULL DEFAULT false, sort_order smallint NOT NULL DEFAULT 0,";
	$qtxt.= " description varchar(100), company_name varchar(255), first_name varchar(100),";
	$qtxt.= " last_name varchar(100), address_line1 varchar(255), address_line2 varchar(255),";
	$qtxt.= " postal_code varchar(20), city varchar(100), country varchar(100),";
	$qtxt.= " contact_name varchar(100), phone varchar(50), email varchar(255),";
	$qtxt.= " created_at timestamp DEFAULT CURRENT_TIMESTAMP,";
	$qtxt.= " PRIMARY KEY (id), FOREIGN KEY (account_id) REFERENCES adresser(id) ON DELETE CASCADE)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
$qtxt = "SELECT indexname FROM pg_indexes WHERE tablename = 'delivery_addresses' AND indexname = 'idx_delivery_addresses_account_id'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	db_modify("CREATE INDEX idx_delivery_addresses_account_id ON delivery_addresses(account_id)", __FILE__ . " linje " . __LINE__);
}

?>
