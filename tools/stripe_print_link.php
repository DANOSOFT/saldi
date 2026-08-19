<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tools/stripe_print_link.php --- 2026.08.07
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
// 20260807 CL/LH Created: pilot fallback for the mailer hook. Prints the
//                signed subscription URL for one or more orders; the
//                bookkeeper pastes it into the invoice mail manually.
//                Runs the same guard chain as the mailer helper will: any
//                gap -> no URL, with the reason on stderr. CLI only.
//
// Usage: php tools/stripe_print_link.php <ordre-id> [<ordre-id> ...]
// 20260818 CL/LH Anchored branch-added include paths to __DIR__.

if (php_sapi_name() !== 'cli') { header('HTTP/1.1 403 Forbidden'); print 'CLI only'; exit; }
chdir(__DIR__);

// db_query.php's get_relative()/logging read these; unset under CLI (notice-spam).
// Depth-3 URI on purpose: get_relative() counts slashes, and a depth-2 URI
// makes temp/ resolve to tools/temp/ (stray dir) instead of the install's temp/.
if (!isset($_SERVER['REQUEST_URI'])) $_SERVER['REQUEST_URI'] = '/install/tools/stripe_print_link.php';
if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = 'cli';

if ($argc < 2) {
	fwrite(STDERR, "Usage: php tools/stripe_print_link.php <ordre-id> [<ordre-id> ...]\n");
	exit(2);
}

include(__DIR__ . '/../includes/db_query.php');
include(__DIR__ . '/../includes/connect.php');
include(__DIR__ . '/../includes/stripeIncludes/stripeBootstrap.php');
include(__DIR__ . '/../includes/stripeIncludes/stripeLink.php');

if (!$stripe_boot_ok) {
	fwrite(STDERR, "ERROR: tenant unresolved or connection failed (SALDI_STRIPE_DB / .ht_stripe_db)\n");
	exit(2);
}

$exit = 0;
for ($i = 1; $i < $argc; $i++) {
	$order_id = (int)$argv[$i];
	$fail = '';
	if ($order_id < 1) $fail = 'ugyldigt ordre-id';
	if (!$fail && stripe_setting('enabled', 'off') !== 'on')                 $fail = 'integrationen er ikke aktiveret (flueben Aktiv i stripe_valg)';
	if (!$fail && !in_array(stripe_setting('mode'), ['test', 'live'], true)) $fail = 'mode ikke sat (stripe_valg)';
	if (!$fail && !stripe_setting('secret_key'))                             $fail = 'secret_key ikke sat';
	if (!$fail && strlen(stripe_setting('link_secret')) < 64)                $fail = 'link_secret mangler (gem stripe_valg en gang)';
	if (!$fail && !stripe_setting('public_base_url'))                        $fail = 'public_base_url ikke sat';
	if (!$fail) {
		$o = db_fetch_array(db_select("select id, art, firmanavn from ordrer where id = $order_id", __FILE__ . " linje " . __LINE__));
		if (!$o)                    $fail = 'ordren findes ikke';
		elseif ($o['art'] !== 'DO') $fail = "ordren er ikke en debitorordre (art={$o['art']})";
	}
	if (!$fail) {
		$q = db_select("select l.varenr from ordrelinjer l join stripe_catalog c on c.active = true and c.varenr = l.varenr where l.ordre_id = $order_id limit 1", __FILE__ . " linje " . __LINE__);
		if (!db_fetch_array($q)) $fail = 'ingen ordrelinjer med aktiv Stripe-mapping (ikke en abonnementskunde?)';
	}
	if ($fail) {
		fwrite(STDERR, "$order_id: INTET LINK - $fail\n");
		$exit = 1;
		continue;
	}
	$url = stripe_link_url($order_id);
	if ($url === '') { fwrite(STDERR, "$order_id: INTET LINK - konfiguration ufuldstændig\n"); $exit = 1; continue; }
	print $order_id . "\t" . trim((string)$o['firmanavn']) . "\t" . $url . "\n";
}
exit($exit);
