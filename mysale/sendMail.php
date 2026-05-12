<?php
    $header = "nix";
    $bg = "nix";
    include("../includes/connect.php");
    include("../includes/std_func.php");

    $data = json_decode(file_get_contents('php://input'), true);
    $startDate = $data['start_date'];
    $endDate = $data['end_date'];
    $price = $data['price'];
    $db = $data['db'];
    $bookingId = $data['booking_id'];
    $user = $data['user'];

    $connection = db_connect($sqhost, $squser, $sqpass, $db);

    $query = db_select("SELECT * FROM adresser WHERE art = 'S'", __FILE__ . " linje " . __LINE__);
    $row = db_fetch_array($query);

    $query = db_select("SELECT * FROM rentalperiod WHERE id = $bookingId", __FILE__ . " linje " . __LINE__);
    $periodRow = db_fetch_array($query);
    $query = db_select("SELECT * FROM rentalitems WHERE id = $periodRow[item_id]", __FILE__ . " linje " . __LINE__);
    $itemRow = db_fetch_array($query);
    $query = db_select("SELECT * FROM ordrer WHERE id = $periodRow[order_id]", __FILE__ . " linje " . __LINE__);
    $orderRow = db_fetch_array($query);
    $query = db_select("SELECT * FROM adresser WHERE id = $user", __FILE__ . " linje " . __LINE__);
    $userRow = db_fetch_array($query);
    $userEmail = $userRow["email"];
    $query = db_select("SELECT * FROM ordrelinjer WHERE ordre_id = $periodRow[order_id]", __FILE__ . " linje " . __LINE__);
    // Calculate total including moms
    $total = $orderRow["sum"] + $orderRow["moms"];

    // Format total to have 2 decimals and change dot to comma
    $total = number_format($total, 2, ',', '');

    // include email template
    include("new-email.php");

    // autoload phpmailer through composer
    require '../../vendor/autoload.php';
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;
    $query = db_select("SELECT * FROM rentalmail WHERE id = 1", __FILE__ . " linje " . __LINE__);
    if(db_num_rows($query) > 0){
       $mailRow = db_fetch_array($query);
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
            $mail->setFrom($row["email"], $row["firmanavn"]);
            $mail->AddAddress($userEmail);     // Add a recipient
            // Content
            $mail->AddEmbeddedImage('images/okok.gif', 'okok'); // Specify the image file path and a unique CID
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = 'Ordrebekraeftelse';
            $mail->Body    =  $emailTemp;
            $mail->AltBody = "Hej du har betalt for leje af $itemRow[item_name] fra $startDate til $endDate.\n Venlig hilsen $row[firmanavn]";
            $mail->send();
        }catch(Exception $e){
            file_put_contents("../temp/$db/error-$timestamp.json", $e->errorMessage());
            echo json_encode(["msg" => "Mail kunne ikke sendes til $userEmail"]);
            exit;
        }
    }else{
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
        }catch(Exception $e){
            file_put_contents("../temp/$db/error-$timestamp.json", $e->errorMessage());
            echo json_encode(["msg" => "Mail kunne ikke sendes til $userEmail"]);
            exit;
        }
    }
   
   echo json_encode(["msg" => "Mail sendt til $userEmail"]);
