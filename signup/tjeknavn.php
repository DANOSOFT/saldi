<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- signup/tjeknavn.php --- ver 5.1.0 --- 2026-11-02 ---
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
// Live availability check for the account name while the customer types on the activation page
// (plan.md, section 3: advisory only - the binding check happens inside signup_activate()).
//
//   GET t=<valid activation token>&navn=<name>
//   -> {ok:true, name:"<normalised>", available:true|false, suggestion:"<free variant>"}
//
// A valid token is required so the endpoint cannot be used to enumerate account names.
//
// 20261014 CL/PS Created.
// 20261102 CL/ASR The rate limit is claimed (recorded, then counted).

chdir(__DIR__);
include(__DIR__ . "/../includes/signupIncludes/signupBootstrap.php");

$row = signup_find_pending_by_token((string)ifset($_GET, 't', ''));
if (!$row || $row['state'] !== 'valid') {
	signup_json(array('ok' => false, 'error' => 'invalid token'), 403);
}
if (!signup_claim_event('navn', 60, 600)) {
	signup_json(array('ok' => false, 'error' => 'too many requests'), 429);
}
$a = signup_name_available((string)ifset($_GET, 'navn', ''));
signup_json(array('ok' => true, 'name' => $a['name'], 'available' => $a['available'], 'suggestion' => $a['suggestion']));
