<?php
if(isset($_GET["put_new_orders"])){
$saldiuser='api'; #En bruger i har i saldi uden nogen rettigheder
$api_key='4M1SlprEv82hhtl2KSfCFOs4BzLYgAdUD'; #Findes under Indstillinger ->  Diverse -> API
$serverurl="https://ssl12.saldi.dk/pblm/api"; #Findes under Indstillinger ->  Diverse -> API
$db='test_4';#' #Findes under Indstillinger ->  Diverse -> API 

 // Build the Saldi API request URL with order information
 $url = "action=insert_shop_order";
 $url .= "&db=" . urlencode($db);
 $url .= "&key=" . urlencode($api_key);
 $url .= "&saldiuser=" . urlencode($saldiuser);
 
 // Add customer details
 $url .= "&firmanavn=" . urlencode("Patrick's Test Shop");
 $url .= "&addr1=" . urlencode("Testvej 1");
 $url .= "&postnr=" . urlencode("1234");
 $url .= "&bynavn=" . urlencode("Testby");
 $url .= "&land=" . urlencode("Danmark");
 $url .= "&tlf=" . urlencode("12345678");
 $url .= "&email=" . urlencode("pblm@saldi.dk");
 $url .= "&gruppe=" . urlencode(1); // Example customer group
 
 // Add order details
 $url .= "&shop_ordre_id=" . urlencode("3485745"); // Example shop order ID
 $url .= "&shop_status=" . urlencode("New Order");
 $url .= "&nettosum=" . urlencode("104.00"); // Example net amount
 $url .= "&momssum=" . urlencode("25.00"); // Example VAT amount
 $url .= "&ordredate=" . urlencode(date('Y-m-d H:i:s')); // Current date and time
 
 // Add delivery information
 $url .= "&lev_firmanavn=" . urlencode("Patrick's Test Delivery");
 $url .= "&lev_addr1=" . urlencode("Leveringsvej 2");
 $url .= "&lev_postnr=" . urlencode("5678");
 $url .= "&lev_bynavn=" . urlencode("Leveringsby");
 $url .= "&lev_land=" . urlencode("Danmark");
 
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
// Remove quotes and whitespace before converting to integer
$saldi_ordre_id = intval(trim($response, " \t\n\r\0\x0B\""));
// Add each order line to Saldi

	$urltxt = "action=insert_shop_orderline";
	$urltxt .= "&db=" . urlencode($db);
	$urltxt .= "&key=" . urlencode($api_key);
	$urltxt .= "&saldiuser=" . urlencode($saldiuser);
	$urltxt .= "&saldi_ordre_id=" . urlencode($saldi_ordre_id);
	
	// Add product details
	$urltxt .= "&varenr=" . urlencode("020350");
	$urltxt .= "&beskrivelse=" . urlencode("2 Phono til 2 x XLR Hun kabel - 1,5 m");
	$urltxt .= "&antal=" . urlencode(2);
	$urltxt .= "&pris=" . urlencode(65.00); // Unit price
	
	// Handle discounts if applicable
	/* if (isset($orderLine['unitPriceBeforeSpecialOffer']) && $orderLine['unitPriceBeforeSpecialOffer'] > $orderLine['unitPrice']) {
		$discountPercentage = (1 - ($orderLine['unitPrice'] / $orderLine['unitPriceBeforeSpecialOffer'])) * 100;
		$urltxt .= "&rabat=" . urlencode($discountPercentage);
	} else {
		$urltxt .= "&rabat=0";
	} */
	
	// Add additional product details
	/* $urltxt .= "&variant=" . urlencode($orderLine['variant']); */
	$urltxt .= "&varegruppe=2"; // Product group ID
	
	// Handle VAT
	/* $vatPercentage = floatval($orderLine['vatPercentage']);
	if ($vatPercentage == 0) {
		$urltxt .= "&momsfri=on";
	} else {
		$urltxt .= "&momsfri=";
	} */
	$urltxt .= "&momsfri="; // Assuming VAT is applicable
	
	// Send the request to Saldi
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $serverurl . "/rest_api.php?" . $urltxt);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$res = curl_exec($ch);
	curl_close($ch);


// Generate invoice from order
/* $urltxt = "action=fakturer_ordre";
$urltxt .= "&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser);
$urltxt .= "&saldi_ordre_id=".$saldi_ordre_id;
$urltxt .= "&udskriv_til=&pos_betaling=on";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$urltxt);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch); */
}
if(isset($_GET["update_price"])){
	$salesPrice = $_GET["salesPrice"];
	$discountType = $_GET["discountType"];
	$discount = $_GET["discount"];
	$itemNo = $_GET["itemNo"];
	$costPrice = $_GET["costPrice"];
	$retailPrice = $_GET["retailPrice"];
	$webFragt = $_GET["webFragt"];
	$barcode = $_GET["barcode"];
	file_put_contents("price.txt", "testApi1: SalesPrice: ".$salesPrice." DiscountType: ".$discountType." Discount: ".$discount." ItemNo: ".$itemNo." CostPrice: ".$costPrice." RetailPrice: ".$retailPrice." WebFragt: ".$webFragt." Barcode: ".$barcode."\n", FILE_APPEND); // Log sales price for debugging
}

if(isset($_GET["stock"])){
	$stock = $_GET["stock"];
	$stockno = $_GET["stockno"];
	$stockvalue = $_GET["stockvalue"];
	$update_stock = $_GET["update_stock"];
	file_put_contents("price.txt", "testApi1: Stock: ".$stock." StockNo: ".$stockno." StockValue: ".$stockvalue." UpdateStock: ".$update_stock."\n", FILE_APPEND); // Log stock details for debugging
}

if(isset($_GET["costPrice"])){
	$costPrice = $_GET["costPrice"];
	$itemNo = $_GET["sku"];
	file_put_contents("price.txt", "testApi1: CostPrice: ".$costPrice." ItemNo: ".$itemNo."\n", FILE_APPEND); // Log cost price for debugging
}