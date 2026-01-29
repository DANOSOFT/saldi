<?php
include "saldiinfo.php";
if(isset($_GET["put_new_orders"])){

    // get data from service.wsdl
    $Client = new SoapClient('service.wsdl');
    $Client->Solution_Connect (array('Username' => 'api@penescotrading.com', 'Password' =>'SmartWeb1648'));
    $Client->Solution_SetLanguage(array('LanguageISO' => 'DK'));

    // set dates for search critiria
    $start = date('Y-m-d', strtotime('-20 days'));
    $end = date('Y-m-d');

    // get orders
    $Client->Order_SetFields(array('Fields' => 'Status,OrderLines,Id,Customer,DateSent,DateUpdated,Vat,Currency,CustomerComment,Transactions,Payment,Total,DateDelivered,Delivery,UserId'));
    $Client->Order_SetOrderLineFields(array('Fields' => 'Id,BuyPrice,ItemNumber,Discount,Amount,ProductTitle,StockStatus,Price,ProductId,VariantId,VariantTitle'));
    $Orders = $Client->Order_GetByDate(array("Start" => $start, "End" => $end, "Status" => "1,3,6"));
    // go through orders and insert them into saldi
    file_put_contents("orders.txt", print_r($Orders, true));
    
    // Normalize orders to always be an array (API returns null for no orders, object for single, array for multiple)
    $orderItems = isset($Orders->Order_GetByDateResult->item) ? $Orders->Order_GetByDateResult->item : [];
    if (!is_array($orderItems) && $orderItems !== null) {
        $orderItems = [$orderItems]; // Wrap single object in array
    } elseif ($orderItems === null) {
        $orderItems = [];
    }
    
    foreach ($orderItems as $order) {
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

        // insert order into saldi
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $callback = curl_exec($ch);
        if (curl_errno($ch)) {
            error_logs(curl_errno($ch));
        }
        curl_close($ch);
        // callback check to see if order is already in saldi
        $callback = str_replace('"','',$callback);
        intval($callback) ? $saldi_ordre_id = (int)$callback : $saldi_ordre_id = 0;
        if ($saldi_ordre_id !== 0) {

            // if its not in saldi add orderlines
            // Normalize item to always be an array (SOAP API returns object for single item, array for multiple)
            $orderLines = isset($order->OrderLines->item) ? $order->OrderLines->item : [];
            if (!is_array($orderLines)) {
                $orderLines = [$orderLines]; // Wrap single object in array
            }
            
            foreach ($orderLines as $orderLine) { 
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

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$urltxt);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $res = curl_exec($ch);
                if (curl_errno($ch)) {
                    error_logs(curl_errno($ch));
                }
                curl_close($ch);
            }

            // add delivery cost
            $urltxt="action=insert_shop_orderline&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&saldi_ordre_id=".$saldi_ordre_id;
            $urltxt.="&varenr=".urlencode(1);
            $urltxt.="&beskrivelse=".urlencode($order->Delivery->Title);
            $urltxt.="&antal=1";
            $urltxt.= ($order->Delivery->Price == 0) ? "&pris=0.0" : "&pris=".urlencode($order->Delivery->Price);
            $urltxt.="&rabat=0&stregkode=&variant=&varegruppe=2";
            $urltxt .= "&momsfri=on";
            $urltxt .= "&fakturadate=" . (!empty($order->DateDelivered) ? urlencode($order->DateDelivered) : date('Y-m-d'));

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$urltxt);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            if (curl_errno($ch)) {
                error_logs(curl_errno($ch));
            }
            curl_close($ch);
        }
    }

}elseif(isset($_GET["stock"])){
    // change stock to int
    $stock = (int)$_GET["stock"];
 }
 
 if(isset($stock)){
    // update stock in the webshop
    // DiscountType = The type of discount given in Discount, either 'p' for percent or 'a' for a amount
    // Discount = The discount given on the product
    $sku = $_GET["itemNo"];

    $Client = new SoapClient('service.wsdl');
    $Client->Solution_Connect (array('Username' => 'api@penescotrading.com', 'Password' =>'SmartWeb1648'));
    $Client->Solution_SetLanguage(array('LanguageISO' => 'DK'));
    $parameter = ["ItemNumber" => $sku, "Stock" => $stock];
    $ProductReturn = $Client->Product_Update(array('ProductData' => $parameter));
 }

 if(isset($_GET["update_price"])){

    $sku = $_GET["itemNo"];
    $price = $_GET["salesPrice"];
    $discountType = $_GET["discountType"];
    $discount = $_GET["discount"];
    if($discountType != "" && $discountType == "percent"){
        $discountType = "p";
    }else if($discountType != "" && $discountType == "amount"){
        $discountType = "a";
    }
    $Client = new SoapClient('service.wsdl');
    $Client->Solution_Connect (array('Username' => 'api@penescotrading.com', 'Password' =>'SmartWeb1648'));
    $Client->Solution_SetLanguage(array('LanguageISO' => 'DK'));
    $parameter = ["ItemNumber" => $sku, "Price" => $price, "DiscountType" => $discountType, "Discount" => $discount];
    $ProductReturn = $Client->Product_Update(array('ProductData' => $parameter));

 }
?>
