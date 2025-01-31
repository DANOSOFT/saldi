<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------/kreditor/scanAttachments/getScan/paperflowapi.php----------------lap 3.9.9---2021-02-04 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2016-2021 saldi.dk aps
// ----------------------------------------------------------------------
// 
//Created 8-04-2020

# include("header.php");

($_SERVER['HTTPS'])?$callback_url = 'HTTPS://':$callback_url = 'HTTP://';
$request.= $_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']);

// $request = "http://ssl8.saldi.dk/saldi/paperpdf/paper_api_call.php"; //27290the 378users id to be used in implementing this?



/*$headers = array(
  "Authorization: Bearer 5ce1ec7e3d2752c24fbbc7cecfef4a10752a80ba97f46e16",
  //access_token=a088cef77d61107cd73fa713dc27f8ae3bd8e5cb907b13de&scope=private&token_type=Bearer from postman
  //"cache-control : no-cache",
   //'content-type => multipart/form-data',
  //"Content-Type: application/json", //for callback url?
  
  "Accept: application/json",
);
*/

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $request);
//curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//curl_setopt($ch, CURLOPT_POSTFIELDS, $lo); 
//curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
//curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$results= curl_exec($ch);
var_dump(json_decode($results));
$s = json_decode($results, true);

echo $s;


/*

ini_set('display_errors',1);
// Initiate curl session in a variable (resource)
$curl_handle = curl_init();
//$url = ;
// Set the curl URL option
curl_setopt($curl_handle, CURLOPT_URL, $url);
// This option will return data as a string instead of direct output
curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
// Execute curl & store data in a variable
$curl_data = curl_exec($curl_handle);
curl_close($curl_handle);
// Decode JSON into PHP array
$user_data = json_decode($curl_data);
// Print all data if needed
// print_r($user_data);
// Extract only first 5 user data (or 5 array elements)
var_dump(json_decode($user_data, true));
//$user_data = array_slice($user_data, 0);
// Traverse array and print employee data
foreach ($user_data as $user) {
	echo "name: ".$user->voucher_id;//test this for reponse
	echo "<br />";
	
// }
// from https://tutorialsclass.com/2019/07/15/php-rest-api-get-data-using-curl/

*/

?>
