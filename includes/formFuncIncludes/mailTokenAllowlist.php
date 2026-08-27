<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/formFuncIncludes/mailTokenAllowlist.php --- 2026.08.17
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
// 20260817 CL/LH Created: tier-1 gate for the mail template $-token resolvers
//                in sendMail.php/oldSendMail.php. The resolvers interpolate the
//                token straight into SQL ("select <token> from ordrer") - this
//                closes that injection surface: a token must be a syntactically
//                clean identifier AND an actual ordrer column, otherwise it
//                renders as '' with one log line, never an SQL error and never
//                an aborted send. Existing templates with real column tokens
//                render byte-identically. (Tier 2 - an explicit list of the
//                tokens templates actually use - is a follow-up ticket.)
// 20260818 CL/LH Scoped mail token column cache to the tenant database.

if (!function_exists('ordrer_mail_token_allowed')) {
	function ordrer_mail_token_allowed($var) {
		global $db;
		static $cols = [];
		if (!is_string($var) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $var)) return false;
		if (!isset($cols[$db])) {
			$cols[$db] = [];
			$q = db_select("SELECT column_name FROM information_schema.columns WHERE table_name = 'ordrer'", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) $cols[$db][strtolower($r['column_name'])] = true;
		}
		if (!isset($cols[$db][strtolower($var)])) {
			error_log("sendMail: unknown mail template token '" . preg_replace('/[^A-Za-z0-9_]/', '', $var) . "' rendered as empty");
			return false;
		}
		return true;
	}
}
