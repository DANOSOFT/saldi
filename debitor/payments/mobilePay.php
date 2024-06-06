<?php

$godkendt = false;

// Initiate payment 389328b7-e3f1-40fd-9ce6-59e3fba3c5be 
// secret 6OQPj_EeYZxkXtXGb0nRzXTTuSqhCKEwY2AYHnwhrCc
$client_id ="389328b7-e3f1-40fd-9ce6-59e3fba3c5be ";
$client_secret= "6OQPj_EeYZxkXtXGb0nRzXTTuSqhCKEwY2AYHnwhrCc";

function createGUID() {
    if (function_exists('com_create_guid') === true)
    {
        return trim(com_create_guid(), '{}');
    }
    
    return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
}

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'https://api.mobilepay.dk/integrator-authentication/connect/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
    'grant_type' => 'client_credentials',
    'merchant_vat' => "DK".$cvrnr
)));

$headers = array();
$headers[] = 'Content-Type: application/x-www-form-urlencoded';
$headers[] = 'Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);

/* echo $response; */
if(curl_errno($ch)){
    echo 'Error:' . curl_error($ch);
}
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
/* echo 'HTTP code: ' . $httpcode; */
curl_close($ch);

$token = json_decode($response)->access_token;


// Onboarding 
$maxAttempts = 3;
$attempt = 0;
$success = false;
$key = createGUID();

$data = array(
    "merchantPosId" => createGUID(),
    "storeId" => $storeId,
    "name" => "Test POS",
    "beaconId" => $beaconId,
    "supportedBeaconTypes" => [
        "QR"
    ],
    "requirePaymentBeforeCheckin" => false
);

while (!$success && $attempt < $maxAttempts) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.mobilepay.dk/pos/v10/pointofsales');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Setting timeout to 5 seconds

    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Authorization: Bearer ' . $token;
    $headers[] = 'X-MobilePay-Idempotency-Key: ' . $key;
    $headers[] = 'X-MobilePay-Client-System-Version: 4.1.0';

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if(isset(json_decode($response)->code)){
        $code = json_decode($response)->code;
        if($code == "1202" && isset($_SESSION["posId"])){
            offBoarding($_SESSION["posId"], $token);
            $attempt++;
        }else{
            header("location: pos_ordre.php?id=$id&godkendt=afbrudt");
        }
    }elseif ($httpcode == 500) {
        echo "Internal Server Error occurred, retrying...\n";
        $attempt++;
    } elseif (curl_errno($ch) == CURLE_OPERATION_TIMEDOUT) {
        echo "Timeout occurred, retrying...\n";
        $attempt++;
    } elseif (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
        $godkendt = false;
        break;
    } else {
        $success = true;
    }
}

if (!$success) {
    echo "Failed after $maxAttempts attempts.\n";
    $godkendt = false;
    exit;
}else{
    $posId = json_decode($response)->posId;
    $_SESSION["posId"] = $posId;
}

function initiatePayment($token, $data) {
    
    $maxAttempts = 3;
    $attempt = 0;
    $success = false;
    $key = createGUID();
    
    while (!$success && $attempt < $maxAttempts) {
        $ch = curl_init();
    
        curl_setopt($ch, CURLOPT_URL, 'https://api.mobilepay.dk/pos/v10/payments');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Setting timeout to 5 seconds
    
        $headers = array();
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Authorization: Bearer ' . $token;
        $headers[] = 'X-MobilePay-Idempotency-Key: ' . $key;
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
    return $paymentId = json_decode($response)->paymentId;
}


/* 
function capturePayment($paymentId, $token) {
    $maxAttempts = 3;
    $attempt = 0;
    $success = false;

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
        echo "Failed capture after $maxAttempts attempts.\n";
        $godkendt = false;
    }
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
}*/

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



 // Initiate payment
$data = array(
    "posId" => $posId,
    "orderId" => $id,
    "amount" => $modtaget,
    "currencyCode" => "DKK",
    "plannedCaptureDelay" => "None"
);

$paymentId = initiatePayment($token, $data);
/*
// Query the payment
$maxAttempts = 10;
$attempt = 0;
while($status != "Reserved" && $attempt < $maxAttempts){
    $status = pollPayment($paymentId, $token, 2000);
    $attempt++;
}

if($godkendt != false){
    // Capture the payment
    capturePayment($paymentId, $token);
}else{
    cancelPayment($paymentId, $token);
}

offBoarding($posId, $token); */
