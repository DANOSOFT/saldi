<?php
    require_once '../vendor/autoload.php';
    use phpmailer\phpmailer\PHPMailer;
    use phpmailer\phpmailer\Exception;
    use phpmailer\phpmailer\SMTP;

    $data = json_decode(file_get_contents("php://input"), true);

    $mail = new PHPMailer(true);
    try{
        $mail->SMTPDebug = SMTP::DEBUG_OFF;                      // Enable verbose debug output
        $mail->isSMTP();                                            // Send using SMTP
        $mail->Host       = 'ssl5.salid.dk';                       // Set the SMTP server to send through                       
        //$mail->SMTPSecure = 'tls';         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
        //$mail->Port       = 587;                                    // TCP port to connect to
        //Recipients
        $mail->setFrom("$db@$_SERVER[SERVER_NAME]", 'Saldi');
        $mail->addAddress($data["email"]);     // Add a recipient
        // Content
        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = 'ordrebekræftelse fra Saldi';
        $mail->Body    = 'Hej, Du modtager hermed en ordrebekræftelse. Venlig hilsen Saldi';
        $mail->AltBody = 'Hej, Du modtager hermed en ordrebekræftelse. Venlig hilsen Saldi';
        $mail->send();
        }catch(Exception $e){
            file_put_contents("../temp/$db/error-$timestamp.json", $e->errorMessage());
        }
    
?>