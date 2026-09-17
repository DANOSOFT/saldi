<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/topLineKassekladde.php --- patch 5.0.0 --- 2026.01.26 ---
// LICENSE
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// -----------------------------------------------------------------------------------
// 20260126 PHR fixed $exitDraft
// 20260904 Sawaneh Gear button for the Customize view panel added next to 'Ny' (editable journals only);
//                  the panel itself and its logic live in kassekladde.php.

$border = 'border:1px';
$TableBG = "bgcolor=$bgcolor";

// Icons for buttons (matching topLineFinans.php style)
$icon_back = '<svg xmlns="http://www.w3.org/2000/svg" width="20px" height="20px" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8l-4 4 4 4M16 12H9"></path></svg>';
$icon_kassekladde = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M280-600v-80h560v80H280Zm0 160v-80h560v80H280Zm0 160v-80h560v80H280ZM160-600q-17 0-28.5-11.5T120-640q0-17 11.5-28.5T160-680q17 0 28.5 11.5T200-640q0 17-11.5 28.5T160-600Zm0 160q-17 0-28.5-11.5T120-480q0-17 11.5-28.5T160-520q17 0 28.5 11.5T200-480q0 17-11.5 28.5T160-440Zm0 160q-17 0-28.5-11.5T120-320q0-17 11.5-28.5T160-360q17 0 28.5 11.5T200-320q0 17-11.5 28.5T160-280Z"/></svg>';
$help_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';
$add_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M440-280h80v-160h160v-80H520v-160h-80v160H280v80h160v160Zm40 200q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';

$tekst = findtekst('154|Dine ændringer er ikke blevet gemt! Tryk OK for at forlade siden uden at gemme.', $sprog_id);

// Header row - matching topLineFinans.php structure
print "<tr><td height='25' align='center' valign='top' class='top-header'>";
print "<table class='topLine' width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody><tr class='header-row'>"; # Tabel 1.1 ->

# Back button
$backTargetS = ($backUrl == '../finans/kladdeliste.php') ? "$backUrl?exitDraft=$kladde_id&line=". __line__ : $backUrl;
print "<td width=5% style='$buttonStyle'>
	<a href=\"javascript:confirmClose('" . htmlspecialchars($backTargetS, ENT_QUOTES, $charset) . "','$tekst')\" accesskey='L'>
	<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
	$icon_back ".findtekst('30|Tilbage', $sprog_id)."</button></a></td>";


?>
	<td width='100%' style='<?php echo $topStyle ?>' align=left>
		<div style='display:flex; align-items:center;'>
			<div style='width: 200px; padding: 2px;' align=center id='back-btn'>
				<button class='headerbtn navbtn-top' style='<?php echo $butDownStyle ?>; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">
					<?php print $icon_kassekladde . findtekst('1072|Kassekladde', $sprog_id) . $kladde_id ?>
				</button>
			</div>
			<?php if (
				$bogfort == "-" // not yet bogfort
				|| $bogfort == "" // new kassekladde
				) { ?>
			<style>
				.auth-status-icon {
					display: inline-flex;
					align-items: center;
					gap: 4px;
					margin-left: auto;
					padding: 6px;
					color: white;
				}
				.auth-status-icon img {
					width: 16px;
					height: 16px;
				}
			</style>
			<?php include(__DIR__ . '/../../bank_integration/includes/auth_check_icon.php'); ?>
			<?php } // bogfort == "-" || $bogfort == "" ?>
		</div>
	</td>
<?php

print "<td id='tutorial-help' width=5% style='$buttonStyle'>";
print "<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
print $help_icon;
print findtekst('2564|Hjælp', $sprog_id)."</button></td>";

print "<td id='create-new' width='5%' style='$buttonStyle'>
	<a href=\"javascript:confirmClose('../finans/kassekladde.php?exitDraft=$kladde_id','$tekst')\" accesskey='N'>
	<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
		$add_icon ".
		findtekst('39|Ny', $sprog_id)."
	</button></a></td>";

if ($bogfort == '-' || $bogfort == '') { // gear only where the Customize view panel exists (editable journal)
	$gear_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M370-80l-16-128q-13-5-24.5-12T307-235l-119 50L78-375l103-78q-1-7-1-13.5v-27q0-6.5 1-13.5L78-585l110-190 119 50q11-8 23-15t24-12l16-128h220l16 128q13 5 24.5 12t22.5 15l119-50 110 190-103 78q1 7 1 13.5v27q0 6.5-2 13.5l103 78-110 190-118-50q-11 8-23 15t-24 12L590-80H370Zm112-260q58 0 99-41t41-99q0-58-41-99t-99-41q-59 0-99.5 41T342-480q0 58 40.5 99t99.5 41Z"/></svg>';
	$gear_title = htmlspecialchars(findtekst('3380|Tilpas visning', $sprog_id), ENT_QUOTES, $charset);
	print "<td id='kk-vis-cell' width='3%' style='$buttonStyle'>
		<button type='button' id='kkVisToggle' class='center-btn' style='$buttonStyle; width:100%; justify-content:center;' title='$gear_title' onMouseOver=\"this.style.cursor='pointer'\">
		$gear_icon</button></td>";
}

print "</tr></tbody></table></td></tr>\n"; # <- Tabel 1.1

?>

<style>
	.headerbtn, .center-btn {
		display: flex;
		align-items: center;
		text-decoration: none;
		gap: 5px;
	}
	#kkVisToggle svg { transition: transform .25s ease; }
	#kkVisToggle:hover svg { transform: rotate(60deg); }
	 /* no use for underlines on the top href buttons */
	.header-row a,
	.header-row a:link,
	.header-row a:visited,
	.header-row a:hover,
	.header-row a:focus,
	.header-row a:active {
	text-decoration: none;
	}
</style>
