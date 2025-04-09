<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- rest_api_client.php --- ver. 2.2.4 --- 2023-06-08 ---
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
// Copyright (c) 2004 - 2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20180302 Tilføjet quickpay ID
// 20180307 Aktiveret 'fakturer';
// 20180403 Fakturerede ordrer skrives i files/ordrlst.txt
// 20180405 Ordrer som allerede ligger i saldi files/ordrlst.txt
// 20180426 Tilføjet shop_fakturanr (order_invoice).
// 20181220 Tilføjet ean under put_new_orders.
// 20181227 Rettet betalingsbet ved. kontosalg mm i forhold til EAN. 	
// 20190129 sætter $ekstra3 til Kontant og $ekstra4 til 0.00
// 20190426 små ændringer i logging og tilføjet $invoice & $shop;
// 20210830 Added '$shop_status' to urltxt.
// 20210831 Added possibility to update orders.current_state
// 20211013 Added update_stock
// 20230607 Added costPrice to update_stock
// 20230608 Seperated costPrice from update_stock

ini_set('display_errors', 0);
date_default_timezone_set('Europe/Copenhagen');
$afd=NULL;

if(!ini_get('allow_url_fopen') ) {
   echo 'allow_url_fopen not enabled<br>';
   exit;
} 
include('inc/saldinfo.php');
$log=fopen("files/log".date("Y-m-d").".txt","a");
fwrite ($log,"\n ---- ".date("Y-m-d H:i:s")." ----\n");
if (in_array($_SERVER['REMOTE_ADDR'],$saldi_ip)) {
	fwrite ($log,__file__." ".__line__." Access from ".$_SERVER['REMOTE_ADDR']."\n");
	fclose($log);
} else {
	fwrite ($log,__file__." ".__line__." ".$_SERVER['REMOTE_ADDR']." illegal\n");
	echo "Illegal IP";
	fclose($log);
	exit;
}
$tmp=date('U')-60*60*24*7;
$tmp=date("Y-m-d H:i:s",$tmp);
$files=array();
$files = scandir('files');
for ($x=0;$x<count($files);$x++) {
	if (substr($files[$x],0,3)=='log' && substr($files[$x],-4)=='.txt') {
		$logdate=str_replace('log','',$files[$x]);
		$logdate=str_replace('.txt','',$logdate);
		if ($logdate < $tmp) {
			unlink("files/$files[$x]");
		}
	}
}

if ((isset($_GET['updateStatus']) && isset($_GET['order_id']) && $_GET['updateStatus']) && $_GET['order_id']) {
	include('inc/connect.php');
	if (is_numeric($_GET['updateStatus']) && is_numeric($_GET['order_id'])) { 
		$qtxt = "update orders set current_state = '$_GET[updateStatus]' where id_order = '$_GET[order_id]'";
		mysqli_query($link,$qtxt) or die(mysqli_error($link));
	}
}
if ((isset($_GET['put_new_orders']) && $_GET['put_new_orders'])) {
	$log=fopen("files/log".date("Y-m-d").".txt","a");
	fwrite ($log,__file__." ".__line__." GET put_new_orders ".$_GET['put_new_orders']."\n");

	include('inc/connect.php');
	include('inc/apifunc.php');

	(isset($_GET['order_id']))?$order_id=$_GET['order_id']:$order_id=NULL;
	(isset($_GET['invoice']))?$invoice=$_GET['invoice']:$invoice=NULL;
	(isset($_GET['shop']))?$shop=$_GET['shop']:$shop=NULL;
	(isset($_GET['from_date']))?$from_date=$_GET['from_date']:$from_date=NULL;
	(isset($_GET['encode']))?$encode=$_GET['encode']:$encode=NULL;
	$ordernr=array();
	
	fwrite ($log,__file__." ".__line__." order_id=$order_id\n");
	fwrite ($log,__file__." ".__line__." invoice=$invoice\n");
	fwrite ($log,__file__." ".__line__." encode=$encode\n");
	if (!$afd && isset($_GET['afd'])) $afd=$_GET['afd']*1;
	$kasse=4;
	fwrite ($log,__file__." ".__line__." afd = $afd ->  from_date = $from_date\n");
	if (!$from_date) {
		$tmp=date("U")-60*60*24*7;
		$from_date=date("Y-m-d H:i:s",$tmp);
	}
	fwrite ($log,__file__." ".__line__." get_orders($from_date,'100',$order_id,$invoice,$shop)\n");
	fclose($log);
	$file=get_orders($from_date,'100',$order_id,$invoice,$shop);
	$log=fopen("files/log".date("Y-m-d").".txt","a");
	fwrite ($log,__line__." $file\n");
	if ($fp=fopen($file,"r")) { # åbner 1. fil. 
		fwrite ($log,__line__." Åbner $file\n");
		$x=0;
		while (!feof($fp)) {
			if ($line=fgets($fp)) { #Trækker filen ind i variabler, linje for linje
				$line=trim($line);
				$line=trim($line,'"'); # fjerner første og sidste '"';
				list($ordernr[$x],$konto_id[$x],$kontakt[$x],$firmanavn[$x],$addr1[$x],$addr2[$x],$bynavn[$x],$postnr[$x],$land[$x],$tlf[$x],$email[$x],
					$lev_kontakt[$x],$lev_firmanavn[$x],$lev_addr1[$x],$lev_addr2[$x],$lev_bynavn[$x],$lev_postnr[$x],$lev_land[$x],$fakt_kontakt[$x],
					$fakt_firmanavn[$x],$fakt_addr1[$x],$fakt_addr2[$x],$fakt_bynavn[$x],$fakt_postnr[$x],$fakt_land[$x],$orderdate[$x],$fakturanr[$x],
					$payment_method[$x],$payment_amount[$x],$quickpay_id[$x],$ean[$x],$shop_status[$x],$valuta[$x],$valutakurs[$x],$ordremoms[$x],$ordresum[$x],
					$vare_id[$x],$varenr[$x],$beskrivelse[$x],$pris[$x],$moms[$x],$antal[$x],$stregkode[$x],$variant[$x])
					=explode('";"',$line);
#					fwrite ($log,__line__." ".$ordernr[$x]."\n");
					$x++;
			}
		}
		if ($x && $orderdate[$x-1]) {
			$fd=fopen('files/fromdate.txt','w');
			fwrite($fd,$orderdate[$x-1]);
			fclose($fd);
		}
		fwrite ($log,__line__." Antal ordrer ".count($ordernr)."\n");
		for ($x=0;$x<count($ordernr);$x++) {# løber gennem variabler. 
			fwrite ($log,__line__." Ordre: $ordernr[$x]\n");
			$cvr[$x]=NULL;
			if ($ean && $payment_method[$x]=='konto') {
				$payment_terms='Netto';
				$payment_days='8';
			} else {
				$payment_terms='Kreditkort';
				$payment_days='0';
			}
		fwrite ($log,__line__." EAN >$ean[$x]<\n");
		fwrite ($log,__line__." Betalingsbet $payment_terms\n");
		fwrite ($log,__line__." Betalingsdage $payment_days\n");
			$ref='Internet';
			$institution[$x]=NULL;
			if (strpos($orderdate[$x],"/")) {
				list($d,$m,$y)=explode("/",$orderdate[$x]);
				$orderdate[$x]=$y."-".$m."-".$d;
			}
			#$saldi_ordre_id[$x]=NULL;	
			$error=NULL;	
			fwrite($log,__line__." Ordre: $ordernr[$x]\n");
			$ordernr[$x]=trim($ordernr[$x]);
			$konto_id[$x]=trim($konto_id[$x]);
			if (!$ordernr[$x] || !is_numeric($ordernr[$x])) $error="Ordrenr $ordernr[$x] not numeric";
			if (!$konto_id[$x] || !is_numeric($konto_id[$x])) $error="Konto ID $konto_id[$x] not numeric";
			if (!$error) {
				if ($x==0 || $ordernr[$x] != $ordernr[$x-1]) {# Hvis ordrenummeret skifter....
					$y=$ordernr[$x];
					$tjekpris[$y]=0;
					$tjekmoms[$y]=0;
					$nettosum[$y]=$ordresum[$x]-$ordremoms[$x];
					$momssum[$y]=$ordremoms[$x];
					$ekstra3='Kontant';
					$ekstra4='0.00';

					$urltxt="action=insert_shop_order&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser);
					$urltxt.="&shop_ordre_id=".urlencode($ordernr[$x])."&shop_status=".urlencode($shop_status[$x])."&shop_fakturanr=".urlencode($fakturanr[$x]);
					$urltxt.="&shop_addr_id=".urlencode($konto_id[$x]);
					$urltxt.="&firmanavn=".urlencode($firmanavn[$x])."&addr1=".urlencode($addr1[$x])."&addr2=".urlencode($addr2[$x]);
					$urltxt.="&postnr=".urlencode($postnr[$x])."&bynavn=".urlencode($bynavn[$x])."&land".urlencode($land[$x]);
					$urltxt.="&tlf=".urlencode($tlf[$x]);
					#$urltxt.="&cvr=".urlencode($cvr[$x])."&$ean=".urlencode($ean[$x])."&institution=".urlencode($institution[$x]);
					$urltxt.="&email=".urlencode($email[$x])."&ref=".urlencode($ref);
					$urltxt.="&nettosum=".urlencode($nettosum[$y])."&momssum=".urlencode($ordremoms[$x]);
					$urltxt.="&kontakt=".urlencode($kontakt[$x])."&lev_firmanavn=".urlencode($lev_firmanavn[$x]);
					$urltxt.="&lev_addr1=".urlencode($lev_addr1[$x])."&lev_addr2=".urlencode($lev_addr2[$x]);
					$urltxt.="&lev_postnr=".urlencode($lev_postnr[$x])."&lev_bynavn=".urlencode($lev_bynavn[$x]); #."&lev_stat=".urlencode($ShippingState[$x]);
					$urltxt.="&lev_land=".urlencode($lev_land[$x]);
					#$urltxt.="&lev_tlf=".urlencode($ShippingPhone[$x])."&lev_email=".urlencode($ShippingEMail[$x]);
					$urltxt.="&lev_kontakt=".urlencode($lev_kontakt[$x])."&betalingsbet=".urlencode($payment_terms);
					$urltxt.="&betalingsdage=".urlencode($payment_days)."&ordredate=".urlencode($orderdate[$x])."&lev_date=".urlencode($orderdate[$x]);
					$urltxt.="&momssats=25&valuta=DKK&valutakurs=100&gruppe=1&afd=$afd&projekt=&ekstra1=$payment_method[$x]&ekstra2=$payment_amount[$x]";
					$urltxt.="&ekstra3=$ekstra3&ekstra4=$ekstra4&ekstra5=$kasse&betalings_id=$quickpay_id[$x]&ean=$ean[$x]";
					($moms[$x])?$urltxt.="&momsfri=":$urltxt.="&momsfri=on";
#					fwrite($log,__line__." ".$serverurl."/rest_api.php?".$urltxt."\n");
					$result = trim(file_get_contents($serverurl."/rest_api.php?".$urltxt));
					$result=str_replace('"','',$result);
					if (is_numeric($result)) $saldi_ordre_id[$x]=$result;
					else {
						fwrite($log,__line__." Order ID $ordernr[$x] failed ($result)\n");
						if (strpos($result,'exists in saldi')) {
							$ordrlst=fopen("files/ordrlst.txt","a");
							fwrite($ordrlst,$ordernr[$x]."\n");
							fclose($ordrlst);
						}
						#cho "Order ID $ordernr[$x] failed ($result)<br>";
					}
#					$tjekpris[$y]+=$pris[$x];
#					$tjekmoms[$y]+=$moms[$x];
				} elseif (isset($saldi_ordre_id[$x-1])) $saldi_ordre_id[$x]=$saldi_ordre_id[$x-1];
				if (isset($saldi_ordre_id[$x]) && $saldi_ordre_id[$x]) {
					$tjekpris[$y]+=$pris[$x];
					$tjekmoms[$y]+=$moms[$x];
#					fwrite($log,__line__." |$varenr[$x]|$beskrivelse[$x]|$antal[$x]|\n");
					$urltxt="action=insert_shop_orderline&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&saldi_ordre_id=".$saldi_ordre_id[$x];
					$urltxt.="&vare_id=".urlencode($vare_id[$x])."&varenr=".urlencode($varenr[$x])."&beskrivelse=".urlencode($beskrivelse[$x])."&antal=".urlencode($antal[$x]);
					$urltxt.="&pris=".urlencode($pris[$x])."&rabat=0";
					($moms[$x])?$urltxt.="&momsfri=":$urltxt.="&momsfri=on";
#					fwrite($log,__line__." ".$serverurl."/rest_api.php?".$urltxt."\n");
					$result = file_get_contents($serverurl."/rest_api.php?".$urltxt);
					fwrite($log,__line__." ". $result ."\n");
				}
				if ((isset($saldi_ordre_id[$x]) && $saldi_ordre_id[$x]) && (!isset($ordernr[$x+1]) || $ordernr[$x] != $ordernr[$x+1])) {
					$urltxt="action=fakturer_ordre&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&saldi_ordre_id=".$saldi_ordre_id[$x]."&udskriv_til=&pos_betaling=on";
	#				fwrite($log,__line__." ".$serverurl."/rest_api.php?".$urltxt."\n");					
					$result = file_get_contents($serverurl."/rest_api.php?".$urltxt);
	#				fwrite($log,__line__." ". $result ."\n");
					$result = json_decode($result, true);
					fwrite($log,__line__." ". $result ."\n");
					$ordrlst=fopen("files/ordrlst.txt","a");
					fwrite($ordrlst,$ordernr[$x]."\n");
					fclose($ordrlst);
				} 
			} else fwrite($log,__line__." Fejl $error\n");
		}
		fclose($fp);
	} else echo "File not found";
	fclose($log);
} elseif (isset($_GET['update_stock']) && isset($_GET['totalStock'])) {
 	if (isset($_GET['stock'])) { 
		$itemNo=$_GET['itemNo'];
		$totalStock=$_GET['totalStock'];
		include('inc/connect.php');
		include('inc/apifunc.php');
		$log=fopen("files/log".date("Y-m-d").".txt","a");
		fwrite ($log,"\n ---- ".date("Y-m-d H:i:s")." ----\n");
		fclose ($log);
		if ($itemNo) update_stock ($itemNo,$totalStock);
	}
} elseif (isset($_GET['costPrice']) && isset($_GET['sku'])) {
  $costPrice = $_GET['costPrice'];
	$sku = $_GET['sku'];
	include('inc/connect.php');
	include('inc/apifunc.php');
	$log=fopen("files/log".date("Y-m-d").".txt","a");
	fwrite ($log,"\n ---- ".date("Y-m-d H:i:s")." ----\n");
	fclose ($log);
	if ($sku && $costPrice) updateCostPrice ($sku,$costPrice);
}

function fetch_from_table($serverurl,$db,$api_key,$saldiuser,$select,$from,$where,$order_by,$limit) { 
	$result = file_get_contents($serverurl."/rest_api.php?action=fetch_from_table&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&select=".urlencode($select)."&from=".urlencode($from)."&where=".urlencode($where)."&order_by=".urlencode($order_by)."&limit=".urlencode($limit));
  $result = json_decode($result, true);
	return $result;
}

function update_table($serverurl,$db,$api_key,$saldiuser,$update,$set,$where) { 
	$result = file_get_contents($serverurl."/rest_api.php?action=update_tablee&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&update=".urlencode($update)."&set=".urlencode($set)."&where=".urlencode($where));
	$result = json_decode($result, true);
	if (!is_numeric($result)) {
		print "error: ".$result;
	} else {
		print "<table border='1'><tbody>";
		print "<td>".$result."</td>";
		print  "</tr>";
		print "</tbody></table>";
	}
	print "<br><a href=\"rest_api_client.php?update=$update&set=$set&where=$where\">Return to query page</a>";
}
function insert_into_table($serverurl,$db,$api_key,$saldiuser,$insert,$fields,$values) {
  $result = file_get_contents($serverurl."/rest_api.php?action=insert_into_table&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&insert=".urlencode($insert)."&fields=".urlencode($fields)."&values=".urlencode($values));
  $result = json_decode($result, true);
	if (!is_numeric($result)) {
		print "error: ".$result;
	} else {
		print "<table border='1'><tbody>";
		print "<td>".$result."</td>";
		print  "</tr>";
		print "</tbody></table>";
	}
	print "<br><a href=\"rest_api_client.php?insert=$insert&fields=$fields&values=$values\">Return to query page</a>";
}
?>
