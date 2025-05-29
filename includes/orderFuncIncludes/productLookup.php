<?php
function vareopslag($art,$sort,$fokus,$id,$vis_kost,$ref,$find, $location=null, $option=null) {
	global $afd,$afd_lager;
	global $bgcolor,$bgcolor5,$bordnr,$bruger_id,$brugernavn;
	global $db;
	global $incl_moms;
	global $menu,$momssats;
	global $regnaar;
	global $sprog_id;
	global $href;
	global $konto_id;
	
	$kundeordre = findtekst(1092,$sprog_id);  #20240416
	if ($menu=='T') {
		include_once '../includes/top_menu.php';
	} else {

	}
	
	$priceListUrl="temp/$db/pricelisturl.txt"; #20241226
	if (!file_exists($priceListUrl)) {
			echo "Error: Could not locate the file.".$priceListUrl;	
	}else{
		file_put_contents($priceListUrl, $location);
		$location = file_get_contents($priceListUrl);
	}
	
	$option = explode(":", $option, 2);
	list($option, $externalPricelist) = $option;
	if($option == ''){$option = NULL;}
	// HTML Form





	if (!$option && $externalPricelist) { 

      $q = db_select("select box2 from grupper where art = 'PL' order by beskrivelse", __FILE__ . " linje " . __LINE__);

      $prisfil1 = [];
      while ($r = db_fetch_array($q)) {
        $prisfil1[] = $r['box2']; 
      }
			if(count($prisfil1)>1){
			#############################Change to new pricelist
			echo '<div style="text-align: center;">';
       echo '<h3>Change Active Pricelist File</h3>';
				echo '<form id="priceListForm" action="ordre.php" method="post">';
				echo '<select name="change_active_pricelist_file">';
				$q = db_select("SELECT * FROM grupper WHERE art = 'PL' ORDER BY beskrivelse", __FILE__ . " linje " . __LINE__);

				while ($r = db_fetch_array($q)) {
					$grupper_id = htmlspecialchars($r['id']);
					$beskrivelse = htmlspecialchars($r['beskrivelse']);
					$selected = ($r['box12'] === 'Yes') ? ' selected' : '';
					echo "<option value=\"$grupper_id\"$selected>$beskrivelse</option>";
				}

				echo '</select>';
				echo '<input type="hidden" name="post_identifier" value="unique_post_' . time() . '">';
				echo '</form>';
			echo '</div>';


			print '<script>
				document.addEventListener("DOMContentLoaded", function () {
				const form = document.getElementById("priceListForm");
				const select = form.querySelector("select[name=\'change_active_pricelist_file\']");

				// Auto-submit form on select change
				select.addEventListener("change", function () {
					form.dispatchEvent(new Event("submit", { cancelable: true }));
				});

				// Handle form submission via fetch
				form.addEventListener("submit", function (e) {
					e.preventDefault();

					const formData = new FormData(form);

					fetch(form.action, {
						method: "POST",
						body: formData
					})
					.then(response => response.text())
					.then(data => {
						console.log("Server response:", data);
						updateTable(data);
					})
					.catch(error => {
						console.error("Fetch error:", error);
					});
				});
			});

			function updateTable(data) {
				const table = document.getElementById("priceListTable");
				table.innerHTML = data;
			}
			</script>';
				############################
		}
			// Show the form only if there's no option in the URL
			print '
			<!-- HTML Form -->
			<form id="optionForm" method="POST" action="' . $location . '">
				<input type="hidden" name="action" id="actionInput" value="">
				<input type="hidden" name="option" id="optionInput" value="">
			</form>
		
				<button onclick="chooseOption(1)" style="display: inline-block; margin-right: 10px; padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer;">
					External Pricelist
				</button>
				

			<div id="result"></div>
			<div id="hiddenContent" style="display:none;"></div>
			<div id="hiddenContent1" style="display:none;"></div>
		
			<script>
			function chooseOption(option) {
				var resultDiv = document.getElementById(\'result\');
				var hiddenContentDiv = document.getElementById(\'hiddenContent\');
				var hiddenContentDiv1 = document.getElementById(\'hiddenContent1\');
		
				// Show the corresponding content based on the selected option
				if (option == 2) {
					hiddenContentDiv.style.display = \'block\'; // Show internal pricelist content
					hiddenContentDiv1.style.display = \'none\'; // Hide external pricelist content
				} else if (option == 1) {
					resultDiv.innerHTML = \'You selected External Pricelist\';
					hiddenContentDiv.style.display = \'none\'; // Hide internal pricelist content
					hiddenContentDiv1.style.display = \'block\'; // Show external pricelist content
				}
		
				// Set the hidden input field with the selected option
				document.getElementById("optionInput").value = option;
		
				// Submit the form via POST
				document.getElementById("optionForm").submit();
			}
			</script>
			';
		
	}
		
	
	if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['option'])) {
		
		$option = $_POST['option']; 
		if ($option) {
			#$option = "&option=$option";
		    $url = $location.$option;
			// Append the URL to the file
			$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
			 // Get the current host (domain name or IP address)
			 $host = $_SERVER['HTTP_HOST'];
			 $requestUri = $_SERVER['REQUEST_URI'];
			 $currentUrl = $protocol . '://' . $host . $requestUri;
			 $startPos = strpos($currentUrl, 'debitor');
			if ($startPos !== false) {
				// Get the substring starting from 'debitor' to the end
				$result = substr($currentUrl, $startPos);
				file_put_contents($priceListUrl, $result.$option . "\n", FILE_APPEND);
				error_log("result and option:".$result.$option);
			}
		   
		}

	}

   ###################### 

		file_put_contents("../temp/$db/vareopslag.log","vareopslag($art,$sort,$fokus,$id,$vis_kost,$ref,$find)\n",FILE_APPEND);
		// Display the corresponding content based on the selected option
		$ordre_id=if_isset($_GET,NULL,'id');
		
	if(isset($option) && $option==2 || !isset($option)){
			if ($option == 2 || !isset($option)) {
				print "<div id='hiddenContent' style='display: block;'>"; // Show internal content
			} else {
				print "<div id='hiddenContent' style='display: none;'>";	
			}
			
			################internal pricelist	
			  file_put_contents("../temp/$db/vareopslag.log", "vareopslag($art,$sort,$fokus,$id,$vis_kost,$ref,$find)\n", FILE_APPEND);

        $cols = '5';
        $findStr = trim($find, '*');
        $lg_nr = array();
        $rowheight = "height=\"50\"";
        $qString = '';

        if ($art == 'PO') {
          $incl_moms = 'on';
        } else
          print "<form action='ordre.php?id=$id' method='post'>";
        $qtxt = NULL;
        if ($sort && $bruger_id) {
          $qtxt = "update settings set var_value='$sort' where var_name='itemLookup' and var_grp='deb_order' and user_id='$bruger_id'";
        } elseif ($bruger_id) {
          $qtxt = "select var_value from settings where var_name='itemLookup' and var_grp='deb_order' and user_id='$bruger_id'";
          if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            $sort = $r['var_value'];
            $qtxt = NULL;
          } else {
            $sort = 'beskrivelse';
            $qtxt = "insert into settings (var_name,var_grp,var_value,var_description,user_id)";
            $qtxt .= " values ";
            $qtxt .= " ('itemLookup','deb_order','$sort','Sorting when doing lookup from debitor order','$bruger_id')";
          }
        }
        if ($qtxt)
          db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        $lagernr = array();
        $lagernavn = array();

        $lager = NULL;
        $linjebg = NULL;

        $momsfri = array();
        $x = 0;
        $q = db_select("select kodenr from grupper where art='VG' and box7 = 'on' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__);
        while ($r = db_fetch_array($q)) {
          $momsfri[$x] = $r['kodenr'];
          $x++;
        }
        if (!$ref)
          $ref = $brugernavn;
        if (!$afd && $ref) {
          $qtxt = "select ansatte.afd from ansatte where navn='$ref'";
          ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) ? $afd = $r['afd'] : $afd = 0;
          if (!$afd) {
            $qtxt = "select ansat_id from brugere where brugernavn='$ref'";
            ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) ? $ansat_id = $r['ansat_id'] : $ansat_id = 0;
            $qtxt = "select afd from ansatte where id='$ansat_id'";
            ($ansat_id && $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) ? $afd = $r['afd'] : $afd = 0;
          }
          db_modify("update ordrer set afd='$afd' where id='$id'", __FILE__ . " linje " . __LINE__);
        }
        $x = 0;
        $q = db_select("select beskrivelse,kodenr,box1 from grupper where art = 'LG' order by kodenr", __FILE__ . " linje " . __LINE__);
        while ($r = db_fetch_array($q)) {
          $lg_navn[$x] = $r['beskrivelse'];
          $lg_nr[$x] = $r['kodenr'];
          $x++;
        }
        if ($afd) { #20161022
          $qtxt = "select box1 from grupper where kodenr='$afd' and art = 'AFD'";
          $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
          $lager = (int) $r['box1'];
          if (!$lager) {
            $qtxt = "select kodenr from grupper where box1='$afd' and art = 'LG'";
            $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
            $lager = (int) $r['kodenr'];
          }
        }
        $lager *= 1;

        if ($id && (!$art || !$ref)) {
          $r = db_fetch_array(db_select("select art,ref from ordrer where id='$id'", __FILE__ . " linje " . __LINE__));
          if (!$art)
            $art = $r['art'];
          if (!$ref)
            $ref = $r['ref'];
        }
        if (!$ref)
          $ref = $brugernavn;
        if ($find = trim($find)) {
          $find = db_escape_string(strtolower($find));
          if (strpos($find, '+')) { #20161110
            $find = str_replace("*", "", $find);
            $ord = array();
            $ord = explode("+", $find);
            $find = NULL;
            for ($f = 0; $f < count($ord); $f++) {
              if ($ord[$f]) {
                if ($find)
                  $find .= "and lower($fokus) like '%$ord[$f]%'";
                else
                  $find = "and (lower($fokus) like '%$ord[$f]%'";
              }
            }
            if ($find)
              $find .= ")";
          } elseif ($find)
            $qString = "and lower($fokus) like '" . str_replace("*", "%", $find) . "'";
          if ($fokus == 'beskrivelse' && $find)
            $qString .= " or lower(trademark) like '" . str_replace("*", "%", $find) . "'";
          #		$focus="lower($focus)";
        }
        if ($art == 'PO' && !strpos($_SERVER['PHP_SELF'], 'pos_ordre'))
          $art = 'DO';
        if ($art == 'DO' || $art == 'DK') {
          sidehoved($id, "../debitor/ordre.php", "../lager/varekort.php", $fokus, "$kundeordre $id - Vareopslag");
          $href = "ordre.php";

        } elseif ($art == 'PO') {
          #		print "<tr><td colspan=\"5\"><hr>";
          #		sidehoved($id, "../debitor/pos_ordre.php", "", $fokus, "POS ordre $id - Vareopslag");
          #		print "<hr></td></tr>";
          $href = "pos_ordre.php";
        }
        print "<script type=\"text/javascript\" src=\"https://code.jquery.com/jquery-latest.min.js\"></script>\n";
        print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/arrowkey.js\"></script>\n";
        print "<script type=\"text/javascript\">
        $(document).ready(function () {
        $('input[type=\"text\"],textarea,a[href]').keyup(function (e) {
        if (e.which === 27) {
          window.location.href = '$href?id=$id';
      }
      });
      });
      </script>";
      /*
      p ri*nt "<script type=\"text/javascript\">
      var TableBackgroundNormalColor = \"$bgcolor\";
      var TableBackgroundMouseoverColor = \"$bgcolor5\";
      // These two functions need no customization.
      function ChangeBackgroundColor(row) { row.style.backgroundColor = TableBackgroundMouseoverColor; }
      function RestoreBackgroundColor(row) { row.style.backgroundColor = TableBackgroundNormalColor; }
      </script>";
      */
      print "<table class='dataTable' cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\"><tbody>";
      $linjebg = $bgcolor;
      $color = '#000000';
      #	$linjebg=$bgcolor5; $color='#000000';
      print "<tr $linjebg>";

      if ($art != 'PO') {
        $listeantal = 0;
        $q = db_select("select id,beskrivelse from grupper where art='PL' and box4='on' order by beskrivelse", __FILE__ . " linje " . __LINE__);
        while ($r = db_fetch_array($q)) {
          $listeantal++;
          $prisliste[$listeantal] = $r['id'];
          $listenavn[$listeantal] = $r['beskrivelse'];
        }
        if ($listeantal) {
          print "<form name=\"prisliste\" action=\"../includes/prislister.php?start=0&ordre_id=$id&fokus=$fokus\" method=\"post\">";
          print "<td><select name=prisliste>";
          for ($x = 1; $x <= $listeantal; $x++)
            print "<option value=\"$prisliste[$x]\">$listenavn[$x]</option>";
          print "</select></td><td><input type=\"submit\" name=\"prislist\" value=\"Vis\"></td>";
        }


        if ($vis_kost) {
          $cols = 9;
          print "<td colspan='$cols' align=center>";
          print "<a class='button blue medium' href=$href?sort=varenr&funktion=vareOpslag&fokus=$fokus&id=$id>Udelad kostpriser</a></td></tr>";
        } else {
          $cols = 6;
          print "<td colspan='$cols' align=center>";
          print "<a class='button blue medium' href=$href?sort=varenr&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=on>Vis kostpriser</a></td></tr>";
        }
        $rowheight = NULL;
      }
      ?>
      <script>
      function filterRows() {
        const input = document.getElementById('filterInput').value.toLowerCase();
        const rows = [...document.querySelectorAll('.dataTable tr')];
        rows.shift();
        rows.shift();
        rows.shift();

        rows.forEach(row => {
          const rowText = row.innerText.toLowerCase();
          if (rowText.includes(input)) {
            row.classList.remove('hidden');
          } else {
            row.classList.add('hidden');
          }
        });
      }
      </script>
      <?php
      print "<tr><td colspan=10><input type='text' id='filterInput' size='100' placeholder='Søg efter vare nr eller vare beskrivelse' oninput='filterRows()'></input></td></tr>";
      ($sort == 'varenr') ? $txt = '<i>Varenr</i>' : $txt = 'Varenr';
      print "<td><a href=$href?sort=varenr&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=$vis_kost&bordnr=$bordnr><b>$txt</b></a></td>";
      print "<td><b> Enhed</b></td>";
      ($sort == 'beskrivelse') ? $txt = '<i>Beskrivelse</i>' : $txt = 'Beskrivelse';
      print "<td><a href=$href?sort=beskrivelse&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=$vis_kost&bordnr=$bordnr><b>$txt</b></a></td>";
      ($sort == 'salgspris') ? $txt = '<i>Salgspris</i>' : $txt = 'Salgspris';
      print "<td align=right><a href=$href?sort=salgspris&funktion=vareOpslag&fokus=$fokus&id=$id&bordnr=$bordnr><b>$txt</b></a></td>";
      if (count($lg_nr) > 1) {
        for ($x = 0; $x < count($lg_nr); $x++) {
          $cols++;
          print "<td align=right><b>$lg_navn[$x]</b></td>";
        }
      } else {
        print "<td align=right><b><a href=$href?sort=beholdning&funktion=vareOpslag&fokus=$fokus&id=$id&bordnr=$bordnr>Beholdning</a></b></td>";
      }
      if ($vis_kost) {
        print "<td align=right><b> Kostpris</b></td>";
      }
      #	if ($art!='PO') print"<td align=right><b><a href=$href?sort=beholdning&funktion=vareOpslag&fokus=$fokus&id=$id&vis_kost=$vis_kost>Beh.</a></b></td>";
      if ($art == 'PO') {
        print "<td><form name='vareopslag' action='pos_ordre.php?id=$id&fokus=varenr_ny' method='post'></td>";
        print " </tr>\n";
        print "<td colspan='2'><input type='hidden' name='fokus' value='varenr_ny'></td>";
        print "<td><input type='text' style='width:100%' name='varenr_ny' value='$findStr' id='opslag_0'></td>";
        print "<td><input type=submit name=\"OK\" value=\"Søg\"></form></td>";
        print " </tr>\n";
      }
      if (count($lg_nr) > 1) {
        for ($x = 0; $x < count($lg_nr); $x++) {
          $l = 0;
          $qtxt = "select vare_id,variant_id,beholdning from lagerstatus where lager = '$lg_nr[$x]' order by vare_id,variant_id";
          $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
          while ($r = db_fetch_array($q)) {
            $ls_lager[$x][$l] = $lg_nr[$x];
            $ls_id[$x][$l] = $r['vare_id'];
            $ls_var_id[$x][$l] = $r['variant_id'];
            $ls_behold[$x][$l] = $r['beholdning'];
            $l++;
          }
        }
      }

      if ($ref) {
        $qtxt = "select afd from ansatte where navn = '$ref' or initialer = '$ref'";
        ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) ? $afd = $r['afd'] : $afd = 0;
        $x = 0;
      }
      if (!$sort)
        $sort = 'id';
        if ($qString)
          $qtxt = "select * from varer where lukket != '1' $qString order by $sort";
        elseif ($art == 'PO')
          $qtxt = "select * from varer where lukket != '1' order by $sort limit 100";
        else
          $qtxt = "select * from varer where lukket != '1' order by $sort";
        if ($art == 'PO') {
          if ($linjebg != $bgcolor) {
            $linjebg = $bgcolor;
            $color = '#000000';
          } else {
            $linjebg = $bgcolor5;
            $color = '#000000';
          }
          #		$colspan=5+count($lg_nr);
          print "<tr bgcolor=\"$linjebg\"  onclick=\"window.document.location='$href?id=$id&bordnr=$bordnr';\">";
          print "<td colspan=\"$cols\" $rowheight align=\"center\"><big><big>Tilbage</big></big></td></tr>\n";
        }
        $z = $x = 0;
        if (strpos($qtxt,'from varer') && strpos($qtxt,'by ordrenr')) {
          $qtxt = str_replace('by ordrenr','by varenr',$qtxt);
        }
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        while ($row = db_fetch_array($q)) {
          $vare_id = $row['id'] * 1;
          $beholdning = $row['beholdning'] * 1;
          /*
          i f* ($lager) {
          for ($l=0;$l<count($ls_id);$l++) {
            if ($ls_id[$l]==$vare_id) {
              $beholdning=$ls_behold[$l];

              break 1;
        }
        }
        }
        */
          $x++;
          $onclick = "onclick=\"window.document.location='$href?id=$id&vare_id=$row[id]&lager=$afd_lager&bordnr=$bordnr';\"";
          if ($linjebg != $bgcolor) {
            $linjebg = $bgcolor;
            $color = '#000000';
          } else {
            $linjebg = $bgcolor5;
            $color = '#000000';
          }
          print "<tr  bgcolor=\"$linjebg\" >";
          #		($art=='PO')?$hreftxt="$href?vare_id=$row[id]&fokus=$fokus&id=$id&bordnr=$bordnr":$hreftxt="";
          $hreftxt = "$href?vare_id=$row[id]&fokus=$fokus&id=$id&bordnr=$bordnr&lager=$afd_lager";#"$href?vare_id=$row[id]&fokus=$fokus&id=$id&bordnr=$bordnr";
          print "<td $rowheight $onclick><a class='no-outline' onfocus=\"this.style.fontSize = '20px';\" onblur=\"this.style.fontSize = '12px';\" id=\"opslag_$x\" href=\"$hreftxt\">$row[varenr]</a></td>";
          print "<td $onclick>$row[enhed]<br></td>";
          print "<td $onclick>$row[beskrivelse]<br></td>";
          if ($incl_moms && !in_array($row['gruppe'], $momsfri)) {
            $salgspris = $row['salgspris'] + $row['salgspris'] * $momssats / 100;
          } else
            $salgspris = $row['salgspris'];
          print "<td  $onclick align=right>" . dkdecimal($salgspris, 2) . "<br></td>";
          if ($vis_kost == 'on') {
            $query2 = db_select("select kostpris from vare_lev where vare_id = '$vare_id' order by posnr", __FILE__ . " linje " . __LINE__);
            $row2 = db_fetch_array($query2);
            $kostpris = dkdecimal($row2['kostpris'], 2);
            print "<td  $onclick align='right'>$kostpris<br></td>";
          }
          $reserveret = 0;
          if (!isset($ls_id))
            $ls_id = null;
          if (count($lg_nr) > 1) {
            for ($x = 0; $x < count($lg_nr); $x++) {
              if (!isset($ls_id[$x]))
                $ls_id[$x] = array();

              print "<td align=right>";
              for ($l = 0; $l < count($ls_id[$x]); $l++) {
                if ($ls_id[$x][$l] == $row['id']) {
                  print "<a href=$hreftxt&lager=$lg_nr[$x]><big>" . dkdecimal($ls_behold[$x][$l], 2) . "</big></a>";
                } elseif ($row['samlevare'] && $l == 0) { #20176127
                  print "<a href=$hreftxt&lager=$lg_nr[$x]><button type='button' style='width:40px;height:20px;'>$lg_navn[$x]</button></a>";
                }
              }
              print "</td>";
            }
          } else {
            $q2 = db_select("select * from batch_kob where vare_id='$vare_id' and rest > 0", __FILE__ . " linje " . __LINE__);
            while ($r2 = db_fetch_array($q2)) {
              $q3 = db_select("select * from reservation where batch_kob_id=$r2[id]", __FILE__ . " linje " . __LINE__);
              while ($r3 = db_fetch_array($q3))
                $reserveret = $reserveret + $r3['antal'];
            }
            $linjetext = "<span title= 'Reserveret: $reserveret'>";
            print "<td align=right>$linjetext " . dkdecimal($beholdning, 2) . "</span></td>";
          }
          if ($art != 'PO') {
            print "<td width='20px' align='center' title='Skriv antal her, hvis der skal indsættes flere varer ad gangen'><input type='hidden' name='insetId[$z]' value='$vare_id'>";
            print "<input type='text' style='width:30px;text-align:right;' name='insetQty[$z]'></td>";
          }
          print "</tr>\n";
          $z++;
        }
        if ($art != 'PO')
          print "<tr><td colspan='$cols'><input style='width:100%;height:5px' type='submit' name='insetItems' value=''></td></tr>";
        print "</tbody></table>\n";
        #	if ($findStr) print "<script language=\"javascript\">	document.vareopslag.varenr_ny.focus();</script>";
        #	else
        print "<body onload=\"document.links['opslag_1'].focus();\" >\n";
                      // End hiddenContent div internal pricelist
	}elseif (isset($option) && $option == 1) { //External pricelist
			// Retrieve the option value
			if ($option == 1) {
				print "<div id='hiddenContent1' style='display: block;'>"; // show content in div if 'limit' is  set
			} else {
				print "<div id='hiddenContent1' style='display: none;'>"; 
			}
			#############################################
			$id = $filtyper = $filtypebeskrivelse = $lev_id=$aktiv = $prislister = array();
			$i=0;
			$q=db_select("select * from grupper where art = 'PL' order by beskrivelse",__FILE__ . " linje " . __LINE__);
			
			while ($r = db_fetch_array($q)) {
				$id[$i]=$r['id'];
				$beskrivelse[$i]=$r['beskrivelse']; //holds names of the records
				$lev_id[$i]=$r['box1'];
				$prisfil[$i]=$r['box2']; //file location ?
				$opdateret[$i]=$r['box3'];
				$inserted[$i]=$r['box4']; //on
        $aktiv[$i]=$r['box12']; //Yes
				$rabat[$i]=$r['box6'];
				$gruppe[$i]=$r['box8'];
				$filtype[$i]=$r['box9'];
				$delimiter[$i] = $r['box10'];
				$encoding[$i]=$r['box11'];
				$i++;
			}
		
			$vgrp = array();
			$i = 0;
			$qtxt = "select * from grupper where art = 'VG' and fiscal_year = '$regnaar' order by kodenr";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$vgrp[$i]   = $r['kodenr'];
				$vgbesk[$i] = $r['beskrivelse'];
				$i++;
			}
		
			$filtyper[0]="csv";
			$filtypebeskrivelse[0]="Kommasepareret";
			$filtyper[1]="tab";
			$filtypebeskrivelse[1]="Tabulator";
			$filtyper[2]="sql";
			$filtypebeskrivelse[2]="Databasefil (SQL-dump)";
			$filtyper[3]="html";
			$filtypebeskrivelse[3]="HTML-celler (td)";
			
			$i = 0;
			$qtxt = "select id, kontonr, firmanavn from adresser where art = 'K' order by firmanavn";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				if (!isset($r['lukket'])) {
					$supplId[$i]      = $r['id'];
					$supplAccout[$i]  = $r['kontonr'];
					$supplCompany[$i] = $r['firmanavn'];
					$i++;
				}
			}
			
				print '<table style="width: 100%; border-collapse: collapse;">';
				// Default delimiter (comma)
			   
					$csvData = '';
					$idCount = count($id);

					for ($x = 0; $x < $idCount; $x++) {
						if (trim($aktiv[$x]) === 'Yes' && !empty($prisfil[$x])) {
         				# $checkDelimiter = bin2hex($delimiter[$x])
							$delimiter = if_isset($delimiter[$x], ';');

							$url = (str_starts_with($prisfil[$x], 'http://') || str_starts_with($prisfil[$x], 'https://'))
								? $prisfil[$x]
								: 'https://' . $prisfil[$x];

							$csv_url = filter_var($url, FILTER_SANITIZE_URL);

							if (!filter_var($csv_url, FILTER_VALIDATE_URL)) {
								echo "<script>alert('Invalid URL format: $url');</script>";
								continue;
							}

							$csvData = @file_get_contents($csv_url);

							if ($csvData === false) {
								echo "<script>alert('Unable to fetch the CSV file: $csv_url');</script>";
								$csvData = '';
								continue;
							}

							// ✅ CSV found — stop loop
							break;
						}
					}

					if (empty($csvData)) {
						error_log('No valid active CSV file found matching the criteria.');
						echo "<script>alert('No valid active CSV file found matching the criteria.');</script>";
						return;
					}
					$lines = explode(PHP_EOL, trim($csvData));
					$rows = [];
					foreach ($lines as $line) {
						$rows[] = str_getcsv($line, $delimiter);
					}
					(isset($rows[0])) ? $header = $rows[0] : $header = [];
					unset($rows[0]);

					
					$rowCount = count($rows);
					$varenrList = [];

					for ($i = 1; $i <= $rowCount; $i++) {
						$covtR = mb_convert_encoding((isset($rows[$i][0])) ? $rows[$i][0] : '', 'UTF-8', 'ISO-8859-1');

						if ($covtR == '/' || $covtR == "") continue;

						$varenrList[] = $covtR;
					}

					$Nrows = [];

					if (count($varenrList) > 0) {
						
            $filteredVarenrList = array_filter($varenrList, function($value) {
                return is_string($value) && $value !== ''; 
            });
            $varenrListStr = implode("','", array_map('addslashes', $filteredVarenrList));
            $qr = db_select("SELECT varenr FROM varer WHERE varenr IN ('$varenrListStr')", __FILE__ . " linje " . __LINE__);


						$existingVarnr = [];
						if(!$qr) {
							echo "<script>alert('Error fetching existing varenr from database.');</script>";
							error_log('Error fetching existing varenr from database.');
							exit;
							#return;
						}
						while ($row = db_fetch_array($qr)) {
							$existingVarnr[] = $row['varenr'];
						}

						for ($i = 1; $i <= $rowCount; $i++) {
							$covtR = mb_convert_encoding($rows[$i][0] ?? '', 'UTF-8', 'ISO-8859-1');

							if ($covtR == '/' || $covtR == "" || in_array($covtR, $existingVarnr)) continue;

							$Nrows[$i] = $rows[$i];
						}
					}

					if (count($Nrows) > 0) {	
						print '<table border="1" style="width: 100%; name: items_A; border-collapse: collapse; margin-bottom: 20px; margin-top: 5px;">';

						// Table Header
						
						######################
						print '<form method="post" action="_varerInsert.php">'; // Form to submit selected rows
						print '<table border="1" cellpadding="5" cellspacing="0">';

						// Header row with "Select All" checkbox
						print '<tr style="background-color: #F6F6F6;">';
						print '<th><input type="checkbox" id="selectAll" onclick="toggleAllCheckboxes(this)"></th>';
						foreach ($header as $columnName) {
							$encodedHeader = htmlspecialchars(mb_convert_encoding($columnName, 'UTF-8', 'ISO-8859-1'));
							print '<th>' . $encodedHeader . '</th>';
						}
						print '</tr>';
						$idd = $_GET['id'] ?? null;
						$cnrRows = count($Nrows);

						// Data rows
						for ($i = 1; $i <= $cnrRows; $i++) {
							if (empty($Nrows[$i])) continue;
							print '<tr style="background-color:rgb(241, 239, 202);">';
							// Prepare row data
							$rowData = [];
							foreach ($header as $index => $columnName) {
								$columnValue = $Nrows[$i][$index] ?? '';
								$columnValue = mb_convert_encoding($columnValue, 'UTF-8', 'ISO-8859-1');
								$rowData[mb_convert_encoding($columnName, 'UTF-8', 'ISO-8859-1')] = $columnValue;
							}
							$rowData['id'] = $idd;
							$rowData['bordnr'] = $bordnr;
							$rowData['db'] = $db;
							$rowData['fokus'] = 'varenr';

							$serialized = htmlspecialchars(json_encode($rowData), ENT_QUOTES, 'UTF-8');
							print "<td><input type='checkbox' class='rowCheckbox' name='selectedRows[]' value='{$serialized}'></td>";
							// Data columns
							foreach ($Nrows[$i] as $column) {
								$column = mb_convert_encoding($column, 'UTF-8', 'ISO-8859-1');
								print '<td>' . htmlspecialchars($column) . '</td>';
							}
							print '</tr>';
						}
						print '</table>'; // End of data table
						print '<br>';
						print '<input type="submit" value="Insert Data" style="padding: 10px; background-color:rgb(47, 156, 102); color: white; border: none; border-radius: 4px;">';
						print '</form>';
						// JavaScript block in print
						print '
						<script>
						function toggleAllCheckboxes(source) {
							var checkboxes = document.querySelectorAll(".rowCheckbox");
							checkboxes.forEach(function(cb) {
								cb.checked = source.checked;
							});
						}
						</script>
						';                     
	   				}
 
					######################				
		print '</table>'; //
	}
	
			print "</div>";
    return;
} //end vareopslag func