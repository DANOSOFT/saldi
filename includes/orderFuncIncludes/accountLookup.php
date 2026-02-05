<?php
// ../includes/orderFuncIncludes/accountLookup.php
function kontoopslag($o_art, $sort, $fokus, $id, $kontonr, $firmanavn, $addr1, $addr2, $postnr, $bynavn, $land, $kontakt, $email, $cvrnr, $ean, $betalingsbet, $betalingsdage)
{
    ?>
    <style>
    /* Remove scrollbars */
        html, body {
            overflow: hidden;
        
        }
        </style>
    <?php
  
    $kontonr = (int) $kontonr;

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
    echo $fokus;
    file_put_contents("../temp/accountLookup.txt", "$href \n", FILE_APPEND);

    // Include the grid system
    require_once '../includes/orderFuncIncludes/grid_account_lookup.php';

    // Define grid configuration
    $grid_id = 'accountLkUp_' . $kontonr;
	$grid_id = substr($grid_id, 0, 19); // Trim to 19 characters

    
	if(!$fokus){ $fokus='kontonr'; }
	
	// Pre-populate search field with the initial search value ($find)
	// Debug: Log the values being passed
	file_put_contents("../temp/accountLookup.txt", "accountLookup: fokus=$fokus, firmanavn=$firmanavn, find=$find, grid_id=$grid_id\n", FILE_APPEND);
	
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
				file_put_contents("../temp/accountLookup.txt", "accountLookup: Set search[$grid_id][$searchField] = $cleanFind\n", FILE_APPEND);
			}
		}
	} else {
		file_put_contents("../temp/accountLookup.txt", "accountLookup: find is empty or invalid - find='$find'\n", FILE_APPEND);
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

    // Create the datagrid
    $rows = create_datagrid($grid_id, $grid_data);

    // account selection JavaScript
    echo <<<HTML
    <script>
/*     function selectAccount{$id}(fokus , konto_id) {
        console.log(fokus);
        // Navigate back to order page with selected account
        window.location.href = "$href?id=$id&fokus=" + fokus + "&konto_id=" + konto_id;
    } */
    
   
    </script>
HTML;

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
