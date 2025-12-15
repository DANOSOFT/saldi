<?php
include "saldiinfoCA.php";

// Logging function
function writeLog($message, $type = 'INFO') {
    $logFile = __DIR__ . '/customAudio.log';
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
    $start = date('Y-m-d', strtotime('-5 days'));
    $end = date('Y-m-d');
    writeLog("Fetching orders from $start to $end");

    // get orders
    $Client->Order_SetFields(array('Fields' => 'Status,OrderLines,Id,Customer,DateSent,DateUpdated,Vat,Currency,CustomerComment,Transactions,Payment,Total,DateDelivered,Delivery,UserId'));
    $Client->Order_SetOrderLineFields(array('Fields' => 'Id,BuyPrice,ItemNumber,Discount,Amount,ProductTitle,StockStatus,Price,ProductId,VariantId,VariantTitle'));
    $Orders = $Client->Order_GetByDate(array("Start" => $start, "End" => $end, "Status" => "1,3,6"));
    writeLog("Retrieved " . count($Orders->Order_GetByDateResult->item) . " orders");
    // go through orders and insert them into saldi
    
    foreach ($Orders->Order_GetByDateResult->item as $order) {
        writeLog("Processing order ID: " . $order->Id . " | Customer: " . $order->Customer->Email . " | Total: " . $order->Total . " " . $order->Currency->Iso);
        file_put_contents("orderline.json", json_encode($order, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        $cardType = "";
        $priceWithVat = $order->Total + ($order->Total * ($order->Vat / 100));
        $vatPrice = $order->Total * $order->Vat;
        if($order->Transactions != null && isset($order->Transactions->CardType)){
        $cardType = $order->Transactions->CardType;
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
        $url .= "&shop_status=".urlencode($order->Status)."&nettosum=".urlencode($order->Total);
        $url .= "&momssum=".urlencode($vatPrice)."&kontakt=".urlencode($order->Customer->Firstname . " " . $order->Customer->Lastname);
        $url .= "&lev_firmanavn=".urlencode($order->Customer->ShippingCompany)."&lev_addr1=".urlencode($order->Customer->ShippingAddress);
        $url .= "&lev_postnr=".urlencode($order->Customer->ShippingZip);
        $url .= "&lev_bynavn=".urlencode($order->Customer->ShippingCity);
        $url .= "&lev_land=".urlencode($order->Customer->ShippingCountry);
        $url .= "&lev_kontakt=".urlencode($order->Customer->ShippingFirstname . " " . $order->Customer->ShippingLastname);
        $url .= "&betalingsbet=netto";
        $url .= "&betalingsdage=30&shop_fakturanr=".$order->Id;
        $url .= "&ordredate=" . (!empty($order->DateDelivered) ? urlencode($order->DateDelivered) : date('Y-m-d'));
        $url .= "&lev_date=";
        $url .= "&momssats=".urlencode($order->Vat);
        $url .= "&valuta=".urlencode($order->Currency->Iso);
        $url .= "&valutakurs=100";
        $url .= "&cvr=".urlencode($order->Customer->Cvr);
        $url .= "&gruppe=1";
        $url .= "&afd=3";
        $url .= "&projekt=";
        $url .= "&ekstra1=".urlencode($cardType);
        $url .= "&ekstra2=".urlencode($priceWithVat);
        $url .= "&notes=".urlencode($order->CustomerComment);
        $url .= "&ekstra3=".urlencode($order->Payment->Title);
        $url .= "&ekstra4=0.00";
        $url .= "&ekstra5=4";
        $url .= (isset($order->Transactions->Id)) ? "&betalings_id=".urlencode($order->Transactions->Id) : "&betalings_id=";
        $url .= "&ean=".urlencode($order->Customer->Ean);
        ($order->Vat !== 0) ? $url .= "&momsfri=" : $url .= "&momsfri=on";
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
        if ($saldi_ordre_id !== 0) {
            writeLog("Order " . $order->Id . " inserted into Saldi with ID: " . $saldi_ordre_id);

            // if its not in saldi add orderlines
            writeLog("Adding " . count($order->OrderLines->item) . " order lines for order " . $order->Id);
            foreach ($order->OrderLines->item as $orderLine) { 
                // get % discount $orderLine->Discount is not a procent but a amount
                $discount = 0;
                if (isset($orderLine->Discount) && $orderLine->Discount > 0) {
                    $discount = ($orderLine->Discount / $orderLine->Price) * 100;
                }
                $urltxt="action=insert_shop_orderline&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&saldi_ordre_id=".$saldi_ordre_id;
                $urltxt.="&varenr=".urlencode($orderLine->ItemNumber);
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
            $urltxt.="&varenr=".urlencode(1);
            $urltxt.="&beskrivelse=".urlencode($order->Delivery->Title);
            $urltxt.="&antal=1";
            $urltxt.= ($order->Delivery->Price == 0) ? "&pris=0.0" : "&pris=".urlencode($order->Delivery->Price);
            $urltxt.="&rabat=0&stregkode=&variant=&varegruppe=2";
            $urltxt .= "&momsfri=on";
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
    writeLog("=== Stock update request === SKU: $sku | New stock: $stock");

    try {
        $Client = new SoapClient('service.wsdl');
        $Client->Solution_Connect (array('Username' => 'Saldi@API.dk', 'Password' =>'u4rHNwP2eG7h9ja'));
        $parameter = ["ItemNumber" => $sku, "Stock" => $stock];
        $ProductReturn = $Client->Product_Update(array('ProductData' => $parameter));
        writeLog("Stock updated successfully for SKU: $sku | Response: " . json_encode($ProductReturn));
    } catch (Throwable $e) {
        writeLog("Stock update failed for SKU: $sku - " . $e->getMessage(), 'ERROR');
    }
 }
 if(isset($_GET["salesPrice"])){
    $sku = $_GET["itemNo"];
    $price = $_GET["salesPrice"];
    $discountType = $_GET["discountType"];
    $discount = $_GET["discount"];
    writeLog("=== Price update request === SKU: $sku | Price: $price | Discount: $discount | Type: $discountType");
    
    if($discountType != "" && $discountType == "percent"){
        $discountType = "p";
    }else if($discountType != "" && $discountType == "amount"){
        $discountType = "a";
    }
    
    try {
        $Client = new SoapClient('service.wsdl');
        $Client->Solution_Connect (array('Username' => 'Saldi@API.dk', 'Password' =>'u4rHNwP2eG7h9ja'));
        $parameter = ["ItemNumber" => $sku, "Price" => $price, "DiscountType" => $discountType, "Discount" => $discount];
        $ProductReturn = $Client->Product_Update(array('ProductData' => $parameter));
        writeLog("Price updated successfully for SKU: $sku | Response: " . json_encode($ProductReturn));
    } catch (Throwable $e) {
        writeLog("Price update failed for SKU: $sku - " . $e->getMessage(), 'ERROR');
    }
 }
 if(isset($_GET["costPrice"])){
    $sku = $_GET["itemNo"];
    $costPrice = $_GET["costPrice"];
    writeLog("=== Cost price update request === SKU: $sku | Cost price: $costPrice");
    
    try {
       $Client = new SoapClient('service.wsdl');
       $Client->Solution_Connect (array('Username' => 'Saldi@API.dk', 'Password' =>'u4rHNwP2eG7h9ja'));
       $parameter = ["ItemNumber" => $sku, "BuyingPrice" => $costPrice];
        $ProductReturn = $Client->Product_Update(array('ProductData' => $parameter));
        writeLog("Cost price updated successfully for SKU: $sku | Response: " . json_encode($ProductReturn));
    } catch (Throwable $e) {
        writeLog("Cost price update failed for SKU: $sku - " . $e->getMessage(), 'ERROR');
    }
 }


?>
