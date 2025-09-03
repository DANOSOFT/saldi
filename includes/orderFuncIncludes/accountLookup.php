<?php
function kontoopslag($o_art, $sort, $fokus, $id, $kontonr, $firmanavn, $addr1, $addr2, $postnr, $bynavn, $land, $kontakt, $email, $cvrnr, $ean, $betalingsbet, $betalingsdage)
{
    $kontonr = (int) $kontonr;

    global $bgcolor, $bgcolor5, $land, $regnaar, $returside, $sag_id, $sprog_id;
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
    } else {
        // no else content here
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
        $fokus = NULL; #20160217 

    if ($find != 'kontonr' && $find != '0') {
        if ($find)
            $find = str_replace("*", "%", $find);
        else
            $find = "%";
    }

    $kundeordre = findtekst(1092, $sprog_id);  #20210630

    if ($o_art == 'DO' || $o_art == 'DK') {
        sidehoved($id, "../debitor/ordre.php", "../debitor/debitorkort.php", $fokus, "$kundeordre $id - Kontoopslag");
        $href = "ordre.php";
    } elseif ($o_art == 'PO' || $o_art == 'KO') {
        sidehoved($id, "../debitor/pos_ordre.php", "../debitor/debitorkort.php", $fokus, "POS ordre $id - Kontoopslag");
        $href = "pos_ordre.php";
        $find = "";
        $fokus = "kontonr";
    }

    ####=====================================search functionality - input filter row
  	print "<table class='dataTable' cellpadding='1' cellspacing='1' border='0' width='100%' valign='top'>";
	print "<thead>";

	// Filter input row
	print "<tr>";
	print "<th><input type='text' id='filter_kontonr' placeholder='Konto nr' style='width:100%'></th>";
	print "<th><input type='text' id='filter_firmanavn' placeholder='Firma navn' style='width:100%'></th>";
	print "<th><input type='text' id='filter_addr1' placeholder='Adresse 1' style='width:100%'></th>";
	print "<th><input type='text' id='filter_addr2' placeholder='Adresse 2' style='width:100%'></th>";
	print "<th><input type='text' id='filter_postnr' placeholder='Postnr' style='width:100%'></th>";
	print "<th><input type='text' id='filter_bynavn' placeholder='By' style='width:100%'></th>";
	print "<th><input type='text' id='filter_land' placeholder='Land' style='width:100%'></th>";
	print "<th><input type='text' id='filter_kontakt' placeholder='Kontakt' style='width:100%'></th>";
	print "<th><input type='text' id='filter_tlf' placeholder='Telefon' style='width:100%'></th>";
	print "</tr>";

	// Column headers
	print "<tr>";
	print "<th><a href='$href?sort=kontonr&funktion=kontoOpslag&fokus=$fokus&id=$id'>" . (($o_art == 'KO') ? "Leverandørnr" : $txt357) . "</a></th>";
	print "<th><a href='$href?sort=firmanavn&funktion=kontoOpslag&fokus=$fokus&id=$id'>$txt646</a></th>";
	print "<th><a href='$href?sort=addr1&funktion=kontoOpslag&fokus=$fokus&id=$id'>$txt648</a></th>";
	print "<th><a href='$href?sort=addr2&funktion=kontoOpslag&fokus=$fokus&id=$id'>$txt649</a></th>";
	print "<th><a href='$href?sort=postnr&funktion=kontoOpslag&fokus=$fokus&id=$id'>$txt650</a></th>";
	print "<th><a href='$href?sort=bynavn&funktion=kontoOpslag&fokus=$fokus&id=$id'>$txt910</a></th>";
	print "<th><a href='$href?sort=land&funktion=kontoOpslag&fokus=$fokus&id=$id'>" . lcfirst($txt593) . "</a></th>";
	print "<th><a href='$href?sort=kontakt&funktion=kontoOpslag&fokus=$fokus&id=$id'>$txt148</a></th>";
	print "<th><a href='$href?sort=tlf&funktion=kontoOpslag&fokus=$fokus&id=$id'>$txt37</a></th>";
	print "</tr>";

	print "</thead>";
	print "<tbody id='tableBody'></tbody>"; // This is filled by JS
	print "</table>";
	####=====================================

    if ($o_art == 'PO' || $o_art == 'KO') {
        print "<form NAME='kontoopslag' action='pos_ordre.php?fokus=kontonr&id=$id' method='post'>";
        print "<tr><td><input name='kontonr' size='4'></td>";
        print "<td><input style='width: 0.01em; height: 0.01em;' type='submit' name='Opdat' value=''></td></tr>";
        print "</form>";
    }

    (isset($_GET['sort'])) ? $sort = $_GET['sort'] : $sort = NULL;
    if ($sort) {
        $qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'adresser' AND column_name = '" . db_escape_string($sort) . "'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        if (!$r = db_fetch_array($q)) $sort = "firmanavn";
    } else {
        $sort = "firmanavn";
    }

    if (strstr($fokus, 'lev_'))
        $soeg = 'firmanavn';
    elseif ($firmanavn || $addr1 || $postnr || $bynavn) {
        $opret = 1;
        if ($find = $firmanavn)
            $soeg = 'firmanavn';
        elseif ($find = $addr1)
            $soeg = 'addr1';
        elseif ($postnr = $addr1)
            $soeg = 'postnr';
        elseif ($find = $bynavn)
            $soeg = 'bynavn';
    } else
        $soeg = $fokus;

    ($o_art == 'KO') ? $art = 'K' : $art = 'D';
    $qtxt = "select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = '$art' and lukket != 'on' ";
    if ($soeg && $find) {
        if ($soeg == 'kontonr')
            $qtxt .= "and $soeg  = '$find' ";
        else
            $qtxt .= "and ($soeg like '%" . db_escape_string($find) . "%' or upper($soeg) like '%" . strtoupper(db_escape_string($find)) . "%') ";
    }

    $fokus_id = 'id=fokus';
    $x = 0;


				#Old table rendering
			//     $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			//     while ($row = db_fetch_array($q)) {
			//         $x++;
			//         $kontonr = str_replace(" ", "", $row['kontonr']);
			//         if ($linjebg != $bgcolor) {
			//             $linjebg = $bgcolor;
			//             $color = '#000000';
			//         } else {
			//             $linjebg = $bgcolor5;
			//             $color = '#000000';
			//         }
			//         print "<tr bgcolor=\"$linjebg\">";
			//        print "<td><a class='no-outline' href='$href?fokus=$fokus&id=$id&konto_id=$row[id]' $fokus_id>$row[kontonr]</a></td>";
			//         $fokus_id = '';
			//         print "<td>" . stripslashes($row['firmanavn']) .
			//  "</td>";
			// 		print "<td>" . stripslashes($row['addr1']) . "</td>";
			// 		print "<td>" . stripslashes($row['addr2']) . "</td>";
			// 		print "<td>" . stripslashes($row['postnr']) . "</td>";
			// 		print "<td>" . stripslashes($row['bynavn']) . "</td>";
			// 		print "<td>" . stripslashes($row['land']) . "</td>";
			// 		print "<td>" . stripslashes($row['kontakt']) . "</td>";
			// 		print "<td>" . stripslashes($row['tlf']) . "</td>";
			// 		print "</tr>\n";
			// 	}
			#############
			# if no results found
	print "<tr id='noResultsForm' style='display:none;'>
         <td colspan='9' style='text-align: right; vertical-align: top;'>";

	#	print "<tr><td colspan=9><hr></td></tr>";
		#		print "<tr><td>$kontonr</td><td>$firmanavn</td><td>$addr1</td><td>$addr2</td><td>$postnr</td><td>$bynavn</td><td>$land</td><td>$kontakt</td><td>$tlf</td></tr>";
#		print "<tr><td colspan=9>Ovenst&aring;ende kunde er ikke oprettet. <a href=\"../debitor/debitorkort.php?kontonr=$kontonr&firmanavn=$firmanavn&addr1=$addr1&addr2=$addr2&postnr=$postnr&bynavn=$bynavn&land=$land&kontakt=$kontakt&tlf=$tlf&returside=../debitor/$href&ordre_id=&fokus=kontonr\">Klik her for at oprette denne kunde</a></td></tr>";
#		print "<tr><td colspan=9><hr></td></tr>";

		if (!$kontonr)
			$kontonr = get_next_number('adresser', 'D');

		$x = 0;
		$qtxt = "select * from grupper where art='DG' and fiscal_year = '$regnaar' order by kodenr";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$grp_nr[$x] = $r['kodenr'];
			$grp_name[$x] = $r['beskrivelse'];
			$x++;
		}
		$pMax = $x = 0;
		$pterms = array();
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
		$ptName[3] = findtekst(369, $sprog_id); // Forud.



		if (!$defaultPterm)
			$defaultPterm = 'Netto';  #20220206
		if (!$defaultPdays)
			$defaultPdays = '8';

		print "<form name=\"create_debtor\" action=\"ordre.php?id=$id&sag_id=$sag_id&returside=$returside\" method=\"post\">\n";
		print "<tr><td colspan='9' align='center' valign='top'><table><tbody>";
		print "<tr><td colspan = '2' align = 'center'><big><b>$txt2118</b></big></td></tr>";
		print "<tr><td colspan = '2'><hr></td></tr>";
		print "<tr><td>$txt357</td><td><input style='width:150px;' type='text' name='kontonr' value=\"$kontonr\"></td></tr>";
		print "<tr><td style='width:100px;'>$txt646</td><td><input style='width:150px;' type='text' name='firmanavn' value=\"$firmanavn\"></td></tr>";
		print "<tr><td style='width:100px;'>$txt648</td><td><input style='width:150px;' type='text' name='addr1' value=\"$addr1\"></td></tr>";
		print "<tr><td style='width:100px;'>$txt648</td><td><input style='width:150px;' type='text' name='addr2' value=\"$addr2\"></td></tr>";
		print "<tr><td style='width:100px;'>$txt650</td><td><input style='width:150px;' type='text' name='postnr' value=\"$postnr\"></td></tr>";
		print "<tr><td style='width:100px;'>$txt651</td><td><input style='width:150px;' type='text' name='bynavn' value=\"$bynavn\"></td></tr>";
		print "<tr><td style='width:100px;'>$txt377</td><td><input style='width:150px;' type='text' name='tlf' value=\"\"></td></tr>";
		print "<tr><td style='width:100px;'>$txt398</td><td><input style='width:150px;' type='text' name='kontakt' value=\"$kontakt\"></td></tr>";
		print "<tr><td style='width:100px;'>$txt402</td><td><input style='width:150px;' type='text' name='email' value=\"$email\"></td></tr>";
		print "<tr><td style='width:100px;'>$txt48</td><td><input style='width:150px;' type='text' name='$txt48' value=\"$cvrnr\"></td></tr>";
		print "<tr><td style='width:100px;'>Ean</td><td><input style='width:150px;' type='text' name='ean' value=\"$ean\"></td></tr>";
		print "<tr><td style='width:100px;'>$txt56</td><td><select style='width:125px;' name='betalingsbet'>";
		#		print "<option value='$defaultPterm'>$defaultPterm</option>";
		for ($x = 0; $x < count($pay_terms); $x++) {
			if ($defaultPterm == $pay_terms[$x])
				print "<option value='$pay_terms[$x]'>$ptName[$x]</option>";
		}
		for ($x = 0; $x < count($pay_terms); $x++) {
			if ($defaultPterm != $pay_terms[$x])
				print "<option value='$pay_terms[$x]'>$ptName[$x]</option>";
		}
		print "</select><input style='width:25px;text-align:right;' type='text' name='betalingsdage' value=\"$defaultPdays\">";
		print "<tr><td>	$txt63</td>";
		print "<td><select style='width:150px;' name='grp'>";
		############
		if (!empty($grp_nr) && is_array($grp_nr) && is_array($grp_name)) {
			for ($x = 0; $x < count($grp_nr); $x++) {
				$nr = htmlspecialchars($grp_nr[$x]);
				$name = htmlspecialchars($grp_name[$x] ?? '');
				echo "<option value='$nr'>$nr : $name</option>";
			}
		}
		##########
		print "</select></td></tr>";
		print "<tr><td colspan='2' align='center'><input style='width:250px;' type='submit' name=create_debtor value=\"$txt1232\"></td></tr>";
		print "</tbody></table></td></tr>";
	#end of no results form

	print "</tbody></table></td></tr>";

############
print '<tr><td colspan="9"><div id="pagination" style="margin-top: 10px; text-align: center;"></div></td></tr>';
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
    . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
 // JS Script added at the end of HTML for client-side filtering
echo <<<JS
<script>
let currentPage = 1;
const rowsPerPage = 100;
let totalPages = 1;
let currentSort = 'firmanavn';
const o_art = '{$o_art}';
const currentUrl = "{$currentUrl}";
console.log("Current URL:", currentUrl);
fokus = "{$fokus}";
usedId = "{$id}";


function fetchData() {
    const filters = {
        kontonr: document.getElementById('filter_kontonr').value,
        firmanavn: document.getElementById('filter_firmanavn').value,
        addr1: document.getElementById('filter_addr1').value,
        addr2: document.getElementById('filter_addr2').value,
        postnr: document.getElementById('filter_postnr').value,
        bynavn: document.getElementById('filter_bynavn').value,
        land: document.getElementById('filter_land').value,
        kontakt: document.getElementById('filter_kontakt').value,
        tlf: document.getElementById('filter_tlf').value,
        o_art: '<?php echo $o_art; ?>'
    };

    const debitorIndex = currentUrl.indexOf('debitor');

    let apiPath = '';
    if (debitorIndex !== -1) {
        const basePath = currentUrl.substring(0, debitorIndex + 'debitor'.length);
        apiPath = basePath + '/accountLookupData.php';
    } else {
        console.error("Could not locate 'debitor' in the URL. Using fallback.");
        apiPath = window.location.origin + '/accountLookupData.php';
    }

    let url = new URL(apiPath);

    url.searchParams.set('page', currentPage);
    url.searchParams.set('rowsPerPage', rowsPerPage);
    url.searchParams.set('sort', currentSort);
    for (const key in filters) {
        if (filters[key]) url.searchParams.set(key, filters[key]);
    }

    fetch(url)
        .then(response => response.json())
        .then(data => {
			console.log("Fetched data:", data);
            
            renderTable(data.data);
            totalPages = Math.ceil(data.totalRows / rowsPerPage);
            renderPagination();
        });
}


function renderTable(entries) {
    const tbody = document.getElementById('tableBody');
    const noResultsForm = document.getElementById('noResultsForm');

    tbody.innerHTML = '';
    if (entries.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;">No results found</td></tr>';
        if (noResultsForm) noResultsForm.style.display = 'block';
        return;
    }

    if (noResultsForm) noResultsForm.style.display = 'none';

    entries.forEach(entry => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><a href="ordre.php?fokus=\${fokus}&id=\${usedId}&konto_id=\${entry.id}">\${entry.kontonr}</a></td>
            <td>\${entry.firmanavn}</td>
            <td>\${entry.addr1}</td>
            <td>\${entry.addr2}</td> 
            <td>\${entry.postnr}</td>
            <td>\${entry.bynavn}</td>
            <td>\${entry.land}</td>
            <td>\${entry.kontakt}</td>
            <td>\${entry.tlf}</td>
        `;
        tbody.appendChild(tr);
    });
}



function renderPagination() {
    const container = document.getElementById('pagination');
    container.innerHTML = '';

    if (totalPages <= 1) return;

    const prevBtn = document.createElement('button');
    prevBtn.textContent = '« Prev';
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => { currentPage--; fetchData(); };
    container.appendChild(prevBtn);

    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement('button');
        btn.textContent = i;
        btn.disabled = i === currentPage;
        btn.onclick = () => { currentPage = i; fetchData(); };
        container.appendChild(btn);
    }

    const nextBtn = document.createElement('button');
    nextBtn.textContent = 'Next »';
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.onclick = () => { currentPage++; fetchData(); };
    container.appendChild(nextBtn);
}

// Event listeners for filters to fetch data on input
document.querySelectorAll('input[id^="filter_"]').forEach(input => {
    input.addEventListener('input', () => {
        currentPage = 1;
        fetchData();
    });
});

// Initial fetch on page load
window.addEventListener('load', fetchData);
</script>
JS;


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