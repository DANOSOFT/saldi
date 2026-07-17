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

?>
