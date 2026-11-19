<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//--- includes/orderFuncIncludes/glsLabel.php ---ver 5.1.1 ----2026-11-13 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// -----------------------------------------------------------
// 20261113 CDX/NTR SD-599: Extract GLS labels into their dedicated module.
// 20261113 CDX/NTR Document the extracted GLS entry point parameters.

if (!function_exists('gls_label')) {
	/**
	 * Request a GLS shipment label and download its PDF.
	 *
	 * @param string $gls_username Carrier API username.
	 * @param string $gls_password Carrier API password supplied by the caller.
	 * @param int|string $gls_customerid Carrier customer identifier.
	 * @param int|string $gls_ctId Carrier contact identifier.
	 * @param int|string $ordrenr Order reference and downloaded PDF filename.
	 * @param string $kundeordnr Customer reference on the parcel.
	 * @param string $firmanavn Billing company, used without a delivery name.
	 * @param string $addr1 Billing street.
	 * @param string $postnr Billing postal code.
	 * @param string $bynavn Billing city.
	 * @param string $land Billing country used for the existing country mapping.
	 * @param string $email Delivery contact email.
	 * @param string $lev_navn Delivery name; a nonempty value selects the delivery address.
	 * @param string $lev_addr1 Delivery street.
	 * @param string $lev_postnr Delivery postal code.
	 * @param string $lev_bynavn Delivery city.
	 * @param string $lev_land Retained delivery-country argument; the legacy mapping uses $land.
	 * @param string $kontakt Delivery contact name.
	 * @param float|int|string $weight Parcel weight, rounded up to at least one.
	 * @return never Sends the label or terminates with the carrier error.
	 */
	function gls_label($gls_username, $gls_password, $gls_customerid, $gls_ctId, $ordrenr, $kundeordnr, $firmanavn, $addr1, $postnr, $bynavn, $land, $email, $lev_navn, $lev_addr1, $lev_postnr, $lev_bynavn, $lev_land, $kontakt, $weight)
	{
		global $db;
		$weight = ceil($weight);
		if ($weight < 1) {
			$weight = 1;
		}
		if ($land == 'Sweden') {
			$code = '752';
		} else if ($land == 'Sverige') {
			$code = '752';
		} else if ($land == 'sverige') {
			$code = '752';
		} else if ($land == 'Denmark') {
			$code = '208';
		} else if ($land == 'Danmark') {
			$code = '208';
		} else {
			$code = '208';
		}
		if (!empty($lev_navn)) {
			$name = $lev_navn;
			$strrrt1 = $lev_addr1;
			$city = $lev_bynavn;
			$zipcode = $lev_postnr;
		} else {
			$name = $firmanavn;
			$strrrt1 = $addr1;
			$city = $bynavn;
			$zipcode = $postnr;
		}
		$name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
		$strrrt1 = html_entity_decode($strrrt1, ENT_QUOTES, 'UTF-8');
		$zipcode = html_entity_decode($zipcode, ENT_QUOTES, 'UTF-8');
		$kontakt = html_entity_decode($kontakt, ENT_QUOTES, 'UTF-8');
		$city = html_entity_decode($city, ENT_QUOTES, 'UTF-8');
		$ary = [
			"UserName" => $gls_username,
			"Password" => $gls_password,
			"Customerid" => $gls_customerid,
			"Contactid" => $gls_ctId,
			"ShipmentDate" => date('Ymd'),
			"Reference" => $ordrenr,
			"Addresses" => [
				"Delivery" => [
					"Name1" => substr($name, 0, 39),
					"Street1" => $strrrt1,
					"CountryNum" => $code,
					"ZipCode" => $zipcode,
					"City" => $city,
					"Contact" => substr($kontakt, 0, 39),
					"Email" => $email,
				]
			],
			"Parcels" => [
				[
					"Weight" => $weight,
					"Reference" => $kundeordnr,
				]
			]
		];
		$json = json_encode($ary);
		file_put_contents("../temp/$db/pdf.json", $json);
		$service_url = 'http://api.gls.dk/ws/DK/V1/CreateShipment/';
		// jSON String for request
		$json_string = $json;
		// Initializing curl
		$ch = curl_init($service_url);
		// Configuring curl options
		$options = array(
			CURLOPT_RETURNTRANSFER => true,
			//CURLOPT_USERPWD => $username . ":" . $password,  // authentication
			//CURLOPT_HTTPHEADER => array('Content-type: application/json; charset=ISO-8859-1') ,
			CURLOPT_HTTPHEADER => array('Content-type: application/json; charset=ISO-8859-1'),
			CURLOPT_POSTFIELDS => $json_string
		);

		// Setting curl options
		curl_setopt_array($ch, $options);

		// Getting results
		$result = curl_exec($ch); // Getting jSON result string
		if (curl_errno($ch)) {
			// Handle curl error
			die('Curl error: ' . curl_error($ch));
		}
		curl_close($ch);

		file_put_contents("../temp/$db/pdf-res.json", $result);
		$res = json_decode($result);

		if (!$res || !isset($res->PDF)) {
			// Handle API error
			die('Invalid response from GLS API');
		}

		// Clean all output buffers
		while (ob_get_level()) {
			ob_end_clean();
		}

		// Set headers for PDF download
		header('Content-Type: application/pdf');
		header('Content-Disposition: attachment; filename="' . $ordrenr . '.pdf"');
		header('Content-Length: ' . strlen(base64_decode($res->PDF)));
		header('Content-Transfer-Encoding: binary');
		header('Cache-Control: private, no-cache, no-store, must-revalidate');
		header('Pragma: no-cache');
		header('Expires: 0');

		// Output PDF content
		echo base64_decode($res->PDF);
		exit();
		/* GLS funktion slut */
	}
}
