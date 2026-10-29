<?php
// 20261006 CL/LH SST-842: Poll the production API (same key/host as the payment link) and URL-encode the intent id.
// 20261029 CL/MMR SST-840: The account name from the request is validated before connecting.
require_once "../includes/connect.php";
include_once(__DIR__ . "/../includes/publicTenantDb.php");

$customerData = json_decode(file_get_contents("php://input"), true);

$connection = db_connect($sqhost, $squser, $sqpass, public_tenant_db($customerData["db"] ?? ''));

$query = db_select("SELECT apikey FROM rentalpayment WHERE id = 1", __FILE__ . " linje " . __LINE__);
$res = db_fetch_array($query);
$apiKey = $res["apikey"];

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "https://pos.api.vibrant.app/pos/v1/payment_intents/" . rawurlencode((string)($_GET["id"] ?? "")));
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