<?php

// 20210712 LOE - Translated Some texts 

function productOptions($defaultProvision) {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	global $db;
	global $labelprint;

	$customerCommissionAccountUsed = $customerCommissionAccountUsedId = NULL;
	$customerCommissionAccountNew = $customerCommissionAccountNewId = NULL;
	$confirmDescriptionChange = $confirmDescriptionChange_id = $confirmStockChange = $confirmStockChange_id = NULL;
	$commissionAccountUsed = $commissionAccountUsedId = $commissionFromDate = $DisItemIfNeg = $DisItemIfNeg_id = NULL;
	$ownCommissionAccountNew = $ownCommissionAccountNewId = NULL;
	$ownCommissionAccountUsed = $ownCommissionAccountNewUsed = NULL;
	$useCommission = $useCommission_id = $vatOnItemCard = $vatOnItemCard_id = NULL;
	$commissionInclVat = $commissionInclVatId = NULL;
	
	
	db_modify("update settings set var_grp = 'items' where var_grp='varer'",__FILE__ . " linje " . __LINE__);
	$qtxt="select id from grupper WHERE art = 'DIV' and kodenr='5'";
	($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$id=$r['id']:$id=0;
	$qtxt="select id,var_value from settings where var_name = 'vatOnItemCard' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$vatOnItemCard_id=$r['id'];
		if ($r['var_value']) $vatOnItemCard='checked';
	}
	/*
	if (!$vatOnItemCard_id) { // remove this after rel 3.7.6
		$q = db_select("select * from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__);
		# OBS $box2,3,4,5,7,9 bruges under shop valg!!
		# OBS $box8 bruges under ordrelaterede valg!!
		$r = db_fetch_array($q);
		$id=$r['id'];$beskrivelse=$r['beskrivelse'];$kodenr=$r['kodenr'];$box1=trim($r['box1']);
		($box1)?$vatOnItemCard_id='checked':$vatOnItemCard_id=NULL;
	}
	*/
	$qtxt="select id,var_value from settings where var_name = 'DisItemIfNeg' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$DisItemIfNeg_id=$r['id'];
		if ($r['var_value']) $DisItemIfNeg='checked';
	}
	$qtxt="select id,var_value from settings where var_name = 'confirmDescriptionChange' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$confirmDescriptionChange_id=$r['id'];
		if ($r['var_value']) $confirmDescriptionChange='checked';
	} 
	$qtxt="select id,var_value from settings where var_name = 'confirmStockChange' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$confirmStockChange_id=$r['id'];
		if ($r['var_value']) $confirmStockChange='checked';
	}
	$qtxt="select id,var_value from settings where var_name = 'useCommission' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$useCommissionId=$r['id'];
		if ($r['var_value']) $useCommission='checked';
	}
	$qtxt="select id,var_value from settings where var_name = 'commissionAccountNew' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$commissionAccountNewId=$r['id'];
		$commissionAccountNew=$r['var_value'];
	}
	$qtxt="select id,var_value from settings where var_name = 'commissionAccountUsed' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$commissionAccountUsedId=$r['id'];
		$commissionAccountUsed=$r['var_value'];
	}
	$qtxt="select id,var_value from settings where var_name = 'customerCommissionAccountNew' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$customerCommissionAccountNewId=$r['id'];
		$customerCommissionAccountNew=$r['var_value'];
	}
	$qtxt="select id,var_value from settings where var_name = 'customerCommissionAccountUsed' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$customerCommissionAccountUsedId=$r['id'];
		$customerCommissionAccountUsed=$r['var_value'];
	}
	$qtxt="select id,var_value from settings where var_name = 'ownCommissionAccountNew' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$ownCommissionAccountNewId=$r['id'];
		$ownCommissionAccountNew=$r['var_value'];
	}
	$qtxt="select id,var_value from settings where var_name = 'ownCommissionAccountUsed' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$ownCommissionAccountUsedId=$r['id'];
		$ownCommissionAccountUsed=$r['var_value'];
	}
	$qtxt="select id,var_value from settings where var_name = 'commissionFromDate' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$commissionFromDate=dkdato($r['var_value']);
	}
	$qtxt="select id,var_value from settings where var_name = 'defaultCommission' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$defaultCommissionId = $r['id'];
		$defaultCommission   = $r['var_value'];
	}
	$qtxt="select id,var_value from settings where var_name = 'commissionInclVat' and var_grp = 'items'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$commissionInclVatId = $r['id'];
		($r['var_value'] == 'on')?$commissionInclVat = 'checked':$commissionInclVat = '';
	}
	$qtxt="select var_value from settings where var_name = 'numberFormat' and var_grp = 'localization'";
	($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$numberFormat = $r['var_value']:$numberFormat = '.|,';
	$qtxt = "SELECT var_value FROM settings WHERE var_name = 'min_beholdning' AND var_grp = 'productOptions'";
	($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$minBeholdning = (int)$r["var_value"]:$minBeholdning = 0;
	print "<form name='productOptions' action='diverse.php?sektion=productOptions' method='post'>";
	print "<tr><td colspan='6'><hr></td></tr>";
	$text = findtekst('470|Varerelaterede valg',$sprog_id);
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>$text<!--tekst 470--></u></b></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<input type=hidden name='id' value='$id'>";
	print "<input type=hidden name='vatOnItemCard_id' value='$vatOnItemCard_id'>";
	print "<input type=hidden name='confirmDescriptionChange_id' value='$confirmDescriptionChange_id'>";
	print "<input type=hidden name='confirmStockChange_id' value='$confirmStockChange_id'>";
	print "<input type=hidden name='DisItemIfNeg_id' value='$DisItemIfNeg_id'>";
	print "<input type=hidden name='useCommissionId' value='$useCommissionId'>";
	print "<input type=hidden name='commissionAccountNewId' value='$commissionAccountNewId'>";
	print "<input type=hidden name='commissionAccountUsedId' value='$commissionAccountUsedId'>";
	print "<input type=hidden name='customerCommissionAccountNewId' value='$customerCommissionAccountNewId'>";
	print "<input type=hidden name='customerCommissionAccountUsedId' value='$customerCommissionAccountUsedId'>";
	print "<input type=hidden name='ownCommissionAccountNewId' value='$ownCommissionAccountNewId'>";
	print "<input type=hidden name='ownCommissionAccountUsedId' value='$ownCommissionAccountUsedId'>";
	print "<input type=hidden name='defaultCommissionId' value='$defaultCommissionId'>";
	print "<input type=hidden name='commissionInclVatId' value='$commissionInclVatId'>";
	
	/*
	$text  = findtekst(468,$sprog_id);
	$title = findtekst(469,$sprog_id);
	print "<tr><td title='$title'>$text</td><td title='$title'><SELECT class='inputbox' name='box1'>";
	$r=db_fetch_array(db_select("select * from grupper where art = 'SM' and kodenr = '$box1'",__FILE__ . " linje " . __LINE__));
	if ($box1) $value="S".$box1.":".$r['beskrivelse'];
	print "<option value='$box1'>$value</option>";
	$q=db_select("select * from grupper where art = 'SM' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$value="S".$r['kodenr'].":".$r['beskrivelse'];
		print "<option value='$r[kodenr]'>$value</option>";
	}
	print "<option></option>";
	print "</select></td></tr>";
	*/

	$text  = findtekst('1273|Vis priser med moms på varekort', $sprog_id); #20210712
	$title = findtekst('1274|Når dette felt er afmærket, bliver varer vist inkl. moms på varekortet', $sprog_id);
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='checkbox' class='inputbox' name='vatOnItemCard' $vatOnItemCard></td></tr>";
	print "<td><br></td><td><br></td><td><br></td>";

	$text  = findtekst('1275|Bekræft ændring af beskrivelse på varekort', $sprog_id);
	$title = findtekst('1276|Når dette felt er afmærket, skal der bekræftes ved ændring af beskrivelsen på varekort', $sprog_id);
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='checkbox' class='inputbox' name='confirmDescriptionChange' $confirmDescriptionChange></td></tr>";
	$text  = findtekst('1277|Bekræft ved ændring af beholdning på varekort', $sprog_id);
	$title = findtekst('1278|Når dette felt er afmærket, skal der bekræftes ved ændring af beholdning på varekort', $sprog_id);
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='checkbox' class='inputbox' name='confirmStockChange' $confirmStockChange></td></tr>";
	print "<td><br></td><td><br></td><td><br></td>";

	# Lager status opsætning
	$text  = findtekst('2553|Mail til lagerstatusrapporter', $sprog_id);
	$title = findtekst('2554|Den mail, der skal bruges til at modtage lagerstatusrapporter', $sprog_id);
	$statusmail = get_settings_value("mail", "lagerstatus", "");
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='text' class='inputbox' name='statusmail' value='$statusmail'></td></tr>";

	$text  = findtekst('2555|Hyppighed for lagerstatus e-mails (timer)', $sprog_id);
	$title = findtekst('2556|Hvor ofte lagerstatus e-mails skal sendes, angivet i timer', $sprog_id);
	$lagertime = get_settings_value("time", "lagerstatus", "");
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='text' class='inputbox' name='lagertime' value='$lagertime'></td></tr>";

	$text  = findtekst('2557|Lagerstatus beholdningsgrænse', $sprog_id);
	$title = findtekst('2558|Angiver den beholdningsmængde, hvor systemet begynder at sende lagerstatus e-mails. Når lageret falder til eller under denne grænse, udsendes en e-mail.', $sprog_id);
	$lagertrigger = get_settings_value("trigger", "lagerstatus", "");
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='text' class='inputbox' name='lagertrigger' value='$lagertrigger'></td></tr>";
	print "<td><br></td><td><br></td><td><br></td>";

	$text  = findtekst('1279|Sæt vare til udgået, når beholdning bliver negativ', $sprog_id);
	$title = findtekst('1280|Når dette felt er afmærket bliver varen markeret som udgået når beholdningen bliver negativ', $sprog_id);
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='checkbox' class='inputbox' name='DisItemIfNeg' $DisItemIfNeg></td></tr>";

	$text  = findtekst('1281|Kommisionsvarer - afmærk hvis der anvendes POS og der sælges varer i kommission', $sprog_id);
	$title = findtekst('1282|Når dette felt er afmærket vises \'Afregn kommission på kasseoptælling\' og \'Kommissionsvare\' på varekort', $sprog_id);
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='checkbox' class='inputbox' name='useCommission' $useCommission></td></tr>";

	if ($useCommission) {
		$text  = findtekst('1283|Standard kommissionssats', $sprog_id);
		$title = findtekst('1284|Sættes en værdi her, anvendes denne sats som udgangspunkt ved oprettelse af kommissionsvarer.', $sprog_id)."\n";
		$title.= findtekst('1285|Hvis feltet er tomt, beregnes kostprisen manuelt.', $sprog_id);
		list($a,$b) = explode("|",$numberFormat);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' style='width:50px;text-align:right;' class='inputbox' ";
		print "name='defaultCommission' value= '". $defaultCommission ."'></td></tr>";

		$text  = findtekst('2544|Medtag moms for kommision', $sprog_id);
		$title = findtekst('2545|Afmærkes dette felt er kommissionen ekskl. moms. Ved f.eks. 15% kommission og 25% moms, bliver den faktiske kommision 18,75%.', $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'>";
		print "<input type='checkbox' class='inputbox' name='commissionInclVat' $commissionInclVat></td></tr>";
		$text  = findtekst('1286|Indtægtskonto for kommisionssalg, nye varer', $sprog_id);
		$title = findtekst('1287|Angiv den konto i kontoplanen hvor indtægter fra kommissionssalg af nye varer skal bogføres', $sprog_id)."\n";
		$title.= findtekst('1288|Er feltet tomt, skal beløbet overføres manuelt i en kassekladde.', $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' style='width:75px;text-align:right;' class='inputbox' ";
		print "name='commissionAccountNew' value= '$commissionAccountNew'></td></tr>";

		$text  = findtekst('1289|Afregningskonto for kommissionssalg, nye varer', $sprog_id);
		$title = findtekst('1290|Angiv den konto i kontoplanen, hvorfra afregning for kommissionssalg af nye varer skal trækkes.', $sprog_id)."\n";
		$title.= findtekst('1288|Er feltet tomt, skal beløbet overføres manuelt i en kassekladde.', $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' style='width:75px;text-align:right;' class='inputbox' ";
		print "name='customerCommissionAccountNew' value= '$customerCommissionAccountNew'></td></tr>";

		$text  = findtekst('1291|Egen konto for kommissionssalg, nye varer', $sprog_id);
		$title = findtekst('1292|Angiv den konto i kontoplanen hvorfra kommission af salg af nye varer skal trækkes.', $sprog_id)."\n";
		$title.= findtekst('1288|Er feltet tomt, skal beløbet overføres manuelt i en kassekladde.', $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' style='width:75px;text-align:right;' class='inputbox' ";
		print "name='ownCommissionAccountNew' value= '$ownCommissionAccountNew'></td></tr>";

		$text  = findtekst('1293|Indtægtskonto for kommissionssalg, brugte varer', $sprog_id);
		$title = findtekst('1294|Angiv den konto i kontoplanen hvor indtægter fra kommissionssalg af brugte skal bogføres.', $sprog_id)."\n";
		$title.= findtekst('1288|Er feltet tomt, skal beløbet overføres manuelt i en kassekladde.', $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' style='width:75px;text-align:right;' class='inputbox' ";
		print "name='commissionAccountUsed' value= '$commissionAccountUsed'></td></tr>";

		$text  = findtekst('1295|Afregningskonto for kommissionssalg, brugte varer', $sprog_id);
		$title = findtekst('1296|Angiv den konto i kontoplanen hvorfra afregning for kommissionssalg af brugte skal bogføres.', $sprog_id)."\n";
		$title.= findtekst('1288|Er feltet tomt, skal beløbet overføres manuelt i en kassekladde.', $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' style='width:75px;text-align:right;' class='inputbox' ";
		print "name='customerCommissionAccountUsed' value= '$customerCommissionAccountUsed'></td></tr>";

		$text  = findtekst('1297|Egen konto for kommissionssalg, brugte varer', $sprog_id);
		$title = findtekst('1298|Angiv den konto i kontoplanen hvorfra kommission af salg af brugte varer skal trækkes.', $sprog_id)."\n";
		$title.= findtekst('1288|Er feltet tomt, skal beløbet overføres manuelt i en kassekladde.', $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' style='width:75px;text-align:right;' class='inputbox' ";
		print "name='ownCommissionAccountUsed' value= '$ownCommissionAccountUsed'></td></tr>";
		
		$text  = findtekst('1299|Konverter eksisterende varer?', $sprog_id);
		$title = findtekst('1300|Denne funktion anvendes til konvertering af varer oprettet til brug med \'Mit Salg\' hvor kostprisen', $sprog_id)." ";
		$title.= findtekst('1301|blev brugt som kommissions procent.', $sprog_id)."\n";
		$title.= findtekst('1302|Afmærkes dette felt, ændres alle varer hvor salgspris er 0, kostpris er mellem 0,10 og 0,50, og varenummeret starter med', $sprog_id)." ";
		$title.= findtekst('1303|\'kb\' eller \'kn\'.', $sprog_id)."\n";
		$title.= findtekst('1304|Hvis kostprisen f.eks. er 0,15 bliver denne ændret til 0,85, og kommisionssatsen bliver sat til 15%.', $sprog_id)."\n";
		$title.= findtekst('1305|Kontakt os gerne for assistance - +45 4690 2208', $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='checkbox' class='inputbox' name='convertExisting'></td></tr>";

		$text  = findtekst('1306|Startdato for afregning til kommissionskunder?', $sprog_id);
		$title = findtekst('1307|Næste afregning til kommissionskunder vil blive beregnet fra og med den dato der angives her', $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' class='inputbox' style='width:75px;' name='comissionFromDate' ";
		print "value='$commissionFromDate' placeholder='01-01-2020'></td></tr>";
	}
	print "<tr><td><br></td><td><br></td><td><br></td></tr>";
	$text  = findtekst('2420|Standard minimumsbeholdning', $sprog_id);
	$title = findtekst('2419|Minimumsbeholdning på varer', $sprog_id);
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='text' class='inputbox' name='minBeholdning' value='$minBeholdning'></td></tr>";
	print "<td><br></td><td><br></td><td><br></td>";
	$text = findtekst('471|Gem/opdatér', $sprog_id);
	print "<td align = center><input class='button green medium' type=submit accesskey='g' value='$text' name='submit'><!--tekst 471--></td>";
	print "<tr><td><br></td></tr>";
	print "</form>";
} # endfunc productOptions

 
?>