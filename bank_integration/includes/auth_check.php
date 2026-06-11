<?php
// Include this file on every page that requires bank OAuth authentication.
// Silently refreshes the session via the Interaction protocol when due.
// Falls through to login.php (Initialization) if a full re-auth is required.
// Returns ['needsInit' => bool, 'needsInteraction' => bool].
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/auth_check.php --- patch 0.0.2 --- 2026-05-26 ---
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
// 20260526 NTR - Initial version. Split from login.php.
// 20260609 NTR - Wrapped in IIFE to prevent variable leakage into caller scope.

@session_start();

return (function () {
    // If the PHP session is empty, try restoring the OAuth data from persistent DB storage
    // before evaluating state — avoids a full re-auth on every new session.
    if (empty($_SESSION['OAuth'])) {
        include_once(__DIR__ . '/../../includes/connect.php');
        include_once(__DIR__ . '/../../includes/std_func.php');
        include_once(__DIR__ . '/crypt.php');
        include_once(__DIR__ . '/persist.php');

        $encrypted = oauthSessionLoad();
        if ($encrypted !== null) {
            $json = oauthDecrypt($encrypted);
            if ($json !== null) {
                $_SESSION['OAuth'] = json_decode($json, true);
            }
        }
    }

    $OAuth = isset($_SESSION['OAuth']) ? $_SESSION['OAuth'] : null;

    $now              = new DateTime();
    $needsInit        = ($OAuth === null || $OAuth === false);
    $needsInteraction = false;

    if (!$needsInit) {
        $expires            = new DateTime($OAuth['login']['expires']);
        $next_interaction   = new DateTime($OAuth['session']['expires']);
        $supportsUnattended = isset($OAuth['login']['supportsUnattended']) && $OAuth['login']['supportsUnattended'] === true;

        if ($now >= $expires) {
            $needsInit = true;
        } elseif (
            $now >= $next_interaction &&
            isset($OAuth['login']['loginToken']) &&
            $supportsUnattended
        ) {
            $needsInteraction = true;
        }
    }

    if ($needsInteraction) {
        // Run at most once per minute per user — prevents double-execution when this file
        // is included via multiple places in the same request,
        // and avoids hammering Aiia on every page load.
        $now_ts = time();
        if (isset($_SESSION['_bank_auth_checked_at']) && ($now_ts - $_SESSION['_bank_auth_checked_at']) < 60) {
            return ['needsInit' => $needsInit, 'needsInteraction' => $needsInteraction];
        }
        $_SESSION['_bank_auth_checked_at'] = $now_ts;

        // include the DB connection and helper functions to perform the unattended refresh call
        include_once(__DIR__ . '/../../includes/connect.php');
        include_once(__DIR__ . '/../../includes/std_func.php');

        // Clear current session and DB state before attempting unattended refresh — if it fails, we want to be sure to fall back to a full re-auth flow in login.php
        include_once(__DIR__ . '/persist.php');
        oauthSessionDelete();
        $sql = "SELECT
                    MAX(CASE WHEN var_name = 'Client ID'     THEN var_value END),
                    MAX(CASE WHEN var_name = 'Client Secret' THEN var_value END)
                FROM settings
                WHERE var_grp = 'OAuth'
                  AND var_name IN ('Client ID', 'Client Secret')";
        [$clientID, $clientSecret] = db_fetch_row(db_select($sql, __FILE__ . " linje " . __LINE__, true));

        $body = json_encode([
            'userHash'   => $OAuth['userHash'] ?? session_id(),
            'loginToken' => $OAuth['login']['loginToken'],
        ]);

        $ch = curl_init('https://api.nordicapigateway.com/v1/authentication/unattended');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Client-Id: ' . $clientID,
                'X-Client-Secret: ' . $clientSecret,
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $data = json_decode($response, true);

        if ($httpCode === 200) {
            $_SESSION['OAuth'] = $data;

            include_once(__DIR__ . '/crypt.php');
            oauthSessionSave(oauthEncrypt(json_encode($data)));
            $needsInit        = false;
            $needsInteraction = false;
        } else {
            // Unattended refresh call failed — escalate to full re-auth
            $_SESSION['OAuth'] = null;
            $needsInit         = true;
            $needsInteraction  = false;
        }
    } elseif ($needsInit) {
        include_once(__DIR__ . '/persist.php');
        oauthSessionDelete();
        $_SESSION['OAuth'] = null;
    }

    return ['needsInit' => $needsInit, 'needsInteraction' => $needsInteraction];
})();
