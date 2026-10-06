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
//             (#538).
// 20260925 CL/NTR (CodeRabbit): a pure content fingerprint made two genuinely separate submissions
//             with identical items/quantities collide - the second was wrongly treated as a replay
//             of the first and silently dropped. Added a per-render form token (embedded as a hidden
//             field, so a real replay resubmits the same token while a fresh page load never does)
//             into the fingerprint, and switched from remembering only the single most recent
//             submission to a bounded set of recent ones (matching saveReplay.php's
//             kk_completed_saves), so an A -> B -> A resend is still recognised as a replay of A.

/**
 * @return string A fresh, unpredictable token for one rendered genbestil form - embed it as a
 *   hidden field so a real resubmission (double-click, Back + resend) carries the same token,
 *   while a later page load never coincidentally collides with an earlier, content-identical one.
 */
function genbestilFormToken(): string {
	return bin2hex(random_bytes(16));
}

/**
 * @param array<string, mixed> $post
 * @return string Fingerprint identifying this exact genbestil submission, scoped to the
 *   authenticated tenant/user and the rendered form's own token, so one tenant's session can never
 *   match another's replay fingerprint and two distinct form loads never collide even with
 *   identical items/quantities.
 */
function genbestilReplayKey(array $post, string $formToken, string $database, string $user): string {
	return hash('sha256', serialize([$database, $user, $formToken, $post]));
}

/**
 * @param array<string, mixed> $session
 * @return bool Whether this exact payload was already processed successfully by this session.
 */
function genbestilIsReplay(array $session, string $key): bool {
	return isset($session['gb_completed_submits'][$key]);
}

/**
 * Remember a successfully processed submission, bounded to the most recent 128 - same retention
 * approach as saveReplay.php's kk_completed_saves, so an earlier submission (A) is still
 * recognised as a replay after a later, different one (B) has also gone through.
 *
 * @param array<string, mixed> $session
 */
function genbestilRememberSubmit(array &$session, string $key): void {
	$submits = $session['gb_completed_submits'] ?? [];
	$submits[$key] = true;
	$session['gb_completed_submits'] = array_slice($submits, -128, null, true);
}
