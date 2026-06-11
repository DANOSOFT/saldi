<?php
#paymentLink
function paymentLink($id){
	global $db;
	$query = db_select("SELECT sum, moms FROM ordrer WHERE id = $id",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($query);
	$sum = $r['sum'];
	$moms = $r['moms'];
	$sum = str_replace(",",".",$sum);
	$moms = str_replace(",",".",$moms);
	$amount = $sum + $moms;
	$amount = $amount * 100;
	$apiKey = "vibrant_pos.47e4a2de-668b-4479-a765-9c56a4474e10.3GJrTmw~-jrY..AoBCx.7Dq2n1cuvk2c";
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, 'https://pos.api.vibrant.app//pos/v1/payment_link');
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
	file_put_contents("../temp/$db/paymentIntentId.txt", $paymentIntentId."\n", FILE_APPEND);
	$betalingsLink = $result->vibrantUrl;
	return "<a href='$betalingsLink' style='text-decoration: none; font-weight: 700; padding-top: 0.5rem; padding-bottom: 0.5rem; padding-left: 1rem; padding-right: 1rem; background-color: rgb(59 130 246); color: #fff; border-radius: 0.25rem; text-align: center;'>Betal her</a>";
}
?>
