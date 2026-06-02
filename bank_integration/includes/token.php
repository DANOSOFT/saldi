<?php
// Provides exchangeCodeForTokens() — exchanges an OAuth authorization code for
// a token set via POST /v1/authentication/tokens.
// Returns [$httpCode, $data] where $data is the decoded JSON response.
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/token.php --- patch 0.0.1 --- 2026-05-26 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
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
//
// 20260526 NTR - Initial version. Extracted from auth_check.php and webhook.php.

/**
 * POST /v1/authentication/tokens — exchanges an OAuth authorization code for a token set.
 * $data is stored as $_SESSION['OAuth'] by webhook.php.
 *
 * @param string $code         Authorization code from the Aiia callback.
 * @param string $clientID     OAuth client ID (from settings table).
 * @param string $clientSecret OAuth client secret (from settings table).
 *
 * @return array{
 *   0: int,        HTTP status code.
 *   1: array{
 *     success:    bool,    True on a successful exchange.
 *     providerId: string,  Bank/provider identifier (top-level mirror of login.providerId).
 *     session: array{
 *       accessToken: string,  Bearer token for API calls (Authorization header). Short-lived.
 *       expires:     string,  ISO 8601 — when accessToken expires. Crossing this triggers an
 *                             unattended refresh via /v1/authentication/unattended.
 *     },
 *     login: array{
 *       providerId:          string,  Bank/provider identifier.
 *       expires:             string,  ISO 8601 — when the full login expires. Crossing this requires
 *                                     a new user-facing auth flow via login.php (needsInit).
 *       loginToken:          string,  Opaque token for unattended refresh. Long-lived.
 *       supportsUnattended:  bool,    Whether silent refresh is supported. False means the user
 *                                     must re-authenticate manually when session.expires is crossed.
 *       label:               string,  Human-readable description of the login session.
 *       subjectId:           string,  Provider-side hashed user identifier.
 *     },
 *     userHash: string,  Added by webhook.php (not from Aiia): session_id() at token exchange time.
 *                        Passed to the unattended endpoint so Aiia can match the original initialize call.
 *   }
 * }
 */
function exchangeCodeForTokens(string $code, string $clientID, string $clientSecret): array {
    $body = json_encode(['code' => $code]);

    $ch = curl_init('https://api.nordicapigateway.com/v1/authentication/tokens');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Client-Id: '     . $clientID,
            'X-Client-Secret: ' . $clientSecret,
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$httpCode, json_decode($response, true)];
}
