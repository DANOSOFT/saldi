<?php
function yearX1($id, $kodenr, $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben,$aut_lager) {
	global $overfor_til,$regnaar,$sprog_id,$menu;
	$debetsum=0;
	$kreditsum=0;

	$r=db_fetch_array(db_select("select max(kodenr) as max_aar from grupper where art = 'RA'",__FILE__ . " linje " . __LINE__));
	$max_aar=$r['max_aar'];

	print "<form id = '1' name='yearX1' action='regnskabskort.php' method='post'>";
	if ($id) print "<tr><td colspan=5 align = center><big><b>".findtekst(1206,$sprog_id)." $kodenr. ".findtekst(894,$sprog_id).": $beskrivelse</td></tr>\n";
	else {
		print "<tr><td colspan=5 align = center><big><b>". findtekst(1232,$sprog_id)." $kodenr. ".findtekst(894,$sprog_id).": $beskrivelse</td></tr>\n";
		$aaben='on';
	}
	print "<tr><td colspan=5 align='center'><table width=100% border=0><tbody><tr>"; ###########################table 8d start
	print "<tr><td></td><td align='center'>Start</td><td align='center'>Start</td><td align='center'>".findtekst(1216,$sprog_id)."</td><td align='center'>".findtekst(1216,$sprog_id)."</td><td align='center'>".findtekst(1086,$sprog_id)."</td></tr>\n";
	print "<tr><td align='center'>".findtekst(914,$sprog_id)."</td><td align='center'>".findtekst(1217,$sprog_id)."</td><td align='center'>".findtekst(1218,$sprog_id)."</td><td align='center'>".findtekst(1217,$sprog_id)."</td><td align='center'>".findtekst(1218,$sprog_id)."</td><td align='center'>".findtekst(1219,$sprog_id)."</td></tr>\n";
	print "<tr><input type=hidden name=kodenr value=$kodenr><input type=hidden name=id value='$id'	>";
	print "<td align='center'><input type=text size=30 name=beskrivelse value=\"$beskrivelse\" onchange=\"javascript:docChange = true;\"></td>";
	print "<td align='center'><input readonly=readonly style='text-align:right' size='2' name=startmd value=$startmd></td>";
	print "<td align='center'><input readonly=readonly style='text-align:right' size='4' name=startaar value=$startaar></td>";
	print "<td align='center'><input type=text style='text-align:right' size='2' name=slutmd value=$slutmd onchange=\"javascript:docChange = true;\"></td>";
	print "<td align='center'><input type=text style='text-align:right' size='4' name=slutaar value=$slutaar onchange=\"javascript:docChange = true;\"></td>";
	(strstr($aaben,'on'))?$aaben='checked':$aaben=NULL;
	if (!$id) $checked='checked';
	print "<td align='center'><input type='checkbox' name='aaben' $aaben onchange=\"javascript:docChange = true;\"></td>";
	print "</tr>\n</tbody></table></td></tr>\n"; #####################################################table 8d slut
	print "<tr><td colspan=2 align='center'> ".findtekst(1231,$sprog_id)." $kodenr. ".findtekst(894,$sprog_id).":</td><td align = center> ".findtekst(1073,$sprog_id)."</td><td align = center> ".findtekst(1228,$sprog_id)." ".findtekst(904,$sprog_id)."</td><td align = center> ".findtekst(39,$sprog_id)." ".findtekst(1229,$sprog_id)."</td></tr>\n";
	$resultat=0;
	$qtxt = "select primo, kontonr, beskrivelse from kontoplan where kontotype='S' and regnskabsaar='$kodenr' order by kontonr";
	$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$primo[$i]=$row['primo'];
		$kontonr[$i]=$row['kontonr'];
		$resultat+=$primo[$i];
		$i++;
	}

	$ny_sum=0;
	for ($i=0;$i<count($kontonr);$i++) {
		$saldosum+=$belob;
		print "<td>$kontonr[$i]</td>";
		print "<td>$beskrivelse[$i]</td>";
		print "<td width=10 align=right><input type=hidden name=saldo[$y] value=$belob>".dkdecimal($belob,2)."</td>";
		print "<td><SELECT NAME=overfor_til[$y]>";
		if ($row['overfor_til'] && in_array($row['overfor_til'],$kontonr)) print "<option>$row[overfor_til]</option>";
		elseif (in_array($row['kontonr'],$kontonr)) print "<option>$row[kontonr]</option>";
		else print "<option></option>";
		for ($x=1;$x<=$kontoantal;$x++) print "<option>$kontonr[$x]</option>";
		print "</SELECT></td>";

		print "<td width=10 align=right><input type=hidden name=ny_primo[$y] value=$ny_primo[$y]>".dkdecimal($ny_primo[$y],2)."</td></tr>\n";
		$ny_sum=$ny_sum+$ny_primo[$y];
	}
	print "<td></td><td></td><td align=right>".dkdecimal($saldosum,2)."</td><td></td><td align=right>".dkdecimal($ny_sum,2)."</td></tr>\n";
	if ($debetsum-$kreditsum!=0) {print "<BODY onLoad=\"javascript:alert('Konti er ikke i balance')\">";}
#	print "<tr><td colspan = 3> Overfr �ningsbalance</td><td align='center'><input type='checkbox' name=primotal checked></td></tr>\n";
	print "<input type=hidden name=kontoantal value=$y>";
	print "<tr><td colspan = 5 align = center><input class='button green medium' type=submit accesskey=\"g\" value=\"".findtekst(471, $sprog_id)."\"  style=\"width:150px\" name=\"submit\" onclick=\"javascript:docChange = false;\">";
	print "</td></tr>\n";
	print "</form>";

	print "</tbody></table></td></tr>\n";# table 3b slut
print "</tbody></table></div></div>";


if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
	exit;
}
?>
