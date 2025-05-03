<?php
require_once "../includes/connect.php";

$customerData = json_decode(file_get_contents("php://input"), true);

$connection = db_connect($sqhost, $squser, $sqpass, $customerData["db"]);

$query = db_select("SELECT apikey FROM rentalpayment WHERE id = 1", __FILE__ . " linje " . __LINE__);
$res = db_fetch_array($query);
$apiKey = $res["apikey"];

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "https://pos-api.sandbox.vibrant.app/pos/v1/payment_intents/$_GET[id]");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$headers = array();
$headers[] = 'Accept: application/json';
$headers[] = 'Apikey: ' . $apiKey;
$headers[] = 'Content-Type: application/json';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
}
curl_close($ch);
$result = json_decode($result);
$paymentIntentId = $result->status;
echo json_encode(["state" => $paymentIntentId]);