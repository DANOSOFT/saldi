<?php

function pollPayment($paymentId, $token, $pollDelay) {
    $maxAttempts = 3;
    $attempt = 0;
    $success = false;

    while (!$success && $attempt < $maxAttempts) {
        usleep($pollDelay * 1000); // Wait before each attempt

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.mobilepay.dk/pos/v10/payments/' . $paymentId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Setting timeout to 5 seconds

        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: Bearer ' . $token;
        $headers[] = 'X-MobilePay-Client-System-Version: 4.1.0';

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
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

    if (!$success) {
        echo "Failed after $maxAttempts attempts.\n";
        $godkendt = false;
    }
    // return status
    if(isset(json_decode($response)->status))
        return json_decode($response)->status;
    else
        return "error";
}

$token = $_GET["token"];
$paymentId = $_GET["paymentId"];

$status = pollPayment($paymentId, $token, 0);

echo json_encode(["status" => $status]);