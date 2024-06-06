<?php
//$bg=$bgcolor5;
print "<div style=\"float:left; margin-right:70px; width:379px;\">\n";
print "<h3>Kunde information</h3>\n";
print "<div class=\"contentA\">\n";
print "<div class=\"row\"><div class=\"left\">Kundetype</div><div class=\"right\"><select style=\"width:100px;\" name=\"kontotype\" onchange=\"javascript:docChange = true;\">\n";
if ($kontotype=='privat') {
	print "<option value=privat>".findtekst(353,$sprog_id)."<!--tekst 353--></option>\n";
	print "<option value=erhverv>".findtekst(354,$sprog_id)."<!--tekst 354--></option>\n";
} else {	
	print "<option value=erhverv>".findtekst(354,$sprog_id)."<!--tekst 354--></option>\n";
	print "<option value=privat>".findtekst(353,$sprog_id)."<!--tekst 353--></option>\n";
}
print "</select></div><div class=\"clear\"></div></div><!-- end of row -->\n";
print "<div class=\"row\"><div class=\"left\">".findtekst(357,$sprog_id)."<!--tekst 357--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"ny_kontonr\" value=\"$kontonr\" onchange=\"javascript:docChange = true;\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if ($kontotype=='privat') {
	print "<input type=\"hidden\" name=\"firmanavn\" value=\"$firmanavn\">\n";
	//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<div class=\"row\"><div class=\"left\">".findtekst(358,$sprog_id)."<!--tekst 358--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"fornavn\" value=\"$fornavn\" onchange=\"javascript:docChange = true;\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<div class=\"row\"><div class=\"left\">".findtekst(359,$sprog_id)."<!--tekst 359--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"efternavn\" value=\"$efternavn\" onchange=\"javascript:docChange = true;\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
} else {
	//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<div class=\"row\"><div class=\"left\">".findtekst(360,$sprog_id)."<!--tekst 360--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"firmanavn\" value=\"$firmanavn\" onchange=\"javascript:docChange = true;\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
}
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<div class=\"row\"><div class=\"left\">".findtekst(361,$sprog_id)."<!--tekst 361--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"addr1\" value=\"$addr1\" onchange=\"javascript:docChange = true;\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<div class=\"row\"><div class=\"left\">".findtekst(362,$sprog_id)."<!--tekst 362--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"addr2\" value=\"$addr2\" onchange=\"javascript:docChange = true;\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<div class=\"row\"><div class=\"left\">".findtekst(666,$sprog_id)."<!--tekst 666--></div><div class=\"right\"><input class=\"textSmall textIndent\" type=\"text\"  name=\"postnr\" value=\"$postnr\" onchange=\"javascript:docChange = true;\"><input class=\"textMediumLarge textSpace textIndent\" type=\"text\" size=\"19\" name=\"bynavn\" value=\"$bynavn\" onchange=\"javascript:docChange = true;\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<div class=\"row\"><div class=\"left\">".findtekst(364,$sprog_id)."<!--tekst 364--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"land\" value=\"$land\" onchange=\"javascript:docChange = true;\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<div class=\"row\"><div class=\"left\">".findtekst(667,$sprog_id)."<!--tekst 667--></div><div class=\"right\"><input class=\"textMediumLargeX textIndent\" type=\"text\" name=\"email\" value=\"$email\" onchange=\"javascript:docChange = true;\">\n";
if ($email && $mailfakt) $mailfakt="checked";
if (!$email && $mailfakt) $mailfakt=NULL;
print "<input class=\"textSpaceSmall\" type=\"checkbox\" name=\"mailfakt\" title=\"".findtekst(366,$sprog_id)."\" $mailfakt></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if ($kontotype=='erhverv') {
	//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<div class=\"row\"><div class=\"left\">".findtekst(367,$sprog_id)."<!--tekst 367--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"web\" value=\"$web\" onchange=\"javascript:docChange = true;\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
}
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<div class=\"row\"><div class=\"left\">".findtekst(668,$sprog_id)."<!--tekst 668--></div><div class=\"right\"><select style=\"width:100px;\" name=\"betalingsbet\" onchange=\"javascript:docChange = true;\" >\n";
print "<option>$betalingsbet</option>\n";
if ($betalingsbet!='Forud') 	{print "<option value=\"Forud\">".findtekst(369,$sprog_id)."<!--tekst 369--></option>"; }
if ($betalingsbet!='Kontant') 	{print "<option value=\"Kontant\">".findtekst(370,$sprog_id)."<!--tekst 370--></option>"; }
if ($betalingsbet!='Efterkrav') 	{print "<option value=\"Efterkrav\">".findtekst(371,$sprog_id)."<!--tekst 371--></option>"; }
if ($betalingsbet!='Netto'){print "<option value=\"Netto\">".findtekst(372,$sprog_id)."<!--tekst 372--></option>"; }
if ($betalingsbet!='Lb. md.'){print "<option value=\"Lb. md.\">".findtekst(373,$sprog_id)."<!--tekst 373--></option>";}
if (($betalingsbet=='Kontant')||($betalingsbet=='Efterkrav')||($betalingsbet=='Forud')) {$betalingsdage='';}

elseif (!$betalingsdage) {$betalingsdage='Nul';}
if ($betalingsdage){
 	if ($betalingsdage=='Nul') {$betalingsdage=0;}
 	print "</select>&nbsp;+&nbsp;<input class=\"textXSmall\" type=\"text\" style=\"text-align:right;\" name=\"betalingsdage\" value=\"$betalingsdage\" onchange=\"javascript:docChange = true;\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
} else print "</select></div><div class=\"clear\"></div></div><!-- end of row -->\n";
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<div class=\"row\"><div class=\"left\">".findtekst(374,$sprog_id)."<!--tekst 374--></div>\n";
if (!$gruppe) {
	if (db_fetch_array(db_select("select id from grupper where art='DIV' and kodenr='2' and box1='on'",__FILE__ . " linje " . __LINE__))) $gruppe='0';
	else $gruppe=1;
}	
print "<div class=\"right\"><select style=\"width: 194px\" name=\"gruppe\" onchange=\"javascript:docChange = true;\">\n";
if ($gruppe) {	
	$r = db_fetch_array(db_select("select beskrivelse from grupper where art='DG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
	print "<option>$gruppe:$r[beskrivelse]</option>\n";
}
$q = db_select("select * from grupper where art='DG' and kodenr!='$gruppe' order by kodenr",__FILE__ . " linje " . __LINE__);

while ($r = db_fetch_array($q)){
 print "<option>$r[kodenr]:$r[beskrivelse]</option>\n";
}
print "</select></div><div class=\"clear\"></div></div><!-- end of row -->\n";
//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
//print "<tr bgcolor=$bg>";
$x=0;
$q = db_select("select * from grupper where art='DRG' order by box1",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)){
	$x++;
	$drg_nr[$x]=$r['kodenr'];
	$drg_navn[$x]=$r['box1'];
}
if ($drg=$x) {
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<div class=\"row\"><div class=\"left\">".findtekst(375,$sprog_id)."<!--tekst 375--></div><div class=\"right\"><select style=\"width: 194px\" name=\"rabatgruppe\" onchange=\"javascript:docChange = true;\">\n";
	for ($x=1;$x<=$drg;$x++) {
		if ($rabatgruppe==$drg_nr[$x]) print "<option value=\"$rabatgruppe\">$drg_navn[$x]</option>\n";
	}
	print "<option value=\"0\"></option>\n";
	for ($x=1;$x<=$drg;$x++) {
		if ($rabatgruppe!=$drg_nr[$x]) print "<option value=\"$drg_nr[$x]\">$drg_navn[$x]</option>\n";
	}
	print "</select></div><div class=\"clear\"></div></div><!-- end of row -->\n";
} //else print "<td colspan=\"2\"><br></td></tr>";
#print "<td><br></td>\n";
print "</div><!-- end of contentA -->\n";
print "</div><!-- end of left container -->\n";
?>