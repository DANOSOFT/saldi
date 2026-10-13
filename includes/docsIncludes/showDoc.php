<?php
// --- includes/docsIncludes/listDocs.php --- patch 4.1.1------2025.09.30---
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
// Copyright (c) 2023-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// PLBM 2024.01.31
//20240305 PHR Varioous corrections


// Check if we're in flexbox layout (docPool-style) or table layout
$inFlexboxLayout = (isset($_GET['showDoc']) && isset($_GET['source']) && $_GET['source'] == 'kassekladde');

if ($inFlexboxLayout) {
	// Flexbox layout - use divs
	print "<div style='width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;'>";
} else {
	// Table layout - use table cells
	print "<tr><td width='100%' height='100%' align='center' valign='middle'>";
}

#echo "<br>$showDoc<br>";
#if (file_exists($showDoc)) echo "den er der skam<br>";
#else echo "Kan ikke finde den<br>";
$fileInfo = pathinfo($showDoc);
if (strtolower(substr($showDoc,-3,3))=='pdf') {
	print "<iframe frameborder='no' width='100%' height='100%' scrolling='auto' src='$showDoc'></iframe>";
} else if (strtolower($fileInfo["extension"]) == "xml") {
	// Need database connection to get API key
	if (!isset($db) || !$db) {
		@session_start();
		$s_id = session_id();
		include_once "../includes/connect.php";
		include_once "../includes/online.php";
	}
	
	// Get the API key from settings
	include "connect.php";
	$apiKeyQuery = db_select("SELECT var_value FROM settings WHERE var_name = 'apiKey' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__);
	$apiKeyRow = db_fetch_array($apiKeyQuery);
	$easyUblApiKey = $apiKeyRow ? $apiKeyRow['var_value'] : '';
	session_start();
	$s_id=session_id();
	include "online.php";
	
	if ($easyUblApiKey) {
		// Create a safe temp filename based on the source file path
		$tempFileName = "xml_view_" . md5($showDoc) . ".html";
		$tempFilePath = "../temp/$db/" . $tempFileName;
		
		// Create temp dir if it doesn't exist
		if (!file_exists("../temp/$db")) {
			@mkdir("../temp/$db", 0777, true);
		}
		
		// Check if we already have a cached converted version
		if (!file_exists($tempFilePath) || (filemtime($showDoc) > filemtime($tempFilePath))) {
			$fileContent = file_get_contents($showDoc);
			if ($fileContent !== false) {
				$data = [
					"language" => "",
					"base64EncodedDocumentXml" => base64_encode($fileContent)
				];
				
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, 'https://easyubl.net/api/HumanReadable/HTMLDocument');
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
				curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: " . $easyUblApiKey));
				curl_setopt($ch, CURLOPT_TIMEOUT, 30);
				
				$res = curl_exec($ch);
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$curlError = curl_error($ch);
				curl_close($ch);
				
				if ($res && $httpCode == 200 && !$curlError) {
					file_put_contents($tempFilePath, $res);
				} else {
					// Failed to convert
					$tempFilePath = "";
					echo "<div style='color:red; padding:10px;'>Kunne ikke konvertere XML til visning. Fejl: " . ($curlError ? $curlError : "HTTP $httpCode") . "</div>";
				}
			}
		}
		
		if ($tempFilePath && file_exists($tempFilePath)) {
			print "<iframe frameborder='no' width='100%' height='100%' scrolling='auto' src='$tempFilePath'></iframe>";
		} else {
			// Fallback: show raw XML if conversion failed
			echo "<pre style='width:90%; margin:1rem auto; overflow:auto; max-height:100%;'>" . htmlspecialchars(file_get_contents($showDoc)) . "</pre>";
		}
	} else {
		// No API key -> Show raw XML
		echo "<div style='padding:10px; color:#856404; background-color:#fff3cd; border:1px solid #ffeeba;'>EasyUBL API-nøgle mangler. Viser rå XML.</div>";
		echo "<pre style='width:90%; margin:1rem auto; overflow:auto; max-height:100%;'>" . htmlspecialchars(file_get_contents($showDoc)) . "</pre>";
	}
 } else print "<img src='$showDoc' style='max-width:100%;height:auto;'>";

if ($inFlexboxLayout) {
	print "</div>";
} else {
	print "</td></tr>";
}

?>
