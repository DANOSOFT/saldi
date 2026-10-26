<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/orderIncludes/orderLineReplay.php --- ver 5.0.0 --- 2026-10-19 ---
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
// 20261019 CL/LH MB-74: Recognize a replayed order POST (reload, resend, double-click) that already added its new line.

/**
 * Fingerprint of an order form POST that carries a new order line (vare0/beskrivelse0).
 *
 * The order form carries a token that is new on every render (orderFormToken()), so only
 * a reload/resend of the same response, or a second click on the same rendered form,
 * posts exactly the same data again. A deliberate second entry of the same item, even
 * after the first line was deleted again, comes from a new render and does not match.
 *
 * Modelled on journalSaveRequestKey() in finans/kassekladde_includes/saveReplay.php.
 *
 * @param array  $post     The request's $_POST.
 * @param string $database Tenant database, so a session that switches accounts never matches.
 * @param string $user     Logged-in user name.
 * @return string|null Key, or null when the POST has no order id, no form token or no new line.
 */
function orderLineReplayKey(array $post, string $database, string $user): ?string {
	$orderId = (int)($post['id'] ?? 0);
	if ($orderId <= 0) {
		return null;
	}
	$token = $post['order_form_token'] ?? null;
	if (!is_string($token) || !preg_match('/\A[0-9a-f]{64}\z/D', $token)) {
		return null;
	}
	if (trim((string)($post['vare0'] ?? '')) === '' && trim((string)($post['beskrivelse0'] ?? '')) === '') {
		return null;
	}
	return hash('sha256', serialize([$database, $user, $post]));
}

/**
 * @return string Token for a hidden order_form_token field, new on every render of the order form.
 */
function orderFormToken(): string {
	return bin2hex(random_bytes(32));
}

/**
 * @param array       $session $_SESSION.
 * @param int         $orderId ordrer.id the line would be added to.
 * @param string|null $key     orderLineReplayKey() of this request.
 * @return bool True when this exact POST already added its new line to this order.
 */
function orderLineIsReplay(array $session, int $orderId, ?string $key): bool {
	if ($key === null || $orderId <= 0) {
		return false;
	}
	return ($session['order_line_saves'][$orderId] ?? null) === $key;
}

/**
 * Remember a POST whose new line was saved and committed. Failed saves are never
 * remembered, so a resend of a refused line is validated (and refused) again.
 *
 * @param array       $session $_SESSION, by reference.
 * @param int         $orderId ordrer.id the line was added to.
 * @param string|null $key     orderLineReplayKey() of this request.
 * @return void
 */
function orderLineRememberSave(array &$session, int $orderId, ?string $key): void {
	if ($key === null || $orderId <= 0) {
		return;
	}
	$saves = $session['order_line_saves'] ?? [];
	unset($saves[$orderId]);
	$saves[$orderId] = $key;
	$session['order_line_saves'] = array_slice($saves, -128, null, true);
}
