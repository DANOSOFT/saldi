<?php
// 20261005 Sawaneh Settings redesign G1.2 (audit F5): the layout for a year whose previous year is deleted. Once F5 was
//                  fixed this page is really shown, so it was rewritten: the old one printed rows from undefined
//                  arrays. The previous year's postings are gone, so the opening balance is shown as it stands and
//                  cannot be transferred again; only description, end and "posting allowed" are saved.
// 20261008 Sawaneh Settings 4e: the old top-menu ($menu=='T') branches removed; the else legs stay (spec §7.6 R3).
function yearX1($id, $kodenr, $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben, $aut_lager) {
	global $sprog_id, $menu;

	$kodenr = (int) $kodenr;
	if (!$beskrivelse) {
		$beskrivelse = $startaar;
		if ($startaar != $slutaar) $beskrivelse .= "/" . $slutaar;
	}
	$h = function ($s) {
		return htmlspecialchars((string) $s, ENT_QUOTES);
	};
	print "<form name='yearX1' action='regnskabskort.php' method='post'>";
	print "<tr><td colspan=4 align=center><big><b>" . findtekst('1206|Ret', $sprog_id) . " $kodenr. " . findtekst('894|Regnskabsår', $sprog_id) . ": " . $h($beskrivelse) . "</b></big></td></tr>\n";
	print "<tr><td colspan=4 align='center'><table width=100% border=0><tbody>";
	print "<tr><td align='center'>" . findtekst('914|Beskrivelse', $sprog_id) . "</td><td align='center'>Start " . findtekst('1217|Måned', $sprog_id) . "</td><td align='center'>Start " . findtekst('1218|År', $sprog_id) . "</td>";
	print "<td align='center'>" . findtekst('1216|Slut', $sprog_id) . " " . findtekst('1217|Måned', $sprog_id) . "</td><td align='center'>" . findtekst('1216|Slut', $sprog_id) . " " . findtekst('1218|År', $sprog_id) . "</td><td align='center'>" . findtekst('1219|Tilladt', $sprog_id) . "</td></tr>\n";
	print "<tr><td align='center'><input type='hidden' name='kodenr' value='$kodenr'><input type='hidden' name='id' value='" . (int) $id . "'>";
	print "<input type='text' size='30' name='beskrivelse' value=\"" . $h($beskrivelse) . "\" onchange=\"javascript:docChange = true;\"></td>";
	print "<td align='center'><input readonly style='text-align:right' size='2' name='startmd' value='" . $h($startmd) . "'></td>";
	print "<td align='center'><input readonly style='text-align:right' size='4' name='startaar' value='" . $h($startaar) . "'></td>";
	print "<td align='center'><input type='text' style='text-align:right' size='2' name='slutmd' value='" . $h($slutmd) . "' onchange=\"javascript:docChange = true;\"></td>";
	print "<td align='center'><input type='text' style='text-align:right' size='4' name='slutaar' value='" . $h($slutaar) . "' onchange=\"javascript:docChange = true;\"></td>";
	print "<td align='center'><input type='checkbox' name='aaben'" . (strstr((string) $aaben, 'on') ? ' checked' : '') . " onchange=\"javascript:docChange = true;\"></td></tr>\n";
	print "</tbody></table></td></tr>\n";
	print "<tr><td colspan=4>" . findtekst('6579|Forrige regnskabsår er slettet, så åbningsbalancen vises, som den står, og kan ikke overføres igen.', $sprog_id) . "</td></tr>\n";
	print "<tr><td colspan=2 align='center'>" . findtekst('1231|Primotal for', $sprog_id) . " $kodenr. " . findtekst('894|Regnskabsår', $sprog_id) . "</td><td align='right'>" . findtekst('1229|Primo', $sprog_id) . "</td><td></td></tr>\n";
	$sum = 0;
	$q = db_select("select kontonr, beskrivelse, primo from kontoplan where kontotype = 'S' and regnskabsaar = '$kodenr' order by kontonr", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$sum += (float) $r['primo'];
		print "<tr><td>" . $h($r['kontonr']) . "</td><td>" . $h($r['beskrivelse']) . "</td><td align='right'>" . dkdecimal($r['primo'], 2) . "</td><td></td></tr>\n";
	}
	print "<tr><td></td><td></td><td align='right'><b>" . dkdecimal($sum, 2) . "</b></td><td></td></tr>\n";
	print "<input type='hidden' name='kontoantal' value='0'>";
	print "<tr><td colspan=4 align=center><input class='button green medium' type='submit' accesskey='g' value=\"" . findtekst('471|Gem/opdatér', $sprog_id) . "\" style='width:150px' name='submit' onclick=\"javascript:docChange = false;\"></td></tr>\n";
	print "</form>";
	print "</tbody></table></td></tr>\n";
	print "</tbody></table></div></div>";
	include_once '../includes/oldDesign/footer.php';
	exit;
}
