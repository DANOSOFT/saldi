<?php

    /* @session_start();
    $s_id=session_id();
    include("../includes/connect.php");
    include("../includes/online.php"); */
    // include PHPMailer with class.phpmailer.php
    require '../../vendor/autoload.php';
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;
    /* $query = db_select("SELECT * FROM rentalmail WHERE id = 1", __FILE__ . " linje " . __LINE__);
    $mailRow = db_fetch_array($query);
    function sendMail($to, $subject, $body, $mailRow) {
        try{
            $mail = new PHPMailer(true);
            $mail->SMTPDebug = SMTP::DEBUG_OFF;                      // Enable verbose debug output
            $mail->isSMTP();                                            // Send using SMTP
            $mail->Host     = $mailRow["host"];                       // Set the SMTP server to send through                       
            $mail->Username = $mailRow["username"];           // SMTP username
            $mail->Password = $mailRow["password"];                    // SMTP password
            $mail->SMTPSecure = 'tls';         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
            $mail->Port       = 587;                                    // TCP port to connect to
            //Recipients
            $mail->setFrom("pblm@saldi.dk", "Patrick Madsen");
            $mail->AddAddress($userEmail);     // Add a recipient
            // Content
            $mail->AddEmbeddedImage('images/okok.gif', 'okok'); // Specify the image file path and a unique CID
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = 'Ordrebekraeftelse';
            $mail->Body    =  "awdawdiajwdoijaw awd awd";
            $mail->AltBody = "Hej du har betalt for leje af";
            $mail->send();
        }catch(Exception $e){
            file_put_contents("../temp/$db/error-$timestamp.json", $e->errorMessage());
            echo json_encode(["msg" => "Mail kunne ikke sendes til $userEmail"]);
            exit;
        }
    }

    echo sendMail("patrickmadsen23@gmail.com", "Test", "Test", $mailRow); */
    $userEmail = "patrickmadsen23@gmail.com";
    try{
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = SMTP::DEBUG_OFF;                      // Enable verbose debug output
        $mail->isSMTP();                                            // Send using SMTP
        $mail->Host       = $_SERVER['SERVER_NAME'];                       // Set the SMTP server to send through                       
        //$mail->SMTPSecure = 'tls';         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
        //$mail->Port       = 587;                                    // TCP port to connect to
        //Recipients
        $mail->setFrom("$db@$_SERVER[SERVER_NAME]", 'Saldi');
        $mail->AddAddress($userEmail);     // Add a recipient
        // Content
        $mail->AddEmbeddedImage('images/okok.gif', 'okok'); // Specify the image file path and a unique CID
        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = 'Ordrebekraeftelse';
        $mail->Body    =  $emailTemp;
        $mail->AltBody = "Hej du har betalt for leje af $itemRow[item_name] fra $startDate til $endDate.\n Venlig hilsen $row[firmanavn]";
        $mail->send();
        echo "Mail sendt til $userEmail";
    }catch(Exception $e){
        file_put_contents("../temp/$db/error-$timestamp.json", $e->errorMessage());
        echo "Mail kunne ikke sendes til $userEmail";
        exit;
    }