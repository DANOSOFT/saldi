<?php

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

$merchant_vat = strpos($cvrnr, "DK") === 0 ? $cvrnr : "DK".$cvrnr;

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'https://api.mobilepay.dk/integrator-authentication/connect/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
    'grant_type' => 'client_credentials',
    'merchant_vat' => $merchant_vat
)));

$headers = array();
$headers[] = 'Content-Type: application/x-www-form-urlencoded';
$headers[] = 'Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
if(curl_errno($ch)){
    echo 'Error:' . curl_error($ch);
}
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
/* echo 'HTTP code: ' . $httpcode; */
curl_close($ch);
if(isset(json_decode($response)->error)){
    $error = json_decode($response)->error;
    echo $error;
}
$token = json_decode($response)->access_token;

// Onboarding 
$maxAttempts = 5;
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
    $decodedResponse = json_decode($response);
    if($decodedResponse !== null && isset($decodedResponse->code)){
        // Fetch posId from api
        $ch = curl_init();
        // Gets a list of point of sale IDs corresponding to the given filters.
        curl_setopt($ch, CURLOPT_URL, 'https://api.mobilepay.dk/pos/v10/pointofsales?beaconId='.$beaconId);
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
        $decodedResponse = json_decode($response);
        file_put_contents("../temp/$db/mobilePayOnBoarding.txt", $response);
        if($decodedResponse !== null && isset($decodedResponse->posIds)){
            $posId = $decodedResponse->posIds[0];
            $response = json_encode(["posId" => $posId]);
            file_put_contents("../temp/$db/newPosId.txt", $posId);
            $success = true;
        }else{
            $attempt++;
        }
    }elseif ($httpcode == 500) {
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
    global $db;
    file_put_contents("../temp/$db/mobilePay.txt", $response);
    header("location: pos_ordre.php?id=$id&godkendt=afbrudt");
}else{
    // get timestamp
    $posId = json_decode($response)->posId;

}

function initiatePayment($token, $data) {
    global $modtaget, $db;
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
        $decodedResponse = json_decode($response);
        if($decodedResponse !== null && isset($decodedResponse->code)){
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://api.mobilepay.dk/pos/v10/payments?posId='.$data["posId"]);
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
            $decodedResponse = json_decode($response);
            file_put_contents("../temp/$db/mobilePayInitiatePayment.txt", $response);

            $paymentId = $decodedResponse->paymentIds[0];

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
            
            $attempt++;
        }elseif ($httpcode == 500) {
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
        global $db;
        file_put_contents("../temp/$db/paymentId.txt", $response);
        header("location: pos_ordre.php?id=$id&godkendt=afbrudt");
    }
    file_put_contents("../temp/$db/paymentId.txt", $response);
    return $paymentId = json_decode($response)->paymentId;
}

function offBoarding($posId, $token){
    // Offboarding
    global $id;
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
        if ($httpcode == 500) {
            echo "Internal Server Error occurred, retrying...\n";
            $attempt++;
        } elseif (curl_errno($ch) == CURLE_OPERATION_TIMEDOUT) {
            echo "Timeout occurred, retrying...\n";
            $attempt++;
        } elseif (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
            break;
        } elseif($httpcode == 200) {
            $success = true;
        }
    }

    if (!$success) {
        global $db;
        file_put_contents("../temp/$db/mobilePayOffBoarding.txt", $response);
    }else{
        file_put_contents("../temp/$db/mobilePayOffBoarding.txt", $response);
        db_modify("DELETE FROM settings WHERE var_name = 'posId'", __FILE__ . " linje " . __LINE__);
    }
}

echo "modtaget: $modtaget<br>";

// Initiate payment
$data = array(
    "posId" => $posId,
    "orderId" => $id,
    "amount" => $modtaget,
    "currencyCode" => "DKK",
    "plannedCaptureDelay" => "None"
);

$paymentId = initiatePayment($token, $data);