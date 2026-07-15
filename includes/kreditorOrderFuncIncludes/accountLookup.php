<?php
// ../includes/kreditorOrderFuncIncludes/accountLookup.php
// 20260304 LOE Converted to use grid framework (same as debitor/order account lookup)
// 20260506 sawaneh Added create-new-supplier overlay when looked-up kontonr/firmanavn has no match
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
	$grid_id = 'kreditor_lookup';
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

	// Define toggleCreateForm BEFORE the grid so AJAX can use it
	echo <<<TOGGLESCRIPT
	<script>
	function toggleCreateForm(show) {
		var form = document.getElementById('createCreditorForm');
		var backdrop = document.getElementById('createCreditorBackdrop');
		if (backdrop) backdrop.style.display = show ? 'block' : 'none';
		if (form) form.style.display = show ? 'block' : 'none';
	}
	</script>
TOGGLESCRIPT;

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

	// ============ Create new creditor form ============
	// Show the create form whenever the user searched for something (any field)
	// and the search returned no matching creditors.
	$cleanFind = ($find && $find != '%' && $find != '0') ? trim(str_replace('%', '', $find)) : '';
	$user_searched = ($cleanFind !== '');
	$is_numeric_search = ($user_searched && ctype_digit($cleanFind));

	$has_match = false;
	if ($user_searched) {
		$fe = db_escape_string($cleanFind);
		$cols = ['kontonr','firmanavn','addr1','addr2','postnr','bynavn','land','kontakt','tlf'];
		$ors = [];
		foreach ($cols as $c) $ors[] = "CAST($c AS TEXT) ILIKE '%$fe%'";
		$check_q = db_select("SELECT id FROM adresser WHERE art='K' AND (lukket != 'on' OR lukket IS NULL) AND (" . implode(' OR ', $ors) . ") LIMIT 1", __FILE__ . " linje " . __LINE__);
		if (db_fetch_array($check_q)) $has_match = true;
	}

	// Prefill: numeric search → kontonr; text search → firmanavn
	$prefill_kontonr  = $is_numeric_search ? $cleanFind : get_next_number('adresser', 'K');
	$prefill_firmanavn = (!$is_numeric_search && $user_searched) ? $cleanFind : '';
	$kontonr_default = $prefill_kontonr;

	// Fetch creditor groups
	$grp_options = '';
	$qtxt = "select kodenr, beskrivelse from grupper where art='KG' and fiscal_year='$regnaar' order by kodenr";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$grp_nr = htmlspecialchars($r['kodenr']);
		$grp_name = htmlspecialchars($r['beskrivelse']);
		$grp_options .= "<option value='$grp_nr'>$grp_nr : $grp_name</option>";
	}

	// Default payment terms (most common among creditors)
	$defaultPterm = 'Netto';
	$defaultPdays = '8';
	$qtxt = "select betalingsbet, betalingsdage, count(*) as cnt from adresser where art='K' group by betalingsbet, betalingsdage order by cnt desc limit 1";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		if ($r['betalingsbet']) $defaultPterm = $r['betalingsbet'];
		if ($r['betalingsdage'] !== null && $r['betalingsdage'] !== '') $defaultPdays = $r['betalingsdage'];
	}

	$pay_options = '';
	$pay_terms = array(
		'Forud'   => findtekst('369|Forud', $sprog_id),
		'Kontant' => findtekst('370|Kontant', $sprog_id),
		'Netto'   => findtekst('372|Netto', $sprog_id),
		'Lb. md.' => findtekst('373|Lb. md.', $sprog_id),
	);
	foreach ($pay_terms as $val => $label) {
		$selected = ($val == $defaultPterm) ? 'selected' : '';
		$pay_options .= "<option value='" . htmlspecialchars($val) . "' $selected>" . htmlspecialchars($label) . "</option>";
	}

	$kontonr_safe = htmlspecialchars($kontonr_default);
	$firmanavn_safe = htmlspecialchars($prefill_firmanavn, ENT_QUOTES);
	$defaultPdays_safe = htmlspecialchars($defaultPdays);

	$lbl_create = ($sprog_id == 2) ? 'Create new supplier' : 'Opret ny leverandør';
	$lbl_name = findtekst('360|Navn', $sprog_id);
	$lbl_address = findtekst('648|Adresse', $sprog_id);
	$lbl_zipcode = findtekst('549|Postnr', $sprog_id);
	$lbl_city = findtekst('1055|By', $sprog_id);
	$lbl_telephone = findtekst('37|Telefon', $sprog_id);
	$lbl_contact = findtekst('632|Kontaktperson', $sprog_id);
	$lbl_email = findtekst('402|E-mail', $sprog_id);
	$lbl_vat = 'CVR-nr.';
	$lbl_payterms = findtekst('935|Betalingsbet', $sprog_id);
	$lbl_group = findtekst('63|Gruppe', $sprog_id);
	$lbl_submit = findtekst('1232|Opret', $sprog_id);
	$lbl_required = ($sprog_id == 2) ? 'Name is required' : 'Navn er påkrævet';

	$showForm = ($user_searched && !$has_match) ? 'block' : 'none';

	print <<<CREATEFORM
<style>
.create-creditor-backdrop {
	display: $showForm;
	position: fixed; top: 0; left: 0; width: 100%; height: 100%;
	background: rgba(0,0,0,0.4); z-index: 999;
}
.create-creditor-overlay {
	display: $showForm;
	position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
	z-index: 1000; background: #fff; border: 1px solid #ccc; border-radius: 4px;
	padding: 20px 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); min-width: 320px;
}
.create-creditor-overlay h3 { margin: 0 0 15px 0; text-align: center; font-size: 14px; font-weight: normal; color: #333; }
.create-creditor-overlay table { width: 100%; border-collapse: collapse; }
.create-creditor-overlay td { padding: 3px 5px; vertical-align: middle; }
.create-creditor-overlay td:first-child { text-align: right; padding-right: 10px; white-space: nowrap; font-size: 12px; color: #333; }
.create-creditor-overlay input[type="text"], .create-creditor-overlay select { width: 150px; padding: 3px 5px; border: 1px solid #ccc; font-size: 12px; }
.create-creditor-overlay input[type="text"].small { width: 40px; text-align: right; }
.create-creditor-overlay .btn-row { text-align: center; padding-top: 10px; }
.create-creditor-overlay input[type="submit"] { width: 150px; padding: 5px 10px; cursor: pointer; }
.create-creditor-overlay .close-btn { position: absolute; top: 8px; right: 12px; font-size: 20px; font-weight: bold; color: #666; cursor: pointer; line-height: 1; }
.create-creditor-overlay .close-btn:hover { color: #000; }
</style>

<div class="create-creditor-backdrop" id="createCreditorBackdrop" onclick="toggleCreateForm(false)"></div>
<div class="create-creditor-overlay" id="createCreditorForm">
  <span class="close-btn" onclick="toggleCreateForm(false)" title="Close">&times;</span>
  <h3>$lbl_create</h3>
  <form name="create_creditor" action="ordre.php" method="post" onsubmit="return validateCreateCreditor()">
    <input type="hidden" name="id" value="$id">
    <table>
      <tr><td>Kontonr</td><td><input type="text" name="kontonr" value="$kontonr_safe"></td></tr>
      <tr><td>$lbl_name <span style="color:red">*</span></td><td><input type="text" name="firmanavn" id="create_firmanavn" value="$firmanavn_safe"></td></tr>
      <tr><td>$lbl_address</td><td><input type="text" name="addr1" value=""></td></tr>
      <tr><td>$lbl_address</td><td><input type="text" name="addr2" value=""></td></tr>
      <tr><td>$lbl_zipcode</td><td><input type="text" name="postnr" value=""></td></tr>
      <tr><td>$lbl_city</td><td><input type="text" name="bynavn" value=""></td></tr>
      <tr><td>$lbl_telephone</td><td><input type="text" name="tlf" value=""></td></tr>
      <tr><td>$lbl_contact</td><td><input type="text" name="kontakt" value=""></td></tr>
      <tr><td>$lbl_email</td><td><input type="text" name="email" value=""></td></tr>
      <tr><td>$lbl_vat</td><td><input type="text" name="cvrnr" value=""></td></tr>
      <tr><td>$lbl_payterms</td><td><select name="betalingsbet">$pay_options</select> <input type="text" name="betalingsdage" value="$defaultPdays_safe" class="small"></td></tr>
      <tr><td>$lbl_group</td><td><select name="grp">$grp_options</select></td></tr>
      <tr><td colspan="2" class="btn-row"><input type="submit" name="create_creditor" value="$lbl_submit"></td></tr>
    </table>
  </form>
</div>

<script>
function validateCreateCreditor() {
	var name = document.getElementById('create_firmanavn').value.trim();
	if (name === '') {
		alert('$lbl_required');
		document.getElementById('create_firmanavn').focus();
		return false;
	}
	return true;
}
</script>
CREATEFORM;
	// ============ End create new creditor form ============

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