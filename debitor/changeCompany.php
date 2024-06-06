<?php
    @session_start();
    $s_id=session_id();
    include("../includes/connect.php");

    // Getting the api key and tenant id from the database
    $query = db_select("SELECT var_value, var_name FROM settings WHERE var_grp = 'peppol'", __FILE__ . " linje " . __LINE__);
    while($res = db_fetch_array($query)){
        if($res["var_value"] !== ""){
            if($res["var_name"] == "apiKey"){
                $key = $res["var_value"];
            }elseif($res["var_name"] == "tenantId"){
                $tenantId = $res["var_value"];
            }
        }
    }
    $apiKey = $tenantId . "&" . $key;
    // 6c772607-988c-4435-8d78-3670f4a0629d&d5610b95-e39d-4894-8a11-22eb350ed84e

    // created endpoint for ebconnect 9280c477-1645-4443-9dee-268f9ce59453


    // Getting the database name from the database
    $query = db_select("SELECT * FROM online WHERE session_id = '$s_id' ORDER BY logtime DESC LIMIT 1", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $db = trim($res["db"]);
    $connection = db_connect($sqhost, $squser, $sqpass, $db);
    include("../includes/forfaldsdag.php");
    $query = db_select("SELECT * FROM adresser WHERE art = 'S'", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $data = [
        "name" => $res["firmanavn"],
        "cvr" => "DK".$res["cvrnr"],
        "currency" => "",
        "country" => "DK",
        "defaultEndpoint" => [
            "endpointType" => "DK:CVR",
            "endpointIdentifier" => $res["cvrnr"],
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
    ];

    echo json_encode($data, JSON_PRETTY_PRINT);

    $query = db_select("SELECT * FROM settings WHERE var_grp = 'peppol'", __FILE__ . " linje " . __LINE__);
    $guid = db_fetch_array($query)["var_value"];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://easyubl.net/api/Company/AddUpdate/$guid");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: ".$apiKey));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    $response = curl_exec($ch);
    echo $esponse;