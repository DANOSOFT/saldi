<?php
function year1($id, $kodenr, $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben,$aut_lager) {
	global $sprog_id, $bgcolor5;
	global $menu;
	$row = db_fetch_array(db_select("select MAX(kodenr) as kodenr from grupper where art = 'RA'",__FILE__ . " linje " . __LINE__));
	if ($row['kodenr'] > $kodenr) $laast=1;
	if ($row = db_fetch_array(db_select("select * from grupper where art = 'RB' order by kodenr",__FILE__ . " linje " . __LINE__))) {
		$fakt=$row['box1']*1;
		$modt=$row['box2']*1;
		$faktbill=trim($row['box3']);
		$modtbill=trim($row['box4']);
		$no_faktbill=trim($row['box5']);

	} else {
		$fakt='1';
		$modt='1';
		$no_faktbill='on';
		$modtbill='on';
	}
	if (!$fakt) $fakt='1';
	if (!$modt) $modt='1';

	print "<form name='year1' action='regnskabskort.php' method='post'>";
	// 1226 = Ret 1. regnskabsår 
	if ($id) print "<tr><td colspan=4 align = center><big><b>".findtekst(1226,$sprog_id)." $beskrivelse</big></td></tr>\n";
	else {
		print "<tr><td colspan=4 align = center><big><b>Velkommen til som SALDI bruger</b></big><br />
			Du skal f&oslash;rst oprette dit 1. regnskabs&aring;r, f&oslash;r du kan bruge systemet.<br /><br />
			Systemet er allerede sat op, s&aring; det passer til de fleste virksomheder. Hvis dit 1. regnskabs&aring;r<br />
			passer med perioden 1. januar $startaar til 31. december $slutaar, skal du blot trykke p&aring; knappen [Gem],<br />
			og dit regnskab er klar til brug.<br /><br />
			Hvis der er noget, du er i tvivl om, er du velkommen til at kontakte os p&aring; telefon 4690 2208<br />
			God forn&oslash;jelse.<br /><br />
			</td></tr>\n";
		print "<tr><td colspan=4 align = center><big><b>".findtekst(1227,$sprog_id)." $beskrivelse</big></td></tr>\n";
	}
	print "<tr><td colspan=4 align='center'><table width=100% border=0><tbody><tr>"; #########################table 4c start
	print "<tr><td></td><td align='center'>Start</td><td align='center'>Start</td><td align='center'>".findtekst(1216,$sprog_id)."</td><td align='center'>".findtekst(1216,$sprog_id)."</td><td align='center'>".findtekst(1086,$sprog_id)."</td></tr>\n";
	print "<tr><td align='center'>".findtekst(914,$sprog_id)."</td><td align='center'>".findtekst(1217,$sprog_id)."</td><td align='center'>".findtekst(1218,$sprog_id)."</td><td align='center'>".findtekst(1217,$sprog_id)."</td><td align='center'>".findtekst(1218,$sprog_id)."</td><td align='center'>".findtekst(1219,$sprog_id)."</tr>\n";
	print "<input type=hidden name=kodenr value=1><input type=hidden name='id' value='$id'>\n";
	print "<tr><td align='center'><input type=text size='30' name='beskrivelse' value=\"$beskrivelse\" onchange=\"javascript:docChange = true;\"></td>\n";
	if ($laast) $type="readonly=readonly";
	else $type="type=text";
	print "<td align='center'><input $type style='text-align:right' size='2' name=startmd value='$startmd' onchange=\"javascript:docChange = true;\"></td>\n";
	print "<td align='center'><input $type style='text-align:right' size='4' name=startaar value='$startaar' onchange=\"javascript:docChange = true;\"></td>\n";
	print "<td align='center'><input $type style='text-align:right' size='2' name=slutmd value='$slutmd' onchange=\"javascript:docChange = true;\"></td>\n";
	print "<td align='center'><input $type style='text-align:right' size='4' name=slutaar value='$slutaar' onchange=\"javascript:docChange = true;\"></td>\n";
	if (strstr($aaben,'on')) {
		print "<td align='center'><input type='checkbox' name='aaben' checked onchange=\"javascript:docChange = true;\"></td>\n";
	} else {
		print "<td align='center'><input type='checkbox' name='aaben' onchange=\"javascript:docChange = true;\"></td>\n";
	}

	if ($menu=='T') {
		$styleborder="bordercolor='$bgcolor5'";
	} else {
		print "";
	}
	print "</tr>\n</tbody></table></td></tr>\n"; ###################################################table 4c slut
	print "<tr><td colspan=4 width=100% align='center'><table heigth=100% border=0><tbody>"; ###########################table 5c start
	print "<td align='center' valign=\"top\"><table heigth=100% border=1 $styleborder><tbody>\n";  #################################table 6d start	print "<tr><td align='center'>1. faktnr</td><td align='center'>1. modt. nr.</td><tr>";
	print "<tr>\n <td>".findtekst(1220,$sprog_id)."</td>\n";
	print " <td align='center'><input type=text style='text-align:right' size='4' name=fakt value=$fakt onchange=\"javascript:docChange = true;\"></td>\n</tr>\n";
	print "<tr>\n <td>".findtekst(1221,$sprog_id)."</td>\n";
	print " <td align='center'><input type=text style='text-align:right' size='4' name=modt value=$modt onchange=\"javascript:docChange = true;\"></td>\n</tr>\n";
	print "</tbody></table></td>\n"; ##########################################################table 6d slut
	print "<td><table border=1 $styleborder><tbody>"; ##############################################table 7d start
	if ($no_faktbill) $no_faktbill="checked";
	if ((!$no_faktbill)&&($faktbill)) $faktbill="checked";
	if ($modtbill) $modtbill="checked";
	print "<tr><td align='center'>".findtekst(1222,$sprog_id)."</td><td align='center'><input type='checkbox' name=no_faktbill $no_faktbill onchange=\"javascript:docChange = true;\"></td></tr>\n"; #20210709
	print "<tr><td align='center'>".findtekst(1223,$sprog_id)."</td><td align='center'><input type='checkbox' name=faktbill $faktbill onchange=\"javascript:docChange = true;\"></td></tr>\n";
	print "<tr><td align='center'>".findtekst(1224,$sprog_id)."</td><td align='center'><input type='checkbox' name=modtbill $modtbill onchange=\"javascript:docChange = true;\"></td></tr>\n";
	print "</tbody></table></td>\n"; ##########################################################table 7d slut
	print "<td valign=\"top\"><table border=0><tbody>\n"; ##############################################table 8d start
	print "<tr><td><input class='button green medium' style='width:150px' type=submit accesskey=\"g\" value=\"".findtekst(3,$sprog_id)."\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td></tr>\n";
	print "</tbody></table></td></tr>\n";#####################################################table8d slut
	print "</td></tbody></table></td></tr>\n";#####################################################table5c slut
	print "<tr><td colspan=2 align='center'> ".findtekst(1225,$sprog_id)."</td><td align = center> ".findtekst(1000,$sprog_id)."</td><td align = center> ".findtekst(1001,$sprog_id)."</td></tr>\n";
	$y=0;
	$debetsum=0;
	$kreditsum=0;
	$qtxt = "select id, kontonr, primo, beskrivelse from kontoplan ";
	$qtxt.= "where kontotype='S' and regnskabsaar='$kodenr' order by kontonr";
#cho "$qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)) {
		$y++;
		print "<tr><input type=hidden name=kontonr[$y] value=$row[kontonr]>";
		$debet[$y]="0,00";
		$kredit[$y]="0,00";
		if ($row['primo']>0) {
			$debet[$y]=dkdecimal($row['primo'],2);
			$debetsum=$debetsum+$row['primo'];
		}
		elseif ($row['primo']<0) {
			$kredit[$y]=dkdecimal($row['primo']*-1,2);
			$kreditsum=$kreditsum+($row['primo']*-1);
		}
		print "<td>$row[kontonr]</td>";
		print "<td>$row[beskrivelse]</td>";
		print "<td align=right><input type=text style='text-align:right' size=10 name=debet[$y] value=$debet[$y] onchange=\"javascript:docChange = true;\"></td>";
		print "<td align=right><input type=text style='text-align:right' size=10 name=kredit[$y] value=$kredit[$y] onchange=\"javascript:docChange = true;\"></td></tr>\n";
	}
	print "<td></td><td></td><td align=right>".dkdecimal($debetsum,2)."</td><td align=right>".dkdecimal($kreditsum,2)."</td></tr>\n";
	if (abs($debetsum-$kreditsum)>0.009) {
		print "<BODY onLoad=\"javascript:alert('Konti er ikke i balance')\">";
	}

#	print "<tr><td colspan = 3> Overfr �ningsbalance</td><td align='center'><input type='checkbox' name=primotal checked></td></tr>\n";
	print "<input type=hidden name=kontoantal value=$y>";
	print "<tr><td colspan='4' align='center'>";
	print "<input class='buttom green medium' style='width:150px;' type='submit' accesskey=\"g\" value=\"".findtekst(471, $sprog_id)."\" style=\"width:100px\" ";
	print "name=\"submit\" onclick=\"javascript:docChange = false;\">";
#	print "&nbsp;&nbsp;<input class='button green medium' type=submit value=\"".findtekst(1009,$sprog_id)." ".findtekst(1218,$sprog_id)."\" name=\"delete\"  style=\"width:100px\" ";
#	print "onclick=\"javascript:docChange = false;\">";
	print "</td></tr>\n";
	print "</form></tbody></table>";

	print "</div></div>";
	if ($menu=='T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}
	exit;
}
?>
