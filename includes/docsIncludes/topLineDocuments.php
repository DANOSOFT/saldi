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

// Note: oldDesign/header.php and topline_settings.php are included by documents.php

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

// Icons for buttons
$icon_back = '<svg xmlns="http://www.w3.org/2000/svg" width="20px" height="20px" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8l-4 4 4 4M16 12H9"></path></svg>';
$icon_documents = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M320-240h320v-80H320v80Zm0-160h320v-80H320v80ZM240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z"/></svg>';
$help_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';

print "<tr><td height = '25' align = 'center' valign = 'top' style='position: sticky; top: 0; z-index: 100; background: white;'>";
print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>"; # Tabel 1.1 ->
print "<tr>"; # Open row for the header cells

# Back button
print "<td width=5% style=$buttonStyle>
	<a href='$backUrl' accesskey='L'>
	<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
	$icon_back ".findtekst('30|Tilbage', $sprog_id)."</button></a></td>";

print "<td width=90% style='$topStyle' align=left><table border=0 cellspacing=2 cellpadding=0><tbody>\n"; # Tabel 1.1.1 ->
print "<tr>"; # Open row for nested table

print "<td width = '200px' align=center id='documents-btn'>
	<button class='headerbtn navbtn-top' style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">
	$icon_documents ".findtekst('1408|Dokumenter', $sprog_id)."
	</button></td>";

print "</tr>"; # Close row for nested table
print "</tbody></table></td>\n"; # <- Tabel 1.1.1

print "<td id='tutorial-help' width=5% style='$buttonStyle'>";
print "<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
print $help_icon;
print findtekst('2564|Hjælp', $sprog_id)."</button></td>";

print "</tr>"; # Close row for header cells
print "</tbody></table></td></tr>\n"; # <- Tabel 1.1

?>

<style>
	.headerbtn, .center-btn {
		display: flex;
		align-items: center;
		text-decoration: none;
		gap: 5px;
	}
	
	/* Sticky header */
	body {
		padding: 0;
		overflow-y: auto;
		overflow-x: hidden;
	}
	
	/* Content area with scroll */
	#content-wrapper {
		height: calc(100vh - 90px);
		overflow-y: auto;
		overflow-x: hidden;
	}
</style>
