<?php

@session_start();
$s_id=session_id();
include("../includes/connect.php");
include("../includes/online.php");
$query = db_select("SELECT * FROM adresser WHERE art = 'S'", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        // check if there is alraedy "DK" before the cvr number
        if(substr($res["cvrnr"], 0, 2) == "DK"){
            $res["cvrnr"] = substr($res["cvrnr"], 2);
        }

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
                "bankName" => $res["bank_navn"],
                "bankRegNo" => $res["bank_reg"],
                "bankAccount" => $res["bank_konto"],
                "bic" => "",
                "iban" => "",
                "creditorIdentifier" => ""
            ],
            "doNotReceiveUBL" => false,
        ];
        echo json_encode($data, JSON_PRETTY_PRINT);
?>