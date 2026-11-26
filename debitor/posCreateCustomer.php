<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/posCreateCustomer.php --- ver 5.1.1 --- 2026.11.26 ---
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
// 20261124 CL/MJ SD-844 K31: the page asks for the till's right (pos.kasse), as pos_ordre.php does, so a cashier with only that right can create.
// 20261126 CL/MJ SD-844 K31: a submitted form that creates nothing (expired, sale settled, Shopify no longer has the customers) tells the cashier, instead of returning to the sale without a word.
// 20261126 CL/MJ SD-844 K31: the alert goes through jsString() (pos_customer_create_alert_script()), so a LATIN9 text with æøå is not blank.

/**
 * Session/account context supplied by includes/online.php.
 * @var int|string $sprog_id
 */
@session_start();
$s_id = session_id();
$modulnr = 5;
$permission_key = 'pos.kasse';
$title = "POS Ordre";
$css = "../css/pos.css";

require __DIR__ . "/../includes/connect.php";
require __DIR__ . "/../includes/online.php";
require_once __DIR__ . "/../includes/std_func.php";
require_once __DIR__ . "/pos_ordre_includes/createCustomer.php";

$id = (int) ($_POST['id'] ?? 0);
$back = "pos_ordre.php" . ($id ? "?id=$id" : "");

/**
 * Back to the sale. A submitted form that creates nothing says so, instead of returning without a word.
 */
function pos_create_customer_back(string $back, string $message = ''): void {
	if ($message !== '') {
		print pos_customer_create_alert_script($message, $back);
	} else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$back\">\n";
	}
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	pos_create_customer_back($back);
}
// a form shown while Shopify had the customers, a sale since settled, or a form this session no longer knows
$notCreated = findtekst('pos.customer.create.not_created|Kunden blev ikke oprettet. Åbn kundeopslaget igen og prøv på ny.', $sprog_id);
if (!pos_customer_create_shopify_mode()) {
	pos_create_customer_back($back, $notCreated);
}
if ($id) {
	$r = db_fetch_array(db_select("select art, status from ordrer where id = '$id'", __FILE__ . " linje " . __LINE__));
	if (!$r || $r['art'] != 'PO' || $r['status'] >= 3) {
		// a settled or unknown sale gets no customer; the till shows the sale as it is
		pos_create_customer_back($back, $notCreated);
	}
}

if (!pos_customer_create_token_issued($_POST['pos_create_token'] ?? null)) {
	// a form from another site, or from a session that has ended: nothing is created
	pos_create_customer_back($back, $notCreated);
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
	pos_create_customer_back($back, $message);
}
$to = "pos_ordre.php?" . ($id ? "id=$id&" : "") . "konto_id=" . (int) $customer['konto_id'];
print "<meta http-equiv=\"refresh\" content=\"0;URL=$to\">\n";
