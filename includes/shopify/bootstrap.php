<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/bootstrap.php --- ver 5.0.0 --- 2026.10.28
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
// Session-less tenant bootstrap for the Shopify entry points (WP02): the worker CLI, the
// webhook endpoint and the document endpoint. Loads one tenant and the legacy globals the
// order, delivery and posting functions expect, without a browser session. Include after
// includes/db_query.php and includes/connect.php, like includes/stripeIncludes/stripeBootstrap.php.
//
// 20261022 Sawaneh SD-734: created (WP02-1).
// 20261022 Sawaneh SD-734 review: cli shims follow the SAPI, closure date lukkes honoured, deleted RA rows
//                 ignored, newest open year chosen chronologically; kodenr as the last sort key (review).
// 20261023 CDX/LH Initialize base currency and paired business time zones; reject timestamp-deleted fiscal years.
// 20261028 CL/SZ SD-748: $fiscalYear false skips the fiscal-year lookup, for the webhook receiver.

/**
 * Injected by includes/connect.php (master connection and credentials):
 * @var string $db_type
 * @var string $sqhost
 * @var string $squser
 * @var string $sqpass
 * @var string $sqdb
 * @var string $db_encode
 * @var resource|\PgSql\Connection $connection
 */

final class ShopifyBootstrapError extends RuntimeException
{
}

/**
 * Loads one tenant without a session and sets the legacy globals. Under the cli SAPI the
 * working directory becomes tools/ and the synthetic depth-3 REQUEST_URI and REMOTE_ADDR that
 * db_query.php's logging needs are set. $cliDb names the tenant on the command line and is
 * accepted under cli only; without it the tenant comes from SALDI_SHOPIFY_DB or
 * includes/shopify/.ht_shopify_db (webhook and document endpoints, cron without an argument).
 * The tenant must exist in master regnskab and be open, by the same rule as includes/online.php.
 * With $fiscalYear false the fiscal year is not looked up and $regnaar is null: the webhook
 * receiver never uses it, and a ledger mistake must not turn its deliveries into 503s.
 *
 * Globals set: $db, $db_id, $db_skriv_id, $regnskab, $regnaar, $brugernavn ('shopify'),
 * $bruger_id (0), $sprog_id (1), $rettigheder (''), $s_id (''), $webservice (true) and the
 * tenant $connection, $baseCurrency (settings, default 'DKK') and $timezone (master settings,
 * default 'Europe/Copenhagen'). PHP and the tenant PostgreSQL session use that same zone.
 * Entry scripts still supply $version via connect.php/version.php. Per-document posting and
 * delivery state ($fakturadate, $mail_fakt, $momssats, $pbs, $retur, $valutakurs, $afd_lager,
 * $levdate, $lev_nr, $ref, $art, $fp, $sn_id and $db_modify_fejl) belongs to the caller or the
 * legacy functions; this bootstrap does not initialize or reset it.
 *
 * @param string|null $cliDb tenant database name given on the command line
 * @param bool $fiscalYear whether to look up the fiscal year
 * @return array{db: string, regnskab_id: int, regnskab: string, regnaar: int|null, brugernavn: string, sapi: string}
 * @throws ShopifyBootstrapError when the tenant cannot be resolved, is closed, cannot be connected or has no single fiscal year
 */
function shopify_bootstrap(?string $cliDb = null, bool $fiscalYear = true): array {
	global $db_type, $sqhost, $squser, $sqpass, $sqdb, $connection;
	global $db, $db_id, $db_skriv_id, $regnskab, $regnaar, $brugernavn, $bruger_id, $sprog_id, $rettigheder, $s_id, $webservice;
	global $baseCurrency, $timezone;

	if (!function_exists('db_connect') || !isset($sqhost, $sqdb)) {
		throw new ShopifyBootstrapError('include includes/db_query.php and includes/connect.php before shopify_bootstrap()');
	}
	if (in_array(strtolower((string)$db_type), array('mysql', 'mysqli'), true)) {
		throw new ShopifyBootstrapError('the Shopify module runs on PostgreSQL tenants only');
	}
	$sapi = php_sapi_name();
	if ($cliDb !== null && $sapi !== 'cli') {
		throw new ShopifyBootstrapError('a tenant on the command line is only accepted under the cli SAPI');
	}
	if ($sapi === 'cli') {
		chdir(__DIR__ . '/../../tools');
		if (!isset($_SERVER['REQUEST_URI'])) {
			$_SERVER['REQUEST_URI'] = '/install/tools/shopify_worker.php';
		}
		if (!isset($_SERVER['REMOTE_ADDR'])) {
			$_SERVER['REMOTE_ADDR'] = 'cli';
		}
	}
	$r = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name = 'timezone'", __FILE__ . " linje " . __LINE__, true));
	$timezone = $r && $r['var_value'] ? $r['var_value'] : 'Europe/Copenhagen';
	if (!date_default_timezone_set($timezone)) {
		throw new ShopifyBootstrapError('invalid business time zone in settings');
	}
	$candidate = $cliDb ?? shopify_bootstrap_configured_db();
	$tenant = shopify_bootstrap_tenant($candidate);

	$tenantConnection = db_connect($sqhost, $squser, $sqpass, $tenant['db']);
	if (!$tenantConnection) {
		throw new ShopifyBootstrapError("could not connect to tenant {$tenant['db']}");
	}
	$connection = $tenantConnection;
	$db = $tenant['db'];
	$db_id = $tenant['id'];
	$db_skriv_id = $tenant['id'];
	$regnskab = $tenant['regnskab'];
	$brugernavn = 'shopify';
	$bruger_id = 0;
	$sprog_id = 1;
	$rettigheder = '';
	$s_id = '';
	$webservice = true;
	db_select("SELECT set_config('TimeZone', '" . db_escape_string($timezone) . "', false)", __FILE__ . " linje " . __LINE__);
	$r = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name = 'baseCurrency'", __FILE__ . " linje " . __LINE__));
	$baseCurrency = $r && $r['var_value'] ? $r['var_value'] : 'DKK';
	$regnaar = $fiscalYear ? shopify_bootstrap_fiscal_year() : null;

	return array(
		'db' => $db,
		'regnskab_id' => $db_id,
		'regnskab' => $regnskab,
		'regnaar' => $regnaar,
		'brugernavn' => $brugernavn,
		'sapi' => $sapi,
	);
}

/**
 * Tenant name for the web entry points: SALDI_SHOPIFY_DB, else includes/shopify/.ht_shopify_db.
 */
function shopify_bootstrap_configured_db(): string {
	$candidate = (string)getenv('SALDI_SHOPIFY_DB');
	if ($candidate === '') {
		$file = __DIR__ . '/.ht_shopify_db';
		if (is_readable($file)) {
			$candidate = trim((string)file_get_contents($file));
		}
	}
	if ($candidate === '') {
		throw new ShopifyBootstrapError('no tenant configured: set SALDI_SHOPIFY_DB or includes/shopify/.ht_shopify_db');
	}
	return $candidate;
}

/**
 * The master regnskab row of a tenant that exists and is open.
 *
 * @return array{id: int, regnskab: string, db: string}
 */
function shopify_bootstrap_tenant(string $candidate): array {
	if (!preg_match('/^[a-z0-9_]{1,40}$/', $candidate)) {
		throw new ShopifyBootstrapError('invalid tenant name');
	}
	$qtxt = "SELECT id, regnskab, db, lukket, lukkes FROM regnskab WHERE db = '" . db_escape_string($candidate) . "'";
	$row = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, true));
	if (!$row) {
		throw new ShopifyBootstrapError("tenant $candidate is not registered in master regnskab");
	}
	$closes = trim((string)$row['lukkes']);
	$closedByDate = $closes !== '' && $closes !== '2099-12-31' && strtotime($closes) <= strtotime(date('Y-m-d'));
	if (trim((string)$row['lukket']) === 'on' || $closedByDate) {
		throw new ShopifyBootstrapError("tenant $candidate is closed");
	}
	return array('id' => (int)$row['id'], 'regnskab' => (string)$row['regnskab'], 'db' => (string)$row['db']);
}

/**
 * The fiscal year (grupper RA kodenr) that contains today, by the rule api/rest_api.php uses,
 * ignoring years deleted in Saldi (box10); the newest open year, as active_fiscal_years() orders
 * them, when none does. More than one matching year is an error, never a guess.
 */
function shopify_bootstrap_fiscal_year(): int {
	$year = date('Y');
	$month = date('m');
	$del1 = "(box1 <= '$month' AND box2 <= '$year' AND box3 >= '$month' AND box4 >= '$year')";
	$del2 = "(box1 <= '$month' AND box2 <= '$year' AND box3 < '$month' AND box4 > '$year')";
	$del3 = "(box1 > '$month' AND box2 < '$year' AND box3 >= '$month' AND box4 >= '$year')";
	$notDeleted = "(box10 IS NULL OR box10 = '')";
	$qtxt = "SELECT kodenr FROM grupper WHERE art = 'RA' AND $notDeleted AND ($del1 OR $del2 OR $del3) ORDER BY box2, box1, kodenr";
	$query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	$years = array();
	while ($r = db_fetch_array($query)) {
		$years[] = (int)$r['kodenr'];
	}
	if (count($years) > 1) {
		throw new ShopifyBootstrapError('several fiscal years contain today (RA ' . implode(', ', $years) . '); fix the ledger before running the Shopify integration');
	}
	if ($years) {
		return $years[0];
	}
	$qtxt = "SELECT kodenr FROM grupper WHERE art = 'RA' AND $notDeleted AND box5 = 'on' ORDER BY box2 DESC, box1 DESC, kodenr DESC LIMIT 1";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$r) {
		throw new ShopifyBootstrapError('no open fiscal year in the ledger');
	}
	return (int)$r['kodenr'];
}
