<?php
@session_start();
$s_id=session_id();
$header = "nix";
$bg = "nix";
include("../includes/connect.php");
include("../includes/online.php");
$apiKey = "6c772607-988c-4435-8d78-3670f4a0629d&d5610b95-e39d-4894-8a11-22eb350ed84e";

$query = db_select("SELECT var_value FROM settings WHERE var_name = 'companyID'", __FILE__ . " linje " . __LINE__);
$companyID = db_fetch_array($query)["var_value"];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://easyubl.net/api/Tools/TemporaryKey/$companyID/3");
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', "Authorization: ".$apiKey));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$res = curl_exec($ch);
curl_close($ch);

//var_dump($res);

header("location: https://easyubl.eu/approve/approver?tempKey=$res");