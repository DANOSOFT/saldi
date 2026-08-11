<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- admin/stripe_setup.php --- 2026.08.07
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
// 20260807 CL/LH Created: idempotent Stripe catalog seed/sync. CLI ONLY.
//                Contract: doc/stripe/INTERFACE_CONTRACT.md
//
// Usage (from the install root or admin/):
//   php admin/stripe_setup.php --db=<regnskab-db> [--csv=<file>] [--dry-run]
//
//   --db       Must equal stripeConfigDb()'s answer (belt and braces - the
//              script refuses to run against any other regnskab).
//   --csv      Optional ingest: semicolon-separated lines "varenr;unit_ore[;navn]".
//              Inserts stripe_catalog rows for varenr without an active row.
//   --dry-run  No writes at all - neither DB nor Stripe. Prints the plan.
//
// What it does, idempotently, for every ACTIVE stripe_catalog row:
//   1. Ensures a Stripe Product with the deterministic id "saldi_<varenr>"
//      (resource_already_exists is success - never the eventually-consistent
//      Search API).
//   2. Ensures a monthly DKK Price (tax_behavior=exclusive) matching the row's
//      unit_ore/billing_interval/interval_count; existing prices are matched
//      on those fields, otherwise one is created with a deterministic
//      Idempotency-Key. The row is updated with product/price ids.
//   3. Asserts unit_ore % 4 == 0 (guarantees integer-ore 25% VAT) and that the
//      varenr exists in varer (varenr discipline).
//
// Exit codes: 0 = clean, 1 = completed with warnings, 2 = errors.

if (php_sapi_name() !== 'cli') {
	header('HTTP/1.1 403 Forbidden');
	print "CLI only";
	exit;
}

chdir(__DIR__); // includes below are ../includes/... like every admin/ page

// db_query.php's get_relative()/logging read these; unset under CLI (notice-spam).
if (!isset($_SERVER['REQUEST_URI'])) $_SERVER['REQUEST_URI'] = '/admin/stripe_setup.php';
if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = 'cli';

$options = getopt('', ['db:', 'csv::', 'dry-run', 'help']);
if (isset($options['help']) || !isset($options['db'])) {
	fwrite(STDERR, "Usage: php admin/stripe_setup.php --db=<regnskab-db> [--csv=<file>] [--dry-run]\n");
	exit(isset($options['help']) ? 0 : 2);
}
$argDb  = (string)$options['db'];
$csv    = isset($options['csv']) ? (string)$options['csv'] : '';
$dryRun = isset($options['dry-run']);

$warnings = 0;
$errors   = 0;
function out($msg)  { print $msg . "\n"; }
function warn($msg) { global $warnings; $warnings++; print "WARNING: $msg\n"; }
function err($msg)  { global $errors; $errors++; fwrite(STDERR, "ERROR: $msg\n"); }

include("../includes/db_query.php");
include("../includes/connect.php");
include("../includes/stripeIncludes/stripeSettings.php");
include("../includes/stripeIncludes/stripeHttp.php");

// Tenant: the resolver is the only authority; --db is just a confirmation.
$target = stripeConfigDb();
if (!$target) {
	err("stripeConfigDb() is unresolved - set SALDI_STRIPE_DB or includes/stripeIncludes/.ht_stripe_db");
	exit(2);
}
if ($argDb !== $target) {
	err("--db=" . $argDb . " does not match the configured tenant '" . $target . "' - refusing");
	exit(2);
}
$connection = db_connect($sqhost, $squser, $sqpass, $target);
if (!$connection) {
	err("could not connect to db '" . $target . "'");
	exit(2);
}
$db = $target;

// Settings sanity before any Stripe call.
$mode = stripe_setting('mode');
if ($mode != 'test' && $mode != 'live') {
	err("stripe mode is not configured (systemdata/diverse.php?sektion=stripe_valg)");
	exit(2);
}
out("Tenant: $target  mode: $mode" . ($dryRun ? "  [DRY RUN - no writes]" : ""));

// ---- Phase 1: optional CSV ingest ----------------------------------------
if ($csv !== '') {
	if (!is_readable($csv)) {
		err("csv file not readable: $csv");
		exit(2);
	}
	$lineNo = 0;
	foreach (file($csv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
		$lineNo++;
		$line = trim($line);
		if ($line === '' || $line[0] == '#') continue;
		$parts = array_map('trim', explode(';', $line));
		if (count($parts) < 2 || $parts[1] === '' || !preg_match('/^\d+$/', $parts[1])) {
			err("csv line $lineNo: expected 'varenr;unit_ore[;navn]' - got '$line'");
			continue;
		}
		$varenr  = $parts[0];
		$unitOre = (int)$parts[1];
		$q = db_select("select id from stripe_catalog where active = true and varenr = '" . db_escape_string($varenr) . "'", __FILE__ . " linje " . __LINE__);
		if (db_fetch_array($q)) {
			out("csv: '$varenr' already has an active row - skipped");
			continue;
		}
		if ($dryRun) {
			out("csv: would insert '$varenr' at $unitOre øre/md");
		} else {
			db_modify("insert into stripe_catalog (varenr, unit_ore, billing_interval, interval_count, currency, active) values ('"
				. db_escape_string($varenr) . "', " . $unitOre . ", 'month', 1, 'DKK', true)", __FILE__ . " linje " . __LINE__);
			out("csv: inserted '$varenr' at $unitOre øre/md");
		}
	}
}

// ---- Phase 2: sync every active row to Stripe -----------------------------
$rows = [];
$q = db_select("select id, varenr, stripe_price_id, stripe_product_id, unit_ore, billing_interval, interval_count, currency from stripe_catalog where active = true order by varenr", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) $rows[] = $r;
if (!$rows) {
	warn("stripe_catalog has no active rows - nothing to sync (use --csv to ingest the Step 0 inventory)");
}

foreach ($rows as $r) {
	$varenr   = $r['varenr'];
	$unitOre  = (int)$r['unit_ore'];
	$interval = $r['billing_interval'] ? $r['billing_interval'] : 'month';
	$intCount = (int)$r['interval_count'] > 0 ? (int)$r['interval_count'] : 1;
	$currency = strtolower($r['currency'] ? $r['currency'] : 'DKK');
	$prefix   = "[$varenr]";

	// Guard 1: integral 25% VAT in ore.
	if ($unitOre <= 0 || $unitOre % 4 !== 0) {
		err("$prefix unit_ore=$unitOre fails the integral-25%-VAT check (must be > 0 and divisible by 4) - fix the price or deactivate the row");
		continue;
	}
	// Guard 2: varenr discipline - the item must exist in varer.
	$vq = db_fetch_array(db_select("select id, beskrivelse from varer where varenr = '" . db_escape_string($varenr) . "'", __FILE__ . " linje " . __LINE__));
	if (!$vq) {
		err("$prefix varenr not found in varer - the bookkeeper's invoices cannot carry it; fix varer first");
		continue;
	}
	$productName = trim($vq['beskrivelse']) !== '' ? trim($vq['beskrivelse']) : $varenr;
	$productId   = 'saldi_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $varenr);

	// Product: deterministic id; already-exists is success.
	if ($dryRun) {
		out("$prefix would ensure product '$productId' ('$productName')");
	} else {
		$resp = stripeHttpRequest('POST', '/v1/products', [
			'id' => $productId,
			'name' => $productName,
			'metadata' => ['saldi_sku' => $varenr],
		]);
		if (!$resp['ok'] && $resp['error_code'] === 'resource_already_exists') {
			// fine - idempotent
		} elseif (!$resp['ok']) {
			err("$prefix product create failed: " . $resp['error'] . " (" . $resp['error_code'] . ")");
			continue;
		} else {
			out("$prefix product '$productId' created");
		}
	}

	// Price: verify a stored id, else match an existing, else create.
	$priceId = trim((string)$r['stripe_price_id']);
	if ($priceId !== '' && !$dryRun) {
		$resp = stripeHttpRequest('GET', '/v1/prices/' . rawurlencode($priceId));
		$p = $resp['ok'] && is_array($resp['body']) ? $resp['body'] : null;
		if ($p && ($p['currency'] !== $currency || (int)$p['unit_amount'] !== $unitOre
			|| !isset($p['recurring']['interval']) || $p['recurring']['interval'] !== $interval
			|| (int)$p['recurring']['interval_count'] !== $intCount || $p['tax_behavior'] !== 'exclusive')) {
			warn("$prefix stored price $priceId no longer matches the row (Stripe: " . $p['unit_amount'] . " " . $p['currency'] . "/" . $p['recurring']['interval'] . ") - clearing and re-matching");
			$priceId = '';
		} elseif (!$p) {
			warn("$prefix stored price $priceId could not be fetched (" . $resp['error'] . ") - clearing and re-matching");
			$priceId = '';
		}
	}
	if ($priceId === '') {
		if ($dryRun) {
			out("$prefix would match-or-create price " . $unitOre . " øre $currency / $intCount x $interval (tax_behavior=exclusive)");
			continue;
		}
		$resp = stripeHttpRequest('GET', '/v1/prices', ['product' => $productId, 'active' => 'true', 'limit' => 100]);
		if ($resp['ok'] && is_array($resp['body']) && isset($resp['body']['data'])) {
			foreach ($resp['body']['data'] as $p) {
				if ($p['currency'] === $currency && (int)$p['unit_amount'] === $unitOre
					&& isset($p['recurring']['interval']) && $p['recurring']['interval'] === $interval
					&& (int)$p['recurring']['interval_count'] === $intCount && $p['tax_behavior'] === 'exclusive') {
					$priceId = $p['id'];
					out("$prefix matched existing price $priceId");
					break;
				}
			}
		}
		if ($priceId === '') {
			$resp = stripeHttpRequest('POST', '/v1/prices', [
				'product' => $productId,
				'currency' => $currency,
				'unit_amount' => $unitOre,
				'recurring' => ['interval' => $interval, 'interval_count' => $intCount],
				'tax_behavior' => 'exclusive',
				'metadata' => ['saldi_sku' => $varenr],
			], ['idempotency_key' => 'saldi_price_' . $productId . '_' . $unitOre . '_' . $intCount . $interval]);
			if (!$resp['ok']) {
				err("$prefix price create failed: " . $resp['error'] . " (" . $resp['error_code'] . ")");
				continue;
			}
			$priceId = $resp['body']['id'];
			out("$prefix price $priceId created (" . $unitOre . " øre / $intCount x $interval)");
		}
		db_modify("update stripe_catalog set stripe_price_id = '" . db_escape_string($priceId) . "', stripe_product_id = '"
			. db_escape_string($productId) . "' where id = " . (int)$r['id'], __FILE__ . " linje " . __LINE__);
	} else {
		out("$prefix ok (price $priceId verified)");
	}
}

out("Done. warnings=$warnings errors=$errors");
exit($errors ? 2 : ($warnings ? 1 : 0));
