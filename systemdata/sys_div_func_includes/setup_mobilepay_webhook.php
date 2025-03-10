
<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- payments/mobilepay/mobilepay.php --- lap 4.1.0 --- 2024.02.27 ---
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

@session_start();
$s_id = session_id();

include ("../../includes/connect.php");
include ("../../includes/online.php");
include ("../../includes/std_func.php");
include ("../../includes/stdFunc/dkDecimal.php");
include ("../../includes/stdFunc/usDecimal.php");

$css = "../../../css/flatpay.css";

$q=db_select("select var_value from settings where var_name = 'client_id' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$client_id = db_fetch_array($q)[0];
$q=db_select("select var_value from settings where var_name = 'client_secret' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$client_secret = db_fetch_array($q)[0];
$q=db_select("select var_value from settings where var_name = 'subscriptionKey' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$subscription = db_fetch_array($q)[0];
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
    'Content-Length: 0'
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



# Get webhooks

/*
$url = 'https://api.vipps.no/webhooks/v1/webhooks';

$headers = array(
    'Content-Type: application/json',
    "Authorization: Bearer $accessToken",
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
	if ($status_code === 201) {
		$data = json_decode($response, true);
print_r($data);
		exit;
	}
}

curl_close($ch);

exit;

*/


# Delete webhooks
/*
$url = 'https://api.vipps.no/webhooks/v1/webhooks/6b55ee2d-d58f-496c-8dbf-2ccb34955bb4';

$headers = array(
    'Content-Type: application/json',
    "Authorization: Bearer $accessToken",
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

curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
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
	if ($status_code === 201) {
		$data = json_decode($response, true);
print_r($data);
		exit;
	}
}

curl_close($ch);

exit;
*/




$url = 'https://api.vipps.no/webhooks/v1/webhooks';

$headers = array(
    'Content-Type: application/json',
    "Authorization: Bearer $accessToken",
    "Client_id: $client_id",
    "Client_secret: $client_secret",
    "Ocp-Apim-Subscription-Key: $subscription",
    "Merchant-Serial-Number: $MSN",
    'Vipps-System-Name: Saldi',
    "Vipps-System-Version: $version",
    "Vipps-System-Plugin-Name: Saldi $db",
    "Vipps-System-Plugin-Version: $version",
);

$data = json_encode(array(
    'url' => "https://$_SERVER[SERVER_NAME]/pos/debitor/payments/mobilepay/webhook_recive.php?db=" . $db,
    'events' => ['epayments.payment.authorized.v1', 'user.checked-in.v1', 'epayments.payment.cancelled.v1', 'epayments.payment.aborted.v1', 'epayments.payment.expired.v1', 'epayments.payment.terminated.v1']
));

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
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
	if ($status_code === 201) {
		$data = json_decode($response, true);
		$qtxt="insert into settings (var_name, var_grp, var_value, var_description) values ('webhook_secret', 'mobilepay', '$data[secret]', 'The secret that is genertated for the webhook')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
print_r($data);
		exit;
		header("Location: ../diverse.php?sektion=div_valg");
		exit;
	}
}

curl_close($ch);

?>
