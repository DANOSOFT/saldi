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

echo "$db | paperflowapi.php<br>";

#include("header.php");
include ("get_fields.php");
$docName = $_POST['ToUpload'];
//print_r($file);

# $token = $row['token'];  //10/06/2020
$token = '5ce1ec7e3d2752c24fbbc7cecfef4a10752a80ba97f46e16';
if( $_SESSION['vouc']!=null ){
	$mj= $_SESSION['vouc'];
	$request  = "https://api.bilagscan.dk/v1/organizations/54595/vouchers";
	$headers = array(
		"Authorization: Bearer $token", //get from section id? using javascript or jquery? 54595 
		//'cache-control => no-cache',
		"Content-type => multipart/form-data",
		//"Content-Type: application/json", 
		"Accept:application/json",
	);
	($_SERVER['HTTPS'])?$callback_url = 'HTTPS://':$callback_url = 'HTTP://';
	$callback_url.= $_SERVER['SERVER_NAME'].str_replace('paperflowapi.php','paper_api_call.php',$_SERVER['PHP_SELF']);
	
	$data = array(
		'voucher_data' =>  $mj,
		// 'callback_url' => 'http://ssl8.saldi.dk/saldi/paperpdf/paper_api_call.php'
	);

	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL => "https://api.bilagscan.dk/v1/organizations/54595/vouchers",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_POSTFIELDS => "voucher_data=$mj&callback_url=$callback_url",
		CURLOPT_HTTPHEADER => $headers,
	));

	$response = curl_exec($ch);
	curl_close($ch);
	$g = json_decode($response);
	$voucher_id = $g->data->id;
	//get_fields($results);
	$status =$g->data->status;
	$duplicate = $g->data->duplicate_of_voucher_id;
	$mytxt = "Click to View the voucher";
	$Addr= "getvoucher.php";
	//echo link($mytxt, $Addr);
	//$uploaded_by_user_id = $g->data->status->$uploaded_by_user_id;
	$upload_date =$g->data->created_at;
	// if(http_response_code() !== 200);
	var_dump(http_response_code());
	if(http_response_code()!=200 ) {
		echo "<meta http-equiv=\"refresh\" content=\"180\" >";
	}
	echo "Copy this voucher Id: $voucher_id </br>";
	print "And click the link below to get the voucher information</br>";
	echo "<a href=\"$Addr?voucher_id=$voucher_id\"target=\"new window\">$mytxt</a> </br>";
$qtxt = "insert into ordrer";
$qtxt.= "(art,scan_id,dokument) values ('CS','$voucher_id','$docName')";
echo "$db -> $qtxt<br>"; 
db_modify ($qtxt,__FILE__ . " linje " . __LINE__);

# print "<meta http-equiv=\"refresh\" content=\"0;URL=../kreditor/ordrer.php\">\n"; 

echo  "1 $1,2 $2,3 $3,4 $4<br>";
exit;
	
	
} else {
	print_r($errors);
}
/*
//var_dump($response);
// if($duplicate != null) echo "this has duplicate: $duplicate";

$qtxt =  "INSERT INTO paperflow(voucher_id,  upload_user_id  , upload_date, upload_username ) VALUES ($1,$2,$3,$4)";
        $rcon = pg_query_params($connection, $ins, 
        [
        $voucher_id, $user_id , $upload_date, $upload_username
        ]

        );

        if(!$rcon){
          echo pg_last_error($connection);
        }else{

     

      print "<meta http-equiv=\"refresh\" content=\"0;URL=../paperpdf/papflowselct.php\">\n"; //commented on 10/12/2020 TT

    }


}

*/








/*


var_dump($response);





$mytxt = "Upload A Voucher";
 
$Addr =" paperflowupload.php";



echo "<br>";
echo "<br>";
echo "<br>";
echo "<br>";
echo "<br>";

echo " <a href=\"$Addr\"target=\"new window\">$mytxt </a> </br>";


*/

//var_dump($mj);



#include("footer.php");

?>









