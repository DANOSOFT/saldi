<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/divFuncs/finishedOrder.php ---------- lap 3.7.7----2019.07.09-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190709 Make function that sets the "Hent bestilling" site for the take away

function getDataFromOrdrelinjerTable($orderId, $ordreNr)
{
	$queryTxt = "select * from ordrelinjer where ordre_id='$orderId'";
	$query = db_select($queryTxt  ,__FILE__ . " linje " . __LINE__);
	$x = 0;
	while($item = db_fetch_array($query)) {
		$productNumber[] = $item['varenr'];
		$number[] = $item['antal'];
		$description[] = $item['beskrivelse'];
		$price[] = $item['pris'];
		$vatPrice[] = $item['vat_price'];
		$x++;
	}
	$queryTxt = "select * from ordrer where ordrenr='$ordreNr'";
	$query = db_select($queryTxt  ,__FILE__ . " linje " . __LINE__);
	while($item = db_fetch_array($query)) {
		$name = $item['lev_navn'];
		$phone = $item['kontakt_tlf'];
		$adress = $item['addr1'];
		$post = $item['postnr'];
		$deliver = $item['lev_addr1'];
		$temp = $item['modtagelse'];
		$received = ($temp == '0') ? "Walk-in" : (($temp == '1') ? "Web" : "Telefon");
		$date = $item['datotid'];
		$time = $item['tidspkt'];
		$receipt = $item['fakturanr'];
		$orderDate = $item['ordredate'];
		$orderTime = $item['tidspkt'];
		$sum = $item['betalt'];
		$city = $item['bynavn'];
		$payedHow = $item['felt_1'];
	}
		return ['prNb' => $productNumber, 'nb' => $number, 'descr' => $description, 'name' => $name,
			'phone' => $phone, 'adress' => $adress, 'post' => $post, 'deliver' => $deliver,
			'receive' => $received, 'x' => $x, 'price' => $price, 'date' => $date, 'time' => $time,
			'receiptNb' => $receipt, 'orderDate' => $orderDate, 'orderTime' => $orderTime,
			'sum' => $sum, 'payed' => $payedHow, 'city' => $city, 'priceWithVat' => $vatPrice];
}

function makeOrderLinePrice($amountArray, $priceArray)
{
	$finalPriceArray = array();
	for ($x=0; $x<sizeof($amountArray); $x++) {
		$finalPriceArray[$x] = $amountArray[$x] * $priceArray[$x];
	}
	return $finalPriceArray;
}

function setDetailedFinishedOrderSite()
{
	$orderNr = $_POST['idButton'];
	$orderId = db_fetch_array(db_select("select id from ordrer where ordrenr='$orderNr'"))['id'];
	$orderArray = getDataFromOrdrelinjerTable($orderId, $orderNr);
	$productNumber = $orderArray['prNb'];
	$productTxt = $orderArray['descr'];
	$amount = $orderArray['nb'];
	$price = $orderArray['price'];
	$priceWithVat = $orderArray['priceWithVat'];
	$orderLinePrice = makeOrderLinePrice($amount, $priceWithVat);

	print "<form name=pos_ordre action=\"pos_ordre.php?id=$id&getOrder=true\" method=\"post\" autocomplete=\"off\">\n";


	print "<html>
			<head>
			<style>
				.backButton {
					width:200px;
					border: none;
					padding: 30px 10px;
					font-size: 25px;
					cursor: pointer;
					border-radius: 6px;
					border: 3px solid #8c8c8c;
					margin-top: 20px;
					margin-bottom: 20px;
				}
				.productTableHeader {
					font-size: 20px;
				}
				.kitchenInfo {
					font-size: 15px;
				}
			</style>
			</head>
			<body>
			
			<h1 style=\"text-align: center;\">Her er en oversigt over den afsluttede ordre med ordre id: $orderNr</h1>
			<h2 style='margin-left: 610px; display: inline-block;'> 
				Kvittering 
			</h2> 
			<h2 style='margin-left: 380px; display: inline-block;'> 
				Køkkenbon 
			</h2>";

	print "<div style='margin-left: 450px'>";

	print "<table cellspacing=\"10\" style=\"border: solid; display: inline-block; float: left; background-color: #F8ECC2;\">
			<tr> 
				<td colspan=\"2\"> 
					<b class='kitchenInfo'> Bonnr: </b> $orderArray[receiptNb] 
				</td>
			</tr>
			<tr> 
				<td colspan=\"2\">
					<b class='kitchenInfo'> Dato: </b> $orderArray[orderDate] 
				</td>
			</tr>
			<tr> 
				<td colspan=\"2\">
					<b class='kitchenInfo'> Kl: </b> $orderArray[orderTime] 
				</td>
			</tr>
			<tr> 
				<td colspan=\"2\">
					<b class='kitchenInfo'> Betalt: </b> $orderArray[sum] DKK
				</td>
			</tr>
			<tr> 
				<td colspan=\"2\">
					<b class='kitchenInfo'> Hvordan: </b> $orderArray[payed]
				</td>
			</tr>";

	print	"<tr>
					<th class=\"productTableHeader\">Varenummer</th>
					<th class=\"productTableHeader\">Stk</th>
					<th class=\"productTableHeader\">Tekst</th>
					<th> </th>
					<th> </th>
					<th> </th>
					<th> </th>
					<th> </th>
					<th> </th>
					<th> </th>
					<th> </th>
					<th> </th>
					<th class=\"productTableHeader\">Beløb</th>
		  	</tr>";


	for ($x = 0; $x < $orderArray['x']; $x++) {
		$amount = number_format($amount[$x], '2', ',', '.');
			print "<tr>
							<td style='text-align: center;'> $productNumber[$x] </td>
							<td> $amount </td>
							<td style='text-align: center;'> $productTxt[$x] </td>
							<td> </td>
							<td> </td>
							<td> </td>
							<td> </td>
							<td> </td>
							<td> </td>
							<td> </td>
							<td> </td>
							<td> </td>
							<td style\"margin-left: 30px;\"> $orderLinePrice[$x] </th>
						</tr>";
		}
	print "</table> <div style='display: inline-block; margin-left: 5px;'></div>";

	print "<table cellspacing='10px' style=\"border: solid; background-color: #F8ECC2; display: inline-block;\">
			<tr> 
				<td colspan=\"2\">
					<b class='kitchenInfo'> Navn: </b> $orderArray[name] 
				</td>
			</tr>	
			<tr>
				<td colspan=\"2\">
					<b class='kitchenInfo'> Tlf: </b> $orderArray[phone]
				</td>
			</tr>
			<tr>
				<td colspan=\"2\">
					<b class='kitchenInfo'> Adresse: </b> $orderArray[adress]
				</td>
			</tr>
			<tr>
				<td colspan=\"2\">
					<b class='kitchenInfo'> Postnr: </b> $orderArray[post]
				</td>
			</tr>
			<tr>
				<td colspan=\"2\">
					<b class='kitchenInfo'> By: </b> $orderArray[city]
				</td>
			</tr>
			<tr>
				<td colspan=\"2\">
					<b class='kitchenInfo'> Leveringsform: </b> $orderArray[deliver]
				</td>
			</tr>
			<tr>
				<td colspan=\"2\">
				<b class='kitchenInfo'> Modtaget via: </b>  $orderArray[receive]
				</td>
			</tr>
			<tr>
				<td colspan=\"2\">
					<b class='kitchenInfo'> Dato: $orderArray[date], tidspunkt: $orderArray[time] </b>
				</td>
			</tr>";


	print "<tr>
				<th class=\"productTableHeader\">Antal</th>
				<th class=\"productTableHeader\">Vare</th>
				<th> </th>
				<th> </th>
				<th> </th>
				<th> </th>
				<th> </th>
				<th> </th>
				<th> </th>
				<th> </th>
				<th> </th>
				<th> </th>
				<th class=\"productTableHeader\">Beløb</th>
			  </tr>";

	for ($x = 0; $x < $orderArray['x']; $x++) {
		print "<tr>
					<td style='text-align: center;'> $amount[$x] </td>
					<td style='text-align: center;'> $productTxt[$x] </td>
					<td> </td>
					<td> </td>
					<td> </td>
					<td> </td>
					<td> </td>
					<td> </td>
					<td> </td>
					<td> </td>
					<td> </td>
					<td> </td>
					<td style=\"margin-left: 30px;\"> $orderLinePrice[$x] </th>
				</tr>";
	}
	print "</table></div></body>";

	print "<div style='text-align: left; margin-left: 550px; display: inline-block;'>
				<button class=\"backButton\" type=\"submit\" name=\"submit\" value=\"receiptReprint\">
					Print kvittering
				</button>
			</div>";

	print "<div style='display: inline-block; margin-left: 5px;'> </div>";

	print "<div style='text-align: center; display: inline-block;'>
				<button class=\"backButton\" type=\"submit\" name=\"submit\" value=\"PrintKitchenReceipt\" style='width: 250px;'>
					Print køkkenbon
				</button>
			</div>";


	print "<div style='display: inline-block; margin-left: 5px;'> </div>";

	print "<div style='text-align: right; display: inline-block;'>
				<button class=\"backButton\" type=\"submit\" name=\"submit\" value=\"Historik\">
					Tilbage
				</button>
			</div>";

	print "<input type=\"hidden\" name=\"orderNr\" id=\"submitId\" value='$orderNr'>";

}








?>
