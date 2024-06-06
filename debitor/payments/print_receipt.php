<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- payments/print_receipt.php --- lap 4.1.0 --- 2024.03.01 ---
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
// Copyright (c) 2024-2024 saldi.dk aps
// ----------------------------------------------------------------------

if (isset($_GET['id']))       $id       = $_GET['id'];
if (isset($_GET['filename'])) $filename = $_GET['filename'];

/*
$kasse = $_COOKIE['saldi_pos'];
$qtxt="select box3,box4,box5,box6,box11,box12 from grupper where art = 'POS' and kodenr='2'";
$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
$x=$kasse-1;
$tmp=explode(chr(9),$r['box3']);
$printserver=trim($tmp[$x]);
if (!$printserver)$printserver='localhost';
if ($printserver=='box' || $printserver=='saldibox') {
	$filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
	$printserver=file_get_contents($filnavn);
}
*/
$kasse = 1;
$printserver = "localhost";
$bon = '';
if ($type == 'flatpay') {
  $data=explode('[',file_get_contents($filename));
  $data=explode('","',$data[1]);
  for($i=0;$i<count($data);$i++) {
    if (strpos($data[$i],']')) {
      list($data[$i],$tmp)=explode(']',$data[$i]);
    }
    $data[$i]=trim($data[$i],'"');
    $data[$i]=str_replace('\u00E6','æ',$data[$i]);
    $data[$i]=str_replace('\u00F8','ø',$data[$i]);
    $data[$i]=str_replace('\u00E5','å',$data[$i]);
    $data[$i]=str_replace('\u00C6','Æ',$data[$i]);
    $data[$i]=str_replace('\u00d8','Ø',$data[$i]);
    $data[$i]=str_replace('\u00C5','Å',$data[$i]);
    if ($data[$i]) $bon.= $data[$i]."\n\r";
  }
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
