<?php
    // Include the database connection
    @session_start();
    $s_id=session_id();
    include("../includes/connect.php");

    // Retrieving webhook data
    $webhookData = file_get_contents('php://input');

    // create random string adn add to file
   /*  $randomString = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
    file_put_contents("../temp/$randomString.json", $webhookData); */
    
    // get company id and document id from webhook data
    $companyID = $webhookData['companyId'];
    $ublDocumentId = $webhookData['ublDocumentId'];

    // put company id and document id in the database
    $sql = "INSERT INTO `?` (`companyID`, `ublDocumentId`) VALUES ('$companyID', '$ublDocumentId')";
    $res = db_modify($sql, __FILE__ . " linje " . __LINE__)

    // send data to other server
    $url = '?';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $webhookData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);


?>