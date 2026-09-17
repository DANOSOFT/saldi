<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- payments/mobilepay/mobilepayListen.php --- lap 4.1.0 --- 2024.02.27 ---
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
// Copyright (c) 2024-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 20240209 PHR Added indbetaling
// 20240227 PHR Added $printfile and call to saldiprint.php

#print '<head>';
#print '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400&display=swap" rel="stylesheet">';
#print '</head>';

include("paymentui.php");

$css = "../../../css/flatpay.css";

$ident = $_GET['ident'];
$indbetaling = $_GET['indbetaling'];
$ordre_id = $_GET['id'];
$kasse = $_COOKIE['saldi_pos'];

$q=db_select("select var_value from settings where var_name = 'client_id' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$client_id = db_fetch_array($q)[0];
$q=db_select("select var_value from settings where var_name = 'client_secret' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$client_secret = db_fetch_array($q)[0];
$q=db_select("select var_value from settings where var_name = 'subscriptionKey' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$subscription = db_fetch_array($q)[0];
$q=db_select("select var_value from settings where var_name = 'MSN' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$q=db_select("select var_value from settings where var_name = 'MSN' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$MSN = db_fetch_array($q)[0];

# #########################################################
# 
# Get auth token
# 
# #########################################################
$url = 'https://api.vipps.no/accesstoken/get';

$headers = array(
    'Content-Type: application/json',
    "Client_id: $client_id",
    "Client_secret: $client_secret",
    "Ocp-Apim-Subscription-Key: $subscription",
    "Merchant-Serial-Number: $MSN",
    'Vipps-System-Name: Saldi',
    "Vipps-System-Version: $version",
    "Vipps-System-Plugin-Name: Saldi $db",
    "Vipps-System-Plugin-Version: $version",
);

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

if ($response === false) {
    // Handle curl error
    $error = curl_error($ch);
    echo "Curl error: " . $error;
} else {
    // Process response
    $response = json_decode($response, true);
    $accessToken = $response["access_token"];
}

curl_close($ch);

# #########################################################
# 
# Get payment status
# 
# #########################################################

$url = "https://api.vipps.no/epayment/v1/payments/$ident/cancel";
echo $url;

$headers = array(
    "Content-Type: application/json",
    "Merchant-Serial-Number: $MSN",
    "Ocp-Apim-Subscription-Key: $subscription",
    "Authorization: Bearer $accessToken",
    "Idempotency-Key: $ident" ,
    'Vipps-System-Name: Saldi',
    "Vipps-System-Version: $version",
    "Vipps-System-Plugin-Name: Saldi $db",
    "Vipps-System-Plugin-Version: $version",
);

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    // Handle curl error
    $error = curl_error($ch);
    echo "Curl error: " . $error;
} else {
    // Process response
    echo "Response: " . $response . "\n";
    echo "Status Code: " . $status_code . "\n";
}

curl_close($ch);

echo "
<script>
window.location.replace('../pos_ordre.php?id=$ordre_id&godkendt=afvist')
</script>
";

?>
