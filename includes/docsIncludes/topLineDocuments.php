<?php
// --- docsIncludes/topLineDocuments.php -----patch 4.1.1 ----2025-12-01-------
//                           LICENSE
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20251201 - LOE Created modern header for documents page
// 20261009 Sawaneh The page on the new page head (page_bar) instead of the blue bar; the print layout untouched.

// Note: oldDesign/header.php and topline_settings.php are included by documents.php

// Ensure style variables are accessible when included from within a function
global $buttonStyle, $topStyle, $butDownStyle, $buttonColor, $buttonTxtColor;
global $source, $sourceId, $kladde_id, $fokus, $sprog_id, $bgcolor;

$border = 'border:1px';
$TableBG = "bgcolor=$bgcolor";

// Determine back URL based on source
if ($source=="kassekladde") {
	$backUrl = "../finans/kassekladde.php?kladde_id=$kladde_id&id=$sourceId&fokus=$fokus";
} elseif ($source=="debitorOrdrer") {
	$backUrl = "../debitor/ordre.php?id=$sourceId&fokus=$fokus";
} elseif ($source=="creditorOrder") {
	$backUrl = "../kreditor/ordre.php?id=$sourceId&fokus=$fokus";
} else {
	$backUrl = "../debitor/historikkort.php?id=$sourceId&fokus=$fokus";
}

// The page head (page_bar): title, Tilbage, the hidden tour trigger for the Assist menu.
include_once(get_relative() . "includes/stdFunc/pageBar.php");
print "<tr><td colspan='3'>";
page_bar(array('title' => findtekst('1408|Kassebilag', $sprog_id), 'back' => $backUrl, 'help' => true));
print "</td></tr>";

?>

<style>
	.headerbtn, .center-btn {
		display: flex;
		align-items: center;
		text-decoration: none;
		gap: 5px;
	}
	
	/* no use for underlines on the top href buttons */
	.header-row a,
	.header-row a:link,
	.header-row a:visited,
	.header-row a:hover,
	.header-row a:focus,
	.header-row a:active {
		text-decoration: none !important;
	}
	
	#topBarHeader td {
		vertical-align: middle;
	}
	
	#topBarHeader button {
		white-space: nowrap;
	}
</style>
