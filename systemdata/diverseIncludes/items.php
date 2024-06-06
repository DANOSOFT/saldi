<?php

// 20210712 LOE - Translated Some texts 

function items($defaultProvision) {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	global $db;
	global $labelprint;

	$customerCommissionAccountUsed = $customerCommissionAccountUsedId = NULL;
	$customerCommissionAccountNew = $customerCommissionAccountNewId = NULL;
	$confirmDescriptionChange = $confirmDescriptionChange_id = $confirmStockChange = $confirmStockChange_id = NULL;
	$commissionAccountUsed = $commissionAccountUsedId = $commissionFromDate = $DisItemIfNeg = $DisItemIfNeg_id = NULL;
	$ownCommissionAccountNew = $ownCommissionAccountNewId = $ownCommissionAccountUsed = $ownCommissionAccountNewUsed = NULL;
	$useCommission = $useCommission_id = $vatOnItemCard = $vatOnItemCard_id = NULL;
	
	
	
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
	
	$qtxt="select var_value from settings where var_name = 'numberFormat' and var_grp = 'localization'";
	($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$numberFormat = $r['var_value']:$numberFormat = '.|,';
	print "<form name='vare_valg' action='diverse.php?sektion=vare_valg' method='post'>";
	print "<tr><td colspan='6'><hr></td></tr>";
	$text=findtekst(470,$sprog_id);
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
	
	/*
	$text=findtekst(468,$sprog_id);
	$title=findtekst(469,$sprog_id);
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

	$text=findtekst(1273, $sprog_id); #20210712
	$title=findtekst(1274, $sprog_id);
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='checkbox' class='inputbox' name='vatOnItemCard' $vatOnItemCard></td></tr>";
	print "<td><br></td><td><br></td><td><br></td>";

	$text=findtekst(1275, $sprog_id);
	$title=findtekst(1276, $sprog_id);
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='checkbox' class='inputbox' name='confirmDescriptionChange' $confirmDescriptionChange></td></tr>";
	$text=findtekst(1277, $sprog_id);
	$title=findtekst(1278, $sprog_id);
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='checkbox' class='inputbox' name='confirmStockChange' $confirmStockChange></td></tr>";
	print "<td><br></td><td><br></td><td><br></td>";

	$text=findtekst(1279, $sprog_id);
	$title=findtekst(1280, $sprog_id);
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='checkbox' class='inputbox' name='DisItemIfNeg' $DisItemIfNeg></td></tr>";
	#chooseProvisionForProductGroup($defaultProvision);
	$text=findtekst(1281, $sprog_id);
	$title=findtekst(1282, $sprog_id);
	print "<tr><td title='$title'>$text</td>";
	print "<td title='$title'><input type='checkbox' class='inputbox' name='useCommission' $useCommission></td></tr>";
	if ($useCommission) {
		$text  = findtekst(1283, $sprog_id);
		$title = findtekst(1284, $sprog_id);
		$title.= findtekst(1285, $sprog_id);
		list($a,$b) = explode("|",$numberFormat);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' style='width:50px;text-align:right;' class='inputbox' ";
		print "name='defaultCommission' value= '". $defaultCommission ."'></td></tr>";

		$text  = findtekst(1286, $sprog_id);
		$title = findtekst(1287, $sprog_id);
		$title.= findtekst(1288, $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' style='width:75px;text-align:right;' class='inputbox' ";
		print "name='commissionAccountNew' value= '$commissionAccountNew'></td></tr>";

		$text  = findtekst(1289, $sprog_id);
		$title = findtekst(1290, $sprog_id);
		$title.= findtekst(1288, $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' style='width:75px;text-align:right;' class='inputbox' ";
		print "name='customerCommissionAccountNew' value= '$customerCommissionAccountNew'></td></tr>";

		$text  = findtekst(1291, $sprog_id);
		$title = findtekst(1292, $sprog_id);
		$title.= findtekst(1288, $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' style='width:75px;text-align:right;' class='inputbox' ";
		print "name='ownCommissionAccountNew' value= '$ownCommissionAccountNew'></td></tr>";

		$text  = findtekst(1293, $sprog_id);
		$title = findtekst(1294, $sprog_id);
		$title.= findtekst(1288, $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' style='width:75px;text-align:right;' class='inputbox' ";
		print "name='commissionAccountUsed' value= '$commissionAccountUsed'></td></tr>";

		$text  = findtekst(1295, $sprog_id);
		$title = findtekst(1296, $sprog_id);
		$title.= findtekst(1288, $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' style='width:75px;text-align:right;' class='inputbox' ";
		print "name='customerCommissionAccountUsed' value= '$customerCommissionAccountUsed'></td></tr>";
		$text  = findtekst(1297, $sprog_id);
		$title = findtekst(1298, $sprog_id);
		$title.= findtekst(1288, $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' style='width:75px;text-align:right;' class='inputbox' ";
		print "name='ownCommissionAccountUsed' value= '$ownCommissionAccountUsed'></td></tr>";
		
		$text  = findtekst(1299, $sprog_id);
		$title = findtekst(1300, $sprog_id);
		$title.= findtekst(1301, $sprog_id);
		$title = findtekst(1302, $sprog_id);
		$title.= findtekst(1303, $sprog_id);
		$title.= findtekst(1304, $sprog_id);
		$title.= findtekst(1305, $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='checkbox' class='inputbox' name='convertExisting'></td></tr>";
		$text  = findtekst(1306, $sprog_id);
		$title = findtekst(1307, $sprog_id);
		print "<tr><td title='$title'>$text</td>";
		print "<td title='$title'><input type='text' class='inputbox' style='width:75px;' name='comissionFromDate' ";
		print "value='$commissionFromDate' placeholder='01-01-2020'></td></tr>";
	}
	print "<td><br></td><td><br></td><td><br></td>";
	$text=findtekst(471,$sprog_id);
	print "<td align = center><input class='button green medium' type=submit accesskey='g' value='$text' name='submit'><!--tekst 471--></td>";
	print "<tr><td><br></td></tr>";
	print "</form>";
} # endfunc vare_valg

 
?>
