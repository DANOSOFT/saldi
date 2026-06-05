<?php
// ../includes/orderFuncIncludes/accountLookup.php
function kontoopslag($o_art, $sort, $fokus, $id, $kontonr, $firmanavn, $addr1, $addr2, $postnr, $bynavn, $land, $kontakt, $email, $cvrnr, $ean, $betalingsbet, $betalingsdage)
{
    // Store original kontonr before casting (to check if user searched for something)
    $original_kontonr = $kontonr;
    $kontonr = (int) $kontonr;
    
    // Check if this kontonr already exists in the database
    $kontonr_exists = false;
    if ($kontonr > 0) {
        $check_q = db_select("SELECT id FROM adresser WHERE art='D' AND kontonr='" . db_escape_string($kontonr) . "'", __FILE__ . " linje " . __LINE__);
        if (db_fetch_array($check_q)) {
            $kontonr_exists = true;
        }
    }

    global $bgcolor, $bgcolor5, $land, $regnaar, $returside, $sag_id, $sprog_id,$bruger_id;
    global $ordre_id; // Order ID for AJAX search script
    global $o_art_global; // Order art for AJAX search script
    $ordre_id = $id; // Store order ID in global for use in AJAX search
    $o_art_global = $o_art; // Store order art in global for use in AJAX search
    $find = $href = $linjebg = $opret = NULL;
    global $menu;

    $txt48 = findtekst(48, $sprog_id); // Cvr nr.
    $txt56 = findtekst(56, $sprog_id); //Betalingsbet.
    $txt63 = findtekst(63, $sprog_id); //Gruppe
    $txt377 = findtekst(377, $sprog_id); // Telefon
    $txt398 = findtekst(398, $sprog_id); // Kontakt
    $txt402 = findtekst(402, $sprog_id); // E-mail
    $txt646 = findtekst(646, $sprog_id); // Navn
    $txt648 = findtekst(648, $sprog_id); // Adresse
    $txt649 = findtekst(649, $sprog_id);
    $txt650 = findtekst(650, $sprog_id); //Postnr
    $txt1232 = findtekst(1232, $sprog_id); //Opret
    $txt2118 = findtekst(2118, $sprog_id); //Opret ny kunde
    $txt357 = findtekst(357, $sprog_id); // 
    $txt651 = findtekst(651, $sprog_id); // 
    $txt910 = findtekst(910, $sprog_id); // city label
    $txt593 = findtekst(593, $sprog_id); // land
    $txt148 = findtekst(148, $sprog_id); // kontakt label
    $txt37 = findtekst(37, $sprog_id); // tlf

    if (isset($_GET['fokus'])) $fokus = $_GET['fokus'];
    if (isset($_GET['find'])) $find = $_GET['find'];

    if ($menu == 'T') {
        include_once '../includes/top_menu.php';
    }

    if ($fokus == 'kontonr')
        $find = $kontonr;
    elseif (strstr($fokus, 'lev'))
        $find = $firmanavn;
    elseif ($fokus == 'firmanavn')
        $find = $firmanavn;
    elseif ($fokus == 'addr1')
        $find = $addr1;
    elseif ($fokus == 'addr2')
        $find = $addr2;
    elseif ($fokus == 'postnr')
        $find = $postnr;
    elseif ($fokus == 'bynavn')
        $find = $bynavn;
    elseif ($fokus == 'kontakt')
        $find = $kontakt;
    elseif ($fokus == 'vare0')
        $fokus = NULL;

    if ($find != 'kontonr' && $find != '0') {
        if ($find)
            $find = str_replace("*", "%", $find);
        else
            $find = "%";
    }

    $kundeordre = findtekst(1092, $sprog_id);

    if ($o_art == 'DO' || $o_art == 'DK') {
        sidehoved($id, "../debitor/ordre.php", "../debitor/debitorkort.php", $fokus, "$kundeordre $id - Kontoopslag");
        $href = "ordre.php";
    } elseif ($o_art == 'PO') {
        $find = "";
        $fokus = "kontonr";
        sidehoved($id, "../debitor/pos_ordre.php", "../debitor/debitorkort.php", $fokus, "POS ordre $id - Kontoopslag");
        $href = "pos_ordre.php";
    }

        // Fix double scrollbar: visually hide the html scrollbar, keep scrolling functional
    // The datatable-wrapper has its own scrollbar, so we only need one visible scrollbar
    echo '<style>
        html { scrollbar-width: none; -ms-overflow-style: none; }
        html::-webkit-scrollbar { display: none; }
    </style>';

    // Include the grid system
    require_once '../includes/orderFuncIncludes/grid_account_lookup.php';

    // Define grid configuration
    $grid_id = 'accountLkUp_' . $kontonr;
	$grid_id = substr($grid_id, 0, 19); // Trim to 19 characters

    
	if(!$fokus){ $fokus='kontonr'; }
	
	// Map fokus field to grid field name (firmanavn in ordre.php maps to firmanavn column in grid)
	if ($find && $find != '%' && $find != '0') {
		$cleanFind = str_replace('%', '', $find);
		if ($cleanFind) {
			if (!isset($_GET['search'])) {
				$_GET['search'] = array();
			}
			if (!isset($_GET['search'][$grid_id])) {
				$_GET['search'][$grid_id] = array();
			}
			// Determine which grid column field to search based on fokus
			// The grid uses 'firmanavn' as the field name for the "Navn" column
			$searchField = $fokus;
			if ($fokus == 'firmanavn' || strstr($fokus, 'lev')) {
				$searchField = 'firmanavn';  // Grid column field name
			}
			// Set the search parameter
			if (!isset($_GET['search'][$grid_id][$searchField]) || empty($_GET['search'][$grid_id][$searchField])) {
				$_GET['search'][$grid_id][$searchField] = $cleanFind;
			}
		}
	}
    
    // Base query
    $base_query = "SELECT id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf 
                   FROM adresser 
                   WHERE art = 'D' AND lukket != 'on'";

    // Define columns for the grid
    $columns = [
        [
            'field' => 'kontonr',
            'headerName' => ($o_art == 'KO') ? "Leverandørnr" : $txt357,
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
        $column['render'] = function ($value, $row, $column) use ($href, $id, $o_art) {
            $style = "text-align: {$column['align']}; cursor: pointer;";
            $fokus = 'kontonr';

            // Make the entire row clickable to select the account
            $onclick = "selectAccount{$id}( '" . $fokus . "', '{$row['id']}')";
            
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
    // Toggle form visibility based on search results via AJAX
    function toggleCreateForm(show) {
        var form = document.getElementById('createCustomerForm');
        var backdrop = document.getElementById('createCustomerBackdrop');
        if (backdrop) {
            backdrop.style.display = show ? 'block' : 'none';
        }
        if (form) {
            form.style.display = show ? 'block' : 'none';
        }
    }
    </script>
TOGGLESCRIPT;

    // Create the datagrid
    $rows = create_datagrid($grid_id, $grid_data);

    // account selection JavaScript
    echo <<<HTML
    <script>
    function selectAccount{$id}(fokus, konto_id) {
        window.location.href = "$href?id=$id&fokus=" + fokus + "&konto_id=" + konto_id;
    }
    </script>
HTML;

    // ============ SD-338: Create new customer form ============
    // Store searched kontonr before we potentially replace it
    $searched_kontonr = $kontonr;
    $user_searched_for_kontonr = ($searched_kontonr > 0);
    
    // Get next available account number if not provided
    if (!$kontonr)
        $kontonr = get_next_number('adresser', 'D');

    // Fetch debtor groups for dropdown
    $grp_options = '';
    $qtxt = "select * from grupper where art='DG' and fiscal_year = '$regnaar' order by kodenr";
    $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($q)) {
        $grp_nr = htmlspecialchars($r['kodenr']);
        $grp_name = htmlspecialchars($r['beskrivelse']);
        $grp_options .= "<option value='$grp_nr'>$grp_nr : $grp_name</option>";
    }

    // Determine default payment terms
    $defaultPterm = 'Kontant';
    $defaultPdays = '8';
    $qtxt = "select betalingsbet, betalingsdage, count(*) as cnt from adresser where art='D' group by betalingsbet, betalingsdage order by cnt desc limit 1";
    if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
        if ($r['betalingsbet']) $defaultPterm = $r['betalingsbet'];
        if ($r['betalingsdage']) $defaultPdays = $r['betalingsdage'];
    }

    // Payment terms dropdown
    $pay_options = '';
    $pay_terms = array(
        'Kontant' => findtekst('370|Kontant', $sprog_id),
        'Netto' => findtekst('372|Netto', $sprog_id),
        'Lb. md.' => findtekst('373|Lb. md.', $sprog_id),
        'Forud' => findtekst('369|Forud', $sprog_id)
    );
    foreach ($pay_terms as $val => $label) {
        $selected = ($val == $defaultPterm) ? 'selected' : '';
        $pay_options .= "<option value='" . htmlspecialchars($val) . "' $selected>" . htmlspecialchars($label) . "</option>";
    }

    $kontonr_safe = htmlspecialchars($kontonr);
    $defaultPdays_safe = htmlspecialchars($defaultPdays);

    // Labels
    $lbl_create = findtekst('2118|Opret ny kunde', $sprog_id);
    $lbl_name = findtekst('646|Navn', $sprog_id);
    $lbl_address = findtekst('648|Adresse', $sprog_id);
    $lbl_zipcode = findtekst('650|Postnr', $sprog_id);
    $lbl_city = findtekst('910|By', $sprog_id);
    $lbl_telephone = findtekst('377|Telefon', $sprog_id);
    $lbl_contact = findtekst('398|Kontakt', $sprog_id);
    $lbl_email = findtekst('402|E-mail', $sprog_id);
    $lbl_vat = findtekst('48|Cvr nr.', $sprog_id);
    $lbl_payterms = findtekst('56|Betalingsbet.', $sprog_id);
    $lbl_group = findtekst('63|Gruppe', $sprog_id);
    $lbl_submit = findtekst('1232|Opret', $sprog_id);

    // Show create form if user searched for a kontonr that doesn't exist
    // Form starts hidden, AJAX will show it when no results are found
    $showForm = 'none';
    
    // Check if the grid has results - if $rows is empty, show the form
    if (empty($rows) || count($rows) == 0) {
        $showForm = 'block';
    }
    // Also show if user searched for a kontonr that doesn't exist
    if ($user_searched_for_kontonr && !$kontonr_exists) {
        $showForm = 'block';
    }

    print <<<CREATEFORM
<style>
.create-customer-backdrop {
    display: $showForm;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.4);
    z-index: 999;
}
.create-customer-overlay {
    display: $showForm;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1000;
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 20px 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    min-width: 320px;
}
.create-customer-overlay h3 {
    margin: 0 0 15px 0;
    text-align: center;
    font-size: 14px;
    font-weight: normal;
    color: #333;
}
.create-customer-overlay table {
    width: 100%;
    border-collapse: collapse;
}
.create-customer-overlay td {
    padding: 3px 5px;
    vertical-align: middle;
}
.create-customer-overlay td:first-child {
    text-align: right;
    padding-right: 10px;
    white-space: nowrap;
    font-size: 12px;
    color: #333;
}
.create-customer-overlay input[type="text"],
.create-customer-overlay select {
    width: 150px;
    padding: 3px 5px;
    border: 1px solid #ccc;
    font-size: 12px;
}
.create-customer-overlay input[type="text"].small {
    width: 40px;
    text-align: right;
}
.create-customer-overlay .btn-row {
    text-align: center;
    padding-top: 10px;
}
.create-customer-overlay input[type="submit"] {
    width: 150px;
    padding: 5px 10px;
    cursor: pointer;
}
.create-customer-overlay .close-btn {
    position: absolute;
    top: 8px;
    right: 12px;
    font-size: 20px;
    font-weight: bold;
    color: #666;
    cursor: pointer;
    line-height: 1;
}
.create-customer-overlay .close-btn:hover {
    color: #000;
}
</style>

<div class="create-customer-backdrop" id="createCustomerBackdrop" onclick="toggleCreateForm(false)"></div>
<div class="create-customer-overlay" id="createCustomerForm">
  <span class="close-btn" onclick="toggleCreateForm(false)" title="Close">&times;</span>
  <h3>$lbl_create</h3>
  <form name="create_debtor" action="ordre.php" method="post" onsubmit="return validateCreateCustomer()">
   <input type="hidden" name="id" value="$id">
    <table>
      <tr>
        <td></td>
        <td><input type="text" name="kontonr" value="$kontonr_safe"></td>
      </tr>
      <tr>
        <td>$lbl_name <span style="color:red">*</span></td>
        <td><input type="text" name="firmanavn" id="create_firmanavn" value=""></td>
      </tr>
      <tr>
        <td>$lbl_address</td>
        <td><input type="text" name="addr1" value=""></td>
      </tr>
      <tr>
        <td>$lbl_address</td>
        <td><input type="text" name="addr2" value=""></td>
      </tr>
      <tr>
        <td>$lbl_zipcode</td>
        <td><input type="text" name="postnr" value=""></td>
      </tr>
      <tr>
        <td>$lbl_city</td>
        <td><input type="text" name="bynavn" value=""></td>
      </tr>
      <tr>
        <td>$lbl_telephone</td>
        <td><input type="text" name="phone" value=""></td>
      </tr>
      <tr>
        <td>$lbl_contact</td>
        <td><input type="text" name="kontakt" value=""></td>
      </tr>
      <tr>
        <td>$lbl_email</td>
        <td><input type="text" name="email" value=""></td>
      </tr>
      <tr>
        <td>$lbl_vat</td>
        <td><input type="text" name="cvrnr" value=""></td>
      </tr>
      <tr>
        <td>Ean</td>
        <td><input type="text" name="ean" value=""></td>
      </tr>
      <tr>
        <td>$lbl_payterms</td>
        <td><select name="betalingsbet">$pay_options</select> <input type="text" name="betalingsdage" value="$defaultPdays_safe" class="small"></td>
      </tr>
      <tr>
        <td>$lbl_group</td>
        <td><select name="grp">$grp_options</select></td>
      </tr>
      <tr>
        <td colspan="2" class="btn-row">
          <input type="submit" name="create_debtor" value="$lbl_submit">
        </td>
      </tr>
    </table>
  </form>
</div>

<script>
function validateCreateCustomer() {
    var name = document.getElementById('create_firmanavn').value.trim();
    if (name === '') {
        alert('Navn er påkrævet / Name is required');
        document.getElementById('create_firmanavn').focus();
        return false;
    }
    return true;
}
</script>
CREATEFORM;
    // ============ End of create new customer form ============

    if ($o_art == 'PO')
        print "<script language=\"javascript\">document.kontoopslag.kontonr.focus();</script>";
    else
        print "<BODY onLoad=\"javascript:document.getElementById('fokus').focus()\">";

    if ($menu == 'T') {
        include_once '../includes/topmenu/footer.php';
    } else {
        include_once '../includes/oldDesign/footer.php'; 
    }
    exit;
}
