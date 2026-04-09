<?php
include "saldiinfoCA.php";

// Logging functions
function writeLog($message, $type = 'INFO') {
    global $db;
    $logFile = '/var/www/html/pos/temp/'.$db.'/customAudio.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function writeLogUpdate($message, $type = 'INFO') {
    global $db;
    $logFile = '/var/www/html/pos/temp/'.$db.'/customAudioChanges.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

if(isset($_GET["put_new_orders"])){
    writeLog("=== Starting order import process ===");

    // get data from service.wsdl
    try {
        $Client = new SoapClient('service.wsdl');
        $Client->Solution_Connect (array('Username' => 'Saldi@API.dk', 'Password' =>'u4rHNwP2eG7h9ja'));
        writeLog("SOAP client connected successfully");
    } catch (Exception $e) {
        writeLog("SOAP connection failed: " . $e->getMessage(), 'ERROR');
        exit;
    }

    // set dates for search critiria
    $start = date('Y-m-d', strtotime('-2 months'));
    $end = date('Y-m-d');
    writeLog("Fetching orders from $start to $end");

    // get orders
    $Client->Order_SetFields(array('Fields' => 'Status,OrderLines,Id,Customer,DateSent,DateUpdated,Vat,Currency,CustomerComment,Transactions,Payment,Total,DateDelivered,Delivery,UserId'));
    $Client->Order_SetOrderLineFields(array('Fields' => 'Id,BuyPrice,ItemNumber,Discount,Amount,ProductTitle,StockStatus,Price,ProductId,VariantId,VariantTitle'));
    $Orders = $Client->Order_GetByDate(array("Start" => $start, "End" => $end));
    
    // Normalize orders to always be an array (API returns null for no orders, object for single, array for multiple)
    $orderItems = isset($Orders->Order_GetByDateResult->item) ? $Orders->Order_GetByDateResult->item : [];
    if (!is_array($orderItems) && $orderItems !== null) {
        $orderItems = [$orderItems]; // Wrap single object in array
    } elseif ($orderItems === null) {
        $orderItems = [];
    }
    
    writeLog("Retrieved " . count($orderItems) . " orders");
    // go through orders and insert them into saldi
    
    foreach ($orderItems as $order) {
        writeLog("Processing order ID: " . $order->Id . " | Customer: " . $order->Customer->Email . " | Total: " . $order->Total . " " . $order->Currency->Iso);
        file_put_contents("Data.json", json_encode($order, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

        // Check if this is a kreditnota by looking for negative antal on order lines
        $isKreditnota = false;
        $orderLinesCheck = isset($order->OrderLines->item) ? $order->OrderLines->item : [];
        if (!is_array($orderLinesCheck) && $orderLinesCheck !== null) {
            $orderLinesCheck = [$orderLinesCheck];
        }
        foreach ($orderLinesCheck as $line) {
            if (isset($line->Amount) && $line->Amount < 0) {
                $isKreditnota = true;
                break;
            }
        }

        if($order->Status != "203"){
            writeLog("Order " . $order->Id . " is not completed (status: " . $order->Status . "), skipping");
            continue;
        }

        if ($isKreditnota) {
            writeLog("Order " . $order->Id . " detected as kreditnota (negative antal found)");
        }
        $priceWithVat = $order->Total + ($order->Total * $order->Vat);
        $vatPrice = $order->Total * $order->Vat;

        // Include delivery cost in totals so they match the sum of all order lines
        // (FRAGT is added as a separate order line later)
        $deliveryPrice = isset($order->Delivery->Price) ? abs((float)$order->Delivery->Price) : 0;
        $deliveryVat = 0;
        if ($deliveryPrice > 0 && isset($order->Delivery->Vat) && $order->Delivery->Vat) {
            $deliveryVat = $deliveryPrice * $order->Vat;
        }
        // For kreditnotas, Dandomain's Total already includes delivery (it's the full negative amount).
        // The FRAGT line is still added separately (antal=-1), so nettosum must equal Total as-is.
        // For normal orders, Total excludes delivery, so we add deliveryPrice to match items (products + FRAGT).
        if ($isKreditnota) {
            $nettosum = $order->Total;
            $momssum = $order->Total * $order->Vat;
        } else {
            $nettosum = $order->Total + $deliveryPrice;
            $momssum = $vatPrice + $deliveryVat;
        }
        
        // Extract transaction data for up to 2 cards
        // ekstra1 = card type 1, ekstra2 = amount paid with card 1
        // ekstra3 = card type 2, ekstra4 = amount paid with card 2
        $cardType1 = "";
        $cardAmount1 = "";
        $cardType2 = "";
        $cardAmount2 = "";
        $transactionId = "";
        
        if($order->Transactions != null && isset($order->Transactions->item)){
            $transactions = $order->Transactions->item;
            if (!is_array($transactions)) {
                $transactions = [$transactions]; // Wrap single object in array
            }
            
            // Get first transaction (card 1)
            if (isset($transactions[0])) {
                if (isset($transactions[0]->Cardtype)) {
                    $cardType1 = $transactions[0]->Cardtype;
                }
                if (isset($transactions[0]->Amount)) {
                    $cardAmount1 = $transactions[0]->Amount;
                }
                if (isset($transactions[0]->Id)) {
                    $transactionId = $transactions[0]->Id;
                }
            }
            
            // Get second transaction (card 2) if it exists
            if (isset($transactions[1])) {
                if (isset($transactions[1]->Cardtype)) {
                    $cardType2 = $transactions[1]->Cardtype;
                }
                if (isset($transactions[1]->Amount)) {
                    $cardAmount2 = $transactions[1]->Amount;
                }
            }
        }
        $url = "action=insert_shop_order&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser);
        $url .= "&shop_ordre_id=".urlencode($order->Id)."&shop_fakturanr=";
        $url .= "&shop_addr_id=".urlencode($order->Customer->Id)."&firmanavn=".urlencode($order->Customer->Company);
        $url .= "&addr1=".urlencode($order->Customer->Address);
        $url .= "&postnr=".urlencode($order->Customer->Zip)."&bynavn=".urlencode($order->Customer->City);
        $url .= "&land=".urlencode($order->Customer->Country);
        if($order->Customer->Phone != ""){
            $url .= "&tlf=".urlencode($order->Customer->Phone);
        } else {
            $url .= "&tlf=".urlencode($order->Customer->Mobile);
        }
        $url .= "&email=".urlencode($order->Customer->Email)."&ref=Dandomain";
        $url .= "&shop_status=".urlencode($order->Status)."&nettosum=".urlencode($nettosum);
        $url .= "&momssum=".urlencode($momssum)."&kontakt=".urlencode($order->Customer->Firstname . " " . $order->Customer->Lastname);
        $url .= "&lev_firmanavn=".urlencode($order->Customer->ShippingCompany)."&lev_addr1=".urlencode($order->Customer->ShippingAddress);
        $url .= "&lev_postnr=".urlencode($order->Customer->ShippingZip);
        $url .= "&lev_bynavn=".urlencode($order->Customer->ShippingCity);
        $url .= "&lev_land=".urlencode($order->Customer->ShippingCountry);
        $url .= "&lev_kontakt=".urlencode($order->Customer->ShippingFirstname . " " . $order->Customer->ShippingLastname);
        $url .= "&betalingsbet=Netto";
        $url .= "&betalingsdage=0&shop_fakturanr=".$order->Id;
        $url .= "&ordredate=" . (!empty($order->DateDelivered) ? urlencode($order->DateDelivered) : date('Y-m-d'));
        $url .= "&lev_date=";
        $url .= "&momssats=".urlencode($order->Vat);
        $url .= "&valuta=".urlencode($order->Currency->Iso);
        $url .= "&valutakurs=100";
        $url .= "&cvr=".urlencode($order->Customer->Cvr);
        $url .= "&gruppe=1";
        $url .= "&afd=3";
        $url .= "&projekt=";
        $url .= "&ekstra1=".urlencode($cardType1);
        $url .= "&ekstra2=".urlencode($cardAmount1);
        $url .= "&notes=".urlencode($order->CustomerComment);
        $url .= "&ekstra3=".urlencode($cardType2);
        $url .= "&ekstra4=".urlencode($cardAmount2);
        $url .= "&ekstra5=3";
        $url .= ($transactionId != "") ? "&betalings_id=".urlencode($transactionId) : "&betalings_id=";
        $url .= "&ean=".urlencode($order->Customer->Ean);
        ($order->Vat !== 0) ? $url .= "&momsfri=" : $url .= "&momsfri=on";
        if ($isKreditnota) {
            $url .= "&art=DK";
        }
        file_put_contents("saldi-text.txt", $url . "\n", FILE_APPEND);
        // insert order into saldi
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $callback = curl_exec($ch);
        if (curl_errno($ch)) {
            writeLog("CURL error inserting order " . $order->Id . ": " . curl_error($ch), 'ERROR');
            error_logs(curl_errno($ch));
        }
        curl_close($ch);
        // callback check to see if order is already in saldi
        $callback = str_replace('"','',$callback);
        intval($callback) ? $saldi_ordre_id = (int)$callback : $saldi_ordre_id = 0;
        if ($saldi_ordre_id !== 0 && $saldi_ordre_id !== "") {
            writeLog("Order " . $order->Id . " inserted into Saldi with ID: " . $saldi_ordre_id);

            // if its not in saldi add orderlines
            // Normalize item to always be an array (SOAP API returns object for single item, array for multiple)
            $orderLines = isset($order->OrderLines->item) ? $order->OrderLines->item : [];
            if (!is_array($orderLines)) {
                $orderLines = [$orderLines]; // Wrap single object in array
            }
            writeLog("Adding " . count($orderLines) . " order lines for order " . $order->Id);
            file_put_contents("orderline.json",json_encode($order->OrderLines, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            foreach ($orderLines as $orderLine) { 
                // get % discount $orderLine->Discount is not a procent but a amount
                $discount = 0;
                $isPureDiscountLine = false;
                if (isset($orderLine->Discount) && $orderLine->Discount > 0) {
                    if($orderLine->Price > 0){
                        $discount = ($orderLine->Discount / $orderLine->Price) * 100;
                    }else{
                        // Pure discount line (e.g. "Rabat"): use negative price
                        $orderLine->Price = -$orderLine->Discount;
                    }
                }
                if($orderLine->Price < 0){
                    $isPureDiscountLine = true;
                }
                $varenr = $isPureDiscountLine ? "R" : $orderLine->ItemNumber;
                $urltxt="action=insert_shop_orderline&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&saldi_ordre_id=".$saldi_ordre_id;
                $urltxt.="&varenr=".urlencode($varenr);
                $urltxt.="&beskrivelse=".urlencode($orderLine->ProductTitle);
                $urltxt.="&antal=".urlencode($orderLine->Amount);
                $urltxt.="&pris=".urlencode($orderLine->Price)."&rabat=".urlencode($discount)."&stregkode=&variant=&varegruppe=2";
                ($order->Vat > 0) ? $urltxt .= "&momsfri=" : $urltxt .= "&momsfri=on";
                file_put_contents("saldi-text.txt", $urltxt . "\n", FILE_APPEND);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$urltxt);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $res = curl_exec($ch);
                if (curl_errno($ch)) {
                    writeLog("CURL error inserting order line " . $orderLine->ItemNumber . ": " . curl_error($ch), 'ERROR');
                    error_logs(curl_errno($ch));
                } else {
                    writeLog("Order line added: " . $orderLine->ItemNumber . " | Qty: " . $orderLine->Amount . " | Price: " . $orderLine->Price);
                }
                curl_close($ch);
            }

            // add delivery cost
            writeLog("Adding delivery cost: " . $order->Delivery->Title . " | Price: " . $order->Delivery->Price);
            $urltxt="action=insert_shop_orderline&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&saldi_ordre_id=".$saldi_ordre_id;
            $urltxt.="&varenr=".urlencode("FRAGT");
            $urltxt.="&beskrivelse=".urlencode($order->Delivery->Title);
            $urltxt.= $isKreditnota ? "&antal=-1" : "&antal=1";
            $urltxt.= ($order->Delivery->Price == 0) ? "&pris=0.0" : "&pris=".urlencode(abs((float)$order->Delivery->Price));
            $urltxt.="&rabat=0&stregkode=&variant=&varegruppe=1";
            if($order->Delivery->Vat == true){
                $urltxt .= "&momsfri=";
            }else{
                $urltxt .= "&momsfri=on";
            }
            $urltxt .= "&fakturadate=" . (!empty($order->DateDelivered) ? urlencode($order->DateDelivered) : date('Y-m-d'));
            file_put_contents("saldi-text.txt", $urltxt . "\n", FILE_APPEND);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$urltxt);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            if (curl_errno($ch)) {
                writeLog("CURL error adding delivery cost: " . curl_error($ch), 'ERROR');
                error_logs(curl_errno($ch));
            } else {
                writeLog("Delivery cost added successfully for order " . $order->Id);
            }
            curl_close($ch);

            // Do invoice
            writeLog("Fakturering af order " . $order->Id);
            $urltxt="action=fakturer_ordre&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&saldi_ordre_id=".$saldi_ordre_id."&udskriv_til=&pos_betaling=on";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$urltxt);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            if (curl_errno($ch)) {
                error_logs(curl_errno($ch));
            }
            curl_close($ch);
            writeLog("Fakturering af order " . $order->Id . " fuldført");
            /* file_put_contents("saldi-text.txt", $res . "\n", FILE_APPEND); */
        } else {
            writeLog("Order " . $order->Id . " already exists in Saldi (skipped)", 'WARNING');
        }
    }
    writeLog("=== Order import process completed ===");



}elseif(isset($_GET["stock"])){
    // change stock to int
    $stock = (int)$_GET["totalStock"];
 }
 if(isset($stock)){
    // update stock in the webshop
    // DiscountType = The type of discount given in Discount, either 'p' for percent or 'a' for a amount
    // Discount = The discount given on the product
    $sku = $_GET["itemNo"];
    writeLogUpdate("=== Stock update request === SKU: $sku | New stock: $stock");

    try {
        $Client = new SoapClient('service.wsdl');
        $Client->Solution_Connect (array('Username' => 'Saldi@API.dk', 'Password' =>'u4rHNwP2eG7h9ja'));
        $parameter = ["ItemNumber" => $sku, "Stock" => $stock];
        try {
            $ProductReturn = $Client->Product_Update(array('ProductData' => $parameter));
            writeLogUpdate("Stock updated successfully for SKU: $sku | Response: " . json_encode($ProductReturn));
        } catch (Throwable $e) {
            // Product not found as product — look up as variant and use stock location update
            writeLogUpdate("Product_Update failed for SKU: $sku, looking up variant - " . $e->getMessage());
            $Client->Product_SetVariantFields(array('Fields' => 'Id,ItemNumber'));
            $variantResult = $Client->Product_GetVariantsByItemNumber(array('ItemNumber' => $sku));
            $variants = isset($variantResult->Product_GetVariantsByItemNumberResult->item)
                ? $variantResult->Product_GetVariantsByItemNumberResult->item
                : null;
            if (!$variants) {
                throw new Exception("No variant found with item number '$sku'");
            }
            if (!is_array($variants)) {
                $variants = [$variants];
            }
            $variantId = $variants[0]->Id;
            $StockReturn = $Client->Product_UpdateVariantStockForStockLocation(array(
                'VariantId' => $variantId,
                'StockLocationId' => 1,
                'Stock' => $stock
            ));
            writeLogUpdate("Variant stock updated for SKU: $sku (VariantId: $variantId) | Response: " . json_encode($StockReturn));
        }
    } catch (Throwable $e) {
        writeLogUpdate("Stock update failed for SKU: $sku - " . $e->getMessage(), 'ERROR');
    }
 }
 if(isset($_GET["salesPrice"])){
    $sku = $_GET["itemNo"];
    $price = $_GET["salesPrice"];
    $discountType = $_GET["discountType"];
    $discount = $_GET["discount"];
    writeLogUpdate("=== Price update request === SKU: $sku | Price: $price | Discount: $discount | Type: $discountType");
    
    if($discountType != "" && $discountType == "percent"){
        $discountType = "p";
    }else if($discountType != "" && $discountType == "amount"){
        $discountType = "a";
    }
    
    try {
        $Client = new SoapClient('service.wsdl');
        $Client->Solution_Connect (array('Username' => 'Saldi@API.dk', 'Password' =>'u4rHNwP2eG7h9ja'));
        $parameter = ["ItemNumber" => $sku, "Price" => $price, "DiscountType" => $discountType, "Discount" => $discount];
        try {
            $ProductReturn = $Client->Product_Update(array('ProductData' => $parameter));
            writeLogUpdate("Price updated successfully for SKU: $sku | Response: " . json_encode($ProductReturn));
        } catch (Throwable $e) {
            // Product not found — try as variant item number
            writeLogUpdate("Product_Update failed for SKU: $sku, trying Product_UpdateVariant - " . $e->getMessage());
            $VariantReturn = $Client->Product_UpdateVariant(array('VariantData' => $parameter));
            writeLogUpdate("Variant price updated successfully for SKU: $sku | Response: " . json_encode($VariantReturn));
        }
    } catch (Throwable $e) {
        writeLogUpdate("Price update failed for SKU: $sku - " . $e->getMessage(), 'ERROR');
    }
 }
 if(isset($_GET["costPrice"])){
    $sku = $_GET["itemNo"];
    $costPrice = $_GET["costPrice"];
    writeLogUpdate("=== Cost price update request === SKU: $sku | Cost price: $costPrice");
    
    try {
       $Client = new SoapClient('service.wsdl');
       $Client->Solution_Connect (array('Username' => 'Saldi@API.dk', 'Password' =>'u4rHNwP2eG7h9ja'));
       $parameter = ["ItemNumber" => $sku, "BuyingPrice" => $costPrice];
        try {
            $ProductReturn = $Client->Product_Update(array('ProductData' => $parameter));
            writeLogUpdate("Cost price updated successfully for SKU: $sku | Response: " . json_encode($ProductReturn));
        } catch (Throwable $e) {
            // Product not found — try as variant item number
            writeLogUpdate("Product_Update failed for SKU: $sku, trying Product_UpdateVariant - " . $e->getMessage());
            $VariantReturn = $Client->Product_UpdateVariant(array('VariantData' => $parameter));
            writeLogUpdate("Variant cost price updated successfully for SKU: $sku | Response: " . json_encode($VariantReturn));
        }
    } catch (Throwable $e) {
        writeLogUpdate("Cost price update failed for SKU: $sku - " . $e->getMessage(), 'ERROR');
    }
 }


?>
