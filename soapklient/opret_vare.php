<?php

$vare_id=$_GET['vare_id'];
$varenr=$_GET['varenr'];
$beskrivelse=$_GET['beskrivelse'];
$kategori=$_GET['kategori'];
$salgspris=$_GET['salgspris'];
$beholdning=$_GET['beholdning'];

if ($vare_id&&$varenr&&$beskrivelse&&$kategori) {
	include("shop_connect.php");
	
	if (!$r=mysql_fetch_array(mysql_query("select products_id from products where products_model = '$varenr'"))){
		mysql_query("insert into products(products_model,products_date_added,products_status) values ('$varenr','$datotid','1')");
		$r=mysql_fetch_array(mysql_query("select products_id from products where products_model = '$varenr'"));
		$products_id=$r['products_id'];
		mysql_query("insert into products_description(products_id,products_viewed) values ('$products_id','0'");
	} else $products_id=$r['products_id'];
	mysql_query("update products set products_price='$salgspris',products_quantity='$beholdning',products_tax_class_id='$products_tax_class_id' where $products_id='$products_id'");
 	mysql_query("update products_description(products_id='$products_id[$x]',language_id='$language_id',products_name='$beskrivelse[$x]',products_description,='$notes[$x]') where $products_id='$products_id'");
	
}


?>