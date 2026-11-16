<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/publicTenantDb.php --- ver 5.1.0 --- 2026-10-29 ---
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
// Copyright (c) 2026-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261029 CL/MMR SST-840: New. Validates the account database name that the login-free booking endpoints get from the request.
//

if (!function_exists('public_tenant_db')) {
/**
 * Validates the account database name that a login-free endpoint (remoteBooking/, mysale/) received
 * in the request. Account databases are named "<master database>_<id>" (includes/opretfunc.php), so
 * anything else, the master database included, is refused with HTTP 400 before a connection is made.
 *
 * @param mixed $value  The raw request value (GET parameter or JSON field).
 * @return string       The validated name: safe for db_connect() and inside single quotes in SQL.
 */
function public_tenant_db($value) {
	$db = is_string($value) ? trim($value) : '';
	if (strlen($db) > 63 || !preg_match('/^[a-z][a-z0-9_]*_[0-9]+$/i', $db)) {
		http_response_code(400);
		header('Content-Type: application/json');
		echo json_encode(array('error' => 'Invalid account'));
		exit;
	}
	return $db;
}
}
