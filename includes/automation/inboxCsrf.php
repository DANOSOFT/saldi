<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/inboxCsrf.php --- ai/mcp-assistant --- 2026.10.01
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// Shared CSRF helpers for human decision endpoints.
//
// 20261001 CDX/LH Created.

declare(strict_types=1);

/** Stable per-session token shared by every Saldi approval surface. */
function saldi_inbox_csrf_token(): string
{
	if (empty($_SESSION['inbox_csrf'])) {
		$_SESSION['inbox_csrf'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['inbox_csrf'];
}
/** Missing or empty values fail before constant-time comparison. */
function saldi_inbox_csrf_check(?string $token): bool
{
	return is_string($token) && $token !== '' && !empty($_SESSION['inbox_csrf']) && hash_equals($_SESSION['inbox_csrf'], $token);
}
