<?php
    @session_start();
    $s_id=session_id();
    include("../includes/connect.php");
    include("../includes/online.php");
    $apiKey = json_decode(file_get_contents('php://input'), true);
   $query = db_select("SELECT * FROM adresser WHERE art = 'S'", __FILE__ . " linje " . __LINE__);
   $res = db_fetch_array($query);
   $data = [
       "name" => $res["firmanavn"],
       "cvr" => "DK".$res["cvrnr"],
       "currency" => "",
       "country" => "DK",
       "webhookUrl" => "",
       "defaultEndpoint" => [
           "endpointType" => "DK:CVR",
           "endpointIdentifier" => "DK".$res["cvrnr"],
           "registerAsRecipient" => true
       ],
       "defaultAddress" => [
           "name" => $res["firmanavn"],
           "department" => "",
           "streetName" => explode(" ",$res["addr1"])[0],
           "additionalStreetName" => $res["addr2"],
           "buildingNumber" => end(explode(" ", $res["addr1"])),
           "inhouseMail" => $res["email"],
           "cityName" => $res["bynavn"],
           "postalCode" => $res["postnr"],
           "countrySubentity" => "",
           "countryCode" => "DK"
       ],
       "defaultContact" => [
           "id" => "",
           "name" => $res["firmanavn"],
           "email" => $res["email"],
           "sms" => $res["tlf"]
       ],
       "payment" => [
           "bankname" => $res["bank_navn"],
           "bankRegNo" => $res["bank_reg"],
           "bankAccount" => $res["bank_konto"],
           "bic" => "",
           "iban" => "",
           "creditorIdentifier" => ""
       ],
       "doNotReceiveUBL" => false,
   ];

    $guid = "00000000-0000-0000-0000-000000000000";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://easyubl.net/api/Company/AddUpdate/$guid");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: ".$apiKey["apiKey"]));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    $response = curl_exec($ch);
    curl_close($ch);
    if ($response === false) {
        // An error occurred
        $errorNumber = curl_errno($ch);
        $errorMessage = curl_error($ch);
        $error = ['error' => $errorNumber, 'message' => $errorMessage, "succes" => false];
        json_encode($error, JSON_PRETTY_PRINT);
        
        // save response in file in temp folder
        $timestamp = date("Y-m-d-H-i-s");
        file_put_contents("../temp/$db/$timestamp.json", $error);
        return "error";
        echo $error;
    }else{
        $response = json_decode($response, true);
        echo json_encode(["companyID" => $response["companyID"], "success" => true]);
    }
    ?>