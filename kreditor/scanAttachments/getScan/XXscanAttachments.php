<?php
echo "scanAttachments<br>";
/*
    $_SESSION['firmanavn'] = if_isset($company_name);
    $_SESSION['cvrnr'] = if_isset($cvrnr);
    $_SESSION['adress'] = if_isset($adress);
    $_SESSION['city'] = if_isset($city);
    $_SESSION['phone'] = if_isset($phone);
    $_SESSION['zip'] = if_isset($zip);

    $request = "https://api.bilagscan.dk/v1/organizations/54595/vouchers"; //27290the 378users id to be used in implementing this?

    $headers = array(
      "Authorization: Bearer 5ce1ec7e3d2752c24fbbc7cecfef4a10752a80ba97f46e16",
      "Accept: application/json",
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $request);
    curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $results= curl_exec($ch);

    $paperflowArray = json_decode($results, true);
    $paperflowLength = count($paperflowArray["data"]);

    echo var_dump($paperflowArray);
*/    
    include("frontpage/frontpageInclude.php");
    include ("showContent/contentInclude.php");
    include ("makeOrder/orderInclude.php");
    include ("makeCreditor/creditorInclude.php");
    include ("showPdf/pdfInclude.php");
    include ("cashDraw/cashDrawInclude.php");

?>
