<?php

    // This file is used to receive webhooks from EasyUBL

    include("../includes/connect.php");
    // Mail function
    function sendSimpleMail($to, $subject, $message, $from = null) {
        // Set default sender if not provided
        if ($from === null) {
            $from = 'noreply@' . $_SERVER['HTTP_HOST'];
        }
        
        // Email headers
        $headers = array(
            'From' => $from,
            'Reply-To' => $from,
            'X-Mailer' => 'PHP/' . phpversion(),
            'Content-Type' => 'text/html; charset=UTF-8'
        );
        
        // Convert headers array to string
        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= $key . ': ' . $value . "\r\n";
        }
        
        // Send email
        return mail($to, $subject, $message, $headerString);
    }

    // Retrieving webhook data
    $webhookData = file_get_contents('php://input');
    /* echo $randomString; */
    // save webhook data on server
    $timestamp = date("Y-m-d-H-i-s");
    file_put_contents("../temp/webhook-$timestamp.json", $webhookData);
    // get timestamp
    $timestamp = date("Y-m-d-H-i-s");
    if($webhookData == ""){
        exit;
    }
    // get base64 encoded msg
    $base64 = json_decode($webhookData, true);

    // get server name based on domain
    $domain = "https://".$_SERVER['SERVER_NAME'];
    $path = trim($_SERVER['REQUEST_URI'], '/');
    $firstFolder = explode('/', $path)[0];
    $serverName = "$domain/$firstFolder";

    // get db name
    $companyId = json_decode($webhookData, true);
    $companyId = $companyId['companyId'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://saldi.dk/locator/locator.php?action=getDBNameByCompanyId&companyId=$companyId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    $jsonOutPut = $output;
    curl_close($ch);
    $output = json_decode($output, true);
    if($output["msg"] == "OK"){
        $db = $output["db_name"];
        $dbLocation = $output["db_location"];
        $connection=db_connect($sqhost,$squser,$sqpass,$db);
        $query = db_select("SELECT email FROM adresser WHERE art = 'S'");
        $r = db_fetch_array($query);
        $email = $r["email"];
        if(!file_exists("../temp/$db")){
            mkdir("../temp/$db");
        }
        file_put_contents("../temp/$db/db-$timestamp.json", $jsonOutPut);
        
        // send notification to user
        $decoded = base64_decode($base64["base64EncodedMessage"]);
        
        
        /* if($decoded != ""){
            db_modify("INSERT INTO notifications (msg, read_status) VALUES ('$decoded', 0)",  __FILE__ . " linje " . __LINE__);
        }

        if($base64["documentStatusCode"] == 5210){
            db_modify("INSERT INTO notifications (msg, read_status) VALUES ('Du har modtaget en faktura', 0)",  __FILE__ . " linje " . __LINE__);
        } */

        // update digital status
        $incStatus = array(
            0 => 'NoStatus',
            5101 => 'Error',
            5110 => 'Pending',
            5115 => 'PendingValidating',
            5120 => 'PendingValid',
            5130 => 'Sending',
            5140 => 'Sent',
            5150 => 'Received',
            5160 => 'Confirmed',
            5170 => 'Rejected',
            5180 => 'Approved',
            5199 => 'Parked',
            5130 => "Sending"
        );
        
        if (array_key_exists((int)$base64["documentStatusCode"], $incStatus)) {
            $statusName = $incStatus[(int)$base64["documentStatusCode"]];
            db_modify("UPDATE ordrer SET digital_status = '$statusName' WHERE fakturanr = '$base64[externalIdentifier]'", __FILE__ . " linje " . __LINE__);
        }

        if($base64["documentStatusCode"] == 5110 || $base64["documentStatusCode"] == 5210){
            // decode base64
            $decoded = base64_decode($base64["base64EncodedMessage"]);
            file_put_contents("../temp/$db/msg-$timestamp.json", ["message" => $decoded]);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "$serverName/debitor/increaseInvoiceNumber.php?db=$db");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $res = curl_exec($ch);
            curl_close($ch);
            file_put_contents("../temp/$db/increase-$timestamp.json", $res);
        }
        file_put_contents("../temp/$db/res-$timestamp.json", $webhookData);
    }

    if($base64 == "" || $base64['documentXmlBase64Content'] == ""){
        exit;
    }

    // decode base64
    $decoded = base64_decode($base64["documentXmlBase64Content"]);

    // save document on server
    // if an invoice is sent from the webhook, save it in the folder "bilag/$db/pulje"
     
    if($base64["actionCode"] == 5010 && $base64["documentStatusCode"] == 5210){
        // increase invoice number
        $newData = ["db" => $db, "db_location" => explode("/", $dbLocation)[2], "invoice" => $decoded];
        $newData = json_encode($newData);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://storage.saldi.dk/getInvoice.php");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $newData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        curl_close($ch);
        // send mail to reciever of invoice 
        $res = json_decode($res, true);
        if($res["msg"] == "File saved"){
            if($base64["documentStatusCode"] == 5210){
                $emailSubject = "Ny faktura modtaget";
                $emailMessage = "
                <html>
                <head>
                    <title>Faktura notification</title>
                </head>
                <body>
                    <h2>Du har modtaget en ny faktura</h2>
                    <p>Der er modtaget en ny faktura i systemet.</p>
                    <p>Tidspunkt: " . date('Y-m-d H:i:s') . "</p>
                </body>
                </html>
                ";
                
                // Send the email
                $mailSent = sendSimpleMail($email, $emailSubject, $emailMessage);
                
                // Log the result
                if($mailSent) {
                    error_log("Email notification sent successfully for invoice: " . $base64['externalIdentifier']);
                } else {
                    error_log("Failed to send email notification for invoice: " . $base64['externalIdentifier']);
                }
            }
        }
        file_put_contents("../temp/$db/storage-$timestamp.json", $res);
    }else{
        // not sure what to do here yet (other responses) 
        file_put_contents("../temp/$db/other-$timestamp.json", $webhookData);
    }
?>