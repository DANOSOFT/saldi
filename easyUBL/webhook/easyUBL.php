<?php
    // Retrieving webhook data
    $webhookData = file_get_contents('php://input');
    // create random string
    $randomString = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
    file_put_contents("../temp/$randomString.json", $webhookData);
    
?>