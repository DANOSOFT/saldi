<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/creditorIncludes/openOrders.php --- lap 4.1.0 --- 2024.06.26 ---
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
// Copyright (c) 2003-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 20240626 PHR Added 'fiscal_year' in queries

print "<table cellpadding='1' cellspacing='0' bordercolor='#ffffff' border='1' valign = 'top' width = '100'><tbody>";
print "<tr><td width=33%><table cellpadding='0' cellspacing='0' border='0' width='100'>";
print "<tr><td width=200>".findtekst(276,$sprog_id)."</td><td colspan='2'>";

if (trim($kontonr)) {
	print "<input class='inputbox' readonly=readonly size='25' name='kontonr' onfocus='document.forms[0].fokus.value=this.name;' value='$kontonr'></td></tr>\n";
} else {
	print "<input class='inputbox' type='text' size='25' name='kontonr' onfocus='document.forms[0].fokus.value=this.name;' ";
	print "value='$kontonr' onchange='javascript:docChange = true;'></td></tr>\n";
}
print "<tr><td>".findtekst(360,$sprog_id)."</td><td colspan='2'><input class='inputbox' type='text' size='25' name='firmanavn' ";
print "onfocus='document.forms[0].fokus.value=this.name;' value='$firmanavn' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td>".findtekst(648,$sprog_id)."</td><td colspan='2'><input class='inputbox' type='text' size='25' name='addr1' ";
print "onfocus='document.forms[0].fokus.value=this.name;' value='$addr1' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td></td><td colspan='2'><input class='inputbox' type='text' size='25' name='addr2' ";
print "onfocus='document.forms[0].fokus.value=this.name;' value='$addr2' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td>".findtekst(549,$sprog_id)."</td><td><input class='inputbox' type='text' size='4' name='postnr' ";
print "onfocus='document.forms[0].fokus.value=this.name;' value='$postnr' onchange='javascript:docChange = true;'></td><td><input class='inputbox' type='text' size=19 name=bynavn onfocus='document.forms[0].fokus.value=this.name;' value='$bynavn' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td>".findtekst(364,$sprog_id)."</td><td colspan='2'><input class='inputbox' type='text' size='25' name='land' ";
print "value='$land' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td>Att.:</td><td colspan='2'><input class='inputbox' type='text' size='25' name='kontakt' ";
print "onfocus='document.forms[0].fokus.value=this.name;' value='$kontakt' onchange='javascript:docChange = true;'></td></tr>\n";
print "</tbody></table></td>";
print "<td width='330px'><table cellpadding='0' cellspacing='0' border='0' width='100%'>";
if (!$id) {
	print "<tr><td colspan='4' width='100%' align='center' valign='top'><span title='".findtekst(1507, $sprog_id)."'>";
	print "<a href=ublimport.php>".findtekst(943,$sprog_id)."</a></span></td></tr>";
	print "<tr><td colspan='4' width='100%'><hr width='90%'></td></tr>";
}
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
	print "<td><select class='inputbox' name=valuta>";
	for ($x=0; $x<=$tmp; $x++) {
if ($valuta!=$list[$x]) print "<option title='$beskriv[$x]' onchange='javascript:docChange = true;'>$list[$x]</option>";
else print "<option title='$beskriv[$x]' selected='selected' onchange='javascript:docChange = true;'>$list[$x]</option>";
	}
	print "</SELECT></td>";
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
	print "<td><select class='inputbox' name=projekt[0]>";
	for ($x=0; $x<=$prj_antal; $x++) {
if ($projekt[0]!=$list[$x]) print "<option title='$beskriv[$x]' onchange='javascript:docChange = true;'>$list[$x]</option>";
else print "<option title='$beskriv[$x]' selected='selected' onchange='javascript:docChange = true;'>$list[$x]</option>";
	}
	print "</SELECT></td></tr>";
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
	print "</SELECT>&nbsp;+<input class='inputbox' type='text' style='text-align:right;width:25px;' name='betalingsdage' value='$betalingsdage' "; 
	print "onchange='javascript:docChange = true;'></td>";
}
print "<td><b>Bilag</b></td><td align = 'center'>";
if (count($attachId) >= 1) {
	print "<select name = 'showAttachment' id = 'showAttachment'>";
	for ($x=0;$x<count($attachId);$x++) {
	print "<option value = '$attachName[$x]'>substr($attachName[$x],0,25)</option>";
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
print "<img src='../ikoner/$clip' style='width:20px;height:20px;'></a></td>";
print "</tr>";
if (!$ref) {
	$row = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
	if ($row['ansat_id']) {
$row = db_fetch_array(db_select("select navn from ansatte where id = $row[ansat_id]",__FILE__ . " linje " . __LINE__));
if ($row['navn']) {$ref=$row['navn'];}
	}
}
$q = db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__);
if ($r = db_fetch_array($q)) {
	$q2 = db_select("select navn,afd from ansatte where konto_id = '$r[id]' and lukket != 'on' order by navn",__FILE__ . " linje " . __LINE__);
	$x=0;
	while ($r2 = db_fetch_array($q2)) {
$x++;
if ($x==1) {
	print "<tr><td>Vor ref.</td>";
	print "<td colspan=3><select style='text-align:right;width:110px;' class='inputbox' name=ref>";
	if ($ref) print "<option>$ref</option>";
}
if ($ref!=$r2['navn']) print "<option> $r2[navn]</option>";
	}
	print "</SELECT>";
	if ($x) print "</td></tr>";
if (count($afd_nr)) {
	print "<tr><td>Afd.</td>";
	print "<td colspan='1'><select style='text-align:right;width:110px;' class='inputbox' name='afd'>";
	for ($x=0;$x<count($afd_nr);$x++) {
if ($afd==$afd_nr[$x]) print "<option value='$afd_nr[$x]'>$afd_nr[$x]: $afd_navn[$x]</option>";
	}
	for ($x=0;$x<count($afd_nr);$x++) {
if ($afd!=$afd_nr[$x]) print "<option value='$afd_nr[$x]'>$afd_nr[$x]: $afd_navn[$x]</option>";
	}
	print "</td></tr>";
}
}

if ($status==0){print "<tr><td>".findtekst(555,$sprog_id)."</td><td><input class='inputbox' type=checkbox name=godkend></td></tr>\n";}
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
	print "<tr><td width=200>".findtekst(828,$sprog_id)."</td><td colspan='2'><input class='inputbox' type='text' size=23 name=fakturanr value='$fakturanr' onchange='javascript:docChange = true;'></td></tr>\n";
}
print "</tbody></table></td>";
print "<td align=center width=33%><table border = '0' cellpadding= '0' cellspacing= '0' width='*'>";
print "<tr><tdcolspan='2' >".findtekst(554,$sprog_id)."</td></tr>\n";
print "<tr><td colspan='2' align=center><hr></td></tr>\n";
print "<tr><td>".findtekst(360,$sprog_id)."</td><td colspan='2'><input class='inputbox' type='text' size='25' name=lev_navn value='$lev_navn' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td>".findtekst(648,$sprog_id)."</td><td colspan='2'><input class='inputbox' type='text' size='25' name=lev_addr1 value='$lev_addr1' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td></td><td colspan='2'><input class='inputbox' type='text' size='25' name=lev_addr2 value='$lev_addr2' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td>".findtekst(549,$sprog_id)."</td><td><input class='inputbox' type='text' size=4 name=lev_postnr value='$lev_postnr' onchange='javascript:docChange = true;'><input class='inputbox' type='text' size=19 name=lev_bynavn value='$lev_bynavn' onchange='javascript:docChange = true;'></td></tr>\n";
print "<tr><td>Att.:</td><td colspan='2'><input class='inputbox' type='text' size='25' name=lev_kontakt value='$lev_kontakt' onchange='javascript:docChange = true;'></td></tr>\n";
	#print "<tr><td><textarea style='font-family: helvetica,arial,sans-serif;' name=lev_adr rows=5 cols=35>$lev_adr</textarea></td></tr>\n";
print "</td></tr></tbody></table></td>";
print "</td></tr><tr><td align=center colspan=3><table cellpadding='1' cellspacing='0' width='100%' border = '1'><tbody>";
print "<tr>";
if ($status==1) {
	print "<td align=center title='".findtekst(1502, $sprog_id)."'>Pos.</td><td align=center title='".findtekst(320, $sprog_id)."'>".findtekst(917, $sprog_id).".</td><td align=center title='".findtekst(1511, $sprog_id)."'>".findtekst(952, $sprog_id).".</td><td align=center>".findtekst(916, $sprog_id)."</td><td align=center>".findtekst(945, $sprog_id)."</td><td align=center>".findtekst(914,$sprog_id)."</td><td align=center>".findtekst(915, $sprog_id)."</td><td align=center title='".findtekst(1503, $sprog_id)."'>%</td><td align=center>".findtekst(947, $sprog_id)."</td>";
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
#cho "OL $omlev<br>";
if ($omlev) print "<td title ='".findtekst(1512, $sprog_id)."'>O/B</td>";

print "</tr>\n";
/*
if ($valuta && $valuta!='DKK') {
	if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
$valutakurs=$r['kurs'];
	} else {
$tmp = dkdato($ordredate);
print "<BODY onLoad='javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')' onchange='javascript:docChange = true;'>";
	}
} else $valutakurs = 100;
db_modify("update ordrer set valutakurs='$valutakurs' where ordre_id = '$ordre_id'",__FILE__ . " linje " . __LINE__);
*/
#$ordre_id*=1;
$x=0;
$qtxt = "select * from ordrelinjer where ordre_id = '$id' order by posnr";
$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query))	{
	if ($row['posnr']>0) {
$x++;
$linje_id[$x]=$row['id'];
$kred_linje_id[$x]=$row['kred_linje_id'];
$posnr[$x]=$row['posnr'];
$varenr[$x]=trim($row['varenr']);
$lev_varenr[$x]=trim($row['lev_varenr']);
$beskrivelse[$x]=trim($row['beskrivelse']);
$pris[$x]=$row['pris'];
$rabat[$x]=$row['rabat'];
$antal[$x]=$row['antal'];
$leveres[$x]=$row['leveres'];
$enhed[$x]=$row['enhed'];
$vare_id[$x]=$row['vare_id'];
$momsfri[$x]=$row['momsfri'];
$projekt[$x]=$row['projekt'];
$serienr[$x]=$row['serienr'];
$samlevare[$x]=$row['samlevare'];
($row['omvbet'])?$omvbet[$x]='checked':$omvbet[$x]='';
	}
}
$linjeantal=$x;
print "<input type='hidden' name='linjeantal' value='$linjeantal'>";
$sum=0;
#if ($status==1){$status=2;}
if ($submit == 'split') include('orderIncludes/splitOrder.php');
else include('orderIncludes/openOrderLines.php'); 
?>
	
