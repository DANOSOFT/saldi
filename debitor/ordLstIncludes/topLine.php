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


###############
$tilbud_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M280-600v-80h560v80H280Zm0 160v-80h560v80H280Zm0 160v-80h560v80H280ZM160-600q-17 0-28.5-11.5T120-640q0-17 11.5-28.5T160-680q17 0 28.5 11.5T200-640q0 17-11.5 28.5T160-600Zm0 160q-17 0-28.5-11.5T120-480q0-17 11.5-28.5T160-520q17 0 28.5 11.5T200-480q0 17-11.5 28.5T160-440Zm0 160q-17 0-28.5-11.5T120-320q0-17 11.5-28.5T160-360q17 0 28.5 11.5T200-320q0 17-11.5 28.5T160-280Z"/></svg>';
$invoice_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff">
  <path d="M280-600v-80h560v80H280Zm0 160v-80h560v80H280Zm0 160v-80h560v80H280ZM160-600q-17 0-28.5-11.5T120-640q0-17 11.5-28.5T160-680q17 0 28.5 11.5T200-640q0 17-11.5 28.5T160-600Zm0 160q-17 0-28.5-11.5T120-480q0-17 11.5-28.5T160-520q17 0 28.5 11.5T200-480q0 17-11.5 28.5T160-440Zm0 160q-17 0-28.5-11.5T120-320q0-17 11.5-28.5T160-360q17 0 28.5 11.5T200-320q0 17-11.5 28.5T160-280Z"/>
</svg>';
$order_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff">
  <path d="M280-600v-80h560v80H280Zm0 160v-80h560v80H280Zm0 160v-80h560v80H280ZM160-600q-17 0-28.5-11.5T120-640q0-17 11.5-28.5T160-680q17 0 28.5 11.5T200-640q0 17-11.5 28.5T160-600Zm0 160q-17 0-28.5-11.5T120-480q0-17 11.5-28.5T160-520q17 0 28.5 11.5T200-480q0 17-11.5 28.5T160-440Zm0 160q-17 0-28.5-11.5T120-320q0-17 11.5-28.5T160-360q17 0 28.5 11.5T200-320q0 17-11.5 28.5T160-280Z"/>
</svg>';

$icon_historik = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M480-120 200-272v-240L40-600l160-88v-192h560v192l160 88-160 88v240L480-120Zm0-332 274-144-274-144-274 144 274 144Zm0 239 200-108v-151L480-360 280-432v151l200 108Zm0-239Zm0 239Zm0 0Z"/></svg>';
$icon_kommission = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M280-600v-80h560v80H280Zm0 160v-80h560v80H280Zm0 160v-80h560v80H280ZM160-600q-17 0-28.5-11.5T120-640q0-17 11.5-28.5T160-680q17 0 28.5 11.5T200-640q0 17-11.5 28.5T160-600Zm0 160q-17 0-28.5-11.5T120-480q0-17 11.5-28.5T160-520q17 0 28.5 11.5T200-480q0 17-11.5 28.5T160-440Zm0 160q-17 0-28.5-11.5T120-320q0-17 11.5-28.5T160-360q17 0 28.5 11.5T200-320q0 17-11.5 28.5T160-280Z"/></svg>';
$pbs_icon= '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff">
  <path d="M280-600v-80h560v80H280Zm0 160v-80h560v80H280Zm0 160v-80h560v80H280ZM160-600q-17 0-28.5-11.5T120-640q0-17 11.5-28.5T160-680q17 0 28.5 11.5T200-640q0 17-11.5 28.5T160-600Zm0 160q-17 0-28.5-11.5T120-480q0-17 11.5-28.5T160-520q17 0 28.5 11.5T200-480q0 17-11.5 28.5T160-440Zm0 160q-17 0-28.5-11.5T120-320q0-17 11.5-28.5T160-360q17 0 28.5 11.5T200-320q0 17-11.5 28.5T160-280Z"/>
</svg>';
$icon_jobliste = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M280-600v-80h560v80H280Zm0 160v-80h560v80H280Zm0 160v-80h560v80H280ZM160-600q-17 0-28.5-11.5T120-640q0-17 11.5-28.5T160-680q17 0 28.5 11.5T200-640q0 17-11.5 28.5T160-600Zm0 160q-17 0-28.5-11.5T120-480q0-17 11.5-28.5T160-520q17 0 28.5 11.5T200-480q0 17-11.5 28.5T160-440Zm0 160q-17 0-28.5-11.5T120-320q0-17 11.5-28.5T160-360q17 0 28.5 11.5T200-320q0 17-11.5 28.5T160-280Z"/></svg>';
$help_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';
$add_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M440-280h80v-160h160v-80H520v-160h-80v160H280v80h160v160Zm40 200q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';

###########

print "<tr><td height = '25' align = 'center' valign = 'top'>";

print "<table class='topLine' width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>"; # Tabel 1.1 ->
	
print "<td width=75% style='$topStyle' align=left><table border=0 cellspacing=2 cellpadding=0><tbody>\n"; # Tabel 1.1.1 ->


if ($valg == "tilbud" && !$hurtigfakt) {
    print "<td  width = '200px' align=center>";
          print "<button class='headerbtn navbtn-top' style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
          print "$tilbud_icon ".findtekst('2770|Tilbud', $sprog_id)."</button></td>"; 
} elseif (!$hurtigfakt) {
    print "<td  width = '200px' align=center>";
          print " <a href='ordreliste.php?valg=tilbud&konto_id=$konto_id&returside=$returside'>";
         print "<button class='headerbtn navbtn-top' style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
           print "$tilbud_icon ".findtekst('2770|Tilbud', $sprog_id)."</button></td>"; 
} //20210318

if ($valg == "ordrer") {
    print "<td width = '200px' align=center>";
           print "<button class='headerbtn navbtn-top' style='$butDownStyle; width:100%' id='ordrer' onMouseOver=\"this.style.cursor = 'pointer'\">";
           print "$order_icon ".findtekst('107|Ordrer', $sprog_id)."</button></td>"; 
		   
} else {
    print "<td width = '200px' align=center>
           <a href='ordreliste.php?valg=ordrer&konto_id=$konto_id&returside=$returside'>
           <button class='headerbtn navbtn-top' style='$butUpStyle; width:100%' id='ordrer' onMouseOver=\"this.style.cursor = 'pointer'\">";
           print "$order_icon ".findtekst('107|Ordrer', $sprog_id)."</button></td>"; 
}

if ($valg == "faktura") {
    print "<td width = '200px' align=center>";
           print "<button class='headerbtn navbtn-top' style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
           
		   print "$invoice_icon".findtekst('1777|Fakturaer', $sprog_id)."</button></td>";
} else {
    print "<td width = '200px' align=center>";
           print"<a href='ordreliste.php?valg=faktura&konto_id=$konto_id&returside=$returside'>";
           print "<button class='headerbtn navbtn-top' style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
           print "$invoice_icon".findtekst('1777|Fakturaer', $sprog_id)."</button></td>";
}

if ($valg == 'pbs') {
	print "<td width = '200px' align='center'>"; 
		   print "<button class='headerbtn navbtn-top' style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
		   print"$pbs_icon".findtekst('385|BS', $sprog_id)."</button></td>";
} elseif ($pbs) {
	print "<td width = '200px' align=center>";
		  print"<a href='ordreliste.php?valg=pbs&konto_id=$konto_id&returside=$returside'>";
		   print "<button class='headerbtn navbtn-top' style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
		   print "$pbs_icon".findtekst('385|BS', $sprog_id)."</button></td>";
}
print "</tbody></table></td>\n"; # <- Tabel 1.1.1
if ($valg == 'pbs') {
	if ($popup)
		print "<td width=10% style='$topStyle'> onClick=\"javascript:ordre=window.open('pbs_import.php?returside=x','ordre','scrollbars=1,resizable=1');ordre.focus();\">
				   <a accesskey=N href=ordreliste.php?sort=$sort>
				   <button class='headerbtn navbtn-top' style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">
					Import PBS</button></a></td>\n";
	else
		print "<td width=10% style='$topStyle'>
				   <a href=pbs_import.php?returside=ordreliste.php>";
				   print "<button class='headerbtn navbtn-top' style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">
					Import PBS</button></a></td>\n";

	include("pbsliste.php");
	exit;
} else {
	print "<td id='tutorial-help' width=5% style='$buttonStyle'>
		   <button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
		   print"$help_icon".findtekst('2564|Hjælp', $sprog_id)."</button></td>";
	if ($popup) {
		print "<td width=5% style='$buttonStyle' onClick=\"javascript:ordre=window.open('ordre.php?returside=ordreliste.php&konto_id=$konto_id','ordre','scrollbars=1,resizable=1');ordre.focus();\">
			   <a accesskey=N href='".$_SERVER['PHP_SELF'] . "'>
			  <button class='center-btn' style='$buttonStyle; width:100%' id='ny' onMouseOver=\"this.style.cursor = 'pointer'\">";
			   print "$add_icon".findtekst('39|Ny', $sprog_id)."</button></a></td>\n";
	} else {
		print "<td width=5% style='$buttonStyle'>
			   <a href=ordre.php?konto_id=$konto_id&returside=ordreliste.php?konto_id=$konto_id>";
			   print "<button class='center-btn' style='$buttonStyle; width:100%' id='ny' onMouseOver=\"this.style.cursor = 'pointer'\">";
			   print "$add_icon".findtekst('39|Ny', $sprog_id)."</button></a></td>\n";
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
print "</tbody></table></td></tr>\n"; # <- Tabel 1.1.1

?>

<style>
	.headerbtn, .center-btn {
		display: flex;
		align-items: center;
		text-decoration: none;
		gap: 5px;
	}
	a:link{
		text-decoration: none;
	}
</style>
