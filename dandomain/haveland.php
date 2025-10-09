<?php
// Include the Saldi info
include "saldiinfo.php";
if(isset($_GET["put_new_orders"])){
    // 2 week ago
    $weekAgo = date('Y-m-d', strtotime('-1 days'));

    // tommorow
    $now = date('Y-m-d', strtotime('+1 day'));

    /* file_put_contents("return.txt", "Week ago: " . $weekAgo . " Now: " . $now . "\n", FILE_APPEND); */
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.direkteimport.dk/admin/WebAPI/v2/orders?start=$weekAgo&end=$now"); // Replace with your actual API endpoint
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
    ));

    // Set up basic authentication
    $apiKey = 'c1047662-d8ad-41ce-a61e-c8942ba8e521'; // Replace with your actual API key
    curl_setopt($ch, CURLOPT_USERPWD, ':' . $apiKey);

    // Execute cURL request
    $callback = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log(curl_error($ch));
    }
    curl_close($ch);

    // Decode the JSON response
    $data = json_decode($callback, true);

    if (!$data || !isset($data['items'])) {
        echo "No orders found.";
        exit;
    }

    /* file_put_contents("return.txt", "Orders found: " . count($data['items']) . "\n", FILE_APPEND); */
    /* file_put_contents("return.json", json_encode($data) . "\n", FILE_APPEND); */

    // Loop through each order item
    foreach ($data['items'] as $order) {
        
        // check if order is paid
        if($order["incomplete"]){
            continue;
        }
        $shopOrderId = str_pad($order['id'], 8, "0", STR_PAD_RIGHT);
		// check if id is in ids.txt
		if(file_exists("ids.txt")){
			$ids = file_get_contents("ids.txt");
			if(strpos($ids, $shopOrderId . ";") !== false){
				continue;
			}
		}
        // Extract and convert values
        $vatPercentage = floatval($order['vatPercentage']);
        $totalPrice = floatval($order['totalPrice']);
        $nettoSum = $totalPrice / (($vatPercentage / 100) + 1);
        $vatPrice = $totalPrice - $nettoSum;

        // Get card type if available
        $cardType = isset($order['paymentCardType']) ? $order['paymentCardType'] : '';

        // Start constructing the URL
        $url = "action=insert_shop_order";
        $url .= "&db=" . urlencode($db);
        $url .= "&key=" . urlencode($api_key);
        $url .= "&saldiuser=" . urlencode($saldiuser);

        // Order information
        $customerId = str_pad($order['customerInfo']['id'], 8, "0", STR_PAD_RIGHT);
        $url .= "&shop_ordre_id=" . urlencode($shopOrderId);
        $url .= "&shop_addr_id=" . urlencode($customerId);
        $url .= "&firmanavn=" . urlencode($order['customerInfo']['name']);
        $url .= "&addr1=" . urlencode($order['customerInfo']['address']);
        $url .= "&postnr=" . urlencode($order['customerInfo']['zipCode']);
        $url .= "&bynavn=" . urlencode($order['customerInfo']['city']);
        $url .= "&land=" . urlencode($order['customerInfo']['country']);
        $url .= "&saldi_kontonr=" . urlencode($order['customerInfo']['phone']);
        
        // Contact information
        $phone = !empty($order['customerInfo']['phone']) ? $order['customerInfo']['phone'] : '';
        $url .= "&tlf=" . urlencode($phone);
        $url .= "&email=" . urlencode($order['customerInfo']['email']);
        $url .= "&ref=Internet";
        $url .= "&shop_status=" . urlencode($order['orderStateInfo']['name']);
        $url .= "&nettosum=" . urlencode($nettoSum);
        $url .= "&momssum=" . urlencode($vatPrice);

        $contactPerson = !empty($order['customerInfo']['attention']) ? $order['customerInfo']['attention'] : $order['customerInfo']['name'];
        $url .= "&kontakt=" . urlencode($contactPerson);

        // Delivery information
        $deliveryInfo = $order['deliveryInfo'];
        $url .= "&lev_firmanavn=" . urlencode($deliveryInfo['name']);
        $url .= "&lev_addr1=" . urlencode($deliveryInfo['address']);
        $url .= "&lev_postnr=" . urlencode($deliveryInfo['zipCode']);
        $url .= "&lev_bynavn=" . urlencode($deliveryInfo['city']);
        $url .= "&lev_land=" . urlencode($deliveryInfo['country']);
        $levKontakt = !empty($deliveryInfo['attention']) ? $deliveryInfo['attention'] : $deliveryInfo['name'];
        $url .= "&lev_kontakt=" . urlencode($levKontakt);

        // Payment and order details
        $url .= "&betalingsbet=Kreditkort";
        $url .= "&betalingsdage=1";
        $url .= "&shop_fakturanr=" . urlencode($order['id']);
        $url .= "&ordredate=" . urlencode($order['createdDate']);
        $url .= "&lev_date="; // Add delivery date if available
        $url .= "&momssats=" . urlencode($vatPercentage);
        $url .= "&valuta=" . urlencode($order['currencyCode']);
        $url .= "&valutakurs=100"; // Assuming exchange rate is 100
        $url .= "&saldi_kontonr=" . urlencode($phone);

        // if vatRegnumber is empty, then it is a private customer
        $vatRegnumber = !empty($order['customerInfo']['vatRegNumber']) ? $order['customerInfo']['vatRegNumber'] : '';
        $url .= "&cvr=" . urlencode($vatRegnumber);
        $url .= "&gruppe=1";
        $url .= "&afd=7";
        $url .= "&projekt=";
        $url .= "&ekstra1=" . urlencode($order['payInfo']['payMethodName']);
        $url .= "&ekstra2=" . urlencode($totalPrice);
        $comment = isset($order['comment']) ? $order['comment'] : '';
        $url .= "&notes=" . urlencode($comment);
        $url .= "&ekstra3=kontant";
        $url .= "&ekstra4=0.00";
        $url .= "&ekstra5=7";
        $betalingsId = isset($order['transactionNumber']) ? $order['transactionNumber'] : '';
        $url .= "&betalings_id=" . urlencode($betalingsId);
        $url .= "&ean=" . urlencode($order['customerInfo']['ean']);

        // VAT exemption
        if ($vatPercentage != 0 && $vatPercentage != '') {
            $url .= "&momsfri=";
        } else {
            $url .= "&momsfri=on";
        }

        /* file_put_contents("saldi-text.txt", $url . "\n", FILE_APPEND); */

        // Send order to Saldi
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $callback = curl_exec($ch);
        if (curl_errno($ch)) {
            error_logs(curl_errno($ch));
        }
        curl_close($ch);
        
        /* file_put_contents("saldi-text.txt", "\n\nCallback: " . $callback . "\n\n\n", FILE_APPEND); */

        // callback check to see if order is already in saldi
        $callback = str_replace('"','',$callback);
        intval($callback) ? $saldi_ordre_id = (int)$callback : $saldi_ordre_id = 0;
        if ($saldi_ordre_id !== 0 && $saldi_ordre_id !== "") {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.direkteimport.dk/admin/WebAPI/v2/orders/$order[id]/lines"); // Replace with your actual API endpoint
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
            ));
            curl_setopt($ch, CURLOPT_USERPWD, ':' . $apiKey);
            $orderLines = curl_exec($ch);
            if (curl_errno($ch)) {
                error_log(curl_error($ch));
            }
            curl_close($ch);
            $orderLines = json_decode($orderLines, true);
            $orderLines["saldi_ordre_id"] = $saldi_ordre_id;
            /* file_put_contents("ordreLines.json", json_encode($orderLines) . "\n", FILE_APPEND); */

            // if its not in saldi add orderlines
            foreach ($orderLines["items"] as $orderLine) { 
                $urltxt = "action=insert_shop_orderline";
                $urltxt .= "&db=" . urlencode($db);
                $urltxt .= "&key=" . urlencode($api_key);
                $urltxt .= "&saldiuser=" . urlencode($saldiuser);
                $urltxt .= "&saldi_ordre_id=" . urlencode($saldi_ordre_id);
            
                $urltxt .= "&varenr=" . urlencode($orderLine['productNumber']);
                $urltxt .= "&beskrivelse=" . urlencode($orderLine['productName']);
                $urltxt .= "&antal=" . urlencode($orderLine['quantity']);
                $urltxt .= "&lager=10";
                if($orderLine["unitPriceBeforeSpecialOffer"] > $orderLine["unitPrice"]){
                    $urltxt .= "&pris=" . urlencode($orderLine['unitPriceBeforeSpecialOffer']);
                } else {
                    $urltxt .= "&pris=" . urlencode($orderLine['unitPrice']);
                }
            
                // Handle discount if available
                /* if($order["shippingInfo"]["shippingMethodId"] == 67){
                    if (isset($orderLine['unitPriceBeforeSpecialOffer']) && $orderLine['unitPriceBeforeSpecialOffer'] > $orderLine['unitPrice']) {
                        $discountPercentage = (1 - ($orderLine['unitPrice'] / $orderLine['unitPriceBeforeSpecialOffer'])) * 100;
                        $discountAmount = $orderLine['unitPriceBeforeSpecialOffer'] - $orderLine['unitPrice'];
                        $urltxt .= "&rabat=" . urlencode($discountPercentage+15);
                    } else {
                        $urltxt .= "&rabat=15";
                    }
                }else{ */
                    if (isset($orderLine['unitPriceBeforeSpecialOffer']) && $orderLine['unitPriceBeforeSpecialOffer'] > $orderLine['unitPrice']) {
                        $discountPercentage = (1 - ($orderLine['unitPrice'] / $orderLine['unitPriceBeforeSpecialOffer'])) * 100;
                        $discountAmount = $orderLine['unitPriceBeforeSpecialOffer'] - $orderLine['unitPrice'];
                        $urltxt .= "&rabat=" . urlencode($discountPercentage);
                    } else {
                        $urltxt .= "&rabat=0";
                    }
                /* } */
            
                $urltxt .= "&stregkode=";
                $urltxt .= "&variant=" . urlencode($orderLine['variant']);
            
                // Determine VAT status
                $vatPercentage = floatval($orderLine['vatPercentage']);
                if ($vatPercentage == 0 || $vatPercentage == '') {
                    $urltxt .= "&momsfri=on";
                } else {
                    $urltxt .= "&momsfri=";
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $serverurl . "/rest_api.php?" . $urltxt);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $res = curl_exec($ch);
                if (curl_errno($ch)) {
                    error_log('Curl error: ' . curl_error($ch));
                }
                curl_close($ch);

                /* file_put_contents("saldi-text.txt", $urltxt . "\n", FILE_APPEND); */
            }

            // add delivery cost
            $urltxt="action=insert_shop_orderline&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&saldi_ordre_id=".$saldi_ordre_id;
            $urltxt.="&varenr=FR";
            $urltxt.="&beskrivelse=".urlencode($order["shippingInfo"]["shippingMethodName"]);
            $urltxt.="&antal=1";
            ($order["shippingInfo"]["vatIncludedInFee"]) ? $price = $order["shippingInfo"]["fee"] * 0.80 : $price = $order["shippingInfo"]["fee"];
            $urltxt.= ($order["shippingInfo"]["fee"] == 0) ? "&pris=0.0" : "&pris=".urlencode($price);
            $urltxt.="&rabat=0&stregkode=&variant=&varegruppe=2";
            $urltxt.= ($order["shippingInfo"]["vatIncludedInFee"]) ? "&momsfri=" : "&momsfri=on";
            /* file_put_contents("saldi-text.txt", $urltxt . "\n", FILE_APPEND); */
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$urltxt);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            if (curl_errno($ch)) {
                error_logs(curl_errno($ch));
            }
            curl_close($ch);

            // add customer comment
            if($order['customerComment'] != ""){
                $urltxt="action=insert_shop_orderline&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&saldi_ordre_id=".$saldi_ordre_id;
                $urltxt.="&varenr=kommentar";
                $urltxt.="&beskrivelse=".urlencode($order['customerComment']);
                $urltxt.="&antal=1";
                $urltxt.="&pris=0.0";
                $urltxt.="&rabat=0&stregkode=&variant=&varegruppe=0";
                $urltxt.="&momsfri=on";
                /* file_put_contents("saldi-text.txt", $urltxt . "\n", FILE_APPEND); */
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$urltxt);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $res = curl_exec($ch);
                if (curl_errno($ch)) {
                    error_logs(curl_errno($ch));
                }
                curl_close($ch);
            }

            // Do invoice
            $urltxt="action=fakturer_ordre&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&saldi_ordre_id=".$saldi_ordre_id."&udskriv_til=&pos_betaling=on";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$urltxt);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            if (curl_errno($ch)) {
                error_logs(curl_errno($ch));
            }
            curl_close($ch);
            /* file_put_contents("saldi-text.txt", $res . "\n", FILE_APPEND); */
        }
        // insert id in ids.txt
        file_put_contents("ids.txt", $shopOrderId . ";", FILE_APPEND);

    }
}elseif(isset($_GET["stock"])){

    // Update stock on shop
    $stock = (int)$_GET["totalStock"];
    $sku = $_GET["itemNo"];
    /* file_put_contents("return.txt", "ItemNr: " . $_GET["itemNo"] . "\nStock: " . $_GET["totalStock"] . "\n", FILE_APPEND); */
    echo $stock;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.direkteimport.dk/admin/WebAPI/v2/products/$sku"); // Replace with your actual API endpoint
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
    ));

    // Set up basic authentication
    $apiKey = 'c1047662-d8ad-41ce-a61e-c8942ba8e521';
    curl_setopt($ch, CURLOPT_USERPWD, ':' . $apiKey);
    $product = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log(curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $product = json_decode($product, true);

    $stockCount = $product['stockCount'];
      // Calculate the difference between the new stock and the current stock
    $stockDifference = $stock - $stockCount;

    if($stockDifference == 0){
        // No change in stock, exit
        exit;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.direkteimport.dk/admin/WebAPI/v2/products/$sku/changeStock"); // Replace with your actual API endpoint
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('change' => $stockDifference)));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'X-HTTP-Method-Override: PATCH'
    ));

    // Set up basic authentication
    $apiKey = 'c1047662-d8ad-41ce-a61e-c8942ba8e521';
    curl_setopt($ch, CURLOPT_USERPWD, ':' . $apiKey);

    // Execute cURL request
    $callback = curl_exec($ch);
    $callback = json_decode($callback, true);
    if (curl_errno($ch)) {
        error_log(curl_error($ch));
    }
    curl_close($ch);

    file_put_contents("return.json", json_encode(["stock" => $stock, "stockCount" => $stockCount, "return" => $callback]) . "\n", FILE_APPEND);

}

if(isset($_GET["salesPrice"])){
    // Update price on shop
    $sku = $_GET["itemNo"];
    $price = $_GET["salesPrice"] * 1.25;
    $discountType = $_GET["discountType"];
    $discount = $price - (int)$_GET["discount"];
    $webFragt = $_GET["webFragt"];
    $retailPrice = $_GET["retailPrice"];
    $barcodeNumber = $_GET["barcode"];
    /* file_put_contents("return.txt", "Price: " . $_GET["salesPrice"] . "\nDiscount: " . $_GET["discount"] . "\n WebFragt: " . $webFragt . "\n salesPrice: " . $price . "\n Barcode: " . $barcodeNumber . "\n\n", FILE_APPEND); */
    
    if($discountType != "" && $discountType == "percent"){
        $discountType = "p";
    }else if($discountType != "" && $discountType == "amount"){
        $discountType = "a";
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.direkteimport.dk/admin/WebAPI/v2/products/$sku/prices"); // Replace with your actual API endpoint
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('specialOfferPeriodId' => 'P1', 'unitPrice' => $price, 'specialOfferPrice' => $discount, "quantity" => 1)));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
    ));

    // Set up basic authentication
    $apiKey = 'c1047662-d8ad-41ce-a61e-c8942ba8e521';
    curl_setopt($ch, CURLOPT_USERPWD, ':' . $apiKey);

    // Execute cURL request
    $callback = curl_exec($ch);

    // get http response code
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    /* file_put_contents("return.txt", "HTTP Code: " . $httpcode . "\n", FILE_APPEND); */
    if (curl_errno($ch)) {
        error_log(curl_error($ch));
    }
    curl_close($ch);
    /* file_put_contents("return.json", $callback . "\n", FILE_APPEND); */
    if($httpcode == 404){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.direkteimport.dk/admin/WebAPI/v2/products/$sku/prices"); // Replace with your actual API endpoint
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('specialOfferPeriodId' => 'P1', 'unitPrice' => $price, 'specialOfferPrice' => $discount, "quantity" => 1)));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
        ));

        // Set up basic authentication
        $apiKey = 'c1047662-d8ad-41ce-a61e-c8942ba8e521';
        curl_setopt($ch, CURLOPT_USERPWD, ':' . $apiKey);

        // Execute cURL request
        $callback = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log(curl_error($ch));
        }
        curl_close($ch);
        /* file_put_contents("return.json", $callback . "\n", FILE_APPEND); */
    }

    // use patch to update costPrice
    $costPrice = $_GET["costPrice"];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.direkteimport.dk/admin/WebAPI/v2/products/$sku"); // Replace with your actual API endpoint
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('costPrice' => $costPrice, 'weight' => $webFragt, "barcodeNumber" => $barcodeNumber)));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'X-HTTP-Method-Override: PATCH'
    ));

    // Set up basic authentication
    $apiKey = 'c1047662-d8ad-41ce-a61e-c8942ba8e521';
    curl_setopt($ch, CURLOPT_USERPWD, ':' . $apiKey);

    // Execute cURL request
    $callback = curl_exec($ch);
    /* file_put_contents("test.json", $callback . "\n", FILE_APPEND); */
    if (curl_errno($ch)) {
        error_log(curl_error($ch));
    }
    curl_close($ch);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.direkteimport.dk/admin/WebAPI/v2/products/$sku/sites/26/settings"); // Replace with your actual API endpoint
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('retailSalesPrice' => $retailPrice)));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'X-HTTP-Method-Override: PATCH'
    ));

    // Set up basic authentication
    $apiKey = 'c1047662-d8ad-41ce-a61e-c8942ba8e521';
    curl_setopt($ch, CURLOPT_USERPWD, ':' . $apiKey);

    // Execute cURL request
    $callback = curl_exec($ch);
    /* file_put_contents("test.json", $callback . "\n", FILE_APPEND); */
    if (curl_errno($ch)) {
        error_log(curl_error($ch));
    }
    curl_close($ch);
}

if(isset($_GET["costPrice"])){
        // use patch to update costPrice
        $costPrice = $_GET["costPrice"];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.direkteimport.dk/admin/WebAPI/v2/products/$sku"); // Replace with your actual API endpoint
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('costPrice' => $costPrice, 'weight' => $webFragt, "barcodeNumber" => $barcodeNumber)));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'X-HTTP-Method-Override: PATCH'
        ));
    
        // Set up basic authentication
        $apiKey = 'c1047662-d8ad-41ce-a61e-c8942ba8e521';
        curl_setopt($ch, CURLOPT_USERPWD, ':' . $apiKey);
    
        // Execute cURL request
        $callback = curl_exec($ch);
        /* file_put_contents("test.json", $callback . "\n", FILE_APPEND); */
        if (curl_errno($ch)) {
            error_log(curl_error($ch));
        }
        curl_close($ch);
}