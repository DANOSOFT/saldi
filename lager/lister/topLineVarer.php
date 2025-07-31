<?php
	include(get_relative()."/includes/oldDesign/header.php");
	include(get_relative()."/includes/topline_settings.php");
	$returside = if_isset($returside, get_relative()."index/menu.php");

	$border = 'border:1px';
	$TableBG = "bgcolor=$bgcolor";

	$icon_serialnumber = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M220-360v-180h-60v-60h120v240h-60Zm140 0v-100q0-17 11.5-28.5T400-500h80v-40H360v-60h140q17 0 28.5 11.5T540-560v60q0 17-11.5 28.5T500-460h-80v40h120v60H360Zm240 0v-60h120v-40h-80v-40h80v-40H600v-60h140q17 0 28.5 11.5T780-560v160q0 17-11.5 28.5T740-360H600Z"/></svg>';
	$icon_vareliste    = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M280-600v-80h560v80H280Zm0 160v-80h560v80H280Zm0 160v-80h560v80H280ZM160-600q-17 0-28.5-11.5T120-640q0-17 11.5-28.5T160-680q17 0 28.5 11.5T200-640q0 17-11.5 28.5T160-600Zm0 160q-17 0-28.5-11.5T120-480q0-17 11.5-28.5T160-520q17 0 28.5 11.5T200-480q0 17-11.5 28.5T160-440Zm0 160q-17 0-28.5-11.5T120-320q0-17 11.5-28.5T160-360q17 0 28.5 11.5T200-320q0 17-11.5 28.5T160-280Z"/></svg>';
	$icon_indkob       = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M856-390 570-104q-12 12-27 18t-30 6q-15 0-30-6t-27-18L103-457q-11-11-17-25.5T80-513v-287q0-33 23.5-56.5T160-880h287q16 0 31 6.5t26 17.5l352 353q12 12 17.5 27t5.5 30q0 15-5.5 29.5T856-390ZM513-160l286-286-353-354H160v286l353 354ZM260-640q25 0 42.5-17.5T320-700q0-25-17.5-42.5T260-760q-25 0-42.5 17.5T200-700q0 25 17.5 42.5T260-640Zm220 160Z"/></svg>';
	$icon_ordre        = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M200-80q-33 0-56.5-23.5T120-160v-451q-18-11-29-28.5T80-680v-120q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v120q0 23-11 40.5T840-611v451q0 33-23.5 56.5T760-80H200Zm0-520v440h560v-440H200Zm-40-80h640v-120H160v120Zm200 280h240v-80H360v80Zm120 20Z"/></svg>';
	$help_icon  	   = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';
	$add_icon 		   = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M440-280h80v-160h160v-80H520v-160h-80v160H280v80h160v160Zm40 200q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';

	print "<tr><td height = '25' align = 'center' valign = 'top'>";
	print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>"; # Tabel 1.1 ->
	# Dont show close on sidebar
	if ($menu !== "S") {
		print "<td width=5% style=$buttonStyle>
			<a href=$returside accesskey='L'>
			<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
			Luk</button></a></td>";
	}

	print "<td width=75% style=$topStyle align=left><table border=0 cellspacing=2 cellpadding=0><tbody>\n"; # Tabel 1.1.1 ->

		if ($valg=="Vareliste") {
		print "<td width = '200px' align=center id='back-btn'>
			   <a href='vareliste.php?returside=$returside'>
			   <button class='headerbtn navbtn-top' style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">
			   $icon_vareliste Vareliste
			   </button></a></td>";
		} else {
			print "<td width = '200px' align=center id='back-btn'>
				<a href='vareliste.php?returside=$returside'>
				<button class='headerbtn navbtn-top' style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">
				$icon_vareliste  Vareliste
				</button></a></td>";
		}

		if (substr($rettigheder,5,1)) {
			print "<td>&nbsp;</td>";

			if ($valg=="Ordrevisning") {
				print "<td width = '200px' align=center id='ordrevisning'>
					<button class='headerbtn navbtn-top' style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">
					$icon_ordre Ordrevisning 
					</button></td>";
			} else {
				print "<td width = '200px' align=center id='ordrevisning'>
					<a href='ordrestatus.php?returside=$returside'>
					<button class='headerbtn navbtn-top' style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">
					$icon_ordre Ordrevisning 
					</button></a></td>";
			}
		}

		if (substr($rettigheder,7,1)) {
			print "<td>&nbsp;</td>";

			if ($valg=="Indkøb") {
				print "<td width = '200px' align=center id='indkob'>
					<button class='headerbtn navbtn-top' style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">
					$icon_indkob Indkøb 
					</button></td>";
			} else {
				print "<td width = '200px' align=center id='indkob'>
					<a href='indkøb.php?returside=$returside'>
					<button class='headerbtn navbtn-top' style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">
					$icon_indkob Indkøb 
					</button></a></td>";
			}
		}

		print "<td>&nbsp;</td>";

		if ($valg=="Serienumre") {
			print "<td width = '200px' align=center id='serial'>
				<button class='headerbtn navbtn-top' style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">
				$icon_serialnumber Serienumre 
				</button></td>";
		} else {
			print "<td width = '200px' align=center id='serial'>
				<a href='serialnumber.php?returside=$returside'>
				<button class='headerbtn navbtn-top' style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">
				$icon_serialnumber Serienumre 
				</button></a></td>";
		}
	print "</tbody></table></td>\n"; # <- Tabel 1.1.1

	/*switch ($valg) {
		case 'Vareliste':
			$icon = $icon_vareliste;
			break;
		case 'Serienumre':
			$icon = $icon_serialnumber;
			break;
		case 'Indkøb':
			$icon = $icon_indkob;
			break;
		case 'Ordrevisning':
			$icon = $icon_ordre;
			break;
		default:
			# code...
			break;
	}

	print "<td style='width: 10%' align=center>
		<button class='headerbtn' style='$buttonStyle; width:100%; padding: 3px'\" id='nav-indicator'>
		$icon $valg 
		</button></td>";*/

	print "<td id='tutorial-help' width=5% style=$buttonStyle>";
	print "<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
	print $help_icon;
	print findtekst('2564|Hjælp', $sprog_id)."</button></td>";
	if ($valg=="Vareliste") {
		print "<td id='create-new' width=5% style=$buttonStyle>
			<a href=../varekort.php accesskey='L'>
			<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
				$add_icon
				Ny  
			</button></a></td>";
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
</style>