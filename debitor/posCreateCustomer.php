<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/posCreateCustomer.php --- ver 5.1.0 --- 2026.11.23 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261120 CL/MJ SD-844 K31: the till's "Opret ny kunde" form posts here when customers belong to Shopify.
//                The customer is created, and the seller is sent back to the same sale with the customer attached, as when picking one from the list.
// 20261123 CL/MJ SD-844 K31: a form whose token this session was not given (another site's) creates nothing.

/**
 * Session/account context supplied by includes/online.php.
 * @var int|string $sprog_id
 */
@session_start();
$s_id = session_id();
$modulnr = 5;
$title = "POS Ordre";
$css = "../css/pos.css";

require __DIR__ . "/../includes/connect.php";
require __DIR__ . "/../includes/online.php";
require_once __DIR__ . "/../includes/std_func.php";
require_once __DIR__ . "/pos_ordre_includes/createCustomer.php";

$id = (int) ($_POST['id'] ?? 0);
$back = "pos_ordre.php" . ($id ? "?id=$id" : "");

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !pos_customer_create_shopify_mode()) {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$back\">\n";
	exit;
}
if ($id) {
	$r = db_fetch_array(db_select("select art, status from ordrer where id = '$id'", __FILE__ . " linje " . __LINE__));
	if (!$r || $r['art'] != 'PO' || $r['status'] >= 3) {
		// a settled or unknown sale gets no customer; the till shows the sale as it is
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$back\">\n";
		exit;
	}
}

if (!pos_customer_create_token_issued($_POST['pos_create_token'] ?? null)) {
	// a form from another site, or from a session that has ended: nothing is created
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$back\">\n";
	exit;
}
$message = '';
try {
	$customer = pos_create_customer($_POST, $_POST['pos_create_token'] ?? '');
} catch (InvalidArgumentException $e) {
	$message = findtekst('pos.customer.create.incomplete|Kunden blev ikke oprettet: navn og gruppe skal udfyldes.', $sprog_id);
} catch (Throwable $e) {
	error_log("posCreateCustomer: " . $e->getMessage());
	$message = findtekst('pos.customer.create.failed|Kunden kunne ikke oprettes. Prøv igen.', $sprog_id);
}
if ($message !== '') {
	print "<script>alert(" . json_encode($message, JSON_HEX_TAG) . "); window.location.href = " . json_encode($back, JSON_HEX_TAG) . ";</script>\n";
	exit;
}
$to = "pos_ordre.php?" . ($id ? "id=$id&" : "") . "konto_id=" . (int) $customer['konto_id'];
print "<meta http-equiv=\"refresh\" content=\"0;URL=$to\">\n";
