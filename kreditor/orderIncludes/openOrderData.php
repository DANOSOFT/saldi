<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/creditorIncludes/openOrderData.php --- lap 5.0.0 --- 2025.02.17 ---
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
// ----------------------------------------------------------------------
// 20221106 PHR - Various changes to fit php8 / MySQLi
// 20221104 MLH added lookup function for the delivery address fields
// 20221104 MLH added Rekv.nr. field
// 20221104 MLH added email and udskriv_til
// 20230105 MLH added mail_text and mail_subj
// 20250415 LOE Some variables initialized and others checked before using.
// 20250503 LOE reordered mix-up text_id from tekster.csv in findtekst()
// 20260217 PHR Added 'kundeordrnr'
/*
$attachId    = null;
$email       = null;
$kundeordnr  = null;
$projekt[0]  = null;
$udskriv_til = null;
*/
include '../includes/topline_settings.php';
print "<!-- BEGIN orderIncludes/openOrderData.php -->";
/*
$attachId    = if_isset($attachId,NULL);
$email       = if_isset($email,NULL);
$kundeordnr  = if_isset($kundeordnr,NULL);
$projekt[0]  = if_isset($projekt[0],NULL);
$ref         = if_isset($ref,NULL);
$udskriv_til = if_isset($udskriv_til,'PDF');
*/
print "<tr><td><table cellpadding='0' cellspacing='0' border='0'style='width:355px' >";
print "<tr><td style='width:100px'>".findtekst(276,$sprog_id)."</td><td style='width:250px'>";
if (trim($kontonr)) {
	print "<input class='inputbox' readonly=readonly style='width:250px' name='kontonr' onfocus='document.forms[0].fokus.value=this.name;' value='$kontonr'></td></tr>\n";
} else {
	print "<input class='inputbox' type='text' style='width:250px' name='kontonr' onfocus='document.forms[0].fokus.value=this.name;' ";
	print "value='$kontonr' onchange='javascript:docChange = true;'></td></tr>\n";
}
print "<tr><td>".findtekst(360,$sprog_id)."</td><td colspan='2'><input class='inputbox' type='text' style='width:250px' name='firmanavn' ";
print "onfocus='document.forms[0].fokus.value=this.name;' value='$firmanavn' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td>".findtekst(648,$sprog_id)."</td><td colspan='2'><input class='inputbox' type='text' style='width:250px' name='addr1' ";
print "onfocus='document.forms[0].fokus.value=this.name;' value='$addr1' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td></td><td colspan='2'><input class='inputbox' type='text' style='width:250px' name='addr2' ";
print "onfocus='document.forms[0].fokus.value=this.name;' value='$addr2' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td>".findtekst(549,$sprog_id)."</td>";
print "<td><input class='inputbox' type='text' style='width:50px' name='postnr' ";
print "onfocus='document.forms[0].fokus.value=this.name;' value='$postnr' onchange='javascript:docChange = true;'>";
print "<input class='inputbox' type='text' style='width:200px' name=bynavn onfocus='document.forms[0].fokus.value=this.name;' value='$bynavn' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td>".findtekst(364,$sprog_id)."</td><td colspan='2'><input class='inputbox' type='text' style='width:250px' name='land' ";
print "value='$land' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td>Att.:</td><td colspan='2'><input class='inputbox' type='text' style='width:250px' name='kontakt' ";
print "onfocus='document.forms[0].fokus.value=this.name;' value='$kontakt' onchange='javascript:docChange = true;'></td></tr>\n";
print "</tbody></table></td>";
print "<td><table cellpadding='0' cellspacing='0' border='0'>";
if (!$id) {
	print "<tr><td colspan='4' width='100%' align='center' valign='top'><span title='".findtekst(1507, $sprog_id)."'>";
	print "<a href=ublimport.php>".findtekst(943,$sprog_id)."</a></span></td></tr>";
	print "<tr><td colspan='4' width='100%'><hr width='90%'></td></tr>";
	$id = 0;
}
#20221104 BEGIN
print "<tr><td>".findtekst(402,$sprog_id)."</td>";
print "<td><input class='inputbox' type='text' style='width:110px;' name='email' value='$email' onchange='javascript:docChange = true;'></td>";
print "<td>".findtekst(2306,$sprog_id)."</td><td><select class='inputbox' style='width:130px' name='udskriv_til' onchange='javascript:docChange = true;'>\n";  #onchange='this.form.submit()'
print "<option value='PDF' ".((!$email || $udskriv_til=="PDF")?"selected='selected'":"").">PDF</option>\n";
print "<option value='email' ".(($email && $udskriv_til=="email")?"selected='selected'":"")." title=\"".findtekst(1450, $sprog_id)."\">".findtekst(652, $sprog_id)."</option>\n";
print "</SELECT></td></tr>\n";
#20221104 END

print "<tr><td>CVR-nr.</td>";
print "<td><input class='inputbox' type='text' style='width:110px;' name=cvrnr value='$cvrnr' onchange='javascript:docChange = true;'></td>";
$dkmomssats=dkdecimal($momssats,2);
print "<td>".findtekst(770,$sprog_id)."</td><td><input class='inputbox' type='text' style='text-align:right;width:110px;' name='momssats' value='$dkmomssats' ";
print "onchange='javascript:docChange = true;'>%</td></tr>\n";
print "<tr><td>".findtekst(881,$sprog_id)."</td><td><input class='inputbox' type='text' style='text-align:right;width:110px;' name='ordredato' value='$ordredato' ";
print "onchange='javascript:docChange = true;'></td>";
print "<td>".findtekst(886,$sprog_id)."</td><td><input class='inputbox' type='text' type='text' style='text-align:right;width:110px;' name='levdato' value='$levdato' ";
print "onchange='javascript:docChange = true;'></td></tr>\n";
$list=array();
$beskriv=array();
$list[0]='DKK';
$x=0;
$q = db_select("select * from grupper where art = 'VK' order by box1 ",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)){
	$x++;
	$list[$x]=$r['box1'];
	$beskriv[$x]=$r['beskrivelse'];
}
$tmp=$x;
if ($x>0) {
	$list[0]='DKK';
	$beskriv[0]='Danske kroner';
	print "<tr><td>Valuta</td>";
	print "<td><input class='inputbox' style='width:110px;' name='valuta' value='$valuta' onfocus='document.forms[0].fokus.value=this.name;'></td>";
} else print "<tr><td width=200></tr>";
$list=array();
$beskriv=array();
$x=0;
$q = db_select("select * from grupper where art = 'PRJ' order by kodenr",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)){
	$x++;
	$list[$x]=$r['kodenr'];
	$beskriv[$x]=$r['beskrivelse'];
}
$prj_antal=$x;
if ($x>0) {
	$vis_projekt='1';
	print "<td><span title= '".findtekst(950, $sprog_id)."';>".findtekst(553, $sprog_id)."</span></td>";
	print "<td><input class='inputbox' style='width:110px;' name='projekt[0]' value='{$projekt[0]}' onfocus='document.forms[0].fokus.value=this.name;'></td></tr>";
} else print "<tr><td</tr>";

print "<tr><td>".findtekst(935,$sprog_id)."</td>";
print "<td colspan='1'><select style='text-align:right;width:75px;' class='inputbox' name=betalingsbet>";
print "<option>$betalingsbet</option>";
if ($betalingsbet!='Forud') print "<option>".findtekst(369,$sprog_id)."</option>";
if ($betalingsbet!='Kontant') print "<option>".findtekst(370,$sprog_id)."</option>";
if ($betalingsbet!='Efterkrav') print "<option>".findtekst(371,$sprog_id)."</option>";
if ($betalingsbet!='Netto') print "<option>".findtekst(372,$sprog_id)."</option>";
if ($betalingsbet!='Lb. md.') print "<option>Lb. md.</option>";
if (($betalingsbet=='Kontant')||($betalingsbet=='Efterkrav')||($betalingsbet=='Forud')) $betalingsdage='';
	elseif (!$betalingsdage) $betalingsdage='Nul';
if ($betalingsdage) {
	if ($betalingsdage=='Nul') $betalingsdage=0;
	print "</SELECT>&nbsp;+<input class='inputbox' type='text' style='text-align:right;width:35px;' name='betalingsdage' value='$betalingsdage' "; 
	print "onchange='javascript:docChange = true;'></td>";
}
print "<td>";
if ($id) {
	$txt671 = findtekst('671|Bilag', $sprog_id);
	print "<b>$txt671</b></td><td align = 'center'>";
	// if (count($attachId) >= 1) {
	// 	print "<select name = 'showAttachment' id = 'showAttachment'>";
	// 	for ($x=0;$x<count($attachId);$x++) {
	// 		print "<option value = '$attachName[$x]'>substr($attachName[$x],0,25)</option>";
	// 	}	

	if (is_array($attachId) && count($attachId) >= 1) {
        print "<select name = 'showAttachment' id = 'showAttachment'>";
        for ($x = 0; $x < count($attachId); $x++) {
            $shortName = substr($attachName[$x], 0, 25);
            print "<option value = '$attachName[$x]'>$shortName</option>";
        }

		print "</select><br>";
		print "<script type='text/javascript'>
			var urlmenu = document.getElementById( 'menu1' )
			urlmenu.onchange = function() { 
				window.open( this.options[ this.selectedIndex ].value, '_self');
			};
		</script>";
	}
	$qtxt = "select id from documents where source = 'creditorOrder' and source_id = '$id'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $clip = 'paper.png';
	else $clip = 'clip.png';
	print "<a href='../includes/documents.php?source=creditorOrder&&ny=ja&sourceId=$id'>";
	print "<img src='../ikoner/$clip' style='width:20px;height:20px;'></a>";
}
print "</td></tr>";
if (!$ref) {
	$qtxt = "select ansat_id from brugere where brugernavn = '$brugernavn'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)) && if_isset($r,NULL,'ansat_id')) {
		$r = db_fetch_array(db_select("select navn from ansatte where id = $r[ansat_id]",__FILE__ . " linje " . __LINE__));
		if ($r['navn']) $ref=$r['navn'];
	}
}
	$txt1097 = findtekst('1097|Vor ref.', $sprog_id);
	print "<tr><td>$txt1097</td>";
	print "<td colspan=3><input class='inputbox' style='width:110px;' name='ref' value='$ref' onfocus='document.forms[0].fokus.value=this.name;'></td></tr>";
if (count($lager_nr)) {
	print "<tr><td>Lager</td>";
	print "<td colspan='1'><select style='text-align:right;width:110px;' class='inputbox' name='lager'>";
	for ($x=0;$x<count($lager_nr);$x++) {
if ($lager==$lager_nr[$x]) print "<option value='$lager_nr[$x]'>$lager_nr[$x]: $lager_navn[$x]</option>";
	}
	for ($x=0;$x<count($lager_nr);$x++) {
if ($lager!=$lager_nr[$x]) print "<option value='$lager_nr[$x]'>$lager_nr[$x]: $lager_navn[$x]</option>";
	}
	print "</td></tr>";
}

if ($status==0){print "<tr><td>".findtekst('555|Godkend', $sprog_id)."</td><td><input class='inputbox' type=checkbox name=godkend></td></tr>\n";}
#elseif ($status==1) {
#	$query = db_select("select * from batch_kob where ordre_id=$id",__FILE__ . " linje " . __LINE__);
#	if(db_fetch_array($query)){print "<tr><td>Dan lev. fakt.</td><td><input class='inputbox' type=checkbox name=godkend></td></tr>\n";}
#	else {
#$query = db_select("select * from batch_salg where ordre_id=$id",__FILE__ . " linje " . __LINE__);
#if(db_fetch_array($query)){print "<tr><td>Dan lev. fakt.</td><td><input class='inputbox' type=checkbox name=godkend></td></tr>\n";}
#	}
#}
#elseif ($status==1){print "<tr><td>Modtag</td><td><input class='inputbox' type=checkbox name=modtag></td></tr>\n";}
else {
	print "<tr><td width=200>".findtekst('828|Fakturanr.', $sprog_id)."</td><td colspan='2'><input class='inputbox' type='text' size=23 name=fakturanr value='$fakturanr' onchange='javascript:docChange = true;'></td></tr>\n";
}
print "</tbody></table></td>";
print "<td align=center><table border = '0' cellpadding= '0' cellspacing= '0'>";
print "<tr><td colspan='2' ><button type='button' class='button gray medium' style='width:100%; cursor: pointer; $buttonStyle' onclick=\"window.location.href='orderIncludes/dropshipping.php?id=$id'\">".findtekst('2954|Leveringsadresse opslag', $sprog_id)."</button></td></tr>\n";
print "<tr><td colspan='2' align=center><hr></td></tr>\n";
print "<tr><td>".findtekst('360|Firmanavn', $sprog_id)."</td><td colspan='2'><input class='inputbox' type='text' style='width:250px' name=lev_navn value='$lev_navn' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td>".findtekst('648|Adresse', $sprog_id)."</td><td colspan='2'><input class='inputbox' type='text' style='width:250px' name=lev_addr1 value='$lev_addr1' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td></td><td colspan='2'><input class='inputbox' type='text' style='width:250px' name=lev_addr2 value='$lev_addr2' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td>".findtekst('549|Postnr, by', $sprog_id)."</td><td><input class='inputbox' type='text' size=4 name=lev_postnr value='$lev_postnr' onchange='javascript:docChange = true;'>&nbsp;<input class='inputbox' type='text' size=16 name=lev_bynavn value='$lev_bynavn' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td>Att.:</td><td colspan='2'><input class='inputbox' type='text' style='width:250px' name=lev_kontakt value='$lev_kontakt' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td>".findtekst('2129|Rekv. nr.', $sprog_id).":</td><td colspan='2'><input class='inputbox' type='text' style='width:250px' name='kundeordnr' value='$kundeordnr' onchange='javascript:docChange = true;'></td></tr>\n"; #20221104
print "</td></tr></tbody></table></td>";
print "</td></tr>";



#20230105 BEGIN
if ($udskriv_til=='email') {
	if (!isset($formularsprog)) $formularsprog='Dansk';

	if ($status<=1) $form_nr=12;
	if ($status==2) $form_nr=13;
	if ($status>2) $form_nr=14;

	$std_subj = $std_txt = $std_txt_title = '';
	
	$q = db_select("select * from formularer where formular='$form_nr' and art='5' and lower(sprog)='".strtolower($formularsprog)."'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['xa']=='1') $std_subj=$r['beskrivelse'];
		elseif ($r['xa']=='2') $std_txt_title=$r['beskrivelse'];
		else {
			if (strpos($std_txt_title,'<br>')) list($std_txt,$tmp)=explode("<br>",$std_txt_title);
			else $std_txt=$std_txt_title;
		}	
		($mail_text)?$std_txt_title=$mail_text:$std_txt_title=str_replace("<br>","",$std_txt_title);
	}
	print "<tr><td align=\"center\" colspan=\"3\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" width=\"100%\"><tbody>\n"; #Tabel 2.4 ->
	print "<tr><td width=\"120px\">Mail emne</td><td><input class=\"inputbox\" type=\"text\" style=\"width:1000px;\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"mail_subj\" placeholder=\"$std_subj\" value=\"$mail_subj\" onchange=\"javascript:docChange = true;\"></td>";
	print "</tr><tr><td valign=\"top\">".findtekst('585|Mail tekst', $sprog_id)."</td><td title=\"$std_txt_title\">";
	if ($mail_text) {
		print "<textarea style=\"width:1000px;\" rows=\"2\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"mail_text\" onchange=\"javascript:docChange = true;\">$mail_text</textarea>\n";
	} else {
		print "<input class=\"inputbox\" type=\"text\" style=\"width:1000px;\" onfocus=\"document.forms[0].fokus.value=this.name;\"name=\"mail_text\" placeholder=\"$std_txt\" value=\"$mail_text\" onchange=\"javascript:docChange = true;\">";
	}
	print "</td></tr></tbody></table></td></tr>\n"; # <- Tabel 2.4
}
#20230105 END
print "<tr><td align=center colspan=3><table cellpadding='1' cellspacing='0' width='100%' border = '0'><tbody>";
print "<tr>";

if ($status==1) {
	print "<td align=center title='".findtekst('1502|Position (ordrelinjenummer)', $sprog_id)."'>Pos.</td><td align=center title='".findtekst(320, $sprog_id)."'>".findtekst(917, $sprog_id).".</td><td align=center title='".findtekst(1511, $sprog_id)."'>".findtekst(952, $sprog_id).".</td><td align=center>".findtekst(916, $sprog_id)."</td><td align=center>".findtekst(945, $sprog_id)."</td><td align=center>".findtekst(914,$sprog_id)."</td><td align=center>".findtekst(915, $sprog_id)."</td><td align=center title='".findtekst(1503, $sprog_id)."'>%</td><td align=center>".findtekst(947, $sprog_id)."</td>";
	if ($vis_projekt && !$projekt[0]) print "<td align=center title='".findtekst(1509, $sprog_id)."'>Proj.</td>";
	if ($art=='KK') print "<td colspan='2' align='center' title='".findtekst(1508, $sprog_id)."'>".findtekst(937, $sprog_id)."</td>";
	elseif ($submit == 'split') {
		print "<td colspan='2' align='center' title='".findtekst(200, $sprog_id)."'>".findtekst(2009, $sprog_id)."</td>";
	} else print "<td colspan='2' align='center' title='".findtekst(1510, $sprog_id)."'>".findtekst(1485, $sprog_id)."</td>";
}
else {
	print "<td align=center title='".findtekst(1502, $sprog_id)."'>Pos.</td><td align=center title='".findtekst(320, $sprog_id)."'>".findtekst(917, $sprog_id).".</td><td align=center title='".findtekst(1511, $sprog_id)."'>".findtekst(952, $sprog_id).".</td><td align=center>".findtekst(916, $sprog_id)."</td><td>".findtekst(945, $sprog_id)."</td><td align=center>".findtekst(914,$sprog_id)."</td><td align=center>".findtekst(915, $sprog_id)."</td><td align=center title='".findtekst(1503, $sprog_id)."'>%</td><td align=center>".findtekst(947, $sprog_id)."</td>";
	if ($vis_projekt && !$projekt[0]) print "<td align=center title='".findtekst(1509, $sprog_id)."'>Proj.</td>";
	else print "<td></td>";
}
if ($omlev) print "<td title ='".findtekst(1512, $sprog_id)."'>O/B</td>";

print "</tr>\n";
print "<!-- END orderIncludes/openOrderData.php -->";

?>
	
