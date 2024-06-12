<?php

    // This file is used to receive webhooks from EasyUBL

    require '../phpmailer/PHPMailerAutoload.php';
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

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

    // get db name
    $companyId = json_decode($webhookData, true);
    $companyId = $companyId['companyId'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://saldi.dk/locator/locator.php?action=getDBNameByCompanyId&companyId=$companyId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    $output = json_decode($output, true);
    if($output["msg"] == "OK"){
        $db = $output["db_name"];
        $dbLocation = $output["db_location"];
        $email = $output["email"];
        file_put_contents("../temp/$db/db-$timestamp.json", $output);
        if(!file_exists("../temp/$db")){
            mkdir("../temp/$db");
        }
        if($base64["documentStatusCode"] == 5120){
            // decode base64
            $decoded = base64_decode($base64["base64EncodedMessage"]);
            file_put_contents("../temp/$db/msg-$timestamp.json", ["message" => $decoded]);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://ssl8.saldi.dk/laja/debitor/increaseInvoiceNumber.php?db=$db");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $res = curl_exec($ch);
            curl_close($ch);
            $timestamp = date("Y-m-d-H-i-s");
            file_put_contents("../temp/$db/increase-$timestamp.json", $res);
            exit;
        }
        file_put_contents("../temp/$db/res-$timestamp.json", $webhookData);
    }else{
        file_put_contents("../temp/bad-companyId-$timestamp.json", $webhookData);
        $mail->SMTPDebug = SMTP::DEBUG_OFF;  // Enable verbose debug output
        $mail->isSMTP();                     // Send using SMTP
        $mail->Host       = 'ssl8.saldi.dk'; // Set the SMTP server to send through                       
        //$mail->SMTPSecure = 'tls';         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
        //$mail->Port       = 587;           // TCP port to connect to
        //Recipients
        $mail->setFrom("easyUBL-error@$_SERVER[SERVER_NAME]", 'Saldi');
        $mail->addAddress("pblm@saldi.dk");  // Add a recipient
        // Content
        $mail->isHTML(true);                 // Set email format to HTML
        $mail->Subject = 'Send faktura error';
        $mail->Body    = 'Hej,<br><br>Der er sket en fejl i EasyUBL, der er sendt en faktura til et forkert selskab.<br><br>Venlig hilsen<br>Saldi';
        $mail->AltBody = 'Hej, Der er sket en fejl i EasyUBL, der er sendt en faktura til et forkert selskab. Venlig hilsen Saldi';
        // attach $webhookData
        $mail->addStringAttachment($webhookData, "webhook-$timestamp.json");
        $mail->send();
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
        /* file_put_contents("../temp/$db/error-$timestamp.json", $newData); */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://ssl8.saldi.dk/laja/debitor/increaseInvoiceNumber.php?db=$db");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        curl_close($ch);
        /* file_put_contents("../temp/$db/iin-$timestamp.json", $res); */
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
        $mail->Host       = 'ssl8.saldi.dk';                       // Set the SMTP server to send through                       
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