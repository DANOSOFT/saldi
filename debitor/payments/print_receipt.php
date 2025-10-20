<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- payments/print_receipt.php --- lap 4.1.1 --- 2025.10.07 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2024-2025 saldi.dk aps
// ----------------------------------------------------------------------
// 20251005 PHR some cleanup.

if (isset($_GET['id']))       $id       = $_GET['id'];
if (isset($_GET['filename'])) $filename = $_GET['filename'];

$kasse = $_COOKIE["saldi_pos"];

if (!$printserver) {
$qtxt = "select box3,box4,box5,box6 from grupper where art = 'POS' and kodenr='2' and fiscal_year = '$regnaar'";
#cho "$qtxt<br>";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
$x = $kasse - 1;
$tmp = explode(chr(9), $r['box3']);
$printserver = trim($tmp[$x]);
#cho "$printserver<br>";
if (!$printserver) $printserver = 'localhost';
elseif ($printserver == 'box' || $printserver == 'saldibox') {
	$filnavn = "http://saldi.dk/kasse/" . $_SERVER['REMOTE_ADDR'] . ".ip";
	if ($fp = fopen($filnavn, 'r')) {
		$printserver = trim(fgets($fp));
		fclose($fp);
	}
}
}
if (!$printserver || $printserver == 'box') {
#		print "<BODY onLoad=\"javascript:alert('print_receipt.php $printserver ikke fundet')\">";
    $printserver = 'localhost';
}
$bon = '';
// Get company info from database
$qtxt = "select * from adresser where art = 'S'";
$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));

// Format company header
$txt = $r['firmanavn'];
while (strlen($txt) < 40) $txt = ' '.$txt.' ';
$bon = "$txt\n";

$txt = $r['addr1'];
while (strlen($txt) < 40) $txt = ' '.$txt.' ';
$bon.= "$txt\n";

$txt = $r['postnr'].' '.$r['bynavn'];
while (strlen($txt) < 40) $txt = ' '.$txt.' ';
$bon.= $txt."\n";

$txt = "Cvr: ".$r['cvrnr'];
while (strlen($txt) < 40) $txt = ' '.$txt.' ';
$bon.= $txt."\n";

    // Separator line
$txt = '_';
while (strlen($txt) < 40) $txt.= '_';
$bon.= "$txt\n";

if ($type == 'flatpay') {
  $jsonData = file_get_contents($filename);
  file_put_contents("../../temp/$db/check.txt", "$jsonData\n", FILE_APPEND);

  // Parse JSON data
  $data = json_decode($jsonData, true);

  if ($data && is_array($data)) {

    // Transaction details
/*
    $createdTime = strtotime($data['createdAt']);
    $txt = date('Y-m-d H:i', $createdTime);
*/
    $txt = date('Y-m-d H:i');
    while (strlen("Terminal ".$data['terminalId'].$txt) < 40) $txt = ' '.$txt;
    $bon.= "Terminal ".$data['terminalId'].$txt."\n";

    $bon.= "ID: ".$data['transactionReference']."\n";
    $bon.= "OrderID: ".$id."\n";
    $bon.= $data['cardScheme']." ".$data['cardPan']."\n";
    if ($data['transactionType'] == 'SALE') $bon.= "KØB\n";
    else $bon.= "RETUR\n";
    $bon.= "DKK ".number_format($data['amount']/100, 2, ',', '.')."\n";

    $txt = strtoupper($data['status']);
    while (strlen($txt) < 40) $txt = ' '.$txt.' ';
    $bon.= "$txt\n";

    // Bottom separator
    $txt = '_';
    while (strlen($txt) < 40) $txt.= '_';
    $bon.= "$txt\n";

    file_put_contents("../../temp/$db/bon.txt", $bon, FILE_APPEND);
  }
} else if ($type == 'move3500') {
        $data=file_get_contents($filename);
        # Fix unicode seq
        $data=str_replace('\u00E6','æ',$data);
        $data=str_replace('\u00e6','æ',$data);
        $data=str_replace('\u00F8','ø',$data);
        $data=str_replace('\u00E5','å',$data);
        $data=str_replace('\u00C6','Æ',$data);
        $data=str_replace('\u00d8','Ø',$data);
        $data=str_replace('\u00C5','Å',$data);
        $data=str_replace('\u00c3\u0098','Ø',$data);
        $data=str_replace('\u000e','',$data);
        $bon2 = $data;
        # Strip escape charecters
        $bon2 = str_replace('\n', "\n", $bon2);
        $bon2 = str_replace('\r', "", $bon2);
        $bon2 = str_replace('\f', "", $bon2);

        # Strip first and last char
        if (strlen($bon2) > 2) {
                $bon2 = substr($bon2, 1, -1);
        }
        $bon .= "\n".$bon2;
} else {
  unlink("$directory/check.txt");
  $content=trim(file_get_contents($filename),'{}');
  $contents=explode(',',$content);
  for($i=0;$i<count($contents);$i++) {
		$contents[$i]=str_replace('{','',$contents[$i]);
		$contents[$i]=str_replace('}','',$contents[$i]);
		$contents[$i]=str_replace('"','',$contents[$i]);
file_put_contents("$directory/check.txt","$contents[$i]\n",FILE_APPEND);
		$data[$i]=explode(':',$contents[$i]);
		if (trim($data[$i][0]) == 'id') $r_id = trim($data[$i][1]);
		elseif (trim($data[$i][0]) == 'amount') $r_amount = trim($data[$i][1])/100;
		elseif (trim($data[$i][0]) == 'currency') $r_currency = trim($data[$i][1]);
		elseif (trim($data[$i][0]) == 'paymentIntent') $r_paymentIntent = trim($data[$i][1]);
		elseif (trim($data[$i][2]) == 'amountAuthorized') $r_amountAuthorized = trim($data[$i][3])/100;
		elseif (trim($data[$i][0]) == 'brand') $r_brand = trim($data[$i][1]);
		elseif (trim($data[$i][0]) == 'last4') $r_last4 = trim($data[$i][1]);
		elseif (trim($data[$i][0]) == 'network') $r_network = trim($data[$i][1]);
		elseif (trim($data[$i][0]) == 'readMethod') $r_readMethod = trim($data[$i][1]);
		elseif (trim($data[$i][0]) == 'status') $r_status = trim($data[$i][1]);
		elseif (trim($data[$i][0]) == 'terminalId') $r_terminalId = trim($data[$i][1]);
		elseif (trim($data[$i][0]) == 'accountId') $r_accountId = trim($data[$i][1]);
		elseif (trim($data[$i][0]) == 'updated') $r_updated = substr(trim($data[$i][1]),0,10);
	}
/*
	$qtxt = "select * from adresser where art = 'S'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$txt = $r['firmanavn'];
  while (strlen($txt) < 40) $txt = ' '.$txt.' ';
  $bon = "$txt\n";
	$txt = $r['addr1'];
  while (strlen($txt) < 40) $txt = ' '.$txt.' ';
  $bon.= "$txt\n";
  $txt = $r['postnr'].' '.$r['bynavn'];
  while (strlen($txt) < 40) $txt = ' '.$txt.' ';
  $bon.= $txt."\n";
	$txt = "Cvr: ".$r['cvrnr'];
  while (strlen($txt) < 40) $txt = ' '.$txt.' ';
  $bon.= $txt."\n";
	$txt = '_';
  while (strlen($txt) < 40) $txt.= '_';
 	$bon.= "$txt\n";
*/
	$txt = date('Y-m-d H:i',$r_updated);
  while (strlen("Terminal $r_terminalId".$txt) < 40) $txt = ' '.$txt;
  $bon.= "Terminal $r_terminalId".$txt."\n";
  $bon.= "ID: $r_id / $id\n";
	$bon.= 	"$r_brand **** **** **** $r_last4\n";
	$bon.= "KØB\n";
  $bon.= "$r_currency ".dkdecimal($r_amount)."\n";
  $txt = strtoupper($r_status);
  while (strlen($txt) < 40) $txt = ' '.$txt.' ';
  $bon.= "$txt\n";
  $txt = '_';
  while (strlen($txt) < 40) $txt.= '_';
 	$bon.= "$txt\n";
}
$bon=iconv('UTF-8', 'cp865',$bon);
#file_put_contents($filename,$bon);
file_put_contents("$directory/receipt_$kasse.txt",$bon);
/*
$printfile = 'https://'.$_SERVER['SERVER_NAME'];
$printfile.= str_replace('debitor/payments/save_receipt.php','',$_SERVER['PHP_SELF']);
$printfile.= str_replace('../../','',$filename);
file_put_contents("../../temp/$db/serverfile.txt","$printfile\n");
$printfile=urlencode($printfile);
#print "window.open(\"http://localhost/saldiprint.php?bruger_id=99&bonantal=1&bon=davs&skuffe=0&gem=1','','width=200,height=100\")";
*/
?>
