<?php
// ../includes/kreditorOrderFuncIncludes/accountLookup.php
// 20260304 LOE Converted to use grid framework (same as debitor/order account lookup)
function kontoopslag($sort, $fokus, $id, $find){

	global $bgcolor, $bgcolor5;
	global $charset;
	global $menu;
	global $sprog_id;
	global $regnaar;
	global $bruger_id;
	global $top_bund;              // For old-style (default) menu header
	global $color;                 // For old-style (default) menu header
	global $ajax_lookup_url;       // For grid_account_lookup.php AJAX endpoint
	global $account_lookup_href;   // For grid_account_lookup.php row click navigation
	global $o_art_global;          // For grid_account_lookup.php
	global $ordre_id;              // For grid_account_lookup.php

	// Set globals for the grid system
	$ajax_lookup_url = '../kreditor/accountLookupData.php';
	$account_lookup_href = 'ordre.php';
	$o_art_global = 'KO';
	$ordre_id = $id;

	$linjebg = NULL;

	if ($find) $find = str_replace("*", "%", $find);

	$txt357 = findtekst(357, $sprog_id); // Kundenr.
	$txt646 = findtekst(138, $sprog_id); // Navn
	$txt648 = findtekst(648, $sprog_id); // Adresse
	$txt649 = findtekst(362, $sprog_id); // Adresse 2
	$txt650 = findtekst(36, $sprog_id);  // Postnr
	$txt910 = findtekst(1055, $sprog_id); // By
	$txt593 = findtekst(364, $sprog_id); // Land
	$txt148 = findtekst(632, $sprog_id); // Kontaktperson
	$txt37  = findtekst(37, $sprog_id);  // Telefon

	// Render header matching the debitor's order lookup style per menu type
	$tekst = "Leverand&oslash;rordre $id - Kontoopslag";
	$alerttekst = findtekst(154, $sprog_id);
	$luk_tekst = findtekst(30, $sprog_id);
	$returside = "../kreditor/ordreliste.php";

	include("../includes/topline_settings.php");
	print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";

	if ($menu == 'T') {
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">";
		print "<div class=\"headerbtnLft headLink\"><a href=\"../kreditor/ordre.php?id=$id&fokus=$fokus\" accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;$luk_tekst</a></div>";
		print "<div class=\"headerTxt\">$tekst</div>";
		print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
		print "</div>";
		print "<div class='content-noside'>";
	} elseif ($menu == 'S') {
		// S-menu style: same pattern as debitor's orderFuncIncludes/topLine.php
		$tilbage_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
		$help_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';

		print "<tr><td height='25' align='center' valign='top'>";
		print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";

		print "<td width=5% style='$buttonStyle'>
			<a href=\"../kreditor/ordre.php?id=$id&fokus=$fokus\" accesskey='L'>
			<button class='headerbtn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
			$tilbage_icon " . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>";

		print "<td width=85% style='$topStyle' align=center>$tekst</td>\n";

		print "<td id='tutorial-help' width=5% style='$buttonStyle'>";
		print "<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
		print "$help_icon " . findtekst('2564|Hjælp', $sprog_id) . "</button></td>";

		print "</tbody></table></td></tr>\n";
		print "<tr><td valign='top' align=center>\n";
		?>
		<style>
			.headerbtn, .center-btn {
				display: flex;
				align-items: center;
				text-decoration: none;
				gap: 5px;
			}
			a:link { text-decoration: none; }
		</style>
		<?php
	} elseif ($menu == 'k') {
		// k-menu style: button-based header (matches creditor sidehoved k-menu)
		print "<body bgcolor=\"#339999\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\" center=\"\">";
		print "<div align=\"center\">";
		print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
		print "<tr><td height=\"25\" align=\"center\" valign=\"top\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
		print "<td width=10%><a href=../kreditor/ordre.php?id=$id&fokus=$fokus accesskey=L>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">$luk_tekst</button></a></td>";
		print "<td width='80%' align='center' style='$topStyle'>$tekst</td>";
		print "<td width='10%' align='center' style='$topStyle'><br></td>";
		print "</tbody></table>";
		print "</td></tr>\n";
		print "<tr><td valign=\"top\" align=center>";
	} else {
		// Default menu style: old-style table header (matches creditor sidehoved default)
		print "<body bgcolor=\"#339999\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\" center=\"\">";
		print "<div align=\"center\">";
		print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
		print "<tr><td height=\"25\" align=\"center\" valign=\"top\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
		print "<td width=\"10%\" $top_bund> $color<a href=\"../kreditor/ordre.php?id=$id&fokus=$fokus\" accesskey=L>$luk_tekst</a></td>";
		print "<td width=\"80%\" $top_bund> $color$tekst</td>";
		print "<td width=\"10%\" $top_bund><br></td>";
		print "</tbody></table>";
		print "</td></tr>\n";
		print "<tr><td valign=\"top\" align=center>";
	}

	if (!$fokus) $fokus = 'kontonr';

	// Fix double scrollbar
	echo '<style>
		html { scrollbar-width: none; -ms-overflow-style: none; }
		html::-webkit-scrollbar { display: none; }
	</style>';

	// Include the grid system (same one used by debitor)
	require_once '../includes/orderFuncIncludes/grid_account_lookup.php';

	// Define grid configuration
	$grid_id = 'kredLkUp_' . $id;
	$grid_id = substr($grid_id, 0, 19); // Trim to 19 characters

	// Map fokus field to grid search field
	if ($find && $find != '%' && $find != '0') {
		$cleanFind = str_replace('%', '', $find);
		if ($cleanFind) {
			if (!isset($_GET['search'])) {
				$_GET['search'] = array();
			}
			if (!isset($_GET['search'][$grid_id])) {
				$_GET['search'][$grid_id] = array();
			}
			$searchField = $fokus;
			if ($fokus == 'firmanavn' || strstr($fokus, 'lev')) {
				$searchField = 'firmanavn';
			}
			if (!isset($_GET['search'][$grid_id][$searchField]) || empty($_GET['search'][$grid_id][$searchField])) {
				$_GET['search'][$grid_id][$searchField] = $cleanFind;
			}
		}
	}

	// Base query - only creditors (art = 'K')
	$base_query = "SELECT id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf 
				   FROM adresser 
				   WHERE art = 'K' AND lukket != 'on'";

	// Define columns for the grid
	$columns = [
		[
			'field' => 'kontonr',
			'headerName' => $txt357,
			'type' => 'text',
			'width' => 1,
			'sortable' => true,
			'searchable' => true,
			'align' => 'left',
			'defaultSort' => true,
			'defaultSortDirection' => 'asc'
		],
		[
			'field' => 'firmanavn',
			'headerName' => $txt646,
			'type' => 'text',
			'width' => 2,
			'sortable' => true,
			'searchable' => true,
			'align' => 'left'
		],
		[
			'field' => 'addr1',
			'headerName' => $txt648,
			'type' => 'text',
			'width' => 2,
			'sortable' => true,
			'searchable' => true,
			'align' => 'left'
		],
		[
			'field' => 'addr2',
			'headerName' => $txt649,
			'type' => 'text',
			'width' => 1.5,
			'sortable' => true,
			'searchable' => true,
			'align' => 'left'
		],
		[
			'field' => 'postnr',
			'headerName' => $txt650,
			'type' => 'text',
			'width' => 1,
			'sortable' => true,
			'searchable' => true,
			'align' => 'left'
		],
		[
			'field' => 'bynavn',
			'headerName' => $txt910,
			'type' => 'text',
			'width' => 1.5,
			'sortable' => true,
			'searchable' => true,
			'align' => 'left'
		],
		[
			'field' => 'land',
			'headerName' => lcfirst($txt593),
			'type' => 'text',
			'width' => 1,
			'sortable' => true,
			'searchable' => true,
			'align' => 'left'
		],
		[
			'field' => 'kontakt',
			'headerName' => $txt148,
			'type' => 'text',
			'width' => 1.5,
			'sortable' => true,
			'searchable' => true,
			'align' => 'left'
		],
		[
			'field' => 'tlf',
			'headerName' => $txt37,
			'type' => 'text',
			'width' => 1.5,
			'sortable' => true,
			'searchable' => true,
			'align' => 'left'
		]
	];

	// Add custom renderer for clickable rows
	foreach ($columns as &$column) {
		$column['render'] = function ($value, $row, $column) use ($id) {
			$style = "text-align: {$column['align']}; cursor: pointer;";
			$fokus = 'kontonr';
			$onclick = "selectAccount{$id}('" . $fokus . "', '{$row['id']}')";
			return "<td style='$style' onclick=\"$onclick\">$value</td>";
		};
	}

	$grid_data = [
		'query' => $base_query,
		'columns' => $columns,
		'filters' => []
	];

	// Create the datagrid
	$rows = create_datagrid($grid_id, $grid_data);

	// Account selection JavaScript
	echo <<<HTML
	<script>
	function selectAccount{$id}(fokus, konto_id) {
		window.location.href = "ordre.php?id=$id&fokus=" + fokus + "&konto_id=" + konto_id;
	}
	</script>
HTML;

	// Close structures and render footer based on menu type
	if ($menu == 'T') {
		include_once '../includes/topmenu/footer.php';
	} elseif ($menu == 'S') {
		// Close the <td><tr> opened after the S-menu header
		print "</td></tr></tbody></table>";
		include_once '../includes/oldDesign/footer.php';
	} elseif ($menu == 'k') {
		// Close the table structures opened in k-menu header
		print "</td></tr></tbody></table>";
		include_once '../includes/oldDesign/footer.php';
	} else {
		// Close the table structures opened in default header
		print "</td></tr></tbody></table>";
		include_once '../includes/oldDesign/footer.php';
	}
	exit;
}