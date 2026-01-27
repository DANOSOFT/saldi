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
	#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td valign=\"top\">";
// print "<script>
// 		document.addEventListener('DOMContentLoaded', function () {
// 		var input = document.getElementById('table-search');
// 		if (!input) return;

// 		var selector = input.getAttribute('data-target');
// 		var table = selector ? document.querySelector(selector) : document.querySelector('.js-filter-table');
// 		if (!table) return;

// 		function getAllRows() {
// 			var rows = [];
// 			if (table.tBodies && table.tBodies.length) {
// 			for (var i = 0; i < table.tBodies.length; i++) {
// 				rows = rows.concat([].slice.call(table.tBodies[i].rows));
// 			}
// 			} else {
// 			rows = [].slice.call(table.querySelectorAll('tr'));
// 			}
// 			return rows;
// 		}

// 		function filter() {
// 			var q = (input.value || '').toLowerCase();
// 			var rows = getAllRows();

// 			for (var i = 0; i < rows.length; i++) {
// 			// always show the first row (header or special row)
// 			if (i === 0) { rows[i].style.display = ''; continue; }

// 			var text = (rows[i].innerText || rows[i].textContent || '').toLowerCase();
// 			rows[i].style.display = (q === '' || text.indexOf(q) !== -1) ? '' : 'none';
// 			}
// 		}

// 		input.addEventListener('input', filter);
// 		filter();
// 		});
// 		</script>";

	#print "<input type='text' id='table-search' name='search' placeholder='Søgning'>";
	
	
	// print"<table cellpadding='1' cellspacing='1' border='0' width='100%' valign = 'top' class='dataTable js-filter-table'>";
	// print"<tbody><tr>";
	// print"<td><b><a href=ordre.php?sort=kontonr&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst('357|Kundenr.', $sprog_id)."</b></td>";
	// print"<td><b><a href=ordre.php?sort=firmanavn&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst('138|Navn', $sprog_id)."</b></td>";
	// print"<td><b><a href=ordre.php?sort=addr1&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst('648|Adresse', $sprog_id)."</b></td>";
	// print"<td><b><a href=ordre.php?sort=addr2&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst('362|Adresse 2', $sprog_id)."</b></td>";
	// print"<td><b><a href=ordre.php?sort=postnr&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst('36|Postnr.', $sprog_id)."</b></td>";
	// print"<td><b><a href=ordre.php?sort=bynavn&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst('1055|By', $sprog_id)."</b></td>";
	// print"<td><b><a href=ordre.php?sort=land&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst('364|Land', $sprog_id)."</b></td>";
	// print"<td><b><a href=ordre.php?sort=kontakt&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst('632|Kontaktperson', $sprog_id)."</b></td>";
	// print"<td><b><a href=ordre.php?sort=tlf&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst('37|Telefon', $sprog_id)."</b></td>";
	// print" </tr>\n";

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



	
	

	//  $qtxt = "select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'K' ";
	//  if ($find) $qtxt.= "and $fokus like '$find' ";
	//  $qtxt.= "order by $sort";
	 
	// $q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	// while ($r = db_fetch_array($q)) {
	// 	$kontonr=str_replace(" ","",$r['kontonr']);
	// 	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
	// 	else {$linjebg=$bgcolor5; $color='#000000';}
	// 	print "<tr bgcolor=\"$linjebg\">";
	// 	print "<td><a href=ordre.php?fokus=$fokus&id=$id&konto_id=$r[id]>$r[kontonr]</a></td>";
	// 	print "<td>".htmlentities($r['firmanavn'],ENT_COMPAT,$charset)."</td>";
	// 	print "<td>".htmlentities( $r['addr1'],ENT_COMPAT,$charset)."</td>";
	// 	print "<td>".htmlentities( $r['addr2'],ENT_COMPAT,$charset)."</td>";
	// 	print "<td> $r[postnr]</td>";
	// 	print "<td>".htmlentities( $r['bynavn'],ENT_COMPAT,$charset)."</td>";
	// 	print "<td> ".htmlentities($r['land'],ENT_COMPAT,$charset)."</td>";
	// 	print "<td>".htmlentities( $r['kontakt'],ENT_COMPAT,$charset)."</td>";
	// 	print "<td> $r[tlf]</td>";
	// 	print "</tr>\n";
	// }

	 print "</tbody></table></td></tr></tbody></table>";

	require_once __DIR__ . '/_accountLookupHelper.php';

	if ($menu=='T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}
	exit;
}