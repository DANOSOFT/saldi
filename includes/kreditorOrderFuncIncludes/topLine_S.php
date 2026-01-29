<?php
//----includes/kreditorOrderFuncIncludes/topLine_S.php---patch 4.1.1 ----2025-12-03---
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
// 20251203 LOE Created file to standardize top used for managing S menu in kreditor/ordre.php



print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>".findtekst(547,$sprog_id)."</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";

    ######################
	$tilbage_icon  = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';

 
	$help_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';
	$add_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M440-280h80v-160h160v-80H520v-160h-80v160H280v80h160v160Zm40 200q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';


	#####################


	print "<body bgcolor=\"#339999\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\" center=\"\">";
	print "<div align=\"center\">";


	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";

	if ($kort) print "<td width=\"5%\">$color<a href=../kreditor/ordre.php?id=$id&fokus=$fokus accesskey=L>
					  <button type='button' style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">Luk</button></a></td>";
		  elseif($valg){
			
			 print "<td width=\"5%\">$color
					  <a href=\"javascript:confirmClose('../includes/luk.php?returside=$returside?valg=$valg&konto_id=','$alerttekst')\" accesskey=L>
					  <button class='headerbtn' type='button' style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
					print "$tilbage_icon" .findtekst('30|Tilbage', $sprog_id)."</button></a></td>";

		  }else{
			 print "<td width=\"5%\">$color
					  <a href=\"javascript:confirmClose('../includes/luk.php?returside=$returside&tabel=ordrer&id=$id','$alerttekst')\" accesskey=L>
					  <button class='headerbtn' type='button' style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
					 print "$tilbage_icon" .findtekst('30|Tilbage', $sprog_id)."</button></a></td>";
		  }

	print "<td width=\"75%\" align='center' style='$topStyle'>$color$tekst</td>";
	print "<td id='tutorial-help' width=5% style=$buttonStyle>";
	print "<button class='center-btn' type='button' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
	print "$help_icon".findtekst('2564|Hjælp', $sprog_id)."</button></td>";
	if (($kort!="../lager/varekort.php" && $returside != "ordre.php")&&($id)) {
		
		  print "<td width=\"5%\">$color
           <a href=\"#\" onclick=\"if(confirm('$alerttekst')) { window.location='ordre.php?returside=ordreliste.php'; return false; }\" accesskey=N>
           <button type=\"button\" class='center-btn' style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
     print "$add_icon" .findtekst(39, $sprog_id)."</button></a></td>";

	} else if (($kort=="../lager/varekort.php" && $returside == "ordre.php")&&($id)) {
		
		print "<td width=\"5%\"> $color<a href=\"$kort?returside=$returside&ordre_id=$id\" accesskey=N>
			   <button class='center-btn' style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
			   print "$add_icon" .findtekst(39, $sprog_id)."</button></a></td>";

	} elseif ($kort=="../kreditor/kreditorkort.php") {
		
		print "<td width=\"5%\" onClick=\"javascript:kreditor_vis=window.open('kreditorvisning.php','kreditor_vis','scrollbars=1,resizable=1');kreditor_vis.focus();\">
			   <span title='".findtekst(1521, $sprog_id)."'><u>
			   <button style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
			   .findtekst(813, $sprog_id)."</button></u></span></td>"; #20210716
		print "<td width=\"5%\">$color
			   <a href=\"javascript:confirmClose('$kort?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus','$alerttekst')\" accesskey=N>
			  <button class='center-btn' style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
			  print "$add_icon" .findtekst(39, $sprog_id)."</button></a></td>";

	} elseif (($id)||($kort!="../lager/varekort.php")) {
		print "<td width=\"5%\">$color
			   <a href=\"javascript:confirmClose('$kort?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus','$alerttekst')\" accesskey=N>
			   <button class='center-btn' style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">";
			  print "$add_icon" .findtekst(39, $sprog_id)."</button></a></td>";
	}
	else {
		print "<td width=\"5%\" align='center' style='$topStyle'><br></td>";
	}

	print "</tbody></table>";
	print "</td></tr>\n";
	print "<tr><td valign=\"top\" align=center>";
	
    print "<div class=\"ordreform\">\n";
?>

<style>
	/* Existing styles for buttons */
	.headerbtn, .center-btn {
		display: flex;
		align-items: center;
		text-decoration: none;
		gap: 5px;
	}

	
tfoot tr td #footer-box {
    margin-bottom: 17px; 
    display: flex;
    align-items: center;
    gap: 10px;
    justify-content: flex-end;
}
a:link{
		text-decoration: none;
	}

</style>
