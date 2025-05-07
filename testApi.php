<?php

$saldiuser='internet'; #En bruger i har i saldi uden nogen rettigheder
$api_key=''; #Findes under Indstillinger ->  Diverse -> API
$serverurl=""; #Findes under Indstillinger ->  Diverse -> API
$db='';#' #Findes under Indstillinger ->  Diverse -> API 

 // Build the Saldi API request URL with order information
 $url = "action=insert_shop_order";
 $url .= "&db=" . urlencode($db);
 $url .= "&key=" . urlencode($api_key);
 $url .= "&saldiuser=" . urlencode($saldiuser);
 
 // Add customer details
 $url .= "&firmanavn=" . urlencode($order['customerInfo']['name']);
 $url .= "&addr1=" . urlencode($order['customerInfo']['address']);
 $url .= "&postnr=" . urlencode($order['customerInfo']['zipCode']);
 $url .= "&bynavn=" . urlencode($order['customerInfo']['city']);
 $url .= "&land=" . urlencode($order['customerInfo']['country']);
 $url .= "&tlf=" . urlencode($order['customerInfo']['phone']);
 $url .= "&email=" . urlencode($order['customerInfo']['email']);
 
 // Add order details
 $url .= "&shop_ordre_id=" . urlencode($order['id']);
 $url .= "&shop_status=" . urlencode($order['orderStateInfo']['name']);
 $url .= "&nettosum=" . urlencode($nettoSum);
 $url .= "&momssum=" . urlencode($vatPrice);
 $url .= "&ordredate=" . urlencode($order['createdDate']);
 
 // Add delivery information
 $url .= "&lev_firmanavn=" . urlencode($order['deliveryInfo']['name']);
 $url .= "&lev_addr1=" . urlencode($order['deliveryInfo']['address']);
 $url .= "&lev_postnr=" . urlencode($order['deliveryInfo']['zipCode']);
 $url .= "&lev_bynavn=" . urlencode($order['deliveryInfo']['city']);
 $url .= "&lev_land=" . urlencode($order['deliveryInfo']['country']);
 
 // Send the request to Saldi
 $ch = curl_init();
 curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$url);
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 $response = curl_exec($ch);
 if (curl_errno($ch)) {
	 error_log(curl_error($ch));
 }
 curl_close($ch);
 
// The response should be the Saldi order ID if successful
$saldi_ordre_id = intval($response);

// Add each order line to Saldi
foreach ($orderLines["items"] as $orderLine) {
	$urltxt = "action=insert_shop_orderline";
	$urltxt .= "&db=" . urlencode($db);
	$urltxt .= "&key=" . urlencode($api_key);
	$urltxt .= "&saldiuser=" . urlencode($saldiuser);
	$urltxt .= "&saldi_ordre_id=" . urlencode($saldi_ordre_id);
	
	// Add product details
	$urltxt .= "&varenr=" . urlencode($orderLine['productNumber']);
	$urltxt .= "&beskrivelse=" . urlencode($orderLine['productName']);
	$urltxt .= "&antal=" . urlencode($orderLine['quantity']);
	$urltxt .= "&pris=" . urlencode($orderLine['unitPrice']);
	
	// Handle discounts if applicable
	if (isset($orderLine['unitPriceBeforeSpecialOffer']) && $orderLine['unitPriceBeforeSpecialOffer'] > $orderLine['unitPrice']) {
		$discountPercentage = (1 - ($orderLine['unitPrice'] / $orderLine['unitPriceBeforeSpecialOffer'])) * 100;
		$urltxt .= "&rabat=" . urlencode($discountPercentage);
	} else {
		$urltxt .= "&rabat=0";
	}
	
	// Add additional product details
	$urltxt .= "&variant=" . urlencode($orderLine['variant']);
	$urltxt .= "&varegruppe=2"; // Product group ID
	
	// Handle VAT
	$vatPercentage = floatval($orderLine['vatPercentage']);
	if ($vatPercentage == 0) {
		$urltxt .= "&momsfri=on";
	} else {
		$urltxt .= "&momsfri=";
	}
	
	// Send the request to Saldi
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $serverurl . "/rest_api.php?" . $urltxt);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$res = curl_exec($ch);
	curl_close($ch);
}

// Generate invoice from order
$urltxt = "action=fakturer_ordre";
$urltxt .= "&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser);
$urltxt .= "&saldi_ordre_id=".$saldi_ordre_id;
$urltxt .= "&udskriv_til=&pos_betaling=on";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$urltxt);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);