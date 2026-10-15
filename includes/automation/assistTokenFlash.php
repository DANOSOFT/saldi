<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/assistTokenFlash.php --- ai/mcp-assistant --- 2026.10.02
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
// One-time flash for a newly issued personal access key (systemdata/assistent.php), bound to the
// company and user that issued it, so a shared session never shows the key in another company.
//
// 20261002 CL/LH Created.

declare(strict_types=1);

/** Keep a new key for the very next page view of the issuing company and user. */
function saldi_assist_token_flash_put(array &$session, array $token, string $db, int $userId, string $company): void
{
	$session['assistent_new_token'] = ['token' => (string)$token['token'], 'grant_id' => (int)$token['grant_id'], 'db' => $db, 'user_id' => $userId, 'company' => $company];
}

/**
 * Take the flash (it is always removed). The key is returned only to the issuing company and user;
 * anywhere else only the grant id and issuing company come back, so the page can ask for a revoke.
 *
 * @return array{token: ?array{token:string, grant_id:int, company:string}, foreign: ?array{grant_id:int, company:string}}
 */
function saldi_assist_token_flash_take(array &$session, string $db, int $userId): array
{
	$flash = $session['assistent_new_token'] ?? null;
	unset($session['assistent_new_token']);
	if (!is_array($flash) || !is_string($flash['token'] ?? null)) {
		return ['token' => null, 'foreign' => null];
	}
	$company = (string)($flash['company'] ?? '');
	if (($flash['db'] ?? null) === $db && (int)($flash['user_id'] ?? 0) === $userId && $userId > 0) {
		return ['token' => ['token' => $flash['token'], 'grant_id' => (int)$flash['grant_id'], 'company' => $company], 'foreign' => null];
	}
	return ['token' => null, 'foreign' => ['grant_id' => (int)($flash['grant_id'] ?? 0), 'company' => $company !== '' ? $company : (string)($flash['db'] ?? '')]];
}
