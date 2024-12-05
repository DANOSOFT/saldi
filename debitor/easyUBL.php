<?php

    // This file is used to receive webhooks from EasyUBL

    require '../vendor/autoload.php';
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;
    include("../includes/connect.php");
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
    if($domain == "https://ssl8.saldi.dk"){
        $serverName = "$domain/laja";
    }else if($domain == "https://ssl5.saldi.dk"){
        $serverName = "$domain/finans";
    }else{
        $serverName = "$domain/pos";
    }

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
        $email = $output["email"];
        
        if(!file_exists("../temp/$db")){
            mkdir("../temp/$db");
        }
        file_put_contents("../temp/$db/db-$timestamp.json", $jsonOutPut);
        
        // send notification to user
        $decoded = base64_decode($base64["base64EncodedMessage"]);
        if($decoded != ""){
            db_modify("INSERT INTO notifications (msg, read_status) VALUES ('$decoded', 0)",  __FILE__ . " linje " . __LINE__);
        }

        if($base64["documentStatusCode"] == 5210){
            db_modify("INSERT INTO notifications (msg, read_status) VALUES ('Du har modtaget en faktura', 0)",  __FILE__ . " linje " . __LINE__);
        }

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
        
        if (array_key_exists($base64["documentStatusCode"], $incStatus)) {
            $statusName = $incStatus[$base64["documentStatusCode"]];
            db_modify("UPDATE ordrer SET digital_status = '$statusName' WHERE id = '$base64[externalIdentifier]'", __FILE__ . " linje " . __LINE__);
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
        // file_put_contents("../temp/$db/error-$timestamp.json", $newData);
/*         $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$serverName/laja/debitor/increaseInvoiceNumber.php?db=$db");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        curl_close($ch); */
        // file_put_contents("../temp/$db/iin-$timestamp.json", $res);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://storage.saldi.dk/getInvoice.php");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $newData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        curl_close($ch);
        // send mail to reciever of invoice with phpmailer
        $mail = new PHPMailer(true);
        //Server settings
        try{
        $mail->SMTPDebug = SMTP::DEBUG_OFF;                      // Enable verbose debug output
        $mail->isSMTP();                                            // Send using SMTP
        $mail->Host       = $_SERVER['SERVER_NAME'];                       // Set the SMTP server to send through                       
        //$mail->SMTPSecure = 'tls';         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
        //$mail->Port       = 587;                                    // TCP port to connect to
        //Recipients
        $mail->setFrom("$db@$_SERVER[SERVER_NAME]", 'Saldi');
        $mail->addAddress($email);     // Add a recipient
        // Content
        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = 'Faktura';
        $mail->Body    = 'Hej,<br><br>Du har modtaget en faktura i Saldi, du kan se den under bilag.<br><br>Venlig hilsen<br>Saldi';
        $mail->AltBody = 'Hej, Du har modtaget en faktura. Venlig hilsen Saldi';
        $mail->send();
        }catch(Exception $e){
            file_put_contents("../temp/$db/error-$timestamp.json", $e->errorMessage());
        }
        file_put_contents("../temp/$db/storage-$timestamp.json", $res);
    }else{
        // not sure what to do here yet (other responses) 
        file_put_contents("../temp/$db/other-$timestamp.json", $webhookData);
    }
?>