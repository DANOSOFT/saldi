<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/sys_div_func.php --- ver 4.1.0 -- 2024.01.18 ---
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
// Copyright (c) 2003-2024 Saldi.DK ApS
// -----------------------------------------------------------------------
// Kaldes fra systemdata/diverse.php
// 2013.11.01 Tilføjet fravalg af tjek for forskellige datoer på samme bilag i kasseklasse. Søg 20131101
// 2013.12.10	Tilføjet valg om kort er betalingskort som aktiver betalingsterminal. Søg 21031210
// 2013.12.13	Tilføjet "intern" bilagsopbevaring (box6 under ftp)
// 2014.01.29	Tilføjet valg til automatisk genkendelse af betalingskort (kun ved integreret betalingsterminal) Søg 20140129
// 2014.05.08	Tilføjet valg til bordhåndtering under pos_valg Søg 20140508
// 2014.06.16 Tilføjet mellemkonto til pos kasser. Søg mellemkonto.
// 2014.07.01	FTP ændret til bilag og intern bilagsopbevaring flyttet til owncloud
// 2015.01.05 I sqlquery_io er separator ændret fra <tab> til ; tekster utf8 decodes og der sættes " om.
// 2015.04.11 Tilføjet labelprint under vare_valg.
// 20150417 CA  Topmenudesign tilføjet for Prisliste               søg 20150417
// 20150522 CA  Oprydning i HTML-kode især input - omfattende så ingen søgning
// 20150529 CA  Håndtering af forskellige typer prislister         søg 20150529
// 20150608 PHR Tilføjet link til ../api/hent_varer.php            søg 20150608
// 20150612 CA  Slette prislister                                  søg 20150612
// 20150625 CA  Tilpasning til topmenu
// 20150814 CA  Link til opsætning af prisliste                    søg 20150815
// 20150907 PHR Sætpriser tilføjet under ordre_valg, Søg $saetvareid
// 20151002	PHR	Fjernet mulighed for at trække en brugerliste.
// 20151005	PHR	Labelprint fungerer kun hvis variablen labelprint er sat. (Midlertidig løsning)
// 20151006 PHR Labelprint ændret fra php til html.
// 20160116 PHR Ændret 'bilag' så inputfelter kun vises ved 'egen ftp'
// 20160226 CA  Tilføjet valg af leverandører under prislister.    søg 20160226
// 20160412 PHR Opdelt vare_valg i vare_valg, labels & shop_valg
// 20160601	PHR SMTP kan nu anvendes med brugernavn, adgangskode og kryptering.
// 20161118	PHR	Tilføjet default bord som option for kasse i funktion pos_valg. Søg bordvalg
// 20161125 PHR Indført html som formulargenerator som alternativ til postscript i funktion div_. Søg pv_box3
// 20170123 PHR Tilføjet API_valg
// 20170314 PHR POS Valg - tilføjet mulighed for at sætte 'udtages fra kasse' til 0 som default.
// 20170329 PHR ordre_valg - tilføjet gennemsnitspris til opdat_kostpris
// 20170404 PHR ordre_valg - Straksbogfør skelner nu mellem debitor og kreditorordrer. Dvs debitor;kreditor - Søg # 20170404
// 20170731 PHR Tilføjet 'Nulstil regnskab under kontoindstillinger - 20170731
// 20181029 CA  Tilføjet gavekort og tilgodehavende tilknyttet id  søg 20181029
// 20181126 PHR	Tilvalg - Marker vare som udgået når beholdning går i minus (vare_valg). Søg DisItemIfNeg
// 20181129 PHR	Tilføjet mulighed for at sætte tidszone i regnskabet. Søg DisItemIfNeg
// 20181216 PHR	Tilføjet 'card_enabled' på betalingskort (Pos_valg) og mulighed for ændring af rækkefølge. Søg '$card_enabled'
// 20190107 PHR	Tilføjet 'change_cardvalue' på betalingskort (Pos_valg) og mulighed for ændring af rækkefølge. Søg '$change_cardvalue'
// 20190129 PHR	(vare_valg) Changed 'Momskode for salgspriser på varekort' to 'Vis priser med moms på varekort'. Search '$vatOnItemCard'
// 20190225 MSC - Rettet topmenu design til
// 20190411 LN Set new field, which sets the default value for provision
// 20190421 PHR - Added confirmDescriptionChange, in 'vare_valg'
// 20190614 LN Added argument to chooseProvisionForProductGroup -> $defaultProvision
// 20200316 PHR Function sqlquery_io. Fixed save & delete sql query
// 20200515 PHR	Function 'div_valg' Added 'mySale'
// 20210112 LOE included language file to sprog fuction
// 20210224 LOE An if Fuction added to check if a language is set and available in settings table 
// 20200515 PHR Function 'div_valg' Added 'mySale'
// 20201128 PHR Function 'labels' Added 'labelType'
// 20210110 PHR Function Vare_valg. Added commission. 
// 20210213 PHR Some cleanup
// 20210302 CA  Added reservation of consignment for Danske Fragtmænd - search dfm_
// 20210303 LOE updated engdan function applied here
// 20210305 CA  Added the selection to use debtor number as phone number in orders - search debtor2orderphone
// 20210710 LOE Added some translation for texts on kontoindstillinger diverse section
// 20210711 LOE - Translated some texts for provision function
// 20210712 LOE - Some more translation for vare_valg , Prislister and labels function and also added if empty to correct undefined variable bug.
// 20210713 LOE - More translation  for bilag(), kontoplan_io() rykker_valg functions
// 20210801 CA  Added the selection to use order notes in ordre_valg - search orderNoteEnabled
// 20210802 LOE Translated the remaining title and alert texts
// 20211019 LOE Some bugs fixed
// 20211022 LOE Fixed some bugs
// 20211123 PHR added paperflow
// 20211123 PHR added paperflowId & paperflowBearer
// 20220413 PHR Renamed pos_valg til posOptions and moved function to diverse/posOptions.php
// 20231228 PBLM Added mobilePay (diverse valg)
// 20240130 PBLM Added Nemhandel (diverse valg)
	include("sys_div_func_includes/chooseProvision.php");

ini_set('display_errors','0');

function kontoindstillinger($regnskab,$skiftnavn) {
	global $bgcolor,$bgcolor5,$sprog_id,$timezone;
#	if (isset($_COOKIE['timezone'])) $timezone=$_COOKIE['timezone'];
#	else {
	$qtxt = "select id,var_value from settings where var_name='timezone'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)) && (isset($r['var_value']))) {
		$timezone=$r['var_value'];
		if ($timezone) {
			date_default_timezone_set($timezone);
			setcookie("timezone",$timezone,time()+60*60*24*30,'/');
		}
	}
	print "<tr><td colspan='6'><hr></td></tr>\n";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(783,$sprog_id)."</u></b></td></tr>\n";
	print "<tr><td colspan='6'><br></td></tr>\n";
	if (!$skiftnavn) {
		$klik = findtekst(149, $sprog_id); $klik1= explode(" ", $klik);  #20210710
		print "<tr><td colspan='6'>".findtekst(1237,$sprog_id)." <span style='font-weight:bold'>$regnskab</span>. ";
		print "$klik1[0] <a href='diverse.php?sektion=kontoindstillinger&amp;skiftnavn=ja'>her</a> ".findtekst(1238,$sprog_id)."</td></tr>\n";
		print "<tr><td colspan='6'><hr></td></tr>\n";
		$tmp=date('U')-60*60*24*365;
		$tmp=date("Y-m-d",$tmp);
		$r=db_fetch_array(db_select("select count(id) as transantal from transaktioner where logdate>='$tmp'",__FILE__ . " linje " . __LINE__));
		$transantal=$r['transantal']*1;
		print "<tr><td>".findtekst(1233,$sprog_id)." $transantal ".findtekst(1234,$sprog_id)."</td></tr>";
		$r=db_fetch_array(db_select("select felt_1,felt_2,felt_3,felt_4 from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
		print "<tr><td colspan='6'><hr></td></tr>\n";
		print "<form name='timezone' action='diverse.php?sektion=kontoindstillinger' method='post'>\n";
		$title=findtekst(1235,$sprog_id);
		$text=findtekst(1236,$sprog_id);
		print "<tr><td title='$title'><!--tekst 434-->$text<!--tekst 435--></td>";
		print "<td title='$title'><select class='inputbox' style='width:200px' name='timezone'>";
		$tz=fopen("../importfiler/timezones.csv","r");
		$x=0;
		while($line=trim(fgets($tz))) {
			list($a,$b[$x],$c[$x])=explode(",",$line);
			$b[$x]=trim($b[$x],'"');$c[$x]=trim($c[$x],'"');
			$x++;
		}
		for ($x=0;$x<count($c);$x++) {
			if ($timezone==$c[$x]) print "<option value='$c[$x]'>$b[$x] $c[$x]</option>";
		}
		for ($x=0;$x<count($c);$x++) {
			if ($timezone!=$c[$x]) print "<option value='$c[$x]'>$b[$x] $c[$x]</option>";
		}
		print "</select></td></tr>";
		$text= findtekst(1091,$sprog_id)." ".findtekst(1236,$sprog_id); 
		print "<td></td><td><input class='button gray medium' style='width:200px' type='submit' value='$text' name='opdat_tidszone'><!--tekst 436--></td></tr>\n";
		print "</form>";
		print "<tr><td colspan='6'><hr></td></tr>\n";
		print "<form name=diverse action='diverse.php?sektion=smtp' method='post'>\n";
		$tekst1=findtekst(434,$sprog_id);
		$tekst2=findtekst(435,$sprog_id);
		print "<tr><td title='$tekst1'><!--tekst 434-->$tekst2<!--tekst 435--></td>";
		print "<td title='$tekst1'><input class='inputbox' type='text' style='width:200px' name='smtp' value='$r[felt_1]'></td></tr>";
		$tekst1=findtekst(749,$sprog_id);
		$tekst2=findtekst(746,$sprog_id);
		print "<tr><td title='$tekst1'><!--tekst 749-->$tekst2<!--tekst 746--></td>";
		print "<td title='$tekst1'><input class='inputbox' type='text' style='width:200px' name='smtpuser' value='$r[felt_2]'></td></tr>";
		$tekst1=findtekst(750,$sprog_id);
		$tekst2=findtekst(747,$sprog_id);
		print "<tr><td title='$tekst1'><!--tekst 750-->$tekst2<!--tekst 747--></td>";
		print "<td title='$tekst1'><input class='inputbox' type='text' style='width:200px' name='smtppass' value='$r[felt_3]'></td></tr>";
		$tekst1=findtekst(751,$sprog_id);
		$tekst2=findtekst(748,$sprog_id);
		print "<tr><td title='$tekst1'><!--tekst 751-->$tekst2<!--tekst 748--></td>";
		print "<td title='$tekst1'><select class='inputbox' style='width:200px' name='smtpcrypt'>";
		print "<option value='$r[felt_4]'>$r[felt_4]</option>";
		if ($r['felt_4']) print "<option value=''></option>";
		if ($r['felt_4']!='ssl') print "<option value='ssl'>ssl</option>";
		if ($r['felt_4']!='tls') print "<option value='tls'>tls</option>";
		print "</select></td></tr>";
		$tekst1=findtekst(436,$sprog_id);
		print "<td></td><td><input class='button gray medium' style='width:200px' type='submit' value='$tekst1' name='submit'><!--tekst 436--></td></tr>\n";
		print "</form>\n";
		print "<tr><td colspan='6'><hr></td></tr>\n";
		print "<tr><td colspan='6'><br></td></tr>\n";
		print "<form name='nulstil_regnskab' action='diverse.php?sektion=kontoindstillinger' method='post'>\n"; #20170731 ->
		$tekst1=findtekst(756,$sprog_id);
		$tekst2=findtekst(757,$sprog_id);
		print "<tr><td title='$tekst2'><b>$tekst1</b></td></tr>";
		$tekst1=findtekst(758,$sprog_id);
		$tekst2=findtekst(759,$sprog_id);
		print "<tr><td title='$tekst2'>$tekst1</td><td title='$tekst2'><input type='checkbox' name='behold_debkred'></td></tr>";
		$tekst1=findtekst(760,$sprog_id);
		$tekst2=findtekst(761,$sprog_id);
		print "<tr><tr><td title='$tekst2'>$tekst1</td><td title='$tekst2'><input type='checkbox' name='behold_varer'></td></tr>";
		$tekst1=findtekst(762,$sprog_id); $nulstil= findtekst(1239,$sprog_id);
		print "<tr><td></td><td><input class='button gray medium' style='width:200px' type='submit' name='nulstil' value='$nulstil' onclick=\"return confirm('$tekst1')\"></td></tr>";
		print "</form>\n"; # <- 20170731
		print "<tr><td colspan='6'><hr></td></tr>\n";
		print "<tr><td colspan='6'><br></td></tr>\n";
		print "<form name='slet_regnskab' action='diverse.php?sektion=kontoindstillinger' method='post'>\n"; #20170731 ->
		$tekst1=findtekst(852,$sprog_id);
		$tekst2=findtekst(853,$sprog_id);
		print "<tr><td title='$tekst2'><b>$tekst1: $regnskab</b></td><td title='$tekst2'><input type='checkbox' name='slet_regnskab'></td></tr>";
		$tekst1=findtekst(851,$sprog_id); $slet = findtekst(1099,$sprog_id);
		print "<tr><td></td><td><input class='button gray medium' title='$tekst2' style='width:200px' type='submit' name='slet' value='$slet' onclick=\"return confirm('$tekst1')\"></td></tr>";
		print "</form>\n"; # <- 20170731
	} else  {
		print "<form name='diverse' action='diverse.php?sektion=kontoindstillinger' method='post'>\n";
		print "<tr><td colspan='6'>Skriv nyt navn p&aring; regnskab <input class='inputbox' type='text' style='width:400px' name='nyt_navn' value='$regnskab'> ";
		print "og klik <input class='button gray medium' style='width:75px' type='submit' value='Skift&nbsp;navn' name='submit'></td></tr>\n";
		print "</form>\n";
	}


	print "<tr><td colspan='6'><br></td></tr>\n";
} # endfunc kontoindstillinger

function provision() {
	global $bgcolor, $bgcolor5, $popup, $sprog_id;
	

	$batch=$beskrivelse=$bet=$box1=$box2=$box3=$box4=NULL;
	$id=$kodenr=$kort=$kua=$ref=NULL;

	$qtxt = "select * from grupper where art = 'DIV' and kodenr = '1'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$id=$r['id'];
		$beskrivelse=$r['beskrivelse'];
		$kodenr=$r['kodenr'];
		$box1=$r['box1'];
		$box2=$r['box2'];
		$box3=$r['box3'];
		$box4=$r['box4'];
	}
	if ($box1=='ref') $ref="checked";
	elseif ($box1=='kua') $kua="checked";
	else $smart="checked";

	if ($box2=='kort') $kort="checked";
	else $batch="checked";

	if ($box4=='bet') $bet="checked";
	else $fak="checked";

	print "<form name='diverse' action='diverse.php?sektion=provision' method='post'>\n";
	print "<tr><td colspan='6'><hr></td></tr>\n";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(1263, $sprog_id)."</u></b></td></tr>\n"; #20210711
	print "<tr><td colspan='6'><br></td></tr>\n";
	print "<input type='hidden' name='id' value='$id'>\n";
	print "<tr>\n<td>".findtekst(1269, $sprog_id)."</td>\n<td></td>\n<td align='center'>".findtekst(1264, $sprog_id)."</td>\n<td align='center'>".findtekst(1265, $sprog_id)."</td></tr>\n";
	print "<tr>\n<td></td>\n<td></td>\n<td align='center'><input class='inputbox' type='radio' name='box4' value='fak' title='".findtekst(1717, $sprog_id)."' $fak></td>\n"; #20210802
	print "<td align='center'><input class='inputbox' type=radio name='box4' value='bet' title='".findtekst(1718, $sprog_id)."' $bet></td>\n</tr>\n";
	print "<tr>\n<td>".findtekst(1268, $sprog_id)."</td>\n<td align='center'>Ref.</td>\n<td align='center'>".findtekst(1267, $sprog_id).".</td>\n<td align='center'>".findtekst(1266, $sprog_id)."</td>\n</tr>\n";
	print "<tr>\n<td></td>\n";
	print "<td align='center'><input class='inputbox' type='radio' name='box1' value='ref' \n";
	print "    title='".findtekst(1719, $sprog_id)."' $ref></td>\n";
	print "<td align='center'><input class='inputbox' type='radio' name='box1' value='kua' \n";
	print "    title='".findtekst(1720, $sprog_id)."' $kua></td>\n";
	print "<td align='center'><input class='inputbox' type='radio' name='box1' value='smart' \n";
	print "    title='".findtekst(1721, $sprog_id)."' $smart></td>\n";
	print "</tr>\n";
	print "<tr><td>".findtekst(1270, $sprog_id)."</td><td></td><td align='center'>".findtekst(1271, $sprog_id)."</td><td align='center'>".findtekst(566, $sprog_id)."</td></tr>\n";
	print "<tr>\n<td></td>\n<td></td>\n";
	print "<td align=center><input class='inputbox' type='radio' name='box2' value='batch' \n";
	print "    title='".findtekst(1722, $sprog_id).".' $batch></td>\n";
	print "<td align='center'><input class='inputbox' type='radio' name='box2' value='kort' title='".findtekst(1723, $sprog_id).".' $kort></td>\n</tr>\n";
	print "<tr>\n<td>".findtekst(1272, $sprog_id)."</td><td></td><td></td>\n";
	print "<td align=center><select class='inputbox' name='box3' \n";
	print "    title='".findtekst(1724, $sprog_id)."'>";
	if ($box3) print "<option>$box3</option>\n";
	for ($x=1; $x<=28; $x++) {
		print "<option>$x</option>\n";
	}
	print "</select></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td><br></td><td><br></td><td><br></td><td align='center'><input class='button green medium' type='submit' accesskey='g' value='".findtekst(471, $sprog_id)."' name='submit'></td></tr>\n";
	print "</form>\n";
} # endfunc provision  # HTML renset hertil 20150522


function kontoplan_io() {
	global $bgcolor, $bgcolor5, $popup, $sprog_id;

	$x=0;
	$q = db_select("select * from grupper where art = 'RA' order by  kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$kodenr[$x]=$r['kodenr'];
	}
	$antal_regnskabsaar=$x;
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(1352, $sprog_id)."</b></u></td></tr>";
	print "<tr><td colspan='6'><br>".findtekst(1353, $sprog_id)."</td></tr>";
	if ($popup) {
		print "<form name=diverse action=diverse.php?sektion=kontoplan_io method=post>";
		print "<tr><td colspan='2'></td>\n";
		print "<td align=center><SELECT class='inputbox' NAME=regnskabsaar title='".findtekst(1354, $sprog_id)."'>";
#		if ($box3[$x]) print"\t<option>$box3[$x]</option>";
		for ($x=1; $x<=$antal_regnskabsaar; $x++) {
			print "\t<option>$kodenr[$x] : $beskrivelse[$x]</option>";
		}
		print "</select></td>";
		print "<td align = center><input type=submit style='width: 8em' accesskey='e' value='".findtekst(1355, $sprog_id)."' name='submit'></td><tr>";
		print "<tr><td colspan='3'>".findtekst(1356, $sprog_id)." ".findtekst(1357, $sprog_id)." </td>";
		print "<td align = center><input type=submit style='width: 8em' accesskey='i' value='".findtekst(1356, $sprog_id)."' name='submit'></td><tr>";
		print "</form>";
	} else {
		print "<tr><td colspan='3'>".findtekst(1355, $sprog_id)." kontoplan</td><td align=center title='".findtekst(1354, $sprog_id)."'>";
#		if ($box3[$x]) {
#			print "<form form name=exporter$kodenr[$x] action='exporter_kontoplan.php?aar=$box3[$x]' method='post'>\n";
#			print"<input type='submit' style='width: 8em' value='$box3[$x]'><br>\n";
#			print "</form>\n";
#		}
		for ($x=1; $x<=$antal_regnskabsaar; $x++) {
			print "";
			print "<form name=exporter$kodenr[$x] action=exporter_kontoplan.php?aar=$kodenr[$x] method=post><input class='button gray medium' type='submit' style='width: 8em' value='$beskrivelse[$x]'></form>\n";
		}	print "";
		print "</td></tr>\n\n";
		print "<tr><td colspan='3'>".findtekst(1356, $sprog_id)." ".findtekst(1357, $sprog_id)." </td>";
		print "<td align = center><form action='importer_kontoplan.php'><input class='button blue medium' type='submit' style='width: 8em' value='".findtekst(1356, $sprog_id)."' accesskey='i'></form></td><tr>";
		print "<tr><td colspan='3'>Importer mappingfil til offentlig standard kontoplan</td>";
		print "<td align = center><form action='importAccountMap.php'><input class='button blue medium' type='submit' style='width: 8em' value='".findtekst(1356, $sprog_id)."' accesskey='i'></form></td><tr>";
#		print "<td align = center><a href='importer_kontoplan.php' style='text-decoration:none' accesskey='i'>Import&eacute;r</a></td><tr>";
	}
#	print "</tbody></table></td></tr>";

} # endfunc kontoplan_io

function kreditor_io() {
	global $bgcolor, $bgcolor5, $popup, $sprog_id;

	$x=0;
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(1360, $sprog_id)."/".findtekst(1361, $sprog_id)." ".findtekst(607, $sprog_id)."</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<tr><td colspan='3'>".findtekst(1355, $sprog_id)." ".findtekst(607, $sprog_id)."</td>";
	if ($popup)	print "<form name=diverse action=diverse.php?sektion=kreditor_io method=post>";
	else print "<form name=diverse action=exporter_kreditor.php method=post>";
	print "<td align = center><input class='button gray medium' type=submit style='width: 8em' value='".findtekst(1355, $sprog_id)."' name='submit'></td><tr>\n\n";
	print "<tr><td colspan='3'>".findtekst(1356, $sprog_id)." ".findtekst(607, $sprog_id)." </td>\n";
	print "</form>";
	if ($popup)	print "<form name=diverse action=diverse.php?sektion=kreditor_io method=post>";
	else print "<form name=diverse action=importer_kreditor.php method=post>";
	print "<td align = center><input class='button blue medium' type=submit style='width: 8em' value='".findtekst(1356, $sprog_id)."' name='submit'></td><tr>\n\n";
#	print "</tbody></table></td></tr>";
	print "</form>";

} # endfunc kreditor_io
function formular_io() {
	global $bgcolor, $bgcolor5, $popup, $sprog_id;

	$x=0;
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(1360, $sprog_id)." ".findtekst(780, $sprog_id)."</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<tr><td colspan='3'>".findtekst(1355, $sprog_id)." ".findtekst(780, $sprog_id)."</td>";
	if ($popup)	print "<form name=diverse action=diverse.php?sektion=formular_io method=post>";
	else print "<form name=diverse action=exporter_formular.php method=post>";
	print "<td align = center><input class='button gray medium' type=submit style='width: 8em' value='".findtekst(1355, $sprog_id)."' name='submit'></td><tr>\n\n";
	print "</form>";
	print "<tr><td><br></td></tr>";
	print "<tr><td colspan='3'>".findtekst(1356, $sprog_id)." ".findtekst(780, $sprog_id)."</td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=formular_io method=post>";
	else print "<form name=diverse action=importer_formular.php method=post>";
	print "<td align = center><input class='button blue medium' type='submit' style='width: 8em' value='".findtekst(1356, $sprog_id)."'></td></tr>\n\n";
	print "</form>";
} # endfunc formular_io

function varer_io() {
	global $bgcolor, $bgcolor5, $popup, $sprog_id;

	$x=0;
#	print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(1360, $sprog_id)." ".findtekst(609, $sprog_id)."</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<tr><td colspan='3'>".findtekst(1355, $sprog_id)." ".findtekst(609, $sprog_id)."</td>";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	else print "<td align = center><a href='exporter_varer.php' style='text-decoration:none'><input class='button gray medium' type='button' style='width: 8em'  value='".findtekst(1355, $sprog_id)."'></a></td></tr>\n\n";
	print "<tr><td colspan='3'>".findtekst(1356, $sprog_id)." ".findtekst(609, $sprog_id)."</td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	else print "<form name=diverse action=importer_varer.php method=post>";
	print "<td align = center><input class='button blue medium' type='submit' style='width: 8em' value='".findtekst(1356, $sprog_id)."'></td></tr>\n\n";
	print "</form>";
	$r=db_fetch_array(db_select("select count(id) lagerantal from grupper where art='LG'",__FILE__ . " linje " . __LINE__));
	if ($r['lagerantal']) {
		print "<tr><td colspan='3'>".findtekst(1356, $sprog_id)." varelokationer</td>\n";
		print "<form name='diverse' action='importer_varelokationer.php' method='post'>";
		print "<td align = center><input class='button blue medium' type='submit' style='width: 8em' value='".findtekst(1356, $sprog_id)."'></td></tr>\n\n";
		print "</form>";
	}
/*
	print "<tr><td colspan='3'>Import&eacute;r VVSpris fil fra Solar </td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=solar_io method=post>";
	else print "<form name=diverse action=solarvvs.php?sektion=solar_io method=post>";
	print "<td align = center><input type=submit style='width: 8em' value='Import&eacute;r' name='submit'></td><tr>\n\n";
#	print "</tbody></table></td></tr>";
	print "</form>";
*/
} # endfunc varer_io
function variantvarer_io() {
	global $bgcolor, $bgcolor5, $popup, $sprog_id;

	$x=0;
#	print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(1360, $sprog_id)." ".findtekst(1359, $sprog_id)."</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<tr><td colspan='3'>".findtekst(1355, $sprog_id)." ".findtekst(1359, $sprog_id)."</td>";
	if ($popup) print "<td align = center><input class='button gray medium' type=submit accesskey='e' value='".findtekst(1355, $sprog_id)."' name='submit'></td><tr>\n\n";
	else print "<td align = center><a href='exporter_variantvarer.php' style='text-decoration:none'><input class='button gray medium' type='button' style='width: 8em'  value='".findtekst(1355, $sprog_id)."'></a></td></tr>\n\n";
	print "<tr><td colspan='3'>".findtekst(1356, $sprog_id)." ".findtekst(1359, $sprog_id)."</td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=variantvarer_io method=post>";
	else print "<form name=diverse action=importer_variantvarer.php method=post>";
	print "<td align = center><input class='button blue medium' type='submit' style='width: 8em' value='".findtekst(1356, $sprog_id)."'></td></tr>\n\n";
	print "</form>";
/*
	print "<tr><td colspan='3'>Import&eacute;r VVSpris fil fra Solar </td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=solar_io method=post>";
	else print "<form name=diverse action=solarvvs.php?sektion=solar_io method=post>";
	print "<td align = center><input type=submit style='width: 8em' value='Import&eacute;r' name='submit'></td><tr>\n\n";
#	print "</tbody></table></td></tr>";
	print "</form>";
*/
} # endfunc variantvarer_io
function adresser_io() {
	global $bgcolor, $bgcolor5, $popup, $sprog_id;

	$x=0;
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(1360, $sprog_id)."/".findtekst(1361, $sprog_id)." ".findtekst(908, $sprog_id)."/".findtekst(607, $sprog_id)."</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<tr><td colspan='3'>".findtekst(1355, $sprog_id)." ".findtekst(908, $sprog_id)."/".findtekst(607, $sprog_id)."</td>";
	if ($popup) {
		print "<form name=diverse action=diverse.php?sektion=adresser_io method=post>";
		print "<td align = center><input type=submit accesskey='e' style='width: 8em' value='".findtekst(1355, $sprog_id)."' name='submit'></td><tr>";
		print "<tr><td colspan='3'>".findtekst(1356, $sprog_id)." ".findtekst(908, $sprog_id)."/".findtekst(607, $sprog_id)."</td>";
		print "<td align = center><input type=submit accesskey='i' style='width: 8em' value='".findtekst(1356, $sprog_id)."' name='submit'></td><tr>";
		print "</form>";
	} else {
		print "<td align = center><form name=impdeb action='exporter_adresser.php'><input class='button gray medium' type='submit' style='width: 8em' value='".findtekst(1355, $sprog_id)."'></form></td></tr>\n\n";
		print "<tr><td colspan='3'>".findtekst(1356, $sprog_id)." ".findtekst(908, $sprog_id)."/".findtekst(607, $sprog_id)."</td>";
		print "<td align = center><form name=expdeb action='importer_adresser.php'><input class='button blue medium' type='submit' style='width: 8em' value='".findtekst(1356, $sprog_id)."'></form></td></tr>\n\n";
	}
#	print "</tbody></table></td></tr>";

} # endfunc adresser_io

function sqlquery_io($sqlstreng) {
	global $bgcolor, $bgcolor5, $popup, $sprog_id;

$sqlQueryId = if_isset($_POST['sqlQueryId']);
$deleteQuery= if_isset($_POST['deleteQuery']);
if ($sqlQueryId) {
	if ($deleteQuery) {
		db_modify("delete from queries where id = '$sqlQueryId'",__FILE__ . " linje " . __LINE__);
	} else {
		$qtxt="select query from queries where id = '$sqlQueryId'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$sqlstreng=$r['query'];
	}
}	
$titletxt="".findtekst(1725, $sprog_id)." != 'on'";
print "<form name=exportselect action=diverse.php?sektion=sqlquery_io method=post>";
print "<tr><td colspan='6'><hr></td></tr>";
print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(1358, $sprog_id)."</u></b></td></tr>";
print "<tr><td colspan='6'><br></td></tr>";
#print "<input type=hidden name=id value='$id'>";
print "<tr><td valign='top' title='$titletxt'>SELECT</td><td colspan='2'><textarea name='sqlstreng' rows='5' cols='80'>$sqlstreng</textarea></td>";
print "<td align = center><input class='button blue medium' style='width: 8em' type=submit accesskey='s' value='Send' name='send'><br>";
print "<br><input class='button green medium' style='width: 8em' type=submit accesskey='g' value='Gem' name='gem'></td>";
print "</form>";
$gem=$sqlstreng=NULL;
if (isset($_POST['sqlstreng'])) {
	$sqlstreng=trim($_POST['sqlstreng']);
	$gem=$_POST['gem'];
}
if ($sqlstreng=trim($sqlstreng)) {
	global $db, $bruger_id, $sprog_id;

	$linje=NULL;
	$filnavn="../temp/$db/$bruger_id.csv";
	$fp=fopen($filnavn,"w");
#	$sqlstreng=strtolower($sqlstreng);
	list($del1,$del2)=explode("where",$sqlstreng,2);
	$fy_ord=array('brugere','grupper');
	for ($x=0;$x<count($fy_ord);$x++) {
		if (strpos($del1,$fy_ord[$x])) {
          $alert = findtekst(1732, $sprog_id);
			print "<BODY onLoad=\"JavaScript:alert('$alert')\">";
			exit;
		}
	}
#cho "del 1 $del1<br>";
#cho "del2 $del2<br>";

	for($x=0;$x<strlen($del2);$x++){
		$t=substr($del2,$x,1);
		if (!$tilde) {
			if ($t=="'") {
				$tilde=1;
				$var='';
			} else $streng.=$t;
		}	else {
			if ($t=="'") {
				$tilde=0;
				$streng.="'".db_escape_string($var)."'";
			}
		}


	}
#cho "$sqlstreng<br>";
	$qtxt="select ".db_escape_string($del1);
#cho "$qtxt<br>";
	$qtxt="select ".$sqlstreng;
	#cho "$qtxt<br>";

	$r=0;
	$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__ . " funktion sqlquery_io");
	while ($r < db_num_fields($q)) {
		$fieldName[$r] = db_field_name($q,$r);
		$fieldType[$r] = db_field_type($q,$r);
		($linje)?$linje.='";"'.$fieldName[$r]."(".$fieldType[$r].")":$linje='"'.$fieldName[$r]."(".$fieldType[$r].")";
		$r++;
	}
	($linje)?$linje.='"':$linje=NULL;
	if ($fp) {
		fwrite ($fp, "$linje\n");
	}
	$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__ . " funktion sqlquery_io");
	while($r=db_fetch_array($q)) {
		$linje=NULL;
		$arraysize=count($r);
		for ($x=0;$x<$arraysize;$x++) {
			if (isset($fieldType[$x]) && $fieldType[$x]=='numeric') $r[$x]=dkdecimal($r[$x]);
			elseif(isset($r[$x])) $r[$x]=utf8_decode($r[$x]);
			if (!isset($r[$x])) $r[$x] = '';
			($linje)?$linje.='";"'.$r[$x]:$linje='"'.$r[$x];
		}
		($linje)?$linje.='"':$linje=NULL;
		if ($fp) {
			fwrite ($fp, "$linje\n");
		}
	}
	fclose($fp);
	print "<tr><td></td><td align='left' colspan='3'> H&oslash;jreklik her: <a href='$filnavn'>Datafil</a> og v&aelig;lg 'gem destination som'</td></tr>";
	if ($gem) {
		$qtxt=NULL;
		if ($sql_id) $qtxt="update queries set query = '". db_escape_string($sqlstreng) ."' where id = '$sql_id'";
		else {
			$qtxt="select id from queries where query = '". db_escape_string($sqlstreng) ."'";
			if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $qxtx=NULL;
			else $qtxt="insert into queries (query,query_descrpition,user_id) values ('". db_escape_string($sqlstreng) ."','','0')";
		}
		if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}
print "<form name='query' action='diverse.php?sektion=div_io' method='post'>";
print "<tr><td></td><td colspan='4'><select name='sqlQueryId' style='width:600px;'>";
$qtxt="select * from queries order by query";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	print "<option value='$r[id]'>$r[query]</option>";
}
$slet = findtekst(1099,$sprog_id);
print "</select>&nbsp;<input type='submit' name='query' value='".findtekst(1078,$sprog_id)."'>&nbsp;";
print "<input type='submit' name='deleteQuery' value='$slet' onclick=\"return confirm('Slet denne søgning?')\"></td></tr>";
print "</form>";

} # endfunc sqlquery_io


#require("englishfile.php");




function jobkort () {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	$q = db_select("select * from grupper where art = 'JOBKORT' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$kodenr[$x]=$r['kodenr'];
		$sprogkode[$x]=$r['box1'];
	}
	$antal_sprog=$x;
	print "<form name=diverse action=diverse.php?sektion=sprog method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>xSprog</b></u></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	$tekst1=findtekst(1,$sprog_id);
	$tekst2=findtekst(2,$sprog_id);
	print "<tr><td>	$tekst1</td><td><SELECT class='inputbox' NAME=sprog title='$tekst2'>";
	if ($box3[$x]) print"<option>$box3[$x]</option>";
	for ($x=1; $x<=$antal_sprog; $x++) {
		print "<option>$beskrivelse[$x]</option>";
	}
	print "</SELECT></td></tr>";
    print "<tr><td><br></td></tr>";
	$tekst1=findtekst(3,$sprog_id);
	print "<tr><td align = right colspan='4'><input type=submit value='$tekst1' name='submit'></td></tr>";
#	print "<td align = center><input type=submit value='$tekst2' name='submit'></td>";
#	print "<td align = center><input type=submit value='$tekst3' name='submit'></td><tr>";
/*
	print "</tbody></table></td></tr>";
*/
	print "</form>";

} # endfunc jobkort 

# see syssetup/Includes/userSettings

function personlige_valg() {
	global $bgcolor,$bgcolor5,$bruger_id,$db;
	global $menu,$nuance;
	global $popup,$sprog_id,$topmenu;
	
	$gl_menu=NULL;$sidemenu=NULL;

	$r = db_fetch_array(db_select("select * from grupper where art = 'USET' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$jsvars=$r['box1'];
	($r['box2'])?$popup='checked':$popup=NULL;
	if ($r['box3'] == 'S') $sidemenu='checked';
	elseif ($r['box3'] == 'T') $topmenu='checked';
	else $gl_menu='checked';
	($r['box4'])?$bgcolor=$r['box4']:$bgcolor=NULL;
	($r['box5'])?$nuance=$r['box5']:$nuance=NULL;

	$nuancefarver[0]=findtekst(418,$sprog_id); $nuancekoder[0]="+00-22-22";
	$nuancefarver[1]=findtekst(419,$sprog_id); $nuancekoder[1]="-22+00-22";
	$nuancefarver[2]=findtekst(420,$sprog_id); $nuancekoder[2]="-22-22+00";
	$nuancefarver[3]=findtekst(421,$sprog_id); $nuancekoder[3]="+00+00-33";
	$nuancefarver[4]=findtekst(422,$sprog_id); $nuancekoder[4]="+00-33+00";
	$nuancefarver[5]=findtekst(423,$sprog_id); $nuancekoder[5]="-33+00+00";

	print "<form name=personlige_valg action=diverse.php?sektion=personlige_valg&popup=$popup method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(785,$sprog_id)."</u></b></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
#	print "<input type=hidden name=id value='$id'>";

	print "<tr><td title='".findtekst(207,$sprog_id)."'>".findtekst(208,$sprog_id)."</td><td><input class='inputbox' type='checkbox' name='popup' $popup></td></tr>";
#	if (strpos($_SERVER['SERVER_NAME'],'dvikling') || strpos($_SERVER['SERVER_NAME'],'sl3')) {
	#	print "<tr><td title='".findtekst(316,$sprog_id)."'><!--Tekst 523-->".findtekst(315,$sprog_id)."<!--Tekst 315--></td><td><input class='inputbox' type='radio' name='menu' value='sidemenu' $sidemenu></td></tr>";
	if (substr($db,0,4) == 'laja') {
		print "<tr><td title='".findtekst(523,$sprog_id)."'><!--Tekst 523-->".findtekst(522,$sprog_id)."<!--Tekst 522--></td><td><input class='inputbox' type='radio' name='menu' value='topmenu' $topmenu></td></tr>";
#	}	else $gl_menu='checked';
	print "<tr><td title='".findtekst(525,$sprog_id)."'><!--Tekst 525-->".findtekst(524,$sprog_id)."<!--Tekst 524--></td><td><input class='inputbox' type='radio' name='menu'  value='gl_menu' $gl_menu></td></tr>";
} else print "<input type = 'hidden' name = 'menu' value='gl_menu'>"; 
	print "<tr><td title='".findtekst(209,$sprog_id)."'>".findtekst(210,$sprog_id)."</td><td colspan='4'><input class='inputbox' type='text' style='width:600px' name='jsvars' value='$jsvars'></td></tr>";
	if ($menu=='T') {
		print "<input type='hidden' name='bgcolor' value='".substr($bgcolor,1,6)."'>";
		print "<input type='hidden' name='nuance' value='$nuance'>\n";
	} else {
		print "<tr><td title='".findtekst(318,$sprog_id)."'>".findtekst(317,$sprog_id)."</td><td colspan='4'><input class='inputbox' type='text' style='width:100px' name='bgcolor' value='".substr($bgcolor,1,6)."'></td></tr>";
		print "<tr><td title='".findtekst(416,$sprog_id)."'>".findtekst(415,$sprog_id)."</td><td colspan='4'><select name='nuance' title='".findtekst(417,$sprog_id)."'>\n";
	if ( ! $nuance ) {
		$valgt = "selected='selected'";
	} else {
		$valgt="";
	}
	print "   <option $valgt value='' style='background:$bgcolor'>Intet</option>\n";
	for ($x=0; $x<count($nuancefarver);$x++) {
		if ( $nuance === $nuancekoder[$x] ) {
			$valgt = "selected='selected'";
		} else {
			$valgt="";
		}
		print "   <option $valgt value='$nuancekoder[$x]' style='background:".farvenuance($bgcolor, $nuancekoder[$x])."'>$nuancefarver[$x]</option>\n";
	}
}
	print "</select></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td><br></td><td><br></td><td><br></td><td align = center><input class='button green medium' type=submit accesskey='g' value='".findtekst(471, $sprog_id)."' name='submit'></td></tr>\n";
	print "</form>";
} # endfunc personlige_valg

function div_valg() {
	global $bgcolor,$bgcolor5;
	global $docubizz;
	global $regnaar;
	global $sprog_id;

	$batch=$ebconnect=$extra_ansat=$forskellige_datoer=$paperflow=NULL;
	$gls_id=$gls_pass=$gls_user=$gls_ctId=NULL; #20211019 $gls_ctId added
	$dfm_id=$dfm_pass=$dfm_user=$dfm_agree=$dfm_hub=$dfm_ship=$dfm_good=$dfm_pay=$dfm_url=$dfm_gooddes=$dfm_sercode=NULL;
	$dfm_pickup_addr=$dfm_pickup_name1=$dfm_pickup_name2=$dfm_pickup_street1=$dfm_pickup_street2=$dfm_pickup_town=$dfm_pickup_zipcode=NULL;
	$gruppevalg=$jobkort=$kort=$kuansvalg=$ref=$kua=$smart=$debtor2orderphone=NULL;
	$qp_merchant = $qp_md5secret = $qp_agreement_id = $qp_itemGrp = NULL;

	
	$q = db_select("select * from grupper where art = 'DIV' and kodenr = '2'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse']; $kodenr=$r['kodenr'];
	$box1=$r['box1']; $box2=$r['box2']; $box3=$r['box3']; $box4=$r['box4'];$box5=$r['box5'];
	$box6=$r['box6'];$box7=$r['box7'];$box8=$r['box8'];$box9=$r['box9'];$box10=$r['box10'];
	if ($box1=='on') $gruppevalg="checked"; if ($box2=='on') $kuansvalg="checked"; if ($box3=='on') $extra_ansat="checked";
	if ($box4=='on') $forskellige_datoer="checked";
	if ($box5=='on') $debtor2orderphone="checked";
	if ($box6=='on') $docubizz="checked";
	if ($box7=='on') $jobkort="checked";
	if ($box8) $ebconnect="checked";
	if ($box9=='on') $ledig="checked"; # ledig
	if ($box10=='on') $betalingsliste="checked";

	$r=db_fetch_array(db_select("select box1,box3 from grupper where art = 'PV' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	($r['box1'])?$direkte_print='checked':$direkte_print=NULL;
	($r['box3'])?$formgen='checked':$formgen=NULL;

	$qtxt="select var_name,var_value from settings where var_grp='GLS'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		if ($r['var_name']=='gls_id')   $gls_id   = $r['var_value'];
		if ($r['var_name']=='gls_user') $gls_user = $r['var_value'];
		if ($r['var_name']=='gls_pass') $gls_pass = $r['var_value'];
		if ($r['var_name']=='gls_ctId') $gls_ctId = $r['var_value'];
	}
	$qtxt="select var_name,var_value from settings where var_grp='DanskeFragt'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		if ($r['var_name']=='dfm_id')             $dfm_id             = $r['var_value'];
		if ($r['var_name']=='dfm_user')           $dfm_user           = $r['var_value'];
		if ($r['var_name']=='dfm_pass')           $dfm_pass           = $r['var_value'];
		if ($r['var_name']=='dfm_agree')          $dfm_agree          = $r['var_value'];
		if ($r['var_name']=='dfm_hub')            $dfm_hub            = $r['var_value'];
		if ($r['var_name']=='dfm_ship')           $dfm_ship           = $r['var_value'];
		if ($r['var_name']=='dfm_good')           $dfm_good           = $r['var_value'];
		if ($r['var_name']=='dfm_pay')            $dfm_pay            = $r['var_value'];
		if ($r['var_name']=='dfm_url')            $dfm_url            = $r['var_value'];
		if ($r['var_name']=='dfm_gooddes')        $dfm_gooddes        = $r['var_value'];
		if ($r['var_name']=='dfm_sercode')        $dfm_sercode        = $r['var_value'];
		if ($r['var_name']=='dfm_pickup_addr')    $dfm_pickup_addr    = $r['var_value'];
		if ($r['var_name']=='dfm_pickup_name1')   $dfm_pickup_name1   = $r['var_value'];
		if ($r['var_name']=='dfm_pickup_name2')   $dfm_pickup_name2   = $r['var_value'];
		if ($r['var_name']=='dfm_pickup_street1') $dfm_pickup_street1 = $r['var_value'];
		if ($r['var_name']=='dfm_pickup_street2') $dfm_pickup_street2 = $r['var_value'];
		if ($r['var_name']=='dfm_pickup_town')    $dfm_pickup_town    = $r['var_value'];
		if ($r['var_name']=='dfm_pickup_zipcode') $dfm_pickup_zipcode = $r['var_value'];
	}

	$qtxt="select var_value from settings where var_grp='debitor' and var_name='mySale'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	($r['var_value'])?$mySale="checked='checked'":$mySale=NULL;

	$qtxt="select var_value from settings where var_grp='debitor' and var_name='mySale'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	($r['var_value'])?$mySaleTest="checked='checked'":$mySaleTest=NULL;

	$qtxt="select var_value from settings where var_grp='debitor' and var_name='mySaleLabel'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	($r['var_value'])?$mySaleLabel="checked='checked'":$mySaleLabel=NULL;
	
	$qtxt="select var_value from settings where var_grp='creditor' and var_name='paperflow'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	($r['var_value'])?$paperflow="checked='checked'":$paperflow=NULL;
	if ($paperflow) {
		$qtxt="select var_value from settings where var_grp='creditor' and var_name='paperflowId'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$paperflowId = $r['var_value'];
		$qtxt="select var_value from settings where var_grp='creditor' and var_name='paperflowBearer'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$paperflowBearer = $r['var_value'];
	}
	$qtxt = "select * from settings where var_grp = 'quickpay'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['var_name'] == 'qp_merchant')     $qp_merchant     = $r['var_value'];
		if ($r['var_name'] == 'qp_md5secret')    $qp_md5secret    = $r['var_value'];
		if ($r['var_name'] == 'qp_agreement_id') $qp_agreement_id = $r['var_value'];
		if ($r['var_name'] == 'qp_itemGrp')      $qp_itemGrp      = $r['var_value'];
	}
	$x=0;
	$qtxt = "select * from grupper where art = 'VG' order by kodenr";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$itemGrpNo[$x]   = $r['kodenr'];
		$itemGrpName[$x] = $r['beskrivelse'];
		$x++;
	}
	array_multisort($itemGrpNo, SORT_ASC, $itemGrpName);

	print "<form name='diverse' action='diverse.php?sektion=div_valg' method='post'>\n";
	print "<tr style='background-color:$bgcolor5'><td colspan='6'><b>".findtekst(794,$sprog_id)."</b></td></tr>\n";
	print "<tr><td colspan='2'>&nbsp;</td></tr>\n";
	print "<input name='id' type='hidden' value='$id'>\n"; 
	print "<tr bgcolor='$bgcolor5'>\n<td title='".findtekst(186,$sprog_id)."'>".findtekst(162,$sprog_id)."</td>\n";
	print "<td title='".findtekst(186,$sprog_id)."'>\n";
	print "<!-- 162 : Tvungen valg af debitorgruppe på debitorkort -->";
	print "<input name='box1' class='inputbox' type='checkbox' $gruppevalg>\n";
	print "</td></tr>\n";
	print "<tr>\n<td title='".findtekst(187,$sprog_id)."'>".findtekst(163,$sprog_id)."</td>\n";
	print "<td title='".findtekst(187,$sprog_id)."'>\n";
	print "<!-- 163 : Tvungen valg af kundeansvarlig på debitorkort -->";
	print "<input name='box2' class='inputbox' type='checkbox' $kuansvalg>\n";
	print "</td></tr>\n";
	print "<tr bgcolor='$bgcolor5'>\n<td title='".findtekst(615,$sprog_id)."'>".findtekst(616,$sprog_id)."</td>\n";
	print "<td title='".findtekst(615,$sprog_id)."'>\n";
	print "<!-- 616 : Tilføj ekstra felter på ansatte -->";
	print "<input name='box3' class='inputbox' type='checkbox' $extra_ansat>\n";
	print "</td></tr>\n";
	print "<tr>\n<td title='".findtekst(185,$sprog_id)."'>".findtekst(184,$sprog_id)."</td>\n";
	print "<td title='".findtekst(185,$sprog_id)."'>\n";
	print "<!-- 184 : Brug betalingslister -->";
	print "<input name='box10' class='inputbox' type='checkbox' $betalingsliste>\n";
	print "</td></tr>\n";
	print "<tr>\n<td title='".findtekst(1061,$sprog_id)."'>".findtekst(1060,$sprog_id)."</td>\n";
	print "<td title='".findtekst(1061,$sprog_id)."'>\n";
	print "<!-- 922  : Benyt debitors kontonummer som telefonnumer på ordre -->";
	print "<input name='box5' class='inputbox' type='checkbox' $debtor2orderphone>\n";
	print "</td></tr>\n";
	print "<tr bgcolor='$bgcolor5'>\n<td title='".findtekst(193,$sprog_id)."'>".findtekst(167,$sprog_id)."</td>\n";
	print "<td title='".findtekst(193,$sprog_id)."'>\n";
	print "<!-- 167 : Integration med DocuBizz -->";
	print "<input name='box6' class='inputbox' type='checkbox' $docubizz>\n";
	print "</td></tr>\n";
	if (strpos(findtekst(768,$sprog_id),"'")) {
		$qtxt="delete from tekster where tekst_id = '767' or tekst_id = '768'";
		db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
	}
	print "<tr>\n<td title='".findtekst(767,$sprog_id)."'>".findtekst(768,$sprog_id)."</td>\n";
	print "<td title='".findtekst(768,$sprog_id)."'>\n";
	print "<!-- 768 : Brug 'Mit salg' -->";
	print "<input name='mySale' class='inputbox' type='checkbox' $mySale>\n";
	print "</td></tr>\n";
/*
	echo "mySale: $mySale <br>";
	echo "mySaleLabel: $mySaleLabel <br>";
	echo "mySaleTest: $mySaleTest";
*/
	if ($mySale){
	print "<tr>\n<td title='Deaktivere labels for kunder så det kun er ejeren der kan oprette dem'>Deaktiver labels for kunder</td>\n";
	print "<td title='Deaktiver labels for kunder'>\n";
	print "<input name='mySaleLabel' class='inputbox' type='checkbox' $mySaleLabel>\n";
	print "</td></tr>\n";
	}

	print "<tr>\n<td title='Test'>mySaleTest</td>\n";
	print "<td title='mySaleTest'>\n";
	print "<input name='mySaleTest' class='inputbox' type='checkbox' $mySaleTest>\n";
	print "</td></tr>\n";



	print "<tr bgcolor='$bgcolor5'>\n<td title='".findtekst(194,$sprog_id)."'>".findtekst(168,$sprog_id)."</td>\n";
	print "<td title='".findtekst(194,$sprog_id)."'>\n";
	print "<!-- 168 : Brug jobkort -->";
	print "<input name='box7' class='inputbox' type='checkbox' $jobkort>\n";
	print "</td></tr>\n";
	$externalContent = file_get_contents('http://checkip.dyndns.com/');
	preg_match('/Current IP Address: \[?([:.0-9a-fA-F]+)\]?/', $externalContent, $m);
	$externalIp = $m[1];
	$txt=str_replace('$myip',$externalIp,findtekst(764,$sprog_id));
	print "<tr>\n<td title='$txt'>".findtekst(763,$sprog_id)."</td>\n";
	print "<td title='$txt'>\n";
	print "<input name='pv_box1' class='inputbox' type='checkbox' $direkte_print>\n";
	print "</td></tr>\n";
	print "<tr bgcolor='$bgcolor5'>\n<td title='".findtekst(817,$sprog_id)."'>".findtekst(818,$sprog_id)."</td>\n";
	print "<td title='".findtekst(817,$sprog_id)."'>\n";
	print "<input name='pv_box3' class='inputbox' type='checkbox' $formgen>\n";
	print "</td></tr>\n";
	print "<tr>\n<td title='".findtekst(709,$sprog_id)."'>".findtekst(708,$sprog_id)."</td>\n";
	print "<td title='".findtekst(709,$sprog_id)."'>\n";
	print "<input name='box4' class='inputbox' type='checkbox' $forskellige_datoer></td></tr>\n"; #20131101
		if (strpos(findtekst(841,$sprog_id),'kortet er et betalingskort')) {
		db_modify("delete from tekster where (tekst_id='841' or tekst_id='642') and sprog_id='$sprog_id'");
  }
	print "</td></tr>\n";
	print "<!-- 795 : Brug 'PaperFlow' -->";
	print "<tr bgcolor='$bgcolor5'>\n<td title='".findtekst(1931,$sprog_id)."'>".findtekst(795,$sprog_id)."</td>\n";
	print "<td title='".findtekst(1931,$sprog_id)."'>\n";
	print "<input name='paperflow' class='inputbox' type='checkbox' $paperflow></td></tr>\n";
	if ($paperflow) {
		print "<tr bgcolor='$bgcolor'>\n<td title='".findtekst(1957,$sprog_id)."'>".findtekst(1957,$sprog_id)."</td>\n";
		print "<td title='".findtekst(1957,$sprog_id)."'>\n";
		print "<input name='paperflowId' class='inputbox' type='text' value = '$paperflowId'></td></tr>\n";
		print "<tr bgcolor='$bgcolor5'>\n<td title='".findtekst(1958,$sprog_id)."'>".findtekst(1958,$sprog_id)."</td>\n";
		print "<td title='".findtekst(1958,$sprog_id)."'>\n";
		print "<input name='paperflowBearer' class='inputbox' type='text' value = '$paperflowBearer'></td></tr>\n";
}
#	print "<tr>\n<td title='".findtekst(642,$sprog_id)."'>".findtekst(841,$sprog_id)."</td>\n";
#	print "<td title='".findtekst(642,$sprog_id)."'>\n";
#	print "    <input name='box5' class='inputbox' type='text' style='width:150px;' placeholder='' value=\"$box5\">\n";
#	print "</td></tr>\n"; #20131101
	print "<tr bgcolor='$bgcolor'>\n<td title='".findtekst(527,$sprog_id)."'>".findtekst(526,$sprog_id)."</td>\n";
	print "<td title='".findtekst(527,$sprog_id)."'>\n";
	print "<!-- 526 : Integration med ebConnect -->";
	print "<input name='box8' class='inputbox' type='checkbox' $ebconnect>\n";
	print "</td></tr>\n";
	if ($box8) {
		list($oiourl,$oiobruger,$oiokode)=explode(chr(9),$box8);
		print "<tr bgcolor='$bgcolor'>\n<td title=''>".findtekst(528,$sprog_id)."</td>\n";
		print "<td><input name='oiourl' class='inputbox' style='width:150px;' type='text' value='$oiourl'></td>\n</tr>\n";
		print "<tr>\n<td title=''>".findtekst(529,$sprog_id)."</td>\n";
		print "<td><input name='oiobruger' class='inputbox' style='width:150px;' type='text' value='$oiobruger'></td>\n</tr>\n";
		print "<tr>\n<td title=''>".findtekst(530,$sprog_id)."</td>\n";
		print "<td><input name='oiokode' class='inputbox' style='width:150px;' type='password' value='$oiokode'></td>\n</tr>\n";
	}
	$txt=findtekst(865,$sprog_id);
	$title=findtekst(866,$sprog_id);
	if ($gls_id) {
		print "<tr bgcolor='$bgcolor5'>\n<td style='font-weight:bold' title='$title'>$txt</td>\n";
		print "<td title='$title'><input name='gls_id' class='inputbox' style='width:150px;' type='text' value='$gls_id'></td>\n</tr>\n";
		$txt=findtekst(867,$sprog_id);
		$title=findtekst(868,$sprog_id);
		print "<tr>\n<td title='$title'>- $txt</td>\n";
		print "<td title='$title'><input name='gls_user' class='inputbox' style='width:150px;' type='text' value='$gls_user'></td>\n</tr>\n";
		$txt=findtekst(873,$sprog_id);
		$title=findtekst(874,$sprog_id);
		print "<tr>\n<td title='$title'>- $txt</td>\n";
		print "<td title='$title'><input name='gls_ctId' class='inputbox' style='width:150px;' type='text' value='$gls_ctId'></td>\n</tr>\n";
		$txt=findtekst(869,$sprog_id);
		$title=findtekst(870,$sprog_id);
		print "<tr>\n<td title='$title'>- $txt</td>\n";
		print "<td title='$title'><input name='gls_pass' class='inputbox' style='width:150px;' type='password' value='$gls_pass'></td>\n</tr>\n";
	} else {
		print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>$txt</td>\n";
		print "<td title='$title'><input name='gls_id' class='inputbox' style='width:150px;' type='text' value='$gls_id'></td>\n</tr>\n";
		print "<input name='gls_user' type='hidden' value='$gls_user'>\n";
		print "<input name='gls_ctId' type='hidden' value='$gls_ctId'>\n";
		print "<input name='gls_pass' type='hidden' value='$gls_pass'>\n";
	}
	$txt=findtekst(1020,$sprog_id);
	$title=findtekst(1021,$sprog_id);
	$title.=" ".findtekst(1040,$sprog_id);
	print "<!-- 1020 Danske Fragtmænd aftalenummer -->";
	if ($dfm_agree) {
		print "<tr bgcolor='$bgcolor5'>\n<td style='font-weight:bold' title='$title'>$txt</td>\n";
		print "<td title='$title'><input name='dfm_agree' class='inputbox' style='width:150px;' type='text' value='$dfm_agree'></td>\n</tr>\n";
		$txt=findtekst(1022,$sprog_id);
		$title=findtekst(1023,$sprog_id);
		print "<!-- 1022 Hub -->";
		print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>- $txt</td>\n";
		print "<td title='$title'><input name='dfm_hub' class='inputbox' style='width:150px;' type='text' value='$dfm_hub'></td>\n</tr>\n";
		$txt=findtekst(1020,$sprog_id);
		$title=findtekst(1031,$sprog_id);
		print "<!-- 1020 API-URL -->";
		print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>- $txt</td>\n";
		print "<td title='$title'><input name='dfm_url' class='inputbox' style='width:150px;' type='text' value='$dfm_url'></td>\n</tr>\n";
		$txt=findtekst(1014,$sprog_id);
		$title=findtekst(1015,$sprog_id);
		print "<!-- 1014 ClientID til API -->";
		print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>- $txt</td>\n";
		print "<td title='$title'><input name='dfm_id' class='inputbox' style='width:150px;' type='text' value='$dfm_id'></td>\n</tr>\n";
		$txt=findtekst(1016,$sprog_id);
		$title=findtekst(1017,$sprog_id);
		print "<!-- 1016 API-brugernavn -->";
		print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>- $txt</td>\n";
		print "<td title='$title'><input name='dfm_user' class='inputbox' style='width:150px;' type='text' value='$dfm_user'></td>\n</tr>\n";
		$txt=findtekst(1018,$sprog_id);
		$title=findtekst(1019,$sprog_id);
		print "<!-- 1018 API-password -->";
		print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>- $txt</td>\n";
		print "<td title='$title'><input name='dfm_pass' class='inputbox' style='width:150px;' type='password' value='$dfm_pass'></td>\n</tr>\n";
		$txt=findtekst(1024,$sprog_id);
		$title=findtekst(1025,$sprog_id);
		print "<!-- 1024 Shippingtype som standard -->";
		print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>- $txt</td>\n";
		print "<td title='$title'><input name='dfm_ship' class='inputbox' style='width:150px;' type='text' value='$dfm_ship'></td>\n</tr>\n";
		$txt=findtekst(1026,$sprog_id);
		$title=findtekst(1027,$sprog_id);
		print "<!-- 1026 Godstype som standard -->";
		print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>- $txt</td>\n";
		print "<td title='$title'><input name='dfm_good' class='inputbox' style='width:150px;' type='text' value='$dfm_good'></td>\n</tr>\n";
		$txt=findtekst(1028,$sprog_id);
		$title=findtekst(1029,$sprog_id);
		print "<!-- 1028 Betalingmetode som standard -->";
		print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>- $txt</td>\n";
		print "<td title='$title'><input name='dfm_pay' class='inputbox' style='width:150px;' type='text' value='$dfm_pay'></td>\n</tr>\n";
		$txt=findtekst(1038,$sprog_id);
		$title=findtekst(1039,$sprog_id);
		print "<!-- 1038 Beskrivelse af gods som standard -->";
		print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>- $txt</td>\n";
		print "<td title='$title'><input name='dfm_gooddes' class='inputbox' style='width:150px;' type='text' value='$dfm_gooddes'></td>\n</tr>\n";
		$txt=findtekst(1058,$sprog_id);
		$title=findtekst(1059,$sprog_id);
		print "<!-- 1058 Leveringsmetode som standard -->";
		print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>- $txt</td>\n";
		print "<td title='$title'><input name='dfm_sercode' class='inputbox' style='width:150px;' type='text' value='$dfm_sercode'></td>\n</tr>\n";

		$txt=findtekst(1043,$sprog_id);
		$title=findtekst(1044,$sprog_id);
		print "<!-- 1043 Afhentningsadresse er en anden end hovedadressen -->";
		print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>- $txt</td>\n";

		if ( $dfm_pickup_addr ) {
			print "<td><input type='checkbox' name='dfm_pickup_addr' class='inputbox' checked value='dfm_pickup_addr'></td>\n</tr>\n";
			$txt=findtekst(360,$sprog_id);
			$title=findtekst(1046,$sprog_id);
			print "<!-- 360 Firmanavn -->";
			print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>--- $txt</td>\n";
			print "<td title='$title'><input name='dfm_pickup_name1' class='inputbox' style='width:150px;' type='text' value='$dfm_pickup_name1'></td>\n</tr>\n";
			$txt=findtekst(1047,$sprog_id);
			$title=findtekst(1048,$sprog_id);
			print "<!-- 1047 Eventuelt ekstra navn -->";
			print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>--- $txt</td>\n";
			print "<td title='$title'><input name='dfm_pickup_name2' class='inputbox' style='width:150px;' type='text' value='$dfm_pickup_name2'></td>\n</tr>\n";
			$txt=findtekst(1049,$sprog_id);
			$title=findtekst(1050,$sprog_id);
			print "<!-- 1049 Adresse -->";
			print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>--- $txt</td>\n";
			print "<td title='$title'><input name='dfm_pickup_street1' class='inputbox' style='width:150px;' type='text' value='$dfm_pickup_street1'></td>\n</tr>\n";
			$txt=findtekst(1051,$sprog_id);
			$title=findtekst(1052,$sprog_id);
			print "<!-- 1051 Eventuel ekstra adresselinje -->";
			print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>--- $txt</td>\n";
			print "<td title='$title'><input name='dfm_pickup_street2' class='inputbox' style='width:150px;' type='text' value='$dfm_pickup_street2'></td>\n</tr>\n";
			$txt=findtekst(1053,$sprog_id);
			$title=findtekst(1054,$sprog_id);
			print "<!-- 1053 Postnummer -->";
			print "<tr bgcolor='$bgcolor5'><td title='$title'>--- $txt</td><td title='$title'>";
			print "<input name='dfm_pickup_zipcode' class='inputbox' style='width:150px;' type='text' value='$dfm_pickup_zipcode'>";
			print "</td>\n</tr>\n";
			$txt=findtekst(1055,$sprog_id);
			$title=findtekst(1056,$sprog_id);
			print "<!-- 1055 Bynavn -->";
			print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>--- $txt</td>\n";
			print "<td title='$title'><input name='dfm_pickup_town' class='inputbox' style='width:150px;' type='text' value='$dfm_pickup_town'></td>\n</tr>\n";
		} else {
			print "<td><input type=\"checkbox\" name=\"dfm_pickup_addr\" value=\"DayP\"></td>\n</tr>\n";
			print "<input name='dfm_pickup_name1' type='hidden' value='$dfm_pickup_name1'>\n";
			print "<input name='dfm_pickup_name2' type='hidden' value='$dfm_pickup_name2'>\n";
			print "<input name='dfm_pickup_street1' type='hidden' value='$dfm_pickup_street1'>\n";
			print "<input name='dfm_pickup_street2' type='hidden' value='$dfm_pickup_street2'>\n";
			print "<input name='dfm_pickup_zipcode' type='hidden' value='$dfm_pickup_zipcode'>\n";
			print "<input name='dfm_pickup_town' type='hidden' value='$dfm_pickup_town'>\n";
		}
	} else {
		print "<tr bgcolor='$bgcolor5'>\n<td title='$title'>$txt</td>\n";
		print "<td title='$title'><input name='dfm_agree' class='inputbox' style='width:150px;' type='text' value='$dfm_agree'></td>\n</tr>\n";

		print "<input name='dfm_hub' type='hidden' value='$dfm_hub'>\n";
		print "<input name='dfm_url' type='hidden' value='$dfm_url'>\n";
		print "<input name='dfm_id' type='hidden' value='$dfm_id'>\n";
		print "<input name='dfm_user' type='hidden' value='$dfm_user'>\n";
		print "<input name='dfm_pass' type='hidden' value='$dfm_pass'>\n";
		print "<input name='dfm_ship' type='hidden' value='$dfm_ship'>\n";
		print "<input name='dfm_good' type='hidden' value='$dfm_good'>\n";
		print "<input name='dfm_pay' type='hidden' value='$dfm_pay'>\n";
		print "<input name='dfm_gooddes' type='hidden' value='$dfm_gooddes'>\n";
		print "<input name='dfm_sercode' type='hidden' value='$dfm_sercode'>\n";
	}
	$txt =   'Quickpay agreement id';
	$title = 'Aftale id fra Quickpay';
	print "<!-- xxxx Aftale id fra Quickpay -->";
	print "<tr bgcolor='$bgcolor5'><td title='$title'>$txt</td><td title='$title'>";
	print "<input name='qp_agreement_id' class='inputbox' style='width:150px;' type='text' value='$qp_agreement_id'>";
	print "</td>\n</tr>\n";
	if ($qp_agreement_id) {
    $txt =   'Quickpay merhcant';
    $title = 'Forretnings nr fra Quickpay';
    print "<!-- xxxx Forretnings nr id fra Quickpay -->";
    print "<tr bgcolor='$bgcolor'><td title='$title'>$txt</td><td title='$title'>";
    print "<input name='qp_merchant' class='inputbox' style='width:150px;' type='text' value='$qp_merchant'>";
    print "</td>\n</tr>\n";
    $txt =   'Quickpay md5 secret';
    $title = 'Krypteringsnøgle fra Quickpay';
    print "<!-- xxxx Krypteringsnøgle fra Quickpay -->";
    print "<tr bgcolor='$bgcolor5'><td title='$title'>$txt</td><td title='$title'>";
    print "<input name='qp_md5secret' class='inputbox' style='width:150px;' type='text' value='$qp_md5secret'>";
    $txt =   'Quickpay varegruppe';
    $title = 'Varegruppe for varer til betaling med Quickpay';
    print "<!-- xxxx Quickpay varegruppe -->";
    print "<tr bgcolor='$bgcolor'><td title='$title'>$txt</td><td title='$title'>";
    print "<select name='qp_itemGrp' class='inputbox' style='width:150px;'>";
    for($x=0;$x<count($itemGrpNo);$x++) {
      if ($qp_itemGrp == $itemGrpNo[$x]) print "<option value = '$itemGrpNo[$x]'>$itemGrpNo[$x] : $itemGrpName[$x]</option>"; 
    }
    print "<option value = ''></option>";
    for($x=0;$x<count($itemGrpNo);$x++) {
      if ($qp_itemGrp != $itemGrpNo[$x]) print "<option value = '$itemGrpNo[$x]'>$itemGrpNo[$x] : $itemGrpName[$x]</option>"; 
    }
    print "</select>";
    print "</td>\n</tr>\n";
    
  }

  $qtxt = "SELECT var_value FROM settings WHERE var_name='flatpay_auth'";
  $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));

  # Guid form flatpay, looks like 9e802837-307b-48c3-9f0e-1b4cac291376
  $guid = $r ? str_split($r[0], 7)[0]."-xxxx-xxxx-xxxx-xxxxxxxxxxxx" : "";

	$mtxt=findtekst(3014,$sprog_id);
	$mtitle=findtekst(3013,$sprog_id);
  print "<tr>\n<td title='$mtitle'><!-- Tekst 3013 -->$mtxt <!-- Tekst 3014 --></td>\n";
  print "<td title='$mtitle'>
    <span style='position:relative;'>
      <input name='flatpay_id' disabled class='inputbox' style='width:150px; cursor: pointer;' type='text' value='$guid' onclick='open_popup()'>
      <div style='position:absolute; left:0; right:0; top:0; bottom:0; cursor: pointer;' onclick='open_popup(this)'></div>
    </span>
  </td>\n</tr>\n";

  # API key for vibrant
  $qtxt = "SELECT var_value FROM settings WHERE var_name='vibrant_auth'";
  $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));

  # Check if it exsists
  $APIKEY = $r ? $r[0] : "";

	$mtxt=findtekst(3016,$sprog_id);
	$mtitle=findtekst(3017,$sprog_id);
  print "<tr>\n<td title='$mtitle'><!-- Tekst 3013 -->$mtxt <!-- Tekst 3014 --></td>\n";
  print "<td title='$mtitle'>
    <input name='vibrant_id' class='inputbox' style='width:150px;' type='text' value='$APIKEY'>
  </td>\n</tr>\n";

  # Vibrant login setup
  $qtxt = "SELECT var_name, var_value FROM settings WHERE var_grp='vibrant_account'";
  $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));

	$mtxt=findtekst(3021,$sprog_id);
	$mtitle=findtekst(3022,$sprog_id);
  print "<tr>\n<td title='$mtitle'><!-- Tekst 3013 -->$mtxt <!-- Tekst 3014 --></td>\n";

  # If an account is already setup, show the "Show account" button
  if ($r) {
	  $ntxt=findtekst(3024,$sprog_id); # Show account

    print "<td title='$mtitle'>
      <button type='button' onclick='alert(\"Dit login til din vibrant terminalen: \\n\\n$r[var_name] \\n$r[var_value]\")'>$ntxt</button>
    </td>\n</tr>\n";
  } else { # No vibrant account in the system
	  $ytxt=findtekst(3023,$sprog_id); # Create account

    print "<td title='$mtitle'>
      <button type='button' onclick='show_popup_vibrant()'>$ytxt</button>
    </td>\n</tr>\n";
  } # TODO: #popupbox for styleing (DONE), setup name, email and password fields for create login

  print "
<div class='blackdrop'></div>

<div id='popupbox'>
  <h1>Vibrant</h1>
  <p>Opret en konto til dine vibrant terminaler, du skal kun bruge en konto og vi opbevare din email og adgangskode for dig.</p>
  <br>
  <span>Navn</span><br>
  <input id='vibrant-acc-name' type='text' placeholder='Navn'></input>
  <br><br><span>Email</span><br>
  <input id='vibrant-acc-email' type='text' placeholder='Email'></input>
  <br><br><span>Adgangskode</span><br>
  <input id='vibrant-acc-passwd' type='password' placeholder='Password'></input>
  <br><br><button onclick='hide_popup_vibrant()' type='button'>Gem</button>
</div>

<script>
  function show_popup_vibrant() {
    document.getElementById('popupbox').style.display = 'block';
    document.getElementsByClassName('backdrop')[0].style.display = 'block';
  }

  function hide_popup_vibrant() {
    var name = document.getElementById('vibrant-acc-name').value;
    var email = document.getElementById('vibrant-acc-email').value;
    var passwd = document.getElementById('vibrant-acc-passwd').value;

    data = {
      'name': name,
      'email': email,
      'roleIds': [
        'ro_1xBHy6kquVWMne9caAaXps',
        'ro_bzDKsUpAeFsFm8kUUXkXTy'
      ],                           
      'password': passwd       
    }

    fetch('https://pos.api.vibrant.app/pos/v1/users', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'apikey': '$APIKEY'
      },
      body: JSON.stringify(data) 
    })
      .then(response => {
        if (!response.ok) {
          console.log(response);
          response.json().then((res) => {console.log(res); alert(res.error + ' : ' + res.message)}).catch(error => {
            throw new Error('Network response was not ok');
          })
          throw new Error('Network response was not ok');
        }
        console.log('Response:', response);
        fetch('diverseIncludes/create_vibrant_login.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({name: name, email: email, passwd: passwd}) 
        })
          .then(response => {
            if (!response.ok) {
              throw new Error('Network response was not ok');
            }
            console.log('Response:', response);
            location.reload();
          })
          .catch(error => {
            console.error('Fetch Error:', error);
          });
      })
      .catch(error => {
        console.error('Fetch Error:', error);
      });


    // document.getElementById('popupbox').style.display = 'none';
    // document.getElementsByClassName('backdrop')[0].style.display = 'none';
  }
</script>
";
# #########################################################
#
# Mobilepay
#
# #########################################################

print "<tr bgcolor='#e0e0f0'>\n<td title='MobilePay'>Mobilepay Opsætning</td>\n";
print "<td title='MobilePay'>\n";
print "</td></tr>\n";  

$q=db_select("select var_value from settings where var_name = 'client_id' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$client_id = db_fetch_array($q)[0];
$q=db_select("select var_value from settings where var_name = 'client_secret' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$client_secret = db_fetch_array($q)[0];
$q=db_select("select var_value from settings where var_name = 'subscriptionKey' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$subscription = db_fetch_array($q)[0];
$q=db_select("select var_value from settings where var_name = 'MSN' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
$MSN = db_fetch_array($q)[0];

print "<tr>\n<td title='MobilePay'>Mobilepay Client ID</td>\n";
print "<td title='MobilePay'>\n";
print "<input name='mobilepay_client_id' class='inputbox' type='text' style='width:150px;' value='$client_id'>\n";
print "</td></tr>\n";  

print "<tr>\n<td title='MobilePay'>Mobilepay Client Secret</td>\n";
print "<td title='MobilePay'>\n";
print "<input name='mobilepay_client_secret' class='inputbox' type='text' style='width:150px;' value='$client_secret'>\n";
print "</td></tr>\n";  

print "<tr>\n<td title='MobilePay'>Mobilepay subscription key</td>\n";
print "<td title='MobilePay'>\n";
print "<input name='mobilepay_subscription' class='inputbox' type='text' style='width:150px;' value='$subscription'>\n";
print "</td></tr>\n";  

print "<tr>\n<td title='MobilePay'>Mobilepay merchant serial number</td>\n";
print "<td title='MobilePay'>\n";
print "<input name='mobilepay_msn' class='inputbox' type='text' style='width:150px;' value='$MSN'>\n";
print "</td></tr>\n";  

# If the system has been setup to run mobilepay intergrated, show extra options
if ($client_id) {
	$q=db_select("select var_value from settings where var_name = 'webhook_secret' AND var_grp = 'mobilepay'",__FILE__ . " linje " . __LINE__);
	$webhook = db_fetch_array($q)[0];
	if (!$webhook) {
		print "<tr>\n<td title='MobilePay'>Forbind webhook</td>\n";
		print "<td title='MobilePay'>\n";
		print "<button onclick=\"window.location.href='sys_div_func_includes/setup_mobilepay_webhook.php';\" class='inputbox' type='button' style='width:150px;'>Forbind</button>\n";
		print "</td></tr>\n";  
	}

	print "<tr bgcolor='#e0e0f0'>\n<td title='MobilePay'>Registerede QR koder</td>\n";
	print "<td title='MobilePay'>\n";
	print "</td></tr>\n";  

	$query = db_select("SELECT * FROM grupper WHERE art = 'POS' AND kodenr = 1 AND fiscal_year = $regnaar",__FILE__ . " linje " . __LINE__);
	$posnum = db_fetch_array($query)["box1"];
	for($i = 1; $i <= $posnum; $i++){
		$query = db_select("SELECT var_value FROM settings WHERE var_name = 'qrkodeuri' AND pos_id = $i AND var_grp='mobilepay'",__FILE__ . " linje " . __LINE__);
		if(db_num_rows($query) > 0) {
			$mobilepay = db_fetch_array($query)[0];
		} else {
			# #########################################################
			# 
			# Get auth token
			# 
			# #########################################################
			$url = 'https://api.vipps.no/accesstoken/get';

			$headers = array(
			    'Content-Type: application/json',
			    "Client_id: $client_id",
			    "Client_secret: $client_secret",
			    "Ocp-Apim-Subscription-Key: $subscription",
			    "Merchant-Serial-Number: $MSN",
			    'Vipps-System-Name: acme',
			    'Vipps-System-Version: 3.1.2',
			    'Vipps-System-Plugin-Name: acme-webshop',
			    'Vipps-System-Plugin-Version: 4.5.6',
			    'Content-Length: 0'
			);

			$ch = curl_init($url);

			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$response = curl_exec($ch);

			if ($response === false) {
			    // Handle curl error
			    $error = curl_error($ch);
			    echo "Curl error: " . $error;
			} else {
			    // Process response
			    $response = json_decode($response, true);
			    $accessToken = $response["access_token"];
			}

			curl_close($ch);
			
			# #########################################################
			# 
			# Create webhook
			# 
			# #########################################################
			$url = "https://api.vipps.no/qr/v1/merchant-callback/kasse$i";

			$headers = array(
			    "Authorization: Bearer $accessToken",
			    "Client_id: $client_id",
			    "Client_secret: $client_secret",
			    "Ocp-Apim-Subscription-Key: $subscription",
			    "Merchant-Serial-Number: $MSN",
			    'Content-Type: application/json',
			    'Vipps-System-Name: acme',
			    'Vipps-System-Version: 3.1.2',
			    'Vipps-System-Plugin-Name: acme-webshop',
			    'Vipps-System-Plugin-Version: 4.5.6',
			);

			$data = json_encode(array(
			    'locationDescription' => "Kasse $i",
			    'Qrimageformat' => 'SVG'
			));

			$ch = curl_init($url);

			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$response = curl_exec($ch);
			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			if ($response === false) {
			    // Handle curl error
			    $error = curl_error($ch);
			    echo "Curl error: " . $error;
			} else {
			    // Process response
			    echo "Response: " . $response . "\n";
			    echo "Status Code: " . $status_code . "\n";
			    $url = 'https://api.vipps.no/qr/v1/merchant-callback/kasse2';

				$headers = array(
				    'Content-Type: application/json',
				    "Authorization: Bearer $accessToken",
				    "Client_id: $client_id",
				    "Client_secret: $client_secret",
				    "Ocp-Apim-Subscription-Key: $subscription",
				    "Merchant-Serial-Number: $MSN",
				    'Accept: text/targetUrl',
				    'Vipps-System-Name: acme',
				    'Vipps-System-Version: 3.1.2',
				    'Vipps-System-Plugin-Name: acme-webshop',
				    'Vipps-System-Plugin-Version: 4.5.6',
				);

				$ch = curl_init($url);

				curl_setopt($ch, CURLOPT_HTTPGET, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

				$response = curl_exec($ch);
				$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

				if ($response === false) {
					// Handle curl error
					$error = curl_error($ch);
					echo "Curl error: " . $error;
				} else {
					// Process response
					echo "Response: " . $response . "\n";
					echo "Status Code: " . $status_code . "\n";
					$data = json_decode($response, true);
					$mobilepay = $data["qrImageUrl"];

					$qtxt="insert into settings (var_name, var_grp, var_value, var_description, pos_id) values ('qrkodeuri', 'mobilepay', '$mobilepay', 'A QR code URI to access the QR image on vipps server', $i)";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}

				curl_close($ch);
			}

			curl_close($ch);
		}

		print "<tr>\n<td title='MobilePay'>MobilePay QR for kasse #$i</td>\n";
		print "<td title='MobilePay'>\n";
		print "<a style='width:150px;' href='$mobilepay' target='_blank'>Se QR kode</a>\n";
		print "</td></tr>\n";  
	}
}


# #########################################################
#
# Mobilepay END
#
# #########################################################

// Nemhandel
$query = db_select("SELECT * FROM settings WHERE var_name = 'companyID' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__);
if(db_num_rows($query) <= 0) {
	echo "<tr><td><label>Opret i nemhandel (for at modtage/afsende electronisk fakture)</label></td>\n<td><input type='checkbox' name='nemhandel'></td></tr>\n";
} else {
	echo "<tr><td>Du er oprettet i nemhandel</td></tr>\n";
}

  # API key for copayone
  $qtxt = "SELECT var_value FROM settings WHERE var_name='copayone_auth'";
  $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));

  # Check if it exsists
  $APIKEY = $r ? $r[0] : "";

	$mtxt=findtekst(2108,$sprog_id);
	$mtitle=findtekst(2109,$sprog_id);
  print "<tr bgcolor='#e0e0f0'>\n<td title='$mtitle'><!-- Tekst 2108 -->$mtxt <!-- Tekst 2109 --></td>\n";
  print "<td title='$mtitle'>
    <input name='copay_id' class='inputbox' style='width:150px;' type='text' value='$APIKEY'>
  </td>\n</tr>\n";



	print "<tr><td colspan='2'>&nbsp;</td></tr>";
	print "<tr><td colspan='1'>&nbsp;</td><td style='text-align:center'>\n";
	print "     <input class='button green medium' name='submit' type=submit accesskey='g' value='".findtekst(471, $sprog_id)."'>\n";
	print "</td></tr>\n";
	print "</form>\n\n";

  # Setup flatpay popup
  print "
    <div class='backdrop' onclick='close_popup()'></div>
    <div id='popup-flatpay'>
      <h2>Flatpay ID</h2>
      <br>
      <span>Dit flatpay ID er det vi brugere for at godkende transaktioner med Flatpay, dette ID må du ikke dele, og vises derfor kun en gang til dig.</span><span>Dit login gemmes ikke men bruges kun til at danne dit unikke ID.</span>
      <br>
      <span>Brugernavn</span>
      <input class='inputbox' type='text' id='flatpay-username'>
      <span>Adgangskode</span>
      <input class='inputbox' type='password' id='flatpay-password'>
      <br>
      <br>
      <button onclick='get_guid()'>Generer</button>
      <button onclick='close_popup()'>Luk</button>
    </div>

    <script>
      function open_popup(){
        document.getElementById('popup-flatpay').style.display = 'block';
        document.getElementsByClassName('backdrop')[0].style.display = 'block';
      }

      function close_popup(){
        document.getElementById('popupbox').style.display = 'none';
        document.getElementById('popup-flatpay').style.display = 'none';
        document.getElementsByClassName('backdrop')[0].style.display = 'none';
      }

      close_popup();

      async function save_id(id){
        var res = await fetch(
          'diverseIncludes/save_flatpay_id.php',
          {
            method: 'post',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              'id': id
            }),
          }
        )
        location.reload();
      }

      async function get_guid(){
        var res = await fetch(
          'https://socket.flatpay.dk/socket/guid',
          {
            method: 'post',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              'username': document.getElementById('flatpay-username').value,
              'password': document.getElementById('flatpay-password').value
            }),
          }
        )
        console.log({
              'username': document.getElementById('flatpay-username').value,
              'password': document.getElementById('flatpay-password').value
            })
        if (res.status == 200) {
          const text = await res.text();
          close_popup();
          alert(`Dit Flatpay ID er \${text}, du vil kun blive vist dit ID denne gang, den vil automatisk blive indsat i systemet.`);
          save_id(text);
        } else {
          alert('Forkert brugernavn eller adgangskode.');
        }
      
      
      }
    </script>

";
} # endfunc div_valg

function ordre_valg() {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$hurtigfakt=$incl_moms=$folge_s_tekst=$negativt_lager=$straks_bogf=$vis_nul_lev=$orderNoteEnabled=NULL;

	$r=db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse'];
	$kodenr=$r['kodenr'];
	($r['box1']=='on')?$incl_moms="checked":$incl_moms=NULL;
	$rabatvareid=(int)$r['box2'];
	($r['box3']=='on')?$folge_s_tekst="checked":$folge_s_tekst=NULL;
	($r['box4']=='on')?$hurtigfakt="checked":$hurtigfakt=NULL;
	if (strstr($r['box5'],';')) {
		list($straks_deb,$straks_kred)=explode(';',$r['box5']); #20170404
	} else {
		$straks_deb=$r['box5'];$straks_kred=$r['box5'];
	}
	($straks_deb=='on')?$straks_deb='checked':$straks_deb=NULL;
	($straks_kred=='on')?$straks_kred='checked':$straks_kred=NULL;
	($r['box6']=='on')?$fifo="checked":$fifo=NULL;
	$kontantkonto=$r['box7'];
	($r['box8']=='on')?$vis_nul_lev="checked":$vis_nul_lev=NULL;
	($r['box9']=='on')?$negativt_lager="checked":$negativt_lager=NULL;
	$kortkonto=$r['box10'];
	($r['box11']=='on')?$advar_lav_beh="checked":$advar_lav_beh=NULL;
	($r['box12']=='on')?$procentfakt="checked":$procentfakt=NULL;
	list($procenttillag,$procentvare)=explode(chr(9),$r['box13']);
	($r['box14']=='on')?$samlet_pris="checked":$samlet_pris=NULL;

	$qtxt="select var_value from settings where var_name='orderNoteEnabled'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		if ($r['var_value']) $orderNoteEnabled='checked';
	} else {
		$orderNoteEnabled=NULL;
	}

	$portovarenr = get_settings_value("porto_varnr", "ordre", "");
	$debitoripad = get_settings_value("debitoripad", "ordre", "off");
	if ($debitoripad === "on") {
		$debitoripad = "checked";
	}

	$rabatvarenr=NULL;
	if ($rabatvareid) {
		$qtxt = "select varenr from varer where id = '$rabatvareid'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $rabatvarenr=$r['varenr'];
	}
	#	print "<tr><td colspan='6'><br></td></tr>";
#	print "<tr><td title='".findtekst(732,$sprog_id)."'>".findtekst(731,$sprog_id)."</td><td title='".findtekst(732,$sprog_id)."'>
#		<input name='box6' type='checkbox' $box6></td></tr>";

	$r=db_fetch_array(db_select("select box6,box8 from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__));
	# OBS $box1,2,3,4,5,7,9 bruges under shop valg!!
	$kostmetode=$r['box6']; #0=opdater ikke kostpris,1=snitpris;2=sidste_købspris
	$kostbeskrivelse[0]="Opdater ikke kostpris";
	$kostbeskrivelse[1]="Gennemsnitspris";
	$kostbeskrivelse[2]="Genanskaffelsespris";
	$saetvareid=$r['box8'];
	if ($saetvareid) {
		$r=db_fetch_array(db_select("select varenr from varer where id = '$saetvareid'",__FILE__ . " linje " . __LINE__));
		$saetvarenr=$r['varenr'];
	}

	print "<form name=diverse action=diverse.php?sektion=ordre_valg method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(786,$sprog_id)."</u></b></td></tr>";
	print "<tr><td colspan='6'><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td title='".findtekst(197,$sprog_id)."'>".findtekst(196,$sprog_id)."</td><td><INPUT title='".findtekst(197,$sprog_id)."' class='inputbox' type='checkbox' name=box1 $incl_moms></td></tr>";
	print "<tr><td title='".findtekst(188,$sprog_id)."'>".findtekst(164,$sprog_id)."</td><td><INPUT title='".findtekst(188,$sprog_id)."' class='inputbox' type='checkbox' name=box3 $folge_s_tekst></td></tr>";
	print "<tr><td title='".findtekst(189,$sprog_id)."'>".findtekst(169,$sprog_id)."</td><td><INPUT title='".findtekst(189,$sprog_id)."' class='inputbox' type='checkbox' name=box8 $vis_nul_lev></td></tr>";
	$qtxt = "select id from grupper where art = 'VG' and box9='on'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $hurtigfakt="onclick='return false'";
	print "<tr><td title='".findtekst(190,$sprog_id)."'>".findtekst(165,$sprog_id)."</td><td><INPUT title='".findtekst(190,$sprog_id)."' class='inputbox' type='checkbox' name='box4' $hurtigfakt></td></tr>";
	print "<tr><td title='".findtekst(191,$sprog_id)."'>".findtekst(166,$sprog_id)."</td><td><INPUT title='".findtekst(191,$sprog_id)."' class='inputbox' type='checkbox' name='straks_deb' $straks_deb></td></tr>";
	print "<tr><td title='".findtekst(214,$sprog_id)."'>".findtekst(213,$sprog_id)."</td><td><INPUT title='".findtekst(214,$sprog_id)."' class='inputbox' type='checkbox' name='straks_kred' $straks_kred></td></tr>";
	print "<tr><td title='".findtekst(313,$sprog_id)."'>".findtekst(314,$sprog_id)."</td><td><INPUT title='".findtekst(313,$sprog_id)."' class='inputbox' type='checkbox' name='box6' $fifo></td></tr>";
	print "<tr><td title='".findtekst(732,$sprog_id)."'>".findtekst(731,$sprog_id)."</td><td colspan='1'><SELECT title='".findtekst(732,$sprog_id)."'class='inputbox' name='kostmetode'>";
	for($i=0;$i<3;$i++) {
		if ($i==$kostmetode) print "<option value=$i>$kostbeskrivelse[$i]</option>";
	}
	for($i=0;$i<3;$i++) {
		if ($i!=$kostmetode) print "<option value=$i>$kostbeskrivelse[$i]</option>";
	}
	print "</SELECT></td></tr>";
	if ($kostmetode>=1) {
		print "<tr><td></td><td colspan='2'><a href='../includes/opdat_kostpriser.php?metode=$kostmetode' target='blank'><INPUT title='".findtekst(738,$sprog_id)."' type='button' value='".findtekst(739,$sprog_id)."'></a></td>";
	}
	print "</tr>";
	print "<tr><td title='".findtekst(192,$sprog_id)."'>".findtekst(183,$sprog_id)."</td><td><INPUT title='".findtekst(192,$sprog_id)."' class='inputbox' type='checkbox' name='box9' $negativt_lager></td></tr>";
	print "<tr><td title='".findtekst(743,$sprog_id)."'>".findtekst(742,$sprog_id)."</td><td><INPUT title='".findtekst(743,$sprog_id)."' class='inputbox' type='checkbox' name='box14' $samlet_pris></td></tr>";
	print "<tr><td title='".findtekst(680,$sprog_id)."'>".findtekst(714,$sprog_id)."</td><td><INPUT title='".findtekst(680,$sprog_id)."' class='inputbox' type='checkbox' name='box11' $advar_lav_beh></td></tr>";
	print "<tr><td title='".findtekst(682,$sprog_id)."'>".findtekst(681,$sprog_id)."</td><td><INPUT title='".findtekst(682,$sprog_id)."' class='inputbox' type='checkbox' name='box12' $procentfakt></td></tr>";
	print "<tr><td title='".findtekst(684,$sprog_id)."'>".findtekst(683,$sprog_id)."</td><td><INPUT title='".findtekst(684,$sprog_id)."' class='inputbox' type='text' style='width:35px;text-align:right;' name='procenttillag' value='$procenttillag'>%</td></tr>";
	print "<tr><td title='".findtekst(686,$sprog_id)."'>".findtekst(685,$sprog_id)."</td><td><INPUT title='".findtekst(686,$sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='procentvare' value='$procentvare'></td></tr>";
	print "<tr><td title='".findtekst(288,$sprog_id)."'>".findtekst(287,$sprog_id)."</td><td><INPUT title='".findtekst(288,$sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='box2' value='$rabatvarenr'></td></tr>";
	if ($samlet_pris) print "<tr><td title='".findtekst(745,$sprog_id)."'>".findtekst(744,$sprog_id)."</td><td><INPUT title='".findtekst(745,$sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='saetvarenr' value='$saetvarenr'></td></tr>";
	print "<tr><td title='".findtekst(688,$sprog_id)."'>".findtekst(687,$sprog_id)."</td><td><INPUT title='".findtekst(688,$sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='box7' value='$kontantkonto'></td></tr>";
	print "<tr><td title='".findtekst(690,$sprog_id)."'>".findtekst(689,$sprog_id)."</td><td><INPUT title='".findtekst(690,$sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='box10' value='$kortkonto'></td></tr>";
	print "<tr><td title='".findtekst(1711,$sprog_id)."'>".findtekst(1714,$sprog_id)."</td><td><INPUT title='".findtekst(1712,$sprog_id)."' class='inputbox' type='checkbox' name='orderNoteEnabled' $orderNoteEnabled></td></tr>";
	print "<tr><td title='".findtekst(3069,$sprog_id)."'>".findtekst(3068,$sprog_id)."</td><td><INPUT title='".findtekst(3069,$sprog_id)."' class='inputbox' type='checkbox' name='debitoripad' $debitoripad></td></tr>";
	print "<tr><td title='".findtekst(690,$sprog_id)."'>".findtekst(2400,$sprog_id)."</td><td><INPUT title='".findtekst(2401,$sprog_id)."' class='inputbox' type='text' style='width:70px;text-align:right;' name='portovarenr' value='$portovarenr'></td></tr>";

	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input class='button green medium' type=submit accesskey='g' value='".findtekst(471, $sprog_id)."' name='submit'></td>";
	print "</form>";
} # endfunc ordre_valg

# ---------------------- varianter ----------------------

function variant_valg() {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	global $db;

	print "<form name='diverse' action='diverse.php?sektion=variant_valg' method='post'>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".str_replace("php","html",findtekst(472,$sprog_id))."<!--tekst 472--></u></b></td></tr>";
	if ($delete_var_type=if_isset($_GET['delete_var_type'])) db_modify("delete from variant_typer where id = '$delete_var_type'",__FILE__ . " linje " . __LINE__);
	if ($delete_variant=if_isset($_GET['delete_variant'])) {
		db_modify("delete from variant_typer where variant_id = '$delete_variant'",__FILE__ . " linje " . __LINE__);
		db_modify("delete from varianter where id = '$delete_variant'",__FILE__ . " linje " . __LINE__);
	}
	if ($rename_var_type=if_isset($_GET['rename_var_type'])) {
		$r=db_fetch_array(db_select("select beskrivelse from variant_typer where id=$rename_var_type",__FILE__ . " linje " . __LINE__));
		print "<input type='hidden' name='rename_var_type' value='$rename_var_type'>";
		print "<tr><td>".findtekst(473,$sprog_id)."<!--tekst 473--></td><td></td><td><input type='text' name='var_type_beskrivelse' value = '$r[beskrivelse]'</td></tr>";
	}elseif ($rename_variant=if_isset($_GET['rename_variant'])) {
		$r=db_fetch_array(db_select("select beskrivelse from varianter where id=$rename_variant",__FILE__ . " linje " . __LINE__));
		print "<input type='hidden' name='rename_varianter' value='$rename_variant'>";
		print "<tr><td>".findtekst(474,$sprog_id)."<!--tekst 474--></td><td></td><td><input type='text' name='variant_beskrivelse' value = '$r[beskrivelse]'</td></tr>";
	} else {
		$x=0;
		$q=db_select("select * from varianter order by beskrivelse",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$x++;
			$variant_id[$x]=$r['id'];
			$variant_beskrivelse[$x]=$r['beskrivelse'];
			$y=0;
			$q2=db_select("select * from variant_typer where variant_id=$variant_id[$x] order by beskrivelse",__FILE__ . " linje " . __LINE__);
			while ($r2=db_fetch_array($q2)) {
				$y++;
				$var_type_id[$x][$y]=$r2['id'];
				$var_type_beskrivelse[$x][$y]=$r2['beskrivelse'];
			}
			$var_type_antal[$x]=$y;
		}
		$variant_antal=$x;
		print "<tr><td></td><td><b>".findtekst(475,$sprog_id)."<!--tekst 475--></b></td><td><b>".findtekst(476,$sprog_id)."<!--tekst 476--></b></td></tr>";
		for ($x=1;$x<=$variant_antal;$x++){
			print "<tr><td></td><td>$variant_beskrivelse[$x]</td></td><td>";
			print "<td><span title='".findtekst(477,$sprog_id)."'><!--tekst 477--><a href='diverse.php?sektion=variant_valg&rename_variant=".$variant_id[$x]."' onclick=\"return confirm('".findtekst(483,$sprog_id)."')\"><img src=../ikoner/rename.png border=0></a></span>\n";
			print "<span title='".findtekst(478,$sprog_id)."'><!--tekst 478--><a href='diverse.php?sektion=variant_valg&delete_variant=".$variant_id[$x]."' onclick=\"return confirm('".findtekst(481,$sprog_id)."')\"><img src=../ikoner/delete.png border=0></a></span></td></tr>\n";
			for ($y=1;$y<=$var_type_antal[$x];$y++){
#				if ($y>1)
				print "<tr></td><td><td></td>";
				print "<td>".$var_type_beskrivelse[$x][$y]."</td>";
				print "<td><span title='".findtekst(479,$sprog_id)."'><!--tekst 479--><a href='diverse.php?sektion=variant_valg&rename_var_type=".$var_type_id[$x][$y]."' onclick=\"return confirm('".findtekst(484,$sprog_id)."')\"><img src=../ikoner/rename.png border=0></a></span>\n";
				print "<span title='".findtekst(480,$sprog_id)."'><!--tekst 480--><a href='diverse.php?sektion=variant_valg&delete_var_type=".$var_type_id[$x][$y]."' onclick=\"return confirm('".findtekst(482,$sprog_id)."')\"><img src=../ikoner/delete.png border=0></a></span></td></tr>\n";
			}
			print "<input type='hidden' name='variant_id[$x]' value='$variant_id[$x]'>";
			print "<tr><td title='".findtekst(486,$sprog_id)."'><!--tekst 486-->".findtekst(473,$sprog_id)."<!--tekst 473--></td><td></td><td title='".findtekst(486,$sprog_id)."'><!--tekst 486--><input type='text' name='var_type_beskrivelse[$x]'</td></tr>";
		}
		print "<input type='hidden' name='variant_antal' value='$variant_antal'>";
		print "<tr><td title='".findtekst(485,$sprog_id)."'><!--tekst 485-->".findtekst(474,$sprog_id)."<!--tekst 474--></td><td title='".findtekst(485,$sprog_id)."'><!--tekst 485--><input type='text' name='variant_beskrivelse'</td></tr>";
	}
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey='g' value='".findtekst(471,$sprog_id)."' name='submit'><!--tekst 471--></td>";
	print "</form>";

} # endfunc variant_valg

function shop_valg() {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	global $db;
	global $labelprint;

#	$hurtigfakt=NULL; $incl_moms=NULL; $folge_s_tekst=NULL; $negativt_lager=NULL; $straks_bogf=NULL; $vis_nul_lev=NULL;
	$q = db_select("select * from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse'];$kodenr=$r['kodenr'];$box2=trim($r['box2']);$box3=trim($r['box3']);$box4=trim($r['box4']);$box5=trim($r['box5']);$box7=trim($r['box7']);$box9=trim($r['box9']);
	# OBS $box1 bruges under vare_valg!!
	# OBS $box8 bruges under ordrelaterede valg!!

	print "<form name=diverse action=diverse.php?sektion=shop_valg method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";

	if ($box2=='!') $box3='1';
	print "<tr><td><br></td></tr>";
	print "<tr><td title='".findtekst(695,$sprog_id)."'><!--tekst 826-->".findtekst(695,$sprog_id)."<!--tekst 826--></td><td colspan='3' title='".findtekst(695,$sprog_id)."'><select style='text-align:left;width:300px;' name='box3'>";
	if (!$box3) print "<option value='0'>".findtekst(697,$sprog_id)."<!--tekst 697--></option>";
	if ($box3=='1') print "<option value='1'>".findtekst(698,$sprog_id)."<!--tekst 698--></option>";
	if ($box3=='2') print "<option value='2'>".findtekst(699,$sprog_id)."<!--tekst 829--></option>";
	if ($box3) print "<option value='0'>".findtekst(697,$sprog_id)."<!--tekst 697--></option>";
	if ($box3!='1') print "<option value='1'>".findtekst(698,$sprog_id)."<!--tekst 698--></option>";
	if ($box3!='2') print "<option value='2'>".findtekst(699,$sprog_id)."<!--tekst 829--></option>";
	print "</select></td></tr>";
	if ($box3=='2') {
		print "<tr><td title='".findtekst(503,$sprog_id)."'><!--tekst 503-->".findtekst(504,$sprog_id)."<!--tekst 504--></td><td colspan='3' title='".findtekst(503,$sprog_id)."'><!--tekst 503--><input type='text' style='text-align:left;width:300px;' name='box2' value = '$box2'</td></tr>";
		print "<tr><td title=''>".findtekst(733,$sprog_id)."<!--tekst 733--></td><td colspan='3' title='".findtekst(733,$sprog_id)."'><!--tekst 733--><select style='text-align:left;width:300px;' name='box7'>";
		if ($box7=='UTF-8') {
			print "<option>UTF-8</option>";
			print "<option>ISO-8859-1</option>";
		} else {
			print "<option>ISO-8859-1</option>";
			print "<option>UTF-8</option>";
		}
		print "</select></td></tr>";
		if ($apifil=$box2) {
			$filnavn=mt_rand().".csv";
			if (substr($apifil,0,4)=='http') { #20150608
				print "<tr><td title='".findtekst(740,$sprog_id)."'><!--tekst 740-->".findtekst(741,$sprog_id)."<!--tekst 741--></td><td colspan='3'  title='".findtekst(740,$sprog_id)."'><!--tekst 740--><a href=../api/hent_varer.php target='blank'><input style='text-align:center;width:300px;' type='button' value='".findtekst(741,$sprog_id)."'><!--tekst 749--></a></td></tr>";
				$apifil=str_replace("/?","sync_saldi_kat.php?",$apifil);
				$apifil=$apifil."&saldi_db=$db&filnavn=$filnavn";
#				print "<tr><td title='".findtekst(678,$sprog_id)."'><!--tekst 678-->".findtekst(679,$sprog_id)."<!--tekst 679--></td><td colspan='3'  title='".findtekst(678,$sprog_id)."'><!--tekst 678--><a href=$apifil target='blank'><input style='text-align:center;width:300px;' type='button' value='".findtekst(679,$sprog_id)."'><!--tekst 679--></a></td></tr>";
#				print "<tr><td colspan='3'><span title='Klik her for at hente nye ordrer fra shop'><a href=$apifil target='_blank'>SHOP import</a</span></td></tr>";
			}
		}
	} elseif ($box3=='1') {
		print "<tr><td title='".findtekst(691,$sprog_id)."'><!--tekst 821-->".findtekst(692,$sprog_id)."<!--tekst 822--></td><td colspan='3' title='".findtekst(691,$sprog_id)."'><!--tekst 621--><input type='text' style='text-align:left;width:300px;' name='box4' value = '$box4'</td></tr>";
		print "<tr><td title='".findtekst(752,$sprog_id)."'><!--tekst 752-->".findtekst(753,$sprog_id)."<!--tekst 753--></td><td colspan='3' title='".findtekst(752,$sprog_id)."'><!--tekst 752--><input type='text' style='text-align:left;width:300px;' name='box9' value = '$box9'</td></tr>";
		print "<tr><td title='".findtekst(693,$sprog_id)."'><!--tekst 823-->".findtekst(694,$sprog_id)."<!--tekst 824--></td><td colspan='3' title='".findtekst(693,$sprog_id)."'><!--tekst 823--><input type='text' style='text-align:left;width:300px;' name='box5' value = '$box5'</td></tr>";
	}
	print "<tr><td>";
	print "<br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey='g' value='".findtekst(471,$sprog_id)."' name='submit'><!--tekst 471--></td>";
	print "</form>";
	print "<tr><td colspan='6'><hr></td></tr>";
} # endfunc shop_valg

function api_valg() {
	global $bgcolor, $bgcolor5, $bruger_id, $db, $sprog_id;
	$r=db_fetch_array(db_select("select * from grupper where art = 'API' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$api_key=trim($r['box1']);
	$ip_list=trim($r['box2']);
	$api_bruger=trim($r['box3']);
	$api_fil=trim($r['box4']);

	$x=0;
	$q=db_select("select * from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)){
		if (strpos($r['rettigheder'],'1')===false) {
			$userId[$x]=$r['id'];
			$userName[$x]=$r['brugernavn'];
			$x++;
		}
	}
	if (!$api_key) {
		$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_!#+-*.:,;';
		$api_key='';
		for ($x=0;$x<36;$x++) $api_key .= substr($chars,rand(0,strlen($chars)-1),1);
	}

	print "<form name=diverse action=diverse.php?sektion=api_valg method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr><td><br></td></tr>";
	list($tmp,$folder,$tmp)=explode('/',$_SERVER['REQUEST_URI'],3);
	$url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/$folder/api";
	if (count($userId)) {
		if ($api_bruger) {
			print "<tr><td title='".findtekst(832,$sprog_id)."'><!--tekst 832-->".findtekst(831,$sprog_id)."<!--tekst 831--></td><td colspan='3' title='".findtekst(832,$sprog_id)."'><!--tekst 832-->$db</td></tr>";
			print "<tr><td title='".findtekst(836,$sprog_id)."'><!--tekst 836-->".findtekst(835,$sprog_id)."<!--tekst 835--></td><td colspan='3' title='".findtekst(836,$sprog_id)."'><!--tekst 836-->$url</td></tr>";
			print "<tr><td title='".findtekst(820,$sprog_id)."'><!--tekst 820-->".findtekst(819,$sprog_id)."<!--tekst 819--></td><td colspan='3' title='".findtekst(819,$sprog_id)."'><!--tekst 819--><input type='text' style='text-align:left;width:300px;' name='api_key' value = '$api_key'></td></tr>";
			print "<tr><td title='".findtekst(822,$sprog_id)."'><!--tekst 822-->".findtekst(821,$sprog_id)."<!--tekst 821--></td><td colspan='3' title='".findtekst(822,$sprog_id)."'><!--tekst 822--><input type='text' style='text-align:left;width:300px;' name='ip_list' value = '$ip_list'></td></tr>";
			print "<tr><td title='".findtekst(830,$sprog_id)."'><!--tekst 830-->".findtekst(829,$sprog_id)."<!--tekst 829--></td><td colspan='3' title='".findtekst(830,$sprog_id)."'><!--tekst 822--><input type='text' style='text-align:left;width:300px;' name='api_fil' value = '$api_fil'></td></tr>";
		} else {
			print "<input type='hidden' style='text-align:left;width:300px;' name='api_key' value = '$api_key'>";
			print "<input type='hidden' style='text-align:left;width:300px;' name='ip_list' value = '$ip_list'>";
			print "<input type='hidden' style='text-align:left;width:300px;' name='api_fil' value = '$api_fil'>";
		}
		print "<tr><td title='".findtekst(824,$sprog_id)."'><!--tekst 824-->".findtekst(823,$sprog_id)."<!--tekst 823--></td><td colspan='3' title='".findtekst(824,$sprog_id)."'><!--tekst 824--><select style='text-align:left;width:300px;' name='api_bruger'>";
		if ($api_bruger) {
			for ($x=0;$x<count($userId);$x++){
				if ($api_bruger==$userId[$x]) print "<option value='$userId[$x]'>$userName[$x]</option>";
			}
		}
		print "<option value=''></option>";
		for ($x=0;$x<count($userId);$x++){
			if ($api_bruger!=$userId[$x]) print "<option value='$userId[$x]'>$userName[$x]</option>";
		}
		print "</select></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td><br></td><td colspan='1'><input type=submit style='text-align:center;width:300px;' accesskey='g' value='".findtekst(471,$sprog_id)."' name='submit'><!--tekst 471--></td></tr>";
		print "</form>";
		print "<tr><td colspan='6'><hr></td></tr>";
		print "<tr><td title='".findtekst(740,$sprog_id)."'><!--tekst 740-->".findtekst(741,$sprog_id)."<!--tekst 741--></td><td colspan='3' title='".findtekst(740,$sprog_id)."'><!--tekst 740--><a href=".$_SERVER['PHP_SELF']."?sektion=api_valg&varesync=1><input style='text-align:center;width:300px;' type='button' value='".findtekst(741,$sprog_id)."'><!--tekst 749--></a></td></tr>";
		print "<tr><td title='".findtekst(1726, $sprog_id)."'><!--tekst 740-->Opdater fra shop<!--tekst 741--></td><td colspan='3' title='Opdaterer beskrivelse, stregkode og pris fra shop'><!--tekst 740--><a href=".$_SERVER['PHP_SELF']."?sektion=api_valg&varesync=2><input style='text-align:center;width:300px;' type='button' value='Opdater fra shop'><!--tekst 749--></a></td></tr>";
	} else print "<tr><td colspan='2'>".findtekst(825,$sprog_id)."</td></tr>";
	print "<tr><td colspan='6'><hr></td></tr>";
	if (isset($_GET['varesync']) && $_GET['varesync']) {
		include ("../api/varesync.php");
		varesync($_GET['varesync']);
	}
} # endfunc api_valg

function labels($valg) {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
	global $db;
	global $labelName;
	global $labelprint;
	
	if (!$labelName) {
		$labelName=if_isset($_POST['labelName']);
		if (isset($_POST['newLabelName'])) $labelName=$_POST['newLabelName'];
	}
	($valg=='box1')?$txt='Vare':$txt='Adresse';
	if (isset($_POST['newLabel'])) {
		print "<form name='diverse' action='diverse.php?sektion=labels&valg=$valg' method='post'>";
		print "<tr bgcolor='$bgcolor5'><td colspan='6' title='".findtekst(737,$sprog_id)."'><!--tekst 737-->";
		print "<b><u>".findtekst(736,$sprog_id)."<!--tekst 736--> ($txt)</u></b></td></tr>";
		$qtxt="select $valg from grupper where art = 'LABEL'";
		if($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $labelText=$r['box1'];
		print "<tr><td><br><br></td></tr>";
		print "<tr><td  valign='top' align='left' title='".findtekst(503,$sprog_id)."'><b>".findtekst(914,$sprog_id)."</b><br>";
		print "<input type='text' style='width:200px' name='newLabelName' pattern='[a-zA-Z0-9+.-]+'><br>";
		print "".findtekst(1309,$sprog_id)."</td>";
		print "<td valign='top' align = 'left'><b>".findtekst(803,$sprog_id)."</b><br><select style='width:200px' name='labelTemplate'>";
		print "<option value=''></option>";
		print "<option value='A4Label38x21_ens.txt'>".findtekst(1310,$sprog_id)."</option>";
		print "<option value='A4Label38x21.txt'>".findtekst(1311,$sprog_id)."</option>";
		print "<option value='BrotherLabel22606.txt'>".findtekst(1312,$sprog_id)."</option>";
		print "<option value='BrotherLabel22606MS.txt'>".findtekst(1313,$sprog_id)."</option>";
		print "<option value='DymoLabelArt11354.txt'>Dymo 11354</option>";
		print "<option value='DymoLabelArt11354MS.txt'>".findtekst(1314,$sprog_id)."</option>";
		print "</td></select></td>";
		print "<td valign='top' align = 'center'>&nbsp<br>";
		print "<input type='submit' style='width:200px' accesskey='s' value='".findtekst(1232,$sprog_id)."' name='createNewLabel'>";
		print "</td></tr></form>";
	} elseif ($valg) {
		$x=0;
		$labelNames=array();
		$qtxt="select id, labeltype, labelname from labels order by labelname";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$labelNames[$x]=$r['labelname'];
			$x++;
		}
		if (!$labelName) $labelName='Standard';
		$txt.=" - $labelName";
		print "<tr bgcolor='$bgcolor5'><td colspan='4' title='".findtekst(737,$sprog_id)."'><!--tekst 737-->";
		print "<b><u>".findtekst(736,$sprog_id)."<!--tekst 736--> ($txt)</u></b></td></tr>";
		if ($valg=='box1') {
			$qtxt="select id, labeltype, labeltext from labels where labelname = '$labelName' ";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$labelText=$r['labeltext'];
			$labelType=$r['labeltype'];
		}
		if (empty($labelType)) $labelType='sheet';                 #20210712
		if ($labelName=='Standard' && empty($labelText)) {
			$qtxt="select $valg from grupper where art = 'LABEL'";
			if($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $labelText=$r[$valg];
		}
		if ($valg=='box1') {
			if (count($labelNames) > 1) {
		    print "<form name='labelvalg' action='diverse.php?sektion=labels&valg=$valg' method='post'>";
				print "<tr><td align='center' colspan='4'>";
				print "Vælg label <select style='width:200px' name='labelName' onchange='javascript:this.form.submit()'>";
				for ($x=0;$x<count($labelNames);$x++) {
				if ($labelName==$labelNames[$x]) print "<option value='$labelNames[$x]'>$labelNames[$x]</option>";
				}
				for ($x=0;$x<count($labelNames);$x++) {
					if ($labelName!=$labelNames[$x]) print "<option value='$labelNames[$x]'>$labelNames[$x]</option>";
				}
				print "</select><br>";
			}
			print "<input type='submit' style='border:0px;width:100%;height:1px' value=' ' name='labelvalg'></form></td></tr>";
		}
		print "<form name='diverse' action='diverse.php?sektion=labels&valg=$valg' method='post'>";
		print "<input type='hidden' name='labelName' value='$labelName'></a></td></tr>";
		print "<tr><td align='center' colspan='4' title='".findtekst(503,$sprog_id)."'><!--tekst 503-->";
		print "<textarea style='width:100%;height:500px' name='labelText'>$labelText</textarea></td></tr>";
		print "<td  align='center' colspan='4'>";
		print "<select name='labelType' style='width:100px'>";
		if ($labelType=='sheet') print "<option value='sheet'>A4 ark</option><option value='label'>".findtekst(1315,$sprog_id)."</option>";
		else print "<option value='label'>".findtekst(1315,$sprog_id)."</option><option value='sheet'>A4 ark</option>";
		print "</select>";
		print "<input type=submit style='width:200px' accesskey='g' value='".findtekst(471,$sprog_id)."' name='saveLabel'><!--tekst 471-->";
		if ($valg=='box1') {
			print "&nbsp;<input type=submit style='width:200px' accesskey='n' value='".findtekst(39,$sprog_id)." ".lcfirst(findtekst(1316,$sprog_id))."' name='newLabel'>";
			if ($labelName!='Standard') {
				$txt="".findtekst(1317,$sprog_id)." $labelName ?";
				print "&nbsp;<input type=submit style='width:200px' value='".findtekst(1099,$sprog_id)." ".lcfirst(findtekst(1316,$sprog_id))."' name='deleteLabel' onclick=\"return confirm('$txt')\">";
			}
		}
		print "</td></form>";
	} else {
		print "<tr><td>".findtekst(1308,$sprog_id)."</td><td>";
		print "<a href='diverse.php?sektion=labels&valg=box1'>";
		print "<input type='button'  style='width:100px' value='".findtekst(110,$sprog_id)."'></a></td></tr>";
		print "<tr><td></td><td><a href=diverse.php?sektion=labels&valg=box2>";
		print "<input type='button' style='width:100px' value='".findtekst(1049,$sprog_id)."'></a></td></tr>";
	}
} # endfunc labels

function prislister()
{
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$filtyper = $filtypebeskrivelse = $lev_id = $prislister = array();
	$antal=0;
	$q=db_select("select * from grupper where art = 'PL' order by beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$antal++;
		$id[$antal]=$r['id'];
		$beskrivelse[$antal]=$r['beskrivelse'];
		$lev_id[$antal]=$r['box1'];
		$prisfil[$antal]=$r['box2'];
		$opdateret[$antal]=$r['box3'];
		$aktiv[$antal]=$r['box4'];
		$rabat[$antal]=$r['box6'];
		$gruppe[$antal]=$r['box8'];
		$filtype[$antal]=$r['box9'];
	}

	$vgrpantal=0;
	$q=db_select("select * from grupper where art = 'VG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$vgrpantal++;
		$vgrp[$vgrpantal]=$r['kodenr'];
		$vgbesk[$vgrpantal]=$r['beskrivelse'];
	}

	$filtyperantal=0;
/*
	$q=db_select("select * from grupper where art = 'FT' order by kodenr",__FILE__ . " linje " . __LINE__);
	if ( db_fetch_array($q) ) {
		while ($r = db_fetch_array($q)) {
			$filtyperantal++;
			$filtyper[$filtyperantal]=$r['kodenr'];
			$filtyperbesk[$filtyperantal]=$r['beskrivelse'];
		}
	} else {
*/
		$filtyperantal++;
		$filtyper[$filtyperantal]="csv";
		$filtypebeskrivelse[$filtyperantal]="Kommasepareret";
		$filtyperantal++;
		$filtyper[$filtyperantal]="tab";
		$filtypebeskrivelse[$filtyperantal]="Tabulator";
		$filtyperantal++;
		$filtyper[$filtyperantal]="sql";
		$filtypebeskrivelse[$filtyperantal]="Databasefil (SQL-dump)";
		$filtyperantal++;
		$filtyper[$filtyperantal]="html";
		$filtypebeskrivelse[$filtyperantal]="HTML-celler (td)";
#	}

#	if (!in_array('Solar',$beskrivelse)) {
#		$antal++;
#		$beskrivelse[$antal]='Solar';
#		$prisfil[$antal]="../prislister/solar.txt";
#	}

        print "<tr bgcolor='$bgcolor5'><td colspan='10'><b><u>".findtekst(792,$sprog_id)."</u></b></td></tr>\n";
        print "<tr><td colspan='10'>\n";
#cho $q;
	print "<p>".findtekst(1318,$sprog_id)."</p>\n";
	print "</td></tr>\n";

	print "<form name='diverse' action='diverse.php?sektion=prislister' method='post'>\n";
	print "<input type='hidden' name='antal' value='$antal'>\n";
	print "<tr><td colspan='10'><hr></td></tr>\n";
	print "<tr bgcolor='$bgcolor5'>\n";
	print "<td><b>".str_replace('er','e',findtekst(427,$sprog_id))."<!--tekst 427--></b></td>\n";
	print "<td><b></b>".findtekst(988,$sprog_id)."</td>\n";
	print "<td><b></b>".findtekst(1319,$sprog_id)."</td>\n";
	print "<td><b></b>".findtekst(1320,$sprog_id)."</td>\n";
	print "<td><b>".findtekst(428,$sprog_id)."<!--tekst 428--></b></td>\n";
	print "<td><b>".findtekst(429,$sprog_id)."<!--tekst 429--></b></td>\n";
	print "<td><b>".findtekst(1321,$sprog_id)."</b></td>\n";
	print "<td><b>".findtekst(430,$sprog_id)."<!--tekst 430--></b></td>\n"; # 20160226c start
	$slet = findtekst(1099,$sprog_id);
	print "<td><b>$slet</b></td>\n";
	print "</tr>\n"; # 20160226c slut
	for ($x=1;$x<=$antal;$x++) {
		print "<input type='hidden' name='beskrivelse[$x]' value='$beskrivelse[$x]'>\n";
		print "<input type='hidden' name='prisfil[$x]' value='$prisfil[$x]'>\n";
		print "<input type='hidden' name='id[$x]' value='$id[$x]'>\n";
		print "<tr>\n";
		$title="".findtekst(1331,$sprog_id)." ".lcfirst(findtekst(646,$sprog_id)).".";
		print "<td title='$title'><input class='inputbox' type='text' size='18' name='beskrivelse[$x]' value='".$beskrivelse[$x]."' /></td>\n";
		$title="".findtekst(1331,$sprog_id)." ".findtekst(988,$sprog_id).".";
		print "<td title='$title'><select class='inputbox' type='text' name='lev_id[$x]' />\n"; # 20120226d start
		$levvalg="";
		$q1 = db_select("select id, kontonr, firmanavn from adresser where art = 'K' order by firmanavn",__FILE__ . " linje " . __LINE__);
		while ($levrk = db_fetch_array($q1))
		{
			if ( $levrk['id'] == $lev_id[$x] ) {
				$levvalg.="    <option value='".$levrk['id']."' title='".$levrk['firmanavn']."'>";
				if ( strlen($levrk['firmanavn'])>20 ) {
					$levvalg.=substr($levrk['firmanavn'],0,20)."...";
				} else {
					$levvalg.=$levrk['firmanavn'];
				}
				$levvalg.="</option>\n";
			}
		}

		$q2 = db_select("select id, kontonr, firmanavn from adresser where art = 'K' order by firmanavn",__FILE__ . " linje " . __LINE__);
		while ($levrk = db_fetch_array($q2))
		{
			if ( strlen($levvalg) == 0 ) $levvalg="     <option value='0'>Ingen valgt - vælg en</option>\n";
			if ( $levrk['id'] != $lev_id[$x] ) {
				$levvalg.="    <option value='".$levrk['id']."' title='".$levrk['firmanavn']."'>";
				if ( strlen($levrk['firmanavn'])>20 ) {
					$levvalg.=substr($levrk['firmanavn'],0,20)."...";
				} else {
					$levvalg.=$levrk['firmanavn'];
				}
				$levvalg.="</option>\n";
			}
		}

		if ( strlen($levvalg) == 0 ) {
			$levvalg="     <option disabled='disabled'>Ingen at vælge</option>\n";
			$lev_findes=0;
		} else {
			$lev_findes=1;
		}
		print $levvalg;
		print "</select></td>\n"; # 20160226d

		$title=findtekst(1322,$sprog_id);
		print "<td title='$title'><input class='inputbox' type='text' size='24' name='prisfil[$x]' value='".$prisfil[$x]."' /></td>\n";
		$title=findtekst(1323,$sprog_id);;
		print "<td title='$title'><!--tekst 432--><select class='inputbox' name='filtype[$x]'>\n";
		$filtypevalg="";
		for ($y=1;$y<=$filtyperantal;$y++) { # 20150529
			if ($filtyper[$y]==$filtype[$x]) {
				$filtypevalg.="<option value='$filtyper[$y]' title='$filtypebeskrivelse[$y]'>$filtyper[$y]</option>\n";
			}
		}
		for ($y=1;$y<=$filtyperantal;$y++) {
			if ($filtyper[$y]!=$filtype[$x]) {
				$filtypevalg.="<option value='$filtyper[$y]' title='$filtypebeskrivelse[$y]'>$filtyper[$y]</option>\n";
			}
		}
		print $filtypevalg;
		print "</select></td>\n";
		$title=str_replace('$beskrivelse',$beskrivelse[$x],findtekst(431,$sprog_id));
		print "<td title='$title'><!--tekst 431--><input class='inputbox' style='width:25px;text-align:right' type='text' name='rabat[$x]' value='$rabat[$x]'>%</td>\n";
		$title=str_replace('$beskrivelse',$beskrivelse[$x],findtekst(432,$sprog_id));
		print "<td title='$title'><!--tekst 432--><select class='inputbox' name='gruppe[$x]'>\n";
		for ($y=1;$y<=$vgrpantal;$y++) {
			if ($vgrp[$y]==$gruppe[$x]) print "<option value='$vgrp[$y]'>$vgrp[$y]: $vgbesk[$y]</option>\n";
		}
		for ($y=1;$y<=$vgrpantal;$y++) {
			if ($vgrp[$y]!=$gruppe[$x]) print "<option value='$vgrp[$y]'>$vgrp[$y]: $vgbesk[$y]</option>\n";
		}
		print "</select></td>\n";
		if ($aktiv[$x]) {
			if ( $lev_findes ) { # 20160226b start
				$aktiv[$x]="checked ";
			} else {
				$aktiv[$x]="disabled='disabled' ";
			}
			$slet[$x]="disabled";
			$title=findtekst(426,$sprog_id);
			print "<td title='$title'><!--tekst 426--><a href='lev_rabat.php?id=$id[$x]&amp;lev_id=$lev_id[$x]&amp;prisliste=$beskrivelse[$x]'>".findtekst(1321,$sprog_id)."</a></td>\n";
			print "<td>\n";
			print "    <input class='inputbox' type='checkbox' name='aktiv[$x]' $aktiv[$x] \n"; # 20150424
			print "        title='".str_replace('$beskrivelse',$beskrivelse[$x],findtekst(425,$sprog_id))."'><!--tekst 425-->&nbsp;\n";
			print "</td>\n<td><input type='checkbox' value='0' name='slet[$x]' $slet[$x] \n";
			print "        title='".findtekst(1324,$sprog_id)."'>\n";
		} else {
			print "<td>-</td>\n";
			print "<td>\n";
			print "    <input class='inputbox' type='checkbox' name='aktiv[$x]' "; # 20150424 20160226
			if ( $lev_findes && $lev_id[$x] ) { # 20160226e start
				print "\n        title='".str_replace('$beskrivelse',$beskrivelse[$x],findtekst(425,$sprog_id))."'><!--tekst 425-->&nbsp;\n"; # 20160226e slut
			} else {
				print "disabled='disabled' \n";
				print "\n        title='".findtekst(1325,$sprog_id)."'>\n"; # 20160226b slut
			}
			print "</td>\n<td><input type='checkbox' value='Slet' name='slet[$x]' \n";
			print "        title='".findtekst(1326,$sprog_id)."'>\n";
		}
		print "</td>\n</tr>\n";
	}
#	print "<input type='hidden' name='aktiv[$x]' value='on'>\n"; # 20160226f
	print "<input type='hidden' name='antal' value='$x'>\n";
	print "<tr>\n";
	print "<td><input class='inputbox' type='text' size='20' name='beskrivelse[$x]' title='Nummer $x'></td>\n";
	$title="".findtekst(1327,$sprog_id)."";
	print "<td title='$title'><select class='inputbox' type='text' name='lev_id[$x]' />\n";
	$levvalg="";
	$q3 = db_select("select id, kontonr, firmanavn from adresser where art = 'K' order by firmanavn",__FILE__ . " linje " . __LINE__);
	while ($levrk = db_fetch_array($q3)) {
#		if ( $levrk['id'] != $lev_id[$x] ) {
			$levvalg.="    <option value='".$levrk['id']."' title='".$levrk['firmanavn']."'>";
			if ( strlen($levrk['firmanavn'])>20 ) {
				$levvalg.=substr($levrk['firmanavn'],0,20)."...";
			} else {
				$levvalg.=$levrk['firmanavn'];
			}
			$levvalg.="</option>\n";
#		}
	}

	if ( strlen($levvalg) == 0 ) {
		$levvalg="     <option disabled='disabled' title='".findtekst(1328,$sprog_id)."</option>\n";
		$lev_findes=0;
	} else {
		$lev_findes=1;
	}
	print $levvalg;
	print "</select></td>\n";

	print "<td><input class='inputbox' type='text' size='24' name='prisfil[$x]'></td>\n";
	$title="".findtekst(1323,$sprog_id)."";
	print "<td title='$title'><!--tekst 432--><select class='inputbox' name='filtype[$x]'>\n";
	$filtypevalg="";
	for ($y=1;$y<=$filtyperantal;$y++) { # 20150529
		if (isset($filtype[$x]) && $filtyper[$y] == $filtype[$x]) {
			$filtypevalg.="<option value='$filtyper[$y]' title='$filtypebeskrivelse[$y]'>$filtyper[$y]</option>\n";
		}
	}
	for ($y=1;$y<=$filtyperantal;$y++) {
		if (!isset($filtype[$y]) || $filtyper[$y] != $filtype[$x]) {
			$filtypevalg.="<option value='$filtyper[$y]' title='$filtypebeskrivelse[$y]'>$filtyper[$y]</option>\n";
		}
	}
	print $filtypevalg;
	print "</select></td>\n";
	print "<td title='".str_replace(' $beskrivelse','', findtekst(431,$sprog_id))." ".findtekst(1329,$sprog_id)."'><!--tekst 431-->\n";
	print "    <input class='inputbox' style='width:25px;text-align:right' type='text' name='rabat[$x]' min='0' max='100' value='0'>%</td>\n";
	print "<td title='".str_replace(' $beskrivelse','', findtekst(432,$sprog_id))." ".findtekst(1329,$sprog_id)."'><!--tekst 432-->\n";
	print "    <select class='inputbox' name='gruppe[$x]'>\n";
	for ($y=1;$y<=$vgrpantal;$y++) {
		print "    <option value='$vgrp[$y]'";
		if ( $y == 1 ) print " selected='selected'";
		print ">$vgrp[$y]: $vgbesk[$y]</option>\n";
	}
	print "<td \n";
	print "    title='".findtekst(1330,$sprog_id)."'>\n";
	print "    &nbsp;\n</td>\n";
	print "</tr>\n";
	print "<tr><td><br></td><td><br></td><td><br></td><td align='center'><input class='button green medium' type='submit' accesskey='g' value='".findtekst(471, $sprog_id)."' name='submit'></td></tr>\n";
	print "</form>\n\n";
} # endfunc prislister

function rykker_valg()
{
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$box1=$box2=$box3=$box4=$box5=$box6=$box7=$box8=$box9=NULL;

	$r = db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '4'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$box1=$r['box1'];
	$box2=$r['box2'];
	if ($r['box3']) $box3=$r['box3']*1;
	$box4=$r['box4'];
	if ($r['box5']) $box5=$r['box5']*1;
	if ($r['box6']) $box6=$r['box6']*1;
	if ($r['box7']) $box7=$r['box7']*1;
#	$box8=$r['box8']; Box 8 bruger til resistrering af sidst sendte reminder.
	$box9=$r['box9']; # Inkasso.
	if ($box9) {
		$qtxt="select kontonr from adresser where id='$box9'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$box9=$r['kontonr'];
	}
#	$box10=$r['box10'];

	$x=0;
	$q = db_select("select id,brugernavn from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$br_id[$x]=$r['id'];
		$br_navn[$x]=$r['brugernavn'];
		if ($box1==$br_id[$x]) $box1=$br_navn[$x];
	}
	$br_antal=$x;
/*
	if ($box3 || $box4) {
		if ($r=db_fetch_array(db_select("select beskrivelse from varer where varenr = '$box4'",__FILE__ . " linje " . __LINE__))) {
			$varetekst=htmlentities($r['beskrivelse']);
		} else print "<BODY onLoad=\"JavaScript:alert('Varenummer ikke gyldigt')\">";
	}
*/
	print "<form name='diverse action=diverse.php?sektion=rykker_valg' method='post'>\n";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b>".findtekst(793,$sprog_id)."</b></td></tr>\n";
	print "<input type='hidden' name=id value='$id'>\n";
	#Box1 Brugernavn for "rykkeransvarlig - Naar bruger logger ind adviseres hvis der skal rykkes - Hvis navn ikke angives adviseres alle..
	$title=""; # HERTIL
	print "<tr><td title='".findtekst(224,$sprog_id)."'>".findtekst(225,$sprog_id)."</td>\n"; #20210713
	print "<td title='".findtekst(224,$sprog_id)."'><select class='inputbox' name='box1' style='width:80px'>\n";
	if ($box1) print "    <option>$box1</option>\n";
	print "<option value=''>- Alle -</option>\n";
	for ($x=1;$x<=$br_antal;$x++){
		if ($br_navn[$x]!=$box1) print "<option>$br_navn[$x]</option>\n";
	}
	print "</select></td></tr>\n";
	#Box2 Mailadresse for rykkeransvarlig hvis angivet sendes email naar der skal rykkes. (Naar nogen logger ind - uanset hvem)
	print "<tr><td title='".findtekst(226,$sprog_id)."'>".findtekst(227,$sprog_id)."</td>\n";
	print "<td title='".findtekst(226,$sprog_id)."'><input class='inputbox' type='text' size='30' name='box2' value='$box2'></td></tr>\n"; # 20150625
	#Box4 Varenummer for rente
#	print "<tr><td title='".findtekst(230,$sprog_id)."'>".findtekst(231,$sprog_id)."</td><td><input class='inputbox' type=text size=15 name=box4 value='$box4'></td></tr>";
	#Box3 Rentesats % pr paabegyndt md.
#	print "<tr><td title='".findtekst(228,$sprog_id)."'>".findtekst(229,$sprog_id)."</td><td><input class='inputbox' type=text style='text-align:right' size=1 name=box3 value='$box3'> %</td></tr>";
	#Box5 Dage betalingsfrist skal vaere overskredet foer der rykkes.
	print "<tr><td title='".findtekst(232,$sprog_id)."'>".findtekst(233,$sprog_id)."</td>\n";
	print "<td><input class='inputbox' type='text' style='text-align:right' size='3' name='box5' value='$box5'> ".findtekst(1332,$sprog_id)."</td></tr>\n";
	#Box6 Dage fra rykker 1 til rykker 2
	print "<tr><td title='".findtekst(234,$sprog_id)."'>".findtekst(235,$sprog_id)." </td>\n";
	print "<td><input class='inputbox' type='text' style='text-align:right' size='3' name='box6' value='$box6'> ".findtekst(1332,$sprog_id)."</td></tr>\n";
	#Box7 Dage fra rykker 2 til rykker 3
	print "<tr><td title='".findtekst(236,$sprog_id)."'>".findtekst(237,$sprog_id)." </td>\n";
	print "<td><input class='inputbox' type='text' style='text-align:right' size='3' name='box7' value='$box7'> ".findtekst(1332,$sprog_id)."</td></tr>\n";
	print "<td colspan='3'>&nbsp;</td>\n";
	if (!strpos(findtekst(833,$sprog_id),'inkasso')) db_modify("delete from tekster where tekst_id='833' and sprog_id='$sprog_id'",__FILE__ . " linje " . __LINE__); #20211019
	if (!strpos(findtekst(834,$sprog_id),'udfylde')) db_modify("delete from tekster where tekst_id='834' and sprog_id='$sprog_id'",__FILE__ . " linje " . __LINE__);
	print "<tr><td title='".findtekst(834,$sprog_id)."'>".findtekst(833,$sprog_id)." </td>\n";
	print "<td><input class='inputbox' type='text' style='text-align:right;width=20px;' name='box9' value='$box9'></td></tr>\n";
	print "<td colspan='3'>&nbsp;</td>\n";
	print "<td align='center'><input class='button green medium' type='submit' accesskey='g' value='".findtekst(471, $sprog_id)."' name='submit'></td>\n";
	print "</form>\n";
} # endfunc rykker_valg


function tjekliste() {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$ret=if_isset($_GET['ret']);
	$id=array();
	$x=0;
	$q = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' order by fase",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$tjekpunkt[$x]=$r['tjekpunkt'];
		$fase[$x]=$r['fase']*1;
		$assign_id[$x]=$r['assign_id']*1;
		$punkt_id[$x]=0;
		$gruppe_id[$x]=0;
		$liste_id[$x]=$id[$x];
		$q2 = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '$id[$x]' order by tjekpunkt",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$x++;
			$max_gruppe=$x;
			$id[$x]=$r2['id'];
			$tjekpunkt[$x]=$r2['tjekpunkt'];
			$assign_id[$x]=$r2['assign_id']*1;
			$fase[$x]=$fase[$x-1];
			$punkt_id[$x]=0;
			$gruppe_id[$x]=$id[$x];
			$liste_id[$x]=$liste_id[$x-1];
#cho "select * from tjekliste where id !=$id[$x] and assign_to = 'sager' and assign_id = '$id[$x]' order by tjekpunkt<br>\n";
			$q3 = db_select("select * from tjekliste where id !=$id[$x] and assign_to = 'sager' and assign_id = '$id[$x]' order by tjekpunkt",__FILE__ . " linje " . __LINE__);
			while ($r3 = db_fetch_array($q3)) {
				$x++;
				$id[$x]=$r3['id'];
				$tjekpunkt[$x]=$r3['tjekpunkt'];
				$assign_id[$x]=$r3['assign_id']*1;
				$fase[$x]=$fase[$x-1];
				$punkt_id[$x]=$id[$x];
				$gruppe_id[$x]=$gruppe_id[$x-1];
				$liste_id[$x]=$liste_id[$x-1];
			}
		}
	}
	$fasenr=0;
	print "<form name='diverse' action='diverse.php?sektion=tjekliste' method='post'>\n";
	print "<tr><td colspan='6'><hr></td></tr>\n";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(796,$sprog_id)."</u></b></td></tr>\n";
	for ($x=1;$x<=count($id);$x++) {
		if (!isset($fase[$x-1]) || $fase[$x]!=$fase[$x-1]) $fasenr++;
		print "<input type='hidden' name='tjekantal' value='".count($id)."'>\n";
		print "<input type='hidden' name='id[$x]' value='$id[$x]'>\n";
		print "<input type='hidden' name='fase[$x]' value='$fase[$x]'>\n";
		print "<input type='hidden' name='tjekpunkt[$x]' value='$tjekpunkt[$x]'>\n";
		if ($fase[$x]!=$fasenr) db_modify("update tjekliste set fase='$fasenr' where id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
		if (!$gruppe_id[$x] && !$punkt_id[$x]) {
			print "<tr><td colspan='6'><hr></td></tr>\n";
			if ($ret==$id[$x]) print "<tr><td colspan='1'><big><b><input class='inputbox' type='text' name='tjekpunkt[$x]' size='20' value='$tjekpunkt[$x]'></b></big></td><td><input class='inputbox' type='text' name='ny_fase[$x]' style='text-align:right;width:20px' value='$fasenr'></td></tr>\n";
			else print "<tr><td colspan='1'><span title='".findtekst(1727, $sprog_id)."'><big><b><a href='../systemdata/diverse.php?sektion=tjekliste&ret=$id[$x]' style='text-decoration:none'>$tjekpunkt[$x]</a></b></big></td><td><input class='inputbox' type='text' name='ny_fase[$x]' style='text-align:right;width:20px' value='$fasenr'></span></td></tr>\n";
			$l_id=$id[$x];
		}
		if ($gruppe_id[$x] && !$punkt_id[$x]) {
			print "<input type='hidden' name='tjekgruppe[$x]' value='$id[$x]'>\n";
			if ($ret==$id[$x]) print "<tr><td title='$assign_id[$x]==$l_id'><b><input class='inputbox' type='text' name='tjekpunkt[$x]' size='20' value='$tjekpunkt[$x]'></b></td><td><input class='inputbox' type='checkbox' name='aktiv[$x]'></td></tr>\n";
			else print "<tr><td title='$assign_id[$x]==$l_id'><span title='".findtekst(1727, $sprog_id)."'><b><a href='../systemdata/diverse.php?sektion=tjekliste&ret=$id[$x]' style='text-decoration:none'>".$tjekpunkt[$x]."</a></b></td><td><input class='inputbox' type='checkbox' name='aktiv[$x]'></span></td></tr>\n";
		}
		if ($punkt_id[$x]) {
			print "<input type='hidden' name='tjekgruppe[$x]' value='$id[$x]'>\n";
			if ($ret==$id[$x]) print "<tr><td title='$assign_id[$x]==$l_id'><input class='inputbox' type='text' name='tjekpunkt[$x]' size='20' value='$tjekpunkt[$x]'></td><td><input class='inputbox' type='checkbox' name='aktiv[$x]'></td></tr>\n";
			else print "<tr><td title='$assign_id[$x]==$l_id'><span title='".findtekst(1727, $sprog_id)."'><a href='../systemdata/diverse.php?sektion=tjekliste&ret=$id[$x]' style='text-decoration:none'>".$tjekpunkt[$x]."</a></td><td><input class='inputbox' type='checkbox' name='aktiv[$x]'></span></td></tr>\n";
		}
		if ($gruppe_id[$x] && $gruppe_id[$x] != $gruppe_id[$x+1]) {
			print "<input type='hidden' name='fase[$x]' value='$fase[$x]'>\n";
			print "<input type='hidden' name='gruppe_id[$x]' value='$gruppe_id[$x]'>\n";
#				print "<input type='hidden' name='assign_id[$x]' value='$assign_id[$x]'>\n";
			print "<tr><td>Nyt tjek punkt</td><td><input class='inputbox' type='text' name='nyt_tjekpunkt[$x]' size='20' value=''></td></tr>\n";
		}
			if (!isset($liste_id[$x+1]) || $liste_id[$x] != $liste_id[$x+1]) {
			print "<input type='hidden' name='fase[$x]' value='$fase[$x]'>\n";
			print "<input type='hidden' name='liste_id[$x]' value='$liste_id[$x]'>\n";
#			print "<input type='hidden' name='liste_id[$x]' value='$assign_id[$x]'>\n";
			print "<tr><td colspan='6'></td></tr>\n";
			print "<tr><td><b>".findtekst(1334, $sprog_id)."</b></td><td><input class='inputbox' type='text' name='ny_tjekgruppe[$x]' size='20' value=''></td></tr>\n";
		}
	}
	print "<tr><td colspan='6'><hr></td></tr>\n";
#	$ny_fase=$fase[$x]+1;
	print "<input type='hidden' name='ret' value='$ret'>\n";
	print "<tr><td>".findtekst(1333, $sprog_id)."</td><td><input class='inputbox' type='text' name='ny_tjekliste' size='20' value=''></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<td><br></td><td><br></td><td><br></td><td align = 'center'><input class='button green medium' type='submit' accesskey='g' value='".findtekst(471, $sprog_id)."' name='submit'></td>\n";
	print "</form>\n";

} # endfunc tjeklister

function docubizz() {
	global $bgcolor, $bgcolor5, $popup, $sprog_id;

	?>
	<script Language="JavaScript">
	<!--
	function Form1_Validator(docubizz) {
		if (docubizz.box3.value != docubizz.pw2.value) {
		alert("".findtekst(1345, $sprog_id)."");
		docubizz.box3.focus();
		return (false);
		}
	}
	//--></script>

	<?php
	$q = db_select("select * from grupper where art = 'DocBiz'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$ftpsted=$r['box1'];
	$ftplogin=$r['box2'];
	$ftpkode=$r['box3'];
	$ftp_dnld_mappe=$r['box4'];
	$ftp_upld_mappe=$r['box5'];

	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b>DocuBizz</b></td></tr>\n";
	print "<tr><td colspan='6'><br></td></tr>\n";

	print "<form name='docubizz' action=diverse.php?sektion=docubizz method='post' onsubmit=\"return Form1_Validator(this)\">\n";
	print "<input type='hidden' name='id' value='$id'>\n";
	print "<tr><td>Navn eller IP-nummer p&aring; ftp-server</td>";
	print "<td colspan='2'><input class='inputbox' type='text' name='box1' size='25' value='$ftpsted'></td></tr>\n";
	print "<tr><td>Mappe til download p&aring; ftp-server</td>";
	print "<td colspan='2'><input class='inputbox' type='text' name='box4' size='25' value='$ftp_dnld_mappe'></td></tr>\n";
	print "<tr><td>Mappe til upload p&aring; ftp-server</td>";
	print "<td colspan='2'><input class='inputbox' type='text' name='box5' size='25' value='$ftp_upld_mappe'></td></tr>\n";
	print "<tr><td>Brugernavn p&aring; ftp-server</td>";
	print "<td colspan='2'><input class='inputbox' type='text' name='box2' size='25' value='$ftplogin'></td></tr>\n";
	print "<tr><td>Adgangskode til ftp-server</td>";
	print "<td colspan='2'><input class='inputbox' type='password' name='box3' size='25' value='$ftpkode'></td></tr>\n";
	print "<tr><td>Gentag adgangskode</td>";
	print "<td colspan='2'><input class='inputbox' type='password' name='pw2' size='25' value='$ftpkode'></td></tr>\n";
	print "<tr><td>&nbsp;</td></tr>\n";
	print "<tr><td>&nbsp;</td><td><br></td><td>&nbsp;</td>";
	print "<td align='center'><input class='button green medium' style='width:8em' type='submit' accesskey='g' value='".findtekst(471, $sprog_id)."' name='submit'></td><tr>\n";
	print "</form>\n\n";
	print "<form name='upload_dbz' action='diverse.php?sektion=upload_dbz' method='post'>\n";
	print "<tr><td>&nbsp;</td></tr>\n";
	print "<tr><td colspan='3'>Opdater Docubizz server</td>";
	print "<td align='center'><input style='width:8em' type='submit' accesskey='g' value='Send data' name='submit'></td><tr>\n";
	print "</form>\n\n";

} # endfunc docubizz

function bilag() {
	global $bgcolor,$bgcolor5, $db, $s_id, $sprog_id;
    $ftp_bilag_mappe = $ftp_dokument_mappe=$id=$internFTP=null; #20211019
	$onclick=$internFTP=$internFTP=$google_docs=null;
	?>
	<script Language="JavaScript">
	<!--
	function Form1_Validator(ftp) {
		if (ftp.box3.value != ftp.pw2.value) {
		$alert = findtekst(1345, $sprog_id)	;
		alert($alert);
		ftp.box3.focus();
		return (false);
		}
	}
	//--></script>

	<?php
	$externFTP=NULL;
	$storageType = if_isset($_POST['storageType']);
	
	$r=db_fetch_array(db_select("select * from grupper where art = 'bilag'",__FILE__ . " linje " . __LINE__));
	if($r){	 #20211019 This checks whether $r is true before assigning values to the variables ..it prevents Trying to access array offset on value of type bool in..error.
	    $id=$r['id'];
		$ftpsted=$r['box1'];
		$ftplogin=$r['box2'];
		$ftpkode='********';
		$ftp_bilag_mappe=$r['box4'];
		$ftp_dokument_mappe=$r['box5'];
		if ($r['box6']=='on') {
			$internFTP='checked';
		} else {
			$internFTP=NULL;
			if (!$ftpsted && !$ftplogin) {
				$ftpsted=NULL;
				$ftplogin=NULL;
				$ftp_bilag_mappe=NULL;
				$ftp_dokument_mappe=NULL;
				$externFTP=NULL;
			} else $externFTP='checked';
		}
	
		if ($storageType == 'externFTP') $externFTP='checked';
		if (!isset ($sprog_id)) $sprog_id = null;
		if (!isset ($onclick)) $onclick = null;
		if (!$ftp_bilag_mappe) $ftp_bilag_mappe='bilag';
		if (!$ftp_dokument_mappe) $ftp_dokument_mappe='dokumenter';
		($r['box7'])?$google_docs='checked':$google_docs=NULL;
	}
		print "<tr bgcolor='$bgcolor5'><td colspan='6'><b>".findtekst(797, $sprog_id)."</b></td></tr>\n";
		print "<tr><td colspan='6'><br>".findtekst(1335, $sprog_id)."</td></tr>\n";
#		print "<tr><td colspan='6'>".findtekst(1336, $sprog_id)."</td></tr>\n";
		print "<tr><td colspan='6'></td></tr>\n";
		print "<tr><td colspan='6'>".findtekst(1337, $sprog_id)."</td></tr>\n";
		print "<tr><td colspan='6'>".findtekst(1338, $sprog_id)."</td></tr>\n";
		print "<tr><td colspan='6'>".findtekst(1339, $sprog_id)." ";
		print "<a href='mailto:bilag_".$db."@".$_SERVER['SERVER_NAME']."'>";
		print "bilag_".$db."@".$_SERVER['SERVER_NAME']."</a>.</td></tr>\n";
		print "<tr><td colspan='6'>".findtekst(1340, $sprog_id)."</td></tr>\n";
		print "<tr><td colspan='6'>&nbsp;</td></tr>\n\n";
		print "<form name='ftp' action='diverse.php?sektion=bilag' method='post' onsubmit=\"return Form1_Validator(this)\">\n";
		print "<input type='hidden' name='id' value='$id'>\n";
		print "<tr><td>".findtekst(1341, $sprog_id)."</td><td><select name=\"storageType\">";
		if ($internFTP) print "<option value=\"internFTP\">".findtekst(1342, $sprog_id)."</option>";
		elseif ($externFTP) print "<option value=\"externFTP\">".findtekst(1343, $sprog_id)."</option>";
		else print "<option value=\"\">".findtekst(1344, $sprog_id)."</option>";
		if (!$internFTP) print "<option value=\"internFTP\">".findtekst(1342, $sprog_id)."</option>";
		if (!$externFTP) print "<option value=\"externFTP\">".findtekst(1343, $sprog_id)."</option>";
		if ($internFTP || $externFTP) print "<option value=\"\">".findtekst(1344, $sprog_id)."</option>";
		print "</select></td></tr>";
	/*
		if ($internFTP) $onclick=NULL;
		else $onclick="onclick=\"return confirm('Intern bilagsopbevaring koster kr. 30,- pr. md. pr. GB.')\"";
		print "<tr>\n<td title='".findtekst(212,$sprog_id)."'>".findtekst(211,$sprog_id)."</td>\n";
		print "<td colspan='2' title='".findtekst(212,$sprog_id)."'>";
		print "<input $onclick class='inputbox' type='checkbox' name='box6' $internFTP></td>\n</tr>\n";
	*/
		print "<tr>\n<td title='".findtekst(720,$sprog_id)."'>".findtekst(719,$sprog_id)."</td>\n";
		print "<td colspan='2' title='".findtekst(720,$sprog_id)."'>";
		print "<input $onclick class='inputbox' type='checkbox' name='box7' $google_docs></td>\n</tr>\n";
	
	if ($externFTP) {
		print "<tr>\n<td>".findtekst(1346,$sprog_id)."</td>\n";
		print "<td colspan='2'><input class='inputbox' type='text' name='box1' size='25' value='$ftpsted'></td>\n</tr>\n";
		print "<tr>\n<td>".findtekst(1347,$sprog_id)."</td>\n";
		print "<td colspan='2'><input class='inputbox' type='text' name='box2' size='25' value='$ftplogin'></td>\n</tr>\n";
		print "<tr>\n<td>".findtekst(1348,$sprog_id)."</td>\n";
		print "<td colspan='2'><input class='inputbox' type='password' name='box3' size='25' value='$ftpkode'></td>\n</tr>\n";
		print "<tr>\n<td>".findtekst(1349,$sprog_id)."</td>\n";
		print "<td colspan='2'><input class='inputbox' type='password' name='pw2' size='25' value='$ftpkode'></td>\n</tr>\n";
		print "<tr>\n<td>".findtekst(1350,$sprog_id)."</td>";
		print "<td colspan='2'><input class='inputbox' type='text' name='box4' size='25' value='$ftp_bilag_mappe'></td>\n</tr>\n";
		print "<tr>\n<td>".findtekst(1351,$sprog_id)."</td>\n";
		print "<td colspan='2'><input class='inputbox' type='text' name='box5' size='25' value='$ftp_dokument_mappe'></td>\n</tr>\n";
		print "<tr><td>&nbsp;</td></tr>\n";
	}
	print "<tr>\n<td colspan='3'>&nbsp;</td>\n";
	print "<td align='center'><input class='button green medium' style='width:8em' type='submit' accesskey='g' value='".findtekst(471, $sprog_id)."' name='submit'></td>\n<tr>\n";
	print "</form>\n\n";
} # endfunc bilag

function orediff($diffkto)
{
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$q = db_select("select * from grupper where art = 'OreDif'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$maxdiff=dkdecimal($r['box1']);
	if (!$diffkto) $diffkto=$r['box2'];

	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b>".findtekst(170,$sprog_id)."</b></td></tr>\n";
	print "<tr><td colspan='2'>&nbsp;</td></tr>\n";

	print "<form name='orediff' action='diverse.php?sektion=orediff' method='post' onsubmit=\"return Form1_Validator(this)\">\n";
	print "<input type='hidden' name='id' value='$id'>\n";
	print "<tr>\n<td title='".findtekst(171,$sprog_id)."'>".findtekst(172,$sprog_id)."</td>\n";
	print "<td colspan='1'><input class='inputbox' type='text' style='text-align:right' name='box1' size='3' value='$maxdiff'></td>\n</tr>\n";
	print "<tr>\n<td title='".findtekst(173,$sprog_id)."'>".findtekst(174,$sprog_id)."</td>\n";
	print "<td colspan='1'><input class='inputbox' type='text' style='text-align:right' name='box2' size='3' value='$diffkto'></td>\n</tr>\n";
	print "<tr><td colspan='1'>&nbsp;</td>\n";
	print "<td align='center'><input class='button green medium' style='width:8em' type='submit' accesskey='g' value='".findtekst(471, $sprog_id)."' name='submit'></td>\n<tr>\n";
	print "</form>\n\n";
} # endfunc orediff.

function massefakt () {
	global $sprog_id;
	global $docubizz;
	global $bgcolor;
	global $bgcolor5;

	$id=$levfrist=0;
	$batch=$brug_dellev=$brug_mfakt=$folge_s_tekst=$gruppevalg=$kua=$kuansvalg=$ref=$smart=NULL;

	$q = db_select("select * from grupper where art = 'MFAKT' and kodenr = '1'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$id=$r['id'];
		if ($r['box1'] == 'on') $brug_mfakt='checked';
		if ($r['box2'] == 'on') $brug_dellev='checked';
		$levfrist=$r['box3'];
		if (!$levfrist) $levfrist = 0;
	}
	print "<form name='diverse' action='diverse.php?sektion=massefakt' method='post'>\n";
	print "<tr bgcolor='$bgcolor5'><td colspan='2'><b>".findtekst(200,$sprog_id)."</b></td></tr>\n";
	print "<tr><td colspan='6'>&nbsp;</td></tr>\n";
	print "<input name='id' type='hidden' value='$id'>\n";
	print "<tr>\n<td title='".findtekst(202,$sprog_id)."'>".findtekst(201,$sprog_id)."</td>\n";
	print "<td><input name='brug_mfakt' class='inputbox' type='checkbox' $brug_mfakt></td>\n</tr>\n";
	print "<tr>\n<td title='".findtekst(204,$sprog_id)."'>".findtekst(203,$sprog_id)."</td>\n";
	print "<td><input name='brug_dellev' class='inputbox' type='checkbox' $brug_dellev></td>\n</tr>\n";
	print "<tr>\n<td title='".findtekst(206,$sprog_id)."'>".findtekst(205,$sprog_id)."</td>\n";
	print "<td><input name='levfrist' class='inputbox' type='text' style='text-align:right' size='3' value='$levfrist'></td>\n</tr>\n";
	print "<tr>\n<td>&nbsp;</td>\n";
	print "<td style='text-align:center'><input class='button green medium' name='submit' type='submit' accesskey='g' value='".findtekst(471, $sprog_id)."'></td>\n</tr>\n";
	print "</form>\n\n";
} # endfunc massefakt
#####################################################
function testftp($box1,$box2,$box3,$box4,$box5,$box6) {
 	global $db, $exec_path, $sprog_id;
	if (!$exec_path) $exec_path="\usr\bin";

	if ($box6) {
		$fp=fopen("../temp/$db/ftpscript1","w");
		if ($fp) {
			fwrite ($fp,"set confirm-close no\nmkdir ".$_SERVER['SERVER_NAME']."\ncd ".$_SERVER['SERVER_NAME']."\nmkdir $db\nbye\n");
		}
		fclose($fp);

		$tmp=$_SERVER['SERVER_NAME']."/";
		$tmp=str_replace($tmp,'',$box1);
		$tmp1=$db."/";
		$tmp=str_replace($tmp1,'',$tmp);
		$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$tmp." < ftpscript1 > ftplog1\nrm testfil.txt\n";
		system ($kommando);
	}
	$fp=fopen("../temp/$db/testfil.txt","w");
	if ($fp) {
		fwrite ($fp,"testfil fra saldi\n");
	}
	fclose($fp);
	$fp=fopen("../temp/$db/ftpscript2","w");
	if ($fp) {
		fwrite ($fp, "mkdir $box4\nmkdir $box5\ncd $box4\nput testfil.txt\nbye\n");
	}
	fclose($fp);
	$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":'".$box3."'@".$box1." < ftpscript2 > ftplog2\nrm testfil.txt\n"; #rm testfil.txt\n
	system ($kommando);
	$fp=fopen("../temp/$db/ftpscript3","w");
	if ($fp) {
		fwrite ($fp, "get testfil.txt\ndel testfil.txt\nbye\n");
	}
	fclose($fp);
	$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":'".$box3."'@".$box1."/".$box4." < ftpscript3 > ftplog3\n"; #rm ftpscript\nrm ftplog\n";
	system ($kommando);
	($box6)?$tmp="Dokumentserver":$tmp="FTP";
      $alert = findtekst(1733, $sprog_id);
	  $alert1 = findtekst(1734, $sprog_id);

	if (file_exists("../temp/$db/testfil.txt")) print "<BODY onLoad=\"JavaScript:alert('$tmp $alert')\">";
	else print "<BODY onLoad=\"JavaScript:alert('$tmp $alert1 $alert')\">";
}




?>
