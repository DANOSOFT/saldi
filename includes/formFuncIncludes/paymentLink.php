<?php
// --- includes/formFuncIncludes/paymentLink.php --- Vibrant payment link for the $..._betalingslink form variable ---
// 20261006 CL/LH SST-842: Use the tenant's own Vibrant key (settings.vibrant_auth) instead of a key hardcoded
//                 in the public repository; render nothing when no key is configured or the call fails.

/**
 * @return string HTML "Betal her" link, or '' when no Vibrant key is configured or no link was issued.
 */
function paymentLink($id){
	global $db;
	$r = db_fetch_array(db_select("select var_value from settings where var_name = 'vibrant_auth'", __FILE__ . " linje " . __LINE__));
	$apiKey = trim((string)($r['var_value'] ?? ''));
	if ($apiKey === '') {
		return '';
	}
	$id = (int)$id;
	$query = db_select("SELECT sum, moms FROM ordrer WHERE id = $id",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($query);
	$sum = $r['sum'];
	$moms = $r['moms'];
	$sum = str_replace(",",".",$sum);
	$moms = str_replace(",",".",$moms);
	$amount = $sum + $moms;
	$amount = $amount * 100;
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, 'https://pos.api.vibrant.app/pos/v1/payment_link');
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
	if (!is_object($result) || empty($result->vibrantUrl)) {
		return '';
	}
	$paymentIntentId = $result->paymentIntentId ?? '';
	file_put_contents("../temp/$db/paymentIntentId.txt", $paymentIntentId."\n", FILE_APPEND);
	$betalingsLink = htmlspecialchars($result->vibrantUrl, ENT_QUOTES, 'UTF-8');
	return "<a href='$betalingsLink' style='text-decoration: none; font-weight: 700; padding-top: 0.5rem; padding-bottom: 0.5rem; padding-left: 1rem; padding-right: 1rem; background-color: rgb(59 130 246); color: #fff; border-radius: 0.25rem; text-align: center;'>Betal her</a>";
}
?>
