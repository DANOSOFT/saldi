<?php
include("../includes/oldDesign/header.php");
include("../includes/topline_settings.php");

$border = 'border:1px';
$TableBG = "bgcolor=$bgcolor";
if(isset($_GET['returside'])){
	$backUrl = $_GET['returside'];
}else{
	$backUrl = '../index/menu.php';
}

print "<tr><td height = '25' align = 'center' valign = 'top'>";

print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>"; # Tabel 1.1 ->
print "<td width=10% style='$buttonStyle'>
	   <a href=$backUrl accesskey='L'>
	   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
	   .findtekst('30|Tilbage', $sprog_id)."</button></a></td>";

print "<td width=75% style='$topStyle' align=center><table border=0 cellspacing=2 cellpadding=0><tbody>\n"; # Tabel 1.1.1 ->

// if ($valg == "$tilbud1" && !$hurtigfakt) {
	
// 	print "<td width = '100px' align=center>
// 		   <button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
// 		   .findtekst('812|Tilbud', $sprog_id)."</button></td>";
// } elseif (!$hurtigfakt) {
// 	print "<td width = '100px' align=center>
// 		   <a href='ordreliste.php?valg=tilbud&konto_id=$konto_id&returside=$returside'>
// 		   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
// 		   .findtekst('812|Tilbud', $sprog_id)."</button></a></td>";
// } //20210318

// if ($valg == "$ordrer1") {
// 	print "<td width = '100px' align=center>
// 		   <button style='$butDownStyle; width:100%' id='ordrer' onMouseOver=\"this.style.cursor = 'pointer'\">"
// 		   .findtekst('107|Ordrer', $sprog_id)."</button></td>";
// } else {
// 	print "<td width = '100px' align=center>
// 		   <a href='ordreliste.php?valg=$ordrer1&konto_id=$konto_id&returside=$returside'>
// 		   <button style='$butUpStyle; width:100%' id='ordrer' onMouseOver=\"this.style.cursor = 'pointer'\">"
// 		   .findtekst('107|Ordrer', $sprog_id)."</button></a></td>";
// }

// if ($valg == "$faktura1") {
// 	print "<td width = '100px' align=center>
// 		   <button style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
// 		   .findtekst('643|Faktura', $sprog_id)."</button></td>";
// } else {
// 	print "<td width = '100px' align=center>
// 		   <a href='ordreliste.php? valg=$faktura1&konto_id=$konto_id&returside=$returside'>
// 		   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
// 		   .findtekst('643|Faktura', $sprog_id)."</button></a></td>";
// }



if ($valg == "tilbud" && !$hurtigfakt) {
    print "<td width = '100px' align=center>
           <button style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
           .findtekst('812|Tilbud', $sprog_id)."</button></td>";
} elseif (!$hurtigfakt) {
    print "<td width = '100px' align=center>
           <a href='ordreliste.php?valg=tilbud&konto_id=$konto_id&returside=$returside'>
           <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
           .findtekst('812|Tilbud', $sprog_id)."</button></a></td>";
} //20210318

if ($valg == "ordrer") {
    print "<td width = '100px' align=center>
           <button style='$butDownStyle; width:100%' id='ordrer' onMouseOver=\"this.style.cursor = 'pointer'\">"
           .findtekst('107|Ordrer', $sprog_id)."</button></td>";
} else {
    print "<td width = '100px' align=center>
           <a href='ordreliste.php?valg=ordrer&konto_id=$konto_id&returside=$returside'>
           <button style='$butUpStyle; width:100%' id='ordrer' onMouseOver=\"this.style.cursor = 'pointer'\">"
           .findtekst('107|Ordrer', $sprog_id)."</button></a></td>";
}

if ($valg == "faktura") {
    print "<td width = '100px' align=center>
           <button style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
           .findtekst('643|Faktura', $sprog_id)."</button></td>";
} else {
    print "<td width = '100px' align=center>
           <a href='ordreliste.php?valg=faktura&konto_id=$konto_id&returside=$returside'>
           <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
           .findtekst('643|Faktura', $sprog_id)."</button></a></td>";
}

if ($valg == 'pbs') {
	print "<td width = '100px' align='center'>
		   <button style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		   .findtekst('385|BS', $sprog_id)."</button></td>";
} elseif ($pbs) {
	print "<td width = '100px' align=center>
		   <a href='ordreliste.php?valg=pbs&konto_id=$konto_id&returside=$returside'>
		   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		   .findtekst('385|BS', $sprog_id)."</button></a></td>";
}
print "</tbody></table></td>\n"; # <- Tabel 1.1.1
if ($valg == 'pbs') {
	if ($popup)
		print "<td width=10% style='$topStyle'> onClick=\"javascript:ordre=window.open('pbs_import.php?returside=x','ordre','scrollbars=1,resizable=1');ordre.focus();\">
				   <a accesskey=N href=ordreliste.php?sort=$sort>
				   <button style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">
					Import PBS</button></a></td>\n";
	else
		print "<td width=10% style='$topStyle'>
				   <a href=pbs_import.php?returside=ordreliste.php>
				   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">
					Import PBS</button></a></td>\n";

	include("pbsliste.php");
	exit;
} else {
	print "<td id='tutorial-help' width=5% style='$buttonStyle'>
		   <button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
		   .findtekst('2564|Hjælp', $sprog_id)."</button></td>";
	print "<td width=5% style='$buttonStyle'>
		   <a accesskey=V href=ordrevisning.php?valg=$valg>
		   <button style='$buttonStyle; width:100%' id='visning' onMouseOver=\"this.style.cursor = 'pointer'\">"
		   .findtekst('813|Visning', $sprog_id)."</button></a></td>\n";
	if ($popup) {
		print "<td width=10% style='$buttonStyle' onClick=\"javascript:ordre=window.open('ordre.php?returside=ordreliste.php&konto_id=$konto_id','ordre','scrollbars=1,resizable=1');ordre.focus();\">
			   <a accesskey=N href='".$_SERVER['PHP_SELF'] . "'>
			   <button style='$buttonStyle; width:100%' id='ny' onMouseOver=\"this.style.cursor = 'pointer'\">"
			   .findtekst('39|Ny', $sprog_id)."</button></a></td>\n";
	} else {
		print "<td width=10% style='$buttonStyle'>
			   <a href=ordre.php?konto_id=$konto_id&returside=ordreliste.php?konto_id=$konto_id>
			   <button style='$buttonStyle; width:100%' id='ny' onMouseOver=\"this.style.cursor = 'pointer'\">"
			   .findtekst('39|Ny', $sprog_id)."</button></a></td>\n";
	}
	print "</tbody></table></td></tr>\n"; # <- Tabel 1.1.1
}

if ($valg == "$ordrer1") { #20121017
	$dir = '../ublfiler/ind/';
	if (file_exists("$dir")) {
		$vis_xml = 0;
		$filer = scandir($dir);
		for ($x = 0; $x < count($filer); $x++) {
			if (substr($filer[$x], -3) == 'xml') $vis_xml = 1;
		}
		if ($vis_xml) print "<tr><td align=\"center\"><a href=\"ubl2ordre.php\" target=\"blank\">" .findtekst('876|Importer UBL til ordrer', $sprog_id) . "</a></td></tr>";
	}
}
print "<center>"; #20141107
