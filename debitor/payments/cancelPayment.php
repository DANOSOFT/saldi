<?php
function createGUID() {
    if (function_exists('com_create_guid') === true)
    {
        return trim(com_create_guid(), '{}');
    }
    
    return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
}


function cancelPayment($paymentId, $token){
    // cancel payment

$maxAttempts = 3;
$attempt = 0;
$success = false;
$key = createGUID();

while (!$success && $attempt < $maxAttempts) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, "https://api.sandbox.mobilepay.dk/pos-self-certification-api/pos/v10/payments/$paymentId/cancel");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Setting timeout to 5 seconds

    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Authorization: Bearer ' . $token;
    $headers[] = 'X-MobilePay-Client-System-Version: 4.1.0';

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpcode == 500) {
        echo "Internal Server Error occurred, retrying...\n";
        $attempt++;
    } elseif (curl_errno($ch) == CURLE_OPERATION_TIMEDOUT) {
        echo "Timeout occurred, retrying...\n";
        $attempt++;
    } elseif (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
        break;
    } else {
        $success = true;
    }
}
$godkendt = false;
}

function offBoarding($posId, $token){
    // Offboarding

$maxAttempts = 3;
$attempt = 0;
$success = false;
$key = createGUID();

while (!$success && $attempt < $maxAttempts) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.mobilepay.dk/pos/v10/pointofsales/'.$posId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Setting timeout to 5 seconds

    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Authorization: Bearer ' . $token;
    $headers[] = 'X-MobilePay-Idempotency-Key: ' . $key;
    $headers[] = 'X-MobilePay-Client-System-Version: 4.1.0';

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpcode == 500) {
        echo "Internal Server Error occurred, retrying...\n";
        $attempt++;
    } elseif (curl_errno($ch) == CURLE_OPERATION_TIMEDOUT) {
        echo "Timeout occurred, retrying...\n";
        $attempt++;
    } elseif (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
        break;
    } else {
        $success = true;
    }
}
}

$token = $_GET["token"];
$posId = $_GET["posId"];
$paymentId = $_GET["paymentId"];

cancelPayment($paymentId, $token);
offBoarding($posId, $token);

echo json_encode(["status" => "cancelled"]);