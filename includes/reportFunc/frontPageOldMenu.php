<?php //20251112
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/reportFunc/fromtPageOldMenu.php --- Patch 4.1.1 --- 20251124 ---
// LICENSE
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
//
// Copyright (c) 2003-2025 saldi.dk aps
// -----------------------------------------------------------------------------------
//
// 20251123 Fixed multiroute/paylist problem

// next line can be removed in 2026
db_modify("update grupper set box10 = 'B' where box10 = 'on' and art = 'DIV' and kodenr = '2'", __FILE__ . " linje " . __LINE__);


	$butStyle = "border:1;border-color:#fefefe;border-radius:7px;width:115px;height:35px;background:url('../img/stor_knap_bg.gif');";
#	$butStyle.= "color:white;background-color:$butCol";

		$dato = $dato_fra;
		if ($dato_til) $dato .= ":$dato_til";
		$konto = $konto_fra;
		if ($konto_til) $konto .= ":$konto_til";

		$tekst1 = findtekst('437|Skriv en dato i formatet ddmmåå fx. 311221, for at se bevægelser indtil denne dato, eller skriv er datointerval fx. 010121:311221', $sprog_id);
		$tekst2 = findtekst('438|Dato', $sprog_id);
		$tekst3 = findtekst('439|Skriv et kontonummer eller et interval adskilt af kolon. Listen vil blive sorteret efter kontonummer<br>...', $sprog_id);
		$tekst4 = findtekst('440|Konto', $sprog_id);
		$tekst5 = findtekst('451|Afmærk her hvis din søgning skal huskes', $sprog_id);
		$tekst6 = findtekst('452|Husk', $sprog_id);
		$overlib1 = "<span class='CellComment'>$tekst1</span>";
		$overlib2 = "<span class='CellComment'>$tekst3</span>";
		$overlib3 = "<span class='CellComment'>$tekst5</span>";
		print "<tr><td align='center' class='CellWithComment'><b>$tekst2</b> $overlib1</td>
		<td align='center' colspan=3 class='CellWithComment'><b>$tekst4</b> $overlib2</td>
		<td align='center' class='CellWithComment'><b>$tekst6</b> $overlib3</td></tr>";
		print "<form name='regnskabsaar' action='rapport.php' method='post'>";
		print "<tr><td align='center' class='CellWithComment'><input class='inputbox' style='width:129px' type='text' name='dato' value=\"$dato\"> $overlib1</td>";
		print "<td align='center' class='CellWithComment' colspan=3><input class='inputbox' style='width:129px' type='text' name='konto' value=\"$konto\"> $overlib2</td>";
		print "<td align='center' class='CellWithComment'><label class='checkContainerVisning'><input class='inputbox' type='checkbox' name='husk' $husk><span class='checkmarkVisning'></span></label> $overlib3</td></tr>";
		$tekst1 = findtekst('441|Åbne poster', $sprog_id);
		$tekst2 = findtekst('444|Viser en aldersfordelt liste over forfaldne/ubetalte udeståender på valgte konti pr. den angivne dato', $sprog_id);
		print "<tr><td align=center><input style=\"$butStyle\" type='submit' value='$tekst1' name='openpost' title='$tekst2'></td>";
		$tekst1 = findtekst('442|Kontosaldo', $sprog_id);
		$tekst2 = findtekst('445|Viser en liste over saldi på valgte konti pr. den angivne dato', $sprog_id);
		print "<td align=center colspan=3><input style=\"$butStyle\" type='submit' value='$tekst1' name='kontosaldo' title='$tekst2'></td>";
		$tekst1 = findtekst('443|Kontokort', $sprog_id);
		$tekst2 = findtekst('446|Viser en specifikation af kontobevægelser på valgte konti i det angivne datointerval.', $sprog_id);
		print "<td align=center><input style=\"$butStyle\" type='submit' value='$tekst1' name='kontokort' title='$tekst2'></td></tr>";
		#if ($kontoart == 'D')
			print "<tr><td colspan='6'><hr></td></tr>";
			if ($kontoart == 'D') {
			$tekst1 = findtekst('447|Liste over de 100 debitorer med den højeste omsætning de seneste 12 måneder.', $sprog_id);
			$tekst2 = findtekst('448|Top 100', $sprog_id);
			$tekst3 = findtekst('455|Kassespor', $sprog_id);
			$tekst4 = findtekst('918|Salgsstat', $sprog_id);
			$tekst5 = findtekst('2705|Oversigt over POS-transaktioner', $sprog_id);
			print "<tr>";
			if ($popup) {
				print "<td align=center><span onClick=\"javascript:top100=window.open('top100.php','top100','$jsvars');top100.focus();\" title='a $tekst1'><input style=\"$butStyle\" type=submit value='$tekst2' name='submit'></span></td>";
				if (db_fetch_array(db_select("select id from grupper where art = 'POS' and box2 >= '1'", __FILE__ . " linje " . __LINE__))) {
					print "<td colspan=3 align=center><span onClick='javascript:kassespor=window.open('kassespor.php','kassespor','$jsvars');kassespor.focus();' title='$tekst1'><input  style=\"$butStyle\" type=submit value='$tekst3' name='submit'></span></td>";
				}
			} else {
				print "<td align=center><span title='$tekst1' onClick=\"window.location.href='top100.php'\"><input style=\"$butStyle\" type=button value='$tekst2' name='submit'></span></td>";
				print "<td colspan = '3' align=center><input title='$tekst4' style=\"$butStyle\" type='submit' value='$tekst4' name='salgsstat'></td>";
				if (db_fetch_array(db_select("select id from grupper where art = 'POS' and box2 >= '1'", __FILE__ . " linje " . __LINE__))) {
					print "<td colspan=2 align=center><a href='kassespor.php'><input title='$tekst5' style=\"$butStyle\" type='button' value='$tekst3'></a></td>";
				}
			}
			$r=db_fetch_array(db_select("select box10 from grupper where art = 'DIV' and kodenr = '2'", __FILE__ . " linje " . __LINE__));
			if ($r['box10'] == 'B' || $r['box10'] == 'D') {
				$tekst1 = findtekst('531|Betalingslister til bank', $sprog_id);
				$tekst2 = findtekst('532|Betalingslister', $sprog_id);
				print "</tr><tr><td colspan = '5'><hr></td></tr><tr><td></td><td align=center>";
				print "<span onClick=\"javascript:location.href='../debitor/betalingsliste.php'\"><input title='$tekst1' style=\"$butStyle\" type='button' value='$tekst2'></span></td>\n";
			}
			$r=db_fetch_array(db_select("select var_value from settings where var_name = 'useMultiRoute'", __FILE__ . " linje " . __LINE__));
			if ($r['var_value'] == 'on') {
				print "</tr><tr><td colspan = '5'><hr></td></tr><tr><td></td><td align = 'center'>";
				print "<span onclick=\"javascript:location.href='../debitor/multiroute.php'\"><input title='Multiroute' style=\"$butStyle\" type='button' value=' " . findtekst('923|Multiroute', $sprog_id) . "'></span></td>";
				print "<td></td><td align='center'><span onClick=\"javascript:location.href='../debitor/postnr.php'\"><input title='Top 100 efter postnr' style=\"$butStyle\" type='button' value='Top 100 postnr'></span></td>\n";
			} else {
				print "</tr><tr><td colspan = '5'><hr></td></tr><tr><td></td><td align='center'>";
				print "<span onClick=\"javascript:location.href='../debitor/postnr.php'\"><input title='Top 100 efter postnr' style=\"$butStyle\" type='button' value='Top 100 postnr'></span></td>\n";
			}
			print "</tr>\n";
		} else {
			$tekst1 = findtekst('531|Betalingslister til bank', $sprog_id);
			$tekst2 = findtekst('532|Betalingslister', $sprog_id);
			##########
			$tekT = findtekst('448|Top 100', $sprog_id);
			$teksS = "List of the 100 creditors with the highest turnover in the last 12 months.";
			print "<tr>";
			if ($popup) {
				print "<td align=center><span onClick=\"javascript:top100=window.open('top100.php','top100','$jsvars');top100.focus();\" title='a $teksS'><input style=\"$butStyle\" type=submit value='$tekT' name='submit'></span></td>";
				
			} else {
				print "<td align=center><span title='$teksS' onClick=\"window.location.href='top100.php'\"><input style=\"$butStyle\" type=button value='$tekT' name='submit'></span></td>";
				
			}
			##########
			$r=db_fetch_array(db_select("select box10 from grupper where art = 'DIV' and kodenr = '2'", __FILE__ . " linje " . __LINE__));
			if ($r['box10'] == 'B' || $r['box10'] == 'K') {
				print "<td><span onClick=\"javascript:location.href='../kreditor/betalingsliste.php'\">\n";
				print "<input title='$tekst1' style=\"$butStyle\" type='button' value='$tekst2'>\n";
				print "</span></td>\n";
			}
#			print "<td align = 'center'><input title='Salgsstat' style=\"$butStyle\" type='submit' value='" . ucfirst(findtekst('918|Salgsstat', $sprog_id)) . "' name='salgsstat'></td>\n";
		}
		print "</tr>\n";
		print "</form>\n";
		$txt2134 = findtekst('2134|Vælg periode', $sprog_id);
		$txt903 = findtekst('903|fra', $sprog_id);
		$txt904 = findtekst('904|til', $sprog_id);
		if ($kontoart == 'D' && db_fetch_array(db_select("select id from grupper where art = 'POS' and box2 != '' and box2 is not null and kodenr = '1'", __FILE__ . " linje " . __LINE__))) {
			print "<tr><td colspan='6'><hr></td></tr>\n";
			print "<tr><td colspan='6'>&nbsp;</td></tr>\n";
			print "<tr><th colspan='6' style='text-align:center;'><p>".findtekst('2711|SAF-T kasserapport', $sprog_id);"</p></th></tr>\n";
			print "<tr><td colspan='6' style='text-align:center;'>&nbsp;</td></tr>\n";
			print "<tr><td colspan='6' style='text-align:center;'>$txt2134:</td></tr>\n";
			print "<form method='post' action='saftCashRegister.php'>";
			print "<tr><td colspan='6' style='text-align:center;'><div>
            <span>$txt903</span>
            <input type='text' id='fromDate' name='startDate' />
            <span>$txt904</span>
            <input type='text' id='toDate' name='endDate' />
            </div></td></tr>\n";
			print "<tr><td colspan='6' style='text-align:center;'>&nbsp;</td></tr>\n";
			print "<tr><td colspan='6' style='text-align:center;'><input style='width:115px;' type='submit' value='SAF-T' name='saft'></td></tr>\n";
			print "</form>\n";
		}
		print "</tbody></table>";
		print "</tbody></table>";
		?>
				<script>
				let dateTimeFrom = document.getElementById('fromDate');
				let dateTimeTo = document.getElementById('toDate');
				let dateTimeToPicker = null;
				let dateTimeFromPicker = flatpickr(dateTimeFrom, {
					altInput: true,
					altFormat: "j. F Y",
					dateFormat: "Y-m-d",
					defaultDate: "today",
					onChange: function(selectedDates, dateStr, instance) {
						dateTimeToPicker.set('minDate', selectedDates[0]);
					},
					"locale": "da"
				});

				dateTimeToPicker = flatpickr(dateTimeTo, { 
					altInput: true,
					altFormat: "j. F Y",
					dateFormat: "Y-m-d",
					defaultDate: "today",
					onChange: function(selectedDates, dateStr, instance) {
						dateTimeFromPicker.set('maxDate', selectedDates[0]);
					},
					"locale": "da"
				});
			</script>
			<?php
?>
