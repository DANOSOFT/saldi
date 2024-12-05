<?php
    $json = json_encode(["companyId" => "4caf022d-f51f-4c8b-a9c4-614112e97054", "base64EncodedMessage" => "SGVqIG1lZCBkaWch"]);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://ssl8.saldi.dk/laja/debitor/easyUBL.php");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $res = curl_exec($ch);
    curl_close($ch);
?>