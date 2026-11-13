<?php
// 20261008 CDX/PHR Route locator requests to the PostgreSQL service on ssl3.
// 20261109 CL/MMR SST-983 Values from the webhook body are escaped or URL-encoded, and only an account on this installation is used.
//                 Nothing runs for a company the locator does not know, and an order is only updated when the webhook names its invoice number.
//                 The saved webhook files get a .ht_ name, which the web server does not hand out.
// 20261109 CL/SZ SST-987 A call must carry the account's webhook key (?key=), which is now part of the webhook URL registered at EasyUBL.
//                Without it the call is logged, and refused with 403 once webhookKeyRequired is set for the account.
//
    // This file is used to receive webhooks from EasyUBL

    include(__DIR__ . "/../includes/connect.php");
    include_once(__DIR__ . "/../includes/std_func.php");
    include_once(__DIR__ . "/../includes/stdFunc/easyUblWebhookKey.php");
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
    // SST-983: temp/ is under the web root, so the saved files are named .ht_*, which the web server refuses to hand out
    file_put_contents("../temp/.ht_webhook-$timestamp.json", $webhookData);
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
    // SST-983: companyId comes from the request body, so it must be a plain value, and it is URL-encoded for the locator
    $companyId = is_scalar(ifset($base64, 'companyId')) ? trim((string)ifset($base64, 'companyId')) : '';
    if ($companyId === '') {
        exit;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://ssl3.saldi.dk/locator/locator.php?action=getDBNameByCompanyId&companyId=" . urlencode($companyId));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    $jsonOutPut = $output;
    curl_close($ch);
    $output = json_decode($output, true);
    if(ifset($output, 'msg') == "OK"){
        // SST-983: only an account database that exists on this installation is used, never the master database.
        // The name is checked before it becomes $db, as db_select() also uses $db for a folder under temp/.
        $dbName = ifset($output, 'db_name');
        $dbName = is_scalar($dbName) ? trim((string)$dbName) : '';
        $qtxt = "SELECT id FROM regnskab WHERE db = '" . db_escape_string($dbName) . "'";
        if ($dbName === '' || $dbName === $sqdb || !db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            exit;
        }
        $db = $dbName;
        $dbLocation = $output["db_location"];
        $connection=db_connect($sqhost,$squser,$sqpass,$db);
        // SST-987: the webhook URL registered at EasyUBL carries the account's key, so a call without it does not come from EasyUBL.
        // Until webhookKeyRequired is set, such a call is only logged, so no incoming invoice is lost while accounts are registered again.
        $keyCheck = easyubl_webhook_key_check(ifset($_GET, 'key'));
        if (!$keyCheck['valid']) {
            error_log("SST-987 easyUBL.php: call for $db without a valid webhook key (" . $keyCheck['reason'] . "), " . ($keyCheck['required'] ? "refused" : "let through"));
            if ($keyCheck['required']) {
                http_response_code(403);
                exit;
            }
        }
        $query = db_select("SELECT email FROM adresser WHERE art = 'S'", __FILE__ . " linje " . __LINE__);
        $r = db_fetch_array($query);
        $email = $r["email"];
        if(!file_exists("../temp/$db")){
            mkdir("../temp/$db");
        }
        file_put_contents("../temp/$db/.ht_db-$timestamp.json", $jsonOutPut);
        
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
            5130 => "Sending",
            5135 => "Error",
        );
        
        // SST-983: externalIdentifier comes from the request body, so it is escaped, and without one no order is touched
        $externalIdentifier = is_scalar(ifset($base64, 'externalIdentifier')) ? trim((string)ifset($base64, 'externalIdentifier')) : '';
        if ($externalIdentifier !== '' && array_key_exists((int)ifset($base64, 'documentStatusCode', 0), $incStatus)) {
            $statusName = $incStatus[(int)ifset($base64, 'documentStatusCode', 0)];
            db_modify("UPDATE ordrer SET digital_status = '$statusName' WHERE fakturanr = '" . db_escape_string($externalIdentifier) . "'", __FILE__ . " linje " . __LINE__);
        }

        if($base64["documentStatusCode"] == 5110 || $base64["documentStatusCode"] == 5210){
            // decode base64
            $decoded = base64_decode($base64["base64EncodedMessage"]);
            file_put_contents("../temp/$db/.ht_msg-$timestamp.json", ["message" => $decoded]);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "$serverName/debitor/increaseInvoiceNumber.php?db=$db");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $res = curl_exec($ch);
            curl_close($ch);
            file_put_contents("../temp/$db/.ht_increase-$timestamp.json", $res);
        }
        file_put_contents("../temp/$db/.ht_res-$timestamp.json", $webhookData);
    } else {
        // SST-983: a company the locator does not know has no account here, so the invoice part below must not run
        exit;
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
                    <h2>Du har modtaget en ny elektronisk faktura</h2>
                    <p>Du kan se den i din dokument pulje</p>
                    <p>Du finder dokument puljen under kassekladder -> gå ind på en kladde -> papirklips ikonet</p>
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
        file_put_contents("../temp/$db/.ht_storage-$timestamp.json", $res);
    }else{
        // not sure what to do here yet (other responses) 
        file_put_contents("../temp/$db/.ht_other-$timestamp.json", $webhookData);
    }
?>