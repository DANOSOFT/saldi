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
$pretty_amount = $_GET['pretty_amount'];
$amount = $_GET['amount'];
$raw_amount = (float) usdecimal(if_isset($_GET['amount'], 0));
$indbetaling = $_GET['indbetaling'];
$ordre_id = $_GET['ordre_id'];
$kasse = $_COOKIE['saldi_pos'];


$filePath = '../../temp/' . $db . '/data_' . $ident . '.json';

// Check if the file exists
if (file_exists($filePath)) {
    // Read the JSON file
    $jsonData = file_get_contents($filePath);

    // Decode the JSON data
    $data = json_decode($jsonData, true); // true to decode as associative array

    // Check if decoding was successful
    if ($data !== null && $data['reference'] === $ident) {
	unlink($filePath);
	if ($data["name"] === "AUTHORIZED") {
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
		    exit;
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

		$url = "https://api.vipps.no/epayment/v1/payments/$ident/capture";

		$data = array(
		    'modificationAmount' => array(
		        'currency' => 'NOK',
		        'value' => $raw_amount
		    )
		);


		$headers = array(
		    'Content-Type: application/json',
		    "Merchant-Serial-Number: $MSN",
		    "Ocp-Apim-Subscription-Key: $subscription",
		    "Authorization: Bearer $accessToken",
		    'Idempotency-Key: ' . $ident,
		    'Vipps-System-Name: Saldi',
		    "Vipps-System-Version: $version",
		    "Vipps-System-Plugin-Name: Saldi $db",
		    "Vipps-System-Plugin-Version: $version",
		);

		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
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
		    $data = json_decode($response, true);
		    if ($data["state"] === "AUTHORIZED") {
			ui_output($pretty_amount, "Gennemført", "green", $raw_amount, $indbetaling, $ordre_id);
		    } else {
print_r($data);
			ui_output($pretty_amount, $data["state"] === "ABORTED" ? "Anulleret af kunde" : $data["state"], "red", $raw_amount, $indbetaling, $ordre_id);
		    }
		}

		curl_close($ch);

		# #########################################################
		
	} if ($data["name"] === "ABORTED") {
		ui_output($pretty_amount, "Anulleret af kunde", "red", $raw_amount, $indbetaling, $ordre_id);
	} else {
		ui_output($pretty_amount, "Annulleret", "red");
	}

    } else {
        // Error handling if JSON decoding failed
        echo "Error decoding JSON file.";
	exit;
    }
} else {
    // Error handling if file doesn't exist
	ui_output($pretty_amount, "Ingen betaling logget", "yellow", $raw_amount, $indbetaling, $ordre_id, $ident);
	print "
	<script>

	setTimeout(() => {
	  location.reload();
	}, 3000)

	</script>
	";
    exit;
}

