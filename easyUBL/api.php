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


    // Getting the database name from the database
    $query = db_select("SELECT * FROM online WHERE session_id = '$s_id' ORDER BY logtime DESC LIMIT 1", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $db = trim($res["db"]);
    $connection = db_connect($sqhost, $squser, $sqpass, $db);
    include("../includes/forfaldsdag.php");

    // Setting up the user as a company at easyUBL
    function createCompany($apiKey, $tenantId){
        $query = db_select("SELECT * FROM adresser WHERE art = 'S'", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $data = [
            "name" => $res["firmanavn"],
            "cvr" => "DK".$res["cvrnr"],
            "currency" => "",
            "country" => "DKK",
            "defaultEndpoint" => [
                "endpointType" => $res["endpointType"],
                "endpointIdentifier" => "DK".$res["cvrnr"]
            ],
            "defaultAddress" => [
                "name" => $res["firmanavn"],
                "department" => "",
                "streetName" => $res["addr1"],
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
                "bankname" => "",
                "bankRegNo" => "",
                "bankAccount" => "",
                "bic" => "",
                "iban" => "",
                "creditorIdentifier" => ""
            ],
        ];

        echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";

        return $data;
        
    }

    // Getting the company id from the database
    function getCompanyID(){
        global $apiKey, $tenantId;
        $query = db_select("SELECT * FROM settings WHERE var_name = 'companyID'", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) === 0){
            // If the company id is not in the database, create it
            $guid = "00000000-0000-0000-0000-000000000000";
            $data = createCompany($apiKey, $tenantId);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://easyubl.net/api/Company/AddUpdate/$guid");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: ".$apiKey));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
            $response = curl_exec($ch);
            
            if ($response === false) {
                // An error occurred
                $errorNumber = curl_errno($ch);
                $errorMessage = curl_error($ch);
                
                echo 'cURL error number: ' . $errorNumber . '<br>';
                echo 'cURL error message: ' . $errorMessage . '<br>';
                return "error";
            } else {
                // Request successful
                $query = db_select("INSERT INTO settings (var_name, var_grp, var_value) VALUES ('companyID', 'peppol', '$response')", __FILE__ . " linje " . __LINE__);
            }

            curl_close($ch);
        }else{
            $res = db_fetch_array($query);
            return $res["var_value"];
            echo "hello";
        }
    }

    // Sending the invoice to the recipient through easyUBL
    function getInvoicesOrder($data, $url) {
        global $db, $apiKey;
        $companyID = getCompanyID();

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url.$companyID);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));

        $headers = array();
        $headers[] = 'Authorization: '.$apiKey;
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
            exit();
        }
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomString = '';

        for ($i = 0; $i < 10; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        file_put_contents("../temp/$db/$randomString.xml", $result);
        curl_close($ch);

        $ch = curl_init();
        $data = [
            "language" => "",
            "base64EncodedDocumentXml" => base64_encode($result)
        ];

        curl_setopt($ch, CURLOPT_URL, 'https://easyubl.net/api/HumanReadable/HTMLDocument');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: ".$apiKey));

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }
        file_put_contents("../temp/$db/$randomString.html", $result);
        curl_close($ch);

        return $randomString;
    }

    // Setting up the invoice data
    function sendInvoice($id, $type) {
        $query = db_select("SELECT * FROM adresser WHERE art = 'S'", __FILE__ . " linje " . __LINE__);
        $adresse = db_fetch_array($query);
        $query = db_select("SELECT * FROM ordrer WHERE id = $id", __FILE__ . " linje " . __LINE__);
        $r_faktura = db_fetch_array($query);

        $initials = explode(" ", $r_faktura["firmanavn"]);
        foreach($initials as $key => $value){
            $initials[$key] = substr($value, 0, 1);
        }
        $initials = implode("", $initials);
        if($type == "creditnote"){
            $creditNote = "Cre";
        }else{
            $creditNote = "Inv";
        }
        if($r_faktura["lev_addr1"] !== ""){
            $deliverAddress = [
                "streetName" => $r_faktura["lev_addr1"],
                "buildingNumber" => end(explode(" ", $r_faktura["lev_addr1"])),
                "inhouseMail" => $r_faktura["email"],
                "additionalStreetName" => $r_faktura["lev_addr2"],
                "attentionName" => $r_faktura["lev_kontakt"],
                "cityName" => $r_faktura["lev_bynavn"],
                "postalCode" => $r_faktura["lev_postnr"],
                "countrySubentity" => "",
                "addressLine" => "",
                "countryCode" => "DK"
            ];
        }else{
            $deliverAddress = [
                "streetName" => "",
                "buildingNumber" => "",
                "inhouseMail" => "",
                "additionalStreetName" => "",
                "attentionName" => "",
                "cityName" => "",
                "postalCode" => "",
                "countrySubentity" => "",
                "addressLine" => "",
                "countryCode" => ""
            ];
        }
        $data = [
            "invoiceCreditnote" => $creditNote,
            "id" => $r_faktura["id"],
            "issueDate" => date("c", strtotime($r_faktura["fakturadate"])),
            "dueDate" => usdate(forfaldsdag($r_faktura['fakturadate'], $r_faktura['betalingsbet'], $r_faktura['betalingsdage']))."T00:00:00.000Z",
            "deliveryDate" => date("c", strtotime($r_faktura["levdate"])),
            "salesOrderID" => $r_faktura["ordrenr"],
            "note" => $r_faktura["notes"],
            "buyerReference" => "0",
            "accountingCost" => "0",
            "accountingCustomerParty" => [
                "endpointId" => $r_faktura["ean"],
                "endpointIdType" => "DK:CVR", // GLN = Global Location Number (EAN)
                "name" => $r_faktura["firmanavn"],
                "companyId" => "DK32879300",
                "postalAddress" => [
                    "streetName" => explode(" ", $r_faktura["addr1"])[0],
                    "buildingNumber" => explode(" ", $r_faktura["addr1"])[1],
                    "inhouseMail" => $r_faktura["email"],
                    "additionalStreetName" => $r_faktura["addr2"],
                    "attentionName" => $r_faktura["firmanavn"],
                    "cityName" => $r_faktura["bynavn"],
                    "postalCode" => $r_faktura["postnr"],
                    "countrySubentity" => "",
                    "addressLine" => "",
                    "countryCode" => "DK"
                ],
                "contact" => [
                    "initials" => $initials,
                    "name" => ($r_faktura["kontakt"] !== "") ? $r_faktura["kontakt"] : $r_faktura["firmanavn"],
                    "telephone" => strval($r_faktura["phone"]),
                    "electronicMail" => $r_faktura["email"]
                ]
            ],
            "documentCurrencyCode" => $r_faktura["valuta"],
            "totalAmount" => (float)number_format((float)$r_faktura["sum"], 2),

            "deliverAddress" => $deliverAddress,
            "paymentMeans" => [
                "bankName" => $adresse["bank_navn"],
                "bankRegNo" => $adresse["bank_reg"],
                "bankAccount" => $adresse["bank_konto"], 
                "bic" => "", 
                "iban" => "", 
                "creditorIdentifier" => "", 
                "paymentID" => "" 
            ],

        ];
    
        $query = db_select("SELECT * FROM ordrelinjer WHERE ordre_id = $id ORDER BY posnr", __FILE__ . " linje " . __LINE__);
        while ($res = db_fetch_array($query)) {
            if ($res["rabat"] > 0) {
                $discAmount = round((float)$res["pris"] * ((float)$res["rabat"] / 100), 0);
                $price = $res["pris"] - $discAmount;
                $price = $price - ($price*1.25);
                $discPrct = $res["rabat"];
            } else {
                $price = (float)$res["pris"]*1.25;
                $discAmount = 0;
                $discPrct = 0;
            }
            $line[] = array(
                "id" => $res["id"],
                "quantity" => round($res["antal"], 0),
                "quantityUnitCode" => "stk",
                "price" => $price,
                "discountPercent" => round((float)$discPrct, 0),
                "discountAmount" => $discAmount,
                "vatPercent" => round($res["momssats"], 0),
                "lineAmount" => $price,
                "priceInclTax" => true,
                "taxOnProfit" => true,
                "name" => $res["varenr"],
                "description" => $res["beskrivelse"],
                "accountingCost" => "",
                "commodityCode" => ""
            );
        }
        $data["invoiceLines"] = $line;
        $name = getInvoicesOrder($data, "https://EasyUBL.net/api/SendDocuments/Invoice/");
        
        return $name;
    }

    function sendOrder($id){
        $query = db_select("SELECT * FROM ordrer WHERE id = $id", __FILE__ . " linje " . __LINE__);
        $r_faktura = db_fetch_array($query);
        if($r_faktura["lev_addr1"] !== ""){
            $deliverAddress = [
                "streetName" => $r_faktura["lev_addr1"],
                "buildingNumber" => end(explode(" ", $r_faktura["lev_addr1"])),
                "inhouseMail" => $r_faktura["email"],
                "additionalStreetName" => $r_faktura["lev_addr2"],
                "attentionName" => $r_faktura["lev_kontakt"],
                "cityName" => $r_faktura["lev_bynavn"],
                "postalCode" => $r_faktura["lev_postnr"],
                "countrySubentity" => "",
                "addressLine" => "",
                "countryCode" => "DK"
            ];
            $deliverParty = [
                "endpointId" => "DK $r_faktura[ean]",
                "endpointIdType" => "0184",
                "name" => $r_faktura["firmanavn"],
                "companyId" => "DK $r_faktura[ean]",
                "postalAddress" => [
                    "streetName" => explode(" ", $r_faktura["addr1"])[0],
                    "buildingNumber" => explode(" ", $r_faktura["addr1"])[1],
                    "inhouseMail" => $r_faktura["email"],
                    "additionalStreetName" => $r_faktura["addr2"],
                    "attentionName" => $r_faktura["firmanavn"],
                    "cityName" => $r_faktura["bynavn"],
                    "postalCode" => $r_faktura["postnr"],
                    "countrySubentity" => "",
                    "addressLine" => "",
                    "countryCode" => "DK"
                ],
                "contact" => [
                    "initials" => "",
                    "name" => ($r_faktura["kontakt"] !== "") ? $r_faktura["kontakt"] : $r_faktura["firmanavn"],
                    "telephone" => strval($r_faktura["phone"]),
                    "electronicMail" => $r_faktura["email"]
                ]
            ];
        }else{
            $deliverAddress = [
                "streetName" => "",
                "buildingNumber" => "",
                "inhouseMail" => "",
                "additionalStreetName" => "",
                "attentionName" => "",
                "cityName" => "",
                "postalCode" => "",
                "countrySubentity" => "",
                "addressLine" => "",
                "countryCode" => ""
            ];
            $deliverParty = [
                "endpointId" => "",
                "endpointIdType" => "",
                "name" => "",
                "companyId" => "",
                "postalAddress" => [
                    "streetName" => "",
                    "buildingNumber" => "",
                    "inhouseMail" => "",
                    "additionalStreetName" => "",
                    "attentionName" => "",
                    "cityName" => "",
                    "postalCode" => "",
                    "countrySubentity" => "",
                    "addressLine" => "",
                    "countryCode" => ""
                ],
                "contact" => [
                    "initials" => "",
                    "name" => "",
                    "telephone" => "",
                    "electronicMail" => ""
                ]
            ];
        }
        $data = [
            "id" => "",
            "issueDate" => $r_faktura["fakturadate"]."T00:00:00.000Z",
            "dueDate" => usdate(forfaldsdag($r_faktura['fakturadate'], $r_faktura['betalingsbet'], $r_faktura['betalingsdage']))."T00:00:00.000Z",
            "deliveryDate" => $r_faktura["levdate"]."T00:00:00.000Z",
            "salesOrderID" => $r_faktura["id"],
            "note" => $r_faktura["notes"],
            "buyerReference" => $r_faktura["firmanavn"],
            "accountingCost" => "0",
            "accountingCustomerParty" => [
                "endpointId" => "DK$r_faktura[cvrnr]",
                "endpointIdType" => "0184",
                "name" => $r_faktura["firmanavn"],
                "companyId" => "DK$r_faktura[cvrnr]",
                "postalAddress" => [
                    "streetName" => explode(" ", $r_faktura["addr1"])[0],
                    "buildingNumber" => explode(" ", $r_faktura["addr1"])[1],
                    "inhouseMail" => $r_faktura["email"],
                    "additionalStreetName" => $r_faktura["addr2"],
                    "attentionName" => $r_faktura["firmanavn"],
                    "cityName" => $r_faktura["bynavn"],
                    "postalCode" => $r_faktura["postnr"],
                    "countrySubentity" => "",
                    "addressLine" => "",
                    "countryCode" => "DK"
                ],
                "contact" => [
                    "initials" => "",
                    "name" => ($r_faktura["kontakt"] !== "") ? $r_faktura["kontakt"] : $r_faktura["firmanavn"],
                    "telephone" => strval($r_faktura["tlf"]),
                    "electronicMail" => $r_faktura["email"]
                ]
            ],
            "buyerCustomerParty" => [
                "endpointId" => "DK$r_faktura[cvrnr]33557799",
                "endpointIdType" => "0184",
                "name" => $r_faktura["firmanavn"],
                "companyId" => "DK$r_faktura[cvrnr]33557799",
                "postalAddress" => [
                    "streetName" => explode(" ", $r_faktura["addr1"])[0],
                    "buildingNumber" => explode(" ", $r_faktura["addr1"])[1],
                    "inhouseMail" => $r_faktura["email"],
                    "additionalStreetName" => $r_faktura["addr2"],
                    "attentionName" => $r_faktura["firmanavn"],
                    "cityName" => $r_faktura["bynavn"],
                    "postalCode" => $r_faktura["postnr"],
                    "countrySubentity" => "",
                    "addressLine" => "",
                    "countryCode" => "DK"
                ],
                "contact" => [
                    "initials" => "",
                    "name" => ($r_faktura["kontakt"] !== "") ? $r_faktura["kontakt"] : $r_faktura["firmanavn"],
                    "telephone" => strval($r_faktura["phone"]),
                    "electronicMail" => $r_faktura["email"]
                ]
            ],
            "deliveryParty" => $deliverParty,
            "documentCurrencyCode" => $r_faktura["valuta"],
            "deliverAddress" => $deliverAddress,
        ];
        $query = db_select("SELECT * FROM ordrelinjer WHERE ordre_id = $id ORDER BY posnr", __FILE__ . " linje " . __LINE__);
        while ($res = db_fetch_array($query)) {
            if ($res["rabat"] > 0) {
                $discAmount = round((float)$res["pris"] * ((float)$res["rabat"] / 100), 0);
                $price = $res["pris"] - $discAmount;
                $price = $price - ($price*0.20);
                $discPrct = $res["rabat"];
            } else {
                $price = (float)$res["pris"];
                $discAmount = 0;
                $discPrct = 0;
            }
            $line[] = array(
                "id" => $res["id"],
                "quantity" => round($res["antal"], 0),
                "quantityUnitCode" => "stk",
                "price" => $price,
                "discountPercent" => round((float)$discPrct, 0),
                "discountAmount" => $discAmount,
                "vatPercent" => round($res["momssats"], 0),
                "lineAmount" => $price,
                "priceInclTax" => true,
                "taxOnProfit" => true,
                "name" => $res["varenr"],
                "description" => $res["beskrivelse"],
                "accountingCost" => "",
                "commodityCode" => ""
            );
        }
        $data["invoiceLines"] = $line;
        $name = getInvoicesOrder($data, "https://easyubl.net/api/SendDocuments/Order/");
        return $name;
    }
    function trySend(){
        // GLN 5790002747557 - Winfinans test
        $data = [
            "invoiceCreditnote" => "Inv",
            "id" => "5985",
            "issueDate" => date("Y-m-d\TH:i:s.v\Z"),
            "dueDate" => date("Y-m-d\TH:i:s.v\Z"),
            "deliveryDate" => date("Y-m-d\TH:i:s.v\Z"),
            "salesOrderID" => "1000",
            "note" => "Fra saldi til Winfinans test",
            "buyerReference" => "jl",
            "accountingCost" => "1010",
            "accountingCustomerParty" => [
                "endpointId" => "5790002747557",
                "endpointIdType" => "GLN",
                "name" => "Winfinans test",
                "companyId" => "DK32879381",
                "postalAddress" => [
                    "streetName" => "Betonvej",
                    "buildingNumber" => "10",
                    "inhouseMail" => "",
                    "additionalStreetName" => "",
                    "attentionName" => "Jørgen Lavesen",
                    "cityName" => "Roskilde",
                    "postalCode" => "4000",
                    "countrySubentity" => "",
                    "addressLine" => "",
                    "countryCode" => "DK"
                ],
                "contact" => [
                    "initials" => "JL",
                    "name" => "Jørgen Lavesen",
                    "telephone" => "29336804",
                    "electronicMail" => "jl@Winfinans.dk"
                ]
            ],
            "documentCurrencyCode" => "DKK",
            "totalAmount" => 1000,
            "deliverAddress" => [
                "streetName" => "string",
                "buildingNumber" => "string",
                "inhouseMail" => "string",
                "additionalStreetName" => "string",
                "attentionName" => "string",
                "cityName" => "string",
                "postalCode" => "string",
                "countrySubentity" => "string",
                "addressLine" => "string",
                "countryCode" => "string"
            ],
            "invoiceLines" => [
                [
                    "id" => "string",
                    "quantity" => 1,
                    "quantityUnitCode" => "stk",
                    "price" => 1000,
                    "discountPercent" => 0,
                    "discountAmount" => 0,
                    "vatPercent" => 25,
                    "lineAmount" => 1000,
                    "priceInclTax" => true,
                    "taxOnProfit" => true,
                    "name" => "101020",
                    "description" => "En vare vi ikke har",
                    "accountingCost" => "",
                    "commodityCode" => ""
                ]
            ],
            "paymentMeans" => [
                "bankName" => "hello",
                "bankRegNo" => "1234",
                "bankAccount" => "1234567890", 
                "bic" => "", 
                "iban" => "", 
                "creditorIdentifier" => "", 
                "paymentID" => "343434343434" 
            ],
        ];
        /* if($companyID == "error"){
            die("error");
        } */
       /*  echo $companyID."<br>";
        echo $apiKey;
        echo "<pre>";
        print_r($data);
        echo "</pre>"; */
        getInvoicesOrder($data);
    }
?>