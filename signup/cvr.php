<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- signup/cvr.php --- ver 5.1.0 --- 2026-11-02 ---
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
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// Server-side CVR lookup for the website's form (plan.md, section 1: the browser no longer calls
// cvrapi.dk itself). Cached and rate limited per client address.
//
//   GET q=<8-digit CVR number or company name>
//   -> {status:"found", vat, name, address, zipcode, city} | {status:"notfound"} | {status:"error"}
//
// 20261014 CL/PS Created.
// 20261102 CL/ASR The rate limit is claimed (recorded, then counted) after the query has been validated.

chdir(__DIR__);
include(__DIR__ . "/../includes/signupIncludes/signupBootstrap.php");

signup_cors();
$q = signup_clean_text(ifset($_GET, 'q', ''), 120);
$digits = preg_replace('/\D/', '', $q);
if (!preg_match('/^\d{8}$/', $digits) && mb_strlen($q) < 3) {
	signup_json(array('status' => 'notfound'));
}
if (!signup_claim_event('cvr', 40, 3600)) {
	signup_json(array('status' => 'error', 'reason' => 'too many requests'), 429);
}
$result = signup_lookup_cvr($q);
header('Cache-Control: private, max-age=600');
signup_json($result);
