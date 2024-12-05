<?php
//$bg=$bgcolor5;
print "<div style=\"float:left; width:379px;\">\n";
print "<h3>&nbsp;</h3>\n";
print "<div class=\"contentA\">\n";
print "<div class=\"row\"><div class=\"left\">".findtekst(376,$sprog_id)."<!--tekst 376--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"cvrnr\" value=\"$cvrnr\" onchange=\"javascript:docChange = true;\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<div class=\"row\"><div class=\"left\">".findtekst(377,$sprog_id)."<!--tekst 377--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"tlf\" value=\"$tlf\" onchange=\"javascript:docChange = true;\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<div class=\"row\"><div class=\"left\">".findtekst(378,$sprog_id)."<!--tekst 378--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"fax\" value=\"$fax\" onchange=\"javascript:docChange = true;\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if ($kontotype=='erhverv') {
	//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<div class=\"row\"><div class=\"left\">".findtekst(379,$sprog_id)."<!--tekst 379--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"ean\" value=\"$ean\" onchange=\"javascript:docChange = true;\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<div class=\"row\"><div class=\"left\">".findtekst(380,$sprog_id)."<!--tekst 380--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"institution\" value=\"$institution\" onchange=\"javascript:docChange = true;\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
}
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<div class=\"row\"><div class=\"left\">".findtekst(381,$sprog_id)."<!--tekst 381--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"kreditmax\" style=\"text-align:right;\" value=\"$kreditmax\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<div class=\"row\"><div class=\"left\">".findtekst(382,$sprog_id)."<!--tekst 382--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"bank_reg\" value=\"$bank_reg\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<div class=\"row\"><div class=\"left\">".findtekst(383,$sprog_id)."<!--tekst 383--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"bank_konto\" value=\"$bank_konto\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
##################### PBS ##################### 
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
if ($pbs) {
	print "<div class=\"row\"><div class=\"left\">".findtekst(384,$sprog_id)."<!--tekst 384--></div><div class=\"right\"><input class=\"textSpaceSmall\" type=\"checkbox\" name=\"pbs\" $pbs><input class=\"textSpace textMediumX textIndent\" type=\"text\" name=\"pbs_nr\" value=\"$pbs_nr\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
} else {
	print "<div class=\"row\"><div class=\"left\">".findtekst(385,$sprog_id)."<!--tekst 385--></div><div class=\"right\"><input class=\"textSpaceSmall\" type=\"checkbox\" name=\"pbs\" $pbs></div><div class=\"clear\"></div></div><!-- end of row -->\n";
}
##################### KONTOANSVARLIG ##################### 
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
/*
print "<div class=\"row\"><div class=\"left\">".findtekst(386,$sprog_id)."<!--tekst 386--></div>\n";
	$r = db_fetch_array(db_select("select initialer from ansatte where id='$kontoansvarlig'",__FILE__ . " linje " . __LINE__));
print "<div class=\"right\"><select style=\"width:194px;\" name=\"kontoansvarlig\" value=\"$kontoansvarlig\"  onchange=\"javascript:docChange = true;\">\n"; // ?? value i select tag ??
if ($r['initialer']) {
	$r = db_fetch_array(db_select("select initialer from ansatte where id='$kontoansvarlig'",__FILE__ . " linje " . __LINE__));
	print "<option>$r[initialer]</option>\n";
}
print "<option></option>\n";
if ($r=db_fetch_array(db_select("select id from adresser where art='S'",__FILE__ . " linje " . __LINE__))) $q = db_select("select id, initialer from ansatte where konto_id='$r[id]'",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)){
 	 print "<option>$r[initialer]</option>\n";
}
print "</select></div><div class=\"clear\"></div></div><!-- end of row -->\n";
*/
/*
	$x=0;
	$q=db_select("select id, initialer from ansatte where konto_id=1 order by id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$kontoansvarlig_id[$x]=$r['id'];
		$kontoansvarlig_initialer[$x]=$r['initialer'];
		$x++;
	}
*/

#20160205

// Query til ansvarlig
	$x=0;
	$q=db_select("select * from grupper where art='brgrp'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$gruppe_id[$x]=$r['id']*1;
		$rettigheder[$x]=(substr($r['box2'],2,1)); //finder opret/ret sag rettighed
		if($rettigheder[$x]==1) $gruppeid[$x]=$gruppe_id[$x]; // finder de gruppe_id'er som har rettighed til opret/ret sag
	} 
	
	$in_str = "'".implode("', '", $gruppeid)."'"; // formatere '$gruppeid[]' til f.eks. '52','77' osv.
	
	$x=0;
	$kontoansvarlig_id = array();
	$q=db_select("select * from ansatte where konto_id=1 and gruppe IN ($in_str) order by id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$kontoansvarlig_id[$x]=$r['id'];
		$kontoansvarlig_initialer[$x]=$r['initialer'];
		$ansvarlig[$x]=htmlspecialchars($r['navn']);
		$x++;
	}
	
print "<div class=\"row\"><div class=\"left\">".findtekst(386,$sprog_id)."<!--tekst 386--></div>\n";
print "<div class=\"right\"><select style=\"width:194px;\" name=\"kontoansvarlig\" onchange=\"javascript:docChange = true;\">\n"; 
	for ($x=0;$x<count($kontoansvarlig_id);$x++) {
		if ($kontoansvarlig==$kontoansvarlig_id[$x]) print "<option value=\"$kontoansvarlig_id[$x]\">$ansvarlig[$x]&nbsp;</option>\n"; 
	}
	if (!$kontoansvarlig) print "<option value=\"0\">&nbsp;</option>\n";
	for ($x=0;$x<count($kontoansvarlig_id);$x++) {
		if ($kontoansvarlig!=$kontoansvarlig_id[$x]) print "<option value=\"$kontoansvarlig_id[$x]\">$ansvarlig[$x]&nbsp;</option>\n"; 
	}
	if ($kontoansvarlig) print "<option value=\"0\">&nbsp;</option>\n";
print "</select></div><div class=\"clear\"></div></div><!-- end of row -->\n";
##################### STATUS ##################### 
for ($x=0;$x<$status_antal;$x++) {
	print "<input type=\"hidden\" name=\"status_id[$x]\" value=\"$status_id[$x]\">";
	print "<input type=\"hidden\" name=\"status_beskrivelse[$x]\" value=\"$status_beskrivelse[$x]\">";
}
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
if ($new_status) {
 print "<div class=\"row\"><div class=\"left\" title=\"".findtekst(497,$sprog_id)."\"><!--tekst 497-->".findtekst(494,$sprog_id)."<!--tekst 494--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"ny_status\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
} else {
	print "<div class=\"row\"><div class=\"left\" title='".findtekst(496,$sprog_id)."'><!--tekst 496-->".findtekst(494,$sprog_id)."<!--tekst 494--></div>\n";
	print "<div class=\"right\"><select style=\"width:194px;\" name=\"status\" onchange=\"javascript:docChange = true;\">\n";
	if (!$status) print "<option>&nbsp;</option>\n";
	for ($x=0;$x<$status_antal;$x++) {
		if ($status==$status_id[$x]) print "<option value=\"$status_id[$x]\">$status_beskrivelse[$x]&nbsp;</option>\n";
	}
	for ($x=0;$x<$status_antal;$x++) {
		if ($status!=$status_id[$x]) print "<option value=\"$status_id[$x]\">$status_beskrivelse[$x]&nbsp;</option>\n";
	}
	if ($status) print "<option>&nbsp;</option>\n";
	print "<option value=\"new_status\">".findtekst(495,$sprog_id)."<!--tekst 495--></option>\n";
	print "</select></div><div class=\"clear\"></div></div><!-- end of row -->\n";
}
##################### LUKKET ##################### 
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<div class=\"row\"><div class=\"left\">".findtekst(387,$sprog_id)."<!--tekst 387--></div><div class=\"right\"><input class=\"textSpaceSmall\" type=\"checkbox\" name=\"lukket\" $lukket></div><div class=\"clear\"></div></div><!-- end of row -->\n";
print "</div><!-- end of contentA -->\n";
print "</div><!-- end of right container -->\n";
?>
