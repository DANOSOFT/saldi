<?php
// Modern sticky header for documents.php, modeled after topLineJobkort.php
include("../includes/oldDesign/header.php");
include("../includes/topline_settings.php");

$icon_back = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8l-4 4 4 4M16 12H9"></path></svg>';
$icon_docs = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 0 24 24" fill="#ffffff"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 13h6M9 17h6" stroke="#fff" stroke-width="2"/></svg>';
$help_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';

if (isset($_GET['returside'])) {
    $backUrl = $_GET['returside'];
} elseif (isset($_GET['source']) && $_GET['source'] === 'kassekladde' && isset($_GET['kladde_id']) && isset($_GET['sourceId'])) {
    $backUrl = "../finans/kassekladde.php?kladde_id=".urlencode($_GET['kladde_id'])."&id=".urlencode($_GET['sourceId']);
} elseif (isset($_GET['source']) && $_GET['source'] === 'debitorOrdrer' && isset($_GET['sourceId'])) {
    $backUrl = "../debitor/ordre.php?id=".urlencode($_GET['sourceId']);
} elseif (isset($_GET['source']) && $_GET['source'] === 'creditorOrder' && isset($_GET['sourceId'])) {
    $backUrl = "../kreditor/ordre.php?id=".urlencode($_GET['sourceId']);
} else {
    $backUrl = 'documents.php'; // fallback
}

print "<tr><td height = '25' align = 'center' valign = 'top' style='position: sticky; top: 0; z-index: 100; background: white;'>";
print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";

// Back button
print "<td width=5%><a href='$backUrl' accesskey='L'><button class='center-btn' style='width:100%;background:#3a3a3a;color:#fff;border:none;padding:6px 0;border-radius:4px;display:flex;align-items:center;gap:5px;'><span>$icon_back</span>".findtekst('30|Tilbage', $sprog_id)."</button></a></td>";

// Title
print "<td width=90% align=left><table border=0 cellspacing=2 cellpadding=0><tbody>";
print "<td width = '200px' align=center id='documents-btn'><button class='headerbtn navbtn-top' style='width:100%;background:#1976d2;color:#fff;border:none;padding:6px 0;border-radius:4px;display:flex;align-items:center;gap:5px;'><span>$icon_docs</span>".findtekst('1408|Dokumenter', $sprog_id)."</button></td>";
print "</tbody></table></td>";

// Help button
print "<td id='tutorial-help' width=5%><button class='center-btn' style='width:100%;background:#3a3a3a;color:#fff;border:none;padding:6px 0;border-radius:4px;display:flex;align-items:center;gap:5px;'>$help_icon".findtekst('2564|Hjælp', $sprog_id)."</button></td>";

print "</tbody></table></td></tr>";
?>

<style>
.headerbtn, .center-btn {
	display: flex;
	align-items: center;
	text-decoration: none;
	gap: 5px;
}
body {
	padding: 0;
	overflow-y: auto;
	overflow-x: hidden;
}
#content-wrapper {
	height: calc(100vh - 90px);
	overflow-y: auto;
	overflow-x: hidden;
}
</style>
