<?php
include("../includes/oldDesign/header.php");

$border = 'border:1px';
$TableBG = "bgcolor=$bgcolor";
$help_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';

print "<tr><td height='25' align='center' valign='top'>";
print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";

// Back button (only if not sidebar)
if ($menu !== "S") {
    print "<td width=5% style='$buttonStyle'>
        <a href='$returside' accesskey='L'>
        <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
        Luk</button></a></td>";
}

print "<td width=75% style='$topStyle' align=left><table border=0 cellspacing=2 cellpadding=0><tbody>\n";

// Regnskab button (active)
if ($valg == "Regnskab") {
    print "<td width='200px' align=center>
        <button class='headerbtn navbtn-top' style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
        " . findtekst('849|Regnskab', $sprog_id) . "
        </button></td>";
} else {
    print "<td width='200px' align=center>
        <a href='regnskab.php'>
        <button class='headerbtn navbtn-top' style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
        " . findtekst('849|Regnskab', $sprog_id) . "
        </button></a></td>";
}

print "<td>&nbsp;</td>";

// Budget button
if ($valg == "Budget") {
    print "<td width='200px' align=center>
        <button class='headerbtn navbtn-top' style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
        Budget
        </button></td>";
} else {
    print "<td width='200px' align=center>
        <a href='budget.php'>
        <button class='headerbtn navbtn-top' style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
        Budget
        </button></a></td>";
}

print "</tbody></table></td>\n";

// Help button
print "<td width=5% style='$buttonStyle'>
    <button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
    $help_icon Hjælp
    </button></td>";

print "</tbody></table></td></tr>\n";
?>