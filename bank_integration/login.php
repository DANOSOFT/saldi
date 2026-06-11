<?php
// Handles OAuth Initialization: calls /v1/authentication/initialize and redirects
// the browser to the bank auth URL. Always ends with exit.
//
// Accessed directly via a link. Never included.
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/login.php --- patch 0.0.2 --- 2026-05-26 ---
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
// 20260526 NTR - Initial version. Redirects to Aiia (Nordic API Gateway) for bank authentication.

@session_start();

if (isset($_GET['check'])) {
    header('Content-Type: application/json');
    echo json_encode(['authenticated' => !empty($_SESSION['OAuth'])]);
    exit;
}

// Relative-only: reject absolute URLs to prevent open redirect
$returnUrl = (isset($_GET['return']) && !preg_match('/^https?:\/\//i', $_GET['return']))
    ? $_GET['return']
    : null;

if (!empty($_SESSION['OAuth'])) {
    $now          = new DateTime();
    $oauthLoginExpires = new DateTime($_SESSION['OAuth']['login']['expires']);
    $oauthSessionExpires = new DateTime($_SESSION['OAuth']['session']['expires']);

    $supportsUnattended = isset($OAuth['login']['supportsUnattended']) && $OAuth['login']['supportsUnattended'] === true;

    if ($now < $oauthLoginExpires) {
        // Still valid — navigate to return URL, or referer if no return param
        $redirect = $returnUrl ?? ($_SERVER['HTTP_REFERER'] ?? '/');
        echo "<script>window.location.href = " . json_encode($redirect) . ";</script>";
        exit;
    } else if ($supportsUnattended && $now >= $oauthSessionExpires) {
        // Expired but supports unattended re-auth — redirect to trigger refresh flow in auth_check.php
        ['needsInit' => $needsInit, 'needsInteraction' => $needsInteraction] = include("includes/auth_check.php");
        if (!$needsInit && !$needsInteraction) {
            $redirect = $returnUrl ?? ($_SERVER['HTTP_REFERER'] ?? '/');
            echo "<script>window.location.href = " . json_encode($redirect) . ";</script>";
            exit;
        }
    }

    // Expired — purge from DB and session, then fall through to re-authenticate
    include_once(__DIR__ . '/../includes/connect.php');
    include_once(__DIR__ . '/../includes/std_func.php');
    include_once(__DIR__ . '/includes/persist.php');
    oauthSessionDelete();
    $_SESSION['OAuth'] = null;
}

include_once(__DIR__ . '/../includes/connect.php');
include_once(__DIR__ . '/../includes/std_func.php');

$sql = "SELECT
            MAX(CASE WHEN var_name = 'Client ID'     THEN var_value END),
            MAX(CASE WHEN var_name = 'Client Secret' THEN var_value END)
        FROM settings
        WHERE var_grp = 'OAuth'
          AND var_name IN ('Client ID', 'Client Secret')";
[$clientID, $clientSecret] = db_fetch_row(db_select($sql, __FILE__ . " linje " . __LINE__, true));

$redirectUrl = "https://ssl12.saldi.dk/ntr/bank_integration/webhook.php";
$body = json_encode([
    'userHash'       => session_id(),
    'redirectUrl'    => $redirectUrl,
    'isCodeExcluded' => false,
]);

$ch = curl_init('https://api.nordicapigateway.com/v1/authentication/initialize');
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

$data = json_decode($response, true);

if ($httpCode === 200 && !empty($data['authUrl'])) {
    ?>
        <!DOCTYPE html>
        <html>
        <head><title>Bank Authentication</title></head>
        <body>
        <p>A new window has been opened for bank authentication. Return here when complete.</p>
        <script>
            const topUrl    = window.top.location.href;
            const returnUrl = <?= json_encode($returnUrl) ?>;
            window.open(<?= json_encode($data['authUrl']) ?>, "_blank", "width=520,height=700,resizable=yes,scrollbars=yes");

            (function poll() {
                fetch("?check=1")
                    .then(r => r.json())
                    .then(data => {
                        if (data.authenticated) {
                            if (returnUrl) {
                                window.location.href = returnUrl;
                            } else {
                                window.top.location.href = topUrl;
                            }
                        } else {
                            setTimeout(poll, 2000);
                        }
                    })
                    .catch(() => setTimeout(poll, 2000));
            })();
        </script>
        </body>
        </html>
    <?php
    exit;
} elseif (!empty($data['error'])) {
    echo '<p>Aiia error: ' . htmlspecialchars($data['error']) . '</p>';
    exit;
} else {
    echo '<p>Unexpected response from authentication initialization.</p>';
    exit;
}
?>
