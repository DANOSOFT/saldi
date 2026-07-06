<?php
// This file is used to send invoices to EasyUBL
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/api.php --- patch 5.0.0 --- 2026-05-12 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260518 NTR - Changed address fetch logic, such that multiple spaces doesn't result in a incorrect address
// &&           - Changed the total logic, such that values above a thousand doesn't cut it to the thousands. aka. 27,010.40 became 27 due to the ,
// 20260609 CL/PHR - Null-check på EasyUBL-svar: tomt svar (HTTP 500) ved kreditnotaer giver nu dansk fejlbesked i stedet for "null"
// 20260619 NTR - Added more info to error files and changed the random string logic with orderId so it's easier to know which file comes from which order.
// &&           - Added the logic from ordre.php about fetching ean numbers from konto instead if it's not in the ordrer.
// 20260623 NTR - Added a , check on Addresses as sometimes people write extra information that is useless to us.
// 20260701 NTR - Added the moms logic from formfunk to api.php. Ideally this should be moved to a separate shared function, but for now it's here.

// 20260703 CDX/NTR - Added a function to take care of streetnames as the edgecases was making it too complex to handle in a oneliner, due to people writing addresses in a non-standard way. This function will split the streetname and buildingnumber into two separate fields, and also handle additional streetname if it exists.
// 20260703 NTR - Added var_grp to db_select for companyID and updatedCompany to avoid conflicts with other modules that might use the same var_name in settings table.
// 20260703 NTR - Added filtering out lines with 0 amount and not just empty description, so if either is empty, the line will be skipped. This is to avoid sending empty lines to EasyUBL, which I suspect crashes their code.

    @session_start();
    $s_id=session_id();
    include("../includes/connect.php");
    include("../includes/var2str.php");

    // Getting the api key and tenant id from the database
    $query = db_select("SELECT var_value FROM settings WHERE var_name = 'apiKey' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $apiKey = $res["var_value"];
    //echo $apiKey . "<br>";

    include("../includes/online.php");
    include("../includes/forfaldsdag.php");

    /**
     * @param string $address The full street address to split
     * @param string $additionalStreetName Additional street name information
     * @return array An array containing the split address components
     */
    function splitStreetAddress($address, $additionalStreetName = '') {
        $address = trim((string)$address);
        $additionalStreetName = trim((string)$additionalStreetName);

        $addressParts = array_map('trim', explode(',', $address, 2));
        $mainAddress = $addressParts[0];
        $commaAddressExtra = isset($addressParts[1]) ? $addressParts[1] : '';

        if ($commaAddressExtra !== '' && $additionalStreetName !== '') {
            $additionalStreetName = $commaAddressExtra . ', ' . $additionalStreetName;
        } elseif ($commaAddressExtra !== '') {
            $additionalStreetName = $commaAddressExtra;
        }

        $streetName = $mainAddress;
        $buildingNumber = '';
        if (preg_match('/^(.*\S)\s+(\d+\p{L}?(?:\s*[-\/]\s*\d+\p{L}?)?(?:\s+\p{L}{1,3})?)$/u', $mainAddress, $matches)) {
            $streetName = trim($matches[1]);
            $buildingNumber = trim($matches[2]);
        }

        return [
            'streetName' => $streetName,
            'buildingNumber' => $buildingNumber,
            'additionalStreetName' => $additionalStreetName,
        ];
    }

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
        $companyAddress = splitStreetAddress($res["addr1"], $res["addr2"]);
        $data = [
            "name" => $res["firmanavn"],
            "cvr" => "DK".$res["cvrnr"],
            "orgNo" => "", //TODO find out what string to put here
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
                "streetName" => $companyAddress['streetName'],
                "buildingNumber" => $companyAddress['buildingNumber'],
                "additionalStreetName" => $companyAddress['additionalStreetName'],
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

        // echo json_encode($data, JSON_PRETTY_PRINT) . "<br>";

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
            $error = ['error' => $errorNumber, 'message' => $errorMessage, 'response' => $response, 'status code' => $httpCode];

            // Save error response in temp folder
            file_put_contents("../temp/$db/Update-company-error-$timestamp.json", json_encode($error, JSON_UNESCAPED_UNICODE)."\n".json_encode($data, JSON_UNESCAPED_UNICODE));

            return ['success' => false, 'message' => 'Error updating company: ' . (is_string($errorMessage) ? $errorMessage : json_encode($errorMessage))];
        } else if (isset($response["hasEndpointPeppol"]) && (false === $response["hasEndpointPeppol"])) {
            $errorNumber = curl_errno($ch);
            $errorMessage = curl_error($ch);
            return ['success' => false,
                'message' => 'CVR is already registered in Semantics elsewhere, you have to cancel that first.',
                'easyUBL_message' => 'Error updating company: ' . json_encode($errorMessage, JSON_PRETTY_PRINT),
                'response' => $response,
                'status code' => $httpCode
            ];
        }

        // Save successful response in temp folder for debugging
        file_put_contents("../temp/$db/Update-company-success-$timestamp.json", json_encode($response, JSON_UNESCAPED_UNICODE));

        return ['success' => true, 'companyId' => $companyId, 'response' => $response];
    }

    // Getting the company id from the database
    function getCompanyID(){
        global $apiKey, $db;
        $sql = <<<SQL
            SELECT var_value 
            FROM settings 
            WHERE var_name = 'companyID' 
              AND var_grp = 'easyUBL'
        SQL;
        $query = db_select($sql, __FILE__ . " linje " . __LINE__);
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
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $timestamp = date("Y-m-d-H-i-s");
            if ($response === false || isset($response["error"]) || isset($response["errorNumber"]) || $response === null || $response === ""){
				// An error occurred
				$errorNumber = curl_errno($ch);
				$errorMessage = curl_error($ch);
				$error = ['error' => $errorNumber, 'message' => $errorMessage, 'statusCode' => $httpCode];

				// save response in file in temp folder
				file_put_contents("../temp/$db/Create-in-nemhandel-error-$timestamp.json", json_encode($error)."\n".json_encode($data, JSON_UNESCAPED_UNICODE));
				?>
				<script>
					alert("Der opstod en fejl under oprettelsen (Nemhandel). Prøv igen senere eller kontakt support.");
				</script>
				<?php
				exit;
			} elseif(isset($response["companyID"]) && $response["companyID"] === "00000000-0000-0000-0000-000000000000" || $httpCode >= 400) {
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
    /**
     * @param array $data The invoice data to send
     * @param string $url The EasyUBL API endpoint URL
     * @param string $orderId The order ID for logging purposes
     */
    function getInvoicesOrder($data, $url, $orderId) {
        global $bruger_id, $db, $apiKey;
        $query = db_select("SELECT var_value FROM settings WHERE var_name = 'updatedCompany' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__);
        if(db_num_rows($query) == 0){
            $query = db_select("SELECT var_value FROM settings WHERE var_name = 'companyID' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__);
            if(db_num_rows($query) > 0){
                $update_return_object = updateCompany();
                if($update_return_object['success']) {
                    $companyID = $update_return_object['companyId'];
                } else {
                    die($update_return_object['message']);
                }
                $query = db_modify("INSERT INTO settings (var_name, var_grp, var_value) VALUES ('updatedCompany', 'easyUBL', 'true')", __FILE__ . " linje " . __LINE__);
            }
        }
        $companyID = getCompanyID();
        if($companyID == "error"){
            die("Der er sket en fejl. Kontakt support.");
        }
        /* // For manual testing
        die(
            'URL: https://EasyUBL.net/api/SendDocuments/InvoiceCreditnote/' . htmlspecialchars($companyID) .
            '<pre>' .
            htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) .
            '</pre>'
        );
        */
        $ch = curl_init();

        $fullUrl = $url.$companyID;
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 20260604 - Add timeout to prevent hanging
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 20260604 - Connection timeout

        $headers = array();
        $headers[] = 'Authorization: '.$apiKey;
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, 1);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseHeaders = substr($rawResponse, 0, $headerSize);
        $result = substr($rawResponse, $headerSize);

        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $ranStr = $characters[rand(0, 4)];
        $fileId = $orderId ?? "no-id-" . $ranStr;

        // 20260604 - Save raw response before JSON decoding for better error diagnosis
        file_put_contents("../temp/$db/fakture-result-raw-$fileId.txt", "URL: $fullUrl\nHTTP Code: $httpCode\nCompanyID: $companyID\n---HEADERS---\n" . $responseHeaders . "\n---RAW RESPONSE---\n" . $result);
        
        if (curl_errno($ch)) {
            // Curl connection error - don't continue
            $errorNumber = curl_errno($ch);
            $errorMessage = curl_error($ch);
            file_put_contents("../temp/$db/fakture-curl-error-$fileId.json", json_encode(['error' => $errorNumber, 'message' => $errorMessage, 'http_code' => $httpCode], JSON_PRETTY_PRINT));
            ?>
            <script>
                alert("Forbindelsesfejl:\n\n<?php echo htmlspecialchars($errorMessage); ?>\n\nKontroller internetforbindelsen og prøv igen.");
            </script>
            <?php
            curl_close($ch);
            exit();
        }

        $rawJsonResponse = $result;
        $result = json_decode($result, true);

        // EasyUBL returnerer tomt svar (HTTP 500) for kreditnotaer - bug i EasyUBL API
        if ($result === null) {
            file_put_contents("../temp/$db/fakture-error-$fileId.json", "HTTP $httpCode: tomt eller ugyldigt JSON-svar fra EasyUBL");
            ?>
            <script>
                alert("EasyUBL returnerede et tomt eller ugyldigt svar (HTTP <?php echo $httpCode; ?>).\n\nDette er sandsynligvis en fejl i EasyUBL's API. Kontakt saldi.dk support med følgende oplysninger:\nDB: <?php echo $db; ?>\nFil-ID: <?php echo $fileId; ?>");
            </script>
            <?php
            exit;
        }

        // decode base64
        $xml = base64_decode($result["base64EncodedDocumentXml"] ?? "", true);
        if($xml === false || trim($xml) == ""){
            // An error occurred - check for easyUBL or Semantic error messages
            $errorNumber = curl_errno($ch);
            $errorMessage = curl_error($ch);
            $easyUBLError = isset($result["errorMessage"]) ? $result["errorMessage"] : "";
            $errorDetails = isset($result["error"]) ? $result["error"] : "";
            
            // 20260604 - Improved error logging for E-APS24003 errors
            // Capture all possible error information
            $error = [
                'curl_error_number' => $errorNumber,
                'curl_error_message' => $errorMessage,
                'easyUBL_errorMessage' => $easyUBLError,
                'easyUBL_error' => $errorDetails,
                'full_response' => $result
            ];
            
            // save response in file in temp folder with full details for debugging
            file_put_contents("../temp/$db/fakture-error-$fileId.json", json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n".json_encode($data, JSON_UNESCAPED_UNICODE));
            error_log(json_encode($error, JSON_PRETTY_PRINT)."\n---SENT DATA---\n".json_encode($data, JSON_UNESCAPED_UNICODE));
            
            // Determine which error to show
            $displayError = "";
            
            if(!empty($errorMessage)){
                // curl error
                $displayError = "Forbindelsesfejl: " . $errorMessage;
            }else if(!empty($easyUBLError)){
                // easyUBL specific error - likely validation issue
                $displayError = "easyUBL fejl: " . $easyUBLError;
            }else if(!empty($errorDetails)){
                // Alternative error field
                $displayError = "API fejl: " . (is_array($errorDetails) ? json_encode($errorDetails) : $errorDetails);
            }else if(is_array($result) && !empty($result)){
                // Show full response if nothing else works
                $displayError = "Uventet svar fra server: " . json_encode($result);
            }else{
                $displayError = "Ukendt fejl - kontakt support";
            }
            
            ?>
            <script>
                alert('Transmission fejl:\n\n<?= $displayError; ?>\n\nFejllogging gemt til debugging. Kontakt support hvis problemet persister.');
            </script>
            <?php
            exit;
        }
        // decode base64
        $xml = base64_decode($result["base64EncodedDocumentXml"], true);
        if($xml === false || trim($xml) == ""){
            $error = [
                'error' => 'Empty or invalid XML returned from EasyUBL',
                'http_code' => $httpCode,
                'json_error' => json_last_error_msg(),
                'base64_length' => strlen($result["base64EncodedDocumentXml"]),
                'decoded_xml_length' => ($xml === false) ? false : strlen($xml),
                'full_response' => $result,
                'raw_response' => $rawJsonResponse,
                'sent_data' => $data
            ];
            file_put_contents("../temp/$db/fakture-empty-xml-error-$fileId.json", json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            curl_close($ch);
            ?>
            <script>
                alert("Transmission fejl:\n\nEasyUBL returnerede en tom eller ugyldig XML-fil. Dokumentet er derfor ikke sendt videre.\n\nFejllogging gemt til debugging.");
            </script>
            <?php
            exit;
        }
        file_put_contents("../temp/$db/xml-$fileId.xml", $xml);
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
        file_put_contents("../temp/$db/$fileId.html", $result);
        curl_close($ch);

        return $fileId;
    }

    // Setting up the invoice data
    /**
     * @param int $id The Orders ID
     * @param string $type The type of document (invoice or creditnote)
     */
    function sendInvoice($id, $type) {
            global $db;
        $query = db_select("SELECT * FROM adresser WHERE art = 'S'", __FILE__ . " linje " . __LINE__);
        $adresse = db_fetch_array($query);
        $query = db_select("SELECT * FROM ordrer WHERE id = $id", __FILE__ . " linje " . __LINE__);
        $r_faktura = db_fetch_array($query);

        // get momssats of the order so that the moms can get reduced to that number.
        $ordre_moms = $r_faktura['momssats'];

        // Fall back to customer record (adresser) for EAN if the order row has none
        if(empty($r_faktura["ean"]) && !empty($r_faktura["konto_id"])){
            $q = db_select("SELECT ean FROM adresser WHERE id = " . intval($r_faktura["konto_id"]), __FILE__ . " linje " . __LINE__);
            $adresser_row = db_fetch_array($q);
            if(!empty($adresser_row["ean"])){
                $r_faktura["ean"] = $adresser_row["ean"];
            }
        }
        $initials = explode(" ", $r_faktura["firmanavn"]);
        foreach($initials as $key => $value){
            $initials[$key] = mb_substr($value, 0, 1, "UTF-8");
        }
        $initials = implode("", $initials);

        $type = strtolower(trim($type));
        if($type == "creditnote"){
            $creditNote = "Cre";
        }else{
            $creditNote = "Inv";
        }
        $cvrnr_with_prefix = "";
        // plain EAN/GLN (no colon) vs formatted "schemeId:value"
        if(!empty($r_faktura["ean"]) && strpos($r_faktura["ean"], ":") === false){
            $endpointId = $r_faktura["ean"];
            $endpointType = "GLN";
        } else if(!empty($r_faktura["ean"]) && strpos($r_faktura["ean"], ":") !== false){
            // Change === true to !== false
            $endpointId = trim(explode(":", $r_faktura["ean"])[1]);
            $endpointType = trim(explode(":", $r_faktura["ean"])[0]);
            if(is_numeric($endpointType)){
                $peppolSchemes = [
                    "0007" => ["prefix" => "SE", "type" => "SE:ORGNR"],
                    "9908" => ["prefix" => "NO", "type" => "NO:ORGNR"],
                    "0192" => ["prefix" => "NO", "type" => "NO:ORG"],
                    "0037" => ["prefix" => "FI", "type" => "FI:OVT"],
                    "0106" => ["prefix" => "NL", "type" => "NL:KVK"],
                    "0190" => ["prefix" => "NL", "type" => "NL:OINO"],
                    "0204" => ["prefix" => "DE", "type" => "DE:LWID"],
                    "9958" => ["prefix" => "DE", "type" => "DE:LID"],
                    "9920" => ["prefix" => "ES", "type" => "ES:VAT"],
                ];
                if(isset($peppolSchemes[$endpointType])){
                    $endpointId = $peppolSchemes[$endpointType]["prefix"] . $endpointId;
                    $endpointType = $peppolSchemes[$endpointType]["type"];
                } else {
                    $endpointType = "DK:CVR";
                }
                $cvrnr_with_prefix = $endpointId;
            }
        } else {
            if(preg_match('/^\d/', $r_faktura["cvrnr"])){
                $endpointId = "DK".$r_faktura["cvrnr"];
                $endpointType = "DK:CVR";
            } else {
                // CVR already has country prefix (e.g. SE123456789, NO987654321)
                $cvrTypes = [
                    "SE" => "SE:ORGNR", "NO" => "NO:ORG", "FI" => "FI:OVT",
                    "NL" => "NL:KVK",   "DE" => "DE:LWID", "ES" => "ES:VAT",
                ];
                $endpointId = $r_faktura["cvrnr"];
                $countryPrefix = strtoupper(substr($r_faktura["cvrnr"], 0, 2));
                $endpointType = $cvrTypes[$countryPrefix] ?? "DK:CVR";
            }
        }
        if($cvrnr_with_prefix == ""){
            if($r_faktura["cvrnr"]) {
                // Check if CVR number starts with digits only (Danish CVR)
                if (preg_match('/^\d/', $r_faktura["cvrnr"])) {
                    // Danish CVR - add DK prefix
                    $cvrnr_with_prefix = "DK" . $r_faktura["cvrnr"];
                } else {
                    // Already has country prefix (SE, NO, etc.) - use as is
                    $cvrnr_with_prefix = $r_faktura["cvrnr"];
                }
            }elseif($endpointType !== "GLN"){
                // No CVR and no GLN — cannot route via Peppol
                file_put_contents("../temp/$db/missing-cvr-error-" . date("Y-m-d-H-i-s") . ".json", json_encode(["error" => "Missing CVR number", "order_id" => $id, "customer" => $r_faktura["firmanavn"]], JSON_PRETTY_PRINT));
                ?>
                <script>
                    alert("Fejl: Kunden mangler både CVR-nummer og EAN/GLN-nummer. Mindst ét af disse kræves til Peppol-transmission. Venligst opdater kundeoplysningerne og prøv igen.");
                </script>
                <?php
                exit;
            }
        }
        // country code should be the same as prefix for cvrnr
        $countryCode = "DK";

        if($cvrnr_with_prefix !== ""){
            $countryCode = substr($cvrnr_with_prefix, 0, 2);
        }
        // Greenland (GL) and Faroe Islands (FO) should be sent as DK in companyId
        if($countryCode == "GL" || $countryCode == "FO"){
            $cvrnr_with_prefix = "DK" . substr($cvrnr_with_prefix, 2);
            $countryCode = "DK";
        }
        // 20260604 - Validate recipient address to prevent E-APS24003 transmission errors
        // Prepare customer address with fallback logic
        $customerAddr1 = trim($r_faktura["addr1"]);
        $customerBynavn = trim($r_faktura["bynavn"]);
        $customerPostnr = trim($r_faktura["postnr"]);
        $customerAddr2 = trim($r_faktura["addr2"]);
        $customerKontakt = trim($r_faktura["kontakt"]);
        
        $levAddr1 = trim($r_faktura["lev_addr1"]);
        $levBynavn = trim($r_faktura["lev_bynavn"]);
        $levPostnr = trim($r_faktura["lev_postnr"]);
        $levAddr2 = trim($r_faktura["lev_addr2"]);
        $levKontakt = trim($r_faktura["lev_kontakt"]);
        
        // If customer main address is empty, use delivery address for customer postal address
        if(empty($customerAddr1) || empty($customerBynavn) || empty($customerPostnr)){
            if(!empty($levAddr1) && !empty($levBynavn) && !empty($levPostnr)){
                // Use delivery address as fallback for customer address
                $customerAddr1 = $levAddr1;
                $customerBynavn = $levBynavn;
                $customerPostnr = $levPostnr;
                $customerAddr2 = $levAddr2;
                $customerKontakt = $levKontakt;
            }
        }

        $customerAddress = splitStreetAddress($customerAddr1, $customerAddr2);
        $deliveryAddress = splitStreetAddress($levAddr1, $levAddr2);
        
        // Delivery address - use best available address
        if($levAddr1 !== "" && $levBynavn !== "" && $levPostnr !== ""){
            $deliverAddress = [
                
                "streetName" => $deliveryAddress['streetName'],
                "buildingNumber" => $deliveryAddress['buildingNumber'],
                "inhouseMail" => $r_faktura["email"],
                "additionalStreetName" => $deliveryAddress['additionalStreetName'],
                "attentionName" => $levKontakt,
                "cityName" => $levBynavn,
                "postalCode" => $levPostnr,
                "countrySubentity" => "",
                "addressLine" => "",
                "countryCode" => $countryCode
            ];
        }else if($customerAddr1 !== "" && $customerBynavn !== "" && $customerPostnr !== ""){
            // 20260604 - Fallback to main address if delivery address is incomplete
            $deliverAddress = [
                "streetName" => $customerAddress['streetName'],
                "buildingNumber" => $customerAddress['buildingNumber'],
                "inhouseMail" => $r_faktura["email"],
                "additionalStreetName" => $customerAddress['additionalStreetName'],
                "attentionName" => $customerKontakt,
                "cityName" => $customerBynavn,
                "postalCode" => $customerPostnr,
                "countrySubentity" => "",
                "addressLine" => "",
                "countryCode" => $countryCode
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
            "orderReference" => $r_faktura['id'] ?? "", //TODO
            "invoiceReference" => "", //TODO
            "salesOrderID" => $r_faktura["ordrenr"],
            "note" => $r_faktura["notes"],
            "buyerReference" => $r_faktura["kundeordnr"],
            "accountingCost" => "0",
            "externalReference" => "",
            "accountingCustomerParty" => [
                "endpointId" =>  $endpointId, //$r_faktura["ean"], // 5790002747557
                "endpointIdType" => $endpointType, // GLN = Global Location Number (EAN)
                "name" => $r_faktura["firmanavn"],
                "companyId" => $cvrnr_with_prefix,
                "postalAddress" => [
                    "streetName" => $customerAddress['streetName'],
                    "buildingNumber" => $customerAddress['buildingNumber'],
                    "inhouseMail" => $r_faktura["email"],
                    "additionalStreetName" => $customerAddress['additionalStreetName'],
                    "attentionName" => $customerKontakt,
                    "cityName" => $customerBynavn,
                    "postalCode" => $customerPostnr,
                    "countrySubentity" => "",
                    "addressLine" => "",
                    "countryCode" => $countryCode
                ],
                "contact" => [
                    "initials" => ($initials !== null && $initials !== "") ? $initials : "",
                    "name" => ($customerKontakt !== "") ? $customerKontakt : $r_faktura["firmanavn"],
                    "telephone" => strval($r_faktura["phone"]),
                    "electronicMail" => $r_faktura["email"]
                ]
            ],
            // Not needed when Customer and Payer are the same
            // "buyerCustomerParty" => [
            //     "endpointId" => "", 
            //     "endpointIdType" => "", 
            //     "name" => "", 
            //     "companyId" => "", 
            //     "postalAddress" => [
            //         "streetName" => "", 
            //         "buildingNumber" => "", 
            //         "inhouseMail" => "", 
            //         "additionalStreetName" => "", 
            //         "attentionName" => "", 
            //         "cityName" => "", 
            //         "postalCode" => "", 
            //         "countrySubentity" => "", 
            //         "addressLine" => "", 
            //         "countryCode" => "", 
            //     ], 
            //     "contact" => [
            //         "initials" => "", 
            //         "name" => "", 
            //         "telephone" => "", 
            //         "electronicMail" => "", 
            //     ]
            // ], 
            "documentCurrencyCode" => $r_faktura["valuta"],
            //(float)number_format((float)$r_faktura["sum"], 2)
            "totalAmount" => round((float)$r_faktura["sum"], 2), ## 20260518 - NTR - Fix values over a thousand being truncated to the thousands.

            "deliverAddress" => $deliverAddress,
            "paymentMeans" => [
                "bankName" => $adresse["bank_navn"],
                "bankRegNo" => $adresse["bank_reg"],
                "bankAccount" => $adresse["bank_konto"],
                "bic" => $adresse["swift"] ?? "",
                "iban" => $adresse["iban"] ?? "",
                "creditorIdentifier" => "",
                "paymentID" => ""
            ],
            "additionalDocuments" => [
                // Template
                // [
                // "iD" => "",
                // "documentType" => "",
                // "documentDescription" => "",
                // "fileName" => "",
                // "base64Object" => "",
                // ],
            ],
            "allowanceCharges" => [
                //Template
                //I don't think this needs to be filled unless it's actually has charges
                //[
                //    "isCharge" => true,
                //    "reasonCode" => "",
                //    "reason": => "",
                //    "percentage"=> 0,
                //    "amount" => 0,
                //    "baseAmount" => 0
                //],
            ],
        ];

        $query = db_select("SELECT * FROM ordrelinjer WHERE ordre_id = $id ORDER BY posnr", __FILE__ . " linje " . __LINE__);
        $line = [];
        while ($res = db_fetch_array($query)) {

            $res["rabat"] = abs((float)$res["rabat"]);
            if($creditNote == "Cre"){
                $res["antal"] = abs((float)$res["antal"]);
                $res["pris"] = abs((float)$res["pris"]);
            }else{
                $res["antal"] = (float)$res["antal"];
                $res["pris"] = (float)$res["pris"];
            }
            $res["momssats"] = min(abs((float)$res["momssats"]), abs((float)$ordre_moms));
            if ($res["momsfri"] == 'on' || $res["omvbet"]) {
                $res["momssats"] = 0;
            }
            $res["beskrivelse"] = strip_tags($res["beskrivelse"]);
            if(trim($res["beskrivelse"]) == "" || (float)$res["antal"] == 0){
                continue;
            }
            file_put_contents("../temp/$db/ordrelinjer.json", json_encode($res, JSON_PRETTY_PRINT), FILE_APPEND);
            if($res["rabat"] > 0) {
                $price = $res["pris"];
                $price *= ($res["procent"]/100);
                $discPrct = $res["rabat"];
                $discAmount = $price * ($discPrct / 100) * $res["antal"];
                $lineAmount = $res["antal"] * ($price - ($price/100 * $discPrct));
            }else{
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
                "id" => strval($res["posnr"]),
                "quantity" => $res["antal"],
                "quantityUnitCode" => "EA",
                "price" => $price,
                "discountPercent" => $discPrct,
                "discountAmount" => round($discAmount, 2), ## 20260518 - NTR - Fix imprecision that leads to 0 and 9 trails. 20260604 - Removed duplicate discountAmount
                "vatPercent" => ($res["momssats"] != "" && $res["momssats"] != null) ? min(abs((float)$res["momssats"]), abs((float)$ordre_moms)): 0,
                "lineAmount" => $lineAmount,
                "priceInclTax" => false,
                "taxOnProfit" => false,
                "name" => ($res["varenr"] != "" && $res["varenr"] != null && $res["varenr"] != "null") ? $res["varenr"] : "txt",
                "description" => $beskrivelse,
                "accountingCost" => "",
                "commodityCode" => "",
                "isAllowanceCharge" => false,
                "item" => [
                    "buyersItemID" => "",
                    "sellersItemID" => ($res["varenr"] != "" && $res["varenr"] != null && $res["varenr"] != "null") ? strval($res["varenr"]) : "",
                    "standardItemID" => "",
                    "standardItemScheme" => "",
                    "cN8" => "",
                    "additionalItemProperti" => [],
                ],
            );
        }
        $data["invoiceLines"] = $line;
        file_put_contents("../temp/$db/data.json", json_encode($data, JSON_PRETTY_PRINT), FILE_APPEND);

        // 20260604 - Validate required fields before transmission to prevent E-APS24003 errors
        $missingFields = [];
        
        if((empty($cvrnr_with_prefix) || $cvrnr_with_prefix === "DK") && $endpointType !== "GLN"){
            $missingFields[] = "CVR-nummer";
        }
        // Check customer address (which may have been set to delivery address as fallback)
        if(empty($customerAddr1)){
            $missingFields[] = "Gadeadresse";
        }
        if(empty($customerBynavn)){
            $missingFields[] = "By";
        }
        if(empty($customerPostnr)){
            $missingFields[] = "Postnummer";
        }
        if(empty($data["invoiceLines"]) || count($data["invoiceLines"]) === 0){
            $missingFields[] = "Ordrelinjer";
        }
        
        if(!empty($missingFields)){
            error_log(json_encode(["error" => "Påkrævede felter mangler", "missing_fields" => $missingFields, "order_id" => $id], JSON_PRETTY_PRINT));
            ?>
            <script>
                alert("Fejl ved sending til Peppol:\n\nManglende felter: <?php echo htmlspecialchars(implode(', ', $missingFields)); ?>\n\nKontroller venligst ordren og kundeoplysningerne før transmission.");
            </script>
            <?php
            exit;
        }

        $name = getInvoicesOrder($data, "https://EasyUBL.net/api/SendDocuments/InvoiceCreditnote/", $id);

        return $name;
    }
    // dosen't get used
    /**
     * @param int $id The Orders ID
     */
    function sendOrder($id){
        $query = db_select("SELECT * FROM ordrer WHERE id = $id", __FILE__ . " linje " . __LINE__);
        $r_faktura = db_fetch_array($query);
        $orderAddress = splitStreetAddress($r_faktura["addr1"], $r_faktura["addr2"]);
        $orderDeliveryAddress = splitStreetAddress($r_faktura["lev_addr1"], $r_faktura["lev_addr2"]);
        if($r_faktura["lev_addr1"] !== ""){
            $deliverAddress = [
                "streetName" => $orderDeliveryAddress['streetName'],
                "buildingNumber" => $orderDeliveryAddress['buildingNumber'],
                "inhouseMail" => $r_faktura["email"],
                "additionalStreetName" => $orderDeliveryAddress['additionalStreetName'],
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
                    "streetName" => $orderAddress['streetName'],
                    "buildingNumber" => $orderAddress['buildingNumber'],
                    "inhouseMail" => $r_faktura["email"],
                    "additionalStreetName" => $orderAddress['additionalStreetName'],
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
                    "streetName" => $orderAddress['streetName'],
                    "buildingNumber" => $orderAddress['buildingNumber'],
                    "inhouseMail" => $r_faktura["email"],
                    "additionalStreetName" => $orderAddress['additionalStreetName'],
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
                    "streetName" => $orderAddress['streetName'],
                    "buildingNumber" => $orderAddress['buildingNumber'],
                    "inhouseMail" => $r_faktura["email"],
                    "additionalStreetName" => $orderAddress['additionalStreetName'],
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
        $line = [];
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
                "discountAmount" => round($discAmount, 2), ## 20260518 - NTR - Fix imprecision that leads to 0 and 9 trails.
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
        $name = getInvoicesOrder($data, "https://easyubl.net/api/SendDocuments/Order/", $id);
        return $name;
    }
?>
