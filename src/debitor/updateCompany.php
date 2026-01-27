<?php
    @session_start();
    $s_id=session_id();
    include("../includes/connect.php");
    include("../includes/var2str.php");

        // Getting the api key and tenant id from the database
    $query = db_select("SELECT var_value FROM settings WHERE var_name = 'apiKey' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $apiKey = $res["var_value"];

    include("../includes/online.php");

// Setting up the user as a company at easyUBL
    function createCompany($apiKey){
        $query = db_select("SELECT * FROM adresser WHERE art = 'S'", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        // check if there is alraedy "DK" before the cvr number
        if(substr($res["cvrnr"], 0, 2) == "DK"){
            $res["cvrnr"] = substr($res["cvrnr"], 2);
        }
        // get domain name
        $domain = "https://".$_SERVER['SERVER_NAME'];
        $path = trim($_SERVER['REQUEST_URI'], '/');
        $firstFolder = explode('/', $path)[0];
        $webhookUrl = "$domain/$firstFolder/debitor/easyUBL.php";
        $data = [
            "name" => $res["firmanavn"],
            "cvr" => "DK".$res["cvrnr"],
            "currency" => "DKK",
            "country" => "DK",
            "webhookUrl" => $webhookUrl,
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
                "inhouseMail" => "",
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

        /* echo json_encode($data, JSON_PRETTY_PRINT); */

        return $data;
        
    }

    // Update company data at EasyUBL with new data from database
    function updateCompany() {
        global $apiKey, $db;
        
        // Get the existing company ID from the database
        $query = db_select("SELECT var_value FROM settings WHERE var_name = 'companyID' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__);
        
        if(db_num_rows($query) === 0) {
            // No company ID exists, cannot update - need to create first
            return ['success' => false, 'message' => 'No company ID found. Please create a company first.'];
        }
        
        $res = db_fetch_array($query);
        $companyId = $res["var_value"];
        
        // Get fresh company data from the database
        $data = createCompany($apiKey);
        
        // Send update request to EasyUBL with the actual company ID
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://easyubl.net/api/Company/Update/$companyId");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: ".$apiKey));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response = json_decode($response, true);
        curl_close($ch);
        
        $timestamp = date("Y-m-d-H-i-s");
        
        if ($response === false || isset($response["error"]) || isset($response["errorNumber"]) || $response === null || $response === "") {
            // An error occurred
            $errorNumber = curl_errno($ch);
            $errorMessage = curl_error($ch);
            $error = ['error' => $errorNumber, 'message' => $errorMessage, 'response' => $response];
            
            // Save error response in temp folder
            file_put_contents("../temp/$db/Update-company-error-$timestamp.json", json_encode($error, JSON_UNESCAPED_UNICODE)."\n".json_encode($data, JSON_UNESCAPED_UNICODE));
            
            return ['success' => false, 'message' => 'Error updating company: ' . $errorMessage];
        }
        
        // Save successful response in temp folder for debugging
        file_put_contents("../temp/$db/Update-company-success-$timestamp.json", json_encode($response, JSON_UNESCAPED_UNICODE));
        
        return ['success' => true, 'companyId' => $companyId, 'response' => $response];
    }

    updateCompany();