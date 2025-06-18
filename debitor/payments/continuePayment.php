<?php

function createGUID() {
    if (function_exists('com_create_guid') === true)
    {
        return trim(com_create_guid(), '{}');
    }
    
    return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
}

$success = false;
$res = "";

function capturePayment($paymentId, $token, $modtaget) {

    global $success, $res;
    $maxAttempts = 3;
    $attempt = 0;
    $success = false;
    // make modtaget an int
    $modtaget = (int)$modtaget;

    while (!$success && $attempt < $maxAttempts) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.mobilepay.dk/pos/v10/payments/' . $paymentId . '/capture');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            "amount" => $modtaget,
        )));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Setting timeout to 5 seconds

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: Bearer ' . $token;
        $headers[] = 'X-MobilePay-Client-System-Version: 4.1.0';

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $res = $httpcode;
        curl_close($ch);
        if ($httpcode != 200) {
            $attempt++;
        } elseif (curl_errno($ch) == CURLE_OPERATION_TIMEDOUT) {
            $attempt++;
        } elseif (curl_errno($ch)) {
            break;
        } else {
            $success = true;
        }
    }

    if (!$success) {
        echo json_encode(["status" => "error", "message" => "Payment capture failed", "response" => $response]);
    }
}

function offBoarding($posId, $token){
    // Offboarding
    global $success;
    $maxAttempts = 3;
    $attempt = 0;
    $success = false;

    while (!$success && $attempt < $maxAttempts) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.mobilepay.dk/pos/v10/pointofsales/'.$posId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Setting timeout to 5 seconds

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: Bearer ' . $token;
        $headers[] = 'X-MobilePay-Client-System-Version: 4.1.0';

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpcode != 200) {
            $attempt++;
        } elseif (curl_errno($ch) == CURLE_OPERATION_TIMEDOUT) {
            $attempt++;
        } elseif (curl_errno($ch)) {
            break;
        } else {
            $success = true;
        }
    }

    if (!$success) {
        echo json_encode(["status" => "error", "message" => "Offboarding failed", "response" => $response]);
    }
}

$posId = $_GET["posId"];
$token = $_GET["token"];
$paymentId = $_GET["paymentId"];
$modtaget = $_GET["modtaget"];

capturePayment($paymentId, $token, $modtaget);
offBoarding($posId, $token);
if($success == true){
    echo json_encode(array(
        "status" => "success",
        "message" => "Payment captured and POS offboarded",
        "response" => $res
    ));
}