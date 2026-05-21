<?php
// This file is used to login to the bank integration module.
// It checks if the user is logged in and has the necessary permissions to access the module.
// If not, it redirects the user to the login page.
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/login.php --- patch 0.0.1 --- 2026-05-21 ---
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
// 20260518 NTR - Initial version.
// 20260521 NTR - OAuth session check and initialization logic.

@session_start();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$OAuth = isset($_SESSION['OAuth']) ? $_SESSION['OAuth'] : null;

$now = new DateTime();
$needsInit        = ($OAuth === null || $OAuth === false);
$needsInteraction = false;

if (!$needsInit) {
    $expires          = new DateTime($OAuth['expires']);
    $next_interaction = new DateTime($OAuth['next_interaction']);

    if ($now >= $expires) {
        $needsInit = true;
    } elseif ($now >= $next_interaction) {
        $needsInteraction = true;
    }
}

if ($needsInit || $needsInteraction) {
    include("../includes/connect.php");
    include("../includes/std_func.php");

    $globalConn = db_connect($sqhost, $squser, $sqpass, 'develop');
    $sql = "SELECT
                MAX(CASE WHEN var_name = 'ClientID'     THEN var_value END),
                MAX(CASE WHEN var_name = 'ClientSecret' THEN var_value END)
            FROM settings
            WHERE var_grp = 'OAuth'
              AND var_name IN ('ClientID', 'ClientSecret')";
    $result = pg_query($globalConn, $sql);
    [$clientID, $clientSecret] = pg_fetch_row($result);
    pg_close($globalConn);

    if ($needsInit) {
        $redirectUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $body = json_encode([
            'userHash'    => session_id(),
            'redirectUrl' => $redirectUrl,
        ]);

        $ch = curl_init('https://api.nordicapigateway.com/v1/authentication/initialize');
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
        curl_close($ch);

        $data = json_decode($response, true);

        // TODO: replace 'authorizationUri' with the actual field name once confirmed
        $authUrl = $data['authorizationUri']
            ?? $data['redirectUri']
            ?? $data['url']
            ?? null;

        if ($authUrl) {
            header('Location: ' . $authUrl);
            exit;
        }

        // Debug: remove once flow is confirmed working
        echo '<pre>HTTP ' . $httpCode . "\n" . htmlspecialchars($response) . '</pre>';
        exit;
    } else {
        // next_interaction: silent background refresh, no redirect
        $body = json_encode(['userHash' => session_id()]);

        $ch = curl_init('https://api.nordicapigateway.com/v1/authentication/initialize');
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
        curl_close($ch);

        $data = json_decode($response, true);

        if (!empty($data)) {
            $data['next_interaction'] = (new DateTime('+5 minutes'))->format(DateTime::ATOM);
            $_SESSION['OAuth'] = $data;
        }
    }
}


?>