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


print "<tr><td width='100%' height='100%' align='center' valign='middle'>";
#echo "<br>$showDoc<br>";
#if (file_exists($showDoc)) echo "den er der skam<br>";
#else echo "Kan ikke finde den<br>";
$fileInfo = pathinfo($showDoc);
if (strtolower(substr($showDoc,-3,3))=='pdf') {
	print "<iframe frameborder='no' width='100%' height='100%' scrolling='auto' src='$showDoc'></iframe>";
} else if($fileInfo["extension"] == "xml"){
	// If html file already exists, use that
	if(file_exists("../temp/$db/pulje/$poolFile.html")){
		$src = "../temp/$db/pulje/$poolFile.html";
	}else{
		$fileContent = file_get_contents($showDoc);
		$data = [
			"language" => "",
			"base64EncodedDocumentXml" => base64_encode($fileContent)
		];
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://easyubl.net/api/HumanReadable/HTMLDocument');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: ".$apiKey));
		$res = curl_exec($ch);
		curl_close($ch);
		file_put_contents("../temp/$db/pulje/$poolFile.html", $res);
		$src = "../temp/$db/pulje/$poolFile.html";
	}
	// Show the html file
	echo "<div style='width:90%; margin:1rem auto;'>".file_get_contents($src)."</div>";
 }else print "<img src='$showDoc' style='max-width:100%;height:auto;'>";
print "</td></tr>";

?>
