<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/sys_div_func_includes/regenerate_mobilepay_qr.php --- ver 5.0.0 --- 2026-10-27 ---
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
// Copyright (c) 2024-2026 Danosoft ApS
// ----------------------------------------------------------------------
// Forgets the stored MobilePay QR code uri of one till, so Diverse valg fetches it from Vipps again
// the next time the page is shown ("Regenerér QR-kode" in sys_div_func.php, div_valg()).
//
// 20261027 CL/LH Created (PR #502). Needs the Indstillinger right like Diverse valg itself and the form
//                token from that page, so a link from another site cannot delete the code.

# Indstillinger is module 1; online.php refuses the request when the user lacks that right.
$modulnr = 1;
$title   = "MobilePay QR-kode";
$css     = "../../css/standard.css";

@session_start();
$s_id = session_id();
# online.php prints the page head; it is buffered and dropped again before the redirect below
ob_start();

include(__DIR__ . "/../../includes/connect.php");
include(__DIR__ . "/../../includes/online.php");
include(__DIR__ . "/../../includes/std_func.php");

# Session-bound token printed into the button on diverse.php
$csrf_token = ifset($_SESSION, 'csrf_token', '');
if ($csrf_token === '' || !hash_equals($csrf_token, (string) ifset($_GET, 'csrf', ''))) {
	http_response_code(403);
	print tekstboks(findtekst('3405|Ugyldig eller udløbet formular - genindlæs siden og prøv igen', $sprog_id));
	exit;
}

$pos_id = (int) ifset($_GET, 'pos_id', 0);

if ($pos_id > 0) {
	$qtxt = "delete from settings where var_name = 'qrkodeuri' AND var_grp = 'mobilepay' AND pos_id = $pos_id";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

ob_end_clean();
header("Location: ../diverse.php?sektion=div_valg");
exit;
