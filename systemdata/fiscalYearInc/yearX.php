<?php
function yearX($id, $kodenr, $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben,$aut_lager) {
	global $overfor_til,$regnaar,$sprog_id,$menu;
	$debetsum=0;
	$kreditsum=0;

	$r=db_fetch_array(db_select("select max(kodenr) as max_aar from grupper where art = 'RA'",__FILE__ . " linje " . __LINE__));
	$max_aar=$r['max_aar'];

	$pre_regnaar=$kodenr-1;
	$query = db_select("select * from grupper where art = 'RA' and kodenr = '$pre_regnaar'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$pre_startmd     = $row['box1'];
		$pre_startaar    = $row['box2'];
		$pre_slutmd      = $row['box3'];
		$pre_slutaar     = $row['box4'];
		$pre_laast_lager = $row['box9'];
		$preDeleted      = $row['box10'];
	}
	if (!$preDeleted) {		
	$pre_slutdato=31;
	while (!checkdate($pre_slutmd, $pre_slutdato, $pre_slutaar)) {
		$pre_slutdato=$pre_slutdato-1;
		if ($pre_slutdato<28) break 1;
	}
	$pre_regnstart = $pre_startaar. "-" . $pre_startmd . "-" . '01';
	$pre_regnslut = $pre_slutaar . "-" . $pre_slutmd . "-" . $pre_slutdato;
}
	if (!$beskrivelse){
		$beskrivelse = $startaar;
		if ($startaar != $slutaar) $beskrivelse.= "/".$slutaar;
	}

	print "<form id = '1' name='yearX' action='regnskabskort.php' method='post'>";
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
	$tmp=$kodenr;
	$kontoantal=0;
	while ($kontoantal<1&&$tmp>0){ #Hvis der ikke er oprettet konti for indevaerende regsskabsaar, hentes konti fra forrige.
		$qtxt = "select primo, kontonr, beskrivelse from kontoplan where kontotype='S' and regnskabsaar='$tmp' order by kontonr";
		$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$kontoantal++;
			$primo[$kontoantal]=$row['primo'];
			$kontonr[$kontoantal]=$row['kontonr'];
		}
		$tmp--;
	}
	$pre_regnaar=$kodenr-1;
/*
	$qtxt = "select box2,box9 from grupper where kodenr='$pre_regnaar' and art='RA'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	($r['box2'] >= '2015' && !$r['box9'])?$aut_lager='on':$aut_lager=NULL;
*/
	$aut_lager=NULL;
	if (!$pre_regnaar) {
		echo "regnaar mangler";
		exit;
	}
/*
	if ($aut_lager) {
		$x=0;
		$varekob=array();
		$laas_lager=1;
		$q=db_select("select box1,box2,box3 from grupper where art = 'VG' and box8 = 'on'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if ($r['box1'] && !in_array($r['box3'],$varekob)) {
				$varelager_i[$x]=$r['box1'];
				$varelager_u[$x]=$r['box2'];
				$varekob[$x]=$r['box3'];
				if ($varelager_i[$x] || $varelager_u[$x]) $laas_lager=0;
				$x++;
			}
		}
		if ($laas_lager) {
			db_modify("update grupper set box9='on' where kodenr='$pre_regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
			$aut_lager=NULL;
		}
	}
*/	
	$ny_sum=0;
	$resultat=0;
	$qtxt = "select * from kontoplan where kontotype='D' and regnskabsaar=$pre_regnaar order by kontonr";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$y=0;
	while ($r = db_fetch_array($q)) {
		$resultat+=afrund($r['primo'],2);
		$q2 = db_select("select * from transaktioner where transdate>='$pre_regnstart' and transdate<='$pre_regnslut' and kontonr='$r[kontonr]'",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) $resultat+=afrund($r2['debet']-$r2['kredit'],2);
/*
		if ($aut_lager) {
			if (in_array($r['kontonr'],$varekob)) {
				$l_a_primo[$x]=find_lagervaerdi($r['kontonr'],$pre_regnstart,'start');
				$l_a_sum[$x]=find_lagervaerdi($r['kontonr'],$pre_regnslut,'slut');
# Varekøb (debet) debiteres lager primo og krediteres lager saldo. Dvs tallet mindskes hvis lager øges
				$resultat+=$l_a_primo[$x];
				$resultat-=$l_a_sum[$x];
			}
			if (in_array($r['kontonr'],$varelager_i) || in_array($r['kontonr'],$varelager_u)) {
				$l_a_primo[$x]=find_lagervaerdi($r['kontonr'],$pre_regnstart,'start');
				$l_a_sum[$x]=find_lagervaerdi($r['kontonr'],$pre_regnslut,'slut');
# Varelager (debet) krediteres lager primo og og debiteres lager saldo.  Dvs tallet øges hvis lager øges
				$resultat-=$l_a_primo[$x];
				$resultat+=$l_a_sum[$x];
			}
		}
*/		
	}
	$resultat=afrund($resultat,2);
	$r=db_fetch_array(db_select("select * from kontoplan where kontotype='X' and regnskabsaar=$pre_regnaar",__FILE__ . " linje " . __LINE__));
	$sideskift=$r['kontonr']*1;
	if ($sideskift) {
		$q2=db_select("select * from transaktioner where transdate>='$pre_regnstart' and transdate<='$pre_regnslut' and kontonr='$sideskift'",__FILE__ . " linje " . __LINE__);
		while ($r2=db_fetch_array($q2)) $resultat+=afrund($r2['debet']-$r2['kredit'],2);
		$saldosum=$resultat;
		print "<td><br /></td>";
		print "<td>Resultat</td>";
		print "<input type='hidden' name='kontonr[0]' value='$sideskift'>";
		print "<td width='10' align='right'><input type=hidden name=saldo[0] value=$resultat>".dkdecimal($resultat,2)."</td>";
		print "<td><SELECT NAME=overfor_til[0]>";
		if (isset($r['overfor_til']) && $r['overfor_til']) print "<option>$r[overfor_til]</option>";
		print "<option></option>";
		for ($x=1;$x<=$kontoantal;$x++) print "<option>$kontonr[$x]</option>";
		print "</SELECT></td>";
		print "<td width='10'><br /></td></tr>\n";
		if (isset($ny_primo[$y])) $ny_sum+=$ny_primo[$y];
	}
#cho "select * from kontoplan where kontotype='S' and regnskabsaar='$pre_regnaar' order by kontonr<br>";
	$query = db_select("select * from kontoplan where kontotype='S' and regnskabsaar='$pre_regnaar' order by kontonr",__FILE__ . " linje " . __LINE__);
	$y=0;
	while ($row = db_fetch_array($query)) {
		$y++;
		$ny_primo[$y]=0;
    for ($x=1; $x<=$kontoantal; $x++) {
			if ($kontonr[$x]==$row['kontonr']) {
				$ny_primo[$y]=$primo[$x];
#				$overfor_til[$y]=$row['overfor_til'];
			}
		}
		$belob=0;
		$belob=$row['primo'];
		print "<tr><input type=hidden name=kontonr[$y] value=$row[kontonr]>";
		$q2 = db_select("select * from transaktioner where transdate>='$pre_regnstart' and transdate<='$pre_regnslut' and kontonr='$row[kontonr]'",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			 $belob+=afrund($r2['debet']-$r2['kredit'],2);
		}
		if ($aut_lager) {
			if (in_array($row['kontonr'],$varekob)) {
				$l_a_primo[$x]=find_lagervaerdi($row['kontonr'],$pre_regnstart,'');
				$l_a_sum[$x]=find_lagervaerdi($row['kontonr'],$pre_regnslut,'');
# Varekøb (debet) debiteres lager primo og krediteres lager saldo. Dvs tallet mindskes hvis lager øges
				$belob+=$l_a_primo[$x];
				$belob-=$l_a_sum[$x];
			}
			if (in_array($row['kontonr'],$varelager_i) || in_array($row['kontonr'],$varelager_u)) {
				$l_a_primo[$x]=find_lagervaerdi($row['kontonr'],$pre_regnstart,'start');
				$l_a_sum[$x]=find_lagervaerdi($row['kontonr'],$pre_regnslut,'slut');
# Varelager (debet) krediteres lager primo og og debiteres lager saldo.  Dvs tallet øges hvis lager øges
				$belob-=$l_a_primo[$x];
				$belob+=$l_a_sum[$x];
			}
		}

		$saldosum=$saldosum+$belob;
		print "<td>$row[kontonr]</td>";
		print "<td>$row[beskrivelse]</td>";
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
/*
	if ($aut_lager && date("Y-m-d")>$pre_regnslut) {
		$title="Bogfører alle lagerbevægelser i det foregående år. Bør gøres umiddelbart efter årsskifte og lageroptælling".
		$confirmtxt="";
		print "&nbsp;<a href=laas_lager.php?regnaar=$pre_regnaar&regnaar_id=$id&print=0><input title=\"$title\" type=\"button\" value=\"".findtekst(1230, $sprog_id)."\" onclick=\"return confirm('Bogfør og lås lagerprimo? Obs - Vær tålmodig det kan tage flere minutter')\"></a>";
	}
*/	
	# if ($regnaar==$max_aar) print "<input type=submit value=\"Slet\" name=\"submit\" onclick=\"javascript:docChange = false;\">";
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
