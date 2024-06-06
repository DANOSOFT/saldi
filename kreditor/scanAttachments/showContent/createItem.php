

<?php

	$productNumber = if_isset($_COOKIE['productNumber']);
	$productNumberCheck = if_isset($_COOKIE['checkProductNumber']);
	$productPrice = if_isset($_COOKIE['productPrice']);
	$product = if_isset($_COOKIE['productDescription']);
	//print "<pre>"; print_r($_COOKIE); print "</pre>";
	//print "<h2> Nb: $productNumber <br> product check: $productNumberCheck <br> price: $productPrice <br> pr: $product </h2>";
	
	if (isset($productNumber) && isset($product) && isset($productPrice) && $productNumberCheck == true) {
			insertProduct($productNumber, $productPrice, $product);
			
			print "<script> 
									document.cookie = \"checkProductNumber = false; expires=Thu, 01 Jan 1970 00:00:00 UTC;\";
									document.cookie = \"productNumber = false; expires=Thu, 01 Jan 1970 00:00:00 UTC;\";
									document.cookie = \"productPrice = false; expires=Thu, 01 Jan 1970 00:00:00 UTC;\";
									document.cookie = \"productDescription = false; expires=Thu, 01 Jan 1970 00:00:00 UTC;\";
						</script>";
	}

	function insertProduct($productNumber, $price, $product)
	{
		print "<h3> Her skal vi til at indsætte varen i databasen, med det indtastede vare nummer $productNumber</h3>";
		print "<h3> Produkt beskrivelse: $product</h3>";
		print "<h3> Kost pris til indsættelse i varer og vare_lev: $price</h3>";

		db_modify("insert into varer (varenr, beskrivelse, kostpris) values 
				  ('$productNumber', '$product', '$price')",__FILE__." linje ".__LINE__);

		$itemId = db_fetch_array(db_select("select id from varer where beskrivelse = '$product'", __FILE__ . " linje " . __LINE__))['id'];

		db_modify("insert into vare_lev (vare_id, lev_varenr , kostpris) values 
				  ('$itemId', '$productNumber', '$price')",__FILE__." linje ".__LINE__);
	}
	
	
?>
