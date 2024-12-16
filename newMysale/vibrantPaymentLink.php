<?php
require_once "../includes/connect.php";
$customerData = json_decode(file_get_contents("php://input"), true);

$connection = db_connect($sqhost, $squser, $sqpass, $customerData["db"]);

$query = db_select("SELECT * FROM rentalperiod WHERE id = $customerData[id]", __FILE__ . " linje " . __LINE__);
$res = db_fetch_array($query);
$orderId = $res["order_id"];

if($orderId == null || $orderId == 0) {
    echo json_encode(["error" => "No order id found", "id" => $customerData["id"]]);
    exit;
}

$query = db_select("SELECT * FROM ordrer WHERE id = '$orderId'", __FILE__ . " linje " . __LINE__);
$res = db_fetch_array($query);
$amount = $res["sum"] * 1.25 * 100;

$query = db_select("SELECT apikey FROM rentalPayment WHERE id = 1", __FILE__ . " linje " . __LINE__);
$res = db_fetch_array($query);
$apiKey = $res["apikey"];

$apiKey = "vibrant_pos.1065f26b-baf7-4ff1-9d36-f6ba85590311.8K49ho/SBjVWXS8b9_U/2XgA~UuSG-11";
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'https://pos-api.sandbox.vibrant.app/pos/v1/payment_link');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array("amount" => $amount)));

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
$paymentIntentId = $result->paymentIntentId;
$betalingsLink = $result->vibrantUrl;
echo json_encode(["url" => $betalingsLink, "id" => $paymentIntentId]);