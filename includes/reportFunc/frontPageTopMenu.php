<?php
		$dato = $dato_fra;
		if ($dato_til)
			$dato .= ":$dato_til";
		$konto = $konto_fra;
		if ($konto_til)
			$konto .= ":$konto_til";

		$tekst1 = findtekst(437, $sprog_id);
		$tekst2 = findtekst(438, $sprog_id);
		$tekst3 = findtekst(439, $sprog_id);
		#			if (strpos($tekst3,'kundenr')) db_modify("update tekster set tekst = '' where tekst_id = 439",__FILE__ . " linje " . __LINE__);
		$tekst4 = findtekst(440, $sprog_id);
		$tekst5 = findtekst(451, $sprog_id);
		$tekst6 = findtekst(452, $sprog_id);
		$overlib1 = "<span class='CellComment'>$tekst1</span>";
		$overlib2 = "<span class='CellComment'>$tekst3</span>";
		$overlib3 = "<span class='CellComment'>$tekst5</span>";
		print "<form name=\"regnskabsaar\" action=\"rapport.php\" method=\"post\">";
		print "<tr>";
		print "<td align=\"center\" colspan='2' class='CellWithComment'><b>$tekst2:</b> &nbsp; &nbsp; <input class=\"inputbox\" style=\"width:129px\" type=\"text\" name=\"dato\" value=\"$dato\"> $overlib1</td>";
		print "<td align=\"center\" colspan='2' class='CellWithComment'><b>$tekst4:</b> &nbsp; &nbsp; <input class=\"inputbox\" style=\"width:129px\" type=\"text\" name=\"konto\" value=\"$konto\"> $overlib2</td>";
		print "</tr>";
		print "<tr>";
		$tekst1 = findtekst(441, $sprog_id);
		$tekst2 = findtekst(444, $sprog_id);
		print "<td align='center'>
		<input style='width:130px' type='submit' value='$tekst1' name='openpost' title='$tekst2'>
		</td>";
		$tekst1 = findtekst(442, $sprog_id);
		$tekst2 = findtekst(445, $sprog_id);
		print "<td align='center'><input style='width:115px' type='submit' value='$tekst1' name='kontosaldo' title='$tekst2'></td>";
		$tekst1 = findtekst(443, $sprog_id);
		$tekst2 = findtekst(446, $sprog_id);
		print "<td align='center'><input style='width:115px' type='submit' value='$tekst1' name='kontokort' title='$tekst2'></td>";
		print "<td align='center' class='CellWithComment'><b>$tekst6:</b>  &nbsp; &nbsp; <label class='checkContainerVisning'><input class='inputbox' type='checkbox' name='husk' $husk><span class='checkmarkVisning'></span></label> $overlib3</td>";
		print "</tr>";
		print "<tr><td></td></tr>";
		print "<tr><td colspan=5 class='border-hr-top'></td></tr>\n";
		print "<tr>";
		if ($kontoart == 'D') {
			$tekst1 = findtekst(447, $sprog_id);
			$tekst2 = findtekst(448, $sprog_id);
			$tekst3 = findtekst(455, $sprog_id);
			print "<td align='center'><span title='$tekst1' onClick='window.location.href='top100.php''><input style='width:115px' type='button' value='$tekst2' name='submit'></span></td>";
			if (db_fetch_array(db_select("select id from grupper where art = 'POS' and box2 >= '1'", __FILE__ . " linje " . __LINE__))) {
				print "<td align='center'><input title='" . findtekst(918, $sprog_id) . "' style='width:115px' type='submit' value='" . findtekst(918, $sprog_id) . "' name='salgsstat'></td>";
				print "<td align=center><a href='kassespor.php'><input title='Oversigt over POS transaktioner' style='width:115px' type='button' value='$tekst3'></a></td>";
			} else {
				print "<td align='center' colspan='2'><input title='" . findtekst(918, $sprog_id) . "' style='width:115px' type='submit' value='" . findtekst(918, $sprog_id) . "' name='salgsstat'></td>";
			}
			if (db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box10 >= 'on'", __FILE__ . " linje " . __LINE__))) {
				$tekst1 = findtekst(531, $sprog_id);
				$tekst2 = findtekst(532, $sprog_id);
				print "<td align=center><span onClick='javascript:location.href='../debitor/betalingsliste.php''><input title='$tekst1' style='width:145px' type='button' value='$tekst2'></span></td>\n";
			} elseif (file_exists("../debitor/multiroute.php")) {
				print "<td align=center><span onclick='javascript:location.href='../debitor/multiroute.php''><input title='Multiroute' style='width:135px' type='button' value='" . findtekst(923, $sprog_id) . "'></span></td>\n";
			}
			print "</tr>\n";
			print "</form>";
			print "<tr><td colspan=5 class='border-hr-top'></td></tr>\n";
			print "<tr><th colspan='6' style='text-align:center;'><p>SAF-T Cash Register Rapport</p></th></tr>\n"; // text skal i 'findtekst'
			print "<tr><td colspan='6' style='text-align:center;'><p>Vælg periode:</p></td></tr>\n"; // text skal i 'findtekst'
			print "<form method='post' action='saftCashRegister.php'>";
			print "<tr><td colspan='6' style='text-align:center;'><div style='display:flex;align-items: center;margin:0 35px 0 30px;'>
            <span style='padding:0 5px 0 5px;'>fra</span>
            <input type='text' id='fromDate' name='startDate' />
            <span style='padding:0 5px 0 5px;'>til</span>
            <input type='text' id='toDate' name='endDate' />
            </div></td></tr>\n";
			print "<tr><td colspan='6' style='text-align:center;'><input style='width:115px;' type='submit' value='SAF-T' name='saft'></td></tr>\n";
			print "</form>";
		} else {
			$tekst1 = findtekst(531, $sprog_id);
			$tekst2 = findtekst(532, $sprog_id);
			print "<td align='center' colspan='2'>";
			if (db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box10 >= 'on'", __FILE__ . " linje " . __LINE__))) {
				print "<span onClick='javascript:location.href='../kreditor/betalingsliste.php''>\n";
				print "<input title='$tekst1' style='width:150px' type='button' value='$tekst2'>\n";
				print "</span></td>\n";
			}
			print "<td align='center' colspan='2'><input title='Salgsstat' style='width:115px' type='submit' value='" . ucfirst(findtekst(918, $sprog_id)) . "' name='salgsstat'></td>\n";
			print "</tr></form>";
		}
		print "</td></tr>\n";
		print "</tbody></table></div>";
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
