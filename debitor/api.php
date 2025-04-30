<?php

    // This file is used to send invoices to EasyUBL

    @session_start();
    $s_id=session_id();
    include("../includes/connect.php");
    include("../includes/var2str.php");

    // Getting the api key and tenant id from the database
    $query = db_select("SELECT var_value FROM settings WHERE var_name = 'apiKey' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $apiKey = $res["var_value"];
    

    include("../includes/online.php");
    include("../includes/forfaldsdag.php");

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

    // Getting the company id from the database
    function getCompanyID(){
        global $apiKey, $db;
        $query = db_select("SELECT * FROM settings WHERE var_name = 'companyID'", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) === 0){
            // If the company id is not in the database, create it
            $guid = "00000000-0000-0000-0000-000000000000";
            $data = createCompany($apiKey);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://easyubl.net/api/Company/Update/$guid");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: ".$apiKey));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $response = curl_exec($ch);
            $response = json_decode($response, true);
            curl_close($ch);
            echo $response;
            $timestamp = date("Y-m-d-H-i-s");
            if ($response === false || isset($response["error"]) || isset($response["errorNumber"]) || $response === null || $response === ""){
				// An error occurred
				$errorNumber = curl_errno($ch);
				$errorMessage = curl_error($ch);
				$error = ['error' => $errorNumber, 'message' => $errorMessage];
				json_encode($error, JSON_PRETTY_PRINT);
				
				// save response in file in temp folder
				file_put_contents("../temp/$db/Create-in-nemhandel-error-$timestamp.json", json_encode($error)."\n".json_encode($data, JSON_UNESCAPED_UNICODE));
				?>
				<script>
					alert("Der opstod en fejl under oprettelsen (Nemhandel). Prøv igen senere eller kontakt support.");
				</script>
				<?php
				exit;
			} elseif(isset($response["companyID"]) && $response["companyID"] === "00000000-0000-0000-0000-000000000000") {
				file_put_contents("../temp/$db/Create-in-nemhandel-error-$timestamp.json", json_encode($response)."\n".json_encode($data, JSON_UNESCAPED_UNICODE));
				?>
				<script>
					alert("Der opstod en fejl under oprettelsen (Nemhandel). Prøv igen senere eller kontakt support");
				</script>
				<?php
				exit;
			}else{
                // Request successful
                $query = db_select("SELECT * FROM settings WHERE var_name = 'globalId'", __FILE__ . " linje " . __LINE__);
                $globalid = db_fetch_array($query)["var_value"];
                $companyId = $response["companyID"];
                $query = db_modify("INSERT INTO settings (var_name, var_grp, var_value) VALUES ('companyID', 'easyUBL', '$companyId')", __FILE__ . " linje " . __LINE__);

                // Send the company id to ssl2.saldi.dk for storage
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://saldi.dk/locator/locator.php?action=insertCompanyId&companyId=$companyId&globalId=$globalid");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
                $res = curl_exec($ch);

                // save response in file in temp folder
                $timestamp = date("Y-m-d-H-i-s");
                file_put_contents("../temp/$db/$timestamp.json", $res);
                curl_close($ch);
                return $companyId;
            }
        }else{
            $res = db_fetch_array($query);
            return $res["var_value"];
        }
    }

    // Sending the invoice to the recipient through easyUBL
    function getInvoicesOrder($data, $url, $orderId) {
        global $db, $apiKey;
        $companyID = getCompanyID();
        if($companyID == "error"){
            die("Der er sket en fejl. Kontakt support.");
        }
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
        
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $ranStr = $characters[rand(0, 4)];
        file_put_contents($result, "../temp/$db/fakture-result-$ranStr.json");
        $result = json_decode($result, true);
        if (curl_errno($ch)) {
            echo 'Error: ' . curl_error($ch);
            file_put_contents(curl_error($ch), "../temp/$db/fakture-error-$ranStr.json");
            exit();
        }
        
        $randomString = '';

        for ($i = 0; $i < 10; $i++) {
            $randomString .= $characters[rand(0, 4)];
        }
        if(!isset($result["base64EncodedDocumentXml"]) || $result["base64EncodedDocumentXml"] == ""){
            // An error occurred
            $errorNumber = curl_errno($ch);
            $errorMessage = curl_error($ch);
            $error = ['error' => $errorNumber, 'message' => $errorMessage];
            json_encode($error, JSON_PRETTY_PRINT);
            
            // save response in file in temp folder
            file_put_contents("../temp/$db/fakture-error-$randomString.json", json_encode($error)."\n".json_encode($data, JSON_UNESCAPED_UNICODE));
            ?>
            <script>
                alert("Der opdstod en fejl under sending af fakturaen. kontakt support. Tlf: 46902208");
            </script>
            <?php
            exit;
        }
        // decode base64
        $xml = base64_decode($result["base64EncodedDocumentXml"]);
        file_put_contents("../temp/$db/xml-$randomString.xml", $xml);
        curl_close($ch);
        $ch = curl_init();
        $data = [
            "language" => "",
            "base64EncodedDocumentXml" => $result["base64EncodedDocumentXml"]
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

        $ch = curl_init("increaseInvoiceNumber.php?db=$db");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        curl_close($ch);

        return $randomString;
    }

    // Setting up the invoice data
    function sendInvoice($id, $type) {
				global $db;
        $query = db_select("SELECT * FROM adresser WHERE art = 'S'", __FILE__ . " linje " . __LINE__);
        $adresse = db_fetch_array($query);
        $query = db_select("SELECT * FROM ordrer WHERE id = $id", __FILE__ . " linje " . __LINE__);
        $r_faktura = db_fetch_array($query);
        $initials = explode(" ", $r_faktura["firmanavn"]);
        foreach($initials as $key => $value){
            $initials[$key] = substr($value, 0, 1);
        }
        $initials = implode("", $initials);
        if($r_faktura["art"] == "DK"){
            $creditNote = "Cre";
        }else{
            $creditNote = "Inv";
        }
        // check if the ean number is 13 characters long
        if($r_faktura["ean"] !== "" && strpos($r_faktura["ean"], ":") === false){
            $endpointId = $r_faktura["ean"];
            $endpointType = "GLN";
        }else if($_faktura["ean"] !== "" && strpos($r_faktura["ean"], ":") === true){
            // split at ean at : and take the first part
            $endpointId = trim(explode(":", $r_faktura["ean"])[1]);
            $endpointType = trim(explode(":", $r_faktura["ean"])[0]);
        }else{
            $endpointId = "DK".$r_faktura["cvrnr"];
            $endpointType = "DK:CVR";
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
            "id" => $r_faktura["fakturanr"],
            "issueDate" => date("c", strtotime($r_faktura["fakturadate"])),
            "dueDate" => usdate(forfaldsdag($r_faktura['fakturadate'], $r_faktura['betalingsbet'], $r_faktura['betalingsdage']))."T00:00:00.000Z",
            "deliveryDate" => date("c", strtotime($r_faktura["levdate"])),
            "salesOrderID" => $r_faktura["kundeordnr"],
            "note" => $r_faktura["notes"],
            "buyerReference" => "",
            "accountingCost" => "0",
            "externalReference" => "",
            "accountingCustomerParty" => [
                "endpointId" =>  $endpointId, //$r_faktura["ean"], // 5790002747557
                "endpointIdType" => $endpointType, // GLN = Global Location Number (EAN)
                "name" => $r_faktura["firmanavn"],
                "companyId" => "DK".$r_faktura["cvrnr"],
                "postalAddress" => [
                    "streetName" => explode(" ", $r_faktura["addr1"])[0],
                    "buildingNumber" => explode(" ", $r_faktura["addr1"])[1],
                    "inhouseMail" => $r_faktura["email"],
                    "additionalStreetName" => $r_faktura["addr2"],
                    "attentionName" => $r_faktura["kontakt"],
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
            //(float)number_format((float)$r_faktura["sum"], 2)
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
            /* $res["pris"] = abs($res["pris"]);
            $res["rabat"] = abs($res["rabat"]);
            $res["antal"] = abs($res["antal"]);
            $res["momssats"] = abs($res["momssats"]); */
            $res["beskrivelse"] = strip_tags($res["beskrivelse"]);
            if(trim($res["beskrivelse"]) == ""){
                continue;
            }
            file_put_contents("../temp/$db/ordrelinjer.json", json_encode($res, JSON_PRETTY_PRINT), FILE_APPEND);
            if($res["rabat"] > 0) {
                // make sure the price is positive
                $res["pris"] = abs($res["pris"]);
                $res["antal"] = abs($res["antal"]);
                $price = $res["pris"];
                $price *= ($res["procent"]/100);
                $discPrct = $res["rabat"];
                $discAmount = $price * ($discPrct / 100) * $res["antal"];
                $lineAmount = $res["antal"] * ($price - ($price/100 * $discPrct));
            }else{
                // make sure the price is positive
                $res["pris"] = abs($res["pris"]);
                $res["antal"] = abs($res["antal"]);
                $price = $res["pris"];
                $price *= ($res["procent"]/100);
                $discAmount = 0;
                $discPrct = 0;
                $lineAmount = $res["antal"] * $price; 
            }
            if($res["momssats"] == null){
                $res["momssats"] = 0;
            }
            // remove html tags from the description (beskrivelse)
            
            $beskrivelse = var2str($res["beskrivelse"], $id, $res['posnr'], $res["varenr"], $res["antal"], $res["enhed"], $price, $res["rabat"], $res["procent"], $res["serienr"], $res["momssats"]);
            $line[] = array(
                "id" => $res["id"],
                "quantity" => $res["antal"],
                "quantityUnitCode" => "EA",
                "price" => $price,
                "discountPercent" => $discPrct,
                "discountAmount" => $discAmount,
                "vatPercent" => ($res["momssats"] != "" && $res["momssats"] != null) ? $res["momssats"] : 0,
                "lineAmount" => $lineAmount,
                "priceInclTax" => false,
                "taxOnProfit" => false,
                "name" => ($res["varenr"] != "" && $res["varenr"] != null && $res["varenr"] != "null") ? $res["varenr"] : "txt",
                "description" => $beskrivelse,
                "accountingCost" => "",
                "commodityCode" => "",
                "isAllowanceCharge" => false,
            );
        }
        $data["invoiceLines"] = $line;
        file_put_contents("../temp/$db/data.json", json_encode($data, JSON_PRETTY_PRINT), FILE_APPEND);
        /* echo json_encode($data, JSON_PRETTY_PRINT); */
        $name = getInvoicesOrder($data, "https://EasyUBL.net/api/SendDocuments/InvoiceCreditnote/", $id);
        
        return $name;
    }
    // dosen't get used 
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
            $beskrivelse = var2str($res["beskrivelse"], $id, $res['posnr'], $res["varenr"], $res["antal"], $res["enhed"], $price, $res["rabat"], $res["procent"], $res["serienr"], $res["momssats"]);
            $line[] = array(
                "id" => $res["id"],
                "quantity" => round($res["antal"], 0),
                "quantityUnitCode" => "EA",
                "price" => $price,
                "discountPercent" => round((float)$discPrct, 0),
                "discountAmount" => $discAmount,
                "vatPercent" => round($res["momssats"], 0),
                "lineAmount" => $price,
                "priceInclTax" => true,
                "taxOnProfit" => true,
                "name" => $res["varenr"],
                "description" => $beskrivelse,
                "accountingCost" => "",
                "commodityCode" => ""
            );
        }
        $data["invoiceLines"] = $line;
        $name = getInvoicesOrder($data, "https://easyubl.net/api/SendDocuments/Order/");
        return $name;
    }
?>
