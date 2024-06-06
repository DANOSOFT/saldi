<?php                           
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//                                      
// -- debitor/func/dfm_consignment.php -------- ver 4.0.0 -- 2021-03-03 --
// LICENSE                              
//                                      
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//      
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
// 
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2021 Saldi.DK ApS
// -----------------------------------------------------------------------
// 20210302 CA  Function to handle consignment for Danske Fragtmænd (dfm)


if (!function_exists('dfm_consignment')) {
function dfm_consignment(
	$deb_id,$deb_firmanavn,$deb_addr1,$deb_addr2,$deb_postnr,$deb_bynavn,$deb_land,$deb_phone,$deb_email,$deb_kontakt,
	$lev_navn,$lev_addr1,$lev_addr2,$lev_postnr,$lev_bynavn,$lev_land,$lev_kontakt,
	$order_id,$ordrenr,$fakturanr,$kundeordnr,$weight,
	$dfm_id,$dfm_user,$dfm_pass,$dfm_agree,$dfm_hub,$dfm_ship,$dfm_good,$dfm_pay,$dfm_url,$dfm_delrem,$dfm_gooddes,$dfm_prodcode,$dfm_sercode,
	$dfm_pickup_addr,$dfm_pickup_name1,$dfm_pickup_name2,$dfm_pickup_street1,$dfm_pickup_street2,$dfm_pickup_town,$dfm_pickup_zipcode) {

	$dfm_q = db_select("select * from adresser where art = 'S'",__FILE__ . " linje " . __LINE__); 
	$dfm_r = db_fetch_array($dfm_q); 
	$kontakt=$r['kontakt']; 
	$dfm_firmanavn=trim_utf8(html_entity_decode($dfm_r['firmanavn'], ENT_QUOTES, 'UTF-8')); 
	$dfm_addr1=trim_utf8(html_entity_decode($dfm_r['addr1'], ENT_QUOTES, 'UTF-8')); 
	$dfm_addr2=trim_utf8(html_entity_decode($dfm_r['addr2'], ENT_QUOTES, 'UTF-8')); 
	$dfm_postnr=trim_utf8(html_entity_decode($dfm_r['postnr'], ENT_QUOTES, 'UTF-8')); 
	$dfm_bynavn=trim_utf8(html_entity_decode($dfm_r['bynavn'], ENT_QUOTES, 'UTF-8')); 
	$dfm_tlf=trim_utf8(html_entity_decode($dfm_r['tlf'], ENT_QUOTES, 'UTF-8')); 
	$dfm_email=trim_utf8(html_entity_decode($dfm_r['email'], ENT_QUOTES, 'UTF-8')); 
	$dfm_tlf=str_replace(' ', '', $dfm_tlf);

	$deb_id=trim_utf8(html_entity_decode($deb_id, ENT_QUOTES, 'UTF-8'));        
	$deb_firmanavn=trim_utf8(html_entity_decode($deb_firmanavn, ENT_QUOTES, 'UTF-8'));        
	$deb_addr1=trim_utf8(html_entity_decode($deb_addr1, ENT_QUOTES, 'UTF-8'));        
	$deb_addr2=trim_utf8(html_entity_decode($deb_addr2, ENT_QUOTES, 'UTF-8'));        
	$deb_bynavn=trim_utf8(html_entity_decode($deb_bynavn, ENT_QUOTES, 'UTF-8'));        
	$deb_land=trim_utf8(html_entity_decode($deb_land, ENT_QUOTES, 'UTF-8'));        
	$deb_email=trim_utf8(html_entity_decode($deb_email, ENT_QUOTES, 'UTF-8'));        
	$deb_phone=trim_utf8(html_entity_decode($deb_phone, ENT_QUOTES, 'UTF-8')); 
	$deb_kontakt=trim_utf8(html_entity_decode($deb_kontakt, ENT_QUOTES, 'UTF-8'));        
	$lev_navn=trim_utf8(html_entity_decode($lev_navn, ENT_QUOTES, 'UTF-8'));        
	$lev_addr1=trim_utf8(html_entity_decode($lev_addr1, ENT_QUOTES, 'UTF-8'));        
	$lev_addr2=trim_utf8(html_entity_decode($lev_addr2, ENT_QUOTES, 'UTF-8'));        
	$lev_bynavn=trim_utf8(html_entity_decode($lev_bynavn, ENT_QUOTES, 'UTF-8'));        
	$lev_land=trim_utf8(html_entity_decode($lev_land, ENT_QUOTES, 'UTF-8'));        
	$lev_kontakt=trim_utf8(html_entity_decode($lev_kontakt, ENT_QUOTES, 'UTF-8'));        
	$dfm_delrem=trim_utf8(html_entity_decode($dfm_delrem, ENT_QUOTES, 'UTF-8'));        
	$dfm_gooddes=trim_utf8(html_entity_decode($dfm_gooddes, ENT_QUOTES, 'UTF-8'));        

	$dfm_dfm_pickup_addr=trim_utf8(html_entity_decode($dfm_dfm_pickup_addr, ENT_QUOTES, 'UTF-8'));        
	$dfm_dfm_pickup_name1=trim_utf8(html_entity_decode($dfm_dfm_pickup_name1, ENT_QUOTES, 'UTF-8'));        
	$dfm_dfm_pickup_name2=trim_utf8(html_entity_decode($dfm_dfm_pickup_name2, ENT_QUOTES, 'UTF-8'));        
	$dfm_dfm_pickup_street1=trim_utf8(html_entity_decode($dfm_dfm_pickup_street1, ENT_QUOTES, 'UTF-8'));        
	$dfm_dfm_pickup_street2=trim_utf8(html_entity_decode($dfm_dfm_pickup_street2, ENT_QUOTES, 'UTF-8'));        
	$dfm_dfm_pickup_town=trim_utf8(html_entity_decode($dfm_dfm_pickup_town, ENT_QUOTES, 'UTF-8'));        
	$dfm_dfm_pickup_zipcode=trim_utf8(html_entity_decode($dfm_dfm_pickup_zipcode, ENT_QUOTES, 'UTF-8'));        

#	if ( empty($deb_phone) ) {
#		$dfm_q = db_select("select phone from ordrer where fakturanr = '$fakturanr'",__FILE__ . " linje " . __LINE__); 
#		$dfm_r = db_fetch_array($dfm_q); 
#		$deb_phone=trim_utf8(html_entity_decode($dfm_r['phone'], ENT_QUOTES, 'UTF-8')); 
#	}
	if ( empty($deb_phone) ) {
		$dfm_q = db_select("select tlf from adresser where id = '$deb_id'",__FILE__ . " linje " . __LINE__); 
		$dfm_r = db_fetch_array($dfm_q); 
		$deb_phone=trim_utf8(html_entity_decode($dfm_r['tlf'], ENT_QUOTES, 'UTF-8')); 
	}
	$deb_phone=str_replace(' ', '', $deb_phone);

	if ( strlen($dfm_addr2) < 1 ) $dfm_addr2=null;
	if ( strlen($dfm_pickup_name2) < 1 ) $dfm_pickup_name2=null;
	if ( strlen($dfm_pickup_street2) < 1 ) $dfm_pickup_street2=null;


	$weight=ceil($weight);
	if ($weight < 1) $weight=1;

	if($land == 'Sweden'){
                    	$dfm_lev_land = "SE";
        } else if ($land == 'Sverige'){
       	                $dfm_lev_land = "SE";
               	} else if ($land == 'sverige'){
                        $dfm_lev_land = "SE";
       	        } else if($land == 'Denmark'){
               	        $dfm_lev_land = "DK";
               	} else if($land == 'Danmark'){
                       	$dfm_lev_land = "DK";
	        } else {
			$dfm_lev_land = "DK";
               	}

	if ( empty($dfm_pickup_addr) ) {
		$dfm_pickup_name1 = $dfm_firmanavn;
		$dfm_pickup_name2 = NULL;
		$dfm_pickup_street1 = $dfm_addr1;
		($dfm_addr2) ? $dfm_pickup_street2 = $dfm_addr2 : $dfm_pickup_street2=NULL;
		$dfm_pickup_town = $dfm_bynavn;
		$dfm_pickup_zipcode = $dfm_postnr;
	}

	if(!empty($lev_navn)) {
               	$dfm_lev_navn = $lev_navn;
                $dfm_lev_addr1 = $lev_addr1;
       	        if (empty($lev_addr2)) {
			$dfm_lev_addr2 = null;
		} else { 
			$dfm_lev_addr2 = $lev_addr2;
		}
		$dfm_lev_bynavn = $lev_bynavn;
               	$dfm_lev_postnr = $lev_postnr;
        } else {
       	        $dfm_lev_navn = $deb_firmanavn;
                $dfm_lev_addr1 = $deb_addr1;
       	        if (empty($deb_addr2)) {
			$dfm_lev_addr2 = null;
		} else { 
			$dfm_lev_addr2 = $deb_addr2;
		}
              	$dfm_lev_bynavn = $deb_bynavn;
                $dfm_lev_postnr = $deb_postnr;
       	}
        $dfm_lev_phone = $deb_phone;
        $dfm_lev_email = $deb_email;

#	$dfm_lev_tlf = $deb_tlf; // receiver phone postponed to later version (CA)
	$dfm_lev_mail = $deb_mail;
	if ( empty($lev_kontakt) ) { 
		if (empty($deb_kontakt)) {
			$dfm_lev_kontakt = $deb_firmanavn;
		} else {
			$dfm_lev_kontakt = $deb_kontakt;
		}
	} else { 
		$dfm_lev_kontakt = $lev_kontakt;
	}

	if ( empty($fakturanr) ) { 
		$dfm_ref = $ordrenr." ord";
	} else {
		$dfm_ref = $fakturanr;
	}

		
if ( strtolower($dfm_gooddes)==="catest" ) {

	echo "\n\n<p>SC: $dfm_sercode</p>\n\n";
}

// Consignment data for Danske Fragtmænd in an array
$json_send = array(
  "ConsignmentNumber" => null,
  "ConsignmentDate" => date("Y-m-d"), # Dags dato
  "ShippingType" => $dfm_ship,
  "AgreementNumber" => $dfm_agree,
  "HubAgreement" => $dfm_hub,
  "WhoPays" => $dfm_pay,
  "Sender" => array(
    "Name" => $dfm_firmanavn,
    "Name2" => null,
    "Name3" => null,
    "Name4" => null,
    "Street" => $dfm_addr1,
    "Street2" => $dfm_addr2,
    "Town" => $dfm_bynavn,
    "Zipcode" => $dfm_postnr,
    "Country" => "DK",
    "Phone" => $dfm_tlf,
    "Email" => $dfm_email,
    "ContactPerson" => null,
    "ContactPersonPhone" => null,
    "ContactPersonEmail" => $dfm_email
  ),
  "Receiver" => array(
    "Name" => $dfm_lev_navn,
    "Name2" => null,
    "Name3" => null,
    "Name4" => null,
    "Street" => $dfm_lev_addr1,
    "Street2" => $dfm_lev_addr2,
    "Town" => $dfm_lev_bynavn,
    "Zipcode" => $dfm_lev_postnr,
    "Country" => "DK",
    "Phone" => $dfm_lev_phone,
    "Email" => $dfm_lev_email,
    "ContactPerson" => $dfm_lev_kontakt,
    "ContactPersonPhone" => $dfm_lev_tlf,
    "ContactPersonEmail" => $dfm_lev_email
  ),
  "Initiator" => array(
    "Name" => $dfm_firmanavn,
    "Name2" => null,
    "Name3" => null,
    "Name4" => null,
    "Street" => $dfm_addr1,
    "Street2" => $dfm_addr2,
    "Town" => $dfm_bynavn,
    "Zipcode" => $dfm_postnr,
    "Country" => "DK",
    "Phone" => $dfm_tlf,
    "Email" => $dfm_email,
    "ContactPerson" => null,
    "ContactPersonPhone" => null,
    "ContactPersonEmail" => $dfm_email
  ),
  "Pickup" => array(
    "Name" => $dfm_pickup_name1,
    "Name2" => null,
#    "Name2" => $dfm_pickup_name2,
    "Name3" => null,
    "Name4" => null,
    "Street" => $dfm_pickup_street1,
    "Street2" => null,
#    "Street2" => $dfm_pickup_street2,
    "Town" => $dfm_pickup_town,
    "Zipcode" => $dfm_pickup_zipcode,
#    "Name" => "Havemøbelland ApS",
#    "Name2" => null,
#    "Name3" => null,
#    "Name4" => null,
#    "Street" => "Ny Mårumvej 260",
#    "Street2" => null,
#    "Town" => "Helsinge",
#    "Zipcode" => "3200",
    "Country" => "DK",
    "Phone" => $dfm_tlf,
#    "Email" => $dfm_email,
    "ContactPerson" => null,
    "ContactPersonPhone" => null
#    "ContactPersonEmail" => $dfm_email
  ),
  "DeliveryNotification" => array(
      "Email" => array(
          $deb_email
      ),
      "SMS" => array(
          $deb_phone
      ),
  ),
  "ExchangePallets" => array(
    "FullPallets" => 0,
    "HalfPallets" => 0,
    "QuarterPallets" => 0
  ),

  "Goods" => array(
      array(
        "Weight" => $weight,
        "Type" => $dfm_good,
        "Description" => $dfm_gooddes,
        "NumberOfItems" => 1
      ),
    ),
  "PickupTime" => array(
    "PickupIntervalStart" => date("Y-m-d"),
    "PickupIntervalEnd" => date("Y-m-d")
  ),
  "PreBooking" => true,
  "ProductCode" => $dfm_prodcode,
  "ServiceCodes" => array(
          $dfm_sercode
  ),
  "SenderReference" => $dfm_ref,
  "DeliveryRemark" => $dfm_devrem 
);

$json_send=json_encode($json_send);


if ( strtolower($dfm_gooddes)==="catest" ) {
	print "\n\n<p style='font-size:80%'>".var_dump($json_send)."</p>\n\n";
	return "CaTesting";
} else {
	print "\n\n<!-- \nvvvvvvvvvvvvvvvvvvvvvv JSON DATA SENT vvvvvvvvvvvvvvvvvvvvvvv\n";
	print var_dump($json_send);
	print "\n====================== JSON DATA SENT =======================\n -->\n\n";

}

 
# 
$urlToken = "https://sts.fragt.dk/adfs/oauth2/token";
$tokenData = dfm_RequestToken($urlToken,$dfm_id,$dfm_user,$dfm_pass,$dfm_url);
$tokenDataArray = json_decode($tokenData, true);
        if (is_array($tokenDataArray)) {
                if (array_key_exists('access_token', $tokenDataArray)) {
                        $access_token = $tokenDataArray['access_token'];
                        $token_type = $tokenDataArray['token_type'];
                        $expires_in = $tokenDataArray['expires_in'];
                        $resource = $tokenDataArray['resource'];
                        $refresh_token = $tokenDataArray['refresh_token'];
                        $refresh_token_expires_in = $tokenDataArray['refresh_token_expires_in'];
                        $id_token = $tokenDataArray['id_token'];
//                      if ($debugsw == 1) {
//                              echo "Viser hentede oplysninger:<br>";
//                              echo "access_token: $access_token<br>";
                                // echo "token_type: $token_type<br>";
                                // echo "expires_in: $expires_in<br>";
                                // echo "resource: $resource<br>";
                                // echo "refresh_token: $refresh_token<br>";
                                // echo "refresh_token_expires_in: $refresh_token_expires_in<br>";
                                // echo "id_token2: $id_token<br>";
//                      }
                }
                else if (array_key_exists('Curl error', $tokenDataArray)) {
                        $tokenFejl = $tokenDataArray['Curl error'];
                        $tokenFejlmeddelelse = $tokenDataArray['error'];
                }
        }
        else {
                echo "Could not get new token.<br>";
                echo "$tokenData<br>";
                echo "Programmet afbrydes...<br>";
                exit;
        }


#$url = "https://apistaging.fragt.dk/v1/Consignments";
#echo "<p>|".$dfm_url."|</p>";
$url = $dfm_url."/v1/Consignments";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"$url?$parameters_string");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//      curl_setopt($ch, CURLOPT_POST, 7);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_send);
        $header = array();
        $header[] = "Content-Type: application/json; charset=utf-8";
//      $header[] = "Content-Type: text/plain";
        $header[] = "Content-Length: " . strlen($json_send);
        $header[] = 'Authorization: ' . $token_type . ' ' . $access_token;
//      print_r($header);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate,br');

curl_setopt ($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('../../temp/dfm_api_temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

        $json_res = curl_exec($ch);

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $info = curl_getinfo($ch);

	$dfm_res = json_decode($json_res);
	#$dfm_errmsg = $drm_res->Pickup.Name->0;

        $errcodetxt=findtekst(894,$sprog_id);
        $errmsgtxt=findtekst(895,$sprog_id);
        $errmsg401=findtekst(896,$sprog_id);
        $errmsg403=findtekst(897,$sprog_id);
	$consigntxt=findtekst(899,$sprog_id);

	if ( $code === 201 ) {
		$dfm_consign_no = $dfm_res->ConsignmentNumber;
		$result = $consigntxt.": ".$dfm_consign_no;
		$qtxt = "update ordrer set consignmentid='".db_escape_string($dfm_consign_no)."' where id='$order_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		print "\n\n<!-- \nvvvvvvvvvvvvvvvvvvvvvv JSON DATA RECIEVED vvvvvvvvvvvvvvvvvvvvvvv\n";
		print var_dump($json_res);
		print "\n====================== JSON DATA RECIEVED =======================\n -->\n\n";
	} else if (( $code === 400) || ( $code === 500 )) {
#		$result = $errcodetxt.": ".$code."<br />".$errmsgtxt.": ".$dfm_errmsg;
		$result = $errcodetxt.": ".$code;
		print var_dump($dfm_res);
	} else if ( $code === 401) {
		$result = $errcodetxt.": ".$code."<br />".$errmsgtxt.": ".$errmsg401;
		print var_dump($dfm_res);
	} else if ( $code === 403) {
		$result = $errcodetxt.": ".$code."<br />".$errmsgtxt.": ".$errmsg403;
		print var_dump($dfm_res);
	}
	return $result;

	} // end funcion dfm_consignment = Fragtbrev Danske Fragtmænd
} // end if function dfm_consignment


// function to get authentication token for prebooking at Danske Fragtmænd
function dfm_RequestToken($urlToken,$dfm_id,$dfm_user,$dfm_pass,$dfm_url) {

        $dfm_user = urlencode($dfm_user);
        $parameters = array(
                        'client_id' => "$dfm_id",
                        'grant_type' => "password",
                        'Username' => "$dfm_user",
                        'Password' => "$dfm_pass",
                        'Resource' => "$dfm_url"
                );
        $parameters_string = "";
        foreach($parameters as $key=>$value) {
                $parameters_string .= $key.'='.$value.'&';
        }
        $parameters_string = rtrim($parameters_string,'&');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"$urlToken");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 5);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-formurlencoded'));
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate,br');

        $result = curl_exec($ch);

        return $result;
} // End dfm_RequestToken

?>
