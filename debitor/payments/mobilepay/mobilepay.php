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

include("paymentui.php");

$css = "../../../css/flatpay.css";

$raw_amount = (float) usdecimal(if_isset($_GET['amount'], 0));
$pretty_amount = dkdecimal($raw_amount, 2);
$ordre_id    = if_isset($_GET['id'], 0);
$indbetaling = if_isset($_GET['indbetaling'], 0);
$kasse = $_COOKIE['saldi_pos'];

$q=db_select("select var_value from settings where var_name = 'client_id' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$client_id = db_fetch_array($q)[0];
$q=db_select("select var_value from settings where var_name = 'client_secret' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$client_secret = db_fetch_array($q)[0];
$q=db_select("select var_value from settings where var_name = 'subscriptionKey' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$subscription = db_fetch_array($q)[0];
$q=db_select("select var_value from settings where var_name = 'MSN' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$MSN = db_fetch_array($q)[0];

$type = ($raw_amount < 0) ? "REFUND" : "SALE";
$amount = abs($raw_amount) * 100;


# Get settings
$qtxt = "SELECT box4 FROM grupper WHERE beskrivelse = 'Pos valg' AND kodenr = '2' and fiscal_year = '$regnaar'";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
$terminal_id = explode(chr(9),db_fetch_array($q)[0])[$kasse-1];

if ($db=='pos_10' || $db=='laja_15') {
  $printfile = 'https://'.$_SERVER['SERVER_NAME'];
  $printfile.= str_replace('debitor/payments/flatpay.php',"temp/$db/receipt_$kasse.txt",$_SERVER['PHP_SELF']);
} else $printfile = NULL;



$filePath = '../../temp/' . $db . '/data_kasse' . $kasse . '.json';

// Check if the file exists
if (file_exists($filePath)) {
    // Read the JSON file
    $jsonData = file_get_contents($filePath);

    // Decode the JSON data
    $data = json_decode($jsonData, true); // true to decode as associative array

    // Check if decoding was successful
    if ($data !== null) {
	$customerToken = $data['customerToken'];
	unlink($filePath);
    } else {
        // Error handling if JSON decoding failed
	ui_output($pretty_amount, "Error decoding file", "red", $raw_amount, $indbetaling, $ordre_id);
	exit;
    }
} else {
    // Error handling if file doesn't exist
	ui_output($pretty_amount, "Afventer QR scan", "yellow", $raw_amount, $indbetaling, $ordre_id);
	print "
	<script>

	setTimeout(() => {
	  location.reload();
	}, 3000)

	</script>
	";
    exit;
}

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
# Start ordre
# 
# #########################################################
# Try creating the payment 20 times, if a payment already exsists with that ID (canceled or split payments)
$time = date("is");
$url = 'https://api.vipps.no/epayment/v1/payments';
$data = array(
    'amount' => array(
	'value' => $amount,
	'currency' => 'DKK'
    ),
    'paymentMethod' => array(
	'type' => 'WALLET'
    ),
    'customer' => array(
	'customerToken' => $customerToken
    ),
    'reference' => "ordreid-" . $ordre_id . '-' . $time,
    'userFlow' => 'PUSH_MESSAGE',
    'paymentDescription' => 'Betaling med mobilepay'
);


$headers = array(
    'Content-Type: application/json',
    'Authorization: Bearer ' . $accessToken,
    'Ocp-Apim-Subscription-Key: ' . $subscription,
    'Merchant-Serial-Number: ' . $MSN,
    'Idempotency-Key: ' . $ordre_id . '-' . $time,
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
	if ($status_code !== 201) {
		$data = json_decode($response, true); // true to decode as associative array
print_r($data);
		if ($data["title"] !== "Idempotency error") {
			ui_output($pretty_amount, "Fejl $data[title] - $data[detail]", "red", $raw_amount, $indbetaling, $ordre_id);
		} else {
			ui_output($pretty_amount, "Fejl en overførsel med det ID eksitere allerede, prøv igen eller kontakt support.", "red", $raw_amount, $indbetaling, $ordre_id);
		}
	} else {
		# Move to next stage
		$ref = "tst";
		header("Location: mobilepayListen.php?ident=ordreid-$ordre_id-$time&amount=$amount&pretty_amount=$pretty_amount&ordre_id=$ordre_id&indbetaling=$indbetaling&ref=$ordre_id");
		exit;
	}
    // Process response
}

curl_close($ch);

