<?php



	function showContent($paperflowArray, $contentId)
	{
		#print "<pre>"; print_r($paperflowArray); print "</pre>";
		$lineItems = $paperflowArray['data'][$contentId]['line_items'];
		#print "<pre>"; print_r($lineItems); print "</pre>";
		if (sizeof($lineItems) < 1) {
			print "<div style='margin-top: 20px; font-size: 25px;'>
						Der kan gå op til 24 timer, før man kan se varerne
					</div>";
		} else {
			print "<table class='theShowContentTable'>
					<tr>
						<th class='showContentTable'>Beksrivelse</th>
						<th class='showContentTable'>Antal</th>
						<th class='showContentTable'>Enhedspris</th>
						<th class='showContentTable'>Samlet pris</th>
						<th class='showContentTable'>Eksisterende vare</th>
					</tr>";
			for ($i = 0; $i < sizeof($lineItems); $i++) {
				$content = $lineItems[$i]['fields'];
				foreach ($content as $singleContent) {
					// 						echo '<pre>'; print_r($singleContent); echo '</pre>';
					if ($singleContent['code'] == "description") {
						$description = $singleContent['value'];
					} elseif ($singleContent['code'] == "unit_price") {
						$unitPrice = $singleContent['value'];
					} elseif ($singleContent['code'] == "amount") {
						$amount = $singleContent['value'];
					} elseif ($singleContent['code'] == "quantity") {
						$quantity = $singleContent['value'];
					} elseif ($singleContent['code'] == "article_number") {
						$artNb = $singleContent['value'];
					}
				}
				$creditorValue = checkItemCreditor($paperflowArray['data'][$contentId]['header_fields']);
				if (isset($description) && isset($quantity) && isset($unitPrice) && isset($amount)) {
					$productCheck = db_fetch_array(db_select("select * from varer where beskrivelse = '$description'", __FILE__ . " linje " . __LINE__));
					print "<tr>
												<td class='showContentTable'>$description</td>
												<td class='showContentTable'>$quantity</td>
												<td class='showContentTable'>$unitPrice</td>
												<td class='showContentTable'>$amount</td>";
					if ($creditorValue == false) {
						print "<td class='showContentTable'> Der er ingen kreditor til denne vare </td></tr>";
					} elseif ($unitPrice == 0 && $artNb == "") {
						print "<td class='showContentTable'> Dette er en beskrivelse </td></tr>";
					} elseif (isset($productCheck['id'])) {
						print "<td class='showContentTable'> Varen er oprettet </td></tr>";
					} else {
						print "<td class='showContentTable'> 
									<a class=\"orderCreditLink\" href='ordreliste.php?insertProduct=$i&contentId=$contentId&description=$description&price=$unitPrice&valg=skanBilag'> 
											Klik her for at oprette varen 
									</a>
							</td></tr>";
					}
				}
			}
		}
		print "</table>";
	}

	function checkItemCreditor($dataArray)
	{
		$checkData = checkItemData($dataArray);
		#print "<h1> Check </h1>";
		#print "<pre>"; print_r($checkData); print "</pre>";

		if (count($checkData) > 0) {
			$cvrCheck = db_fetch_array(db_select("select * from adresser where cvrnr = '$checkData[cvr]'", __FILE__ . " linje " . __LINE__));
			$bankAccCheck = db_fetch_array(db_select("select * from adresser where bank_konto = '$checkData[payAccNb]' and bank_reg = '$checkData[payRegNb]'", __FILE__ . " linje " . __LINE__));
			$bankCvrCheck = db_fetch_array(db_select("select * from adresser where bank_konto = '$checkData[payAccNb]' and bank_reg = '$checkData[payRegNb]' and cvrnr = '$checkData[cvr]'", __FILE__ . " linje " . __LINE__));
			#print "<h3> cvr: </h3>"; print_r($cvrCheck);
			#print "<h3> bank: </h3>"; print_r($bankAccCheck);
			#print "<h3> cvr og bank: </h3>"; print_r($bankCvrCheck);
			if (isset($cvrCheck['id']) || isset($bankAccCheck['id']) || isset($bankCvrCheck['id'])) {
				#print "True <br>";
				return true;
			} else {
				#print "False <br>";
				return false;
			}
		} else {
			return false;
		}
	}



	function checkItemData($pdfArray)
	{
		#print "<h2> Check item data </h2>";
		#print "<pre>"; print_r($pdfArray); print "</pre>";
		$returnArray = array();
		foreach($pdfArray as $data) {
			if ($data['code'] == "payment_reg_number") {
				$returnArray['payRegNb'] = $data['value'];
			} elseif ($data['code'] == "payment_account_number") {
				$returnArray['payAccNb'] = $data['value'];
			} elseif ($data['code'] == "company_vat_reg_no") {
				$returnArray['cvr'] = $data['value'];
			}
		}
		#print "<pre>"; print_r($returnArray); print "</pre>";
		return $returnArray;
	}




	function prepareItemInsert($paperflowArray, $insertProduct)
	{
		$i = $insertProduct;
		$contentId = $_GET['contentId'];
		$productArray = $paperflowArray['data'][$contentId]['line_items'][$i]['fields'];
		$theProduct = if_isset($_GET['description']);
		$thePrice = if_isset($_GET['price']);
		//		print "<h3> Product to be inserted: $theProduct </h3>";
		//		print "<h3> Product price: $thePrice </h3>";
	// 		print "<h3> Det er nummer $i </h3>";
		$articleValue = getArticleValue($productArray);
	// 		print "<h3> Article: $articleValue </h3>";
	// 		echo '<pre>'; print_r($productArray); echo '</pre>';
		if (!isset($articleValue) || $articleValue == "") {
			insertProductNumber($thePrice, $theProduct);
		} else {
			insertProduct($articleValue, $thePrice, $theProduct);
		}
	}

	function insertProductNumber($price, $product)
	{
			print "<div id=\"prNbModal\" class=\"modal\">
							<div class=\"modal-content\">
									<span class=\"prNbClose\">&times;</span>
									<h2>
											Denne vare har ikke et vare nummer. Du skal derfor indtaste et nyt vare nummer nedenfor:
									</h2>
									
									<input type=\"text\" id=\"productNumber\" name=\"productNumber\">
									<br><br>
									<a class=\"makeProductButton submitProductNumber\" style=\"padding: 5px 20px;\" href='ordreliste.php?sort=$sort&arrayId=$i&valg=skanBilag$hreftext'> 
											Indsæt
									</a>
	
									<a class=\"makeProductButton\" style=\"padding: 5px 20px;\" href='ordreliste.php?sort=$sort&valg=skanBilag$hreftext'> 
											Stop vare indsættelse
									</a>
									
							</div>
					</div>";

	//	echo '<script> let productPrice = ' . json_encode($price) . '; </script>';
			print "<script>		
								let productPrice = " . json_encode($price) . ";
								let product = " . json_encode($product) . ";
								$(\".prNbClose\").click(function() {
										$(\"#prNbModal\").hide();
								});
								$(\".submitProductNumber\").click(function (){
										let prNb = $(\"#productNumber\").val();
	
										if (!prNb.match(/^[a-zA-Z0-9&_-]+/)) {
												alert(\"Du skal indtaste et vare nummer bestående af tal og bogstaver\");
												return false;
										} else {
												document.cookie = \"productNumber =\" + prNb;
												document.cookie = \"checkProductNumber = true\";																	
												document.cookie = \"productPrice =\" + productPrice;																	
												document.cookie = \"productDescription =\" + product;																	
										}
								});
	
						</script>";
			print "<style>
									.prNbClose {
									  color: #aaaaaa;
									  float: right;
									  font-size: 28px;
									  font-weight: bold;
									}
									.prNbClose:hover,
									.prNbClose:focus {
										color: #000;
										text-decoration: none;
										cursor: pointer;
									}
									a.makeProductButton {
										appearance: button;
										border: solid;
										background-color: #e7e7e7;
										font-size: 12px;
										border-radius: 2px;
										padding: 12px 25px;
										text-decoration: none;
										color: initial;
										cursor: pointer;
								}
	
							</style>";
	}

	function getArticleValue($pdfArray)
	{
		#print "<pre>"; print_r($pdfArray); print "</pre>";
		$returnArray = array();
		foreach($pdfArray as $data) {
			if ($data['code'] == "article_number") {
				return $data['value'];
			}
		}
		#print "<pre>"; print_r($returnArray); print "</pre>";
		return $returnArray;
	}

?>
