<?php

$success = false;

function cancelPayment($paymentId, $token){
    // cancel payment
    global $success;

    $maxAttempts = 3;
    $attempt = 0;
    $success = false;

    while (!$success && $attempt < $maxAttempts) {
        sleep(1); // Wait before each attempt
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.mobilepay.dk/pos/v10/payments/$paymentId/cancel");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Setting timeout to 5 seconds

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

/*     if(!$success){
        echo json_encode(["status" => "error", "message" => "Payment cancel failed", "response" => $response]);
        exit;
    } */
        

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
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Setting timeout to 5 seconds

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

    if(!$success){
        echo json_encode(["status" => "error", "message" => "Off boarding failed", "response" => $response]);
        exit;
    }
}

$token = $_GET["token"];
$posId = $_GET["posId"];
$paymentId = $_GET["paymentId"];

cancelPayment($paymentId, $token);
offBoarding($posId, $token);
if($success){
    echo json_encode(["status" => "cancelled"]);
}