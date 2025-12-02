<?php

include("../includes/oldDesign/header.php");
include("../includes/topline_settings.php");
alert("oppfss");

print "<script>alert('topLine.php is being loaded!');</script>";
$border = 'border:1px';
$TableBG = "bgcolor=$bgcolor";

if (isset($_GET['returside'])) {
	$backUrl = $_GET['returside'];
} else {
	$backUrl = '../index/menu.php';
}

// Icons for buttons
$icon_kreditor = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M280-600v-80h560v80H280Zm0 160v-80h560v80H280Zm0 160v-80h560v80H280ZM160-600q-17 0-28.5-11.5T120-640q0-17 11.5-28.5T160-680q17 0 28.5 11.5T200-640q0 17-11.5 28.5T160-600Zm0 160q-17 0-28.5-11.5T120-480q0-17 11.5-28.5T160-520q17 0 28.5 11.5T200-480q0 17-11.5 28.5T160-440Zm0 160q-17 0-28.5-11.5T120-320q0-17 11.5-28.5T160-360q17 0 28.5 11.5T200-320q0 17-11.5 28.5T160-280Z"/></svg>';
$help_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';
$add_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M440-280h80v-160h160v-80H520v-160h-80v160H280v80h160v160Zm40 200q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';
$icon_visning = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M480-320q75 0 127.5-52.5T660-500q0-75-52.5-127.5T480-680q-75 0-127.5 52.5T300-500q0 75 52.5 127.5T480-320Zm0-72q-42 0-71-29t-29-71q0-42 29-71t71-29q42 0 71 29t29 71q0 42-29 71t-71 29Zm0 192q-146 0-266-81.5T40-500q54-137 174-218.5T480-800q146 0 266 81.5T920-500q-54 137-174 218.5T480-200Zm0-300Zm0 220q113 0 207.5-59.5T832-500q-50-101-144.5-160.5T480-720q-113 0-207.5 59.5T128-500q50 101 144.5 160.5T480-280Z"/></svg>';

print "<tr><td height = '25' align = 'center' valign = 'top'>";
print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>"; # Tabel 1.1 ->

# Dont show close on sidebar
if ($menu !== "S") {
	print "<td width=5% style=$buttonStyle>
		<a href=$backUrl accesskey='L'>
		<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
		Luk</button></a></td>";
}

print "<td width=75% style='$topStyle' align=left><table border=0 cellspacing=2 cellpadding=0><tbody>\n"; # Tabel 1.1.1 ->

print "<td id='kreditore' width = '200px' align=center>";
print "<button class='headerbtn navbtn-top' style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
print "$icon_kreditor ".findtekst('607|Kreditorer', $sprog_id)."</button></td>";

print "</tbody></table></td>\n"; # <- Tabel 1.1.1

print "<td id='tutorial-help' width=5% style='$buttonStyle'>";
print "<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
print $help_icon;
print findtekst('2564|Hjælp', $sprog_id)."</button></td>";

print "<td width=5% style='$buttonStyle'>";
print "<a accesskey=V href=kreditorvisning.php?valg=$valg>";
print "<button class='center-btn' style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
print "$icon_visning ".findtekst('813|Visning', $sprog_id)."</button></a></td>\n";

print "<td id='opret-ny' width=5% style='$buttonStyle'>";
print "<a href=kreditorkort.php?returside=kreditor.php>";
print "<button class='center-btn' style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
print "$add_icon ".findtekst('39|Ny', $sprog_id)."</button></a></td>\n";

print "</tbody></table></td></tr>\n"; # <- Tabel 1.1.1

?>

<style>
	.headerbtn, .center-btn {
		display: flex;
		align-items: center;
		text-decoration: none;
		gap: 5px;
	}
</style>
