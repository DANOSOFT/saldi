<?php
// 20261006 CL/LH SST-842: Use the tenant's own Vibrant key against the production API; the hardcoded sandbox key is gone; booking id int-cast.
// 20261023 CL/LH SST-842: Read rentalpayment.apikey as vibrantPaymentIntent.php does (table name, no trim), so the link and the status poll use the same key.
// 20261029 CL/MMR SST-840: The account name from the request is validated before connecting; the order id is int-cast; the include uses __DIR__.
// 20261029 CL/MMR SST-841: The payment intent Vibrant returns is stored on the booking, so only that payment can approve it;
//                 a retry gets the link already bound to the booking.
//                 SALDI_VIBRANT_API_BASE may point the call at a local mock in tests.
require_once __DIR__ . "/../includes/connect.php";
include_once(__DIR__ . "/../includes/publicTenantDb.php");
include_once(__DIR__ . "/../includes/vibrantPayment.php");
$customerData = json_decode(file_get_contents("php://input"), true);

$db = public_tenant_db($customerData["db"] ?? '');
$connection = db_connect($sqhost, $squser, $sqpass, $db);
rental_payment_column($db);

$bookingId = (int)($customerData["id"] ?? 0);
$query = db_select("SELECT * FROM rentalperiod WHERE id = $bookingId", __FILE__ . " linje " . __LINE__);
$res = db_fetch_array($query);
$orderId = $res ? (int)$res["order_id"] : 0;

if($orderId == null || $orderId == 0) {
    echo json_encode(["error" => "No order id found", "id" => $customerData["id"]]);
    exit;
}
if ((string)$res["payment_id"] !== '' && (string)$res["payment_url"] !== '') {
    // one payment per booking: a retry or reload gets the link the server already bound to it
    echo json_encode(["url" => $res["payment_url"], "id" => $res["payment_id"]]);
    exit;
}

$query = db_select("SELECT * FROM ordrer WHERE id = '$orderId'", __FILE__ . " linje " . __LINE__);
$res = db_fetch_array($query);
$amount = $res["sum"] * 1.25 * 100;

$query = db_select("SELECT apikey FROM rentalpayment WHERE id = 1", __FILE__ . " linje " . __LINE__);
$res = db_fetch_array($query);
$apiKey = (string)($res["apikey"] ?? '');
if ($apiKey === '') {
    echo json_encode(["error" => "No Vibrant API key configured", "id" => $customerData["id"]]);
    exit;
}

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, rtrim(getenv('SALDI_VIBRANT_API_BASE') ?: 'https://pos.api.vibrant.app', '/') . '/pos/v1/payment_link');
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
if (is_string($paymentIntentId) && $paymentIntentId !== '' && is_string($betalingsLink) && $betalingsLink !== '') {
	$qtxt = "UPDATE rentalperiod SET payment_id = '" . db_escape_string($paymentIntentId) . "', payment_url = '" . db_escape_string($betalingsLink) . "'";
	$qtxt.= " WHERE id = $bookingId AND (payment_id IS NULL OR payment_id = '')";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$bound = db_fetch_array(db_select("SELECT payment_id, payment_url FROM rentalperiod WHERE id = $bookingId", __FILE__ . " linje " . __LINE__));
	if ($bound && (string)$bound["payment_id"] !== '') {
		// a concurrent call bound its payment first: hand out that one, never a link the server will not check
		$paymentIntentId = $bound["payment_id"];
		$betalingsLink = $bound["payment_url"];
	}
}
echo json_encode(["url" => $betalingsLink, "id" => $paymentIntentId]);