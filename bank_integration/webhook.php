<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/webhook.php --- patch 0.0.1 --- 2026-05-21 ---
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
// 20260521 NTR - Initial version. Handles OAuth callback from Aiia (Nordic API Gateway).

/*
error_reporting(E_ALL);
ini_set('display_errors', 1);
*/

@session_start();
$s_id = session_id();
$header = 'nix';

$code      = $_GET['code']      ?? null;
$returnUrl = $_GET['returnUrl'] ?? '/';

if (empty($code)) {
    echo '<p>Aiia callback error: missing authorization code.</p>';
    exit;
}

include_once(__DIR__ . '/../includes/connect.php');
include_once(__DIR__ . '/../includes/online.php');
include_once(__DIR__ . '/../includes/std_func.php');

$sql = "SELECT
            MAX(CASE WHEN var_name = 'Client ID'     THEN var_value END),
            MAX(CASE WHEN var_name = 'Client Secret' THEN var_value END)
        FROM settings
        WHERE var_grp = 'OAuth'
          AND var_name IN ('Client ID', 'Client Secret')";
[$clientID, $clientSecret] = db_fetch_row(db_select($sql, __FILE__ . " linje " . __LINE__, true));

include_once(__DIR__ . '/includes/token.php');
[$httpCode, $data] = exchangeCodeForTokens($code, $clientID, $clientSecret);

if ($httpCode === 200 && empty($data['error'])) {
    $data['userHash'] = session_id();
    $_SESSION['OAuth'] = $data;

    include_once(__DIR__ . '/includes/crypt.php');
    include_once(__DIR__ . '/includes/persist.php');
    try {
        oauthSessionSave(oauthEncrypt(json_encode($data)));
    } catch (Throwable $e) {
        $ref = 'BANK-' . date('YmdHis') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
        error_log("[bank_integration/webhook.php][$ref] " . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        echo '<p>Bank authentication could not be saved.</p>'
           . '<p>If this is your own setup, enter the logs otherwise contact support and quote reference: <strong>' . $ref . '</strong></p>';
        exit;
    }

    echo '<script>console.log("Closing window..."); window.close();</script>';
} else {
    $error = $data['error'] ?? 'Unexpected response (HTTP ' . $httpCode . ')';
    echo '<p>Aiia token error: ' . htmlspecialchars($error) . '</p>';
}
?>
