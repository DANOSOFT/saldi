<?php


###############
$konto_id = if_isset($_GET, NULL,'konto_id');
$returside = if_isset($_GET, NULL,'returside'); 
$valg = if_isset($_GET, 'ordrer','valg');
$sort = if_isset($_GET, NULL,'sort');

$forslag_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff">
  <path d="M280-600v-80h560v80H280Zm0 160v-80h560v80H280Zm0 160v-80h560v80H280ZM160-600q-17 0-28.5-11.5T120-640q0-17 11.5-28.5T160-680q17 0 28.5 11.5T200-640q0 17-11.5 28.5T160-600Zm0 160q-17 0-28.5-11.5T120-480q0-17 11.5-28.5T160-520q17 0 28.5 11.5T200-480q0 17-11.5 28.5T160-440Zm0 160q-17 0-28.5-11.5T120-320q0-17 11.5-28.5T160-360q17 0 28.5 11.5T200-320q0 17-11.5 28.5T160-280Z"/>
</svg>';


$invoice_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff">
  <path d="M280-600v-80h560v80H280Zm0 160v-80h560v80H280Zm0 160v-80h560v80H280ZM160-600q-17 0-28.5-11.5T120-640q0-17 11.5-28.5T160-680q17 0 28.5 11.5T200-640q0 17-11.5 28.5T160-600Zm0 160q-17 0-28.5-11.5T120-480q0-17 11.5-28.5T160-520q17 0 28.5 11.5T200-480q0 17-11.5 28.5T160-440Zm0 160q-17 0-28.5-11.5T120-320q0-17 11.5-28.5T160-360q17 0 28.5 11.5T200-320q0 17-11.5 28.5T160-280Z"/>
</svg>';
$order_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff">
  <path d="M280-600v-80h560v80H280Zm0 160v-80h560v80H280Zm0 160v-80h560v80H280ZM160-600q-17 0-28.5-11.5T120-640q0-17 11.5-28.5T160-680q17 0 28.5 11.5T200-640q0 17-11.5 28.5T160-600Zm0 160q-17 0-28.5-11.5T120-480q0-17 11.5-28.5T160-520q17 0 28.5 11.5T200-480q0 17-11.5 28.5T160-440Zm0 160q-17 0-28.5-11.5T120-320q0-17 11.5-28.5T160-360q17 0 28.5 11.5T200-320q0 17-11.5 28.5T160-280Z"/>
</svg>';

$help_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';
$add_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M440-280h80v-160h160v-80H520v-160h-80v160H280v80h160v160Zm40 200q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';

###########

print "<tr><td height = '25' align = 'center' valign = 'top'>";

print "<table class='topLine' width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
	
print "<td width=75% style='$topStyle' align=left><table border=0 cellspacing=2 cellpadding=0><tbody>\n"; 


	if (!$hurtigfakt || $hurtigfakt == 'off') {
			print "<td width = 200px align=center ";
			if ($valg == 'forslag') {
				print "<td width = '200px' align=center>
					<button class='headerbtn navbtn-top' style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">" .$forslag_icon . findtekst('827|Forslag', $sprog_id) . "</button></td>";
			} else {
				print "<td width = 200px align=center><a href='ordreliste.php?sort=$sort&valg=forslag$hreftext'>
					<button class='headerbtn navbtn-top' style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
					.$forslag_icon. findtekst('827|Forslag', $sprog_id) . "</button></a></td>";
			}
	} 


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
			
			print "$invoice_icon".findtekst('643|Faktura', $sprog_id)."</button></td>";
	} else {
		print "<td width = '200px' align=center>";
			print"<a href='ordreliste.php?valg=faktura&konto_id=$konto_id&returside=$returside'>";
			print "<button class='headerbtn navbtn-top' style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
			print "$invoice_icon".findtekst('643|Faktura', $sprog_id)."</button></td>";
	}

   if ($paperflow) {
		print "</td><td width = 200px align=center ";
		if ($valg == 'skanBilag') {
			print "<td width = '200px' align=center>
				   <button class='headerbtn navbtn-top' style='$butDownStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">" . findtekst('2182|Skan bilag', $sprog_id) . "</button></td>";
		} else {
			print "<td width = 200px align=center><a href='ordreliste.php?sort=$sort&valg=faktura$hreftext'>
				   <button class='headerbtn navbtn-top' style='$butUpStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
				. findtekst('2182|Skan bilag', $sprog_id) . "</button></a></td>";
		}
		print "</td>";
	}
    print "</tbody></table></td>\n"; 
	
	print "<td id='tutorial-help' width=5% style='$buttonStyle'>";
	print "<button class='headerbtn navbtn-top' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
	print "$help_icon".findtekst('2564|Hjælp', $sprog_id)."</button></td>";
	print "<td width=5% style='$buttonStyle'><a href=ordre.php?returside=ordreliste.php>
		   <button class='headerbtn navbtn-top' style='$buttonStyle; width: 100%' id='ny' onMouseOver=\"this.style.cursor = 'pointer'\">" .$add_icon. findtekst('39|Ny', $sprog_id) . "</button></a></td>";
	print "</tbody></table>";
	print "</tbody></table></td></tr>\n"; 


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
