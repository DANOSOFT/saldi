<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/varerIncludes/genbestilReplay.php --- lap 5.0.0 --- 2026-09-25 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260925 CL/NTR MB-35 follow-up: a double-click on the Indkøbsforslag "Opret" button, or browser
//             Back + resend, posted the identical genbestil_ant/gb_id_*/gb_antal_* form twice.
//             genbestil() has no idempotency of its own - it always inserts a fresh ordrelinjer row
//             (hardcoded posnr, no check for an existing line for the item on that day's order), so
//             the second POST silently created a duplicate purchase-order line and summed quantities
//             for every item in the submission (e.g. a suggested 9 could become 9+9=18, or two
//             different edited values could sum to something like the reported "17"). Same replay
//             class and same fingerprint approach as finans/kassekladde_includes/saveReplay.php
//             (#538), simplified: genbestil's target (the item) always already exists, so there is no
//             "first save creates a new entity" case to key a form token by - the whole POST payload
//             is fingerprinted directly.

/**
 * @param array<string, mixed> $post
 * @return string Fingerprint identifying this exact genbestil submission's payload, scoped to the
 *   authenticated tenant/user so one tenant's session can never match another's replay fingerprint.
 */
function genbestilReplayKey(array $post, string $database, string $user): string {
	return hash('sha256', serialize([$database, $user, $post]));
}

/**
 * @param array<string, mixed> $session
 * @return bool Whether this exact payload was already processed successfully by this session.
 */
function genbestilIsReplay(array $session, string $key): bool {
	return ($session['gb_last_submit'] ?? null) === $key;
}

/**
 * Remember a successfully processed submission, bounded to the single most recent one - unlike
 * kassekladde (many journals open at once), only one Indkøbsforslag submission is ever in flight
 * per session, so there is nothing else to key it by.
 *
 * @param array<string, mixed> $session
 */
function genbestilRememberSubmit(array &$session, string $key): void {
	$session['gb_last_submit'] = $key;
}
