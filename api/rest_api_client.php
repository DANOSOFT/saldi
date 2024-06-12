<html>
 <body>
<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------dht_saldi_api_client.php---ver. 1.0---2017-02-07--------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2017 DANOSOFT ApS
// ----------------------------------------------------------------------

ini_set('display_errors', 1);

if(!ini_get('allow_url_fopen') ) {
   echo 'allow_url_fopen not enabled<br>';
   exit;
} 

$serverurl=""; #f.eks https://ssl.saldi.dk/api 
$db=''; #Findes under Indstillinger ->  Diverse -> API 
$saldiuser=''; #Findes under Indstillinger ->  Diverse -> API
$api_key=''; #Findes under Indstillinger ->  Diverse -> API
$ftp_url=''; #URL til ftp sted på webshop hvor filer kan hentes og afleveres 
$ftp_user='';
$ftp_pw='';
$ftp_stock_file='lagerfil.csv';
$order_path='orderexport';
$fragt_varenr='A90'; # varenummer i saldi som bruges til fragt.


if (isset($_GET['get_stock']) && $_GET['get_stock']) {
	$select="varenr,beholdning";
	$from="varer";
	if ($_GET['get_stock']=='*') $where='';
	else $where=$_GET['get_stock'];
	$order_by="varenr";
	$limit='';
	$result=fetch_from_table($serverurl,$db,$api_key,$saldiuser,$select,$from,$where,$order_by,$limit);
  if (is_array($result)) {
		if ($ftp_url) $file = fopen ("ftp://$ftp_user:$ftp_pw@$ftp_url/$ftp_stock_file", "w");
		else $file = fopen ("$ftp_stock_file", "w");
		if ($file = fopen ("ftp://$ftp_user:$ftp_pw@$ftp_url/$ftp_stock_file", "w")) {
			$rows=count($result);
			$cols=count($result[0]);
			fwrite ($file,'"'.$result[0][0].'","'.$result[0][1].'"'."\n");
			for ($x=1;$x<$rows;$x++){
				for ($y=0;$y<$cols;$y++) {
					if ($y) {
						fwrite ($file,$result[$x][$y]*1);
					} else fwrite ($file,'"'.$result[$x][$y].'",');
				}
				fwrite ($file,"\n");
			}
			fclose($file);
			echo "Done";
		} else echo "Cannot open stockfile";
	} else echo "$result<br>";
} elseif ((isset($_GET['put_new_orders']) && $_GET['put_new_orders'])) {
	$file=NULL;
	if ($ftp_url) {
		$ftp_id = ftp_connect($ftp_url);
		$ftp_login = ftp_login($ftp_id,$ftp_user,$ftp_pw);
		if ($ftp_contents = ftp_nlist($ftp_id,$order_path."/*.csv")) { #finder filnavne;
			$file = fopen("ftp://$ftp_user:$ftp_pw@$ftp_url/$ftp_contents[0]", "r");
		}
	} else {
		if ($files = glob("$order_path/*.csv")) {
			$file = fopen("$files[0]", "r");
		}	
	}
	if ($file) { # åbner 1. fil. 
		$x=0;
		while (!feof($file)) {
			if ($line=fgets($file)) { #Trækker filen ind i variabler, linje for linje
				$line=trim($line,'"'); # fjerner første og sidste '"';
				list($Ordernr[$x],$Orderdate[$x],$OrderStatus[$x],$PurchasedWebsite[$x],$PaymentMethod[$x],$ShippingMethod[$x],$Subtotal[$x],
					$ShippingCost[$x],$GrandTotal[$x],$TotalTax[$x],$TotalPaid[$x],$TotalRefunded[$x],$ItemName[$x],$ItemSKU[$x],$ItemISBN[$x],
					$ItemStock[$x],$ItemPrice[$x],$CostPrice[$x],$ItemOrdered[$x],$ItemInvoiced[$x],$ItemSent[$x],$CustomerID[$x],$BillingFirstName[$x],
					$BillingLastName[$x],$BillingCompany[$x],$BillingEMail[$x],$BillingPhone[$x],$BillingAddress1[$x],$BillingAddress2[$x],
					$BillingCity[$x],$BillingPostcode[$x],$BillingState[$x],$BillingCountry[$x],$ShippingFirstName[$x],$ShippingLastName[$x],
					$ShippingCompany[$x],$ShippingEMail[$x],$ShippingPhone[$x],$ShippingAddress1[$x],$ShippingAddress2[$x],$ShippingCity[$x],
					$ShippingPostcode[$x],$ShippingState[$x],$ShippingCountry[$x])=explode('","',$line);
				$x++;
			}
		}
		for ($x=0;$x<count($Ordernr);$x++) {# løber gennem variabler. 
			$cvr[$x]=NULL;
			$ean[$x]=NULL;
			$institution[$x]=NULL;
			if (strpos($Orderdate[$x],"/")) {
				list($d,$m,$y)=explode("/",$Orderdate[$x]);
				$Orderdate[$x]=$y."-".$m."-".$d;
			}
			#$saldi_ordre_id[$x]=NULL;	
			$error=NULL;	
			if (!$Ordernr[$x] || !is_numeric($Ordernr[$x])) $error="$Ordernr[$x] not numeric";
			if (!$CustomerID[$x] || !is_numeric($CustomerID[$x])) $error="$CustomerID[$x] not numeric";
			if (!$error) {
				if ($x==0 || $Ordernr[$x] != $Ordernr[$x-1]) {# Hvis ordrenummeret skifter....
					$PaymentMethod[$x]='Kreditkort';
					$urltxt="action=insert_shop_order&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser);
					$urltxt.="&shop_ordre_id=".urlencode($Ordernr[$x])."&shop_addr_id=".urlencode($CustomerID[$x])."&firmanavn=".urlencode($BillingCompany[$x]);
					$urltxt.="&addr1=".urlencode($BillingAddress1[$x])."&addr2=".urlencode($BillingAddress2[$x])."&postnr=".urlencode($BillingPostcode[$x]);
					$urltxt.="&stat=".urlencode($BillingState[$x])."&bynavn=".urlencode($BillingCity[$x])."&land".urlencode($BillingCountry[$x]);
					$urltxt.="&tlf=".urlencode($BillingPhone[$x]);
#					$urltxt.="&cvr=".urlencode($cvr[$x])."&$ean=".urlencode($ean[$x])."&institution=".urlencode($institution[$x]);
					$urltxt.="&email=".urlencode($BillingEMail[$x])."&ref=".urlencode($saldiuser)."&nettosum=".urlencode($GrandTotal[$x]-$TotalTax[$x])."&momssum=".urlencode($TotalTax[$x]);
					$urltxt.="&kontakt=".urlencode($BillingFirstName[$x]." ".$BillingLastName[$x])."&lev_firmanavn=".urlencode($ShippingCompany[$x]);
					$urltxt.="&lev_addr1=".urlencode($ShippingAddress1[$x])."&lev_addr2=".urlencode($ShippingAddress2[$x]);
					$urltxt.="&lev_postnr=".urlencode($ShippingPostcode[$x])."&lev_bynavn=".urlencode($ShippingCity[$x])."&lev_stat=".urlencode($ShippingState[$x]);
					$urltxt.="&lev_land=".urlencode($ShippingCountry[$x])."&lev_tlf=".urlencode($ShippingPhone[$x])."&lev_email=".urlencode($ShippingEMail[$x]);
					$urltxt.="&lev_kontakt=".urlencode($ShippingFirstName[$x]." ".$ShippingLastName[$x])."&betalingsbet=".urlencode($PaymentMethod[$x]);
					$urltxt.="&betalingsdage=0&ordredate=".urlencode($Orderdate[$x])."&lev_date=".urlencode($Orderdate[$x]);
					$urltxt.="&momssats=25&valuta=DKK&valutakurs=100&gruppe=1&afd=0&projekt=&ekstra1=&ekstra2=&ekstra3=&ekstra4=&ekstra5=";
					$result = trim(file_get_contents($serverurl."/rest_api.php?".$urltxt));
					$result=str_replace('"','',$result);
					if (is_numeric($result)) $saldi_ordre_id[$x]=$result;
					else echo "Order ID $Ordernr[$x] failed<br>";
				} elseif (isset($saldi_ordre_id[$x-1])) $saldi_ordre_id[$x]=$saldi_ordre_id[$x-1];
				if (isset($saldi_ordre_id[$x]) && $saldi_ordre_id[$x]) {
					$urltxt="action=insert_shop_orderline&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&saldi_ordre_id=".$saldi_ordre_id[$x];
					$urltxt.="&varenr=".urlencode($ItemSKU[$x])."&beskrivelse=".urlencode($ItemName[$x])."&antal=".urlencode($ItemOrdered[$x]);
					$urltxt.="&pris=".urlencode($ItemPrice[$x])."&rabat=0";
					$result = file_get_contents($serverurl."/rest_api.php?".$urltxt);
					if ($Ordernr[$x] && $fragt_varenr && ($x==count($Ordernr) || ($x<count($Ordernr)-1 && $Ordernr[$x] != $Ordernr[$x+1]))) {
						$urltxt="action=insert_shop_orderline&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&saldi_ordre_id=".$saldi_ordre_id[$x];
						$urltxt.="&varenr=".urlencode($fragt_varenr)."&beskrivelse=Fragt&antal=1";
						$urltxt.="&pris=".urlencode($ShippingCost[$x])."&rabat=0&momsfri=on";
						$result = file_get_contents($serverurl."/rest_api.php?".$urltxt);
					}
				}
			}
		}	
		fclose($file);
		if (count($saldi_ordre_id)>0) { 
			if ($ftp_url)ftp_delete($ftp_id,$ftp_contents[0]);
			else unlink ("$files[0]");
		}
	} else echo "File not found";
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
 </body>
</html> 
