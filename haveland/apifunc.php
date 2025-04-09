<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------apifunc.php---ver. 2.2.3 ---2023-06-08--------------
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
// 20180403 Medtager kun ordrer som ikke ligger i files/ordrlst.txt 
// 20180406 Firmanavn hentes nu fra customers i stedet for fra address
// 20180426 Tilføjet order_invoice. (Shop fakturanr)
// 20180506 Fjernet UTF8 encoding i 'get orders' 
// 20180509	Rettet lev_postnr til delivery_postcode & lev_city til delivery_city. 
// 20180614	Tilføjet message.message som ordrelinje. 
// 20181220	Tilføjet søgning efter ean i eanpayment (get_orders). 
// 20181220 Rettet $orders_id til $order_id 
// 20181227 div rettelser forhold til EAN. 	
// 20190426 små ændringer i logging og tilføjet $invoice;
// 20190426 $payment_amount sættes nu til $orders_total ved eanpayment dat beløbet ellers står som returbeløb i kassespor.
// 20190905 Rettet 'customer.company' til address.company i function get_orders.
// 20210831 Added '$current_state' to orders.csv
// 20211013 Added function update_stock.
// 20211013 Added update stock_available.quantity and removed update products.quantity & products.out_of_stock from function update_stock.
// 20211021 Extendes logging and return if id_product not found 
// 20211025 Changed id_product lookup from product_attribute to product in function update_stock as I got no result.
// 20211109 out_of_stock is now always set to '2' in function update_stock.
// 20220616 function update_stock Added id_product lookup in 'product_attribute' if not found in 'product' as some items is located there.
// 20220620 function update_stock Added id_product_attribute lookup in 'product_attribute' and id_product_attribute in update. 
// 20220621 function update_stock. stock_avaible product_id with id_product_attribute 0 id now updated to total stock.  
// 20230607 Added costPrice to update_stock
// 20230608 castPrice from update_stock new function updateCostPrice

ini_set('display_errors',0);

function get_orders($from_date,$numbers,$order_id,$invoice,$shop) {
	global $link,$url;
	global $fragt_varenr;
	#$from_date="2018-01-01";

	$orders_id=array();
	
	$log=fopen("files/log".date("Y-m-d").".txt","a");
	fwrite ($log,__file__." ".__line__." orders_id=$order_id, invoice=$invoice,  IsNum = ".is_numeric($order_id)."\n");
	
	if ($invoice) {
		$qtxt="select id_order from order_invoice where number = '$invoice'";
		$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
		if ($r=mysqli_fetch_array($q)) $order_id=$r['id_order'];
	}
	
	$done=array();
	if (file_exists("files/ordrlst.txt") && !$order_id) {
		$x=0;
		$fp=fopen("files/ordrlst.txt","r");
		while ($linje=fgets($fp)){
			if(trim($linje)) {
				$done[$x]=trim($linje);
				$x++;
			}	
		}
		fclose($fp);
	}
	$x=0;
	$nye_ordrer='';
	$qtxt="select orders.id_order,orders.reference,orders.id_customer,orders.current_state,orders.id_currency,";
	$qtxt.="orders.id_address_invoice,orders.id_address_delivery,"; 
	$qtxt.="orders.id_cart,orders.invoice_date,orders.total_paid_tax_excl,orders.total_paid_tax_incl,orders.total_products,"; 
	$qtxt.="orders.total_shipping_tax_excl,orders.total_shipping_tax_incl,"; 
	$qtxt.="address.company,address.firstname,address.lastname,address.address1,address.address2,address.postcode,address.city,";
	$qtxt.="address.id_country,address.phone,address.vat_number,customer.email";
	$qtxt.=" from ";
	$qtxt.="orders,customer,address ";
	$qtxt.=" where ";
	if ($order_id && is_numeric($order_id)) {
		$from_date='2010-01-01';
		$qtxt.="orders.id_order='$order_id' and ";
	}
	$qtxt.="orders.invoice_date >= '$from_date' and orders.id_address_invoice=address.id_address and customer.id_customer=orders.id_customer ";
	fwrite($log,__line__." ".$qtxt."\n"); #20150909
	$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
	while($r=mysqli_fetch_array($q))  {
		if ($x<100 && !in_array($r['id_order'],$done)) {
			fwrite($log,__line__." X $x ".$r['id_order']." ".$r['current_state']."\n"); #20150909
			$orders_id[$x]=$r['id_order'];
			$id_cart[$x]=$r['id_cart'];
			$ordre_ref[$x]=$r['reference'];
			$nye_ordrer.=$orders_id[$x].",";
			$ordre_fornavn[$x]=$r['firstname'];
			$ordre_efternavn[$x]=$r['lastname'];
			$ordre_email[$x]=$r['email'];
#			$orders_sum[$x]=$r['total_paid'];
			$orders_total[$x]=$r['total_paid_tax_incl'];
			//$quickpay_id[$x]=$r['quickpay_id'];
			$orders_tax[$x]=$r['total_paid_tax_incl']-$r['total_paid_tax_excl'];
			$currency[$x]='DKK';
			$currency_value[$x]=100;
			$current_state[$x]=$r['current_state'];
			$customers_id[$x]=$r['id_customer'];
			$konto_id[$x]=$r['id_address_invoice'];
			$lev_konto_id[$x]=$r['id_address_delivery'];
			$customers_company[$x]=$r['company'];
			$fornavn[$x]=$r['firstname'];
			$efternavn[$x]=$r['lastname'];
			$customers_name[$x]=trim($r['firstname']." ".$r['lastname']);
			$customers_street_address[$x]=$r['address1'];
			$customers_suburb[$x]=$r['address2'];
			$customers_postcode[$x]=$r['postcode'];	
			$customers_city[$x]=$r['city'];
			$customers_country_id[$x]=$r['id_country'];
			$customers_telephone[$x]=$r['phone'];
			$total_shipping_tax_excl[$x]=$r['total_shipping_tax_excl'];
			$total_shipping_tax[$x]=$r['total_shipping_tax_incl']-$r['total_shipping_tax_excl'];
#				$cvrnr[$x]=$r['vat_id'];
			$customers_email_address[$x]=$r['email'];
#			$updated_at[$x]=$r['date_upd'];
#			list($invoice_date[$x],$time[$x])=explode(" ",$invoice_date[$x]);
			$invoice_date[$x]=$r['invoice_date'];
			$x++;
		} elseif ($x>100) fwrite($log,__line__." ".$r['id_order']." ".$r['current_state']." ikke medtaget grundet antal > 100\n"); #20150909
	}
	for($x=0;$x<count($orders_id);$x++){
		$qtxt="select number from order_invoice where id_order=$orders_id[$x]";
		$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
		$r=mysqli_fetch_array($q);
		$invoice_number[$x]=$r['number'];
		$qtxt="select name ";
		$qtxt.="from country_lang ";
		$qtxt.="where id_country = $customers_country_id[$x]";
    $q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
		$r=mysqli_fetch_array($q);
		$customers_country[$x]=$r['name'];
		if ($konto_id[$x]!=$lev_konto_id[$x]) {
			$qtxt="select address.company,address.firstname,address.lastname,address.address1,address.address2,address.postcode,address.city,";
			$qtxt.="address.phone,";
			$qtxt.="customer.email,";
			$qtxt.="country_lang.name as country ";
			$qtxt.="from address,customer,country_lang ";
			$qtxt.="where address.id_address='$lev_konto_id[$x]' and customer.id_customer = address.id_customer and country_lang.id_country = address.id_country";
	    $q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
			$r=mysqli_fetch_array($q);
			$delivery_company[$x]=$r['company'];
			$delivery_name[$x]=trim($r['firstname']." ".$r['lastname']);
			$delivery_street_address[$x]=$r['address1'];
			$delivery_suburb[$x]=$r['address2'];
			$delivery_postcode[$x]=$r['postcode'];
			$delivery_city[$x]=$r['city'];
			$delivery_country[$x]=$r['country'];
		} else {
			$delivery_company[$x]=$customers_company[$x];
			$delivery_name[$x]=$customers_name[$x];
			$delivery_street_address[$x]=$customers_street_address[$x];
			$delivery_suburb[$x]=$customers_suburb[$x];
			$delivery_postcode[$x]=$customers_postcode[$x];
			$delivery_city[$x]=$customers_city[$x];
			$delivery_country[$x]=$customers_country[$x];
		}
	}
	for($x=0;$x<count($orders_id);$x++){
		$qtxt="select order_id from quickpay_execution where id_cart='$id_cart[$x]'";
		$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
		$r=mysqli_fetch_array($q);
		$quickpay_id[$x]=$r['order_id'];
		if ($quickpay_id[$x]) {
			$qtxt="select payment_method,amount from order_payment where order_reference = '$ordre_ref[$x]'";
			$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
			$r=mysqli_fetch_array($q);
			$payment_method[$x]=$r['payment_method'];
			$payment_amount[$x]=$r['amount'];
		}
		$qtxt="select ean from eanpayment where id_cart='$id_cart[$x]'";
		$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
		$r=mysqli_fetch_array($q);
		if ($ean[$x]=$r['ean']) {
			$payment_method[$x]='konto';
			$payment_amount[$x]=$orders_total[$x];
		}
	}
	fwrite($log,__line__." ".$nye_ordrer."\n"); #20150909
	if (file_exists('files/orders.csv') && filesize("files/orders.csv")) {
		$fp=fopen('files/orders.csv','r');
		$tmp=fread($fp,filesize("files/orders.csv"));
		fclose ($fp);
		$fp=fopen('files/orders.log','a');
		fwrite($fp,"$tmp\n");
		fclose ($fp);
	}
	$fp=fopen('files/orders.csv','w');
	for ($x=0;$x<count($orders_id);$x++) {
		$z=0;
		$qtxt="select order_detail.product_id,order_detail.product_ean13,order_detail.product_name,order_detail.unit_price_tax_excl,";
		$qtxt.="order_detail.unit_price_tax_incl,order_detail.product_quantity,order_detail.product_reference ";
		$qtxt.="from order_detail where order_detail.id_order='$orders_id[$x]'";
		fwrite ($log,__line__. "$qtxt\n");
		$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));;
		if (!$q) {
			fwrite($log,__line__." " .mysqli_error($link)."\n");
			exit();
		}
		while($r=mysqli_fetch_array($q)) {
			fwrite($log,__line__. "$r[product_reference] -> $r[product_name]\n");
			$products_id[$x][$z]=$r['product_id'];
			$products_model[$x][$z]=$r['product_reference'];
			$products_name[$x][$z]=$r['product_name'];
			$products_price[$x][$z]=$r['unit_price_tax_excl']*1;
			$products_tax[$x][$z]=$r['unit_price_tax_incl']-$r['unit_price_tax_excl'];
			$products_quantity[$x][$z]=$r['product_quantity']*1;
			$products_ean[$x][$z]=$r['product_ean13']*1;
			$products_variation[$x][$z]=0;
			$medtag[$x][$z]=1;
			$txt="\"$orders_id[$x]\";\"$customers_id[$x]\";\"$customers_name[$x]\";\"$customers_company[$x]\";\"$customers_street_address[$x]\";";
			$txt.="\"$customers_suburb[$x]\";\"$customers_city[$x]\";\"$customers_postcode[$x]\";\"$customers_country[$x]\";\"$customers_telephone[$x]\";";
			$txt.="\"$customers_email_address[$x]\";\"$delivery_name[$x]\";\"$delivery_company[$x]\";\"$delivery_street_address[$x]\";";
			$txt.="\"$delivery_suburb[$x]\";\"$delivery_city[$x]\";\"$delivery_postcode[$x]\";\"$delivery_country[$x]\";\"$customers_name[$x]\";";
			$txt.="\"$customers_company[$x]\";\"$customers_street_address[$x]\";\"$customers_suburb[$x]\";\"$customers_city[$x]\";";
			$txt.="\"$customers_postcode[$x]\";\"$customers_country[$x]\";\"$invoice_date[$x]\";\"$invoice_number[$x]\";\"$payment_method[$x]\";\"$payment_amount[$x]\";\"$quickpay_id[$x]\";\"$ean[$x]\";\"$current_state[$x]\";";
			$txt.="\"$currency[$x]\";\"$currency_value[$x]\";\"$orders_tax[$x]\";\"$orders_total[$x]\";\"\";\"".$products_model[$x][$z]."\";";
			$txt.="\"".$products_name[$x][$z]."\";\"".$products_price[$x][$z]."\";\"".$products_tax[$x][$z]."\";\"".$products_quantity[$x][$z]."\";";
			$txt.="\"".$products_ean[$x][$z]."\";\"".$products_variation[$x][$z]."\"";
			$z++;
			fwrite($fp,$txt."\n");
		}
		if ($total_shipping_tax_excl[$x]) {
			$txt="\"$orders_id[$x]\";\"$customers_id[$x]\";\"$customers_name[$x]\";\"$customers_company[$x]\";\"$customers_street_address[$x]\";";
			$txt.="\"$customers_suburb[$x]\";\"$customers_city[$x]\";\"$customers_postcode[$x]\";\"$customers_country[$x]\";\"$customers_telephone[$x]\";";
			$txt.="\"$customers_email_address[$x]\";\"$delivery_name[$x]\";\"$delivery_company[$x]\";\"$delivery_street_address[$x]\";";
			$txt.="\"$delivery_suburb[$x]\";\"$delivery_city[$x]\";\"$delivery_postcode[$x]\";\"$delivery_country[$x]\";\"$customers_name[$x]\";";
			$txt.="\"$customers_company[$x]\";\"$customers_street_address[$x]\";\"$customers_suburb[$x]\";\"$customers_city[$x]\";";
			$txt.="\"$customers_postcode[$x]\";\"$customers_country[$x]\";\"$invoice_date[$x]\";\"$invoice_number[$x]\";\"$payment_method[$x]\";\"$payment_amount[$x]\";\"$quickpay_id[$x]\";\"$ean[$x]\";\"$current_state[$x]\";";
			$txt.="\"$currency[$x]\";\"$currency_value[$x]\";\"$orders_tax[$x]\";\"$orders_total[$x]\";\"\";\"".$fragt_varenr."\";";
			$txt.="\"Fragt\";\"".$total_shipping_tax_excl[$x]."\";\"".$total_shipping_tax[$x]."\";\"1\";";
			$txt.="\"\";\"0\"";
			fwrite($fp,$txt."\n");
		} 
		$qtxt="select message ";
		$qtxt.="from message where id_order='$orders_id[$x]'";
		$q=mysqli_query($link,$qtxt);
		if (!$q) {
			fwrite($log,__line__." " .mysqli_error($link)."\n");
			exit();
		}
		while($r=mysqli_fetch_array($q)) {
			$products_id[$x][$z]=0;
			$products_model[$x][$z]='';
			$products_name[$x][$z]=$r['message'];
			$products_price[$x][$z]='0';
			$products_tax[$x][$z]='0';
			$products_quantity[$x][$z]='0';
			$products_ean[$x][$z]='';
			$products_variation[$x][$z]=0;
			$medtag[$x][$z]=1;
			$txt="\"$orders_id[$x]\";\"$customers_id[$x]\";\"$customers_name[$x]\";\"$customers_company[$x]\";\"$customers_street_address[$x]\";";
			$txt.="\"$customers_suburb[$x]\";\"$customers_city[$x]\";\"$customers_postcode[$x]\";\"$customers_country[$x]\";\"$customers_telephone[$x]\";";
			$txt.="\"$customers_email_address[$x]\";\"$delivery_name[$x]\";\"$delivery_company[$x]\";\"$delivery_street_address[$x]\";";
			$txt.="\"$delivery_suburb[$x]\";\"$delivery_city[$x]\";\"$delivery_postcode[$x]\";\"$delivery_country[$x]\";\"$customers_name[$x]\";";
			$txt.="\"$customers_company[$x]\";\"$customers_street_address[$x]\";\"$customers_suburb[$x]\";\"$customers_city[$x]\";";
			$txt.="\"$customers_postcode[$x]\";\"$customers_country[$x]\";\"$invoice_date[$x]\";\"$invoice_number[$x]\";\"$payment_method[$x]\";\"$payment_amount[$x]\";\"$quickpay_id[$x]\";\"$ean[$x]\";\"$current_state[$x]\";";
			$txt.="\"$currency[$x]\";\"$currency_value[$x]\";\"$orders_tax[$x]\";\"$orders_total[$x]\";\"\";\"".$products_model[$x][$z]."\";";
			$txt.="\"".$products_name[$x][$z]."\";\"".$products_price[$x][$z]."\";\"".$products_tax[$x][$z]."\";\"".$products_quantity[$x][$z]."\";";
			$txt.="\"".$products_ean[$x][$z]."\";\"".$products_variation[$x][$z]."\"";
			$z++;
			fwrite($fp,$txt."\n");
		}
	}
	fwrite ($log,__file__." ".__line__." Returnerer files/orders.csv\n");
	fclose ($log);
	fclose ($fp);
	return('files/orders.csv');
}

function get_products($products_id=null,$shop=null) {
	global $link;

	$log=fopen("files/log".date("Y-m-d").".txt","a");

	$gruppe=2; #Products group in Saldi
	$att_vare_id=array();
	$att_varenr=array();
	$att_stregkode=array();
	$x=0;
	
	$qtxt="select id_product,reference,ean13 ";
	$qtxt.="from product_attribute ";
	$qtxt.="where id_product > '0' and reference != '' ";
	$qtxt.="order by id_product,reference";
	$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
	if (!$q) {
		echo mysqli_error($link)."<br>";
		fwrite($log,__line__." " .mysqli_error($link)."\n");
		exit();
	}
	while ($r=mysqli_fetch_array($q)) {
		$att_vare_id[$x]=$r['id_product'];
		$att_varenr[$x]=$r['reference'];
		$att_stregkode[$x]=$r['ean13'];
		$x++;
	}
	
	$qtxt="select product.id_product,product.price,product.reference,product.ean13,product_lang.name,specific_price.reduction "; 
	$qtxt.="from product,product_lang,specific_price ";
	$qtxt.="where product_lang.id_product = product.id_product and specific_price.id_product = product.id_product";
	$qtxt.=" order by product.reference";
	fwrite ($log,__LINE__." GET products_id ".$_GET['products_id']."\n");
	$linje=NULL;
	$x=0;
	fwrite ($log,__LINE__." $qtxt\n");
	$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
	if (!$q) {
		echo mysqli_error($link)."<br>";
		fwrite($log,__line__." " .mysqli_error($link)."\n");
		exit();
	}
	while ($r=mysqli_fetch_array($q)) {
		if (in_array($r['id_product'],$att_vare_id)) {
			for ($a=0;$a<count($att_vare_id);$a++) {
				if ($r['id_product']==$att_vare_id[$a]) {
					$varenr[$x]=$att_varenr[$a];
					$stregkode[$x]=$att_stregkode[$a];
					$beskrivelse[$x]=$r['name']." ".$varenr[$x];
					$salgspris[$x]=$r['price']-$r['reduction'];
					$x++;
				}
			} 
		} elseif ($r['reference']) {
			$vare_id[$x]=$r['id_product'];
			$varenr[$x]=$r['reference'];
			$stregkode[$x]=$r['ean13'];
			$salgspris[$x]=$r['price'];
			$beskrivelse[$x]=$r['name'];
			$x++;
		}
	}
	$fp=fopen("files/shop_products.csv","w");
	fwrite ($fp,'"vare_id";"varenr";"stregkode";"salgspris";"beskrivelse";"gruppe";"rabat";"notat"'."\n");
	for ($x=0;$x<count($vare_id);$x++) {
		if ($varenr[$x]) {
			$txt="\"\";\"$varenr[$x]\";\"$stregkode[$x]\";\"".$salgspris[$x]."\";\"".$beskrivelse[$x]."\";\"2\";\"0\";\"\"";
			fwrite ($fp,$txt."\n");
		}
	}
	fwrite($log,"$x varer skrevet til shop_products.csv\n");
	fclose ($fp);
	fclose ($log);
	return("files/shop_products.csv");
}
function update_stock($itemNo,$qty) {
	global $link;
	$log=fopen("files/log".date("Y-m-d").".txt","a");
	fwrite ($log,date("Y-m-d H:i")."\n");
	fwrite ($log,__line__." update_stock ($itemNo,$qty)\n");
	
	$id_product = 0;
	$qtxt="select id_product ";
	$qtxt.="from product ";
	$qtxt.="where reference = '$itemNo'";
	fwrite($log,__line__." ".$qtxt."\n");
	$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
	if (!$q) {
		echo mysqli_error($link)."<br>";
		fwrite($log,__line__." " .mysqli_error($link)."\n");
		exit();
	}
	if ($r=mysqli_fetch_array($q)) {
		$id_product=$r['id_product'];
		$id_product_attribute = 0;
		fwrite($log,__line__." Item No: $itemNo is id_product $id_product in table product\n");
	}  
	if (!$id_product) {
		$qtxt="select id_product, id_product_attribute ";
		$qtxt.="from product_attribute ";
		$qtxt.="where reference = '$itemNo'";
		fwrite($log,__line__." ".$qtxt."\n");
		$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
		if (!$q) {
			echo mysqli_error($link)."<br>";
			fwrite($log,__line__." " .mysqli_error($link)."\n");
			exit();
		}
		if ($r=mysqli_fetch_array($q)) {
			$id_product=$r['id_product'];
			$id_product_attribute=$r['id_product_attribute'];
			if (!$id_product_attribute) $id_product_attribute = 0;
			fwrite($log,__line__." Item No: $itemNo is id_product $id_product and id_product_attribute $id_product_attribute in table product_attribute\n");
		} 
	}
	if (!$id_product) {
		fwrite($log,__line__." Item No: $itemNo not found\n");
		return("Item No: $itemNo not found");
	} 
	
	$qtxt = "select quantity from stock_available where id_product='$id_product' ";
	$qtxt.= "and id_product_attribute = '$id_product_attribute'";
	fwrite($log,__line__." ".$qtxt."\n");
	$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
	if (!$q) {
		echo mysqli_error($link)."<br>";
		fwrite($log,__line__." " .mysqli_error($link)."\n");
		exit();
	}
	if ($r=mysqli_fetch_array($q)) {
		fwrite($log,__line__." Existing stock ".$r['quantity']."\n");
		$existing_stock=$r['quantity'];
		if ($qty != $existing_stock) {
			$qtxt="update stock_available set quantity='$qty' where id_product='$id_product' and id_product_attribute = '$id_product_attribute'";
			fwrite($log,__line__." ".$qtxt."\n");
			mysqli_query($link,$qtxt);
			if (mysqli_error($link)) {	
				fwrite($log,__line__." ".mysqli_error($link)."\n");
				exit;
			}
			$qtxt = "UPDATE stock_available SET out_of_stock = '2' where ";
			$qtxt.= "id_product='$id_product' and id_product_attribute = '$id_product_attribute'";
			fwrite($log,__line__." ".$qtxt."\n");
			mysqli_query($link,$qtxt);
			if (mysqli_error($link)) {	
				fwrite($log,__line__." ".mysqli_error($link)."\n");
				exit;
			}
		} 
		if ($id_product_attribute) {
			$qtxt = "SELECT sum(quantity) as total_qty from stock_available where id_product='$id_product' ";
			$qtxt.= "and id_product_attribute != '0'";
			fwrite($log,__line__." ".$qtxt."\n");
			$q = mysqli_query($link,$qtxt);
			if (mysqli_error($link)) {	
				fwrite($log,__line__." ".mysqli_error($link)."\n");
				exit;
			}
			if ($r=mysqli_fetch_array($q)) {
				$totalQty=$r['total_qty'];
				$qtxt="update stock_available set quantity='$totalQty' where id_product='$id_product' and id_product_attribute = '0'";
				fwrite($log,__line__." ".$qtxt."\n");
				mysqli_query($link,$qtxt);
				if (mysqli_error($link)) {	
					fwrite($log,__line__." ".mysqli_error($link)."\n");
					exit;
				}
			}
		}
	} else fwrite($log,__line__." id_product: '$id_product' Not found\n");
	fclose($log);
	return ('OK');
	exit;
#	}
}
function updateCostPrice($itemNo,$costPrice) {
	global $link;
	$log=fopen("files/log".date("Y-m-d").".txt","a");
	fwrite ($log,date("Y-m-d H:i")."\n");
	fwrite ($log,__line__." update_cost ($itemNo,$costPrice)\n");

	$id_product = 0;
	$qtxt="select id_product ";
	$qtxt.="from product ";
	$qtxt.="where reference = '$itemNo'";
	fwrite($log,__line__." ".$qtxt."\n");
	$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
	if (!$q) {
		echo mysqli_error($link)."<br>";
		fwrite($log,__line__." " .mysqli_error($link)."\n");
		exit();
	}
	if ($r=mysqli_fetch_array($q)) {
		$id_product=$r['id_product'];
		$id_product_attribute = 0;
		fwrite($log,__line__." Item No: $itemNo is id_product $id_product in table product\n");
	}
	if (!$id_product) {
		$qtxt="select id_product, id_product_attribute ";
		$qtxt.="from product_attribute ";
		$qtxt.="where reference = '$itemNo'";
		fwrite($log,__line__." ".$qtxt."\n");
		$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
		if (!$q) {
			echo mysqli_error($link)."<br>";
			fwrite($log,__line__." " .mysqli_error($link)."\n");
			exit();
		}
		if ($r=mysqli_fetch_array($q)) {
			$id_product=$r['id_product'];
			$id_product_attribute=$r['id_product_attribute'];
			if (!$id_product_attribute) $id_product_attribute = 0;
			fwrite($log,__line__." Item No: $itemNo is id_product $id_product and id_product_attribute $id_product_attribute in table product_attribute\n");
		}
	}
	if (!$id_product) {
		fwrite($log,__line__." Item No: $itemNo not found\n");
		return("Item No: $itemNo not found");
	}
	if ($costPrice) {
		$qtxt = "SELECT wholesale_price from product_shop where id_product='$id_product'";
		fwrite($log,__line__." ".$qtxt."\n");
		$q = mysqli_query($link,$qtxt);
		if (mysqli_error($link)) {
			fwrite($log,__line__." ".mysqli_error($link)."\n");
			exit;
		}
		if ($r=mysqli_fetch_array($q)) {
			fwrite($log,__line__." ".$r['wholesale_price']." != $costPrice\n");
			if ($r['wholesale_price'] != $costPrice) {
				$qtxt="update product_shop set wholesale_price='$costPrice' where id_product='$id_product'";
				fwrite($log,__line__." ".$qtxt."\n");
				mysqli_query($link,$qtxt);
				if (mysqli_error($link)) {
					fwrite($log,__line__." ".mysqli_error($link)."\n");
					exit;
				}
			} else {
				fwrite($log,__line__." Cost $costPrice not changed\n");
			}
		}
	} else fwrite($log,__line__." id_product: '$id_product' Not found\n");
	fclose($log);
	return ('OK');
	exit;
}

// make a function for updating stregkode in product_attribute
function updateEAN($itemNo,$ean) {
	global $link;
	$log=fopen("files/log".date("Y-m-d").".txt","a");
	fwrite ($log,date("Y-m-d H:i")."\n");
	fwrite ($log,__line__." update_ean ($itemNo,$ean)\n");

	$id_product = 0;
	$qtxt="select id_product ";
	$qtxt.="from product ";
	$qtxt.="where reference = '$itemNo'";
	fwrite($log,__line__." ".$qtxt."\n");
	$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
	if (!$q) {
		echo mysqli_error($link)."<br>";
		fwrite($log,__line__." " .mysqli_error($link)."\n");
		exit();
	}
	if ($r=mysqli_fetch_array($q)) {
		$id_product=$r['id_product'];
		$id_product_attribute = 0;
		fwrite($log,__line__." Item No: $itemNo is id_product $id_product in table product\n");
	}
	if (!$id_product) {
		$qtxt="select id_product, id_product_attribute ";
		$qtxt.="from product_attribute ";
		$qtxt.="where reference = '$itemNo'";
		fwrite($log,__line__." ".$qtxt."\n");
		$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
		if (!$q) {
			echo mysqli_error($link)."<br>";
			fwrite($log,__line__." " .mysqli_error($link)."\n");
			exit();
		}
		if ($r=mysqli_fetch_array($q)) {
			$id_product=$r['id_product'];
			$id_product_attribute=$r['id_product_attribute'];
			if (!$id_product_attribute) $id_product_attribute = 0;
			fwrite($log,__line__." Item No: $itemNo is id_product $id_product and id_product_attribute $id_product_attribute in table product_attribute\n");
		}
	}
	if (!$id_product) {
		fwrite($log,__line__." Item No: $itemNo not found\n");
		return("Item No: $itemNo not found");
	}
	if ($ean) {
		$qtxt = "SELECT ean13 from product where id_product='$id_product'";
		fwrite($log,__line__." ".$qtxt."\n");
		$q = mysqli_query($link,$qtxt);
		if (mysqli_error($link)) {
			fwrite($log,__line__." ".mysqli_error($link)."\n");
			exit;
		}
		if ($r=mysqli_fetch_array($q)) {
			fwrite($log,__line__." ".$r['ean13']." != $ean\n");
			if ($r['ean13'] != $ean) {
				$qtxt="update product set ean13='$ean' where id_product='$id_product'";
				fwrite($log,__line__." ".$qtxt."\n");
				mysqli_query($link,$qtxt);
				if (mysqli_error($link)) {
					fwrite($log,__line__." ".mysqli_error($link)."\n");
					exit;
				}
			} else {
				fwrite($log,__line__." EAN $ean not changed\n");
			}
		}
	} else fwrite($log,__line__." id_product: '$id_product' Not found\n");
	fclose($log);
	return ('OK');
	exit;
}

// make a function for updating price in product_attribute

function updatePrice($itemNo,$price) {
	global $link;
	$log=fopen("files/log".date("Y-m-d").".txt","a");
	fwrite ($log,date("Y-m-d H:i")."\n");
	fwrite ($log,__line__." update_price ($itemNo,$price)\n");

	$id_product = 0;
	$qtxt="select id_product ";
	$qtxt.="from product ";
	$qtxt.="where reference = '$itemNo'";
	fwrite($log,__line__." ".$qtxt."\n");
	$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
	if (!$q) {
		echo mysqli_error($link)."<br>";
		fwrite($log,__line__." " .mysqli_error($link)."\n");
		exit();
	}
	if ($r=mysqli_fetch_array($q)) {
		$id_product=$r['id_product'];
		$id_product_attribute = 0;
		fwrite($log,__line__." Item No: $itemNo is id_product $id_product in table product\n");
	}
	if (!$id_product) {
		$qtxt="select id_product, id_product_attribute ";
		$qtxt.="from product_attribute ";
		$qtxt.="where reference = '$itemNo'";
		fwrite($log,__line__." ".$qtxt."\n");
		$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
		if (!$q) {
			echo mysqli_error($link)."<br>";
			fwrite($log,__line__." " .mysqli_error($link)."\n");
			exit();
		}
		if ($r=mysqli_fetch_array($q)) {
			$id_product=$r['id_product'];
			$id_product_attribute=$r['id_product_attribute'];
			if (!$id_product_attribute) $id_product_attribute = 0;
			fwrite($log,__line__." Item No: $itemNo is id_product $id_product and id_product_attribute $id_product_attribute in table product_attribute\n");
		}
	}
	if (!$id_product) {
		fwrite($log,__line__." Item No: $itemNo not found\n");
		return("Item No: $itemNo not found");
	}
	if ($price) {
		$qtxt = "SELECT price from product where id_product='$id_product'";
		fwrite($log,__line__." ".$qtxt."\n");
		$q = mysqli_query($link,$qtxt);
		if (mysqli_error($link)) {
			fwrite($log,__line__." ".mysqli_error($link)."\n");
			exit;
		}
		if ($r=mysqli_fetch_array($q)) {
			fwrite($log,__line__." ".$r['price']." != $price\n");
			if ($r['price'] != $price) {
				$qtxt="update product set price='$price' where id_product='$id_product'";
				fwrite($log,__line__." ".$qtxt."\n");
				mysqli_query($link,$qtxt);
				if (mysqli_error($link)) {
					fwrite($log,__line__." ".mysqli_error($link)."\n");
					exit;
				}
			} else {
				fwrite($log,__line__." Price $price not changed\n");
			}
		}
	} else fwrite($log,__line__." id_product: '$id_product' Not found\n");
	fclose($log);
	return ('OK');
	exit;
}

// update wieght in product_attribute

function updateWeight($itemNo,$weight) {
	global $link;
	$log=fopen("files/log".date("Y-m-d").".txt","a");
	fwrite ($log,date("Y-m-d H:i")."\n");
	fwrite ($log,__line__." update_weight ($itemNo,$weight)\n");

	$id_product = 0;
	$qtxt="select id_product ";
	$qtxt.="from product ";
	$qtxt.="where reference = '$itemNo'";
	fwrite($log,__line__." ".$qtxt."\n");
	$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
	if (!$q) {
		echo mysqli_error($link)."<br>";
		fwrite($log,__line__." " .mysqli_error($link)."\n");
		exit();
	}
	if ($r=mysqli_fetch_array($q)) {
		$id_product=$r['id_product'];
		$id_product_attribute = 0;
		fwrite($log,__line__." Item No: $itemNo is id_product $id_product in table product\n");
	}
	if (!$id_product) {
		$qtxt="select id_product, id_product_attribute ";
		$qtxt.="from product_attribute ";
		$qtxt.="where reference = '$itemNo'";
		fwrite($log,__line__." ".$qtxt."\n");
		$q=mysqli_query($link,$qtxt) or die(mysqli_error($link));
		if (!$q) {
			echo mysqli_error($link)."<br>";
			fwrite($log,__line__." " .mysqli_error($link)."\n");
			exit();
		}
		if ($r=mysqli_fetch_array($q)) {
			$id_product=$r['id_product'];
			$id_product_attribute=$r['id_product_attribute'];
			if (!$id_product_attribute) $id_product_attribute = 0;
			fwrite($log,__line__." Item No: $itemNo is id_product $id_product and id_product_attribute $id_product_attribute in table product_attribute\n");
		}
	}
	if (!$id_product) {
		fwrite($log,__line__." Item No: $itemNo not found\n");
		return("Item No: $itemNo not found");
	}
	if ($weight) {
		$qtxt = "SELECT weight from product where id_product='$id_product'";
		fwrite($log,__line__." ".$qtxt."\n");
		$q = mysqli_query($link,$qtxt);
		if (mysqli_error($link)) {
			fwrite($log,__line__." ".mysqli_error($link)."\n");
			exit;
		}
		if ($r=mysqli_fetch_array($q)) {
			fwrite($log,__line__." ".$r['weight']." != $weight\n");
			if ($r['weight'] != $weight) {
				$qtxt="update product set weight='$weight' where id_product='$id_product'";
				fwrite($log,__line__." ".$qtxt."\n");
				mysqli_query($link,$qtxt);
				if (mysqli_error($link)) {
					fwrite($log,__line__." ".mysqli_error($link)."\n");
					exit;
				}
			} else {
				fwrite($log,__line__." Weight $weight not changed\n");
			}
		}
	} else fwrite($log,__line__." id_product: '$id_product' Not found\n");
	fclose($log);
	return ('OK');
	exit;
}

?> 
