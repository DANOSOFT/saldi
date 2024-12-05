<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/scanAttachments/paperflowapi.php --- lap 4.0.4 --- 2021-11-25 ---
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
//20210804 Created by LOE
//20211125 PHR Moved to kreditor/scanAttachments and modified

@session_start();
$s_id=session_id();
$title="paperflow api";
$modulnr=9;
$css="../css/std.css";
$bg='nix';

include("../../includes/connect.php");
include("../../includes/online.php");
include("../../includes/std_func.php");

$toUpload = $_FILES['toUpload'];
$docName = $toUpload['name'];
$tmpName = $toUpload['tmp_name'];

//print_r($file);
#cho $_SESSION['content'];
$content = if_isset($_SESSION['content']);

$qtxt="select var_value from settings where var_grp='creditor' and var_name='paperflowId'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$paperflowId = $r['var_value'];
$qtxt="select var_value from settings where var_grp='creditor' and var_name='paperflowBearer'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$bearer = $r['var_value'];

#$token = '5ce1ec7e3d2752c24fbbc7cecfef4a10752a80ba97f46e16';
#if( $_SESSION['vouc']!=null ){
if(  $content && isset($_POST['docName'])) {
#	$mj= $_SESSION['vouc'];
	$docName = $_POST['docName'];
	$request  = "https://api.bilagscan.dk/v1/organizations/$paperflowId/vouchers";
	
	$headers = array(
		"Authorization: Bearer $bearer", //get from section id? using javascript or jquery? $paperflowId 
		//'cache-control => no-cache',
		"Content-type => multipart/form-data",
		//"Content-Type: application/json", 
		"Accept:application/json",
	);
	($_SERVER['HTTPS'])?$callback_url = 'HTTPS://':$callback_url = 'HTTP://';
	$callback_url.= $_SERVER['SERVER_NAME'].str_replace('paperflowapi.php','paper_api_call.php',$_SERVER['PHP_SELF']);
	
	$data = array(
		'voucher_data' =>  $content,
		// 'callback_url' => 'http://ssl8.saldi.dk/saldi/paperpdf/paper_api_call.php'
	);

	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL => "https://api.bilagscan.dk/v1/organizations/$paperflowId/vouchers",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_POSTFIELDS => "voucher_data=$content&callback_url=$callback_url",
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
#cho "Copy this voucher Id: $voucher_id </br>";
#	print "And click the link below to get the voucher information</br>";
#cho "<a href=\"$Addr?voucher_id=$voucher_id\"target=\"new window\">$mytxt</a> </br>";
$qtxt = "insert into paperflow";
$qtxt.= "(voucher_id,upload_user_id,upload_time,document) values ";
$qtxt.= "('$voucher_id','$bruger_id','". date('U') ."','".db_escape_string("$docName")."')";
#cho "$qtxt<br>";
db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
$qtxt = "select id from paperflow where voucher_id = '$voucher_id'";
#cho "$qtxt<br>";
$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$paperflowId = $r['id'];
$qtxt = "insert into ordrer";
$qtxt.= "(art,scan_id,dokument) values ('CS','$paperflowId','".db_escape_string("$docName")."')";
db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
echo "$qtxt<br>";
 print "<meta http-equiv=\"refresh\" content=\"0;URL=../ordreliste.php?valg=skanBilag\">\n"; 

} elseif ($tmpName) {
$docName=basename($_FILES['toUpload']['name']);
$_SESSION['content'] = file_get_contents($_FILES['toUpload']['tmp_name']);
if (!file_exists("../../bilag/")) {
	alert('Bilagsmappe ikke oprettet');
}
if (!file_exists("../../bilag/$db")) {
	mkdir ("../../bilag/$db");
}
if (!file_exists("../../bilag/$db/scan")) {
	mkdir ("../../bilag/$db/scan");
}
$toImport="../../bilag/".$db."/scan/".$docName;
if (move_uploaded_file($tmpName, $toImport)) {
print "<center>"; //this works and displays pdf
print "<embed src='$toImport' type='application/pdf' width='80%' height='80%' /><br><br>"; //this works and displays pdf
print "<form action='paperflowapi.php' method='POST'>";
print "<input type='hidden' name='toImport' value='$toImport'>";
print "<input type='hidden' name='docName' value='$docName'>";
print "<input type='submit' value='Aflæs PDF' name='importAsOrder'>";
print "</form>";
print "<br><a href='../ordreliste.php?valg=skanBilag'><button>Cancel</button></a>";
print "</center>";
#	echo "The file has been uploaded.";
} else {
	echo "Sorry, there was an error uploading your file.";
  return;
}
#$uploaded= "../bilag/$db/$docName";

exit;
	
	
} else {
echo __line__."<br>";
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









