<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- payments/flatpay.php --- lap 4.1.0 --- 2024.02.27 ---
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

@session_start();
$s_id = session_id();

# #####################################################
# 
# Verify request
#
# #####################################################

file_put_contents("../../../temp/works", file_get_contents("php://input"));
// Get the request body
$requestBody = file_get_contents('php://input');

// Extract required headers
$method = $_SERVER['REQUEST_METHOD'];
$url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$pathAndQuery = $_SERVER['REQUEST_URI'];
$xMsDate = $_SERVER['HTTP_X_MS_DATE'];
$xMsContentSha256 = $_SERVER['HTTP_X_MS_CONTENT_SHA256'];
$host = $_SERVER['HTTP_HOST'];

// Construct expected content hash
$expectedContentHash = base64_encode(hash('sha256', $requestBody, true));

// Construct expected signed string
$expectedSignedString = "$method\n$pathAndQuery\n$xMsDate;$host;$xMsContentSha256";

// Define the secret
$dbFolder = '../../../temp/' . $_GET["db"];
$secret = file_get_contents("$dbFolder/.ht_mobilepay_secret.txt");

// Generate expected signature
$expectedSignature = base64_encode(hash_hmac('sha256', $expectedSignedString, $secret, true));

// Construct expected Authorization header
$expectedAuth = "HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature=$expectedSignature";

// Verify content hash
if ($xMsContentSha256 !== $expectedContentHash) {
    file_put_contents("../../../temp/works", 'Content hash was not valid');
    exit;
}

// Verify signature
if ($_SERVER['HTTP_X_VIPPS_AUTHORIZATION'] !== $expectedAuth) {
    # file_put_contents("../../../temp/works", "Authendication was not valid\n$_SERVER[HTTP_X_VIPPS_AUTHORIZATION] \n$expectedAuth\n\n$xMsContentSha256\n$expectedContentHash\n\n$expectedSignedString\n$secret\n---");
#    exit;
}


# #####################################################
# 
# Execute request
#
# #####################################################



// Check if POST data exists
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get raw POST data
    $postData = file_get_contents("php://input");

    // Convert JSON data to an associative array
    $postDataArray = json_decode($postData, true);

    if ($postDataArray !== null) {
        // Determine filename based on the presence of a key in the JSON data
        if (isset($postDataArray["merchantQrId"])) {
            $filename = $postDataArray["merchantQrId"];
        } else {
            // If key doesn't exist, use a default filename
            $filename = $postDataArray["reference"];
        }

        // Check if the folder exists
        if (!file_exists($dbFolder)) {
            // Create the folder if it doesn't exist
            mkdir($dbFolder, 0777, true); // You might want to adjust the permissions
        }

	$filePath = '../../../temp/' . $_GET["db"] . '/data_' . $filename . '.json';

        // Write JSON data to file
        file_put_contents($filePath, $postData);
	file_put_contents("../../../temp/works", $filePath);

        echo "JSON data has been written to file with timestamp.";
    } else {
        echo "Failed to decode JSON data.";
file_put_contents("../../../temp/works", "Failed decode");
    }
} else {

    echo "No POST data received.";
}

?>
