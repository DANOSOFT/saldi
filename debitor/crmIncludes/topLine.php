<?php
include("../includes/oldDesign/header.php");
include("../includes/topline_settings.php");

$border = 'border:1px';
$TableBG = "bgcolor=$bgcolor";

print "<tr><td height = '25' align = 'center' valign = 'top'>";

// if ($valg == "visning") {
// 	$returside = "crmkalender.php?returside=../index/dashboard.php&vismenu=all";
// }


// If a 'returside' query parameter is present in this case crmkalender.php, use it.
// Otherwise, fallback to JavaScript history.go(-2) to go two steps back —
// this handles cases where the browser added an intermediate "#" route step.


// $backUrl = isset($_GET['returside'])
// 	? $_GET['returside']
// 	: '../index/menu.php';

$backUrl = isset($_GET['returside'])
	? $_GET['returside']
	: '../index/menu.php';


print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>"; # Tabel 1.1 ->

print "<td width=10% style=$buttonStyle><a href=\"$backUrl\"><button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
print findtekst('30|Tilbage', $sprog_id)."</button></a></td>";

print "<td width=80% style=$topStyle align=center><table border=0 cellspacing=2 cellpadding=0><tbody>\n"; # Tabel 1.1.1 ->

if ($valg == "kalender") {
	print "<td width = '100px' align=center><button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
	print findtekst('2710|Kalender', $sprog_id)."</button></td>";
} else {
	print "<td width = '100px' align=center><a href='crmkalender.php?returside=$returside&vismenu=$_GET[vismenu]'>";
	print "<button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">".findtekst('2710|Kalender', $sprog_id)."</button></a></td>";
} //20210318

print "</tbody></table></td>\n"; # <- Tabel 1.1.1

print "<td width=10% style=$buttonStyle><a accesskey=V href=crmvisning.php?returside=$returside>";
print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">".findtekst('813|Visning', $sprog_id)."</button></a></td>\n";
print "</tbody></table></td></tr>\n"; # <- Tabel 1.1.1
