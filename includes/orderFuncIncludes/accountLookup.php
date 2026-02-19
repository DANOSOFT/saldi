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

    // ============ SD-338: Create new customer form ============
    // Old form (commented out for reference - from f31c650f):
    // print "<tr id='noResultsForm' style='display:none;'><td colspan='9' style='text-align: right; vertical-align: top;'>";
    // print "<form name=\"create_debtor\" action=\"ordre.php?id=$id&sag_id=$sag_id&returside=$returside\" method=\"post\">\n";
    // print "<tr><td colspan='9' align='center' valign='top'><table><tbody>";
    // print "<tr><td colspan = '2' align = 'center'><big><b>$txt2118</b></big></td></tr>";
    // print "<tr><td colspan = '2'><hr></td></tr>";
    // print "<tr><td>$txt357</td><td><input style='width:150px;' type='text' name='kontonr' value=\"$kontonr\"></td></tr>";
    // print "<tr><td style='width:100px;'>$txt646</td><td><input style='width:150px;' type='text' name='firmanavn' value=\"$firmanavn\"></td></tr>";
    // print "<tr><td style='width:100px;'>$txt648</td><td><input style='width:150px;' type='text' name='addr1' value=\"$addr1\"></td></tr>";
    // print "<tr><td style='width:100px;'>$txt648</td><td><input style='width:150px;' type='text' name='addr2' value=\"$addr2\"></td></tr>";
    // print "<tr><td style='width:100px;'>$txt650</td><td><input style='width:150px;' type='text' name='postnr' value=\"$postnr\"></td></tr>";
    // print "<tr><td style='width:100px;'>$txt651</td><td><input style='width:150px;' type='text' name='bynavn' value=\"$bynavn\"></td></tr>";
    // print "<tr><td style='width:100px;'>$txt377</td><td><input style='width:150px;' type='text' name='tlf' value=\"\"></td></tr>";
    // print "<tr><td style='width:100px;'>$txt398</td><td><input style='width:150px;' type='text' name='kontakt' value=\"$kontakt\"></td></tr>";
    // print "<tr><td style='width:100px;'>$txt402</td><td><input style='width:150px;' type='text' name='email' value=\"$email\"></td></tr>";
    // print "<tr><td style='width:100px;'>$txt48</td><td><input style='width:150px;' type='text' name='$txt48' value=\"$cvrnr\"></td></tr>";
    // print "<tr><td style='width:100px;'>Ean</td><td><input style='width:150px;' type='text' name='ean' value=\"$ean\"></td></tr>";
    // print "<tr><td style='width:100px;'>$txt56</td><td><select style='width:125px;' name='betalingsbet'>...</select><input style='width:25px;text-align:right;' type='text' name='betalingsdage' value=\"$defaultPdays\">";
    // print "<tr><td>$txt63</td><td><select style='width:150px;' name='grp'>...</select></td></tr>";
    // print "<tr><td colspan='2' align='center'><input style='width:250px;' type='submit' name=create_debtor value=\"$txt1232\"></td></tr>";
    // print "</tbody></table></td></tr>";

    // New form (SD-338) - clean table-based UI matching debtor card style
    // Shown/hidden by JS in _accountLookupHelper.php via noResultsForm element

    // Get next available account number
    if (!$kontonr)
        $kontonr = get_next_number('adresser', 'D');

    // Fetch debtor groups
    $x = 0;
    $grp_nr = array();
    $grp_name = array();
    $qtxt = "select * from grupper where art='DG' and fiscal_year = '$regnaar' order by kodenr";
    $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($q)) {
        $grp_nr[$x] = $r['kodenr'];
        $grp_name[$x] = $r['beskrivelse'];
        $x++;
    }

    // Determine most common payment terms from last 100 debtors
    $pMax = $x = 0;
    $pterms = array();
    $pcount = array();
    $defaultPterm = '';
    $defaultPdays = '';
    $qtxt = "select betalingsbet,betalingsdage from adresser where art='D' order by id desc limit 100";
    $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($q)) {
        $tmp = $r['betalingsbet'] . "|" . $r['betalingsdage'];
        if (in_array($tmp, $pterms)) {
            for ($y = 0; $y < count($pterms); $y++) {
                if ($pterms[$y] == $tmp) {
                    $pcount[$y]++;
                    if ($pMax < $pcount[$y]) {
                        $pMax = $pcount[$y];
                        $defaultPterm = $r['betalingsbet'];
                        $defaultPdays = $r['betalingsdage'];
                    }
                }
            }
        } else {
            $pterms[$x] = $tmp;
            $pcount[$x] = 1;
            if ($pMax < $pcount[$x]) {
                $pMax = $pcount[$x];
                $defaultPterm = $r['betalingsbet'];
                $defaultPdays = $r['betalingsdage'];
            }
            $x++;
        }
    }
    $pay_terms = array('Kontant', 'Netto', 'Lb. md.', 'Forud');
    $ptName[0] = findtekst(370, $sprog_id); // Kontant
    $ptName[1] = findtekst(372, $sprog_id); // Netto
    $ptName[2] = findtekst(373, $sprog_id); // Lb. md.
    $ptName[3] = findtekst(369, $sprog_id); // Forud

    if (!$defaultPterm) $defaultPterm = 'Netto';
    if (!$defaultPdays) $defaultPdays = '8';

    // Build payment terms options (default first)
    $payOptions = '';
    for ($x = 0; $x < count($pay_terms); $x++) {
        if ($defaultPterm == $pay_terms[$x])
            $payOptions .= "<option value='" . htmlspecialchars($pay_terms[$x]) . "'>" . htmlspecialchars($ptName[$x]) . "</option>";
    }
    for ($x = 0; $x < count($pay_terms); $x++) {
        if ($defaultPterm != $pay_terms[$x])
            $payOptions .= "<option value='" . htmlspecialchars($pay_terms[$x]) . "'>" . htmlspecialchars($ptName[$x]) . "</option>";
    }

    // Build group options
    $grpOptions = '';
    if (!empty($grp_nr) && is_array($grp_nr) && is_array($grp_name)) {
        for ($x = 0; $x < count($grp_nr); $x++) {
            $nr = htmlspecialchars($grp_nr[$x]);
            $name = htmlspecialchars($grp_name[$x] ?? '');
            $grpOptions .= "<option value='$nr'>$nr : $name</option>";
        }
    }

    $kontonr_safe = htmlspecialchars($kontonr);
    $firmanavn_safe = htmlspecialchars($firmanavn);
    $addr1_safe = htmlspecialchars($addr1);
    $addr2_safe = htmlspecialchars($addr2);
    $postnr_safe = htmlspecialchars($postnr);
    $bynavn_safe = htmlspecialchars($bynavn);
    $kontakt_safe = htmlspecialchars($kontakt);
    $email_safe = htmlspecialchars($email);
    $cvrnr_safe = htmlspecialchars($cvrnr);
    $ean_safe = htmlspecialchars($ean);
    $defaultPdays_safe = htmlspecialchars($defaultPdays);

    // Render the create customer form (hidden by default, shown when no results)
    print <<<CREATEFORM
<div id='noResultsForm' style='display:none; padding: 20px 0;'>
  <form name="create_debtor" action="ordre.php?id=$id&sag_id=$sag_id&returside=$returside" method="post">
    <table align="center" cellpadding="3" cellspacing="0" style="border-collapse:collapse;">
      <tr>
        <td colspan="2" align="center" style="padding-bottom:8px;">
          <big><b>$txt2118</b></big>
        </td>
      </tr>
      <tr><td colspan="2"><hr></td></tr>
      <tr bgcolor="#f5f5f5">
        <td style="width:120px;">$txt357</td>
        <td><input class="inputbox" style="width:200px;" type="text" name="kontonr" value="$kontonr_safe"></td>
      </tr>
      <tr>
        <td>$txt646</td>
        <td><input class="inputbox" style="width:200px;" type="text" name="firmanavn" value="$firmanavn_safe"></td>
      </tr>
      <tr bgcolor="#f5f5f5">
        <td>$txt648</td>
        <td><input class="inputbox" style="width:200px;" type="text" name="addr1" value="$addr1_safe"></td>
      </tr>
      <tr>
        <td>$txt648</td>
        <td><input class="inputbox" style="width:200px;" type="text" name="addr2" value="$addr2_safe"></td>
      </tr>
      <tr bgcolor="#f5f5f5">
        <td>$txt650</td>
        <td><input class="inputbox" style="width:80px;" type="text" name="postnr" value="$postnr_safe"> <input class="inputbox" style="width:112px;" type="text" name="bynavn" value="$bynavn_safe"></td>
      </tr>
      <tr>
        <td>$txt377</td>
        <td><input class="inputbox" style="width:200px;" type="text" name="phone" value=""></td>
      </tr>
      <tr bgcolor="#f5f5f5">
        <td>$txt398</td>
        <td><input class="inputbox" style="width:200px;" type="text" name="kontakt" value="$kontakt_safe"></td>
      </tr>
      <tr>
        <td>$txt402</td>
        <td><input class="inputbox" style="width:200px;" type="text" name="email" value="$email_safe"></td>
      </tr>
      <tr bgcolor="#f5f5f5">
        <td>$txt48</td>
        <td><input class="inputbox" style="width:200px;" type="text" name="cvrnr" value="$cvrnr_safe"></td>
      </tr>
      <tr>
        <td>Ean</td>
        <td><input class="inputbox" style="width:200px;" type="text" name="ean" value="$ean_safe"></td>
      </tr>
      <tr bgcolor="#f5f5f5">
        <td>$txt56</td>
        <td><select class="inputbox" style="width:130px;" name="betalingsbet">$payOptions</select> <input class="inputbox" style="width:40px; text-align:right;" type="text" name="betalingsdage" value="$defaultPdays_safe"></td>
      </tr>
      <tr>
        <td>$txt63</td>
        <td><select class="inputbox" style="width:200px;" name="grp">$grpOptions</select></td>
      </tr>
      <tr><td colspan="2"><hr></td></tr>
      <tr>
        <td colspan="2" align="center" style="padding-top:8px;">
          <input style="width:200px;" type="submit" name="create_debtor" value="$txt1232">
        </td>
      </tr>
    </table>
  </form>
</div>
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
