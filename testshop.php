<?php 
  include "saldiinfo.php";
  // Get specific order by increment_id
  $increment_id = "3000037872";
  $ch = curl_init("https://www.havemoebelshoppen.dk/rest/V1/orders?searchCriteria[filter_groups][0][filters][0][field]=increment_id&searchCriteria[filter_groups][0][filters][0][condition_type]=eq&searchCriteria[filter_groups][0][filters][0][value]=".$increment_id);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: Bearer " . "cg5wbd1ful4m237mytt7i1cre0ta3x0s"));
  $res = curl_exec($ch);
  
  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  file_put_contents("response.json", $res);
  if (curl_errno($ch)){
    echo "curl error: " . curl_error($ch);
  }
  curl_close($ch);

  $res = json_decode($res);

  // Database connection
  /* $pdo = new PDO("mysql:host=localhost;dbname=accd7f09_866b5", "accd7f09_866b5", "LardedAcaciaBiopsyCuries");
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); */

  $i = 0;
  foreach($res->items as $item){
    // Get EAN from custom fields
    /* query = $pdo->prepare("SELECT * FROM `amasty_amcheckout_order_custom_fields` WHERE `order_id` = ?");
    $query->execute([$item->entity_id]);
    if($query->rowCount() == 1){
      $ean = $query->fetch(PDO::FETCH_ASSOC)["billing_value"];
    }else{
      $ean = "";
    } */
   $ean = "";
    
    // Get payment info
    /* $query = $pdo->prepare("SELECT * FROM `sales_order_payment` WHERE `parent_id` = ?");
    $query->execute([$item->entity_id]); */

    /* $magentoData[$i]["payment_id"] = $query->fetch(PDO::FETCH_ASSOC)["last_trans_id"]; */
    $magentoData[$i]["payment_id"] = $item->payment->additional_information[0];
    $magentoData[$i]["ean"] = $ean;
    $magentoData[$i]["order_id"] = $item->entity_id;
    $magentoData[$i]["order_nr"] = $item->increment_id;
    $magentoData[$i]["fornavn"] = $item->customer_firstname;
    $magentoData[$i]["efternavn"] = $item->customer_lastname;
    $magentoData[$i]["customer_email"] = $item->customer_email;
    $magentoData[$i]["moms"] = $item->extension_attributes->applied_taxes[0]->percent;
    $magentoData[$i]["valuta"] = $item->order_currency_code;
    $magentoData[$i]["order_status"] = $item->status;
    $magentoData[$i]["konto_id"] = $item->billing_address_id;
    $magentoData[$i]["customer_postcode"] = $item->billing_address->postcode;
    $magentoData[$i]["customer_city"] = $item->billing_address->city;
    $magentoData[$i]["customer_country"] = $item->billing_address->country_id;
    $magentoData[$i]["customer_telephone"] = $item->billing_address->telephone;
    $magentoData[$i]["shipping_country"] = $item->extension_attributes->shipping_assignments[0]->shipping->address->country_id;
    $magentoData[$i]["shipping_city"] = $item->extension_attributes->shipping_assignments[0]->shipping->address->city;
    $magentoData[$i]["shipping_firstname"] = $item->extension_attributes->shipping_assignments[0]->shipping->address->firstname;
    $magentoData[$i]["shipping_lastname"] = $item->extension_attributes->shipping_assignments[0]->shipping->address->lastname;
    $magentoData[$i]["shipping_postcode"] = $item->extension_attributes->shipping_assignments[0]->shipping->address->postcode;
    $magentoData[$i]["shipping_description"] = $item->shipping_description;
    $magentoData[$i]["shipping_amount"] = $item->shipping_amount;
    $magentoData[$i]["net_sum"] = $item->base_subtotal;
    $magentoData[$i]["vat_sum"] = $item->base_tax_amount;
    $magentoData[$i]["created_at"] = $item->created_at;
    
    // Payment method detection
    if(str_contains($item->payment->additional_information[0], "kreditkort")){
      $magentoData[$i]["payment_method"] = "kreditkort";
      $magentoData[$i]["cc_type"] = $item->payment->cc_type;
    }else if(str_contains($item->payment->additional_information[0], "EAN") || str_contains($item->extension_attributes->payment_additional_info[0]->value, "Sparxpres") || str_contains($item->extension_attributes->payment_additional_info[0]->value, "XpresPay")){
      $magentoData[$i]["payment_method"] = "konto";
      $magentoData[$i]["cc_type"] = "konto";
    }else{
      $magentoData[$i]["payment_method"] = "kreditkort";
      $magentoData[$i]["cc_type"] = explode(" ", $item->payment->cc_type)[0];
    }

    $magentoData[$i]["payment_additional_info"] = $item->payment->additional_information[0];
    $magentoData[$i]["total_price"] = $item->total_due;
    $length = count($item->status_histories);
    $magentoData[$i]["comment"] = $item->status_histories[$length-1]->comment;

    // Shipping address
    $j = 0;
    foreach($item->extension_attributes->shipping_assignments[0]->shipping->address->street as $street){
      $magentoData[$i]["shipping_street_address"][$j] = $street;
      $j++;
    }

    // Billing address
    $j = 0;
    foreach($item->billing_address->street as $street){
      $magentoData[$i]["customer_street_address"][$j] = $street;
      $j++;
    }

    // Order items
    $j = 0;
    foreach($item->items as $it){
      $magentoData[$i]["items_prices"][$j] = $it->base_original_price;
      $magentoData[$i]["product_id"][$j] = $it->product_id;
      $magentoData[$i]["name"][$j] = $it->name;
      $magentoData[$i]["qty_ordered"][$j] = $it->qty_ordered;
      $magentoData[$i]["item_price"][$j] = $it->base_price;
      $magentoData[$i]["item_vat"][$j] = $it->price_incl_tax - $it->price;
      $magentoData[$i]["sku"][$j] = $it->sku;
      $j++;
    }

    // Company handling
    if(isset($item->extension_attributes->shipping_assignments[0]->shipping->address->company)){
      $magentoData[$i]["company"] = $item->extension_attributes->shipping_assignments[0]->shipping->address->company;
    }else{
      $magentoData[$i]["company"] = $magentoData[$i]["shipping_firstname"]." ".$magentoData[$i]["shipping_lastname"];
    }
    if(isset($item->billing_address->company)){
      $magentoData[$i]["billing-company"] = $item->billing_address->company;
      $magentoData[$i]["att"] = $magentoData[$i]["fornavn"]." ".$magentoData[$i]["efternavn"];
    }else{
      $magentoData[$i]["billing-company"] = $magentoData[$i]["fornavn"]." ".$magentoData[$i]["efternavn"];
      $magentoData[$i]["att"] = "";
    }
    if(isset($item->extension_attributes->shipping_assignments[0]->shipping->address->company)){
      $magentoData[$i]["shipping-company"] = $item->extension_attributes->shipping_assignments[0]->shipping->address->company;
      $magentoData[$i]["shipping-att"] = $magentoData[$i]["shipping_firstname"]." ".$magentoData[$i]["shipping_lastname"];
    }else{
      $magentoData[$i]["shipping-company"] = $magentoData[$i]["shipping_firstname"]." ".$magentoData[$i]["shipping_lastname"];
      $magentoData[$i]["shipping-att"] = "";
    }
    $i++;
  }
  
  // Send data to API (same as orderhop.php)
  if(isset($magentoData)){
    file_put_contents("res.json", json_encode($magentoData));
    foreach($magentoData as $data){
        $url = "action=insert_shop_order&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser);
        $url .= "&shop_ordre_id=".urlencode($data["order_id"])."&shop_fakturanr=";
        $url .= "&shop_addr_id=".urlencode($data["konto_id"])."&firmanavn=".urlencode($data["billing-company"]);
        $url .= "&addr1=".urlencode($data["customer_street_address"][0]);
        (isset($data["customer_street_address"][1])) ? $url .= "&addr2=".urlencode($data["customer_street_address"][1]) : $url .= "&addr2="; 
        $url .= "&postnr=".urlencode($data["customer_postcode"])."&bynavn=".urlencode($data["customer_city"]);
        $url .= "&land=".urlencode($data["customer_country"])."&tlf=".urlencode($data["customer_telephone"]);
        $url .= "&email=".urlencode($data["customer_email"])."&ref=Magento";
        $url .= "&shop_status=".urlencode($data["order_status"])."&nettosum=".urlencode($data["net_sum"]);
        $url .= "&momssum=".urlencode($data["vat_sum"])."&kontakt=".urlencode($data["att"]);
        $url .= "&lev_firmanavn=".urlencode($data["shipping-company"])."&lev_addr1=".urlencode($data["shipping_street_address"][0]);
        (isset($data["shipping_address_street"][1])) ? $url .= "&lev_addr2=".urlencode($data["shipping_address_street"][1]) :  $url .= "&lev_addr2=";
        $url .= "&lev_postnr=".urlencode($data["shipping_postcode"]);
        $url .= "&lev_bynavn=".urlencode($data["shipping_city"]);
        $url .= "&lev_land=".urlencode($data["shipping_country"]);
        $url .= "&lev_kontakt=".urlencode($data["shipping-att"])."&betalingsbet=".urlencode($data["payment_method"]);
        $url .= "&betalingsdage=8&shop_fakturanr=".$data["order_nr"];
        $url .= "&ordredate=".urlencode($data["created_at"]);
        $url .= "&lev_date=";
        $url .= "&momssats=".urlencode($data["moms"]);
        $url .= "&valuta=".urlencode($data["valuta"]);
        $url .= "&valutakurs=100";
        $url .= "&gruppe=1";
        $url .= "&afd=3";
        $url .= "&projekt=";
        $url .= "&ekstra1=".urlencode($data["cc_type"]);
        $url .= "&ekstra2=".urlencode($data["total_price"]);
        $url .= "&notes=".urlencode($data["comment"]);
        $url .= "&ekstra3=".urlencode($data["payment_method"]);
        $url .= "&ekstra4=0.00";
        $url .= "&ekstra5=4";
        $url .= "&betalings_id=".urlencode($data["payment_id"]);
        $url .= "&ean=".urlencode($data["ean"]);
        $url .= "&momsfri=";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $callback = curl_exec($ch);
        echo "Response: " . $callback . "\n";
        /* if (curl_errno($ch)) {
          error_logs(curl_errno($ch));
        } */
        curl_close($ch);
        $callback = str_replace('"','',$callback);
        intval($callback) ? $saldi_ordre_id = (int)$callback : $saldi_ordre_id = 0;
        echo "Order ID: " . $saldi_ordre_id . "\n";
        // Insert order lines
        for($k = 0; $k < count($data["items_prices"]); $k++){
          $urltxt="action=insert_shop_orderline&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&saldi_ordre_id=".$saldi_ordre_id;
          $urltxt.="&vare_id=".urlencode($data["product_id"][$k])."&varenr=".urlencode($data["sku"][$k]);
          $urltxt.="&beskrivelse=".urlencode($data["name"][$k]);
          $urltxt.="&antal=".urlencode($data["qty_ordered"][$k]);
          $urltxt.="&pris=".urlencode($data["item_price"][$k])."&rabat=0&stregkode=&variant=&varegruppe=2";
          $urltxt .= "&momsfri=";
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$urltxt);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          $res = curl_exec($ch);
          /* if (curl_errno($ch)) {
            error_logs(curl_errno($ch));
          } */
          curl_close($ch);
          file_put_contents("response.txt", $urltxt, FILE_APPEND);
        }
        
        // Insert shipping line
        $urltxt="action=insert_shop_orderline&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&saldi_ordre_id=".$saldi_ordre_id;
        $urltxt.="&vare_id=0&varenr=fm&beskrivelse=".urlencode($data["shipping_description"]);
        $urltxt.="&antal=1&pris=".urlencode($data["shipping_amount"])."&rabat=0&stregkode=&variant=&varegruppe=72";
        $urltxt.="&momsfri=";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $serverurl."/rest_api.php?".$urltxt);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        /* if (curl_errno($ch)) {
          error_logs(curl_errno($ch));
        } */
        curl_close($ch);
        
        echo "Order sent successfully: " . $data["order_nr"] . "\n";
    }
  } else {
    echo "No order found with increment_id: " . $increment_id;
  }
?>