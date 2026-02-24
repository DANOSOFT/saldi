<?php
// ../kreditor/accountLookup.php
function kontoopslag($sort, $fokus, $id, $find){

	global $bgcolor,$bgcolor5;
	global $charset;
	global $memu;
	global $sprog_id;
	global $x;

	$linjebg = NULL;
	$menu = if_isset($menu,NULL);
	
 	if ($menu=='T') {
 		include_once '../includes/top_header.php';
 		include_once '../includes/top_menu.php';
 	}
 	
	if ($find) $find=str_replace("*","%",$find);

	sidehoved($id, "../kreditor/ordre.php", "../kreditor/kreditorkort.php", $fokus, "Leverand&oslash;rordre $id");
    $sort = if_isset($_GET, 'firmanavn','sort');
	

	 ####=====================================search functionality - input filter row
  	print "<table class='dataTable js-filter-table' cellpadding='1' cellspacing='1' border='0' width='100%' valign='top'>";
	print "<thead id='headerAndFilterRows'>";

	// === Header Row with JS sorting ===
	print "<tr>";
	print "<th><a href='#' onclick=\"changeSort('kontonr'); return false;\">" . findtekst('357|Kundenr.', $sprog_id) . "</a></th>";
	print "<th><a href='#' onclick=\"changeSort('firmanavn'); return false;\">" . findtekst('138|Navn', $sprog_id) . "</a></th>";
	print "<th><a href='#' onclick=\"changeSort('addr1'); return false;\">" . findtekst('648|Adresse', $sprog_id) . "</a></th>";
	print "<th><a href='#' onclick=\"changeSort('addr2'); return false;\">" . findtekst('362|Adresse 2', $sprog_id) . "</a></th>";
	print "<th><a href='#' onclick=\"changeSort('postnr'); return false;\">" . findtekst('36|Postnr.', $sprog_id) . "</a></th>";
	print "<th><a href='#' onclick=\"changeSort('bynavn'); return false;\">" . findtekst('1055|By', $sprog_id) . "</a></th>";
	print "<th><a href='#' onclick=\"changeSort('land'); return false;\">" . findtekst('364|Land', $sprog_id) . "</a></th>";
	print "<th><a href='#' onclick=\"changeSort('kontakt'); return false;\">" . findtekst('632|Kontaktperson', $sprog_id) . "</a></th>";
	print "<th><a href='#' onclick=\"changeSort('tlf'); return false;\">" . findtekst('37|Telefon', $sprog_id) . "</a></th>";
	print "</tr>";

	// === Filter Input Row ===
	print "<tr>";
	print "<th><input type='text' id='filter_kontonr' placeholder='Konto nr' style='width:100%'></th>";
	print "<th><input type='text' id='filter_firmanavn' placeholder='Navn' style='width:100%'></th>";
	print "<th><input type='text' id='filter_addr1' placeholder='Adresse 1' style='width:100%'></th>";
	print "<th><input type='text' id='filter_addr2' placeholder='Adresse 2' style='width:100%'></th>";
	print "<th><input type='text' id='filter_postnr' placeholder='Postnr' style='width:100%'></th>";
	print "<th><input type='text' id='filter_bynavn' placeholder='By' style='width:100%'></th>";
	print "<th><input type='text' id='filter_land' placeholder='Land' style='width:100%'></th>";
	print "<th><input type='text' id='filter_kontakt' placeholder='Kontaktperson' style='width:100%'></th>";
	print "<th><input type='text' id='filter_tlf' placeholder='Telefon' style='width:100%'></th>";
	print "</tr>";

	print "</thead>";
	print "<tbody id='tableBody'></tbody>"; // JavaScript will populate this
	print "</table>";

	print "</tbody></table></td></tr></tbody></table>";

	require_once __DIR__ . '/_accountLookupHelper.php';

	if ($menu=='T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}
	exit;
}